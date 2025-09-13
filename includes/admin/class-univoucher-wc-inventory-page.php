<?php
/**
 * UniVoucher For WooCommerce Inventory Management Page
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * UniVoucher_WC_Inventory_List_Table class extends WP_List_Table.
 */
class UniVoucher_WC_Inventory_List_Table extends WP_List_Table {

	/**
	 * Gift card manager instance.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	private $gift_card_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'gift_card',
			'plural'   => 'gift_cards',
			'ajax'     => false,
		) );

		$this->gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox" />',
			'card_id'         => __( 'Card ID', 'univoucher-for-woocommerce' ),
			'product_id'      => __( 'Product', 'univoucher-for-woocommerce' ),
			'order_id'        => __( 'Order', 'univoucher-for-woocommerce' ),
			'status'          => __( 'Status', 'univoucher-for-woocommerce' ),
			'delivery_status' => __( 'Delivery', 'univoucher-for-woocommerce' ),
			'amount'          => __( 'Amount', 'univoucher-for-woocommerce' ),
			'network'         => __( 'Network', 'univoucher-for-woocommerce' ),
			'created_at'      => __( 'Added', 'univoucher-for-woocommerce' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'card_id'         => array( 'card_id', false ),
			'product_id'      => array( 'product_id', false ),
			'order_id'        => array( 'order_id', false ),
			'status'          => array( 'status', false ),
			'delivery_status' => array( 'delivery_status', false ),
			'amount'          => array( 'amount', false ),
			'created_at'      => array( 'created_at', true ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete'          => __( 'Delete', 'univoucher-for-woocommerce' ),
			'mark_inactive'   => __( 'Mark as Inactive', 'univoucher-for-woocommerce' ),
			'mark_available'  => __( 'Mark as Available', 'univoucher-for-woocommerce' ),
		);
	}

	/**
	 * Column checkbox.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_cb( $item ) {
		// Disable checkbox for assigned cards to prevent bulk deletion
		if ( ! empty( $item->order_id ) ) {
			return sprintf( 
				'<input type="checkbox" name="gift_card[]" value="%d" disabled title="%s" />', 
				$item->id,
				esc_attr__( 'Assigned cards cannot be deleted', 'univoucher-for-woocommerce' )
			);
		}
		
		return sprintf( '<input type="checkbox" name="gift_card[]" value="%d" />', $item->id );
	}

	/**
	 * Column card ID.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_card_id( $item ) {
		$actions = array();

		// Edit action - always available
		$actions['edit'] = sprintf(
			'<a href="#" class="edit-gift-card" data-id="%d">%s</a>',
			$item->id,
			__( 'Edit', 'univoucher-for-woocommerce' )
		);

		if ( empty( $item->order_id ) ) {
			$actions['delete'] = sprintf(
				'<a href="#" class="delete-gift-card" data-id="%d">%s</a>',
				$item->id,
				__( 'Delete', 'univoucher-for-woocommerce' )
			);
		}

		return sprintf(
			'<strong>%s</strong> %s',
			esc_html( $item->card_id ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Column product ID.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_product_id( $item ) {
		$product = wc_get_product( $item->product_id );
		if ( $product ) {
			return sprintf(
				'<a href="%s">#%d %s</a>',
				esc_url( get_edit_post_link( $item->product_id ) ),
				$item->product_id,
				esc_html( $product->get_name() )
			);
		}
		return sprintf( '#%d <small>(deleted)</small>', $item->product_id );
	}

	/**
	 * Column order ID.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_order_id( $item ) {
		if ( empty( $item->order_id ) ) {
			return '<small>' . esc_html__( 'Not assigned to any order', 'univoucher-for-woocommerce' ) . '</small>';
		}

		$order = wc_get_order( $item->order_id );
		if ( $order ) {
			return sprintf(
				'<a href="%s">#%d</a><br><small>%s</small>',
				esc_url( $order->get_edit_order_url() ),
				$item->order_id,
				esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() )
			);
		}
		return sprintf( '#%d <small>(deleted)</small>', $item->order_id );
	}

	/**
	 * Column status.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_status( $item ) {
		$status_class = 'status-' . $item->status;
		$status_label = ucfirst( $item->status );

		return sprintf(
			'<span class="status-badge %s">%s</span>',
			esc_attr( $status_class ),
			esc_html( $status_label )
		);
	}

	/**
	 * Column delivery status.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_delivery_status( $item ) {
		$delivery_class = 'delivery-' . str_replace( ' ', '-', $item->delivery_status );
		$delivery_label = ucwords( $item->delivery_status );

		$icons = array(
			'never delivered'           => 'dashicons-clock',
			'delivered'                => 'dashicons-yes-alt',
			'returned after delivery'  => 'dashicons-undo',
		);

		$icon = isset( $icons[ $item->delivery_status ] ) ? $icons[ $item->delivery_status ] : 'dashicons-minus';

		return sprintf(
			'<span class="delivery-badge %s"><span class="dashicons %s"></span> %s</span>',
			esc_attr( $delivery_class ),
			esc_attr( $icon ),
			esc_html( $delivery_label )
		);
	}

	/**
	 * Column network.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_network( $item ) {
		$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
		$network_name = isset( $networks[ $item->chain_id ] ) ? $networks[ $item->chain_id ]['name'] : 'Unknown';

		return sprintf(
			'%s<br><small>ID: %d</small>',
			esc_html( $network_name ),
			$item->chain_id
		);
	}

	/**
	 * Column amount (merged with token info).
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_amount( $item ) {
		// Format amount preserving significant digits
		$amount_float = (float) $item->amount;
		if ( $amount_float >= 1 ) {
			$formatted_amount = number_format( $amount_float, 0, '.', ',' );
		} elseif ( $amount_float >= 0.01 ) {
			$formatted_amount = number_format( $amount_float, 2, '.', ',' );
		} elseif ( $amount_float >= 0.0001 ) {
			$formatted_amount = number_format( $amount_float, 4, '.', ',' );
		} else {
			$formatted_amount = rtrim( rtrim( number_format( $amount_float, 8, '.', ',' ), '0' ), '.' );
		}
		
		$output = sprintf( '<div><strong>%s %s</strong><br>', esc_html( $formatted_amount ), esc_html( $item->token_symbol ) );

		if ( $item->token_address && strtolower( $item->token_type ) === 'erc20' ) {
			$output .= sprintf(
				'<small>ERC20 (%s...%s)</small>',
				esc_html( substr( $item->token_address, 0, 6 ) ),
				esc_html( substr( $item->token_address, -4 ) )
			);
		}
		// For native tokens, show nothing additional

		$output .= '</div>';
		return $output;
	}



	/**
	 * Column created at.
	 *
	 * @param object $item Item data.
	 * @return string
	 */
	public function column_created_at( $item ) {
		$created_date = mysql2date( get_option( 'date_format' ), $item->created_at );
		$created_time = mysql2date( get_option( 'time_format' ), $item->created_at );

		return sprintf(
			'%s<br><small>%s</small>',
			esc_html( $created_date ),
			esc_html( $created_time )
		);
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Item data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '—';
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$current_page = $this->get_pagenum();

		// Check nonce for $_GET parameter access (if any filter/search parameters are present)
		if ( ! empty( $_GET ) && ( isset( $_GET['status'] ) || isset( $_GET['delivery_status'] ) || isset( $_GET['chain_id'] ) || isset( $_GET['product_id'] ) || isset( $_GET['s'] ) || isset( $_GET['orderby'] ) || isset( $_GET['order'] ) ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_inventory_filter' ) ) {
				// If nonce is invalid, use default values
				$filter_status = '';
				$filter_delivery_status = '';
				$chain_filter = '';
				$product_filter = '';
				$search = '';
				$orderby = 'created_at';
				$order = 'DESC';
			} else {
				// Get filter values with valid nonce.
				$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
				$filter_delivery_status = isset( $_GET['delivery_status'] ) ? sanitize_text_field( wp_unslash( $_GET['delivery_status'] ) ) : '';
				$chain_filter = isset( $_GET['chain_id'] ) ? absint( wp_unslash( $_GET['chain_id'] ) ) : '';
				$product_filter = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : '';
				$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

				// Get order by and order.
				$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
				$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
			}
		} else {
			// No filter parameters, use defaults
			$filter_status = '';
			$filter_delivery_status = '';
			$chain_filter = '';
			$product_filter = '';
			$search = '';
			$orderby = 'created_at';
			$order = 'DESC';
		}

		$args = array(
			'page'            => $current_page,
			'per_page'        => $per_page,
			'status'          => $filter_status,
			'delivery_status' => $filter_delivery_status,
			'chain_id'        => $chain_filter,
			'product_id'      => $product_filter,
			'search'          => $search,
			'orderby'         => $orderby,
			'order'           => $order,
		);

		$results = $this->gift_card_manager->uv_get_gift_cards( $args );

		$this->items = $results['items'];

		$this->set_pagination_args( array(
			'total_items' => $results['total'],
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Display table navigation.
	 *
	 * @param string $which Position (top/bottom).
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			$this->display_filters();
		}
		parent::display_tablenav( $which );
	}

	/**
	 * Display filters.
	 */
	protected function display_filters() {
		// Check nonce for $_GET parameter access (if any filter parameters are present)
		if ( ! empty( $_GET ) && ( isset( $_GET['status'] ) || isset( $_GET['delivery_status'] ) || isset( $_GET['chain_id'] ) || isset( $_GET['product_id'] ) ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_inventory_filter' ) ) {
				// If nonce is invalid, use default values
				$filter_status = '';
				$filter_delivery_status = '';
				$chain_filter = '';
				$product_filter = '';
			} else {
				// Get filter values with valid nonce
				$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
				$filter_delivery_status = isset( $_GET['delivery_status'] ) ? sanitize_text_field( wp_unslash( $_GET['delivery_status'] ) ) : '';
				$chain_filter = isset( $_GET['chain_id'] ) ? absint( wp_unslash( $_GET['chain_id'] ) ) : '';
				$product_filter = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : '';
			}
		} else {
			// No filter parameters, use defaults
			$filter_status = '';
			$filter_delivery_status = '';
			$chain_filter = '';
			$product_filter = '';
		}
		$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
		?>
		<div class="alignleft actions">
			<select id="filter-by-status">
				<option value=""><?php esc_html_e( 'All Statuses', 'univoucher-for-woocommerce' ); ?></option>
				<option value="available" <?php selected( $filter_status, 'available' ); ?>><?php esc_html_e( 'Available', 'univoucher-for-woocommerce' ); ?></option>
				<option value="sold" <?php selected( $filter_status, 'sold' ); ?>><?php esc_html_e( 'Sold', 'univoucher-for-woocommerce' ); ?></option>
				<option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'univoucher-for-woocommerce' ); ?></option>
			</select>
			
			<select id="filter-by-delivery-status">
				<option value=""><?php esc_html_e( 'All Delivery Status', 'univoucher-for-woocommerce' ); ?></option>
				<option value="never delivered" <?php selected( $filter_delivery_status, 'never delivered' ); ?>><?php esc_html_e( 'Never Delivered', 'univoucher-for-woocommerce' ); ?></option>
				<option value="delivered" <?php selected( $filter_delivery_status, 'delivered' ); ?>><?php esc_html_e( 'Delivered', 'univoucher-for-woocommerce' ); ?></option>
				<option value="returned after delivery" <?php selected( $filter_delivery_status, 'returned after delivery' ); ?>><?php esc_html_e( 'Returned After Delivery', 'univoucher-for-woocommerce' ); ?></option>
			</select>
			
			<select id="filter-by-chain">
				<option value=""><?php esc_html_e( 'All Networks', 'univoucher-for-woocommerce' ); ?></option>
				<?php foreach ( $networks as $chain_id => $network ) : ?>
					<option value="<?php echo esc_attr( $chain_id ); ?>" <?php selected( $chain_filter, $chain_id ); ?>>
						<?php echo esc_html( $network['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			
			<select id="filter-by-product">
				<option value=""><?php esc_html_e( 'All Products', 'univoucher-for-woocommerce' ); ?></option>
				<?php
				// Get products that have UniVoucher enabled
				$univoucher_products = get_posts( array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'     => '_univoucher_enabled',
							'value'   => 'yes',
							'compare' => '=',
						),
					),
					'orderby'        => 'title',
					'order'          => 'ASC',
				) );
				
				foreach ( $univoucher_products as $product ) {
					$selected = selected( $product_filter, $product->ID, false );
					echo sprintf(
						'<option value="%d"%s>#%d %s</option>',
						esc_attr( $product->ID ),
						esc_attr( $selected ),
						esc_html( $product->ID ),
						esc_html( $product->post_title )
					);
				}
				?>
			</select>
			
			<button type="button" class="button" id="filter-submit"><?php esc_html_e( 'Filter', 'univoucher-for-woocommerce' ); ?></button>
		</div>
		<?php
	}

	/**
	 * No items message.
	 */
	public function no_items() {
		esc_html_e( 'No gift cards found.', 'univoucher-for-woocommerce' );
	}
}

/**
 * UniVoucher_WC_Inventory_Page class.
 */
class UniVoucher_WC_Inventory_Page {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Inventory_Page
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Inventory_Page Instance.
	 *
	 * @return UniVoucher_WC_Inventory_Page - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Inventory_Page Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_univoucher_get_card_for_edit', array( $this, 'ajax_get_card_for_edit' ) );
		add_action( 'wp_ajax_univoucher_update_card', array( $this, 'ajax_update_card' ) );
		add_action( 'wp_ajax_univoucher_bulk_action', array( $this, 'ajax_bulk_action' ) );
	}

	/**
	 * Render the inventory management page.
	 */
	public function render_page() {
		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$stats = $gift_card_manager->get_inventory_stats();

		// Create list table instance.
		$list_table = new UniVoucher_WC_Inventory_List_Table();
		$list_table->prepare_items();

		// Enqueue inventory page styles (script is handled by admin class)
		wp_enqueue_style( 'univoucher-inventory', plugin_dir_url( __FILE__ ) . '../../admin/css/inventory.css', array(), UNIVOUCHER_WC_VERSION );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Inventory Management', 'univoucher-for-woocommerce' ); ?></h1>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-add-cards' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Gift Cards', 'univoucher-for-woocommerce' ); ?></a>

			<hr class="wp-header-end">

			<!-- Edit Card Modal -->
			<div id="edit-card-modal" style="display: none;">
				<div class="edit-card-backdrop">
					<div class="edit-card-content">
						<div class="edit-card-header">
							<h2><?php esc_html_e( 'Edit Gift Card', 'univoucher-for-woocommerce' ); ?></h2>
							<button type="button" class="close-edit-modal">&times;</button>
						</div>
						<div class="edit-card-body">
							<form id="edit-card-form">
								<input type="hidden" id="edit-card-id" name="card_id_pk" />
								<input type="hidden" id="edit-product-id" name="product_id" />
								<input type="hidden" id="edit-order-id" name="order_id" />
								<input type="hidden" id="edit-chain-id" name="chain_id" />
								<input type="hidden" id="edit-token-address" name="token_address" />
								<input type="hidden" id="edit-token-symbol" name="token_symbol" />
								<input type="hidden" id="edit-token-type" name="token_type" />
								<input type="hidden" id="edit-token-decimals" name="token_decimals" />
								<input type="hidden" id="edit-amount" name="amount" />
								
								<table class="form-table">
									<tr>
										<th scope="row"><?php esc_html_e( 'Card ID', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<input type="number" id="edit-card-id-input" name="card_id" class="regular-text" autocomplete="off" />
											<div class="validation-error" id="edit-card-id-error" style="display: none;"></div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Card Secret', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<input type="text" id="edit-card-secret-input" name="card_secret" class="regular-text" maxlength="23" autocomplete="off" />
											<div class="validation-error" id="edit-card-secret-error" style="display: none;"></div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Card Information', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<div id="edit-card-info" style="background: #f0f0f1; padding: 10px; border-radius: 4px;">
												<strong><?php esc_html_e( 'Token:', 'univoucher-for-woocommerce' ); ?></strong> <span id="edit-info-token"></span><br>
												<strong><?php esc_html_e( 'Amount:', 'univoucher-for-woocommerce' ); ?></strong> <span id="edit-info-amount"></span><br>
												<strong><?php esc_html_e( 'Network:', 'univoucher-for-woocommerce' ); ?></strong> <span id="edit-info-network"></span>
											</div>

										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Product', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<span id="edit-product-info"></span>
											<p class="description"><?php esc_html_e( 'Product cannot be changed. Please delete and add to a different product.', 'univoucher-for-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Order', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<span id="edit-order-info"></span>
											<div id="edit-unassign-section" style="display: none;">
												<button type="button" id="unassign-order-btn" class="button button-secondary button-small" style="margin-top: 5px;">
													<?php esc_html_e( 'Unassign', 'univoucher-for-woocommerce' ); ?>
												</button>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Status', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<select id="edit-status" name="status">
												<option value="available"><?php esc_html_e( 'Available', 'univoucher-for-woocommerce' ); ?></option>
												<option value="sold"><?php esc_html_e( 'Sold', 'univoucher-for-woocommerce' ); ?></option>
												<option value="inactive"><?php esc_html_e( 'Inactive', 'univoucher-for-woocommerce' ); ?></option>
											</select>
											<div id="edit-status-message" style="display: none; margin-top: 5px;"></div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Delivery Status', 'univoucher-for-woocommerce' ); ?></th>
										<td>
											<select id="edit-delivery-status" name="delivery_status">
												<option value="never delivered"><?php esc_html_e( 'Never Delivered', 'univoucher-for-woocommerce' ); ?></option>
												<option value="delivered"><?php esc_html_e( 'Delivered', 'univoucher-for-woocommerce' ); ?></option>
												<option value="returned after delivery"><?php esc_html_e( 'Returned After Delivery', 'univoucher-for-woocommerce' ); ?></option>
											</select>
										</td>
									</tr>
								</table>
								
								<div class="edit-validation-section">
									<h3><?php esc_html_e( 'Validation Results', 'univoucher-for-woocommerce' ); ?></h3>
									<div class="validation-list">
										<div class="validation-item" data-validation="new">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'New Card', 'univoucher-for-woocommerce' ); ?></span>
										</div>
										<div class="validation-item" data-validation="active">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'Active', 'univoucher-for-woocommerce' ); ?></span>
										</div>
										<div class="validation-item" data-validation="network">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'Network', 'univoucher-for-woocommerce' ); ?></span>
										</div>
										<div class="validation-item" data-validation="amount">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'Amount', 'univoucher-for-woocommerce' ); ?></span>
										</div>
										<div class="validation-item" data-validation="token">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'Token', 'univoucher-for-woocommerce' ); ?></span>
										</div>
										<div class="validation-item" data-validation="secret">
											<span class="validation-icon dashicons dashicons-minus pending"></span>
											<span class="validation-label"><?php esc_html_e( 'Secret', 'univoucher-for-woocommerce' ); ?></span>
										</div>
									</div>
								</div>
							</form>
						</div>
						<div class="edit-card-footer">
							<button type="button" id="validate-edit-card-btn" class="button button-secondary"><?php esc_html_e( 'Validate', 'univoucher-for-woocommerce' ); ?></button>
							<button type="button" id="save-edit-card-btn" class="button button-primary" disabled><?php esc_html_e( 'Save', 'univoucher-for-woocommerce' ); ?></button>
							<button type="button" class="button cancel-edit-btn"><?php esc_html_e( 'Cancel', 'univoucher-for-woocommerce' ); ?></button>
						</div>
					</div>
				</div>
			</div>

			<!-- Statistics Cards -->
			<div class="univoucher-stats-grid">
				<div class="stats-card">
					<div class="stats-number"><?php echo esc_html( number_format( $stats['total'] ) ); ?></div>
					<div class="stats-label"><?php esc_html_e( 'Total Cards', 'univoucher-for-woocommerce' ); ?></div>
				</div>
				<div class="stats-card available">
					<div class="stats-number"><?php echo esc_html( number_format( $stats['available'] ) ); ?></div>
					<div class="stats-label"><?php esc_html_e( 'Available', 'univoucher-for-woocommerce' ); ?></div>
				</div>
				<div class="stats-card sold">
					<div class="stats-number"><?php echo esc_html( number_format( $stats['sold'] ) ); ?></div>
					<div class="stats-label"><?php esc_html_e( 'Sold', 'univoucher-for-woocommerce' ); ?></div>
				</div>
				<div class="stats-card inactive">
					<div class="stats-number"><?php echo esc_html( number_format( $stats['inactive'] ) ); ?></div>
					<div class="stats-label"><?php esc_html_e( 'Inactive', 'univoucher-for-woocommerce' ); ?></div>
				</div>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php 
				// Check nonce for $_GET parameter access
				if ( isset( $_GET['page'] ) ) {
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'univoucher_inventory_filter' ) ) {
						echo esc_attr( 'univoucher-inventory' ); // Default page value
					} else {
						echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) );
					}
				} else {
					echo esc_attr( 'univoucher-inventory' ); // Default page value
				}
				?>" />
				<?php
				$list_table->search_box( __( 'Search gift cards...', 'univoucher-for-woocommerce' ), 'gift_card' );
				$list_table->display();
				?>
			</form>





		</div>
		<?php
	}

	/**
	 * AJAX handler to get card details for editing.
	 */
	public function ajax_get_card_for_edit() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_edit_card' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$card_id = isset( $_POST['card_id'] ) ? absint( wp_unslash( $_POST['card_id'] ) ) : 0;
		if ( ! $card_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid card ID.', 'univoucher-for-woocommerce' ) ) );
		}

		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$card = $gift_card_manager->uv_get_gift_card( $card_id );

		if ( ! $card ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Card not found.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get product details
		$product = wc_get_product( $card->product_id );
		$product_info = $product ? sprintf( '#%d %s', $card->product_id, $product->get_name() ) : sprintf( '#%d (deleted)', $card->product_id );

		// Get order details
		$order_info = esc_html__( 'Not assigned to any order', 'univoucher-for-woocommerce' );
		$order_edit_url = '';
		if ( ! empty( $card->order_id ) ) {
			$order = wc_get_order( $card->order_id );
			if ( $order ) {
				$order_info = sprintf( '#%d %s %s', $card->order_id, $order->get_billing_first_name(), $order->get_billing_last_name() );
				$order_edit_url = $order->get_edit_order_url();
			} else {
				$order_info = sprintf( '#%d (deleted)', $card->order_id );
			}
		}

		// Get network name
		$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
		$network_name = isset( $networks[ $card->chain_id ] ) ? $networks[ $card->chain_id ]['name'] : 'Unknown';

		// Format amount
		$formatted_amount = rtrim( rtrim( number_format( (float) $card->amount, 18, '.', ',' ), '0' ), '.' );

		wp_send_json_success( array(
			'card' => array(
				'id' => $card->id,
				'card_id' => $card->card_id,
				'card_secret' => $card->card_secret,
				'product_id' => $card->product_id,
				'order_id' => $card->order_id,
				'status' => $card->status,
				'delivery_status' => $card->delivery_status,
				'chain_id' => $card->chain_id,
				'token_address' => $card->token_address,
				'token_symbol' => $card->token_symbol,
				'token_type' => $card->token_type,
				'token_decimals' => $card->token_decimals,
				'amount' => $card->amount,
			),
			'display' => array(
				'product_info' => $product_info,
				'order_info' => $order_info,
				'order_edit_url' => $order_edit_url,
				'network_name' => $network_name,
				'formatted_amount' => $formatted_amount,
				'token_info' => sprintf( '%s (%s)', $card->token_symbol, ucfirst( $card->token_type ) ),
			),
		) );
	}

	/**
	 * AJAX handler to update card.
	 */
	public function ajax_update_card() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_edit_card' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$card_pk_id = isset( $_POST['card_id_pk'] ) ? absint( wp_unslash( $_POST['card_id_pk'] ) ) : 0;
		$new_card_id = isset( $_POST['card_id'] ) ? sanitize_text_field( wp_unslash( $_POST['card_id'] ) ) : '';
		$new_card_secret = isset( $_POST['card_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['card_secret'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$delivery_status = isset( $_POST['delivery_status'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_status'] ) ) : '';

		if ( ! $card_pk_id || empty( $new_card_id ) || empty( $new_card_secret ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'univoucher-for-woocommerce' ) ) );
		}

		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		
		// Get current card to check if it's assigned to order
		$current_card = $gift_card_manager->uv_get_gift_card( $card_pk_id );
		if ( ! $current_card ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Card not found.', 'univoucher-for-woocommerce' ) ) );
		}

		// Encrypt card secret before storing
		$encrypted_secret = UniVoucher_For_WooCommerce::uv_encrypt_data( $new_card_secret );
		if ( is_wp_error( $encrypted_secret ) ) {
			wp_send_json_error( array( 'message' => $encrypted_secret->get_error_message() ) );
		}

		// Determine the correct status: keep as 'sold' if assigned to order, otherwise use provided status
		$final_status = ! empty( $current_card->order_id ) ? 'sold' : $status;

		$update_data = array(
			'card_id' => $new_card_id,
			'card_secret' => $encrypted_secret,
			'status' => $final_status,
			'delivery_status' => $delivery_status,
		);

		// Check if status is changing to handle stock adjustment
		$old_status = $current_card->status;
		$new_status = $final_status;
		
		// Update the card directly without going through the full validation
		// since we're only updating specific fields and product_id shouldn't be required for updates
		global $wpdb;
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			UniVoucher_WC_Database::instance()->uv_get_gift_cards_table(),
			$update_data,
			array( 'id' => $card_pk_id ),
			array( '%s', '%s', '%s', '%s' ), // formats
			array( '%d' ) // where format
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to update gift card.', 'univoucher-for-woocommerce' ) ) );
		}


		// Handle stock adjustment for status changes
		if ( $old_status !== $new_status ) {
			$product = wc_get_product( $current_card->product_id );
			if ( $product && $product->managing_stock() ) {
				$current_stock = $product->get_stock_quantity();
				
				// If changing FROM available status - decrease stock
				if ( $old_status === 'available' && $new_status !== 'available' ) {
					$product->set_stock_quantity( $current_stock - 1 );
					$product->save();
				}
				// If changing TO available status - increase stock
				elseif ( $old_status !== 'available' && $new_status === 'available' ) {
					$product->set_stock_quantity( $current_stock + 1 );
					$product->save();
				}
			}
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Card updated successfully.', 'univoucher-for-woocommerce' ) ) );
	}

	/**
	 * AJAX handler for bulk actions.
	 */
	public function ajax_bulk_action() {
		// Check nonce - use single, specific nonce for this action
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_bulk_action' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		
		// Support both old format (ids) and new format (card_ids)
		$card_ids = array();
		if ( isset( $_POST['card_ids'] ) ) {
			$card_ids = array_map( 'absint', wp_unslash( $_POST['card_ids'] ) );
		} elseif ( isset( $_POST['ids'] ) ) {
			$card_ids = array_map( 'absint', wp_unslash( $_POST['ids'] ) );
		}

		if ( empty( $action ) || empty( $card_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required parameters.', 'univoucher-for-woocommerce' ) ) );
		}

		if ( ! in_array( $action, array( 'delete', 'mark_inactive', 'mark_available' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid action.', 'univoucher-for-woocommerce' ) ) );
		}

		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$updated_count = 0;
		$errors = array();

		if ( $action === 'delete' ) {
			// Handle delete action
			foreach ( $card_ids as $card_id ) {
				$result = $gift_card_manager->uv_delete_gift_card( $card_id );
				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'ID %d: %s', $card_id, $result->get_error_message() );
				} else {
					$updated_count++;
				}
			}
		} else {
			// Handle mark as inactive/available actions
			global $wpdb;
			$new_status = ( $action === 'mark_inactive' ) ? 'inactive' : 'available';
			$product_stock_changes = array(); // Track stock changes per product

			foreach ( $card_ids as $card_id ) {
				$card = $gift_card_manager->uv_get_gift_card( $card_id );
				if ( ! $card ) {
					continue;
				}

				// Skip if card is assigned to an order (sold cards)
				if ( ! empty( $card->order_id ) ) {
					continue;
				}

				$old_status = $card->status;

				// Update card status
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->update(
					UniVoucher_WC_Database::instance()->uv_get_gift_cards_table(),
					array( 'status' => $new_status ),
					array( 'id' => $card_id ),
					array( '%s' ),
					array( '%d' )
				);

				if ( false !== $result ) {
					$updated_count++;


					// Track stock changes for this product
					if ( ! isset( $product_stock_changes[ $card->product_id ] ) ) {
						$product_stock_changes[ $card->product_id ] = 0;
					}

					// Calculate stock change based on status transition
					if ( $old_status === 'available' && $new_status !== 'available' ) {
						$product_stock_changes[ $card->product_id ] -= 1; // Decrease stock
					} elseif ( $old_status !== 'available' && $new_status === 'available' ) {
						$product_stock_changes[ $card->product_id ] += 1; // Increase stock
					}
				}
			}

			// Apply stock changes to products
			foreach ( $product_stock_changes as $product_id => $stock_change ) {
				if ( $stock_change === 0 ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( $product && $product->managing_stock() ) {
					$current_stock = $product->get_stock_quantity();
					$new_stock = $current_stock + $stock_change;
					$product->set_stock_quantity( $new_stock );
					$product->save();
				}
			}
		}

		if ( $updated_count > 0 ) {
			if ( $action === 'delete' ) {
				/* translators: %d: number of cards deleted */
				$message = sprintf( _n( '%d card deleted successfully.', '%d cards deleted successfully.', $updated_count, 'univoucher-for-woocommerce' ), $updated_count );
			} else {
				/* translators: %d: number of cards updated */
				$message = sprintf( _n( '%d card updated successfully.', '%d cards updated successfully.', $updated_count, 'univoucher-for-woocommerce' ), $updated_count );
			}
			
			if ( ! empty( $errors ) ) {
				/* translators: %d: number of errors */
				$message .= ' ' . sprintf( _n( '%d error occurred.', '%d errors occurred.', count( $errors ), 'univoucher-for-woocommerce' ), count( $errors ) );
			}
			
			wp_send_json_success( array( 'message' => $message, 'errors' => $errors ) );
		} else {
			if ( $action === 'delete' ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No cards were deleted.', 'univoucher-for-woocommerce' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'No cards were updated. Only unassigned cards can be updated.', 'univoucher-for-woocommerce' ) ) );
			}
		}
	}
}