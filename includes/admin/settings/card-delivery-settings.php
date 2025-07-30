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
	$require_processing = get_option( 'univoucher_wc_require_processing_if_missing_cards', true );
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Order Auto-Completion', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Automatically mark orders as "Completed" when they contain only UniVoucher products or other products that don\'t require processing, with sufficient inventory available.', 'univoucher-for-woocommerce' ); ?>
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
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with only UniVoucher products or other non-processing products will auto-complete if sufficient inventory is available', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Orders with mixed products (UniVoucher + physical items) will still require manual processing', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Gift cards will be delivered immediately upon order completion', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'If inventory is insufficient, orders will require manual processing regardless of this setting', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>

		<div id="inventory-processing-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e1e5e9; <?php echo $auto_complete ? '' : 'display: none;'; ?>">
			<h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px;">
				<?php esc_html_e( 'Inventory-Based Processing (recommended)', 'univoucher-for-woocommerce' ); ?>
			</h5>
			
			<div style="margin-bottom: 15px;">
				<p style="margin: 5px 0 8px 0; font-size: 13px;">
					<?php esc_html_e( 'Control whether orders should require manual processing based on available inventory for UniVoucher products.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="univoucher_wc_require_processing_if_missing_cards" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="univoucher_wc_require_processing_if_missing_cards"
						name="univoucher_wc_require_processing_if_missing_cards"
						value="1"
						<?php checked( $require_processing, true ); ?>
						style="margin-right: 10px;"
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'Require manual processing when inventory is insufficient', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'When enabled, orders will only auto-complete if there is sufficient available inventory for all UniVoucher products in the order.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-warning" style="margin-top: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
				<strong style="color: #721c24;">
					<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Warning:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<span style="font-size: 13px; color: #721c24;">
					<?php esc_html_e( 'If disabled, orders will auto-complete regardless of inventory availability, which may lead to orders with unassigned gift cards.', 'univoucher-for-woocommerce' ); ?>
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
			<?php esc_html_e( 'Order Details Display', 'univoucher-for-woocommerce' ); ?>
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

		<div class="univoucher-settings-box-warning" style="margin-top: 15px;">
			<strong style="color: #856404;">
				<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Important Note:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<span style="font-size: 13px; color: #856404;">
				<?php esc_html_e( 'If disabled, customers will not see their gift card details on the website. Ensure you have an alternative delivery method in place.', 'univoucher-for-woocommerce' ); ?>
			</span>
		</div>
	</div>

	<?php
}

/**
 * Send email cards field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_send_email_cards_callback( $args ) {
	$send_email = get_option( 'univoucher_wc_send_email_cards', true );
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