<?php
/**
 * UniVoucher For WooCommerce CSV Handler
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_CSV_Handler class.
 */
class UniVoucher_WC_CSV_Handler {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_CSV_Handler
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_CSV_Handler Instance.
	 *
	 * @return UniVoucher_WC_CSV_Handler - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_CSV_Handler Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_process_csv', array( $this, 'ajax_process_csv' ) );
	}

	/**
	 * AJAX handler to process uploaded CSV file.
	 */
	public function ajax_process_csv() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_csv_upload' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		// Validate uploaded file exists.
		if ( ! isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded.', 'univoucher-for-woocommerce' ) ) );
		}

		// Sanitize file data.
		$sanitized_file = array(
			'name'     => isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( $_FILES['csv_file']['name'] ) : '',
			'type'     => isset( $_FILES['csv_file']['type'] ) ? sanitize_mime_type( $_FILES['csv_file']['type'] ) : '',
			'tmp_name' => isset( $_FILES['csv_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['csv_file']['tmp_name'] ) : '',
			'error'    => isset( $_FILES['csv_file']['error'] ) ? intval( $_FILES['csv_file']['error'] ) : 0,
			'size'     => isset( $_FILES['csv_file']['size'] ) ? intval( $_FILES['csv_file']['size'] ) : 0,
		);

		// Process the uploaded file.
		$result = $this->process_uploaded_csv( $sanitized_file );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process uploaded CSV file.
	 *
	 * @param array $file Uploaded file array.
	 * @return array|WP_Error Processed cards or error.
	 */
	private function process_uploaded_csv( $file ) {
		// Validate file type.
		$file_type = wp_check_filetype( $file['name'] );
		if ( 'csv' !== $file_type['ext'] && 'text/csv' !== $file['type'] ) {
			return new WP_Error( 'invalid_file', esc_html__( 'Please upload a CSV file.', 'univoucher-for-woocommerce' ) );
		}

		// Read and parse CSV file.
		$csv_data = $this->parse_csv_file( $file['tmp_name'] );

		if ( is_wp_error( $csv_data ) ) {
			return $csv_data;
		}

		return array(
			'cards' => $csv_data,
			'count' => count( $csv_data ),
		);
	}

	/**
	 * Parse CSV file and extract card data.
	 *
	 * @param string $file_path Path to uploaded CSV file.
	 * @return array|WP_Error Array of card data or error.
	 */
	private function parse_csv_file( $file_path ) {
		// Initialize WordPress filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', esc_html__( 'File not found.', 'univoucher-for-woocommerce' ) );
		}

		$file_contents = $wp_filesystem->get_contents( $file_path );
		if ( false === $file_contents ) {
			return new WP_Error( 'file_read_error', esc_html__( 'Could not read the file.', 'univoucher-for-woocommerce' ) );
		}

		$cards = array();
		$lines = explode( "\n", $file_contents );
		$line_number = 0;

		foreach ( $lines as $line ) {
			$line_number++;

			// Skip header row and empty lines.
			if ( 1 === $line_number || empty( trim( $line ) ) ) {
				continue;
			}

			// Parse CSV row.
			$row = str_getcsv( $line );

			// Expected format: No,Card ID,Card Secret,Amount,Token,Network,Fee paid,Token address,Created by,Created on,Abandoned on
			if ( count( $row ) >= 3 ) {
				$card_id = isset( $row[1] ) ? sanitize_text_field( trim( $row[1] ) ) : '';
				$card_secret = isset( $row[2] ) ? sanitize_text_field( trim( $row[2] ) ) : '';

				if ( ! empty( $card_id ) && ! empty( $card_secret ) ) {
					$cards[] = array(
						'card_id' => $card_id,
						'card_secret' => $card_secret,
					);
				}
			}
		}

		if ( empty( $cards ) ) {
			return new WP_Error( 'no_cards_found', esc_html__( 'No valid cards found in CSV file.', 'univoucher-for-woocommerce' ) );
		}

		return $cards;
	}
}