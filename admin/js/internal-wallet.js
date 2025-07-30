
(function($) {
    'use strict';

    var UniVoucherInternalWallet = {
        // Cache for fee percentages
        feeCache: {},
        
        /**
         * Initialize Internal Wallet method
         */
        initInternalWallet: function() {
            // Check if we have the required settings
            var alchemyApiKey = $('#alchemy-api-key').val();
            if (!alchemyApiKey) {
                univoucherNotify.error('Alchemy API key is required. Please configure it in the API settings.');
                return;
            }

            // Show UI elements first
            $('#method-elements').show();
            $('#internal-wallet-form').show();

            // Load ethers.js if not already loaded
            this.loadEthersJS().then(() => {
                this.setupInternalWalletEvents();
                this.loadWalletInfo();
                
                // Verify Web Crypto API is available for UniVoucher compatibility
                if (typeof crypto === 'undefined' || !crypto.subtle) {
                    this.showError('Web Crypto API is required for creating cards compatible with UniVoucher.com. Please use HTTPS or a modern browser.');
                    // Disable the prepare button to prevent card creation
                    $('#prepare-cards-btn').prop('disabled', true);
                    return;
                }
            }).catch((error) => {
                univoucherNotify.error('Failed to load required libraries. Please refresh the page and try again.');
            });
        },

        /**
         * Load ethers.js library
         */
        loadEthersJS: function() {
            return new Promise((resolve, reject) => {
                if (typeof ethers !== 'undefined') {
                    resolve(ethers);
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/ethers@6.0.6/dist/ethers.umd.min.js';
                script.onload = () => {
                    if (typeof ethers !== 'undefined') {
                        resolve(ethers);
                    } else {
                        reject(new Error('Ethers.js failed to load'));
                    }
                };
                script.onerror = () => reject(new Error('Failed to load ethers.js'));
                document.head.appendChild(script);
            });
        },

        /**
         * Setup event handlers for internal wallet
         */
        setupInternalWalletEvents: function() {
            // Quantity change
            $('#card-quantity').off('input').on('input', this.updateCostSummary.bind(this));
            
            // Step 1 actions
            $('#prepare-cards-btn').off('click').on('click', this.prepareCards.bind(this));
            
            // Step 2 actions  
            $('#back-to-step1-btn').off('click').on('click', this.backToStep1.bind(this));
            $('#create-cards-btn').off('click').on('click', this.createCardsInternal.bind(this));
            
            // Step 3 actions
            $('#create-more-cards-btn').off('click').on('click', this.createMoreCards.bind(this));
            
            // Approve tokens
            $('#approve-tokens-btn').off('click').on('click', this.approveTokens.bind(this));
            $('#approve-unlimited-tokens-btn').off('click').on('click', this.approveUnlimitedTokens.bind(this));
        },

        /**
         * Load wallet information and balances
         */
        loadWalletInfo: function() {
            // Get product settings
            var chainId = $('#product-chain-id').val();
            var tokenAddress = $('#product-token-address').val() || '0x0000000000000000000000000000000000000000';
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            if (!chainId || !alchemyApiKey) {
                $('#wallet-address-display').text('Configuration incomplete');
                $('#balance-loading').text('Cannot load balances - missing configuration');
                return;
            }

            // Get the real wallet address from the private key
            this.getWalletAddressFromPrivateKey().then((walletAddress) => {
                $('#wallet-address-display').text(walletAddress);
                this.loadWalletBalances(chainId, tokenAddress, walletAddress, alchemyApiKey);
                this.updateCostSummary();
                            }).catch((error) => {
                $('#wallet-address-display').text('Error: ' + error.message);
                $('#balance-loading').text('Cannot load balances');
            });
        },

        /**
         * Get wallet address from encrypted private key
         */
        getWalletAddressFromPrivateKey: function() {
            return new Promise((resolve, reject) => {
                // Get the private key from backend and derive address using ethers.js
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'univoucher_get_wallet_address',
                        nonce: $('#verification-nonce').val()
                    },
                    success: (response) => {
                        if (response.success && response.data.private_key) {
                            try {
                                // Use ethers.js to derive the wallet address
                                const wallet = new ethers.Wallet(response.data.private_key);
                                const walletAddress = wallet.address;
                                
                                // Clear the private key from memory for security
                                response.data.private_key = null;
                                
                                resolve(walletAddress);
                            } catch (error) {
                                reject(new Error('Failed to derive wallet address: ' + error.message));
                            }
                        } else {
                            reject(new Error(response.data.message || 'Failed to get private key'));
                        }
                    },
                    error: () => {
                        reject(new Error('Failed to connect to server'));
                    }
                });
            });
        },

        /**
         * Get native token symbol for a given chain ID
         */
        getNativeTokenSymbol: function(chainId) {
            const nativeTokenMap = {
                1: 'ETH',
                10: 'ETH',
                56: 'BNB',
                137: 'POL',
                8453: 'ETH',
                42161: 'ETH',
                43114: 'AVAX'
            };
            return nativeTokenMap[chainId] || 'ETH';
        },

        /**
         * Load wallet balances using Alchemy API
         */
        loadWalletBalances: function(chainId, tokenAddress, walletAddress, alchemyApiKey) {
            // Map chain IDs to Alchemy network names
            const networkMap = {
                1: 'eth-mainnet',
                10: 'opt-mainnet',
                56: 'bnb-mainnet',
                137: 'polygon-mainnet',
                8453: 'base-mainnet',
                42161: 'arb-mainnet',
                43114: 'avax-mainnet'
            };

            const network = networkMap[chainId];
            if (!network) {
                $('#balance-loading').text('Unsupported network');
                return;
            }

            const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;

            // Get native token balance
            this.getNativeBalance(rpcUrl, walletAddress).then((nativeBalance) => {
                const nativeSymbol = this.getNativeTokenSymbol(chainId);
                let balanceText = `Native: ${nativeBalance} ${nativeSymbol}`;

                // If it's an ERC-20 token, also get token balance
                if (tokenAddress !== '0x0000000000000000000000000000000000000000') {
                    this.getTokenBalance(rpcUrl, walletAddress, tokenAddress).then((tokenBalance) => {
                        const tokenSymbol = $('#product-token-symbol').val();
                        balanceText += `<br>ERC20 Token: ${tokenBalance} ${tokenSymbol}`;
                        $('#balance-loading').parent().html(balanceText);
                    }).catch((error) => {
                        balanceText += '<br>ERC20 Token: Error loading';
                        $('#balance-loading').parent().html(balanceText);
                    });
                } else {
                    $('#balance-loading').parent().html(balanceText);
                }
            }).catch((error) => {

                $('#balance-loading').text('Error loading balances');
            });
        },

        /**
         * Get native token balance
         */
        getNativeBalance: function(rpcUrl, address) {
            return fetch(rpcUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'eth_getBalance',
                    params: [address, 'latest'],
                    id: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error.message);
                const balanceWei = BigInt(data.result);
                const balanceEth = Number(balanceWei) / Math.pow(10, 18);
                return balanceEth.toFixed(6);
            });
        },

        /**
         * Get ERC-20 token balance
         */
        getTokenBalance: function(rpcUrl, address, tokenAddress) {
            // ERC-20 balanceOf function call
            const data = '0x70a08231' + address.substring(2).padStart(64, '0');
            
            return fetch(rpcUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'eth_call',
                    params: [{
                        to: tokenAddress,
                        data: data
                    }, 'latest'],
                    id: 1
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.error) throw new Error(result.error.message);
                const balanceWei = BigInt(result.result || '0x0');
                const decimals = parseInt($('#product-token-decimals').val()) || 18;
                const balance = Number(balanceWei) / Math.pow(10, decimals);
                return balance.toFixed(6);
            });
        },

        /**
         * Get gas price from Alchemy RPC
         */
        getGasPriceFromAlchemy: function(rpcUrl) {
            return fetch(rpcUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'eth_gasPrice',
                    params: [],
                    id: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error.message);
                return BigInt(data.result);
            });
        },

        /**
         * Update cost summary based on quantity
         */
        updateCostSummary: function() {
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var cardAmount = parseFloat($('#product-amount').val()) || 0;
            var tokenSymbol = $('#product-token-symbol').val();
            var chainId = $('#product-chain-id').val();
            
            // Calculate UniVoucher fee dynamically
            this.calculateFee(cardAmount, chainId).then((univoucherFee) => {
                var totalPerCard = cardAmount + univoucherFee;
                var totalNeeded = totalPerCard * quantity;
                
                // Update display
                $('#cost-card-amount').text(`${cardAmount} ${tokenSymbol}`);
                $('#cost-univoucher-fee').text(`${univoucherFee.toFixed(6)} ${tokenSymbol}`);
                $('#cost-quantity').text(quantity);
                $('#cost-total-needed').text(`${totalNeeded.toFixed(6)} ${tokenSymbol}`);
                
                // Check if we need token allowance
                var tokenAddress = $('#product-token-address').val();
                if (tokenAddress && tokenAddress !== '0x0000000000000000000000000000000000000000') {
                    this.checkTokenAllowance(totalNeeded);
                } else {
                    // For native tokens, check balance directly
                    this.checkStep1Balance();
                }
            }).catch((error) => {
                // Show error and disable functionality
                univoucherNotify.error('Failed to calculate fees: ' + error.message);
                $('#cost-univoucher-fee').text('Error');
                $('#cost-total-needed').text('Error');
            });
        },

        /**
         * Check token allowance for ERC-20 tokens (Real blockchain check)
         */
        checkTokenAllowance: function(totalNeeded) {
            var tokenAddress = $('#product-token-address').val();
            
            if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                // Native token - no allowance needed
                $('#allowance-section').hide();
                this.enablePrepareButton();
                return;
            }
            
            // Show allowance section for ERC-20 tokens
            $('#allowance-section').show();
            $('#allowance-loading').show();
            $('#allowance-insufficient').hide();
            $('#allowance-sufficient').hide();
            
            // Get real allowance from blockchain
            var chainId = $('#product-chain-id').val();
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var cardAmount = parseFloat($('#product-amount').val()) || 0;
            var tokenDecimals = parseInt($('#product-token-decimals').val()) || 18;
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            this.getWalletPrivateKey().then((privateKey) => {
                return this.checkRealTokenAllowance(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey);
            }).then((allowanceData) => {
                $('#allowance-loading').hide();
                if (allowanceData.hasAllowance) {
                    if (allowanceData.isUnlimited) {
                        // Show unlimited allowance message with revoke button
                        $('#allowance-sufficient').html(`
                            <p style="color: #46b450;">✅ Token allowance is sufficient (unlimited).</p>
                            <button type="button" class="button button-secondary" id="revoke-tokens-btn" style="margin-top: 10px;">
                                Revoke Token Allowance
                            </button>
                        `);
                        $('#revoke-tokens-btn').off('click').on('click', this.revokeTokens.bind(this));
                    } else {
                        // Show regular sufficient allowance message
                        $('#allowance-sufficient').html('<p style="color: #46b450;">✅ Token allowance is sufficient.</p>');
                    }
                    $('#allowance-sufficient').show();
                    // Check balance after allowance is confirmed
                    this.checkStep1Balance();
                } else {
                    // Calculate total amount needed for button text
                    this.calculateFee(cardAmount, chainId).then((univoucherFee) => {
                        var totalPerCard = cardAmount + univoucherFee;
                        var totalAmount = totalPerCard * quantity;
                        var tokenSymbol = $('#product-token-symbol').val();
                        
                        $('#approve-tokens-btn').text(`Approve ${totalAmount.toFixed(6)} ${tokenSymbol} Allowance`);
                        $('#approve-unlimited-tokens-btn').text(`Approve unlimited ${tokenSymbol} Allowance`);
                        $('#allowance-insufficient').show();
                        $('#prepare-cards-btn').prop('disabled', true);
                    }).catch((error) => {
                        univoucherNotify.error('Failed to calculate fees: ' + error.message);
                        $('#allowance-insufficient').hide();
                        $('#prepare-cards-btn').prop('disabled', true);
                    });
                }
            }).catch((error) => {
                // Show error and disable functionality
                univoucherNotify.error('Failed to check allowance: ' + error.message);
                $('#allowance-loading').hide();
                $('#allowance-insufficient').hide();
                $('#prepare-cards-btn').prop('disabled', true);
            });
        },

        /**
         * Check balance in Step 1 and show error if insufficient
         */
        checkStep1Balance: function() {
            var chainId = $('#product-chain-id').val();
            var tokenAddress = $('#product-token-address').val();
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var cardAmount = parseFloat($('#product-amount').val()) || 0;
            var tokenDecimals = parseInt($('#product-token-decimals').val()) || 18;
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            if (!chainId || !alchemyApiKey) {
                this.enablePrepareButton();
                return;
            }
            
            // Calculate fee dynamically
            this.calculateFee(cardAmount, chainId).then((univoucherFee) => {
                var totalPerCard = cardAmount + univoucherFee;
                var totalNeeded = totalPerCard * quantity;
                
                this.getWalletPrivateKey().then((privateKey) => {
                    return this.checkWalletBalance(privateKey, chainId, tokenAddress, totalNeeded, tokenDecimals, alchemyApiKey);
                }).then((hasSufficientBalance) => {
                    if (hasSufficientBalance) {
                        this.enablePrepareButton();
                        $('#step1-error').hide();
                    } else {
                        this.disablePrepareButton('Insufficient token balance. Please add more tokens to your wallet.');
                    }
                }).catch((error) => {
                    this.disablePrepareButton('Failed to check balance: ' + error.message);
                });
            }).catch((error) => {
                this.disablePrepareButton('Failed to calculate fees: ' + error.message);
            });
        },

        /**
         * Check real token allowance on blockchain
         */
        checkRealTokenAllowance: function(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey) {
            return new Promise((resolve, reject) => {
                try {
                    // Setup provider and signer
                    const networkMap = {
                        1: 'eth-mainnet', 10: 'opt-mainnet', 56: 'bnb-mainnet',
                        137: 'polygon-mainnet', 8453: 'base-mainnet', 42161: 'arb-mainnet', 43114: 'avax-mainnet'
                    };
                    const network = networkMap[chainId];
                    const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;
                    const provider = new ethers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    
                    // UniVoucher contract address
                    const UNIVOUCHER_ADDRESS = '0x51553818203e38ce0E78e4dA05C07ac779ec5b58';
                    
                    // ERC-20 token contract
                    const erc20Abi = [
                        'function allowance(address owner, address spender) view returns (uint256)'
                    ];
                    const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, provider);
                    
                    // Calculate total amount needed with dynamic fee
                    const cardAmountWei = ethers.parseUnits(cardAmount.toString(), tokenDecimals);
                    
                    // Get fee percentage from API and calculate fee
                    this.getUniVoucherFeePercentage(chainId).then((feePercentage) => {
                        const feeAmount = cardAmountWei * BigInt(Math.floor(feePercentage * 100)) / 100n;
                        const totalPerCard = cardAmountWei + feeAmount;
                        const totalAmount = totalPerCard * BigInt(quantity);
                        
                        // Check current allowance
                        tokenContract.allowance(wallet.address, UNIVOUCHER_ADDRESS).then((currentAllowance) => {
                            const hasEnoughAllowance = currentAllowance >= totalAmount;
                            const isUnlimited = currentAllowance === ethers.MaxUint256;
                            
                            resolve({
                                hasAllowance: hasEnoughAllowance,
                                isUnlimited: isUnlimited,
                                currentAllowance: currentAllowance
                            });
                        }).catch(reject);
                    }).catch(reject);
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Enable prepare button
         */
        enablePrepareButton: function() {
            $('#prepare-cards-btn').prop('disabled', false);
            $('#step1-error').hide();
        },

        /**
         * Disable prepare button with error message
         */
        disablePrepareButton: function(errorMessage) {
            $('#prepare-cards-btn').prop('disabled', true);
            $('#step1-error').text(errorMessage).show();
        },

        /**
         * Approve tokens for spending (Real blockchain transaction)
         */
        approveTokens: function() {
            $('#approve-tokens-btn').prop('disabled', true).text('Approving...');
            $('#approve-unlimited-tokens-btn').prop('disabled', true);
            
            // Get transaction data
            var chainId = $('#product-chain-id').val();
            var tokenAddress = $('#product-token-address').val();
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var cardAmount = parseFloat($('#product-amount').val()) || 0;
            var tokenDecimals = parseInt($('#product-token-decimals').val()) || 18;
            var tokenSymbol = $('#product-token-symbol').val();
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            // Calculate fee dynamically
            this.calculateFee(cardAmount, chainId).then((univoucherFee) => {
                var totalPerCard = cardAmount + univoucherFee;
                var totalAmount = totalPerCard * quantity;
                
                // Get wallet private key and execute approval
                this.getWalletPrivateKey().then((privateKey) => {
                    return this.executeTokenApproval(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey);
                }).then((txHash) => {
                    $('#allowance-insufficient').hide();
                    this.showAllowanceSuccess(txHash, false);
                    $('#approve-tokens-btn').prop('disabled', false).text(`Approve ${totalAmount.toFixed(6)} ${tokenSymbol} Allowance`);
                    $('#approve-unlimited-tokens-btn').prop('disabled', false);
                    this.enablePrepareButton();
                    univoucherNotify.success('Token allowance approved successfully! TX: ' + txHash);
                }).catch((error) => {
                    $('#approve-tokens-btn').prop('disabled', false).text(`Approve ${totalAmount.toFixed(6)} ${tokenSymbol} Allowance`);
                    $('#approve-unlimited-tokens-btn').prop('disabled', false);
                    univoucherNotify.error('Token approval failed: ' + error.message);
                });
            }).catch((error) => {
                univoucherNotify.error('Failed to calculate fees: ' + error.message);
                $('#approve-tokens-btn').prop('disabled', false).text('Approve Token Allowance');
                $('#approve-unlimited-tokens-btn').prop('disabled', false);
            });
        },

        /**
         * Approve unlimited tokens for UniVoucher contract
         */
        approveUnlimitedTokens: function() {
            $('#approve-unlimited-tokens-btn').prop('disabled', true).text('Approving Unlimited...');
            $('#approve-tokens-btn').prop('disabled', true);
            
            // Get transaction data
            var chainId = $('#product-chain-id').val();
            var tokenAddress = $('#product-token-address').val();
            var tokenSymbol = $('#product-token-symbol').val();
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            // Get wallet private key and execute unlimited approval using original method
            this.getWalletPrivateKey().then((privateKey) => {
                return this.executeTokenApproval(privateKey, chainId, tokenAddress, 0, 1, 18, alchemyApiKey, true); // true = unlimited
            }).then((txHash) => {
                $('#allowance-insufficient').hide();
                this.showAllowanceSuccess(txHash, true);
                $('#approve-unlimited-tokens-btn').prop('disabled', false).text(`Approve unlimited ${tokenSymbol} Allowance`);
                $('#approve-tokens-btn').prop('disabled', false);
                this.enablePrepareButton();
                univoucherNotify.success('Unlimited token allowance approved successfully! TX: ' + txHash);
            }).catch((error) => {
                $('#approve-unlimited-tokens-btn').prop('disabled', false).text(`Approve unlimited ${tokenSymbol} Allowance`);
                $('#approve-tokens-btn').prop('disabled', false);
                univoucherNotify.error('Unlimited token approval failed: ' + error.message);
            });
        },

        /**
         * Revoke token allowance
         */
        revokeTokens: function() {
            $('#revoke-tokens-btn').prop('disabled', true).text('Revoking...');
            
            // Get transaction data
            var chainId = $('#product-chain-id').val();
            var tokenAddress = $('#product-token-address').val();
            var tokenSymbol = $('#product-token-symbol').val();
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            // Get wallet private key and execute revocation
            this.getWalletPrivateKey().then((privateKey) => {
                return this.executeTokenApproval(privateKey, chainId, tokenAddress, 0, 1, 18, alchemyApiKey, false, true); // true = revoke
            }).then((txHash) => {
                $('#allowance-sufficient').hide();
                $('#allowance-insufficient').show();
                $('#revoke-tokens-btn').prop('disabled', false).text('Revoke Token Allowance');
                $('#prepare-cards-btn').prop('disabled', true);
                univoucherNotify.success('Token allowance revoked successfully! TX: ' + txHash);
            }).catch((error) => {
                $('#revoke-tokens-btn').prop('disabled', false).text('Revoke Token Allowance');
                univoucherNotify.error('Token revocation failed: ' + error.message);
            });
        },

        /**
         * Show allowance success with transaction hash and explorer link
         */
        showAllowanceSuccess: function(txHash, isUnlimited) {
            var chainId = $('#product-chain-id').val();
            var explorerUrl = this.getExplorerUrl(chainId, txHash);
            var message = isUnlimited ? 'Token allowance is sufficient (unlimited).' : 'Token allowance is sufficient.';
            
            $('#allowance-sufficient').html(`
                <p style="color: #46b450;">✅ ${message}</p>
                <p style="margin-top: 10px; font-size: 13px; color: #666;">
                    Transaction: <a href="${explorerUrl}" target="_blank" style="color: #0073aa; text-decoration: none;">${txHash}</a>
                </p>
                <button type="button" class="button button-secondary" id="revoke-tokens-btn" style="margin-top: 10px;">
                    Revoke Token Allowance
                </button>
            `);
            $('#revoke-tokens-btn').off('click').on('click', this.revokeTokens.bind(this));
            $('#allowance-sufficient').show();
        },



        /**
         * Get wallet private key securely
         */
        getWalletPrivateKey: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'univoucher_get_wallet_address',
                        nonce: $('#verification-nonce').val()
                    },
                    success: (response) => {
                        if (response.success && response.data.private_key) {
                            resolve(response.data.private_key);
                        } else {
                            reject(new Error(response.data.message || 'Failed to get private key'));
                        }
                    },
                    error: () => {
                        reject(new Error('Failed to connect to server'));
                    }
                });
            });
        },

        /**
         * Execute real token approval transaction
         */
        executeTokenApproval: function(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey, unlimited = false, revoke = false) {
            return new Promise((resolve, reject) => {
                try {
                    // Setup provider and signer
                    const networkMap = {
                        1: 'eth-mainnet', 10: 'opt-mainnet', 56: 'bnb-mainnet',
                        137: 'polygon-mainnet', 8453: 'base-mainnet', 42161: 'arb-mainnet', 43114: 'avax-mainnet'
                    };
                    const network = networkMap[chainId];
                    const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;
                    const provider = new ethers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    
                    // UniVoucher contract address
                    const UNIVOUCHER_ADDRESS = '0x51553818203e38ce0E78e4dA05C07ac779ec5b58';
                    
                    // ERC-20 token contract
                    const erc20Abi = [
                        'function approve(address spender, uint256 amount) returns (bool)',
                        'function allowance(address owner, address spender) view returns (uint256)'
                    ];
                    const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, wallet);
                    
                    // Calculate total amount needed with dynamic fee
                    if (revoke) {
                        const totalAmount = 0n; // Revoke by setting allowance to 0
                        this.executeApprovalWithAmount(tokenContract, UNIVOUCHER_ADDRESS, totalAmount, rpcUrl, resolve, reject);
                    } else if (unlimited) {
                        const totalAmount = ethers.MaxUint256;
                        this.executeApprovalWithAmount(tokenContract, UNIVOUCHER_ADDRESS, totalAmount, rpcUrl, resolve, reject);
                    } else {
                        const cardAmountWei = ethers.parseUnits(cardAmount.toString(), tokenDecimals);
                        
                        // Get fee percentage from API and calculate fee
                        this.getUniVoucherFeePercentage(chainId).then((feePercentage) => {
                            const feeAmount = cardAmountWei * BigInt(Math.floor(feePercentage * 100)) / 100n;
                            const totalPerCard = cardAmountWei + feeAmount;
                            const totalAmount = totalPerCard * BigInt(quantity);
                            this.executeApprovalWithAmount(tokenContract, UNIVOUCHER_ADDRESS, totalAmount, rpcUrl, resolve, reject);
                        }).catch(reject);
                    }
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Prepare cards for creation (Step 1 -> Step 2)
         */
        prepareCards: function() {
            $('#internal-wallet-step1').hide();
            $('#internal-wallet-step2').show();
            $('#internal-wallet-step').val('2');
            
            // Start gas estimation
            this.estimateGasCosts();
        },

        /**
         * Estimate gas costs using Alchemy (Real gas estimation)
         */
        estimateGasCosts: function() {
            $('#gas-estimation-loading').show();
            $('#transaction-summary').hide();
            
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var cardAmount = parseFloat($('#product-amount').val()) || 0;
            var tokenSymbol = $('#product-token-symbol').val();
            var tokenAddress = $('#product-token-address').val();
            var tokenDecimals = parseInt($('#product-token-decimals').val()) || 18;
            var chainId = $('#product-chain-id').val();
            var alchemyApiKey = $('#alchemy-api-key').val();
            
            // Calculate fee dynamically
            this.calculateFee(cardAmount, chainId).then((univoucherFee) => {
                var totalPerCard = cardAmount + univoucherFee;
                
                // Get wallet private key and estimate gas for card creation
                this.getWalletPrivateKey().then((privateKey) => {
                    return this.estimateCardCreationGas(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey);
                }).then((gasEstimation) => {
                    // Update transaction summary with real gas estimation
                    $('#tx-cost-card-amount').text(`${cardAmount} ${tokenSymbol}`);
                    $('#tx-cost-univoucher-fee').text(`${univoucherFee.toFixed(6)} ${tokenSymbol}`);
                    $('#tx-cost-quantity').text(quantity);
                    $('#tx-gas-required').text(gasEstimation.gasLimit.toLocaleString());
                    $('#tx-gas-cost').text(`${gasEstimation.gasCostEth.toFixed(6)} ETH`);
                    $('#tx-total-cost').text(`${(totalPerCard * quantity).toFixed(6)} ${tokenSymbol} + ${gasEstimation.gasCostEth.toFixed(6)} ETH`);
                    
                    $('#gas-estimation-loading').hide();
                    $('#transaction-summary').show();
                }).catch((error) => {
                    univoucherNotify.error('Gas estimation failed: ' + error.message);
                    $('#gas-estimation-loading').hide();
                    $('#transaction-summary').hide();
                });
            }).catch((error) => {
                univoucherNotify.error('Failed to calculate fees: ' + error.message);
                $('#gas-estimation-loading').hide();
                $('#transaction-summary').hide();
            });
        },

        /**
         * Check if wallet has sufficient balance for the transaction
         */
        checkWalletBalance: function(privateKey, chainId, tokenAddress, requiredAmount, tokenDecimals, alchemyApiKey) {
            return new Promise((resolve, reject) => {
                try {
                    // Setup provider and wallet
                    const networkMap = {
                        1: 'eth-mainnet', 10: 'opt-mainnet', 56: 'bnb-mainnet',
                        137: 'polygon-mainnet', 8453: 'base-mainnet', 42161: 'arb-mainnet', 43114: 'avax-mainnet'
                    };
                    const network = networkMap[chainId];
                    const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;
                    const provider = new ethers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    
                    if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                        // Native token - check ETH balance
                        provider.getBalance(wallet.address).then((balance) => {
                            const requiredWei = ethers.parseUnits(requiredAmount.toString(), 18);
                            resolve(balance >= requiredWei);
                        }).catch(reject);
                    } else {
                        // ERC-20 token - check token balance
                        const erc20Abi = [
                            'function balanceOf(address owner) view returns (uint256)'
                        ];
                        const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, provider);
                        
                        tokenContract.balanceOf(wallet.address).then((balance) => {
                            const requiredWei = ethers.parseUnits(requiredAmount.toString(), tokenDecimals);
                            resolve(balance >= requiredWei);
                        }).catch(reject);
                    }
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Estimate gas for card creation
         */
        estimateCardCreationGas: function(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey) {
            return new Promise((resolve, reject) => {
                try {
                    // Setup provider and signer
                    const networkMap = {
                        1: 'eth-mainnet', 10: 'opt-mainnet', 56: 'bnb-mainnet',
                        137: 'polygon-mainnet', 8453: 'base-mainnet', 42161: 'arb-mainnet', 43114: 'avax-mainnet'
                    };
                    const network = networkMap[chainId];
                    const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;
                    const provider = new ethers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    
                    // UniVoucher contract
                    const UNIVOUCHER_ADDRESS = '0x51553818203e38ce0E78e4dA05C07ac779ec5b58';
                    const univoucherAbi = [
                        'function depositETH(address slotId, uint256 amount, string memory message, string memory encryptedPrivateKey) external payable',
                        'function depositERC20(address slotId, address tokenAddress, uint256 amount, string memory message, string memory encryptedPrivateKey) external',
                        'function bulkDepositETH(address[] calldata slotIds, uint256[] calldata amounts, string[] calldata messages, string[] calldata encryptedPrivateKeys) external payable',
                        'function bulkDepositERC20(address[] calldata slotIds, address tokenAddress, uint256[] calldata amounts, string[] calldata messages, string[] calldata encryptedPrivateKeys) external',
                        'function calculateFee(uint256 amount) external view returns (uint256)'
                    ];
                    const contract = new ethers.Contract(UNIVOUCHER_ADDRESS, univoucherAbi, wallet);
                    
                    // Use bulk estimation for multiple cards, single estimation for one card
                    if (quantity === 1) {
                        this.estimateSingleCardGas(contract, tokenAddress, cardAmount, tokenDecimals, rpcUrl).then(resolve).catch(reject);
                    } else {
                        this.estimateBulkCardsGas(contract, tokenAddress, cardAmount, tokenDecimals, quantity, rpcUrl).then(resolve).catch(reject);
                    }
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Generate realistic dummy encrypted private key for gas estimation
         */
        generateDummyEncryptedPrivateKey: function() {
            // Create a realistic dummy encrypted private key that matches the actual encryption format
            // This simulates the JSON structure with salt, iv, and ciphertext
            const dummySalt = 'a1b2c3d4e5f678901234567890123456'; // 32 hex chars
            const dummyIv = 'a1b2c3d4e5f6789012345678'; // 24 hex chars  
            const dummyCiphertext = 'dummy_ciphertext_base64_encoded_string_that_matches_real_encryption_length_and_format';
            
            return JSON.stringify({
                salt: dummySalt,
                iv: dummyIv,
                ciphertext: dummyCiphertext
            });
        },

        /**
         * Estimate gas for single card creation
         */
        estimateSingleCardGas: function(contract, tokenAddress, cardAmount, tokenDecimals, rpcUrl) {
            return new Promise((resolve, reject) => {
                try {
                    // Prepare realistic dummy transaction data for estimation
                    const randomWallet = ethers.Wallet.createRandom();
                    const slotId = randomWallet.address;
                    const message = '';
                    const encryptedPrivateKey = this.generateDummyEncryptedPrivateKey();
                    
                    let estimatePromise;
                    
                    if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                        // Native token estimation for depositETH function
                        const amountWei = ethers.parseUnits(cardAmount.toString(), 18);
                        estimatePromise = contract.calculateFee(amountWei).then((feeWei) => {
                            const totalValue = amountWei + feeWei;
                            return contract.depositETH.estimateGas(slotId, amountWei, message, encryptedPrivateKey, { value: totalValue });
                        });
                    } else {
                        // ERC-20 token estimation for depositERC20 function
                        const amountWei = ethers.parseUnits(cardAmount.toString(), tokenDecimals);
                        estimatePromise = contract.depositERC20.estimateGas(slotId, tokenAddress, amountWei, message, encryptedPrivateKey);
                    }
                    
                    // Get gas estimate
                    Promise.all([
                        estimatePromise,
                        this.getGasPriceFromAlchemy(rpcUrl)
                    ]).then(([gasEstimate, gasPrice]) => {
                        // Add 25% buffer to account for encryption complexity differences
                        const gasLimit = gasEstimate * 125n / 100n;
                        const totalGasCost = gasLimit * gasPrice;
                        const gasCostEth = Number(totalGasCost) / Math.pow(10, 18);
                        
                        resolve({
                            gasLimit: Number(gasLimit),
                            gasPrice: gasPrice,
                            gasCostEth: gasCostEth
                        });
                    }).catch(reject);
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Estimate gas for bulk card creation
         */
        estimateBulkCardsGas: function(contract, tokenAddress, cardAmount, tokenDecimals, quantity, rpcUrl) {
            return new Promise((resolve, reject) => {
                try {
                    // Prepare realistic dummy arrays for bulk estimation
                    const slotIds = [];
                    const amounts = [];
                    const messages = [];
                    const encryptedPrivateKeys = [];
                    
                    // Generate realistic dummy data for estimation
                    for (let i = 0; i < quantity; i++) {
                        const randomWallet = ethers.Wallet.createRandom();
                        slotIds.push(randomWallet.address);
                        amounts.push(ethers.parseUnits(cardAmount.toString(), tokenDecimals));
                        messages.push('');
                        encryptedPrivateKeys.push(this.generateDummyEncryptedPrivateKey());
                    }
                    
                    let estimatePromise;
                    
                    if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                        // Native token estimation for bulkDepositETH function
                        const totalAmount = amounts.reduce((sum, amount) => sum + amount, 0n);
                        estimatePromise = contract.bulkDepositETH.estimateGas(slotIds, amounts, messages, encryptedPrivateKeys, { value: totalAmount });
                    } else {
                        // ERC-20 token estimation for bulkDepositERC20 function
                        estimatePromise = contract.bulkDepositERC20.estimateGas(slotIds, tokenAddress, amounts, messages, encryptedPrivateKeys);
                    }
                    
                    // Get gas estimate
                    Promise.all([
                        estimatePromise,
                        this.getGasPriceFromAlchemy(rpcUrl)
                    ]).then(([gasEstimate, gasPrice]) => {
                        // Add 25% buffer to account for encryption complexity differences
                        const gasLimit = gasEstimate * 125n / 100n;
                        const totalGasCost = gasLimit * gasPrice;
                        const gasCostEth = Number(totalGasCost) / Math.pow(10, 18);
                        
                        resolve({
                            gasLimit: Number(gasLimit),
                            gasPrice: gasPrice,
                            gasCostEth: gasCostEth
                        });
                    }).catch(reject);
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Go back to step 1
         */
        backToStep1: function() {
            $('#internal-wallet-step2').hide();
            $('#internal-wallet-step1').show();
            $('#internal-wallet-step').val('1');
            
            // Reset button state
            this.resetCreateCardsButton();
        },

        /**
         * Create cards using internal wallet
         */
        createCardsInternal: function() {
            $('#create-cards-btn').prop('disabled', true).text('Creating Cards...');
            
            var quantity = parseInt($('#card-quantity').val()) || 1;
            var productId = $('#selected-product-id').val();
            
            // Simulate card creation process
            this.createCardsOnBlockchain(quantity).then((result) => {
                // Add cards to inventory via backend
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'univoucher_create_cards_internal',
                        nonce: $('#verification-nonce').val(),
                        product_id: productId,
                        card_data: result.cards,
                        transaction_hash: result.transactionHash,
                        chain_id: $('#product-chain-id').val(),
                        token_address: $('#product-token-address').val() || '0x0000000000000000000000000000000000000000',
                        token_symbol: $('#product-token-symbol').val(),
                        token_type: $('#product-token-type').val(),
                        token_decimals: $('#product-token-decimals').val(),
                        amount: $('#product-amount').val()
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showSuccess(response.data, result.transactionHash);
                        } else {
                            this.showError('Step 2', response.data.message || 'Failed to add cards to inventory');
                            $('#create-cards-btn').prop('disabled', false).text('Create Cards & Add to Inventory');
                        }
                    },
                    error: () => {
                        this.showError('Step 2', 'Failed to connect to server');
                        $('#create-cards-btn').prop('disabled', false).text('Create Cards & Add to Inventory');
                    }
                });
            }).catch((error) => {
                this.showError('Step 2', error.message);
                $('#create-cards-btn').prop('disabled', false).text('Create Cards & Add to Inventory');
            });
        },

        /**
         * Create cards on blockchain using real UniVoucher contract
         */
        createCardsOnBlockchain: function(quantity) {
            return new Promise((resolve, reject) => {
                var chainId = $('#product-chain-id').val();
                var tokenAddress = $('#product-token-address').val();
                var cardAmount = parseFloat($('#product-amount').val()) || 0;
                var tokenDecimals = parseInt($('#product-token-decimals').val()) || 18;
                var alchemyApiKey = $('#alchemy-api-key').val();
                
                
                
                // Get wallet private key and create cards
                this.getWalletPrivateKey().then((privateKey) => {
                    return this.executeCardCreation(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey);
                }).then((result) => {

                    resolve(result);
                }).catch((error) => {

                    reject(error);
                });
            });
        },

        /**
         * Execute real card creation transactions using bulk functions
         */
        executeCardCreation: function(privateKey, chainId, tokenAddress, cardAmount, quantity, tokenDecimals, alchemyApiKey) {
            return new Promise((resolve, reject) => {
                try {
                    // Setup provider and signer
                    const networkMap = {
                        1: 'eth-mainnet', 10: 'opt-mainnet', 56: 'bnb-mainnet',
                        137: 'polygon-mainnet', 8453: 'base-mainnet', 42161: 'arb-mainnet', 43114: 'avax-mainnet'
                    };
                    const network = networkMap[chainId];
                    const rpcUrl = `https://${network}.g.alchemy.com/v2/${alchemyApiKey}`;
                    const provider = new ethers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    
                    // UniVoucher contract
                    const UNIVOUCHER_ADDRESS = '0x51553818203e38ce0E78e4dA05C07ac779ec5b58';
                    const univoucherAbi = [
                        'function depositETH(address slotId, uint256 amount, string memory message, string memory encryptedPrivateKey) external payable',
                        'function depositERC20(address slotId, address tokenAddress, uint256 amount, string memory message, string memory encryptedPrivateKey) external',
                        'function bulkDepositETH(address[] calldata slotIds, uint256[] calldata amounts, string[] calldata messages, string[] calldata encryptedPrivateKeys) external payable',
                        'function bulkDepositERC20(address[] calldata slotIds, address tokenAddress, uint256[] calldata amounts, string[] calldata messages, string[] calldata encryptedPrivateKeys) external',
                        'function calculateFee(uint256 amount) external view returns (uint256)',
                        'event CardCreated(string cardId, address indexed slotId, address indexed creator, address tokenAddress, uint256 tokenAmount, uint256 feePaid, string message, string encryptedPrivateKey, uint256 timestamp)'
                    ];
                    const contract = new ethers.Contract(UNIVOUCHER_ADDRESS, univoucherAbi, wallet);
                    
                    // Use bulk creation for multiple cards, single creation for one card
                    if (quantity === 1) {
                        this.createSingleCard(contract, tokenAddress, cardAmount, tokenDecimals, rpcUrl).then(resolve).catch(reject);
                    } else {
                        this.createBulkCards(contract, tokenAddress, cardAmount, tokenDecimals, quantity, rpcUrl).then(resolve).catch(reject);
                    }
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Create multiple cards using bulk deposit functions
         */
        createBulkCards: function(contract, tokenAddress, cardAmount, tokenDecimals, quantity, rpcUrl) {
            return new Promise((resolve, reject) => {
                try {
                    // Prepare arrays for bulk creation
                    const slotIds = [];
                    const amounts = [];
                    const messages = [];
                    const encryptedPrivateKeys = [];
                    const cardSecrets = [];
                    
                    // Generate all card data upfront
                    for (let i = 0; i < quantity; i++) {
                        const randomWallet = ethers.Wallet.createRandom();
                        const slotId = randomWallet.address;
                        const cardSecret = this.generateFriendlySecret();
                        const message = '';
                        
                        slotIds.push(slotId);
                        amounts.push(ethers.parseUnits(cardAmount.toString(), tokenDecimals));
                        messages.push(message);
                        cardSecrets.push(cardSecret);
                        
                        // Encrypt private key for this card
                        this.encryptPrivateKey(randomWallet.privateKey, cardSecret).then((encryptedPrivateKey) => {
                            encryptedPrivateKeys.push(encryptedPrivateKey);
                            
                            // When all encryptions are complete, execute bulk transaction
                            if (encryptedPrivateKeys.length === quantity) {
                                this.executeBulkTransaction(contract, tokenAddress, slotIds, amounts, messages, encryptedPrivateKeys, cardSecrets, rpcUrl).then(resolve).catch(reject);
                            }
                        }).catch(reject);
                    }
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Execute bulk transaction using UniVoucher bulk functions
         */
        executeBulkTransaction: function(contract, tokenAddress, slotIds, amounts, messages, encryptedPrivateKeys, cardSecrets, rpcUrl) {
            return new Promise((resolve, reject) => {
                try {
                    if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                        // Native token - use bulkDepositETH
                        const totalAmount = amounts.reduce((sum, amount) => sum + amount, 0n);
                        
                        // Get gas estimate for bulkDepositETH function
                        Promise.all([
                            contract.bulkDepositETH.estimateGas(slotIds, amounts, messages, encryptedPrivateKeys, { value: totalAmount }),
                            this.getGasPriceFromAlchemy(rpcUrl)
                        ]).then(([gasEstimate, gasPrice]) => {
                            const gasLimit = gasEstimate * 120n / 100n; // 20% buffer
                            
                            return contract.bulkDepositETH(slotIds, amounts, messages, encryptedPrivateKeys, {
                                value: totalAmount,
                                gasLimit,
                                gasPrice
                            });
                        }).then((tx) => {
                            return tx.wait();
                        }).then((receipt) => {
                            // Extract card IDs from events
                            const cardCreatedEvents = receipt.logs.filter(log => {
                                try {
                                    const parsed = contract.interface.parseLog(log);
                                    return parsed.name === 'CardCreated';
                                } catch (e) {
                                    return false;
                                }
                            });
                            
                            const cards = [];
                            cardCreatedEvents.forEach((event, index) => {
                                const parsed = contract.interface.parseLog(event);
                                cards.push({
                                    card_id: parsed.args.cardId,
                                    card_secret: cardSecrets[index]
                                });
                            });
                            
                            resolve({
                                cards: cards,
                                transactionHash: receipt.hash
                            });
                        }).catch(reject);
                        
                    } else {
                        // ERC-20 token - use bulkDepositERC20
                        Promise.all([
                            contract.bulkDepositERC20.estimateGas(slotIds, tokenAddress, amounts, messages, encryptedPrivateKeys),
                            this.getGasPriceFromAlchemy(rpcUrl)
                        ]).then(([gasEstimate, gasPrice]) => {
                            const gasLimit = gasEstimate * 120n / 100n; // 20% buffer
                            
                            return contract.bulkDepositERC20(slotIds, tokenAddress, amounts, messages, encryptedPrivateKeys, {
                                gasLimit,
                                gasPrice
                            });
                        }).then((tx) => {
                            return tx.wait();
                        }).then((receipt) => {
                            // Extract card IDs from events
                            const cardCreatedEvents = receipt.logs.filter(log => {
                                try {
                                    const parsed = contract.interface.parseLog(log);
                                    return parsed.name === 'CardCreated';
                                } catch (e) {
                                    return false;
                                }
                            });
                            
                            const cards = [];
                            cardCreatedEvents.forEach((event, index) => {
                                const parsed = contract.interface.parseLog(event);
                                cards.push({
                                    card_id: parsed.args.cardId,
                                    card_secret: cardSecrets[index]
                                });
                            });
                            
                            resolve({
                                cards: cards,
                                transactionHash: receipt.hash
                            });
                        }).catch(reject);
                    }
                    
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Create a single card
         */
        createSingleCard: function(contract, tokenAddress, cardAmount, tokenDecimals, rpcUrl) {
            return new Promise((resolve, reject) => {
                try {
                    // Generate card data
                    const randomWallet = ethers.Wallet.createRandom();
                    const slotId = randomWallet.address;
                    const cardSecret = this.generateFriendlySecret();
                    const message = '';
                    
                                         // Encrypt private key with card secret
                     this.encryptPrivateKey(randomWallet.privateKey, cardSecret).then((encryptedPrivateKey) => {
                         if (tokenAddress === '0x0000000000000000000000000000000000000000') {
                             // Native token - estimate gas for depositETH function
                             const amountWei = ethers.parseUnits(cardAmount.toString(), 18);
                             return contract.calculateFee(amountWei).then((feeWei) => {
                                 const totalValue = amountWei + feeWei;
                                 // Get gas estimate for depositETH function
                                 return Promise.all([
                                     contract.depositETH.estimateGas(slotId, amountWei, message, encryptedPrivateKey, { value: totalValue }),
                                     this.getGasPriceFromAlchemy(rpcUrl)
                                 ]).then(([gasEstimate, gasPrice]) => {
                                     const gasLimit = gasEstimate * 120n / 100n; // 20% buffer
                                     
                                     return contract.depositETH(slotId, amountWei, message, encryptedPrivateKey, { 
                                         value: totalValue,
                                         gasLimit,
                                         gasPrice
                                     });
                                 });
                             });
                         } else {
                             // ERC-20 token - estimate gas for depositERC20 function
                             const amountWei = ethers.parseUnits(cardAmount.toString(), tokenDecimals);
                             return Promise.all([
                                 contract.depositERC20.estimateGas(slotId, tokenAddress, amountWei, message, encryptedPrivateKey),
                                 this.getGasPriceFromAlchemy(rpcUrl)
                             ]).then(([gasEstimate, gasPrice]) => {
                                 const gasLimit = gasEstimate * 120n / 100n; // 20% buffer
                                 
                                 return contract.depositERC20(slotId, tokenAddress, amountWei, message, encryptedPrivateKey, {
                                     gasLimit,
                                     gasPrice
                                 });
                             });
                         }
                     }).then((tx) => {
                        return tx.wait();
                    }).then((receipt) => {
                        // Extract card ID from event
                        const cardCreatedEvent = receipt.logs.find(log => {
                            try {
                                const parsed = contract.interface.parseLog(log);
                                return parsed.name === 'CardCreated';
                            } catch (e) {
                                return false;
                            }
                        });
                        
                        let cardId;
                        if (cardCreatedEvent) {
                            const parsed = contract.interface.parseLog(cardCreatedEvent);
                            cardId = parsed.args.cardId;
                            
                            resolve({
                                cards: [{
                                    card_id: cardId,
                                    card_secret: cardSecret
                                }],
                                transactionHash: receipt.hash
                            });
                        } else {
                            // Card ID not found in event - try to get it from API using slot ID
                            // Add small delay to ensure blockchain has updated
                            setTimeout(() => {
                                fetch(`https://api.univoucher.com/v1/cards/single?slotId=${slotId}`)
                                    .then(response => {
                                        if (!response.ok) {
                                            throw new Error(`API error: ${response.status}`);
                                        }
                                        return response.json();
                                    })
                                    .then(cardData => {
                                        cardId = cardData.cardId.toString();
                                        
                                        resolve({
                                            cards: [{
                                                card_id: cardId,
                                                card_secret: cardSecret
                                            }],
                                            transactionHash: receipt.hash
                                        });
                                    })
                                    .catch(apiError => {
                                        reject(new Error('Card creation succeeded but card ID could not be retrieved. Transaction: ' + receipt.hash));
                                    });
                            }, 3000); // 3 second delay to allow blockchain indexing
                        }
                    }).catch(reject);
                    
                } catch (error) {
                    reject(error);
                }
            });
        },



        /**
         * Encrypt private key using UniVoucher standard (PBKDF2 + AES-GCM)
         */
        encryptPrivateKey: function(privateKey, friendlySecret) {
            return new Promise((resolve, reject) => {
                try {
                    // Check if Web Crypto API is available - REQUIRED for UniVoucher compatibility
                    if (typeof crypto === 'undefined' || !crypto.subtle) {
                        reject(new Error('Web Crypto API is required for UniVoucher compatibility. Please use HTTPS or a modern browser.'));
                        return;
                    }
                    
                    // Normalize the friendly secret (remove hyphens and convert to uppercase)
                    const normalizedSecret = friendlySecret.replace(/-/g, '').toUpperCase();
                    
                    // Generate salt and IV using crypto.getRandomValues
                    const saltBytes = new Uint8Array(16);
                    const ivBytes = new Uint8Array(12); // 12 bytes for GCM
                    crypto.getRandomValues(saltBytes);
                    crypto.getRandomValues(ivBytes);
                    
                    // Convert secret to UTF-8 bytes
                    const secretBytes = new TextEncoder().encode(normalizedSecret);
                    
                    // Import the secret as a raw key
                    crypto.subtle.importKey(
                        'raw',
                        secretBytes,
                        { name: 'PBKDF2' },
                        false,
                        ['deriveKey']
                    ).then(baseKey => {
                        // Derive a key using PBKDF2 with same parameters as UniVoucher
                        return crypto.subtle.deriveKey(
                            {
                                name: 'PBKDF2',
                                salt: saltBytes,
                                iterations: 310000, // High iteration count for better security
                                hash: 'SHA-256'
                            },
                            baseKey,
                            { name: 'AES-GCM', length: 256 },
                            false,
                            ['encrypt']
                        );
                    }).then(cryptoKey => {
                        // Convert private key to bytes
                        const privateKeyBytes = new TextEncoder().encode(privateKey);
                        
                        // Encrypt with Web Crypto API
                        return crypto.subtle.encrypt(
                            {
                                name: 'AES-GCM',
                                iv: ivBytes
                            },
                            cryptoKey,
                            privateKeyBytes
                        );
                    }).then(encryptedBuffer => {
                        // Convert to hex string helper
                        const uint8ArrayToHex = (bytes) => {
                            return Array.from(bytes)
                                .map(b => b.toString(16).padStart(2, '0'))
                                .join('');
                        };
                        
                        // Convert ArrayBuffer to Base64
                        const arrayBufferToBase64 = (buffer) => {
                            const bytes = new Uint8Array(buffer);
                            let binary = '';
                            for (let i = 0; i < bytes.byteLength; i++) {
                                binary += String.fromCharCode(bytes[i]);
                            }
                            return btoa(binary);
                        };
                        
                        // Store salt and IV with the ciphertext for decryption (same format as UniVoucher)
                        const result = {
                            salt: uint8ArrayToHex(saltBytes),
                            iv: uint8ArrayToHex(ivBytes),
                            ciphertext: arrayBufferToBase64(encryptedBuffer)
                        };
                        
                        // Return as a JSON string
                        resolve(JSON.stringify(result));
                    }).catch(reject);
                    
                } catch (error) {
                    reject(new Error(`Encryption failed: ${error.message}`));
                }
            });
        },



        /**
         * Generate friendly card secret (UniVoucher standard with unbiased sampling)
         */
        generateFriendlySecret: function() {
            const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            
            // Verify crypto API availability
            if (!crypto || !crypto.getRandomValues) {
                throw new Error("Cryptographically secure random number generation not available");
            }
            
            // Request random bytes (more than needed to handle rejection sampling)
            const randomBytes = new Uint8Array(64);
            
            try {
                crypto.getRandomValues(randomBytes);
            } catch (err) {
                throw new Error("Failed to generate secure random values");
            }
            
            let result = '';
            let byteIndex = 0;
            
            // Generate 4 groups of 5 letters (20 characters total)
            for (let group = 0; group < 4; group++) {
                for (let i = 0; i < 5; i++) {
                    // Use rejection sampling to get unbiased random index
                    const { index, newPosition } = this.getUnbiasedIndex(randomBytes, byteIndex, charset.length);
                    byteIndex = newPosition;
                    
                    // Add the character to our result
                    result += charset[index];
                }
                
                if (group < 3) {
                    result += '-';
                }
            }
            
            return result;
        },

        /**
         * Produces an unbiased random index for any charset length
         * Uses rejection sampling to avoid modulo bias
         */
        getUnbiasedIndex: function(randomBytes, bytePosition, charsetLength) {
            // Find the largest multiple of charsetLength that is less than 256
            const maxValidValue = 256 - (256 % charsetLength);
            let value, position = bytePosition;
            
            do {
                if (position >= randomBytes.length) {
                    // Need more random bytes
                    crypto.getRandomValues(randomBytes);
                    position = 0;
                }
                value = randomBytes[position++];
            } while (value >= maxValidValue);
            
            return {
                index: value % charsetLength,
                newPosition: position
            };
        },

        /**
         * Show success (Step 3)
         */
        showSuccess: function(data, transactionHash) {
            $('#internal-wallet-step2').hide();
            $('#internal-wallet-step3').show();
            $('#internal-wallet-step').val('3');
            
            // Update success message
            $('#success-message').text(`${data.success_count} cards created successfully!`);
            
            // Show created cards
            var cardsHtml = '<div style="font-family: monospace; font-size: 12px;">';
            data.added_cards.forEach((cardId, index) => {
                cardsHtml += `<div style="margin: 5px 0;">Card ${index + 1}: ${cardId}</div>`;
            });
            cardsHtml += '</div>';
            $('#created-cards-list').html(cardsHtml);
            
            // Show current stock quantity
            $('#new-stock-quantity').text(data.current_stock || 'Updated automatically');
            
            // Show transaction link
            var chainId = $('#product-chain-id').val();
            var explorerUrl = this.getExplorerUrl(chainId);
            $('#transaction-link').attr('href', `${explorerUrl}/tx/${transactionHash}`).text(transactionHash);
            
            univoucherNotify.success(data.message);
        },

        /**
         * Get explorer URL for chain
         */
        getExplorerUrl: function(chainId, txHash = null) {
            const explorers = {
                1: 'https://etherscan.io',
                10: 'https://optimistic.etherscan.io',
                56: 'https://bscscan.com',
                137: 'https://polygonscan.com',
                8453: 'https://basescan.org',
                42161: 'https://arbiscan.io',
                43114: 'https://snowtrace.io'
            };
            const baseUrl = explorers[chainId] || 'https://etherscan.io';
            return txHash ? `${baseUrl}/tx/${txHash}` : baseUrl;
        },

        /**
         * Show error message
         */
        showError: function(step, message) {
            var errorElement = step === 'Step 1' ? '#step1-error' : '#step2-error';
            $(errorElement).text(message).show();
            setTimeout(() => {
                $(errorElement).hide();
            }, 5000);
        },

        /**
         * Reset create cards button state
         */
        resetCreateCardsButton: function() {
            $('#create-cards-btn').prop('disabled', false).text('Create Cards & Add to Inventory');
        },

        /**
         * Create more cards (reset to step 1)
         */
        createMoreCards: function() {
            $('#internal-wallet-step3').hide();
            $('#internal-wallet-step1').show();
            $('#internal-wallet-step').val('1');
            
            // Reset form
            $('#card-quantity').val(1);
            this.updateCostSummary();
            
            // Reset button state
            this.resetCreateCardsButton();
        },

        /**
         * Get UniVoucher fee percentage from API with caching
         */
        getUniVoucherFeePercentage: function(chainId) {
            // Check cache first
            if (this.feeCache[chainId]) {
                return Promise.resolve(this.feeCache[chainId]);
            }
            
            return new Promise((resolve, reject) => {
                fetch(`https://api.univoucher.com/v1/fees/current?chainId=${chainId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Failed to fetch fee: ${response.status} ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.feePercentage !== undefined) {
                            const feePercentage = data.feePercentage / 100; // Convert percentage to decimal
                            // Cache the result
                            this.feeCache[chainId] = feePercentage;
                            resolve(feePercentage);
                        } else {
                            throw new Error('Invalid fee data received from API');
                        }
                    })
                    .catch(error => {
                        console.error('Failed to fetch UniVoucher fee:', error);
                        reject(new Error('Failed to fetch fee from UniVoucher API. Please try again.'));
                    });
            });
        },

        /**
         * Calculate fee with dynamic percentage
         */
        calculateFee: function(cardAmount, chainId) {
            return this.getUniVoucherFeePercentage(chainId).then(feePercentage => {
                return cardAmount * feePercentage;
            }).catch(error => {
                // Show error to user and re-throw
                univoucherNotify.error(error.message);
                throw error;
            });
        },

        /**
         * Execute approval with given amount
         */
        executeApprovalWithAmount: function(tokenContract, spenderAddress, totalAmount, rpcUrl, resolve, reject) {
            // Get gas estimate for ERC-20 approve function
            Promise.all([
                tokenContract.approve.estimateGas(spenderAddress, totalAmount),
                this.getGasPriceFromAlchemy(rpcUrl)
            ]).then(([gasEstimate, gasPrice]) => {
                // Add 20% buffer
                const gasLimit = gasEstimate * 120n / 100n;
                
                // Execute with explicit gas settings
                return tokenContract.approve(spenderAddress, totalAmount, {
                    gasLimit,
                    gasPrice
                });
            }).then((tx) => {
                return tx.wait();
            }).then((receipt) => {
                resolve(receipt.hash);
            }).catch(reject);
        }
    };

    // Make it globally accessible
    window.UniVoucherInternalWallet = UniVoucherInternalWallet;

})(jQuery);