<?php
/**
 * UniVoucher Cart Limits
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Cart
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Cart_Limits class.
 */
class UniVoucher_WC_Cart_Limits {

	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Cart_Limits Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Only initialize hooks if on-demand order limits are enabled
		if ( get_option( 'univoucher_wc_on_demand_order_limit', true ) ) {
			// Hook into order creation
			add_action( 'woocommerce_checkout_create_order', array( $this, 'handle_checkout_create_order' ), 10, 2 );
			
			// Only initialize cart hooks if on-demand cart limits are enabled (and order limits are enabled)
			if ( get_option( 'univoucher_wc_on_demand_cart_limit', true ) ) {
				// Hook into WooCommerce's core stock calculation (for cart/checkout)
				add_filter( 'woocommerce_store_api_product_quantity_limit', array( $this, 'modify_product_quantity_limit' ), 10, 2 );
				// Hook into quantity input max (for single product pages)
				add_filter( 'woocommerce_quantity_input_max', array( $this, 'modify_quantity_input_max' ), 10, 2 );
				// Validate add to cart
				add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
			}
		}
	}

	/**
	 * Modify the product quantity limit to include on-demand capacity.
	 * 
	 * This hook is called by WooCommerce's Store API when calculating the maximum
	 * quantity allowed for a product. For UniVoucher products with backorder enabled,
	 * we add the on-demand limit to the remaining stock.
	 *
	 * @param int $limit The current quantity limit.
	 * @param WC_Product $product The product instance.
	 * @return int The modified quantity limit.
	 */
	public function modify_product_quantity_limit( $limit, $product ) {
		// Check if this is a UniVoucher product
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product ) ) {
			return $limit;
		}

		// Check if backorder is enabled
		if ( ! $product->backorders_allowed() ) {
			return $limit;
		}

		// Get the on-demand limit
		require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
		$on_demand_limit = uv_get_on_demand_limit( $product->get_id() );

		if ( is_wp_error( $on_demand_limit ) ) {
			// If there's an error with on-demand, return the original limit
			return $limit;
		}

		// Get the remaining stock (this is what WooCommerce would normally use)
		$remaining_stock = $product->get_stock_quantity();
		if ( $remaining_stock === null ) {
			$remaining_stock = 0;
		}

		// Return remaining stock + on-demand limit
		return $remaining_stock + $on_demand_limit;
	}

	/**
	 * Modify the quantity input max value for single product pages.
	 * 
	 * This hook is called by WooCommerce when displaying the quantity input
	 * on single product pages. We add the on-demand limit to the max quantity
	 * for UniVoucher products with backorder enabled.
	 *
	 * @param int $max_value The current maximum quantity value.
	 * @param WC_Product $product The product instance.
	 * @return int The modified maximum quantity value.
	 */
	public function modify_quantity_input_max( $max_value, $product ) {
		// Check if this is a UniVoucher product
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product ) ) {
			return $max_value;
		}

		// Check if product is sold individually
		if ( $product->is_sold_individually() ) {
			return $max_value;
		}

		// Check if backorder is enabled
		if ( ! $product->backorders_allowed() ) {
			return $max_value;
		}

		// Get the on-demand limit
		require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
		$on_demand_limit = uv_get_on_demand_limit( $product->get_id() );

		if ( is_wp_error( $on_demand_limit ) ) {
			// If there's an error with on-demand, return the original max value
			return $max_value;
		}

		// Get the remaining stock
		$remaining_stock = $product->get_stock_quantity();
		if ( $remaining_stock === null ) {
			$remaining_stock = 0;
		}

		// Return remaining stock + on-demand limit
		return $remaining_stock + $on_demand_limit;
	}

	/**
	 * Validate add to cart for UniVoucher products.
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		// Early return if validation already failed
		if ( ! $passed ) {
			return $passed;
		}

		// Ensure we have valid parameters
		if ( ! $product_id || ! is_numeric( $product_id ) || ! $quantity || ! is_numeric( $quantity ) ) {
			return $passed;
		}
		
		// Check if this is a UniVoucher product
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			return $passed;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $passed;
		}

		// Check if backorder is enabled
		if ( ! $product->backorders_allowed() ) {
			return $passed;
		}

		// Get the on-demand limit
		require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
		$on_demand_limit = uv_get_on_demand_limit( $product_id );

		if ( is_wp_error( $on_demand_limit ) ) {
			return $passed;
		}

		// Get remaining stock
		$remaining_stock = $product->get_stock_quantity();
		if ( $remaining_stock === null ) {
			$remaining_stock = 0;
		}

		// Get current cart quantity for this product
		$cart_quantity = 0;
		if ( WC()->cart && method_exists( WC()->cart, 'get_cart' ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					$cart_quantity += $cart_item['quantity'];
				}
			}
		}

		// Check if cart quantity + requested quantity exceeds available stock + on-demand limit
		$total_available = $remaining_stock + $on_demand_limit;
		$total_requested = $cart_quantity + $quantity;
		
		if ( $total_requested > $total_available ) {
			$error_message = get_option( 'univoucher_wc_on_demand_error_message', 'Sorry, but the maximum available quantity from {product_name} is {maximum_quantity}. You have {cart_quantity} in cart.' );
			$error_message = str_replace( 
				array( '{product_name}', '{maximum_quantity}', '{cart_quantity}' ),
				array( $product->get_name(), $total_available, $cart_quantity ),
				$error_message
			);
			wc_add_notice( 
				esc_html( $error_message ), 
				'error' 
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Handle checkout order creation for UniVoucher products.
	 * 
	 * This hook is called by WooCommerce when creating an order during checkout.
	 * For UniVoucher products with backorder enabled, we validate that the order
	 * quantities don't exceed the on-demand limits.
	 *
	 * @param WC_Order $order The order being created.
	 * @param array $data The checkout data.
	 */
	public function handle_checkout_create_order( $order, $data ) {
		// Check if order has items
		if ( ! $order->get_item_count() ) {
			return;
		}
		
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$product = wc_get_product( $product_id );
			
			if ( ! $product ) {
				continue;
			}

			// Check if this is a UniVoucher product
			if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product ) ) {
				continue;
			}

			// Check if backorder is enabled
			if ( ! $product->backorders_allowed() ) {
				continue;
			}

			// Get the on-demand limit with retry logic
			require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
			$on_demand_limit = uv_get_on_demand_limit( $product_id );

			if ( is_wp_error( $on_demand_limit ) ) {
				// Wait for 1 second and try again
				sleep( 1 );
				$on_demand_limit = uv_get_on_demand_limit( $product_id );
				
				if ( is_wp_error( $on_demand_limit ) ) {
					continue;
				}
			}

			// Get remaining stock
			$remaining_stock = $product->get_stock_quantity();
			if ( $remaining_stock === null ) {
				$remaining_stock = 0;
			}

			// Calculate total available
			$total_available = $remaining_stock + $on_demand_limit;
			$requested_quantity = $item->get_quantity();

			// Check if quantity exceeds available stock + on-demand limit
			if ( $requested_quantity > $total_available ) {
				// Prevent order creation by throwing an exception
				$error_message = get_option( 'univoucher_wc_on_demand_error_message', 'Sorry, but the maximum available quantity from {product_name} is {maximum_quantity}. You have {cart_quantity} in cart.' );
				$error_message = str_replace( 
					array( '{product_name}', '{maximum_quantity}', '{cart_quantity}' ),
					array( $product->get_name(), $total_available, $requested_quantity ),
					$error_message
				);
				throw new Exception( esc_html( $error_message ) );
			}
		}
	}
} 