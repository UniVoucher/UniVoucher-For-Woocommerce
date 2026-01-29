<?php
/**
 * UniVoucher For WooCommerce Database
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Database
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Database class.
 */
class UniVoucher_WC_Database {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Database
	 */
	protected static $_instance = null;

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '3.5.8';

	/**
	 * Gift cards table name.
	 *
	 * @var string
	 */
	private $uv_gift_cards_table;

	/**
	 * Promotions table name.
	 *
	 * @var string
	 */
	private $uv_promotions_table;

	/**
	 * Promotion user tracking table name.
	 *
	 * @var string
	 */
	private $uv_promotion_user_tracking_table;

	/**
	 * Promotional gift cards table name.
	 *
	 * @var string
	 */
	private $uv_promotional_cards_table;

	/**
	 * Main UniVoucher_WC_Database Instance.
	 *
	 * @return UniVoucher_WC_Database - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Database Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->uv_gift_cards_table = $wpdb->prefix . 'univoucher_gift_cards';
		$this->uv_promotions_table = $wpdb->prefix . 'univoucher_promotions';
		$this->uv_promotion_user_tracking_table = $wpdb->prefix . 'univoucher_promotion_user_tracking';
		$this->uv_promotional_cards_table = $wpdb->prefix . 'univoucher_promotional_cards';

		// Check database version immediately on instantiation.
		$this->uv_check_database_version();
	}

	/**
	 * Get gift cards table name.
	 *
	 * @return string
	 */
	public function uv_get_gift_cards_table() {
		return $this->uv_gift_cards_table;
	}

	/**
	 * Get promotions table name.
	 *
	 * @return string
	 */
	public function uv_get_promotions_table() {
		return $this->uv_promotions_table;
	}

	/**
	 * Get promotion user tracking table name.
	 *
	 * @return string
	 */
	public function uv_get_promotion_user_tracking_table() {
		return $this->uv_promotion_user_tracking_table;
	}

	/**
	 * Get promotional cards table name.
	 *
	 * @return string
	 */
	public function uv_get_promotional_cards_table() {
		return $this->uv_promotional_cards_table;
	}

	/**
	 * Check if database needs updating.
	 */
	public function uv_check_database_version() {
		$installed_version = get_option( 'univoucher_wc_db_version', false );

		// If no version is set or version doesn't match, update database.
		if ( $installed_version === false || $installed_version !== self::DB_VERSION ) {
			$this->create_tables();
			update_option( 'univoucher_wc_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create database tables.
	 */
	public function create_tables() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		// Gift cards table.
		$sql = "CREATE TABLE $this->uv_gift_cards_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			status enum('available','sold','inactive') NOT NULL DEFAULT 'available',
			delivery_status enum('never delivered','delivered','returned after delivery') NOT NULL DEFAULT 'never delivered',
			card_id varchar(20) NOT NULL,
			card_secret varchar(255) NOT NULL,
			chain_id bigint(20) unsigned NOT NULL,
			token_address varchar(42) DEFAULT NULL,
			token_symbol varchar(20) NOT NULL,
			token_type enum('native','erc20') NOT NULL DEFAULT 'native',
			token_decimals tinyint unsigned NOT NULL DEFAULT 18,
			amount decimal(36,18) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY card_id (card_id),
			UNIQUE KEY card_secret (card_secret),
			KEY product_id (product_id),
			KEY order_id (order_id),
			KEY chain_id (chain_id),
			KEY token_address (token_address),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql );

		// Promotions table.
		$sql_promotions = "CREATE TABLE $this->uv_promotions_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			chain_id bigint(20) unsigned NOT NULL,
			token_type enum('native','erc20') NOT NULL DEFAULT 'native',
			token_address varchar(42) DEFAULT NULL,
			token_symbol varchar(20) NOT NULL,
			token_decimals tinyint unsigned NOT NULL DEFAULT 18,
			card_amount decimal(36,18) NOT NULL,
			rules longtext NOT NULL,
			max_per_user int unsigned NOT NULL DEFAULT 0,
			max_total int unsigned NOT NULL DEFAULT 0,
			total_issued int unsigned NOT NULL DEFAULT 0,
			send_separate_email tinyint(1) NOT NULL DEFAULT 0,
			email_subject varchar(255) DEFAULT NULL,
			email_template longtext DEFAULT NULL,
			manual_email_subject varchar(255) DEFAULT NULL,
			manual_email_template longtext DEFAULT NULL,
			show_account_notice tinyint(1) NOT NULL DEFAULT 0,
			account_notice_message text DEFAULT NULL,
			show_order_notice tinyint(1) NOT NULL DEFAULT 0,
			order_notice_message longtext DEFAULT NULL,
			show_shortcode_notice tinyint(1) NOT NULL DEFAULT 0,
			shortcode_notice_message text DEFAULT NULL,
			card_expiration_days int unsigned NOT NULL DEFAULT 0,
			auto_cancel_expired tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY is_active (is_active),
			KEY chain_id (chain_id),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql_promotions );

		// Promotion user tracking table.
		$sql_user_tracking = "CREATE TABLE $this->uv_promotion_user_tracking_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			promotion_id bigint(20) unsigned NOT NULL,
			times_received int unsigned NOT NULL DEFAULT 1,
			last_received_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_promotion (user_id, promotion_id),
			KEY promotion_id (promotion_id),
			KEY user_id (user_id)
		) $charset_collate;";

		dbDelta( $sql_user_tracking );

		// Promotional gift cards table.
		$sql_promotional_cards = "CREATE TABLE $this->uv_promotional_cards_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			promotion_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			card_id varchar(20) NOT NULL,
			card_secret varchar(255) NOT NULL,
			chain_id bigint(20) unsigned NOT NULL,
			token_address varchar(42) DEFAULT NULL,
			token_symbol varchar(20) NOT NULL,
			token_type enum('native','erc20') NOT NULL DEFAULT 'native',
			token_decimals tinyint unsigned NOT NULL DEFAULT 18,
			amount decimal(36,18) NOT NULL,
			transaction_hash varchar(66) DEFAULT NULL,
			status enum('active','redeemed','cancelled','expired') NOT NULL DEFAULT 'active',
			redeemed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY card_id (card_id),
			UNIQUE KEY card_secret (card_secret),
			KEY promotion_id (promotion_id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql_promotional_cards );
	}


	/**
	 * Validate gift card data.
	 *
	 * @param array $data Gift card data.
	 * @return WP_Error|bool
	 */
	public function uv_validate_gift_card_data( $data ) {
		$errors = new WP_Error();

		// Required fields.
		$required_fields = array( 'product_id', 'card_id', 'card_secret', 'chain_id', 'token_symbol', 'token_type', 'token_decimals', 'amount' );
		
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) && $data[ $field ] !== 0 ) {
				$errors->add( 'missing_field', sprintf( 'Field %s is required.', $field ) );
			}
		}

		// Validate product_id.
		if ( ! empty( $data['product_id'] ) && ! is_numeric( $data['product_id'] ) ) {
			$errors->add( 'invalid_product_id', 'Product ID must be numeric.' );
		}

		// Validate order_id (if provided).
		if ( ! empty( $data['order_id'] ) && ! is_numeric( $data['order_id'] ) ) {
			$errors->add( 'invalid_order_id', 'Order ID must be numeric.' );
		}

		// Validate card_id format (numeric string).
		if ( ! empty( $data['card_id'] ) && ! preg_match( '/^[0-9]+$/', $data['card_id'] ) ) {
			$errors->add( 'invalid_card_id', 'Card ID must be numeric.' );
		}

		// Validate card_secret format (XXXXX-XXXXX-XXXXX-XXXXX).
		if ( ! empty( $data['card_secret'] ) && ! preg_match( '/^[A-Z]{5}-[A-Z]{5}-[A-Z]{5}-[A-Z]{5}$/', $data['card_secret'] ) ) {
			$errors->add( 'invalid_card_secret', 'Card secret must be in format XXXXX-XXXXX-XXXXX-XXXXX (20 uppercase letters with hyphens).' );
		}

		// Validate chain_id.
		if ( ! empty( $data['chain_id'] ) && ! is_numeric( $data['chain_id'] ) ) {
			$errors->add( 'invalid_chain_id', 'Chain ID must be numeric.' );
		}

		// Validate token_address for ERC-20 tokens.
		if ( ! empty( $data['token_type'] ) && $data['token_type'] === 'erc20' ) {
			if ( empty( $data['token_address'] ) ) {
				$errors->add( 'missing_token_address', 'Token address is required for ERC-20 tokens.' );
			} elseif ( ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $data['token_address'] ) ) {
				$errors->add( 'invalid_token_address', 'Invalid token address format.' );
			}
		}

		// Validate token_decimals.
		if ( isset( $data['token_decimals'] ) && ( ! is_numeric( $data['token_decimals'] ) || $data['token_decimals'] < 0 || $data['token_decimals'] > 255 ) ) {
			$errors->add( 'invalid_token_decimals', 'Token decimals must be a number between 0 and 255.' );
		}

		// Validate amount.
		if ( ! empty( $data['amount'] ) && ( ! is_numeric( $data['amount'] ) || $data['amount'] <= 0 ) ) {
			$errors->add( 'invalid_amount', 'Amount must be a positive number.' );
		}

		// Validate token_type.
		if ( ! empty( $data['token_type'] ) && ! in_array( $data['token_type'], array( 'native', 'erc20' ), true ) ) {
			$errors->add( 'invalid_token_type', 'Token type must be either "native" or "erc20".' );
		}

		// Validate status.
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], array( 'available', 'sold', 'inactive' ), true ) ) {
			$errors->add( 'invalid_status', 'Invalid status.' );
		}

		// Validate delivery_status.
		if ( ! empty( $data['delivery_status'] ) && ! in_array( $data['delivery_status'], array( 'never delivered', 'delivered', 'returned after delivery' ), true ) ) {
			$errors->add( 'invalid_delivery_status', 'Invalid delivery status.' );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Sanitize gift card data.
	 *
	 * @param array $data Gift card data.
	 * @return array
	 */
	public function uv_sanitize_gift_card_data( $data ) {
		$sanitized = array();

		// String fields.
		$string_fields = array( 'card_id', 'card_secret', 'token_address', 'token_symbol', 'token_type', 'status', 'delivery_status' );
		foreach ( $string_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Numeric fields.
		$numeric_fields = array( 'product_id', 'order_id', 'chain_id', 'token_decimals' );
		foreach ( $numeric_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		// Decimal fields.
		if ( isset( $data['amount'] ) ) {
			$sanitized['amount'] = number_format( (float) $data['amount'], 18, '.', '' );
		}

		// Date fields.
		$date_fields = array( 'created_at' );
		foreach ( $date_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		return $sanitized;
	}
}