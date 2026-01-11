<?php
/**
 * UniVoucher For WooCommerce Promotion Notices
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Promotion_Notices
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Promotion_Notices class.
 */
class UniVoucher_WC_Promotion_Notices {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Promotion_Notices
	 */
	protected static $_instance = null;

	/**
	 * Database instance.
	 *
	 * @var UniVoucher_WC_Database
	 */
	private $database;

	/**
	 * Main UniVoucher_WC_Promotion_Notices Instance.
	 *
	 * @return UniVoucher_WC_Promotion_Notices - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Promotion_Notices Constructor.
	 */
	public function __construct() {
		$this->database = UniVoucher_WC_Database::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Display account notice on My Account dashboard only.
		add_action( 'woocommerce_account_content', array( $this, 'display_account_notice' ), 1 );

		// Display order page notice before thank you message.
		add_action( 'woocommerce_before_thankyou', array( $this, 'display_order_notice' ), 99, 1 );

		// Display order page notice before order table (but not on thank you page).
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_order_notice_before_table' ), 99, 1 );

		// Register shortcode for displaying notices.
		add_shortcode( 'univoucher_unredeemed_promotion', array( $this, 'shortcode_notice' ) );

		// AJAX handler for dismissing notices.
		add_action( 'wp_ajax_univoucher_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_nopriv_univoucher_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Enqueue scripts for notices.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for notice functionality.
	 */
	public function enqueue_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'univoucher-notices',
			plugins_url( 'assets/js/promotion-notices.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		wp_localize_script(
			'univoucher-notices',
			'univoucherNotices',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'univoucher_dismiss_notice' ),
			)
		);

		// Add inline styles for notices.
		wp_add_inline_style( 'wp-block-library', $this->get_notice_styles() );
	}

	/**
	 * Get notice styles.
	 *
	 * @return string CSS styles.
	 */
	private function get_notice_styles() {
		return "
			.univoucher-promotion-notice {
				position: relative !important;
				padding: 15px 40px 15px 15px !important;
				margin: 15px auto !important;
				max-width: 1200px;
				border: 1px solid currentColor;
				border-radius: 4px;
				font-size: 14px;
				line-height: 1.6;
				display: block !important;
			}
			.univoucher-promotion-notice-title {
				font-weight: bold;
				margin-bottom: 8px;
				font-size: 16px;
			}
			.univoucher-promotion-notice-message {
				margin: 0;
			}
			.univoucher-promotion-notice-dismiss {
				position: absolute !important;
				top: 10px !important;
				right: 10px !important;
				background: transparent !important;
				border: none !important;
				font-size: 20px !important;
				cursor: pointer !important;
				padding: 0 !important;
				width: 24px !important;
				height: 24px !important;
				line-height: 1 !important;
				color: inherit !important;
				opacity: 0.5;
				z-index: 10 !important;
				margin: 0 !important;
				float: none !important;
				transition: opacity 0.2s ease;
			}
			.univoucher-promotion-notice-dismiss:hover {
				opacity: 1;
				color: inherit !important;
				background: transparent !important;
			}
			.univoucher-promotion-card-details {
				margin-top: 10px;
				padding: 10px;
				background: rgba(0,0,0,0.05);
				border-radius: 4px;
				font-family: monospace;
			}
			.univoucher-promotion-card-details div {
				margin: 5px 0;
			}
			.univoucher-order-promotion-notice {
				margin: 20px 0;
			}
		";
	}

	/**
	 * Display account notice for active promotional cards on dashboard.
	 */
	public function display_account_notice() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Only show on dashboard page (when no specific endpoint is set).
		global $wp;
		$is_dashboard = true;

		// Check if any WooCommerce endpoint is active (other than dashboard).
		if ( isset( $wp->query_vars ) ) {
			foreach ( WC()->query->get_query_vars() as $key => $value ) {
				if ( isset( $wp->query_vars[ $key ] ) && 'dashboard' !== $key ) {
					$is_dashboard = false;
					break;
				}
			}
		}

		if ( ! $is_dashboard ) {
			return;
		}

		$user_id = get_current_user_id();
		$cards = $this->get_active_promotional_cards( $user_id );

		if ( empty( $cards ) ) {
			return;
		}

		foreach ( $cards as $card ) {
			// Get promotion settings.
			$promotion = $this->get_promotion( $card['promotion_id'] );
			if ( ! $promotion ) {
				continue;
			}

			// Check if account notice is enabled for this promotion.
			if ( empty( $promotion['show_account_notice'] ) ) {
				continue;
			}

			// Check if notice is dismissed.
			if ( $this->is_notice_dismissed( $user_id, $card['id'], 'website' ) ) {
				continue;
			}

			// Get network name.
			$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card['chain_id'] );
			$network_name = $network_data ? $network_data['name'] : 'Unknown';

			// Prepare message template.
			$message = ! empty( $promotion['account_notice_message'] )
				? $promotion['account_notice_message']
				: 'You have an unredeemed {amount} {symbol} on {network} UniVoucher promotional gift card.';

			$message = str_replace( '{amount}', $this->format_token_amount( $card['amount'] ), $message );
			$message = str_replace( '{symbol}', $card['token_symbol'], $message );
			$message = str_replace( '{network}', $network_name, $message );
			$message = str_replace( '{card_id}', $card['card_id'], $message );
			$message = str_replace( '{card_secret}', $card['card_secret'], $message );

			// Display notice.
			$this->render_notice( $card, $message, 'website' );
		}
	}

	/**
	 * Display order page notice before order table (only if not on thank you page).
	 *
	 * @param WC_Order|int $order Order object or order ID.
	 */
	public function display_order_notice_before_table( $order ) {
		// Check if we're on the thank you page - if so, skip this hook.
		if ( is_order_received_page() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		// Otherwise, display the notice.
		$this->display_order_notice( $order );
	}

	/**
	 * Display order page notice for active promotional cards.
	 *
	 * @param WC_Order|int $order Order object or order ID.
	 */
	public function display_order_notice( $order ) {
		// Handle both order object and order ID.
		if ( is_numeric( $order ) ) {
			$order_id = absint( $order );
			$order = wc_get_order( $order_id );
		} elseif ( is_a( $order, 'WC_Order' ) ) {
			$order_id = $order->get_id();
		} else {
			return;
		}

		if ( ! $order ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Get all active promotional cards for this user.
		$cards = $this->get_active_promotional_cards( $user_id );

		if ( empty( $cards ) ) {
			return;
		}

		foreach ( $cards as $card ) {
			// Only show cards for this specific order.
			if ( ! isset( $card['order_id'] ) || (int) $card['order_id'] !== (int) $order_id ) {
				continue;
			}

			// Get promotion settings.
			$promotion = $this->get_promotion( $card['promotion_id'] );
			if ( ! $promotion ) {
				continue;
			}

			// Check if order notice is enabled for this promotion.
			if ( empty( $promotion['show_order_notice'] ) ) {
				continue;
			}


			// Get network name.
			$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card['chain_id'] );
			$network_name = $network_data ? $network_data['name'] : 'Unknown';

			// Prepare message.
			$message = ! empty( $promotion['order_notice_message'] )
				? $promotion['order_notice_message']
				: '<div style="border-left:4px solid #667eea;background:#f8f9fa;padding:15px;margin:15px 0;border-radius:5px">
	<h4 style="margin:0 0 5px 0;color:#667eea">🎁 You have got a Reward !</h4>
	<h3 style="margin:0 0 15px 0;color:#2c3e50">{amount} {symbol} UniVoucher Gift Card</h3>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:10px">
		<small><strong style="color:#667eea">CARD ID:</strong></small>
		<div style="font-family:monospace;margin-top:3px;word-break:break-all"><small>{card_id}</small></div>
	</div>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:10px">
		<small><strong style="color:#667eea">CARD SECRET:</strong></small>
		<div style="font-family:monospace;margin-top:3px;word-break:break-all"><small>{card_secret}</small></div>
	</div>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:12px">
		<small><strong style="color:#667eea">NETWORK:</strong></small>
		<div style="font-family:monospace;margin-top:3px"><small>{network}</small></div>
	</div>
	<a href="https://univoucher.com" target="_blank" rel="noopener noreferrer" style="display:inline-block;background:#667eea;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none">Redeem Now →</a>
</div>';

			$message = str_replace( '{amount}', $this->format_token_amount( $card['amount'] ), $message );
			$message = str_replace( '{symbol}', $card['token_symbol'], $message );
			$message = str_replace( '{network}', $network_name, $message );
			$message = str_replace( '{card_id}', $card['card_id'], $message );
			$message = str_replace( '{card_secret}', $card['card_secret'], $message );

			// Display notice without title and dismiss button for order pages.
			$this->render_order_notice( $message );
		}
	}

	/**
	 * Shortcode handler for displaying promotional notices.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Notice HTML.
	 */
	public function shortcode_notice( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id = get_current_user_id();
		$cards = $this->get_active_promotional_cards( $user_id );

		if ( empty( $cards ) ) {
			return '';
		}

		ob_start();

		foreach ( $cards as $card ) {
			// Get promotion settings.
			$promotion = $this->get_promotion( $card['promotion_id'] );
			if ( ! $promotion ) {
				continue;
			}

			// Check if shortcode notice is enabled for this promotion.
			if ( empty( $promotion['show_shortcode_notice'] ) ) {
				continue;
			}

			// Check if notice is dismissed.
			if ( $this->is_notice_dismissed( $user_id, $card['id'], 'shortcode' ) ) {
				continue;
			}

			// Get network name.
			$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card['chain_id'] );
			$network_name = $network_data ? $network_data['name'] : 'Unknown';

			// Prepare message.
			$message = ! empty( $promotion['shortcode_notice_message'] )
				? $promotion['shortcode_notice_message']
				: 'You have an unredeemed {amount} {symbol} on {network} UniVoucher promotional gift card. Card ID: {card_id}, Card Secret: {card_secret}. To redeem this card, please visit <a href="https://univoucher.com" target="_blank" rel="noopener noreferrer">univoucher.com</a>';

			$message = str_replace( '{amount}', $this->format_token_amount( $card['amount'] ), $message );
			$message = str_replace( '{symbol}', $card['token_symbol'], $message );
			$message = str_replace( '{network}', $network_name, $message );
			$message = str_replace( '{card_id}', $card['card_id'], $message );
			$message = str_replace( '{card_secret}', $card['card_secret'], $message );

			// Display notice.
			$this->render_notice( $card, $message, 'shortcode' );
		}

		return ob_get_clean();
	}

	/**
	 * Render order notice HTML (no dismiss button, no frame, no title).
	 *
	 * @param string $message Notice message.
	 */
	private function render_order_notice( $message ) {
		?>
		<div class="univoucher-order-promotion-notice">
			<?php echo wp_kses_post( $message ); ?>
		</div>
		<?php
	}

	/**
	 * Render notice HTML.
	 *
	 * @param array  $card    Card data.
	 * @param string $message Notice message.
	 * @param string $type    Notice type (website or order).
	 * @param string $title   Notice title (optional).
	 */
	private function render_notice( $card, $message, $type, $title = '' ) {
		?>
		<div class="univoucher-promotion-notice" data-card-id="<?php echo esc_attr( $card['id'] ); ?>" data-notice-type="<?php echo esc_attr( $type ); ?>">
			<?php if ( ! empty( $title ) ) : ?>
				<div class="univoucher-promotion-notice-title"><?php echo esc_html( $title ); ?></div>
			<?php endif; ?>
			<div class="univoucher-promotion-notice-message"><?php echo wp_kses_post( $message ); ?></div>
			<button class="univoucher-promotion-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'univoucher-for-woocommerce' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Get active promotional cards for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Active promotional cards.
	 */
	private function get_active_promotional_cards( $user_id ) {
		global $wpdb;
		$table = $this->database->uv_get_promotional_cards_table();

		$cards = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $cards ) ) {
			return array();
		}

		// Verify and update card status from blockchain.
		$active_cards = array();
		foreach ( $cards as $card ) {
			$updated_status = $this->verify_card_status( $card );
			if ( 'active' === $updated_status ) {
				$active_cards[] = $card;
			}
		}

		return $active_cards;
	}

	/**
	 * Verify card status from blockchain.
	 *
	 * @param array $card Card data.
	 * @return string Updated card status.
	 */
	private function verify_card_status( $card ) {
		// Call UniVoucher API to check card status.
		$api_url = 'https://api.univoucher.com/v1/cards/single?id=' . urlencode( $card['card_id'] );
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $card['status'];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $response_code || empty( $data ) ) {
			return $card['status'];
		}

		// Check card status from API response
		if ( isset( $data['status'] ) ) {
			$api_status = strtolower( $data['status'] );
			if ( 'redeemed' === $api_status ) {
				$new_status = 'redeemed';
			} elseif ( 'cancelled' === $api_status ) {
				$new_status = 'cancelled';
			} else {
				// Check active flag
				$new_status = ( isset( $data['active'] ) && $data['active'] ) ? 'active' : 'inactive';
			}
		} else {
			// Fallback to active flag if status not present
			$new_status = ( isset( $data['active'] ) && $data['active'] ) ? 'active' : 'inactive';
		}

		// Update card status in database if changed.
		if ( $new_status !== $card['status'] ) {
			global $wpdb;
			$table = $this->database->uv_get_promotional_cards_table();

			$update_data = array( 'status' => $new_status );
			$format = array( '%s' );

			if ( 'redeemed' === $new_status ) {
				$update_data['redeemed_at'] = current_time( 'mysql' );
				$format[] = '%s';
			}

			$wpdb->update(
				$table,
				$update_data,
				array( 'id' => $card['id'] ),
				$format,
				array( '%d' )
			);
		}

		return $new_status;
	}

	/**
	 * Get promotion by ID.
	 *
	 * @param int $promotion_id Promotion ID.
	 * @return array|null Promotion data or null if not found.
	 */
	private function get_promotion( $promotion_id ) {
		global $wpdb;
		$table = $this->database->uv_get_promotions_table();

		$promotion = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$promotion_id
			),
			ARRAY_A
		);

		return $promotion;
	}

	/**
	 * Check if notice is dismissed.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $card_id Card ID.
	 * @param string $type    Notice type (website or order).
	 * @return bool True if dismissed, false otherwise.
	 */
	private function is_notice_dismissed( $user_id, $card_id, $type ) {
		$dismissed_notices = get_user_meta( $user_id, 'univoucher_dismissed_notices', true );
		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = array();
		}

		$key = $card_id . '_' . $type;
		if ( ! isset( $dismissed_notices[ $key ] ) ) {
			return false;
		}

		$dismissed_time = $dismissed_notices[ $key ];
		$seven_days_ago = strtotime( '-7 days' );

		// If dismissed more than 7 days ago, show notice again.
		if ( $dismissed_time < $seven_days_ago ) {
			unset( $dismissed_notices[ $key ] );
			update_user_meta( $user_id, 'univoucher_dismissed_notices', $dismissed_notices );
			return false;
		}

		return true;
	}

	/**
	 * Format token amount to 6 decimals, removing trailing zeros.
	 *
	 * @param string $amount Token amount.
	 * @return string Formatted amount.
	 */
	private function format_token_amount( $amount ) {
		// Format to 6 decimals
		$formatted = number_format( (float) $amount, 6, '.', '' );
		// Remove trailing zeros
		$formatted = rtrim( $formatted, '0' );
		// Remove trailing decimal point if no decimals remain
		$formatted = rtrim( $formatted, '.' );
		return $formatted;
	}

	/**
	 * AJAX handler for dismissing notices.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'univoucher_dismiss_notice', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'User not logged in.', 'univoucher-for-woocommerce' ) ) );
		}

		$user_id = get_current_user_id();
		$card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( ! $card_id || ! in_array( $type, array( 'website', 'order', 'shortcode' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'univoucher-for-woocommerce' ) ) );
		}

		$dismissed_notices = get_user_meta( $user_id, 'univoucher_dismissed_notices', true );
		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = array();
		}

		$key = $card_id . '_' . $type;
		$dismissed_notices[ $key ] = time();

		update_user_meta( $user_id, 'univoucher_dismissed_notices', $dismissed_notices );

		wp_send_json_success( array( 'message' => esc_html__( 'Notice dismissed.', 'univoucher-for-woocommerce' ) ) );
	}
}
