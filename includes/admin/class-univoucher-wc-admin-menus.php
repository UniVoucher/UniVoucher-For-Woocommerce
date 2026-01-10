<?php
/**
 * UniVoucher For WooCommerce Admin Menus
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Admin_Menus class.
 */
class UniVoucher_WC_Admin_Menus {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Admin_Menus
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Admin_Menus Instance.
	 *
	 * @return UniVoucher_WC_Admin_Menus - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Admin_Menus Constructor.
	 */
	public function __construct() {
		$this->add_menus();
	}

	/**
	 * Add admin menus.
	 */
	private function add_menus() {
		// Official UniVoucher icon PNG (100x100px for crisp display, constrained with CSS)
		$univoucher_icon = plugins_url( 'admin/images/univoucher-icon-100px.png', UNIVOUCHER_WC_PLUGIN_FILE );

		// Add main menu page (pointing to Inventory instead of Dashboard).
		add_menu_page(
			esc_html__( 'UniVoucher', 'univoucher-for-woocommerce' ),
			esc_html__( 'UniVoucher', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-inventory',
			array( $this, 'inventory_page' ),
			$univoucher_icon,
			56
		);

		// Add Inventory Management submenu (same as parent).
		add_submenu_page(
			'univoucher-inventory',
			esc_html__( 'Store Inventory', 'univoucher-for-woocommerce' ),
			esc_html__( 'Store Inventory', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-inventory',
			array( $this, 'inventory_page' )
		);

		// Add Promotions submenu.
		add_submenu_page(
			'univoucher-inventory',
			esc_html__( 'Promotions', 'univoucher-for-woocommerce' ),
			esc_html__( 'Promotions', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-promotions',
			array( $this, 'promotions_page' )
		);

		// Add Promotional Cards submenu (hidden from menu, accessed via button).
		add_submenu_page(
			null,
			esc_html__( 'Promotional Issued Cards', 'univoucher-for-woocommerce' ),
			esc_html__( 'Promotional Issued Cards', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-promotional-cards',
			array( $this, 'promotional_cards_page' )
		);

		// Add Settings submenu.
		add_submenu_page(
			'univoucher-inventory',
			esc_html__( 'Settings', 'univoucher-for-woocommerce' ),
			esc_html__( 'Settings', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-settings',
			array( $this, 'settings_page' )
		);

		// Add Tools submenu.
		add_submenu_page(
			'univoucher-inventory',
			esc_html__( 'Tools', 'univoucher-for-woocommerce' ),
			esc_html__( 'Tools', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-tools',
			array( $this, 'tools_page' )
		);
	}

	/**
	 * Inventory Management page callback.
	 */
	public function inventory_page() {
		$inventory_page = UniVoucher_WC_Inventory_Page::instance();
		$inventory_page->render_page();
	}

	/**
	 * Add Gift Cards page callback.
	 */
	public function add_cards_page() {
		$add_cards_page = UniVoucher_WC_Add_Cards_Page::instance();
		$add_cards_page->render_page();
	}

	/**
	 * Promotions page callback.
	 */
	public function promotions_page() {
		$promotions_page = UniVoucher_WC_Promotions_Page::instance();
		$promotions_page->render_page();
	}

	/**
	 * Promotional Cards page callback.
	 */
	public function promotional_cards_page() {
		$promotional_cards_page = UniVoucher_WC_Promotional_Cards_Page::instance();
		$promotional_cards_page->render_page();
	}

	/**
	 * Tools page callback.
	 */
	public function tools_page() {
		$tools_page = UniVoucher_WC_Admin_Tools::instance();
		$tools_page->render_page();
	}

	/**
	 * Settings page callback.
	 */
	public function settings_page() {
		// Get current tab with nonce verification.
		if ( isset( $_GET['tab'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_settings_tab' ) ) {
				$active_tab = 'security'; // Default tab if nonce is invalid
			} else {
				$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			}
		} else {
			$active_tab = 'security'; // Default tab
		}
		
		// Define tabs.
		$tabs = array(
			'security' => array(
				'title' => esc_html__( 'Security', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_security_settings',
				'sections' => array( 'univoucher_wc_security_section' ),
			),
			'api' => array(
				'title' => esc_html__( 'API', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_api_settings',
				'sections' => array( 'univoucher_wc_api_section' ),
			),
			'wallet' => array(
				'title' => esc_html__( 'Internal Wallet', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_wallet_settings',
				'sections' => array( 'univoucher_wc_wallet_section' ),
			),
			'delivery' => array(
				'title' => esc_html__( 'Card Delivery', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_delivery_settings',
				'sections' => array( 'univoucher_wc_delivery_section' ),
			),
			'backorders' => array(
				'title' => esc_html__( 'On-Demand (Backorders)', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_backorders_settings',
				'sections' => array( 'univoucher_wc_backorders_section' ),
			),
			'templates' => array(
				'title' => esc_html__( 'Content Templates', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_templates_settings',
				'sections' => array( 'univoucher_wc_content_templates_section' ),
			),
			'image-templates' => array(
				'title' => esc_html__( 'Image Templates', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_image_settings',
				'sections' => array( 'univoucher_wc_template_settings_section', 'univoucher_wc_visual_editor_section' ),
			),
			'compatibility' => array(
				'title' => esc_html__( 'Integrations', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_compatibility_settings',
				'sections' => array( 'univoucher_wc_compatibility_section' ),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UniVoucher Settings', 'univoucher-for-woocommerce' ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=univoucher-settings&tab=' . $tab_key ), 'univoucher_settings_tab' ) ); ?>" 
					   class="nav-tab <?php echo esc_attr( $active_tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
						<?php echo esc_html( $tab_data['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			
			<?php if ( $active_tab === 'image-templates' ) : ?>
				<?php
				// Image Templates tab uses custom layout - bypass WordPress settings API
				$this->render_image_templates_custom_page();
				?>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php
					if ( isset( $tabs[ $active_tab ] ) ) {
						$settings_group = $tabs[ $active_tab ]['settings_group'];
						settings_fields( $settings_group );
						
						// Render sections for the active tab only.
						foreach ( $tabs[ $active_tab ]['sections'] as $section_id ) {
							$this->render_settings_section( $settings_group, $section_id );
						}
						
						submit_button();
					}
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a specific settings section.
	 *
	 * @param string $page The settings page slug.
	 * @param string $section_id The section ID to render.
	 */
	private function render_settings_section( $page, $section_id ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $page ][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections[ $page ][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . "</h2>\n";
		}

		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section_id ] ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $page, $section_id );
		echo '</table>';
	}

	/**
	 * Render a settings section without form (for tabs with no form submission).
	 *
	 * @param string $section_id The section ID to render.
	 */
	private function render_settings_section_no_form( $section_id ) {
		global $wp_settings_sections, $wp_settings_fields;

		// Stock sync section is registered under univoucher_wc_stock_settings
		$page = 'univoucher_wc_stock_settings';
		
		if ( isset( $wp_settings_sections[ $page ][ $section_id ] ) ) {
			$section = $wp_settings_sections[ $page ][ $section_id ];

			if ( $section['title'] ) {
				echo '<h2>' . esc_html( $section['title'] ) . "</h2>\n";
			}

			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}

			// Render the fields with proper table structure like other tabs
			if ( isset( $wp_settings_fields[ $page ][ $section_id ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( $page, $section_id );
				echo '</table>';
			}
		}
	}

	/**
	 * Render custom Image Templates page.
	 */
	private function render_image_templates_custom_page() {
		?>
		<form method="post" action="options.php" style="margin-top: 20px;">
			<?php
			settings_fields( 'univoucher_wc_image_settings' );
			
			// Get the image templates instance and render the custom layout
			$image_templates = UniVoucher_WC_Image_Templates::instance();
			$image_templates->render_custom_layout();
			
			submit_button();
			?>
		</form>
		<?php
	}

}