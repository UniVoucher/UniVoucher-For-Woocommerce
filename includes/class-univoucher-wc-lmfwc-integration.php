<?php
/**
 * UniVoucher For WooCommerce License Manager Integration
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Integration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_LMFWC_Integration class.
 */
class UniVoucher_WC_LMFWC_Integration {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_LMFWC_Integration
	 */
	protected static $_instance = null;

	/**
	 * Gift Card Manager instance.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	private $gift_card_manager;

	/**
	 * Main UniVoucher_WC_LMFWC_Integration Instance.
	 *
	 * @return UniVoucher_WC_LMFWC_Integration - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_LMFWC_Integration Constructor.
	 */
	public function __construct() {
		$this->gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Check if integration is enabled and LMFWC is active
		if ( ! get_option( 'univoucher_wc_lmfwc_integration', false ) || ! class_exists( 'LicenseManagerForWooCommerce\Main' ) ) {
			return;
		}

		// Set up order context before License Manager processes it (priority 5, before LMFWC's priority 10)
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'setup_order_context' ), 5, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'setup_order_context' ), 5, 4 );
		add_action( 'lmfwc_email_order_license_keys', array( $this, 'setup_order_context' ), 5, 4 );
		
		// Add filters for license manager integration
		add_filter( 'lmfwc_get_customer_license_keys', array( $this, 'add_univoucher_cards_to_license_keys' ), 10, 1 );
		add_filter( 'lmfwc_get_all_customer_license_keys', array( $this, 'add_univoucher_cards_to_all_license_keys' ), 10, 1 );
		
		// Ensure orders with UniVoucher cards are processed even when no license keys exist
		add_filter( 'lmfwc_get_customer_license_keys', array( $this, 'ensure_univoucher_orders_processed' ), 5, 1 );
		
		// Add filter to make LMFWC aware of UniVoucher cards for admin actions
		add_filter( 'woocommerce_order_actions', array( $this, 'ensure_send_license_keys_action' ), 5, 2 );
	}

	/**
	 * Set up order context for the integration.
	 *
	 * @param WC_Order $order The order object.
	 * @param bool     $isAdminEmail Whether this is an admin email (for email context).
	 * @param bool     $plainText Whether this is plain text email (for email context).
	 * @param WC_Email $email The email object (for email context).
	 */
	public function setup_order_context( $order, $isAdminEmail = false, $plainText = false, $email = null ) {
		// Store the order ID in a static property for use in the filter
		static $current_order_id = null;
		$current_order_id = $order->get_id();
		
		// Store in a global variable that our filter can access
		global $univoucher_current_order_id;
		$univoucher_current_order_id = $current_order_id;
		
		$context = $email ? 'email' : 'order_details';
	}

	/**
	 * Ensure orders with UniVoucher cards are processed even when no license keys exist.
	 *
	 * @param array $license_keys Array of license keys from the Controller.
	 * @return array Modified array to ensure processing continues.
	 */
	public function ensure_univoucher_orders_processed( $license_keys ) {
		global $univoucher_current_order_id;
		
		if ( ! $univoucher_current_order_id ) {
			return $license_keys;
		}
		
		// Check if this order has UniVoucher cards
		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $univoucher_current_order_id );
		
		if ( ! empty( $gift_cards ) ) {
			// If no license keys exist but we have UniVoucher cards, return a minimal structure
			// to ensure the License Manager continues processing
			if ( empty( $license_keys ) ) {
				$license_keys = array( 'univoucher_placeholder' => array( 'name' => 'UniVoucher Cards', 'keys' => array() ) );
			}
		}
		
		return $license_keys;
	}

	/**
	 * Add UniVoucher cards to customer license keys for a specific order.
	 *
	 * @param array $license_keys Array of license keys from the Controller.
	 * @return array Modified array with UniVoucher cards added.
	 */
	public function add_univoucher_cards_to_license_keys( $license_keys ) {
		// Get order ID from our setup context
		global $univoucher_current_order_id;
		if ( ! $univoucher_current_order_id ) {
			return $license_keys;
		}
		
		$order_id = $univoucher_current_order_id;
		
		// Check if this order has UniVoucher cards, even if no license keys exist
		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order_id );
		if ( empty( $gift_cards ) ) {
			return $license_keys;
		}
		
		// Get the order object
		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'has_status' ) ) {
			return $license_keys;
		}
		
		// Only proceed for completed orders
		if ( ! $order->has_status( 'completed' ) ) {
			return $license_keys;
		}

		// Add UniVoucher cards to the existing license keys structure
		foreach ( $gift_cards as $card ) {
			$product_id = $card->product_id;
			
			// Initialize product section if it doesn't exist
			if ( ! isset( $license_keys[ $product_id ] ) ) {
				$product = wc_get_product( $product_id );
				$license_keys[ $product_id ] = array(
					'name' => $product ? $product->get_name() : 'Product #' . $product_id,
					'keys' => array()
				);
			}
			
			// Remove placeholder if it exists
			if ( isset( $license_keys['univoucher_placeholder'] ) ) {
				unset( $license_keys['univoucher_placeholder'] );
			}
			
			// Get the license key template from settings
			$template = get_option( 'univoucher_wc_lmfwc_license_key_template', 'Card ID: {card_id} Card Secret: {card_secret} Network: {card_network} Abandoned on {card_abandoned}' );
			
			// Get network name
			$networks = array(1=>'Ethereum',10=>'Optimism',56=>'BNB Chain',137=>'Polygon',42161=>'Arbitrum',43114=>'Avalanche',8453=>'Base');
			$network_name = isset( $networks[ $card->chain_id ] ) ? $networks[ $card->chain_id ] : 'Chain ' . $card->chain_id;
			
			// Calculate abandoned date (5 years from creation)
			$creation_date = new DateTime( $card->created_at );
			$abandoned_date = $creation_date->modify( '+5 years' );
			$abandoned_date_formatted = $abandoned_date->format( 'Y-m-d' );
			
			// Replace placeholders in template
			$license_key_text = str_replace(
				array( '{card_id}', '{card_secret}', '{card_network}', '{card_abandoned}' ),
				array( $card->card_id, $card->card_secret, $network_name, $abandoned_date_formatted ),
				$template
			);
			
			// Create a license key object that matches the expected structure
			$license_key_obj = new stdClass();
			$license_key_obj->id = 'univoucher_' . $card->id;
			$license_key_obj->order_id = $order->get_id();
			$license_key_obj->product_id = $card->product_id;
			$license_key_obj->user_id = get_current_user_id();
			$license_key_obj->license_key = apply_filters( 'lmfwc_encrypt', $license_key_text );
			$license_key_obj->hash = apply_filters( 'lmfwc_hash', $license_key_text );
			
			// Set expiration date based on settings
			$show_abandoned_date = get_option( 'univoucher_wc_lmfwc_show_abandoned_date', true );
			if ( $show_abandoned_date ) {
				// Set expiration date to 5 years from card creation date
				$creation_date = new DateTime( $card->created_at );
				$expiration_date = $creation_date->modify( '+5 years' );
				$license_key_obj->expires_at = $expiration_date->format( 'Y-m-d H:i:s' );
			} else {
				$license_key_obj->expires_at = null;
			}
			$license_key_obj->valid_for = null;
			$license_key_obj->source = 2; // Same as other licenses
			$license_key_obj->status = 2; // Same as other licenses
			$license_key_obj->times_activated = null;
			$license_key_obj->times_activated_max = null;
			$license_key_obj->created_at = $card->created_at;
			$license_key_obj->created_by = get_current_user_id();
			$license_key_obj->updated_at = !empty( $card->updated_at ) ? $card->updated_at : $card->created_at; // Use created_at if updated_at is empty
			$license_key_obj->updated_by = get_current_user_id();
			
			// Create a proper License object
			$license_obj = new \LicenseManagerForWooCommerce\Models\Resources\License( $license_key_obj );
			
			$license_keys[ $product_id ]['keys'][] = $license_obj;
		}

		return $license_keys;
	}

	/**
	 * Add UniVoucher cards to all customer license keys.
	 *
	 * @param array $license_keys Array of license keys.
	 * @return array Modified array with UniVoucher cards added.
	 */
	public function add_univoucher_cards_to_all_license_keys( $license_keys ) {
		// Check if include in all licenses is enabled
		$include_in_all_licenses = get_option( 'univoucher_wc_lmfwc_include_in_all_licenses', true );
		if ( ! $include_in_all_licenses ) {
			return $license_keys;
		}
		
		// Get current user ID
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return $license_keys;
		}

		// Get all completed orders for this user
		$orders = wc_get_orders( array(
			'customer_id' => $user_id,
			'status'      => 'completed',
			'limit'       => -1,
		) );

		// Add UniVoucher cards to the existing license keys structure
		foreach ( $orders as $order ) {
			$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order->get_id() );
			
			if ( empty( $gift_cards ) ) {
				continue;
			}

			foreach ( $gift_cards as $card ) {
				$product_id = $card->product_id;
				
				// Initialize product section if it doesn't exist
				if ( ! isset( $license_keys[ $product_id ] ) ) {
					$product = wc_get_product( $product_id );
					$license_keys[ $product_id ] = array(
						'name' => $product ? $product->get_name() : 'Product #' . $product_id,
						'licenses' => array()
					);
				}
				
				// Get the license key template from settings
				$template = get_option( 'univoucher_wc_lmfwc_license_key_template', 'Card ID: {card_id} Card Secret: {card_secret} Network: {card_network} Abandoned on {card_abandoned}' );
				
				// Get network name
				$networks = array(1=>'Ethereum',10=>'Optimism',56=>'BNB Chain',137=>'Polygon',42161=>'Arbitrum',43114=>'Avalanche',8453=>'Base');
				$network_name = isset( $networks[ $card->chain_id ] ) ? $networks[ $card->chain_id ] : 'Chain ' . $card->chain_id;
				
				// Calculate abandoned date (5 years from creation)
				$creation_date = new DateTime( $card->created_at );
				$abandoned_date = $creation_date->modify( '+5 years' );
				$abandoned_date_formatted = $abandoned_date->format( 'Y-m-d' );
				
				// Replace placeholders in template
				$license_key_text = str_replace(
					array( '{card_id}', '{card_secret}', '{card_network}', '{card_abandoned}' ),
					array( $card->card_id, $card->card_secret, $network_name, $abandoned_date_formatted ),
					$template
				);
				
				// Create a license key object that matches the expected structure
				$license_key_obj = new stdClass();
				$license_key_obj->id = 'univoucher_' . $card->id;
				$license_key_obj->order_id = $order->get_id();
				$license_key_obj->product_id = $card->product_id;
				$license_key_obj->user_id = get_current_user_id();
				$license_key_obj->license_key = apply_filters( 'lmfwc_encrypt', $license_key_text );
				$license_key_obj->hash = apply_filters( 'lmfwc_hash', $license_key_text );
				
				// Set expiration date based on settings
				$show_abandoned_date = get_option( 'univoucher_wc_lmfwc_show_abandoned_date', true );
				if ( $show_abandoned_date ) {
					// Set expiration date to 5 years from card creation date
					$creation_date = new DateTime( $card->created_at );
					$expiration_date = $creation_date->modify( '+5 years' );
					$license_key_obj->expires_at = $expiration_date->format( 'Y-m-d H:i:s' );
				} else {
					$license_key_obj->expires_at = null;
				}
				$license_key_obj->valid_for = null;
				$license_key_obj->source = 2; // Same as other licenses
				$license_key_obj->status = 2; // Same as other licenses
				$license_key_obj->times_activated = null;
				$license_key_obj->times_activated_max = null;
				$license_key_obj->created_at = $card->created_at;
				$license_key_obj->created_by = get_current_user_id();
				$license_key_obj->updated_at = !empty( $card->updated_at ) ? $card->updated_at : $card->created_at; // Use created_at if updated_at is empty
				$license_key_obj->updated_by = get_current_user_id();
				
				// Create a proper License object
				$license_obj = new \LicenseManagerForWooCommerce\Models\Resources\License( $license_key_obj );
				
				$license_keys[ $product_id ]['licenses'][] = $license_obj;
			}
		}

		return $license_keys;
	}

	/**
	 * Ensure the "Send license key(s) to customer" action appears for orders with UniVoucher cards.
	 *
	 * @param array $actions Array of order actions.
	 * @param WC_Order $order The order object.
	 * @return array Modified actions array.
	 */
	public function ensure_send_license_keys_action( $actions, $order ) {
		// Check if this order has UniVoucher cards
		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order->get_id() );
		
		if ( ! empty( $gift_cards ) ) {
			// Add the action if it doesn't already exist
			if ( ! isset( $actions['lmfwc_send_license_keys'] ) ) {
				$actions['lmfwc_send_license_keys'] = __( 'Send license key(s) to customer', 'license-manager-for-woocommerce' );
			}
		}
		
		return $actions;
	}
} 