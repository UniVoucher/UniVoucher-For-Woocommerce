/**
 * UniVoucher For WooCommerce - Inventory Management JavaScript
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

(function($) {
	'use strict';

	let InventoryManager = {

		init: function() {
			this.bindEvents();
			this.setupNonces();
		},

		setupNonces: function() {
			// Create nonce for AJAX requests
			this.nonce = typeof univoucher_inventory_vars !== 'undefined' ? univoucher_inventory_vars.nonce : '';
		},

		bindEvents: function() {
			// Delete events
			$(document).on('click', '.delete-gift-card', this.deleteGiftCard.bind(this));

			// Bulk actions
			$('#doaction, #doaction2').on('click', this.handleBulkAction.bind(this));

			// Filter buttons
			$('#filter-submit').on('click', this.handleFilter.bind(this));

			// Enter key for search
			$('input[name="s"]').on('keypress', function(e) {
				if (e.which === 13) {
					InventoryManager.handleFilter();
				}
			});

			// Edit modal events
			this.bindEditModalEvents();
		},

		bindEditModalEvents: function() {
			// Edit card functionality
			$(document).on('click', '.edit-gift-card', function(e) {
				e.preventDefault();
				var cardId = $(this).data('id');
				InventoryManager.openEditModal(cardId);
			});

			// Close modal functionality
			$(document).on('click', '.close-edit-modal, .cancel-edit-btn', function() {
				InventoryManager.closeEditModal();
			});

			// Close modal when clicking backdrop
			$(document).on('click', '.edit-card-backdrop', function(e) {
				if (e.target === this) {
					InventoryManager.closeEditModal();
				}
			});

			// Card secret formatting
			$(document).on('input', '#edit-card-secret-input', function() {
				var value = $(this).val();
				var formatted = InventoryManager.formatCardSecret(value);
				$(this).val(formatted);
				// Reset validation when card secret is changed
				InventoryManager.resetValidation();
			});

			// Card ID input
			$(document).on('input', '#edit-card-id-input', function() {
				// Reset validation when card ID is changed
				InventoryManager.resetValidation();
			});

			// Status change handling (only for main status, not delivery status)
			$(document).on('change', '#edit-status', function() {
				var currentOrderId = $('#edit-card-form input[name="order_id"]').val();
				var isAssigned = currentOrderId && currentOrderId !== '' && currentOrderId !== '0';
				var $message = $('#edit-status-message');
				
				// For assigned cards, trying to change from sold should show message and reset
				if (isAssigned && $(this).val() !== 'sold') {
					$message.html('<span style="color: #dc3232;">Please unassign from order first</span>').show();
					$(this).val('sold'); // Reset to sold
				} else {
					$message.hide();
				}
			});
			
			// Delivery status can always be changed, no restrictions
			$(document).on('change', '#edit-delivery-status', function() {
				// No restrictions on delivery status changes
			});

			// Validate card
			$(document).on('click', '#validate-edit-card-btn', function() {
				InventoryManager.validateEditCard();
			});

			// Save card
			$(document).on('click', '#save-edit-card-btn', function() {
				InventoryManager.saveEditCard();
			});

			// Unassign from order
			$(document).on('click', '#unassign-order-btn', function() {
				InventoryManager.unassignFromOrder();
			});
		},

		deleteGiftCard: function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete this gift card?')) {
				return;
			}

			const id = $(e.currentTarget).data('id');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_delete_gift_card',
					id: id,
					nonce: this.nonce
				},
				success: function(response) {
					if (response.success) {
						InventoryManager.showSuccess('Gift card deleted successfully.');
						location.reload(); // Refresh the page
					} else {
						InventoryManager.showError('Failed to delete gift card: ' + response.data.message);
					}
				},
				error: function() {
					InventoryManager.showError('Failed to delete gift card. Please try again.');
				}
			});
		},

		handleBulkAction: function(e) {
			e.preventDefault();
			
			const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
			const selectedIds = this.getSelectedIds();
			
			if (!action || action === '-1') {
				this.showError('Please select an action.');
				return;
			}
			
			if (selectedIds.length === 0) {
				this.showError('Please select at least one gift card.');
				return;
			}
			
			// Different confirmation messages for different actions
			let confirmMessage = '';
			let ajaxAction = 'univoucher_bulk_action';
			
			if (action === 'delete') {
				confirmMessage = 'Are you sure you want to delete ' + selectedIds.length + ' gift card(s)? This action cannot be undone.';
			} else if (action === 'mark_inactive') {
				confirmMessage = 'Are you sure you want to mark ' + selectedIds.length + ' gift card(s) as inactive? This will decrease product stock.';
			} else if (action === 'mark_available') {
				confirmMessage = 'Are you sure you want to mark ' + selectedIds.length + ' gift card(s) as available? This will increase product stock.';
			} else {
				this.showError('Unknown action selected.');
				return;
			}
			
			if (!confirm(confirmMessage)) {
				return;
			}
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: ajaxAction,
					action_type: action,
					card_ids: selectedIds,
					nonce: univoucher_vars.bulk_action_nonce
				},
				success: function(response) {
					if (response.success) {
						InventoryManager.showSuccess(response.data.message);
						location.reload(); // Refresh the page
					} else {
						InventoryManager.showError('Bulk action failed: ' + response.data.message);
					}
				},
				error: function() {
					InventoryManager.showError('Bulk action failed. Please try again.');
				}
			});
		},

		handleFilter: function() {
			const status = $('#filter-by-status').val();
			const deliveryStatus = $('#filter-by-delivery-status').val();
			const chainId = $('#filter-by-chain').val();
			const productId = $('#filter-by-product').val();
			const search = $('input[name="s"]').val();
			
			const url = new URL(window.location);
			
			// Update URL parameters
			if (status) {
				url.searchParams.set('status', status);
			} else {
				url.searchParams.delete('status');
			}
			
			if (deliveryStatus) {
				url.searchParams.set('delivery_status', deliveryStatus);
			} else {
				url.searchParams.delete('delivery_status');
			}
			
			if (chainId) {
				url.searchParams.set('chain_id', chainId);
			} else {
				url.searchParams.delete('chain_id');
			}
			
			if (productId) {
				url.searchParams.set('product_id', productId);
			} else {
				url.searchParams.delete('product_id');
			}
			
			if (search) {
				url.searchParams.set('s', search);
			} else {
				url.searchParams.delete('s');
			}
			
			// Reset pagination
			url.searchParams.delete('paged');
			
			// Add filter nonce
			if (typeof univoucher_inventory_vars !== 'undefined' && univoucher_inventory_vars.filter_nonce) {
				url.searchParams.set('_wpnonce', univoucher_inventory_vars.filter_nonce);
			}
			
			window.location.href = url.toString();
		},

		getSelectedIds: function() {
			const selectedIds = [];
			$('input[name="gift_card[]"]:checked').each(function() {
				selectedIds.push($(this).val());
			});
			return selectedIds;
		},

		// Edit Modal Functions
		openEditModal: function(cardId) {
			// Define ajaxurl if not available
			if (typeof ajaxurl === 'undefined') {
				var ajaxurl = univoucher_vars.ajax_url;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_get_card_for_edit',
					card_id: cardId,
					nonce: univoucher_vars.edit_card_nonce
				},
				success: function(response) {
					if (response.success) {
						InventoryManager.populateEditForm(response.data);
						$('#edit-card-modal').show();
						$('body').addClass('edit-modal-open');
					} else {
						alert('Error: ' + (response.data ? response.data.message : 'Failed to load card'));
					}
				},
				error: function(xhr, status, error) {
					alert('Failed to load card details. Please try again.');
				}
			});
		},

		populateEditForm: function(data) {
			var card = data.card;
			var display = data.display;

			// Set form values
			$('#edit-card-id').val(card.id);
			$('#edit-card-id-input').val(card.card_id);
			$('#edit-card-secret-input').val(card.card_secret);
			$('#edit-product-id').val(card.product_id);
			$('#edit-order-id').val(card.order_id || '');
			$('#edit-chain-id').val(card.chain_id);
			$('#edit-token-address').val(card.token_address);
			$('#edit-token-symbol').val(card.token_symbol);
			$('#edit-token-type').val(card.token_type);
			$('#edit-token-decimals').val(card.token_decimals);
			$('#edit-amount').val(card.amount);
			$('#edit-status').val(card.status);
			$('#edit-delivery-status').val(card.delivery_status);

			// Set display values with clickable links
			if (display.product_info.includes('#')) {
				var productId = display.product_info.match(/#(\d+)/)[1];
				var productEditUrl = univoucher_vars.post_edit_url + productId;
				$('#edit-product-info').html('<a href="' + productEditUrl + '" target="_blank">' + display.product_info + '</a>');
			} else {
				$('#edit-product-info').text(display.product_info);
			}

			if (display.order_edit_url) {
				$('#edit-order-info').html('<a href="' + display.order_edit_url + '" target="_blank">' + display.order_info + '</a>');
			} else {
				$('#edit-order-info').text(display.order_info);
			}

			$('#edit-info-token').text(display.token_info);
			$('#edit-info-amount').text(display.formatted_amount + ' ' + card.token_symbol);
			$('#edit-info-network').text(display.network_name + ' (ID: ' + card.chain_id + ')');

			// Show/hide unassign section and manage status options
			if (card.order_id && card.order_id !== '' && card.order_id !== '0') {
				$('#edit-unassign-section').show();
				// For assigned cards, only "sold" should be enabled
				$('#edit-status option[value="available"]').prop('disabled', true);
				$('#edit-status option[value="inactive"]').prop('disabled', true);
				$('#edit-status option[value="sold"]').prop('disabled', false);
				// Delivery status remains fully enabled
				$('#edit-delivery-status option').prop('disabled', false);
			} else {
				$('#edit-unassign-section').hide();
				// For unassigned cards, enable available and inactive, disable sold
				$('#edit-status option[value="available"]').prop('disabled', false);
				$('#edit-status option[value="inactive"]').prop('disabled', false);
				$('#edit-status option[value="sold"]').prop('disabled', true);
				$('#edit-delivery-status option').prop('disabled', false);
			}

			// Set initial validation as all valid since this card was already validated when added
			this.setInitialValidationState();
		},

		closeEditModal: function() {
			$('#edit-card-modal').hide();
			$('body').removeClass('edit-modal-open');
		},

		resetValidation: function() {
			$('.validation-icon').removeClass('valid invalid loading').addClass('pending').removeClass('dashicons-yes-alt dashicons-no-alt dashicons-update').addClass('dashicons-minus');
			$('.validation-error').hide();
			$('#save-edit-card-btn').prop('disabled', true);
		},

		setInitialValidationState: function() {
			// Set all validation icons to valid since this card was already validated when added
			$('.validation-icon').removeClass('pending invalid loading dashicons-minus dashicons-no-alt dashicons-update').addClass('valid dashicons-yes-alt');
			$('.validation-error').hide();
			$('#save-edit-card-btn').prop('disabled', false);
		},

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

		validateEditCard: function() {
			var cardId = $('#edit-card-id-input').val().trim();
			var cardSecret = $('#edit-card-secret-input').val().trim();

			if (!cardId || !cardSecret) {
				alert('Please enter both Card ID and Card Secret');
				return;
			}

			// Check format first
			if (cardId.length < 4) {
				alert('Card ID must be at least 4 digits');
				return;
			}

			// Check card secret format
			var rawCardSecret = cardSecret.replace(/-/g, '');
			if (rawCardSecret.length !== 20 || !/^[A-Z]{20}$/.test(rawCardSecret)) {
				alert('Card Secret must be exactly 20 uppercase letters');
				return;
			}

			// Set loading state
			$('.validation-icon').removeClass('dashicons-yes-alt dashicons-no-alt dashicons-minus valid invalid pending').addClass('dashicons-update loading');
			$('#validate-edit-card-btn').prop('disabled', true).text('Validating...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_validate_single_card',
					nonce: univoucher_vars.verify_cards_nonce,
					card_id: cardId,
					card_secret: cardSecret,
					product_id: $('#edit-product-id').val(),
					chain_id: $('#edit-chain-id').val(),
					token_address: $('#edit-token-address').val(),
					amount: $('#edit-amount').val(),
					token_decimals: $('#edit-token-decimals').val(),
					edit_mode: true,
					current_card_id: $('#edit-card-id').val()
				},
				success: function(response) {
					$('#validate-edit-card-btn').prop('disabled', false).text('Validate Card');
					
					if (response.success) {
						InventoryManager.displayValidationResults(response.data);
						
						// Update card info display
						if (response.data.api_data) {
							var apiData = response.data.api_data;
							var tokenSymbol = apiData.tokenSymbol || $('#edit-token-symbol').val();
							var tokenType = apiData.tokenType || $('#edit-token-type').val();
							var amount = response.data.formatted_amount || $('#edit-amount').val();
							
							$('#edit-info-token').text(tokenSymbol + ' (' + tokenType + ')');
							$('#edit-info-amount').text(amount + ' ' + tokenSymbol);
						}
					} else {
						// Set all icons to invalid
						$('.validation-icon').removeClass('dashicons-yes-alt dashicons-update dashicons-minus valid loading pending').addClass('dashicons-no-alt invalid');
						alert('Validation failed: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: function() {
					$('#validate-edit-card-btn').prop('disabled', false).text('Validate Card');
					$('.validation-icon').removeClass('dashicons-yes-alt dashicons-update dashicons-minus valid loading pending').addClass('dashicons-no-alt invalid');
					alert('Failed to validate card. Please try again.');
				}
			});
		},

		displayValidationResults: function(data) {
			var validations = data.validations;
			var allValid = data.all_valid;

			// Update validation icons
			$('.validation-item[data-validation="new"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.new ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');
			$('.validation-item[data-validation="active"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.active ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');
			$('.validation-item[data-validation="network"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.network ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');
			$('.validation-item[data-validation="amount"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.amount ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');
			$('.validation-item[data-validation="token"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.token ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');
			$('.validation-item[data-validation="secret"] .validation-icon').removeClass('dashicons-update dashicons-minus loading pending').addClass(validations.secret ? 'dashicons-yes-alt valid' : 'dashicons-no-alt invalid');

			// Enable save button if all valid
			$('#save-edit-card-btn').prop('disabled', !allValid);
		},

		saveEditCard: function() {
			if ($('#save-edit-card-btn').prop('disabled')) {
				return;
			}

			$('#save-edit-card-btn').prop('disabled', true).text('Saving...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_update_card',
					nonce: univoucher_vars.edit_card_nonce,
					card_id_pk: $('#edit-card-id').val(),
					card_id: $('#edit-card-id-input').val(),
					card_secret: $('#edit-card-secret-input').val(),
					status: $('#edit-status').val(),
					delivery_status: $('#edit-delivery-status').val()
				},
				success: function(response) {
					if (response.success) {
						if (typeof univoucherNotify !== 'undefined') {
							univoucherNotify.success('Card updated successfully!');
							// Close modal after a short delay to show the notification
							setTimeout(function() {
								InventoryManager.closeEditModal();
								location.reload(); // Refresh the page to show updated data
							}, 200);
						} else {
							InventoryManager.closeEditModal();
							location.reload(); // Refresh the page to show updated data
						}
					} else {
						if (typeof univoucherNotify !== 'undefined') {
							univoucherNotify.error('Error: ' + (response.data.message || 'Failed to update card'));
						} else {
							alert('Error: ' + (response.data.message || 'Failed to update card'));
						}
						$('#save-edit-card-btn').prop('disabled', false).text('Save Changes');
					}
				},
				error: function() {
					if (typeof univoucherNotify !== 'undefined') {
						univoucherNotify.error('Failed to update card. Please try again.');
					} else {
						alert('Failed to update card. Please try again.');
					}
					$('#save-edit-card-btn').prop('disabled', false).text('Save Changes');
				}
			});
		},

		unassignFromOrder: function() {
			var cardId = $('#edit-card-id').val();
			var orderId = $('#edit-order-id').val();
			
			if (!orderId || orderId === '' || orderId === '0') {
				alert('No order found to unassign from');
				return;
			}
			
			var productId = $('#edit-product-id').val();
			
			if (!confirm('Are you sure you want to unassign this card from the order?')) {
				return;
			}

			$('#unassign-order-btn').prop('disabled', true).text('Unassigning...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'univoucher_unassign_card',
					nonce: univoucher_vars.unassign_card_nonce,
					card_id: cardId,
					order_id: orderId,
					product_id: productId
				},
				success: function(response) {
					if (response.success) {
						// Update form state after successful unassignment
						$('#edit-order-id').val('');
						$('#edit-order-info').text(univoucher_vars.not_assigned_text);
						$('#edit-unassign-section').hide();
						// For unassigned cards, enable available and inactive, disable sold
						$('#edit-status option[value="available"]').prop('disabled', false);
						$('#edit-status option[value="inactive"]').prop('disabled', false);
						$('#edit-status option[value="sold"]').prop('disabled', true);
						// Set status to available after unassignment
						$('#edit-status').val('available');
						$('#edit-delivery-status option').prop('disabled', false);
						if (typeof univoucherNotify !== 'undefined') {
							univoucherNotify.success('Card unassigned successfully!');
						} else {
							alert('Card unassigned successfully!');
						}
						$('#unassign-order-btn').prop('disabled', false).text('Unassign from Order');
					} else {
						if (typeof univoucherNotify !== 'undefined') {
							univoucherNotify.error('Error: ' + (response.data.message || 'Failed to unassign card'));
						} else {
							alert('Error: ' + (response.data.message || 'Failed to unassign card'));
						}
						$('#unassign-order-btn').prop('disabled', false).text('Unassign from Order');
					}
				},
				error: function() {
					if (typeof univoucherNotify !== 'undefined') {
						univoucherNotify.error('Failed to unassign card. Please try again.');
					} else {
						alert('Failed to unassign card. Please try again.');
					}
					$('#unassign-order-btn').prop('disabled', false).text('Unassign from Order');
				}
			});
		},

		showError: function(message) {
			this.showNotice(message, 'error');
		},

		showSuccess: function(message) {
			this.showNotice(message, 'success');
		},

		showNotice: function(message, type) {
			// Use the new notification system
			if (typeof univoucherNotify !== 'undefined') {
				univoucherNotify(message, type, type === 'error' ? 8000 : 5000);
			}
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		InventoryManager.init();
	});

})(jQuery); 