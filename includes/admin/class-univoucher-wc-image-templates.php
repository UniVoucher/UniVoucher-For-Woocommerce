<?php
/**
 * UniVoucher For WooCommerce Image Templates
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Image_Templates class.
 */
class UniVoucher_WC_Image_Templates {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Image_Templates
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Image_Templates Instance.
	 *
	 * @return UniVoucher_WC_Image_Templates - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Image_Templates Constructor.
	 */
	public function __construct() {
		$this->init_settings();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for AJAX handlers.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_get_templates', array( $this, 'ajax_get_templates' ) );
		add_action( 'wp_ajax_univoucher_get_fonts', array( $this, 'ajax_get_fonts' ) );
		add_action( 'wp_ajax_univoucher_test_image_generation', array( $this, 'ajax_test_image_generation' ) );
		add_action( 'wp_ajax_univoucher_get_custom_resources', array( $this, 'ajax_get_custom_resources' ) );
		add_action( 'wp_ajax_univoucher_upload_resource', array( $this, 'ajax_upload_resource' ) );
		add_action( 'wp_ajax_univoucher_delete_resource', array( $this, 'ajax_delete_resource' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Initialize settings.
	 */
	private function init_settings() {
		// Register image template settings.
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'UniVoucher-wide-4x3.png',
			)
		);

		// Register visibility settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_amount_with_symbol',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_amount',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_token_symbol',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_network_name',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_token_logo',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_show_network_logo',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		// Register amount with symbol text styling settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_font',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Inter-Bold.ttf',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 69,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'default'           => '#1f2937',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_align',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_text_align' ),
				'default'           => 'center',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 411,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_with_symbol_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 315,
			)
		);

		// Register amount text styling settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_font',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Inter-Bold.ttf',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'default'           => '#dd3333',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_align',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_text_align' ),
				'default'           => 'right',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 53,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_amount_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 21,
			)
		);

		// Register token symbol text styling settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_font',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Inter-Bold.ttf',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'default'           => '#dd3333',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_align',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_text_align' ),
				'default'           => 'left',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 33,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_symbol_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 48,
			)
		);

		// Register network name text styling settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_font',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Inter-Bold.ttf',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 27,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'default'           => '#1f2937',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_align',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_text_align' ),
				'default'           => 'left',
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 147,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_network_name_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 452,
			)
		);

		// Register network logo settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_logo_height',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 33,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_logo_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 125,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_logo_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 452,
			)
		);

		// Register token logo settings
		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_height',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 68,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_x',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 649,
			)
		);

		register_setting(
			'univoucher_wc_image_settings',
			'univoucher_wc_image_token_y',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 177,
			)
		);

		// Add template settings section.
		add_settings_section(
			'univoucher_wc_template_settings_section',
			esc_html__( 'Template Settings', 'univoucher-for-woocommerce' ),
			array( $this, 'template_settings_section_callback' ),
			'univoucher_wc_image_settings'
		);

		// Add visual editor section.
		add_settings_section(
			'univoucher_wc_visual_editor_section',
			esc_html__( 'Visual Editor', 'univoucher-for-woocommerce' ),
			array( $this, 'visual_editor_section_callback' ),
			'univoucher_wc_image_settings'
		);

		// Add template settings fields (horizontal layout).
		add_settings_field(
			'univoucher_wc_template_settings_group',
			'',
			array( $this, 'template_settings_group_callback' ),
			'univoucher_wc_image_settings',
			'univoucher_wc_template_settings_section',
			array(
				'label_for' => 'univoucher_wc_template_settings_group',
				'class'     => 'univoucher-wc-row',
			)
		);

		// Add drag-and-drop editor field.
		add_settings_field(
			'univoucher_wc_image_editor',
			'',
			array( $this, 'image_editor_callback' ),
			'univoucher_wc_image_settings',
			'univoucher_wc_visual_editor_section',
			array(
				'label_for' => 'univoucher_wc_image_editor',
				'class'     => 'univoucher-wc-row',
			)
		);
	}

	/**
	 * Sanitize checkbox input.
	 *
	 * @param mixed $input The input value.
	 * @return bool The sanitized value.
	 */
	public function sanitize_checkbox( $input ) {
		return (bool) $input;
	}

	/**
	 * Sanitize text alignment input.
	 *
	 * @param string $input The input value.
	 * @return string The sanitized value.
	 */
	public function sanitize_text_align( $input ) {
		return in_array( $input, array( 'left', 'center', 'right' ), true ) ? $input : 'center';
	}
	
	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on UniVoucher settings page with image templates tab.
		if ( 'toplevel_page_univoucher-inventory' !== $hook && 'univoucher_page_univoucher-settings' !== $hook ) {
			return;
		}

		// Check if we're on the image templates tab.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'security';
		if ( 'image-templates' !== $active_tab ) {
			return;
		}

		// Enqueue WordPress color picker.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Enqueue jQuery UI for draggable functionality.
		wp_enqueue_script( 'jquery-ui-draggable' );

		// Enqueue our custom styles.
		wp_enqueue_style(
			'univoucher-image-templates',
			plugins_url( 'admin/css/image-templates.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);

		// Enqueue our custom script.
		wp_enqueue_script(
			'univoucher-image-templates',
			plugins_url( 'admin/js/image-templates.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery', 'wp-color-picker', 'jquery-ui-draggable' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce.
		wp_localize_script(
			'univoucher-image-templates',
			'univoucher_image_templates_ajax',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'univoucher_image_templates_nonce' ),
				'templates_url' => plugins_url( 'admin/images/templates/', UNIVOUCHER_WC_PLUGIN_FILE ),
				'plugin_url'    => plugins_url( '', UNIVOUCHER_WC_PLUGIN_FILE ),
			)
		);
	}

	/**
	 * Get available templates.
	 *
	 * @return array Array of template data.
	 */
	private function get_available_templates() {
		$templates_dir = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/templates/';
		$templates_url = plugins_url( 'admin/images/templates/', UNIVOUCHER_WC_PLUGIN_FILE );
		$templates = array();

		if ( is_dir( $templates_dir ) ) {
			$files = scandir( $templates_dir );
			foreach ( $files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'png' ) {
					$templates[] = array(
						'filename' => $file,
						'name'     => ucfirst( str_replace( array( '-', '_', '.png' ), array( ' ', ' ', '' ), $file ) ),
						'url'      => $templates_url . $file,
					);
				}
			}
		}

		// Fallback if no templates found.
		if ( empty( $templates ) ) {
			$templates[] = array(
				'filename' => 'giftcard-template-1.png',
				'name'     => 'Default Template',
				'url'      => $templates_url . 'giftcard-template-1.png',
			);
		}

		return $templates;
	}

	/**
	 * Get available fonts.
	 *
	 * @return array Array of font data.
	 */
	private function get_available_fonts() {
		$fonts_dir = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/fonts/';
		$fonts = array();

		if ( is_dir( $fonts_dir ) ) {
			$files = scandir( $fonts_dir );
			foreach ( $files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'ttf' || pathinfo( $file, PATHINFO_EXTENSION ) === 'otf' ) {
					$font_name = pathinfo( $file, PATHINFO_FILENAME );
					// Convert hyphens to spaces for display, but keep original for CSS
					$display_name = ucfirst( str_replace( array( '-', '_' ), array( ' ', ' ' ), $font_name ) );
					$fonts[] = array(
						'filename' => $file,
						'name'     => $display_name,
						'css_name' => $font_name, // Keep original name for CSS font-family
					);
				}
			}
		}

		// Fallback if no fonts found.
		if ( empty( $fonts ) ) {
			$fonts[] = array(
				'filename' => 'Inter-Bold.ttf',
				'name'     => 'Inter Bold',
				'css_name' => 'Inter-Bold',
			);
		}

		return $fonts;
	}

	/**
	 * AJAX handler for getting available templates.
	 */
	public function ajax_get_templates() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$templates = $this->get_available_templates();
		wp_send_json_success( $templates );
	}

	/**
	 * AJAX handler for getting available fonts.
	 */
	public function ajax_get_fonts() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$fonts = $this->get_available_fonts();
		wp_send_json_success( $fonts );
	}

	/**
	 * AJAX handler for testing image generation.
	 */
	public function ajax_test_image_generation() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check if GD extension is available.
		if ( ! extension_loaded( 'gd' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'GD extension is not available on this server.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get all new settings from POST with updated fallback defaults
		$settings = array(
			'template' => sanitize_text_field( $_POST['template'] ?? '' ),
			'show_amount_with_symbol' => isset( $_POST['show_amount_with_symbol'] ) ? (bool) $_POST['show_amount_with_symbol'] : true,
			'show_amount' => isset( $_POST['show_amount'] ) ? (bool) $_POST['show_amount'] : false,
			'show_token_symbol' => isset( $_POST['show_token_symbol'] ) ? (bool) $_POST['show_token_symbol'] : false,
			'show_network_name' => isset( $_POST['show_network_name'] ) ? (bool) $_POST['show_network_name'] : true,
			'show_token_logo' => isset( $_POST['show_token_logo'] ) ? (bool) $_POST['show_token_logo'] : true,
			'show_network_logo' => isset( $_POST['show_network_logo'] ) ? (bool) $_POST['show_network_logo'] : true,
			
			// Amount with symbol text settings
			'amount_with_symbol_font' => sanitize_text_field( $_POST['amount_with_symbol_font'] ?? 'Inter-Bold.ttf' ),
			'amount_with_symbol_size' => absint( $_POST['amount_with_symbol_size'] ?? 69 ),
			'amount_with_symbol_color' => sanitize_hex_color( $_POST['amount_with_symbol_color'] ?? '#1f2937' ),
			'amount_with_symbol_align' => $this->sanitize_text_align( $_POST['amount_with_symbol_align'] ?? 'center' ),
			'amount_with_symbol_x' => absint( $_POST['amount_with_symbol_x'] ?? 411 ),
			'amount_with_symbol_y' => absint( $_POST['amount_with_symbol_y'] ?? 315 ),
			
			// Amount text settings
			'amount_font' => sanitize_text_field( $_POST['amount_font'] ?? 'Inter-Bold.ttf' ),
			'amount_size' => absint( $_POST['amount_size'] ?? 20 ),
			'amount_color' => sanitize_hex_color( $_POST['amount_color'] ?? '#dd3333' ),
			'amount_align' => $this->sanitize_text_align( $_POST['amount_align'] ?? 'right' ),
			'amount_x' => absint( $_POST['amount_x'] ?? 53 ),
			'amount_y' => absint( $_POST['amount_y'] ?? 21 ),
			
			// Token symbol text settings
			'token_symbol_font' => sanitize_text_field( $_POST['token_symbol_font'] ?? 'Inter-Bold.ttf' ),
			'token_symbol_size' => absint( $_POST['token_symbol_size'] ?? 20 ),
			'token_symbol_color' => sanitize_hex_color( $_POST['token_symbol_color'] ?? '#dd3333' ),
			'token_symbol_align' => $this->sanitize_text_align( $_POST['token_symbol_align'] ?? 'left' ),
			'token_symbol_x' => absint( $_POST['token_symbol_x'] ?? 33 ),
			'token_symbol_y' => absint( $_POST['token_symbol_y'] ?? 48 ),
			
			// Network name text settings
			'network_name_font' => sanitize_text_field( $_POST['network_name_font'] ?? 'Inter-Bold.ttf' ),
			'network_name_size' => absint( $_POST['network_name_size'] ?? 27 ),
			'network_name_color' => sanitize_hex_color( $_POST['network_name_color'] ?? '#1f2937' ),
			'network_name_align' => $this->sanitize_text_align( $_POST['network_name_align'] ?? 'left' ),
			'network_name_x' => absint( $_POST['network_name_x'] ?? 147 ),
			'network_name_y' => absint( $_POST['network_name_y'] ?? 452 ),
			
			// Logo settings
			'logo_height' => absint( $_POST['logo_height'] ?? 33 ),
			'logo_x' => absint( $_POST['logo_x'] ?? 125 ),
			'logo_y' => absint( $_POST['logo_y'] ?? 452 ),
			'token_height' => absint( $_POST['token_height'] ?? 68 ),
			'token_x' => absint( $_POST['token_x'] ?? 649 ),
			'token_y' => absint( $_POST['token_y'] ?? 177 ),
		);

		// Generate 6 test variations with different data
		$test_variations = array(
			array(
				'amount' => '100',
				'token_symbol' => 'USDT',
				'chain_id' => 1,
				'network_name' => 'Ethereum',
				'label' => 'Ethereum - USDT'
			),
			array(
				'amount' => '250',
				'token_symbol' => 'USDC',
				'chain_id' => 8453,
				'network_name' => 'Base',
				'label' => 'Base - USDC'
			),
			array(
				'amount' => '50',
				'token_symbol' => 'BNB',
				'chain_id' => 56,
				'network_name' => 'BNB Chain',
				'label' => 'BNB Chain - BNB'
			),
			array(
				'amount' => '1000',
				'token_symbol' => 'ARB',
				'chain_id' => 42161,
				'network_name' => 'Arbitrum',
				'label' => 'Arbitrum - ARB'
			),
			array(
				'amount' => '75',
				'token_symbol' => 'AVAX',
				'chain_id' => 43114,
				'network_name' => 'Avalanche',
				'label' => 'Avalanche - AVAX'
			),
			array(
				'amount' => '500',
				'token_symbol' => 'OP',
				'chain_id' => 10,
				'network_name' => 'Optimism',
				'label' => 'Optimism - OP'
			),
		);

		$generated_images = array();
		
		foreach ( $test_variations as $test_data ) {
			// Generate test image for this variation.
			$image_data = $this->generate_test_image_with_settings( $settings, $test_data );
			
			if ( is_wp_error( $image_data ) ) {
				wp_send_json_error( array( 'message' => $image_data->get_error_message() ) );
			}

			// Convert image data to base64 for preview.
			$image_base64 = 'data:image/png;base64,' . base64_encode( $image_data );
			
			$generated_images[] = array(
				'label' => $test_data['label'],
				'image_data' => $image_base64
			);
		}

		wp_send_json_success( array( 
			'message' => esc_html__( 'Test images generated successfully!', 'univoucher-for-woocommerce' ),
			'images' => $generated_images
		) );
	}

	/**
	 * Generate test image using new settings structure.
	 * 
	 * Memory optimization: Images are resized to maximum width of 800 pixels
	 * to prevent memory exhaustion during test generation.
	 *
	 * @param array $settings Image generation settings.
	 * @param array $test_data Test data (amount, token_symbol, chain_id, network_name).
	 * @return string|WP_Error Image data or error.
	 */
	private function generate_test_image_with_settings( $settings, $test_data ) {
		// Load the template image.
		$template_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/templates/' . $settings['template'];
		
		if ( ! file_exists( $template_path ) ) {
			return new WP_Error( 'template_missing', esc_html__( 'Selected template image not found.', 'univoucher-for-woocommerce' ) );
		}

		$image = imagecreatefrompng( $template_path );
		if ( ! $image ) {
			return new WP_Error( 'template_load', esc_html__( 'Failed to load template image.', 'univoucher-for-woocommerce' ) );
		}

		// Get original image dimensions
		$original_width = imagesx( $image );
		$original_height = imagesy( $image );

		// Calculate new dimensions with maximum width of 800 pixels
		$max_width = 800;
		$new_width = $original_width;
		$new_height = $original_height;

		if ( $original_width > $max_width ) {
			$new_width = $max_width;
			$new_height = (int) ( $original_height * ( $max_width / $original_width ) );
		}

		// Create a new image with the target dimensions
		$resized_image = imagecreatetruecolor( $new_width, $new_height );
		if ( ! $resized_image ) {
			imagedestroy( $image );
			return new WP_Error( 'image_resize', esc_html__( 'Failed to create resized image.', 'univoucher-for-woocommerce' ) );
		}

		// Enable alpha blending for transparency
		imagealphablending( $resized_image, false );
		imagesavealpha( $resized_image, true );
		$transparent = imagecolorallocatealpha( $resized_image, 255, 255, 255, 127 );
		imagefill( $resized_image, 0, 0, $transparent );

		// Resize the image
		imagecopyresampled( $resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height );

		// Destroy the original image to free memory
		imagedestroy( $image );

		// Use the resized image for further processing
		$image = $resized_image;

		// Enable anti-aliasing for better text quality.
		imageantialias( $image, true );
		
		// Enable alpha blending for better transparency handling.
		imagealphablending( $image, true );
		imagesavealpha( $image, true );
		
		// Set interpolation method for higher quality (if available in PHP 5.5+).
		if ( function_exists( 'imagesetinterpolation' ) ) {
			imagesetinterpolation( $image, IMG_BICUBIC );
		}

		// Calculate scale factor for positioning elements proportionally
		$scale_factor = $new_width / $original_width;

		// Draw amount with symbol text if enabled
		if ( $settings['show_amount_with_symbol'] ) {
			$color = $this->hex_to_rgb( $settings['amount_with_symbol_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$combined_text = $test_data['amount'] . ' ' . $test_data['token_symbol'];
			$this->draw_text_on_image( 
				$image, 
				$combined_text, 
				$settings['amount_with_symbol_font'], 
				(int) ( $settings['amount_with_symbol_size'] * $scale_factor ), 
				$text_color, 
				(int) ( $settings['amount_with_symbol_x'] * $scale_factor ), 
				(int) ( $settings['amount_with_symbol_y'] * $scale_factor ),
				$settings['amount_with_symbol_align'] ?? 'center'
			);
		}

		// Draw amount text if enabled
		if ( $settings['show_amount'] ) {
			$color = $this->hex_to_rgb( $settings['amount_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$this->draw_text_on_image( 
				$image, 
				$test_data['amount'], 
				$settings['amount_font'], 
				(int) ( $settings['amount_size'] * $scale_factor ), 
				$text_color, 
				(int) ( $settings['amount_x'] * $scale_factor ), 
				(int) ( $settings['amount_y'] * $scale_factor ),
				$settings['amount_align'] ?? 'center'
			);
		}

		// Draw token symbol text if enabled
		if ( $settings['show_token_symbol'] ) {
			$color = $this->hex_to_rgb( $settings['token_symbol_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$this->draw_text_on_image( 
				$image, 
				$test_data['token_symbol'], 
				$settings['token_symbol_font'], 
				(int) ( $settings['token_symbol_size'] * $scale_factor ), 
				$text_color, 
				(int) ( $settings['token_symbol_x'] * $scale_factor ), 
				(int) ( $settings['token_symbol_y'] * $scale_factor ),
				$settings['token_symbol_align'] ?? 'center'
			);
		}

		// Draw network name text if enabled
		if ( $settings['show_network_name'] ) {
			$color = $this->hex_to_rgb( $settings['network_name_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$this->draw_text_on_image( 
				$image, 
				$test_data['network_name'], 
				$settings['network_name_font'], 
				(int) ( $settings['network_name_size'] * $scale_factor ), 
				$text_color, 
				(int) ( $settings['network_name_x'] * $scale_factor ), 
				(int) ( $settings['network_name_y'] * $scale_factor ),
				$settings['network_name_align'] ?? 'center'
			);
		}

		// Draw chain logo if enabled
		if ( $settings['show_network_logo'] ) {
			$this->draw_chain_logo_on_image( $image, $test_data['chain_id'], (int) ( $settings['logo_height'] * $scale_factor ), (int) ( $settings['logo_x'] * $scale_factor ), (int) ( $settings['logo_y'] * $scale_factor ) );
		}

		// Draw token logo if enabled
		if ( $settings['show_token_logo'] ) {
			$this->draw_token_logo_on_image( $image, $test_data['token_symbol'], (int) ( $settings['token_height'] * $scale_factor ), (int) ( $settings['token_x'] * $scale_factor ), (int) ( $settings['token_y'] * $scale_factor ) );
		}

		// Get image data with optimal compression (level 6-9 provides best balance)
		ob_start();
		imagepng( $image, null, 6 );
		$image_data = ob_get_clean();
		imagedestroy( $image );

		if ( empty( $image_data ) ) {
			return new WP_Error( 'image_generate', esc_html__( 'Failed to generate image data.', 'univoucher-for-woocommerce' ) );
		}

		return $image_data;
	}

	/**
	 * Get current image template settings.
	 *
	 * @return array Current settings array.
	 */
	public static function get_current_settings() {
		return array(
			'template' => get_option( 'univoucher_wc_image_template', 'UniVoucher-wide-4x3.png' ),
			'show_amount_with_symbol' => get_option( 'univoucher_wc_image_show_amount_with_symbol', true ),
			'show_amount' => get_option( 'univoucher_wc_image_show_amount', false ),
			'show_token_symbol' => get_option( 'univoucher_wc_image_show_token_symbol', false ),
			'show_network_name' => get_option( 'univoucher_wc_image_show_network_name', true ),
			'show_token_logo' => get_option( 'univoucher_wc_image_show_token_logo', true ),
			'show_network_logo' => get_option( 'univoucher_wc_image_show_network_logo', true ),
			
			// Amount with symbol text settings
			'amount_with_symbol_font' => get_option( 'univoucher_wc_image_amount_with_symbol_font', 'Inter-Bold.ttf' ),
			'amount_with_symbol_size' => get_option( 'univoucher_wc_image_amount_with_symbol_size', 69 ),
			'amount_with_symbol_color' => get_option( 'univoucher_wc_image_amount_with_symbol_color', '#1f2937' ),
			'amount_with_symbol_align' => get_option( 'univoucher_wc_image_amount_with_symbol_align', 'center' ),
			'amount_with_symbol_x' => get_option( 'univoucher_wc_image_amount_with_symbol_x', 411 ),
			'amount_with_symbol_y' => get_option( 'univoucher_wc_image_amount_with_symbol_y', 315 ),
			
			// Amount text settings
			'amount_font' => get_option( 'univoucher_wc_image_amount_font', 'Inter-Bold.ttf' ),
			'amount_size' => get_option( 'univoucher_wc_image_amount_size', 20 ),
			'amount_color' => get_option( 'univoucher_wc_image_amount_color', '#dd3333' ),
			'amount_align' => get_option( 'univoucher_wc_image_amount_align', 'right' ),
			'amount_x' => get_option( 'univoucher_wc_image_amount_x', 53 ),
			'amount_y' => get_option( 'univoucher_wc_image_amount_y', 21 ),
			
			// Token symbol text settings
			'token_symbol_font' => get_option( 'univoucher_wc_image_token_symbol_font', 'Inter-Bold.ttf' ),
			'token_symbol_size' => get_option( 'univoucher_wc_image_token_symbol_size', 20 ),
			'token_symbol_color' => get_option( 'univoucher_wc_image_token_symbol_color', '#dd3333' ),
			'token_symbol_align' => get_option( 'univoucher_wc_image_token_symbol_align', 'left' ),
			'token_symbol_x' => get_option( 'univoucher_wc_image_token_symbol_x', 33 ),
			'token_symbol_y' => get_option( 'univoucher_wc_image_token_symbol_y', 48 ),
			
			// Network name text settings
			'network_name_font' => get_option( 'univoucher_wc_image_network_name_font', 'Inter-Bold.ttf' ),
			'network_name_size' => get_option( 'univoucher_wc_image_network_name_size', 27 ),
			'network_name_color' => get_option( 'univoucher_wc_image_network_name_color', '#1f2937' ),
			'network_name_align' => get_option( 'univoucher_wc_image_network_name_align', 'left' ),
			'network_name_x' => get_option( 'univoucher_wc_image_network_name_x', 147 ),
			'network_name_y' => get_option( 'univoucher_wc_image_network_name_y', 452 ),
			
			// Logo settings
			'logo_height' => get_option( 'univoucher_wc_image_logo_height', 33 ),
			'logo_x' => get_option( 'univoucher_wc_image_logo_x', 125 ),
			'logo_y' => get_option( 'univoucher_wc_image_logo_y', 452 ),
			'token_height' => get_option( 'univoucher_wc_image_token_height', 68 ),
			'token_x' => get_option( 'univoucher_wc_image_token_x', 649 ),
			'token_y' => get_option( 'univoucher_wc_image_token_y', 177 ),
		);
	}

	/**
	 * AJAX handler for getting custom resources.
	 */
	public function ajax_get_custom_resources() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$custom_resources = $this->get_custom_resources();
		wp_send_json_success( $custom_resources );
	}

	/**
	 * AJAX handler for uploading custom resources.
	 */
	public function ajax_upload_resource() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check if file was uploaded.
		if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded or upload error.', 'univoucher-for-woocommerce' ) ) );
		}

		$upload_type = sanitize_text_field( $_POST['upload_type'] ?? '' );
		$token_symbol = sanitize_text_field( $_POST['token_symbol'] ?? '' );

		// Validate file type.
		$allowed_types = array( 'image/png', 'application/x-font-ttf', 'font/ttf', 'application/octet-stream' );
		$file_ext = strtolower( pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, array( 'png', 'ttf' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type. Only PNG and TTF files are allowed.', 'univoucher-for-woocommerce' ) ) );
		}

		$plugin_dir = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/';

		try {
			// Determine target directory and filename based on upload type.
			if ( $file_ext === 'ttf' ) {
				// Upload font.
				$target_dir = $plugin_dir . 'fonts/';
				$target_filename = basename( $_FILES['file']['name'] );
			} elseif ( $upload_type === 'template' ) {
				// Upload template.
				$target_dir = $plugin_dir . 'images/templates/';
				$target_filename = basename( $_FILES['file']['name'] );
			} elseif ( $upload_type === 'token' && ! empty( $token_symbol ) ) {
				// Upload token logo.
				$target_dir = $plugin_dir . 'images/tokens/';
				$target_filename = strtolower( $token_symbol ) . '.png';
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid upload parameters.', 'univoucher-for-woocommerce' ) ) );
			}

			// Create directory if it doesn't exist.
			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			$target_file = $target_dir . $target_filename;

			// Check if file already exists to prevent overwriting.
			if ( file_exists( $target_file ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'File already exists. Please choose a different name or delete the existing file first.', 'univoucher-for-woocommerce' ) ) );
			}

			// Use WordPress file handling functions instead of move_uploaded_file.
			$upload_overrides = array(
				'test_form' => false,
				'unique_filename_callback' => function( $dir, $name, $ext ) use ( $target_filename ) {
					return $target_filename;
				}
			);

			$uploaded_file = wp_handle_upload( $_FILES['file'], $upload_overrides );

			if ( isset( $uploaded_file['error'] ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Upload failed: ', 'univoucher-for-woocommerce' ) . $uploaded_file['error'] ) );
			}

			// Move the file from WordPress uploads directory to our target directory.
			if ( ! copy( $uploaded_file['file'], $target_file ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to move uploaded file to target directory.', 'univoucher-for-woocommerce' ) ) );
			}

			// Clean up the temporary file in WordPress uploads directory.
			wp_delete_file( $uploaded_file['file'] );

			wp_send_json_success( array( 
				'message' => esc_html__( 'File uploaded successfully.', 'univoucher-for-woocommerce' ),
				'filename' => $target_filename
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Upload failed: ', 'univoucher-for-woocommerce' ) . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for deleting custom resources.
	 */
	public function ajax_delete_resource() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_image_templates_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$filename = sanitize_text_field( $_POST['filename'] ?? '' );
		$file_type = sanitize_text_field( $_POST['file_type'] ?? '' );

		if ( empty( $filename ) || empty( $file_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'univoucher-for-woocommerce' ) ) );
		}

		$plugin_dir = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/';

		// Determine file path based on type.
		switch ( $file_type ) {
			case 'font':
				$file_path = $plugin_dir . 'fonts/' . $filename;
				break;
			case 'template':
				$file_path = $plugin_dir . 'images/templates/' . $filename;
				break;
			case 'token':
				$file_path = $plugin_dir . 'images/tokens/' . $filename;
				break;
			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type.', 'univoucher-for-woocommerce' ) ) );
		}

		// Delete the file.
		if ( file_exists( $file_path ) && unlink( $file_path ) ) {
			wp_send_json_success( array( 'message' => esc_html__( 'File deleted successfully.', 'univoucher-for-woocommerce' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete file.', 'univoucher-for-woocommerce' ) ) );
		}
	}

	/**
	 * Get custom resources by comparing existing files with default lists.
	 */
	private function get_custom_resources() {
		$plugin_dir = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/';
		$custom_resources = array();

		// Define default files that should not be considered custom.
		$default_fonts = array( 
			'Inter-Bold.ttf',
			'Martius.ttf',
			'MartiusItalic.ttf',
			'BorderWall.otf',
			'MinnePetat.ttf',
			'AreaKilometer50.ttf',
			'Excluded.ttf',
			'Excludeditalic.ttf',
			'Freedom.ttf',
			'Swansea.ttf',
			'SwanseaBold.ttf',
			'SwanseaBoldItalic.ttf',
			'MoonHouse.ttf'
		);
		$default_templates = array( 'UniVoucher-wide-4x3.png', 'UniVoucher-square.png', 'UniVoucher-wide-croped.png' );
		$default_tokens = array( 'arb.png', 'avax.png', 'bnb.png', 'eth.png', 'op.png', 'pol.png', 'token.png', 'usdc.png', 'usdt.png' );

		// Check fonts directory.
		$fonts_dir = $plugin_dir . 'fonts/';
		if ( is_dir( $fonts_dir ) ) {
			$font_files = scandir( $fonts_dir );
			foreach ( $font_files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'ttf' && ! in_array( $file, $default_fonts, true ) ) {
					$custom_resources[] = array(
						'filename' => $file,
						'type' => 'font',
						'is_custom' => true
					);
				}
			}
		}

		// Check templates directory.
		$templates_dir = $plugin_dir . 'images/templates/';
		if ( is_dir( $templates_dir ) ) {
			$template_files = scandir( $templates_dir );
			foreach ( $template_files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'png' && ! in_array( $file, $default_templates, true ) ) {
					$custom_resources[] = array(
						'filename' => $file,
						'type' => 'template',
						'is_custom' => true
					);
				}
			}
		}

		// Check tokens directory.
		$tokens_dir = $plugin_dir . 'images/tokens/';
		if ( is_dir( $tokens_dir ) ) {
			$token_files = scandir( $tokens_dir );
			foreach ( $token_files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'png' && ! in_array( $file, $default_tokens, true ) ) {
					$custom_resources[] = array(
						'filename' => $file,
						'type' => 'token',
						'is_custom' => true
					);
				}
			}
		}

		return $custom_resources;
	}

	/**
	 * Convert hex color to RGB array.
	 *
	 * @param string $hex Hex color code.
	 * @return array RGB values.
	 */
	public function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Multistage resize for maximum quality when scaling down significantly.
	 * Uses progressive halving with bilinear interpolation, followed by final bicubic step.
	 *
	 * @param resource $source_image Source image resource.
	 * @param int      $source_width Source width.
	 * @param int      $source_height Source height.
	 * @param int      $target_width Target width.
	 * @param int      $target_height Target height.
	 * @return resource Resized image resource.
	 */
	public function multistage_resize( $source_image, $source_width, $source_height, $target_width, $target_height ) {
		$current_image = $source_image;
		$current_width = $source_width;
		$current_height = $source_height;
		
		// Calculate target ratio to maintain proportions
		$target_ratio = min( $target_width / $source_width, $target_height / $source_height );
		
		// Progressive halving while ratio > 0.5
		while ( $target_ratio < 0.5 && ( $current_width > $target_width * 2 || $current_height > $target_height * 2 ) ) {
			$new_width = max( intval( $current_width / 2 ), $target_width );
			$new_height = max( intval( $current_height / 2 ), $target_height );
			
			// Create intermediate image
			$intermediate = imagecreatetruecolor( $new_width, $new_height );
			
			// Preserve transparency
			imagealphablending( $intermediate, false );
			imagesavealpha( $intermediate, true );
			$transparent = imagecolorallocatealpha( $intermediate, 255, 255, 255, 127 );
			imagefill( $intermediate, 0, 0, $transparent );
			imagealphablending( $intermediate, true );
			
			// Resize with bilinear interpolation (faster for intermediate steps)
			imagecopyresampled(
				$intermediate,
				$current_image,
				0, 0, 0, 0,
				$new_width, $new_height,
				$current_width, $current_height
			);
			
			// Clean up previous image if it's not the source
			if ( $current_image !== $source_image ) {
				imagedestroy( $current_image );
			}
			
			$current_image = $intermediate;
			$current_width = $new_width;
			$current_height = $new_height;
			$target_ratio = min( $target_width / $current_width, $target_height / $current_height );
		}
		
		// Final resize to exact dimensions with bicubic interpolation
		if ( $current_width !== $target_width || $current_height !== $target_height ) {
			$final_image = imagecreatetruecolor( $target_width, $target_height );
			
			// Preserve transparency
			imagealphablending( $final_image, false );
			imagesavealpha( $final_image, true );
			$transparent = imagecolorallocatealpha( $final_image, 255, 255, 255, 127 );
			imagefill( $final_image, 0, 0, $transparent );
			imagealphablending( $final_image, true );
			
			// Final resize with bicubic interpolation
			imagecopyresampled(
				$final_image,
				$current_image,
				0, 0, 0, 0,
				$target_width, $target_height,
				$current_width, $current_height
			);
			
			// Clean up intermediate image if it's not the source
			if ( $current_image !== $source_image ) {
				imagedestroy( $current_image );
			}
			
			return $final_image;
		}
		
		return $current_image;
	}

	/**
	 * Draw text on image.
	 *
	 * @param resource $image      Image resource.
	 * @param string   $text       Text to draw.
	 * @param string   $font       Font filename.
	 * @param int      $size       Font size.
	 * @param int      $text_color Text color.
	 * @param int      $text_x     Text X position.
	 * @param int      $text_y     Text Y position.
	 * @param string   $align      Text alignment (left, center, right).
	 */
	public function draw_text_on_image( $image, $text, $font, $size, $text_color, $text_x, $text_y, $align = 'center' ) {
		$font_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/fonts/' . $font;
		
		if ( file_exists( $font_path ) && function_exists( 'imagettftext' ) ) {
			$angle = 0;

			// Convert pixel size to points for GD (pixels * 0.75 = points)
			$size_in_points = $size * 0.72;

			// Get text dimensions for positioning.
			$text_box = imagettfbbox( $size_in_points, $angle, $font_path, $text );
			$text_width = $text_box[4] - $text_box[0];
			$text_height = $text_box[1] - $text_box[7];

			// Calculate X position based on alignment.
			switch ( $align ) {
				case 'left':
					$x = intval( $text_x );
					break;
				case 'right':
					$x = intval( $text_x - $text_width );
					break;
				case 'center':
				default:
					$x = intval( $text_x - ( $text_width / 2 ) );
					break;
			}

			// Adjust Y position for vertical centering on baseline.
			$y = intval( $text_y + ( $text_height / 2 ) );

			// Draw the text.
			imagettftext( $image, $size_in_points, $angle, $x, $y, $text_color, $font_path, $text );
		} else {
			// Fallback to built-in font if TTF not available.
			$text_width = imagefontwidth( 5 ) * strlen( $text );
			$text_height = imagefontheight( 5 );
			
			// Calculate X position based on alignment.
			switch ( $align ) {
				case 'left':
					$x = intval( $text_x );
					break;
				case 'right':
					$x = intval( $text_x - $text_width );
					break;
				case 'center':
				default:
					$x = intval( $text_x - ( $text_width / 2 ) );
					break;
			}
			
			$y = intval( $text_y - ( $text_height / 2 ) );
			
			imagestring( $image, 5, $x, $y, $text, $text_color );
		}
	}

	/**
	 * Draw chain logo on image.
	 *
	 * @param resource $image       Image resource.
	 * @param int      $chain_id    Chain ID.
	 * @param int      $logo_height Logo height.
	 * @param int      $logo_x      Logo X position.
	 * @param int      $logo_y      Logo Y position.
	 */
	public function draw_chain_logo_on_image( $image, $chain_id, $logo_height, $logo_x, $logo_y ) {
		// Use chain ID as filename
		$logo_filename = $chain_id . '.png';
		$logo_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/networks/' . $logo_filename;

		if ( ! file_exists( $logo_path ) ) {
			return; // Skip if logo not found.
		}

		// Load logo image.
		$logo_image = imagecreatefrompng( $logo_path );
		if ( ! $logo_image ) {
			return; // Skip if failed to load.
		}

		// Get original logo dimensions.
		$original_width = imagesx( $logo_image );
		$original_height = imagesy( $logo_image );

		// Calculate new width maintaining aspect ratio.
		$logo_width = intval( ( $logo_height / $original_height ) * $original_width );

		// Use multistage resizing for maximum quality when scaling down significantly.
		$scale_factor = min( $logo_width / $original_width, $logo_height / $original_height );
		
		if ( $scale_factor < 0.5 ) {
			// Multistage resizing for better quality
			$resized_logo = $this->multistage_resize( $logo_image, $original_width, $original_height, $logo_width, $logo_height );
		} else {
			// Single-stage resizing for smaller reductions
			$resized_logo = imagecreatetruecolor( $logo_width, $logo_height );
			
			// Preserve transparency with high quality.
			imagealphablending( $resized_logo, false );
			imagesavealpha( $resized_logo, true );
			$transparent = imagecolorallocatealpha( $resized_logo, 255, 255, 255, 127 );
			imagefill( $resized_logo, 0, 0, $transparent );
			imagealphablending( $resized_logo, true );

			// Resize the logo with bicubic interpolation for better quality.
			imagecopyresampled(
				$resized_logo,
				$logo_image,
				0,
				0,
				0,
				0,
				$logo_width,
				$logo_height,
				$original_width,
				$original_height
			);
		}

		// Calculate position (center the logo on the given coordinates).
		$dest_x = intval( $logo_x - ( $logo_width / 2 ) );
		$dest_y = intval( $logo_y - ( $logo_height / 2 ) );

		// Draw logo on main image with alpha blending.
		imagecopy( $image, $resized_logo, $dest_x, $dest_y, 0, 0, $logo_width, $logo_height );

		// Clean up.
		imagedestroy( $logo_image );
		imagedestroy( $resized_logo );
	}

	/**
	 * Draw token logo on image.
	 *
	 * @param resource $image        Image resource.
	 * @param string   $token_symbol Token symbol.
	 * @param int      $token_height Token height.
	 * @param int      $token_x      Token X position.
	 * @param int      $token_y      Token Y position.
	 */
	public function draw_token_logo_on_image( $image, $token_symbol, $token_height, $token_x, $token_y ) {
		// Try specific token logo first, fallback to generic token.png
		$token_filename = strtolower( $token_symbol ) . '.png';
		$token_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/tokens/' . $token_filename;
		
		if ( ! file_exists( $token_path ) ) {
			// Fallback to generic token logo
			$token_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/tokens/token.png';
		}

		if ( ! file_exists( $token_path ) ) {
			return; // Skip if no token logo found.
		}

		// Load token image.
		$token_image = imagecreatefrompng( $token_path );
		if ( ! $token_image ) {
			return; // Skip if failed to load.
		}

		// Get original token dimensions.
		$original_width = imagesx( $token_image );
		$original_height = imagesy( $token_image );

		// Calculate new width maintaining aspect ratio.
		$token_width = intval( ( $token_height / $original_height ) * $original_width );

		// Use multistage resizing for maximum quality when scaling down significantly.
		$scale_factor = min( $token_width / $original_width, $token_height / $original_height );
		
		if ( $scale_factor < 0.5 ) {
			// Multistage resizing for better quality
			$resized_token = $this->multistage_resize( $token_image, $original_width, $original_height, $token_width, $token_height );
		} else {
			// Single-stage resizing for smaller reductions
			$resized_token = imagecreatetruecolor( $token_width, $token_height );
			
			// Preserve transparency with high quality.
			imagealphablending( $resized_token, false );
			imagesavealpha( $resized_token, true );
			$transparent = imagecolorallocatealpha( $resized_token, 255, 255, 255, 127 );
			imagefill( $resized_token, 0, 0, $transparent );
			imagealphablending( $resized_token, true );

			// Resize the token logo with bicubic interpolation for better quality.
			imagecopyresampled(
				$resized_token,
				$token_image,
				0,
				0,
				0,
				0,
				$token_width,
				$token_height,
				$original_width,
				$original_height
			);
		}

		// Calculate position (center the token on the given coordinates).
		$dest_x = intval( $token_x - ( $token_width / 2 ) );
		$dest_y = intval( $token_y - ( $token_height / 2 ) );

		// Draw token logo on main image with alpha blending.
		imagecopy( $image, $resized_token, $dest_x, $dest_y, 0, 0, $token_width, $token_height );

		// Clean up.
		imagedestroy( $token_image );
		imagedestroy( $resized_token );
	}

	/**
	 * Render custom layout for Image Templates tab (bypasses WordPress settings API).
	 */
	public function render_custom_layout() {
		// Get image template settings
		$selected_template = get_option( 'univoucher_wc_image_template', 'UniVoucher-wide-4x3.png' );
		
		// Get visibility settings
		$show_amount_with_symbol = get_option( 'univoucher_wc_image_show_amount_with_symbol', true );
		$show_amount = get_option( 'univoucher_wc_image_show_amount', false );
		$show_token_symbol = get_option( 'univoucher_wc_image_show_token_symbol', false );
		$show_network_name = get_option( 'univoucher_wc_image_show_network_name', true );
		$show_token_logo = get_option( 'univoucher_wc_image_show_token_logo', true );
		$show_network_logo = get_option( 'univoucher_wc_image_show_network_logo', true );
		
		// Get amount with symbol text styling settings
		$amount_with_symbol_font = get_option( 'univoucher_wc_image_amount_with_symbol_font', 'Inter-Bold.ttf' );
		$amount_with_symbol_size = get_option( 'univoucher_wc_image_amount_with_symbol_size', 69 );
		$amount_with_symbol_color = get_option( 'univoucher_wc_image_amount_with_symbol_color', '#1f2937' );
		$amount_with_symbol_align = get_option( 'univoucher_wc_image_amount_with_symbol_align', 'center' );
		$amount_with_symbol_x = get_option( 'univoucher_wc_image_amount_with_symbol_x', 411 );
		$amount_with_symbol_y = get_option( 'univoucher_wc_image_amount_with_symbol_y', 315 );
		
		// Get amount text styling settings
		$amount_font = get_option( 'univoucher_wc_image_amount_font', 'Inter-Bold.ttf' );
		$amount_size = get_option( 'univoucher_wc_image_amount_size', 20 );
		$amount_color = get_option( 'univoucher_wc_image_amount_color', '#dd3333' );
		$amount_align = get_option( 'univoucher_wc_image_amount_align', 'right' );
		$amount_x = get_option( 'univoucher_wc_image_amount_x', 53 );
		$amount_y = get_option( 'univoucher_wc_image_amount_y', 21 );
		
		// Get token symbol text styling settings
		$token_symbol_font = get_option( 'univoucher_wc_image_token_symbol_font', 'Inter-Bold.ttf' );
		$token_symbol_size = get_option( 'univoucher_wc_image_token_symbol_size', 20 );
		$token_symbol_color = get_option( 'univoucher_wc_image_token_symbol_color', '#dd3333' );
		$token_symbol_align = get_option( 'univoucher_wc_image_token_symbol_align', 'left' );
		$token_symbol_x = get_option( 'univoucher_wc_image_token_symbol_x', 33 );
		$token_symbol_y = get_option( 'univoucher_wc_image_token_symbol_y', 48 );
		
		// Get network name text styling settings
		$network_name_font = get_option( 'univoucher_wc_image_network_name_font', 'Inter-Bold.ttf' );
		$network_name_size = get_option( 'univoucher_wc_image_network_name_size', 27 );
		$network_name_color = get_option( 'univoucher_wc_image_network_name_color', '#1f2937' );
		$network_name_align = get_option( 'univoucher_wc_image_network_name_align', 'left' );
		$network_name_x = get_option( 'univoucher_wc_image_network_name_x', 147 );
		$network_name_y = get_option( 'univoucher_wc_image_network_name_y', 452 );
		
		// Get network logo settings
		$logo_height = get_option( 'univoucher_wc_image_logo_height', 33 );
		$logo_x = get_option( 'univoucher_wc_image_logo_x', 125 );
		$logo_y = get_option( 'univoucher_wc_image_logo_y', 452 );
		
		// Get token logo settings
		$token_height = get_option( 'univoucher_wc_image_token_height', 68 );
		$token_x = get_option( 'univoucher_wc_image_token_x', 649 );
		$token_y = get_option( 'univoucher_wc_image_token_y', 177 );
		
		$templates = $this->get_available_templates();
		$fonts = $this->get_available_fonts();
		$initial_template_url = plugins_url( 'admin/images/templates/' . $selected_template, UNIVOUCHER_WC_PLUGIN_FILE );
		$chain_logo_url = plugins_url( 'admin/images/networks/1.png', UNIVOUCHER_WC_PLUGIN_FILE );
		$token_logo_url = plugins_url( 'admin/images/tokens/usdt.png', UNIVOUCHER_WC_PLUGIN_FILE );
		?>
		
		<!-- Container with sidebar layout -->
		<div class="univoucher-settings-container" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
			
			<!-- Settings Sidebar -->
			<div class="univoucher-settings-sidebar" style="flex: 0 0 250px; min-width: 250px; background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #ddd;">
				
				<!-- Basic Template Settings -->
				<div class="settings-group" style="margin-bottom: 25px;">
					<h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #23282d; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
						<?php esc_html_e( 'Template Settings', 'univoucher-for-woocommerce' ); ?>
					</h4>
					
					<div style="margin-bottom: 15px;">
						<label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 12px;">
							<?php esc_html_e( 'Background Template', 'univoucher-for-woocommerce' ); ?>
						</label>
						<select id="univoucher_wc_image_template" name="univoucher_wc_image_template" style="width: 100%; font-size: 12px;">
							<?php foreach ( $templates as $template ) : ?>
								<option value="<?php echo esc_attr( $template['filename'] ); ?>" <?php selected( $selected_template, $template['filename'] ); ?>>
									<?php echo esc_html( $template['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				
				<!-- Element Visibility -->
				<div class="settings-group" style="margin-bottom: 25px;">
					<h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #23282d; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
						<?php esc_html_e( 'Element Visibility', 'univoucher-for-woocommerce' ); ?>
					</h4>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_amount_with_symbol" name="univoucher_wc_image_show_amount_with_symbol" value="1" <?php checked( $show_amount_with_symbol ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Amount with Symbol', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_amount" name="univoucher_wc_image_show_amount" value="1" <?php checked( $show_amount ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Amount', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_token_symbol" name="univoucher_wc_image_show_token_symbol" value="1" <?php checked( $show_token_symbol ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Token Symbol', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_network_name" name="univoucher_wc_image_show_network_name" value="1" <?php checked( $show_network_name ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Network Name', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_token_logo" name="univoucher_wc_image_show_token_logo" value="1" <?php checked( $show_token_logo ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Token Logo', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
					
					<div style="margin-bottom: 10px;">
						<label style="display: flex; align-items: center; font-size: 12px;">
							<input type="checkbox" id="univoucher_wc_image_show_network_logo" name="univoucher_wc_image_show_network_logo" value="1" <?php checked( $show_network_logo ); ?> style="margin-right: 8px;" />
							<?php esc_html_e( 'Show Network Logo', 'univoucher-for-woocommerce' ); ?>
						</label>
					</div>
				</div>
				
				<!-- Custom Resources -->
				<div class="settings-group" style="margin-bottom: 25px;">
					<h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #23282d; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
						<?php esc_html_e( 'Custom Resources', 'univoucher-for-woocommerce' ); ?>
					</h4>
					
					<p style="margin: 0 0 15px 0; font-size: 12px; color: #666; line-height: 1.4;">
						<?php esc_html_e( 'Upload and manage your custom background templates, fonts, and token logos.', 'univoucher-for-woocommerce' ); ?>
					</p>
					
					<!-- Custom Resources Table -->
					<div id="custom-resources-table" style="margin-bottom: 15px;">
						<table style="width: 100%; font-size: 11px; border-collapse: collapse;">
							<thead>
								<tr style="background: #f0f0f0;">
									<th style="padding: 6px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'File Name', 'univoucher-for-woocommerce' ); ?></th>
									<th style="padding: 6px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'Type', 'univoucher-for-woocommerce' ); ?></th>
									<th style="padding: 6px; text-align: center; border: 1px solid #ddd;"><?php esc_html_e( 'Action', 'univoucher-for-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody id="custom-resources-tbody">
								<!-- Table content will be populated by JavaScript -->
							</tbody>
						</table>
					</div>
					
					<!-- Upload Form -->
					<div id="upload-form" style="border: 1px dashed #ccc; padding: 15px; border-radius: 4px; background: #fafafa;">
						<input type="file" id="resource-upload" accept=".png,.ttf" style="margin-bottom: 10px; font-size: 11px;" />
						<p style="margin: 0 0 10px 0; font-size: 11px; color: #666; font-style: italic;">
							<?php esc_html_e( 'Only PNG and TTF files are supported.', 'univoucher-for-woocommerce' ); ?>
						</p>
						
						<!-- Upload Details (hidden by default) -->
						<div id="upload-details" style="display: none; margin-top: 10px;">
							<!-- Font Upload -->
							<div id="font-upload-section" style="display: none;">
								<p style="margin: 5px 0; font-size: 11px;"><strong><?php esc_html_e( 'Font Name:', 'univoucher-for-woocommerce' ); ?></strong> <span id="font-name"></span></p>
								<button type="button" id="upload-font-btn" class="button button-small"><?php esc_html_e( 'Upload Font', 'univoucher-for-woocommerce' ); ?></button>
							</div>
							
							<!-- PNG Upload -->
							<div id="png-upload-section" style="display: none;">
								<div style="margin-bottom: 10px;">
									<label style="display: block; margin-bottom: 5px; font-size: 11px;"><strong><?php esc_html_e( 'Upload Type:', 'univoucher-for-woocommerce' ); ?></strong></label>
									<label style="display: block; margin-bottom: 3px; font-size: 11px;">
										<input type="radio" name="png-type" value="template" style="margin-right: 5px;" />
										<?php esc_html_e( 'Background Template', 'univoucher-for-woocommerce' ); ?>
									</label>
									<label style="display: block; font-size: 11px;">
										<input type="radio" name="png-type" value="token" style="margin-right: 5px;" />
										<?php esc_html_e( 'Token Logo', 'univoucher-for-woocommerce' ); ?>
									</label>
								</div>
								
								<!-- Template Upload -->
								<div id="template-upload-section" style="display: none;">
									<p style="margin: 5px 0; font-size: 11px;"><strong><?php esc_html_e( 'Template Name:', 'univoucher-for-woocommerce' ); ?></strong> <span id="template-name"></span></p>
									<button type="button" id="upload-template-btn" class="button button-small"><?php esc_html_e( 'Upload Template', 'univoucher-for-woocommerce' ); ?></button>
								</div>
								
								<!-- Token Upload -->
								<div id="token-upload-section" style="display: none;">
									<label style="display: block; margin: 5px 0; font-size: 11px;">
										<strong><?php esc_html_e( 'Token Symbol:', 'univoucher-for-woocommerce' ); ?></strong>
										<input type="text" id="token-symbol" placeholder="e.g. USDT" style="margin-left: 5px; width: 80px; font-size: 11px;" />
									</label>
									<button type="button" id="upload-token-btn" class="button button-small"><?php esc_html_e( 'Upload Token Logo', 'univoucher-for-woocommerce' ); ?></button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		
			<!-- Visual Editor Main Area -->
			<div class="univoucher-visual-editor" style="flex: 1; min-width: 300px; background: #fff; padding: 0; border-radius: 8px; border: 1px solid #ddd; overflow: hidden;">
				
				<!-- Element Style Controllers -->
				<div class="univoucher-element-controllers">
					<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
						
						<!-- Text Controls (for text elements) -->
						<div id="text-controls" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
							<div style="display: flex; align-items: center; gap: 8px;">
								<label style="font-size: 11px; color: #6c757d; font-weight: 500;"><?php esc_html_e( 'Font', 'univoucher-for-woocommerce' ); ?></label>
								<div id="element-font" class="custom-font-picker disabled" style="min-width: 160px; font-size: 13px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff; cursor: pointer; position: relative;">
									<div class="font-picker-selected" style="padding: 6px 10px; display: flex; justify-content: space-between; align-items: center;">
										<span class="font-picker-text"><?php esc_html_e( 'Select Font', 'univoucher-for-woocommerce' ); ?></span>
										<span class="font-picker-arrow">▼</span>
									</div>
									<div class="font-picker-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; max-height: 350px; overflow-y: auto; z-index: 1000; display: none;">
										<?php foreach ( $fonts as $font ) : ?>
											<div class="font-option" data-value="<?php echo esc_attr( $font['filename'] ); ?>" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-family: '<?php echo esc_attr( $font['css_name'] ); ?>', sans-serif; font-size: 16px;">
												<?php echo esc_html( $font['name'] ); ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
							
							<div style="display: flex; align-items: center; gap: 8px;">
								<label style="font-size: 11px; color: #6c757d; font-weight: 500;"><?php esc_html_e( 'Size', 'univoucher-for-woocommerce' ); ?></label>
								<input type="number" id="element-size" min="8" max="200" disabled style="width: 60px; font-size: 11px; padding: 4px 8px;" />
							</div>
							
							<div style="display: flex; align-items: center; gap: 8px;">
								<label style="font-size: 11px; color: #6c757d; font-weight: 500;"><?php esc_html_e( 'Color', 'univoucher-for-woocommerce' ); ?></label>
								<input type="text" id="element-color" disabled class="color-picker" data-default-color="#000000" style="width: 60px;" />
							</div>
							
							<div style="display: flex; align-items: center; gap: 8px;">
								<label style="font-size: 11px; color: #6c757d; font-weight: 500;"><?php esc_html_e( 'Align', 'univoucher-for-woocommerce' ); ?></label>
								<select id="element-align" disabled style="font-size: 11px; padding: 4px 8px;">
									<option value="left"><?php esc_html_e( 'Left', 'univoucher-for-woocommerce' ); ?></option>
									<option value="center"><?php esc_html_e( 'Center', 'univoucher-for-woocommerce' ); ?></option>
									<option value="right"><?php esc_html_e( 'Right', 'univoucher-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>
						

						
						<!-- Help Text -->
						<div id="no-selection-help" style="margin-left: auto; font-style: italic; color: #6c757d; font-size: 12px;">
							<?php esc_html_e( 'Click on an element to edit its properties', 'univoucher-for-woocommerce' ); ?>
						</div>
					</div>
				</div>
				
				<!-- Visual Editor Content -->
				<div style="padding: 20px;">
					<div id="univoucher-image-editor-container" style="width: 100%; text-align: center;">
						<div id="univoucher-image-editor" style="position: relative; display: inline-block; max-width: 100%; overflow: hidden;">
							<img id="univoucher-template-preview" src="<?php echo esc_url( $initial_template_url ); ?>" alt="<?php esc_attr_e( 'Template Preview', 'univoucher-for-woocommerce' ); ?>" style="display: block; max-width: 100%; height: auto;" />
							<div id="univoucher-draggable-amount-with-symbol" class="draggable-element" data-element-type="amount_with_symbol" style="position: absolute; cursor: move; <?php echo $show_amount_with_symbol ? '' : 'display: none;'; ?>">
								100 USDT
							</div>
							<div id="univoucher-draggable-amount" class="draggable-element" data-element-type="amount" style="position: absolute; cursor: move; <?php echo $show_amount ? '' : 'display: none;'; ?>">
								100
							</div>
							<div id="univoucher-draggable-token-symbol" class="draggable-element" data-element-type="token_symbol" style="position: absolute; cursor: move; <?php echo $show_token_symbol ? '' : 'display: none;'; ?>">
								USDT
							</div>
							<div id="univoucher-draggable-network-name" class="draggable-element" data-element-type="network_name" style="position: absolute; cursor: move; <?php echo $show_network_name ? '' : 'display: none;'; ?>">
								Ethereum
							</div>
							<div id="univoucher-draggable-logo" class="draggable-element" data-element-type="logo" style="position: absolute; cursor: move; <?php echo $show_network_logo ? '' : 'display: none;'; ?>">
								<img src="<?php echo esc_url( $chain_logo_url ); ?>" alt="<?php esc_attr_e( 'Chain Logo', 'univoucher-for-woocommerce' ); ?>" style="height: 40px; width: auto; display: block;" />
							</div>
							<div id="univoucher-draggable-token" class="draggable-element" data-element-type="token" style="position: absolute; cursor: move; <?php echo $show_token_logo ? '' : 'display: none;'; ?>">
								<img src="<?php echo esc_url( $token_logo_url ); ?>" alt="<?php esc_attr_e( 'Token Logo', 'univoucher-for-woocommerce' ); ?>" style="height: 30px; width: auto; display: block;" />
							</div>
						</div>
					</div>
				</div>
				
			</div>
		</div>
		
		<!-- Test Generation Section -->
		<div style="padding: 20px; border-top: 1px solid #dee2e6; background: #f8f9fa; margin-top: 20px; border-radius: 8px;">
			<div style="text-align: center;">
				<button type="button" id="univoucher-test-generation" class="button button-secondary">
					<?php esc_html_e( 'Generate Test Variations', 'univoucher-for-woocommerce' ); ?>
				</button>
				<span class="spinner" id="univoucher-test-spinner"></span>
				<div id="univoucher-test-result" style="margin-top: 15px;"></div>
			</div>
		</div>
		
		<!-- Hidden inputs to store all settings -->
		<div style="display: none;">
			<!-- Position inputs -->

			<input type="hidden" id="univoucher_wc_image_logo_x" name="univoucher_wc_image_logo_x" value="<?php echo esc_attr( $logo_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_logo_y" name="univoucher_wc_image_logo_y" value="<?php echo esc_attr( $logo_y ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_x" name="univoucher_wc_image_token_x" value="<?php echo esc_attr( $token_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_y" name="univoucher_wc_image_token_y" value="<?php echo esc_attr( $token_y ); ?>" />
			
			<!-- Individual element position inputs -->
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_x" name="univoucher_wc_image_amount_with_symbol_x" value="<?php echo esc_attr( $amount_with_symbol_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_y" name="univoucher_wc_image_amount_with_symbol_y" value="<?php echo esc_attr( $amount_with_symbol_y ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_x" name="univoucher_wc_image_amount_x" value="<?php echo esc_attr( $amount_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_y" name="univoucher_wc_image_amount_y" value="<?php echo esc_attr( $amount_y ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_x" name="univoucher_wc_image_token_symbol_x" value="<?php echo esc_attr( $token_symbol_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_y" name="univoucher_wc_image_token_symbol_y" value="<?php echo esc_attr( $token_symbol_y ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_x" name="univoucher_wc_image_network_name_x" value="<?php echo esc_attr( $network_name_x ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_y" name="univoucher_wc_image_network_name_y" value="<?php echo esc_attr( $network_name_y ); ?>" />
			
			<!-- Individual element style inputs -->
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_font" name="univoucher_wc_image_amount_with_symbol_font" value="<?php echo esc_attr( $amount_with_symbol_font ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_size" name="univoucher_wc_image_amount_with_symbol_size" value="<?php echo esc_attr( $amount_with_symbol_size ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_color" name="univoucher_wc_image_amount_with_symbol_color" value="<?php echo esc_attr( $amount_with_symbol_color ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_with_symbol_align" name="univoucher_wc_image_amount_with_symbol_align" value="<?php echo esc_attr( $amount_with_symbol_align ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_font" name="univoucher_wc_image_amount_font" value="<?php echo esc_attr( $amount_font ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_size" name="univoucher_wc_image_amount_size" value="<?php echo esc_attr( $amount_size ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_color" name="univoucher_wc_image_amount_color" value="<?php echo esc_attr( $amount_color ); ?>" />
			<input type="hidden" id="univoucher_wc_image_amount_align" name="univoucher_wc_image_amount_align" value="<?php echo esc_attr( $amount_align ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_font" name="univoucher_wc_image_token_symbol_font" value="<?php echo esc_attr( $token_symbol_font ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_size" name="univoucher_wc_image_token_symbol_size" value="<?php echo esc_attr( $token_symbol_size ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_color" name="univoucher_wc_image_token_symbol_color" value="<?php echo esc_attr( $token_symbol_color ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_symbol_align" name="univoucher_wc_image_token_symbol_align" value="<?php echo esc_attr( $token_symbol_align ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_font" name="univoucher_wc_image_network_name_font" value="<?php echo esc_attr( $network_name_font ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_size" name="univoucher_wc_image_network_name_size" value="<?php echo esc_attr( $network_name_size ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_color" name="univoucher_wc_image_network_name_color" value="<?php echo esc_attr( $network_name_color ); ?>" />
			<input type="hidden" id="univoucher_wc_image_network_name_align" name="univoucher_wc_image_network_name_align" value="<?php echo esc_attr( $network_name_align ); ?>" />
			<input type="hidden" id="univoucher_wc_image_logo_height" name="univoucher_wc_image_logo_height" value="<?php echo esc_attr( $logo_height ); ?>" />
			<input type="hidden" id="univoucher_wc_image_token_height" name="univoucher_wc_image_token_height" value="<?php echo esc_attr( $token_height ); ?>" />
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('.color-picker').wpColorPicker();
			
			// Toggle visibility of draggable elements based on checkboxes
			$('#univoucher_wc_image_show_amount_with_symbol').change(function() {
				$('#univoucher-draggable-amount-with-symbol').toggle(this.checked);
			});
			
			$('#univoucher_wc_image_show_amount').change(function() {
				$('#univoucher-draggable-amount').toggle(this.checked);
			});
			
			$('#univoucher_wc_image_show_token_symbol').change(function() {
				$('#univoucher-draggable-token-symbol').toggle(this.checked);
			});
			
			$('#univoucher_wc_image_show_network_name').change(function() {
				$('#univoucher-draggable-network-name').toggle(this.checked);
			});
			
			$('#univoucher_wc_image_show_token_logo').change(function() {
				$('#univoucher-draggable-token').toggle(this.checked);
			});
			
			$('#univoucher_wc_image_show_network_logo').change(function() {
				$('#univoucher-draggable-logo').toggle(this.checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Get network name from chain ID.
	 *
	 * @param int $chain_id Chain ID.
	 * @return string Network name.
	 */
	public static function get_network_name_by_chain_id( $chain_id ) {
		$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
		return isset( $networks[ $chain_id ] ) ? $networks[ $chain_id ]['name'] : 'Unknown Network';
	}
} 