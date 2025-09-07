/**
 * UniVoucher Order Manager JavaScript
 */

jQuery(document).ready(function($) {
    // Assign cards functionality
    $('.assign-product-cards-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.find('.spinner');
        var productId = $btn.data('product-id');
        var $result = $('#assign-result-' + productId);
        var orderId = $btn.data('order-id');
        var missing = $btn.data('missing');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.empty();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'univoucher_assign_product_cards',
                order_id: orderId,
                product_id: productId,
                missing_quantity: missing,
                nonce: univoucher_order_manager_vars.assign_nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div style="color: #28a745; padding: 8px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Success:</strong> ' + response.data.message + '</div>');
                    // Reload page after 2 seconds to show updated cards
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> ' + response.data.message + '</div>');
                }
            },
            error: function() {
                $result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> Failed to assign cards. Please try again.</div>');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Unassign card functionality
    $('.unassign-card-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.find('.spinner');
        var cardId = $btn.data('card-id');
        var orderId = $btn.data('order-id');
        var productId = $btn.data('product-id');
        var $result = $('#assign-result-' + productId);
        var $row = $btn.closest('tr');
        var deliveryStatus = $row.find('td:nth-child(4)').text().trim().toLowerCase();
        
        // Different confirmation messages based on delivery status
        var confirmMessage;
        if (deliveryStatus.includes('delivered') && !deliveryStatus.includes('never')) {
            confirmMessage = univoucher_order_manager_vars.confirm_delivered_unassign;
        } else {
            confirmMessage = univoucher_order_manager_vars.confirm_unassign;
        }
        
        // Confirm action
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.empty();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'univoucher_unassign_card',
                card_id: cardId,
                order_id: orderId,
                product_id: productId,
                nonce: univoucher_order_manager_vars.unassign_nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div style="color: #28a745; padding: 8px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Success:</strong> ' + response.data.message + '</div>');
                    // Reload page after 2 seconds to show updated cards
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> ' + response.data.message + '</div>');
                }
            },
            error: function() {
                $result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> Failed to unassign card. Please try again.</div>');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});