<?php
/**
 * UniVoucher Card Delivery Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delivery section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_delivery_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Configure how gift cards are delivered to customers on order completion ( order status changes to "Completed").', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Auto-complete orders field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_auto_complete_orders_callback( $args ) {
	$auto_complete = get_option( 'univoucher_wc_auto_complete_orders', true );
	$backorder_status = get_option( 'univoucher_wc_backorder_initial_status', 'processing' );
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Order Auto-Completion', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Automatically mark orders as "Completed" when they contain only UniVoucher products or other products that don\'t require processing.', 'univoucher-for-woocommerce' ); ?>
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
				<div style="margin: 2px 0;">• <?php esc_html_e( 'This tells WooCommerce that UniVoucher products are non-processing products (don\'t need manual processing).', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with only UniVoucher products or other non-processing products will auto-complete.', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with mixed products (UniVoucher + physical items) will still require manual processing.', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Gift cards will be delivered immediately upon order completion.', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>

		<div id="inventory-processing-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e1e5e9; <?php echo $auto_complete ? '' : 'display: none;'; ?>">
			<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
				<?php esc_html_e( 'Backorder Initial Status', 'univoucher-for-woocommerce' ); ?>
			</h5>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Set the initial order status when inventory is insufficient for UniVoucher products.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="univoucher_wc_backorder_initial_status" style="display: block; margin: 0 0 10px 0;">
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Initial status for backordered items:', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<select
					id="univoucher_wc_backorder_initial_status"
					name="univoucher_wc_backorder_initial_status"
					style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
				>
					<option value="processing" <?php selected( $backorder_status, 'processing' ); ?>>
						<?php esc_html_e( 'Processing', 'univoucher-for-woocommerce' ); ?>
					</option>
					<option value="completed" <?php selected( $backorder_status, 'completed' ); ?>>
						<?php esc_html_e( 'Completed', 'univoucher-for-woocommerce' ); ?>
					</option>
				</select>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'Choose whether orders with insufficient inventory should be marked as "Processing" (requires manual intervention) or "Completed" (auto-completes but may have unassigned cards).', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
				<strong style="color: #721c24;">
					<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Warning:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #721c24;">
					<?php esc_html_e( 'If set to "Completed", orders will auto-complete regardless of inventory availability, make sure to enable auto-create backordered cards to avoid orders with unassigned gift cards.', 'univoucher-for-woocommerce' ); ?>
				</span>
			</div>
		</div>

		<!-- Line spacer -->
		<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e5e9;">
			<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
				<?php esc_html_e( 'Auto-Create Backordered Cards', 'univoucher-for-woocommerce' ); ?>
			</h5>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'For UniVoucher products that have backorder feature enabled, automatically create missing gift cards (backordered) using your internal wallet private key. Cards will be created via UniVoucher API and assigned to orders once confirmed.', 'univoucher-for-woocommerce' ); ?>
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
						<?php esc_html_e( 'Auto-create missing cards using internal wallet', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, missing cards (backordered) will be automatically created via UniVoucher API using your configured internal wallet private key.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px;">
				<strong style="color: #856404;">
					<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'How it works:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<div style="margin-top: 8px; font-size: 12px; color: #856404;">
					<div style="margin: 2px 0;">• <?php esc_html_e( 'This feature is only available for UniVoucher products that have backorder feature enabled', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'When inventory is insufficient and backordered cards are requested, the system will request card creation via UniVoucher API', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'Once cards are created, they will be automatically added to inventory and assigned to the order', 'univoucher-for-woocommerce' ); ?></div>
					<div style="margin: 2px 0;">• <?php esc_html_e( 'Requires a valid internal wallet private key to be configured in the internal wallet settings', 'univoucher-for-woocommerce' ); ?></div>
				</div>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
				<strong style="color: #721c24;">
					<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Important:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #721c24;">
					<?php esc_html_e( 'This feature requires your internal wallet to have sufficient balance for card creation fees and gas costs. Make sure your wallet is properly configured in the Internal Wallet settings.', 'univoucher-for-woocommerce' ); ?>
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

/**
 * Show customer cards field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_show_customer_cards_callback( $args ) {
	$show_cards = get_option( 'univoucher_wc_show_in_order_details', true );
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Show Gift Cards (for completed orders only)', 'univoucher-for-woocommerce' ); ?>
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

		<div id="cards-display-position-option" style="margin-top: 15px; padding-left: 20px; border-left: 3px solid #0c5460; <?php echo $show_cards ? '' : 'display: none;'; ?>">
			<label for="univoucher_wc_cards_display_position" style="font-weight: bold; display: block; margin-bottom: 8px;">
				<?php esc_html_e( 'Display Position:', 'univoucher-for-woocommerce' ); ?>
			</label>
			<select
				id="univoucher_wc_cards_display_position"
				name="univoucher_wc_cards_display_position"
				style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
			>
				<option value="before" <?php selected( get_option( 'univoucher_wc_cards_display_position', 'before' ), 'before' ); ?>>
					<?php esc_html_e( 'Before order table', 'univoucher-for-woocommerce' ); ?>
				</option>
				<option value="after" <?php selected( get_option( 'univoucher_wc_cards_display_position', 'before' ), 'after' ); ?>>
					<?php esc_html_e( 'After order table', 'univoucher-for-woocommerce' ); ?>
				</option>
			</select>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'Choose where to display gift cards in relation to the order table on order details and thank you pages.', 'univoucher-for-woocommerce' ); ?>
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
		<!-- Line spacer -->
		<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e5e9;">
		<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
			<?php esc_html_e( 'Backorder notice (if order status is completed)', 'univoucher-for-woocommerce' ); ?>
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
					<?php esc_html_e( 'Show processing notice for unassigned cards', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'When enabled, customers will see a notice on order details and thank you pages when their order contains gift cards that are still being processed (only if order status is completed).', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div style="margin-top: 15px; padding-left: 20px;">
			<label for="univoucher_wc_unassigned_notice_text" style="font-weight: bold; display: block; margin-bottom: 8px;">
				<?php esc_html_e( 'Notice text:', 'univoucher-for-woocommerce' ); ?>
			</label>
			<input
				type="text"
				id="univoucher_wc_unassigned_notice_text"
				name="univoucher_wc_unassigned_notice_text"
				value="<?php echo esc_attr( get_option( 'univoucher_wc_unassigned_notice_text', __( 'Your order contains gift cards that are still being processed. This page will automatically refresh once all cards are ready.', 'univoucher-for-woocommerce' ) ) ); ?>"
				class="large-text"
				style="width: 100%; max-width: 600px; font-size: 14px; padding: 8px;"
				placeholder="<?php esc_attr_e( 'Enter notice text...', 'univoucher-for-woocommerce' ); ?>"
			/>
			<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
				<?php esc_html_e( 'Customize the text shown to customers when their order has unassigned gift cards but order status is completed.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

			</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		var showCardsCheckbox = $('#<?php echo esc_js( $args['label_for'] ); ?>');
		var positionOption = $('#cards-display-position-option');
		
		function togglePositionOption() {
			if (showCardsCheckbox.is(':checked')) {
				positionOption.show();
			} else {
				positionOption.hide();
			}
		}
		
		showCardsCheckbox.on('change', togglePositionOption);
		togglePositionOption(); // Initial state
	});
	</script>

	<?php
}

/**
 * Send email cards field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_send_email_cards_callback( $args ) {
	$send_email = get_option( 'univoucher_wc_send_email_cards', true );
	$send_email_only_fully_assigned = get_option( 'univoucher_wc_send_email_only_fully_assigned', true );
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

		<div class="univoucher-settings-box-info" id="email-fully-assigned-option" style="margin-top: 15px; padding-left: 20px; border-left: 3px solid #0c5460; <?php echo !$send_email ? 'display: none;' : ''; ?>">
			<label for="univoucher_wc_send_email_only_fully_assigned" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_send_email_only_fully_assigned"
					name="univoucher_wc_send_email_only_fully_assigned"
					value="1"
					<?php checked( $send_email_only_fully_assigned, true ); ?>
					style="margin-right: 10px;"
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Only send email if order is fully assigned', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'When enabled, emails will only be sent when all UniVoucher products in the order have their required number of cards assigned. This prevents sending incomplete emails for partially assigned orders.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var emailCheckbox = $('#<?php echo esc_js( $args['label_for'] ); ?>');
			var fullyAssignedOption = $('#email-fully-assigned-option');
			
			function toggleFullyAssignedOption() {
				if (emailCheckbox.is(':checked')) {
					fullyAssignedOption.show();
				} else {
					fullyAssignedOption.hide();
				}
			}
			
			emailCheckbox.on('change', toggleFullyAssignedOption);
			toggleFullyAssignedOption(); // Initial state
		});
		</script>

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