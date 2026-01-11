<?php
/**
 * Plugin Name: UniVoucher For WooCommerce
 * Plugin URI: https://univoucher.com
 * Description: Integrate UniVoucher decentralized crypto gift cards with WooCommerce. Create and redeem blockchain-based gift cards for any ERC-20 token or native currency.
 * Version: 1.4.5
 * Author: UniVoucher
 * Author URI: https://univoucher.com
 * Text Domain: univoucher-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package UniVoucher_For_WooCommerce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main UniVoucher For WooCommerce class.
 */
if ( ! class_exists( 'UniVoucher_For_WooCommerce' ) ) :

	/**
	 * Main UniVoucher_For_WooCommerce Class
	 *
	 * @class UniVoucher_For_WooCommerce
	 * @version 1.4.5
	 */
	final class UniVoucher_For_WooCommerce {

		/**
		 * UniVoucher For WooCommerce version.
		 *
		 * @var string
		 */
		public $version = '1.4.5';

		/**
		 * The single instance of the class.
		 *
		 * @var UniVoucher_For_WooCommerce
		 */
		protected static $_instance = null;

		/**
		 * Main UniVoucher_For_WooCommerce Instance.
		 *
		 * Ensures only one instance of UniVoucher_For_WooCommerce is loaded or can be loaded.
		 *
		 * @return UniVoucher_For_WooCommerce - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * UniVoucher_For_WooCommerce Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();
			$this->init_database();

			do_action( 'univoucher_for_woocommerce_loaded' );
		}

		/**
		 * Define UniVoucher For WooCommerce Constants.
		 */
		private function define_constants() {
			define( 'UNIVOUCHER_WC_PLUGIN_FILE', __FILE__ );
			define( 'UNIVOUCHER_WC_ABSPATH', dirname( UNIVOUCHER_WC_PLUGIN_FILE ) . '/' );
			define( 'UNIVOUCHER_WC_PLUGIN_BASENAME', plugin_basename( UNIVOUCHER_WC_PLUGIN_FILE ) );
			define( 'UNIVOUCHER_WC_VERSION', $this->version );
		}

		/**
		 * Include required files.
		 */
		private function includes() {
			// Include core classes.
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-database.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-encryption.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-product-manager.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-order-manager.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-product-fields.php';

			// Include gift card and stock management classes (needed for both admin and frontend).
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-gift-card-manager.php';

			// Include on-demand manager
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';

			// Include cart limits
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-cart-limits.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-stock-manager.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-callback-manager.php';

			// Include promotion processor.
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-promotion-processor.php';
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-promotion-notices.php';

			// Include integration classes.
			include_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-lmfwc-integration.php';

			// Include admin class.
			if ( is_admin() ) {
				include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-admin.php';
				include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-internal-wallet.php';
				include_once UNIVOUCHER_WC_ABSPATH . 'includes/admin/class-univoucher-wc-admin-products.php';
			}
		}

		/**
		 * Hook into actions and filters.
		 */
		private function init_hooks() {
			register_activation_hook( __FILE__, array( $this, 'activation' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

			add_action( 'plugins_loaded', array( $this, 'check_woocommerce_dependency' ), 10 );
			add_action( 'admin_notices', array( $this, 'uv_admin_notices' ) );
			add_action( 'before_woocommerce_init', array( $this, 'uv_declare_woocommerce_compatibility' ) );
			add_action( 'init', array( $this, 'init_components' ) );

			// Add plugin action links.
			add_filter( 'plugin_action_links_' . UNIVOUCHER_WC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

			// Register REST API endpoints for webhooks
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

			// Handle plugin updates
			add_action( 'upgrader_process_complete', array( $this, 'plugin_update' ), 10, 2 );
		}

		/**
		 * Add plugin action links.
		 *
		 * @param array $links Plugin action links.
		 * @return array Modified plugin action links.
		 */
		public function plugin_action_links( $links ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=univoucher-settings' ) ),
				esc_html__( 'Settings', 'univoucher-for-woocommerce' )
			);
			
			array_unshift( $links, $settings_link );
			
			return $links;
		}

		/**
		 * Plugin activation hook.
		 */
		public function activation() {
			// Check if WooCommerce is active.
			if ( ! $this->is_woocommerce_active() ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die(
					wp_kses_post( __( 'UniVoucher For WooCommerce requires WooCommerce to be installed and active.', 'univoucher-for-woocommerce' ) ),
					esc_html__( 'Plugin dependency check', 'univoucher-for-woocommerce' ),
					array( 'back_link' => true )
				);
			}

			// Generate database security key if it doesn't exist
			$encryption = UniVoucher_WC_Encryption::instance();
			$encryption->uv_generate_database_security_key();

			// Initialize database.
			$database = UniVoucher_WC_Database::instance();
			$database->create_tables();

			// Set plugin version.
			update_option( 'univoucher_wc_version', UNIVOUCHER_WC_VERSION );

			// Set database version.
			update_option( 'univoucher_wc_db_version', UniVoucher_WC_Database::DB_VERSION );
		}

		/**
		 * Handle plugin update.
		 *
		 * @param WP_Upgrader $upgrader_object WP_Upgrader instance.
		 * @param array       $options Array of bulk item update data.
		 */
		public function plugin_update( $upgrader_object, $options ) {
			// Check if this is a plugin update.
			if ( $options['action'] !== 'update' || $options['type'] !== 'plugin' ) {
				return;
			}

			// Check if our plugin is being updated.
			$current_plugin = plugin_basename( __FILE__ );
			if ( isset( $options['plugins'] ) ) {
				foreach ( $options['plugins'] as $plugin ) {
					if ( $plugin === $current_plugin ) {
						// Run database updates - instantiating will automatically check version.
						UniVoucher_WC_Database::instance();
						break;
					}
				}
			}
		}

		/**
		 * Get the database security key.
		 *
		 * @return string|false Database key or false if not found.
		 */
		public static function uv_get_database_key() {
			$encryption = UniVoucher_WC_Encryption::instance();
			return $encryption->uv_get_database_key();
		}

		/**
		 * Encrypt card secret.
		 *
		 * @param string $data Data to encrypt.
		 * @return string|WP_Error Encrypted data (base64 encoded) or WP_Error on failure.
		 */
		public static function uv_encrypt_data( $data ) {
			$encryption = UniVoucher_WC_Encryption::instance();
			return $encryption->uv_encrypt_data( $data );
		}

		/**
		 * Decrypt card secret.
		 *
		 * @param string $encrypted_data Encrypted data (base64 encoded).
		 * @return string|WP_Error Decrypted data or WP_Error on failure.
		 */
		public static function uv_decrypt_data( $encrypted_data ) {
			$encryption = UniVoucher_WC_Encryption::instance();
			return $encryption->uv_decrypt_data( $encrypted_data );
		}

		/**
		 * Plugin deactivation hook.
		 */
		public function deactivation() {
			// Cleanup if needed.
		}

		/**
		 * Check if WooCommerce is active.
		 *
		 * @return bool
		 */
		private function is_woocommerce_active() {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Check WooCommerce dependency.
		 */
		public function check_woocommerce_dependency() {
			if ( ! $this->is_woocommerce_active() ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			// Initialize admin if WooCommerce is active.
			if ( is_admin() ) {
				UniVoucher_WC_Admin::instance();
			}
		}

		/**
		 * WooCommerce missing notice.
		 */
		public function woocommerce_missing_notice() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'UniVoucher For WooCommerce', 'univoucher-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'univoucher-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
						<?php esc_html_e( 'Install WooCommerce', 'univoucher-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Show admin notices.
		 */
		public function uv_admin_notices() {
			// Show database key backup warning if not confirmed.
			$this->uv_show_database_key_backup_notice();
		}

		/**
		 * Show database key backup notice if backup is not confirmed.
		 */
		private function uv_show_database_key_backup_notice() {
			// Only show on admin pages.
			if ( ! is_admin() ) {
				return;
			}

			// Check if backup is confirmed first (most important check).
			if ( function_exists( 'univoucher_is_database_key_backup_confirmed' ) && univoucher_is_database_key_backup_confirmed() ) {
				return;
			}

			// Check if database key exists.
			$database_key = self::uv_get_database_key();
			if ( ! $database_key ) {
				return;
			}

			// Don't show on the plugin's own settings page to avoid redundancy.
			if ( isset( $_GET['page'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_admin_page' ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'univoucher-settings' ) {
				return;
			}

			// Show the warning notice.
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'UniVoucher Security Warning:', 'univoucher-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Please backup your database security key to prevent data loss.', 'univoucher-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-settings' ) ); ?>">
						<?php esc_html_e( 'Confirm to hide this notice', 'univoucher-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Initialize database and check for updates.
		 */
		private function init_database() {
			// Instantiate database class - this will register its hooks.
			UniVoucher_WC_Database::instance();
		}

		/**
		 * Declare WooCommerce feature compatibility.
		 */
		public function uv_declare_woocommerce_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					UNIVOUCHER_WC_PLUGIN_FILE,
					true
				);
			}
		}

		/**
		 * Initialize components.
		 */
		public function init_components() {
			// Initialize components
			if ( class_exists( 'WooCommerce' ) ) {
				UniVoucher_WC_Product_Manager::instance();
				UniVoucher_WC_Gift_Card_Manager::instance();
				UniVoucher_WC_Stock_Manager::instance();
				UniVoucher_WC_Order_Manager::instance();
				UniVoucher_WC_LMFWC_Integration::instance();
				UniVoucher_WC_Cart_Limits::instance();
				UniVoucher_WC_Promotion_Processor::instance();
				UniVoucher_WC_Promotion_Notices::instance();

				// Initialize admin components
				if ( is_admin() ) {
					UniVoucher_WC_Internal_Wallet::instance();
					UniVoucher_WC_Admin_Products::instance();
				}
			}
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'univoucher-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'univoucher-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Register REST API routes.
		 */
		public function register_rest_routes() {
			$callback_manager = UniVoucher_WC_Callback_Manager::instance();
			register_rest_route( 'univoucher/v1', '/callback', array(
				'methods' => 'POST',
				'callback' => array( $callback_manager, 'handle_univoucher_callback' ),
				'permission_callback' => '__return_true',
			) );

			// Register promotion callback endpoint.
			register_rest_route( 'univoucher/v1', '/promotion-callback', array(
				'methods' => 'POST',
				'callback' => array( $callback_manager, 'handle_promotion_callback' ),
				'permission_callback' => '__return_true',
			) );
		}


	}

endif;

/**
 * Main instance of UniVoucher_For_WooCommerce.
 *
 * Returns the main instance of UniVoucher_For_WooCommerce to prevent the need to use globals.
 *
 * @return UniVoucher_For_WooCommerce
 */
function UniVoucher_WC() { 
	return UniVoucher_For_WooCommerce::instance();
}

// Global for backwards compatibility.
$GLOBALS['univoucher_wc'] = UniVoucher_WC();