<?php
/**
 * Plugin Name: Cater Ice Cream Catalogue Guard
 * Description: Standalone catalogue-mode purchase guard. Fails closed: public purchasing, cart, checkout and account shopping paths are disabled on the server unless sales are deliberately enabled in wp-config.php. Independent of the theme and the cater-ice-cream-core plugin.
 * Version: 1.0.0
 * Author: Web Karma
 * License: GPL-2.0-or-later
 *
 * Configuration (wp-config.php only, never an editor setting):
 *   define( 'CIC_COMMERCE_MODE', 'sales' );  // the ONLY value that disables this guard.
 * Missing, empty or mistyped values keep catalogue mode active.
 *
 * @package CIC\CatalogGuard
 */

namespace CIC\CatalogGuard;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';

/** Public message used for every blocked purchase path. Contains no technical detail. */
const MESSAGE = 'Online purchasing is not available on this website. Please request a quote and our team will be in touch.';

/** Request keys used by WooCommerce cart, checkout, order-pay and customer-account form handlers. */
const BLOCKED_REQUEST_KEYS = array(
	'add-to-cart',
	'update_cart',
	'apply_coupon',
	'remove_coupon',
	'remove_item',
	'undo_item',
	'order_again',
	'cancel_order',
	'pay_for_order',
	'woocommerce_pay',
	'woocommerce-pay-nonce',
	'woocommerce-cart-nonce',
	'woocommerce_checkout_place_order',
	'woocommerce-process-checkout-nonce',
	'add_payment_method',
	'woocommerce-add-payment-method-nonce',
	'woocommerce-register-nonce',
	'save-account-details-nonce',
	'woocommerce-edit-address-nonce',
);

/** Explicit legacy admin-ajax actions; any other nopriv woocommerce_* action is also denied dynamically. */
const BLOCKED_AJAX_ACTIONS = array(
	'woocommerce_add_to_cart',
	'woocommerce_remove_from_cart',
	'woocommerce_apply_coupon',
	'woocommerce_remove_coupon',
	'woocommerce_update_shipping_method',
	'woocommerce_get_cart_totals',
	'woocommerce_update_order_review',
	'woocommerce_checkout',
	'woocommerce_get_refreshed_fragments',
);

/** wc-ajax endpoints allowed in catalogue mode. Empty = deny all (reviewed allowlist only). */
const ALLOWED_WC_AJAX = array();

/** Purchase and account blocks removed from public output and from block-hook injection. */
const BLOCKED_BLOCKS = array(
	'woocommerce/mini-cart',
	'woocommerce/cart',
	'woocommerce/checkout',
	'woocommerce/customer-account',
	'woocommerce/cart-link',
	'woocommerce/add-to-cart-form',
	'woocommerce/add-to-cart-with-options',
	'woocommerce/product-button',
	'woocommerce/product-price',
	'woocommerce/price-filter',
);

/** Shortcodes that render purchase or account UI. */
const BLOCKED_SHORTCODES = array(
	'woocommerce_cart',
	'woocommerce_checkout',
	'woocommerce_my_account',
	'woocommerce_order_tracking',
	'add_to_cart',
	'add_to_cart_url',
);

/** Frontend commerce scripts that are unnecessary in catalogue mode. */
const BLOCKED_SCRIPTS = array(
	'wc-cart-fragments',
	'wc-add-to-cart',
	'wc-add-to-cart-variation',
	'wc-cart',
	'wc-checkout',
	'wc-mini-cart-block-frontend',
	// Phase 09: general WooCommerce front-end scripts have no use in catalogue mode, and order
	// attribution (Sourcebuster) sets tracking cookies with no approved tracking configuration.
	'woocommerce',
	'wc-jquery-blockui',
	'wc-js-cookie',
	'sourcebuster-js',
	'wc-order-attribution',
);

/** Order and customer emails that must never be sent from the catalogue. */
const BLOCKED_EMAILS = array(
	'new_order',
	'cancelled_order',
	'failed_order',
	'customer_on_hold_order',
	'customer_processing_order',
	'customer_completed_order',
	'customer_refunded_order',
	'customer_invoice',
	'customer_note',
	'customer_new_account',
);

/**
 * Catalogue mode is on unless sales were deliberately enabled with the exact constant value.
 */
function catalogue_mode(): bool {
	return ! ( defined( 'CIC_COMMERCE_MODE' ) && 'sales' === CIC_COMMERCE_MODE );
}

define( 'CIC_CATALOG_GUARD_VERSION', VERSION );
define( 'CIC_CATALOG_MODE_ACTIVE', catalogue_mode() );

if ( ! CIC_CATALOG_MODE_ACTIVE ) {
	return;
}

/**
 * Requests that are trusted operator tooling rather than public traffic.
 */
function is_exempt_request(): bool {
	return ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron();
}

/**
 * Whether the response should be JSON (AJAX, wc-ajax, REST or JSON clients).
 */
function wants_json(): bool {
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) || isset( $_GET['rest_route'] ) || wp_is_json_request() ) { // phpcs:ignore WordPress.Security.NonceVerification
		return true;
	}
	$path   = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
	return false !== stripos( $path, $prefix );
}

/**
 * Stop the request with a consistent 403 before any WooCommerce side effect.
 */
function deny(): void {
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
	if ( wants_json() ) {
		wp_send_json(
			array(
				'code'    => 'cic_catalog_mode',
				'message' => MESSAGE,
				'data'    => array( 'status' => 403 ),
			),
			403
		);
	}
	wp_die( esc_html( MESSAGE ), 'Purchasing unavailable', array( 'response' => 403 ) );
}

/**
 * Block legacy form/query purchase paths, wc-ajax and wc-api before WooCommerce handles them.
 * WooCommerce form handlers run on wp_loaded and do not check is_admin(), so this applies to
 * every web request (front end, admin-post, admin-ajax and REST URLs alike).
 */
function guard_request(): void {
	if ( is_exempt_request() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	if ( isset( $_REQUEST['wc-ajax'] ) ) {
		$endpoint = sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) );
		if ( ! in_array( $endpoint, ALLOWED_WC_AJAX, true ) ) {
			deny();
		}
	}
	if ( isset( $_REQUEST['wc-api'] ) ) {
		deny();
	}

	$found = array_values( array_intersect( BLOCKED_REQUEST_KEYS, array_keys( $_REQUEST ) ) );
	// WooCommerce customer registration also accepts the generic _wpnonce field.
	$registration = isset( $_POST['register'], $_POST['email'] );
	if ( ! $found && ! $registration ) {
		return;
	}

	$is_get_only = 'GET' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' )
		&& ! $registration
		&& ! array_intersect( $found, array_keys( $_POST ) )
		&& ! array_intersect( $found, array_keys( $_COOKIE ) );
	// phpcs:enable

	if ( $is_get_only && ! wants_json() ) {
		// Safe redirect to the same URL without purchase parameters (avoids indexable duplicates).
		$clean = remove_query_arg( $found, wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		nocache_headers();
		wp_safe_redirect( $clean, 302, 'cic-catalog-guard' );
		exit;
	}
	deny();
}
add_action( 'init', __NAMESPACE__ . '\guard_request', PHP_INT_MIN );

/**
 * wc-ajax can also arrive as a parsed query var; wc-api can arrive as a /wc-api/ path endpoint.
 */
add_action(
	'parse_request',
	static function ( $wp ): void {
		if ( ! is_exempt_request() && ( ! empty( $wp->query_vars['wc-api'] ) ) ) {
			deny();
		}
	},
	PHP_INT_MIN
);
add_action(
	'template_redirect',
	static function (): void {
		if ( is_exempt_request() ) {
			return;
		}
		$endpoint = sanitize_key( (string) get_query_var( 'wc-ajax' ) );
		if ( '' !== $endpoint && ! in_array( $endpoint, ALLOWED_WC_AJAX, true ) ) {
			deny();
		}
	},
	PHP_INT_MIN
);

/**
 * Legacy admin-ajax WooCommerce shopping actions. Priv-only admin actions (product editing,
 * variations, order administration) have no nopriv handler and are unaffected.
 */
add_action(
	'admin_init',
	static function (): void {
		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $action, BLOCKED_AJAX_ACTIONS, true )
			|| ( 0 === strpos( $action, 'woocommerce_' ) && has_action( 'wp_ajax_nopriv_' . $action ) ) ) {
			deny();
		}
	},
	PHP_INT_MIN
);

/**
 * Store API decision for a REST route. Cart, checkout, order and batch routes are denied for
 * everyone. Read-only product routes are allowed only for authenticated product editors
 * (block-editor previews); the PHP catalogue itself has no public Store API dependency.
 *
 * @return \WP_Error|null Error when the route must be denied.
 */
function store_api_decision( string $route, string $method ): ?\WP_Error {
	$route = strtolower( (string) preg_replace( '#/+#', '/', '/' . ltrim( $route, '/' ) ) );
	if ( ! preg_match( '#^/wc/store(?:/v\d+)?(?:/(.*))?$#', $route, $m ) ) {
		return null;
	}
	$sub     = isset( $m[1] ) ? $m[1] : '';
	$is_read = in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true );
	if ( $is_read && 0 === strpos( $sub, 'products' ) && current_user_can( 'edit_products' ) ) {
		return null;
	}
	return new \WP_Error( 'cic_catalog_mode', MESSAGE, array( 'status' => 403 ) );
}

// Before route matching: covers versioned, unversioned, ?rest_route= and mixed-case routes.
// Returns a response object, not a WP_Error: core batch requests pass rest_pre_dispatch results
// to rest_post_dispatch without converting errors, and WP 7.1 has a filter there that requires
// WP_REST_Response (a WP_Error caused a fatal error during Phase 01 testing).
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		$error = store_api_decision( $request->get_route(), $request->get_method() );
		return $error ? rest_convert_error_to_response( $error ) : $result;
	},
	PHP_INT_MIN,
	3
);
// Before permission and route callbacks for every individual request, including batch sub-requests.
add_filter(
	'rest_request_before_callbacks',
	static function ( $response, $handler, $request ) {
		$error = store_api_decision( $request->get_route(), $request->get_method() );
		return $error ? $error : $response;
	},
	PHP_INT_MIN,
	3
);

/**
 * Cart, checkout (including order-pay and order-received) and account pages are not public
 * destinations in catalogue mode. Redirect them to the catalogue.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_exempt_request() || ! function_exists( 'is_cart' ) ) {
			return;
		}
		if ( is_cart() || is_checkout() || is_account_page() ) {
			$target = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			nocache_headers();
			wp_safe_redirect( $target ? $target : home_url( '/' ), 302, 'cic-catalog-guard' );
			exit;
		}
	},
	PHP_INT_MIN + 1
);

// Server-side purchasability, independent of price (never rely on an absent price alone).
add_filter( 'woocommerce_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_variation_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false', PHP_INT_MAX );
add_action(
	'woocommerce_before_checkout_process',
	static function (): void {
		throw new \Exception( MESSAGE ); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	PHP_INT_MIN
);
add_filter(
	'woocommerce_available_payment_gateways',
	static function ( $gateways ) {
		return ( is_admin() && ! wp_doing_ajax() ) ? $gateways : array();
	},
	PHP_INT_MAX
);

// Customer accounts are not part of the catalogue.
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', static fn() => 'no' );
add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', static fn() => 'no' );

foreach ( BLOCKED_EMAILS as $email_id ) {
	add_filter( 'woocommerce_email_enabled_' . $email_id, '__return_false', PHP_INT_MAX );
}

/**
 * Whether we are rendering public output (not wp-admin screens).
 */
function is_public_output(): bool {
	return ! is_admin() || wp_doing_ajax();
}

// Hide price HTML and purchase controls from public output. Admin product screens keep prices.
add_filter(
	'woocommerce_get_price_html',
	static function ( $html ) {
		return is_public_output() ? '' : $html;
	},
	PHP_INT_MAX
);
add_filter( 'woocommerce_loop_add_to_cart_link', static fn() => '', PHP_INT_MAX );
add_action(
	'wp',
	static function (): void {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	},
	PHP_INT_MAX
);

// No sales schema: remove Offer data from product structured data.
add_filter(
	'woocommerce_structured_data_product',
	static function ( $markup ) {
		if ( is_array( $markup ) ) {
			unset( $markup['offers'] );
		}
		return $markup;
	},
	PHP_INT_MAX
);

// Purchase/account blocks: never auto-inserted by block hooks, and render nothing publicly.
add_filter(
	'hooked_block_types',
	static function ( $types ) {
		return is_array( $types ) ? array_values( array_diff( $types, BLOCKED_BLOCKS ) ) : $types;
	},
	PHP_INT_MAX
);
add_filter(
	'render_block',
	static function ( $content, $block ) {
		return ( is_public_output() && isset( $block['blockName'] ) && in_array( $block['blockName'], BLOCKED_BLOCKS, true ) ) ? '' : $content;
	},
	PHP_INT_MAX,
	2
);
add_filter(
	'pre_do_shortcode_tag',
	static function ( $output, $tag ) {
		return in_array( $tag, BLOCKED_SHORTCODES, true ) ? '' : $output;
	},
	PHP_INT_MAX,
	2
);

// Remove unnecessary commerce scripts from public pages.
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		foreach ( BLOCKED_SCRIPTS as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	PHP_INT_MAX
);

// Keep cart/checkout/account pages out of core XML sitemaps.
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $post_type ) {
		if ( 'page' === $post_type && function_exists( 'wc_get_page_id' ) ) {
			$ids                  = array_filter( array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) ), static fn( $id ) => $id > 0 );
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids );
		}
		return $args;
	},
	10,
	2
);

// Tell product managers why there are no purchase controls.
add_action(
	'admin_notices',
	static function (): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( in_array( $screen->id, array( 'product', 'edit-product', 'woocommerce_page_wc-settings' ), true ) ) {
			echo '<div class="notice notice-info"><p><strong>Catalogue mode is active.</strong> Public purchasing, cart and checkout are disabled on the server by the cic-catalog-guard must-use plugin. Products remain fully editable.</p></div>';
		}
	}
);
