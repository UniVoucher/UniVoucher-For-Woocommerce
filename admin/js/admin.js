/**
 * UniVoucher For WooCommerce - Admin Scripts
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Initialize admin functionality
		UniVoucherAdmin.init();
		
	});

	/**
	 * UniVoucher Admin object
	 */
	var UniVoucherAdmin = {
		
		/**
		 * Initialize admin functionality
		 */
		init: function() {
			this.initSettingsPage();
		},
		
		/**
		 * Initialize settings page functionality
		 */
		initSettingsPage: function() {
			// API key show/hide toggle
			$('#toggle-api-key').on('click', function() {
				var field = $('#univoucher_wc_alchemy_api_key');
				var button = $(this);
				
				if (field.length) {
					var isHidden = field.data('hidden') === true;
					
					if (isHidden) {
						field.css({
							'-webkit-text-security': 'none',
							'text-security': 'none'
						}).data('hidden', false);
						button.text('Hide');
					} else {
						field.css({
							'-webkit-text-security': 'disc',
							'text-security': 'disc'
						}).data('hidden', true);
						button.text('Show');
					}
				}
			});

			// Initialize the field as hidden
			$('#univoucher_wc_alchemy_api_key').css({
				'-webkit-text-security': 'disc',
				'text-security': 'disc'
			}).data('hidden', true);
			
			// Database key show/hide toggle
			$('#toggle-database-key').on('click', function() {
				var field = $('#database-key-display');
				var button = $(this);
				
				if (field.length && field.attr('type') === 'password') {
					field.attr('type', 'text');
					button.text('Hide Key');
				} else if (field.length) {
					field.attr('type', 'password');
					button.text('Show/Hide Key');
				}
			});

			// Database key copy functionality
			$('#copy-database-key').on('click', function() {
				var field = $('#database-key-display');
				var button = $(this);
				
				if (!field.length) {
					alert('Database key field not found.');
					return;
				}
				
				var keyValue = field.val();
				
				// Modern clipboard API first
				if (navigator.clipboard) {
					navigator.clipboard.writeText(keyValue).then(function() {
						button.text('Copied!');
						setTimeout(function() {
							button.text('Copy Key');
						}, 2000);
					}).catch(function() {
						// Fallback to old method
						fallbackCopy();
					});
				} else {
					fallbackCopy();
				}
				
				function fallbackCopy() {
					field.select();
					field[0].setSelectionRange(0, 99999);
					try {
						document.execCommand('copy');
						button.text('Copied!');
						setTimeout(function() {
							button.text('Copy Key');
						}, 2000);
					} catch (err) {
						alert('Failed to copy. Please select and copy manually.');
					}
				}
			});

			// Stock sync functionality
			this.initStockSync();

			// API test functionality
			this.initApiTest();
		},

		/**
		 * Initialize API test functionality
		 */
		initApiTest: function() {
			var self = this;

			// Show/hide test button based on API key input
			$('#univoucher_wc_alchemy_api_key').on('input', function() {
				var apiKey = $(this).val().trim();
				var statusDiv = $('#api-key-status');
				var testSection = $('#api-test-section');

				if (apiKey.length > 0) {
					statusDiv.hide();
					testSection.show();
				} else {
					statusDiv.show();
					testSection.hide();
				}
			});

			// Test API key button
			$('#test-api-key').on('click', function() {
				var button = $(this);
				var resultDiv = $('#api-test-result');
				var apiKey = $('#univoucher_wc_alchemy_api_key').val().trim();

				if (!apiKey) {
					resultDiv.removeClass('success').addClass('error')
						.css('background', '#f8d7da')
						.css('border', '1px solid #f5c6cb')
						.css('color', '#721c24')
						.css('padding', '10px')
						.css('border-radius', '4px')
						.html('<strong>Error:</strong> Please enter an API key first.')
						.show();
					return;
				}

				button.prop('disabled', true).text('Testing...');
				resultDiv.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'univoucher_test_api_key',
						api_key: apiKey,
						nonce: self.getStockSyncNonce()
					},
					success: function(response) {
						if (response.success) {
							resultDiv.removeClass('error').addClass('success')
								.css('background', '#d4edda')
								.css('border', '1px solid #c3e6cb')
								.css('color', '#155724')
								.css('padding', '10px')
								.css('border-radius', '4px')
								.html('<strong>Success:</strong> ' + response.data.message)
								.show();
						} else {
							resultDiv.removeClass('success').addClass('error')
								.css('background', '#f8d7da')
								.css('border', '1px solid #f5c6cb')
								.css('color', '#721c24')
								.css('padding', '10px')
								.css('border-radius', '4px')
								.html('<strong>Error:</strong> ' + response.data.message)
								.show();
						}
					},
					error: function() {
						resultDiv.removeClass('success').addClass('error')
							.css('background', '#f8d7da')
							.css('border', '1px solid #f5c6cb')
							.css('color', '#721c24')
							.css('padding', '10px')
							.css('border-radius', '4px')
							.html('<strong>Error:</strong> Failed to test API. Please try again.')
							.show();
					},
					complete: function() {
						button.prop('disabled', false).text('Test API Connection');
					}
				});
			});
		},

		/**
		 * Initialize stock sync functionality
		 */
		initStockSync: function() {
			var self = this;

			// Enable/disable single product sync button based on selection
			$('#univoucher-product-select').on('change', function() {
				var productId = $(this).val();
				$('#sync-single-product').prop('disabled', !productId);
			});

			// Single product sync
			$('#sync-single-product').on('click', function() {
				var button = $(this);
				var productId = $('#univoucher-product-select').val();
				var statusDiv = $('#single-sync-status');

				if (!productId) return;

				button.prop('disabled', true).text('Syncing...');
				statusDiv.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'univoucher_sync_single_product',
						product_id: productId,
						nonce: self.getStockSyncNonce()
					},
					success: function(response) {
						if (response.success) {
							statusDiv.removeClass('error').addClass('success')
								.css('background', '#d4edda')
								.css('border-color', '#c3e6cb')
								.css('color', '#155724')
								.html('<strong>Success:</strong> ' + response.data.message)
								.show();
						} else {
							statusDiv.removeClass('success').addClass('error')
								.css('background', '#f8d7da')
								.css('border-color', '#f5c6cb')
								.css('color', '#721c24')
								.html('<strong>Error:</strong> ' + response.data.message)
								.show();
						}
					},
					error: function() {
						statusDiv.removeClass('success').addClass('error')
							.css('background', '#f8d7da')
							.css('border-color', '#f5c6cb')
							.css('color', '#721c24')
							.html('<strong>Error:</strong> Failed to sync product. Please try again.')
							.show();
					},
					complete: function() {
						button.prop('disabled', false).text('Sync Selected Product');
					}
				});
			});

			// Bulk sync all products
			$('#sync-all-products').on('click', function() {
				var button = $(this);
				var progressDiv = $('#bulk-sync-progress');
				var progressBar = $('#progress-bar');
				var progressText = $('#progress-text');
				var resultsDiv = $('#sync-results');

				button.prop('disabled', true).text('Syncing...');
				progressDiv.show();
				resultsDiv.empty();
				progressBar.css('width', '0%');

				self.performBulkSync(0, function() {
					button.prop('disabled', false).text('Sync All Products');
				});
			});
		},

		/**
		 * Perform bulk sync with progress tracking
		 */
		performBulkSync: function(offset, completeCallback) {
			var self = this;
			var progressBar = $('#progress-bar');
			var progressText = $('#progress-text');
			var resultsDiv = $('#sync-results');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_sync_all_products',
					offset: offset,
					batch_size: 5,
					nonce: self.getStockSyncNonce()
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						var percentage = Math.round((data.processed / data.total) * 100);
						
						// Update progress bar
						progressBar.css('width', percentage + '%');
						progressText.text('Syncing... ' + data.processed + ' of ' + data.total + ' products (' + percentage + '%)');

						// Add results to the log
						data.results.forEach(function(result) {
							var color = result.success ? '#28a745' : '#dc3545';
							resultsDiv.append('<div style="color: ' + color + '">' + result.message + '</div>');
						});

						// Auto-scroll to bottom
						resultsDiv.scrollTop(resultsDiv[0].scrollHeight);

						// Continue with next batch if not complete
						if (!data.is_complete) {
							setTimeout(function() {
								self.performBulkSync(data.next_offset, completeCallback);
							}, 100); // Small delay to show progress
						} else {
							progressText.text('Sync completed! Processed ' + data.total + ' products.');
							progressBar.css('background', '#28a745');
							if (completeCallback) completeCallback();
						}
					} else {
						progressText.text('Error: ' + response.data.message);
						progressBar.css('background', '#dc3545');
						if (completeCallback) completeCallback();
					}
				},
				error: function() {
					progressText.text('Error: Failed to sync products. Please try again.');
					progressBar.css('background', '#dc3545');
					if (completeCallback) completeCallback();
				}
			});
		},

		/**
		 * Get stock sync nonce
		 */
		getStockSyncNonce: function() {
			// Use the nonce provided by wp_localize_script if available
			if (typeof univoucher_settings_vars !== 'undefined' && univoucher_settings_vars.nonce) {
				return univoucher_settings_vars.nonce;
			}
			// Fallback for backward compatibility
			return $('#get-products-nonce').val() || 'fallback_nonce';
		},
		

		
		/**
		 * Show loading state
		 */
		showLoading: function(element) {
			$(element).addClass('loading').prop('disabled', true);
		},
		
		/**
		 * Hide loading state
		 */
		hideLoading: function(element) {
			$(element).removeClass('loading').prop('disabled', false);
		},
		
		/**
		 * Show notice
		 */
		showNotice: function(message, type) {
			type = type || 'info';
			var noticeClass = 'notice notice-' + type;
			var notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
			
			$('.wrap h1').after(notice);
			
			// Auto-hide after 5 seconds
			setTimeout(function() {
				notice.fadeOut();
			}, 5000);
		}
		
	};

})(jQuery);