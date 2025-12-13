<?php
/**
 * UniVoucher For WooCommerce Admin Settings
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Admin_Settings class.
 */
class UniVoucher_WC_Admin_Settings {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Admin_Settings
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Admin_Settings Instance.
	 *
	 * @return UniVoucher_WC_Admin_Settings - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Admin_Settings Constructor.
	 */
	public function __construct() {
		$this->init_settings();
		$this->init_hooks();
		
		// Include settings files
		require_once plugin_dir_path( __FILE__ ) . 'settings/security-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/api-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/internal-wallet-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/card-delivery-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/content-templates-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/compatibility-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'settings/backorders-settings.php';
	}

	/**
	 * Initialize hooks for AJAX handlers.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_test_api_key', 'univoucher_ajax_test_api_key' );
		add_action( 'wp_ajax_univoucher_get_content_templates', 'univoucher_ajax_get_content_templates' );
	}

	/**
	 * Initialize settings.
	 */
	private function init_settings() {
		// Register security settings.
		register_setting(
			'univoucher_wc_security_settings',
			'univoucher_wc_database_key_backup_confirmed',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		// Register API settings.
		register_setting(
			'univoucher_wc_api_settings',
			'univoucher_wc_alchemy_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'univoucher_sanitize_api_key',
				'default'           => '',
			)
		);

		// Register internal wallet settings.
		register_setting(
			'univoucher_wc_wallet_settings',
			'univoucher_wc_wallet_private_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'univoucher_sanitize_private_key',
				'default'           => '',
			)
		);

		register_setting(
			'univoucher_wc_wallet_settings',
			'univoucher_wc_wallet_public_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Register card delivery settings.
		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_show_in_order_details',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_cards_display_position',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'before',
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_auto_complete_orders',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);



		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_send_email_cards',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_send_email_only_fully_assigned',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_email_subject',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Your UniVoucher Gift Cards - Order #{order_number}',
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_email_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '<h2>Hello {customer_name},</h2><p>Your UniVoucher gift cards are ready!</p><p><strong>Order:</strong> #{order_number}</p><div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">{cards_content}</div><p><strong>Redeem your cards at:</strong></p><ul><li><a href="https://univoucher.com" target="_blank">https://univoucher.com</a></li><li><a href="https://redeembase.com" target="_blank">https://redeembase.com</a></li></ul><p>Thank you for your purchase!</p><p>Best regards,<br>{site_name}</p>',
			)
		);

		// Register backorders settings.
		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_backorder_initial_status',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'processing',
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_auto_create_backordered_cards',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_show_unassigned_notice',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_unassigned_notice_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Your order contains gift cards that are still being processed. This page will automatically refresh once all cards are ready.', 'univoucher-for-woocommerce' ),
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_show_on_demand_limit',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_on_demand_order_limit',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_on_demand_error_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Sorry, but the maximum available quantity from {product_name} is {maximum_quantity}. You have {cart_quantity} in cart.',
			)
		);

		register_setting(
			'univoucher_wc_backorders_settings',
			'univoucher_wc_on_demand_cart_limit',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		// Register compatibility settings.
		register_setting(
			'univoucher_wc_compatibility_settings',
			'univoucher_wc_lmfwc_integration',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'univoucher_wc_compatibility_settings',
			'univoucher_wc_lmfwc_license_key_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => 'Card ID: {card_id} Card Secret: {card_secret} Network: {card_network} Abandoned on {card_abandoned}',
			)
		);

		register_setting(
			'univoucher_wc_compatibility_settings',
			'univoucher_wc_lmfwc_show_abandoned_date',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_compatibility_settings',
			'univoucher_wc_lmfwc_include_in_all_licenses',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		// Add security settings section.
		add_settings_section(
			'univoucher_wc_security_section',
			esc_html__( 'Security Settings', 'univoucher-for-woocommerce' ),
			array( $this, 'security_section_callback' ),
			'univoucher_wc_security_settings'
		);

		// Add database key backup confirmation field.
		add_settings_field(
			'univoucher_wc_database_key_backup_confirmed',
			esc_html__( 'Database Key', 'univoucher-for-woocommerce' ),
			array( $this, 'database_key_backup_callback' ),
			'univoucher_wc_security_settings',
			'univoucher_wc_security_section',
			array(
				'label_for' => 'univoucher_wc_database_key_backup_confirmed',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add API settings section (second).
		add_settings_section(
			'univoucher_wc_api_section',
			esc_html__( 'API Configuration', 'univoucher-for-woocommerce' ),
			array( $this, 'api_section_callback' ),
			'univoucher_wc_api_settings'
		);

		// Add API key field.
		add_settings_field(
			'univoucher_wc_alchemy_api_key',
			esc_html__( 'Alchemy API Key', 'univoucher-for-woocommerce' ),
			array( $this, 'alchemy_api_key_callback' ),
			'univoucher_wc_api_settings',
			'univoucher_wc_api_section',
			array(
				'label_for' => 'univoucher_wc_alchemy_api_key',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add internal wallet section.
		add_settings_section(
			'univoucher_wc_wallet_section',
			esc_html__( 'Internal Crypto Wallet Configuration', 'univoucher-for-woocommerce' ),
			array( $this, 'wallet_section_callback' ),
			'univoucher_wc_wallet_settings'
		);

		// Add wallet private key field.
		add_settings_field(
			'univoucher_wc_wallet_private_key',
			esc_html__( 'Wallet Private Key', 'univoucher-for-woocommerce' ),
			array( $this, 'wallet_private_key_callback' ),
			'univoucher_wc_wallet_settings',
			'univoucher_wc_wallet_section',
			array(
				'label_for' => 'univoucher_wc_wallet_private_key',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add wallet details field.
		add_settings_field(
			'univoucher_wc_wallet_details',
			esc_html__( 'Wallet Details', 'univoucher-for-woocommerce' ),
			array( $this, 'wallet_details_callback' ),
			'univoucher_wc_wallet_settings',
			'univoucher_wc_wallet_section',
			array(
				'label_for' => 'univoucher_wc_wallet_details',
				'class'     => 'univoucher-wc-row',
			)
		);


		// Register content template settings.
		register_setting(
			'univoucher_wc_templates_settings',
			'univoucher_wc_title_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'UniVoucher {amount} {symbol} Gift Card on {network}',
			)
		);

		register_setting(
			'univoucher_wc_templates_settings',
			'univoucher_wc_short_description_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => 'UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported',
			)
		);

		register_setting(
			'univoucher_wc_templates_settings',
			'univoucher_wc_description_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeembase.com\" target=\"_blank\">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>",
			)
		);

		// Add content templates section (fourth).
		add_settings_section(
			'univoucher_wc_content_templates_section',
			esc_html__( 'Auto-Generated Content Templates', 'univoucher-for-woocommerce' ),
			array( $this, 'content_templates_section_callback' ),
			'univoucher_wc_templates_settings'
		);

		// Add available placeholders field.
		add_settings_field(
			'univoucher_wc_available_placeholders',
			esc_html__( 'Available Placeholders', 'univoucher-for-woocommerce' ),
			array( $this, 'available_placeholders_callback' ),
			'univoucher_wc_templates_settings',
			'univoucher_wc_content_templates_section',
			array(
				'label_for' => 'univoucher_wc_available_placeholders',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add title template field.
		add_settings_field(
			'univoucher_wc_title_template',
			esc_html__( 'Product Title Template', 'univoucher-for-woocommerce' ),
			array( $this, 'title_template_callback' ),
			'univoucher_wc_templates_settings',
			'univoucher_wc_content_templates_section',
			array(
				'label_for' => 'univoucher_wc_title_template',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add short description template field.
		add_settings_field(
			'univoucher_wc_short_description_template',
			esc_html__( 'Short Description Template', 'univoucher-for-woocommerce' ),
			array( $this, 'short_description_template_callback' ),
			'univoucher_wc_templates_settings',
			'univoucher_wc_content_templates_section',
			array(
				'label_for' => 'univoucher_wc_short_description_template',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add description template field.
		add_settings_field(
			'univoucher_wc_description_template',
			esc_html__( 'Full Description Template', 'univoucher-for-woocommerce' ),
			array( $this, 'description_template_callback' ),
			'univoucher_wc_templates_settings',
			'univoucher_wc_content_templates_section',
			array(
				'label_for' => 'univoucher_wc_description_template',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add reset templates field.
		add_settings_field(
			'univoucher_wc_reset_templates',
			esc_html__( 'Reset Templates', 'univoucher-for-woocommerce' ),
			array( $this, 'reset_templates_callback' ),
			'univoucher_wc_templates_settings',
			'univoucher_wc_content_templates_section',
			array(
				'label_for' => 'univoucher_wc_reset_templates',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add card delivery settings section.
		add_settings_section(
			'univoucher_wc_delivery_section',
			esc_html__( 'Cards delivery for completed orders', 'univoucher-for-woocommerce' ),
			array( $this, 'delivery_section_callback' ),
			'univoucher_wc_delivery_settings'
		);

		// Add auto-complete orders field.
		add_settings_field(
			'univoucher_wc_auto_complete_orders',
			esc_html__( 'Order Auto-Completion', 'univoucher-for-woocommerce' ),
			array( $this, 'auto_complete_orders_callback' ),
			'univoucher_wc_delivery_settings',
			'univoucher_wc_delivery_section',
			array(
				'label_for' => 'univoucher_wc_auto_complete_orders',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add show customer cards field.
		add_settings_field(
			'univoucher_wc_show_in_order_details',
			esc_html__( 'Show in Order Details', 'univoucher-for-woocommerce' ),
			array( $this, 'show_customer_cards_callback' ),
			'univoucher_wc_delivery_settings',
			'univoucher_wc_delivery_section',
			array(
				'label_for' => 'univoucher_wc_show_in_order_details',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add email delivery field.
		add_settings_field(
			'univoucher_wc_send_email_cards',
			esc_html__( 'Email Delivery', 'univoucher-for-woocommerce' ),
			array( $this, 'send_email_cards_callback' ),
			'univoucher_wc_delivery_settings',
			'univoucher_wc_delivery_section',
			array(
				'label_for' => 'univoucher_wc_send_email_cards',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add backorders settings section.
		add_settings_section(
			'univoucher_wc_backorders_section',
			esc_html__( 'On-Demand (Backorders)', 'univoucher-for-woocommerce' ),
			array( $this, 'backorders_section_callback' ),
			'univoucher_wc_backorders_settings'
		);

		// Add auto-create backordered cards field.
		add_settings_field(
			'univoucher_wc_auto_create_backordered_cards',
			esc_html__( 'On-Demand automation', 'univoucher-for-woocommerce' ),
			array( $this, 'auto_create_backordered_cards_callback' ),
			'univoucher_wc_backorders_settings',
			'univoucher_wc_backorders_section',
			array(
				'label_for' => 'univoucher_wc_auto_create_backordered_cards',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add backorder initial status field.
		add_settings_field(
			'univoucher_wc_backorder_initial_status',
			esc_html__( 'On-Demand Order Status', 'univoucher-for-woocommerce' ),
			array( $this, 'backorder_initial_status_callback' ),
			'univoucher_wc_backorders_settings',
			'univoucher_wc_backorders_section',
			array(
				'label_for' => 'univoucher_wc_backorder_initial_status',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add on-demand limit settings field.
		add_settings_field(
			'univoucher_wc_on_demand_limit_settings',
			esc_html__( 'On-Demand Limit', 'univoucher-for-woocommerce' ),
			array( $this, 'on_demand_limit_settings_callback' ),
			'univoucher_wc_backorders_settings',
			'univoucher_wc_backorders_section',
			array(
				'class' => 'univoucher-wc-row',
			)
		);

		// Add compatibility settings section.
		add_settings_section(
			'univoucher_wc_compatibility_section',
			esc_html__( 'Third-Party Plugin Integrations', 'univoucher-for-woocommerce' ),
			array( $this, 'compatibility_section_callback' ),
			'univoucher_wc_compatibility_settings'
		);

		// Add license manager integrations field.
		add_settings_field(
			'univoucher_wc_lmfwc_integration',
			esc_html__( 'License Manager Integration', 'univoucher-for-woocommerce' ),
			array( $this, 'lmfwc_integration_callback' ),
			'univoucher_wc_compatibility_settings',
			'univoucher_wc_compatibility_section',
			array(
				'label_for' => 'univoucher_wc_lmfwc_integration',
				'class'     => 'univoucher-wc-row',
			)
		);


	}

	/**
	 * Security section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function security_section_callback( $args ) {
		univoucher_security_section_callback( $args );
	}

	/**
	 * Database key backup confirmation field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function database_key_backup_callback( $args ) {
		univoucher_database_key_backup_callback( $args );
	}

	/**
	 * API section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function api_section_callback( $args ) {
		univoucher_api_section_callback( $args );
	}

	/**
	 * Alchemy API key field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function alchemy_api_key_callback( $args ) {
		univoucher_alchemy_api_key_callback( $args );
	}

	/**
	 * Wallet section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function wallet_section_callback( $args ) {
		univoucher_internal_wallet_section_callback( $args );
	}

	/**
	 * Wallet private key field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function wallet_private_key_callback( $args ) {
		univoucher_wallet_private_key_callback( $args );
	}

	/**
	 * Wallet details field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function wallet_details_callback( $args ) {
		univoucher_wallet_details_callback( $args );
	}

	/**
	 * Content Templates section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function content_templates_section_callback( $args ) {
		univoucher_content_templates_section_callback( $args );
	}

	/**
	 * Delivery section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function delivery_section_callback( $args ) {
		univoucher_delivery_section_callback( $args );
	}

	/**
	 * Available placeholders field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function available_placeholders_callback( $args ) {
		univoucher_available_placeholders_callback( $args );
	}

	/**
	 * Title template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function title_template_callback( $args ) {
		univoucher_title_template_callback( $args );
	}

	/**
	 * Short description template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function short_description_template_callback( $args ) {
		univoucher_short_description_template_callback( $args );
	}

	/**
	 * Description template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function description_template_callback( $args ) {
		univoucher_description_template_callback( $args );
	}

	/**
	 * Show customer cards field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function show_customer_cards_callback( $args ) {
		univoucher_show_customer_cards_callback( $args );
	}

	/**
	 * Send email cards field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function send_email_cards_callback( $args ) {
		univoucher_send_email_cards_callback( $args );
	}

	/**
	 * Reset templates field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function reset_templates_callback( $args ) {
		univoucher_reset_templates_callback( $args );
	}

	/**
	 * Sanitize checkbox input.
	 *
	 * @param mixed $input The input value.
	 * @return bool The sanitized value.
	 */
	public function sanitize_checkbox( $input ) {
		return (bool) $input;
	}

	/**
	 * Auto-complete orders field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function auto_complete_orders_callback( $args ) {
		univoucher_auto_complete_orders_callback( $args );
	}

	/**
	 * Compatibility section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function compatibility_section_callback( $args ) {
		univoucher_compatibility_section_callback( $args );
	}

	/**
	 * License Manager integration field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function lmfwc_integration_callback( $args ) {
		univoucher_lmfwc_integration_callback( $args );
	}

	/**
	 * Backorders section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function backorders_section_callback( $args ) {
		univoucher_backorders_section_callback( $args );
	}

	/**
	 * Backorder initial status field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function backorder_initial_status_callback( $args ) {
		univoucher_backorder_initial_status_callback( $args );
	}

	/**
	 * Auto-create backordered cards field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function auto_create_backordered_cards_callback( $args ) {
		univoucher_auto_create_backordered_cards_callback( $args );
	}

	/**
	 * On-demand limit settings field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function on_demand_limit_settings_callback( $args ) {
		univoucher_on_demand_limit_settings_callback( $args );
	}
}