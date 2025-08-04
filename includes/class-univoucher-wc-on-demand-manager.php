<?php
/**
 * UniVoucher On-Demand Manager
 *
 * @package UniVoucher_For_WooCommerce
 * @subpackage On_Demand
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the maximum number of cards that can be created for a product
 * based on the current balance in the internal wallet.
 *
 * @param int $product_id The product ID.
 * @return int|WP_Error The maximum number of cards that can be created, or WP_Error on failure.
 */
function uv_get_on_demand_limit( $product_id ) {
	// Get product data
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'invalid_product', __( 'Invalid product ID.', 'univoucher-for-woocommerce' ) );
	}

	// Get the card amount and token address from product meta
	$card_amount = get_post_meta( $product_id, '_univoucher_card_amount', true );
	$token_address = get_post_meta( $product_id, '_univoucher_token_address', true );
	$network_id = get_post_meta( $product_id, '_univoucher_network', true );
	$token_decimals = get_post_meta( $product_id, '_univoucher_token_decimals', true );
	$token_type = get_post_meta( $product_id, '_univoucher_token_type', true );

	if ( ! $card_amount || ! $network_id || ! $token_decimals ) {
		return new WP_Error( 'missing_product_data', __( 'Product is not configured for UniVoucher cards.', 'univoucher-for-woocommerce' ) );
	}

	// Check if backorder is enabled for this product
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'invalid_product', __( 'Invalid product ID.', 'univoucher-for-woocommerce' ) );
	}

	// Check if backorder is enabled
	if ( ! $product->backorders_allowed() ) {
		return new WP_Error( 'backorder_disabled', __( 'Backorder not enabled.', 'univoucher-for-woocommerce' ) );
	}

	// Get the internal wallet public key (address)
	$wallet_address = get_option( 'univoucher_wc_wallet_public_key', '' );
	if ( ! $wallet_address ) {
		return new WP_Error( 'no_wallet_address', __( 'Internal wallet address not available.', 'univoucher-for-woocommerce' ) );
	}

	// Get Alchemy API key
	$alchemy_api_key = get_option( 'univoucher_wc_alchemy_api_key', '' );
	if ( ! $alchemy_api_key ) {
		return new WP_Error( 'no_api_key', __( 'Alchemy API key not configured.', 'univoucher-for-woocommerce' ) );
	}

	// Get network RPC endpoint based on network ID
	$network_rpc = uv_get_network_rpc( $network_id );
	if ( ! $network_rpc ) {
		return new WP_Error( 'unsupported_network', __( 'Unsupported network.', 'univoucher-for-woocommerce' ) );
	}

	// Get token balance from Alchemy API
	if ( $token_type === 'native' ) {
		// For native tokens (ETH, etc.), get native balance
		$token_balance = uv_get_native_balance( $wallet_address, $network_rpc, $alchemy_api_key );
	} else {
		// For ERC-20 tokens, get token balance
		if ( ! $token_address ) {
			return new WP_Error( 'missing_token_address', __( 'Token address required for ERC-20 tokens.', 'univoucher-for-woocommerce' ) );
		}
		$token_balance = uv_get_token_balance( $wallet_address, $token_address, $network_rpc, $alchemy_api_key );
	}
	
	if ( is_wp_error( $token_balance ) ) {
		return $token_balance;
	}

	// Convert card amount to wei (respecting token decimals)
	$card_amount_wei = $card_amount * pow( 10, $token_decimals );

	// Calculate maximum number of cards
	$max_cards = floor( $token_balance / $card_amount_wei );

	return max( 0, $max_cards );
}

/**
 * Get native token balance from Alchemy API.
 *
 * @param string $wallet_address The wallet address.
 * @param string $network_rpc The network RPC endpoint.
 * @param string $api_key The Alchemy API key.
 * @return float|WP_Error The native token balance or WP_Error on failure.
 */
function uv_get_native_balance( $wallet_address, $network_rpc, $api_key ) {
	$url = 'https://' . $network_rpc . '.g.alchemy.com/v2/' . $api_key;

	$payload = array(
		'jsonrpc' => '2.0',
		'method'  => 'eth_getBalance',
		'params'  => array( $wallet_address, 'latest' ),
		'id'      => 1,
	);

	$response = wp_remote_post(
		$url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_request_failed', $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! $data || isset( $data['error'] ) ) {
		$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
		return new WP_Error( 'api_error', $error_message );
	}

	if ( isset( $data['result'] ) ) {
		// Convert hex balance to decimal
		$balance_hex = $data['result'];
		return hexdec( $balance_hex );
	}

	return 0;
}

/**
 * Get network RPC endpoint based on network ID.
 *
 * @param int $network_id The network ID.
 * @return string|false The RPC endpoint or false if not supported.
 */
function uv_get_network_rpc( $network_id ) {
	$networks = array(
		1 => 'eth-mainnet',
		137 => 'polygon-mainnet',
		10 => 'opt-mainnet',
		42161 => 'arb-mainnet',
		56 => 'bnb-mainnet',
		43114 => 'avax-mainnet',
		8453 => 'base-mainnet',
	);

	return isset( $networks[ $network_id ] ) ? $networks[ $network_id ] : false;
}

/**
 * Get token balance from Alchemy API.
 *
 * @param string $wallet_address The wallet address.
 * @param string $token_address The token contract address.
 * @param string $network_rpc The network RPC endpoint.
 * @param string $api_key The Alchemy API key.
 * @return float|WP_Error The token balance or WP_Error on failure.
 */
function uv_get_token_balance( $wallet_address, $token_address, $network_rpc, $api_key ) {
	$url = 'https://' . $network_rpc . '.g.alchemy.com/v2/' . $api_key;

	$payload = array(
		'jsonrpc' => '2.0',
		'method'  => 'alchemy_getTokenBalances',
		'params'  => array( $wallet_address, 'erc20' ),
		'id'      => 1,
	);

	$response = wp_remote_post(
		$url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_request_failed', $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! $data || isset( $data['error'] ) ) {
		$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
		return new WP_Error( 'api_error', $error_message );
	}

	// Find the specific token balance
	if ( isset( $data['result']['tokenBalances'] ) ) {
		foreach ( $data['result']['tokenBalances'] as $token ) {
			if ( strtolower( $token['contractAddress'] ) === strtolower( $token_address ) ) {
				// Convert hex balance to decimal
				$balance_hex = $token['tokenBalance'];
				return hexdec( $balance_hex );
			}
		}
	}

	// Token not found, return 0 balance
	return 0;
}

 