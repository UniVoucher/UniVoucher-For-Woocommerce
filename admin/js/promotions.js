/**
 * UniVoucher Promotions Admin JavaScript
 */

(function($) {
    'use strict';

    let ruleIndex = 0;

    $(document).ready(function() {
        initPromotions();
    });

    /**
     * Initialize promotions functionality.
     */
    function initPromotions() {
        // Initialize rule index based on existing rules
        ruleIndex = $('.univoucher-rule-row').length;

        // Toggle promotion status
        $('.univoucher-toggle-promotion').on('change', function() {
            togglePromotionStatus($(this));
        });

        // Token type change
        $('#token_type').on('change', function() {
            updateTokenTypeFields($(this).val());
        });

        // Get token info button
        $('#get-token-info').on('click', function() {
            getTokenInfo();
        });

        // Network change - update token symbol for native tokens
        $('#chain_id').on('change', function() {
            updateNativeTokenSymbol();
        });

        // Add rule button
        $('#add-rule').on('click', function() {
            addRule();
        });

        // Rule type and condition changes
        $(document).on('change', '.rule-type', function() {
            updateRuleConditionOptions($(this));
        });

        $(document).on('change', '.rule-condition', function() {
            updateRuleValueField($(this));
        });

        // Operator change for total value rules
        $(document).on('change', '.rule-operator', function() {
            updateOperatorFields($(this));
        });

        // Remove rule button
        $(document).on('click', '.remove-rule', function() {
            removeRule($(this));
        });

        // Initialize product select2
        initProductSelect();

        // Initialize rule condition options for existing rules
        $('.rule-type').each(function() {
            updateRuleConditionOptions($(this));
        });

        // Initialize rule value fields state
        $('.rule-condition').each(function() {
            updateRuleValueField($(this));
        });

        // Initialize operator fields state for existing rules
        $('.rule-operator').each(function() {
            updateOperatorFields($(this));
        });

        // Show/hide order email template based on checkbox
        $('#include_in_order_email').on('change', function() {
            if ($(this).is(':checked')) {
                $('#order-email-template-row').show();

                // Resize TinyMCE editor when it becomes visible
                if (typeof tinymce !== 'undefined') {
                    var editor = tinymce.get('order_email_template');
                    if (editor) {
                        // Set a larger height for the editor
                        setTimeout(function() {
                            editor.theme.resizeTo(null, 300);
                        }, 100);
                    }
                }
            } else {
                $('#order-email-template-row').hide();
            }
        });

        // Show/hide separate email fields based on checkbox
        $('#send_separate_email').on('change', function() {
            if ($(this).is(':checked')) {
                $('#email-subject-row, #email-template-row').show();

                // Resize TinyMCE editor when it becomes visible
                if (typeof tinymce !== 'undefined') {
                    var editor = tinymce.get('email_template');
                    if (editor) {
                        // Set a larger height for the editor
                        setTimeout(function() {
                            editor.theme.resizeTo(null, 600);
                        }, 100);
                    }
                }
            } else {
                $('#email-subject-row, #email-template-row').hide();
            }
        });

        // Show/hide account notice message based on checkbox
        $('#show_account_notice').on('change', function() {
            if ($(this).is(':checked')) {
                $('#account-notice-message-row').show();
            } else {
                $('#account-notice-message-row').hide();
            }
        });

        // Show/hide order notice fields based on checkbox
        $('#show_order_notice').on('change', function() {
            if ($(this).is(':checked')) {
                $('#order-notice-title-row, #order-notice-message-row').show();
            } else {
                $('#order-notice-title-row, #order-notice-message-row').hide();
            }
        });

        // Show/hide shortcode notice fields based on checkbox
        $('#show_shortcode_notice').on('change', function() {
            if ($(this).is(':checked')) {
                $('#shortcode-notice-message-row').show();
            } else {
                $('#shortcode-notice-message-row').hide();
            }
        });

        // Form validation before submit
        $('#univoucher-promotion-form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });

        // Initial state
        updateTokenTypeFields($('#token_type').val());
        updateNativeTokenSymbol();
    }

    /**
     * Toggle promotion active status.
     */
    function togglePromotionStatus($toggle) {
        const promotionId = $toggle.data('promotion-id');
        const isActive = $toggle.is(':checked') ? 1 : 0;

        $.ajax({
            url: univoucher_promotions_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'univoucher_toggle_promotion',
                nonce: univoucher_promotions_ajax.nonce,
                promotion_id: promotionId,
                is_active: isActive
            },
            success: function(response) {
                if (!response.success) {
                    alert(response.data.message);
                    $toggle.prop('checked', !isActive);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $toggle.prop('checked', !isActive);
            }
        });
    }

    /**
     * Update token type fields visibility.
     */
    function updateTokenTypeFields(tokenType) {
        if (tokenType === 'erc20') {
            $('.token-address-row').show();
            $('#token_symbol').prop('readonly', true);
        } else {
            $('.token-address-row').hide();
            updateNativeTokenSymbol();
            $('#token_symbol').prop('readonly', true);
        }
    }

    /**
     * Update native token symbol based on selected network.
     */
    function updateNativeTokenSymbol() {
        const tokenType = $('#token_type').val();
        if (tokenType !== 'native') {
            return;
        }

        const chainId = $('#chain_id').val();
        const networks = univoucher_promotions_ajax.networks;

        if (networks[chainId]) {
            $('#token_symbol').val(networks[chainId].symbol);
            $('#token_symbol_display').val(networks[chainId].symbol);
            $('#token_decimals').val(networks[chainId].decimals);
        }
    }

    /**
     * Get token info from blockchain.
     */
    function getTokenInfo() {
        const tokenAddress = $('#token_address').val();
        const chainId = $('#chain_id').val();

        if (!tokenAddress) {
            showTokenMessage('Please enter a token address.', 'error');
            return;
        }

        $('#token-spinner').addClass('is-active');
        $('#get-token-info').prop('disabled', true);
        showTokenMessage('Fetching token information...', 'info');

        $.ajax({
            url: univoucher_promotions_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'univoucher_promotions_get_token_info',
                nonce: univoucher_promotions_ajax.nonce,
                token_address: tokenAddress,
                network: chainId
            },
            success: function(response) {
                $('#token-spinner').removeClass('is-active');
                $('#get-token-info').prop('disabled', false);

                if (response.success) {
                    $('#token_symbol').val(response.data.symbol);
                    $('#token_symbol_display').val(response.data.symbol);
                    $('#token_decimals').val(response.data.decimals);
                    showTokenMessage('Token info loaded: ' + response.data.name + ' (' + response.data.symbol + ')', 'success');
                } else {
                    showTokenMessage(response.data.message, 'error');
                }
            },
            error: function() {
                $('#token-spinner').removeClass('is-active');
                $('#get-token-info').prop('disabled', false);
                showTokenMessage('An error occurred. Please try again.', 'error');
            }
        });
    }

    /**
     * Show token info message.
     */
    function showTokenMessage(message, type) {
        const $message = $('#token-info-message');
        $message.removeClass('notice-success notice-error notice-info');

        if (type === 'success') {
            $message.addClass('notice notice-success');
        } else if (type === 'error') {
            $message.addClass('notice notice-error');
        } else {
            $message.addClass('notice notice-info');
        }

        $message.html('<p>' + message + '</p>').show();
    }

    /**
     * Add a new rule row.
     */
    function addRule() {
        const template = $('#rule-row-template').html();
        const newRule = template.replace(/{{INDEX}}/g, ruleIndex);

        // Add AND separator before new rule if this is not the first rule
        if ($('.univoucher-rule-row').length > 0) {
            $('#promotion-rules-container').append('<div class="rule-separator"><span class="rule-and-label">AND</span></div>');
        }

        $('#promotion-rules-container').append(newRule);

        // Initialize select2 for the new product and category selects
        const $newProductSelect = $('.univoucher-rule-row[data-index="' + ruleIndex + '"] .rule-value-product');
        const $newCategorySelect = $('.univoucher-rule-row[data-index="' + ruleIndex + '"] .rule-value-category');

        initProductSelectForElement($newProductSelect);
        initCategorySelectForElement($newCategorySelect);

        ruleIndex++;
    }

    /**
     * Remove a rule row.
     */
    function removeRule($button) {
        const $ruleRow = $button.closest('.univoucher-rule-row');

        // Prevent removing the last rule
        if ($('.univoucher-rule-row').length <= 1) {
            alert('You must have at least one rule.');
            return;
        }

        // Check if there's an AND separator before this rule
        const $prevSeparator = $ruleRow.prev('.rule-separator');
        if ($prevSeparator.length) {
            $prevSeparator.remove();
        } else {
            // If no separator before, remove the one after (if it's the first rule)
            const $nextSeparator = $ruleRow.next('.rule-separator');
            if ($nextSeparator.length) {
                $nextSeparator.remove();
            }
        }

        $ruleRow.remove();
    }

    /**
     * Update rule condition options based on rule type.
     */
    function updateRuleConditionOptions($select) {
        const ruleType = $select.val();
        const $ruleRow = $select.closest('.univoucher-rule-row');
        const $conditionSelect = $ruleRow.find('.rule-condition');
        const currentCondition = $conditionSelect.val();

        // Show/hide options based on rule type
        $conditionSelect.find('option').each(function() {
            const $option = $(this);
            const optionType = $option.data('type');

            if (optionType === ruleType) {
                $option.show();
            } else {
                $option.hide();
            }
        });

        // Only change the selection if the current condition doesn't match the rule type
        const $currentOption = $conditionSelect.find('option[value="' + currentCondition + '"]');
        const currentOptionType = $currentOption.data('type');

        if (currentOptionType !== ruleType) {
            // Select the first visible option
            const $firstVisible = $conditionSelect.find('option[data-type="' + ruleType + '"]').first();
            if ($firstVisible.length) {
                $conditionSelect.val($firstVisible.val()).trigger('change');
            }
        }
    }

    /**
     * Update rule value field based on condition.
     */
    function updateRuleValueField($select) {
        const condition = $select.val();
        const $ruleRow = $select.closest('.univoucher-rule-row');
        const $valueProduct = $ruleRow.find('.value-product');
        const $valueCategory = $ruleRow.find('.value-category');
        const $valueAmount = $ruleRow.find('.value-amount');
        const $valueRegistrationDate = $ruleRow.find('.value-registration-date');
        const $valueNeverReceived = $ruleRow.find('.value-never-received');

        // Hide all value fields first
        $valueProduct.hide().find('select, input').prop('disabled', true);
        $valueCategory.hide().find('select, input').prop('disabled', true);
        $valueAmount.hide().find('select, input').prop('disabled', true);
        $valueRegistrationDate.hide().find('select, input').prop('disabled', true);
        $valueNeverReceived.hide().find('input').prop('disabled', true);

        // Show the appropriate value field
        if (condition === 'includes_product') {
            $valueProduct.show();
            $valueProduct.find('select, input').prop('disabled', false);
        } else if (condition === 'includes_category') {
            $valueCategory.show();
            $valueCategory.find('select, input').prop('disabled', false);
        } else if (condition === 'total_value') {
            $valueAmount.show();
            $valueAmount.find('select, input').prop('disabled', false);
        } else if (condition === 'registration_date') {
            $valueRegistrationDate.show();
            $valueRegistrationDate.find('select, input').prop('disabled', false);
        } else if (condition === 'never_received_promotion') {
            $valueNeverReceived.show();
            $valueNeverReceived.find('input').prop('disabled', false);
        }
    }

    /**
     * Update operator fields based on selected operator.
     */
    function updateOperatorFields($select) {
        const operator = $select.val();
        const $ruleRow = $select.closest('.univoucher-rule-row');
        const $singleValue = $ruleRow.find('.rule-value-single');
        const $rangeValue = $ruleRow.find('.rule-value-range');

        if (operator === 'between') {
            $singleValue.hide();
            // Remove name attribute to prevent submission
            const $singleInput = $singleValue.find('input');
            $singleInput.data('original-name', $singleInput.attr('name'));
            $singleInput.removeAttr('name');

            $rangeValue.show();
            // Restore name attributes
            $rangeValue.find('input').each(function() {
                const $input = $(this);
                if ($input.data('original-name')) {
                    $input.attr('name', $input.data('original-name'));
                }
            });
        } else {
            $singleValue.show();
            // Restore name attribute
            const $singleInput = $singleValue.find('input');
            if ($singleInput.data('original-name')) {
                $singleInput.attr('name', $singleInput.data('original-name'));
            }

            $rangeValue.hide();
            // Remove name attributes to prevent submission
            $rangeValue.find('input').each(function() {
                const $input = $(this);
                $input.data('original-name', $input.attr('name'));
                $input.removeAttr('name');
            });
        }
    }

    /**
     * Initialize Select2 for product and category selection.
     */
    function initProductSelect() {
        $('.rule-value-product').each(function() {
            initProductSelectForElement($(this));
        });
        $('.rule-value-category').each(function() {
            initCategorySelectForElement($(this));
        });
    }

    /**
     * Initialize Select2 for a specific product element.
     */
    function initProductSelectForElement($element) {
        $element.select2({
            ajax: {
                url: univoucher_promotions_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'univoucher_search_products',
                        term: params.term,
                        security: univoucher_promotions_ajax.nonce
                    };
                },
                processResults: function(data) {
                    const results = [];
                    if (data) {
                        $.each(data, function(id, text) {
                            results.push({
                                id: id,
                                text: text
                            });
                        });
                    }
                    return {
                        results: results
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'Search for a product...',
            allowClear: true
        });
    }

    /**
     * Initialize Select2 for a specific category element.
     */
    function initCategorySelectForElement($element) {
        $element.select2({
            ajax: {
                url: univoucher_promotions_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'univoucher_search_categories',
                        term: params.term,
                        security: univoucher_promotions_ajax.nonce
                    };
                },
                processResults: function(data) {
                    const results = [];
                    if (data) {
                        $.each(data, function(id, text) {
                            results.push({
                                id: id,
                                text: text
                            });
                        });
                    }
                    return {
                        results: results
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'Search for a category...',
            allowClear: true
        });
    }

    /**
     * Validate form before submission.
     */
    function validateForm() {
        let isValid = true;
        const errors = [];

        // Validate title
        if (!$('#title').val().trim()) {
            errors.push('Title is required.');
            isValid = false;
        }

        // Validate token symbol
        if (!$('#token_symbol').val().trim()) {
            errors.push('Token Symbol is required. Please get token info for ERC-20 tokens.');
            isValid = false;
        }

        // Validate card amount
        const cardAmount = parseFloat($('#card_amount').val());
        if (!cardAmount || cardAmount <= 0) {
            errors.push('Card Amount must be greater than 0.');
            isValid = false;
        }

        // Validate token address for ERC-20
        const tokenType = $('#token_type').val();
        if (tokenType === 'erc20') {
            const tokenAddress = $('#token_address').val();
            if (!tokenAddress || !tokenAddress.match(/^0x[a-fA-F0-9]{40}$/)) {
                errors.push('Valid Token Address is required for ERC-20 tokens.');
                isValid = false;
            }
        }

        // Validate rules
        let hasValidRule = false;
        $('.univoucher-rule-row').each(function() {
            const condition = $(this).find('.rule-condition').val();

            if (condition === 'includes_product') {
                const product = $(this).find('.rule-value-product').val();
                if (product) {
                    hasValidRule = true;
                }
            } else if (condition === 'includes_category') {
                const category = $(this).find('.rule-value-category').val();
                if (category) {
                    hasValidRule = true;
                }
            } else if (condition === 'total_value') {
                const operator = $(this).find('.rule-operator').val();
                if (operator === 'between') {
                    const minValue = $(this).find('.rule-value-amount-min').val();
                    const maxValue = $(this).find('.rule-value-amount-max').val();
                    const minAmount = parseFloat(minValue);
                    const maxAmount = parseFloat(maxValue);

                    if (!minValue || !maxValue || isNaN(minAmount) || isNaN(maxAmount)) {
                        errors.push('Order total value is required for "between" operator.');
                        isValid = false;
                    } else if (minAmount <= 0 || maxAmount <= 0) {
                        errors.push('Order total values must be greater than 0.');
                        isValid = false;
                    } else if (maxAmount <= minAmount) {
                        errors.push('Maximum order total must be greater than minimum.');
                        isValid = false;
                    } else {
                        hasValidRule = true;
                    }
                } else {
                    const amountValue = $(this).find('.rule-value-amount').val();
                    const amount = parseFloat(amountValue);

                    if (!amountValue || isNaN(amount)) {
                        errors.push('Order total value is required.');
                        isValid = false;
                    } else if (amount <= 0) {
                        errors.push('Order total value must be greater than 0.');
                        isValid = false;
                    } else {
                        hasValidRule = true;
                    }
                }
            } else if (condition === 'registration_date') {
                const dateValue = $(this).find('.rule-value-date').val();
                if (dateValue) {
                    hasValidRule = true;
                }
            } else if (condition === 'never_received_promotion') {
                hasValidRule = true;
            }
        });

        if (!hasValidRule) {
            errors.push('At least one rule with a valid value is required.');
            isValid = false;
        }

        if (!isValid) {
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
        }

        return isValid;
    }

})(jQuery);
