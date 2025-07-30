<?php
/**
 * UniVoucher Stock Sync Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stock sync section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_stock_sync_section_callback( $args ) {
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
function univoucher_stock_sync_callback( $args ) {
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
 * AJAX handler for syncing a single product's stock.
 */
function univoucher_ajax_sync_single_product() {
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
function univoucher_ajax_sync_all_products() {
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