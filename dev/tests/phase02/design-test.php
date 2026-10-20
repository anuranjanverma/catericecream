<?php
/**
 * Phase 02 server-side checks: schema/theme.json parity, defaults, validation, inheritance by
 * reference, overrides, import/export, contrast, contact and layout sanitizers.
 * Every option touched is snapshotted first and restored at the end.
 *
 * Usage: wp eval-file dev/tests/phase02/design-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Design\Admin\DesignPage;
use CIC\Design\Contrast;
use CIC\Design\Overrides;
use CIC\Design\Schema;
use CIC\Design\Settings;
use CIC\Design\Stylesheet;
use CIC\Design\ThemeJson;
use CIC\Site\Contact;
use CIC\Site\Layout;

global $cic_results;
$cic_results = array( 'pass' => 0, 'fail' => 0 );

function cic_check( string $label, bool $ok, string $detail = '' ): void {
	global $cic_results;
	$cic_results[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail && ! $ok ? ' — ' . $detail : '' ) );
}

function cic_save_design( array $input ): array {
	$errors = array();
	$clean  = Settings::sanitize( $input, Settings::saved(), $errors );
	update_option( Settings::OPTION, $clean );
	Settings::flush_cache();
	Stylesheet::reset();
	wp_clean_theme_json_cache();
	return $errors;
}

function cic_global_css(): string {
	wp_clean_theme_json_cache();
	return wp_get_global_stylesheet( array( 'variables' ) );
}

$snapshot = array();
foreach ( array( Settings::OPTION, Layout::OPTION, CIC\Config::OPTION_SETTINGS ) as $opt ) {
	$snapshot[ $opt ] = get_option( $opt, null );
}

try {
	delete_option( Settings::OPTION );
	Settings::flush_cache();

	WP_CLI::log( '== A. theme.json parity and approved defaults' );
	$theme = json_decode( file_get_contents( get_template_directory() . '/theme.json' ), true );
	$gen   = ThemeJson::theme_json_settings( Schema::defaults() );
	cic_check( 'A1 theme.json palette equals Schema defaults', $theme['settings']['color']['palette'] === $gen['settings']['color']['palette'] );
	cic_check( 'A2 theme.json font sizes equal Schema scale', $theme['settings']['typography']['fontSizes'] === $gen['settings']['typography']['fontSizes'] );
	cic_check( 'A3 theme.json custom tokens equal Schema', $theme['settings']['custom'] === $gen['settings']['custom'] );
	cic_check( 'A4 theme.json layout widths equal Schema', $theme['settings']['layout'] === $gen['settings']['layout'] );
	$t = Settings::resolved();
	$spec = array( 'brand_raspberry' => '#DE2879', 'action_primary' => '#D92373', 'action_hover' => '#BC1F64', 'text_primary' => '#503564', 'strawberry' => '#F3A2BD', 'lavender' => '#B9A0DD', 'surface_soft' => '#F3EFF9', 'surface_base' => '#FFFFFF', 'birthday_sky' => '#63C9EB', 'birthday_mint' => '#8CDCBF', 'birthday_violet' => '#7652B8', 'wedding_ivory' => '#FFFDF8', 'wedding_gold' => '#B29458', 'wedding_blush' => '#F5E7E8' );
	cic_check( 'A5 spec §5 colour values are the defaults', array_intersect_key( $t['colors'], $spec ) == $spec );
	cic_check( 'A6 fonts default to Fraunces 700 / DM Sans 400-500-700', 'fraunces' === $t['scalars']['font_heading'] && 'dm-sans' === $t['scalars']['font_body'] && 700 === $t['scalars']['heading_weight'] && 400 === $t['scalars']['body_weight'] && 500 === $t['scalars']['ui_weight'] && 700 === $t['scalars']['strong_weight'] );
	$scale = array( 'h1' => array( 40, 64, 44, 70 ), 'h2' => array( 32, 42, 38, 48 ), 'h3' => array( 24, 28, 30, 34 ), 'body' => array( 16, 18, 26, 28 ) );
	$ok = true;
	foreach ( $scale as $role => list( $min, $max, $lhmin, $lhmax ) ) {
		$ok = $ok && $t['type'][ $role ] === array( 'min' => $min, 'max' => $max, 'lh_min' => $lhmin, 'lh_max' => $lhmax );
	}
	cic_check( 'A7 spec type scale (H1 64/70–40/44, H2 42/48–32/38, H3 28/34–24/30, body 18/28–16/26)', $ok );
	cic_check( 'A8 Wedding preset: weight 600 + italic accents, inherits the heading family', 600 === $t['contexts']['wedding']['heading_weight'] && 'italic' === $t['contexts']['wedding']['heading_accent_style'] && ! isset( $t['contexts']['wedding']['font_heading'] ) );
	cic_check( 'A9 Birthday preset references central sky/mint/violet tokens', 'birthday_sky' === $t['contexts']['birthday']['roles']['accent'] && 'birthday_mint' === $t['contexts']['birthday']['roles']['accent_2'] && 'birthday_violet' === $t['contexts']['birthday']['roles']['decor'] );

	WP_CLI::log( '== B. Contrast maths and default warnings' );
	cic_check( 'B1 white on action pink ≈ 4.73:1 (spec)', abs( Contrast::ratio( '#FFFFFF', '#D92373' ) - 4.73 ) < 0.02, (string) Contrast::ratio( '#FFFFFF', '#D92373' ) );
	cic_check( 'B2 raspberry on white ≈ 4.48:1 (spec)', abs( Contrast::ratio( '#DE2879', '#FFFFFF' ) - 4.48 ) < 0.02, (string) Contrast::ratio( '#DE2879', '#FFFFFF' ) );
	$fails = Contrast::failures();
	cic_check( 'B3 default configuration has no failing text pairs', 0 === count( $fails ), wp_json_encode( $fails ) );
	WP_CLI::log( '     ratios: muted/white ' . Contrast::ratio( '#6B587A', '#FFFFFF' ) . ', plum/ivory ' . Contrast::ratio( '#503564', '#FFFDF8' ) . ', link/ivory ' . Contrast::ratio( '#D92373', '#FFFDF8' ) . ', white/hover ' . Contrast::ratio( '#FFFFFF', '#BC1F64' ) );

	WP_CLI::log( '== C. Validation (bounded, allowlisted, no CSS injection)' );
	$e = array();
	$c = Settings::sanitize( array( 'colors' => array( 'action_primary' => '#1f6fb2', 'lavender' => '#B9A0DD' ) ), array(), $e );
	cic_check( 'C1 valid hex normalised to uppercase and stored', '#1F6FB2' === ( $c['colors']['action_primary'] ?? null ) && ! $e );
	cic_check( 'C2 value equal to default is not stored (sparse)', ! isset( $c['colors']['lavender'] ) );
	$e = array();
	$c = Settings::sanitize( array( 'colors' => array( 'action_primary' => 'red;}body{display:none}' ) ), array( 'colors' => array( 'action_primary' => '#123456' ) ), $e );
	cic_check( 'C3 CSS injection in a colour is rejected and previous value kept', '#123456' === ( $c['colors']['action_primary'] ?? null ) && 1 === count( $e ) );
	$e = array();
	$c = Settings::sanitize( array( 'scalars' => array( 'content_width' => '5000', 'radius_card' => '12.4', 'font_body' => 'comic-sans', 'font_heading' => 'url(evil.woff)' ) ), array(), $e );
	cic_check( 'C4 out-of-range number rejected', ! isset( $c['scalars']['content_width'] ) );
	cic_check( 'C5 number rounded to its step', 12 === ( $c['scalars']['radius_card'] ?? null ) );
	cic_check( 'C6 non-approved font families rejected', ! isset( $c['scalars']['font_body'] ) && ! isset( $c['scalars']['font_heading'] ) && 3 === count( $e ), implode( ' | ', $e ) );
	$e = array();
	$c = Settings::sanitize( array( 'scalars' => array( 'heading_weight' => '400' ) ), array(), $e );
	cic_check( 'C7 heading weight not bundled for Fraunces is corrected to 600 with a message', 600 === ( $c['scalars']['heading_weight'] ?? null ) && 1 === count( $e ) );
	$e = array();
	$c = Settings::sanitize( array( 'type' => array( 'h1' => array( 'min' => '70', 'max' => '64' ), 'h2' => array( 'lh_max' => '20' ), 'h3' => array( 'min' => '999' ) ) ), array(), $e );
	cic_check( 'C8 mobile larger than desktop rejected', ! isset( $c['type']['h1'] ) );
	cic_check( 'C9 line height below font size rejected', ! isset( $c['type']['h2'] ) );
	cic_check( 'C10 type size outside 10–120 rejected', ! isset( $c['type']['h3'] ) && 3 === count( $e ) );
	$e = array();
	$c = Settings::sanitize( array( 'contexts' => array( 'birthday' => array( 'roles' => array( 'accent' => 'lavender', 'decor' => 'brand', 'action' => 'custom', 'link' => 'javascript:alert(1)' ), 'custom' => array( 'action' => '#2a9d8f' ) ), 'wedding' => array( 'heading_weight' => '500' ) ) ), array(), $e );
	cic_check( 'C11 preset roles accept token, "brand" and custom hex', 'lavender' === $c['contexts']['birthday']['roles']['accent'] && 'brand' === $c['contexts']['birthday']['roles']['decor'] && '#2A9D8F' === $c['contexts']['birthday']['roles']['action'] );
	cic_check( 'C12 preset junk values and unbundled weights rejected', ! isset( $c['contexts']['birthday']['roles']['link'] ) && ! isset( $c['contexts']['wedding']['heading_weight'] ) && 2 === count( $e ) );

	WP_CLI::log( '== D. Inheritance by reference (global change propagates, local stays local)' );
	cic_save_design( array( 'colors' => array( 'action_primary' => '#1F6FB2' ), 'contexts' => array( 'corporate' => array( 'roles' => array( 'action' => 'custom' ), 'custom' => array( 'action' => '#2A9D8F' ) ) ) ) );
	$css = Stylesheet::css();
	$gcss = cic_global_css();
	cic_check( 'D1 global stylesheet now prints the new action colour', str_contains( $gcss, '--wp--preset--color--action-primary: #1F6FB2' ) );
	$inherit_ok = true;
	foreach ( array_keys( Schema::contexts() ) as $ctx ) {
		if ( 'corporate' === $ctx ) {
			continue;
		}
		$sel = 'brand' === $ctx ? ':root,.cic-context-brand{' : '.cic-context-' . $ctx . '{';
		preg_match( '/' . preg_quote( $sel, '/' ) . '([^}]*)\}/', $css, $m );
		$inherit_ok = $inherit_ok && str_contains( $m[1] ?? '', '--cic-color-action:var(--wp--preset--color--action-primary)' );
	}
	cic_check( 'D2 every inheriting preset references the action token (no frozen copies)', $inherit_ok );
	cic_check( 'D3 explicit Corporate custom action stays local', (bool) preg_match( '/\.cic-context-corporate\{[^}]*--cic-color-action:#2A9D8F/', $css ) );
	cic_check( 'D4 no hex values in inheriting preset rules (only the explicit custom one)', 1 === preg_match_all( '/#[0-9A-F]{6}/i', $css ) );
	cic_check( 'D5 presets never set a font family', ! str_contains( $css, 'font-family' ) && ! str_contains( $css, '--cic-font-' ) );
	cic_save_design( array( 'colors' => array( 'action_primary' => '#1F6FB2' ), 'scalars' => array( 'font_body' => 'system-sans' ) ) );
	cic_check( 'D6 global body font change reaches the editor/frontend custom property', str_contains( cic_global_css(), '--wp--custom--cic--font--body: var(--wp--preset--font-family--system-sans)' ) );
	cic_save_design( array() );
	cic_check( 'D7 reset (empty settings) restores approved defaults', str_contains( cic_global_css(), '--wp--preset--color--action-primary: #D92373' ) && str_contains( cic_global_css(), '--wp--custom--cic--font--body: var(--wp--preset--font-family--dm-sans)' ) );

	WP_CLI::log( '== E. Local overrides (page / section / element)' );
	$e = array();
	$o = Overrides::sanitize( array( 'action' => 'lavender', 'heading' => '#abc', 'surface' => 'expression(alert(1))', 'h1_min' => '30', 'h1_max' => '20', 'font_heading' => 'system-serif', 'heading_weight' => '800', 'context' => 'wedding', 'bogus' => 'x' ), Schema::scope_fields(), $e );
	cic_check( 'E1 override accepts token, short hex, preset and approved font', 'lavender' === $o['action'] && '#AABBCC' === $o['heading'] && 'wedding' === $o['context'] && 'system-serif' === $o['font_heading'] );
	cic_check( 'E2 override rejects injection, unknown keys, unbundled weight and min>max sizes', ! isset( $o['surface'], $o['bogus'], $o['heading_weight'], $o['h1_min'], $o['h1_max'] ) && 3 === count( $e ), implode( ' | ', $e ) );
	$d = Overrides::declarations( array( 'heading' => '#AABBCC', 'size_min' => 30 ), 'heading', 'h2' );
	cic_check( 'E3 element override emits only scoped custom properties', in_array( '--cic-color-heading:#AABBCC', $d['decls'], true ) && in_array( '--cic-fs-min:30', $d['decls'], true ) && in_array( '--cic-fs-max:var(--cic-fs-h2-max)', $d['decls'], true ) && in_array( 'cic-has-size', $d['classes'], true ) );
	$html  = do_blocks( '<!-- wp:heading {"cicStyle":{"heading":"#1F6F8B","size_min":30}} --><h2 class="wp-block-heading">A</h2><!-- /wp:heading --><!-- wp:heading --><h2 class="wp-block-heading">B</h2><!-- /wp:heading --><!-- wp:heading {"cicStyle":{"heading":"lavender"}} --><h2 class="wp-block-heading">C</h2><!-- /wp:heading -->' );
	cic_check( 'E4 render_block adds the override to that instance only', 2 === substr_count( $html, 'cic-has-override' ) && str_contains( $html, 'style="--cic-color-heading:#1F6F8B;' ) && str_contains( $html, '<h2 class="wp-block-heading">B</h2>' ) );
	cic_check( 'E5 two instances of the same block differ', str_contains( $html, '--cic-color-heading:var(--wp--preset--color--lavender)' ) );
	$html = do_blocks( '<!-- wp:heading {"cicStyle":{"heading":"red;}body{x:y"}} --><h2 class="wp-block-heading">X</h2><!-- /wp:heading -->' );
	cic_check( 'E6 invalid block attribute values are dropped server-side', ! str_contains( $html, 'red;' ) && ! str_contains( $html, 'cic-has-override' ) );
	$html = do_blocks( '<!-- wp:group {"cicStyle":{"context":"birthday","action":"#2A9D8F"}} --><div class="wp-block-group"><p>x</p></div><!-- /wp:group -->' );
	cic_check( 'E7 section override sets preset classes and scoped variables', str_contains( $html, 'cic-ctx' ) && str_contains( $html, 'cic-context-birthday' ) && str_contains( $html, '--cic-color-action:#2A9D8F' ) );
	$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'CIC phase02 unit fixture', 'meta_input' => array( '_cic_fixture' => 'phase02' ) ) );
	update_post_meta( $pid, Overrides::META, array( 'heading_accent' => '#2E7D32', 'h1_min' => '36', 'junk' => '1' ) );
	$saved_meta = get_post_meta( $pid, Overrides::META, true );
	cic_check( 'E8 page meta is sanitized on save (registered meta)', array( 'heading_accent' => '#2E7D32', 'h1_min' => 36 ) === $saved_meta, wp_json_encode( $saved_meta ) );
	$pcss = Overrides::page_css( $pid );
	cic_check( 'E9 page CSS is scoped to that page', str_starts_with( trim( substr( $pcss, strpos( $pcss, "\n" ) ) ), 'body.cic-page-' . $pid . ',body.cic-page-' . $pid . ' .cic-ctx{' ) && str_contains( $pcss, '--cic-fs-h1-min:36' ) && str_contains( $pcss, '--cic-fs-h1-lh-min:40' ) );
	delete_post_meta( $pid, Overrides::META );
	cic_check( 'E10 deleting the page override leaves no page CSS (inherits again)', '' === Overrides::page_css( $pid ) );
	wp_delete_post( $pid, true );

	WP_CLI::log( '== F. Export / import' );
	cic_save_design( array( 'colors' => array( 'lavender' => '#A58BD6' ), 'type' => array( 'h1' => array( 'max' => '60' ) ) ) );
	$payload = DesignPage::export_payload();
	list( $clean, $errors ) = DesignPage::validate_import( wp_json_encode( $payload ) );
	cic_check( 'F1 export → import round trip is lossless', $clean === Settings::saved() && ! $errors );
	$payload['settings']['colors']['action_primary'] = 'url(javascript:alert(1))';
	list( $clean, $errors ) = DesignPage::validate_import( wp_json_encode( $payload ) );
	cic_check( 'F2 an import with any invalid value is rejected entirely', null === $clean && 1 === count( $errors ) );
	list( $clean ) = DesignPage::validate_import( '{"format":"other"}' );
	cic_check( 'F3 foreign JSON rejected', null === $clean );
	cic_save_design( array() );

	WP_CLI::log( '== G. Competing style sources are closed' );
	$user = WP_Theme_JSON_Resolver::get_user_data()->get_raw_data();
	cic_check( 'G1 user Global Styles are ignored while the theme is active', empty( $user['settings'] ) && empty( $user['styles'] ) );
	$editor = apply_filters( 'block_editor_settings_all', array(), new WP_Block_Editor_Context() );
	cic_check( 'G2 Font Library disabled in the editor', isset( $editor['fontLibraryEnabled'] ) && false === $editor['fontLibraryEnabled'] );
	$settings = wp_get_global_settings();
	cic_check( 'G3 custom colours, custom font sizes and free spacing are disabled', false === $settings['color']['custom'] && false === $settings['typography']['customFontSize'] && false === $settings['spacing']['padding'] );

	WP_CLI::log( '== H. Contact and header/footer settings' );
	cic_check( 'H1 tel: link from display phone', 'tel:+18187269700' === Contact::tel_href( '(818) 726-9700' ) );
	$e = array();
	$cc = Contact::sanitize( array( 'business_phone' => '(818) 555-0100', 'business_email' => 'not-an-email', 'quote_url' => 'javascript:alert(1)', 'social_instagram' => 'http://insecure.example', 'call_label' => '<b>Call</b>' ), $e );
	cic_check( 'H2 contact: valid phone saved, bad email/link/social rejected', '(818) 555-0100' === $cc['business_phone'] && 3 === count( $e ) && 'Call' === $cc['call_label'], implode( ' | ', $e ) );
	cic_check( 'H3 private operator recipient is preserved untouched', ( $snapshot[ CIC\Config::OPTION_SETTINGS ]['operator_recipient'] ?? CIC\Config::defaults()['operator_recipient'] ) === ( $cc['operator_recipient'] ?? CIC\Config::defaults()['operator_recipient'] ) );
	$e = array();
	$l = Layout::sanitize( array( 'main' => array( 'logo_width' => '999', 'logo_id' => '999999', 'sticky' => '' ), 'top' => array( 'surface' => 'not-a-token', 'text' => '<script>x</script>Hi' ), 'footer' => array( 'layout' => 'evil' ) ), $e );
	cic_check( 'H4 layout: bounded widths, real images only, allowlisted tokens/layouts, plain text', Layout::defaults()['main']['logo_width'] === $l['main']['logo_width'] && 0 === $l['main']['logo_id'] && false === $l['main']['sticky'] && 'text_primary' === $l['top']['surface'] && 'Hi' === $l['top']['text'] && 'brand-menu-contact' === $l['footer']['layout'] && 3 === count( $e ), implode( ' | ', $e ) );
} finally {
	foreach ( $snapshot as $opt => $value ) {
		if ( null === $value ) {
			delete_option( $opt );
		} else {
			update_option( $opt, $value );
		}
	}
	Settings::flush_cache();
	Stylesheet::reset();
	wp_clean_theme_json_cache();
}

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic_results['pass'], $cic_results['fail'] ) );
