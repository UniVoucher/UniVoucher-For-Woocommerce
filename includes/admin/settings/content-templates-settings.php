<?php
/**
 * UniVoucher Content Templates Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Templates section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_content_templates_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Customize the content of auto-generated product titles, short descriptions, and full descriptions for UniVoucher gift cards products.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<p style="margin: 5px 0 8px 0; font-size: 13px;">
		<?php esc_html_e( 'The below templates will be used to automatically to generate product content when the "Generate Title & Description" button is clicked in the product edit page.', 'univoucher-for-woocommerce' ); ?>
	</p>		
	<?php
}

/**
 * Available placeholders field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_available_placeholders_callback( $args ) {
	?>
	<div class="univoucher-settings-box">

		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'The content templates support the following dynamic placeholders that will be replaced with actual product data when generating content.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-highlight">
			<strong>
				<?php esc_html_e( '{amount}, {symbol}, {network}', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 12px; color: #2980b9;">
				<div style="margin: 2px 0;"><strong>{amount}</strong> - <?php esc_html_e( 'The gift card amount (e.g., "10")', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;"><strong>{symbol}</strong> - <?php esc_html_e( 'The token symbol (e.g., "USDC")', 'univoucher-for-woocommerce' ); ?></div>
				<div style="margin: 2px 0;"><strong>{network}</strong> - <?php esc_html_e( 'The blockchain network name (e.g., "Ethereum")', 'univoucher-for-woocommerce' ); ?></div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Title template field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_title_template_callback( $args ) {
	$template = get_option( 'univoucher_wc_title_template', 'UniVoucher {amount} {symbol} Gift Card on {network}' );
	?>
	<div class="univoucher-settings-box">
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( $args['label_for'] ); ?>"
			value="<?php echo esc_attr( $template ); ?>"
			class="large-text"
			style="width: 100%; max-width: 800px; font-size: 16px; padding: 5px;"
			placeholder="<?php esc_attr_e( 'Enter a template for product titles', 'univoucher-for-woocommerce' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Template for generating product titles.', 'univoucher-for-woocommerce' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Short description template field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_short_description_template_callback( $args ) {
	$template = get_option( 'univoucher_wc_short_description_template', 'UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported' );
	?>
	<div class="univoucher-settings-box">
		<?php
		// Use WordPress native rich text editor with compact settings
		wp_editor( 
			$template, 
			$args['label_for'], 
			array(
				'textarea_name' => $args['label_for'],
				'media_buttons' => false,
				'textarea_rows' => 5,
				'teeny' => true,
				'dfw' => false,
				'quicktags' => array(
					'buttons' => 'strong,em,link,ul,ol,li,close'
				),
				'tinymce' => array(
					'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
					'toolbar2' => '',
					'toolbar3' => '',
				),
				'editor_class' => 'univoucher-short-description-editor',
			)
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Template for generating product short descriptions (excerpts). You can use basic formatting and HTML tags.', 'univoucher-for-woocommerce' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Description template field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_description_template_callback( $args ) {
	$template = get_option( 'univoucher_wc_description_template', "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeembase.com\" target=\"_blank\">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>" );
	?>
	<div class="univoucher-settings-box">
		<?php
		// Use WordPress native rich text editor with enhanced features
		wp_editor( 
			$template, 
			$args['label_for'], 
			array(
				'textarea_name' => $args['label_for'],
				'media_buttons' => true,
				'textarea_rows' => 20,
				'teeny' => false,
				'dfw' => false,
				'quicktags' => array(
					'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
				),
				'tinymce' => array(
					'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_fullscreen,wp_adv',
					'toolbar2' => 'undo,redo,cut,copy,paste,removeformat,charmap,outdent,indent,wp_help',
					'toolbar3' => '',
				),
				'editor_class' => 'univoucher-description-editor',
			)
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Template for generating detailed product descriptions. You can use rich formatting, images, and HTML tags.', 'univoucher-for-woocommerce' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Reset templates field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_reset_templates_callback( $args ) {
	?>
	<div class="univoucher-settings-box">
		<button type="button" class="button button-secondary" id="reset-content-templates">
			<?php esc_html_e( 'Reset All Templates to Default', 'univoucher-for-woocommerce' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Reset all content templates to their original default values. This action cannot be undone.', 'univoucher-for-woocommerce' ); ?>
		</p>
	</div>
	
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const resetButton = document.getElementById('reset-content-templates');
		if (resetButton) {
			resetButton.addEventListener('click', function() {
				if (confirm('<?php echo esc_js( __( 'Are you sure you want to reset all templates to default values? This action cannot be undone.', 'univoucher-for-woocommerce' ) ); ?>')) {
					// Reset title template
					const titleField = document.getElementById('univoucher_wc_title_template');
					if (titleField) {
						titleField.value = 'UniVoucher {amount} {symbol} Gift Card on {network}';
					}
					
					// Reset short description template
					const shortDescEditor = window.tinymce ? window.tinymce.get('univoucher_wc_short_description_template') : null;
					if (shortDescEditor) {
						shortDescEditor.setContent('UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported');
					} else {
						const shortDescField = document.getElementById('univoucher_wc_short_description_template');
						if (shortDescField) {
							shortDescField.value = 'UniVoucher gift card worth {amount} {symbol} on {network} network. instantly redeemable to any crypto wallet and globally supported';
						}
					}
					
					// Reset full description template
					const descEditor = window.tinymce ? window.tinymce.get('univoucher_wc_description_template') : null;
					if (descEditor) {
						descEditor.setContent('<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeembase.com\" target=\"_blank\">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>');
					} else {
						const descField = document.getElementById('univoucher_wc_description_template');
						if (descField) {
							descField.value = "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeembase.com\" target=\"_blank\">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>";
						}
					}
					
					alert('<?php echo esc_js( __( 'Templates have been reset to default values. Save Changes to apply.', 'univoucher-for-woocommerce' ) ); ?>');
				}
			});
		}
	});
	</script>
	<?php
}

/**
 * AJAX handler for fetching content templates.
 */
function univoucher_ajax_get_content_templates() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_product_nonce' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
	}

	// Check capabilities.
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
	}

	// Get templates from database
	$title_template = get_option( 'univoucher_wc_title_template', 'UniVoucher {amount} {symbol} Gift Card on {network}' );
	$short_description_template = get_option( 'univoucher_wc_short_description_template', 'Digital gift card worth {amount} {symbol} on {network} network. Instantly delivered via UniVoucher.' );
	$description_template = get_option( 'univoucher_wc_description_template', "This is a UniVoucher digital gift card containing {amount} {symbol} tokens on the {network} blockchain network.\n\nFeatures:\n• Instant digital delivery\n• Secure blockchain-based gift card\n• Redeemable on {network} network\n• Value: {amount} {symbol}\n\nAfter purchase, you will receive your gift card details that can be redeemed through the UniVoucher platform." );

	// Apply WordPress content formatting (wpautop) to properly handle line breaks for TinyMCE
	// This ensures content appears in TinyMCE the same way it would when saved and displayed
	$description_template = wpautop( $description_template );
	$short_description_template = wpautop( $short_description_template );

	wp_send_json_success( array(
		'title_template' => $title_template,
		'short_description_template' => $short_description_template,
		'description_template' => $description_template,
	) );
} 