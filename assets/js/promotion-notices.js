/**
 * UniVoucher Promotion Notices
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle notice dismiss
		$('.univoucher-promotion-notice-dismiss').on('click', function(e) {
			e.preventDefault();

			var $notice = $(this).closest('.univoucher-promotion-notice');
			var cardId = $notice.data('card-id');
			var noticeType = $notice.data('notice-type');

			// Fade out notice
			$notice.fadeOut(300, function() {
				$(this).remove();
			});

			// Send AJAX request to dismiss notice
			$.ajax({
				url: univoucherNotices.ajaxUrl,
				type: 'POST',
				data: {
					action: 'univoucher_dismiss_notice',
					nonce: univoucherNotices.nonce,
					card_id: cardId,
					type: noticeType
				},
				success: function(response) {
					// Notice dismissed successfully
				},
				error: function(xhr, status, error) {
					console.error('Error dismissing notice:', error);
				}
			});
		});
	});

})(jQuery);
