<?php
/**
 * Phase 01 catalogue-guard verification. Sends real HTTP requests to the local site and checks
 * that no WooCommerce session, cart cookie, order, customer or email is created.
 *
 * Usage (WP-CLI only; run fixtures.php create first):
 *   wp eval-file dev/tests/phase01/guard-test.php <context-label> [anon-only]
 *
 * Prints PASS/FAIL per probe. Never prints credentials, cookies or application passwords.
 *
 * @package CIC\Tests
 */

if ( ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// eval-file runs this script inside a function: bind shared state to real globals for the helpers.
global $base, $results, $cookies;

$context   = $args[0] ?? 'default';
$anon_only = in_array( 'anon-only', $args, true );
$base      = home_url();
$results   = array();
$cookies   = array();

$log_file  = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
$log_start = is_file( $log_file ) ? filesize( $log_file ) : 0;

// ---------------------------------------------------------------- helpers.

function cic_t_request( string $method, string $path, array $opts = array() ): array {
	global $base, $cookies;
	$url  = ( 0 === strpos( $path, 'http' ) ) ? $path : $base . $path;
	$args = array(
		'method'      => $method,
		'timeout'     => 30,
		'redirection' => 0,
		'sslverify'   => false,
		'headers'     => $opts['headers'] ?? array(),
		'cookies'     => $opts['cookies'] ?? array(),
	);
	if ( isset( $opts['json'] ) ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $opts['json'] );
	} elseif ( isset( $opts['form'] ) ) {
		$args['body'] = $opts['form'];
	}
	$r = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) {
		return array( 'code' => 0, 'body' => $r->get_error_message(), 'location' => '', 'json' => null );
	}
	if ( empty( $opts['authenticated'] ) ) {
		foreach ( wp_remote_retrieve_cookies( $r ) as $cookie ) {
			$cookies[] = $cookie->name;
		}
	}
	$body = wp_remote_retrieve_body( $r );
	return array(
		'code'     => (int) wp_remote_retrieve_response_code( $r ),
		'body'     => $body,
		'location' => (string) wp_remote_retrieve_header( $r, 'location' ),
		'json'     => json_decode( $body, true ),
	);
}

function cic_t_record( string $group, string $label, bool $pass, string $detail ): void {
	global $results;
	$results[] = compact( 'group', 'label', 'pass', 'detail' );
}

function cic_t_json403( array $r ): bool {
	return 403 === $r['code'] && is_array( $r['json'] ) && 'cic_catalog_mode' === ( $r['json']['code'] ?? '' );
}

function cic_t_html403( array $r ): bool {
	return 403 === $r['code'] && false !== strpos( $r['body'], 'Online purchasing is not available' );
}

function cic_t_redirect_without( array $r, string $param ): bool {
	return in_array( $r['code'], array( 301, 302, 303, 307 ), true ) && false === strpos( $r['location'], $param . '=' );
}

function cic_t_db_counts(): array {
	global $wpdb;
	$p      = $wpdb->prefix;
	// Guest (anonymous) sessions use non-numeric keys; logged-in sessions use the user ID.
	$carts = 0;
	foreach ( (array) $wpdb->get_col( "SELECT session_value FROM {$p}woocommerce_sessions" ) as $value ) { // phpcs:ignore
		$session = maybe_unserialize( $value );
		$cart    = is_array( $session ) && isset( $session['cart'] ) ? maybe_unserialize( $session['cart'] ) : array();
		$carts  += ( is_array( $cart ) && $cart ) ? 1 : 0;
	}
	$counts = array(
		'wc_guest_sessions'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}woocommerce_sessions WHERE session_key NOT REGEXP '^[0-9]+$'" ), // phpcs:ignore
		'sessions_with_items' => $carts,
		'order_posts'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}posts WHERE post_type IN ('shop_order','shop_order_placehold','shop_order_refund')" ), // phpcs:ignore
		'users'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}users" ), // phpcs:ignore
		'customer_lookup' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wc_customer_lookup" ), // phpcs:ignore
	);
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . 'wc_orders' ) ) ) {
		$counts['hpos_orders'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wc_orders" ); // phpcs:ignore
	}
	return $counts;
}

function cic_t_mail_count(): int {
	$r = wp_remote_get( 'http://localhost:8025/api/v1/messages?limit=1', array( 'timeout' => 10 ) );
	$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	return isset( $j['total'] ) ? (int) $j['total'] : -1;
}

// ---------------------------------------------------------------- fixtures.

$fixtures = wc_get_products(
	array(
		'limit'      => -1,
		'meta_key'   => '_cic_fixture', // phpcs:ignore
		'meta_value' => 'phase01', // phpcs:ignore
	)
);
$simple   = null;
$variable = null;
foreach ( $fixtures as $p ) {
	if ( $p->is_type( 'variable' ) ) {
		$variable = $p;
	} elseif ( $p->is_type( 'simple' ) ) {
		$simple = $p;
	}
}
if ( ! $simple || ! $variable ) {
	WP_CLI::error( 'Fixtures missing. Run: wp eval-file dev/tests/phase01/fixtures.php create' );
}
$sid      = $simple->get_id();
$vid      = $variable->get_id();
$var_id   = $variable->get_children()[0];
$s_url    = wp_make_link_relative( get_permalink( $sid ) );
$v_url    = wp_make_link_relative( get_permalink( $vid ) );
$shop_url = wp_make_link_relative( wc_get_page_permalink( 'shop' ) );

$db_before   = cic_t_db_counts();
$mail_before = cic_t_mail_count();

WP_CLI::log( "Context: {$context} | theme: " . get_stylesheet() . ' | core plugin: ' . ( is_plugin_active( 'cater-ice-cream-core/cater-ice-cream-core.php' ) ? 'active' : 'inactive' ) . " | fixtures: simple #{$sid}, variable #{$vid}, variation #{$var_id}" );

// ---------------------------------------------------------------- A. legacy query and form paths.

$r = cic_t_request( 'GET', "/?add-to-cart={$sid}" );
cic_t_record( 'A legacy', 'GET /?add-to-cart=simple', cic_t_redirect_without( $r, 'add-to-cart' ), "{$r['code']} -> {$r['location']}" );
$r = cic_t_request( 'GET', "{$s_url}?add-to-cart={$sid}&quantity=3" );
cic_t_record( 'A legacy', 'GET product?add-to-cart&quantity', cic_t_redirect_without( $r, 'add-to-cart' ), "{$r['code']} -> {$r['location']}" );
$r = cic_t_request( 'GET', "/?add-to-cart={$var_id}" );
cic_t_record( 'A legacy', 'GET /?add-to-cart=variation-id', cic_t_redirect_without( $r, 'add-to-cart' ), "{$r['code']}" );
$r = cic_t_request( 'POST', $s_url, array( 'form' => array( 'add-to-cart' => $sid, 'quantity' => 1 ) ) );
cic_t_record( 'A legacy', 'POST product form add-to-cart', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', $v_url, array( 'form' => array( 'add-to-cart' => $vid, 'product_id' => $vid, 'variation_id' => $var_id, 'attribute_size' => 'Small', 'quantity' => 1 ) ) );
cic_t_record( 'A legacy', 'POST variable form with variation_id', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/wp-admin/admin-post.php', array( 'form' => array( 'add-to-cart' => $sid ) ) );
cic_t_record( 'A legacy', 'POST admin-post.php add-to-cart', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request( 'GET', "/wp-json/?add-to-cart={$sid}" );
cic_t_record( 'A legacy', 'GET REST URL ?add-to-cart (JSON)', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/cart/', array( 'form' => array( 'update_cart' => 'Update', 'woocommerce-cart-nonce' => 'x' ) ) );
cic_t_record( 'A legacy', 'POST cart update_cart', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request(
	'POST',
	'/checkout/',
	array(
		'form' => array(
			'woocommerce_checkout_place_order'   => 'Place order',
			'woocommerce-process-checkout-nonce' => 'x',
			'billing_email'                      => 'probe@example.test',
			'payment_method'                     => 'cod',
		),
	)
);
cic_t_record( 'A legacy', 'POST checkout place_order', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request( 'GET', '/checkout/order-pay/999999/?pay_for_order=true&key=wc_order_probe' );
cic_t_record( 'A legacy', 'GET order-pay link', in_array( $r['code'], array( 302, 403 ), true ) && false === strpos( $r['location'], 'pay_for_order' ), "{$r['code']} -> {$r['location']}" );
$r = cic_t_request( 'POST', '/my-account/', array( 'form' => array( 'register' => 'Register', 'email' => 'probe@example.test', '_wpnonce' => 'x' ) ) );
cic_t_record( 'A legacy', 'POST customer registration', cic_t_html403( $r ), "{$r['code']}" );
$r = cic_t_request( 'GET', '/?order_again=1&_wpnonce=x' );
cic_t_record( 'A legacy', 'GET order_again', cic_t_redirect_without( $r, 'order_again' ), "{$r['code']}" );

// ---------------------------------------------------------------- B. wc-ajax.

$r = cic_t_request( 'POST', '/?wc-ajax=add_to_cart', array( 'form' => array( 'product_id' => $sid, 'quantity' => 1 ) ) );
cic_t_record( 'B wc-ajax', 'POST ?wc-ajax=add_to_cart', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/', array( 'form' => array( 'wc-ajax' => 'add_to_cart', 'product_id' => $sid, 'quantity' => 1 ) ) );
cic_t_record( 'B wc-ajax', 'POST body wc-ajax=add_to_cart', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/?wc-ajax=add_to_cart', array( 'form' => array( 'product_id' => $var_id, 'variation_id' => $var_id, 'quantity' => 1 ) ) );
cic_t_record( 'B wc-ajax', 'POST wc-ajax add_to_cart variation', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/?wc-ajax=checkout', array( 'form' => array( 'billing_email' => 'probe@example.test' ) ) );
cic_t_record( 'B wc-ajax', 'POST ?wc-ajax=checkout', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'POST', '/?wc-ajax=update_order_review' );
cic_t_record( 'B wc-ajax', 'POST ?wc-ajax=update_order_review', cic_t_json403( $r ), "{$r['code']}" );
$r = cic_t_request( 'GET', '/?wc-ajax=get_refreshed_fragments' );
cic_t_record( 'B wc-ajax', 'GET ?wc-ajax=get_refreshed_fragments', cic_t_json403( $r ), "{$r['code']}" );

// ---------------------------------------------------------------- C. legacy admin-ajax.

foreach ( array( 'woocommerce_add_to_cart', 'woocommerce_checkout', 'woocommerce_apply_coupon', 'woocommerce_get_variation' ) as $action ) {
	$r = cic_t_request( 'POST', '/wp-admin/admin-ajax.php', array( 'form' => array( 'action' => $action, 'product_id' => $sid, 'quantity' => 1 ) ) );
	cic_t_record( 'C admin-ajax', "POST admin-ajax {$action}", cic_t_json403( $r ), "{$r['code']}" );
}
$r = cic_t_request( 'POST', '/wp-admin/admin-ajax.php', array( 'form' => array( 'action' => 'heartbeat', 'data' => array() ) ) );
cic_t_record( 'C admin-ajax', 'Control: unrelated nopriv heartbeat still works', 200 === $r['code'], "{$r['code']}" );

// ---------------------------------------------------------------- D. wc-api.

$r = cic_t_request( 'GET', '/?wc-api=WC_Gateway_Paypal' );
cic_t_record( 'D wc-api', 'GET ?wc-api=gateway', 403 === $r['code'], "{$r['code']}" );
$r = cic_t_request( 'GET', '/wc-api/WC_Gateway_Paypal/' );
cic_t_record( 'D wc-api', 'GET /wc-api/gateway/ path form', 403 === $r['code'], "{$r['code']}" );

// ---------------------------------------------------------------- E. Store API.

$add_item = array( 'id' => $sid, 'quantity' => 1 );
$store    = array(
	array( 'GET', '/wp-json/wc/store/v1/cart', null, 'GET cart' ),
	array( 'POST', '/wp-json/wc/store/v1/cart/add-item', $add_item, 'POST cart/add-item' ),
	array( 'POST', '/wp-json/wc/store/v1/cart/add-item', array( 'id' => $var_id, 'quantity' => 1, 'variation' => array( array( 'attribute' => 'Size', 'value' => 'Small' ) ) ), 'POST cart/add-item variation' ),
	array( 'POST', '/wp-json/wc/store/cart/add-item', $add_item, 'POST unversioned /wc/store/cart/add-item' ),
	array( 'POST', '/?rest_route=/wc/store/v1/cart/add-item', $add_item, 'POST ?rest_route= add-item' ),
	array( 'POST', '/wp-json/WC/STORE/V1/CART/ADD-ITEM', $add_item, 'POST upper-case route' ),
	array( 'POST', '/wp-json/wc/store/v2/cart/add-item', $add_item, 'POST future v2 route' ),
	array( 'GET', '/wp-json/wc/store/v1/checkout', null, 'GET checkout (draft order init)' ),
	array( 'POST', '/wp-json/wc/store/v1/checkout', array( 'billing_address' => array( 'email' => 'probe@example.test' ), 'payment_method' => 'cod' ), 'POST checkout' ),
	array( 'POST', '/wp-json/wc/store/v1/order/999999', array( 'billing_email' => 'probe@example.test' ), 'POST order-pay route' ),
	array( 'POST', '/wp-json/wc/store/v1/batch', array( 'requests' => array( array( 'method' => 'POST', 'path' => '/wc/store/v1/cart/add-item', 'body' => $add_item ) ) ), 'POST Store API batch' ),
	array( 'GET', '/wp-json/wc/store/v1/products', null, 'GET products (anonymous read)' ),
);
foreach ( $store as list( $method, $path, $json, $label ) ) {
	$r = cic_t_request( $method, $path, null === $json ? array() : array( 'json' => $json ) );
	cic_t_record( 'E Store API', $label, cic_t_json403( $r ), "{$r['code']}" );
}
$r       = cic_t_request( 'POST', '/wp-json/batch/v1', array( 'json' => array( 'requests' => array( array( 'method' => 'POST', 'path' => '/wc/store/v1/cart/add-item', 'body' => $add_item ) ) ) ) );
$sub_ok  = true;
$sub_txt = '';
foreach ( (array) ( $r['json']['responses'] ?? array() ) as $sub ) {
	$status  = (int) ( $sub['status'] ?? 0 );
	$sub_txt .= " sub={$status}";
	$sub_ok   = $sub_ok && ( $status < 200 || $status >= 300 );
}
cic_t_record( 'E Store API', 'POST core /batch/v1 wrapping add-item', $r['code'] >= 400 || ( $sub_txt && $sub_ok ), "{$r['code']}{$sub_txt}" );

// ---------------------------------------------------------------- F. shopping pages.

foreach ( array( '/cart/', '/checkout/', '/my-account/', '/checkout/order-received/999999/', '/my-account/orders/' ) as $page ) {
	$r = cic_t_request( 'GET', $page );
	cic_t_record( 'F pages', "GET {$page} redirects away", 302 === $r['code'] && false === strpos( $r['location'], $page ), "{$r['code']} -> {$r['location']}" );
}

// ---------------------------------------------------------------- G. public presentation.

$commerce_markers = array( 'woocommerce-Price-amount', 'single_add_to_cart_button', 'add_to_cart_button', 'variations_form', 'wc-block-mini-cart', 'wc-block-components-product-price', 'wc-block-components-product-button', 'cart-fragments', 'add-to-cart-variation' );
foreach ( array( array( $s_url, $simple->get_name() ), array( $v_url, $variable->get_name() ), array( $shop_url, $simple->get_name() ) ) as list( $url, $title ) ) {
	$r     = cic_t_request( 'GET', $url );
	$found = array_filter( $commerce_markers, static fn( $m ) => false !== strpos( $r['body'], $m ) );
	preg_match_all( '#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#s', $r['body'], $ld );
	$offers = false !== strpos( implode( '', $ld[1] ), '"offers"' );
	$titled = false !== strpos( $r['body'], esc_html( $title ) );
	cic_t_record( 'G presentation', "GET {$url} renders without price/cart UI or Offer schema", 200 === $r['code'] && $titled && ! $found && ! $offers, "{$r['code']}" . ( $titled ? '' : ' title-missing' ) . ( $found ? ' found:' . implode( ',', $found ) : '' ) . ( $offers ? ' offers-in-schema' : '' ) );
}
$r = cic_t_request( 'GET', '/wp-json/wp/v2/pages?per_page=1' );
cic_t_record( 'G presentation', 'Control: unrelated core REST still works', 200 === $r['code'], "{$r['code']}" );
$sitemap_args = apply_filters( 'wp_sitemaps_posts_query_args', array(), 'page' );
$excluded     = array_map( 'intval', (array) ( $sitemap_args['post__not_in'] ?? array() ) );
$need         = array_filter( array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) ), static fn( $id ) => $id > 0 );
cic_t_record( 'G presentation', 'Cart/checkout/account excluded from sitemap query', ! array_diff( $need, $excluded ), 'excluded=' . implode( ',', $excluded ) );
cic_t_record( 'G presentation', 'Products report non-purchasable server-side', ! wc_get_product( $sid )->is_purchasable() && ! wc_get_product( $var_id )->is_purchasable(), 'simple/variation is_purchasable=false' );

// ---------------------------------------------------------------- H. authenticated product administration.

if ( ! $anon_only ) {
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
	if ( ! $admin ) {
		cic_t_record( 'H admin', 'Administrator account available', false, 'none found' );
	} else {
		// Application password for REST (revoked at the end; never printed).
		$app_ok = wp_is_application_passwords_available();
		cic_t_record( 'H admin', 'Application passwords available locally', $app_ok, $app_ok ? 'yes' : 'no' );
		if ( $app_ok ) {
			list( $app_pass, $app_item ) = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'cic-phase01-test' ) );
			$auth = array( 'Authorization' => 'Basic ' . base64_encode( $admin->user_login . ':' . $app_pass ) ); // phpcs:ignore
			$o    = array( 'headers' => $auth, 'authenticated' => true );

			$r = cic_t_request( 'GET', "/wp-json/wc/v3/products/{$sid}", $o );
			cic_t_record( 'H admin', 'wc/v3 GET product (authenticated)', 200 === $r['code'], "{$r['code']}" );
			$marker = 'Phase 01 admin edit check ' . wp_generate_password( 6, false );
			$r      = cic_t_request( 'PUT', "/wp-json/wc/v3/products/{$sid}", $o + array( 'json' => array( 'short_description' => $marker ) ) );
			clean_post_cache( $sid );
			$saved = wc_get_product( $sid )->get_short_description();
			cic_t_record( 'H admin', 'wc/v3 PUT product edit persists', 200 === $r['code'] && $saved === $marker, "{$r['code']}" );
			$r      = cic_t_request( 'POST', '/wp-json/wc/v3/products', $o + array( 'json' => array( 'name' => '[Fixture] Phase 01 REST-created draft', 'status' => 'draft', 'meta_data' => array( array( 'key' => '_cic_fixture', 'value' => 'phase01' ) ) ) ) );
			$new_id = (int) ( $r['json']['id'] ?? 0 );
			cic_t_record( 'H admin', 'wc/v3 POST create product', 201 === $r['code'] && $new_id > 0, "{$r['code']} id={$new_id}" );
			if ( $new_id ) {
				$r = cic_t_request( 'DELETE', "/wp-json/wc/v3/products/{$new_id}?force=true", $o );
				cic_t_record( 'H admin', 'wc/v3 DELETE product', 200 === $r['code'], "{$r['code']}" );
			}
			$r = cic_t_request( 'GET', '/wp-json/wc/store/v1/products', $o );
			cic_t_record( 'H admin', 'Store API product read allowed for product editors', 200 === $r['code'], "{$r['code']}" );
			$r = cic_t_request( 'POST', '/wp-json/wc/store/v1/cart/add-item', $o + array( 'json' => $add_item ) );
			cic_t_record( 'H admin', 'Store API add-item denied even for admin', cic_t_json403( $r ), "{$r['code']}" );

			WP_Application_Passwords::delete_application_password( $admin->ID, $app_item['uuid'] );
		}

		// Cookie session for wp-admin screens (token destroyed at the end; never printed).
		$manager = WP_Session_Tokens::get_instance( $admin->ID );
		$expires = time() + HOUR_IN_SECONDS;
		$token   = $manager->create( $expires );
		$c       = array(
			SECURE_AUTH_COOKIE => wp_generate_auth_cookie( $admin->ID, $expires, 'secure_auth', $token ),
			LOGGED_IN_COOKIE   => wp_generate_auth_cookie( $admin->ID, $expires, 'logged_in', $token ),
		);
		$o       = array( 'cookies' => $c, 'authenticated' => true );

		$r = cic_t_request( 'GET', '/wp-admin/edit.php?post_type=product', $o );
		cic_t_record( 'H admin', 'wp-admin Products list loads', 200 === $r['code'] && false !== strpos( $r['body'], 'Catalogue mode is active' ), "{$r['code']}" );
		$r = cic_t_request( 'GET', "/wp-admin/post.php?post={$vid}&action=edit", $o );
		cic_t_record( 'H admin', 'wp-admin product edit screen loads', 200 === $r['code'] && false !== strpos( $r['body'], 'woocommerce-product-data' ), "{$r['code']}" );
		if ( preg_match( '/"load_variations_nonce":"([a-f0-9]+)"/', $r['body'], $m ) ) {
			$r = cic_t_request( 'POST', '/wp-admin/admin-ajax.php', $o + array( 'form' => array( 'action' => 'woocommerce_load_variations', 'security' => $m[1], 'product_id' => $vid, 'attributes' => array(), 'page' => 1, 'per_page' => 10 ) ) );
			cic_t_record( 'H admin', 'Admin variation editor AJAX (priv) still works', 200 === $r['code'] && false !== strpos( $r['body'], 'woocommerce_variation' ), "{$r['code']}" );
		} else {
			cic_t_record( 'H admin', 'Admin variation editor AJAX (priv) still works', false, 'nonce not found on edit screen' );
		}
		$r = cic_t_request( 'GET', '/wp-admin/admin.php?page=wc-settings', $o );
		cic_t_record( 'H admin', 'WooCommerce settings screen loads', 200 === $r['code'], "{$r['code']}" );

		$manager->destroy( $token );
	}
}

// ---------------------------------------------------------------- I. side effects.

$db_after   = cic_t_db_counts();
$mail_after = cic_t_mail_count();
foreach ( $db_before as $key => $before ) {
	cic_t_record( 'I side effects', "No new rows: {$key}", $db_after[ $key ] === $before, "{$before} -> {$db_after[$key]}" );
}
cic_t_record( 'I side effects', 'No email sent (Mailpit count unchanged)', $mail_before >= 0 && $mail_after === $mail_before, "{$mail_before} -> {$mail_after}" );
$wc_cookies = array_unique( array_filter( $cookies, static fn( $n ) => 0 === strpos( $n, 'wp_woocommerce_session_' ) || 0 === strpos( $n, 'woocommerce_' ) ) );
cic_t_record( 'I side effects', 'No WooCommerce session/cart cookies issued', ! $wc_cookies, $wc_cookies ? implode( ',', $wc_cookies ) : 'none' );

clearstatcache();
$new_log = ( is_file( $log_file ) && filesize( $log_file ) > $log_start ) ? (string) file_get_contents( $log_file, false, null, $log_start ) : '';
$issues  = array_filter( explode( "\n", $new_log ), static fn( $l ) => '' !== trim( $l ) && false === strpos( $l, 'wp-cli.phar' ) && preg_match( '/PHP (Fatal|Warning|Parse|Notice|Deprecated)/', $l ) );
cic_t_record( 'I side effects', 'No new site PHP errors in debug log', ! array_filter( $issues, static fn( $l ) => (bool) preg_match( '/PHP (Fatal|Warning|Parse)/', $l ) ), count( $issues ) . ' notices/deprecations' );
foreach ( array_slice( array_unique( array_map( static fn( $l ) => preg_replace( '/^\[[^\]]+\]\s*/', '', $l ), $issues ) ), 0, 5 ) as $line ) {
	WP_CLI::log( '  log: ' . substr( $line, 0, 220 ) );
}

// ---------------------------------------------------------------- report.

$fail = 0;
foreach ( $results as $row ) {
	$fail += $row['pass'] ? 0 : 1;
	WP_CLI::log( sprintf( '%s  %-15s %-62s %s', $row['pass'] ? 'PASS' : 'FAIL', $row['group'], $row['label'], $row['detail'] ) );
}
$total = count( $results );
WP_CLI::log( "Summary [{$context}]: " . ( $total - $fail ) . "/{$total} passed" );
if ( $fail ) {
	WP_CLI::halt( 1 );
}
