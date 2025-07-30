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
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-image-templates.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-product-fields.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-inventory-page.php';
		include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-add-cards-page.php';
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
		UniVoucher_WC_Image_Templates::instance();
		UniVoucher_WC_CSV_Handler::instance();
		UniVoucher_WC_Product_Fields::instance();
		UniVoucher_WC_Gift_Card_Manager::instance();
		UniVoucher_WC_Add_Cards_Page::instance();
		UniVoucher_WC_Inventory_Page::instance();
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_scripts( $hook ) {
		// Only load on UniVoucher admin pages.
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
			// Enqueue ethers.js for wallet functionality
			wp_enqueue_script(
				'univoucher-wc-ethers',
				'https://unpkg.com/ethers@6.0.6/dist/ethers.umd.min.js',
				array(),
				'6.0.6',
				true
			);

			// Enqueue QRCode.js for wallet QR code generation
			wp_enqueue_script(
				'univoucher-wc-qrcode',
				'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
				array(),
				'1.0.0',
				true
			);

			wp_localize_script(
				'univoucher-wc-admin',
				'univoucher_settings_vars',
				array(
					'nonce' => wp_create_nonce( 'univoucher_stock_sync' ),
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
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
				array( 'jquery', 'univoucher-wc-notifications', 'univoucher-wc-add-cards' ),
				UNIVOUCHER_WC_VERSION,
				true
			);
		}
	}
}