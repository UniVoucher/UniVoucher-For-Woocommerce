<?php
/**
 * UniVoucher For WooCommerce Encryption
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage Encryption
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UniVoucher_WC_Encryption class.
 */
class UniVoucher_WC_Encryption {

	/**
	 * The single instance of the class.
	 *
	 * @var UniVoucher_WC_Encryption
	 */
	protected static $_instance = null;

	/**
	 * Main UniVoucher_WC_Encryption Instance.
	 *
	 * @return UniVoucher_WC_Encryption - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * UniVoucher_WC_Encryption Constructor.
	 */
	public function __construct() {
		// Constructor logic if needed
	}

	/**
	 * Generate database security key file if it doesn't exist.
	 * Includes migration logic to preserve existing keys from old wp-includes location.
	 */
	public function uv_generate_database_security_key() {
		// Store the key file in uploads directory following WordPress best practices
		$upload_dir = wp_upload_dir();
		$univoucher_dir = $upload_dir['basedir'] . '/univoucher-security';
		$key_file_path = $univoucher_dir . '/database-security-key.php';
		
		// Initialize WordPress filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create univoucher security directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $univoucher_dir ) ) {
			$wp_filesystem->mkdir( $univoucher_dir, FS_CHMOD_DIR );
			// Create .htaccess file to protect the directory
			$htaccess_content = "# UniVoucher Security Directory Protection\n<Files \"*\">\nOrder Allow,Deny\nDeny from all\n</Files>";
			$wp_filesystem->put_contents( $univoucher_dir . '/.htaccess', $htaccess_content, FS_CHMOD_FILE );
		}

		// Only create if file doesn't exist in new location
		if ( ! $wp_filesystem->exists( $key_file_path ) ) {
			$database_key = null;
			
			// Check for existing key in old wp-includes location for migration
			$old_key_file_path = ABSPATH . 'wp-includes/univoucher-database-security-key.php';
			if ( $wp_filesystem->exists( $old_key_file_path ) ) {
				// Migrate existing key from old location
				include_once $old_key_file_path;
				if ( defined( 'UNIVOUCHER_DATABASE_KEY' ) ) {
					$database_key = UNIVOUCHER_DATABASE_KEY;
				}
			}
			
			// Generate new key if no existing key found
			if ( ! $database_key ) {
				$database_key = bin2hex( random_bytes( 32 ) ); // 64-character hex string
			}
			
			$file_content = '<?php
/**
 * UniVoucher Database Security Key
 * 
 * ⚠️  CRITICAL SECURITY WARNING ⚠️
 * 
 * This file contains the database encryption key, The database key is used to encrypt and store your gift card inventory encrypted ( unreadable ) in the database.
 * It is also used to decrypt (and show the real key) in the WP admin or user dashboard. Keep this key secure and always maintain a backup.
 * If this key is lost, you will not be able to access any of your existing gift cards, and they will become permanently unusable.
 * 
 * IMPORTANT INSTRUCTIONS:
 * - NEVER delete this file or your encrypted gift card data will be permanently lost
 * - NEVER manually edit the security key below
 * - ALWAYS keep a secure backup of this file
 * - If you lose this key, NO ONE can recover your encrypted gift card secrets
 * - Keep this file secure and restrict access to it
 * - This file is stored in uploads directory with .htaccess protection
 * 
 * Generated: ' . current_time( 'mysql' ) . '
 */

// Exit if accessed directly
if ( ! defined( \'ABSPATH\' ) ) {
	exit;
}

// Database encryption key - DO NOT MODIFY
define( \'UNIVOUCHER_DATABASE_KEY\', \'' . $database_key . '\' );
';

			// Write the file using WordPress filesystem
			$wp_filesystem->put_contents( $key_file_path, $file_content, FS_CHMOD_FILE );
			
			// If we migrated from old location, optionally clean up old file
			// (Only if new file was created successfully)
			if ( $wp_filesystem->exists( $key_file_path ) && isset( $old_key_file_path ) && $wp_filesystem->exists( $old_key_file_path ) ) {
				// Add a small delay and verify the key can be read from new location
				if ( $this->uv_get_database_key() ) {
					// Successfully migrated, can safely remove old file
					$wp_filesystem->delete( $old_key_file_path );
				}
			}
		}
	}

	/**
	 * Get the database security key.
	 *
	 * @return string|false Database key or false if not found.
	 */
	public function uv_get_database_key() {
		// Look for the key file in uploads directory
		$upload_dir = wp_upload_dir();
		$key_file_path = $upload_dir['basedir'] . '/univoucher-security/database-security-key.php';
		
		// Initialize WordPress filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		
		if ( $wp_filesystem->exists( $key_file_path ) ) {
			include_once $key_file_path;
			return defined( 'UNIVOUCHER_DATABASE_KEY' ) ? UNIVOUCHER_DATABASE_KEY : false;
		}
		
		return false;
	}

	/**
	 * Encrypt card secret.
	 *
	 * @param string $data Data to encrypt.
	 * @return string|WP_Error Encrypted data (base64 encoded) or WP_Error on failure.
	 */
	public function uv_encrypt_data( $data ) {
		$database_key = $this->uv_get_database_key();
		
		if ( ! $database_key ) {
			return new WP_Error( 'encryption_failed', esc_html__( 'Database security key not found. Cannot encrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		$key = hex2bin( $database_key );
		if ( ! $key ) {
			return new WP_Error( 'encryption_failed', esc_html__( 'Invalid database security key format. Cannot encrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		$iv = random_bytes( 16 );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
		
		if ( false === $encrypted ) {
			return new WP_Error( 'encryption_failed', esc_html__( 'OpenSSL encryption failed. Cannot encrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt card secret.
	 *
	 * @param string $encrypted_data Encrypted data (base64 encoded).
	 * @return string|WP_Error Decrypted data or WP_Error on failure.
	 */
	public function uv_decrypt_data( $encrypted_data ) {
		$database_key = $this->uv_get_database_key();
		
		if ( ! $database_key ) {
			return new WP_Error( 'decryption_failed', esc_html__( 'Database security key not found. Cannot decrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		$key = hex2bin( $database_key );
		if ( ! $key ) {
			return new WP_Error( 'decryption_failed', esc_html__( 'Invalid database security key format. Cannot decrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		$data = base64_decode( $encrypted_data );
		if ( false === $data ) {
			return new WP_Error( 'decryption_failed', esc_html__( 'Invalid encrypted data format. Cannot decrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		if ( strlen( $data ) < 16 ) {
			return new WP_Error( 'decryption_failed', esc_html__( 'Encrypted data too short. Cannot decrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		$iv = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );
		
		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
		
		if ( false === $decrypted ) {
			return new WP_Error( 'decryption_failed', esc_html__( 'OpenSSL decryption failed. Cannot decrypt card secret.', 'univoucher-for-woocommerce' ) );
		}
		
		return $decrypted;
	}
}