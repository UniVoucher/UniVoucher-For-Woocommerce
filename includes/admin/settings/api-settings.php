<?php
/**
 * UniVoucher API Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_api_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Configure your API keys and settings for blockchain interactions.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Alchemy API key field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_alchemy_api_key_callback( $args ) {
	$api_key = get_option( 'univoucher_wc_alchemy_api_key', '' );
	?>
	
	<!-- Alchemy API explanation -->
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Why UniVoucher Needs Alchemy API:', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'UniVoucher uses Alchemy to fetch ERC20 token details such as token name, symbol, decimals, and other metadata when configuring products.', 'univoucher-for-woocommerce' ); ?>
			</p>
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Alchemy provides reliable, fast blockchain RPC (Remote Procedure Call) endpoints that allow UniVoucher to query token information from multiple blockchain networks.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-success">
			<strong style="color: #27ae60;">
				<span class="dashicons dashicons-yes-alt" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Free Tier Available:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<span style="font-size: 13px; color: #155724;">
				<?php esc_html_e( 'Alchemy offers a generous free tier that is sufficient for most stores. No credit card required for signup.', 'univoucher-for-woocommerce' ); ?>
			</span>
		</div>

		<div class="univoucher-settings-box-info">
			<strong style="color: #0c5460;">
				<span class="dashicons dashicons-info" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Step-by-Step Setup:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<ol style="margin: 8px 0 0 20px; font-size: 13px; color: #0c5460;">
				<li>
					<?php
					printf(
						/* translators: %1$s: opening link tag, %2$s: closing link tag */
						esc_html__( 'Create free account at %1$sAlchemy.com%2$s', 'univoucher-for-woocommerce' ),
						'<a href="https://www.alchemy.com/" target="_blank" rel="noopener" style="color: #0c5460; text-decoration: underline;">',
						'</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Create new app', 'univoucher-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select Use case: "Marketplace"', 'univoucher-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Choose chains: "Enable all EVM" chains', 'univoucher-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Activate services: "Token API"', 'univoucher-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Copy your API key and paste it below', 'univoucher-for-woocommerce' ); ?></li>
			</ol>
		</div>
		<div style="margin-top: 15px;">
			<input
				type="text"
				id="<?php echo esc_attr( $args['label_for'] ); ?>"
				name="<?php echo esc_attr( $args['label_for'] ); ?>"
				value="<?php echo esc_attr( $api_key ); ?>"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Enter your Alchemy API key', 'univoucher-for-woocommerce' ); ?>"
				autocomplete="off"
				style="font-family: monospace;"
			/>
			<button type="button" class="button" id="toggle-api-key">
				<?php esc_html_e( 'Show/Hide', 'univoucher-for-woocommerce' ); ?>
			</button>
		</div>
		
		<div id="api-key-status" style="margin-top: 10px;">
			<?php if ( empty( $api_key ) ) : ?>
				<p class="description">
					<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
					<?php esc_html_e( 'API key is required for fetching ERC20 token details when configuring products.', 'univoucher-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>
		</div>
		
		<div id="api-test-section" style="margin-top: 10px; <?php echo empty( $api_key ) ? 'display: none;' : ''; ?>">
			<button type="button" class="button" id="test-api-key">
				<?php esc_html_e( 'Test API Connection', 'univoucher-for-woocommerce' ); ?>
			</button>
			<div id="api-test-result" style="margin-top: 10px; display: none;"></div>
		</div>
	</div>

	<?php
}

/**
 * AJAX handler for testing Alchemy API key.
 */
function univoucher_ajax_test_api_key() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'univoucher_stock_sync' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'univoucher-for-woocommerce' ) ) );
	}

	// Check capabilities.
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'univoucher-for-woocommerce' ) ) );
	}

	$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'No API key provided.', 'univoucher-for-woocommerce' ) ) );
	}

	// Test API with a simple eth_chainId call (lighter than block number)
	$response = wp_remote_post( 'https://eth-mainnet.g.alchemy.com/v2/' . $api_key, array(
		'body' => wp_json_encode( array(
			'jsonrpc' => '2.0',
			'method' => 'eth_chainId',
			'params' => array(),
			'id' => 1,
		) ),
		'headers' => array(
			'Content-Type' => 'application/json',
		),
		'timeout' => 10,
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Connection failed. Please check your internet connection.', 'univoucher-for-woocommerce' ) ) );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( isset( $data['error'] ) ) {
		$error_message = $data['error']['message'];
		if ( stripos( $error_message, 'authenticate' ) !== false || stripos( $error_message, 'unauthorized' ) !== false ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid API key. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ) ) );
		} else {
			/* translators: %s is the error message from the API */
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'API Error: %s', 'univoucher-for-woocommerce' ), $error_message ) ) );
		}
	}

	if ( isset( $data['result'] ) ) {
		$chain_id = hexdec( $data['result'] );
		$network_name = $chain_id === 1 ? 'Ethereum Mainnet' : 'Chain ID ' . $chain_id;
		wp_send_json_success( array( 'message' => sprintf( esc_html__( '✅ API key is valid!', 'univoucher-for-woocommerce' ), $network_name ) ) );
	}

	wp_send_json_error( array( 'message' => esc_html__( 'Unexpected response from Alchemy API. Please try again.', 'univoucher-for-woocommerce' ) ) );
}

/**
 * Sanitize API key.
 *
 * @param string $input The input value.
 * @return string The sanitized value.
 */
function univoucher_sanitize_api_key( $input ) {
	// Remove any whitespace.
	$input = trim( $input );
	
	// Basic validation - Alchemy API keys are typically 32 characters.
	if ( ! empty( $input ) && strlen( $input ) < 10 ) {
		add_settings_error(
			'univoucher_wc_alchemy_api_key',
			'api_key_invalid',
			esc_html__( 'The API key appears to be too short. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ),
			'error'
		);
		return get_option( 'univoucher_wc_alchemy_api_key', '' );
	}

	// Sanitize the key.
	$sanitized = sanitize_text_field( $input );

	// Add success message if key was updated.
	if ( ! empty( $sanitized ) && $sanitized !== get_option( 'univoucher_wc_alchemy_api_key', '' ) ) {
		add_settings_error(
			'univoucher_wc_alchemy_api_key',
			'api_key_updated',
			esc_html__( 'Alchemy API key updated successfully.', 'univoucher-for-woocommerce' ),
			'updated'
		);
	}

	return $sanitized;
}

/**
 * Get the API key.
 *
 * @return string The API key.
 */
function univoucher_get_api_key() {
	return get_option( 'univoucher_wc_alchemy_api_key', '' );
}

/**
 * Check if API key is configured.
 *
 * @return bool True if API key is set.
 */
function univoucher_is_api_key_configured() {
	$api_key = univoucher_get_api_key();
	return ! empty( $api_key ) && strlen( $api_key ) >= 10;
} 