jQuery(document).ready(function($) {
	// Toggle visibility of notice text options based on checkbox state
	$('#univoucher_wc_show_unassigned_notice').on('change', function() {
		if ($(this).is(':checked')) {
			$('#univoucher-notice-text-options').show();
		} else {
			$('#univoucher-notice-text-options').hide();
		}
	});

	// Toggle visibility of order limit options based on checkbox state
	$('#univoucher_wc_on_demand_order_limit').on('change', function() {
		if ($(this).is(':checked')) {
			$('#univoucher-order-limit-options').show();
		} else {
			$('#univoucher-order-limit-options').hide();
		}
	});
});