/**
 * UniVoucher Order Assignment Checker JavaScript
 * Handles auto-refresh functionality for order assignment status
 */

jQuery(document).ready(function($) {
    function checkOrderStatus() {
        $.ajax({
            url: univoucher_assignment_checker_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'univoucher_check_order_assignment',
                order_id: univoucher_assignment_checker_vars.order_id,
                nonce: univoucher_assignment_checker_vars.check_nonce
            },
            success: function(response) {
                if (response.success && response.data.fully_assigned) {
                    location.reload();
                }
            },
            error: function() {
                // Continue checking even if there's an error
            }
        });
    }

    // Only run if the unassigned notice is present
    if ($('#univoucher-unassigned-notice').length) {
        // Check every 10 seconds
        setInterval(checkOrderStatus, 10000);
        
        // Initial check
        checkOrderStatus();
    }
});