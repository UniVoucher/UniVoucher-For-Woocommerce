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
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_univoucher_admin_styles' ) );
			
			// Disable quick edit stock fields
			add_action( 'admin_footer-edit.php', array( $this, 'uv_disable_quick_edit_stock_fields' ) );
			
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
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Make stock fields disabled for UniVoucher products but preserve values with hidden fields
			$('#_manage_stock, #_stock, #_stock_status').prop('disabled', true);
			
			// Add hidden fields to preserve values during form submission
			$('#_stock').after('<input type="hidden" name="_stock" value="' + $('#_stock').val() + '">');
			$('#_manage_stock').after('<input type="hidden" name="_manage_stock" value="yes">');
			$('#_stock_status').after('<input type="hidden" name="_stock_status" value="' + $('#_stock_status').val() + '">');
			
			// Add visual indication
			$('#_stock').css({
				'background-color': '#f9f9f9',
				'color': '#666'
			}).attr('title', '<?php esc_attr_e( 'This value is automatically managed by UniVoucher', 'univoucher-for-woocommerce' ); ?>');
			
			// Add notice to stock field
			$('#_stock').after('<span style="color: #666; font-size: 11px; margin-left: 8px;"><?php esc_html_e( '(Auto-managed by UniVoucher)', 'univoucher-for-woocommerce' ); ?></span>');
		});
		</script>
		<?php
	}

	/**
	 * Enqueue admin styles for UniVoucher notices.
	 */
	public function enqueue_univoucher_admin_styles( $hook ) {
		global $post;
		
		// Only on product edit pages
		if ( 'post.php' !== $hook || ! $post || 'product' !== $post->post_type ) {
			return;
		}
		
		$product = wc_get_product( $post->ID );
		if ( ! $product || ! $this->is_univoucher_enabled( $product ) ) {
			return;
		}
		
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
	 * Disable quick edit stock fields.
	 */
	public function uv_disable_quick_edit_stock_fields() {
		global $current_screen;
		
		if ( ! $current_screen || 'edit-product' !== $current_screen->id ) {
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
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var univoucherIds = <?php echo wp_json_encode( $univoucher_products ); ?>;
			
			$(document).on('click', '.editinline', function() {
				var postId = parseInt($(this).closest('tr').attr('id').replace('post-', ''));
				
				if (univoucherIds.includes(postId)) {
					setTimeout(function() {
						var $stockField = $('.inline-edit-product').find('input[name="_stock"]');
						var $manageStockField = $('.inline-edit-product').find('input[name="_manage_stock"]');
						
						// Only modify if not already processed
						if (!$stockField.prop('disabled')) {
							// Disable the fields and add hidden fields to preserve values
							$stockField.prop('disabled', true)
								.css('background-color', '#f9f9f9')
								.after('<input type="hidden" name="_stock" value="' + $stockField.val() + '">')
								.after('<br><small style="color:#666;">Auto-managed by UniVoucher</small>');
							$manageStockField.prop('disabled', true)
								.css('opacity', '0.5')
								.after('<input type="hidden" name="_manage_stock" value="yes">');
						}
					}, 100);
				}
			});
		});
		</script>
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
		$table = $database->uv_get_gift_cards_table();

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE product_id = %d",
			$product_id
		) );

		return (int) $count > 0;
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
		$table = $database->uv_get_gift_cards_table();

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE product_id = %d",
			$product_id
		) );

		return (int) $count;
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
			$product_name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'univoucher-for-woocommerce' ), $post_id );
			
			wp_die(
				sprintf(
					/* translators: %1$s is the product name, %2$d is the product ID, %3$d is the number of gift cards */
					esc_html__( 'You cannot delete "%1$s" (Product #%2$d) as it has %3$d existing gift card/s in the inventory. You must delete all gift cards connected to this product from the inventory before you can permanently delete this product.', 'univoucher-for-woocommerce' ),
					$product_name,
					$post_id,
					$cards_count
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