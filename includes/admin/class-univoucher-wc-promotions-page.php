<?php
/**
 * UniVoucher For WooCommerce Promotions Page
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Promotions_Page class.
 */
class UniVoucher_WC_Promotions_Page {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Promotions_Page
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Promotions_Page Instance.
	 *
	 * @return UniVoucher_WC_Promotions_Page - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Promotions_Page Constructor.
	 */
	public function __construct() {
		// Call handle_actions immediately since we're constructed during admin_init
		$this->handle_actions();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_univoucher_toggle_promotion', array( $this, 'ajax_toggle_promotion' ) );
		add_action( 'wp_ajax_univoucher_promotions_get_token_info', array( $this, 'ajax_get_token_info' ) );
		add_action( 'wp_ajax_univoucher_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_univoucher_search_categories', array( $this, 'ajax_search_categories' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'univoucher_page_univoucher-promotions' !== $hook ) {
			return;
		}

		// Enqueue Select2 for product search.
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );

		wp_enqueue_style(
			'univoucher-wc-promotions',
			plugins_url( 'admin/css/promotions.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'select2' ),
			UNIVOUCHER_WC_VERSION
		);

		wp_enqueue_script(
			'univoucher-wc-promotions',
			plugins_url( 'admin/js/promotions.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery', 'jquery-ui-sortable', 'select2' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		wp_localize_script(
			'univoucher-wc-promotions',
			'univoucher_promotions_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'univoucher_promotions_nonce' ),
				'networks' => UniVoucher_WC_Product_Fields::get_supported_networks(),
			)
		);
	}

	/**
	 * Handle promotion actions.
	 */
	public function handle_actions() {
		// Check if we're on the promotions page (either GET or POST).
		$is_promotions_page = ( isset( $_GET['page'] ) && 'univoucher-promotions' === $_GET['page'] ) ||
		                      ( isset( $_POST['univoucher_save_promotion'] ) );

		if ( ! $is_promotions_page ) {
			return;
		}

		// Handle bulk delete action.
		if ( isset( $_POST['action'] ) && 'bulk_delete' === $_POST['action'] && isset( $_POST['promotion_ids'] ) && is_array( $_POST['promotion_ids'] ) ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk_delete_promotions' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$promotion_ids = array_map( 'absint', $_POST['promotion_ids'] );
			foreach ( $promotion_ids as $promotion_id ) {
				$this->delete_promotion( $promotion_id );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&deleted=' . count( $promotion_ids ) ) );
			exit;
		}

		// Handle single delete action.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['promotion_id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_promotion' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$this->delete_promotion( absint( $_GET['promotion_id'] ) );
			wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&deleted=1' ) );
			exit;
		}

		// Handle duplicate action.
		if ( isset( $_GET['action'] ) && 'duplicate' === $_GET['action'] && isset( $_GET['promotion_id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'duplicate_promotion' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$new_id = $this->duplicate_promotion( absint( $_GET['promotion_id'] ) );
			if ( $new_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&action=edit&promotion_id=' . $new_id . '&duplicated=1' ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&error=duplicate' ) );
			}
			exit;
		}

		// Handle save action.
		if ( isset( $_POST['univoucher_save_promotion'] ) ) {
			if ( ! isset( $_POST['univoucher_promotion_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['univoucher_promotion_nonce'] ) ), 'save_promotion' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
			}

			$result = $this->save_promotion();

			if ( is_array( $result ) && isset( $result['error'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&error=' . urlencode( $result['error'] ) ) );
			} elseif ( $result ) {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&action=edit&promotion_id=' . $result . '&saved=1' ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=univoucher-promotions&error=save' ) );
			}
			exit;
		}
	}

	/**
	 * AJAX handler to toggle promotion status.
	 */
	public function ajax_toggle_promotion() {
		check_ajax_referer( 'univoucher_promotions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'univoucher-for-woocommerce' ) ) );
		}

		$promotion_id = isset( $_POST['promotion_id'] ) ? absint( $_POST['promotion_id'] ) : 0;
		$is_active = isset( $_POST['is_active'] ) ? absint( $_POST['is_active'] ) : 0;

		if ( ! $promotion_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid promotion ID.', 'univoucher-for-woocommerce' ) ) );
		}

		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		$result = $wpdb->update(
			$table,
			array( 'is_active' => $is_active ),
			array( 'id' => $promotion_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Promotion status updated.', 'univoucher-for-woocommerce' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to update promotion status.', 'univoucher-for-woocommerce' ) ) );
		}
	}

	/**
	 * Delete promotion.
	 *
	 * @param int $promotion_id Promotion ID.
	 */
	private function delete_promotion( $promotion_id ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$promotions_table = $database->uv_get_promotions_table();
		$cards_table = $database->uv_get_promotional_cards_table();
		$tracking_table = $database->uv_get_promotion_user_tracking_table();

		// Delete associated promotional cards.
		$wpdb->delete( $cards_table, array( 'promotion_id' => $promotion_id ), array( '%d' ) );

		// Delete user tracking records.
		$wpdb->delete( $tracking_table, array( 'promotion_id' => $promotion_id ), array( '%d' ) );

		// Delete the promotion itself.
		$wpdb->delete( $promotions_table, array( 'id' => $promotion_id ), array( '%d' ) );
	}

	/**
	 * Duplicate promotion.
	 *
	 * @param int $promotion_id Promotion ID.
	 * @return int|false New promotion ID or false on failure.
	 */
	private function duplicate_promotion( $promotion_id ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		// Get original promotion.
		$promotion = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $promotion_id ), ARRAY_A );

		if ( ! $promotion ) {
			return false;
		}

		// Remove ID, timestamps, and update title.
		unset( $promotion['id'] );
		unset( $promotion['created_at'] );
		unset( $promotion['updated_at'] );
		$promotion['title'] = $promotion['title'] . ' (Copy)';
		$promotion['is_active'] = 0;
		$promotion['total_issued'] = 0;

		// Define format specifiers for wpdb->insert().
		$format = array(
			'%s', // title
			'%s', // description
			'%d', // chain_id
			'%s', // token_type
			'%s', // token_address
			'%s', // token_symbol
			'%d', // token_decimals
			'%s', // card_amount
			'%s', // rules
			'%d', // max_per_user
			'%d', // max_total
			'%d', // total_issued
			'%d', // send_separate_email
			'%s', // email_subject
			'%s', // email_template
			'%d', // show_account_notice
			'%s', // account_notice_message
			'%d', // show_order_notice
			'%s', // order_notice_message
			'%d', // show_shortcode_notice
			'%s', // shortcode_notice_message
			'%d', // is_active
		);

		// Insert new promotion.
		$result = $wpdb->insert( $table, $promotion, $format );

		return ( false !== $result ) ? $wpdb->insert_id : false;
	}

	/**
	 * Save promotion.
	 *
	 * @return int|array Promotion ID on success, or array with error on failure.
	 */
	private function save_promotion() {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		// Get promotion ID (0 for new promotion).
		$promotion_id = isset( $_POST['promotion_id'] ) ? absint( $_POST['promotion_id'] ) : 0;

		// Prepare rules data.
		$rules = array();
		if ( isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ) {
			foreach ( $_POST['rules'] as $rule ) {
				$sanitized_rule = array(
					'type'      => isset( $rule['type'] ) ? sanitize_text_field( wp_unslash( $rule['type'] ) ) : 'order',
					'condition' => isset( $rule['condition'] ) ? sanitize_text_field( wp_unslash( $rule['condition'] ) ) : 'includes_product',
					'value'     => isset( $rule['value'] ) ? ( is_array( $rule['value'] ) ? array_map( 'sanitize_text_field', wp_unslash( $rule['value'] ) ) : sanitize_text_field( wp_unslash( $rule['value'] ) ) ) : '',
					'operator'  => isset( $rule['operator'] ) ? sanitize_text_field( wp_unslash( $rule['operator'] ) ) : 'more_than',
				);
				$rules[] = $sanitized_rule;
			}
		}

		// Prepare data.
		$data = array(
			'title'                  => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'            => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'chain_id'               => isset( $_POST['chain_id'] ) ? absint( $_POST['chain_id'] ) : 1,
			'token_type'             => isset( $_POST['token_type'] ) ? sanitize_text_field( wp_unslash( $_POST['token_type'] ) ) : 'native',
			'token_address'          => isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '',
			'token_symbol'           => isset( $_POST['token_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['token_symbol'] ) ) : '',
			'token_decimals'         => isset( $_POST['token_decimals'] ) ? absint( $_POST['token_decimals'] ) : 18,
			'card_amount'            => isset( $_POST['card_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['card_amount'] ) ) : '',
			'rules'                  => wp_json_encode( $rules ),
			'max_per_user'           => isset( $_POST['max_per_user'] ) ? absint( $_POST['max_per_user'] ) : 0,
			'max_total'              => isset( $_POST['max_total'] ) ? absint( $_POST['max_total'] ) : 0,
			'send_separate_email'    => isset( $_POST['send_separate_email'] ) ? 1 : 0,
			'email_subject'          => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_template'         => isset( $_POST['email_template'] ) ? wp_kses_post( wp_unslash( $_POST['email_template'] ) ) : '',
			'show_account_notice'      => isset( $_POST['show_account_notice'] ) ? 1 : 0,
			'account_notice_message'   => isset( $_POST['account_notice_message'] ) ? wp_kses_post( wp_unslash( $_POST['account_notice_message'] ) ) : '',
			'show_order_notice'        => isset( $_POST['show_order_notice'] ) ? 1 : 0,
			'order_notice_message'     => isset( $_POST['order_notice_message'] ) ? wp_kses_post( wp_unslash( $_POST['order_notice_message'] ) ) : '',
			'show_shortcode_notice'    => isset( $_POST['show_shortcode_notice'] ) ? 1 : 0,
			'shortcode_notice_message' => isset( $_POST['shortcode_notice_message'] ) ? wp_kses_post( wp_unslash( $_POST['shortcode_notice_message'] ) ) : '',
		);

		// Validate required fields.
		if ( empty( $data['title'] ) ) {
			return array( 'error' => 'missing_title' );
		}

		if ( empty( $data['token_symbol'] ) ) {
			return array( 'error' => 'missing_token' );
		}

		if ( empty( $data['card_amount'] ) ) {
			return array( 'error' => 'missing_amount' );
		}

		// Validate that we have at least one rule.
		if ( empty( $rules ) ) {
			return array( 'error' => 'missing_rules' );
		}

		// Define format specifiers for each field.
		$format = array(
			'%s', // title
			'%s', // description
			'%d', // chain_id
			'%s', // token_type
			'%s', // token_address
			'%s', // token_symbol
			'%d', // token_decimals
			'%s', // card_amount
			'%s', // rules
			'%d', // max_per_user
			'%d', // max_total
			'%d', // send_separate_email
			'%s', // email_subject
			'%s', // email_template
			'%d', // show_account_notice
			'%s', // account_notice_message
			'%d', // show_order_notice
			'%s', // order_notice_message
			'%d', // show_shortcode_notice
			'%s', // shortcode_notice_message
		);

		if ( $promotion_id ) {
			// Update existing promotion.
			$result = $wpdb->update( $table, $data, array( 'id' => $promotion_id ), $format, array( '%d' ) );

			if ( false === $result ) {
				error_log( 'UniVoucher Promotion Update Error: ' . $wpdb->last_error );
				return array( 'error' => 'db_error' );
			}

			return $promotion_id;
		} else {
			// Insert new promotion.
			$result = $wpdb->insert( $table, $data, $format );

			if ( false === $result ) {
				error_log( 'UniVoucher Promotion Insert Error: ' . $wpdb->last_error );
				return array( 'error' => 'db_error' );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Format token amount with proper decimals, removing trailing zeros.
	 *
	 * @param string $amount   Token amount.
	 * @param int    $decimals Number of decimals (default 6).
	 * @return string Formatted amount.
	 */
	private function format_token_amount( $amount, $decimals = 6 ) {
		// Format to specified decimals
		$formatted = number_format( (float) $amount, $decimals, '.', '' );
		// Remove trailing zeros
		$formatted = rtrim( $formatted, '0' );
		// Remove trailing decimal point if no decimals remain
		$formatted = rtrim( $formatted, '.' );
		return $formatted;
	}

	/**
	 * Format rules for display in the list table.
	 *
	 * @param array $rules Array of rules.
	 * @return string Formatted rules HTML.
	 */
	private function format_rules_display( $rules ) {
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return '<em>' . esc_html__( 'No rules', 'univoucher-for-woocommerce' ) . '</em>';
		}

		$output = array();

		foreach ( $rules as $index => $rule ) {
			$rule_text = '';

			// Build rule description based on type and condition.
			if ( 'order' === $rule['type'] ) {
				if ( 'includes_product' === $rule['condition'] ) {
					$product_id = isset( $rule['value'] ) ? absint( $rule['value'] ) : 0;
					if ( $product_id ) {
						$product = wc_get_product( $product_id );
						$product_name = $product ? $product->get_name() : esc_html__( 'Unknown Product', 'univoucher-for-woocommerce' );
						$rule_text = sprintf( esc_html__( 'Order includes: %s', 'univoucher-for-woocommerce' ), $product_name );
					}
				} elseif ( 'includes_category' === $rule['condition'] ) {
					$category_id = isset( $rule['value'] ) ? absint( $rule['value'] ) : 0;
					if ( $category_id ) {
						$category = get_term( $category_id, 'product_cat' );
						$category_name = $category && ! is_wp_error( $category ) ? $category->name : esc_html__( 'Unknown Category', 'univoucher-for-woocommerce' );
						$rule_text = sprintf( esc_html__( 'Order includes category: %s', 'univoucher-for-woocommerce' ), $category_name );
					}
				} elseif ( 'total_value' === $rule['condition'] ) {
					$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'more_than';
					$value = isset( $rule['value'] ) ? $rule['value'] : '';
					$currency = get_woocommerce_currency_symbol();

					if ( 'between' === $operator && is_array( $value ) ) {
						$min = isset( $value['min'] ) ? $value['min'] : '';
						$max = isset( $value['max'] ) ? $value['max'] : '';
						$rule_text = sprintf( esc_html__( 'Order total between %s%s and %s%s', 'univoucher-for-woocommerce' ), $currency, $min, $currency, $max );
					} elseif ( 'more_than' === $operator ) {
						$rule_text = sprintf( esc_html__( 'Order total more than %s%s', 'univoucher-for-woocommerce' ), $currency, $value );
					} elseif ( 'less_than' === $operator ) {
						$rule_text = sprintf( esc_html__( 'Order total less than %s%s', 'univoucher-for-woocommerce' ), $currency, $value );
					}
				}
			} elseif ( 'user' === $rule['type'] ) {
				if ( 'user_id' === $rule['condition'] ) {
					$user_ids = isset( $rule['value'] ) ? $rule['value'] : '';
					if ( $user_ids ) {
						$rule_text = sprintf( esc_html__( 'User ID is: %s', 'univoucher-for-woocommerce' ), $user_ids );
					}
				} elseif ( 'completed_orders_count' === $rule['condition'] ) {
					$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'more_than';
					$count = isset( $rule['value'] ) ? $rule['value'] : '';
					if ( 'more_than' === $operator ) {
						$rule_text = sprintf( esc_html__( 'User has more than %s completed orders', 'univoucher-for-woocommerce' ), $count );
					} elseif ( 'less_than' === $operator ) {
						$rule_text = sprintf( esc_html__( 'User has less than %s completed orders', 'univoucher-for-woocommerce' ), $count );
					}
				} elseif ( 'user_role' === $rule['condition'] ) {
					$roles = isset( $rule['value'] ) ? ( is_array( $rule['value'] ) ? $rule['value'] : explode( ',', $rule['value'] ) ) : array();
					if ( ! empty( $roles ) ) {
						global $wp_roles;
						if ( ! isset( $wp_roles ) ) {
							$wp_roles = new WP_Roles();
						}
						$role_names = array();
						foreach ( $roles as $role_key ) {
							if ( isset( $wp_roles->role_names[ $role_key ] ) ) {
								$role_names[] = translate_user_role( $wp_roles->role_names[ $role_key ] );
							}
						}
						if ( ! empty( $role_names ) ) {
							$rule_text = sprintf( esc_html__( 'User role is: %s', 'univoucher-for-woocommerce' ), implode( ', ', $role_names ) );
						}
					}
				} elseif ( 'registration_date' === $rule['condition'] ) {
					$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'after';
					$date = isset( $rule['value'] ) ? $rule['value'] : '';
					if ( 'before' === $operator ) {
						$rule_text = sprintf( esc_html__( 'User registered before %s', 'univoucher-for-woocommerce' ), $date );
					} else {
						$rule_text = sprintf( esc_html__( 'User registered after %s', 'univoucher-for-woocommerce' ), $date );
					}
				} elseif ( 'never_received_promotion' === $rule['condition'] ) {
					$rule_text = esc_html__( 'User never received any promotions before', 'univoucher-for-woocommerce' );
				}
			}

			if ( ! empty( $rule_text ) ) {
				$output[] = $rule_text;
			}
		}

		if ( empty( $output ) ) {
			return '<em>' . esc_html__( 'No rules', 'univoucher-for-woocommerce' ) . '</em>';
		}

		return implode( '<br>', $output );
	}

	/**
	 * Get promotions with pagination and filters.
	 *
	 * @param int   $per_page Number of items per page.
	 * @param int   $page_number Current page number.
	 * @param array $filters Filter parameters.
	 * @return array
	 */
	private function get_promotions( $per_page = 20, $page_number = 1, $filters = array() ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		$where = array( '1=1' );
		$params = array();

		// Apply filters.
		if ( ! empty( $filters['status'] ) ) {
			if ( 'active' === $filters['status'] ) {
				$where[] = 'is_active = 1';
			} elseif ( 'inactive' === $filters['status'] ) {
				$where[] = 'is_active = 0';
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(title LIKE %s OR description LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[] = $search_term;
			$params[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );
		$offset = ( $page_number - 1 ) * $per_page;

		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total count of promotions with filters.
	 *
	 * @param array $filters Filter parameters.
	 * @return int
	 */
	private function get_promotions_count( $filters = array() ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		$where = array( '1=1' );
		$params = array();

		// Apply filters.
		if ( ! empty( $filters['status'] ) ) {
			if ( 'active' === $filters['status'] ) {
				$where[] = 'is_active = 1';
			} elseif ( 'inactive' === $filters['status'] ) {
				$where[] = 'is_active = 0';
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(title LIKE %s OR description LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[] = $search_term;
			$params[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $table WHERE $where_clause";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get promotion by ID.
	 *
	 * @param int $promotion_id Promotion ID.
	 * @return object|null
	 */
	private function get_promotion( $promotion_id ) {
		global $wpdb;
		$database = UniVoucher_WC_Database::instance();
		$table = $database->uv_get_promotions_table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $promotion_id ) );
	}

	/**
	 * Render promotions page.
	 */
	public function render_page() {
		// Check if we're in edit mode.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action || 'add' === $action ) {
			$this->render_edit_page();
		} else {
			$this->render_list_page();
		}
	}

	/**
	 * Render promotions list page.
	 */
	private function render_list_page() {
		// Get filters from URL.
		$filters = array(
			'status' => isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);

		// Get pagination.
		$per_page = 20;
		$page_number = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		// Get promotions and total count.
		$promotions = $this->get_promotions( $per_page, $page_number, $filters );
		$total_items = $this->get_promotions_count( $filters );
		$total_pages = ceil( $total_items / $per_page );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Promotions', 'univoucher-for-woocommerce' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Promotion', 'univoucher-for-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotional-cards' ) ); ?>" class="page-title-action">
				<span class="dashicons dashicons-tickets-alt" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'View All Issued Promotional Cards', 'univoucher-for-woocommerce' ); ?>
			</a>
			<hr class="wp-heading-inline">

			<!-- Page Introduction -->
			<div class="univoucher-page-intro" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
				<h2 style="margin-top: 0;"><?php esc_html_e( '🎁 Automated Promotional Gift Cards', 'univoucher-for-woocommerce' ); ?></h2>
				<p style="font-size: 15px; line-height: 1.6;">
					<?php esc_html_e( 'Create rule-based promotions that automatically generate and send UniVoucher gift cards to customers when their orders meet specific conditions. Perfect for customer rewards, loyalty programs, first-time buyer incentives, and marketing campaigns.', 'univoucher-for-woocommerce' ); ?>
				</p>

				<div class="univoucher-important-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
					<strong><?php esc_html_e( '⚠️ Important: Internal Wallet Required', 'univoucher-for-woocommerce' ); ?></strong>
					<p style="margin: 10px 0 0 0;">
						<?php
						printf(
							/* translators: %s: link to settings page */
							esc_html__( 'Promotional gift cards are generated on-demand using your internal wallet and are separate from your UniVoucher inventory. Each promotion creates real blockchain transactions funded directly by your internal wallet. Before activating promotions, ensure your internal wallet is properly configured in %s with sufficient balance to cover promotional card generation.', 'univoucher-for-woocommerce' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=univoucher-settings' ) ) . '">' . esc_html__( 'Settings', 'univoucher-for-woocommerce' ) . '</a>'
						);
						?>
					</p>
				</div>


			</div>

			<?php
			// Display notices.
			if ( isset( $_GET['deleted'] ) ) {
				$count = absint( $_GET['deleted'] );
				echo '<div class="notice notice-success is-dismissible"><p>' .
					sprintf(
						esc_html( _n( '%d promotion deleted successfully.', '%d promotions deleted successfully.', $count, 'univoucher-for-woocommerce' ) ),
						$count
					) .
					'</p></div>';
			}
			if ( isset( $_GET['saved'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Promotion saved successfully.', 'univoucher-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_GET['duplicated'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Promotion duplicated successfully.', 'univoucher-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_GET['error'] ) ) {
				$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
				$error_messages = array(
					'missing_title'  => esc_html__( 'Error: Title is required.', 'univoucher-for-woocommerce' ),
					'missing_token'  => esc_html__( 'Error: Token symbol is required.', 'univoucher-for-woocommerce' ),
					'missing_amount' => esc_html__( 'Error: Card amount is required.', 'univoucher-for-woocommerce' ),
					'missing_rules'  => esc_html__( 'Error: At least one rule is required.', 'univoucher-for-woocommerce' ),
					'db_error'       => esc_html__( 'Error: Database error occurred. Check error log for details.', 'univoucher-for-woocommerce' ),
					'duplicate'      => esc_html__( 'Error: Failed to duplicate promotion.', 'univoucher-for-woocommerce' ),
				);
				$message = isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : esc_html__( 'An error occurred. Please try again.', 'univoucher-for-woocommerce' );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
			?>

			<!-- Filters -->
			<div class="univoucher-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<form method="get" id="promotions-filter-form">
					<input type="hidden" name="page" value="univoucher-promotions">
					<div style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
						<div>
							<label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">
								<?php esc_html_e( 'Status', 'univoucher-for-woocommerce' ); ?>
							</label>
							<select name="filter_status" id="filter_status" style="width: 150px;">
								<option value=""><?php esc_html_e( 'All Statuses', 'univoucher-for-woocommerce' ); ?></option>
								<option value="active" <?php selected( $filters['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'univoucher-for-woocommerce' ); ?></option>
								<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'univoucher-for-woocommerce' ); ?></option>
							</select>
						</div>
						<div>
							<label for="s" style="display: block; margin-bottom: 5px; font-weight: 600;">
								<?php esc_html_e( 'Search', 'univoucher-for-woocommerce' ); ?>
							</label>
							<input type="text" name="s" id="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search promotions...', 'univoucher-for-woocommerce' ); ?>" style="width: 250px;">
						</div>
						<div>
							<button type="submit" class="button">
								<?php esc_html_e( 'Filter', 'univoucher-for-woocommerce' ); ?>
							</button>
							<?php if ( ! empty( $filters['status'] ) || ! empty( $filters['search'] ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions' ) ); ?>" class="button">
									<?php esc_html_e( 'Reset', 'univoucher-for-woocommerce' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</form>
			</div>

			<form method="post" id="promotions-list-form">
				<?php wp_nonce_field( 'bulk_delete_promotions' ); ?>
				<input type="hidden" name="action" value="bulk_delete">

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<button type="submit" class="button action" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete the selected promotions? All associated promotional cards and user tracking data will be permanently deleted.', 'univoucher-for-woocommerce' ); ?>');">
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

				<table class="wp-list-table widefat striped univoucher-promotions-table">
					<thead>
						<tr>
							<td class="check-column">
								<input type="checkbox" id="select-all-promotions">
							</td>
							<th><?php esc_html_e( 'Title', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Rules', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Gift Card', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Network', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Total Issued', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Active', 'univoucher-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'univoucher-for-woocommerce' ); ?></th>
						</tr>
					</thead>
				<tbody>
					<?php if ( empty( $promotions ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 40px;">
								<?php esc_html_e( 'No promotions found.', 'univoucher-for-woocommerce' ); ?>
								<br><br>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions&action=add' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Create Your First Promotion', 'univoucher-for-woocommerce' ); ?>
								</a>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $promotions as $promotion ) : ?>
							<?php
							$network_data = UniVoucher_WC_Product_Fields::get_network_data( $promotion->chain_id );
							$network_name = $network_data ? $network_data['name'] : esc_html__( 'Unknown', 'univoucher-for-woocommerce' );
							$rules = json_decode( $promotion->rules, true );
							$rules_display = $this->format_rules_display( $rules );
							?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="promotion_ids[]" value="<?php echo absint( $promotion->id ); ?>">
								</th>
								<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions&action=edit&promotion_id=' . $promotion->id ) ); ?>"><?php echo esc_html( $promotion->title ); ?></a></strong></td>
								<td><?php echo wp_kses_post( $rules_display ); ?></td>
								<td><?php echo esc_html( $this->format_token_amount( $promotion->card_amount, $promotion->token_decimals ) . ' ' . $promotion->token_symbol ); ?></td>
								<td><?php echo esc_html( $network_name ); ?></td>
								<td><?php echo absint( $promotion->total_issued ); ?></td>
								<td>
									<label class="univoucher-toggle-switch">
										<input type="checkbox"
											class="univoucher-toggle-promotion"
											data-promotion-id="<?php echo absint( $promotion->id ); ?>"
											<?php checked( $promotion->is_active, 1 ); ?>>
										<span class="univoucher-toggle-slider"></span>
									</label>
								</td>
								<td class="univoucher-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions&action=edit&promotion_id=' . $promotion->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'univoucher-for-woocommerce' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=univoucher-promotions&action=duplicate&promotion_id=' . $promotion->id ), 'duplicate_promotion' ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Duplicate', 'univoucher-for-woocommerce' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=univoucher-promotions&action=delete&promotion_id=' . $promotion->id ), 'delete_promotion' ) ); ?>"
										class="button button-small button-link-delete"
										onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this promotion? All associated promotional cards and user tracking data will be permanently deleted.', 'univoucher-for-woocommerce' ); ?>');">
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
				// Select all functionality
				$('#select-all-promotions').on('change', function() {
					$('input[name="promotion_ids[]"]').prop('checked', $(this).prop('checked'));
				});

				// Update select-all state when individual checkboxes change
				$('input[name="promotion_ids[]"]').on('change', function() {
					var allChecked = $('input[name="promotion_ids[]"]').length === $('input[name="promotion_ids[]"]:checked').length;
					$('#select-all-promotions').prop('checked', allChecked);
				});

				// Validate form submission
				$('#promotions-list-form').on('submit', function(e) {
					if ($('input[name="promotion_ids[]"]:checked').length === 0) {
						e.preventDefault();
						alert('<?php esc_html_e( 'Please select at least one promotion to delete.', 'univoucher-for-woocommerce' ); ?>');
						return false;
					}
				});
			});
		</script>

		<style>
			.check-column {
				width: 40px;
				text-align: center;
			}
		</style>
		</div>
		<?php
	}

	/**
	 * Render edit/add promotion page.
	 */
	private function render_edit_page() {
		$promotion_id = isset( $_GET['promotion_id'] ) ? absint( $_GET['promotion_id'] ) : 0;
		$promotion = $promotion_id ? $this->get_promotion( $promotion_id ) : null;
		$is_edit = (bool) $promotion;

		// Set default values for new promotion.
		if ( ! $is_edit ) {
			$promotion = (object) array(
				'title'                     => '',
				'description'               => '',
				'chain_id'                  => '1',
				'token_type'                => 'native',
				'token_address'             => '',
				'token_symbol'              => '',
				'token_decimals'            => 18,
				'card_amount'               => '',
				'rules'                     => '[]',
				'max_per_user'              => 0,
				'max_total'                 => 0,
				'send_separate_email'       => 1,
				'email_subject'             => '',
				'email_template'            => '',
				'show_account_notice'       => 1,
				'account_notice_message'    => '',
				'show_order_notice'         => 1,
				'order_notice_message'      => '',
				'show_shortcode_notice'     => 1,
				'shortcode_notice_message'  => '',
			);
		}

		$rules = json_decode( $promotion->rules, true );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo $is_edit ? esc_html__( 'Edit Promotion', 'univoucher-for-woocommerce' ) : esc_html__( 'Add New Promotion', 'univoucher-for-woocommerce' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions' ) ); ?>" class="page-title-action">
				<span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Back to Promotions List', 'univoucher-for-woocommerce' ); ?>
			</a>
			<hr class="wp-heading-inline">

			<?php
			// Display notices.
			if ( isset( $_GET['saved'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Promotion saved successfully.', 'univoucher-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_GET['duplicated'] ) ) {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'This is a duplicate. Make changes and save.', 'univoucher-for-woocommerce' ) . '</p></div>';
			}
			?>

			<!-- Page Introduction -->
			<div class="univoucher-form-intro" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
				<p style="font-size: 15px; line-height: 1.6; margin: 0;">
					<?php esc_html_e( 'Configure your promotional campaign by defining the gift card details, trigger rules, usage limits, and customer notifications. All rules use AND logic - every condition must be satisfied for the promotion to apply.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions' ) ); ?>" id="univoucher-promotion-form">
				<?php wp_nonce_field( 'save_promotion', 'univoucher_promotion_nonce' ); ?>
				<input type="hidden" name="promotion_id" value="<?php echo absint( $promotion_id ); ?>">
				<input type="hidden" name="univoucher_save_promotion" value="1">

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Promotion Details', 'univoucher-for-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="title"><?php esc_html_e( 'Title', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $promotion->title ); ?>" required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="description"><?php esc_html_e( 'Description', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<textarea id="description" name="description" class="large-text" rows="3"><?php echo esc_textarea( $promotion->description ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Gift Card Details', 'univoucher-for-woocommerce' ); ?></h2>
					<p class="description" style="margin: 0 0 15px 0;">
						<?php esc_html_e( '⚠️ These settings determine what will be generated from your internal wallet for each qualifying order.', 'univoucher-for-woocommerce' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="chain_id"><?php esc_html_e( 'Blockchain Network', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<select id="chain_id" name="chain_id" required>
									<?php
									$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
									foreach ( $networks as $chain_id => $network ) {
										echo '<option value="' . esc_attr( $chain_id ) . '" ' . selected( $promotion->chain_id, $chain_id, false ) . '>' . esc_html( $network['name'] ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="token_type"><?php esc_html_e( 'Token Type', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<select id="token_type" name="token_type" required>
									<option value="native" <?php selected( $promotion->token_type, 'native' ); ?>><?php esc_html_e( 'Native Token', 'univoucher-for-woocommerce' ); ?></option>
									<option value="erc20" <?php selected( $promotion->token_type, 'erc20' ); ?>><?php esc_html_e( 'ERC-20 Token', 'univoucher-for-woocommerce' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="token-address-row" style="<?php echo ( 'erc20' === $promotion->token_type ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="token_address"><?php esc_html_e( 'Token Address', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="token_address" name="token_address" class="regular-text" value="<?php echo esc_attr( $promotion->token_address ); ?>" placeholder="0x...">
								<p class="description"><?php esc_html_e( 'Enter the ERC-20 token contract address.', 'univoucher-for-woocommerce' ); ?></p>
								<button type="button" id="get-token-info" class="button" style="margin-top: 10px;">
									<?php esc_html_e( 'Get Token Info', 'univoucher-for-woocommerce' ); ?>
								</button>
								<span class="spinner" id="token-spinner" style="float: none; margin: 0 0 0 10px;"></span>
								<div id="token-info-message" style="margin-top: 10px;"></div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="token_symbol"><?php esc_html_e( 'Token Symbol', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="token_symbol_display" class="regular-text" value="<?php echo esc_attr( $promotion->token_symbol ); ?>" required readonly>
								<input type="hidden" id="token_symbol" name="token_symbol" value="<?php echo esc_attr( $promotion->token_symbol ); ?>">
								<input type="hidden" id="token_decimals" name="token_decimals" value="<?php echo absint( $promotion->token_decimals ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="card_amount"><?php esc_html_e( 'Card Amount', 'univoucher-for-woocommerce' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="number" id="card_amount" name="card_amount" class="regular-text" value="<?php echo esc_attr( $this->format_token_amount( $promotion->card_amount, $promotion->token_decimals ) ); ?>" step="0.000001" min="0" required>
								<p class="description"><strong><?php esc_html_e( 'Amount of tokens per gift card. Your internal wallet will fund this amount for each qualifying order.', 'univoucher-for-woocommerce' ); ?></strong></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Rules (AND)', 'univoucher-for-woocommerce' ); ?></h2>
					<p class="description" style="margin: 0 0 15px 0;">
						<?php esc_html_e( 'All rules must be satisfied for the promotion to trigger. Add multiple rules to create precise targeting.', 'univoucher-for-woocommerce' ); ?>
						<br>
						<strong><?php esc_html_e( 'Example:', 'univoucher-for-woocommerce' ); ?></strong>
						<?php esc_html_e( '"Order total more than $100 AND user never received any promotions before" ensures only first-time high-value customers get the reward.', 'univoucher-for-woocommerce' ); ?>
					</p>

					<div id="promotion-rules-container">
						<?php
						if ( empty( $rules ) ) {
							$rules = array( array( 'type' => 'order', 'condition' => 'includes_product', 'value' => '' ) );
						}
						foreach ( $rules as $index => $rule ) {
							$this->render_rule_row( $index, $rule );
						}
						?>
					</div>

					<button type="button" id="add-rule" class="button">
						<?php esc_html_e( '+ Add Rule', 'univoucher-for-woocommerce' ); ?>
					</button>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Promotion Limits', 'univoucher-for-woocommerce' ); ?></h2>
					<p class="description" style="margin: 0 0 15px 0;">
						<?php esc_html_e( 'Control costs and prevent abuse by setting usage limits.', 'univoucher-for-woocommerce' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="max_per_user"><?php esc_html_e( 'Maximum per User', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<input type="number" id="max_per_user" name="max_per_user" class="small-text" value="<?php echo absint( $promotion->max_per_user ); ?>" min="0">
								<p class="description">
									<?php esc_html_e( 'Maximum times this gift can be given to a single user. 0 for unlimited.', 'univoucher-for-woocommerce' ); ?>
									<br>
									<strong><?php esc_html_e( 'Recommended:', 'univoucher-for-woocommerce' ); ?></strong>
									<?php esc_html_e( 'Set to 1 for welcome gifts, higher for recurring rewards.', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="max_total"><?php esc_html_e( 'Maximum Total', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<input type="number" id="max_total" name="max_total" class="small-text" value="<?php echo absint( $promotion->max_total ); ?>" min="0">
								<p class="description">
									<?php esc_html_e( 'Maximum total times this gift can be given across all customers. 0 for unlimited.', 'univoucher-for-woocommerce' ); ?>
									<br>
									<strong><?php esc_html_e( 'Use this to cap your promotion budget.', 'univoucher-for-woocommerce' ); ?></strong>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Email Settings', 'univoucher-for-woocommerce' ); ?></h2>
					<p class="description" style="margin: 0 0 15px 0;">
						<?php esc_html_e( 'Configure how customers receive their promotional gift cards.', 'univoucher-for-woocommerce' ); ?>
						<strong><?php esc_html_e( 'Tip:', 'univoucher-for-woocommerce' ); ?></strong>
						<?php esc_html_e( 'Enable both options to maximize customer awareness.', 'univoucher-for-woocommerce' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Send Separate Email', 'univoucher-for-woocommerce' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="send_separate_email" id="send_separate_email" value="1" <?php checked( $promotion->send_separate_email, 1 ); ?>>
									<?php esc_html_e( 'Send a dedicated email with the promotional gift card', 'univoucher-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr id="email-subject-row" style="<?php echo ( $promotion->send_separate_email ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="email_subject"><?php esc_html_e( 'Email Subject', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<?php
								$default_subject = 'Your order #{order_id} got a free gift card 🎁';
								$email_subject = ! empty( $promotion->email_subject ) ? $promotion->email_subject : $default_subject;
								?>
								<input type="text" id="email_subject" name="email_subject" class="large-text" value="<?php echo esc_attr( $email_subject ); ?>">
								<p class="description">
									<?php esc_html_e( 'Subject line for the separate gift card email. Available placeholders: {customer_name}, {order_number}, {order_id}, {site_name}', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
						<tr id="email-template-row" style="<?php echo ( $promotion->send_separate_email ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="email_template"><?php esc_html_e( 'Separate Email Template', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<?php
								$default_separate_template = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
	<div style="background-color: #96588a; border-radius: 12px 12px 0 0; padding: 40px 30px; text-align: center; border-bottom: 3px solid #7a4872;">
		<h1 style="margin: 0; font-size: 32px; color: #ffffff;">🎉 You\'ve Got a Gift! 🎉</h1>
	</div>

	<div style="background-color: #f7f7f7; padding: 40px 30px; border-radius: 0 0 12px 12px; border: 1px solid #ddd; border-top: none;">
		<p style="margin: 0 0 20px 0; font-size: 18px; color: #333;">Dear {customer_name},</p>

		<p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #555;">
			Great news! Your recent order <strong>#{order_number}</strong> qualifies for a special gift card. We\'re thrilled to share this exclusive reward with you!
		</p>

		<div style="background-color: #ffffff; border-radius: 12px; padding: 30px; margin: 30px 0; border: 2px solid #96588a;">
			<h2 style="margin: 0 0 20px 0; font-size: 24px; text-align: center; color: #96588a;">Your Gift Card</h2>
			{gift_card_details}
		</div>

		<div style="border-left: 4px solid #96588a; padding: 20px; margin: 30px 0; border-radius: 4px; background-color: #ffffff;">
			<h3 style="margin: 0 0 10px 0; font-size: 18px; color: #96588a;">How to Redeem:</h3>
			<ol style="margin: 10px 0; padding-left: 20px; line-height: 1.8; color: #555;">
				<li>Visit <a href="https://univoucher.com" style="color: #96588a; text-decoration: none; font-weight: bold;">UniVoucher.com</a></li>
				<li>Enter your Card ID and Card Secret</li>
				<li>Follow the redemption instructions</li>
				<li>Enjoy your reward!</li>
			</ol>
		</div>

		<p style="margin: 30px 0 10px 0; font-size: 16px; text-align: center; color: #333;">
			Thank you for being a valued customer!
		</p>

		<p style="margin: 0; font-size: 14px; text-align: center; color: #777;">
			If you have any questions, please don\'t hesitate to contact us.
		</p>
	</div>
</div>';

								$separate_template = ! empty( $promotion->email_template ) ? $promotion->email_template : $default_separate_template;

								wp_editor(
									$separate_template,
									'email_template',
									array(
										'textarea_name' => 'email_template',
										'textarea_rows' => 40,
										'media_buttons' => false,
										'teeny'         => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,alignleft,aligncenter,alignright,forecolor,backcolor',
											'toolbar2' => '',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'This is the complete email template that will be sent as a separate email. Available placeholders: {customer_name}, {user_name}, {order_number}, {order_id}, {gift_card_details}, {card_id}, {card_secret}, {amount}, {symbol}, {network}, {site_name}', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Order Page Notice Settings', 'univoucher-for-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Order Page Notice', 'univoucher-for-woocommerce' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="show_order_notice" id="show_order_notice" value="1" <?php checked( $promotion->show_order_notice, 1 ); ?>>
									<?php esc_html_e( 'Show an elegant notice on the order details and thank you page if the user has an active promotional gift card (not redeemed nor cancelled)', 'univoucher-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr id="order-notice-message-row" style="<?php echo ( $promotion->show_order_notice ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="order_notice_message"><?php esc_html_e( 'Order Page Notice Template', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<?php
								$default_order_notice_template = '<div style="border-left:4px solid #667eea;background:#f8f9fa;padding:15px;margin:15px 0;border-radius:5px">
	<h4 style="margin:0 0 5px 0;color:#667eea">🎁 You have got a Reward !</h4>
	<h3 style="margin:0 0 15px 0;color:#2c3e50">{amount} {symbol} UniVoucher Gift Card</h3>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:10px">
		<small><strong style="color:#667eea">CARD ID:</strong></small>
		<div style="font-family:monospace;margin-top:3px;word-break:break-all"><small>{card_id}</small></div>
	</div>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:10px">
		<small><strong style="color:#667eea">CARD SECRET:</strong></small>
		<div style="font-family:monospace;margin-top:3px;word-break:break-all"><small>{card_secret}</small></div>
	</div>
	<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:12px">
		<small><strong style="color:#667eea">NETWORK:</strong></small>
		<div style="font-family:monospace;margin-top:3px"><small>{network}</small></div>
	</div>
	<a href="https://univoucher.com" target="_blank" rel="noopener noreferrer" style="display:inline-block;background:#667eea;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none">Redeem Now →</a>
</div>';

								$order_notice_template = ! empty( $promotion->order_notice_message ) ? $promotion->order_notice_message : $default_order_notice_template;

								wp_editor(
									$order_notice_template,
									'order_notice_message',
									array(
										'textarea_name' => 'order_notice_message',
										'textarea_rows' => 30,
										'media_buttons' => false,
										'teeny'         => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,alignleft,aligncenter,alignright,forecolor,backcolor',
											'toolbar2' => '',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Design an elegant notice that will be displayed on order pages. Available placeholders: {amount}, {symbol}, {network}, {card_id}, {card_secret}', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'My Account Notice Settings', 'univoucher-for-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'My Account Notice', 'univoucher-for-woocommerce' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="show_account_notice" id="show_account_notice" value="1" <?php checked( $promotion->show_account_notice, 1 ); ?>>
									<?php esc_html_e( 'Show a notice in My Account dashboard if the user has an active promotional gift card (not redeemed nor cancelled) or until user dismisses the notification (dismiss stops the notification for 7 days)', 'univoucher-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr id="account-notice-message-row" style="<?php echo ( $promotion->show_account_notice ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="account_notice_message"><?php esc_html_e( 'My Account Notice Message', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<textarea id="account_notice_message" name="account_notice_message" class="large-text" rows="3"><?php echo esc_textarea( ! empty( $promotion->account_notice_message ) ? $promotion->account_notice_message : 'You have an unredeemed {amount} {symbol} on {network} UniVoucher promotional gift card. Card ID: {card_id}, Card Secret: {card_secret}. To redeem this card, please visit <a href="https://univoucher.com" target="_blank" rel="noopener noreferrer">univoucher.com</a>' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Available placeholders: {amount}, {symbol}, {network}, {card_id}, {card_secret}', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="univoucher-promotion-section">
					<h2><?php esc_html_e( 'Shortcode Notice Settings', 'univoucher-for-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Shortcode Notice', 'univoucher-for-woocommerce' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="show_shortcode_notice" id="show_shortcode_notice" value="1" <?php checked( $promotion->show_shortcode_notice, 1 ); ?>>
									<?php esc_html_e( 'Show a notice anywhere using the shortcode', 'univoucher-for-woocommerce' ); ?> <strong>[univoucher_unredeemed_promotion]</strong> <?php esc_html_e( 'if the user has an active promotional gift card (not redeemed nor cancelled) or until user dismisses the notification (dismiss stops the notification for 7 days)', 'univoucher-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr id="shortcode-notice-message-row" style="<?php echo ( $promotion->show_shortcode_notice ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="shortcode_notice_message"><?php esc_html_e( 'Notice Message', 'univoucher-for-woocommerce' ); ?></label>
							</th>
							<td>
								<textarea id="shortcode_notice_message" name="shortcode_notice_message" class="large-text" rows="3"><?php echo esc_textarea( ! empty( $promotion->shortcode_notice_message ) ? $promotion->shortcode_notice_message : 'You have an unredeemed {amount} {symbol} on {network} UniVoucher promotional gift card. Card ID: {card_id}, Card Secret: {card_secret}. To redeem this card, please visit <a href="https://univoucher.com" target="_blank" rel="noopener noreferrer">univoucher.com</a>' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Available placeholders: {amount}, {symbol}, {network}, {card_id}, {card_secret}', 'univoucher-for-woocommerce' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__( 'Update Promotion', 'univoucher-for-woocommerce' ) : esc_attr__( 'Create Promotion', 'univoucher-for-woocommerce' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-promotions' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'univoucher-for-woocommerce' ); ?>
					</a>
				</p>
			</form>

			<!-- Template for new rule row (hidden) -->
			<script type="text/template" id="rule-row-template">
				<?php $this->render_rule_row( '{{INDEX}}', array( 'type' => 'order', 'condition' => 'includes_product', 'value' => '' ), true ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Render a single rule row.
	 *
	 * @param int   $index Rule index.
	 * @param array $rule Rule data.
	 * @param bool  $is_template Whether this is a template row.
	 */
	private function render_rule_row( $index, $rule, $is_template = false ) {
		$rule = wp_parse_args(
			$rule,
			array(
				'type'      => 'order',
				'condition' => 'includes_products',
				'value'     => '',
			)
		);

		$index_attr = $is_template ? '{{INDEX}}' : $index;
		?>
		<?php if ( $index > 0 && ! $is_template ) : ?>
			<div class="rule-separator">
				<span class="rule-and-label"><?php esc_html_e( 'AND', 'univoucher-for-woocommerce' ); ?></span>
			</div>
		<?php endif; ?>
		<div class="univoucher-rule-row" data-index="<?php echo esc_attr( $index_attr ); ?>">
			<div class="rule-field">
				<label><?php esc_html_e( 'If', 'univoucher-for-woocommerce' ); ?></label>
				<select name="rules[<?php echo esc_attr( $index_attr ); ?>][type]" class="rule-type">
					<option value="order" <?php selected( $rule['type'], 'order' ); ?>><?php esc_html_e( 'Order', 'univoucher-for-woocommerce' ); ?></option>
					<option value="user" <?php selected( $rule['type'], 'user' ); ?>><?php esc_html_e( 'User', 'univoucher-for-woocommerce' ); ?></option>
				</select>
			</div>

			<div class="rule-field">
				<select name="rules[<?php echo esc_attr( $index_attr ); ?>][condition]" class="rule-condition">
					<!-- Order conditions -->
					<option value="includes_product" <?php selected( $rule['condition'], 'includes_product' ); ?> data-type="order"><?php esc_html_e( 'Includes this product', 'univoucher-for-woocommerce' ); ?></option>
					<option value="includes_category" <?php selected( $rule['condition'], 'includes_category' ); ?> data-type="order"><?php esc_html_e( 'Includes a product from this category', 'univoucher-for-woocommerce' ); ?></option>
					<option value="total_value" <?php selected( $rule['condition'], 'total_value' ); ?> data-type="order"><?php esc_html_e( 'Total value is', 'univoucher-for-woocommerce' ); ?></option>
					<!-- User conditions -->
					<option value="user_id" <?php selected( $rule['condition'], 'user_id' ); ?> data-type="user" style="display:none;"><?php esc_html_e( 'User ID is', 'univoucher-for-woocommerce' ); ?></option>
					<option value="completed_orders_count" <?php selected( $rule['condition'], 'completed_orders_count' ); ?> data-type="user" style="display:none;"><?php esc_html_e( 'Completed orders count', 'univoucher-for-woocommerce' ); ?></option>
					<option value="user_role" <?php selected( $rule['condition'], 'user_role' ); ?> data-type="user" style="display:none;"><?php esc_html_e( 'User role is', 'univoucher-for-woocommerce' ); ?></option>
					<option value="registration_date" <?php selected( $rule['condition'], 'registration_date' ); ?> data-type="user" style="display:none;"><?php esc_html_e( 'Registration date', 'univoucher-for-woocommerce' ); ?></option>
					<option value="never_received_promotion" <?php selected( $rule['condition'], 'never_received_promotion' ); ?> data-type="user" style="display:none;"><?php esc_html_e( 'never received any promotions before before', 'univoucher-for-woocommerce' ); ?></option>
				</select>
			</div>

			<div class="rule-field rule-value-field">
				<div class="value-product" style="<?php echo ( 'includes_product' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-product" style="width: 300px;">
						<?php
						if ( ! $is_template && 'includes_product' === $rule['condition'] && ! empty( $rule['value'] ) ) {
							$product = wc_get_product( $rule['value'] );
							if ( $product ) {
								echo '<option value="' . absint( $rule['value'] ) . '" selected>' . esc_html( $product->get_name() ) . '</option>';
							}
						}
						?>
					</select>
				</div>
				<div class="value-category" style="<?php echo ( 'includes_category' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-category" style="width: 300px;">
						<?php
						if ( ! $is_template && 'includes_category' === $rule['condition'] && ! empty( $rule['value'] ) ) {
							$category = get_term( $rule['value'], 'product_cat' );
							if ( $category && ! is_wp_error( $category ) ) {
								echo '<option value="' . absint( $rule['value'] ) . '" selected>' . esc_html( $category->name ) . '</option>';
							}
						}
						?>
					</select>
				</div>
				<div class="value-amount" style="<?php echo ( 'total_value' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<?php
					$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'more_than';
					$value = ( 'total_value' === $rule['condition'] && isset( $rule['value'] ) ) ? $rule['value'] : '';
					$value_min = '';
					$value_max = '';
					if ( is_array( $value ) ) {
						$value_min = isset( $value['min'] ) ? $value['min'] : '';
						$value_max = isset( $value['max'] ) ? $value['max'] : '';
					}
					?>
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][operator]" class="rule-operator" style="width: 150px;">
						<option value="more_than" <?php selected( $operator, 'more_than' ); ?>><?php esc_html_e( 'More than', 'univoucher-for-woocommerce' ); ?></option>
						<option value="less_than" <?php selected( $operator, 'less_than' ); ?>><?php esc_html_e( 'Less than', 'univoucher-for-woocommerce' ); ?></option>
						<option value="between" <?php selected( $operator, 'between' ); ?>><?php esc_html_e( 'Between', 'univoucher-for-woocommerce' ); ?></option>
					</select>
					<span class="rule-value-single" style="<?php echo ( 'between' === $operator ) ? 'display:none;' : ''; ?>">
						<input type="number" name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-amount" value="<?php echo ! is_array( $value ) ? esc_attr( $value ) : ''; ?>" step="0.01" min="0" style="width: 150px;">
						<span class="currency-symbol"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
					</span>
					<span class="rule-value-range" style="<?php echo ( 'between' === $operator ) ? '' : 'display:none;'; ?>">
						<input type="number" name="rules[<?php echo esc_attr( $index_attr ); ?>][value][min]" class="rule-value-amount-min" value="<?php echo esc_attr( $value_min ); ?>" step="0.01" min="0" style="width: 150px;" placeholder="<?php esc_attr_e( 'Min', 'univoucher-for-woocommerce' ); ?>">
						<span><?php esc_html_e( 'and', 'univoucher-for-woocommerce' ); ?></span>
						<input type="number" name="rules[<?php echo esc_attr( $index_attr ); ?>][value][max]" class="rule-value-amount-max" value="<?php echo esc_attr( $value_max ); ?>" step="0.01" min="0" style="width: 150px;" placeholder="<?php esc_attr_e( 'Max', 'univoucher-for-woocommerce' ); ?>">
						<span class="currency-symbol"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
					</span>
				</div>
				<div class="value-registration-date" style="<?php echo ( 'registration_date' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<?php
					$date_operator = isset( $rule['operator'] ) ? $rule['operator'] : 'after';
					$date_value = ( 'registration_date' === $rule['condition'] && isset( $rule['value'] ) ) ? $rule['value'] : '';
					?>
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][operator]" class="rule-date-operator" style="width: 150px;">
						<option value="before" <?php selected( $date_operator, 'before' ); ?>><?php esc_html_e( 'Before', 'univoucher-for-woocommerce' ); ?></option>
						<option value="after" <?php selected( $date_operator, 'after' ); ?>><?php esc_html_e( 'After', 'univoucher-for-woocommerce' ); ?></option>
					</select>
					<input type="date" name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-date" value="<?php echo esc_attr( $date_value ); ?>" style="width: 200px;">
				</div>
				<div class="value-never-received" style="<?php echo ( 'never_received_promotion' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<input type="hidden" name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" value="1">
					<span class="description"><?php esc_html_e( 'User has never received any promotions before before', 'univoucher-for-woocommerce' ); ?></span>
				</div>
				<div class="value-user-id" style="<?php echo ( 'user_id' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<?php
					$user_id_value = ( 'user_id' === $rule['condition'] && isset( $rule['value'] ) ) ? $rule['value'] : '';
					?>
					<input type="text" name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-user-id" value="<?php echo esc_attr( $user_id_value ); ?>" style="width: 300px;" placeholder="<?php esc_attr_e( 'User ID(s) separated by comma, e.g. 1,2,3', 'univoucher-for-woocommerce' ); ?>">
					<span class="description"><?php esc_html_e( 'Enter one or more user IDs separated by commas', 'univoucher-for-woocommerce' ); ?></span>
				</div>
				<div class="value-completed-orders" style="<?php echo ( 'completed_orders_count' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<?php
					$orders_operator = isset( $rule['operator'] ) ? $rule['operator'] : 'more_than';
					$orders_value = ( 'completed_orders_count' === $rule['condition'] && isset( $rule['value'] ) ) ? $rule['value'] : '';
					?>
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][operator]" class="rule-orders-operator" style="width: 150px;">
						<option value="more_than" <?php selected( $orders_operator, 'more_than' ); ?>><?php esc_html_e( 'More than', 'univoucher-for-woocommerce' ); ?></option>
						<option value="less_than" <?php selected( $orders_operator, 'less_than' ); ?>><?php esc_html_e( 'Less than', 'univoucher-for-woocommerce' ); ?></option>
					</select>
					<input type="number" name="rules[<?php echo esc_attr( $index_attr ); ?>][value]" class="rule-value-orders-count" value="<?php echo esc_attr( $orders_value ); ?>" min="0" step="1" style="width: 150px;" placeholder="<?php esc_attr_e( 'Number of orders', 'univoucher-for-woocommerce' ); ?>">
				</div>
				<div class="value-user-role" style="<?php echo ( 'user_role' === $rule['condition'] ) ? '' : 'display:none;'; ?>">
					<?php
					// Get all WordPress roles
					global $wp_roles;
					if ( ! isset( $wp_roles ) ) {
						$wp_roles = new WP_Roles();
					}
					$all_roles = $wp_roles->get_names();

					$selected_roles = ( 'user_role' === $rule['condition'] && isset( $rule['value'] ) ) ? ( is_array( $rule['value'] ) ? $rule['value'] : explode( ',', $rule['value'] ) ) : array();
					?>
					<select name="rules[<?php echo esc_attr( $index_attr ); ?>][value][]" class="rule-value-user-role" multiple style="width: 300px; height: 150px;">
						<?php
						if ( ! empty( $all_roles ) ) {
							foreach ( $all_roles as $role_key => $role_name ) {
								$selected = in_array( $role_key, $selected_roles, false ) ? 'selected' : '';
								echo '<option value="' . esc_attr( $role_key ) . '" ' . $selected . '>' . esc_html( $role_name ) . '</option>';
							}
						}
						?>
					</select>
					<span class="description"><?php esc_html_e( 'User must have one of the selected roles (Hold Ctrl/Cmd to select multiple)', 'univoucher-for-woocommerce' ); ?></span>
				</div>
			</div>

			<div class="rule-field rule-actions">
				<button type="button" class="button remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'univoucher-for-woocommerce' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get token information.
	 */
	public function ajax_get_token_info() {
		check_ajax_referer( 'univoucher_promotions_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'univoucher-for-woocommerce' ) ) );
		}

		$token_address = isset( $_POST['token_address'] ) ? sanitize_text_field( wp_unslash( $_POST['token_address'] ) ) : '';
		$network = isset( $_POST['network'] ) ? sanitize_text_field( wp_unslash( $_POST['network'] ) ) : '1';

		if ( empty( $token_address ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Token address is required.', 'univoucher-for-woocommerce' ) ) );
		}

		// Validate address format.
		if ( ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $token_address ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid token address format.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get API key.
		$api_key = get_option( 'univoucher_wc_alchemy_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Alchemy API key not configured. Please check your UniVoucher settings.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get network data.
		$network_data = UniVoucher_WC_Product_Fields::get_network_data( $network );
		if ( ! $network_data ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unsupported network.', 'univoucher-for-woocommerce' ) ) );
		}

		// Map network ID to Alchemy network name.
		$network_mapping = array(
			'1'     => 'eth-mainnet',
			'8453'  => 'base-mainnet',
			'56'    => 'bnb-mainnet',
			'137'   => 'polygon-mainnet',
			'42161' => 'arb-mainnet',
			'10'    => 'opt-mainnet',
			'43114' => 'avax-mainnet',
		);

		$alchemy_network = isset( $network_mapping[ $network ] ) ? $network_mapping[ $network ] : 'eth-mainnet';
		$rpc_url = "https://{$alchemy_network}.g.alchemy.com/v2/{$api_key}";

		// Prepare RPC calls.
		$calls = array(
			array(
				'method' => 'eth_call',
				'params' => array(
					array(
						'to'   => $token_address,
						'data' => '0x95d89b41', // symbol()
					),
					'latest',
				),
				'id'     => 1,
			),
			array(
				'method' => 'eth_call',
				'params' => array(
					array(
						'to'   => $token_address,
						'data' => '0x313ce567', // decimals()
					),
					'latest',
				),
				'id'     => 2,
			),
			array(
				'method' => 'eth_call',
				'params' => array(
					array(
						'to'   => $token_address,
						'data' => '0x06fdde03', // name()
					),
					'latest',
				),
				'id'     => 3,
			),
		);

		$results = array();
		foreach ( $calls as $call ) {
			$response = wp_remote_post(
				$rpc_url,
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'jsonrpc' => '2.0',
							'method'  => $call['method'],
							'params'  => $call['params'],
							'id'      => $call['id'],
						)
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to connect to blockchain API.', 'univoucher-for-woocommerce' ) ) );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid token contract or network issue.', 'univoucher-for-woocommerce' ) ) );
			}

			$results[ $call['id'] ] = isset( $data['result'] ) ? $data['result'] : '';
		}

		// Decode results.
		$symbol = '';
		$decimals = 18;
		$name = '';

		if ( ! empty( $results[1] ) && '0x' !== $results[1] ) {
			$symbol = $this->decode_string( $results[1] );
		}

		if ( ! empty( $results[2] ) && '0x' !== $results[2] ) {
			$decimals = hexdec( $results[2] );
		}

		if ( ! empty( $results[3] ) && '0x' !== $results[3] ) {
			$name = $this->decode_string( $results[3] );
		}

		if ( empty( $symbol ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid token contract.', 'univoucher-for-woocommerce' ) ) );
		}

		wp_send_json_success(
			array(
				'name'     => $name,
				'symbol'   => $symbol,
				'decimals' => $decimals,
				'address'  => $token_address,
			)
		);
	}

	/**
	 * Decode hex string from contract call.
	 *
	 * @param string $hex Hex string.
	 * @return string Decoded string.
	 */
	private function decode_string( $hex ) {
		// Remove 0x prefix.
		$hex = substr( $hex, 2 );

		// Skip the first 64 characters (offset + length).
		$hex = substr( $hex, 128 );

		// Convert hex to string and remove null bytes.
		$string = pack( 'H*', $hex );
		return trim( $string, "\0" );
	}

	/**
	 * AJAX handler to search products.
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'univoucher_promotions_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'univoucher-for-woocommerce' ) ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json( array() );
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $term,
		);

		$products = get_posts( $args );
		$results = array();

		foreach ( $products as $product ) {
			$results[ $product->ID ] = $product->post_title;
		}

		wp_send_json( $results );
	}

	/**
	 * AJAX handler to search categories.
	 */
	public function ajax_search_categories() {
		check_ajax_referer( 'univoucher_promotions_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'univoucher-for-woocommerce' ) ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json( array() );
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'search'     => $term,
				'number'     => 20,
			)
		);

		$results = array();

		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$results[ $category->term_id ] = $category->name;
			}
		}

		wp_send_json( $results );
	}
}
