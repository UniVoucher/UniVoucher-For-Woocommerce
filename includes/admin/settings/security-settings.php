<?php
/**
 * UniVoucher Security Settings Tab
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security section callback.
 *
 * @param array $args Section arguments.
 */
function univoucher_security_section_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Manage your security settings and database encryption key.', 'univoucher-for-woocommerce' ); ?>
	</p>
	<?php
}

/**
 * Database key backup confirmation field callback.
 *
 * @param array $args Field arguments.
 */
function univoucher_database_key_backup_callback( $args ) {
	$database_key = UniVoucher_For_WooCommerce::uv_get_database_key();
	$backup_confirmed = get_option( 'univoucher_wc_database_key_backup_confirmed', false );
	$upload_dir = wp_upload_dir();
	$key_file_path = $upload_dir['basedir'] . '/univoucher-security/database-security-key.php';
	?>
	
	<?php if ( $database_key ) : ?>
		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Database Key Status', 'univoucher-for-woocommerce' ); ?>
			</h4>
			<p style="margin: 0 0 10px 0;">
				<span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 5px;"></span>
				<?php esc_html_e( 'Database security key is configured and active', 'univoucher-for-woocommerce' ); ?>
			</p>
			<p style="margin: 0; font-size: 12px; color: #6c757d;">
				<strong><?php esc_html_e( 'Key file location:', 'univoucher-for-woocommerce' ); ?></strong> 
				<code><?php echo esc_html( $key_file_path ); ?></code>
			</p>
			<p style="margin: 5px 0 0 0; font-size: 11px; color: #28a745;">
				<span class="dashicons dashicons-lock" style="font-size: 11px; margin-right: 3px;"></span>
				<?php esc_html_e( 'Stored in uploads directory with .htaccess protection', 'univoucher-for-woocommerce' ); ?>
			</p>
			<h5 style="margin: 15px 0 5px 0; font-size: 14px; color: #495057;">
				<?php esc_html_e( 'What is Database Key?', 'univoucher-for-woocommerce' ); ?>
			</h5>
			<p style="margin: 0; font-size: 13px; color: #495057;">
				<?php esc_html_e( 'The database key is used to encrypt and store your gift card inventory encrypted ( unreadable ) in the database. It is also used to decrypt (and show the real key) in the WP admin or user dashboard. Keep this key secure and always maintain a backup. If this key is lost, you will not be able to access any of your existing gift cards, and they will become permanently unusable.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>

		<div class="univoucher-settings-box">
			<h4>
				<?php esc_html_e( 'Backup Database Key', 'univoucher-for-woocommerce' ); ?>
			</h4>
			<div style="margin-bottom: 15px;">
				<input 
					type="password" 
					id="database-key-display" 
					value="<?php echo esc_attr( $database_key ); ?>" 
					readonly 
					style="width: 100%; max-width: 600px; font-family: monospace; font-size: 12px;"
				/>
			</div>
			<div style="margin-bottom: 15px;">
				<button type="button" class="button" id="toggle-database-key">
					<?php esc_html_e( 'Show/Hide Key', 'univoucher-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button" id="copy-database-key">
					<?php esc_html_e( 'Copy Key', 'univoucher-for-woocommerce' ); ?>
				</button>
			</div>

			<div class="univoucher-settings-box-info">
				<label for="<?php echo esc_attr( $args['label_for'] ); ?>" style="display: flex; align-items: center; margin: 0;">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $args['label_for'] ); ?>"
						name="<?php echo esc_attr( $args['label_for'] ); ?>"
						value="1"
						<?php checked( $backup_confirmed, true ); ?>
						style="margin-right: 10px;"
					/>
					<strong style="color: #0c5460;">
						<?php esc_html_e( 'I have saved a copy (backup) of the database key in a safe place', 'univoucher-for-woocommerce' ); ?>
					</strong>
				</label>
				<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">
					<?php esc_html_e( 'Check this box to confirm you have safely backed up your database key. This will hide backup reminder notices throughout the admin area.', 'univoucher-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="univoucher-settings-box-danger">
				<h4>
					<span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
					<?php esc_html_e( 'CRITICAL WARNINGS', 'univoucher-for-woocommerce' ); ?>
				</h4>
				<ul style="margin: 0; padding-left: 20px;">
					<li><?php esc_html_e( 'NEVER delete the database key file', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'NEVER manually edit the key in the file', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'ALWAYS keep a secure backup of this key', 'univoucher-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'If lost, NO ONE can recover your encrypted gift cards', 'univoucher-for-woocommerce' ); ?></li>
				</ul>
			</div>
		</div>
	<?php else : ?>
		<div class="univoucher-settings-box-error">
			<h4>
				<span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
				<?php esc_html_e( 'Database Key Not Found', 'univoucher-for-woocommerce' ); ?>
			</h4>
			<p style="margin: 0; color: #721c24;">
				<?php esc_html_e( 'The database security key file is missing. Please deactivate and reactivate the plugin to generate a new key.', 'univoucher-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php
}

/**
 * Check if database key backup is confirmed.
 *
 * @return bool True if backup is confirmed.
 */
function univoucher_is_database_key_backup_confirmed() {
	return get_option( 'univoucher_wc_database_key_backup_confirmed', false );
} 