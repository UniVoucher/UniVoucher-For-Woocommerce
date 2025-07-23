/**
 * UniVoucher For WooCommerce - Product Admin Scripts
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize product admin functionality
        UniVoucherProductAdmin.init();
        
    });

    /**
     * UniVoucher Product Admin object
     */
    var UniVoucherProductAdmin = {
        
        /**
         * Initialize product admin functionality
         */
        init: function() {
            this.initToggleFields();
            this.initNetworkChange();
            this.initTokenTypeChange();
            this.initGetTokenInfo();
            this.updateTokenSymbol();
            this.initAutoGenerate();
            this.initImageGeneration();
        },
        
        /**
         * Initialize field toggling based on enable checkbox
         */
        initToggleFields: function() {
            var $enableCheckbox = $('#_univoucher_enabled');
            var $options = $('.univoucher-gift-card-options, .univoucher-auto-generate-section');
            
            // Initial state
            this.toggleOptions($enableCheckbox.is(':checked'), $options);
            
            // Handle checkbox change
            $enableCheckbox.on('change', function() {
                UniVoucherProductAdmin.toggleOptions($(this).is(':checked'), $options);
            });
        },
        
        /**
         * Initialize network change handler
         */
        initNetworkChange: function() {
            $('#_univoucher_network').on('change', function() {
                UniVoucherProductAdmin.resetTokenDetailsOnNetworkChange();
                UniVoucherProductAdmin.updateTokenSymbol();
            });
        },
        
        /**
         * Initialize token type change handler
         */
        initTokenTypeChange: function() {
            var $tokenType = $('#_univoucher_token_type');
            
            // Initial state
            this.toggleERC20Fields($tokenType.val());
            
            // Handle change
            $tokenType.on('change', function() {
                UniVoucherProductAdmin.toggleERC20Fields($(this).val());
                UniVoucherProductAdmin.updateTokenSymbol();
            });
        },
        
        /**
         * Initialize get token info functionality
         */
        initGetTokenInfo: function() {
            // Disable button if cards exist
            if (univoucher_product_ajax.has_existing_cards) {
                $('#univoucher-get-token-info').prop('disabled', true);
            }
            
            $('#univoucher-get-token-info').on('click', function(e) {
                e.preventDefault();
                UniVoucherProductAdmin.fetchTokenInfo();
            });
        },
        
        /**
         * Toggle options visibility
         */
        toggleOptions: function(enabled, $options) {
            // Check if product has existing cards (this will be set by PHP)
            var hasExistingCards = univoucher_product_ajax.has_existing_cards || false;
            
            if (enabled && !hasExistingCards) {
                $options.slideDown();
            } else {
                $options.slideUp();
            }
        },
        
        /**
         * Toggle ERC20 specific fields
         */
        toggleERC20Fields: function(tokenType) {
            var $erc20Fields = $('.univoucher-erc20-field');
            
            if ('erc20' === tokenType) {
                $erc20Fields.show();
            } else {
                $erc20Fields.hide();
                // Clear token info when switching away from ERC20
                $('#univoucher-token-info').html('');
            }
        },
        
        /**
         * Reset token details when network changes
         */
        resetTokenDetailsOnNetworkChange: function() {
            var tokenType = $('#_univoucher_token_type').val();
            
            // Only reset ERC-20 token details (native tokens will be updated by updateTokenSymbol)
            if ('erc20' === tokenType) {                
                // Clear token symbol and decimals
                $('#univoucher-token-symbol-display').text('Click "Get Token Info"');
                $('#_univoucher_token_symbol').val('');
                $('#univoucher-token-decimals-display').text('Click "Get Token Info"');
                $('#_univoucher_token_decimals').val('');
                
                // Clear token info display
                $('#univoucher-token-info').html('');
            }
        },
        
        /**
         * Update token symbol and decimals based on network and token type
         */
        updateTokenSymbol: function() {
            var network = $('#_univoucher_network').val();
            var tokenType = $('#_univoucher_token_type').val();
            var $symbolDisplay = $('#univoucher-token-symbol-display');
            var $symbolInput = $('#_univoucher_token_symbol');
            var $decimalsDisplay = $('#univoucher-token-decimals-display');
            var $decimalsInput = $('#_univoucher_token_decimals');
            
            if ('native' === tokenType) {
                // Use native token symbol and decimals for the selected network
                var networkData = univoucher_product_ajax.networks[network];
                if (networkData) {
                    $symbolDisplay.text(networkData.symbol);
                    $symbolInput.val(networkData.symbol);
                    $decimalsDisplay.text(networkData.decimals || 18);
                    $decimalsInput.val(networkData.decimals || 18);
                }
            } else {
                // For ERC20, clear the fields unless they were set by Get Token Info
                var currentSymbol = $symbolInput.val();
                var currentDecimals = $decimalsInput.val();
                
                // Check if current values are native token symbols (need to clear them)
                var nativeSymbols = ['ETH', 'BNB', 'POL', 'AVAX'];
                var isNativeSymbol = nativeSymbols.indexOf(currentSymbol) !== -1;
                
                // Clear symbol and decimals when switching to ERC20 or if they contain native values
                if (isNativeSymbol || !currentSymbol || currentSymbol === 'TOKEN') {
                    $symbolDisplay.text('Click "Get Token Info"');
                    $symbolInput.val('');
                }
                
                // Clear decimals if they're from native tokens (usually 18) or not set properly
                if (!currentDecimals || (isNativeSymbol && currentDecimals === '18')) {
                    $decimalsDisplay.text('Click "Get Token Info"');
                    $decimalsInput.val('');
                }
            }
        },
        
        /**
         * Fetch token information from blockchain
         */
        fetchTokenInfo: function() {
            var tokenAddress = $('#_univoucher_token_address').val();
            var network = $('#_univoucher_network').val();
            var $button = $('#univoucher-get-token-info');
            var $spinner = $('#univoucher-token-spinner');
            var $tokenInfo = $('#univoucher-token-info');
            
            if (!tokenAddress) {
                this.showTokenError('Please enter a token address.');
                return;
            }
            
            // Validate address format
            if (!/^0x[a-fA-F0-9]{40}$/.test(tokenAddress)) {
                this.showTokenError('Invalid token address format.');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $tokenInfo.html('');
            
            // Make AJAX request
            $.ajax({
                url: univoucher_product_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'univoucher_get_token_info',
                    nonce: univoucher_product_ajax.nonce,
                    token_address: tokenAddress,
                    network: network
                },
                success: function(response) {
                    if (response.success) {
                        UniVoucherProductAdmin.showTokenSuccess(response.data);
                    } else {
                        UniVoucherProductAdmin.showTokenError(response.data.message || 'Unknown error occurred.');
                    }
                },
                error: function() {
                    UniVoucherProductAdmin.showTokenError('Failed to fetch token information.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Show token information success
         */
        showTokenSuccess: function(tokenData) {
            var $tokenInfo = $('#univoucher-token-info');
            var $symbolDisplay = $('#univoucher-token-symbol-display');
            var $symbolInput = $('#_univoucher_token_symbol');
            var $decimalsDisplay = $('#univoucher-token-decimals-display');
            var $decimalsInput = $('#_univoucher_token_decimals');
            
            // Update token symbol
            $symbolDisplay.text(tokenData.symbol);
            $symbolInput.val(tokenData.symbol);
            
            // Update token decimals
            $decimalsDisplay.text(tokenData.decimals);
            $decimalsInput.val(tokenData.decimals);
            
            // Show token information
            var html = '<div class="univoucher-token-success">' +
                '<p><strong>✓ Token Found:</strong></p>' +
                '<table class="form-table">' +
                '<tr><td><strong>Name:</strong></td><td>' + this.escapeHtml(tokenData.name) + '</td></tr>' +
                '<tr><td><strong>Symbol:</strong></td><td>' + this.escapeHtml(tokenData.symbol) + '</td></tr>' +
                '<tr><td><strong>Decimals:</strong></td><td>' + tokenData.decimals + '</td></tr>' +
                '<tr><td><strong>Address:</strong></td><td>' + this.escapeHtml(tokenData.address) + '</td></tr>' +
                '</table>' +
                '</div>';
            
            $tokenInfo.html(html).hide().fadeIn();
            
            // Update auto-generate button state
            this.updateAutoGenerateButton();
        },
        
        /**
         * Show token error
         */
        showTokenError: function(message) {
            var $tokenInfo = $('#univoucher-token-info');
            
            var html = '<div class="univoucher-token-error">' +
                '<p><strong>✗ Error:</strong> ' + this.escapeHtml(message) + '</p>' +
                '</div>';
            
            $tokenInfo.html(html).hide().fadeIn();
        },
        
        /**
         * Initialize auto-generation functionality
         */
        initAutoGenerate: function() {
            var self = this;
            
            // Monitor field changes to enable/disable auto-generate button
            $('#_univoucher_network, #_univoucher_card_amount, #_univoucher_token_symbol').on('change keyup', function() {
                // Reset both buttons when card details change
                self.resetButtonsOnCardDetailsChange();
                
                self.updateAutoGenerateButton();
                self.updateImageGenerationButtonState();
            });
            
            // Initial check
            this.updateAutoGenerateButton();
            
            // Handle auto-generate button click
            $('#univoucher-auto-generate-btn').on('click', function() {
                self.generateTitleAndDescription();
            });
        },

        /**
         * Update auto-generate button state
         */
        updateAutoGenerateButton: function() {
            var $button = $('#univoucher-auto-generate-btn');
            var $status = $('#univoucher-content-status');
            var isComplete = this.areCardDetailsComplete();
            
            $button.prop('disabled', !isComplete);
            
            // Don't update status if showing success message
            if ($status.html().indexOf('Content generated successfully') !== -1) {
                return;
            }
            
            if (!isComplete) {
                $status.html('<span style="color: #d63638;">⚠ Please complete all card details before generating content</span>');
            } else {
                $status.html('<span style="color: #f56e28;">• Ready to generate content</span>');
            }
        },

        /**
         * Check if all card details are complete
         */
        areCardDetailsComplete: function() {
            var network = $('#_univoucher_network').val();
            var amount = $('#_univoucher_card_amount').val();
            var symbol = $('#_univoucher_token_symbol').val();
            
            return network && amount && symbol;
        },

        /**
         * Generate title and description based on card details
         */
        generateTitleAndDescription: function() {
            var self = this;
            
            if (!this.areCardDetailsComplete()) {
                alert('Please complete all card details first.');
                return;
            }
            
            var $button = $('#univoucher-auto-generate-btn');
            var $status = $('#univoucher-content-status');
            
            // Show loading state
            $button.prop('disabled', true).text('Generating Content...');
            $status.html('<span style="color: #0073aa;">🔄 Generating content, please wait...</span>');
            
            // Fetch templates from settings
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'univoucher_get_content_templates',
                    nonce: univoucher_product_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.applyTemplates(response.data);
                    } else {
                        // Fallback to hardcoded templates if settings fetch fails
                        var fallbackTemplates = {
                            title_template: 'UniVoucher {amount} {symbol} Gift Card on {network}',
                            short_description_template: 'Digital gift card worth {amount} {symbol} on {network} network. Instantly delivered via UniVoucher.',
                            description_template: 'This is a UniVoucher digital gift card containing {amount} {symbol} tokens on the {network} blockchain network.\n\nFeatures:\n• Instant digital delivery\n• Secure blockchain-based gift card\n• Redeemable on {network} network\n• Value: {amount} {symbol}\n\nAfter purchase, you will receive your gift card details that can be redeemed through the UniVoucher platform.'
                        };
                        self.applyTemplates(fallbackTemplates);
                    }
                },
                error: function() {
                    // Fallback to hardcoded templates on error
                    var fallbackTemplates = {
                        title_template: 'UniVoucher {amount} {symbol} Gift Card on {network}',
                        short_description_template: 'Digital gift card worth {amount} {symbol} on {network} network. Instantly delivered via UniVoucher.',
                        description_template: 'This is a UniVoucher digital gift card containing {amount} {symbol} tokens on the {network} blockchain network.\n\nFeatures:\n• Instant digital delivery\n• Secure blockchain-based gift card\n• Redeemable on {network} network\n• Value: {amount} {symbol}\n\nAfter purchase, you will receive your gift card details that can be redeemed through the UniVoucher platform.'
                    };
                    self.applyTemplates(fallbackTemplates);
                }
            });
        },
        
        /**
         * Apply templates with placeholder replacement
         */
        applyTemplates: function(templates) {
            var amount = $('#_univoucher_card_amount').val();
            var symbol = $('#_univoucher_token_symbol').val();
            var networkSelect = $('#_univoucher_network');
            var networkText = networkSelect.find('option:selected').text();
            
            // Replace placeholders in templates
            var title = templates.title_template
                .replace(/{amount}/g, amount)
                .replace(/{symbol}/g, symbol)
                .replace(/{network}/g, networkText);
                
            var shortDescription = templates.short_description_template
                .replace(/{amount}/g, amount)
                .replace(/{symbol}/g, symbol)
                .replace(/{network}/g, networkText);
                
            var fullDescription = templates.description_template
                .replace(/{amount}/g, amount)
                .replace(/{symbol}/g, symbol)
                .replace(/{network}/g, networkText);
            
            // Update the fields
            var $titleField = $('#title');
            $titleField.val(title);
            
            // Remove placeholder attribute if it exists
            if ($titleField.attr('placeholder')) {
                $titleField.removeAttr('placeholder');
            }
            
            // Trigger change event to update UI
            $titleField.trigger('change').trigger('input');
            
            // Update short description (excerpt)
            if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                tinymce.get('excerpt').setContent(shortDescription);
            } else {
                $('#excerpt').val(shortDescription);
            }
            
            // Update full description (WordPress editor) - pass HTML as-is
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').setContent(fullDescription);
            } else {
                $('#content').val(fullDescription);
            }
            
            // Show success message
            this.showAutoGenerateSuccess();
        },
        
        /**
         * Show success message for auto-generation
         */
        showAutoGenerateSuccess: function() {
            var $button = $('#univoucher-auto-generate-btn');
            var $status = $('#univoucher-content-status');
            
            // Keep button disabled with success state
            $button.text('Content Generated Successfully').prop('disabled', true);
            $status.html('<span style="color: #00a32a;">✓ Content generated successfully!</span>');
        },

        /**
         * Reset both buttons when card details change
         */
        resetButtonsOnCardDetailsChange: function() {
            // Reset auto-generate button if it shows success
            var $autoGenerateButton = $('#univoucher-auto-generate-btn');
            var $contentStatus = $('#univoucher-content-status');
            
            if ($autoGenerateButton.text() === 'Content Generated Successfully') {
                $autoGenerateButton.text('Generate Title & Description').prop('disabled', false);
                $contentStatus.html(''); // Clear success message
            }
            
            // Reset image generation button if it shows success
            var $imageButton = $('#univoucher-generate-image-btn');
            var $imageStatus = $('#univoucher-image-status');
            
            if ($imageButton.text() === 'Image Generated Successfully!') {
                $imageButton.text('Generate Product Image').prop('disabled', false);
                $imageStatus.html(''); // Clear success message
                // Clear any success flags
                this.isShowingSuccess = false;
            }
        },

        /**
         * Initialize image generation functionality
         */
        initImageGeneration: function() {
            var self = this;
            
            // Handle image generation button click
            $('#univoucher-generate-image-btn').on('click', function(e) {
                e.preventDefault();
                self.generateProductImage();
            });
            
            // Store initial card details to track changes
            this.storeInitialCardDetails();
            
            // Monitor card detail changes
            $('#_univoucher_network, #_univoucher_token_type, #_univoucher_token_address, #_univoucher_card_amount, #_univoucher_token_symbol, #_univoucher_token_decimals').on('change keyup', function() {
                // Reset buttons when any card detail changes (including token type, address, decimals)
                self.resetButtonsOnCardDetailsChange();
                self.updateImageGenerationButtonState();
            });
            
            // Monitor product save state and update button accordingly
            this.updateImageGenerationButtonState();
            
            // Monitor for product ID changes (when product gets saved)
            setInterval(function() {
                self.updateImageGenerationButtonState();
            }, 1000);
            
            // Update initial details when product is saved
            $('#post').on('submit', function() {
                // Small delay to allow form submission to process
                setTimeout(function() {
                    self.storeInitialCardDetails();
                    self.updateImageGenerationButtonState();
                }, 500);
            });
        },

        /**
         * Store initial card details to track changes
         */
        storeInitialCardDetails: function() {
            this.initialCardDetails = {
                network: $('#_univoucher_network').val(),
                token_type: $('#_univoucher_token_type').val(),
                token_address: $('#_univoucher_token_address').val(),
                card_amount: $('#_univoucher_card_amount').val(),
                token_symbol: $('#_univoucher_token_symbol').val(),
                token_decimals: $('#_univoucher_token_decimals').val()
            };
        },

        /**
         * Check if card details have changed since last save
         */
        haveCardDetailsChanged: function() {
            if (!this.initialCardDetails) return false;
            
            var current = {
                network: $('#_univoucher_network').val(),
                token_type: $('#_univoucher_token_type').val(),
                token_address: $('#_univoucher_token_address').val(),
                card_amount: $('#_univoucher_card_amount').val(),
                token_symbol: $('#_univoucher_token_symbol').val(),
                token_decimals: $('#_univoucher_token_decimals').val()
            };
            
            return JSON.stringify(this.initialCardDetails) !== JSON.stringify(current);
        },

        /**
         * Update image generation button state based on product save status
         */
        updateImageGenerationButtonState: function() {
            // Don't update state while generating image or showing success
            if (this.isGeneratingImage || this.isShowingSuccess) {
                return;
            }
            
            var $button = $('#univoucher-generate-image-btn');
            var $status = $('#univoucher-image-status');
            var productId = $('#post_ID').val();
            var postStatus = $('#post_status').val();
            var originalPostStatus = $('#original_post_status').val();
            var areDetailsComplete = this.areCardDetailsComplete();
            var haveDetailsChanged = this.haveCardDetailsChanged();
            
            // Check if product is properly saved (not auto-draft)
            var isProductSaved = productId && 
                                productId !== '0' && 
                                productId !== '' && 
                                postStatus !== 'auto-draft' && 
                                originalPostStatus !== 'auto-draft';
            
            // Reset button text to default
            $button.text('Generate Product Image');
            $button.removeAttr('title');
            
            // Clear any previous results when status changes
            $('#univoucher-image-result').html('');
            
            if (!isProductSaved) {
                $button.prop('disabled', true);
                $status.html('<span style="color: #d63638;">⚠ Please save the product first before generating image</span>');
            } else if (!areDetailsComplete) {
                $button.prop('disabled', true);
                $status.html('<span style="color: #d63638;">⚠ Please complete all card details before generating image</span>');
            } else if (haveDetailsChanged) {
                $button.prop('disabled', true);
                $status.html('<span style="color: #d63638;">⚠ Card details have changed. Please save the product first</span>');
            } else {
                $button.prop('disabled', false);
                $status.html('<span style="color: #f56e28;">• Ready to generate image</span>');
            }
        },

        /**
         * Generate product image
         */
        generateProductImage: function() {
            var self = this;
            
            // Check if product is saved first
            var productId = $('#post_ID').val();
            var postStatus = $('#post_status').val();
            var originalPostStatus = $('#original_post_status').val();
            
            // Check if product is properly saved (not auto-draft)
            var isProductSaved = productId && 
                                productId !== '0' && 
                                productId !== '' && 
                                postStatus !== 'auto-draft' && 
                                originalPostStatus !== 'auto-draft';
            
            if (!isProductSaved) {
                alert('Please save the product first before generating image.');
                return;
            }
            
            // Check if all card details are complete
            if (!this.areCardDetailsComplete()) {
                alert('Please complete all card details before generating image.');
                return;
            }

            var $button = $('#univoucher-generate-image-btn');
            var $spinner = $('#univoucher-image-spinner');
            var $result = $('#univoucher-image-result');
            var $status = $('#univoucher-image-status');
            
            // Set flag to prevent monitoring from interfering
            this.isGeneratingImage = true;
            
            // Show loading state
            $button.prop('disabled', true).text('Generating Image...');
            $spinner.addClass('is-active');
            $result.html('');
            $status.html('<span style="color: #0073aa;">🔄 Generating image, please wait...</span>');
            
            // Collect data
            var data = {
                action: 'univoucher_generate_image',
                nonce: univoucher_product_ajax.nonce,
                product_id: productId
            };
            
            // Make AJAX request for image generation
            $.ajax({
                url: univoucher_product_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Set flag to prevent status monitoring from interfering with success state
                        self.isShowingSuccess = true;
                        
                        // Show success message without reloading entire page
                        $button.text('Image Generated Successfully!').prop('disabled', true);
                        $spinner.removeClass('is-active');
                        $status.html('<span style="color: #00a32a;">✓ Image generated and set successfully!</span>');
                        
                        // Reload only the featured image metabox
                        if (response.data.attachment_id) {
                            // Update the featured image display directly
                            var $metabox = $('#postimagediv');
                            var $inside = $metabox.find('.inside');
                            
                            // Show loading state
                            $inside.html('<p>' + 'Updating featured image...' + '</p>');
                            
                            // Try to get updated thumbnail HTML via AJAX
                            $.post(ajaxurl, {
                                action: 'set-post-thumbnail',
                                post_id: $('input#post_ID').val(),
                                thumbnail_id: response.data.attachment_id,
                                _wpnonce: $('#_wpnonce').val(),
                                json: 1
                            }).done(function(result) {
                                if (result && result.success && result.data) {
                                    // Replace with the updated HTML
                                    $inside.html(result.data);
                                } else {
                                    // Fallback: simple update with image preview
                                    var imageHtml = '<p><strong>Featured image updated!</strong></p>';
                                    if (response.data.image_url) {
                                        imageHtml += '<p><img src="' + response.data.image_url + '" style="max-width: 100%; height: auto;" /></p>';
                                    }
                                    imageHtml += '<p><a href="#" onclick="location.reload(); return false;">Refresh page to see full featured image controls</a></p>';
                                    $inside.html(imageHtml);
                                }
                            }).fail(function() {
                                // Fallback on AJAX failure
                                var imageHtml = '<p><strong>Featured image updated!</strong></p>';
                                if (response.data.image_url) {
                                    imageHtml += '<p><img src="' + response.data.image_url + '" style="max-width: 100%; height: auto;" /></p>';
                                }
                                imageHtml += '<p><a href="#" onclick="location.reload(); return false;">Refresh page to see full featured image controls</a></p>';
                                $inside.html(imageHtml);
                            });
                        }
                        
                        // Keep button disabled with success state (no reset)
                        // Button will remain disabled with success message
                    } else {
                        self.showImageGenerationError(response.data.message || 'Unknown error occurred.');
                    }
                },
                error: function() {
                    self.showImageGenerationError('Failed to generate image. Please try again.');
                },
                complete: function() {
                    // Clear the generation flag
                    self.isGeneratingImage = false;
                    
                    // Always remove spinner, but only reset button state on error
                    $spinner.removeClass('is-active');
                    
                    // Only reset button state if there was an error (not on success)
                    if ($button.text() !== 'Image Generated Successfully!') {
                        $button.prop('disabled', false).text('Generate Product Image');
                        self.updateImageGenerationButtonState();
                    }
                }
            });
        },



        /**
         * Show image generation error
         */
        showImageGenerationError: function(message) {
            var $status = $('#univoucher-image-status');
            
            $status.html('<span style="color: #d63638;">✗ Error: ' + this.escapeHtml(message) + '</span>');
        },


        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

})(jQuery); 