<?php
/**
 * UniVoucher For WooCommerce Add Gift Cards Page
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Add_Cards_Page class.
 */
class UniVoucher_WC_Add_Cards_Page {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Add_Cards_Page
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Add_Cards_Page Instance.
	 *
	 * @return UniVoucher_WC_Add_Cards_Page - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Add_Cards_Page Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_univoucher_get_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_univoucher_get_product_settings', array( $this, 'ajax_get_product_settings' ) );
		add_action( 'wp_ajax_univoucher_validate_single_card', array( $this, 'ajax_validate_single_card' ) );
		add_action( 'wp_ajax_univoucher_add_cards', array( $this, 'ajax_add_cards' ) );
	}

	/**
	 * Render the add gift cards page.
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add UniVoucher Gift Cards', 'univoucher-for-woocommerce' ); ?></h1>

			<div class="univoucher-add-cards">
				
				<!-- Combined Product Selection, Details, and Methods -->
				<div class="univoucher-section" id="main-container">
					<div class="postbox">
						<div class="inside compact">
							<!-- Product Selection -->
							<div id="product-selection-area">
								<div id="product-loading" style="display: none;">
									<p><span class="spinner is-active"></span> <?php esc_html_e( 'Loading product settings...', 'univoucher-for-woocommerce' ); ?></p>
								</div>
								
								<div id="product-selection-form">
									<label for="selected-product" style="font-weight: 600; display: block; margin-bottom: 5px;">
										<?php esc_html_e( 'Select Product:', 'univoucher-for-woocommerce' ); ?>
									</label>
									<p class="description" style="margin-top: 0; margin-bottom: 8px;">
										<?php esc_html_e( 'Choose the product you want to add gift cards to. Only products with UniVoucher enabled will be shown.', 'univoucher-for-woocommerce' ); ?>
									</p>
									<select id="selected-product" name="product_id">
										<option value=""><?php esc_html_e( 'Select a product...', 'univoucher-for-woocommerce' ); ?></option>
									</select>
								</div>
								
								<div id="no-products-message" style="display: none; text-align: center; margin-top: 20px;">
									<div style="padding: 20px; display: inline-block; text-align: center; width: auto;">
										<h3 style="margin-top: 0; color: #d63638;">
											<?php esc_html_e( 'No UniVoucher Products Found', 'univoucher-for-woocommerce' ); ?>
										</h3>
										<p style="margin-bottom: 20px;">
											<?php esc_html_e( 'You need to create a product with UniVoucher gift card enabled first.', 'univoucher-for-woocommerce' ); ?>
										</p>
										<div style="margin: 20px 0;">
											<img src="<?php echo esc_url( plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'admin/images/add_product_guide.png' ); ?>" 
												 alt="<?php esc_attr_e( 'How to enable UniVoucher for products', 'univoucher-for-woocommerce' ); ?>" 
												 style="width: 600px; max-width: 100%; height: auto; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" />
										</div>
										<p style="margin-bottom: 20px; color: #666;">
											<strong><?php esc_html_e( '1.', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Create or edit a product', 'univoucher-for-woocommerce' ); ?> → 
											<strong><?php esc_html_e( '2.', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Go to "UniVoucher" tab', 'univoucher-for-woocommerce' ); ?> → 
											<strong><?php esc_html_e( '3.', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Check "Enable UniVoucher"', 'univoucher-for-woocommerce' ); ?>
										</p>
										<p style="margin-bottom: 0;">
											<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button button-primary">
												<?php esc_html_e( 'Create New Product', 'univoucher-for-woocommerce' ); ?>
											</a>
										</p>
									</div>
								</div>
							</div>

							<!-- Gift Card Details -->
							<div id="product-details" style="display: none; margin-top: 20px;">
								<label style="font-weight: 600; display: block; margin-bottom: 5px;">
									<?php esc_html_e( 'Product Details:', 'univoucher-for-woocommerce' ); ?>
								</label>
								<p class="description" style="margin-top: 0; margin-bottom: 8px;">
									<?php esc_html_e( 'Verify these settings are correct. All gift cards you add must match these exact specifications.', 'univoucher-for-woocommerce' ); ?>
								</p>
								<div id="product-settings-display">
									<!-- Settings will be loaded here via AJAX -->
								</div>
							</div>

							<!-- Method Selection -->
							<div id="method-selection" style="display: none; margin-top: 20px;">
								<h3 style="margin-bottom: 8px; font-size: 14px; color: #1d2327;">
									<?php esc_html_e( 'Creating Method:', 'univoucher-for-woocommerce' ); ?>
								</h3>
								<p class="description" style="margin-top: 0; margin-bottom: 15px;">
									<?php esc_html_e( 'Choose how you want to create and add gift cards to your inventory:', 'univoucher-for-woocommerce' ); ?>
								</p>
								<div class="methods-container-inline">
									<!-- UniVoucher dApp Method -->
									<div class="method-box-inline available" data-method="univoucher">
										<div class="method-icon-inline">
											<span class="dashicons dashicons-admin-site-alt3"></span>
										</div>
										<div class="method-content-inline">
											<h4><?php esc_html_e( 'UniVoucher.com', 'univoucher-for-woocommerce' ); ?></h4>
											<p><?php esc_html_e( 'Use univoucher.com to create gift cards, then copy cards or import from CSV.', 'univoucher-for-woocommerce' ); ?></p>
										</div>
									</div>

									<!-- Create with Internal Wallet Method -->
									<div class="method-box-inline available" data-method="internal-wallet">
										<div class="method-icon-inline">
											<span class="dashicons dashicons-admin-network"></span>
										</div>
										<div class="method-content-inline">
											<h4><?php esc_html_e( 'Internal Wallet (locally)', 'univoucher-for-woocommerce' ); ?></h4>
											<p><?php esc_html_e( 'Create new cards locally using your Internal Wallet private key with javascript that runs in your browser.', 'univoucher-for-woocommerce' ); ?></p>
										</div>
									</div>

									<!-- Create with Wallet Connect Method -->
									<div class="method-box-inline coming-soon" data-method="wallet-connect">
										<div class="method-icon-inline">
											<span class="dashicons dashicons-admin-links"></span>
										</div>
										<div class="method-content-inline">
											<h4><?php esc_html_e( 'Internal Wallet (API)', 'univoucher-for-woocommerce' ); ?> <span class="soon-tag"><?php esc_html_e( 'soon', 'univoucher-for-woocommerce' ); ?></span></h4>
											<p><?php esc_html_e( 'Create new cards remotely using your Internal Wallet private key with the UniVoucher API.', 'univoucher-for-woocommerce' ); ?></p>
										</div>
									</div>

									<!-- On-Demand Feature Call to Action -->
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-settings&tab=backorders' ) ); ?>" target="_blank" class="method-box-inline" data-method="ondemand" style="text-decoration: none; color: inherit; border: 2px solid; border-image: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #feca57, #ff9ff3) 1; box-shadow: 0 0 15px rgba(255, 107, 107, 0.3);">
										<div class="method-icon-inline">
											<span class="dashicons dashicons-star-filled"></span>
										</div>
										<div class="method-content-inline">
											<h4><?php esc_html_e( 'On-Demand Mode', 'univoucher-for-woocommerce' ); ?></h4>
											<p><?php esc_html_e( 'Automatically create cards after customers place orders using your Internal Wallet private key with the UniVoucher API.', 'univoucher-for-woocommerce' ); ?></p>
										</div>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Selected Method Elements -->
				<div class="univoucher-section" id="method-elements" style="display: none;">
					<!-- Cards to Add Form -->
					<div class="method-form" id="cards-form">
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle"><?php esc_html_e( 'Cards to Add', 'univoucher-for-woocommerce' ); ?></h2>
						</div>
						<div class="inside">
							<div id="cards-entry-instructions">
								<p><?php esc_html_e( 'Enter the Card ID and Card Secret for each gift card manually, or use the Upload CSV button to bulk import from UniVoucher.com file. Then validate them to ensure they are correct before adding to inventory.', 'univoucher-for-woocommerce' ); ?></p>
							</div>
							
							<!-- Action Controls Area -->
							<div class="cards-action-controls">
								<div class="action-controls-left">
									<div id="add-cards-disabled-message" class="disabled-message">
										<span class="dashicons dashicons-warning"></span>
										<?php esc_html_e( 'Please enter and validate cards before adding to inventory', 'univoucher-for-woocommerce' ); ?>
									</div>
								</div>
							</div>
								
							<div style="margin-bottom: 10px;">
								<input type="file" id="csv-file" accept=".csv" style="display: none;" />
								<button type="button" class="button button-primary csv-upload-btn" id="upload-csv-btn">
									<span class="dashicons dashicons-media-spreadsheet"></span>
									<?php esc_html_e( 'Upload CSV', 'univoucher-for-woocommerce' ); ?>
								</button>
								<button type="button" class="button button-secondary" id="validate-all-cards-btn">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e( 'Validate All Cards', 'univoucher-for-woocommerce' ); ?>
								</button>
								<button type="button" class="button button-primary" id="add-cards-btn" disabled>
									<span class="dashicons dashicons-plus-alt"></span>
									<?php esc_html_e( 'Add Cards to Inventory', 'univoucher-for-woocommerce' ); ?>
								</button>
							</div>

								<form id="gift-cards-form">
									<div class="table-responsive">
									<table class="widefat" id="gift-cards-table">
										<thead>
											<tr>
												<th style="width: 10px;"><?php esc_html_e( 'Row', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 140px;"><?php esc_html_e( 'Card ID', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 200px;"><?php esc_html_e( 'Card Secret', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'New', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'Active', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'Network', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'Amount', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'Token', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 50px;"><?php esc_html_e( 'Secret', 'univoucher-for-woocommerce' ); ?></th>
												<th style="width: 160px;"><?php esc_html_e( 'Actions', 'univoucher-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody id="gift-cards-tbody">
											<tr class="gift-card-row">
												<td class="card-number">1</td>
												<td>
													<input type="number" name="card_id[]" placeholder="<?php esc_attr_e( 'e.g., 102123456', 'univoucher-for-woocommerce' ); ?>" class="regular-text card-id-input" autocomplete="off" />
													<div class="validation-error card-id-error" style="display: none;"></div>
												</td>
												<td>
													<input type="text" name="card_secret[]" placeholder="<?php esc_attr_e( 'XXXXX-XXXXX-XXXXX-XXXXX', 'univoucher-for-woocommerce' ); ?>" class="regular-text card-secret-input" maxlength="23" autocomplete="off" />
													<div class="validation-error card-secret-error" style="display: none;"></div>
												</td>
												<td class="validation-col" data-validation="new">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td class="validation-col" data-validation="active">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td class="validation-col" data-validation="network">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td class="validation-col" data-validation="amount">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td class="validation-col" data-validation="token">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td class="validation-col" data-validation="secret">
													<span class="validation-icon dashicons dashicons-minus"></span>
												</td>
												<td>
													<button type="button" class="button button-secondary validate-card-btn" style="margin-right: 5px;">
														<?php esc_html_e( 'Validate', 'univoucher-for-woocommerce' ); ?>
													</button>
													<button type="button" class="button remove-card-btn" disabled>
														<?php esc_html_e( 'Remove', 'univoucher-for-woocommerce' ); ?>
													</button>
												</td>
											</tr>
										</tbody>
									</table>
									</div>

									<p>
										<button type="button" class="button" id="add-card-row-btn">
											<?php esc_html_e( '+ Add Another Card', 'univoucher-for-woocommerce' ); ?>
										</button>
									</p>
									
									<div id="validation-requirements" style="display: none; margin-top: 20px;">
										<p><strong><?php esc_html_e( 'All cards must be:', 'univoucher-for-woocommerce' ); ?></strong></p>
										<ul class="validation-requirements-list">
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'New:', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( "Don't exist in the current inventory", 'univoucher-for-woocommerce' ); ?></li>
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'Active:', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Not redeemed or cancelled', 'univoucher-for-woocommerce' ); ?></li>
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'Network:', 'univoucher-for-woocommerce' ); ?></strong> <span id="requirement-network"></span></li>
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'Amount:', 'univoucher-for-woocommerce' ); ?></strong> <span id="requirement-amount"></span></li>
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'Token:', 'univoucher-for-woocommerce' ); ?></strong> <span id="requirement-token"></span></li>
											<li><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong><?php esc_html_e( 'Secret:', 'univoucher-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Is the correct secret for this card ID and can redeem it', 'univoucher-for-woocommerce' ); ?></li>
										</ul>
									</div>
																		
								</form>
							</div>
						</div>
					</div>

					<!-- Internal Wallet Form -->
					<div class="method-form" id="internal-wallet-form" style="display: none;">
						<!-- Step 1: Quantity and Cost -->
						<div class="postbox" id="internal-wallet-step1">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Step 1: Configure Cards Creation', 'univoucher-for-woocommerce' ); ?></h2>
							</div>
							<div class="inside">
								<!-- Wallet Info -->
								<div class="wallet-info-section" style="margin-bottom: 20px;">
									<h4><?php esc_html_e( 'Internal Wallet Information:', 'univoucher-for-woocommerce' ); ?></h4>
									<div class="wallet-info-grid" style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin-bottom: 15px;">
										<div>
											<strong><?php esc_html_e( 'Wallet Address:', 'univoucher-for-woocommerce' ); ?></strong>
											<div id="wallet-address-display" style="font-family: monospace; font-size: 13px; color: #666; margin-top: 3px;"><?php esc_html_e( 'Loading...', 'univoucher-for-woocommerce' ); ?></div>
										</div>
										<div>
											<strong><?php esc_html_e( 'Balances (showing only what we need):', 'univoucher-for-woocommerce' ); ?></strong>
											<div id="wallet-balances" style="margin-top: 3px;">
												<div id="balance-loading" style="color: #666; font-size: 13px;"><?php esc_html_e( 'Loading balances...', 'univoucher-for-woocommerce' ); ?></div>
											</div>
										</div>
									</div>
								</div>

								<!-- Quantity Selection -->
								<div class="quantity-section" style="margin-bottom: 20px;">
									<label for="card-quantity" style="font-weight: 600; display: block; margin-bottom: 5px;">
										<?php esc_html_e( 'Number of Cards to Create:', 'univoucher-for-woocommerce' ); ?>
									</label>
									<input type="number" id="card-quantity" min="1" max="100" value="1" style="width: 100px;" />
									<p class="description"><?php esc_html_e( 'Choose how many cards to create (1-100)', 'univoucher-for-woocommerce' ); ?></p>
								</div>

								<!-- Cost Summary -->
								<div class="cost-summary" style="margin-bottom: 20px;">
									<h4><?php esc_html_e( 'Cost Summary:', 'univoucher-for-woocommerce' ); ?></h4>
									<table class="widefat" style="max-width: 500px;">
										<tbody>
											<tr>
												<td><?php esc_html_e( 'Card Amount:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="cost-card-amount">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'UniVoucher Fee:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="cost-univoucher-fee">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'Quantity:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="cost-quantity">1</td>
											</tr>
											<tr style="font-weight: bold; border-top: 1px solid #ddd;">
												<td><?php esc_html_e( 'Total Balance Needed:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="cost-total-needed">-</td>
											</tr>
										</tbody>
									</table>
								</div>

								<!-- Token Allowance Section -->
									<h4><?php esc_html_e( 'Token Allowance:', 'univoucher-for-woocommerce' ); ?></h4>
									<div id="allowance-status">
										<div id="allowance-loading" style="display: none;">
											<span class="spinner is-active"></span> <?php esc_html_e( 'Checking allowance...', 'univoucher-for-woocommerce' ); ?>
										</div>
										<div id="allowance-insufficient" style="display: none;">
											<p style="color: #d63638;"><?php esc_html_e( 'Insufficient token allowance. You need to approve the UniVoucher contract to spend your tokens.', 'univoucher-for-woocommerce' ); ?></p>
											<div style="margin-top: 10px;">
												<button type="button" class="button button-primary" id="approve-tokens-btn" style="margin-right: 10px;">
													<?php esc_html_e( 'Approve Token Allowance', 'univoucher-for-woocommerce' ); ?>
												</button>
												<button type="button" class="button button-secondary" id="approve-unlimited-tokens-btn">
													<?php esc_html_e( 'Approve Unlimited Token Allowance', 'univoucher-for-woocommerce' ); ?>
												</button>
											</div>
										</div>
										<div id="allowance-sufficient" style="display: none;">
											<p style="color: #46b450;"><?php esc_html_e( '✅ Token allowance is sufficient.', 'univoucher-for-woocommerce' ); ?></p>
										</div>
									</div>

								<!-- Action Buttons -->
								<div class="step1-actions">
									<button type="button" class="button button-primary" id="prepare-cards-btn" disabled>
										<?php esc_html_e( 'Prepare Cards', 'univoucher-for-woocommerce' ); ?>
									</button>
									<div id="step1-error" style="display: none; margin-top: 10px; color: #d63638;"></div>
								</div>
							</div>
						</div>

						<!-- Step 2: Transaction Summary -->
						<div class="postbox" id="internal-wallet-step2" style="display: none;">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Step 2: Transaction Summary', 'univoucher-for-woocommerce' ); ?></h2>
							</div>
							<div class="inside">
								<div id="gas-estimation-loading" style="margin-bottom: 20px;">
									<span class="spinner is-active"></span> <?php esc_html_e( 'Estimating gas costs...', 'univoucher-for-woocommerce' ); ?>
								</div>

								<div id="transaction-summary" style="display: none;">
									<h4><?php esc_html_e( 'Transaction Cost Summary:', 'univoucher-for-woocommerce' ); ?></h4>
									<table class="widefat" style="max-width: 500px; margin-bottom: 20px;">
										<tbody>
											<tr>
												<td><?php esc_html_e( 'Card Amount:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-cost-card-amount">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'UniVoucher Fee:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-cost-univoucher-fee">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'Quantity:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-cost-quantity">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'Gas Required:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-gas-required">-</td>
											</tr>
											<tr>
												<td><?php esc_html_e( 'Gas Cost:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-gas-cost">-</td>
											</tr>
											<tr style="font-weight: bold; border-top: 1px solid #ddd;">
												<td><?php esc_html_e( 'Total Transaction Cost:', 'univoucher-for-woocommerce' ); ?></td>
												<td id="tx-total-cost">-</td>
											</tr>
										</tbody>
									</table>

									<div class="step2-actions">
										<button type="button" class="button button-secondary" id="back-to-step1-btn">
											<?php esc_html_e( '← Back to Step 1', 'univoucher-for-woocommerce' ); ?>
										</button>
										<button type="button" class="button button-primary" id="create-cards-btn">
											<?php esc_html_e( 'Create Cards & Add to Inventory', 'univoucher-for-woocommerce' ); ?>
										</button>
									</div>
								</div>

								<div id="step2-error" style="display: none; margin-top: 10px; color: #d63638;"></div>
							</div>
						</div>

						<!-- Step 3: Success -->
						<div class="postbox" id="internal-wallet-step3" style="display: none;">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Step 3: Cards Created Successfully', 'univoucher-for-woocommerce' ); ?></h2>
							</div>
							<div class="inside">
								<div id="creation-success" style="margin-bottom: 20px;">
									<p style="color: #46b450; font-size: 16px; font-weight: bold;">
										✅ <span id="success-message"><?php esc_html_e( 'Cards created successfully!', 'univoucher-for-woocommerce' ); ?></span>
									</p>
								</div>

								<div id="created-cards-info">
									<h4><?php esc_html_e( 'Created Cards:', 'univoucher-for-woocommerce' ); ?></h4>
									<div id="created-cards-list" style="margin-bottom: 20px;">
										<!-- Will be populated with created card details -->
									</div>
								</div>

								<div id="updated-stock-info">
									<h4><?php esc_html_e( 'Updated Inventory:', 'univoucher-for-woocommerce' ); ?></h4>
									<p><?php esc_html_e( 'New Stock Quantity:', 'univoucher-for-woocommerce' ); ?> <strong id="new-stock-quantity">-</strong></p>
								</div>

								<div id="transaction-info" style="margin-bottom: 20px;">
									<h4><?php esc_html_e( 'Transaction Details:', 'univoucher-for-woocommerce' ); ?></h4>
									<p><?php esc_html_e( 'Transaction Hash:', 'univoucher-for-woocommerce' ); ?> <a id="transaction-link" href="#" target="_blank" style="font-family: monospace;">-</a></p>
								</div>

								<div class="step3-actions">
									<button type="button" class="button button-primary" id="create-more-cards-btn">
										<?php esc_html_e( 'Create More Cards', 'univoucher-for-woocommerce' ); ?>
									</button>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=univoucher-inventory' ) ); ?>" class="button button-secondary">
										<?php esc_html_e( 'View Inventory', 'univoucher-for-woocommerce' ); ?>
									</a>
								</div>
							</div>
						</div>
					</div>


				</div>
			</div>
		</div>

		<!-- Hidden fields for form data -->
		<input type="hidden" id="selected-product-id" value="" />
		<input type="hidden" id="product-chain-id" value="" />
		<input type="hidden" id="product-token-address" value="" />
		<input type="hidden" id="product-token-type" value="" />
		<input type="hidden" id="product-token-symbol" value="" />
		<input type="hidden" id="product-token-decimals" value="" />
		<input type="hidden" id="product-amount" value="" />
		<input type="hidden" id="get-products-nonce" value="<?php echo esc_attr( wp_create_nonce( 'univoucher_get_products' ) ); ?>" />
		<input type="hidden" id="get-settings-nonce" value="<?php echo esc_attr( wp_create_nonce( 'univoucher_get_product_settings' ) ); ?>" />
		<input type="hidden" id="verification-nonce" value="<?php echo esc_attr( wp_create_nonce( 'univoucher_verify_cards' ) ); ?>" />
		<input type="hidden" id="add-cards-nonce" value="<?php echo esc_attr( wp_create_nonce( 'univoucher_add_cards' ) ); ?>" />
		<input type="hidden" id="csv-upload-nonce" value="<?php echo esc_attr( wp_create_nonce( 'univoucher_csv_upload' ) ); ?>" />
		
		<!-- Internal Wallet Hidden Fields -->
		<input type="hidden" id="alchemy-api-key" value="<?php echo esc_attr( get_option( 'univoucher_wc_alchemy_api_key', '' ) ); ?>" />
		<input type="hidden" id="current-method" value="" />
		<input type="hidden" id="internal-wallet-step" value="1" />
		<?php
	}

	/**
	 * AJAX handler to get products with UniVoucher enabled.
	 */
	public function ajax_get_products() {
		// Verify nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'univoucher_get_products' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		// Get products with UniVoucher enabled.
		$args = array(
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
		);

		$products = get_posts( $args );
		$product_options = array();

		foreach ( $products as $product ) {
			$product_options[] = array(
				'id'   => $product->ID,
				'name' => $product->post_title,
			);
		}

		wp_send_json_success( $product_options );
	}

	/**
	 * AJAX handler to get product gift card settings.
	 */
	public function ajax_get_product_settings() {
		// Verify nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'univoucher_get_product_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$product_id = absint( $_GET['product_id'] );
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid product ID.', 'univoucher-for-woocommerce' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Product not found.', 'univoucher-for-woocommerce' ) ) );
		}

		// Get product meta data.
		$settings = array(
			'product_id'      => $product_id,
			'product_name'    => $product->get_name(),
			'chain_id'        => $product->get_meta( '_univoucher_network' ),
			'token_type'      => $product->get_meta( '_univoucher_token_type' ),
			'token_address'   => $product->get_meta( '_univoucher_token_address' ),
			'token_symbol'    => $product->get_meta( '_univoucher_token_symbol' ),
			'amount'          => $product->get_meta( '_univoucher_card_amount' ),
		);
		
		// Get stock information
		$stock_quantity = $product->get_stock_quantity();
		$stock_status = $product->get_stock_status();
		$settings['stock_quantity'] = $stock_quantity;
		$settings['stock_status'] = $stock_status;

		// Get network info.
		$networks = UniVoucher_WC_Product_Fields::get_supported_networks();
		$network = isset( $networks[ $settings['chain_id'] ] ) ? $networks[ $settings['chain_id'] ] : null;

		if ( $network ) {
			$settings['network_name'] = $network['name'];
		}

		// Get token decimals from saved product meta.
		$saved_decimals = $product->get_meta( '_univoucher_token_decimals' );
		if ( $saved_decimals ) {
			$settings['token_decimals'] = absint( $saved_decimals );
		} else {
			// If no decimals saved, this indicates incomplete product configuration
			wp_send_json_error( array( 
				'message' => esc_html__( 'Product configuration incomplete. Please edit the product and ensure token decimals are set correctly.', 'univoucher-for-woocommerce' ) 
			) );
			return;
		}

		wp_send_json_success( $settings );
	}

	/**
	 * AJAX handler to validate a single gift card.
	 */
	public function ajax_validate_single_card() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$card_id = sanitize_text_field( $_POST['card_id'] );
		$card_secret = sanitize_text_field( $_POST['card_secret'] );
		$product_id = absint( $_POST['product_id'] );
		$expected_chain_id = absint( $_POST['chain_id'] );
		$expected_token_address = sanitize_text_field( $_POST['token_address'] );
		$expected_amount = sanitize_text_field( $_POST['amount'] );
		$expected_token_decimals = absint( $_POST['token_decimals'] );
		$edit_mode = isset( $_POST['edit_mode'] ) && $_POST['edit_mode'];
		$current_card_id = isset( $_POST['current_card_id'] ) ? absint( $_POST['current_card_id'] ) : 0;

		if ( empty( $card_id ) || empty( $card_secret ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Card ID and Secret are required.', 'univoucher-for-woocommerce' ) ) );
		}

		$validation_result = $this->validate_card_comprehensive( $card_id, $card_secret, $product_id, $expected_chain_id, $expected_token_address, $expected_amount, $expected_token_decimals, $edit_mode, $current_card_id );

		wp_send_json_success( $validation_result );
	}

	/**
	 * AJAX handler to verify gift cards via UniVoucher API.
	 */
	public function ajax_verify_cards() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$card_ids = isset( $_POST['card_ids'] ) ? array_map( 'sanitize_text_field', $_POST['card_ids'] ) : array();
		$card_secrets = isset( $_POST['card_secrets'] ) ? array_map( 'sanitize_text_field', $_POST['card_secrets'] ) : array();
		$expected_chain_id = absint( $_POST['chain_id'] );
		$expected_token_address = sanitize_text_field( $_POST['token_address'] );
		$expected_amount = sanitize_text_field( $_POST['amount'] );
		$expected_token_decimals = absint( $_POST['token_decimals'] );

		if ( empty( $card_ids ) || count( $card_ids ) !== count( $card_secrets ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid card data provided.', 'univoucher-for-woocommerce' ) ) );
		}

		$verification_results = array();
		$all_valid = true;

		foreach ( $card_ids as $index => $card_id ) {
			$card_secret = $card_secrets[ $index ];
			
			if ( empty( $card_id ) || empty( $card_secret ) ) {
				continue;
			}

			$result = $this->verify_single_card( $card_id, $card_secret, $expected_chain_id, $expected_token_address, $expected_amount, $expected_token_decimals );
			$verification_results[] = $result;
			
			if ( ! $result['valid'] ) {
				$all_valid = false;
			}
		}

		wp_send_json_success( array(
			'results' => $verification_results,
			'all_valid' => $all_valid,
		) );
	}

	/**
	 * Verify a single card via UniVoucher API.
	 *
	 * @param string $card_id Card ID.
	 * @param string $card_secret Card secret.
	 * @param int    $expected_chain_id Expected chain ID.
	 * @param string $expected_token_address Expected token address.
	 * @param string $expected_amount Expected amount.
	 * @param int    $expected_token_decimals Expected token decimals.
	 * @return array Verification result.
	 */
	private function verify_single_card( $card_id, $card_secret, $expected_chain_id, $expected_token_address, $expected_amount, $expected_token_decimals ) {
		$result = array(
			'card_id' => $card_id,
			'valid' => false,
			'errors' => array(),
		);

		// Make API call to UniVoucher.
		$api_url = 'https://api.univoucher.com/v1/cards/single?id=' . urlencode( $card_id );
		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			$result['errors'][] = esc_html__( 'Failed to connect to UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$card_data = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$result['errors'][] = esc_html__( 'Card not found in UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		if ( ! $card_data || ! isset( $card_data['chainId'] ) ) {
			$result['errors'][] = esc_html__( 'Invalid response from UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		// Verify chain ID.
		if ( $card_data['chainId'] != $expected_chain_id ) {
			$result['errors'][] = sprintf( 
				// translators: %1$d is the expected chain ID, %2$d is the found chain ID
				esc_html__( 'Chain ID mismatch. Expected: %1$d, Found: %2$d', 'univoucher-for-woocommerce' ),
				$expected_chain_id,
				$card_data['chainId']
			);
		}

		// Verify token address.
		$card_token_address = strtolower( $card_data['tokenAddress'] );
		$expected_token_address_lower = strtolower( $expected_token_address );
		
		if ( $card_token_address !== $expected_token_address_lower ) {
			$result['errors'][] = sprintf(
				// translators: %1$s is the expected token address, %2$s is the found token address
				esc_html__( 'Token address mismatch. Expected: %1$s, Found: %2$s', 'univoucher-for-woocommerce' ),
				$expected_token_address,
				$card_data['tokenAddress']
			);
		}

		// Verify amount (convert from wei to decimal).
		$card_amount_wei = $card_data['tokenAmount'];
		$card_amount_decimal = $this->wei_to_decimal( $card_amount_wei, $expected_token_decimals );
		
		if ( $card_amount_decimal !== $expected_amount ) {
			$result['errors'][] = sprintf(
				// translators: %1$s is the expected amount, %2$s is the found amount
				esc_html__( 'Amount mismatch. Expected: %1$s, Found: %2$s', 'univoucher-for-woocommerce' ),
				$expected_amount,
				$card_amount_decimal
			);
		}

		// Verify card is active.
		if ( ! $card_data['active'] || $card_data['status'] !== 'active' ) {
			$result['errors'][] = esc_html__( 'Card is not active.', 'univoucher-for-woocommerce' );
		}

		// If no errors, card is valid.
		if ( empty( $result['errors'] ) ) {
			$result['valid'] = true;
		}

		return $result;
	}

	/**
	 * Comprehensive validation for a single card.
	 *
	 * @param string $card_id Card ID.
	 * @param string $card_secret Card secret.
	 * @param int    $product_id Product ID.
	 * @param int    $expected_chain_id Expected chain ID.
	 * @param string $expected_token_address Expected token address.
	 * @param string $expected_amount Expected amount.
	 * @param int    $expected_token_decimals Expected token decimals.
	 * @param bool   $edit_mode Whether this is for editing (allows same card ID).
	 * @param int    $current_card_id Current card PK ID when editing.
	 * @return array Validation result.
	 */
	private function validate_card_comprehensive( $card_id, $card_secret, $product_id, $expected_chain_id, $expected_token_address, $expected_amount, $expected_token_decimals, $edit_mode = false, $current_card_id = 0 ) {
		$result = array(
			'card_id' => $card_id,
			'validations' => array(
				'new' => false,
				'active' => false,
				'network' => false,
				'amount' => false,
				'token' => false,
				'secret' => false,
			),
			'errors' => array(),
			'all_valid' => false,
		);

		// Check if card exists in inventory
		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$existing_card = $gift_card_manager->uv_get_gift_card_by_card_id( $card_id );
		
		if ( $existing_card && ( ! $edit_mode || $existing_card->id != $current_card_id ) ) {
			$result['validations']['new'] = false;
			$result['errors'][] = esc_html__( 'Card already exists in inventory.', 'univoucher-for-woocommerce' );
		} else {
			$result['validations']['new'] = true;
		}

		// Make API call to UniVoucher.
		$api_url = 'https://api.univoucher.com/v1/cards/single?id=' . urlencode( $card_id );
		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			$result['errors'][] = esc_html__( 'Failed to connect to UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$card_data = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$result['errors'][] = esc_html__( 'Card not found in UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		if ( ! $card_data || ! isset( $card_data['chainId'] ) ) {
			$result['errors'][] = esc_html__( 'Invalid response from UniVoucher API.', 'univoucher-for-woocommerce' );
			return $result;
		}

		// Validate active status
		if ( $card_data['active'] && $card_data['status'] === 'active' ) {
			$result['validations']['active'] = true;
		} else {
			$result['validations']['active'] = false;
			$result['errors'][] = esc_html__( 'Card is not active.', 'univoucher-for-woocommerce' );
		}

		// Validate network
		if ( $card_data['chainId'] == $expected_chain_id ) {
			$result['validations']['network'] = true;
		} else {
			$result['validations']['network'] = false;
			$result['errors'][] = sprintf( 
				// translators: %1$d is the expected network/chain ID, %2$d is the found network/chain ID
				esc_html__( 'Network mismatch. Expected: %1$d, Found: %2$d', 'univoucher-for-woocommerce' ),
				$expected_chain_id,
				$card_data['chainId']
			);
		}

		// Validate token address
		$card_token_address = strtolower( $card_data['tokenAddress'] );
		$expected_token_address_lower = strtolower( $expected_token_address );
		
		if ( $card_token_address === $expected_token_address_lower ) {
			$result['validations']['token'] = true;
		} else {
			$result['validations']['token'] = false;
			$result['errors'][] = sprintf(
				// translators: %1$s is the expected token address, %2$s is the found token address
				esc_html__( 'Token address mismatch. Expected: %1$s, Found: %2$s', 'univoucher-for-woocommerce' ),
				$expected_token_address,
				$card_data['tokenAddress']
			);
		}

		// Validate amount
		$card_amount_wei = $card_data['tokenAmount'];
		$card_amount_decimal = $this->wei_to_decimal( $card_amount_wei, $expected_token_decimals );
		
		// Compare amounts as strings to handle decimal precision correctly
		if ( rtrim( rtrim( $card_amount_decimal, '0' ), '.' ) === rtrim( rtrim( $expected_amount, '0' ), '.' ) ) {
			$result['validations']['amount'] = true;
		} else {
			$result['validations']['amount'] = false;
			$result['errors'][] = sprintf(
				// translators: %1$s is the expected amount, %2$s is the found amount
				esc_html__( 'Amount mismatch. Expected: %1$s, Found: %2$s', 'univoucher-for-woocommerce' ),
				$expected_amount,
				$card_amount_decimal
			);
		}

		// Validate card secret by attempting decryption
		if ( isset( $card_data['encryptedPrivateKey'] ) && ! empty( $card_data['encryptedPrivateKey'] ) ) {
			if ( $this->validate_card_secret( $card_data['encryptedPrivateKey'], $card_secret ) ) {
				$result['validations']['secret'] = true;
			} else {
				$result['validations']['secret'] = false;
				$result['errors'][] = esc_html__( 'Invalid card secret - cannot decrypt private key.', 'univoucher-for-woocommerce' );
			}
		} else {
			$result['validations']['secret'] = false;
			$result['errors'][] = esc_html__( 'Card does not have encrypted private key data.', 'univoucher-for-woocommerce' );
		}

		// Check if all validations passed
		$result['all_valid'] = $result['validations']['new'] && 
								$result['validations']['active'] && 
								$result['validations']['network'] && 
								$result['validations']['amount'] && 
								$result['validations']['token'] &&
								$result['validations']['secret'];

		// Store API data for updating card info display
		$result['api_data'] = $card_data;
		$result['formatted_amount'] = $card_amount_decimal;

		return $result;
	}

	/**
	 * Validate card secret by attempting to decrypt the private key.
	 *
	 * @param string $encrypted_data JSON string containing encrypted private key data.
	 * @param string $card_secret Card secret to validate.
	 * @return bool True if secret can decrypt the private key, false otherwise.
	 */
	private function validate_card_secret( $encrypted_data, $card_secret ) {
		try {
			// Parse encrypted data
			$data = json_decode( $encrypted_data, true );
			if ( ! $data || ! isset( $data['salt'], $data['iv'], $data['ciphertext'] ) ) {
				return false;
			}

			// Normalize secret (remove hyphens)
			$normalized_secret = str_replace( '-', '', $card_secret );

			// Convert hex strings to binary
			$salt = hex2bin( $data['salt'] );
			$iv = hex2bin( $data['iv'] );
			$ciphertext = base64_decode( $data['ciphertext'] );

			if ( ! $salt || ! $iv || ! $ciphertext ) {
				return false;
			}

			// Derive key using PBKDF2 (same parameters as UniVoucher)
			$key = hash_pbkdf2( 'sha256', $normalized_secret, $salt, 310000, 32, true );

			// Handle AES-GCM decryption
			$auth_tag_length = 16;
			if ( strlen( $ciphertext ) < $auth_tag_length ) {
				return false;
			}

			$auth_tag = substr( $ciphertext, -$auth_tag_length );
			$encrypted_content = substr( $ciphertext, 0, -$auth_tag_length );

			// Decrypt using OpenSSL
			$decrypted = openssl_decrypt( $encrypted_content, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $auth_tag );

			if ( $decrypted === false ) {
				return false;
			}

			// Validate that decrypted data looks like a private key (64 hex chars)
			$private_key = ltrim( $decrypted, '0x' );
			return strlen( $private_key ) === 64 && ctype_xdigit( $private_key );

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Convert wei amount to decimal.
	 *
	 * @param string $wei_amount Amount in wei.
	 * @param int    $decimals Token decimals.
	 * @return string Decimal amount.
	 */
	private function wei_to_decimal( $wei_amount, $decimals ) {
		// Use bcmath for precision.
		if ( function_exists( 'bcdiv' ) ) {
			$divisor = bcpow( '10', $decimals );
			return rtrim( rtrim( bcdiv( $wei_amount, $divisor, $decimals ), '0' ), '.' );
		}

		// Fallback to regular division.
		$divisor = pow( 10, $decimals );
		return number_format( $wei_amount / $divisor, $decimals, '.', '' );
	}

	/**
	 * AJAX handler to add cards to inventory.
	 */
	public function ajax_add_cards() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_verify_cards' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) );
		}

		$product_id = absint( $_POST['product_id'] );
		$card_ids = isset( $_POST['card_ids'] ) ? array_map( 'sanitize_text_field', $_POST['card_ids'] ) : array();
		$card_secrets = isset( $_POST['card_secrets'] ) ? array_map( 'sanitize_text_field', $_POST['card_secrets'] ) : array();
		$creation_dates = isset( $_POST['creation_dates'] ) ? array_map( 'sanitize_text_field', $_POST['creation_dates'] ) : array();
		$chain_id = absint( $_POST['chain_id'] );
		$token_address = sanitize_text_field( $_POST['token_address'] );
		$token_symbol = sanitize_text_field( $_POST['token_symbol'] );
		$token_type = sanitize_text_field( $_POST['token_type'] );
		$token_decimals = absint( $_POST['token_decimals'] );
		$amount = sanitize_text_field( $_POST['amount'] );

		if ( empty( $card_ids ) || count( $card_ids ) !== count( $card_secrets ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid card data provided.', 'univoucher-for-woocommerce' ) ) );
		}

		$gift_card_manager = UniVoucher_WC_Gift_Card_Manager::instance();
		$added_cards = array();
		$errors = array();

		foreach ( $card_ids as $index => $card_id ) {
			$card_secret = $card_secrets[ $index ];
			$creation_date = isset( $creation_dates[ $index ] ) ? $creation_dates[ $index ] : null;
			
			if ( empty( $card_id ) || empty( $card_secret ) ) {
				continue;
			}

			$data = array(
				'product_id'     => $product_id,
				'order_id'       => null,
				'status'         => 'available',
				'card_id'        => $card_id,
				'card_secret'    => $card_secret,
				'chain_id'       => $chain_id,
				'token_address'  => $token_address,
				'token_symbol'   => $token_symbol,
				'token_type'     => $token_type,
				'token_decimals' => $token_decimals,
				'amount'         => $amount,
			);

			// Add creation date if available
			if ( $creation_date ) {
				$data['created_at'] = $creation_date;
			}

			$result = $gift_card_manager->uv_add_gift_card( $data );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( 
					// translators: %1$s is the card ID, %2$s is the error message
					esc_html__( 'Card %1$s: %2$s', 'univoucher-for-woocommerce' ),
					$card_id,
					$result->get_error_message()
				);
			} else {
				$added_cards[] = $card_id;
			}
		}

		$success_count = count( $added_cards );
		$error_count = count( $errors );

		$message = sprintf(
			// translators: %d is the number of cards added successfully
			esc_html__( '%d cards added successfully.', 'univoucher-for-woocommerce' ),
			$success_count
		);

		if ( $error_count > 0 ) {
			$message .= ' ' . sprintf(
				// translators: %d is the number of errors
				esc_html__( '%d errors occurred.', 'univoucher-for-woocommerce' ),
				$error_count
			);
		}

		wp_send_json_success( array(
			'message' => $message,
			'added_cards' => $added_cards,
			'errors' => $errors,
			'success_count' => $success_count,
			'error_count' => $error_count,
		) );
	}
}