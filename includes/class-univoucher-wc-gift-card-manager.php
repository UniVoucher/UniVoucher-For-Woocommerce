<?php
/**
 * UniVoucher For WooCommerce Gift Card Management
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Gift_Card_Manager class.
 */
class UniVoucher_WC_Gift_Card_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Gift_Card_Manager
	 */
	protected static $_instance = null;

	/**
	 * Database instance.
	 *
	 * @var UniVoucher_WC_Database
	 */
	private $database;

	/**
	 * Main UniVoucher_WC_Gift_Card_Manager Instance.
	 *
	 * @return UniVoucher_WC_Gift_Card_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Gift_Card_Manager Constructor.
	 */
	public function __construct() {
		$this->database = UniVoucher_WC_Database::instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_delete_gift_card', array( $this, 'uv_ajax_delete_gift_card' ) );

	}

	/**
	 * Add gift card to the database.
	 *
	 * @param array $data Gift card data.
	 * @return int|WP_Error Gift card ID on success, WP_Error on failure.
	 */
	public function uv_add_gift_card( $data ) {
		global $wpdb;

		// Validate data.
		$validation = $this->database->uv_validate_gift_card_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Sanitize data.
		$data = $this->database->uv_sanitize_gift_card_data( $data );

		// Check if card_id already exists.
		$existing = $this->uv_get_gift_card_by_card_id( $data['card_id'] );
		if ( $existing ) {
			return new WP_Error( 'duplicate_card_id', esc_html__( 'A gift card with this card ID already exists.', 'univoucher-for-woocommerce' ) );
		}

		// Set default values.
		if ( empty( $data['status'] ) ) {
			$data['status'] = 'available';
		}
		if ( empty( $data['delivery_status'] ) ) {
			$data['delivery_status'] = 'never delivered';
		}
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// Encrypt card secret before storing
		$encrypted_secret = UniVoucher_For_WooCommerce::uv_encrypt_data( $data['card_secret'] );
		if ( is_wp_error( $encrypted_secret ) ) {
			return $encrypted_secret;
		}

		// Ensure data is in correct order for database insert.
		$ordered_data = array(
			'product_id'      => $data['product_id'],
			'order_id'        => isset( $data['order_id'] ) ? $data['order_id'] : null,
			'status'          => $data['status'],
			'delivery_status' => $data['delivery_status'],
			'card_id'         => $data['card_id'],
			'card_secret'     => $encrypted_secret,
			'chain_id'        => $data['chain_id'],
			'token_address'   => $data['token_address'],
			'token_symbol'    => $data['token_symbol'],
			'token_type'      => $data['token_type'],
			'token_decimals'  => $data['token_decimals'],
			'amount'          => $data['amount'],
			'created_at'      => $data['created_at'],
		);

		// Insert into database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->database->uv_get_gift_cards_table(),
			$ordered_data,
			array(
				'%d', // product_id
				'%d', // order_id
				'%s', // status
				'%s', // delivery_status
				'%s', // card_id
				'%s', // card_secret
				'%d', // chain_id
				'%s', // token_address
				'%s', // token_symbol
				'%s', // token_type
				'%d', // token_decimals
				'%s', // amount
				'%s', // created_at
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', sprintf( 
				// translators: %s is the database error message
				esc_html__( 'Failed to create gift card. DB Error: %s', 'univoucher-for-woocommerce' ),
				$wpdb->last_error ?: 'Unknown error'
			) );
		}

		// Increase product stock after adding card
		$product = wc_get_product( $data['product_id'] );
		if ( $product && $product->managing_stock() ) {
			$current_stock = $product->get_stock_quantity();
			$product->set_stock_quantity( $current_stock + 1 );
			$product->save();
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete a gift card.
	 *
	 * @param int $id Gift card ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function uv_delete_gift_card( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', esc_html__( 'Invalid gift card ID.', 'univoucher-for-woocommerce' ) );
		}

		// Check if gift card exists.
		$existing = $this->uv_get_gift_card( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', esc_html__( 'Gift card not found.', 'univoucher-for-woocommerce' ) );
		}

		// Don't allow deletion of cards assigned to orders.
		if ( ! empty( $existing->order_id ) ) {
			return new WP_Error( 'cannot_delete', sprintf( 
				// translators: %1$s is the card ID, %2$d is the order ID
				esc_html__( 'Card %1$s is already assigned to order %2$d and cannot be deleted.', 'univoucher-for-woocommerce' ),
				$existing->card_id,
				$existing->order_id
			) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->database->uv_get_gift_cards_table(),
			array( 'id' => $id ),
			array( '%d' )
		);


		if ( false === $result ) {
			return new WP_Error( 'db_error', esc_html__( 'Failed to delete gift card.', 'univoucher-for-woocommerce' ) );
		}

		// Decrease product stock only if deleting an available card (inactive cards don't count towards stock)
		if ( $existing->status === 'available' ) {
			$product = wc_get_product( $existing->product_id );
			if ( $product && $product->managing_stock() ) {
				$current_stock = $product->get_stock_quantity();
				$product->set_stock_quantity( $current_stock - 1 );
				$product->save();
			}
		}

		return true;
	}

	/**
	 * Get a gift card by ID.
	 *
	 * @param int $id Gift card ID.
	 * @return object|null Gift card object or null if not found.
	 */
	public function uv_get_gift_card( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$table = $this->database->uv_get_gift_cards_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$card = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}univoucher_gift_cards WHERE id = %d",
			$id
		) );

		// Decrypt card secret if card exists
		if ( $card && ! empty( $card->card_secret ) ) {
			$decrypted = UniVoucher_For_WooCommerce::uv_decrypt_data( $card->card_secret );
			if ( is_wp_error( $decrypted ) ) {
				$card->card_secret = '[DECRYPTION ERROR: ' . $decrypted->get_error_message() . ']';
			} else {
				$card->card_secret = $decrypted;
			}
		}

		return $card;
	}

	/**
	 * Get a gift card by card ID.
	 *
	 * @param string $card_id Card ID.
	 * @return object|null Gift card object or null if not found.
	 */
	public function uv_get_gift_card_by_card_id( $card_id ) {
		global $wpdb;

		if ( empty( $card_id ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$card = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}univoucher_gift_cards WHERE card_id = %s",
			sanitize_text_field( $card_id )
		) );

		// Decrypt card secret if card exists
		if ( $card && ! empty( $card->card_secret ) ) {
			$decrypted = UniVoucher_For_WooCommerce::uv_decrypt_data( $card->card_secret );
			if ( is_wp_error( $decrypted ) ) {
				$card->card_secret = '[DECRYPTION ERROR: ' . $decrypted->get_error_message() . ']';
			} else {
				$card->card_secret = $decrypted;
			}
		}

		return $card;
	}

	/**
	 * Get gift cards with pagination and filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total' keys.
	 */
	public function uv_get_gift_cards( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'            => 1,
			'per_page'        => 20,
			'status'          => '',
			'delivery_status' => '',
			'chain_id'        => '',
			'product_id'      => '',
			'token_type'      => '',
			'search'          => '',
			'orderby'         => 'created_at',
			'order'           => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause and values.
		$where_parts = array();
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where_parts[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['delivery_status'] ) ) {
			$where_parts[] = 'delivery_status = %s';
			$where_values[] = $args['delivery_status'];
		}

		if ( ! empty( $args['chain_id'] ) ) {
			$where_parts[] = 'chain_id = %d';
			$where_values[] = absint( $args['chain_id'] );
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where_parts[] = 'product_id = %d';
			$where_values[] = absint( $args['product_id'] );
		}

		if ( ! empty( $args['token_type'] ) ) {
			$where_parts[] = 'token_type = %s';
			$where_values[] = $args['token_type'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_parts[] = '(card_id LIKE %s OR token_symbol LIKE %s OR token_address LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		// Build ORDER BY clause.
		$allowed_orderby = array( 'id', 'product_id', 'card_id', 'chain_id', 'token_symbol', 'amount', 'status', 'delivery_status', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Get total count.
		if ( ! empty( $where_values ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_parts );
			$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}univoucher_gift_cards {$where_clause}";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}univoucher_gift_cards" );
		}

		// Get items.
		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$limit = absint( $args['per_page'] );

		if ( ! empty( $where_values ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_parts );
			$main_sql = "SELECT * FROM {$wpdb->prefix}univoucher_gift_cards {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
			$query_values = array_merge( $where_values, array( $limit, $offset ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $main_sql, $query_values ) );
		} else {
			$main_sql = "SELECT * FROM {$wpdb->prefix}univoucher_gift_cards ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $main_sql, $limit, $offset ) );
		}

		// Decrypt card secrets for all items
		if ( $items ) {
			foreach ( $items as $item ) {
				if ( ! empty( $item->card_secret ) ) {
					$decrypted = UniVoucher_For_WooCommerce::uv_decrypt_data( $item->card_secret );
					if ( is_wp_error( $decrypted ) ) {
						$item->card_secret = '[DECRYPTION ERROR: ' . $decrypted->get_error_message() . ']';
					} else {
						$item->card_secret = $decrypted;
					}
				}
			}
		}

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Get inventory stats.
	 *
	 * @return array
	 */
	public function get_inventory_stats() {
		global $wpdb;

		$stats = array(
			'total'     => 0,
			'available' => 0,
			'sold'      => 0,
			'inactive'  => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}univoucher_gift_cards GROUP BY status" );

		if ( $results ) {
			foreach ( $results as $result ) {
				$stats[ $result->status ] = (int) $result->count;
				$stats['total'] += (int) $result->count;
			}
		}


		return $stats;
	}

	/**
	 * Get gift cards for a specific order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of gift card objects.
	 */
	public function uv_get_gift_cards_for_order( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cards = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}univoucher_gift_cards WHERE order_id = %d ORDER BY created_at ASC",
			$order_id
		) );


		// Decrypt card secrets for all cards
		if ( $cards ) {
			foreach ( $cards as $card ) {
				if ( ! empty( $card->card_secret ) ) {
					$decrypted = UniVoucher_For_WooCommerce::uv_decrypt_data( $card->card_secret );
					if ( is_wp_error( $decrypted ) ) {
						$card->card_secret = '[DECRYPTION ERROR: ' . $decrypted->get_error_message() . ']';
					} else {
						$card->card_secret = $decrypted;
					}
				}
			}
		}

		return $cards ?: array();
	}




	/**
	 * AJAX handler for deleting gift card.
	 */
	public function uv_ajax_delete_gift_card() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_inventory_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		if ( ! isset( $_POST['id'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'univoucher-for-woocommerce' ) );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$result = $this->uv_delete_gift_card( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Gift card deleted successfully.', 'univoucher-for-woocommerce' ) ) );
	}

	/**
	 * AJAX handler for bulk actions.
	 */
	public function uv_ajax_bulk_action() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_inventory_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		if ( ! isset( $_POST['action_type'] ) || ! isset( $_POST['ids'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'univoucher-for-woocommerce' ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No items selected.', 'univoucher-for-woocommerce' ) ) );
		}

		$success_count = 0;
		$errors = array();

		switch ( $action ) {
			case 'delete':
				foreach ( $ids as $id ) {
					$result = $this->uv_delete_gift_card( $id );
					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf( 'ID %d: %s', $id, $result->get_error_message() );
					} else {
						$success_count++;
					}
				}
				break;

			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid action.', 'univoucher-for-woocommerce' ) ) );
		}

		// translators: %d is the number of successfully processed items
		$message = sprintf( esc_html__( '%d items processed successfully.', 'univoucher-for-woocommerce' ), $success_count );
		if ( ! empty( $errors ) ) {
			// translators: %d is the number of errors
			$message .= ' ' . sprintf( esc_html__( '%d errors occurred.', 'univoucher-for-woocommerce' ), count( $errors ) );
		}

		wp_send_json_success( array(
			'message' => $message,
			'errors'  => $errors,
		) );
	}


}