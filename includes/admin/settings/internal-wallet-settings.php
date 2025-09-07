<?php
/**
 * UniVoucher Internal Wallet Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal wallet section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_internal_wallet_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Configure your crypto wallet for UniVoucher operations. This wallet will be used for manually adding cards to the inventory and creating cards automatically on demand.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Wallet private key field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_wallet_private_key_callback( $args ) {
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
	$private_key = '';
	
	if ( $encrypted_private_key ) {
		$decrypted = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( ! is_wp_error( $decrypted ) ) {
			$private_key = $decrypted;
		}
	}
	?>
	
	<div class="univoucher-settings-box">
		<h4>
			<?php esc_html_e( 'Your Ethereum Wallet Private Key (EVM-compatible):', 'univoucher-for-woocommerce' ); ?>
		</h4>
		
		<div style="margin-bottom: 15px;">
			<p style="margin: 5px 0 8px 0; font-size: 13px;">
				<?php esc_html_e( 'Your wallet private key is used for blockchain transactions and is encrypted and stored securely in the database.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box-warning" style="margin-top: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px;">
			<span style="color: #856404;">
				<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
				<strong><?php esc_html_e( 'Attention:', 'univoucher-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'You need to activate "Automatically create cards on demand" option in the On-Demand settings tab. When enabled, this wallet will be used to automatically create required cards on demand for backorders.', 'univoucher-for-woocommerce' ); ?>
			</span>
		</div>

		<div style="margin-top: 15px; display: flex; gap: 10px; align-items: flex-start;">
			<input
				type="password"
				id="univoucher_wc_wallet_private_key"
				name="univoucher_wc_wallet_private_key"
				value="<?php echo esc_attr( $private_key ); ?>"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Enter your private key', 'univoucher-for-woocommerce' ); ?>"
				autocomplete="off"
				style="font-family: monospace; width: 600px; max-width: 100%;"
			/>
			<button type="button" class="button" id="toggle-private-key" style="flex-shrink: 0;">
				<?php esc_html_e( 'Show', 'univoucher-for-woocommerce' ); ?>
			</button>
		</div>
		
		<div style="margin-top: 10px;">
			<p class="description">
				<?php esc_html_e( 'Enter your 64-character hexadecimal private key (with or without 0x prefix).', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
		
		<div style="margin-top: 10px;">
			<button type="button" class="button button-secondary" id="validate-private-key">
				<?php esc_html_e( 'Validate Private Key', 'univoucher-for-woocommerce' ); ?>
			</button>
		</div>
		
		<div style="margin-top: 10px;">
			<span id="validation-result" style="font-weight: bold;"></span>
		</div>
		
		<div class="univoucher-settings-box-warning" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px;">
			<strong style="color: #721c24;">
				<span class="dashicons dashicons-warning" style="margin-right: 3px;"></span>
				<?php esc_html_e( 'Security Warning:', 'univoucher-for-woocommerce' ); ?>
			</strong>
			<span style="font-size: 13px; color: #721c24;">
				<?php esc_html_e( 'Never share your private key with anyone. It provides full access to your wallet.', 'univoucher-for-woocommerce' ); ?>
			</span>
		</div>
	
	</div>
	<?php
}

/**
 * Wallet details display callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_wallet_details_callback( $args ) {
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted_private_key = get_option( 'univoucher_wc_wallet_private_key', '' );
	$wallet_address = '';
	
	if ( $encrypted_private_key ) {
		$decrypted = $encryption->uv_decrypt_data( $encrypted_private_key );
		if ( ! is_wp_error( $decrypted ) ) {
			// Generate wallet address from private key
			if ( function_exists( 'wp_remote_get' ) ) {
				// We'll generate the address via JavaScript for security
				$wallet_address = 'loading...';
			}
		}
	}
	?>
	
	<div class="univoucher-settings-box">
		<div id="wallet-details-container">
			<!-- First Row: Wallet Address and QR Code -->
			<div style="display: grid; grid-template-columns: auto 1fr; gap: 30px; align-items: flex-start; margin-bottom: 30px;">
				<div style="min-width: 400px;">
					<h4 style="margin: 0 0 10px 0;">
						<?php esc_html_e( 'Wallet Details:', 'univoucher-for-woocommerce' ); ?>
					</h4>
					<div style="margin-bottom: 15px;">
						<p style="margin: 5px 0 8px 0; font-size: 13px;">
							<?php esc_html_e( 'Current wallet information and balances across supported networks.', 'univoucher-for-woocommerce' ); ?>
						</p>
					</div>
					<strong><?php esc_html_e( 'Wallet Address:', 'univoucher-for-woocommerce' ); ?></strong>
					<div style="margin-top: 5px;">
						<input
							type="text"
							id="wallet-address-display"
							value="<?php echo esc_attr( $wallet_address ); ?>"
							readonly
							style="width: 100%; max-width: 500px; margin-bottom: 10px;"
						/>
						<input
							type="hidden"
							id="univoucher_wc_wallet_public_key"
							name="univoucher_wc_wallet_public_key"
							value="<?php echo esc_attr( get_option( 'univoucher_wc_wallet_public_key', '' ) ); ?>"
						/>
						<div style="display: flex; gap: 10px;">
							<button type="button" class="button" id="copy-address-btn">
								<?php esc_html_e( 'Copy', 'univoucher-for-woocommerce' ); ?>
							</button>
							<button type="button" class="button button-secondary" id="refresh-balances-btn">
								<?php esc_html_e( 'Refresh Balances', 'univoucher-for-woocommerce' ); ?>
							</button>
						</div>
					</div>
				</div>
				
				<div style="display: flex; justify-content: center; align-items: center;">
					<div id="wallet-qr-code" style="border: 1px solid #ddd; padding: 20px; background: white; text-align: center; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 200px;">
						<div style="font-size: 14px; color: #666; margin-bottom: 12px; font-weight: 600;"><?php esc_html_e( 'Wallet QR Code', 'univoucher-for-woocommerce' ); ?></div>
						<div id="qr-code-container" style="width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; background: #f9f9f9; border-radius: 4px; margin: 0 auto;">
							<span style="color: #999; font-size: 11px;"><?php esc_html_e( 'Loading...', 'univoucher-for-woocommerce' ); ?></span>
						</div>
						<div style="margin-top: 10px; font-size: 11px; color: #666;">
							<?php esc_html_e( 'Scan with your wallet app', 'univoucher-for-woocommerce' ); ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Second Row: Network Balances -->
			<div id="balances-container">
				<h5><?php esc_html_e( 'Network Balances:', 'univoucher-for-woocommerce' ); ?></h5>
				<div id="balances-loading" style="display: none;">
					<p><?php esc_html_e( 'Loading balances...', 'univoucher-for-woocommerce' ); ?></p>
				</div>
				<div id="balances-error" style="display: none;">
					<p style="color: #dc3232;"><?php esc_html_e( 'Error loading balances. Please check your Alchemy API key.', 'univoucher-for-woocommerce' ); ?></p>
				</div>
				<div id="balances-list">
					<!-- Balances will be populated here -->
				</div>
			</div>
		</div>
	</div>

	<?php
}

/**
 * Sanitize private key input.
 *
 * @param mixed $input The input value.
 * @return string The sanitized value.
 */
function univoucher_sanitize_private_key( $input ) {
	// Sanitize input
	$input = sanitize_text_field( $input );
	
	// If empty, return empty
	if ( empty( $input ) ) {
		return '';
	}
	
	// Check if this is already encrypted data (base64 encoded and much longer than 64 chars)
	// Encrypted data is typically much longer than a private key
	if ( base64_decode( $input, true ) !== false && strlen( $input ) > 100 ) {
		return $input; // Return encrypted data as-is
	}
	
	// Remove 0x prefix if present
	$clean_key = $input;
	if ( strpos( $clean_key, '0x' ) === 0 ) {
		$clean_key = substr( $clean_key, 2 );
	}
	
	// Validate private key format (64 hex characters)
	if ( strlen( $clean_key ) !== 64 || ! ctype_xdigit( $clean_key ) ) {
		// Show error and return empty
		add_settings_error(
			'univoucher_wc_wallet_private_key',
			'invalid_private_key',
			esc_html__( 'Invalid private key format. Please enter a 64-character hexadecimal private key.', 'univoucher-for-woocommerce' ),
			'error'
		);
		return '';
	}
	
	// Encrypt the valid private key
	$encryption = UniVoucher_WC_Encryption::instance();
	$encrypted = $encryption->uv_encrypt_data( $clean_key );
	
	if ( is_wp_error( $encrypted ) ) {
		add_settings_error(
			'univoucher_wc_wallet_private_key',
			'encryption_failed',
			esc_html__( 'Failed to encrypt private key. Please try again.', 'univoucher-for-woocommerce' ),
			'error'
		);
		return '';
	}
	
	return $encrypted;
}