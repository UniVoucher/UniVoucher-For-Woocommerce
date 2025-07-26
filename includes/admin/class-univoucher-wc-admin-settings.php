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
	}

	/**
	 * Initialize hooks for AJAX handlers.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_sync_single_product', array( $this, 'ajax_sync_single_product' ) );
		add_action( 'wp_ajax_univoucher_sync_all_products', array( $this, 'ajax_sync_all_products' ) );
		add_action( 'wp_ajax_univoucher_test_api_key', array( $this, 'ajax_test_api_key' ) );
		add_action( 'wp_ajax_univoucher_get_content_templates', array( $this, 'ajax_get_content_templates' ) );
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
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
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
			'univoucher_wc_auto_complete_orders',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_delivery_settings',
			'univoucher_wc_require_processing_if_missing_cards',
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
				'default'           => '<h2>Hello {customer_name},</h2><p>Your UniVoucher gift cards are ready!</p><p><strong>Order:</strong> #{order_number}</p><div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">{cards_content}</div><p><strong>Redeem your cards at:</strong></p><ul><li><a href="https://univoucher.com" target="_blank">https://univoucher.com</a></li><li><a href="https://redeemnow.xyz" target="_blank">https://redeemnow.xyz</a></li></ul><p>Thank you for your purchase!</p><p>Best regards,<br>{site_name}</p>',
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

		// Add stock sync section (third).
		add_settings_section(
			'univoucher_wc_stock_sync_section',
			esc_html__( 'Stock Synchronization', 'univoucher-for-woocommerce' ),
			array( $this, 'stock_sync_section_callback' ),
			'univoucher_wc_stock_settings'
		);

		// Add stock sync field.
		add_settings_field(
			'univoucher_wc_stock_sync',
			esc_html__( 'Sync Product Stock', 'univoucher-for-woocommerce' ),
			array( $this, 'stock_sync_callback' ),
			'univoucher_wc_stock_settings',
			'univoucher_wc_stock_sync_section',
			array(
				'label_for' => 'univoucher_wc_stock_sync',
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
				'default'           => "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeemnow.xyz\" target=\"_blank\">https://redeemnow.xyz</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>",
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
	}

	/**
	 * Security section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function security_section_callback( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Manage your security settings and database encryption key.', 'univoucher-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Database key backup confirmation field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function database_key_backup_callback( $args ) {
		$database_key = UniVoucher_For_WooCommerce::uv_get_database_key();
		$backup_confirmed = get_option( 'univoucher_wc_database_key_backup_confirmed', false );
		$key_file_path = ABSPATH . 'wp-includes/univoucher-database-security-key.php';
		?>
		
		<?php if ( $database_key ) : ?>
			<div class="univoucher-settings-box">
				<h4>
					<?php esc_html_e( 'Database Key Status', 'univoucher-for-woocommerce' ); ?>
				</h4>
				<p style="margin: 0 0 10px 0;">
					<span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 5px;"></span>
					<?php esc_html_e( 'Database security key is configured and active', 'univoucher-for-woocommerce' ); ?>
				</p>
				<p style="margin: 0; font-size: 12px; color: #6c757d;">
					<strong><?php esc_html_e( 'Key file location:', 'univoucher-for-woocommerce' ); ?></strong> 
					<code><?php echo esc_html( $key_file_path ); ?></code>
				</p>
				<p style="margin: 5px 0 0 0; font-size: 11px; color: #28a745;">
					<span class="dashicons dashicons-lock" style="font-size: 11px; margin-right: 3px;"></span>
					<?php esc_html_e( 'Stored in wp-includes for enhanced security', 'univoucher-for-woocommerce' ); ?>
				</p>
				<h5 style="margin: 15px 0 5px 0; font-size: 14px; color: #495057;">
					<?php esc_html_e( 'What is Database Key?', 'univoucher-for-woocommerce' ); ?>
				</h5>
				<p style="margin: 0; font-size: 13px; color: #495057;">
					<?php esc_html_e( 'The database key is used to encrypt and store your gift card inventory encrypted ( unreadable ) in the database. It is also used to decrypt (and show the real key) in the WP admin or user dashboard. Keep this key secure and always maintain a backup. If this key is lost, you will not be able to access any of your existing gift cards, and they will become permanently unusable.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-light">
				<h4>
					<?php esc_html_e( 'Backup Database Key', 'univoucher-for-woocommerce' ); ?>
				</h4>
				<div style="margin-bottom: 15px;">
					<input 
						type="password" 
						id="database-key-display" 
						value="<?php echo esc_attr( $database_key ); ?>" 
						readonly 
						style="width: 100%; max-width: 600px; font-family: monospace; font-size: 12px;"
					/>
				</div>
				<div style="margin-bottom: 15px;">
					<button type="button" class="button" id="toggle-database-key">
						<?php esc_html_e( 'Show/Hide Key', 'univoucher-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button" id="copy-database-key">
						<?php esc_html_e( 'Copy Key', 'univoucher-for-woocommerce' ); ?>
					</button>
				</div>

				<div class="univoucher-settings-box-info">
					<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $args['label_for'] ); ?>"
							name="<?php echo esc_attr( $args['label_for'] ); ?>"
							value="1"
							<?php checked( $backup_confirmed, true ); ?>
							style="margin-right: 10px;"
						/>
						<strong style="color: #0c5460;">
							<?php esc_html_e( 'I have saved a copy (backup) of the database key in a safe place', 'univoucher-for-woocommerce' ); ?>
						</strong>
					</label>
					<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
						<?php esc_html_e( 'Check this box to confirm you have safely backed up your database key. This will hide backup reminder notices throughout the admin area.', 'univoucher-for-woocommerce' ); ?>
					</p>
				</div>

				<div class="univoucher-settings-box-danger">
					<h4>
						<span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
						<?php esc_html_e( 'CRITICAL WARNINGS', 'univoucher-for-woocommerce' ); ?>
					</h4>
					<ul style="margin: 0; padding-left: 20px;">
						<li><?php esc_html_e( 'NEVER delete the database key file', 'univoucher-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'NEVER manually edit the key in the file', 'univoucher-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'ALWAYS keep a secure backup of this key', 'univoucher-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'If lost, NO ONE can recover your encrypted gift cards', 'univoucher-for-woocommerce' ); ?></li>
					</ul>
				</div>
			</div>
		<?php else : ?>
			<div class="univoucher-settings-box-error">
				<h4>
					<span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
					<?php esc_html_e( 'Database Key Not Found', 'univoucher-for-woocommerce' ); ?>
				</h4>
				<p style="margin: 0; color: #721c24;">
					<?php esc_html_e( 'The database security key file is missing. Please deactivate and reactivate the plugin to generate a new key.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>
		<?php endif; ?>




		<?php
	}

	/**
	 * API section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function api_section_callback( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Configure your API keys and settings for blockchain interactions.', 'univoucher-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Alchemy API key field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function alchemy_api_key_callback( $args ) {
		$api_key = get_option( 'univoucher_wc_alchemy_api_key', '' );
		?>
		
		<!-- Alchemy API explanation -->
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Why UniVoucher Needs Alchemy API:', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'UniVoucher uses Alchemy to fetch ERC20 token details such as token name, symbol, decimals, and other metadata when configuring products.', 'univoucher-for-woocommerce' ); ?>
				</p>
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Alchemy provides reliable, fast blockchain RPC (Remote Procedure Call) endpoints that allow UniVoucher to query token information from multiple blockchain networks.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-success">
				<strong style="color: #27ae60;">
					<span class="dashicons dashicons-yes-alt" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Free Tier Available:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #155724;">
					<?php esc_html_e( 'Alchemy offers a generous free tier that is sufficient for most stores. No credit card required for signup.', 'univoucher-for-woocommerce' ); ?>
				</span>
			</div>

			<div class="univoucher-settings-box-info">
				<strong style="color: #0c5460;">
					<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Step-by-Step Setup:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<ol style="margin: 8px 0 0 20px; font-size: 13px; color: #0c5460;">
					<li>
						<?php
						printf(
							/* translators: %1$s: opening link tag, %2$s: closing link tag */
							esc_html__( 'Create free account at %1$sAlchemy.com%2$s', 'univoucher-for-woocommerce' ),
							'<a href="https://www.alchemy.com/" target="_blank" rel="noopener" style="color: #0c5460; text-decoration: underline;">',
							'</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Create new app', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Select Use case: "Marketplace"', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Choose chains: "Enable all EVM" chains', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Activate services: "Token API"', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Copy your API key and paste it below', 'univoucher-for-woocommerce' ); ?></li>
				</ol>
			</div>
		</div>
		
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( $args['label_for'] ); ?>"
			value="<?php echo esc_attr( $api_key ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Enter your Alchemy API key', 'univoucher-for-woocommerce' ); ?>"
			autocomplete="off"
			style="font-family: monospace;"
		/>
		<button type="button" class="button" id="toggle-api-key">
			<?php esc_html_e( 'Show/Hide', 'univoucher-for-woocommerce' ); ?>
		</button>
		
		<div id="api-key-status" style="margin-top: 10px;">
			<?php if ( empty( $api_key ) ) : ?>
				<p class="description">
					<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
					<?php esc_html_e( 'API key is required for fetching ERC20 token details when configuring products.', 'univoucher-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>
		</div>
		
		<div id="api-test-section" style="margin-top: 10px; <?php echo empty( $api_key ) ? 'display: none;' : ''; ?>">
			<button type="button" class="button" id="test-api-key">
				<?php esc_html_e( 'Test API Connection', 'univoucher-for-woocommerce' ); ?>
			</button>
			<div id="api-test-result" style="margin-top: 10px; display: none;"></div>
		</div>

		<?php
	}

	/**
	 * Stock sync section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function stock_sync_section_callback( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Synchronize WooCommerce stock levels with your UniVoucher gift card inventory.', 'univoucher-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Stock sync field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function stock_sync_callback( $args ) {
		global $wpdb;
		
		// Get all UniVoucher enabled products
		$univoucher_products = $wpdb->get_results(
			"SELECT p.ID, p.post_title 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
				AND p.post_status = 'publish'
				AND pm.meta_key = '_univoucher_enabled' 
				AND pm.meta_value = 'yes'
			ORDER BY p.post_title ASC"
		);

		$total_products = count( $univoucher_products );
		?>
		
		<!-- How it works explanation -->
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'UniVoucher Automatic Stock Management:', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'All UniVoucher-enabled products automatically activate WooCommerce stock management and cannot be disabled.', 'univoucher-for-woocommerce' ); ?>
				</p>
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'The plugin automatically assigns/unassigns gift cards for all order scenarios including order adjustments, refunds, cancellations, backorders, etc.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-highlight">
				<strong>
					<?php esc_html_e( 'Stock Formula: WooCommerce Stock = Available Cards - Missing Cards', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</div>

			<div style="margin-bottom: 15px;">
				<div style="margin-bottom: 10px;">
					<strong style="color: #27ae60;"><?php esc_html_e( 'Available Cards:', 'univoucher-for-woocommerce' ); ?></strong>
					<span style="font-size: 13px;"><?php esc_html_e( 'Gift cards with a status of "Available" in the UniVoucher inventory ready to be assigned to an order', 'univoucher-for-woocommerce' ); ?></span>
				</div>
				<div style="margin-bottom: 10px;">
					<strong style="color: #f39c12;"><?php esc_html_e( 'Missing Cards:', 'univoucher-for-woocommerce' ); ?></strong>
					<span style="font-size: 13px;"><?php esc_html_e( 'Cards needed for active orders in case of backorders or manually unassigned cards', 'univoucher-for-woocommerce' ); ?></span>
				</div>
			</div>

			<div class="univoucher-settings-box-warning">
				<strong style="color: #856404;"><?php esc_html_e( 'Note:', 'univoucher-for-woocommerce' ); ?></strong>
				<span style="font-size: 13px; color: #856404;">
					<?php esc_html_e( 'Negative stock is normal when missing cards exceed available inventory (indicates backorder situation).', 'univoucher-for-woocommerce' ); ?>
				</span>
			</div>

			<div class="univoucher-settings-box-info">
				<strong style="color: #0c5460;">
					<span class="dashicons dashicons-admin-tools" style="margin-right: 3px; font-size: 16px;"></span>
					<?php esc_html_e( 'When to Sync:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #0c5460;">
					<?php esc_html_e( 'Use manual sync if stock appears incorrect due to database issues or plugin conflicts.', 'univoucher-for-woocommerce' ); ?>
				</span>
			</div>
		</div>
		
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Individual Product Sync', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<?php if ( empty( $univoucher_products ) ) : ?>
				<p style="margin: 0; color: #6c757d;">
					<span class="dashicons dashicons-info" style="color: #17a2b8; margin-right: 5px;"></span>
					<?php esc_html_e( 'No UniVoucher enabled products found. Enable UniVoucher on products first.', 'univoucher-for-woocommerce' ); ?>
				</p>
			<?php else : ?>
				<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
					<select id="univoucher-product-select" style="min-width: 300px;">
						<option value=""><?php esc_html_e( 'Select a product to sync...', 'univoucher-for-woocommerce' ); ?></option>
						<?php foreach ( $univoucher_products as $product ) : ?>
							<option value="<?php echo esc_attr( $product->ID ); ?>">
								<?php echo esc_html( $product->post_title ); ?> (ID: <?php echo esc_html( $product->ID ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="sync-single-product" disabled>
						<?php esc_html_e( 'Sync Selected Product', 'univoucher-for-woocommerce' ); ?>
					</button>
				</div>
				
				<div id="single-sync-status" style="display: none; padding: 10px; border-radius: 4px; margin-bottom: 15px;"></div>
			<?php endif; ?>
		</div>

		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Bulk Sync All Products', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<?php if ( empty( $univoucher_products ) ) : ?>
				<p style="margin: 0; color: #6c757d;">
					<span class="dashicons dashicons-info" style="color: #17a2b8; margin-right: 5px;"></span>
					<?php esc_html_e( 'No products available for bulk sync.', 'univoucher-for-woocommerce' ); ?>
				</p>
			<?php else : ?>
				<div style="margin-bottom: 15px;">
					<p style="margin: 0 0 10px 0; color: #495057;">
						<?php
						printf(
							// translators: %d is the number of products
							esc_html__( 'Total UniVoucher products: %d', 'univoucher-for-woocommerce' ),
							$total_products
						);
						?>
					</p>
					<button type="button" class="button button-primary" id="sync-all-products">
						<?php esc_html_e( 'Sync All Products', 'univoucher-for-woocommerce' ); ?>
					</button>
				</div>
				
				<div id="bulk-sync-progress" style="display: none;">
					<div style="margin-bottom: 10px;">
						<span id="progress-text">
							<?php esc_html_e( 'Preparing...', 'univoucher-for-woocommerce' ); ?>
						</span>
					</div>
					<div style="background: #e9ecef; border-radius: 4px; height: 20px; margin-bottom: 10px;">
						<div id="progress-bar" style="background: #28a745; height: 100%; border-radius: 4px; width: 0%; transition: width 0.3s ease;"></div>
					</div>
					<div id="sync-results" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 10px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
				</div>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Content Templates section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function content_templates_section_callback( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Customize the content of auto-generated product titles, short descriptions, and full descriptions for UniVoucher gift cards products.', 'univoucher-for-woocommerce' ); ?>
		</p>
		<p style="margin: 5px 0 8px 0; font-size: 13px;">
			<?php esc_html_e( 'The below templates will be used to automatically to generate product content when the "Generate Title & Description" button is clicked in the product edit page.', 'univoucher-for-woocommerce' ); ?>
		</p>		
		<?php
	}

	/**
	 * Delivery section callback.
	 *
	 * @param array $args Section arguments.
	 */
	public function delivery_section_callback( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Configure how gift cards are delivered to customers on order completion ( order status changes to "Completed").', 'univoucher-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Available placeholders field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function available_placeholders_callback( $args ) {
		?>
		<div class="univoucher-settings-box">

			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'The content templates support the following dynamic placeholders that will be replaced with actual product data when generating content.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-highlight">
				<strong>
					<?php esc_html_e( '{amount}, {symbol}, {network}', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<div style="margin-top: 8px; font-size: 12px; color: #2980b9;">
					<div style="margin: 2px 0;"><strong>{amount}</strong> - <?php esc_html_e( 'The gift card amount (e.g., "10")', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;"><strong>{symbol}</strong> - <?php esc_html_e( 'The token symbol (e.g., "USDC")', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;"><strong>{network}</strong> - <?php esc_html_e( 'The blockchain network name (e.g., "Ethereum")', 'univoucher-for-woocommerce' ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Title template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function title_template_callback( $args ) {
		$template = get_option( 'univoucher_wc_title_template', 'UniVoucher {amount} {symbol} Gift Card on {network}' );
		?>
		<div class="univoucher-settings-box">
			<input
				type="text"
				id="<?php echo esc_attr( $args['label_for'] ); ?>"
				name="<?php echo esc_attr( $args['label_for'] ); ?>"
				value="<?php echo esc_attr( $template ); ?>"
				class="large-text"
				style="width: 100%; max-width: 800px; font-size: 16px; padding: 5px;"
				placeholder="<?php esc_attr_e( 'Enter a template for product titles', 'univoucher-for-woocommerce' ); ?>"
			/>
			<p class="description">
				<?php esc_html_e( 'Template for generating product titles.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Short description template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function short_description_template_callback( $args ) {
		$template = get_option( 'univoucher_wc_short_description_template', 'Digital gift card worth {amount} {symbol} on {network} network. Instantly delivered via UniVoucher.' );
		?>
		<div class="univoucher-settings-box">
			<?php
			// Use WordPress native rich text editor with compact settings
			wp_editor( 
				$template, 
				$args['label_for'], 
				array(
					'textarea_name' => $args['label_for'],
					'media_buttons' => false,
					'textarea_rows' => 5,
					'teeny' => true,
					'dfw' => false,
					'quicktags' => array(
						'buttons' => 'strong,em,link,ul,ol,li,close'
					),
					'tinymce' => array(
						'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
						'toolbar2' => '',
						'toolbar3' => '',
					),
					'editor_class' => 'univoucher-short-description-editor',
				)
			);
			?>
			<p class="description">
				<?php esc_html_e( 'Template for generating product short descriptions (excerpts). You can use basic formatting and HTML tags.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Description template field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function description_template_callback( $args ) {
		$template = get_option( 'univoucher_wc_description_template', "This is a UniVoucher digital gift card containing {amount} {symbol} tokens on the {network} blockchain network.\n\nFeatures:\n• Instant digital delivery\n• Secure blockchain-based gift card\n• Redeemable on {network} network\n• Value: {amount} {symbol}\n\nAfter purchase, you will receive your gift card details that can be redeemed through the UniVoucher platform." );
		?>
		<div class="univoucher-settings-box">
			<?php
			// Use WordPress native rich text editor with enhanced features
			wp_editor( 
				$template, 
				$args['label_for'], 
				array(
					'textarea_name' => $args['label_for'],
					'media_buttons' => true,
					'textarea_rows' => 20,
					'teeny' => false,
					'dfw' => false,
					'quicktags' => array(
						'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
					),
					'tinymce' => array(
						'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_fullscreen,wp_adv',
						'toolbar2' => 'undo,redo,cut,copy,paste,removeformat,charmap,outdent,indent,wp_help',
						'toolbar3' => '',
					),
					'editor_class' => 'univoucher-description-editor',
				)
			);
			?>
			<p class="description">
				<?php esc_html_e( 'Template for generating detailed product descriptions. You can use rich formatting, images, and HTML tags.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show customer cards field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function show_customer_cards_callback( $args ) {
		$show_cards = get_option( 'univoucher_wc_show_in_order_details', true );
		?>
		
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Order Details Display', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Control whether customers can see their gift card details (Card ID, Card Secret, Network, etc.) in order details and thank you page.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $args['label_for'] ); ?>"
						name="<?php echo esc_attr( $args['label_for'] ); ?>"
						value="1"
						<?php checked( $show_cards, true ); ?>
						style="margin-right: 10px;"
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Show gift cards in order details and thank you pages', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, customers will see their gift card details including Card ID, Card Secret, Network, and redemption links in order details and thank you pages (only for completed orders).', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px;">
				<strong style="color: #856404;">
					<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Important Note:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #856404;">
					<?php esc_html_e( 'If disabled, customers will not see their gift card details on the website. Ensure you have an alternative delivery method in place.', 'univoucher-for-woocommerce' ); ?>
				</span>
			</div>
		</div>

		<?php
	}

	/**
	 * Send email cards field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function send_email_cards_callback( $args ) {
		$send_email = get_option( 'univoucher_wc_send_email_cards', true );
		$template = get_option( 'univoucher_wc_email_template', '<h2>Hello {customer_name},</h2><p>Your UniVoucher gift cards are ready!</p><p><strong>Order:</strong> #{order_number}</p><div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">{cards_content}</div><p><strong>Redeem your cards at:</strong></p><ul><li><a href="https://univoucher.com" target="_blank">https://univoucher.com</a></li><li><a href="https://redeemnow.xyz" target="_blank">https://redeemnow.xyz</a></li></ul><p>Thank you for your purchase!</p><p>Best regards,<br>{site_name}</p>' );
		$subject = get_option( 'univoucher_wc_email_subject', 'Your UniVoucher Gift Cards - Order #{order_number}' );
		?>
		
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Email Delivery', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Send gift cards to customers via email when order status changes to "Completed".', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $args['label_for'] ); ?>"
						name="<?php echo esc_attr( $args['label_for'] ); ?>"
						value="1"
						<?php checked( $send_email, true ); ?>
						style="margin-right: 10px;"
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Enable email delivery', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, customers will receive an HTML email with their gift card details when order status changes to "Completed".', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-highlight" style="margin-top: 15px;">
				<strong>
					<?php esc_html_e( 'Available Placeholders:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<div style="margin-top: 8px; font-size: 12px; color: #2980b9;">
					<div style="margin: 2px 0;"><strong>{customer_name}</strong> - <?php esc_html_e( 'Customer\'s first name', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;"><strong>{order_number}</strong> - <?php esc_html_e( 'Order number', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;"><strong>{cards_content}</strong> - <?php esc_html_e( 'Formatted gift cards details', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;"><strong>{site_name}</strong> - <?php esc_html_e( 'Website name', 'univoucher-for-woocommerce' ); ?></div>
				</div>
			</div>

			<div style="margin-top: 15px;">
				<label for="univoucher_wc_email_subject" style="font-weight: bold; display: block; margin-bottom: 8px;">
					<?php esc_html_e( 'Email Subject:', 'univoucher-for-woocommerce' ); ?>
				</label>
				<input
					type="text"
					id="univoucher_wc_email_subject"
					name="univoucher_wc_email_subject"
					value="<?php echo esc_attr( $subject ); ?>"
					class="large-text"
					style="width: 100%; max-width: 600px; font-size: 16px; padding: 8px; margin-bottom: 15px;"
					placeholder="<?php esc_attr_e( 'Enter email subject...', 'univoucher-for-woocommerce' ); ?>"
				/>
			</div>

			<div style="margin-top: 15px;">
				<label for="univoucher_wc_email_template" style="font-weight: bold; display: block; margin-bottom: 8px;">
					<?php esc_html_e( 'Email Template:', 'univoucher-for-woocommerce' ); ?>
				</label>
				<?php
				wp_editor( 
					$template, 
					'univoucher_wc_email_template', 
					array(
						'textarea_name' => 'univoucher_wc_email_template',
						'media_buttons' => true,
						'textarea_rows' => 12,
						'teeny' => false,
						'dfw' => false,
						'quicktags' => array(
							'buttons' => 'strong,em,link,ul,ol,li,close'
						),
						'tinymce' => array(
							'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,undo,redo',
							'toolbar2' => 'cut,copy,paste,removeformat,charmap,outdent,indent,wp_help',
							'toolbar3' => '',
						),
						'editor_class' => 'univoucher-email-editor',
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'The {cards_content} placeholder will be automatically replaced with formatted gift card details.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>
		</div>

		<?php
	}

	/**
	 * Reset templates field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function reset_templates_callback( $args ) {
		?>
		<div class="univoucher-settings-box">
			<button type="button" class="button button-secondary" id="reset-content-templates">
				<?php esc_html_e( 'Reset All Templates to Default', 'univoucher-for-woocommerce' ); ?>
			</button>
			<p class="description">
				<?php esc_html_e( 'Reset all content templates to their original default values. This action cannot be undone.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const resetButton = document.getElementById('reset-content-templates');
			if (resetButton) {
				resetButton.addEventListener('click', function() {
					if (confirm('<?php echo esc_js( __( 'Are you sure you want to reset all templates to default values? This action cannot be undone.', 'univoucher-for-woocommerce' ) ); ?>')) {
						// Reset title template
						const titleField = document.getElementById('univoucher_wc_title_template');
						if (titleField) {
							titleField.value = 'UniVoucher {amount} {symbol} Gift Card on {network}';
						}
						
						// Reset short description template
						const shortDescEditor = window.tinymce ? window.tinymce.get('univoucher_wc_short_description_template') : null;
						if (shortDescEditor) {
							shortDescEditor.setContent('UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported');
						} else {
							const shortDescField = document.getElementById('univoucher_wc_short_description_template');
							if (shortDescField) {
								shortDescField.value = 'UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported';
							}
						}
						
						// Reset full description template
						const descEditor = window.tinymce ? window.tinymce.get('univoucher_wc_description_template') : null;
						if (descEditor) {
							descEditor.setContent('<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeemnow.xyz\" target=\"_blank\">https://redeemnow.xyz</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>');
						} else {
							const descField = document.getElementById('univoucher_wc_description_template');
							if (descField) {
								descField.value = "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeemnow.xyz\" target=\"_blank\">https://redeemnow.xyz</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>";
							}
						}
						
						alert('<?php echo esc_js( __( 'Templates have been reset to default values. Save Changes to apply.', 'univoucher-for-woocommerce' ) ); ?>');
					}
				});
			}
		});
		</script>
		<?php
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
	 * Sanitize API key.
	 *
	 * @param string $input The input value.
	 * @return string The sanitized value.
	 */
	public function sanitize_api_key( $input ) {
		// Remove any whitespace.
		$input = trim( $input );
		
		// Basic validation - Alchemy API keys are typically 32 characters.
		if ( ! empty( $input ) && strlen( $input ) < 10 ) {
			add_settings_error(
				'univoucher_wc_alchemy_api_key',
				'api_key_invalid',
				esc_html__( 'The API key appears to be too short. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ),
				'error'
			);
			return get_option( 'univoucher_wc_alchemy_api_key', '' );
		}

		// Sanitize the key.
		$sanitized = sanitize_text_field( $input );

		// Add success message if key was updated.
		if ( ! empty( $sanitized ) && $sanitized !== get_option( 'univoucher_wc_alchemy_api_key', '' ) ) {
			add_settings_error(
				'univoucher_wc_alchemy_api_key',
				'api_key_updated',
				esc_html__( 'Alchemy API key updated successfully.', 'univoucher-for-woocommerce' ),
				'updated'
			);
		}

		return $sanitized;
	}

	/**
	 * Get the API key.
	 *
	 * @return string The API key.
	 */
	public static function get_api_key() {
		return get_option( 'univoucher_wc_alchemy_api_key', '' );
	}

	/**
	 * Check if API key is configured.
	 *
	 * @return bool True if API key is set.
	 */
	public static function is_api_key_configured() {
		$api_key = self::get_api_key();
		return ! empty( $api_key ) && strlen( $api_key ) >= 10;
	}

	/**
	 * Check if database key backup is confirmed.
	 *
	 * @return bool True if backup is confirmed.
	 */
	public static function is_database_key_backup_confirmed() {
		return get_option( 'univoucher_wc_database_key_backup_confirmed', false );
	}

	/**
	 * AJAX handler for syncing a single product's stock.
	 */
	public function ajax_sync_single_product() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_stock_sync' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid product ID.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check if product exists and has UniVoucher enabled.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Product not found.', 'univoucher-for-woocommerce' ) ) );
		}

		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'UniVoucher is not enabled for this product.', 'univoucher-for-woocommerce' ) ) );
		}

		// Sync the product stock.
		$stock_manager = UniVoucher_WC_Stock_Manager::instance();
		$stock_manager->uv_sync_product_stock( $product_id );

		// Get updated stock information.
		$product = wc_get_product( $product_id ); // Reload product.
		$stock_quantity = $product->get_stock_quantity();
		$stock_status = $product->get_stock_status();

		wp_send_json_success( array(
			'message' => sprintf(
				// translators: %1$s is the product name, %2$d is the stock quantity, %3$s is the stock status
				esc_html__( 'Product "%1$s" synced successfully. Stock: %2$d (%3$s)', 'univoucher-for-woocommerce' ),
				$product->get_name(),
				$stock_quantity,
				$stock_status
			),
			'product_name' => $product->get_name(),
			'stock_quantity' => $stock_quantity,
			'stock_status' => $stock_status,
		) );
	}

	/**
	 * AJAX handler for syncing all products' stock.
	 */
	public function ajax_sync_all_products() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_stock_sync' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 5;
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		global $wpdb;
		
		// Get UniVoucher enabled products.
		$products = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_title 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
				AND p.post_status = 'publish'
				AND pm.meta_key = '_univoucher_enabled' 
				AND pm.meta_value = 'yes'
			ORDER BY p.ID ASC
			LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		) );

		$results = array();
		$stock_manager = UniVoucher_WC_Stock_Manager::instance();

		foreach ( $products as $product_data ) {
			$product_id = $product_data->ID;
			$product_name = $product_data->post_title;
			
			try {
				// Sync the product stock.
				$stock_manager->uv_sync_product_stock( $product_id );
				
				// Get updated stock information.
				$product = wc_get_product( $product_id );
				$stock_quantity = $product->get_stock_quantity();
				$stock_status = $product->get_stock_status();
				
				$results[] = array(
					'id' => $product_id,
					'name' => $product_name,
					'success' => true,
					'stock_quantity' => $stock_quantity,
					'stock_status' => $stock_status,
					'message' => sprintf(
						// translators: %1$s is the product name, %2$d is the stock quantity, %3$s is the stock status
						esc_html__( 'Synced: %1$s - Stock: %2$d (%3$s)', 'univoucher-for-woocommerce' ),
						$product_name,
						$stock_quantity,
						$stock_status
					),
				);
			} catch ( Exception $e ) {
				$results[] = array(
					'id' => $product_id,
					'name' => $product_name,
					'success' => false,
					'message' => sprintf(
						// translators: %1$s is the product name, %2$s is the error message
						esc_html__( 'Error syncing %1$s: %2$s', 'univoucher-for-woocommerce' ),
						$product_name,
						$e->getMessage()
					),
				);
			}
		}

		// Get total count for progress calculation.
		$total_products = (int) $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
				AND p.post_status = 'publish'
				AND pm.meta_key = '_univoucher_enabled' 
				AND pm.meta_value = 'yes'"
		);

		$processed_count = $offset + count( $products );
		$is_complete = $processed_count >= $total_products;

		wp_send_json_success( array(
			'results' => $results,
			'processed' => $processed_count,
			'total' => $total_products,
			'is_complete' => $is_complete,
			'next_offset' => $processed_count,
		) );
	}

	/**
	 * AJAX handler for testing Alchemy API key.
	 */
	public function ajax_test_api_key() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_stock_sync' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No API key provided.', 'univoucher-for-woocommerce' ) ) );
		}

		// Test API with a simple eth_chainId call (lighter than block number)
		$response = wp_remote_post( 'https://eth-mainnet.g.alchemy.com/v2/' . $api_key, array(
			'body' => wp_json_encode( array(
				'jsonrpc' => '2.0',
				'method' => 'eth_chainId',
				'params' => array(),
				'id' => 1,
			) ),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Connection failed. Please check your internet connection.', 'univoucher-for-woocommerce' ) ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			$error_message = $data['error']['message'];
			if ( stripos( $error_message, 'authenticate' ) !== false || stripos( $error_message, 'unauthorized' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid API key. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ) ) );
			} else {
				wp_send_json_error( array( 'message' => sprintf( esc_html__( 'API Error: %s', 'univoucher-for-woocommerce' ), $error_message ) ) );
			}
		}

		if ( isset( $data['result'] ) ) {
			$chain_id = hexdec( $data['result'] );
			$network_name = $chain_id === 1 ? 'Ethereum Mainnet' : 'Chain ID ' . $chain_id;
			wp_send_json_success( array( 'message' => sprintf( esc_html__( '✅ API key is valid!', 'univoucher-for-woocommerce' ), $network_name ) ) );
		}

		wp_send_json_error( array( 'message' => esc_html__( 'Unexpected response from Alchemy API. Please try again.', 'univoucher-for-woocommerce' ) ) );
	}

	/**
	 * AJAX handler for fetching content templates.
	 */
	public function ajax_get_content_templates() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_product_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get templates from database
		$title_template = get_option( 'univoucher_wc_title_template', 'UniVoucher {amount} {symbol} Gift Card on {network}' );
		$short_description_template = get_option( 'univoucher_wc_short_description_template', 'Digital gift card worth {amount} {symbol} on {network} network. Instantly delivered via UniVoucher.' );
		$description_template = get_option( 'univoucher_wc_description_template', "This is a UniVoucher digital gift card containing {amount} {symbol} tokens on the {network} blockchain network.\n\nFeatures:\n• Instant digital delivery\n• Secure blockchain-based gift card\n• Redeemable on {network} network\n• Value: {amount} {symbol}\n\nAfter purchase, you will receive your gift card details that can be redeemed through the UniVoucher platform." );

		// Apply WordPress content formatting (wpautop) to properly handle line breaks for TinyMCE
		// This ensures content appears in TinyMCE the same way it would when saved and displayed
		$description_template = wpautop( $description_template );
		$short_description_template = wpautop( $short_description_template );

		wp_send_json_success( array(
			'title_template' => $title_template,
			'short_description_template' => $short_description_template,
			'description_template' => $description_template,
		) );
	}

	/**
	 * Auto-complete orders field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function auto_complete_orders_callback( $args ) {
		$auto_complete = get_option( 'univoucher_wc_auto_complete_orders', true );
		$require_processing = get_option( 'univoucher_wc_require_processing_if_missing_cards', true );
		?>
		
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Order Auto-Completion', 'univoucher-for-woocommerce' ); ?>
			</h4>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Automatically mark orders as "Completed" when they contain only UniVoucher products or other products that don\'t require processing, with sufficient inventory available.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $args['label_for'] ); ?>"
						name="<?php echo esc_attr( $args['label_for'] ); ?>"
						value="1"
						<?php checked( $auto_complete, true ); ?>
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Auto-complete orders with UniVoucher products', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, orders containing only UniVoucher products or other products that don\'t require processing will automatically be marked as "Completed" upon payment, allowing immediate gift card delivery.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px;">
				<strong style="color: #856404;">
					<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'How it works:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<div style="margin-top: 8px; font-size: 12px; color: #856404;">
					<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with only UniVoucher products or other non-processing products will auto-complete if sufficient inventory is available', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with mixed products (UniVoucher + physical items) will still require manual processing', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'Gift cards will be delivered immediately upon order completion', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'If inventory is insufficient, orders will require manual processing regardless of this setting', 'univoucher-for-woocommerce' ); ?></div>
				</div>
			</div>

			<div id="inventory-processing-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e1e5e9; <?php echo $auto_complete ? '' : 'display: none;'; ?>">
				<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
					<?php esc_html_e( 'Inventory-Based Processing (recommended)', 'univoucher-for-woocommerce' ); ?>
				</h5>
				
				<div style="margin-bottom: 15px;">
					<p style="margin: 5px 0 8px 0; font-size: 13px;">
						<?php esc_html_e( 'Control whether orders should require manual processing based on available inventory for UniVoucher products.', 'univoucher-for-woocommerce' ); ?>
					</p>
				</div>

				<div class="univoucher-settings-box-info">
					<label for="univoucher_wc_require_processing_if_missing_cards" style="display: flex; align-items: center; margin: 0;">
						<input
							type="checkbox"
							id="univoucher_wc_require_processing_if_missing_cards"
							name="univoucher_wc_require_processing_if_missing_cards"
							value="1"
							<?php checked( $require_processing, true ); ?>
							style="margin-right: 10px;"
						/>
						<strong style="color: #0c5460;">
							<?php esc_html_e( 'Require manual processing when inventory is insufficient', 'univoucher-for-woocommerce' ); ?>
						</strong>
					</label>
					<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
						<?php esc_html_e( 'When enabled, orders will only auto-complete if there is sufficient available inventory for all UniVoucher products in the order.', 'univoucher-for-woocommerce' ); ?>
					</p>
				</div>

				<div class="univoucher-settings-box-warning" style="margin-top: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
					<strong style="color: #721c24;">
						<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
						<?php esc_html_e( 'Warning:', 'univoucher-for-woocommerce' ); ?>
					</strong>
					<span style="font-size: 13px; color: #721c24;">
						<?php esc_html_e( 'If disabled, orders will auto-complete regardless of inventory availability, which may lead to orders with unassigned gift cards.', 'univoucher-for-woocommerce' ); ?>
					</span>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#<?php echo esc_js( $args['label_for'] ); ?>').on('change', function() {
				if ($(this).is(':checked')) {
					$('#inventory-processing-section').show();
				} else {
					$('#inventory-processing-section').hide();
				}
			});
		});
		</script>

		<?php
	}


}