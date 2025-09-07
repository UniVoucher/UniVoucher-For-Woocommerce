<?php
/**
 * UniVoucher Admin Products
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Admin_Products class.
 */
class UniVoucher_WC_Admin_Products {

	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Admin_Products Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add on-demand info to stock column
		add_filter( 'woocommerce_admin_stock_html', array( $this, 'modify_stock_column_content' ), 10, 2 );
		
		// Enqueue admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Modify stock column content to include on-demand info.
	 */
	public function modify_stock_column_content( $stock_html, $product ) {
		// Check if show on-demand limit is enabled
		if ( ! get_option( 'univoucher_wc_show_on_demand_limit', true ) ) {
			return $stock_html;
		}

		// Check if this is a UniVoucher product
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product ) ) {
			return $stock_html;
		}

		// Include the on-demand manager
		require_once UNIVOUCHER_WC_ABSPATH . 'includes/class-univoucher-wc-on-demand-manager.php';
		
		// Get the on-demand limit
		$limit = uv_get_on_demand_limit( $product->get_id() );
		
		if ( is_wp_error( $limit ) ) {
			$error_code = $limit->get_error_code();
			$color = ( $error_code === 'backorder_disabled' ) ? '#999' : '#dc3232';
			$text = ( $error_code === 'backorder_disabled' ) ? __( 'On-demand: Inactive', 'univoucher-for-woocommerce' ) : __( 'On-demand: Error', 'univoucher-for-woocommerce' );
			$title = ( $error_code !== 'backorder_disabled' ) ? ' title="' . esc_attr( $limit->get_error_message() ) . '"' : '';
			$stock_html .= '<br><small style="color: ' . $color . ';"' . $title . '>' . esc_html( $text ) . '</small>';
		} else {
			$stock_html .= '<br><small style="color: #46b450;">' . esc_html__( 'On-demand', 'univoucher-for-woocommerce' ) . ' (' . esc_html( $limit ) . ')</small>';
		}
		
		return $stock_html;
	}

	/**
	 * Enqueue admin styles for the products page.
	 */
	public function enqueue_admin_styles( $hook ) {
		// Only enqueue on product list page with nonce verification
		if ( 'edit.php' === $hook && isset( $_GET['post_type'] ) ) {
			// Check nonce for $_GET parameter access
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_admin_products' ) ) {
				// If nonce is invalid, still check if we can safely determine we're on products page by other means
				$current_screen = get_current_screen();
				if ( ! $current_screen || $current_screen->post_type !== 'product' ) {
					return;
				}
			} else {
				// Nonce is valid, check post_type parameter
				if ( 'product' !== sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ) {
					return;
				}
			}
			wp_enqueue_style(
				'univoucher-admin-products',
				plugins_url( 'admin/css/admin-products.css', UNIVOUCHER_WC_PLUGIN_FILE ),
				array(),
				UNIVOUCHER_WC_VERSION
			);
		}
	}
} 