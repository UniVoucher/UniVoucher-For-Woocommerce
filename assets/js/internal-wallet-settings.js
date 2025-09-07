jQuery(document).ready(function($) {
	// Toggle private key visibility
	$('#toggle-private-key').on('click', function() {
		var $input = $('#univoucher_wc_wallet_private_key');
		var $button = $(this);
		
		if ($input.attr('type') === 'password') {
			$input.attr('type', 'text');
			$button.text(walletSettings.i18n.hide);
		} else {
			$input.attr('type', 'password');
			$button.text(walletSettings.i18n.show);
		}
	});
	
	// Validate private key
	$('#validate-private-key').on('click', function() {
		var $button = $(this);
		var $result = $('#validation-result');
		var privateKey = $('#univoucher_wc_wallet_private_key').val();
		
		if (!privateKey) {
			$result.html('<span style="color: #dc3232;">' + walletSettings.i18n.enterPrivateKeyFirst + '</span>');
			return;
		}
		
		$button.prop('disabled', true).text(walletSettings.i18n.validating);
		$result.html('');
		
		try {
			// Clean the private key
			var cleanKey = privateKey;
			if (cleanKey.startsWith('0x')) {
				cleanKey = cleanKey.substring(2);
			}
			
			// Validate format
			if (cleanKey.length !== 64 || !/^[0-9a-fA-F]+$/.test(cleanKey)) {
				$result.html('<span style="color: #dc3232;">' + walletSettings.i18n.invalidPrivateKey + '</span>');
				return;
			}
			
			// Use ethers.js to generate wallet address
			if (typeof ethers !== 'undefined') {
				var wallet = new ethers.Wallet('0x' + cleanKey);
				var address = wallet.address;
				$result.html('<span style="color: #46b450;">' + walletSettings.i18n.validAddress + address + '</span>');
			} else {
				$result.html('<span style="color: #dc3232;">' + walletSettings.i18n.ethersNotLoaded + '</span>');
			}
		} catch (error) {
			$result.html('<span style="color: #dc3232;">' + walletSettings.i18n.invalidPrivateKeyError + error.message + '</span>');
		} finally {
			$button.prop('disabled', false).text(walletSettings.i18n.validatePrivateKey);
		}
	});
});