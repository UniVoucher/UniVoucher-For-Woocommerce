<?php
/**
 * UniVoucher For WooCommerce Product Manager
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Product_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Product_Manager class.
 */
class UniVoucher_WC_Product_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Product_Manager
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Product_Manager Instance.
	 *
	 * @return UniVoucher_WC_Product_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Product_Manager Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize stock management filters for UniVoucher enabled products.
	 */
	public function init_hooks() {
		if ( class_exists( 'WooCommerce' ) ) {
			// Add admin notices for UniVoucher stock management
			add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_univoucher_stock_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_univoucher_admin_assets' ) );
			
			// Enqueue quick edit scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'uv_enqueue_quick_edit_scripts' ) );
			
			// Handle product save, update, and creation events (WordPress hooks)
			add_action( 'save_post', array( $this, 'uv_handle_product_save' ) );
			 
			// Handle WordPress bulk edit operations
			add_action( 'bulk_edit_posts', array( $this, 'uv_handle_bulk_edit' ) );
			
			// Handle product duplication to sync stock
			add_action( 'woocommerce_product_duplicate', array( $this, 'uv_handle_product_duplicate' ), 10, 2 );
			
			// Prevent permanent deletion of products with existing gift cards
			add_action( 'before_delete_post', array( $this, 'uv_prevent_product_deletion' ) );
		}
	}

	/**
	 * Add notice about UniVoucher automatic stock management.
	 */
	public function add_univoucher_stock_notice() {
		global $product_object;
		
		if ( ! $product_object || ! $this->is_univoucher_enabled( $product_object ) ) {
			return;
		}
		
		?>
		<div class="univoucher-stock-notice" style="background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px; padding: 12px; margin: 25px; display: block;">
			<h4 style="margin: 0 0 8px 0; color: #0073aa; font-size: 14px;">
				<span class="dashicons dashicons-info" style="font-size: 16px; margin-right: 5px;"></span>
				<?php esc_html_e( 'UniVoucher Gift Card Stock Management', 'univoucher-for-woocommerce' ); ?>
			</h4>
			<p style="margin: 0; color: #0073aa; font-size: 13px; line-height: 1.4;">
				<?php esc_html_e( 'Since UniVoucher Gift Card is activated for this product, stock management (Track stock quantity) is automatically enabled and stock quantity is calculated by UniVoucher inventory management.', 'univoucher-for-woocommerce' ); ?>
			</p>
			<p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-inventory&product_id=' . ( $product_object ? $product_object->get_id() : 0 ) ) ); ?>" style="color: #0073aa; text-decoration: none;">
					<?php esc_html_e( 'Manage UniVoucher inventory →', 'univoucher-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets for UniVoucher notices.
	 */
	public function enqueue_univoucher_admin_assets( $hook ) {
		global $post;
		
		// Only on product edit pages
		if ( 'post.php' !== $hook || ! $post || 'product' !== $post->post_type ) {
			return;
		}
		
		$product = wc_get_product( $post->ID );
		if ( ! $product || ! $this->is_univoucher_enabled( $product ) ) {
			return;
		}
		
		// Enqueue the UniVoucher product manager JavaScript
		wp_enqueue_script(
			'univoucher-product-manager',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/univoucher-product-manager.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/univoucher-product-manager.js' ),
			true
		);
		
		// Localize script for translations
		wp_localize_script( 'univoucher-product-manager', 'univoucherProductManager', array(
			'autoManagedText'  => esc_attr__( 'This value is automatically managed by UniVoucher', 'univoucher-for-woocommerce' ),
			'autoManagedLabel' => esc_html__( 'Auto-managed by UniVoucher', 'univoucher-for-woocommerce' ),
		));
		
		// Add inline CSS for better notice styling
		$css = '
		.univoucher-stock-notice {
			animation: fadeIn 0.3s ease-in;
		}
		@keyframes fadeIn {
			from { opacity: 0; }
			to { opacity: 1; }
		}
		';
		
		wp_add_inline_style( 'wp-admin', $css );
	}

	/**
	 * Enqueue quick edit scripts on product list pages.
	 */
	public function uv_enqueue_quick_edit_scripts( $hook ) {
		// Only on product list edit page
		if ( 'edit.php' !== $hook ) {
			return;
		}
		
		global $typenow;
		if ( 'product' !== $typenow ) {
			return;
		}
		
		// Get UniVoucher product IDs
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_univoucher_enabled',
					'value'   => 'yes',
					'compare' => '=',
				),
			),
			'fields' => 'ids',
		);
		
		$univoucher_products = get_posts( $args );
		
		if ( empty( $univoucher_products ) ) {
			return;
		}
		
		// Enqueue the quick edit script
		wp_enqueue_script(
			'univoucher-quick-edit',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/univoucher-quick-edit.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/univoucher-quick-edit.js' ),
			true
		);
		
		// Localize script with UniVoucher product IDs and translations
		wp_localize_script( 'univoucher-quick-edit', 'univoucherQuickEdit', array(
			'univoucherIds'      => $univoucher_products,
			'autoManagedLabel'   => esc_html__( 'Auto-managed by UniVoucher', 'univoucher-for-woocommerce' ),
		));
		?>
		<?php
	}

	/**
	 * Get product object from mixed input (product object or product ID).
	 *
	 * @param mixed $product Product object or product ID.
	 * @return WC_Product|null Product object or null if not found.
	 */
	private function uv_get_product_object( $product ) {
		if ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) {
			return $product;
		} elseif ( is_numeric( $product ) ) {
			return wc_get_product( $product );
		}
		return null;
	}

	/**
	 * Check if UniVoucher is enabled for a product.
	 *
	 * @param mixed $product Product object or product ID.
	 * @return bool Whether UniVoucher is enabled.
	 */
	public function is_univoucher_enabled( $product ) {
		$product_obj = $this->uv_get_product_object( $product );
		if ( ! $product_obj ) {
			return false;
		}
		return 'yes' === $product_obj->get_meta( '_univoucher_enabled' );
	}

	/**
	 * Check if a product has existing gift cards.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True if the product has existing gift cards, false otherwise.
	 */
	public function product_has_existing_cards( $product_id ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}univoucher_gift_cards WHERE product_id = %d",
			$product_id
		) );

		$has_cards = (int) $count > 0;

		return $has_cards;
	}

	/**
	 * Get the count of gift cards for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int Number of gift cards for the product.
	 */
	public function get_product_cards_count( $product_id ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}univoucher_gift_cards WHERE product_id = %d",
			$product_id
		) );

		$count = (int) $count;

		return $count;
	}

	/**
	 * Prevent permanent deletion of products with existing gift cards.
	 *
	 * @param int $post_id Post ID.
	 */
	public function uv_prevent_product_deletion( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( $this->product_has_existing_cards( $post_id ) ) {
			$cards_count = $this->get_product_cards_count( $post_id );
			$product = wc_get_product( $post_id );
			/* translators: %d is the product ID */
			$product_name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'univoucher-for-woocommerce' ), $post_id );
			
			wp_die(
				sprintf(
					// translators: %1$s is the product name, %2$d is the product ID, %3$d is the number of gift cards
					esc_html__( 'You cannot delete "%1$s" (Product #%2$d) as it has %3$d existing gift card/s in the inventory. You must delete all gift cards connected to this product from the inventory before you can permanently delete this product.', 'univoucher-for-woocommerce' ),
					esc_html( $product_name ),
					absint( $post_id ),
					absint( $cards_count )
				),
				esc_html__( 'Product with Gift Cards', 'univoucher-for-woocommerce' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Handle WordPress bulk edit operations for UniVoucher products.
	 *
	 * @param array $post_ids Array of post IDs that were bulk edited.
	 */
	public function uv_handle_bulk_edit( $post_ids ) {
		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			// Check if this is a product post type
			if ( 'product' !== get_post_type( $post_id ) ) {
				continue;
			}

			// Check if UniVoucher is enabled for this product
			if ( ! $this->is_univoucher_enabled( $post_id ) ) {
				continue;
			}

			// Handle the product save/edit
			$this->uv_handle_product_save( $post_id );
		}
	}

	/**
	 * Handle product save and edit events for UniVoucher enabled products.
	 * This method is triggered when a product is saved or edited and ensures
	 * stock management is activated for UniVoucher products.
	 *
	 * @param int $product_id Product ID.
	 */
	public function uv_handle_product_save( $product_id ) {
		// Check if this is a product post type
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Check if UniVoucher is enabled for this product
		if ( ! $this->is_univoucher_enabled( $product_id ) ) {
			return;
		}

		// Activate stock management if not already activated
		$current_manage = get_post_meta( $product_id, '_manage_stock', true );
		if ( $current_manage !== 'yes' ) {
			update_post_meta( $product_id, '_manage_stock', 'yes' );
			
			// Sync product stock with UniVoucher inventory
			$stock_manager = UniVoucher_WC_Stock_Manager::instance();
			$stock_manager->uv_sync_product_stock( $product_id );
		}
	}

	/**
	 * Handle product duplication for UniVoucher enabled products.
	 * This method is triggered when a product is duplicated and ensures
	 * stock management is properly synced for the duplicated product.
	 *
	 * @param WC_Product $duplicate The duplicated product object.
	 * @param WC_Product $product The original product object.
	 */
	public function uv_handle_product_duplicate( $duplicate, $product ) {
		// Check if the duplicated product has UniVoucher enabled
		if ( ! $this->is_univoucher_enabled( $duplicate ) ) {
			return;
		}

		// Ensure stock management is activated for the duplicated product
		$duplicate_id = $duplicate->get_id();
		$current_manage = get_post_meta( $duplicate_id, '_manage_stock', true );
		
		if ( $current_manage !== 'yes' ) {
			update_post_meta( $duplicate_id, '_manage_stock', 'yes' );
		}

		// Sync product stock with UniVoucher inventory
		$stock_manager = UniVoucher_WC_Stock_Manager::instance();
		$stock_manager->uv_sync_product_stock( $duplicate_id );
	}
}