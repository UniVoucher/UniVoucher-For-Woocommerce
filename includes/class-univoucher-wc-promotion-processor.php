<?php
/**
 * UniVoucher For WooCommerce Promotion Processor
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Promotion_Processor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Promotion_Processor class.
 */
class UniVoucher_WC_Promotion_Processor {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Promotion_Processor
	 */
	protected static $_instance = null;

	/**
	 * Database instance.
	 *
	 * @var UniVoucher_WC_Database
	 */
	private $database;

	/**
	 * Main UniVoucher_WC_Promotion_Processor Instance.
	 *
	 * @return UniVoucher_WC_Promotion_Processor - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Promotion_Processor Constructor.
	 */
	public function __construct() {
		$this->database = UniVoucher_WC_Database::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_order_promotions' ), 10, 1 );
	}

	/**
	 * Process promotions for completed order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_order_promotions( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Get all active promotions.
		$promotions = $this->get_active_promotions();
		if ( empty( $promotions ) ) {
			return;
		}

		// Phase 1: Evaluate all promotions first (before creating any tracking records).
		$qualifying_promotions = array();
		foreach ( $promotions as $promotion ) {
			if ( $this->should_promotion_trigger( $promotion, $order, $user_id ) ) {
				$qualifying_promotions[] = $promotion;
			}
		}

		// Phase 2: Create cards and tracking for all qualifying promotions.
		foreach ( $qualifying_promotions as $promotion ) {
			$this->create_promotion_card_and_track( $promotion, $order, $user_id );
		}
	}

	/**
	 * Get all active promotions.
	 *
	 * @return array
	 */
	private function get_active_promotions() {
		global $wpdb;
		$table = $this->database->uv_get_promotions_table();

		$promotions = $wpdb->get_results(
			"SELECT * FROM $table WHERE is_active = 1 ORDER BY id ASC",
			ARRAY_A
		);

		return $promotions ? $promotions : array();
	}

	/**
	 * Check if a promotion should trigger for an order.
	 *
	 * @param array    $promotion Promotion data.
	 * @param WC_Order $order     Order object.
	 * @param int      $user_id   User ID.
	 * @return bool
	 */
	private function should_promotion_trigger( $promotion, $order, $user_id ) {
		// Check max total limit.
		if ( $promotion['max_total'] > 0 && $promotion['total_issued'] >= $promotion['max_total'] ) {
			return false;
		}

		// Check per-user limit.
		if ( $promotion['max_per_user'] > 0 ) {
			$user_count = $this->get_user_promotion_count( $user_id, $promotion['id'] );
			if ( $user_count >= $promotion['max_per_user'] ) {
				return false;
			}
		}

		// Evaluate rules.
		if ( ! $this->evaluate_promotion_rules( $promotion, $order, $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create promotional card and tracking for a qualifying promotion.
	 *
	 * @param array    $promotion Promotion data.
	 * @param WC_Order $order     Order object.
	 * @param int      $user_id   User ID.
	 */
	private function create_promotion_card_and_track( $promotion, $order, $user_id ) {
		// Generate promotional gift card.
		$card_data = $this->generate_promotional_card( $promotion, $order, $user_id );
		if ( ! $card_data ) {
			return;
		}

		// Counters are updated in handle_promotion_callback() when card creation is confirmed.
		// Emails are also sent from callback when card is ready.
	}

	/**
	 * Get user's promotion count.
	 *
	 * @param int $user_id      User ID.
	 * @param int $promotion_id Promotion ID.
	 * @return int
	 */
	private function get_user_promotion_count( $user_id, $promotion_id ) {
		global $wpdb;
		$table = $this->database->uv_get_promotion_user_tracking_table();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT times_received FROM $table WHERE user_id = %d AND promotion_id = %d",
				$user_id,
				$promotion_id
			)
		);

		return $count ? (int) $count : 0;
	}

	/**
	 * Evaluate promotion rules.
	 *
	 * @param array    $promotion Promotion data.
	 * @param WC_Order $order     Order object.
	 * @param int      $user_id   User ID.
	 * @return bool
	 */
	private function evaluate_promotion_rules( $promotion, $order, $user_id ) {
		$rules = json_decode( $promotion['rules'], true );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			error_log( 'UniVoucher Promotion: No valid rules found for promotion ' . $promotion['id'] );
			return false;
		}

		// All rules must pass (AND logic).
		foreach ( $rules as $rule ) {
			if ( ! $this->evaluate_single_rule( $rule, $order, $user_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single rule.
	 *
	 * @param array    $rule    Rule data.
	 * @param WC_Order $order   Order object.
	 * @param int      $user_id User ID.
	 * @return bool
	 */
	private function evaluate_single_rule( $rule, $order, $user_id ) {
		$type = isset( $rule['type'] ) ? $rule['type'] : 'order';
		$condition = isset( $rule['condition'] ) ? $rule['condition'] : '';
		$value = isset( $rule['value'] ) ? $rule['value'] : '';
		$operator = isset( $rule['operator'] ) ? $rule['operator'] : '';

		if ( 'order' === $type ) {
			return $this->evaluate_order_rule( $condition, $operator, $value, $order );
		} elseif ( 'user' === $type ) {
			return $this->evaluate_user_rule( $condition, $operator, $value, $user_id );
		}

		return false;
	}

	/**
	 * Evaluate order rule.
	 *
	 * @param string   $condition Rule condition.
	 * @param string   $operator  Rule operator.
	 * @param mixed    $value     Rule value.
	 * @param WC_Order $order     Order object.
	 * @return bool
	 */
	private function evaluate_order_rule( $condition, $operator, $value, $order ) {
		switch ( $condition ) {
			case 'includes_product':
				$product_id = absint( $value );
				foreach ( $order->get_items() as $item ) {
					if ( $item->get_product_id() === $product_id || $item->get_variation_id() === $product_id ) {
						return true;
					}
				}
				return false;

			case 'includes_category':
				$category_id = absint( $value );
				foreach ( $order->get_items() as $item ) {
					$product = $item->get_product();
					if ( $product ) {
						$categories = $product->get_category_ids();
						if ( in_array( $category_id, $categories, true ) ) {
							return true;
						}
					}
				}
				return false;

			case 'total_value':
				$order_total = (float) $order->get_total();

				if ( 'more_than' === $operator ) {
					return $order_total > (float) $value;
				} elseif ( 'less_than' === $operator ) {
					return $order_total < (float) $value;
				} elseif ( 'between' === $operator && is_array( $value ) ) {
					$min = isset( $value['min'] ) ? (float) $value['min'] : 0;
					$max = isset( $value['max'] ) ? (float) $value['max'] : 0;
					return $order_total >= $min && $order_total <= $max;
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Evaluate user rule.
	 *
	 * @param string $condition Rule condition.
	 * @param string $operator  Rule operator.
	 * @param mixed  $value     Rule value.
	 * @param int    $user_id   User ID.
	 * @return bool
	 */
	private function evaluate_user_rule( $condition, $operator, $value, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		switch ( $condition ) {
			case 'user_id':
				// Value is a comma-separated list of user IDs
				if ( is_string( $value ) ) {
					$allowed_user_ids = array_map( 'absint', explode( ',', $value ) );
					return in_array( $user_id, $allowed_user_ids, true );
				}
				return false;

			case 'completed_orders_count':
				// Get count of completed orders for this user
				$customer = new WC_Customer( $user_id );
				$orders_count = wc_get_orders(
					array(
						'customer_id' => $user_id,
						'status'      => 'completed',
						'return'      => 'ids',
						'limit'       => -1,
					)
				);
				$total_completed = is_array( $orders_count ) ? count( $orders_count ) : 0;

				if ( 'more_than' === $operator ) {
					return $total_completed > (int) $value;
				} elseif ( 'less_than' === $operator ) {
					return $total_completed < (int) $value;
				}
				return false;

			case 'user_role':
				// Check if user has any of the specified roles
				$allowed_roles = is_array( $value ) ? $value : explode( ',', $value );
				$allowed_roles = array_map( 'trim', $allowed_roles );

				if ( empty( $allowed_roles ) ) {
					return false;
				}

				// Check if user has any of the allowed roles
				$user_roles = $user->roles;
				foreach ( $allowed_roles as $role ) {
					if ( in_array( $role, $user_roles, true ) ) {
						return true;
					}
				}
				return false;

			case 'registration_date':
				$registration_date = strtotime( $user->user_registered );
				$target_date = strtotime( $value );

				if ( 'before' === $operator ) {
					return $registration_date < $target_date;
				} elseif ( 'after' === $operator ) {
					return $registration_date > $target_date;
				}
				return false;

			case 'never_received_promotion':
				global $wpdb;
				$tracking_table = $this->database->uv_get_promotion_user_tracking_table();
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $tracking_table WHERE user_id = %d",
						$user_id
					)
				);
				return 0 === (int) $count;

			default:
				return false;
		}
	}

	/**
	 * Generate promotional gift card using UniVoucher API.
	 *
	 * @param array    $promotion Promotion data.
	 * @param WC_Order $order     Order object.
	 * @param int      $user_id   User ID.
	 * @return array|false
	 */
	private function generate_promotional_card( $promotion, $order, $user_id ) {
		// Get internal wallet private key.
		$encryption = UniVoucher_WC_Encryption::instance();
		$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );

		if ( ! $encrypted_private_key ) {
			error_log( 'UniVoucher Promotion: Cannot create card - internal wallet not configured' );
			return false;
		}

		$private_key = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( is_wp_error( $private_key ) ) {
			error_log( 'UniVoucher Promotion: Cannot create card - invalid wallet configuration' );
			return false;
		}

		// Ensure private key has 0x prefix.
		if ( ! empty( $private_key ) && strpos( $private_key, '0x' ) !== 0 ) {
			$private_key = '0x' . $private_key;
		}

		// Convert amount to wei.
		$token_amount_wei = $this->decimal_to_wei( $promotion['card_amount'], $promotion['token_decimals'] );

		// Handle token address format - for native tokens, use zero address.
		$token_address = $promotion['token_address'];
		if ( $promotion['token_type'] === 'native' || empty( $token_address ) ) {
			$token_address = '0x0000000000000000000000000000000000000000';
		}

		// Validate network is supported.
		$supported_networks = array( 1, 8453, 56, 137, 42161, 10, 43114 );
		if ( ! in_array( intval( $promotion['chain_id'] ), $supported_networks, true ) ) {
			error_log( 'UniVoucher Promotion: Cannot create card - unsupported network ' . $promotion['chain_id'] );
			return false;
		}

		// Generate unique order ID for this request.
		$api_order_id = 'promo_' . $promotion['id'] . '_' . $order->get_id() . '_' . time();

		// Generate callback URL.
		$callback_url = home_url( '/wp-json/univoucher/v1/promotion-callback' );

		// Generate auth token.
		$auth_token = wp_generate_password( 32, false );

		// Store auth token and context for callback verification (expires in 1 hour).
		set_transient( 'univoucher_promo_callback_auth_' . $api_order_id, $auth_token, HOUR_IN_SECONDS );
		set_transient(
			'univoucher_promo_callback_context_' . $api_order_id,
			array(
				'promotion_id' => $promotion['id'],
				'user_id'      => $user_id,
				'order_id'     => $order->get_id(),
				'promotion'    => $promotion,
			),
			HOUR_IN_SECONDS
		);

		// Prepare API request.
		$api_data = array(
			'network'      => intval( $promotion['chain_id'] ),
			'tokenAddress' => $token_address,
			'amount'       => (string) $token_amount_wei,
			'quantity'     => 1,
			'privateKey'   => $private_key,
			'orderId'      => $api_order_id,
			'callbackUrl'  => $callback_url,
			'authToken'    => $auth_token,
		);

		// Make API request.
		$response = wp_remote_post(
			'https://api.univoucher.com/v1/cards/create',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $api_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'UniVoucher Promotion: Failed to create card - ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( $response_code === 202 && isset( $response_data['success'] ) && $response_data['success'] ) {
			// Success - card creation initiated.
			// The actual card will be stored via callback.
			$order->add_order_note(
				sprintf(
					// translators: %s is the promotion title
					__( 'UniVoucher: Promotional gift card creation initiated for "%s"', 'univoucher-for-woocommerce' ),
					$promotion['title']
				)
			);

			// Return temporary placeholder data.
			return array(
				'status'      => 'pending',
				'api_order_id' => $api_order_id,
			);
		} else {
			// Error.
			$error_message = isset( $response_data['error'] ) ? $response_data['error'] : 'Unknown error';
			error_log( 'UniVoucher Promotion: Failed to create card - ' . $error_message );
			return false;
		}
	}

	/**
	 * Convert decimal amount to wei format.
	 *
	 * @param string $decimal_amount Decimal amount.
	 * @param int    $decimals Number of decimals.
	 * @return string Wei amount as string.
	 */
	private function decimal_to_wei( $decimal_amount, $decimals ) {
		// Use bcmath for precision if available.
		if ( function_exists( 'bcmul' ) ) {
			$multiplier = bcpow( '10', $decimals );
			return bcmul( $decimal_amount, $multiplier, 0 );
		}

		// Fallback to regular multiplication.
		$multiplier = pow( 10, $decimals );
		return (string) ( $decimal_amount * $multiplier );
	}

	/**
	 * Handle promotional card creation callback from UniVoucher API.
	 *
	 * @param string $api_order_id The API order ID.
	 * @param array  $callback_data The callback data from API.
	 * @return bool Success status.
	 */
	public function handle_promotion_callback( $api_order_id, $callback_data ) {
		// Get stored context.
		$context = get_transient( 'univoucher_promo_callback_context_' . $api_order_id );
		if ( ! $context ) {
			error_log( 'UniVoucher Promotion: Callback context not found for ' . $api_order_id );
			return false;
		}

		$promotion = $context['promotion'];
		$order = wc_get_order( $context['order_id'] );
		if ( ! $order ) {
			error_log( 'UniVoucher Promotion: Order not found for callback ' . $api_order_id );
			return false;
		}

		// Check callback status.
		$status = isset( $callback_data['status'] ) ? $callback_data['status'] : '';

		if ( $status === 'success' && isset( $callback_data['cards'] ) && ! empty( $callback_data['cards'] ) ) {
			// Success - card was created.
			$card = $callback_data['cards'][0];

			// Get transaction hash from callback data.
			$transaction_hash = null;
			if ( ! empty( $callback_data['creationTransactionHashes'] ) && is_array( $callback_data['creationTransactionHashes'] ) ) {
				$transaction_hash = $callback_data['creationTransactionHashes'][0];
			}

			global $wpdb;
			$card_data = array(
				'promotion_id'     => $context['promotion_id'],
				'user_id'          => $context['user_id'],
				'order_id'         => $context['order_id'],
				'card_id'          => $card['cardId'],
				'card_secret'      => $card['cardSecret'],
				'chain_id'         => $callback_data['network'],
				'token_address'    => $card['tokenAddress'],
				'token_symbol'     => $promotion['token_symbol'],
				'token_type'       => $promotion['token_type'],
				'token_decimals'   => $promotion['token_decimals'],
				'amount'           => $promotion['card_amount'],
				'transaction_hash' => $transaction_hash,
				'status'           => 'active',
			);

			$table = $this->database->uv_get_promotional_cards_table();
			$inserted = $wpdb->insert( $table, $card_data );

			if ( $inserted ) {
				// Update promotion counters.
				$this->update_promotion_counters( $context['promotion_id'], $context['user_id'] );

				// Add order note with transaction details.
				$note_message = sprintf(
					// translators: %1$s is the promotion title, %2$s is the card ID
					__( 'UniVoucher: Promotional gift card created for "%1$s" (Card ID: %2$s)', 'univoucher-for-woocommerce' ),
					$promotion['title'],
					$card['cardId']
				);

				// Add transaction hashes if available.
				if ( ! empty( $callback_data['creationTransactionHashes'] ) ) {
					$creation_hashes = implode( ', ', $callback_data['creationTransactionHashes'] );
					$note_message .= sprintf(
						// translators: %s is the creation transaction hashes
						__( ' - TX: %s', 'univoucher-for-woocommerce' ),
						$creation_hashes
					);
				}

				$order->add_order_note( $note_message );

				// Send promotion emails.
				$card_data['id'] = $wpdb->insert_id;
				$this->send_promotion_emails( $promotion, $order, $card_data );

				// Clean up transients.
				delete_transient( 'univoucher_promo_callback_auth_' . $api_order_id );
				delete_transient( 'univoucher_promo_callback_context_' . $api_order_id );

				return true;
			} else {
				error_log( 'UniVoucher Promotion: Failed to insert card into database - ' . $wpdb->last_error );
			}
		} else {
			// Error.
			$error_message = isset( $callback_data['error'] ) ? $callback_data['error'] : 'Unknown error';
			$order->add_order_note(
				sprintf(
					// translators: %1$s is the promotion title, %2$s is the error message
					__( 'UniVoucher: Failed to create promotional gift card for "%1$s" - %2$s', 'univoucher-for-woocommerce' ),
					$promotion['title'],
					$error_message
				)
			);

			// Clean up transients.
			delete_transient( 'univoucher_promo_callback_auth_' . $api_order_id );
			delete_transient( 'univoucher_promo_callback_context_' . $api_order_id );
		}

		return false;
	}

	/**
	 * Update promotion counters.
	 *
	 * @param int $promotion_id Promotion ID.
	 * @param int $user_id      User ID.
	 */
	private function update_promotion_counters( $promotion_id, $user_id ) {
		global $wpdb;

		// Update total_issued counter.
		$promotions_table = $this->database->uv_get_promotions_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $promotions_table SET total_issued = total_issued + 1 WHERE id = %d",
				$promotion_id
			)
		);

		// Update user tracking.
		$tracking_table = $this->database->uv_get_promotion_user_tracking_table();
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $tracking_table WHERE user_id = %d AND promotion_id = %d",
				$user_id,
				$promotion_id
			)
		);

		if ( $existing ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $tracking_table SET times_received = times_received + 1, last_received_at = NOW() WHERE user_id = %d AND promotion_id = %d",
					$user_id,
					$promotion_id
				)
			);
		} else {
			$wpdb->insert(
				$tracking_table,
				array(
					'user_id'      => $user_id,
					'promotion_id' => $promotion_id,
					'times_received' => 1,
				)
			);
		}
	}

	/**
	 * Send promotion emails.
	 *
	 * @param array    $promotion Promotion data.
	 * @param WC_Order $order     Order object.
	 * @param array    $card_data Card data.
	 */
	private function send_promotion_emails( $promotion, $order, $card_data ) {
		$user = get_userdata( $card_data['user_id'] );
		if ( ! $user ) {
			return;
		}

		// Get network name.
		$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card_data['chain_id'] );
		$network_name = $network_data ? $network_data['name'] : 'Unknown';

		$card_info = sprintf(
			"Card ID: %s<br>Card Secret: %s<br>Amount: %s %s<br>Network: %s",
			$card_data['card_id'],
			$card_data['card_secret'],
			$this->format_token_amount( $card_data['amount'], $card_data['token_decimals'] ),
			$card_data['token_symbol'],
			$network_name
		);

		// Send separate email if configured.
		if ( ! empty( $promotion['send_separate_email'] ) ) {
			// Use user-defined email subject or default.
			if ( ! empty( $promotion['email_subject'] ) ) {
				$subject = $promotion['email_subject'];
			} else {
				$site_name = get_bloginfo( 'name' );
				$subject = sprintf( 'Your order #%s got a free gift card 🎁', $order->get_id() );
			}

			// Replace placeholders in subject.
			$subject = str_replace( '{site_name}', get_bloginfo( 'name' ), $subject );
			$subject = str_replace( '{user_name}', $user->display_name, $subject );
			$subject = str_replace( '{order_id}', $order->get_id(), $subject );

			if ( ! empty( $promotion['email_template'] ) ) {
				$message = $promotion['email_template'];
				$message = str_replace( '{card_id}', $card_data['card_id'], $message );
				$message = str_replace( '{card_secret}', $card_data['card_secret'], $message );
				$message = str_replace( '{amount}', $this->format_token_amount( $card_data['amount'], $card_data['token_decimals'] ), $message );
				$message = str_replace( '{symbol}', $card_data['token_symbol'], $message );
				$message = str_replace( '{token_symbol}', $card_data['token_symbol'], $message ); // Backward compatibility.
				$message = str_replace( '{network}', $network_name, $message );
				$message = str_replace( '{user_name}', $user->display_name, $message );
				$message = str_replace( '{customer_name}', $user->display_name, $message );
				$message = str_replace( '{order_id}', $order->get_id(), $message );
				$message = str_replace( '{order_number}', $order->get_id(), $message );
				$message = str_replace( '{site_name}', get_bloginfo( 'name' ), $message );
				$message = str_replace( '{gift_card_details}', $card_info, $message );
			} else {
				$message = sprintf(
					"Hello %s,\n\nCongratulations! You've received a promotional gift card.\n\n%s\n\nThank you for your order!",
					$user->display_name,
					$card_info
				);
			}

			// Set HTML content type for email.
			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

			// Build headers to improve deliverability and avoid promotions tab.
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'X-Priority: 1',
				'Importance: high',
				'X-Auto-Response-Suppress: OOF, AutoReply',
			);

			wp_mail( $user->user_email, $subject, $message, $headers );

			// Reset content type to avoid conflicts with other emails.
			remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		}
	}

	/**
	 * Set email content type to HTML.
	 *
	 * @return string
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Format token amount with proper decimals.
	 *
	 * @param string|float $amount   The amount to format.
	 * @param int          $decimals Number of decimals (default 6).
	 * @return string Formatted amount.
	 */
	private function format_token_amount( $amount, $decimals = 6 ) {
		// Format to specified decimals
		$formatted = number_format( (float) $amount, $decimals, '.', '' );
		// Remove trailing zeros
		$formatted = rtrim( $formatted, '0' );
		// Remove trailing decimal point if no decimals remain
		$formatted = rtrim( $formatted, '.' );
		return $formatted;
	}

	/**
	 * Process expired promotional cards - mark as expired or cancel them if auto-cancel is enabled.
	 *
	 * This method is called when visiting any UniVoucher admin page to avoid cron jobs.
	 */
	public function process_expired_promotional_cards() {
		global $wpdb;

		// Get all active promotions with expiration days set.
		$promotions_table = $this->database->uv_get_promotions_table();
		$promotions = $wpdb->get_results(
			"SELECT * FROM $promotions_table
			WHERE is_active = 1
			AND card_expiration_days > 0",
			ARRAY_A
		);

		if ( empty( $promotions ) ) {
			return;
		}

		// Process each promotion.
		foreach ( $promotions as $promotion ) {
			if ( ! empty( $promotion['auto_cancel_expired'] ) ) {
				// Auto-cancel is enabled - cancel cards via API.
				$this->cancel_expired_cards_for_promotion( $promotion );
			} else {
				// Auto-cancel is disabled - just mark cards as expired.
				$this->mark_expired_cards_for_promotion( $promotion );
			}
		}
	}

	/**
	 * Mark expired cards as 'expired' status without cancelling them on-chain.
	 *
	 * @param array $promotion Promotion data.
	 */
	private function mark_expired_cards_for_promotion( $promotion ) {
		global $wpdb;

		$cards_table = $this->database->uv_get_promotional_cards_table();
		$expiration_days = absint( $promotion['card_expiration_days'] );

		// Update expired active cards to 'expired' status.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $cards_table
				SET status = 'expired'
				WHERE promotion_id = %d
				AND status = 'active'
				AND DATEDIFF(NOW(), created_at) > %d",
				$promotion['id'],
				$expiration_days
			)
		);

		if ( $updated > 0 ) {
			error_log(
				sprintf(
					'UniVoucher Promotion: Marked %d cards as expired for promotion "%s" (no auto-cancel)',
					$updated,
					$promotion['title']
				)
			);
		}
	}

	/**
	 * Cancel expired cards for a specific promotion.
	 *
	 * @param array $promotion Promotion data.
	 */
	private function cancel_expired_cards_for_promotion( $promotion ) {
		global $wpdb;

		$cards_table = $this->database->uv_get_promotional_cards_table();
		$expiration_days = absint( $promotion['card_expiration_days'] );

		// First, mark all expired active cards as 'expired'.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $cards_table
				SET status = 'expired'
				WHERE promotion_id = %d
				AND status = 'active'
				AND DATEDIFF(NOW(), created_at) > %d",
				$promotion['id'],
				$expiration_days
			)
		);

		// Now find the expired cards to cancel them.
		$expired_cards = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $cards_table
				WHERE promotion_id = %d
				AND status = 'expired'
				AND DATEDIFF(NOW(), created_at) > %d
				LIMIT 100",
				$promotion['id'],
				$expiration_days
			),
			ARRAY_A
		);

		if ( empty( $expired_cards ) ) {
			return;
		}

		// Group cards by chain_id for batch processing.
		$cards_by_chain = array();
		foreach ( $expired_cards as $card ) {
			$chain_id = $card['chain_id'];
			if ( ! isset( $cards_by_chain[ $chain_id ] ) ) {
				$cards_by_chain[ $chain_id ] = array();
			}
			$cards_by_chain[ $chain_id ][] = $card;
		}

		// Process each chain separately.
		foreach ( $cards_by_chain as $chain_id => $cards ) {
			$this->cancel_cards_batch( $cards, $promotion );
		}
	}

	/**
	 * Cancel a batch of promotional cards using UniVoucher API.
	 *
	 * @param array $cards     Array of card data.
	 * @param array $promotion Promotion data.
	 * @return bool|WP_Error True on success (202 accepted), WP_Error on failure.
	 */
	private function cancel_cards_batch( $cards, $promotion ) {
		// Get internal wallet private key.
		$encryption = UniVoucher_WC_Encryption::instance();
		$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );

		if ( ! $encrypted_private_key ) {
			error_log( 'UniVoucher Promotion: Cannot cancel cards - internal wallet not configured' );
			return new WP_Error( 'wallet_not_configured', __( 'Internal wallet not configured.', 'univoucher-for-woocommerce' ) );
		}

		$private_key = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( is_wp_error( $private_key ) ) {
			error_log( 'UniVoucher Promotion: Cannot cancel cards - invalid wallet configuration' );
			return new WP_Error( 'invalid_wallet', __( 'Invalid wallet configuration.', 'univoucher-for-woocommerce' ) );
		}

		// Ensure private key has 0x prefix.
		if ( ! empty( $private_key ) && strpos( $private_key, '0x' ) !== 0 ) {
			$private_key = '0x' . $private_key;
		}

		// Extract card IDs.
		$card_ids = array_map(
			function( $card ) {
				return $card['card_id'];
			},
			$cards
		);

		// Generate callback URL and auth token.
		$callback_url = home_url( '/wp-json/univoucher/v1/promotion-cancel-callback' );
		$auth_token = wp_generate_password( 32, false );
		$cancel_batch_id = 'cancel_promo_' . $promotion['id'] . '_' . time();

		// Store auth token and context for callback verification.
		set_transient( 'univoucher_cancel_callback_auth_' . $cancel_batch_id, $auth_token, HOUR_IN_SECONDS );
		set_transient(
			'univoucher_cancel_callback_context_' . $cancel_batch_id,
			array(
				'promotion_id' => $promotion['id'],
				'card_ids'     => $card_ids,
				'cards'        => $cards,
			),
			HOUR_IN_SECONDS
		);

		// Prepare API request.
		$api_data = array(
			'cardIds'     => $card_ids,
			'privateKey'  => $private_key,
			'callbackUrl' => $callback_url,
			'authToken'   => $auth_token,
		);

		// Make API request.
		$response = wp_remote_post(
			'https://api.univoucher.com/v1/cards/cancel',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $api_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'UniVoucher Promotion Cancellation: Failed to cancel cards - ' . $response->get_error_message() );
			return new WP_Error( 'api_request_failed', sprintf( __( 'API request failed: %s', 'univoucher-for-woocommerce' ), $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( $response_code === 202 && isset( $response_data['success'] ) && $response_data['success'] ) {
			// Success - cancellation initiated.
			error_log(
				sprintf(
					'UniVoucher Promotion: Initiated cancellation of %d cards for promotion "%s"',
					count( $card_ids ),
					$promotion['title']
				)
			);
			return true;
		} else {
			// Error - API validation failed.
			$error_message = isset( $response_data['error'] ) ? $response_data['error'] : 'Unknown error';
			error_log( 'UniVoucher Promotion Cancellation: Failed to cancel cards - ' . $error_message );
			return new WP_Error( 'api_validation_failed', $error_message );
		}
	}

	/**
	 * Handle promotional card cancellation callback from UniVoucher API.
	 *
	 * @param string $cancel_batch_id The cancellation batch ID.
	 * @param array  $callback_data   The callback data from API.
	 * @return bool Success status.
	 */
	public function handle_cancellation_callback( $cancel_batch_id, $callback_data ) {
		// Get stored context.
		$context = get_transient( 'univoucher_cancel_callback_context_' . $cancel_batch_id );
		if ( ! $context ) {
			error_log( 'UniVoucher Promotion Cancellation: Callback context not found for ' . $cancel_batch_id );
			return false;
		}

		global $wpdb;
		$cards_table = $this->database->uv_get_promotional_cards_table();

		// Check callback status.
		$status = isset( $callback_data['status'] ) ? $callback_data['status'] : '';

		if ( $status === 'completed' && isset( $callback_data['cancelledCards'] ) && ! empty( $callback_data['cancelledCards'] ) ) {
			// Success - cards were cancelled.
			$cancelled_count = 0;

			foreach ( $callback_data['cancelledCards'] as $cancelled_card ) {
				$card_id = $cancelled_card['cardId'];

				// Update card status.
				$updated = $wpdb->update(
					$cards_table,
					array( 'status' => 'cancelled' ),
					array( 'card_id' => $card_id ),
					array( '%s' ),
					array( '%s' )
				);

				if ( $updated !== false ) {
					$cancelled_count++;
				}
			}

			error_log(
				sprintf(
					'UniVoucher Promotion: Successfully cancelled %d expired promotional cards',
					$cancelled_count
				)
			);

			// Clean up transients.
			delete_transient( 'univoucher_cancel_callback_auth_' . $cancel_batch_id );
			delete_transient( 'univoucher_cancel_callback_context_' . $cancel_batch_id );

			return true;
		} else {
			// Error.
			$error_message = isset( $callback_data['error'] ) ? $callback_data['error'] : 'Unknown error';
			error_log( 'UniVoucher Promotion Cancellation: Failed to cancel cards - ' . $error_message );

			// Clean up transients.
			delete_transient( 'univoucher_cancel_callback_auth_' . $cancel_batch_id );
			delete_transient( 'univoucher_cancel_callback_context_' . $cancel_batch_id );
		}

		return false;
	}

}
