jQuery(document).ready(function($) {
	// Toggle visibility of cards display position option based on show cards checkbox
	var showCardsCheckbox = $('#univoucher_wc_show_in_order_details');
	var positionOption = $('#cards-display-position-option');
	
	function togglePositionOption() {
		if (showCardsCheckbox.is(':checked')) {
			positionOption.show();
		} else {
			positionOption.hide();
		}
	}
	
	showCardsCheckbox.on('change', togglePositionOption);
	togglePositionOption(); // Initial state

	// Toggle visibility of email fully assigned option based on email checkbox
	var emailCheckbox = $('#univoucher_wc_send_email_cards');
	var fullyAssignedOption = $('#email-fully-assigned-option');
	
	function toggleFullyAssignedOption() {
		if (emailCheckbox.is(':checked')) {
			fullyAssignedOption.show();
		} else {
			fullyAssignedOption.hide();
		}
	}
	
	emailCheckbox.on('change', toggleFullyAssignedOption);
	toggleFullyAssignedOption(); // Initial state
});