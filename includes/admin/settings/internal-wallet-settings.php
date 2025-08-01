<?php
/**
 * UniVoucher Internal Wallet Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal wallet section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_internal_wallet_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Configure your crypto wallet for UniVoucher operations. This wallet will be used for blockchain transactions.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Wallet private key field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_wallet_private_key_callback( $args ) {
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
	$private_key = '';
	
	if ( $encrypted_private_key ) {
		$decrypted = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( ! is_wp_error( $decrypted ) ) {
			$private_key = $decrypted;
		}
	}
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Your Ethereum Wallet Private Key (EVM-compatible):', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Your wallet private key is used for blockchain transactions and is encrypted and stored securely in the database.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-warning" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
			<strong style="color: #721c24;">
				<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Security Warning:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<span style="font-size: 13px; color: #721c24;">
				<?php esc_html_e( 'Never share your private key with anyone. It provides full access to your wallet.', 'univoucher-for-woocommerce' ); ?>
			</span>
		</div>

		<div style="margin-top: 15px; display: flex; gap: 10px; align-items: flex-start;">
			<input
				type="password"
				id="univoucher_wc_wallet_private_key"
				name="univoucher_wc_wallet_private_key"
				value="<?php echo esc_attr( $private_key ); ?>"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Enter your private key', 'univoucher-for-woocommerce' ); ?>"
				autocomplete="off"
				style="font-family: monospace; width: 600px; max-width: 100%;"
			/>
			<button type="button" class="button" id="toggle-private-key" style="flex-shrink: 0;">
				<?php esc_html_e( 'Show', 'univoucher-for-woocommerce' ); ?>
			</button>
		</div>
		
		<div style="margin-top: 10px;">
			<p class="description">
				<?php esc_html_e( 'Enter your 64-character hexadecimal private key (with or without 0x prefix).', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		
		<div style="margin-top: 10px;">
			<button type="button" class="button button-secondary" id="validate-private-key">
				<?php esc_html_e( 'Validate Private Key', 'univoucher-for-woocommerce' ); ?>
			</button>
		</div>
		
		<div style="margin-top: 10px;">
			<span id="validation-result" style="font-weight: bold;"></span>
		</div>
		
		<div class="univoucher-settings-box-info" style="margin-top: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 12px;">
			<strong style="color: #0c5460;">
				<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Auto-Create Cards For Backorders:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<div style="margin-top: 8px; font-size: 13px; color: #0c5460;">
				<?php esc_html_e( 'This wallet can be used for the "Auto-create missing cards using internal wallet" feature in Card Delivery settings. When enabled, this wallet will be used to automatically create required cards for backordered orders.', 'univoucher-for-woocommerce' ); ?>
			</div>
		</div>
	
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Toggle private key visibility
		$('#toggle-private-key').on('click', function() {
			var $input = $('#univoucher_wc_wallet_private_key');
			var $button = $(this);
			
			if ($input.attr('type') === 'password') {
				$input.attr('type', 'text');
				$button.text('<?php esc_html_e( 'Hide', 'univoucher-for-woocommerce' ); ?>');
			} else {
				$input.attr('type', 'password');
				$button.text('<?php esc_html_e( 'Show', 'univoucher-for-woocommerce' ); ?>');
			}
		});
		
		// Validate private key
		$('#validate-private-key').on('click', function() {
			var $button = $(this);
			var $result = $('#validation-result');
			var privateKey = $('#univoucher_wc_wallet_private_key').val();
			
			if (!privateKey) {
				$result.html('<span style="color: #dc3232;"><?php esc_html_e( 'Please enter a private key first.', 'univoucher-for-woocommerce' ); ?></span>');
				return;
			}
			
			$button.prop('disabled', true).text('<?php esc_html_e( 'Validating...', 'univoucher-for-woocommerce' ); ?>');
			$result.html('');
			
			try {
				// Clean the private key
				var cleanKey = privateKey;
				if (cleanKey.startsWith('0x')) {
					cleanKey = cleanKey.substring(2);
				}
				
				// Validate format
				if (cleanKey.length !== 64 || !/^[0-9a-fA-F]+$/.test(cleanKey)) {
					$result.html('<span style="color: #dc3232;"><?php esc_html_e( 'Invalid private key format. Must be 64 hex characters.', 'univoucher-for-woocommerce' ); ?></span>');
					return;
				}
				
				// Use ethers.js to generate wallet address
				if (typeof ethers !== 'undefined') {
					var wallet = new ethers.Wallet('0x' + cleanKey);
					var address = wallet.address;
					$result.html('<span style="color: #46b450;"><?php esc_html_e( 'Valid! Address: ', 'univoucher-for-woocommerce' ); ?>' + address + '</span>');
				} else {
					$result.html('<span style="color: #dc3232;"><?php esc_html_e( 'Ethers.js not loaded. Please refresh the page.', 'univoucher-for-woocommerce' ); ?></span>');
				}
			} catch (error) {
				$result.html('<span style="color: #dc3232;"><?php esc_html_e( 'Invalid private key: ', 'univoucher-for-woocommerce' ); ?>' + error.message + '</span>');
			} finally {
				$button.prop('disabled', false).text('<?php esc_html_e( 'Validate Private Key', 'univoucher-for-woocommerce' ); ?>');
			}
		});
	});
	</script>
	<?php
}

/**
 * Wallet details display callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_wallet_details_callback( $args ) {
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
	$wallet_address = '';
	
	if ( $encrypted_private_key ) {
		$decrypted = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( ! is_wp_error( $decrypted ) ) {
			// Generate wallet address from private key
			if ( function_exists( 'wp_remote_get' ) ) {
				// We'll generate the address via JavaScript for security
				$wallet_address = 'loading...';
			}
		}
	}
	?>
	
	<div class="univoucher-settings-box">
		<div id="wallet-details-container">
			<!-- First Row: Wallet Address and QR Code -->
			<div style="display: grid; grid-template-columns: auto 1fr; gap: 30px; align-items: flex-start; margin-bottom: 30px;">
				<div style="min-width: 400px;">
					<h4 style="margin: 0 0 10px 0;">
						<?php esc_html_e( 'Wallet Details:', 'univoucher-for-woocommerce' ); ?>
					</h4>
					<div style="margin-bottom: 15px;">
						<p style="margin: 5px 0 8px 0; font-size: 13px;">
							<?php esc_html_e( 'Current wallet information and balances across supported networks.', 'univoucher-for-woocommerce' ); ?>
						</p>
					</div>
					<strong><?php esc_html_e( 'Wallet Address:', 'univoucher-for-woocommerce' ); ?></strong>
					<div style="margin-top: 5px;">
						<input
							type="text"
							id="wallet-address-display"
							value="<?php echo esc_attr( $wallet_address ); ?>"
							readonly
							style="width: 100%; max-width: 500px; margin-bottom: 10px;"
						/>
						<div style="display: flex; gap: 10px;">
							<button type="button" class="button" id="copy-address-btn">
								<?php esc_html_e( 'Copy', 'univoucher-for-woocommerce' ); ?>
							</button>
							<button type="button" class="button button-secondary" id="refresh-balances-btn">
								<?php esc_html_e( 'Refresh Balances', 'univoucher-for-woocommerce' ); ?>
							</button>
						</div>
					</div>
				</div>
				
				<div style="display: flex; justify-content: center; align-items: center;">
					<div id="wallet-qr-code" style="border: 1px solid #ddd; padding: 20px; background: white; text-align: center; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 200px;">
						<div style="font-size: 14px; color: #666; margin-bottom: 12px; font-weight: 600;"><?php esc_html_e( 'Wallet QR Code', 'univoucher-for-woocommerce' ); ?></div>
						<div id="qr-code-container" style="width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; background: #f9f9f9; border-radius: 4px; margin: 0 auto;">
							<span style="color: #999; font-size: 11px;"><?php esc_html_e( 'Loading...', 'univoucher-for-woocommerce' ); ?></span>
						</div>
						<div style="margin-top: 10px; font-size: 11px; color: #666;">
							<?php esc_html_e( 'Scan with your wallet app', 'univoucher-for-woocommerce' ); ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Second Row: Network Balances -->
			<div id="balances-container">
				<h5><?php esc_html_e( 'Network Balances:', 'univoucher-for-woocommerce' ); ?></h5>
				<div id="balances-loading" style="display: none;">
					<p><?php esc_html_e( 'Loading balances...', 'univoucher-for-woocommerce' ); ?></p>
				</div>
				<div id="balances-error" style="display: none;">
					<p style="color: #dc3232;"><?php esc_html_e( 'Error loading balances. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ); ?></p>
				</div>
				<div id="balances-list">
					<!-- Balances will be populated here -->
				</div>
			</div>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		var walletAddress = '';
		var balancesLoaded = false;

		// Generate wallet address from private key
		function generateWalletAddress() {
			var privateKey = $('#univoucher_wc_wallet_private_key').val();
			if (!privateKey) {
				$('#wallet-address-display').val('<?php esc_html_e( 'No private key set', 'univoucher-for-woocommerce' ); ?>');
				return;
			}

			try {
				// Clean the private key
				var cleanKey = privateKey;
				if (cleanKey.startsWith('0x')) {
					cleanKey = cleanKey.substring(2);
				}

				// Validate format
				if (cleanKey.length !== 64 || !/^[0-9a-fA-F]+$/.test(cleanKey)) {
					$('#wallet-address-display').val('<?php esc_html_e( 'Invalid private key format', 'univoucher-for-woocommerce' ); ?>');
					return;
				}

				// Use ethers.js to generate wallet address
				if (typeof ethers !== 'undefined') {
					var wallet = new ethers.Wallet('0x' + cleanKey);
					walletAddress = wallet.address;
					$('#wallet-address-display').val(walletAddress);
					
					// Generate QR code for the address
					generateQRCode(walletAddress);
					
					// Load balances if we have a valid address
					if (walletAddress && !balancesLoaded) {
						loadBalances();
					}
				} else {
					$('#wallet-address-display').val('<?php esc_html_e( 'Ethers.js not loaded', 'univoucher-for-woocommerce' ); ?>');
					generateQRCode('<?php esc_html_e( 'Ethers.js not loaded', 'univoucher-for-woocommerce' ); ?>');
				}
			} catch (error) {
				$('#wallet-address-display').val('<?php esc_html_e( 'Error generating address', 'univoucher-for-woocommerce' ); ?>');
				generateQRCode('<?php esc_html_e( 'Error generating address', 'univoucher-for-woocommerce' ); ?>');
			}
		}

		// Copy address to clipboard
		$('#copy-address-btn').on('click', function() {
			var addressInput = document.getElementById('wallet-address-display');
			addressInput.select();
			addressInput.setSelectionRange(0, 99999); // For mobile devices
			
			try {
				document.execCommand('copy');
				var $btn = $(this);
				var originalText = $btn.text();
				$btn.text('<?php esc_html_e( 'Copied!', 'univoucher-for-woocommerce' ); ?>');
				setTimeout(function() {
					$btn.text(originalText);
				}, 2000);
			} catch (err) {
				console.error('Failed to copy: ', err);
			}
		});

		// Refresh balances
		$('#refresh-balances-btn').on('click', function() {
			if (walletAddress) {
				loadBalances();
			}
		});

		// Load balances from Alchemy API
		function loadBalances() {
			if (!walletAddress) {
				return;
			}

			$('#balances-loading').show();
			$('#balances-error').hide();
			$('#balances-list').empty();

			// Supported networks configuration
			var networks = [
				{ id: 1, name: 'Ethereum', symbol: 'ETH', rpc: 'eth-mainnet', decimals: 18 },
				{ id: 137, name: 'Polygon', symbol: 'POL', rpc: 'polygon-mainnet', decimals: 18 },
				{ id: 10, name: 'Optimism', symbol: 'ETH', rpc: 'opt-mainnet', decimals: 18 },
				{ id: 42161, name: 'Arbitrum', symbol: 'ETH', rpc: 'arb-mainnet', decimals: 18 },
				{ id: 56, name: 'BNB Chain', symbol: 'BNB', rpc: 'bnb-mainnet', decimals: 18 },
				{ id: 43114, name: 'Avalanche', symbol: 'AVAX', rpc: 'avax-mainnet', decimals: 18 },
				{ id: 8453, name: 'Base', symbol: 'ETH', rpc: 'base-mainnet', decimals: 18 }
			];

			var alchemyApiKey = '<?php echo esc_js( get_option( 'univoucher_wc_alchemy_api_key', '' ) ); ?>';
			
			if (!alchemyApiKey) {
				$('#balances-error').show().find('p').text('<?php esc_html_e( 'Alchemy API key not configured. Please set it in the API Configuration section.', 'univoucher-for-woocommerce' ); ?>');
				$('#balances-loading').hide();
				return;
			}

			var promises = networks.map(function(network) {
				return fetchBalance(network, walletAddress, alchemyApiKey);
			});

			Promise.all(promises).then(function(results) {
				$('#balances-loading').hide();
				displayBalances(results);
			}).catch(function(error) {
				$('#balances-loading').hide();
				$('#balances-error').show().find('p').text('<?php esc_html_e( 'Error loading balances: ', 'univoucher-for-woocommerce' ); ?>' + error.message);
			});
		}

		// Fetch token metadata (name, symbol, and decimals)
		function fetchTokenMetadata(rpc, tokenAddress, apiKey) {
			var url = 'https://' + rpc + '.g.alchemy.com/v2/' + apiKey;
			
			// Make parallel calls for name, symbol, and decimals
			var namePayload = {
				jsonrpc: '2.0',
				method: 'eth_call',
				params: [{
					to: tokenAddress,
					data: '0x06fdde03' // name()
				}, 'latest'],
				id: 1
			};
			
			var symbolPayload = {
				jsonrpc: '2.0',
				method: 'eth_call',
				params: [{
					to: tokenAddress,
					data: '0x95d89b41' // symbol()
				}, 'latest'],
				id: 2
			};
			
			var decimalsPayload = {
				jsonrpc: '2.0',
				method: 'eth_call',
				params: [{
					to: tokenAddress,
					data: '0x313ce567' // decimals()
				}, 'latest'],
				id: 3
			};
			
			return Promise.all([
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(namePayload)
				}),
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(symbolPayload)
				}),
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(decimalsPayload)
				})
			])
			.then(function(responses) {
				return Promise.all([
					responses[0].json(),
					responses[1].json(),
					responses[2].json()
				]);
			})
			.then(function(dataArray) {
				var nameData = dataArray[0];
				var symbolData = dataArray[1];
				var decimalsData = dataArray[2];
				
				var name = '';
				var symbol = '';
				var decimals = 18; // Default to 18 decimals
				
				// Decode name
				if (nameData.result && nameData.result !== '0x') {
					try {
						name = decodeHexString(nameData.result);
					} catch (e) {
						name = 'Unknown Token';
					}
				}
				
				// Decode symbol
				if (symbolData.result && symbolData.result !== '0x') {
					try {
						symbol = decodeHexString(symbolData.result);
					} catch (e) {
						symbol = 'Unknown';
					}
				}
				
				// Decode decimals
				if (decimalsData.result && decimalsData.result !== '0x') {
					try {
						// Remove 0x prefix and convert hex to decimal
						var decimalsHex = decimalsData.result.substring(2);
						decimals = parseInt(decimalsHex, 16);
					} catch (e) {
						decimals = 18; // Default to 18 if decoding fails
					}
				}
				
				return {
					name: name || 'Unknown Token',
					symbol: symbol || 'Unknown',
					decimals: decimals
				};
			})
			.catch(function(error) {
				return {
					name: 'Unknown Token',
					symbol: 'Unknown',
					decimals: 18
				};
			});
		}
		
		// Decode hex string from contract call
		function decodeHexString(hex) {
			// Remove 0x prefix
			hex = hex.substring(2);
			
			// Skip the first 64 characters (offset + length)
			hex = hex.substring(128);
			
			// Convert hex to UTF-8 string
			var string = '';
			for (var i = 0; i < hex.length; i += 2) {
				var byte = parseInt(hex.substr(i, 2), 16);
				string += String.fromCharCode(byte);
			}
			
			// Handle UTF-8 decoding properly
			try {
				// Use TextDecoder for proper UTF-8 handling
				var bytes = new Uint8Array(hex.match(/.{1,2}/g).map(function(byte) {
					return parseInt(byte, 16);
				}));
				var decoder = new TextDecoder('utf-8');
				string = decoder.decode(bytes);
			} catch (e) {
				// Fallback to simple decoding if TextDecoder fails
				string = string.replace(/\0/g, '').trim();
			}
			
			return string.replace(/\0/g, '').trim();
		}

		// Fetch balance for a specific network
		function fetchBalance(network, address, apiKey) {
			var url = 'https://' + network.rpc + '.g.alchemy.com/v2/' + apiKey;
			
			// First get native token balance
			var nativePayload = {
				jsonrpc: '2.0',
				method: 'eth_getBalance',
				params: [address, 'latest'],
				id: 1
			};

			// Then get ERC-20 token balances with metadata
			var tokenPayload = {
				jsonrpc: '2.0',
				method: 'alchemy_getTokenBalances',
				params: [address, 'erc20'],
				id: 2
			};

			// Make both requests in parallel
			var nativePromise = fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(nativePayload)
			});

			var tokenPromise = fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(tokenPayload)
			});

			return Promise.all([nativePromise, tokenPromise])
			.then(function(responses) {
				// Check for HTTP errors
				responses.forEach(function(response, index) {
					if (!response.ok) {
						if (response.status === 404) {
							throw new Error('Network not supported by Alchemy API');
						} else if (response.status === 401) {
							throw new Error('Invalid API key');
						} else if (response.status === 403) {
							throw new Error('API access denied');
						} else if (response.status === 429) {
							throw new Error('Rate limit exceeded');
						} else {
							throw new Error('Network response was not ok (HTTP ' + response.status + ')');
						}
					}
				});

				// Parse both responses
				return Promise.all([
					responses[0].json(),
					responses[1].json()
				]);
			})
			.then(function(dataArray) {
				var nativeData = dataArray[0];
				var tokenData = dataArray[1];

				// Check for RPC errors
				if (nativeData.error) {
					var errorMsg = nativeData.error.message || 'Unknown error';
					if (errorMsg.includes('authenticate') || errorMsg.includes('unauthorized')) {
						throw new Error('Invalid API key');
					} else if (errorMsg.includes('rate limit') || errorMsg.includes('limit')) {
						throw new Error('Rate limit exceeded');
					} else {
						throw new Error(errorMsg);
					}
				}

				// Convert native token balance
				var balanceHex = nativeData.result;
				var balanceWei = parseInt(balanceHex, 16);
				var balanceEth = balanceWei / Math.pow(10, network.decimals);

				// Process ERC-20 tokens
				var erc20Tokens = [];
				if (tokenData.result && tokenData.result.tokenBalances) {
					// Create promises for fetching token metadata
					var tokenPromises = [];
					
					tokenData.result.tokenBalances.forEach(function(token) {
						if (token.tokenBalance && token.tokenBalance !== '0x0') {
							// Fetch token metadata first to get the correct decimals
							var metadataPromise = fetchTokenMetadata(network.rpc, token.contractAddress, apiKey);
							tokenPromises.push(metadataPromise.then(function(metadata) {
								// Convert token balance to decimal using the correct decimals
								var tokenBalanceWei = parseInt(token.tokenBalance, 16);
								var tokenBalance = tokenBalanceWei / Math.pow(10, metadata.decimals);
								
								return {
									address: token.contractAddress,
									balance: tokenBalance,
									symbol: metadata.symbol || 'Unknown',
									name: metadata.name || 'Unknown Token',
									decimals: metadata.decimals
								};
							}));
						}
					});
					
					// Wait for all metadata to be fetched
					if (tokenPromises.length > 0) {
						return Promise.all(tokenPromises).then(function(tokens) {
							return {
								network: network.name,
								symbol: network.symbol,
								balance: balanceEth,
								balanceWei: balanceWei,
								address: address,
								erc20Tokens: tokens
							};
						});
					}
				}
				
				// Return immediately if no tokens or no token balances
				return Promise.resolve({
					network: network.name,
					symbol: network.symbol,
					balance: balanceEth,
					balanceWei: balanceWei,
					address: address,
					erc20Tokens: erc20Tokens
				});
			})
			.catch(function(error) {
				// Return error info for this specific network
				return Promise.resolve({
					network: network.name,
					symbol: network.symbol,
					balance: 0,
					error: error.message,
					address: address,
					erc20Tokens: []
				});
			});
		}

		// Display balances in a nice format
		function displayBalances(balances) {
			var $container = $('#balances-list');
			$container.empty();

			if (balances.length === 0) {
				$container.html('<p><?php esc_html_e( 'No balances found.', 'univoucher-for-woocommerce' ); ?></p>');
				return;
			}

			var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 10px;">';
			
			balances.forEach(function(balance) {
				html += '<div class="balance-card">';
				html += '<div style="font-weight: bold; margin-bottom: 12px; font-size: 16px;">' + balance.network + '</div>';
				
				if (balance.error) {
					// Show error for this network
					html += '<div style="color: #dc3232; font-size: 12px;">';
					html += '<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>';
					html += balance.error;
					html += '</div>';
				} else {
					// Show native token balance
					var balanceFormatted = balance.balance.toFixed(6);
					
					html += '<div style="margin-bottom: 12px;">';
					html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 4px;"><?php esc_html_e( 'Native Token', 'univoucher-for-woocommerce' ); ?></div>';
					html += '<div style="font-family: monospace; font-size: 14px;">';
					html += '<span>' + balanceFormatted + ' ' + balance.symbol + '</span>';
					html += '</div>';
					html += '</div>';

					// Show ERC-20 token balances
					if (balance.erc20Tokens && balance.erc20Tokens.length > 0) {
						html += '<div style="margin-top: 12px;">';
						html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 8px;"><?php esc_html_e( 'ERC-20 Tokens', 'univoucher-for-woocommerce' ); ?></div>';
						
						// Sort tokens: famous tokens first, then alphabetical
						var famousTokens = ['USDT', 'USDT0', 'USD₮0', 'USDC', 'DAI', 'WBTC', 'WETH', 'UNI', 'LINK', 'AAVE', 'COMP', 'MKR', 'CRV', 'SNX', 'YFI', 'BAL', 'SUSHI', '1INCH', 'SAND', 'MANA', 'ENJ', 'CHZ', 'BAT', 'ZRX', 'REP', 'KNC', 'BNT', 'LRC', 'STORJ', 'DNT', 'CVC', 'MLN', 'GNT', 'FUN', 'TRST', 'RLC', 'ENG', 'AST', 'POE', 'DGD', 'SWT', 'WINGS', 'CFI', 'ANT'];
						
						var sortedTokens = balance.erc20Tokens.sort(function(a, b) {
							var aIndex = famousTokens.indexOf(a.symbol.toUpperCase());
							var bIndex = famousTokens.indexOf(b.symbol.toUpperCase());
							
							// If both are famous tokens, sort by their order in the famous list
							if (aIndex !== -1 && bIndex !== -1) {
								return aIndex - bIndex;
							}
							// If only a is famous, a comes first
							if (aIndex !== -1 && bIndex === -1) {
								return -1;
							}
							// If only b is famous, b comes first
							if (aIndex === -1 && bIndex !== -1) {
								return 1;
							}
							// If neither is famous, sort alphabetically
							return a.symbol.localeCompare(b.symbol);
						});
						
						sortedTokens.forEach(function(token) {
							var tokenBalanceFormatted = token.balance.toFixed(6);
							
							// Truncate symbol to first 7 characters
							var displaySymbol = token.symbol.length > 12 ? token.symbol.substring(0, 6) + '...' : token.symbol;
							
							html += '<div style="margin-bottom: 4px;">';
							html += '<span style="font-family: monospace; font-size: 12px;">' + tokenBalanceFormatted + ' ' + displaySymbol + '</span>';
							html += '</div>';
						});
						
						html += '</div>';
					} else {
						html += '<div style="margin-top: 12px;">';
						html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 4px;"><?php esc_html_e( 'ERC-20 Tokens', 'univoucher-for-woocommerce' ); ?></div>';
						html += '<div style="font-size: 11px; color: #999; font-style: italic;"><?php esc_html_e( 'No ERC-20 tokens found', 'univoucher-for-woocommerce' ); ?></div>';
						html += '</div>';
					}
				}
				
				html += '</div>';
			});
			
			html += '</div>';
			$container.html(html);
		}

		// Generate QR code for wallet address
		function generateQRCode(address) {
			var $container = $('#qr-code-container');
			
			if (!address || address === '<?php esc_html_e( 'No private key set', 'univoucher-for-woocommerce' ); ?>' || 
				address === '<?php esc_html_e( 'Invalid private key format', 'univoucher-for-woocommerce' ); ?>' ||
				address === '<?php esc_html_e( 'Error generating address', 'univoucher-for-woocommerce' ); ?>' ||
				address === '<?php esc_html_e( 'Ethers.js not loaded', 'univoucher-for-woocommerce' ); ?>') {
				$container.html('<span style="color: #999; font-size: 11px;"><?php esc_html_e( 'No valid address', 'univoucher-for-woocommerce' ); ?></span>');
				return;
			}
			
			// Clear container
			$container.empty();
			
			// Create QR code using qrcode.js library
			if (typeof QRCode !== 'undefined') {
				try {
					// Create QR code using the constructor approach
					new QRCode($container[0], {
						text: address,
						width: 200,
						height: 200,
						colorDark: '#000000',
						colorLight: '#ffffff',
						correctLevel: QRCode.CorrectLevel.M
					});
				} catch (error) {
					console.error('QR Code generation error:', error);
					$container.empty();
					// Fallback: create a simple text representation
					$container.html('<div style="font-family: monospace; font-size: 8px; word-break: break-all; line-height: 1.2; color: #333;">' + address + '</div>');
				}
			} else {
				// Fallback: create a simple text representation
				$container.html('<div style="font-family: monospace; font-size: 8px; word-break: break-all; line-height: 1.2; color: #333;">' + address + '</div>');
			}
		}

		// Generate address when private key changes
		$('#univoucher_wc_wallet_private_key').on('input', function() {
			setTimeout(generateWalletAddress, 500);
		});

		// Initial generation
		generateWalletAddress();
	});
	</script>


	<?php
}

/**
 * Sanitize private key input.
 *
 * @param mixed $input The input value.
 * @return string The sanitized value.
 */
function univoucher_sanitize_private_key( $input ) {
	// Sanitize input
	$input = sanitize_text_field( $input );
	
	// If empty, return empty
	if ( empty( $input ) ) {
		return '';
	}
	
	// Check if this is already encrypted data (base64 encoded and much longer than 64 chars)
	// Encrypted data is typically much longer than a private key
	if ( base64_decode( $input, true ) !== false && strlen( $input ) > 100 ) {
		return $input; // Return encrypted data as-is
	}
	
	// Remove 0x prefix if present
	$clean_key = $input;
	if ( strpos( $clean_key, '0x' ) === 0 ) {
		$clean_key = substr( $clean_key, 2 );
	}
	
	// Validate private key format (64 hex characters)
	if ( strlen( $clean_key ) !== 64 || ! ctype_xdigit( $clean_key ) ) {
		// Show error and return empty
		add_settings_error(
			'univoucher_wc_wallet_private_key',
			'invalid_private_key',
			esc_html__( 'Invalid private key format. Please enter a 64-character hexadecimal private key.', 'univoucher-for-woocommerce' ),
			'error'
		);
		return '';
	}
	
	// Encrypt the valid private key
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted = $encryption->uv_encrypt_data( $clean_key );
	
	if ( is_wp_error( $encrypted ) ) {
		add_settings_error(
			'univoucher_wc_wallet_private_key',
			'encryption_failed',
			esc_html__( 'Failed to encrypt private key. Please try again.', 'univoucher-for-woocommerce' ),
			'error'
		);
		return '';
	}
	
	return $encrypted;
}