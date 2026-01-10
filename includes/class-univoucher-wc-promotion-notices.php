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
		// Display account notice on My Account pages (except order pages).
		add_action( 'woocommerce_account_content', array( $this, 'display_account_notice' ), 1 );

		// Display order page notice.
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_order_notice' ), 1, 1 );

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
				position: relative;
				padding: 15px 40px 15px 15px;
				margin: 15px auto;
				max-width: 1200px;
				border: 1px solid currentColor;
				border-radius: 4px;
				font-size: 14px;
				line-height: 1.6;
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
				position: absolute;
				top: 10px;
				right: 10px;
				background: transparent;
				border: none;
				font-size: 20px;
				cursor: pointer;
				padding: 0;
				width: 24px;
				height: 24px;
				line-height: 1;
				color: inherit;
				opacity: 0.7;
			}
			.univoucher-promotion-notice-dismiss:hover {
				opacity: 1;
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
		";
	}

	/**
	 * Display account notice for active promotional cards.
	 */
	public function display_account_notice() {
		// Don't show on order pages.
		if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
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
	 * Display order page notice for active promotional cards.
	 *
	 * @param int $order_id Order ID.
	 */
	public function display_order_notice( $order_id ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

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

			// Check if order notice is enabled for this promotion.
			if ( empty( $promotion['show_order_notice'] ) ) {
				continue;
			}

			// Check if notice is dismissed.
			if ( $this->is_notice_dismissed( $user_id, $card['id'], 'order' ) ) {
				continue;
			}

			// Get network name.
			$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card['chain_id'] );
			$network_name = $network_data ? $network_data['name'] : 'Unknown';

			// Prepare title and message.
			$title = ! empty( $promotion['order_notice_title'] )
				? $promotion['order_notice_title']
				: 'You have got a free gift';

			$message = ! empty( $promotion['order_notice_message'] )
				? $promotion['order_notice_message']
				: 'Thank you for being our customer, please enjoy the free {amount} {symbol} on {network} UniVoucher gift card.<br><br>
<strong>Card Value:</strong> {amount} {symbol}<br>
<strong>Card ID:</strong> {card_id}<br>
<strong>Card Secret:</strong> {card_secret}<br>
<strong>Network:</strong> {network}<br><br>
To redeem this card, please visit <a href="https://univoucher.com" target="_blank" rel="noopener noreferrer">univoucher.com</a>';

			$message = str_replace( '{amount}', $this->format_token_amount( $card['amount'] ), $message );
			$message = str_replace( '{symbol}', $card['token_symbol'], $message );
			$message = str_replace( '{network}', $network_name, $message );
			$message = str_replace( '{card_id}', $card['card_id'], $message );
			$message = str_replace( '{card_secret}', $card['card_secret'], $message );

			// Display notice with title.
			$this->render_notice( $card, $message, 'order', $title );
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
		// Get UniVoucher API key.
		$api_key = get_option( 'univoucher_wc_api_key', '' );
		if ( empty( $api_key ) ) {
			return $card['status'];
		}

		// Call UniVoucher API to check card status.
		$api_url = 'https://api.univoucher.com/v1/cards/single?id=' . urlencode( $card['card_id'] );
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
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

		// Check if card is redeemed or cancelled
		$is_redeemed = isset( $data['isRedeemed'] ) && $data['isRedeemed'];
		$is_cancelled = isset( $data['isCancelled'] ) && $data['isCancelled'];

		if ( $is_redeemed ) {
			$new_status = 'redeemed';
		} elseif ( $is_cancelled ) {
			$new_status = 'cancelled';
		} else {
			$new_status = 'active';
		}

		// Update card status in database if changed.
		if ( $new_status !== $card['status'] ) {
			global $wpdb;
			$table = $this->database->uv_get_promotional_cards_table();

			$update_data = array( 'status' => $new_status );
			if ( 'redeemed' === $new_status ) {
				$update_data['redeemed_at'] = current_time( 'mysql' );
			}

			$wpdb->update(
				$table,
				$update_data,
				array( 'id' => $card['id'] ),
				array( '%s', '%s' ),
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
