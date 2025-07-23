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
		add_action( 'admin_head', array( $this, 'admin_menu_icon_styles' ) );
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
			esc_html__( 'Inventory', 'univoucher-for-woocommerce' ),
			esc_html__( 'Inventory', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-inventory',
			array( $this, 'inventory_page' )
		);

		// Add Gift Cards submenu.
		add_submenu_page(
			'univoucher-inventory',
			esc_html__( 'Add Gift Cards', 'univoucher-for-woocommerce' ),
			esc_html__( 'Add Gift Cards', 'univoucher-for-woocommerce' ),
			'manage_options',
			'univoucher-add-cards',
			array( $this, 'add_cards_page' )
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
	 * Settings page callback.
	 */
	public function settings_page() {
		// Get current tab.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'security';
		
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
			'stock' => array(
				'title' => esc_html__( 'Stock Sync', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_stock_settings',
				'sections' => array( 'univoucher_wc_stock_sync_section' ),
			),
			'delivery' => array(
				'title' => esc_html__( 'Card Delivery', 'univoucher-for-woocommerce' ),
				'settings_group' => 'univoucher_wc_delivery_settings',
				'sections' => array( 'univoucher_wc_delivery_section' ),
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
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UniVoucher Settings', 'univoucher-for-woocommerce' ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-settings&tab=' . $tab_key ) ); ?>" 
					   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_data['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			
			<?php if ( $active_tab === 'stock' ) : ?>
				<?php
				// Stock sync tab has no form submission - just display the section
				if ( isset( $tabs[ $active_tab ] ) ) {
					foreach ( $tabs[ $active_tab ]['sections'] as $section_id ) {
						$this->render_settings_section_no_form( $section_id );
					}
				}
				?>
			<?php elseif ( $active_tab === 'image-templates' ) : ?>
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
			echo "<h2>{$section['title']}</h2>\n";
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
				echo "<h2>{$section['title']}</h2>\n";
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

	/**
	 * Add admin menu icon styles.
	 */
	public function admin_menu_icon_styles() {
		?>
		<style>
			#toplevel_page_univoucher-inventory .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				object-fit: contain;
				max-width: 20px;
				max-height: 20px;
			}
			/* Ensure proper display in collapsed menu */
			.folded #toplevel_page_univoucher-inventory .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
			}
		</style>
		<?php
	}
}