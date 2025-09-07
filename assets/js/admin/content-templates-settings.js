/**
 * UniVoucher Content Templates Settings JavaScript
 * Handles the reset functionality for content templates
 */

document.addEventListener('DOMContentLoaded', function() {
	const resetButton = document.getElementById('reset-content-templates');
	if (resetButton) {
		resetButton.addEventListener('click', function() {
			if (confirm(univoucherContentTemplates.confirmMessage)) {
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
					descEditor.setContent('<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href="https://univoucher.com" target="_blank">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click "Redeem Card"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href="https://redeembase.com" target="_blank">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>');
				} else {
					const descField = document.getElementById('univoucher_wc_description_template');
					if (descField) {
						descField.value = "<p>UniVoucher enables anyone to gift crypto across multiple blockchain networks with a redeemable crypto gift cards. Whether you want to gift ETH to a friend, distribute USDC rewards to your team, or create promotional crypto codes, UniVoucher provides a decentralized, transparent, and user-friendly solution.</p>\n\n<p><strong>Features:</strong></p>\n<ul>\n<li>Globally supported</li>\n<li>Redeemable to any crypto wallet</li>\n<li>Issued on {network} network</li>\n</ul>\n\n<p>After purchase, you will receive your gift card details in format of card ID and card Secret XXXXX-XXXXX-XXXXX-XXXXX that can be redeemed instantly.</p>\n\n<h3>HOW TO REDEEM</h3>\n\n<p><strong>Option 1 - Regular Redemption (gas fees required):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://univoucher.com\" target=\"_blank\">https://univoucher.com</a></li>\n<li>Connect your wallet</li>\n<li>Click Redeem</li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Click \"Redeem Card\"</li>\n</ol>\n\n<p><strong>Option 2 - Gasless Redemption (no fees):</strong></p>\n<ol>\n<li>Visit: <a href=\"https://redeembase.com\" target=\"_blank\">https://redeembase.com</a></li>\n<li>Enter the Card ID and Card Secret above</li>\n<li>Enter your wallet address</li>\n<li>Get tokens without paying gas fees</li>\n</ol>";
					}
				}
				
				alert(univoucherContentTemplates.successMessage);
			}
		});
	}
});