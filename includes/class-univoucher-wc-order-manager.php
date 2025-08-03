<?php
/**
 * UniVoucher For WooCommerce Order Management
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Order_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Order_Manager class.
 */
class UniVoucher_WC_Order_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Order_Manager
	 */
	protected static $_instance = null;

	/**
	 * Gift Card Manager instance.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	private $gift_card_manager;

	/**
	 * Main UniVoucher_WC_Order_Manager Instance.
	 *
	 * @return UniVoucher_WC_Order_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Order_Manager Constructor.
	 */
	public function __construct() {
		$this->gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Customer-facing hooks - show gift cards only for completed orders
		$display_position = get_option( 'univoucher_wc_cards_display_position', 'before' );
		
		if ( $display_position === 'before' ) {
			add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_customer_gift_cards' ) );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_customer_gift_cards' ) );
		}

		// Admin-facing hooks - show gift cards with assignment functionality
		add_action( 'woocommerce_admin_order_items_after_line_items', array( $this, 'display_admin_gift_cards' ) );
		
		// Email delivery hook - use lower priority to run after stock manager
		add_action( 'woocommerce_order_status_completed', array( $this, 'send_gift_cards_email' ), 20 );
		
		// AJAX handlers for admin card assignment and unassignment
		add_action( 'wp_ajax_univoucher_assign_product_cards', array( $this, 'ajax_assign_product_cards' ) );
		add_action( 'wp_ajax_univoucher_unassign_card', array( $this, 'ajax_unassign_card' ) );
		
		// AJAX handler for checking order assignment status
		add_action( 'wp_ajax_univoucher_check_order_assignment', array( $this, 'ajax_check_order_assignment' ) );
		add_action( 'wp_ajax_nopriv_univoucher_check_order_assignment', array( $this, 'ajax_check_order_assignment' ) );
		
		// check if univoucher order item needs processing or not - Automatically mark orders as "Completed"
		add_filter( 'woocommerce_order_item_needs_processing', array( $this, 'univoucher_check_item_needs_processing' ), 10, 3 );
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Order details page notice for unassigned cards
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_unassigned_cards_notice' ), 5 );
	}

	/**
	 * Display gift cards for customers (only for completed orders).
	 *
	 * @param WC_Order|int $order Order object or order ID.
	 */
	public function display_customer_gift_cards( $order ) {
		// Check if customer card display is enabled
		if ( ! get_option( 'univoucher_wc_show_in_order_details', true ) ) {
			return;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		if ( ! $order || ! $order->has_status( 'completed' ) ) {
			return;
		}

		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order->get_id() );
		
		if ( empty( $gift_cards ) ) {
			return;
		}

		$networks = array(1=>'Ethereum',10=>'Optimism',56=>'BNB Chain',137=>'Polygon',42161=>'Arbitrum',43114=>'Avalanche',8453=>'Base');
		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );

		?>
		<section class="woocommerce-univoucher-gift-cards">
			<h2><?php esc_html_e( 'UniVoucher Gift Cards:', 'univoucher-for-woocommerce' ); ?></h2>
			<div class="univoucher-cards-container">
				<?php foreach ( $gift_cards as $card ) : 
					$network_name = isset( $networks[ $card->chain_id ] ) ? $networks[ $card->chain_id ] : 'Chain ' . $card->chain_id;
				?>
					<div class="univoucher-card">
						<div class="card-header">
							<div class="card-amount">
								<img src="<?php echo esc_url( $plugin_url . 'admin/images/tokens/' . strtolower( $card->token_symbol ) . '.png' ); ?>" alt="<?php echo esc_attr( $card->token_symbol ); ?>" onerror="this.src='<?php echo esc_url( $plugin_url . 'admin/images/tokens/token.png' ); ?>'">
								<span><?php echo esc_html( number_format( (float) $card->amount, 4 ) ); ?> <?php echo esc_html( $card->token_symbol ); ?></span>
							</div>
							<div class="card-network">
								<img src="<?php echo esc_url( $plugin_url . 'admin/images/networks/' . $card->chain_id . '.png' ); ?>" alt="<?php echo esc_attr( $network_name ); ?>">
								<span><?php echo esc_html( $network_name ); ?></span>
							</div>
						</div>
						<div class="card-details">
							<div class="card-id">
								<strong><?php esc_html_e( 'Card ID:', 'univoucher-for-woocommerce' ); ?></strong>
								<code><?php echo esc_html( $card->card_id ); ?></code>
							</div>
							<div class="card-secret">
								<strong><?php esc_html_e( 'Card Secret:', 'univoucher-for-woocommerce' ); ?></strong>
								<code><?php echo esc_html( $card->card_secret ); ?></code>
							</div>
						</div>
						<div class="card-redeem">
							<?php esc_html_e( 'Redeem at:', 'univoucher-for-woocommerce' ); ?> 
							<a href="https://univoucher.com" target="_blank" rel="noopener noreferrer">univoucher.com</a> 
							<?php esc_html_e( 'or', 'univoucher-for-woocommerce' ); ?> 
							<a href="https://redeemnow.xyz" target="_blank" rel="noopener noreferrer">redeemnow.xyz</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<style>
		.univoucher-card{border:1px solid #ddd;border-radius:8px;padding:20px;margin:15px 0;background:#fff}
		.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
		.card-amount,.card-network{display:flex;align-items:center;gap:8px}
		.card-amount img,.card-network img{width:24px;height:24px}
		.card-details{margin:15px 0}
		.card-id,.card-secret{margin:8px 0}
		.card-id code,.card-secret code{background:#f5f5f5;padding:4px 8px;border-radius:4px;font-family:monospace;word-break:break-all;display:block;margin-top:4px}
		.card-redeem{margin-top:15px;padding-top:15px;border-top:1px solid #eee}
		</style>
		<?php
	}

	/**
	 * Display gift cards for admin with assignment functionality.
	 *
	 * @param int $order_id Order ID.
	 */
	public function display_admin_gift_cards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order_id );
		
		// Calculate ordered cards for UniVoucher products
		$ordered_cards = $this->get_ordered_cards_for_order( $order );
		$total_ordered = array_sum( $ordered_cards );
		
		// Only show if there are UniVoucher products in this order
		if ( $total_ordered === 0 ) {
			return;
		}

		// Check if this order status should show missing cards calculation
		$active_statuses = array( 'processing', 'on-hold', 'completed' );
		$show_missing_cards = in_array( $order->get_status(), $active_statuses );
		
		// Group cards by product
		$cards_by_product = array();
		foreach ( $gift_cards as $card ) {
			$cards_by_product[ $card->product_id ][] = $card;
		}
		
		?>
		</tbody>
		</table>
		
		<div class="univoucher-admin-section" style="margin: 20px 0; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'UniVoucher Gift Cards', 'univoucher-for-woocommerce' ); ?></h3>
			
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product / Card Details', 'univoucher-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Secret', 'univoucher-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'univoucher-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'univoucher-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Chain', 'univoucher-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'univoucher-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ordered_cards as $product_id => $ordered_quantity ) : 
						$assigned_cards = isset( $cards_by_product[ $product_id ] ) ? $cards_by_product[ $product_id ] : array();
						$assigned_quantity = count( $assigned_cards );
						$missing_quantity = max( 0, $ordered_quantity - $assigned_quantity );
						$product = wc_get_product( $product_id );
						$product_name = $product ? $product->get_name() : 'Product #' . $product_id;
					?>
						<tr style="background: #f8f9fa; border-top: 2px solid #dee2e6;">
							<td colspan="5" style="padding: 12px; font-weight: bold;">
								<?php echo esc_html( $product_name ); ?>
								<div style="font-weight: normal; margin-top: 5px; font-size: 13px;">
									<span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px; margin-right: 5px;">
										<?php 
										// translators: %d is the quantity ordered
										echo esc_html( sprintf( __( 'Ordered: %d', 'univoucher-for-woocommerce' ), $ordered_quantity ) ); 
										?>
									</span>
									<span style="background: #e8f5e8; padding: 3px 8px; border-radius: 3px; margin-right: 5px;">
										<?php 
										// translators: %d is the quantity assigned
										echo esc_html( sprintf( __( 'Assigned: %d', 'univoucher-for-woocommerce' ), $assigned_quantity ) ); 
										?>
									</span>
									<?php if ( $show_missing_cards && $missing_quantity > 0 ) : ?>
										<span style="background: #ffebee; padding: 3px 8px; border-radius: 3px; margin-right: 5px; color: #c62828;">
											<?php 
											// translators: %d is the quantity missing
											echo esc_html( sprintf( __( 'Missing: %d', 'univoucher-for-woocommerce' ), $missing_quantity ) ); 
											?>
										</span>
										<button type="button" class="assign-product-cards-btn button button-small" 
											data-order-id="<?php echo esc_attr( $order_id ); ?>" 
											data-product-id="<?php echo esc_attr( $product_id ); ?>"
											data-missing="<?php echo esc_attr( $missing_quantity ); ?>">
											<?php esc_html_e( 'Assign Missing Cards', 'univoucher-for-woocommerce' ); ?>
											<span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
										</button>
									<?php elseif ( ! $show_missing_cards ) : ?>
										<span style="background: #fff3cd; padding: 3px 8px; border-radius: 3px; color: #856404; font-style: italic;">
											<?php 
											echo esc_html( sprintf( 
												// translators: %s is the order status
												__( 'Order status "%s" - Cards will auto-assign when status changes to on-hold, processing, or completed', 'univoucher-for-woocommerce' ),
												$order->get_status()
											) ); 
											?>
										</span>
									<?php else : ?>
										<span style="background: #d4edda; padding: 3px 8px; border-radius: 3px; color: #155724;">
											<?php esc_html_e( 'Fully Assigned', 'univoucher-for-woocommerce' ); ?>
										</span>
									<?php endif; ?>
								</div>
								<div id="assign-result-<?php echo esc_attr( $product_id ); ?>" style="margin-top: 10px;"></div>
							</td>
						</tr>
						
						<?php if ( ! empty( $assigned_cards ) ) : ?>
							<?php foreach ( $assigned_cards as $card ) : ?>
								<tr>
									<td style="padding-left: 30px;"><?php echo esc_html( $card->card_id ); ?></td>
									<td><code style="font-size: 11px;"><?php echo esc_html( $card->card_secret ); ?></code></td>
									<td><?php echo esc_html( number_format( (float) $card->amount, 4 ) . ' ' . $card->token_symbol ); ?></td>
									<td>
										<span style="color: <?php echo $card->delivery_status === 'delivered' ? '#28a745' : '#6c757d'; ?>">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $card->delivery_status ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $card->chain_id ); ?></td>
									<td>
										<?php if ( $show_missing_cards ) : ?>
											<button type="button" class="unassign-card-btn button button-small" 
												data-card-id="<?php echo esc_attr( $card->id ); ?>"
												data-order-id="<?php echo esc_attr( $order_id ); ?>"
												data-product-id="<?php echo esc_attr( $product_id ); ?>"
												style="font-size: 11px; padding: 2px 6px;">
												<?php esc_html_e( 'Unassign', 'univoucher-for-woocommerce' ); ?>
												<span class="spinner" style="float: none; margin: 0 0 0 3px;"></span>
											</button>
											<?php if ( $card->delivery_status === 'delivered' ) : ?>
												<br><small style="color: #d63638; font-style: italic; font-size: 10px;">
													<?php esc_html_e( '(will mark inactive)', 'univoucher-for-woocommerce' ); ?>
												</small>
											<?php endif; ?>
										<?php else : ?>
											<span style="color: #666; font-size: 11px; font-style: italic;">
												<?php esc_html_e( 'N/A', 'univoucher-for-woocommerce' ); ?>
											</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="6" style="padding-left: 30px; color: #6c757d; font-style: italic;">
									<?php esc_html_e( 'No cards assigned yet', 'univoucher-for-woocommerce' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Assign cards functionality
			$('.assign-product-cards-btn').on('click', function() {
				var $btn = $(this);
				var $spinner = $btn.find('.spinner');
				var productId = $btn.data('product-id');
				var $result = $('#assign-result-' + productId);
				var orderId = $btn.data('order-id');
				var missing = $btn.data('missing');
				
				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$result.empty();
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'univoucher_assign_product_cards',
						order_id: orderId,
						product_id: productId,
						missing_quantity: missing,
						nonce: '<?php echo esc_attr( wp_create_nonce( 'univoucher_assign_product_cards' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<div style="color: #28a745; padding: 8px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Success:</strong> ' + response.data.message + '</div>');
							// Reload page after 2 seconds to show updated cards
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							$result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> ' + response.data.message + '</div>');
						}
					},
					error: function() {
						$result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> Failed to assign cards. Please try again.</div>');
					},
					complete: function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// Unassign card functionality
			$('.unassign-card-btn').on('click', function() {
				var $btn = $(this);
				var $spinner = $btn.find('.spinner');
				var cardId = $btn.data('card-id');
				var orderId = $btn.data('order-id');
				var productId = $btn.data('product-id');
				var $result = $('#assign-result-' + productId);
				var $row = $btn.closest('tr');
				var deliveryStatus = $row.find('td:nth-child(4)').text().trim().toLowerCase();
				
				// Different confirmation messages based on delivery status
				var confirmMessage;
				if (deliveryStatus.includes('delivered') && !deliveryStatus.includes('never')) {
					confirmMessage = '<?php esc_html_e( 'Are you sure you want to unassign this delivered card? It will be marked as INACTIVE (not available for other orders).', 'univoucher-for-woocommerce' ); ?>';
				} else {
					confirmMessage = '<?php esc_html_e( 'Are you sure you want to unassign this card? It will become available for assignment to other orders.', 'univoucher-for-woocommerce' ); ?>';
				}
				
				// Confirm action
				if (!confirm(confirmMessage)) {
					return;
				}
				
				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$result.empty();
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'univoucher_unassign_card',
						card_id: cardId,
						order_id: orderId,
						product_id: productId,
						nonce: '<?php echo esc_attr( wp_create_nonce( 'univoucher_unassign_card' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<div style="color: #28a745; padding: 8px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Success:</strong> ' + response.data.message + '</div>');
							// Reload page after 2 seconds to show updated cards
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							$result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> ' + response.data.message + '</div>');
						}
					},
					error: function() {
						$result.html('<div style="color: #721c24; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; margin-top: 5px;"><strong>Error:</strong> Failed to unassign card. Please try again.</div>');
					},
					complete: function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});
		});
		</script>
		</div>
		
		<table class="woocommerce_order_items">
		<tbody>
		<?php
	}

	/**
	 * Get net ordered cards count for each UniVoucher product in the order (after refunds).
	 * Aggregates net quantities for the same product across multiple order items.
	 *
	 * @param WC_Order $order Order object.
	 * @return array Product ID => total net quantity mapping.
	 */
	private function get_ordered_cards_for_order( $order ) {
		$ordered_cards = array();
		
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			
			// Check if this product has UniVoucher enabled
			if ( UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
				// Calculate net quantity
				$original_quantity = $item->get_quantity();
				$refunded_quantity = abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
				$net_quantity = $original_quantity - $refunded_quantity;
				
				// Aggregate net quantities if the same product appears in multiple items
				if ( isset( $ordered_cards[ $product_id ] ) ) {
					$ordered_cards[ $product_id ] += $net_quantity;
				} else {
					$ordered_cards[ $product_id ] = $net_quantity;
				}
			}
		}
		
		return $ordered_cards;
	}



	/**
	 * AJAX handler for assigning cards to a specific product in an order.
	 */
	public function ajax_assign_product_cards() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'univoucher_assign_product_cards' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$missing_quantity = absint( $_POST['missing_quantity'] ?? 0 );
		
		if ( ! $order_id || ! $product_id || ! $missing_quantity ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ) );
		}

		// Check if order status allows card assignment
		$active_statuses = array( 'processing', 'on-hold', 'completed' );
		if ( ! in_array( $order->get_status(), $active_statuses ) ) {
			wp_send_json_error( array( 'message' => 'Card assignment not allowed for this order status.' ) );
		}

		// Check if product has UniVoucher enabled
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product does not have UniVoucher enabled.' ) );
		}

		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = esc_sql( $database->uv_get_gift_cards_table() );

		// Get available cards for this product
		$available_cards = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, card_id FROM $table WHERE product_id = %d AND status = 'available' ORDER BY created_at ASC LIMIT %d",
			$product_id,
			$missing_quantity
		) );

		if ( count( $available_cards ) < $missing_quantity ) {
			wp_send_json_error( array( 
				'message' => sprintf( 
					'Only %d cards available for this product, but %d missing.',
					count( $available_cards ),
					$missing_quantity
				)
			) );
		}

		// Determine delivery status based on order status
		$delivery_status = ( $order->get_status() === 'completed' ) ? 'delivered' : 'never delivered';
		
		// Assign the cards
		$card_ids = wp_list_pluck( $available_cards, 'id' );
		$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
		
		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE $table SET status = 'sold', order_id = %d, delivery_status = %s WHERE id IN ($card_ids_placeholder)",
			array_merge( array( $order_id, $delivery_status ), $card_ids )
		) );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Failed to assign cards to order.' ) );
		}

		// Add order notes
		$product = wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : 'Product #' . $product_id;
		
		foreach ( $available_cards as $card ) {
			$order->add_order_note( sprintf( 
				// translators: %1$s is the card ID, %2$s is the product name, %3$s is the delivery status
				__( 'UniVoucher Card %1$s assigned to %2$s (status: sold, delivery: %3$s)', 'univoucher-for-woocommerce' ),
				$card->card_id,
				$product_name,
				$delivery_status
			) );
		}

		wp_send_json_success( array( 
			'message' => sprintf( 
				// translators: %1$d is the number of cards, %2$s is the product name
				_n( '%1$d card assigned to %2$s.', '%1$d cards assigned to %2$s.', count( $available_cards ), 'univoucher-for-woocommerce' ),
				count( $available_cards ),
				$product_name
			)
		) );
	}

	/**
	 * AJAX handler for unassigning a card from an order.
	 */
	public function ajax_unassign_card() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'univoucher_unassign_card' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$card_id = absint( $_POST['card_id'] ?? 0 );
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		
		if ( ! $card_id || ! $order_id || ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ) );
		}

		// Check if order status allows card unassignment
		$active_statuses = array( 'processing', 'on-hold', 'completed' );
		if ( ! in_array( $order->get_status(), $active_statuses ) ) {
			wp_send_json_error( array( 'message' => 'Card unassignment not allowed for this order status.' ) );
		}

		// Check if product has UniVoucher enabled
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product does not have UniVoucher enabled.' ) );
		}

		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = esc_sql( $database->uv_get_gift_cards_table() );

		// Get the card to verify it exists and belongs to this order
		$card = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, card_id, delivery_status FROM $table WHERE id = %d AND order_id = %d AND product_id = %d AND status = 'sold'",
			$card_id,
			$order_id,
			$product_id
		) );

		if ( ! $card ) {
			wp_send_json_error( array( 'message' => 'Card not found or not assigned to this order.' ) );
		}

		// Handle delivered vs never delivered cards differently
		if ( $card->delivery_status === 'delivered' ) {
			// Mark delivered card as inactive and reduce stock (like a return)
			$result = $wpdb->update(
				$table,
				array( 
					'status' => 'inactive',
					'delivery_status' => 'returned after delivery',
					'order_id' => null
				),
				array( 'id' => $card_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);

			if ( $result === false ) {
				wp_send_json_error( array( 'message' => 'Failed to unassign card from order.' ) );
			}

			// Reduce WooCommerce stock for inactivated card
			$product = wc_get_product( $product_id );
			if ( $product && $product->managing_stock() ) {
				$current_stock = $product->get_stock_quantity();
				$new_stock_level = $current_stock - 1;
				$product->set_stock_quantity( $new_stock_level );
				$product->save();
			}

			// Add order note for inactivated card
			$order->add_order_note( sprintf( 
				// translators: %s is the card ID
				__( 'UniVoucher Card %s marked inactive after manual unassignment (reducing stock)', 'univoucher-for-woocommerce' ),
				$card->card_id
			) );

			wp_send_json_success( array( 
				'message' => sprintf( 
					// translators: %s is the card ID
					__( 'Delivered card %s unassigned and marked inactive. Product stock reduced by 1.', 'univoucher-for-woocommerce' ),
					$card->card_id
				)
			) );
		} else {
			// Unassign never delivered card (make it available again)
			$result = $wpdb->update(
				$table,
				array( 
					'status' => 'available',
					'order_id' => null
				),
				array( 'id' => $card_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			if ( $result === false ) {
				wp_send_json_error( array( 'message' => 'Failed to unassign card from order.' ) );
			}

			// Add order note for restored card
			$order->add_order_note( sprintf( 
				// translators: %s is the card ID
				__( 'UniVoucher Card %s unassigned from order (status: available, delivery: never delivered)', 'univoucher-for-woocommerce' ),
				$card->card_id
			) );

			wp_send_json_success( array( 
				'message' => sprintf( 
					// translators: %s is the card ID
					__( 'Card %s unassigned and available for other orders.', 'univoucher-for-woocommerce' ),
					$card->card_id
				)
			) );
		}
	}

	/**
	 * Send gift cards email when order is completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function send_gift_cards_email( $order_id ) {
		// Check if email delivery is enabled
		if ( ! get_option( 'univoucher_wc_send_email_cards', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if we should only send email when order is fully assigned
		$send_email_only_fully_assigned = get_option( 'univoucher_wc_send_email_only_fully_assigned', true );
		
		if ( $send_email_only_fully_assigned ) {
			// Check if the order is fully assigned
			if ( ! $this->is_order_fully_assigned( $order ) ) {
				return;
			}
		}

		$gift_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order_id );
		if ( empty( $gift_cards ) ) {
			return;
		}

		// Get email template and subject
		$template = get_option( 'univoucher_wc_email_template', '<h2>Hello {customer_name},</h2><p>Your UniVoucher gift cards are ready!</p><p><strong>Order:</strong> #{order_number}</p><div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">{cards_content}</div><p><strong>Redeem your cards at:</strong></p><ul><li><a href="https://univoucher.com" target="_blank">https://univoucher.com</a></li><li><a href="https://redeemnow.xyz" target="_blank">https://redeemnow.xyz</a></li></ul><p>Thank you for your purchase!</p><p>Best regards,<br>{site_name}</p>' );
		$subject_template = get_option( 'univoucher_wc_email_subject', 'Your UniVoucher Gift Cards - Order #{order_number}' );

		// Build cards content
		$cards_content = '';
		$networks = array(1=>'Ethereum',10=>'Optimism',56=>'BNB Chain',137=>'Polygon',42161=>'Arbitrum',43114=>'Avalanche',8453=>'Base');
		
		foreach ( $gift_cards as $card ) {
			$network_name = isset( $networks[ $card->chain_id ] ) ? $networks[ $card->chain_id ] : 'Chain ' . $card->chain_id;
			$cards_content .= sprintf(
				'<div style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin: 10px 0; background: #fff;"><strong>Card ID:</strong> %s<br><strong>Card Secret:</strong> <code style="background: #f5f5f5; padding: 2px 4px; border-radius: 3px;">%s</code><br><strong>Amount:</strong> %s %s<br><strong>Network:</strong> %s</div>',
				$card->card_id,
				$card->card_secret,
				number_format( (float) $card->amount, 4 ),
				$card->token_symbol,
				$network_name
			);
		}

		// Replace placeholders in subject and content
		$replacements = array( 
			$order->get_billing_first_name() ?: $order->get_billing_email(),
			$order->get_order_number(),
			$cards_content,
			get_bloginfo( 'name' )
		);
		
		$email_content = str_replace(
			array( '{customer_name}', '{order_number}', '{cards_content}', '{site_name}' ),
			$replacements,
			$template
		);
		
		$email_subject = str_replace(
			array( '{customer_name}', '{order_number}', '{site_name}' ),
			array( 
				$order->get_billing_first_name() ?: $order->get_billing_email(),
				$order->get_order_number(),
				get_bloginfo( 'name' )
			),
			$subject_template
		);

		// Send email
		$to = $order->get_billing_email();
		$headers = array(
		    'Content-Type: text/html; charset=UTF-8'
		);

		$email_sent = wp_mail( $to, $email_subject, $email_content, $headers );
		
		// Log email sending in order notes
		if ( $email_sent ) {
			$order->add_order_note( sprintf( 
				// translators: %s is the customer email address
				__( 'UniVoucher: Gift cards email sent successfully to %s', 'univoucher-for-woocommerce' ),
				$to
			) );
		} else {
			$order->add_order_note( sprintf( 
				// translators: %s is the customer email address
				__( 'UniVoucher: Failed to send gift cards email to %s', 'univoucher-for-woocommerce' ),
				$to
			) );
		}
	}

	/**
	 * Check if an order is fully assigned (all UniVoucher products have their required cards assigned).
	 *
	 * @param WC_Order $order The order object.
	 * @return bool True if the order is fully assigned, false otherwise.
	 */
	private function is_order_fully_assigned( $order ) {
		$ordered_cards = $this->get_ordered_cards_for_order( $order );
		
		if ( empty( $ordered_cards ) ) {
			// No UniVoucher products in this order
			return true;
		}

		$assigned_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order->get_id() );
		
		// Group assigned cards by product ID
		$assigned_by_product = array();
		foreach ( $assigned_cards as $card ) {
			if ( ! isset( $assigned_by_product[ $card->product_id ] ) ) {
				$assigned_by_product[ $card->product_id ] = 0;
			}
			$assigned_by_product[ $card->product_id ]++;
		}

		// Check if each product has the required number of cards assigned
		foreach ( $ordered_cards as $product_id => $ordered_quantity ) {
			$assigned_quantity = isset( $assigned_by_product[ $product_id ] ) ? $assigned_by_product[ $product_id ] : 0;
			
			if ( $assigned_quantity < $ordered_quantity ) {
				// This product doesn't have enough cards assigned
				return false;
			}
		}

		// All products have their required cards assigned
		return true;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only on order edit pages
		if ( 'post.php' !== $hook || ! isset( $_GET['post'] ) ) {
			return;
		}
		
		$post_id = absint( wp_unslash( $_GET['post'] ) );
		$post = get_post( $post_id );
		if ( ! $post || 'shop_order' !== $post->post_type ) {
			return;
		}
		
		// Scripts are inlined in the display method for simplicity
	}

	/**
	 * Display notice for unassigned cards on thank you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function display_unassigned_cards_notice( $order_id ) {
		// Check if notice is enabled
		if ( ! get_option( 'univoucher_wc_show_unassigned_notice', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order is fully assigned
		if ( $this->is_order_fully_assigned( $order ) ) {
			return;
		}

		// Display notice and auto-refresh script
		?>
		<div id="univoucher-unassigned-notice" class="woocommerce-message woocommerce-message--info">
			<span class="dashicons dashicons-clock" style="vertical-align: middle;"></span>
			<span><?php echo esc_html( get_option( 'univoucher_wc_unassigned_notice_text', __( 'Your order contains gift cards that are still being processed. This page will automatically refresh once all cards are ready.', 'univoucher-for-woocommerce' ) ) ); ?></span>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			function checkOrderStatus() {
				$.ajax({
					url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
					type: 'POST',
					data: {
						action: 'univoucher_check_order_assignment',
						order_id: <?php echo esc_js( $order_id ); ?>,
						nonce: '<?php echo esc_js( wp_create_nonce( 'univoucher_check_order_assignment' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.fully_assigned) {
							location.reload();
						}
					},
					error: function() {
						// Continue checking even if there's an error
					}
				});
			}

			// Check every 10 seconds
			setInterval(checkOrderStatus, 10000);
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to check if order is fully assigned.
	 */
	public function ajax_check_order_assignment() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_check_order_assignment' ) ) {
			wp_die();
		}

		$order_id = absint( $_POST['order_id'] );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error();
		}

		$fully_assigned = $this->is_order_fully_assigned( $order );
		wp_send_json_success( array( 'fully_assigned' => $fully_assigned ) );
	}

	/**
	 * Auto-complete orders with UniVoucher products.
	 *
	 * @param bool       $needs_processing Whether the item needs processing.
	 * @param WC_Product $product          The product object.
	 * @param int        $order_id         The order ID.
	 * @return bool Whether the item needs processing.
	 */
	public function univoucher_check_item_needs_processing( $needs_processing, $product, $order_id ) {
		// Check if auto-completion is enabled
		$auto_complete_enabled = get_option( 'univoucher_wc_auto_complete_orders', true );
		
		if ( ! $auto_complete_enabled ) {
			return $needs_processing;
		}

		// Check if this product has UniVoucher enabled
		$is_univoucher_enabled = UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product );
		
		if ( $is_univoucher_enabled ) {
			
			// Check for missing cards and determine if processing is needed
			$backorder_status = get_option( 'univoucher_wc_backorder_initial_status', 'processing' );
			
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$ordered_cards = $this->get_ordered_cards_for_order( $order );
				
				$product_id = $product->get_id();
				$ordered_quantity = isset( $ordered_cards[ $product_id ] ) ? $ordered_cards[ $product_id ] : 0;
				
				$assigned_cards = $this->gift_card_manager->uv_get_gift_cards_for_order( $order_id );
				
				$assigned_quantity = 0;
				foreach ( $assigned_cards as $card ) {
					if ( $card->product_id == $product_id ) {
						$assigned_quantity++;
					}
				}
				
				if ( $ordered_quantity > $assigned_quantity ) {
					$missing = $ordered_quantity - $assigned_quantity;
					// Check available inventory for this product
					global $wpdb;
					$database = UniVoucher_WC_Database::instance();
					$table = esc_sql( $database->uv_get_gift_cards_table() );
					$available_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE product_id = %d AND status = 'available'", $product_id ) );
					if ( $available_count < $missing ) {
						// Return true for processing, false for completed based on setting
						return ( $backorder_status === 'processing' );
					}
				}
			}
			
			// For UniVoucher products with sufficient inventory, don't need processing (auto-complete)
			return false;
		}

		// For non-UniVoucher products, use default behavior
		return $needs_processing;
	}
} 