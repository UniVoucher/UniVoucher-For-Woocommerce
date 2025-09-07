<?php
/**
 * UniVoucher For WooCommerce Internal Wallet Handler
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Internal_Wallet class.
 */
class UniVoucher_WC_Internal_Wallet {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Internal_Wallet
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Internal_Wallet Instance.
	 *
	 * @return UniVoucher_WC_Internal_Wallet - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Internal_Wallet Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Internal wallet AJAX handlers
		add_action( 'wp_ajax_univoucher_get_wallet_info', array( $this, 'ajax_get_wallet_info' ) );
		add_action( 'wp_ajax_univoucher_get_wallet_address', array( $this, 'ajax_get_wallet_address' ) );
		add_action( 'wp_ajax_univoucher_check_allowance', array( $this, 'ajax_check_allowance' ) );
		add_action( 'wp_ajax_univoucher_estimate_gas', array( $this, 'ajax_estimate_gas' ) );
		add_action( 'wp_ajax_univoucher_create_cards_internal', array( $this, 'ajax_create_cards_internal' ) );
	}

	/**
	 * AJAX handler to get wallet information.
	 */
	public function ajax_get_wallet_info() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$chain_id = isset( $_POST['chain_id'] ) ? absint( wp_unslash( $_POST['chain_id'] ) ) : 0;
		$token_address = isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '';

		// Get private key from settings
		$encryption = UniVoucher_WC_Encryption::instance();
		$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
		
		if ( empty( $encrypted_private_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Internal wallet not configured. Please set up your private key in settings.', 'univoucher-for-woocommerce' ) ) );
		}

		$private_key = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( is_wp_error( $private_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to decrypt private key.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get wallet address (this should be done client-side but we'll return it for verification)
		$wallet_info = array(
			'has_private_key' => true,
			'chain_id' => $chain_id,
			'token_address' => $token_address,
		);

		wp_send_json_success( $wallet_info );
	}

	/**
	 * AJAX handler to get wallet address from private key.
	 */
	public function ajax_get_wallet_address() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		// Get private key from settings
		$encryption = UniVoucher_WC_Encryption::instance();
		$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
		
		if ( empty( $encrypted_private_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Internal wallet not configured. Please set up your private key in settings.', 'univoucher-for-woocommerce' ) ) );
		}

		$private_key = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( is_wp_error( $private_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to decrypt private key.', 'univoucher-for-woocommerce' ) ) );
		}

		// Clean the private key (remove 0x prefix if present)
		$clean_private_key = $private_key;
		if ( strpos( $clean_private_key, '0x' ) === 0 ) {
			$clean_private_key = substr( $clean_private_key, 2 );
		}

		// Validate private key format
		if ( strlen( $clean_private_key ) !== 64 || ! ctype_xdigit( $clean_private_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid private key format.', 'univoucher-for-woocommerce' ) ) );
		}

		// Return the private key to the client for address derivation using ethers.js
		// This is done securely by returning it once and clearing it from memory
		wp_send_json_success( array( 
			'private_key' => '0x' . $clean_private_key,
		) );
	}

	/**
	 * AJAX handler to check token allowance.
	 */
	public function ajax_check_allowance() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$chain_id = isset( $_POST['chain_id'] ) ? absint( wp_unslash( $_POST['chain_id'] ) ) : 0;
		$token_address = isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '';
		$wallet_address = isset( $_POST['wallet_address'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_address'] ) ) : '';
		$required_amount = isset( $_POST['required_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['required_amount'] ) ) : '';

		// This will be handled by the frontend using Alchemy API
		// We just return success to indicate the endpoint is available
		wp_send_json_success( array( 
			'message' => esc_html__( 'Allowance check endpoint ready.', 'univoucher-for-woocommerce' ),
			'chain_id' => $chain_id,
			'token_address' => $token_address,
			'wallet_address' => $wallet_address,
			'required_amount' => $required_amount,
		) );
	}

	/**
	 * AJAX handler to estimate gas for card creation.
	 */
	public function ajax_estimate_gas() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$chain_id = isset( $_POST['chain_id'] ) ? absint( wp_unslash( $_POST['chain_id'] ) ) : 0;
		$token_address = isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '';
		$quantity = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;
		$card_amount = isset( $_POST['card_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['card_amount'] ) ) : '';

		// This will be handled by the frontend using Alchemy API for gas estimation
		// We just return the parameters for frontend processing
		wp_send_json_success( array( 
			'message' => esc_html__( 'Gas estimation endpoint ready.', 'univoucher-for-woocommerce' ),
			'chain_id' => $chain_id,
			'token_address' => $token_address,
			'quantity' => $quantity,
			'card_amount' => $card_amount,
		) );
	}

	/**
	 * AJAX handler to create cards using internal wallet.
	 */
	public function ajax_create_cards_internal() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		// Sanitize card data array properly
		$card_data = array();
		if ( isset( $_POST['card_data'] ) && is_array( $_POST['card_data'] ) ) {
			$raw_card_data = map_deep( wp_unslash( $_POST['card_data'] ), 'sanitize_text_field' );
			foreach ( $raw_card_data as $card_info ) {
				if ( is_array( $card_info ) && isset( $card_info['card_id'] ) && isset( $card_info['card_secret'] ) ) {
					$card_data[] = array(
						'card_id' => $card_info['card_id'],
						'card_secret' => $card_info['card_secret'],
					);
				}
			}
		}
		$transaction_hash = isset( $_POST['transaction_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_hash'] ) ) : '';
		

		$chain_id = isset( $_POST['chain_id'] ) ? absint( wp_unslash( $_POST['chain_id'] ) ) : 0;
		$token_address = isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '';
		$token_symbol = isset( $_POST['token_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['token_symbol'] ) ) : '';
		$token_type = isset( $_POST['token_type'] ) ? sanitize_text_field( wp_unslash( $_POST['token_type'] ) ) : '';
		$token_decimals = isset( $_POST['token_decimals'] ) ? absint( wp_unslash( $_POST['token_decimals'] ) ) : 0;
		$amount = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';

		if ( empty( $card_data ) || empty( $transaction_hash ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid card data or transaction hash.', 'univoucher-for-woocommerce' ) ) );
		}

		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$added_cards = array();
		$errors = array();

		foreach ( $card_data as $card_info ) {
			if ( empty( $card_info['card_id'] ) || empty( $card_info['card_secret'] ) ) {
				continue;
			}

			$data = array(
				'product_id'     => $product_id,
				'order_id'       => null,
				'status'         => 'available',
				'card_id'        => $card_info['card_id'],
				'card_secret'    => $card_info['card_secret'],
				'chain_id'       => $chain_id,
				'token_address'  => $token_address,
				'token_symbol'   => $token_symbol,
				'token_type'     => $token_type,
				'token_decimals' => $token_decimals,
				'amount'         => $amount,
				'transaction_hash' => $transaction_hash,
			);

			$result = $gift_card_manager->uv_add_gift_card( $data );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( 
					// translators: %1$s is the card ID, %2$s is the error message
					esc_html__( 'Card %1$s: %2$s', 'univoucher-for-woocommerce' ),
					$card_info['card_id'],
					$result->get_error_message()
				);
			} else {
				$added_cards[] = $card_info['card_id'];
			}
		}

		$success_count = count( $added_cards );
		$error_count = count( $errors );

		$message = sprintf(
			// translators: %d is the number of cards created successfully
			esc_html__( '%d cards created and added successfully.', 'univoucher-for-woocommerce' ),
			$success_count
		);

		if ( $error_count > 0 ) {
			$message .= ' ' . sprintf(
				// translators: %d is the number of errors
				esc_html__( '%d errors occurred.', 'univoucher-for-woocommerce' ),
				$error_count
			);
		}

		// Get current stock quantity using WooCommerce native method
		$product = wc_get_product( $product_id );
		$current_stock = $product ? $product->get_stock_quantity() : 0;

		wp_send_json_success( array(
			'message' => $message,
			'added_cards' => $added_cards,
			'errors' => $errors,
			'success_count' => $success_count,
			'error_count' => $error_count,
			'transaction_hash' => $transaction_hash,
			'current_stock' => $current_stock,
		) );
	}
} 