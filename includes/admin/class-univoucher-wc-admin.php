<?php
/**
 * UniVoucher For WooCommerce Admin
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Admin class.
 */
class UniVoucher_WC_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Admin
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Admin Instance.
	 *
	 * Ensures only one instance of UniVoucher_WC_Admin is loaded or can be loaded.
	 *
	 * @return UniVoucher_WC_Admin - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Admin Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include admin files.
	 */
	private function includes() {
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-admin-menus.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-admin-settings.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-admin-tools.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-image-templates.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-product-fields.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-inventory-page.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-add-cards-page.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-promotions-page.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-promotional-cards-page.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-csv-handler.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Add admin menus.
	 */
	public function admin_menu() {
		UniVoucher_WC_Admin_Menus::instance();
	}

	/**
	 * Initialize admin.
	 */
	public function admin_init() {
		UniVoucher_WC_Admin_Settings::instance();
		UniVoucher_WC_Admin_Tools::instance();
		UniVoucher_WC_Image_Templates::instance();
		UniVoucher_WC_CSV_Handler::instance();
		UniVoucher_WC_Product_Fields::instance();
		UniVoucher_WC_Gift_Card_Manager::instance();
		UniVoucher_WC_Add_Cards_Page::instance();
		UniVoucher_WC_Inventory_Page::instance();
		UniVoucher_WC_Promotions_Page::instance();
		UniVoucher_WC_Promotional_Cards_Page::instance();

		// Process expired promotional cards (every 12 hours on any admin page).
		$this->process_expired_promotional_cards();
	}

	/**
	 * Process expired promotional cards when visiting admin pages.
	 */
	private function process_expired_promotional_cards() {
		// Use a transient to limit processing to once every 12 hours.
		$last_check = get_transient( 'univoucher_last_expiration_check' );
		if ( $last_check ) {
			return;
		}

		// Set transient for 12 hours.
		set_transient( 'univoucher_last_expiration_check', time(), 12 * HOUR_IN_SECONDS );

		// Process expired cards.
		$promotion_processor = UniVoucher_WC_Promotion_Processor::instance();
		$promotion_processor->process_expired_promotional_cards();
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_scripts( $hook ) {
		// Always enqueue menu styles for consistent icon display across all admin pages.
		wp_enqueue_style(
			'univoucher-wc-admin-menu',
			plugins_url( 'admin/css/admin-menu.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);

		// Only load other styles/scripts on UniVoucher admin pages.
		if ( false === strpos( $hook, 'univoucher' ) ) {
			return;
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'univoucher-wc-admin',
			plugins_url( 'admin/css/admin.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);

		// Enqueue notification system (available on all admin pages).
		wp_enqueue_script(
			'univoucher-wc-notifications',
			plugins_url( 'admin/js/notifications.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		// Enqueue admin scripts.
		wp_enqueue_script(
			'univoucher-wc-admin',
			plugins_url( 'admin/js/admin.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery', 'univoucher-wc-notifications' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		// Localize script for AJAX on settings page.
		if ( strpos( $hook, 'univoucher-settings' ) !== false ) {
			// Enqueue backorders settings script
			wp_enqueue_script(
				'univoucher-wc-backorders-settings',
				plugins_url( 'assets/js/admin/backorders-settings.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Enqueue card delivery settings script
			wp_enqueue_script(
				'univoucher-wc-card-delivery-settings',
				plugins_url( 'assets/js/admin/card-delivery-settings.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Enqueue content templates settings script
			wp_enqueue_script(
				'univoucher-wc-content-templates-settings',
				plugins_url( 'assets/js/admin/content-templates-settings.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Enqueue ethers.js for wallet functionality
			wp_enqueue_script(
				'univoucher-wc-ethers',
				plugins_url( 'assets/js/vendors/ethers.umd.min.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array(),
				'6.0.6',
				true
			);

			// Enqueue QRCode.js for wallet QR code generation
			wp_enqueue_script(
				'univoucher-wc-qrcode',
				plugins_url( 'assets/js/vendors/qrcode.min.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array(),
				'1.0.0',
				true
			);

			// Enqueue wallet settings scripts
			wp_enqueue_script(
				'univoucher-internal-wallet-settings',
				plugins_url( 'assets/js/internal-wallet-settings.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery', 'univoucher-wc-ethers' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			wp_enqueue_script(
				'univoucher-wallet-details',
				plugins_url( 'assets/js/wallet-details.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery', 'univoucher-wc-ethers', 'univoucher-wc-qrcode' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Localize wallet settings scripts
			wp_localize_script( 'univoucher-internal-wallet-settings', 'walletSettings', array(
				'i18n' => array(
					'hide'                     => esc_html__( 'Hide', 'univoucher-for-woocommerce' ),
					'show'                     => esc_html__( 'Show', 'univoucher-for-woocommerce' ),
					'enterPrivateKeyFirst'     => esc_html__( 'Please enter a private key first.', 'univoucher-for-woocommerce' ),
					'validating'               => esc_html__( 'Validating...', 'univoucher-for-woocommerce' ),
					'invalidPrivateKey'        => esc_html__( 'Invalid private key format. Must be 64 hex characters.', 'univoucher-for-woocommerce' ),
					'validAddress'             => esc_html__( 'Valid! Address: ', 'univoucher-for-woocommerce' ),
					'ethersNotLoaded'          => esc_html__( 'Ethers.js not loaded. Please refresh the page.', 'univoucher-for-woocommerce' ),
					'invalidPrivateKeyError'   => esc_html__( 'Invalid private key: ', 'univoucher-for-woocommerce' ),
					'validatePrivateKey'       => esc_html__( 'Validate Private Key', 'univoucher-for-woocommerce' ),
				),
			));

			wp_localize_script( 'univoucher-wallet-details', 'walletDetails', array(
				'alchemyApiKey' => esc_js( get_option( 'univoucher_wc_alchemy_api_key', '' ) ),
				'i18n' => array(
					'noPrivateKey'      => esc_html__( 'No private key set', 'univoucher-for-woocommerce' ),
					'invalidPrivateKey' => esc_html__( 'Invalid private key format', 'univoucher-for-woocommerce' ),
					'ethersNotLoaded'   => esc_html__( 'Ethers.js not loaded', 'univoucher-for-woocommerce' ),
					'errorGenerating'   => esc_html__( 'Error generating address', 'univoucher-for-woocommerce' ),
					'copied'            => esc_html__( 'Copied!', 'univoucher-for-woocommerce' ),
					'noApiKey'          => esc_html__( 'Alchemy API key not configured. Please set it in the API Configuration section.', 'univoucher-for-woocommerce' ),
					'errorLoading'      => esc_html__( 'Error loading balances: ', 'univoucher-for-woocommerce' ),
					'noBalances'        => esc_html__( 'No balances found.', 'univoucher-for-woocommerce' ),
					'nativeToken'       => esc_html__( 'Native Token', 'univoucher-for-woocommerce' ),
					'erc20Tokens'       => esc_html__( 'ERC-20 Tokens', 'univoucher-for-woocommerce' ),
					'noErc20Tokens'     => esc_html__( 'No ERC-20 tokens found', 'univoucher-for-woocommerce' ),
					'noValidAddress'    => esc_html__( 'No valid address', 'univoucher-for-woocommerce' ),
				),
			));

			wp_localize_script(
				'univoucher-wc-admin',
				'univoucher_settings_vars',
				array(
					'nonce' => wp_create_nonce( 'univoucher_stock_sync' ),
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'reset_template_confirm' => __( 'Are you sure you want to reset the Template to its default value?', 'univoucher-for-woocommerce' ),
					'reset_template_success' => __( 'Template has been reset to default. Save Changes to apply.', 'univoucher-for-woocommerce' ),
				)
			);

			// Localize content templates script
			wp_localize_script(
				'univoucher-wc-content-templates-settings',
				'univoucherContentTemplates',
				array(
					'confirmMessage' => __( 'Are you sure you want to reset all templates to default values? This action cannot be undone.', 'univoucher-for-woocommerce' ),
					'successMessage' => __( 'Templates have been reset to default values. Save Changes to apply.', 'univoucher-for-woocommerce' ),
				)
			);
		}

		// Enqueue inventory management scripts on inventory page.
		if ( strpos( $hook, 'univoucher-inventory' ) !== false ) {
			wp_enqueue_script(
				'univoucher-wc-inventory',
				plugins_url( 'admin/js/inventory.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery', 'univoucher-wc-notifications' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Localize script for AJAX.
			wp_localize_script(
				'univoucher-wc-inventory',
				'univoucher_inventory_vars',
				array(
					'nonce' => wp_create_nonce( 'univoucher_inventory_action' ),
					'filter_nonce' => wp_create_nonce( 'univoucher_inventory_filter' ),
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
				)
			);

			// Additional localization for edit modal functionality
			wp_localize_script(
				'univoucher-wc-inventory',
				'univoucher_vars',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'edit_card_nonce' => wp_create_nonce( 'univoucher_edit_card' ),
					'verify_cards_nonce' => wp_create_nonce( 'univoucher_verify_cards' ),
					'unassign_card_nonce' => wp_create_nonce( 'univoucher_unassign_card' ),
					'bulk_action_nonce' => wp_create_nonce( 'univoucher_bulk_action' ),
					'post_edit_url' => admin_url( 'post.php?action=edit&post=' ),
					'not_assigned_text' => __( 'Not assigned to any order', 'univoucher-for-woocommerce' ),
				)
			);
		}

		// Enqueue add cards scripts on add cards page.
		if ( strpos( $hook, 'univoucher-add-cards' ) !== false ) {
			// Enqueue ethers.js for wallet functionality
			wp_enqueue_script(
				'univoucher-wc-ethers',
				plugins_url( 'assets/js/vendors/ethers.umd.min.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array(),
				'6.0.6',
				true
			);

			wp_enqueue_script(
				'univoucher-wc-add-cards',
				plugins_url( 'admin/js/add-cards.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery', 'univoucher-wc-notifications' ),
				UNIVOUCHER_WC_VERSION,
				true
			);

			// Enqueue internal wallet scripts
			wp_enqueue_script(
				'univoucher-wc-internal-wallet',
				plugins_url( 'admin/js/internal-wallet.js', UNIVOUCHER_WC_PLUGIN_FILE ),
				array( 'jquery', 'univoucher-wc-notifications', 'univoucher-wc-add-cards', 'univoucher-wc-ethers' ),
				UNIVOUCHER_WC_VERSION,
				true
			);
		}
	}
}