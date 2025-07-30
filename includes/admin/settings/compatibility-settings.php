<?php
/**
 * UniVoucher Compatibility Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_compatibility_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Integrate UniVoucher gift cards with third-party plugins for enhanced functionality.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * License Manager integration field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_lmfwc_integration_callback( $args ) {
	$lmfwc_integration = get_option( 'univoucher_wc_lmfwc_integration', false );
	$lmfwc_active = class_exists( 'LicenseManagerForWooCommerce\Main' );
	$license_key_template = get_option( 'univoucher_wc_lmfwc_license_key_template', 'Card ID: {card_id} Card Secret: {card_secret} Network: {card_network} Abandoned on {card_abandoned}' );
	$show_abandoned_date = get_option( 'univoucher_wc_lmfwc_show_abandoned_date', true );
	$include_in_all_licenses = get_option( 'univoucher_wc_lmfwc_include_in_all_licenses', true );
	?>
	
	<!-- Box 1: Main Integration Toggle with How it works -->
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'License Manager Plugin Integration', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div class="univoucher-settings-box-info">
			<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="<?php echo esc_attr( $args['label_for'] ); ?>"
					value="1"
					<?php checked( $lmfwc_integration, true ); ?>
					<?php echo ! $lmfwc_active ? 'disabled' : ''; ?>
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Enable License Manager integration', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php 
				printf(
					/* translators: %s: Link to License Manager for WooCommerce plugin */
					esc_html__( 'When enabled, UniVoucher gift cards will be displayed alongside license keys in customer-facing pages of %s plugin.', 'univoucher-for-woocommerce' ),
					'<a href="https://wordpress.org/plugins/license-manager-for-woocommerce/" target="_blank" rel="noopener noreferrer">License Manager for WooCommerce</a>'
				); 
				?>
			</p>
		</div>

		<?php if ( ! $lmfwc_active ) : ?>
			<div class="univoucher-settings-box-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin-top: 15px;">
				<strong style="color: #856404;">
					<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
					<?php esc_html_e( 'Plugin Not Active', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #856404;">
					<?php 
					printf(
						/* translators: %s: Link to License Manager for WooCommerce plugin */
						esc_html__( '%s plugin is not active. This integration will not work until the plugin is activated.', 'univoucher-for-woocommerce' ),
						'<a href="https://wordpress.org/plugins/license-manager-for-woocommerce/" target="_blank" rel="noopener noreferrer">License Manager for WooCommerce</a>'
					); 
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="univoucher-settings-box-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin-top: 15px;">
			<strong style="color: #856404;">
				<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'How it works:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 12px; color: #856404;">
				<div style="margin: 2px 0;">• <?php esc_html_e( 'This is only a display integration to unify the user experience of UniVoucher gift cards with license keys', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'UniVoucher Gift cards will appear for users in the same places as license keys', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Card details will be combined into a single line format', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Shown in order details page, order emails and Licenses Keys Page in My Account', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;">• <?php esc_html_e( 'Only works for orders with completed status', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>

		<div class="univoucher-settings-box-warning" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px; margin-top: 15px;">
			<strong style="color: #721c24;">
				<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Important:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<p style="margin: 8px 0 0 0; font-size: 12px; color: #721c24;">
				<?php esc_html_e( 'It is recommended to disable UniVoucher\'s native delivery options in the Card Delivery settings page to prevent redundancy and avoid showing cards twice to customers.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
	</div>

	<!-- Box 2: License Key Template Setting -->
	<div class="univoucher-settings-box" id="lmfwc-template-box" style="display: <?php echo ( $lmfwc_active && $lmfwc_integration ) ? 'block' : 'none'; ?>;">
		<h4>
			<?php esc_html_e( 'License Key Template', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<p style="margin: 5px 0 8px 0; font-size: 13px;">
			<?php esc_html_e( 'Template for displaying UniVoucher card details in License Manager. Available placeholders:', 'univoucher-for-woocommerce' ); ?>
		</p>
		
		<div style="margin: 8px 0; font-size: 12px; color: #495057;">
			<div style="margin: 2px 0;">• <code>{card_id}</code> - <?php esc_html_e( 'Card ID', 'univoucher-for-woocommerce' ); ?></div>
			<div style="margin: 2px 0;">• <code>{card_secret}</code> - <?php esc_html_e( 'Card Secret', 'univoucher-for-woocommerce' ); ?></div>
			<div style="margin: 2px 0;">• <code>{card_network}</code> - <?php esc_html_e( 'Network name (e.g., Ethereum, Polygon)', 'univoucher-for-woocommerce' ); ?></div>
			<div style="margin: 2px 0;">• <code>{card_abandoned}</code> - <?php esc_html_e( 'Abandoned date (5 years from creation)', 'univoucher-for-woocommerce' ); ?></div>
		</div>
		
		<div class="univoucher-settings-box-info">
			<textarea 
				id="univoucher_wc_lmfwc_license_key_template" 
				name="univoucher_wc_lmfwc_license_key_template" 
				rows="1" 
				cols="50" 
				style="width: 100%; height: 50px; font-size: 14px; line-height: 1.4; padding: 12px; border: 1px solid #ddd; border-radius: 4px; resize: none; overflow: hidden; white-space: nowrap;"
				oninput="this.style.height = '50px'; this.style.height = this.scrollHeight + 'px';"
				onkeydown="if(event.keyCode==13) event.preventDefault();"><?php echo esc_textarea( $license_key_template ); ?></textarea>
			<button type="button" class="button button-secondary" id="reset-license-key-template" style="margin-top: 8px;">
				<?php esc_html_e( 'Reset to Default', 'univoucher-for-woocommerce' ); ?>
			</button>
			<p style="margin: 8px 0 0 0; font-size: 11px; color: #856404; font-style: italic;">
				<?php esc_html_e( 'Note: License Manager plugin has only one field for the license key, so we combine all card details into this single field.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
	</div>

	<!-- Box 3: Show Abandoned Date Setting -->
	<div class="univoucher-settings-box" id="lmfwc-abandoned-box" style="display: <?php echo ( $lmfwc_active && $lmfwc_integration ) ? 'block' : 'none'; ?>;">
		<h4>
			<?php esc_html_e( 'Show Abandoned Date', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div class="univoucher-settings-box-info">
			<label for="univoucher_wc_lmfwc_show_abandoned_date" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_lmfwc_show_abandoned_date"
					name="univoucher_wc_lmfwc_show_abandoned_date"
					value="1"
					<?php checked( $show_abandoned_date, true ); ?>
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Show abandoned date in License Manager "Valid until" column', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 8px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'If enabled, the abandoned date (5 years from card creation) will be shown in the "Valid until" column instead of "Never Expires".', 'univoucher-for-woocommerce' ); ?>
			</p>
			</div>
			<div class="univoucher-settings-box-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin-top: 8px;">
				<strong style="color: #856404;">
					<?php esc_html_e( 'UniVoucher Abandoned Date:', 'univoucher-for-woocommerce' ); ?>
				</strong>
				<p style="margin: 8px 0 0 0; font-size: 11px; color: #856404;">
					<?php esc_html_e( 'If the card is neither redeemed nor canceled for 5 years, it will be considered abandoned by the UniVoucher smart contract. Only then will the smart contract owner (UniVoucher) gain the right to cancel it and claim the funds. However, the card is still redeemable and cancelable even after this date.', 'univoucher-for-woocommerce' ); ?>
				</p>
		</div>
	</div>

	<!-- Box 4: Include in All Licenses Keys Page Setting -->
	<div class="univoucher-settings-box" id="lmfwc-all-licenses-box" style="display: <?php echo ( $lmfwc_active && $lmfwc_integration ) ? 'block' : 'none'; ?>;">
		<h4>
			<?php esc_html_e( 'Include in Licenses Keys Page', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div class="univoucher-settings-box-info">
			<label for="univoucher_wc_lmfwc_include_in_all_licenses" style="display: flex; align-items: center; margin: 0;">
				<input
					type="checkbox"
					id="univoucher_wc_lmfwc_include_in_all_licenses"
					name="univoucher_wc_lmfwc_include_in_all_licenses"
					value="1"
					<?php checked( $include_in_all_licenses, true ); ?>
				/>
				<strong style="color: #0c5460;">
					<?php esc_html_e( 'Include UniVoucher cards in "License Keys" page', 'univoucher-for-woocommerce' ); ?>
				</strong>
			</label>
			<p style="margin: 8px 0 0 0; font-size: 12px; color: #0c5460;">
				<?php esc_html_e( 'If enabled, UniVoucher gift cards will appear in the customer\'s "License Keys" page in My Account.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
	</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#<?php echo esc_js( $args['label_for'] ); ?>').on('change', function() {
			var isChecked = $(this).is(':checked');
			var isLmfwcActive = <?php echo $lmfwc_active ? 'true' : 'false'; ?>;
			
			if (isChecked && isLmfwcActive) {
				$('#lmfwc-template-box, #lmfwc-abandoned-box, #lmfwc-all-licenses-box').show();
			} else {
				$('#lmfwc-template-box, #lmfwc-abandoned-box, #lmfwc-all-licenses-box').hide();
			}
		});
		
		// Reset License Key Template to Default
		$('#reset-license-key-template').on('click', function() {
			if (confirm('<?php echo esc_js( __( 'Are you sure you want to reset the License Key Template to its default value?', 'univoucher-for-woocommerce' ) ); ?>')) {
				var defaultTemplate = 'Card ID: {card_id} Card Secret: {card_secret} Network: {card_network} Abandoned on {card_abandoned}';
				$('#univoucher_wc_lmfwc_license_key_template').val(defaultTemplate);
				
				// Trigger the height adjustment
				var textarea = $('#univoucher_wc_lmfwc_license_key_template')[0];
				textarea.style.height = '50px';
				textarea.style.height = textarea.scrollHeight + 'px';
				
				alert('<?php echo esc_js( __( 'License Key Template has been reset to default. Save Changes to apply.', 'univoucher-for-woocommerce' ) ); ?>');
			}
		});
	});
	</script>
	<?php
} 