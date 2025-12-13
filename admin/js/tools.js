/**
 * UniVoucher Tools Page JavaScript
 *
 * @package UniVoucher_For_WooCommerce
 */

(function($) {
	'use strict';

	/**
	 * Find Missing Cards functionality
	 */
	var FindMissingCards = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			$('#univoucher-find-missing-cards').on('click', this.scanForMissingCards);
		},

		/**
		 * Scan for missing cards
		 */
		scanForMissingCards: function() {
			var $button = $(this);
			var $spinner = $('#univoucher-scan-spinner');
			var $results = $('#univoucher-missing-cards-results');
			var $resultsContent = $('#univoucher-results-content');

			// Disable button and show spinner
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$results.hide();
			$resultsContent.html('');

			// Make AJAX request
			$.ajax({
				url: univoucherTools.ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_find_missing_cards',
					nonce: univoucherTools.nonce
				},
				success: function(response) {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						FindMissingCards.displayResults(response.data, $resultsContent);
					} else {
						FindMissingCards.displayError(response.data ? response.data.message : univoucherTools.i18n.unknownError, $resultsContent);
					}

					$results.fadeIn();
				},
				error: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
					FindMissingCards.displayError(univoucherTools.i18n.ajaxError, $resultsContent);
					$results.fadeIn();
				}
			});
		},

		/**
		 * Display scan results
		 *
		 * @param {Object} data Response data
		 * @param {jQuery} $container Results container
		 */
		displayResults: function(data, $container) {
			var orders = data.orders;
			var total = data.total_missing;

			if (orders.length === 0) {
				var successHtml = '<div class="univoucher-notice-success">';
				successHtml += '<strong>' + univoucherTools.i18n.greatNews + '</strong> ';
				successHtml += univoucherTools.i18n.noMissingCards;
				successHtml += '</div>';
				$container.html(successHtml);
				return;
			}

			// Count unique orders
			var uniqueOrders = {};
			$.each(orders, function(i, order) {
				uniqueOrders[order.order_id] = true;
			});
			var orderCount = Object.keys(uniqueOrders).length;

			var html = '<div class="univoucher-notice-info">';
			html += '<strong>' + univoucherTools.i18n.found + '</strong> ';
			html += orderCount + ' order(s) with ';
			html += orders.length + ' ' + univoucherTools.i18n.ordersWithText + ' ';
			html += '<span class="univoucher-missing-count">' + total + '</span> ';
			html += univoucherTools.i18n.missingCardsTotal;
			html += '</div>';

			html += this.buildTable(orders);
			$container.html(html);
		},

		/**
		 * Build results table
		 *
		 * @param {Array} orders Orders data
		 * @return {string} HTML table
		 */
		buildTable: function(orders) {
			// Group orders by order_id
			var groupedOrders = {};
			$.each(orders, function(i, order) {
				if (!groupedOrders[order.order_id]) {
					groupedOrders[order.order_id] = {
						order_id: order.order_id,
						status: order.status,
						status_label: order.status_label,
						edit_url: order.edit_url,
						products: []
					};
				}
				groupedOrders[order.order_id].products.push({
					product_name: order.product_name,
					ordered_qty: order.ordered_qty,
					assigned_qty: order.assigned_qty,
					missing_qty: order.missing_qty
				});
			});

			var html = '<table class="univoucher-results-table">';
			html += '<thead><tr>';
			html += '<th>' + univoucherTools.i18n.order + '</th>';
			html += '<th>' + univoucherTools.i18n.status + '</th>';
			html += '<th>' + univoucherTools.i18n.product + '</th>';
			html += '<th>' + univoucherTools.i18n.ordered + '</th>';
			html += '<th>' + univoucherTools.i18n.assigned + '</th>';
			html += '<th>' + univoucherTools.i18n.missing + '</th>';
			html += '<th>' + univoucherTools.i18n.actions + '</th>';
			html += '</tr></thead><tbody>';

			$.each(groupedOrders, function(orderId, orderData) {
				var statusClass = 'univoucher-status-' + orderData.status.replace('wc-', '');
				var productCount = orderData.products.length;

				$.each(orderData.products, function(i, product) {
					html += '<tr>';

					// Show order info only on first row
					if (i === 0) {
						html += '<td rowspan="' + productCount + '"><a href="' + orderData.edit_url + '" target="_blank">#' + orderData.order_id + '</a></td>';
						html += '<td rowspan="' + productCount + '"><span class="univoucher-status-badge ' + statusClass + '">' + orderData.status_label + '</span></td>';
					}

					html += '<td>' + product.product_name + '</td>';
					html += '<td>' + product.ordered_qty + '</td>';
					html += '<td>' + product.assigned_qty + '</td>';
					html += '<td><span class="univoucher-missing-count">' + product.missing_qty + '</span></td>';

					// Show action button only on first row
					if (i === 0) {
						html += '<td rowspan="' + productCount + '"><a href="' + orderData.edit_url + '" class="button button-small" target="_blank">' + univoucherTools.i18n.viewOrder + '</a></td>';
					}

					html += '</tr>';
				});
			});

			html += '</tbody></table>';
			return html;
		},

		/**
		 * Display error message
		 *
		 * @param {string} message Error message
		 * @param {jQuery} $container Results container
		 */
		displayError: function(message, $container) {
			var html = '<div class="univoucher-notice-error">';
			html += '<strong>' + univoucherTools.i18n.error + '</strong> ' + message;
			html += '</div>';
			$container.html(html);
		}
	};

	/**
	 * Stock Sync functionality
	 */
	var StockSync = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Enable/disable single product sync button based on selection
			$('#univoucher-product-select').on('change', function() {
				var productId = $(this).val();
				$('#sync-single-product').prop('disabled', !productId);
			});

			// Single product sync
			$('#sync-single-product').on('click', function() {
				StockSync.syncSingleProduct();
			});

			// Bulk sync all products
			$('#sync-all-products').on('click', function() {
				StockSync.bulkSyncProducts();
			});
		},

		/**
		 * Sync single product
		 */
		syncSingleProduct: function() {
			var $button = $('#sync-single-product');
			var productId = $('#univoucher-product-select').val();
			var $statusDiv = $('#single-sync-status');

			if (!productId) return;

			$button.prop('disabled', true).text('Syncing...');
			$statusDiv.hide();

			$.ajax({
				url: univoucherTools.ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_sync_single_product',
					product_id: productId,
					nonce: univoucherTools.stockNonce
				},
				success: function(response) {
					if (response.success) {
						$statusDiv.removeClass('error').addClass('success')
							.css('background', '#d4edda')
							.css('border', '1px solid #c3e6cb')
							.css('color', '#155724')
							.html('<strong>Success:</strong> ' + response.data.message)
							.show();
					} else {
						$statusDiv.removeClass('success').addClass('error')
							.css('background', '#f8d7da')
							.css('border', '1px solid #f5c6cb')
							.css('color', '#721c24')
							.html('<strong>Error:</strong> ' + response.data.message)
							.show();
					}
				},
				error: function() {
					$statusDiv.removeClass('success').addClass('error')
						.css('background', '#f8d7da')
						.css('border', '1px solid #f5c6cb')
						.css('color', '#721c24')
						.html('<strong>Error:</strong> Failed to sync product. Please try again.')
						.show();
				},
				complete: function() {
					$button.prop('disabled', false).text('Sync Selected Product');
				}
			});
		},

		/**
		 * Bulk sync all products
		 */
		bulkSyncProducts: function() {
			var $button = $('#sync-all-products');
			var $progressDiv = $('#bulk-sync-progress');
			var $progressBar = $('#progress-bar');
			var $progressText = $('#progress-text');
			var $resultsDiv = $('#sync-results');

			$button.prop('disabled', true).text('Syncing...');
			$progressDiv.show();
			$resultsDiv.empty();
			$progressBar.css('width', '0%');

			this.performBulkSync(0, function() {
				$button.prop('disabled', false).text('Sync All Products');
			});
		},

		/**
		 * Perform bulk sync with progress tracking
		 *
		 * @param {number} offset Current offset
		 * @param {Function} completeCallback Callback when complete
		 */
		performBulkSync: function(offset, completeCallback) {
			var $progressBar = $('#progress-bar');
			var $progressText = $('#progress-text');
			var $resultsDiv = $('#sync-results');

			$.ajax({
				url: univoucherTools.ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_sync_all_products',
					offset: offset,
					batch_size: 5,
					nonce: univoucherTools.stockNonce
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						var percentage = Math.round((data.processed / data.total) * 100);

						// Update progress bar
						$progressBar.css('width', percentage + '%');
						$progressText.text('Syncing... ' + data.processed + ' of ' + data.total + ' products (' + percentage + '%)');

						// Add results to log
						$.each(data.results, function(i, result) {
							var color = result.success ? '#155724' : '#721c24';
							var line = '<div style="color: ' + color + ';">' + result.message + '</div>';
							$resultsDiv.append(line);
						});

						// Auto-scroll to bottom
						$resultsDiv.scrollTop($resultsDiv[0].scrollHeight);

						// Continue if not complete
						if (!data.is_complete) {
							StockSync.performBulkSync(data.next_offset, completeCallback);
						} else {
							$progressText.text('Completed! Synced ' + data.total + ' products.');
							if (completeCallback) {
								completeCallback();
							}
						}
					} else {
						$resultsDiv.append('<div style="color: #721c24;"><strong>Error:</strong> ' + response.data.message + '</div>');
						if (completeCallback) {
							completeCallback();
						}
					}
				},
				error: function() {
					$resultsDiv.append('<div style="color: #721c24;"><strong>Error:</strong> AJAX request failed.</div>');
					if (completeCallback) {
						completeCallback();
					}
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('#univoucher-find-missing-cards').length) {
			FindMissingCards.init();
		}

		if ($('#univoucher-product-select').length || $('#sync-all-products').length) {
			StockSync.init();
		}
	});

})(jQuery);
