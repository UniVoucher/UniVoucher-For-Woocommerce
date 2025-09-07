jQuery(document).ready(function($) {
	var walletAddress = '';
	var balancesLoaded = false;

	// Generate wallet address from private key
	function generateWalletAddress() {
		var privateKey = $('#univoucher_wc_wallet_private_key').val();
		if (!privateKey) {
			$('#wallet-address-display').val(walletDetails.i18n.noPrivateKey);
			$('#univoucher_wc_wallet_public_key').val('');
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
				$('#wallet-address-display').val(walletDetails.i18n.invalidPrivateKey);
				$('#univoucher_wc_wallet_public_key').val('');
				return;
			}

			// Use ethers.js to generate wallet address
			if (typeof ethers !== 'undefined') {
				var wallet = new ethers.Wallet('0x' + cleanKey);
				walletAddress = wallet.address;
				$('#wallet-address-display').val(walletAddress);
				$('#univoucher_wc_wallet_public_key').val(walletAddress);
				
				// Generate QR code for the address
				generateQRCode(walletAddress);
				
				// Load balances if we have a valid address
				if (walletAddress && !balancesLoaded) {
					loadBalances();
				}
			} else {
				$('#wallet-address-display').val(walletDetails.i18n.ethersNotLoaded);
				$('#univoucher_wc_wallet_public_key').val('');
				generateQRCode(walletDetails.i18n.ethersNotLoaded);
			}
		} catch (error) {
			$('#wallet-address-display').val(walletDetails.i18n.errorGenerating);
			$('#univoucher_wc_wallet_public_key').val('');
			generateQRCode(walletDetails.i18n.errorGenerating);
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
			$btn.text(walletDetails.i18n.copied);
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

		var alchemyApiKey = walletDetails.alchemyApiKey;
		
		if (!alchemyApiKey) {
			$('#balances-error').show().find('p').text(walletDetails.i18n.noApiKey);
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
			$('#balances-error').show().find('p').text(walletDetails.i18n.errorLoading + error.message);
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
			$container.html('<p>' + walletDetails.i18n.noBalances + '</p>');
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
				html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 4px;">' + walletDetails.i18n.nativeToken + '</div>';
				html += '<div style="font-family: monospace; font-size: 14px;">';
				html += '<span>' + balanceFormatted + ' ' + balance.symbol + '</span>';
				html += '</div>';
				html += '</div>';

				// Show ERC-20 token balances
				if (balance.erc20Tokens && balance.erc20Tokens.length > 0) {
					html += '<div style="margin-top: 12px;">';
					html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 8px;">' + walletDetails.i18n.erc20Tokens + '</div>';
					
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
					html += '<div style="font-weight: 600; font-size: 12px; color: #666; margin-bottom: 4px;">' + walletDetails.i18n.erc20Tokens + '</div>';
					html += '<div style="font-size: 11px; color: #999; font-style: italic;">' + walletDetails.i18n.noErc20Tokens + '</div>';
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
		
		if (!address || address === walletDetails.i18n.noPrivateKey || 
			address === walletDetails.i18n.invalidPrivateKey ||
			address === walletDetails.i18n.errorGenerating ||
			address === walletDetails.i18n.ethersNotLoaded) {
			$container.html('<span style="color: #999; font-size: 11px;">' + walletDetails.i18n.noValidAddress + '</span>');
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