<?php
/**
 * UniVoucher For WooCommerce Callback Manager
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Callback_Manager class.
 */
class UniVoucher_WC_Callback_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Callback_Manager
	 */
	protected static $_instance = null;

	/**
	 * Gift card manager instance.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	private $gift_card_manager;

	/**
	 * Main UniVoucher_WC_Callback_Manager Instance.
	 *
	 * @return UniVoucher_WC_Callback_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Callback_Manager Constructor.
	 */
	public function __construct() {
		$this->gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
	}

	/**
	 * Handle UniVoucher API callback.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function handle_univoucher_callback( $request ) {
		$body = $request->get_json_params();
		
		if ( ! $body || ! isset( $body['orderId'] ) || ! isset( $body['authToken'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid callback data' ), 400 );
		}

		$order_id = sanitize_text_field( $body['orderId'] );
		$auth_token = sanitize_text_field( $body['authToken'] );
		
		// Verify auth token
		$stored_auth_token = get_transient( 'univoucher_callback_auth_' . $order_id );
		if ( ! $stored_auth_token || $stored_auth_token !== $auth_token ) {
			return new WP_REST_Response( array( 'error' => 'Invalid auth token' ), 401 );
		}

		// Extract order details from order ID
		$order_id_parts = explode( '_', $order_id );
		if ( count( $order_id_parts ) < 4 ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order ID format' ), 400 );
		}

		$wc_order_id = intval( $order_id_parts[1] );
		$product_id = intval( $order_id_parts[2] );
		
		$order = wc_get_order( $wc_order_id );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Order not found' ), 404 );
		}

		// Check callback status
		$status = isset( $body['status'] ) ? $body['status'] : '';
		
		if ( $status === 'success' && isset( $body['cards'] ) ) {
			// Success - cards were created
			$this->handle_successful_card_creation( $order, $product_id, $body );
			
			// Clean up auth token
			delete_transient( 'univoucher_callback_auth_' . $order_id );
			
			return new WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			// Failure
			$error_message = isset( $body['error'] ) ? $body['error'] : 'Unknown error';
			$this->handle_failed_card_creation( $order, $product_id, $body );
			
			// Clean up auth token
			delete_transient( 'univoucher_callback_auth_' . $order_id );
			
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}
	}

	/**
	 * Handle successful card creation callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param int      $product_id Product ID.
	 * @param array    $callback_data Complete callback data.
	 */
	private function handle_successful_card_creation( $order, $product_id, $callback_data ) {
		$assigned_cards = array();
		$cards = $callback_data['cards'];
		
		// Check if order is completed
		$is_order_completed = $order->get_status() === 'completed';
		
		// Get product to retrieve token information
		$product = wc_get_product( $product_id );
		$token_symbol = $product ? $product->get_meta( '_univoucher_token_symbol' ) : '';
		$token_type = $product ? $product->get_meta( '_univoucher_token_type' ) : 'native';
		$token_decimals = $product ? $product->get_meta( '_univoucher_token_decimals' ) : 18;
		
		foreach ( $cards as $card ) {
			// Convert wei amount to decimal
			$amount_decimal = $this->wei_to_decimal( $card['amount'], $token_decimals );
			
			// Set delivery status based on order status
			$delivery_status = $is_order_completed ? 'delivered' : 'never delivered';
			
			// Prepare card data for gift card manager
			$card_data = array(
				'product_id'     => $product_id,
				'order_id'       => $order->get_id(),
				'status'         => 'sold',
				'delivery_status' => $delivery_status,
				'card_id'        => $card['cardId'],
				'card_secret'    => $card['cardSecret'],
				'chain_id'       => $callback_data['network'],
				'token_address'  => $card['tokenAddress'],
				'token_symbol'   => $token_symbol,
				'token_type'     => $token_type,
				'token_decimals' => $token_decimals,
				'amount'         => $amount_decimal,
				'created_at'     => current_time( 'mysql' ),
			);

			// Add card using gift card manager
			$result = $this->gift_card_manager->uv_add_gift_card( $card_data );
			
			if ( ! is_wp_error( $result ) ) {
				$assigned_cards[] = $card['cardId'];
			}
		}
		
		// Update order meta with product-specific key
		$order->update_meta_data( 'univoucher_backorder_status_' . $product_id, 'success' );
		$order->save();
		
		// Add order note with transaction details
		if ( ! empty( $assigned_cards ) ) {
			$card_ids_string = implode( ', ', $assigned_cards );
			$note_message = sprintf( 
				// translators: %1$d is the number of cards, %2$s is the card IDs
				__( 'UniVoucher: %1$d backordered cards created and assigned to order (IDs: %2$s)', 'univoucher-for-woocommerce' ),
				count( $assigned_cards ),
				$card_ids_string
			);
			
			// Add transaction hashes if available
			if ( ! empty( $callback_data['approvalTransactionHashes'] ) ) {
				$approval_hashes = implode( ', ', $callback_data['approvalTransactionHashes'] );
				$note_message .= sprintf( 
					// translators: %s is the approval transaction hashes
					__( ' - Approval TX: %s', 'univoucher-for-woocommerce' ),
					$approval_hashes
				);
			}
			
			if ( ! empty( $callback_data['creationTransactionHashes'] ) ) {
				$creation_hashes = implode( ', ', $callback_data['creationTransactionHashes'] );
				$note_message .= sprintf( 
					// translators: %s is the creation transaction hashes
					__( ' - Creation TX: %s', 'univoucher-for-woocommerce' ),
					$creation_hashes
				);
			}
			
			$order->add_order_note( $note_message );
		}
		
		// If order is completed, send gift cards email
		if ( $is_order_completed && ! empty( $assigned_cards ) ) {
			$order_manager = UniVoucher_WC_Order_Manager::instance();
			$order_manager->send_gift_cards_email( $order->get_id() );
		}
	}

	/**
	 * Handle failed card creation callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param int      $product_id Product ID.
	 * @param array    $callback_data Complete callback data.
	 */
	private function handle_failed_card_creation( $order, $product_id, $callback_data ) {
		// Update order meta with product-specific key
		$order->update_meta_data( 'univoucher_backorder_status_' . $product_id, 'failed' );
		$order->save();
		
		// Get error message
		$error_message = isset( $callback_data['error'] ) ? $callback_data['error'] : 'Unknown error';
		
		// Build order note with transaction details
		$note_message = sprintf( 
			// translators: %s is the error message
			__( 'UniVoucher: Backordered cards creation failed - %s', 'univoucher-for-woocommerce' ),
			$error_message
		);
		
		// Add transaction hashes if available (even for failures, some transactions might have been attempted)
		if ( ! empty( $callback_data['approvalTransactionHashes'] ) ) {
			$approval_hashes = implode( ', ', $callback_data['approvalTransactionHashes'] );
			$note_message .= sprintf( 
				// translators: %s is the approval transaction hashes
				__( ' - Approval TX: %s', 'univoucher-for-woocommerce' ),
				$approval_hashes
			);
		}
		
		if ( ! empty( $callback_data['creationTransactionHashes'] ) ) {
			$creation_hashes = implode( ', ', $callback_data['creationTransactionHashes'] );
			$note_message .= sprintf( 
				// translators: %s is the creation transaction hashes
				__( ' - Creation TX: %s', 'univoucher-for-woocommerce' ),
				$creation_hashes
			);
		}
		
		$order->add_order_note( $note_message );
	}

	/**
	 * Convert wei amount to decimal.
	 *
	 * @param string $wei_amount Amount in wei.
	 * @param int    $decimals Token decimals.
	 * @return string Decimal amount.
	 */
	private function wei_to_decimal( $wei_amount, $decimals ) {
		// Use bcmath for precision.
		if ( function_exists( 'bcdiv' ) ) {
			$divisor = bcpow( '10', $decimals );
			return rtrim( rtrim( bcdiv( $wei_amount, $divisor, $decimals ), '0' ), '.' );
		}

		// Fallback to regular division.
		$divisor = pow( 10, $decimals );
		return number_format( $wei_amount / $divisor, $decimals, '.', '' );
	}
} 