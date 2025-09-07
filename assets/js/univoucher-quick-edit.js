jQuery(document).ready(function($) {
	$(document).on('click', '.editinline', function() {
		var postId = parseInt($(this).closest('tr').attr('id').replace('post-', ''));
		
		if (univoucherQuickEdit.univoucherIds.includes(postId)) {
			setTimeout(function() {
				var $stockField = $('.inline-edit-product').find('input[name="_stock"]');
				var $manageStockCheckbox = $('.inline-edit-product').find('input[name="_manage_stock"]');
				var $stockStatusSelect = $('.inline-edit-product').find('select[name="_stock_status"]');
				
				// Only modify if not already processed
				if (!$stockField.prop('disabled')) {
					// Force enable manage stock and disable the checkbox
					$manageStockCheckbox.prop('checked', true).prop('disabled', true)
						.css('opacity', '0.5')
						.after('<input type="hidden" name="_manage_stock" value="1">');
					
					// Disable and style the stock quantity field
					$stockField.prop('disabled', true)
						.css('background-color', '#f9f9f9')
						.after('<input type="hidden" name="_stock" value="' + $stockField.val() + '">')
						.after('<br><small style="color:#666;">' + univoucherQuickEdit.autoManagedLabel + '</small>');
					
					// Disable stock status dropdown if it exists
					if ($stockStatusSelect.length) {
						$stockStatusSelect.prop('disabled', true)
							.css('opacity', '0.5')
							.after('<input type="hidden" name="_stock_status" value="' + $stockStatusSelect.val() + '">');
					}
					
					// Add a visual indicator
					$manageStockCheckbox.closest('label').after('<br><small style="color:#0073aa;">' + univoucherQuickEdit.autoManagedLabel + '</small>');
				}
			}, 200);
		}
	});
});