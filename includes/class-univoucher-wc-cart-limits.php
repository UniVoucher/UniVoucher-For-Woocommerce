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
		// Only initialize hooks if cart limits are enabled
		if ( get_option( 'univoucher_wc_enable_cart_limits', true ) ) {
			// Hook into WooCommerce's core stock calculation (for cart/checkout)
			add_filter( 'woocommerce_store_api_product_quantity_limit', array( $this, 'modify_product_quantity_limit' ), 10, 2 );
			// Hook into quantity input max (for single product pages)
			add_filter( 'woocommerce_quantity_input_max', array( $this, 'modify_quantity_input_max' ), 10, 2 );
			// Validate add to cart
			add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
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

		error_log( "UniVoucher: validate_add_to_cart called - product_id: $product_id, quantity: $quantity, variation_id: $variation_id" );
		
		// Check if this is a UniVoucher product
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			error_log( "UniVoucher: Not a UniVoucher product" );
			return $passed;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			error_log( "UniVoucher: Invalid product" );
			return $passed;
		}

		// Check if backorder is enabled
		if ( ! $product->backorders_allowed() ) {
			error_log( "UniVoucher: Backorder not allowed" );
			return $passed;
		}

		// Get the on-demand limit
		require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
		$on_demand_limit = uv_get_on_demand_limit( $product_id );

		if ( is_wp_error( $on_demand_limit ) ) {
			error_log( "UniVoucher: On-demand limit error: " . $on_demand_limit->get_error_message() );
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
		error_log( "UniVoucher: remaining_stock: $remaining_stock, on_demand_limit: $on_demand_limit, total_available: $total_available, cart_quantity: $cart_quantity, requested_quantity: $quantity, total_requested: $total_requested" );
		
		if ( $total_requested > $total_available ) {
			error_log( "UniVoucher: Total quantity exceeds limit - blocking add to cart" );
			wc_add_notice( 
				sprintf( 
					__( 'Maximum quantity allowed is %d. You already have %d in cart.', 'univoucher-for-woocommerce' ), 
					$total_available,
					$cart_quantity
				), 
				'error' 
			);
			return false;
		}

		error_log( "UniVoucher: Validation passed" );
		return $passed;
	}
} 