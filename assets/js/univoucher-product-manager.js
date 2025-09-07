jQuery(document).ready(function($) {
	// Make stock fields disabled for UniVoucher products but preserve values with hidden fields
	$('#_manage_stock, #_stock, #_stock_status').prop('disabled', true);
	
	// Add hidden fields to preserve values during form submission
	$('#_stock').after('<input type="hidden" name="_stock" value="' + $('#_stock').val() + '">');
	$('#_manage_stock').after('<input type="hidden" name="_manage_stock" value="yes">');
	$('#_stock_status').after('<input type="hidden" name="_stock_status" value="' + $('#_stock_status').val() + '">');
	
	// Add visual indication
	$('#_stock').css({
		'background-color': '#f9f9f9',
		'color': '#666'
	}).attr('title', univoucherProductManager.autoManagedText);
	
	// Add notice to stock field
	$('#_stock').after('<span style="color: #666; font-size: 11px; margin-left: 8px;">(' + univoucherProductManager.autoManagedLabel + ')</span>');
});