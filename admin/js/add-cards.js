/**
 * UniVoucher For WooCommerce Add Cards JavaScript
 */

(function($) {
    'use strict';

    /**
     * UniVoucher Add Cards object
     */
    var UniVoucherAddCards = {
        
        /**
         * Initialize add cards functionality
         */
        init: function() {
            this.loadProducts();
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Product selection with automatic loading
            $('#selected-product').on('change', this.onProductChangeWithConfirmation);
            
            // Method selection
            $(document).on('click', '.method-box-inline.available', this.onMethodSelect);
            
            // Card management
            $('#add-card-row-btn').on('click', this.addCardRow);
            $(document).on('click', '.remove-card-btn', this.removeCardRow);
            
            // Input formatting and validation clearing
            $(document).on('input', '.card-id-input', this.handleCardIdInput);
            $(document).on('input', '.card-secret-input', this.handleCardSecretInput);
            
            // Validation buttons
            $('#validate-all-cards-btn').on('click', this.validateAllCards);
            $(document).on('click', '.validate-card-btn', this.validateSingleCard);
            
            // Form submission
            $('#add-cards-btn').on('click', this.addCards);
            
            // CSV upload handler
            $('#upload-csv-btn').on('click', this.triggerCsvUpload);
            $('#csv-file').on('change', this.handleCsvUpload);
        },
        
        /**
         * Load products with UniVoucher enabled
         */
        loadProducts: function() {
            $('#product-loading').show();
            $('#product-selection-form').hide();
            $('#no-products-message').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'univoucher_get_products',
                    nonce: $('#get-products-nonce').val()
                },
                success: function(response) {
                    $('#product-loading').hide();
                    
                    if (response.success && response.data.length > 0) {
                        var $select = $('#selected-product');
                        $select.empty().append('<option value="">Select a product...</option>');
                        
                        $.each(response.data, function(index, product) {
                            $select.append('<option value="' + product.id + '">' + product.name + '</option>');
                        });
                        
                        $('#product-selection-form').show();
                    } else {
                        $('#no-products-message').show();
                    }
                },
                error: function() {
                    $('#product-loading').hide();
                    univoucherNotify.error('Failed to load products. Please try again.');
                }
            });
        },
        
        /**
         * Handle product dropdown change with confirmation
         */
        onProductChangeWithConfirmation: function() {
            var productId = $(this).val();
            
            if (!productId) {
                UniVoucherAddCards.clearAllSections();
                return;
            }
            
            // Check if there are unsaved cards
            var hasUnsavedCards = UniVoucherAddCards.hasUnsavedCards();
            
            if (hasUnsavedCards) {
                var confirmMessage = 'Changing the product will reset the form and any unsaved cards will be lost. Are you sure you want to continue?';
                if (!confirm(confirmMessage)) {
                    // Reset the dropdown to the previous selection
                    $('#selected-product').val($('#selected-product-id').val());
                    return;
                }
            }
            
            UniVoucherAddCards.loadProductSettings(productId);
        },
        
        /**
         * Check if there are unsaved cards in the form
         */
        hasUnsavedCards: function() {
            var hasCards = false;
            $('.card-id-input, .card-secret-input').each(function() {
                if ($(this).val().trim() !== '') {
                    hasCards = true;
                    return false; // Break out of each loop
                }
            });
            return hasCards;
        },
        
        /**
         * Clear all sections except product selection
         */
        clearAllSections: function() {
            $('#product-details').hide();
            $('#method-selection').hide();
            $('#method-elements').hide();
            $('#selected-product-id').val('');
            
            // Clear all hidden fields
            $('#product-chain-id, #product-token-address, #product-token-type, #product-token-symbol, #product-token-decimals, #product-amount').val('');
            
            // Reset card inputs to initial state
            this.resetCardTable();
            
            // Clear product settings display
            $('#product-settings-display').html('');
            
            // Hide validation requirements
            $('#validation-requirements').hide();
            
            // Clear method forms
            $('.method-form').hide();
            $('.method-box-inline').removeClass('active');
            
            // Reset internal wallet specific elements
            $('#current-method').val('');
            $('#internal-wallet-step').val('1');
            $('#internal-wallet-step1').show();
            $('#internal-wallet-step2').hide();
            $('#internal-wallet-step3').hide();
            $('#card-quantity').val(1);
            $('#allowance-section').hide();
            $('#prepare-cards-btn').prop('disabled', false);
        },
        
        /**
         * Reset card table to initial state
         */
        		resetCardTable: function() {
			$('#gift-cards-tbody').html(`
				<tr class="gift-card-row">
					<td class="card-number">1</td>
					<td>
						<input type="number" name="card_id[]" placeholder="e.g., 102123456" class="regular-text card-id-input" autocomplete="off" />
						<div class="validation-error card-id-error" style="display: none;"></div>
					</td>
					<td>
						<input type="text" name="card_secret[]" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" class="regular-text card-secret-input" maxlength="23" autocomplete="off" />
						<div class="validation-error card-secret-error" style="display: none;"></div>
					</td>
					<td class="validation-col" data-validation="new">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="active">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="network">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="amount">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="token">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="secret">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td>
						<button type="button" class="button button-secondary validate-card-btn" style="margin-right: 5px;">
							Validate
						</button>
						<button type="button" class="button remove-card-btn" disabled>
							Remove
						</button>
					</td>
				</tr>
			`);
            
            // Update add cards button state and disabled message
            this.updateAddCardsButtonState();
        },
        
        /**
         * Load product settings
         */
        loadProductSettings: function(productId) {
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'univoucher_get_product_settings',
                    product_id: productId,
                    nonce: $('#get-settings-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        UniVoucherAddCards.displayProductSettings(response.data);
                        UniVoucherAddCards.showProductSettings();
                    } else {
                        univoucherNotify.error(response.data.message || 'Failed to load product settings.');
                    }
                },
                error: function() {
                    univoucherNotify.error('Failed to load product settings. Please try again.');
                }
            });
        },
        
        /**
         * Display product settings
         */
        displayProductSettings: function(settings) {
            // Store settings in hidden fields
            $('#selected-product-id').val(settings.product_id);
            $('#product-chain-id').val(settings.chain_id);
            $('#product-token-address').val(settings.token_address || '');
            $('#product-token-type').val(settings.token_type);
            $('#product-token-symbol').val(settings.token_symbol);
            $('#product-token-decimals').val(settings.token_decimals);
            $('#product-amount').val(settings.amount);
            
            // Build horizontal display HTML
            var html = this.buildProductSettingsBox(settings);
            
            $('#product-settings-display').html(html);
            
            // Show the next sections
            $('#product-details').show();
            $('#method-selection').show();
            
            // Clear method elements and reset form
            $('#method-elements').hide();
            $('.method-form').hide();
            
            // Update validation requirements
            this.updateValidationRequirements(settings);
            
            // Reset card inputs
            this.resetCardTable();
        },
        
        /**
         * Update validation requirements with product-specific info
         */
        updateValidationRequirements: function(settings) {
            // Update the dynamic requirement texts
            $('#requirement-network').text(settings.network_name || 'Chain ID: ' + settings.chain_id);
            $('#requirement-amount').text(settings.amount + ' ' + settings.token_symbol);
            $('#requirement-token').text(settings.token_symbol + ' (' + (settings.token_address ? settings.token_address.substring(0, 6) + '...' + settings.token_address.substring(settings.token_address.length - 4) : 'Unknown') + ')');
            
            // Show the requirements section
            $('#validation-requirements').show();
        },
        
        /**
         * Build product settings box HTML
         */
        buildProductSettingsBox: function(settings) {
            var html = '<div class="product-settings-box">';
            
            html += '<div class="setting-item">';
            html += '<div class="setting-label">Product</div>';
            html += '<div class="setting-value">';
            html += '<a href="' + 'post.php?post=' + settings.product_id + '&action=edit' + '" target="_blank" class="product-link">';
            html += settings.product_name;
            html += '<span class="dashicons dashicons-external"></span>';
            html += '</a>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="setting-item">';
            html += '<div class="setting-label">Current Stock</div>';
            var stockClass = '';
            var stockText = '';
            
            // Handle stock status with proper colors and text
            switch (settings.stock_status) {
                case 'instock':
                    stockClass = 'in-stock';
                    stockText = 'In Stock (' + (settings.stock_quantity || 0) + ')';
                    break;
                case 'outofstock':
                    stockClass = 'out-of-stock';
                    stockText = 'Out of Stock (' + (settings.stock_quantity || 0) + ')';
                    break;
                case 'onbackorder':
                    stockClass = 'on-backorder';
                    stockText = 'On Backorder (' + (settings.stock_quantity || 0) + ')';
                    break;
                default:
                    stockClass = 'unknown-stock';
                    stockText = 'Unknown (' + (settings.stock_quantity || 0) + ')';
                    break;
            }
            
            html += '<div class="setting-value stock-info ' + stockClass + '">' + stockText + '</div>';
            html += '</div>';
            
            html += '<div class="setting-item">';
            html += '<div class="setting-label">Network</div>';
            html += '<div class="setting-value">' + (settings.network_name || 'Chain ID: ' + settings.chain_id) + '</div>';
            html += '</div>';
            
            html += '<div class="setting-item">';
            html += '<div class="setting-label">Token</div>';
            html += '<div class="setting-value">' + settings.token_symbol + '</div>';
            html += '</div>';
            
            html += '<div class="setting-item">';
            html += '<div class="setting-label">Amount</div>';
            html += '<div class="setting-value">' + settings.amount + ' ' + settings.token_symbol + '</div>';
            html += '</div>';
            
            if (settings.token_address) {
                html += '<div class="setting-item">';
                html += '<div class="setting-label">Token Address</div>';
                html += '<div class="setting-value">' + settings.token_address.substring(0, 6) + '...' + settings.token_address.substring(settings.token_address.length - 4) + '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Show product settings
         */
        showProductSettings: function() {
            $('#product-details').show();
            $('#method-selection').show();
        },
        
        /**
         * Handle method selection
         */
        onMethodSelect: function(e) {
            e.preventDefault();
            
            var method = $(this).data('method');
            
            // Remove active class from all methods
            $('.method-box-inline').removeClass('active');
            
            // Add active class to selected method
            $(this).addClass('active');
            
            // Show method elements
            UniVoucherAddCards.showMethodElements(method);
        },
        
        /**
         * Show method elements
         */
        showMethodElements: function(method) {
            $('.method-form').hide();
            $('#current-method').val(method);
            
            if (method === 'univoucher') {
                $('#method-elements').show();
                $('#cards-form').show();
            } else if (method === 'internal-wallet') {
                UniVoucherInternalWallet.initInternalWallet();
            }
        },
        
        /**
         * Add a new card row
         */
        addCardRow: function(e) {
            e.preventDefault();
            
            var rowCount = $('#gift-cards-tbody tr').length;
            var rowNumber = rowCount + 1;
            
            			var newRow = `
				<tr class="gift-card-row">
					<td class="card-number">${rowNumber}</td>
					<td>
						<input type="number" name="card_id[]" placeholder="e.g., 102123456" class="regular-text card-id-input" autocomplete="off" />
						<div class="validation-error card-id-error" style="display: none;"></div>
					</td>
					<td>
						<input type="text" name="card_secret[]" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" class="regular-text card-secret-input" maxlength="23" autocomplete="off" />
						<div class="validation-error card-secret-error" style="display: none;"></div>
					</td>
					<td class="validation-col" data-validation="new">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="active">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="network">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="amount">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="token">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="secret">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td>
						<button type="button" class="button button-secondary validate-card-btn" style="margin-right: 5px;">
							Validate
						</button>
						<button type="button" class="button remove-card-btn">
							Remove
						</button>
					</td>
				</tr>
			`;
            
            $('#gift-cards-tbody').append(newRow);
            
            // Update remove button states
            this.updateRemoveButtonStates();
            
            // Update add cards button state
            this.updateAddCardsButtonState();

            // Attach events to the new row
            this.attachRowEvents($(newRow));
        },
        
        /**
         * Remove a card row
         */
        removeCardRow: function(e) {
            e.preventDefault();
            
            $(this).closest('tr').remove();
            
            // Update row numbers
            UniVoucherAddCards.updateRowNumbers();
            
            // Update remove button states
            UniVoucherAddCards.updateRemoveButtonStates();
            
            // Update add cards button state
            UniVoucherAddCards.updateAddCardsButtonState();
        },
        
        /**
         * Update row numbers
         */
        updateRowNumbers: function() {
            $('#gift-cards-tbody tr').each(function(index) {
                $(this).find('.card-number').text(index + 1);
            });
        },
        
        /**
         * Update remove button states
         */
        updateRemoveButtonStates: function() {
            var rowCount = $('#gift-cards-tbody tr').length;
            
            if (rowCount <= 1) {
                $('.remove-card-btn').prop('disabled', true);
            } else {
                $('.remove-card-btn').prop('disabled', false);
            }
        },
        
        /**
         * Handle card ID input - only clear validation
         */
        handleCardIdInput: function(e) {
            var $row = $(this).closest('tr');
            UniVoucherAddCards.clearValidation($row);
            UniVoucherAddCards.updateAddCardsButtonState();
        },

        /**
         * Clear validation for a row
         */
        clearValidation: function($row) {
            // Clear all validation icons for this row
            $row.find('.validation-icon').removeClass('dashicons-yes-alt dashicons-no-alt dashicons-update valid invalid loading').addClass('dashicons-minus pending');
            
            // Clear input states
            $row.find('.card-id-input, .card-secret-input').removeClass('error valid');
            $row.find('.validation-error').hide();
            
            // Clear stored validation data
            $row.removeData('validation-result');
        },
        
        /**
         * Handle card secret input with formatting
         */
        handleCardSecretInput: function(e) {
            var $input = $(this);
            var $row = $input.closest('tr');
            var cursorPos = $input[0].selectionStart;
            var oldValue = $input.val();
            var oldLength = oldValue.length;
            
            // Auto-format the input
            var formatted = UniVoucherAddCards.formatCardSecret(oldValue);
            if (formatted !== oldValue) {
                $input.val(formatted);
                
                // Adjust cursor position after formatting
                var newLength = formatted.length;
                var diff = newLength - oldLength;
                var newCursorPos = cursorPos + diff;
                
                // Ensure cursor position is valid
                if (newCursorPos < 0) newCursorPos = 0;
                if (newCursorPos > newLength) newCursorPos = newLength;
                
                // Set cursor position
                $input[0].setSelectionRange(newCursorPos, newCursorPos);
            }
            
            // Clear validation for this row
            UniVoucherAddCards.clearValidation($row);
            UniVoucherAddCards.updateAddCardsButtonState();
        },
        

        
        /**
         * Format card secret input with hyphens
         */
        formatCardSecret: function(value) {
            // Remove all non-letter characters and convert to uppercase
            var clean = value.replace(/[^A-Za-z]/g, '').toUpperCase();
            
            // Limit to 20 characters
            clean = clean.substring(0, 20);
            
            // Add hyphens every 5 characters
            var formatted = '';
            for (var i = 0; i < clean.length; i++) {
                if (i > 0 && i % 5 === 0) {
                    formatted += '-';
                }
                formatted += clean[i];
            }
            
            return formatted;
        },
        
        /**
         * Validate all cards
         */
        validateAllCards: function(e) {
            e.preventDefault();
            
            var $rows = $('#gift-cards-tbody tr');
            var cardsToValidate = [];
            
            $rows.each(function() {
                var $row = $(this);
                var cardId = $row.find('.card-id-input').val().trim();
                var cardSecret = $row.find('.card-secret-input').val().trim();
                
                if (cardId && cardSecret) {
                    cardsToValidate.push($row);
                }
            });
            
            if (cardsToValidate.length === 0) {
                univoucherNotify.error('Please enter at least one card before validating.');
                return;
            }
            
            // Validate each card
            $.each(cardsToValidate, function(index, $row) {
                UniVoucherAddCards.validateSingleCardRow($row);
            });
        },
        
        /**
         * Validate single card from button click
         */
        validateSingleCard: function(e) {
            e.preventDefault();
            
            var $row = $(this).closest('tr');
            UniVoucherAddCards.validateSingleCardRow($row);
        },
        
        /**
         * Validate single card row
         */
        validateSingleCardRow: function($row) {
            var cardId = $row.find('.card-id-input').val().trim();
            var cardSecret = $row.find('.card-secret-input').val().trim();
            var rowNumber = $row.find('.card-number').text();
            
            if (!cardId || !cardSecret) {
                univoucherNotify.error('Row ' + rowNumber + ': Please enter both Card ID and Card Secret before validating.');
                return;
            }
            
                         // Check format first
             if (cardId.length < 4) {
                 univoucherNotify.error('Row ' + rowNumber + ': Card ID must be at least 4 digits.');
                 return;
             }
             
             // Check card secret format (20 uppercase letters, with optional hyphens)
             var rawCardSecret = cardSecret.replace(/-/g, '');
             if (rawCardSecret.length !== 20 || !/^[A-Z]{20}$/.test(rawCardSecret)) {
                 univoucherNotify.error('Row ' + rowNumber + ': Card Secret must be exactly 20 uppercase letters.');
                 return;
             }
            
            // Set loading state
            $row.find('.validation-icon').removeClass('dashicons-yes-alt dashicons-no-alt dashicons-minus valid invalid pending').addClass('dashicons-update loading');
            $row.find('.validate-card-btn').addClass('validating').text('Validating...');
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'univoucher_validate_single_card',
                    nonce: $('#verification-nonce').val(),
                    card_id: cardId,
                    card_secret: cardSecret,
                    product_id: $('#selected-product-id').val(),
                    chain_id: $('#product-chain-id').val(),
                    token_address: $('#product-token-address').val(),
                    amount: $('#product-amount').val(),
                    token_decimals: $('#product-token-decimals').val()
                },
                success: function(response) {
                    $row.find('.validate-card-btn').removeClass('validating').text('Validate');
                    var rowNumber = $row.find('.card-number').text();
                    
                    if (response.success) {
                        UniVoucherAddCards.displayValidationResults($row, response.data);
                    } else {
                        // Set all icons to invalid
                        $row.find('.validation-icon').removeClass('dashicons-yes-alt dashicons-update dashicons-minus valid loading pending').addClass('dashicons-no-alt invalid');
                        univoucherNotify.error('Row ' + rowNumber + ': ' + (response.data.message || 'Validation failed.'));
                    }
                },
                error: function() {
                    $row.find('.validate-card-btn').removeClass('validating').text('Validate');
                    $row.find('.validation-icon').removeClass('dashicons-yes-alt dashicons-update dashicons-minus valid loading pending').addClass('dashicons-no-alt invalid');
                    var rowNumber = $row.find('.card-number').text();
                    univoucherNotify.error('Row ' + rowNumber + ': Failed to validate card. Please try again.');
                }
            });
        },
        
        /**
         * Display validation results for a single card
         */
        displayValidationResults: function($row, validationData) {
            var validations = validationData.validations;
            
            // Update each validation icon
            $.each(validations, function(type, isValid) {
                var $icon = $row.find('.validation-col[data-validation="' + type + '"] .validation-icon');
                $icon.removeClass('dashicons-update dashicons-minus loading pending');
                
                if (isValid) {
                    $icon.removeClass('dashicons-no-alt invalid').addClass('dashicons-yes-alt valid');
                } else {
                    $icon.removeClass('dashicons-yes-alt valid').addClass('dashicons-no-alt invalid');
                }
            });
            
            // Store validation data for form submission
            $row.data('validation-result', validationData);
            
            // Update add cards button state
            this.updateAddCardsButtonState();
        },
        
        /**
         * Update the Add Cards to Inventory button state
         */
        updateAddCardsButtonState: function() {
            var $button = $('#add-cards-btn');
            var $disabledMessage = $('#add-cards-disabled-message');
            var allValid = true;
            var hasCards = false;
            var validatedCards = 0;
            var totalCardsWithData = 0;
            
            $('#gift-cards-tbody tr').each(function() {
                var $row = $(this);
                var cardId = $row.find('.card-id-input').val().trim();
                var cardSecret = $row.find('.card-secret-input').val().trim();
                
                if (cardId && cardSecret) {
                    hasCards = true;
                    totalCardsWithData++;
                    var validationResult = $row.data('validation-result');
                    
                    if (validationResult && validationResult.all_valid) {
                        validatedCards++;
                    } else {
                        allValid = false;
                    }
                }
            });
            
            if (hasCards && allValid) {
                $button.prop('disabled', false);
                $disabledMessage.removeClass('hidden success');
                $disabledMessage.addClass('success');
                $('.cards-action-controls').slideDown(200);
                
                // Show success message
                var messageText = 'All ' + totalCardsWithData + ' cards validated successfully - ready to add to inventory';
                var iconClass = 'dashicons-yes-alt';
                
                $disabledMessage.find('.dashicons').removeClass().addClass('dashicons ' + iconClass);
                $disabledMessage.contents().filter(function() {
                    return this.nodeType === 3; // Text nodes
                }).remove();
                $disabledMessage.append(' ' + messageText);
            } else {
                $button.prop('disabled', true);
                $disabledMessage.removeClass('hidden success');
                $('.cards-action-controls').slideDown(200);
                
                // Update message text based on state
                var messageText = '';
                var iconClass = 'dashicons-warning';
                
                if (!hasCards) {
                    messageText = 'Upload a CSV file or enter Card ID and Card Secret for at least one gift card to begin';
                    iconClass = 'dashicons-edit';
                } else {
                    messageText = validatedCards + ' of ' + totalCardsWithData + ' cards validated - validate all cards to continue';
                    iconClass = 'dashicons-clock';
                }
                
                $disabledMessage.find('.dashicons').removeClass().addClass('dashicons ' + iconClass);
                $disabledMessage.contents().filter(function() {
                    return this.nodeType === 3; // Text nodes
                }).remove();
                $disabledMessage.append(' ' + messageText);
            }
        },
        
        /**
         * Add cards to inventory
         */
        addCards: function(e) {
            e.preventDefault();
            
            var cardIds = [];
            var cardSecrets = [];
            var creationDates = [];
            var allValid = true;
            
            $('#gift-cards-tbody tr').each(function() {
                var $row = $(this);
                var cardId = $row.find('.card-id-input').val().trim();
                var cardSecret = $row.find('.card-secret-input').val().trim();
                
                if (cardId && cardSecret) {
                    var validationResult = $row.data('validation-result');
                    
                    if (!validationResult || !validationResult.all_valid) {
                        allValid = false;
                        return false;
                    }
                    
                    cardIds.push(cardId);
                    cardSecrets.push(cardSecret);
                    
                    // Extract creation date from validation result
                    var creationDate = null;
                    if (validationResult.api_data && validationResult.api_data.createdAt) {
                        // Convert UTC timestamp to MySQL format
                        var date = new Date(validationResult.api_data.createdAt);
                        creationDate = date.getUTCFullYear() + '-' + 
                                     String(date.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                                     String(date.getUTCDate()).padStart(2, '0') + ' ' + 
                                     String(date.getUTCHours()).padStart(2, '0') + ':' + 
                                     String(date.getUTCMinutes()).padStart(2, '0') + ':' + 
                                     String(date.getUTCSeconds()).padStart(2, '0');
                    }
                    creationDates.push(creationDate);
                }
            });
            
            if (cardIds.length === 0) {
                univoucherNotify.error('Please enter at least one card.');
                return;
            }
            
            if (!allValid) {
                univoucherNotify.error('Please validate all cards before adding them to inventory.');
                return;
            }
            
            // Show loading
            $('#add-cards-btn').prop('disabled', true).text('Adding Cards...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'univoucher_add_cards',
                    nonce: $('#verification-nonce').val(),
                    product_id: $('#selected-product-id').val(),
                    card_ids: cardIds,
                    card_secrets: cardSecrets,
                    creation_dates: creationDates,
                    chain_id: $('#product-chain-id').val(),
                    token_address: $('#product-token-address').val(),
                    token_symbol: $('#product-token-symbol').val(),
                    token_type: $('#product-token-type').val(),
                    token_decimals: $('#product-token-decimals').val(),
                    amount: $('#product-amount').val()
                },
                success: function(response) {
                    $('#add-cards-btn').prop('disabled', false).text('Add Cards to Inventory');
                    
                    if (response.success) {
                        univoucherNotify.success(response.data.message);
                        
                        // Show warning for any errors that occurred
                        if (response.data.errors.length > 0) {
                            var errorList = response.data.errors.join('\n• ');
                            univoucherNotify.warning('Some errors occurred:\n• ' + errorList);
                        }
                        
                        if (response.data.success_count > 0) {
                            // Disable button and show redirecting message
                            $('#add-cards-btn').prop('disabled', true).text('Redirecting...');
                            
                            // Redirect to inventory page after success
                            setTimeout(function() {
                                window.location.href = 'admin.php?page=univoucher-inventory';
                            }, 2000);
                        }
                    } else {
                        univoucherNotify.error(response.data.message || 'Failed to add cards.');
                    }
                },
                error: function() {
                    $('#add-cards-btn').prop('disabled', false).text('Add Cards to Inventory');
                    univoucherNotify.error('Failed to add cards. Please try again.');
                }
            });
        },
        


		/**
		 * Attach event listeners to card row inputs.
		 * Note: Most events are handled by global delegated handlers for efficiency.
		 *
		 * @param {jQuery} $row The card row element.
		 */
		attachRowEvents: function($row) {
			// All event handlers are now managed by global delegated events
			// This prevents duplicate event execution and improves performance
		},
		
		/**
		 * Trigger CSV file upload
		 */
		triggerCsvUpload: function(e) {
			e.preventDefault();
			$('#csv-file').click();
		},
		
		/**
		 * Handle CSV file selection and upload
		 */
		handleCsvUpload: function(e) {
			var fileInput = e.target;
			
			if (!fileInput.files.length) {
				return;
			}
			
			var formData = new FormData();
			formData.append('action', 'univoucher_process_csv');
			formData.append('nonce', $('#csv-upload-nonce').val());
			formData.append('csv_file', fileInput.files[0]);
			
			// Show loading
			$('#upload-csv-btn').prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite; vertical-align: middle; margin-right: 5px;"></span>Processing...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					$('#upload-csv-btn').prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-right: 5px;"></span>Upload CSV');
					
					if (response.success) {
						UniVoucherAddCards.populateCardsFromCsv(response.data.cards);
						univoucherNotify.success('CSV processed successfully. ' + response.data.count + ' cards loaded.');
					} else {
						univoucherNotify.error(response.data.message || 'Failed to process CSV.');
					}
					
					// Clear file input
					$('#csv-file').val('');
				},
				error: function() {
					$('#upload-csv-btn').prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-right: 5px;"></span>Upload CSV');
					univoucherNotify.error('Failed to upload CSV. Please try again.');
					// Clear file input
					$('#csv-file').val('');
				}
			});
		},
		
		/**
		 * Populate cards form with CSV data
		 */
		populateCardsFromCsv: function(cards) {
			// Clear existing cards
			$('#gift-cards-tbody').empty();
			
			// Add cards from CSV
			for (var i = 0; i < cards.length; i++) {
				var card = cards[i];
				var rowNumber = i + 1;
				
				var newRow = `
					<tr class="gift-card-row">
						<td class="card-number">${rowNumber}</td>
						<td>
							<input type="number" name="card_id[]" value="${card.card_id}" placeholder="e.g., 102123456" class="regular-text card-id-input" autocomplete="off" />
							<div class="validation-error card-id-error" style="display: none;"></div>
						</td>
						<td>
							<input type="text" name="card_secret[]" value="${card.card_secret}" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" class="regular-text card-secret-input" maxlength="23" autocomplete="off" />
							<div class="validation-error card-secret-error" style="display: none;"></div>
						</td>
											<td class="validation-col" data-validation="new">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="active">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="network">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="amount">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="token">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
					<td class="validation-col" data-validation="secret">
						<span class="validation-icon dashicons dashicons-minus pending"></span>
					</td>
						<td>
							<button type="button" class="button button-secondary validate-card-btn" style="margin-right: 5px;">
								Validate
							</button>
							<button type="button" class="button remove-card-btn">
								Remove
							</button>
						</td>
					</tr>
				`;
				
				$('#gift-cards-tbody').append(newRow);
			}
			
			// Show the cards form
			$('#cards-form').show();
			
			// Update remove button states
			this.updateRemoveButtonStates();
			
			// Update add cards button state
			this.updateAddCardsButtonState();
			
			// Attach events to new rows
			$('#gift-cards-tbody tr').each(function() {
				UniVoucherAddCards.attachRowEvents($(this));
			});
		}
    };

    // Make it globally accessible
    window.UniVoucherAddCards = UniVoucherAddCards;

})(jQuery); 