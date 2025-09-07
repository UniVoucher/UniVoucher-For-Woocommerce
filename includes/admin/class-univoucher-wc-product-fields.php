<?php
/**
 * UniVoucher For WooCommerce Product Fields
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Product_Fields class.
 */
class UniVoucher_WC_Product_Fields {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Product_Fields
	 */
	protected static $_instance = null;

	/**
	 * Supported blockchain networks.
	 *
	 * @var array
	 */
	private static $supported_networks = array(
		'1'     => array( 'name' => 'Ethereum', 'symbol' => 'ETH', 'decimals' => 18 ),
		'8453'  => array( 'name' => 'Base', 'symbol' => 'ETH', 'decimals' => 18 ),
		'56'    => array( 'name' => 'BNB Chain', 'symbol' => 'BNB', 'decimals' => 18 ),
		'137'   => array( 'name' => 'Polygon', 'symbol' => 'POL', 'decimals' => 18 ),
		'42161' => array( 'name' => 'Arbitrum', 'symbol' => 'ETH', 'decimals' => 18 ),
		'10'    => array( 'name' => 'Optimism', 'symbol' => 'ETH', 'decimals' => 18 ),
		'43114' => array( 'name' => 'Avalanche', 'symbol' => 'AVAX', 'decimals' => 18 ),
	);

	/**
	 * Main UniVoucher_WC_Product_Fields Instance.
	 *
	 * @return UniVoucher_WC_Product_Fields - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Product_Fields Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		// Add product data tab.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		
		// Add product data panel.
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_data_panel' ) );
		
		// Validate before save.
		add_action( 'woocommerce_process_product_meta', array( $this, 'validate_product_meta' ), 5 );
		
		// Save product meta.
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ), 10 );
		
		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// AJAX handler for token info.
		add_action( 'wp_ajax_univoucher_get_token_info', array( $this, 'ajax_get_token_info' ) );
		
		// AJAX handler for image generation.
		add_action( 'wp_ajax_univoucher_generate_image', array( $this, 'ajax_generate_image' ) );
		
		// Note: Stock management is now handled by the custom UniVoucher_WC_Product class
	}

	/**
	 * Add UniVoucher product data tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['univoucher_gift_card'] = array(
			'label'    => esc_html__( 'UniVoucher', 'univoucher-for-woocommerce' ),
			'target'   => 'univoucher_gift_card_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 15,
		);
		return $tabs;
	}

	/**
	 * Add UniVoucher product data panel.
	 */
	public function add_product_data_panel() {
		global $product_object;
		
		// Check if product has existing gift cards in inventory
		$product_manager = UniVoucher_WC_Product_Manager::instance();
		$has_existing_cards = $product_manager->product_has_existing_cards( $product_object ? $product_object->get_id() : 0 );
		$cards_count = $product_manager->get_product_cards_count( $product_object ? $product_object->get_id() : 0 );
		
		?>
		<div id="univoucher_gift_card_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				// Enable UniVoucher Gift Card.
				woocommerce_wp_checkbox(
					array(
						'id'          => '_univoucher_enabled',
						'label'       => esc_html__( 'Enable UniVoucher', 'univoucher-for-woocommerce' ),
						'description' => esc_html__( 'Deliver a UniVoucher gift card with this product from available stock.', 'univoucher-for-woocommerce' ),
						'desc_tip'    => true,
						'value'       => $product_object ? $product_object->get_meta( '_univoucher_enabled' ) : '',
						'custom_attributes' => $has_existing_cards ? array( 'disabled' => 'disabled' ) : array(),
					)
				);
				
				// Add hidden field to preserve enable checkbox value when disabled
				if ( $has_existing_cards ) {
					$enabled_value = $product_object ? $product_object->get_meta( '_univoucher_enabled' ) : '';
					echo '<input type="hidden" name="_univoucher_enabled" value="' . esc_attr( $enabled_value ) . '" />';
				}
				?>
			</div>

			<?php if ( $has_existing_cards ) : ?>
			<div class="univoucher-stock-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin: 25px; display: block;">
				<h4 style="margin: 0 0 8px 0; color: #856404; font-size: 14px;">
					<span class="dashicons dashicons-warning" style="font-size: 16px; margin-right: 5px;"></span>
					<?php esc_html_e( 'Gift Card Settings Locked', 'univoucher-for-woocommerce' ); ?>
				</h4>
				<p style="margin: 0; color: #856404; font-size: 13px; line-height: 1.4;">
					<?php 
					printf(
						/* translators: %d is the number of gift cards */
						esc_html__( 'This product has %d existing gift card/s in the inventory. You must delete all gift cards connected to this product from the inventory before you can modify the gift card settings.', 'univoucher-for-woocommerce' ),
						$cards_count
					);
					?>
				</p>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #856404;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-inventory&product_id=' . ( $product_object ? $product_object->get_id() : 0 ) ) ); ?>" style="color: #856404; text-decoration: underline;">
						<?php esc_html_e( 'Manage Inventory →', 'univoucher-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php endif; ?>

			<div class="options_group univoucher-gift-card-options" style="<?php echo ( $product_object && $product_object->get_meta( '_univoucher_enabled' ) ) ? '' : 'display:none;'; ?>">
				<?php
				// Network selection.
				woocommerce_wp_select(
					array(
						'id'          => '_univoucher_network',
						'label'       => esc_html__( 'Blockchain Network', 'univoucher-for-woocommerce' ),
						'description' => esc_html__( 'Select the blockchain network for the gift card.', 'univoucher-for-woocommerce' ),
						'desc_tip'    => true,
						'options'     => $this->get_network_options(),
						'value'       => $product_object ? $product_object->get_meta( '_univoucher_network' ) : '1',
						'custom_attributes' => $has_existing_cards ? array( 'disabled' => 'disabled' ) : array(),
					)
				);
				
				// Add hidden field to preserve network value when disabled
				if ( $has_existing_cards ) {
					$network_value = $product_object ? $product_object->get_meta( '_univoucher_network' ) : '1';
					echo '<input type="hidden" name="_univoucher_network" value="' . esc_attr( $network_value ) . '" />';
				}

				// Token type selection.
				woocommerce_wp_select(
					array(
						'id'          => '_univoucher_token_type',
						'label'       => esc_html__( 'Token Type', 'univoucher-for-woocommerce' ),
						'description' => esc_html__( 'Select native token or ERC-20 token.', 'univoucher-for-woocommerce' ),
						'desc_tip'    => true,
						'options'     => array(
							'native' => esc_html__( 'Native Token', 'univoucher-for-woocommerce' ),
							'erc20'  => esc_html__( 'ERC-20 Token', 'univoucher-for-woocommerce' ),
						),
						'value'       => $product_object ? $product_object->get_meta( '_univoucher_token_type' ) : 'native',
						'custom_attributes' => $has_existing_cards ? array( 'disabled' => 'disabled' ) : array(),
					)
				);
				
				// Add hidden field to preserve token type value when disabled
				if ( $has_existing_cards ) {
					$token_type_value = $product_object ? $product_object->get_meta( '_univoucher_token_type' ) : 'native';
					echo '<input type="hidden" name="_univoucher_token_type" value="' . esc_attr( $token_type_value ) . '" />';
				}

				// ERC-20 token address.
				woocommerce_wp_text_input(
					array(
						'id'          => '_univoucher_token_address',
						'label'       => esc_html__( 'Token Address', 'univoucher-for-woocommerce' ),
						'description' => esc_html__( 'Enter the ERC-20 token contract address.', 'univoucher-for-woocommerce' ),
						'desc_tip'    => true,
						'placeholder' => esc_attr__( '0x...', 'univoucher-for-woocommerce' ),
						'value'       => $product_object ? $product_object->get_meta( '_univoucher_token_address' ) : '',
						'wrapper_class' => 'univoucher-erc20-field',
						'custom_attributes' => $has_existing_cards ? array( 'disabled' => 'disabled' ) : array(),
					)
				);
				
				// Add hidden field to preserve token address value when disabled
				if ( $has_existing_cards ) {
					$token_address_value = $product_object ? $product_object->get_meta( '_univoucher_token_address' ) : '';
					echo '<input type="hidden" name="_univoucher_token_address" value="' . esc_attr( $token_address_value ) . '" />';
				}
				?>

				<p class="form-field">
					<label>&nbsp;</label>
					<button type="button" id="univoucher-get-token-info" class="button univoucher-erc20-field" <?php echo $has_existing_cards ? 'disabled="disabled"' : ''; ?>>
						<?php esc_html_e( 'Get Token Info', 'univoucher-for-woocommerce' ); ?>
					</button>
					<span class="spinner" id="univoucher-token-spinner"></span>
				</p>

				<div id="univoucher-token-info" class="univoucher-token-info"></div>

				<?php
				// Card amount.
				woocommerce_wp_text_input(
					array(
						'id'          => '_univoucher_card_amount',
						'label'       => esc_html__( 'Card Amount', 'univoucher-for-woocommerce' ),
						'description' => esc_html__( 'Amount of tokens per gift card.', 'univoucher-for-woocommerce' ),
						'desc_tip'    => true,
						'type'        => 'number',
						'custom_attributes' => array_merge(
							array(
								'step' => '0.000001',
								'min'  => '0',
							),
							$has_existing_cards ? array( 'disabled' => 'disabled' ) : array()
						),
						'value'       => $product_object ? $product_object->get_meta( '_univoucher_card_amount' ) : '',
					)
				);
				
				// Add hidden field to preserve card amount value when disabled
				if ( $has_existing_cards ) {
					$card_amount_value = $product_object ? $product_object->get_meta( '_univoucher_card_amount' ) : '';
					echo '<input type="hidden" name="_univoucher_card_amount" value="' . esc_attr( $card_amount_value ) . '" />';
				}

				// Token symbol (display only).
				?>
				<p class="form-field">
					<label for="_univoucher_token_symbol"><?php esc_html_e( 'Token Symbol', 'univoucher-for-woocommerce' ); ?></label>
					<span id="univoucher-token-symbol-display" class="univoucher-token-symbol">
						<?php 
						$saved_symbol = $product_object ? $product_object->get_meta( '_univoucher_token_symbol' ) : '';
						$token_type = $product_object ? $product_object->get_meta( '_univoucher_token_type' ) : 'native';
						if ( $saved_symbol ) {
							echo esc_html( $saved_symbol );
						} elseif ( 'erc20' === $token_type ) {
							echo esc_html__( 'Click "Get Token Info"', 'univoucher-for-woocommerce' );
						} else {
							echo esc_html__( 'Select token type first', 'univoucher-for-woocommerce' );
						}
						?>
					</span>
					<input type="hidden" id="_univoucher_token_symbol" name="_univoucher_token_symbol" value="<?php echo esc_attr( $saved_symbol ); ?>" />
				</p>

				<?php
				// Token decimals (hidden field).
				?>
				<p class="form-field">
					<label for="_univoucher_token_decimals"><?php esc_html_e( 'Token Decimals', 'univoucher-for-woocommerce' ); ?></label>
					<span id="univoucher-token-decimals-display" class="univoucher-token-decimals">
						<?php 
						$saved_decimals = $product_object ? $product_object->get_meta( '_univoucher_token_decimals' ) : '';
						$token_type = $product_object ? $product_object->get_meta( '_univoucher_token_type' ) : 'native';
						if ( $saved_decimals ) {
							echo esc_html( $saved_decimals );
						} elseif ( 'erc20' === $token_type ) {
							echo esc_html__( 'Click "Get Token Info"', 'univoucher-for-woocommerce' );
						} else {
							echo esc_html__( 'Select token type first', 'univoucher-for-woocommerce' );
						}
						?>
					</span>
					<input type="hidden" id="_univoucher_token_decimals" name="_univoucher_token_decimals" value="<?php echo esc_attr( $saved_decimals ); ?>" />
				</p>
			</div>

			<!-- Auto-generate Title & Description Section -->
			<div class="options_group univoucher-auto-generate-section" style="<?php echo ( $product_object && $product_object->get_meta( '_univoucher_enabled' ) ) ? '' : 'display:none;'; ?>">
				<h4 style="padding-left: 12px;"><?php esc_html_e( 'Auto Generate:', 'univoucher-for-woocommerce' ); ?></h4>
				<p class="form-field">
					<label>&nbsp;</label>
					<span class="description" style="display: block; margin-left: 0px;">
						<?php esc_html_e( 'Automatically generate product title and description based on the card details above.', 'univoucher-for-woocommerce' ); ?>
					</span>
					<button type="button" id="univoucher-auto-generate-btn" class="button button-secondary" disabled>
						<?php esc_html_e( 'Generate Title & Description', 'univoucher-for-woocommerce' ); ?>
					</button>
					<br>
					<span id="univoucher-content-status" style="font-size: 12px; color: #666; margin-left: 0;"></span>
					<br>
					<br>
					<span class="description" style="display: block; margin-left: 0px;">
						<?php esc_html_e( 'Automatically generate a product image with gift card details and set it as the featured image.', 'univoucher-for-woocommerce' ); ?>
					</span>
					<button type="button" id="univoucher-generate-image-btn" class="button button-secondary">
						<?php esc_html_e( 'Generate Product Image', 'univoucher-for-woocommerce' ); ?>
					</button>
					<span class="spinner" id="univoucher-image-spinner"></span>
					<br>
					<span id="univoucher-image-status" style="font-size: 12px; color: #666; margin-left: 0;"></span>
					<br>
					<br>
					<div class="univoucher-stock-notice" style="background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px; padding: 12px; margin: 25px; display: block;">
						<h4 style="margin: 0 0 8px 0; color: #0073aa; font-size: 14px;">
							<span class="dashicons dashicons-info" style="font-size: 16px; margin-right: 5px;"></span>
							<?php esc_html_e( 'Generated content and image templates can be customized in settings', 'univoucher-for-woocommerce' ); ?>
						</h4>
						<ul style="margin: 8px 0 0 0; padding-left: 20px; color: #0073aa; font-size: 13px;">
							<li style="margin-bottom: 4px;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-settings&tab=templates' ) ); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">
									<?php esc_html_e( 'Customize product title and descriptions Template →', 'univoucher-for-woocommerce' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-settings&tab=image-templates' ) ); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">
									<?php esc_html_e( 'Customize product image template →', 'univoucher-for-woocommerce' ); ?>
								</a>
							</li>
						</ul>
					</div>
					<br>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate product meta data before saving.
	 *
	 * @param int $post_id Product ID.
	 */
	public function validate_product_meta( $post_id ) {
		// Only validate if UniVoucher is enabled.
		$enabled = isset( $_POST['_univoucher_enabled'] ) ? 'yes' : 'no';
		if ( 'yes' !== $enabled ) {
			return;
		}

		$errors = array();

		// Validate network.
		$network = isset( $_POST['_univoucher_network'] ) ? sanitize_text_field( $_POST['_univoucher_network'] ) : '';
		if ( empty( $network ) ) {
			$errors[] = esc_html__( 'Blockchain Network is required.', 'univoucher-for-woocommerce' );
		}

		// Validate token type.
		$token_type = isset( $_POST['_univoucher_token_type'] ) ? sanitize_text_field( $_POST['_univoucher_token_type'] ) : '';
		if ( empty( $token_type ) ) {
			$errors[] = esc_html__( 'Token Type is required.', 'univoucher-for-woocommerce' );
		}

		// Validate token address (only required for ERC-20 tokens).
		$token_address = isset( $_POST['_univoucher_token_address'] ) ? sanitize_text_field( $_POST['_univoucher_token_address'] ) : '';
		if ( 'erc20' === $token_type && empty( $token_address ) ) {
			$errors[] = esc_html__( 'Token Address is required for ERC-20 tokens.', 'univoucher-for-woocommerce' );
		} elseif ( 'erc20' === $token_type && ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $token_address ) ) {
			$errors[] = esc_html__( 'Token Address must be a valid Ethereum address.', 'univoucher-for-woocommerce' );
		}

		// Validate card amount.
		$card_amount = isset( $_POST['_univoucher_card_amount'] ) ? sanitize_text_field( $_POST['_univoucher_card_amount'] ) : '';
		if ( empty( $card_amount ) || ! is_numeric( $card_amount ) || floatval( $card_amount ) <= 0 ) {
			$errors[] = esc_html__( 'Card Amount is required and must be greater than 0.', 'univoucher-for-woocommerce' );
		}

		// Validate token symbol.
		$token_symbol = isset( $_POST['_univoucher_token_symbol'] ) ? sanitize_text_field( $_POST['_univoucher_token_symbol'] ) : '';
		if ( empty( $token_symbol ) ) {
			$errors[] = esc_html__( 'Token Symbol is required. Please use "Get Token Info" button.', 'univoucher-for-woocommerce' );
		}

		// Validate token decimals.
		$token_decimals = isset( $_POST['_univoucher_token_decimals'] ) ? sanitize_text_field( $_POST['_univoucher_token_decimals'] ) : '';
		if ( empty( $token_decimals ) || ! is_numeric( $token_decimals ) ) {
			$errors[] = esc_html__( 'Token Decimals is required. Please use "Get Token Info" button.', 'univoucher-for-woocommerce' );
		}

		// If there are validation errors, prevent save and display errors.
		if ( ! empty( $errors ) ) {
			// Add WooCommerce admin errors to prevent save and show proper error messages.
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				foreach ( $errors as $error ) {
					WC_Admin_Meta_Boxes::add_error( 'UniVoucher: ' . $error );
				}
			}
			
			// Set flag to prevent saving UniVoucher data.
			$_POST['_univoucher_validation_failed'] = true;
		}
	}

	/**
	 * Save product meta data.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_meta( $post_id ) {
		// Get product object.
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// UniVoucher enabled.
		$enabled = isset( $_POST['_univoucher_enabled'] ) ? 'yes' : 'no';
		
		// Check if validation failed.
		$validation_failed = isset( $_POST['_univoucher_validation_failed'] ) && $_POST['_univoucher_validation_failed'];
		
		// If validation failed, disable UniVoucher and don't save other data.
		if ( $validation_failed ) {
			$product->update_meta_data( '_univoucher_enabled', 'no' );
			$product->save_meta_data();
			return;
		}
		
		$product->update_meta_data( '_univoucher_enabled', $enabled );

		// Only save other fields if enabled and validation passed.
		if ( 'yes' === $enabled ) {
			// Network.
			if ( isset( $_POST['_univoucher_network'] ) ) {
				$network = sanitize_text_field( $_POST['_univoucher_network'] );
				$product->update_meta_data( '_univoucher_network', $network );
			}

			// Token type.
			if ( isset( $_POST['_univoucher_token_type'] ) ) {
				$token_type = sanitize_text_field( $_POST['_univoucher_token_type'] );
				$product->update_meta_data( '_univoucher_token_type', $token_type );
			}

			// Token address.
			if ( isset( $_POST['_univoucher_token_address'] ) ) {
				$token_address = sanitize_text_field( $_POST['_univoucher_token_address'] );
				$product->update_meta_data( '_univoucher_token_address', $token_address );
			}

			// Card amount.
			if ( isset( $_POST['_univoucher_card_amount'] ) ) {
				$card_amount = sanitize_text_field( $_POST['_univoucher_card_amount'] );
				$product->update_meta_data( '_univoucher_card_amount', $card_amount );
			}

			// Token symbol.
			if ( isset( $_POST['_univoucher_token_symbol'] ) ) {
				$token_symbol = sanitize_text_field( $_POST['_univoucher_token_symbol'] );
				$product->update_meta_data( '_univoucher_token_symbol', $token_symbol );
			}

			// Token decimals.
			if ( isset( $_POST['_univoucher_token_decimals'] ) && '' !== $_POST['_univoucher_token_decimals'] ) {
				$token_decimals = absint( $_POST['_univoucher_token_decimals'] );
				$product->update_meta_data( '_univoucher_token_decimals', $token_decimals );
			}
		}

		// Save meta data.
		$product->save_meta_data();
	}

	/**
	 * Get network options for dropdown.
	 *
	 * @return array Network options.
	 */
	private function get_network_options() {
		$options = array();
		foreach ( self::$supported_networks as $chain_id => $network ) {
			$options[ $chain_id ] = $network['name'];
		}
		return $options;
	}

	/**
	 * Get network data by chain ID.
	 *
	 * @param string $chain_id Chain ID.
	 * @return array|null Network data.
	 */
	public static function get_network_data( $chain_id ) {
		return isset( self::$supported_networks[ $chain_id ] ) ? self::$supported_networks[ $chain_id ] : null;
	}

	/**
	 * Get all supported networks.
	 *
	 * @return array
	 */
	public static function get_supported_networks() {
		return self::$supported_networks;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $post;

		// Only load on product edit pages.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		// Enqueue product admin script.
		wp_enqueue_script(
			'univoucher-wc-product-admin',
			plugins_url( 'admin/js/product-admin.js', UNIVOUCHER_WC_PLUGIN_FILE ),
			array( 'jquery' ),
			UNIVOUCHER_WC_VERSION,
			true
		);

		// Enqueue product admin styles.
		wp_enqueue_style(
			'univoucher-wc-product-admin',
			plugins_url( 'admin/css/product-admin.css', UNIVOUCHER_WC_PLUGIN_FILE ),
			array(),
			UNIVOUCHER_WC_VERSION
		);

		// Check if product has existing cards
		$product_manager = UniVoucher_WC_Product_Manager::instance();
		$has_existing_cards = $product_manager->product_has_existing_cards( $post->ID );
		$cards_count = $product_manager->get_product_cards_count( $post->ID );
		
		// Localize script.
		wp_localize_script(
			'univoucher-wc-product-admin',
			'univoucher_product_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'univoucher_product_nonce' ),
				'networks' => self::$supported_networks,
				'has_existing_cards' => $has_existing_cards,
				'cards_count' => $cards_count,
			)
		);
	}

	/**
	 * AJAX handler to get token information.
	 */
	public function ajax_get_token_info() {
		// Verify nonce.
		check_ajax_referer( 'univoucher_product_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$token_address = sanitize_text_field( $_POST['token_address'] ?? '' );
		$network = sanitize_text_field( $_POST['network'] ?? '1' );

		if ( empty( $token_address ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Token address is required.', 'univoucher-for-woocommerce' ) ) );
		}

		// Validate address format.
		if ( ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $token_address ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid token address format.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get API key.
		$api_key = function_exists( 'univoucher_get_api_key' ) ? univoucher_get_api_key() : '';
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Alchemy API key not configured, please check your UniVoucher settings.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get network data.
		$network_data = self::get_network_data( $network );
		if ( ! $network_data ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unsupported network.', 'univoucher-for-woocommerce' ) ) );
		}

		// Make API call to get token info.
		$token_info = $this->fetch_token_info( $token_address, $network, $api_key );

		if ( is_wp_error( $token_info ) ) {
			wp_send_json_error( array( 'message' => $token_info->get_error_message() ) );
		}

		wp_send_json_success( $token_info );
	}

	/**
	 * Fetch token information from Alchemy API.
	 *
	 * @param string $token_address Token contract address.
	 * @param string $network       Network chain ID.
	 * @param string $api_key       Alchemy API key.
	 * @return array|WP_Error Token info or error.
	 */
	private function fetch_token_info( $token_address, $network, $api_key ) {
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

		$alchemy_network = $network_mapping[ $network ] ?? 'eth-mainnet';
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
				return new WP_Error( 'api_error', esc_html__( 'Failed to connect to blockchain API. Check your internet connection or try again later. Contact support at https://t.me/univoucher if the issue persists.', 'univoucher-for-woocommerce' ) );
			}

			// Check HTTP status code
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( $http_code === 401 ) {
				return new WP_Error( 'auth_error', esc_html__( 'Invalid API key. Please check your Alchemy API key in UniVoucher settings.', 'univoucher-for-woocommerce' ) );
			} elseif ( $http_code === 403 ) {
				return new WP_Error( 'forbidden_error', esc_html__( 'API access denied. Check your Alchemy plan limits or API key permissions.', 'univoucher-for-woocommerce' ) );
			} elseif ( $http_code === 429 ) {
				return new WP_Error( 'rate_limit', esc_html__( 'API rate limit exceeded. Please wait a moment and try again.', 'univoucher-for-woocommerce' ) );
			} elseif ( $http_code >= 500 ) {
				return new WP_Error( 'server_error', esc_html__( 'Blockchain API temporarily unavailable. Please try again in a few moments.', 'univoucher-for-woocommerce' ) );
			} elseif ( $http_code !== 200 ) {
				// translators: %d is the HTTP status code
				return new WP_Error( 'http_error', sprintf( esc_html__( 'API returned error (HTTP %d). Contact support at https://t.me/univoucher if this persists.', 'univoucher-for-woocommerce' ), $http_code ) );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				$error_code = $data['error']['code'] ?? 0;
				$error_message = $data['error']['message'] ?? '';
				
				// Handle specific error scenarios
				switch ( $error_code ) {
					case -32600:
						if ( strpos( $error_message, 'authenticated' ) !== false || strpos( $error_message, 'access key' ) !== false ) {
							return new WP_Error( 'auth_error', esc_html__( 'Invalid API key. Please check your Alchemy API key in UniVoucher settings.', 'univoucher-for-woocommerce' ) );
						}
						return new WP_Error( 'request_error', esc_html__( 'Invalid request format. Please contact support at https://t.me/univoucher', 'univoucher-for-woocommerce' ) );
					case -32601:
						return new WP_Error( 'method_error', esc_html__( 'Unsupported network or method. Please try a different network or contact support at https://t.me/univoucher', 'univoucher-for-woocommerce' ) );
					case -32602:
						return new WP_Error( 'params_error', esc_html__( 'Invalid token address format. Please ensure the address is a valid 0x... format.', 'univoucher-for-woocommerce' ) );
					case 3:
						return new WP_Error( 'contract_error', esc_html__( 'Invalid token contract. The address is not a valid ERC-20 token on this network. Verify the token exists on the selected network.', 'univoucher-for-woocommerce' ) );
					case 429:
						return new WP_Error( 'rate_limit', esc_html__( 'API rate limit exceeded. Please wait a moment and try again.', 'univoucher-for-woocommerce' ) );
					case -32000:
					case -32603:
						if ( strpos( $error_message, 'limit' ) !== false ) {
							return new WP_Error( 'limit_error', esc_html__( 'API capacity limit reached. Please check your Alchemy plan or try again later.', 'univoucher-for-woocommerce' ) );
						}
						return new WP_Error( 'server_error', esc_html__( 'Blockchain network temporarily unavailable. Please try again in a few moments.', 'univoucher-for-woocommerce' ) );
					default:
						return new WP_Error( 'rpc_error', esc_html__( 'Unable to fetch token info. This could be due to invalid token address, network issues, or API problems. Contact support at https://t.me/univoucher if the issue persists.', 'univoucher-for-woocommerce' ) );
				}
			}

			$results[ $call['id'] ] = $data['result'] ?? '';
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
			return new WP_Error( 'invalid_token', esc_html__( 'Invalid token contract. The address does not implement ERC-20 standard or does not exist on this network. Verify the token address is correct for the selected network.', 'univoucher-for-woocommerce' ) );
		}

		return array(
			'name'     => $name,
			'symbol'   => $symbol,
			'decimals' => $decimals,
			'address'  => $token_address,
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
	 * AJAX handler to generate product image.
	 */
	public function ajax_generate_image() {
		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_product_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions to perform this action.', 'univoucher-for-woocommerce' ) ) );
		}

		// Verify GD extension is available
		if ( ! extension_loaded( 'gd' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'GD extension is not available on this server. Please contact your hosting provider.', 'univoucher-for-woocommerce' ) ) );
		}

		// Validate and sanitize product ID
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Valid product ID is required.', 'univoucher-for-woocommerce' ) ) );
		}

		// Generate the image.
		$image_data = $this->generate_gift_card_image( $product_id );
		
		if ( is_wp_error( $image_data ) ) {
			wp_send_json_error( array( 'message' => $image_data->get_error_message() ) );
		}

		// Get product meta for filename generation
		$product = wc_get_product( $product_id );
		$token_symbol = $product->get_meta( '_univoucher_token_symbol' );
		$card_amount = $product->get_meta( '_univoucher_card_amount' );

		// Save image and set as featured image.
		$attachment_id = $this->save_generated_image( $image_data, $product_id, $token_symbol, $card_amount );
		
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		// Get the image URL for preview - WordPress will now have all sizes available
		$image_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
		
		if ( ! $image_url ) {
			// Fallback to full size if medium is not available
			$image_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		}
		
		// Get additional image data for frontend
		$image_sizes = array();
		$available_sizes = array( 'thumbnail', 'medium', 'large', 'full' );
		
		foreach ( $available_sizes as $size ) {
			$size_url = wp_get_attachment_image_url( $attachment_id, $size );
			if ( $size_url ) {
				$image_sizes[ $size ] = $size_url;
			}
		}

		wp_send_json_success( array( 
			'message' => esc_html__( 'Product image generated and set successfully!', 'univoucher-for-woocommerce' ),
			'image_url' => $image_url,
			'attachment_id' => $attachment_id,
			'product_id' => $product_id,
			'image_sizes' => $image_sizes
		) );
	}

	/**
	 * Generate gift card image using GD.
	 *
	 * @param int $product_id Product ID.
	 * @return array|WP_Error Image data or error.
	 */
	private function generate_gift_card_image( $product_id ) {
		// Get product object and meta data
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'product_not_found', esc_html__( 'Product not found.', 'univoucher-for-woocommerce' ) );
		}

		// Get product meta data
		$chain_id = $product->get_meta( '_univoucher_network' );
		$token_symbol = $product->get_meta( '_univoucher_token_symbol' );
		$card_amount = $product->get_meta( '_univoucher_card_amount' );

		if ( empty( $chain_id ) || empty( $token_symbol ) || empty( $card_amount ) ) {
			return new WP_Error( 'incomplete_product', esc_html__( 'Product configuration incomplete. Please ensure all UniVoucher settings are saved.', 'univoucher-for-woocommerce' ) );
		}

		// Get current image template settings.
		$settings = UniVoucher_WC_Image_Templates::get_current_settings();
		
		// Load the template image.
		$template_path = plugin_dir_path( UNIVOUCHER_WC_PLUGIN_FILE ) . 'admin/images/templates/' . $settings['template'];
		
		if ( ! file_exists( $template_path ) ) {
			return new WP_Error( 'template_missing', esc_html__( 'Gift card template image not found.', 'univoucher-for-woocommerce' ) );
		}

		$image = imagecreatefrompng( $template_path );
		if ( ! $image ) {
			return new WP_Error( 'template_load', esc_html__( 'Failed to load gift card template image.', 'univoucher-for-woocommerce' ) );
		}

		// Enable anti-aliasing.
		imageantialias( $image, true );
		
		// Enable alpha blending for proper transparency handling.
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		// Get network name
		$network_name = UniVoucher_WC_Image_Templates::get_network_name_by_chain_id( $chain_id );

		// Use the same drawing methods as test generation for consistency
		$image_templates = UniVoucher_WC_Image_Templates::instance();
		
		// Draw amount with symbol text if enabled
		if ( $settings['show_amount_with_symbol'] ) {
			$color = $image_templates->hex_to_rgb( $settings['amount_with_symbol_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$combined_text = $card_amount . ' ' . $token_symbol;
			$image_templates->draw_text_on_image( 
				$image, 
				$combined_text, 
				$settings['amount_with_symbol_font'], 
				$settings['amount_with_symbol_size'], 
				$text_color, 
				$settings['amount_with_symbol_x'], 
				$settings['amount_with_symbol_y'],
				$settings['amount_with_symbol_align'] ?? 'center'
			);
		}
		
		// Draw amount text if enabled
		if ( $settings['show_amount'] ) {
			$color = $image_templates->hex_to_rgb( $settings['amount_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$image_templates->draw_text_on_image( 
				$image, 
				$card_amount, 
				$settings['amount_font'], 
				$settings['amount_size'], 
				$text_color, 
				$settings['amount_x'], 
				$settings['amount_y'],
				$settings['amount_align'] ?? 'center'
			);
		}

		// Draw token symbol text if enabled
		if ( $settings['show_token_symbol'] ) {
			$color = $image_templates->hex_to_rgb( $settings['token_symbol_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$image_templates->draw_text_on_image( 
				$image, 
				$token_symbol, 
				$settings['token_symbol_font'], 
				$settings['token_symbol_size'], 
				$text_color, 
				$settings['token_symbol_x'], 
				$settings['token_symbol_y'],
				$settings['token_symbol_align'] ?? 'center'
			);
		}

		// Draw network name text if enabled
		if ( $settings['show_network_name'] ) {
			$color = $image_templates->hex_to_rgb( $settings['network_name_color'] );
			$text_color = imagecolorallocate( $image, $color['r'], $color['g'], $color['b'] );
			$image_templates->draw_text_on_image( 
				$image, 
				$network_name, 
				$settings['network_name_font'], 
				$settings['network_name_size'], 
				$text_color, 
				$settings['network_name_x'], 
				$settings['network_name_y'],
				$settings['network_name_align'] ?? 'center'
			);
		}

		// Draw chain logo if enabled
		if ( $settings['show_network_logo'] ) {
			$image_templates->draw_chain_logo_on_image( $image, $chain_id, $settings['logo_height'], $settings['logo_x'], $settings['logo_y'] );
		}

		// Draw token logo if enabled
		if ( $settings['show_token_logo'] ) {
			$image_templates->draw_token_logo_on_image( $image, $token_symbol, $settings['token_height'], $settings['token_x'], $settings['token_y'] );
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
	 * Save generated image and create attachment using WordPress best practices.
	 *
	 * @param string $image_data   Image data.
	 * @param int    $product_id   Product ID.
	 * @param string $token_symbol Token symbol.
	 * @param string $card_amount  Card amount.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function save_generated_image( $image_data, $product_id, $token_symbol, $card_amount ) {
		// Validate inputs
		if ( empty( $image_data ) || empty( $product_id ) || empty( $token_symbol ) || empty( $card_amount ) ) {
			return new WP_Error( 'invalid_input', esc_html__( 'Invalid input parameters for image saving.', 'univoucher-for-woocommerce' ) );
		}

		// Get upload directory using WordPress function
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir_error', esc_html__( 'Unable to access upload directory.', 'univoucher-for-woocommerce' ) );
		}

		// Generate filename with proper sanitization
		$filename = sprintf( 'univoucher-giftcard-%d-%s.png', $product_id, sanitize_title( $token_symbol ) );
		$filename = sanitize_file_name( $filename );

		// Create unique filename to prevent conflicts
		$unique_filename = wp_unique_filename( $upload_dir['path'], $filename );
		$file_path = $upload_dir['path'] . '/' . $unique_filename;

		// Save file using WordPress file system functions for better compatibility
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$file_saved = $wp_filesystem->put_contents( $file_path, $image_data, FS_CHMOD_FILE );
		
		if ( false === $file_saved ) {
			return new WP_Error( 'file_save_error', esc_html__( 'Failed to save image file.', 'univoucher-for-woocommerce' ) );
		}

		// Verify file was created and is readable
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_verification_error', esc_html__( 'Generated image file could not be verified.', 'univoucher-for-woocommerce' ) );
		}

		// Get file URL for attachment
		$file_url = $upload_dir['url'] . '/' . $unique_filename;

		// Prepare attachment data following WordPress standards
		$attachment_data = array(
			'post_title'     => sprintf( 'UniVoucher Gift Card - %s %s', $card_amount, $token_symbol ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
			'guid'           => $file_url,
		);

		// Insert attachment using WordPress function
		$attachment_id = wp_insert_attachment( $attachment_data, $file_path, $product_id );
		
		if ( is_wp_error( $attachment_id ) ) {
			// Clean up file if attachment creation failed
			$wp_filesystem->delete( $file_path );
			return $attachment_id;
		}

		// Generate proper attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		
		// Generate proper WordPress attachment metadata including all image sizes
		// Use WordPress standard metadata generation which creates all registered image sizes
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		
		if ( is_wp_error( $attachment_metadata ) ) {
			// Fallback to basic metadata if generation fails
			$image_size = getimagesize( $file_path );
			if ( $image_size ) {
				$attachment_metadata = array(
					'width'  => $image_size[0],
					'height' => $image_size[1],
					'file'   => wp_basename( $file_path ),
					'sizes'  => array(),
				);
			} else {
				$attachment_metadata = array(
					'file' => wp_basename( $file_path ),
					'sizes' => array(),
				);
			}
		}
		
		// Update attachment metadata
		$metadata_updated = wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Set as featured image for the product
		$featured_set = set_post_thumbnail( $product_id, $attachment_id );

		return $attachment_id;
	}

}