<?php
/**
 * UniVoucher For WooCommerce Admin Tools
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Admin_Tools class.
 */
class UniVoucher_WC_Admin_Tools {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Admin_Tools
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Admin_Tools Instance.
	 *
	 * @return UniVoucher_WC_Admin_Tools - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Admin_Tools Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for AJAX handlers.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_find_missing_cards', array( $this, 'ajax_find_missing_cards' ) );
		add_action( 'wp_ajax_univoucher_sync_single_product', array( $this, 'ajax_sync_single_product' ) );
		add_action( 'wp_ajax_univoucher_sync_all_products', array( $this, 'ajax_sync_all_products' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles for tools page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on tools page.
		if ( strpos( $hook, 'univoucher-tools' ) === false ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'univoucher-wc-tools',
			plugins_url( 'admin/css/tools.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'univoucher-wc-tools',
			plugins_url( 'admin/js/tools.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'univoucher-wc-tools',
			'univoucherTools',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'univoucher_find_missing_cards' ),
				'stockNonce' => wp_create_nonce( 'univoucher_stock_sync' ),
				'i18n'       => array(
					'greatNews'         => __( 'Great news!', 'univoucher-for-woocommerce' ),
					'noMissingCards'    => __( 'No orders with missing cards found.', 'univoucher-for-woocommerce' ),
					'found'             => __( 'Found:', 'univoucher-for-woocommerce' ),
					'ordersWithText'    => __( 'item(s) with', 'univoucher-for-woocommerce' ),
					'missingCardsTotal' => __( 'missing card(s) total.', 'univoucher-for-woocommerce' ),
					'order'             => __( 'Order', 'univoucher-for-woocommerce' ),
					'status'            => __( 'Status', 'univoucher-for-woocommerce' ),
					'product'           => __( 'Product', 'univoucher-for-woocommerce' ),
					'ordered'           => __( 'Ordered', 'univoucher-for-woocommerce' ),
					'assigned'          => __( 'Assigned', 'univoucher-for-woocommerce' ),
					'missing'           => __( 'Missing', 'univoucher-for-woocommerce' ),
					'actions'           => __( 'Actions', 'univoucher-for-woocommerce' ),
					'viewOrder'         => __( 'View Order', 'univoucher-for-woocommerce' ),
					'error'             => __( 'Error:', 'univoucher-for-woocommerce' ),
					'unknownError'      => __( 'Unknown error', 'univoucher-for-woocommerce' ),
					'ajaxError'         => __( 'An unexpected error occurred. Please try again.', 'univoucher-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render the tools page.
	 */
	public function render_page() {
		// Get current tab with nonce verification.
		$active_tab = 'missing-cards'; // Default tab
		if ( isset( $_GET['tab'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'univoucher_tools_tab' ) ) {
				$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			}
		}

		// Define tabs.
		$tabs = array(
			'missing-cards' => array(
				'title' => esc_html__( 'Find Missing Cards', 'univoucher-for-woocommerce' ),
			),
			'stock-sync' => array(
				'title' => esc_html__( 'Stock Sync', 'univoucher-for-woocommerce' ),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UniVoucher Tools', 'univoucher-for-woocommerce' ); ?></h1>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=univoucher-tools&tab=' . $tab_key ), 'univoucher_tools_tab' ) ); ?>"
					   class="nav-tab <?php echo esc_attr( $active_tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
						<?php echo esc_html( $tab_data['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $active_tab === 'missing-cards' ) : ?>
				<?php $this->render_missing_cards_tab(); ?>
			<?php elseif ( $active_tab === 'stock-sync' ) : ?>
				<?php $this->render_stock_sync_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Find Missing Cards tab.
	 */
	private function render_missing_cards_tab() {
		?>
		<div style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Find Orders with Missing Cards', 'univoucher-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'This tool scans all active orders to find those with unassigned gift cards (where ordered quantity exceeds assigned cards).', 'univoucher-for-woocommerce' ); ?></p>

			<div style="margin: 20px 0;">
				<button type="button" id="univoucher-find-missing-cards" class="button button-primary">
					<?php esc_html_e( 'Scan for Missing Cards', 'univoucher-for-woocommerce' ); ?>
				</button>
				<span id="univoucher-scan-spinner" class="spinner" style="float: none; margin-left: 10px;"></span>
			</div>

			<div id="univoucher-missing-cards-results" style="display: none; margin-top: 20px;">
				<h3><?php esc_html_e( 'Scan Results', 'univoucher-for-woocommerce' ); ?></h3>
				<div id="univoucher-results-content"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler to find orders with missing cards.
	 */
	public function ajax_find_missing_cards() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_find_missing_cards' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get all active orders.
		$order_statuses = array( 'processing', 'on-hold', 'completed' );
		$orders_with_missing = array();
		$total_missing = 0;

		// Query orders using WooCommerce functions.
		$args = array(
			'status'   => $order_statuses,
			'limit'    => -1,
			'return'   => 'ids',
		);

		$order_ids = wc_get_orders( $args );

		if ( empty( $order_ids ) ) {
			wp_send_json_success(
				array(
					'orders'        => array(),
					'total_missing' => 0,
				)
			);
		}

		$stock_manager = UniVoucher_WC_Stock_Manager::instance();
		$product_manager = UniVoucher_WC_Product_Manager::instance();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$product = $item->get_product();

				if ( ! $product || ! $product_manager->is_univoucher_enabled( $product_id ) ) {
					continue;
				}

				// Calculate ordered quantity (minus refunds).
				$ordered_qty = $item->get_quantity();
				$refunded_qty = abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
				$net_ordered = $ordered_qty - $refunded_qty;

				if ( $net_ordered <= 0 ) {
					continue;
				}

				// Get assigned cards count using existing functions.
				$assigned_count = $this->get_assigned_cards_count( $order_id, $product_id );

				$missing = $net_ordered - $assigned_count;

				if ( $missing > 0 ) {
					$total_missing += $missing;

					$orders_with_missing[] = array(
						'order_id'     => $order_id,
						'status'       => 'wc-' . $order->get_status(),
						'status_label' => wc_get_order_status_name( $order->get_status() ),
						'product_id'   => $product_id,
						'product_name' => $product->get_name(),
						'ordered_qty'  => $net_ordered,
						'assigned_qty' => $assigned_count,
						'missing_qty'  => $missing,
						'edit_url'     => $order->get_edit_order_url(),
					);
				}
			}
		}

		wp_send_json_success(
			array(
				'orders'        => $orders_with_missing,
				'total_missing' => $total_missing,
			)
		);
	}

	/**
	 * Get the count of assigned cards for a specific order and product.
	 *
	 * @param int $order_id Order ID.
	 * @param int $product_id Product ID.
	 * @return int Number of assigned cards.
	 */
	private function get_assigned_cards_count( $order_id, $product_id ) {
		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();

		// Get all gift cards for this order using existing function.
		$all_cards = $gift_card_manager->uv_get_gift_cards_for_order( $order_id );

		// Filter by product_id and status = 'sold'.
		$count = 0;
		foreach ( $all_cards as $card ) {
			if ( (int) $card->product_id === (int) $product_id && $card->status === 'sold' ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Render the Stock Sync tab.
	 */
	private function render_stock_sync_tab() {
		global $wpdb;

		// Get all UniVoucher enabled products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
						absint( $total_products )
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

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

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

		wp_send_json_success(
			array(
				'message' => sprintf(
					// translators: %1$s is the product name, %2$d is the stock quantity, %3$s is the stock status
					esc_html__( 'Product "%1$s" synced successfully. Stock: %2$d (%3$s)', 'univoucher-for-woocommerce' ),
					$product->get_name(),
					$stock_quantity,
					$stock_status
				),
				'product_name'   => $product->get_name(),
				'stock_quantity' => $stock_quantity,
				'stock_status'   => $stock_status,
			)
		);
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

		$batch_size = isset( $_POST['batch_size'] ) ? absint( wp_unslash( $_POST['batch_size'] ) ) : 5;
		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;

		global $wpdb;

		// Get UniVoucher enabled products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$products = $wpdb->get_results(
			$wpdb->prepare(
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
			)
		);

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
					'id'             => $product_id,
					'name'           => $product_name,
					'success'        => true,
					'stock_quantity' => $stock_quantity,
					'stock_status'   => $stock_status,
					'message'        => sprintf(
						// translators: %1$s is the product name, %2$d is the stock quantity, %3$s is the stock status
						esc_html__( 'Synced: %1$s - Stock: %2$d (%3$s)', 'univoucher-for-woocommerce' ),
						$product_name,
						$stock_quantity,
						$stock_status
					),
				);
			} catch ( Exception $e ) {
				$results[] = array(
					'id'      => $product_id,
					'name'    => $product_name,
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

		wp_send_json_success(
			array(
				'results'     => $results,
				'processed'   => $processed_count,
				'total'       => $total_products,
				'is_complete' => $is_complete,
				'next_offset' => $processed_count,
			)
		);
	}
}
