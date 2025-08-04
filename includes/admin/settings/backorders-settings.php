<?php
/**
 * UniVoucher Backorders Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backorders section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_backorders_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'UniVoucher on-demand feature uses WooCommerce\'s native backorder feature as the fundamental business logic. In on-demand selling, you don\'t have actual stock in inventory or your actual stock is insufficient, so customers have to "backorder" the cards. At this point, our on-demand feature automatically creates the backordered cards and assigns them to orders - just like they were specially made by the factory on demand, but instantly.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Backorder initial status field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_backorder_initial_status_callback( $args ) {
	$backorder_status = get_option( 'univoucher_wc_backorder_initial_status', 'processing' );
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'On-Demand Order Status', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Set the initial order status when customers place orders for cards that need to be created on-demand (backordered).', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-info">
			<label for="univoucher_wc_backorder_initial_status" style="display: block; margin: 0 0 10px 0;">
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Initial status for on-demand orders:', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<select
				id="univoucher_wc_backorder_initial_status"
				name="univoucher_wc_backorder_initial_status"
				style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
			>
				<option value="processing" <?php selected( $backorder_status, 'processing' ); ?>>
					<?php esc_html_e( 'Processing (recommended)', 'univoucher-for-woocommerce' ); ?>
				</option>
				<option value="completed" <?php selected( $backorder_status, 'completed' ); ?>>
					<?php esc_html_e( 'Completed', 'univoucher-for-woocommerce' ); ?>
				</option>
			</select>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'Choose whether on-demand orders should be marked as "Processing" until cards are actually created or immediately marked as "Completed" regardless what could happen with the card creation process.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<!-- Backorder Notice Section -->
		<div id="backorder-notice-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e5e9;">
			<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
				<?php esc_html_e( 'On-demand processing notice', 'univoucher-for-woocommerce' ); ?>
			</h5>
			
			<div class="univoucher-settings-box-info" style="margin-top: 15px; padding-left: 20px; border-left: 3px solid #0c5460;">
				<label for="univoucher_wc_show_unassigned_notice" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="univoucher_wc_show_unassigned_notice"
						name="univoucher_wc_show_unassigned_notice"
						value="1"
						<?php checked( get_option( 'univoucher_wc_show_unassigned_notice', true ), true ); ?>
						style="margin-right: 10px;"
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Show processing notice for on-demand cards', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, customers will see a notice on order details and thank you pages when their order contains cards that are still being created on-demand.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div id="univoucher-notice-text-options" style="margin-top: 15px; padding-left: 20px; <?php echo get_option( 'univoucher_wc_show_unassigned_notice', true ) ? '' : 'display: none;'; ?>">
				<label for="univoucher_wc_unassigned_notice_text" style="font-weight: bold; display: block; margin-bottom: 8px;">
					<?php esc_html_e( 'Notice text:', 'univoucher-for-woocommerce' ); ?>
				</label>
				<input
					type="text"
					id="univoucher_wc_unassigned_notice_text"
					name="univoucher_wc_unassigned_notice_text"
					value="<?php echo esc_attr( get_option( 'univoucher_wc_unassigned_notice_text', __( 'Your order contains gift cards that are still being processed. This page will automatically refresh once all cards are ready.', 'univoucher-for-woocommerce' ) ) ); ?>"
					class="large-text"
					style="width: 100%; max-width: 1000px; font-size: 14px; padding: 8px;"
					placeholder="<?php esc_attr_e( 'Enter notice text...', 'univoucher-for-woocommerce' ); ?>"
				/>
				<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
					<?php esc_html_e( 'Customize the text shown to customers when their order has cards that are still being created on-demand.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<script>
			jQuery(document).ready(function($) {
				$('#univoucher_wc_show_unassigned_notice').on('change', function() {
					if ($(this).is(':checked')) {
						$('#univoucher-notice-text-options').show();
					} else {
						$('#univoucher-notice-text-options').hide();
					}
				});
			});
			</script>
		</div>


	</div>

	<?php
}

/**
 * Auto-create backordered cards field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_auto_create_backordered_cards_callback( $args ) {
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Enable On-Demand Automation', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'For UniVoucher products with backorder feature enabled, backordered cards will be automatically created on demand using your internal wallet private key. Cards are created via UniVoucher API and assigned to orders once confirmed.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-info">
			<label for="univoucher_wc_auto_create_backordered_cards" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_auto_create_backordered_cards"
					name="univoucher_wc_auto_create_backordered_cards"
					value="1"
					<?php checked( get_option( 'univoucher_wc_auto_create_backordered_cards', false ), true ); ?>
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Automatically create cards on demand using internal wallet and UniVoucher API', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'When enabled, backordered cards will be automatically created on demand via UniVoucher API using your configured internal wallet private key.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-warning" style="margin-top: 15px;">
			<strong style="color: #856404;">
				<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'How it works:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 12px; color: #856404;">
				<div style="margin: 2px 0;">• <?php esc_html_e( 'This feature is only available for UniVoucher products that have backorder feature enabled', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'When inventory is insufficient and customers place orders, the system will automatically create cards on-demand via UniVoucher API', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Once cards are created, they will be automatically added to inventory and assigned to the order', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Requires a valid internal wallet private key to be configured in the internal wallet settings', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>

		<div style="margin-top: 15px;">
			<strong>
				<?php esc_html_e( 'Requirements Check:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 13px;">
				<?php
				// Check internal wallet configuration
				$wallet_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
				$wallet_configured = !empty( $wallet_private_key );
				
				// Check if at least one UniVoucher product has backorder enabled
				$backorder_products = get_posts( array(
					'post_type' => 'product',
					'post_status' => 'publish',
					'meta_query' => array(
						array(
							'key' => '_univoucher_enabled',
							'value' => 'yes',
							'compare' => '='
						)
					),
					'posts_per_page' => 1
				) );
				
				// Check if any of these UniVoucher products have backorder enabled
				$backorder_enabled = false;
				if ( !empty( $backorder_products ) ) {
					foreach ( $backorder_products as $product_post ) {
						$product = wc_get_product( $product_post->ID );
						if ( $product ) {
							// Check if backorder is enabled using WooCommerce's method
							$backorder_status = $product->get_backorders();
							if ( in_array( $backorder_status, array( 'yes', 'notify' ) ) ) {
								$backorder_enabled = true;
								break;
							}
						}
					}
				}
				?>
				
				<div style="margin: 2px 0; display: flex; align-items: center;">
					<span class="dashicons dashicons-<?php echo $wallet_configured ? 'yes' : 'no'; ?>" style="color: <?php echo $wallet_configured ? '#28a745' : '#dc3545'; ?>; margin-right: 5px;"></span>
					<span><?php esc_html_e( 'Internal wallet private key is configured', 'univoucher-for-woocommerce' ); ?></span>
				</div>
				
				<div style="margin: 2px 0; display: flex; align-items: center;">
					<span class="dashicons dashicons-<?php echo $backorder_enabled ? 'yes' : 'no'; ?>" style="color: <?php echo $backorder_enabled ? '#28a745' : '#dc3545'; ?>; margin-right: 5px;"></span>
					<span><?php esc_html_e( 'At least one UniVoucher product has backorder feature enabled', 'univoucher-for-woocommerce' ); ?></span>
				</div>
				
				<?php if ( !$wallet_configured || !$backorder_enabled ) : ?>
					<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
						<strong><?php esc_html_e( 'To enable on-demand card creation:', 'univoucher-for-woocommerce' ); ?></strong>
						<ul style="margin: 5px 0 0 0; padding-left: 20px;">
							<?php if ( !$wallet_configured ) : ?>
								<li><?php esc_html_e( 'Go to UniVoucher Settings → Internal Wallet tab and configure your wallet private key', 'univoucher-for-woocommerce' ); ?></li>
							<?php endif; ?>
							<?php if ( !$backorder_enabled ) : ?>
								<li><?php esc_html_e( 'Edit a UniVoucher product → Inventory tab → Set "Allow backorders?" to "Allow" or "Allow, but notify customer"', 'univoucher-for-woocommerce' ); ?></li>
							<?php endif; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php
}

/**
 * On-demand limit settings field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_on_demand_limit_settings_callback( $args ) {
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'On-Demand Limit', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'The on-demand limit is calculated based on the available balance in your internal wallet for the specific token used by each UniVoucher product. This limit represents the maximum number of cards that can be created on-demand using your current wallet balance.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-info" style="margin-top: 15px;">
			<label for="univoucher_wc_show_on_demand_limit" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_show_on_demand_limit"
					name="univoucher_wc_show_on_demand_limit"
					value="1"
					<?php checked( get_option( 'univoucher_wc_show_on_demand_limit', true ), true ); ?>
					style="margin-right: 10px;"
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Show on-demand limit in WooCommerce products admin page', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'When enabled, the on-demand limit will be displayed in the Stock column of the WooCommerce products admin page for UniVoucher products with backorder enabled.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-info" style="margin-top: 15px;">
			<label for="univoucher_wc_on_demand_order_limit" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_on_demand_order_limit"
					name="univoucher_wc_on_demand_order_limit"
					value="1"
					<?php checked( get_option( 'univoucher_wc_on_demand_order_limit', true ), true ); ?>
					style="margin-right: 10px;"
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Limit orders to (available stock + on-demand limit)', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'Example: If you have 5 cards in stock and 10 cards on-demand limit, customers can order a maximum of 15 cards.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div id="univoucher-order-limit-options" style="margin-top: 15px; padding-left: 20px; <?php echo get_option( 'univoucher_wc_on_demand_order_limit', true ) ? '' : 'display: none;'; ?>">
			<div style="margin-bottom: 15px;">
				<label for="univoucher_wc_on_demand_cart_limit" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="univoucher_wc_on_demand_cart_limit"
						name="univoucher_wc_on_demand_cart_limit"
						value="1"
						<?php checked( get_option( 'univoucher_wc_on_demand_cart_limit', true ), true ); ?>
						style="margin-right: 10px;"
					/>
					<strong>
						<?php esc_html_e( 'Limit cart to (available stock + on-demand limit)', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #dc3545; font-style: italic;">
					<?php esc_html_e( 'Note: The limit cart option may not be supported by all themes, templates, and UI blocks.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div>
				<label for="univoucher_wc_on_demand_error_message" style="font-weight: bold; display: block; margin-bottom: 8px;">
					<?php esc_html_e( 'Error message:', 'univoucher-for-woocommerce' ); ?>
				</label>
				<input
					type="text"
					id="univoucher_wc_on_demand_error_message"
					name="univoucher_wc_on_demand_error_message"
					value="<?php echo esc_attr( get_option( 'univoucher_wc_on_demand_error_message', 'Sorry, but the maximum available quantity from {product_name} is {maximum_quantity}. You have {cart_quantity} in cart.' ) ); ?>"
					class="large-text"
					style="width: 100%; max-width: 1000px; font-size: 14px; padding: 8px;"
					placeholder="<?php esc_attr_e( 'Enter error message...', 'univoucher-for-woocommerce' ); ?>"
				/>
				<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
					<?php esc_html_e( 'Message shown when customers try to order more than the available limit. Use {product_name} for product name, {maximum_quantity} for maximum quantity, and {cart_quantity} for current cart quantity.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#univoucher_wc_on_demand_order_limit').on('change', function() {
				if ($(this).is(':checked')) {
					$('#univoucher-order-limit-options').show();
				} else {
					$('#univoucher-order-limit-options').hide();
				}
			});
		});
		</script>

		<div class="univoucher-settings-box-warning" style="margin-top: 20px;">
			<strong style="color: #856404;">
				<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'How it Works:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 12px; color: #856404;">
				<div style="margin: 2px 0;">• <?php esc_html_e( 'On-Demand Limit = Internal Wallet balance ÷ Card amount', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Example: 500 USDT wallet balance ÷ 50 USDT card amount = 10 cards maximum', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'On-demand limits are calculated in real-time based on your current wallet balance', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Gas fees required for blockchain transactions are not included in the limit calculation', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'If your wallet balance changes, the on-demand limit will automatically update', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>
	</div>

	<?php
} 