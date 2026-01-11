<?php
/**
 * UniVoucher For WooCommerce Promotional Cards Page
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Promotional_Cards_Page class.
 */
class UniVoucher_WC_Promotional_Cards_Page {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Promotional_Cards_Page
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Promotional_Cards_Page Instance.
	 *
	 * @return UniVoucher_WC_Promotional_Cards_Page - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Promotional_Cards_Page Constructor.
	 */
	public function __construct() {
		$this->handle_actions();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'univoucher_page_univoucher-promotional-cards' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'univoucher-wc-promotional-cards',
			plugins_url( 'admin/css/promotional-cards.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);
	}

	/**
	 * Handle promotional cards actions.
	 */
	public function handle_actions() {
		// Check if we're on the promotional cards page.
		$is_promo_cards_page = isset( $_GET['page'] ) && 'univoucher-promotional-cards' === $_GET['page'];

		if ( ! $is_promo_cards_page ) {
			return;
		}

		// Handle manual card creation.
		if ( isset( $_POST['action'] ) && 'add_manual_card' === $_POST['action'] ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'add_manual_promotional_card' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$result = $this->create_manual_promotional_card( $_POST );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotional-cards&error=' . urlencode( $result->get_error_message() ) ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotional-cards&added=1' ) );
			}
			exit;
		}

		// Handle bulk delete action.
		if ( isset( $_POST['action'] ) && 'bulk_delete' === $_POST['action'] && isset( $_POST['card_ids'] ) && is_array( $_POST['card_ids'] ) ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk_delete_promotional_cards' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$card_ids = array_map( 'absint', $_POST['card_ids'] );
			$this->delete_promotional_cards( $card_ids );
			wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotional-cards&deleted=' . count( $card_ids ) ) );
			exit;
		}

		// Handle single delete action.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['card_id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_promotional_card' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$this->delete_promotional_cards( array( absint( $_GET['card_id'] ) ) );
			wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotional-cards&deleted=1' ) );
			exit;
		}
	}

	/**
	 * Create manual promotional card.
	 *
	 * @param array $post_data POST data from form.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function create_manual_promotional_card( $post_data ) {
		global $wpdb;

		// Sanitize and validate inputs.
		$promotion_id = isset( $post_data['promotion_id'] ) ? absint( $post_data['promotion_id'] ) : 0;
		$user_id = isset( $post_data['user_id'] ) ? absint( $post_data['user_id'] ) : 0;
		$order_id = isset( $post_data['order_id'] ) ? absint( $post_data['order_id'] ) : 0;

		// Validate required fields.
		if ( ! $promotion_id ) {
			return new WP_Error( 'missing_promotion', __( 'Please select a promotion.', 'univoucher-for-woocommerce' ) );
		}

		if ( ! $user_id ) {
			return new WP_Error( 'missing_user', __( 'Please enter a valid user ID.', 'univoucher-for-woocommerce' ) );
		}

		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', __( 'User not found.', 'univoucher-for-woocommerce' ) );
		}

		// Verify order exists if provided.
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'invalid_order', __( 'Order not found.', 'univoucher-for-woocommerce' ) );
			}
		}

		// Get promotion details.
		$database = UniVoucher_WC_Database::instance();
		$promotions_table = $database->uv_get_promotions_table();
		$promotion = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $promotions_table WHERE id = %d", $promotion_id ), ARRAY_A );

		if ( ! $promotion ) {
			return new WP_Error( 'invalid_promotion', __( 'Promotion not found.', 'univoucher-for-woocommerce' ) );
		}

		// Generate promotional gift card via API.
		$processor = UniVoucher_WC_Promotion_Processor::instance();
		$order_obj = $order_id ? wc_get_order( $order_id ) : null;

		// Use reflection to call private method.
		$reflection = new ReflectionClass( $processor );
		$method = $reflection->getMethod( 'generate_promotional_card' );
		$method->setAccessible( true );

		$card_data = $method->invoke( $processor, $promotion, $order_obj, $user_id );

		if ( ! $card_data ) {
			return new WP_Error( 'card_creation_failed', __( 'Failed to create promotional card. Please check the internal wallet configuration.', 'univoucher-for-woocommerce' ) );
		}

		// Add order note if order exists.
		if ( $order_obj ) {
			$order_obj->add_order_note(
				sprintf(
					// translators: %s is the promotion title
					__( 'UniVoucher: Manual promotional gift card created for "%s"', 'univoucher-for-woocommerce' ),
					$promotion['title']
				)
			);
		}

		return true;
	}

	/**
	 * Delete promotional cards.
	 *
	 * @param array $card_ids Array of card IDs to delete.
	 */
	private function delete_promotional_cards( $card_ids ) {
		if ( empty( $card_ids ) ) {
			return;
		}

		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$cards_table = $database->uv_get_promotional_cards_table();
		$promotions_table = $database->uv_get_promotions_table();
		$tracking_table = $database->uv_get_promotion_user_tracking_table();

		$placeholders = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );

		// Get card data before deletion to update related tables.
		$cards_to_delete = $wpdb->get_results(
			$wpdb->prepare( "SELECT promotion_id, user_id FROM $cards_table WHERE id IN ($placeholders)", $card_ids ),
			ARRAY_A
		);

		// Group cards by promotion_id and user_id for batch updates.
		$promotion_counts = array();
		$user_tracking_counts = array();

		foreach ( $cards_to_delete as $card ) {
			$promotion_id = $card['promotion_id'];
			$user_id = $card['user_id'];

			// Count cards per promotion.
			if ( ! isset( $promotion_counts[ $promotion_id ] ) ) {
				$promotion_counts[ $promotion_id ] = 0;
			}
			$promotion_counts[ $promotion_id ]++;

			// Count cards per user per promotion.
			$tracking_key = $promotion_id . '_' . $user_id;
			if ( ! isset( $user_tracking_counts[ $tracking_key ] ) ) {
				$user_tracking_counts[ $tracking_key ] = array(
					'promotion_id' => $promotion_id,
					'user_id'      => $user_id,
					'count'        => 0,
				);
			}
			$user_tracking_counts[ $tracking_key ]['count']++;
		}

		// Update promotions table - reduce total_issued.
		foreach ( $promotion_counts as $promotion_id => $count ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $promotions_table SET total_issued = GREATEST(0, total_issued - %d) WHERE id = %d",
					$count,
					$promotion_id
				)
			);
		}

		// Update user tracking table - reduce cards_issued.
		foreach ( $user_tracking_counts as $tracking ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $tracking_table SET cards_issued = GREATEST(0, cards_issued - %d) WHERE promotion_id = %d AND user_id = %d",
					$tracking['count'],
					$tracking['promotion_id'],
					$tracking['user_id']
				)
			);
		}

		// Delete the cards.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $cards_table WHERE id IN ($placeholders)", $card_ids ) );
	}

	/**
	 * Get promotional cards with pagination and filters.
	 *
	 * @param int   $per_page Number of items per page.
	 * @param int   $page_number Current page number.
	 * @param array $filters Filter parameters.
	 * @return array
	 */
	private function get_promotional_cards( $per_page = 20, $page_number = 1, $filters = array() ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$cards_table = $database->uv_get_promotional_cards_table();
		$promotions_table = $database->uv_get_promotions_table();

		$where = array( '1=1' );
		$params = array();

		// Apply filters.
		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = 'pc.user_id = %d';
			$params[] = absint( $filters['user_id'] );
		}

		if ( ! empty( $filters['order_id'] ) ) {
			$where[] = 'pc.order_id = %d';
			$params[] = absint( $filters['order_id'] );
		}

		if ( ! empty( $filters['promotion_id'] ) ) {
			$where[] = 'pc.promotion_id = %d';
			$params[] = absint( $filters['promotion_id'] );
		}

		$where_clause = implode( ' AND ', $where );
		$offset = ( $page_number - 1 ) * $per_page;

		$query = "SELECT
			pc.*,
			p.title as promotion_title,
			p.token_decimals,
			u.user_email,
			u.display_name
		FROM $cards_table pc
		LEFT JOIN $promotions_table p ON pc.promotion_id = p.id
		LEFT JOIN {$wpdb->users} u ON pc.user_id = u.ID
		WHERE $where_clause
		ORDER BY pc.created_at DESC
		LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total count of promotional cards with filters.
	 *
	 * @param array $filters Filter parameters.
	 * @return int
	 */
	private function get_promotional_cards_count( $filters = array() ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$cards_table = $database->uv_get_promotional_cards_table();

		$where = array( '1=1' );
		$params = array();

		// Apply filters.
		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$params[] = absint( $filters['user_id'] );
		}

		if ( ! empty( $filters['order_id'] ) ) {
			$where[] = 'order_id = %d';
			$params[] = absint( $filters['order_id'] );
		}

		if ( ! empty( $filters['promotion_id'] ) ) {
			$where[] = 'promotion_id = %d';
			$params[] = absint( $filters['promotion_id'] );
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $cards_table WHERE $where_clause";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get all promotions for filter dropdown.
	 *
	 * @return array
	 */
	private function get_all_promotions() {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		return $wpdb->get_results( "SELECT id, title FROM $table ORDER BY title ASC" );
	}

	/**
	 * Format token amount with proper decimals, removing trailing zeros.
	 *
	 * @param string $amount   Token amount.
	 * @param int    $decimals Number of decimals (default 6).
	 * @return string Formatted amount.
	 */
	private function format_token_amount( $amount, $decimals = 6 ) {
		$formatted = number_format( (float) $amount, $decimals, '.', '' );
		$formatted = rtrim( $formatted, '0' );
		$formatted = rtrim( $formatted, '.' );
		return $formatted;
	}

	/**
	 * Render promotional cards page.
	 */
	public function render_page() {
		// Get filters from URL.
		$filters = array(
			'user_id'      => isset( $_GET['filter_user_id'] ) ? absint( $_GET['filter_user_id'] ) : 0,
			'order_id'     => isset( $_GET['filter_order_id'] ) ? absint( $_GET['filter_order_id'] ) : 0,
			'promotion_id' => isset( $_GET['filter_promotion_id'] ) ? absint( $_GET['filter_promotion_id'] ) : 0,
		);

		// Get pagination.
		$per_page = 20;
		$page_number = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		// Get promotional cards and total count.
		$promotional_cards = $this->get_promotional_cards( $per_page, $page_number, $filters );
		$total_items = $this->get_promotional_cards_count( $filters );
		$total_pages = ceil( $total_items / $per_page );

		// Get all promotions for filter dropdown.
		$promotions = $this->get_all_promotions();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Promotional Issued Cards', 'univoucher-for-woocommerce' ); ?></h1>
			<a href="#" id="add-manual-card-btn" class="page-title-action">
				<?php esc_html_e( 'Add Manual Card', 'univoucher-for-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions' ) ); ?>" class="page-title-action">
				<span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Back to Promotions List', 'univoucher-for-woocommerce' ); ?>
			</a>
			<hr class="wp-heading-inline">

			<!-- Page Introduction -->
			<div class="univoucher-page-intro" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
				<h2 style="margin-top: 0;"><?php esc_html_e( '📊 Promotional Gift Cards Issued', 'univoucher-for-woocommerce' ); ?></h2>
				<p style="font-size: 15px; line-height: 1.6;">
					<?php esc_html_e( 'This page displays all promotional gift cards that have been automatically generated and issued to customers. Each entry represents a real blockchain transaction funded by your internal wallet when a customer\'s order met promotion criteria.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<?php
			// Display notices.
			if ( isset( $_GET['deleted'] ) ) {
				$count = absint( $_GET['deleted'] );
				echo '<div class="notice notice-success is-dismissible"><p>' .
					sprintf(
						esc_html( _n( '%d promotional card deleted successfully.', '%d promotional cards deleted successfully.', $count, 'univoucher-for-woocommerce' ) ),
						$count
					) .
					'</p></div>';
			}

			if ( isset( $_GET['added'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' .
					esc_html__( 'Manual promotional card created successfully. The card will appear here once the blockchain transaction is confirmed.', 'univoucher-for-woocommerce' ) .
					'</p></div>';
			}

			if ( isset( $_GET['error'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) .
					'</p></div>';
			}
			?>

			<!-- Manual Card Form Modal -->
			<div id="manual-card-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
				<div style="background-color: #fff; margin: 5% auto; padding: 0; border: 1px solid #ccd0d4; border-radius: 4px; width: 80%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
					<div style="padding: 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center;">
						<h2 style="margin: 0;"><?php esc_html_e( 'Add Manual Promotional Card', 'univoucher-for-woocommerce' ); ?></h2>
						<span id="close-modal" style="cursor: pointer; font-size: 28px; font-weight: bold; color: #666;">&times;</span>
					</div>
					<form method="post" id="manual-card-form" style="padding: 20px;">
						<?php wp_nonce_field( 'add_manual_promotional_card' ); ?>
						<input type="hidden" name="action" value="add_manual_card">

						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<label for="promotion_id"><?php esc_html_e( 'Promotion', 'univoucher-for-woocommerce' ); ?> <span style="color: red;">*</span></label>
									</th>
									<td>
										<select name="promotion_id" id="promotion_id" class="regular-text" required>
											<option value=""><?php esc_html_e( 'Select a promotion...', 'univoucher-for-woocommerce' ); ?></option>
											<?php foreach ( $promotions as $promotion ) : ?>
												<option value="<?php echo absint( $promotion->id ); ?>">
													<?php echo esc_html( $promotion->title ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Select which promotion this card is for.', 'univoucher-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="user_id"><?php esc_html_e( 'User ID', 'univoucher-for-woocommerce' ); ?> <span style="color: red;">*</span></label>
									</th>
									<td>
										<input type="number" name="user_id" id="user_id" class="regular-text" required min="1">
										<p class="description"><?php esc_html_e( 'Enter the WordPress user ID who will receive this card.', 'univoucher-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="order_id"><?php esc_html_e( 'Order ID (Optional)', 'univoucher-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" name="order_id" id="order_id" class="regular-text" min="1">
										<p class="description"><?php esc_html_e( 'Optionally link this card to a WooCommerce order.', 'univoucher-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<div style="border-top: 1px solid #ccd0d4; padding-top: 20px; margin-top: 20px;">
							<button type="submit" class="button button-primary button-large">
								<?php esc_html_e( 'Create Promotional Card', 'univoucher-for-woocommerce' ); ?>
							</button>
							<button type="button" id="cancel-modal" class="button button-large" style="margin-left: 10px;">
								<?php esc_html_e( 'Cancel', 'univoucher-for-woocommerce' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- Filters -->
			<div class="univoucher-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<form method="get" id="promotional-cards-filter-form">
					<input type="hidden" name="page" value="univoucher-promotional-cards">
					<div style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
						<div>
							<label for="filter_promotion_id" style="display: block; margin-bottom: 5px; font-weight: 600;">
								<?php esc_html_e( 'Promotion', 'univoucher-for-woocommerce' ); ?>
							</label>
							<select name="filter_promotion_id" id="filter_promotion_id" style="width: 200px;">
								<option value=""><?php esc_html_e( 'All Promotions', 'univoucher-for-woocommerce' ); ?></option>
								<?php foreach ( $promotions as $promotion ) : ?>
									<option value="<?php echo absint( $promotion->id ); ?>" <?php selected( $filters['promotion_id'], $promotion->id ); ?>>
										<?php echo esc_html( $promotion->title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label for="filter_user_id" style="display: block; margin-bottom: 5px; font-weight: 600;">
								<?php esc_html_e( 'User ID', 'univoucher-for-woocommerce' ); ?>
							</label>
							<input type="number" name="filter_user_id" id="filter_user_id" value="<?php echo esc_attr( $filters['user_id'] ); ?>" placeholder="<?php esc_attr_e( 'User ID', 'univoucher-for-woocommerce' ); ?>" style="width: 150px;">
						</div>
						<div>
							<label for="filter_order_id" style="display: block; margin-bottom: 5px; font-weight: 600;">
								<?php esc_html_e( 'Order ID', 'univoucher-for-woocommerce' ); ?>
							</label>
							<input type="number" name="filter_order_id" id="filter_order_id" value="<?php echo esc_attr( $filters['order_id'] ); ?>" placeholder="<?php esc_attr_e( 'Order ID', 'univoucher-for-woocommerce' ); ?>" style="width: 150px;">
						</div>
						<div>
							<button type="submit" class="button">
								<?php esc_html_e( 'Filter', 'univoucher-for-woocommerce' ); ?>
							</button>
							<?php if ( ! empty( array_filter( $filters ) ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotional-cards' ) ); ?>" class="button">
									<?php esc_html_e( 'Reset', 'univoucher-for-woocommerce' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</form>
			</div>

			<form method="post" id="promotional-cards-form">
				<?php wp_nonce_field( 'bulk_delete_promotional_cards' ); ?>
				<input type="hidden" name="action" value="bulk_delete">

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<button type="submit" class="button action" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete the selected promotional cards?', 'univoucher-for-woocommerce' ); ?>');">
							<?php esc_html_e( 'Delete Selected', 'univoucher-for-woocommerce' ); ?>
						</button>
					</div>
					<div class="tablenav-pages">
						<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page_number,
							)
						);
						?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped univoucher-promotional-cards-table">
					<thead>
						<tr>
							<td class="check-column">
								<input type="checkbox" id="select-all">
							</td>
							<th><?php esc_html_e( 'Promotion', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'User', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Order ID', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Card ID', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Card Secret', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Network', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Transaction', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Issued Date', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Redeemed Date', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'univoucher-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $promotional_cards ) ) : ?>
							<tr>
								<td colspan="13" style="text-align: center; padding: 40px;">
									<?php esc_html_e( 'No promotional cards found.', 'univoucher-for-woocommerce' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $promotional_cards as $card ) : ?>
								<?php
								$network_data = UniVoucher_WC_Product_Fields::get_network_data( $card->chain_id );
								$network_name = $network_data ? $network_data['name'] : esc_html__( 'Unknown', 'univoucher-for-woocommerce' );
								$status_class = 'active' === $card->status ? 'status-active' : ( 'redeemed' === $card->status ? 'status-redeemed' : 'status-cancelled' );
								?>
								<tr>
									<th class="check-column">
										<input type="checkbox" name="card_ids[]" value="<?php echo absint( $card->id ); ?>">
									</th>
									<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions&action=edit&promotion_id=' . $card->promotion_id ) ); ?>"><?php echo esc_html( $card->promotion_title ); ?></a></strong></td>
									<td>
										<?php
										if ( $card->user_email ) {
											echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . absint( $card->user_id ) ) ) . '">' . esc_html( $card->display_name ) . '</a><br><small>' . esc_html( $card->user_email ) . '</small>';
										} else {
											esc_html_e( 'N/A', 'univoucher-for-woocommerce' );
										}
										?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $card->order_id ) . '&action=edit' ) ); ?>" target="_blank">
											#<?php echo absint( $card->order_id ); ?>
										</a>
									</td>
									<td><code><?php echo esc_html( $card->card_id ); ?></code></td>
									<td><code><?php echo esc_html( $card->card_secret ); ?></code></td>
									<td><?php echo esc_html( $this->format_token_amount( $card->amount, $card->token_decimals ) . ' ' . $card->token_symbol ); ?></td>
									<td><?php echo esc_html( $network_name ); ?></td>
									<td>
										<?php
										if ( ! empty( $card->transaction_hash ) ) {
											// Get block explorer URL based on network.
											$explorer_urls = array(
												'1'     => 'https://etherscan.io/tx/',
												'8453'  => 'https://basescan.org/tx/',
												'56'    => 'https://bscscan.com/tx/',
												'137'   => 'https://polygonscan.com/tx/',
												'42161' => 'https://arbiscan.io/tx/',
												'10'    => 'https://optimistic.etherscan.io/tx/',
												'43114' => 'https://snowtrace.io/tx/',
											);
											$explorer_url = isset( $explorer_urls[ $card->chain_id ] ) ? $explorer_urls[ $card->chain_id ] : 'https://etherscan.io/tx/';
											$short_hash = substr( $card->transaction_hash, 0, 10 ) . '...';
											echo '<a href="' . esc_url( $explorer_url . $card->transaction_hash ) . '" target="_blank" title="' . esc_attr( $card->transaction_hash ) . '"><code>' . esc_html( $short_hash ) . '</code> ↗</a>';
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( ucfirst( $card->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $card->created_at ) ) ); ?></td>
									<td>
										<?php
										if ( $card->redeemed_at ) {
											echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $card->redeemed_at ) ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=univoucher-promotional-cards&action=delete&card_id=' . $card->id ), 'delete_promotional_card' ) ); ?>"
											class="button button-small button-link-delete"
											onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this promotional card?', 'univoucher-for-woocommerce' ); ?>');">
											<?php esc_html_e( 'Delete', 'univoucher-for-woocommerce' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page_number,
							)
						);
						?>
						<span class="displaying-num">
							<?php
							printf(
								esc_html( _n( '%s item', '%s items', $total_items, 'univoucher-for-woocommerce' ) ),
								number_format_i18n( $total_items )
							);
							?>
						</span>
					</div>
				</div>
			</form>

			<script>
				jQuery(document).ready(function($) {
					// Modal functionality
					$('#add-manual-card-btn').on('click', function(e) {
						e.preventDefault();
						$('#manual-card-modal').fadeIn(200);
					});

					$('#close-modal, #cancel-modal').on('click', function() {
						$('#manual-card-modal').fadeOut(200);
						$('#manual-card-form')[0].reset();
					});

					// Close modal when clicking outside
					$(window).on('click', function(e) {
						if (e.target.id === 'manual-card-modal') {
							$('#manual-card-modal').fadeOut(200);
							$('#manual-card-form')[0].reset();
						}
					});

					// Select all functionality
					$('#select-all').on('change', function() {
						$('input[name="card_ids[]"]').prop('checked', $(this).prop('checked'));
					});

					// Update select-all state when individual checkboxes change
					$('input[name="card_ids[]"]').on('change', function() {
						var allChecked = $('input[name="card_ids[]"]').length === $('input[name="card_ids[]"]:checked').length;
						$('#select-all').prop('checked', allChecked);
					});

					// Validate form submission
					$('#promotional-cards-form').on('submit', function(e) {
						if ($('input[name="card_ids[]"]:checked').length === 0) {
							e.preventDefault();
							alert('<?php esc_html_e( 'Please select at least one promotional card to delete.', 'univoucher-for-woocommerce' ); ?>');
							return false;
						}
					});
				});
			</script>

			<style>
				.univoucher-promotional-cards-table {
					margin-top: 20px;
				}
				.univoucher-promotional-cards-table code {
					background: #f0f0f1;
					padding: 2px 6px;
					border-radius: 3px;
					font-size: 12px;
				}
				.status-badge {
					display: inline-block;
					padding: 4px 10px;
					border-radius: 4px;
					font-size: 12px;
					font-weight: 500;
				}
				.status-active {
					background: #d4edda;
					color: #155724;
				}
				.status-redeemed {
					background: #cce5ff;
					color: #004085;
				}
				.status-cancelled {
					background: #f8d7da;
					color: #721c24;
				}
				.check-column {
					width: 40px;
					text-align: center;
				}
			</style>
		</div>
		<?php
	}
}
