<?php
/**
 * UniVoucher For WooCommerce Stock Management
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Core
 * 
 * DUAL STATUS SYSTEM EXPLANATION
 * ==============================
 * 
 * This class manages a dual status system for gift cards:
 * 
 * PRIMARY STATUS (affects stock and statistics):
 * - available: Card is ready to be sold
 * - sold: Card is assigned to an order  
 * - inactive: Card cannot be sold (returned/refunded)
 * 
 * DELIVERY STATUS (informational tracking):
 * - never delivered: Card has not been delivered to customer
 * - delivered: Card has been delivered successfully
 * - returned after delivery: Card was delivered but later returned
 * 
 * SCENARIOS AND STATUS TRANSITIONS:
 * =================================
 * 
 * 1. INVENTORY ADDED:
 *    → status: available + delivery_status: never delivered
 *    
 * 2. STOCK REDUCED (woocommerce_reduce_order_item_stock):
 *    → status: sold + delivery_status: never delivered
 *    → Adds order note: "Card {card_id} assigned to order (status: sold, delivery: never delivered)"
 *    
 * 3. ORDER COMPLETED (woocommerce_order_status_completed):
 *    → status: sold + delivery_status: delivered
 *    → Adds order note: "Card {card_id} marked as delivered"
 *    
 * 4. STOCK RESTORED (woocommerce_restore_order_item_stock):
 *    IF delivery_status = 'never delivered':
 *      → status: available + delivery_status: never delivered
 *      → Adds order note: "Card {card_id} released from order (status: available, delivery: never delivered)"
 *    ELSE (card was delivered):
 *      → status: inactive + delivery_status: returned after delivery
 *      → Reduce WooCommerce stock by number of inactivated cards
 *      → Adds order note: "Card {card_id} marked inactive after return (reducing stock, delivery: returned after delivery)"
 *      
 * 5. REFUND RESTOCK (woocommerce_restock_refunded_item):
 *    Same logic as stock restored above
 *    
 * 6. MANUAL ORDER EDIT (woocommerce_saved_order_items):
 *    Applies appropriate logic based on quantity changes and delivery status
 *    
 * 7. ORDER DELETION (woocommerce_delete_order):
 *    IF delivery_status = 'never delivered':
 *      → status: available + delivery_status: never delivered
 *      → Increase WooCommerce stock by number of restored cards
 *    ELSE (card was delivered):
 *      → status: inactive + delivery_status: returned after delivery
 *      → No stock increase 
 * 
 * The system ensures that:
 * - Only 'available' cards count toward sellable stock
 * - 'inactive' cards are permanently removed from circulation
 * - Delivered cards that are returned don't re-enter circulation
 * - All status changes are logged in order notes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Stock_Manager class.
 */
class UniVoucher_WC_Stock_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Stock_Manager
	 */
	protected static $_instance = null;

	/**
	 * Database instance.
	 *
	 * @var UniVoucher_WC_Database
	 */
	private $database;

	/**
	 * Gift card manager instance.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	private $gift_card_manager;

	/**
	 * Main UniVoucher_WC_Stock_Manager Instance.
	 *
	 * @return UniVoucher_WC_Stock_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Stock_Manager Constructor.
	 */
	public function __construct() {
		$this->database = UniVoucher_WC_Database::instance();
		$this->gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Hook into WooCommerce native stock management functions
		add_action( 'woocommerce_reduce_order_item_stock', array( $this, 'uv_mark_gift_cards_sold_for_order_item' ), 10, 3 );
		add_action( 'woocommerce_restore_order_item_stock', array( $this, 'uv_mark_gift_cards_available_for_order_item' ), 10, 4 );
		add_action( 'woocommerce_saved_order_items', array( $this, 'uv_handle_order_items_saved' ), 10, 2 );
		add_action( 'woocommerce_restock_refunded_item', array( $this, 'uv_handle_refund_restock' ), 10, 5 );
		
		// Hook into WooCommerce order status changes
		add_action( 'woocommerce_order_status_completed', array( $this, 'uv_mark_gift_cards_delivered_for_order' ) );
		
		// Hook into WooCommerce order deletion
		add_action( 'woocommerce_delete_order', array( $this, 'uv_restore_gift_cards_on_order_deletion' ) );
	}

	/**
	 * Mark gift cards as sold when stock is reduced for a specific order item.
	 *
	 * @param WC_Order_Item_Product $item Order item data.
	 * @param array                 $change Change details.
	 * @param WC_Order              $order Order data.
	 */
	public function uv_mark_gift_cards_sold_for_order_item( $item, $change, $order ) {
		if ( ! $order || ! $item ) {
			return;
		}

		$product_id = $item->get_product_id();
		
		// Use the actual stock change amount if available, otherwise fall back to item quantity
		$quantity = is_array( $change ) && isset( $change['from'] ) && isset( $change['to'] ) 
			? abs( $change['from'] - $change['to'] ) 
			: $item->get_quantity();

		// Check if this product has UniVoucher enabled
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			return;
		}

		// Mark the ordered number of gift cards as sold
		global $wpdb;

		// Get all available gift cards for this product
		$cards = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, card_id FROM {$this->database->uv_get_gift_cards_table()}
			WHERE product_id = %d AND status = 'available' 
			ORDER BY created_at ASC",
			$product_id
		) );

		$available_count = count( $cards );
		$cards_to_assign = min( $available_count, $quantity );
		$unassigned_count = $quantity - $cards_to_assign;

		if ( $cards_to_assign > 0 ) {
			// Take only the cards we can assign
			$cards_to_process = array_slice( $cards, 0, $cards_to_assign );
			
			// Mark the cards as sold (delivery_status remains 'never delivered')
			$card_ids = wp_list_pluck( $cards_to_process, 'id' );
			$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
			
			$result = $wpdb->query( $wpdb->prepare(
				"UPDATE {$this->database->uv_get_gift_cards_table()}
				SET status = 'sold', order_id = %d 
				WHERE id IN ($card_ids_placeholder)",
				array_merge( array( $order->get_id() ), $card_ids )
			) );

			if ( $result === false ) {
				return;
			}

			// Create aggregated order note
			$assigned_card_ids = wp_list_pluck( $cards_to_process, 'card_id' );
			$card_ids_string = implode( ', ', $assigned_card_ids );
			
			$note_message = sprintf( 
				// translators: %1$d is the number of cards assigned, %2$s is the card IDs, %3$d is the number of unassigned cards
				__( 'UniVoucher: %1$d cards assigned to order (IDs: %2$s)', 'univoucher-for-woocommerce' ),
				$cards_to_assign,
				$card_ids_string
			);
			
			if ( $unassigned_count > 0 ) {
				$note_message .= sprintf( 
					// translators: %d is the number of unassigned cards
					__( ' - %d cards unassigned due to insufficient stock', 'univoucher-for-woocommerce' ),
					$unassigned_count
				);
			}
			
			$order->add_order_note( $note_message );
		} else {
			// No cards available at all
			$order->add_order_note( sprintf( 
				// translators: %d is the number of cards requested
				__( 'UniVoucher: No cards available for assignment - %d cards requested but none in stock', 'univoucher-for-woocommerce' ),
				$quantity
			) );
		}

		// WooCommerce handles stock quantity/status automatically
	}

	/**
	 * Handle gift cards when WooCommerce restores stock for an order item.
	 * Cards are handled differently based on their delivery status.
	 *
	 * @param WC_Order_Item_Product $item Order item data.
	 * @param int                   $new_stock New stock level.
	 * @param int                   $old_stock Old stock level.
	 * @param WC_Order              $order Order object.
	 */
	public function uv_mark_gift_cards_available_for_order_item( $item, $new_stock, $old_stock, $order ) {
		if ( ! $order || ! $item ) {
			return;
		}

		$product_id = $item->get_product_id();

		// Check if this product has UniVoucher enabled
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			return;
		}

		// Calculate how many cards to restore (stock increase amount)
		$quantity_to_restore = $new_stock - $old_stock;
		
		if ( $quantity_to_restore <= 0 ) {
			return;
		}

		global $wpdb;
		
		// Get the most recently sold cards for this order/product to restore
		$cards_to_restore = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, card_id, delivery_status FROM {$this->database->uv_get_gift_cards_table()}
			WHERE order_id = %d AND product_id = %d AND status = 'sold'
			ORDER BY id DESC 
			LIMIT %d",
			$order->get_id(),
			$product_id,
			$quantity_to_restore
		) );

		if ( ! $cards_to_restore ) {
			return;
		}

		$cards_never_delivered = array();
		$cards_delivered = array();

		// Separate cards by delivery status
		foreach ( $cards_to_restore as $card ) {
			if ( $card->delivery_status === 'never delivered' ) {
				$cards_never_delivered[] = $card;
			} else {
				$cards_delivered[] = $card;
			}
		}

		// Handle never delivered cards - restore to available
		if ( ! empty( $cards_never_delivered ) ) {
			$card_ids = wp_list_pluck( $cards_never_delivered, 'id' );
			$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
			
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->database->uv_get_gift_cards_table()}
				SET status = 'available', order_id = NULL 
				WHERE id IN ($card_ids_placeholder)",
				$card_ids
			) );

			// Add order notes for restored cards
			foreach ( $cards_never_delivered as $card ) {
				$order->add_order_note( sprintf( 
					// translators: %s is the card ID
					__( 'UniVoucher Card %s released from order (status: available, delivery: never delivered)', 'univoucher-for-woocommerce' ),
					$card->card_id
				) );
			}
		}

		// Handle delivered cards - mark as inactive and reduce stock
		if ( ! empty( $cards_delivered ) ) {
			$card_ids = wp_list_pluck( $cards_delivered, 'id' );
			$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
			
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->database->uv_get_gift_cards_table()}
				SET status = 'inactive', delivery_status = 'returned after delivery', order_id = NULL 
				WHERE id IN ($card_ids_placeholder)",
				$card_ids
			) );

			// Reduce WooCommerce stock for inactivated cards
			$product = wc_get_product( $product_id );
			if ( $product && $product->managing_stock() ) {
				$current_stock = $product->get_stock_quantity();
				$new_stock_level = $current_stock - count( $cards_delivered );
				$product->set_stock_quantity( $new_stock_level );
				$product->save();
			}

			// Add order notes for inactivated cards
			foreach ( $cards_delivered as $card ) {
				$order->add_order_note( sprintf( 
					// translators: %s is the card ID
					__( 'UniVoucher Card %s released from order (status: inactive, delivery: returned after delivery) - reducing stock', 'univoucher-for-woocommerce' ),
					$card->card_id
				) );
			}
		}

		// WooCommerce handles stock quantity/status automatically for available cards
	}

	/**
	 * Handle direct order item saves when admin manually edits orders.
	 * Applies delivery status logic when releasing cards.
	 * 
	 * FIXED: Now properly aggregates quantities by product to avoid
	 * incorrect card assignments when multiple line items exist.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items Order items data.
	 */
	public function uv_handle_order_items_saved( $order_id, $items ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only handle orders that would have stock managed
		if ( ! in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
			return;
		}

		// First, aggregate NET quantities by product (original quantity minus refunded quantity)
		$product_quantities = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			
			// Check if this product has UniVoucher enabled
			if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
				continue;
			}
			
			// Calculate net quantity
			$original_quantity = $item->get_quantity();
			$refunded_quantity = abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
			$net_quantity = $original_quantity - $refunded_quantity;
			
			// Aggregate net quantities for the same product
			if ( ! isset( $product_quantities[ $product_id ] ) ) {
				$product_quantities[ $product_id ] = 0;
			}
			$product_quantities[ $product_id ] += $net_quantity;
		}

		// Now process each product with its total quantity
		foreach ( $product_quantities as $product_id => $total_quantity ) {
			global $wpdb;
			
			// Get currently assigned gift cards for this product in this order	
			$assigned_cards = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, card_id, status, delivery_status FROM {$this->database->uv_get_gift_cards_table()}
				WHERE order_id = %d AND product_id = %d AND status = 'sold'",
				$order_id,
				$product_id
			) );

			$assigned_count = count( $assigned_cards );

			if ( $total_quantity > $assigned_count ) {
				// Need to assign more cards
				$cards_needed = $total_quantity - $assigned_count;
				
				$available_cards = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, card_id FROM {$this->database->uv_get_gift_cards_table()}
					WHERE product_id = %d AND status = 'available' 
					ORDER BY created_at ASC 
					LIMIT %d",
					$product_id,
					$cards_needed
				) );

				if ( count( $available_cards ) >= $cards_needed ) {
					$card_ids = wp_list_pluck( $available_cards, 'id' );
					$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
					
					$wpdb->query( $wpdb->prepare(
						"UPDATE {$this->database->uv_get_gift_cards_table()}
						SET status = 'sold', order_id = %d 
						WHERE id IN ($card_ids_placeholder)",
						array_merge( array( $order_id ), $card_ids )
					) );

					// Add order notes for assigned cards
					foreach ( $available_cards as $card ) {
						$order->add_order_note( sprintf( 
							// translators: %s is the card ID
							__( 'UniVoucher Card %s assigned during manual edit (status: sold, delivery: never delivered)', 'univoucher-for-woocommerce' ),
							$card->card_id
						) );
					}
				}
			} elseif ( $total_quantity < $assigned_count ) {
				// Need to release some cards
				$cards_to_release = $assigned_count - $total_quantity;
				
				// Release the most recently assigned cards first
				$cards_to_release_list = array_slice( $assigned_cards, -$cards_to_release );

				$cards_never_delivered = array();
				$cards_delivered = array();

				// Separate cards by delivery status
				foreach ( $cards_to_release_list as $card ) {
					if ( $card->delivery_status === 'never delivered' ) {
						$cards_never_delivered[] = $card;
					} else {
						$cards_delivered[] = $card;
					}
				}

				// Handle never delivered cards - restore to available
				if ( ! empty( $cards_never_delivered ) ) {
					$card_ids = wp_list_pluck( $cards_never_delivered, 'id' );
					$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
					
					$wpdb->query( $wpdb->prepare(
						"UPDATE {$this->database->uv_get_gift_cards_table()}
						SET status = 'available', order_id = NULL 
						WHERE id IN ($card_ids_placeholder)",
						$card_ids
					) );

					// Add order notes for restored cards
					foreach ( $cards_never_delivered as $card ) {
						$order->add_order_note( sprintf( 
							// translators: %s is the card ID
							__( 'UniVoucher Card %s released during manual edit (status: available, delivery: never delivered)', 'univoucher-for-woocommerce' ),
							$card->card_id
						) );
					}
				}

				// Handle delivered cards - mark as inactive and reduce stock
				if ( ! empty( $cards_delivered ) ) {
					$card_ids = wp_list_pluck( $cards_delivered, 'id' );
					$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
					
					$wpdb->query( $wpdb->prepare(
						"UPDATE {$this->database->uv_get_gift_cards_table()}
						SET status = 'inactive', delivery_status = 'returned after delivery', order_id = NULL 
						WHERE id IN ($card_ids_placeholder)",
						$card_ids
					) );

					// Reduce WooCommerce stock for inactivated cards
					$product = wc_get_product( $product_id );
					if ( $product && $product->managing_stock() ) {
						$current_stock = $product->get_stock_quantity();
						$new_stock_level = $current_stock - count( $cards_delivered );
						$product->set_stock_quantity( $new_stock_level );
						$product->save();
					}

					// Add order notes for inactivated cards
					foreach ( $cards_delivered as $card ) {
						$order->add_order_note( sprintf( 
							// translators: %s is the card ID
							__( 'UniVoucher Card %s released after manual edit (status: inactive, delivery: returned after delivery) - reducing stock', 'univoucher-for-woocommerce' ),
							$card->card_id
						) );
					}
				}
			}
			// If $total_quantity == $assigned_count, do nothing (quantities match)
		}
	}

	/**
	 * Handle restocking during refunds.
	 * Uses the same logic as restore stock - cards handled based on delivery status.
	 *
	 * @param int              $product_id Product ID.
	 * @param int              $old_stock Old stock level.
	 * @param int              $new_stock New stock level.
	 * @param WC_Order         $order Order object.
	 * @param WC_Product|false $product Product object.
	 */
	public function uv_handle_refund_restock( $product_id, $old_stock, $new_stock, $order, $product ) {
		if ( ! $order || ! $product_id ) {
			return;
		}

		// Check if this product has UniVoucher enabled
		if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
			return;
		}

		// Calculate how many cards to restore
		$quantity_to_restore = $new_stock - $old_stock;
		
		if ( $quantity_to_restore <= 0 ) {
			return;
		}

		global $wpdb;
		
		// Get the most recently sold cards for this order/product to restore
		$cards_to_restore = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, card_id, delivery_status FROM {$this->database->uv_get_gift_cards_table()}
			WHERE order_id = %d AND product_id = %d AND status = 'sold'
			ORDER BY id DESC 
			LIMIT %d",
			$order->get_id(),
			$product_id,
			$quantity_to_restore
		) );

		if ( ! $cards_to_restore ) {
			return;
		}

		$cards_never_delivered = array();
		$cards_delivered = array();

		// Separate cards by delivery status
		foreach ( $cards_to_restore as $card ) {
			if ( $card->delivery_status === 'never delivered' ) {
				$cards_never_delivered[] = $card;
			} else {
				$cards_delivered[] = $card;
			}
		}

		// Handle never delivered cards - restore to available
		if ( ! empty( $cards_never_delivered ) ) {
			$card_ids = wp_list_pluck( $cards_never_delivered, 'id' );
			$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
			
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->database->uv_get_gift_cards_table()}
				SET status = 'available', order_id = NULL 
				WHERE id IN ($card_ids_placeholder)",
				$card_ids
			) );

			// Add order notes for restored cards
			foreach ( $cards_never_delivered as $card ) {
				$order->add_order_note( sprintf( 
					// translators: %s is the card ID
					__( 'UniVoucher Card %s released during refund (status: available, delivery: never delivered)', 'univoucher-for-woocommerce' ),
					$card->card_id
				) );
			}
		}

		// Handle delivered cards - mark as inactive and reduce stock
		if ( ! empty( $cards_delivered ) ) {
			$card_ids = wp_list_pluck( $cards_delivered, 'id' );
			$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
			
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->database->uv_get_gift_cards_table()}
				SET status = 'inactive', delivery_status = 'returned after delivery', order_id = NULL 
				WHERE id IN ($card_ids_placeholder)",
				$card_ids
			) );

			// Reduce WooCommerce stock for inactivated cards
			if ( $product && $product->managing_stock() ) {
				$current_stock = $product->get_stock_quantity();
				$new_stock_level = $current_stock - count( $cards_delivered );
				$product->set_stock_quantity( $new_stock_level );
				$product->save();
			}

			// Add order notes for inactivated cards
			foreach ( $cards_delivered as $card ) {
				$order->add_order_note( sprintf( 
					// translators: %s is the card ID
					__( 'UniVoucher Card %s released during refund (status: inactive, delivery: returned after delivery) - reducing stock', 'univoucher-for-woocommerce' ),
					$card->card_id
				) );
			}
		}
	}

	/**
	 * Mark gift cards as delivered when payment is completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function uv_mark_gift_cards_delivered_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();

			// Check if this product has UniVoucher enabled
			if ( ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product_id ) ) {
				continue;
			}

			// Get cards to be marked as delivered (for order notes)
			global $wpdb;
			$table = esc_sql( $this->database->uv_get_gift_cards_table() );
			$cards_to_deliver = $wpdb->get_results( $wpdb->prepare(
				"SELECT card_id FROM $table
				WHERE order_id = %d AND product_id = %d AND status = 'sold' AND delivery_status = 'never delivered'",
				$order_id,
				$product_id
			) );

			// Mark gift cards as delivered for this product
			$result = $wpdb->update(
				$this->database->uv_get_gift_cards_table(),
				array( 'delivery_status' => 'delivered' ),
				array( 'order_id' => $order_id, 'product_id' => $product_id, 'status' => 'sold', 'delivery_status' => 'never delivered' ),
				array( '%s' ),
				array( '%d', '%d', '%s', '%s' )
			);

			// Add order notes for delivered cards
			if ( $result !== false && ! empty( $cards_to_deliver ) ) {
				foreach ( $cards_to_deliver as $card ) {
					$order->add_order_note( sprintf( 
						// translators: %s is the card ID
						__( 'UniVoucher Card %s marked as delivered', 'univoucher-for-woocommerce' ),
						$card->card_id
					) );
				}
			}
		}

		// WooCommerce handles stock quantity/status automatically
	}

	/**
	 * Restore (unassign) gift cards when an order is permanently deleted.
	 * Handles both delivered and never delivered cards with appropriate stock management.
	 *
	 * @param int $order_id Order ID.
	 */
	public function uv_restore_gift_cards_on_order_deletion( $order_id ) {
		global $wpdb;
		
		// Get all assigned gift cards for this order
		$table = esc_sql( $this->database->uv_get_gift_cards_table() );
		$assigned_cards = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, card_id, product_id, delivery_status FROM $table
			WHERE order_id = %d AND status = 'sold'",
			$order_id
		) );

		if ( empty( $assigned_cards ) ) {
			return;
		}

		// Group cards by product and delivery status
		$cards_by_product = array();
		foreach ( $assigned_cards as $card ) {
			if ( ! isset( $cards_by_product[ $card->product_id ] ) ) {
				$cards_by_product[ $card->product_id ] = array(
					'never_delivered' => array(),
					'delivered' => array()
				);
			}
			
			if ( $card->delivery_status === 'never delivered' ) {
				$cards_by_product[ $card->product_id ]['never_delivered'][] = $card;
			} else {
				$cards_by_product[ $card->product_id ]['delivered'][] = $card;
			}
		}

		// Process each product's cards
		foreach ( $cards_by_product as $product_id => $cards ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Handle never delivered cards - restore to available and increase stock
			if ( ! empty( $cards['never_delivered'] ) ) {
				$card_ids = wp_list_pluck( $cards['never_delivered'], 'id' );
				$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
				
				$wpdb->query( $wpdb->prepare(
					"UPDATE $table
					SET status = 'available', order_id = NULL 
					WHERE id IN ($card_ids_placeholder)",
					$card_ids
				) );

				// Increase WooCommerce stock for restored cards
				if ( $product->managing_stock() ) {
					$current_stock = $product->get_stock_quantity();
					$new_stock_level = $current_stock + count( $cards['never_delivered'] );
					$product->set_stock_quantity( $new_stock_level );
					$product->save();
				}
			}

			// Handle delivered cards - mark as inactive (no stock increase)
			if ( ! empty( $cards['delivered'] ) ) {
				$card_ids = wp_list_pluck( $cards['delivered'], 'id' );
				$card_ids_placeholder = implode( ',', array_fill( 0, count( $card_ids ), '%d' ) );
				
				$wpdb->query( $wpdb->prepare(
					"UPDATE $table
					SET status = 'inactive', delivery_status = 'returned after delivery', order_id = NULL 
					WHERE id IN ($card_ids_placeholder)",
					$card_ids
				) );
			}
		}
	}

	/**
	 * Synchronize product stock data for a UniVoucher product.
	 * This method ensures stock quantity and status in the product meta match the actual stock
	 * in the Gift Card table, including calculation of missing cards.
	 *
	 * @param int $product_id Product ID.
	 */
	public function uv_sync_product_stock( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! UniVoucher_WC_Product_Manager::instance()->is_univoucher_enabled( $product ) ) {
			return;
		}

		global $wpdb;
		$table = esc_sql( $this->database->uv_get_gift_cards_table() );
		$product_id = absint( $product_id );

		// Get the actual available stock in the Gift Card table
		$available_quantity = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE product_id = %d AND status = 'available'",
			$product_id
		) );

		// Calculate missing cards for active orders (always positive)
		$missing_cards = $this->calculate_missing_cards_for_product( $product_id );

		// Calculate total stock: available - missing (this can be negative)
		$total_stock_quantity = $available_quantity - $missing_cards;

		// Use WooCommerce's built-in stock management (handles stock status automatically)
		$current_stock = $product->get_stock_quantity();
		// Handle null values and ensure proper comparison
		if ( $current_stock === null || $current_stock !== $total_stock_quantity ) {
			$product->set_stock_quantity( $total_stock_quantity );
			$product->save();
		}
	}

	/**
	 * Calculate missing cards for a specific product.
	 * This includes cards needed for active orders that haven't been assigned yet.
	 * Optimized for high-volume scenarios with pure SQL queries.
	 *
	 * @param int $product_id Product ID.
	 * @return int Number of missing cards (always >= 0).
	 */
	public function calculate_missing_cards_for_product( $product_id ) {
		global $wpdb;
		$product_id = absint( $product_id );
		$valid_statuses = array( 'wc-processing', 'wc-on-hold', 'wc-completed' );
		$status_placeholders = implode( ',', array_fill( 0, count( $valid_statuses ), '%s' ) );

		// Calculate total ordered cards with a single optimized query
		$ordered_cards = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(
				CAST(order_item_qty.meta_value AS SIGNED) - 
				COALESCE(CAST(refunded_qty.meta_value AS SIGNED), 0)
			), 0)
			FROM {$wpdb->prefix}woocommerce_order_items AS order_items
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_meta 
				ON order_items.order_item_id = product_meta.order_item_id
				AND product_meta.meta_key = '_product_id' 
				AND product_meta.meta_value = %s
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_qty
				ON order_items.order_item_id = order_item_qty.order_item_id
				AND order_item_qty.meta_key = '_qty'
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS refunded_qty
				ON order_items.order_item_id = refunded_qty.order_item_id
				AND refunded_qty.meta_key = '_refunded_qty'
			INNER JOIN {$wpdb->posts} AS orders
				ON order_items.order_id = orders.ID
				AND orders.post_status IN ($status_placeholders)",
			array_merge( array( $product_id ), $valid_statuses )
		) );

		// Calculate total assigned cards with a single optimized query
		$assigned_cards = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$this->database->uv_get_gift_cards_table()} AS cards
			INNER JOIN {$wpdb->posts} AS orders
				ON cards.order_id = orders.ID
				AND orders.post_status IN ($status_placeholders)
			WHERE cards.product_id = %d 
				AND cards.status = 'sold' 
				AND cards.order_id IS NOT NULL",
			array_merge( $valid_statuses, array( $product_id ) )
		) );

		// Calculate missing cards (always positive or zero)
		return max( 0, $ordered_cards - $assigned_cards );
	}

}