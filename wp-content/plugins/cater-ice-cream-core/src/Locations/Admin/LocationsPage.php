<?php
/**
 * Service Areas admin (capability manage_locations; every action nonce-checked, validated in
 * CityStore, audited and cache-invalidating):
 * - Cities: searchable/filterable list with Edit city / content / Preview / View actions, and
 *   Add City (always Draft + Noindex, no ZIPs, so coverage is never approved by creating it).
 * - City editor: details and slug (old slug kept as a redirect), publication and search-engine
 *   settings, ZIP coverage (ZIP-wide status vs this city's decision, clearly separated), adding
 *   ZIPs with a check step that lists other cities sharing them, and removing a relationship.
 * - Import: upload a UTF-8 CSV, review the dry run (including conflicts with manual edits), apply.
 * - Log: audit trail and recent imports.
 * Fixture rows are labelled everywhere; they are local test data, not approved coverage.
 *
 * @package CIC
 */

namespace CIC\Locations\Admin;

use CIC\Locations\CityStore;
use CIC\Locations\Coverage;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Importer;
use CIC\Locations\Labels;
use CIC\Locations\Service;
use CIC\Locations\Tables;

defined( 'ABSPATH' ) || exit;

final class LocationsPage {

	public const SLUG = 'cic-locations';

	/** Joins notice lines (redirect URLs cannot carry new lines; shown as line breaks). */
	private const SEP = " • ";

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_cic_city_save', array( $this, 'save_city' ) );
		add_action( 'admin_post_cic_city_create', array( $this, 'create_city' ) );
		add_action( 'admin_post_cic_city_zips_add', array( $this, 'add_zips' ) );
		add_action( 'admin_post_cic_city_zip_unlink', array( $this, 'unlink_zip' ) );
		add_action( 'admin_post_cic_city_editorial', array( $this, 'create_editorial' ) );
		add_action( 'admin_post_cic_location_import', array( $this, 'import' ) );
	}

	public function menu(): void {
		add_menu_page( __( 'Service Areas', 'cater-ice-cream-core' ), __( 'Service Areas', 'cater-ice-cream-core' ), Tables::CAPABILITY, self::SLUG, array( $this, 'render_cities' ), 'dashicons-location', 26 );
		add_submenu_page( self::SLUG, __( 'Cities', 'cater-ice-cream-core' ), __( 'Cities', 'cater-ice-cream-core' ), Tables::CAPABILITY, self::SLUG, array( $this, 'render_cities' ) );
		add_submenu_page( self::SLUG, __( 'Import locations', 'cater-ice-cream-core' ), __( 'Import', 'cater-ice-cream-core' ), Tables::CAPABILITY, self::SLUG . '-import', array( $this, 'render_import' ) );
		add_submenu_page( self::SLUG, __( 'Location log', 'cater-ice-cream-core' ), __( 'Log', 'cater-ice-cream-core' ), Tables::CAPABILITY, self::SLUG . '-log', array( $this, 'render_log' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function deny(): void {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
	}

	private static function fixture_badge(): string {
		return '<span class="cic-badge" style="display:inline-block;padding:0 6px;border-radius:3px;background:#fcf0c3;color:#5c4400;font-size:11px;font-weight:600">' . esc_html__( 'FIXTURE — local test data', 'cater-ice-cream-core' ) . '</span>';
	}

	private static function pill( string $text, string $tone = 'neutral' ): string {
		return '<span class="cic-pill cic-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	/** Admin-only presentation (WordPress admin colours; the public design system is untouched). */
	private static function styles(): void {
		echo '<style>
.cic-pill{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;line-height:18px;background:#f0f0f1;color:#3c434a;white-space:nowrap}
.cic-pill--good{background:#edfaef;color:#00450c}.cic-pill--warn{background:#fcf9e8;color:#614200}.cic-pill--bad{background:#fcf0f1;color:#8a2424}
.cic-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}.cic-actions form{margin:0}
.cic-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:16px 0}
.cic-card{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 14px}.cic-card h3{margin:0 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:#50575e}
.cic-card p{margin:6px 0 0}.cic-card ul{margin:6px 0 0 1.2em;list-style:disc}
.cic-section{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:4px 16px 16px;margin:16px 0}
.cic-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin:8px 0 12px}
.cic-legend div{border-left:4px solid #72aee6;padding:4px 10px;background:#f6f7f7}.cic-legend div.cic-legend--city{border-color:#dba617}
.cic-zip-table select{max-width:100%}.cic-zip-table td{vertical-align:top}.cic-help{display:block;color:#646970;font-size:12px;margin-top:3px}
.cic-scope-warn{display:block;color:#8a2424;font-size:12px;margin-top:3px}
.cic-plan td{vertical-align:top}.cic-plan + form,.cic-plan + p{margin-top:12px}.cic-row-actions{font-size:12px;margin-top:3px}.cic-row-actions a{text-decoration:none}
</style>';
	}

	private function notices(): void {
		$msg = isset( $_GET['cic_msg'] ) ? sanitize_textarea_field( wp_unslash( $_GET['cic_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$err = isset( $_GET['cic_err'] ) ? sanitize_textarea_field( wp_unslash( $_GET['cic_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . str_replace( esc_html( self::SEP ), "<br>", esc_html( $msg ) ) . '</p></div>';
		}
		if ( '' !== $err ) {
			echo '<div class="notice notice-error"><p>' . str_replace( esc_html( self::SEP ), "<br>", esc_html( $err ) ) . '</p></div>';
		}
		if ( ! Tables::ready() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Location tables are not installed yet. Reload this screen to run the pending database update.', 'cater-ice-cream-core' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Cities
	 * ------------------------------------------------------------------ */

	public function render_cities(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage locations.', 'cater-ice-cream-core' ) );
		}
		$city_id = isset( $_GET['city'] ) ? absint( $_GET['city'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap">';
		self::styles();
		if ( 'new' === $action && Tables::ready() ) {
			$this->render_new_city();
		} elseif ( $city_id && Tables::ready() ) {
			$this->render_city( $city_id );
		} else {
			$this->render_city_list();
		}
		echo '</div>';
	}

	/** @param array<string, mixed> $city City. */
	private static function content_status( array $city ): string {
		$editorial = CityStore::editorial_id( (int) $city['id'] );
		if ( ! $editorial || ! get_post( $editorial ) ) {
			return __( 'Template only', 'cater-ice-cream-core' );
		}
		$count = Editorial::override_count( $editorial );
		/* translators: 1: overrides, 2: record status. */
		return sprintf( _n( '%1$d custom field (%2$s)', '%1$d custom fields (%2$s)', $count['overrides'], 'cater-ice-cream-core' ), $count['overrides'], 'publish' === get_post_status( $editorial ) ? __( 'published', 'cater-ice-cream-core' ) : __( 'draft', 'cater-ice-cream-core' ) );
	}

	/** @param array<string, mixed> $city City. */
	private static function content_link( array $city ): string {
		$editorial = CityStore::editorial_id( (int) $city['id'] );
		if ( $editorial && get_post( $editorial ) ) {
			return '<a href="' . esc_url( get_edit_post_link( $editorial, 'url' ) ) . '">' . esc_html__( 'Edit content', 'cater-ice-cream-core' ) . '</a>';
		}
		$url = wp_nonce_url( add_query_arg( array( 'action' => 'cic_city_editorial', 'city_id' => (int) $city['id'] ), admin_url( 'admin-post.php' ) ), 'cic_city_editorial_' . (int) $city['id'] );
		return '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Create content', 'cater-ice-cream-core' ) . '</a>';
	}

	private static function coverage_tone( string $status ): string {
		return array( 'covered' => 'good', 'outside_area' => 'bad', 'manual_review' => 'warn' )[ $status ] ?? 'neutral';
	}

	private function render_city_list(): void {
		global $wpdb;
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Service area cities', 'cater-ice-cream-core' ) . '</h1> ';
		if ( Tables::ready() ) {
			echo '<a class="page-title-action" id="cic-add-city" href="' . esc_url( self::url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Add City', 'cater-ice-cream-core' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		$this->notices();
		if ( ! Tables::ready() ) {
			return;
		}
		echo '<p class="description">' . esc_html__( 'A listed city is not a promise that every ZIP is served: coverage comes only from reviewed ZIP decisions. Publishing a page and allowing search engines are separate settings.', 'cater-ice-cream-core' ) . '</p>';
		$s      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$county = isset( $_GET['county'] ) ? sanitize_title( wp_unslash( $_GET['county'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$t      = Tables::name( 'cities' );
		$cz     = Tables::name( 'city_zips' );
		$where  = '1=1';
		$params = array();
		if ( '' !== $s ) {
			$where   .= ' AND name_search LIKE %s';
			$params[] = $wpdb->esc_like( Tables::search_key( $s ) ) . '%';
		}
		if ( '' !== $county ) {
			$where   .= ' AND county_slug = %s';
			$params[] = $county;
		}
		if ( in_array( $status, Tables::PUBLISH_STATUSES, true ) ) {
			$where   .= ' AND publish_status = %s';
			$params[] = $status;
		}
		$per     = 50;
		$count_q = "SELECT COUNT(*) FROM {$t} WHERE {$where}";
		$list_q  = "SELECT c.*, (SELECT COUNT(*) FROM {$cz} m WHERE m.city_id = c.id) AS zip_count FROM {$t} c WHERE {$where} ORDER BY state_code, county_slug, display_order, city_name LIMIT %d OFFSET %d";
		$total   = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_q, $params ) ) : $wpdb->get_var( $count_q ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows    = $wpdb->get_results( $wpdb->prepare( $list_q, array_merge( $params, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counties = $wpdb->get_results( "SELECT DISTINCT county_slug, county_name FROM {$t} ORDER BY county_name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		echo '<form method="get" style="margin:1em 0"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label class="screen-reader-text" for="cic-city-search">' . esc_html__( 'Search cities', 'cater-ice-cream-core' ) . '</label><input type="search" id="cic-city-search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'City name', 'cater-ice-cream-core' ) . '"> ';
		echo '<label class="screen-reader-text" for="cic-county-filter">' . esc_html__( 'County', 'cater-ice-cream-core' ) . '</label><select id="cic-county-filter" name="county"><option value="">' . esc_html__( 'All counties', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $counties as $c ) {
			echo '<option value="' . esc_attr( $c['county_slug'] ) . '"' . selected( $county, $c['county_slug'], false ) . '>' . esc_html( $c['county_name'] ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="cic-status-filter">' . esc_html__( 'Page publication', 'cater-ice-cream-core' ) . '</label><select id="cic-status-filter" name="status"><option value="">' . esc_html__( 'Any page status', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Tables::PUBLISH_STATUSES as $st ) {
			echo '<option value="' . esc_attr( $st ) . '"' . selected( $status, $st, false ) . '>' . esc_html( Labels::publish( $st ) ) . '</option>';
		}
		echo '</select> <button class="button">' . esc_html__( 'Filter', 'cater-ice-cream-core' ) . '</button></form>';

		$heads = array( __( 'City', 'cater-ice-cream-core' ), __( 'County', 'cater-ice-cream-core' ), __( 'Page', 'cater-ice-cream-core' ), __( 'Search engines', 'cater-ice-cream-core' ), __( 'Coverage (whole city)', 'cater-ice-cream-core' ), __( 'ZIPs', 'cater-ice-cream-core' ), __( 'Content', 'cater-ice-cream-core' ), __( 'Data', 'cater-ice-cream-core' ) );
		echo '<table class="widefat striped" id="cic-city-list"><thead><tr>';
		foreach ( $heads as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$repo = new DatabaseRepository();
		foreach ( $rows as $row ) {
			$city    = DatabaseRepository::city( $row );
			$cov     = $repo->city_coverage( $city );
			$idx     = $repo->is_indexable( $city );
			$preview = Editorial::city_preview_url( $city['id'] );
			$links   = array( '<a href="' . esc_url( self::url( array( 'city' => $city['id'] ) ) ) . '">' . esc_html__( 'Edit city', 'cater-ice-cream-core' ) . '</a>', self::content_link( $city ) );
			if ( '' !== $preview ) {
				$links[] = '<a href="' . esc_url( $preview ) . '" target="_blank" rel="noopener">' . esc_html__( 'Preview', 'cater-ice-cream-core' ) . '</a>';
			}
			if ( 'published' === $city['publish_status'] ) {
				$links[] = '<a href="' . esc_url( $city['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'cater-ice-cream-core' ) . '</a>';
			}
			echo '<tr data-city="' . (int) $city['id'] . '"><td><a class="row-title" href="' . esc_url( self::url( array( 'city' => $city['id'] ) ) ) . '">' . esc_html( $city['label'] ) . '</a><div class="cic-row-actions">' . implode( ' | ', $links ) . '</div></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links escaped above.
			echo '<td>' . esc_html( $city['county_name'] ) . '</td>';
			echo '<td>' . self::pill( Labels::publish( $city['publish_status'] ), 'published' === $city['publish_status'] ? 'good' : 'neutral' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . self::pill( Labels::index( $idx, $city['index_status'] ), $idx ? 'good' : 'neutral' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td data-coverage="' . esc_attr( $cov['status'] ) . '">' . self::pill( Labels::coverage( $cov['status'] ), self::coverage_tone( $cov['status'] ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . (int) $row['zip_count'] . '</td>';
			echo '<td>' . esc_html( self::content_status( $city ) ) . '</td>';
			echo '<td>' . ( $city['fixture'] ? self::fixture_badge() : esc_html__( 'Production', 'cater-ice-cream-core' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No cities found. Use “Add City”, or import a reviewed CSV under Service Areas → Import.', 'cater-ice-cream-core' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) ) . '</p>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Add City
	 * ------------------------------------------------------------------ */

	private function render_new_city(): void {
		global $wpdb;
		$old = static fn( string $k ): string => isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- refills the form after a validation error.
		echo '<h1>' . esc_html__( 'Add City', 'cater-ice-cream-core' ) . '</h1>';
		echo '<p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All cities', 'cater-ice-cream-core' ) . '</a></p>';
		$this->notices();
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'A new city starts as a Draft (not public) and Noindex, with no ZIP codes. Creating a city never approves service coverage: add its ZIP codes next and review their coverage.', 'cater-ice-cream-core' ) . '</p></div>';
		$counties = $wpdb->get_col( 'SELECT DISTINCT county_name FROM ' . Tables::name( 'cities' ) . ' ORDER BY county_name' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$base     = home_url( '/service-areas/' );

		echo '<form method="post" id="cic-new-city" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_city_create">';
		wp_nonce_field( 'cic_city_create' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="cic-city_name">' . esc_html__( 'City name', 'cater-ice-cream-core' ) . ' <span aria-hidden="true">*</span></label></th><td><input class="regular-text" type="text" id="cic-city_name" name="city_name" required maxlength="120" value="' . esc_attr( $old( 'city_name' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="cic-state_code">' . esc_html__( 'State', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-state_code" name="state_code">';
		foreach ( Tables::STATES as $slug => $code ) {
			echo '<option value="' . esc_attr( $code ) . '" data-slug="' . esc_attr( $slug ) . '">' . esc_html( Tables::state_name( $code ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="cic-county_name">' . esc_html__( 'County', 'cater-ice-cream-core' ) . ' <span aria-hidden="true">*</span></label></th><td><input class="regular-text" type="text" id="cic-county_name" name="county_name" required maxlength="100" list="cic-counties" value="' . esc_attr( $old( 'county_name' ) ) . '"><datalist id="cic-counties">';
		foreach ( $counties as $c ) {
			echo '<option value="' . esc_attr( $c ) . '">';
		}
		echo '</datalist><p class="description">' . esc_html__( 'Use the same county name as existing cities (for example “Los Angeles County”) so the city appears in that county’s directory.', 'cater-ice-cream-core' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="cic-city_slug">' . esc_html__( 'URL slug', 'cater-ice-cream-core' ) . '</label></th><td><input class="regular-text" type="text" id="cic-city_slug" name="city_slug" maxlength="140" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="' . esc_attr( $old( 'city_slug' ) ) . '"><p class="description">' . esc_html__( 'Generated from the city name; you can edit it. Public address:', 'cater-ice-cream-core' ) . ' <code id="cic-slug-preview">' . esc_html( $base ) . '<span></span>/</code></p></td></tr>';
		echo '<tr><th scope="row"><label for="cic-latitude">' . esc_html__( 'Latitude (optional)', 'cater-ice-cream-core' ) . '</label></th><td><input type="text" id="cic-latitude" name="latitude" inputmode="decimal" value="' . esc_attr( $old( 'latitude' ) ) . '"><p class="description">' . esc_html__( 'Approximate city centre, used only to order nearby cities. Never a venue or customer address.', 'cater-ice-cream-core' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="cic-longitude">' . esc_html__( 'Longitude (optional)', 'cater-ice-cream-core' ) . '</label></th><td><input type="text" id="cic-longitude" name="longitude" inputmode="decimal" value="' . esc_attr( $old( 'longitude' ) ) . '"></td></tr>';
		if ( Tables::fixtures_allowed() ) {
			$ds = 'fixture' === $old( 'dataset' ) ? 'fixture' : 'production';
			echo '<tr><th scope="row"><label for="cic-dataset">' . esc_html__( 'Dataset', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-dataset" name="dataset"><option value="production"' . selected( $ds, 'production', false ) . '>' . esc_html__( 'Production (real city)', 'cater-ice-cream-core' ) . '</option><option value="fixture"' . selected( $ds, 'fixture', false ) . '>' . esc_html__( 'Fixture (local test data only)', 'cater-ice-cream-core' ) . '</option></select><p class="description">' . esc_html__( 'Only shown on local/development sites. Fixture cities are ignored on staging and production.', 'cater-ice-cream-core' ) . '</p></td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Starts as', 'cater-ice-cream-core' ) . '</th><td>' . self::pill( Labels::publish( 'draft' ) ) . ' ' . self::pill( __( 'Noindex', 'cater-ice-cream-core' ) ) . ' ' . self::pill( Labels::coverage( 'unknown' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</table>';
		submit_button( __( 'Create city', 'cater-ice-cream-core' ) );
		echo '</form>';
		// Mirrors sanitize_title for the preview; the server re-sanitises and validates.
		echo "<script>(function(){var n=document.getElementById('cic-city_name'),s=document.getElementById('cic-city_slug'),p=document.querySelector('#cic-slug-preview span'),touched=s.value!=='';function slug(v){return v.normalize('NFD').replace(/[\\u0300-\\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');}function show(){p.textContent='california/'+(s.value||slug(n.value));}n.addEventListener('input',function(){if(!touched){s.value=slug(n.value);}show();});s.addEventListener('input',function(){touched=s.value!=='';show();});show();})();</script>";
	}

	public function create_city(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_create' ) ) {
			self::deny();
		}
		$data = array();
		foreach ( array( 'city_name', 'state_code', 'county_name', 'city_slug', 'latitude', 'longitude', 'dataset' ) as $f ) {
			$data[ $f ] = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '';
		}
		$id = CityStore::create_city( $data );
		if ( is_wp_error( $id ) ) {
			$back = array_intersect_key( $data, array_flip( array( 'city_name', 'county_name', 'city_slug', 'latitude', 'longitude', 'dataset' ) ) );
			wp_safe_redirect( self::url( array_merge( array( 'action' => 'new', 'cic_err' => rawurlencode( $id->get_error_message() ) ), array_map( 'rawurlencode', $back ) ) ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'city' => $id, 'cic_msg' => rawurlencode( __( 'City created as a Draft with Noindex. It has no ZIP codes yet, so its coverage is “Not confirmed”. Add its ZIP codes below.', 'cater-ice-cream-core' ) ) ) ) . '#cic-add-zips' );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * City editor
	 * ------------------------------------------------------------------ */

	private function render_city( int $id ): void {
		$row = CityStore::row( $id );
		if ( ! $row ) {
			echo '<h1>' . esc_html__( 'City not found', 'cater-ice-cream-core' ) . '</h1><p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All cities', 'cater-ice-cream-core' ) . '</a></p>';
			return;
		}
		$repo      = new DatabaseRepository();
		$city      = DatabaseRepository::city( $row );
		$cov       = $repo->city_coverage( $city );
		$indexable = $repo->is_indexable( $city );
		$editorial = CityStore::editorial_id( $id );
		$editorial = $editorial && get_post( $editorial ) ? $editorial : 0;
		$content   = $repo->get_city_content( $id );
		$canonical = DatabaseRepository::url( $city['state_slug'], $city['slug'] );
		$preview   = Editorial::city_preview_url( $id );
		$mappings  = CityStore::mappings( $id );

		echo '<h1>' . esc_html( $city['label'] ) . ' ' . ( $city['fixture'] ? self::fixture_badge() : '' ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
		echo '<p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All cities', 'cater-ice-cream-core' ) . '</a></p>';
		$this->notices();

		// Actions.
		echo '<div class="cic-actions" id="cic-city-actions">';
		echo '<a class="button" href="#cic-details">' . esc_html__( 'Edit city', 'cater-ice-cream-core' ) . '</a>';
		if ( $editorial ) {
			echo '<a class="button button-primary" id="cic-edit-content" href="' . esc_url( get_edit_post_link( $editorial, 'url' ) ) . '">' . esc_html__( 'Edit city content', 'cater-ice-cream-core' ) . '</a>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_city_editorial"><input type="hidden" name="city_id" value="' . (int) $id . '">';
			wp_nonce_field( 'cic_city_editorial_' . $id );
			echo '<button class="button button-primary" id="cic-create-content">' . esc_html__( 'Create city content', 'cater-ice-cream-core' ) . '</button></form>';
		}
		if ( '' !== $preview ) {
			echo '<a class="button" id="cic-preview" target="_blank" rel="noopener" href="' . esc_url( $preview ) . '">' . esc_html__( 'Preview city page', 'cater-ice-cream-core' ) . '</a>';
		}
		if ( 'published' === $city['publish_status'] ) {
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $canonical ) . '">' . esc_html__( 'View public page', 'cater-ice-cream-core' ) . '</a>';
		}
		echo '</div>';

		// Status summary: four separate things.
		$count = Editorial::override_count( $editorial );
		echo '<div class="cic-cards" id="cic-status">';
		echo '<div class="cic-card"><h3>' . esc_html__( 'Page publication', 'cater-ice-cream-core' ) . '</h3>' . self::pill( Labels::publish( $city['publish_status'] ), 'published' === $city['publish_status'] ? 'good' : 'neutral' ) . '<p>' . esc_html( 'published' === $city['publish_status'] ? __( 'Visitors can open the city page.', 'cater-ice-cream-core' ) : __( 'Visitors get “not found”; only managers can preview it.', 'cater-ice-cream-core' ) ) . '</p><p><code>' . esc_html( $canonical ) . '</code></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$checks = array(
			array( 'published' === $city['publish_status'], __( 'Page published', 'cater-ice-cream-core' ) ),
			array( 'index' === $city['index_status'], __( 'Search engines set to Index', 'cater-ice-cream-core' ) ),
			array( $content['reviewed'], __( 'City content reviewed and published', 'cater-ice-cream-core' ) ),
			array( $repo->is_served( $city ), __( 'At least one approved (enabled) production ZIP code', 'cater-ice-cream-core' ) ),
		);
		echo '<div class="cic-card"><h3>' . esc_html__( 'Search-engine indexing', 'cater-ice-cream-core' ) . '</h3>' . self::pill( Labels::index( $indexable, $city['index_status'] ), $indexable ? 'good' : 'neutral' ) . '<ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $checks as $c ) {
			echo '<li>' . ( $c[0] ? '✓ ' : '✗ ' ) . esc_html( $c[1] ) . '</li>';
		}
		echo '</ul></div>';
		echo '<div class="cic-card"><h3>' . esc_html__( 'Coverage (whole city)', 'cater-ice-cream-core' ) . '</h3>' . self::pill( Labels::coverage( $cov['status'] ), self::coverage_tone( $cov['status'] ) ) . '<p>' . esc_html( $cov['message'] ) . '</p><p class="description">' . esc_html( sprintf( /* translators: %d: ZIP count. */ _n( 'Worked out from %d linked ZIP.', 'Worked out from %d linked ZIPs.', count( $mappings ), 'cater-ice-cream-core' ), count( $mappings ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="cic-card" id="cic-content"><h3>' . esc_html__( 'City content', 'cater-ice-cream-core' ) . '</h3>';
		if ( $editorial ) {
			/* translators: 1: overrides, 2: total fields. */
			echo '<p>' . esc_html( sprintf( __( '%1$d of %2$d fields customised; the rest inherit the city template.', 'cater-ice-cream-core' ), $count['overrides'], $count['total'] ) ) . '</p><p>' . esc_html( sprintf( /* translators: 1: status, 2: yes/no. */ __( 'Record: %1$s · reviewed: %2$s', 'cater-ice-cream-core' ), 'publish' === get_post_status( $editorial ) ? __( 'published', 'cater-ice-cream-core' ) : __( 'draft', 'cater-ice-cream-core' ), $content['reviewed'] ? __( 'yes', 'cater-ice-cream-core' ) : __( 'no', 'cater-ice-cream-core' ) ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No city content yet: the page uses the city template from the Service Areas page. Create content to customise any field.', 'cater-ice-cream-core' ) . '</p>';
		}
		echo '<p>' . esc_html( Labels::dataset( $row['dataset'] ) ) . ' · ' . esc_html( (string) $row['source_reference'] ) . '</p></div>';
		echo '</div>';

		// Main form.
		echo '<form method="post" id="cic-city-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="cic_city_save"><input type="hidden" name="city_id" value="' . (int) $id . '">';
		wp_nonce_field( 'cic_city_save_' . $id );
		$text   = static function ( string $name, string $label, string $value, string $help = '' ): void {
			echo '<tr><th scope="row"><label for="cic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" id="cic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$select = static function ( string $name, string $label, string $value, array $options, string $help = '' ): void {
			echo '<tr><th scope="row"><label for="cic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><select id="cic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};

		echo '<div class="cic-section"><h2 id="cic-details">' . esc_html__( 'City details', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		$text( 'city_name', __( 'City name', 'cater-ice-cream-core' ), $row['city_name'] );
		$text( 'city_slug', __( 'URL slug', 'cater-ice-cream-core' ), $row['city_slug'], __( 'Changing the slug keeps the old address as a one-hop redirect. A slug used or redirected by another city is refused.', 'cater-ice-cream-core' ) );
		$text( 'county_name', __( 'County', 'cater-ice-cream-core' ), $row['county_name'] );
		$text( 'display_order', __( 'Order in directories', 'cater-ice-cream-core' ), (string) $row['display_order'] );
		$text( 'latitude', __( 'Latitude (optional)', 'cater-ice-cream-core' ), (string) $row['latitude'], __( 'Approximate centre for nearby-city ordering. Never a venue or customer address.', 'cater-ice-cream-core' ) );
		$text( 'longitude', __( 'Longitude (optional)', 'cater-ice-cream-core' ), (string) $row['longitude'] );
		$text( 'alias', __( 'Add alias slug', 'cater-ice-cream-core' ), '', __( 'Optional. An alias address redirects once to this city.', 'cater-ice-cream-core' ) );
		echo '</table>';
		$redirects = CityStore::redirects( $id );
		if ( $redirects ) {
			echo '<h3>' . esc_html__( 'Addresses that redirect here', 'cater-ice-cream-core' ) . '</h3><ul id="cic-redirects">';
			foreach ( $redirects as $r ) {
				echo '<li><code>' . esc_html( $r['slug'] ) . '</code> (' . esc_html( 'alias' === $r['kind'] ? __( 'alias', 'cater-ice-cream-core' ) : __( 'previous slug', 'cater-ice-cream-core' ) ) . ') <label><input type="checkbox" name="remove_redirect[]" value="' . esc_attr( $r['slug'] ) . '"> ' . esc_html__( 'remove', 'cater-ice-cream-core' ) . '</label></li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<div class="cic-section"><h2>' . esc_html__( 'Publication and search engines', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		$select( 'publish_status', __( 'Page publication', 'cater-ice-cream-core' ), $row['publish_status'], array( 'draft' => __( 'Draft — not public (visitors get “not found”)', 'cater-ice-cream-core' ), 'published' => __( 'Published — visitors can open the page', 'cater-ice-cream-core' ) ), __( 'Publishing shows the page; it does not change coverage.', 'cater-ice-cream-core' ) );
		$select( 'index_status', __( 'Search-engine indexing', 'cater-ice-cream-core' ), $row['index_status'], array( 'noindex' => __( 'Noindex — keep out of search engines', 'cater-ice-cream-core' ), 'index' => __( 'Index — allowed once the page is published and its content reviewed', 'cater-ice-cream-core' ) ), __( 'Separate from publishing: a published city can stay out of search engines. Index takes effect only when all three requirements above are met.', 'cater-ice-cream-core' ) );
		echo '</table></div>';

		$this->render_zip_table( $id, $mappings, $city['label'] );
		submit_button( __( 'Save city', 'cater-ice-cream-core' ) );
		echo '</form>';

		// One small form per relationship (outside the main form; buttons use form="").
		foreach ( $mappings as $m ) {
			echo '<form method="post" id="cic-unlink-' . esc_attr( $m['zip'] ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden><input type="hidden" name="action" value="cic_city_zip_unlink"><input type="hidden" name="city_id" value="' . (int) $id . '"><input type="hidden" name="zip" value="' . esc_attr( $m['zip'] ) . '">';
			wp_nonce_field( 'cic_city_zip_unlink_' . $id );
			echo '</form>';
		}

		$this->render_add_zips( $id );
		$this->editor_script();
	}

	/** @param array<int, array<string, mixed>> $mappings City mappings. */
	private function render_zip_table( int $id, array $mappings, string $label ): void {
		echo '<div class="cic-section"><h2 id="cic-zips">' . esc_html__( 'ZIP codes and coverage', 'cater-ice-cream-core' ) . '</h2>';
		echo '<div class="cic-legend"><div><strong>' . esc_html__( 'ZIP coverage — affects every linked city', 'cater-ice-cream-core' ) . '</strong><br>' . esc_html__( 'One decision per ZIP code. If the ZIP is linked to several cities, changing it changes coverage for all of them.', 'cater-ice-cream-core' ) . '</div><div class="cic-legend--city"><strong>' . esc_html__( 'Decision for this city — this relationship only', 'cater-ice-cream-core' ) . '</strong><br>' . esc_html__( 'Applies only to this city’s use of the ZIP. Other cities sharing the ZIP keep their own decision.', 'cater-ice-cream-core' ) . '</div></div>';
		echo '<p class="description">' . esc_html__( 'Exclusions always win: a ZIP marked “Not served” cannot be re-enabled by any city decision. When cities sharing a ZIP have different results, visitors who enter only that ZIP are asked for their venue city. Notes are internal and never public.', 'cater-ice-cream-core' ) . '</p>';
		if ( ! $mappings ) {
			echo '<p id="cic-no-zips"><strong>' . esc_html__( 'No ZIP codes linked yet, so this city’s coverage is “Not confirmed”.', 'cater-ice-cream-core' ) . '</strong> <a href="#cic-add-zips">' . esc_html__( 'Add ZIP codes', 'cater-ice-cream-core' ) . '</a></p></div>';
			return;
		}
		echo '<div style="overflow-x:auto"><table class="widefat striped cic-zip-table" id="cic-zip-table"><thead><tr><th scope="col">ZIP</th><th scope="col">' . esc_html__( 'ZIP coverage (all linked cities)', 'cater-ice-cream-core' ) . '</th><th scope="col">' . esc_html__( 'Decision for this city only', 'cater-ice-cream-core' ) . '</th><th scope="col">' . esc_html__( 'Result here', 'cater-ice-cream-core' ) . '</th><th scope="col">' . esc_html__( 'Also linked to', 'cater-ice-cream-core' ) . '</th><th scope="col">' . esc_html__( 'Internal note', 'cater-ice-cream-core' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Remove', 'cater-ice-cream-core' ) . '</span></th></tr></thead><tbody>';
		$zip_labels = Labels::zip_statuses();
		$ovr_labels = Labels::overrides();
		foreach ( $mappings as $m ) {
			$zip    = (string) $m['zip'];
			$others = (int) $m['shared'] > 1 ? CityStore::zip_cities( $zip, $id ) : array();
			$names  = implode( '; ', wp_list_pluck( $others, 'label' ) );
			$status = (string) $m['service_status'];
			$eff    = Coverage::effective( (string) $m['service_override'], $status );
			echo '<tr data-zip="' . esc_attr( $zip ) . '"><td><code>' . esc_html( $zip ) . '</code>' . ( 'fixture' === $m['dataset'] ? '<br>' . self::fixture_badge() : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
			echo '<td><select name="zip_status[' . esc_attr( $zip ) . ']" data-original="' . esc_attr( $status ) . '" data-shared="' . count( $others ) . '" data-names="' . esc_attr( $names ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: ZIP. */ __( 'ZIP %s coverage, all linked cities', 'cater-ice-cream-core' ), $zip ) ) . '">';
			foreach ( $zip_labels as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '" title="' . esc_attr( $l[1] ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $l[0] ) . '</option>';
			}
			echo '</select><span class="cic-help">' . esc_html( $zip_labels[ $status ][1] ?? '' ) . '</span>';
			echo '<span class="cic-scope-warn" hidden></span></td>';
			echo '<td><select name="override[' . esc_attr( $zip ) . ']" aria-label="' . esc_attr( sprintf( /* translators: %s: ZIP. */ __( 'ZIP %s decision for this city only', 'cater-ice-cream-core' ), $zip ) ) . '">';
			foreach ( $ovr_labels as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '" title="' . esc_attr( $l[1] ) . '"' . selected( (string) $m['service_override'], $k, false ) . '>' . esc_html( $l[0] ) . '</option>';
			}
			echo '</select><span class="cic-help">' . esc_html( $ovr_labels[ (string) $m['service_override'] ][1] ?? '' ) . '</span></td>';
			echo '<td data-effective="' . esc_attr( $eff ) . '">' . self::pill( Labels::effective( $eff ), array( 'enabled' => 'good', 'disabled' => 'bad', 'manual_review' => 'warn' )[ $eff ] ?? 'neutral' ) . ( $m['manual_at'] ? '<span class="cic-help">' . esc_html__( 'Decided by hand', 'cater-ice-cream-core' ) . '</span>' : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . ( $others ? esc_html( $names ) : '—' ) . '</td>';
			echo '<td><input type="text" class="regular-text" style="max-width:220px" name="note[' . esc_attr( $zip ) . ']" value="' . esc_attr( $m['review_note'] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: ZIP. */ __( 'ZIP %s internal note', 'cater-ice-cream-core' ), $zip ) ) . '"></td>';
			/* translators: 1: ZIP, 2: city. */
			$confirm = sprintf( __( 'Remove ZIP %1$s from %2$s? The ZIP record and its links to other cities are kept.', 'cater-ice-cream-core' ), $zip, $label );
			echo '<td><button type="submit" class="button-link cic-unlink" form="cic-unlink-' . esc_attr( $zip ) . '" data-confirm="' . esc_attr( $confirm ) . '" style="color:#b32d2e">' . esc_html__( 'Remove from this city', 'cater-ice-cream-core' ) . '</button></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private function render_add_zips( int $id ): void {
		$raw = isset( $_GET['add_zips'] ) ? sanitize_textarea_field( wp_unslash( $_GET['add_zips'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check; writing needs the nonce below.
		echo '<div class="cic-section"><h2 id="cic-add-zips">' . esc_html__( 'Add ZIP codes to this city', 'cater-ice-cream-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Enter one ZIP or paste several, separated by commas, spaces or new lines. Leading zeros are kept. Nothing is saved until you confirm: the check lists new ZIPs, existing ZIPs and any other cities that already use them.', 'cater-ice-cream-core' ) . '</p>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '#cic-add-zips"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="city" value="' . (int) $id . '">';
		echo '<label class="screen-reader-text" for="cic-add-zips-input">' . esc_html__( 'ZIP codes to add', 'cater-ice-cream-core' ) . '</label><textarea id="cic-add-zips-input" name="add_zips" rows="3" cols="50" placeholder="91101, 91103&#10;91106">' . esc_textarea( $raw ) . '</textarea><br>';
		echo '<button class="button" id="cic-check-zips">' . esc_html__( 'Check ZIP codes', 'cater-ice-cream-core' ) . '</button></form>';
		if ( '' !== trim( $raw ) ) {
			$this->render_zip_plan( $id, $raw );
		}
		echo '</div>';
	}

	private function render_zip_plan( int $id, string $raw ): void {
		$plan = CityStore::plan_zip_links( $id, $raw );
		if ( is_wp_error( $plan ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $plan->get_error_message() ) . '</p></div>';
			return;
		}
		$labels = Labels::zip_statuses();
		$city   = $plan['city']['city_name'];
		echo '<div class="card" style="max-width:none" id="cic-zip-plan"><h3>' . esc_html__( 'Check result — nothing saved yet', 'cater-ice-cream-core' ) . '</h3>';
		echo '<table class="widefat cic-plan"><thead><tr><th scope="col">ZIP</th><th scope="col">' . esc_html__( 'What will happen', 'cater-ice-cream-core' ) . '</th><th scope="col">' . esc_html__( 'Scope', 'cater-ice-cream-core' ) . '</th></tr></thead><tbody>';
		foreach ( $plan['items'] as $item ) {
			$scope = '—';
			switch ( $item['action'] ) {
				case 'create':
					$what  = self::pill( __( 'New ZIP record', 'cater-ice-cream-core' ), 'good' ) . ' ' . esc_html__( 'Created as “Not reviewed yet” and linked to this city.', 'cater-ice-cream-core' );
					$scope = esc_html__( 'Not served until an authorised user reviews it.', 'cater-ice-cream-core' );
					break;
				case 'link':
					/* translators: %s: ZIP coverage label. */
					$what = self::pill( __( 'Link existing ZIP', 'cater-ice-cream-core' ) ) . ' ' . esc_html( sprintf( __( 'Existing ZIP coverage: %s.', 'cater-ice-cream-core' ), $labels[ $item['zip_status'] ][0] ?? $item['zip_status'] ) ) . ( 'fixture' === $item['zip_dataset'] ? ' ' . self::fixture_badge() : '' );
					if ( $item['others'] ) {
						$list = implode( '; ', array_map( static fn( $o ) => $o['label'] . ' (' . ( Labels::overrides()[ $o['service_override'] ][0] ?? $o['service_override'] ) . ')', $item['others'] ) );
						/* translators: 1: other cities, 2: this city. */
						$scope = '<strong>' . esc_html__( 'Already used by:', 'cater-ice-cream-core' ) . '</strong> ' . esc_html( $list ) . '<br>' . esc_html( sprintf( __( 'Linking adds a relationship for %2$s only; it does not change coverage for %1$s. The ZIP coverage above is shared: changing it later affects every linked city. If the cities’ results differ, visitors who enter only this ZIP are asked for their venue city.', 'cater-ice-cream-core' ), __( 'those cities', 'cater-ice-cream-core' ), $city ) );
					} else {
						$scope = esc_html__( 'Not linked to any other city.', 'cater-ice-cream-core' );
					}
					break;
				case 'already':
					$what = self::pill( __( 'Already linked', 'cater-ice-cream-core' ) ) . ' ' . esc_html__( 'Skipped (no duplicate is created).', 'cater-ice-cream-core' );
					break;
				default:
					$what = self::pill( __( 'Cannot add', 'cater-ice-cream-core' ), 'bad' ) . ' ' . esc_html( $item['reason'] );
			}
			echo '<tr data-plan="' . esc_attr( $item['action'] ) . '"><td><code>' . esc_html( $item['zip'] ) . '</code></td><td>' . $what . '</td><td>' . $scope . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		foreach ( $plan['invalid'] as $bad ) {
			echo '<tr data-plan="invalid"><td><code>' . esc_html( $bad['raw'] ) . '</code></td><td>' . self::pill( __( 'Invalid', 'cater-ice-cream-core' ), 'bad' ) . ' ' . esc_html( $bad['reason'] ) . '</td><td>—</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		foreach ( $plan['repeated'] as $rep ) {
			echo '<tr data-plan="repeated"><td><code>' . esc_html( $rep ) . '</code></td><td>' . self::pill( __( 'Repeated', 'cater-ice-cream-core' ) ) . ' ' . esc_html__( 'Entered more than once; counted once.', 'cater-ice-cream-core' ) . '</td><td>—</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
		if ( $plan['addable'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_city_zips_add"><input type="hidden" name="city_id" value="' . (int) $id . '"><input type="hidden" name="zips" value="' . esc_attr( $raw ) . '">';
			wp_nonce_field( 'cic_city_zips_add_' . $id );
			/* translators: %d: number of ZIPs. */
			submit_button( sprintf( _n( 'Confirm: add %d ZIP to this city', 'Confirm: add %d ZIPs to this city', $plan['addable'], 'cater-ice-cream-core' ), $plan['addable'] ), 'primary', 'cic-confirm-zips', false );
			echo ' <a class="button-link" href="' . esc_url( self::url( array( 'city' => $id ) ) ) . '#cic-add-zips">' . esc_html__( 'Cancel', 'cater-ice-cream-core' ) . '</a></form>';
		} else {
			echo '<p><strong>' . esc_html__( 'Nothing to add.', 'cater-ice-cream-core' ) . '</strong></p>';
		}
		echo '</div>';
	}

	/** Shared-ZIP scope warning and removal confirmation. */
	private function editor_script(): void {
		/* translators: 1: number of other cities, 2: their names. */
		$warn = esc_js( __( 'This changes ZIP coverage for every linked city: this city and %1$s other(s) (%2$s).', 'cater-ice-cream-core' ) );
		echo "<script>(function(){document.querySelectorAll('select[name^=\"zip_status[\"]').forEach(function(s){var w=s.parentNode.querySelector('.cic-scope-warn');s.addEventListener('change',function(){var n=parseInt(s.dataset.shared,10)||0;if(n>0&&s.value!==s.dataset.original){w.textContent='{$warn}'.replace('%1\$s',n).replace('%2\$s',s.dataset.names);w.hidden=false;}else{w.hidden=true;}});});document.querySelectorAll('.cic-unlink').forEach(function(b){b.addEventListener('click',function(e){if(!window.confirm(b.dataset.confirm)){e.preventDefault();}});});})();</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script, escaped text.
	}

	public function save_city(): void {
		$id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_save_' . $id ) ) {
			self::deny();
		}
		$row = CityStore::row( $id );
		if ( ! $row ) {
			self::deny();
		}
		$fields = array();
		foreach ( array( 'city_name', 'city_slug', 'county_name', 'publish_status', 'index_status', 'display_order', 'latitude', 'longitude' ) as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				$fields[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
			}
		}
		$result = CityStore::update_city( $id, $fields );
		$errors = is_wp_error( $result ) ? array( $result->get_error_message() ) : array();
		$notes  = array();

		$alias = isset( $_POST['alias'] ) ? sanitize_title( wp_unslash( $_POST['alias'] ) ) : '';
		if ( '' !== $alias ) {
			$r = CityStore::add_alias( $id, $alias );
			if ( is_wp_error( $r ) ) {
				$errors[] = $r->get_error_message();
			}
		}
		foreach ( (array) ( $_POST['remove_redirect'] ?? array() ) as $slug ) {
			CityStore::remove_redirect( $id, sanitize_title( wp_unslash( $slug ) ) );
		}
		$overrides  = (array) ( $_POST['override'] ?? array() );
		$note_input = (array) ( $_POST['note'] ?? array() );
		$statuses   = (array) ( $_POST['zip_status'] ?? array() );
		$zip_labels = Labels::zip_statuses();
		$ovr_labels = Labels::overrides();
		foreach ( CityStore::mappings( $id ) as $m ) {
			$zip      = (string) $m['zip'];
			$override = isset( $overrides[ $zip ] ) ? sanitize_key( wp_unslash( $overrides[ $zip ] ) ) : $m['service_override'];
			$note     = isset( $note_input[ $zip ] ) ? sanitize_text_field( wp_unslash( $note_input[ $zip ] ) ) : $m['review_note'];
			if ( $override !== $m['service_override'] || $note !== $m['review_note'] ) {
				$r = CityStore::set_override( $id, $zip, $override, $note );
				if ( is_wp_error( $r ) ) {
					$errors[] = $zip . ': ' . $r->get_error_message();
				} elseif ( $override !== $m['service_override'] ) {
					/* translators: 1: ZIP, 2: city, 3: decision. */
					$notes[] = sprintf( __( 'ZIP %1$s, %2$s only: %3$s.', 'cater-ice-cream-core' ), $zip, $row['city_name'], $ovr_labels[ $override ][0] );
				}
			}
			$status = isset( $statuses[ $zip ] ) ? sanitize_key( wp_unslash( $statuses[ $zip ] ) ) : (string) $m['service_status'];
			if ( $status !== (string) $m['service_status'] ) {
				$r = CityStore::set_zip_status( $zip, $status );
				if ( is_wp_error( $r ) ) {
					$errors[] = $zip . ': ' . $r->get_error_message();
				} else {
					$all = array_merge( array( $row['city_name'] . ', ' . $row['state_code'] ), wp_list_pluck( CityStore::zip_cities( $zip, $id ), 'label' ) );
					/* translators: 1: ZIP, 2: coverage, 3: count, 4: city list. */
					$notes[] = sprintf( _n( 'ZIP %1$s coverage is now “%2$s” for its %3$d linked city: %4$s.', 'ZIP %1$s coverage is now “%2$s” for all %3$d linked cities: %4$s.', count( $all ), 'cater-ice-cream-core' ), $zip, $zip_labels[ $status ][0], count( $all ), implode( '; ', $all ) );
				}
			}
		}
		$args = array( 'city' => $id );
		if ( $errors ) {
			$args['cic_err'] = rawurlencode( implode( self::SEP,$errors ) );
		}
		$args['cic_msg'] = rawurlencode( implode( self::SEP,array_merge( array( $errors ? __( 'Other changes saved.', 'cater-ice-cream-core' ) : __( 'City saved.', 'cater-ice-cream-core' ) ), array_slice( $notes, 0, 12 ) ) ) );
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public function add_zips(): void {
		$id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_zips_add_' . $id ) ) {
			self::deny();
		}
		$raw  = isset( $_POST['zips'] ) ? sanitize_textarea_field( wp_unslash( $_POST['zips'] ) ) : '';
		$plan = CityStore::link_zips( $id, $raw );
		if ( is_wp_error( $plan ) ) {
			wp_safe_redirect( self::url( array( 'city' => $id, 'cic_err' => rawurlencode( $plan->get_error_message() ) ) ) );
			exit;
		}
		$new    = wp_list_pluck( array_filter( $plan['items'], static fn( $i ) => 'create' === $i['action'] ), 'zip' );
		$linked = wp_list_pluck( array_filter( $plan['items'], static fn( $i ) => 'link' === $i['action'] ), 'zip' );
		$lines  = array();
		if ( $new ) {
			/* translators: %s: ZIP list. */
			$lines[] = sprintf( __( 'New ZIP records (Not reviewed yet): %s.', 'cater-ice-cream-core' ), implode( ', ', $new ) );
		}
		if ( $linked ) {
			/* translators: %s: ZIP list. */
			$lines[] = sprintf( __( 'Existing ZIPs linked (coverage unchanged): %s.', 'cater-ice-cream-core' ), implode( ', ', $linked ) );
		}
		if ( ! $lines ) {
			$lines[] = __( 'Nothing was added.', 'cater-ice-cream-core' );
		}
		wp_safe_redirect( self::url( array( 'city' => $id, 'cic_msg' => rawurlencode( implode( self::SEP,$lines ) ) ) ) . '#cic-zips' );
		exit;
	}

	public function unlink_zip(): void {
		$id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_zip_unlink_' . $id ) ) {
			self::deny();
		}
		$zip  = isset( $_POST['zip'] ) ? sanitize_text_field( wp_unslash( $_POST['zip'] ) ) : '';
		$left = CityStore::unlink_zip( $id, $zip );
		if ( is_wp_error( $left ) ) {
			wp_safe_redirect( self::url( array( 'city' => $id, 'cic_err' => rawurlencode( $left->get_error_message() ) ) ) );
			exit;
		}
		/* translators: 1: ZIP, 2: number of other cities. */
		$msg = sprintf( __( 'ZIP %1$s removed from this city. The ZIP record is kept and stays linked to %2$d other city/cities.', 'cater-ice-cream-core' ), $zip, $left );
		wp_safe_redirect( self::url( array( 'city' => $id, 'cic_msg' => rawurlencode( $msg ) ) ) . '#cic-zips' );
		exit;
	}

	public function create_editorial(): void {
		$id = isset( $_REQUEST['city_id'] ) ? absint( $_REQUEST['city_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified next.
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_editorial_' . $id ) ) {
			self::deny();
		}
		$post_id = CityStore::row( $id ) ? CityStore::ensure_editorial( $id ) : 0;
		wp_safe_redirect( $post_id ? get_edit_post_link( $post_id, 'url' ) : self::url( array( 'city' => $id, 'cic_err' => rawurlencode( __( 'Could not create the content record.', 'cater-ice-cream-core' ) ) ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Import
	 * ------------------------------------------------------------------ */

	private static function transient( string $token ): string {
		return 'cic_loc_import_' . get_current_user_id() . '_' . $token;
	}

	public function render_import(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage locations.', 'cater-ice-cream-core' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Import locations', 'cater-ice-cream-core' ) . '</h1>';
		$this->notices();
		$token  = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stored = '' !== $token ? get_transient( self::transient( $token ) ) : false;
		if ( is_array( $stored ) && isset( $stored['report'] ) ) {
			$this->render_report( $stored['report'], $token, empty( $stored['applied'] ) );
		}
		echo '<h2>' . esc_html__( 'Upload a CSV', 'cater-ice-cream-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Import cities first, then ZIP geography, then city-ZIP mappings. Geography never becomes coverage on its own: new ZIPs start as “Not reviewed yet” unless this is a reviewed coverage dataset. Coverage decisions made by hand in the city editor are never overwritten: they are listed as conflicts. Every upload is a dry run first.', 'cater-ice-cream-core' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_location_import">';
		wp_nonce_field( 'cic_location_import' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="cic-import-file">' . esc_html__( 'CSV file (UTF-8)', 'cater-ice-cream-core' ) . '</label></th><td><input type="file" id="cic-import-file" name="csv" accept=".csv,text/csv" required></td></tr>';
		echo '<tr><th scope="row"><label for="cic-import-type">' . esc_html__( 'Contents', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-import-type" name="type"><option value="cities">' . esc_html__( 'Cities (state_code, county_name, city_name…)', 'cater-ice-cream-core' ) . '</option><option value="zips">' . esc_html__( 'ZIP geography (zip, state_code, county_label, latitude, longitude)', 'cater-ice-cream-core' ) . '</option><option value="city_zips">' . esc_html__( 'City-ZIP mappings (state_code, city_slug, zip)', 'cater-ice-cream-core' ) . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="cic-import-dataset">' . esc_html__( 'Dataset', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-import-dataset" name="dataset"><option value="production">' . esc_html__( 'Production (owner-approved source)', 'cater-ice-cream-core' ) . '</option>' . ( Tables::fixtures_allowed() ? '<option value="fixture">' . esc_html__( 'Fixture (local test data only)', 'cater-ice-cream-core' ) . '</option>' : '' ) . '</select></td></tr>';
		echo '<tr><th scope="row"><label for="cic-import-source">' . esc_html__( 'Source reference', 'cater-ice-cream-core' ) . '</label></th><td><input class="regular-text" type="text" id="cic-import-source" name="source" required placeholder="' . esc_attr__( 'e.g. Client Ventura list, reviewed 2026-11-02', 'cater-ice-cream-core' ) . '"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Reviewed coverage', 'cater-ice-cream-core' ) . '</th><td><label><input type="checkbox" name="apply_status" value="1"> ' . esc_html__( 'This file contains reviewed coverage decisions (apply service_status / service_override columns)', 'cater-ice-cream-core' ) . '</label></td></tr>';
		echo '</table>';
		submit_button( __( 'Preview import (dry run)', 'cater-ice-cream-core' ) );
		echo '</form></div>';
	}

	/** @param array<string, mixed> $report Report. */
	private function render_report( array $report, string $token, bool $can_apply ): void {
		$c = $report['counts'];
		echo '<div class="card" style="max-width:none"><h2>' . esc_html( $report['dry_run'] ? __( 'Dry run result', 'cater-ice-cream-core' ) : __( 'Import applied', 'cater-ice-cream-core' ) ) . '</h2>';
		/* translators: 1: type, 2: dataset, 3: source. */
		echo '<p>' . esc_html( sprintf( __( 'Contents: %1$s · dataset: %2$s · source: %3$s', 'cater-ice-cream-core' ), $report['type'], $report['dataset'], $report['source'] ) ) . ( 'fixture' === $report['dataset'] ? ' ' . self::fixture_badge() : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
		/* translators: 1-6: counts. */
		echo '<p><strong>' . esc_html( sprintf( __( '%1$d to create, %2$d to update, %3$d unchanged, %4$d rejected (%5$d duplicates), %6$d conflicts with manual edits (kept).', 'cater-ice-cream-core' ), $c['created'], $c['updated'], $c['unchanged'], $c['rejected'], $c['duplicates'], $c['conflicts'] ?? 0 ) ) . '</strong></p>';
		if ( $report['ignored_columns'] ) {
			echo '<p>' . esc_html__( 'Ignored columns:', 'cater-ice-cream-core' ) . ' <code>' . esc_html( implode( ', ', $report['ignored_columns'] ) ) . '</code></p>';
		}
		if ( ! empty( $report['conflicts'] ) ) {
			echo '<h3 id="cic-import-conflicts">' . esc_html__( 'Conflicts with manual edits (left unchanged)', 'cater-ice-cream-core' ) . '</h3><ul>';
			foreach ( array_slice( $report['conflicts'], 0, 100 ) as $r ) {
				/* translators: 1: line, 2: key, 3: reason. */
				echo '<li>' . esc_html( sprintf( __( 'Line %1$d (%2$s): %3$s', 'cater-ice-cream-core' ), $r['line'], $r['key'], $r['reason'] ) ) . '</li>';
			}
			echo '</ul>';
		}
		if ( $report['rejections'] ) {
			echo '<h3>' . esc_html__( 'Rejected rows', 'cater-ice-cream-core' ) . '</h3><ul>';
			foreach ( array_slice( $report['rejections'], 0, 100 ) as $r ) {
				/* translators: 1: line, 2: reason. */
				echo '<li>' . esc_html( sprintf( __( 'Line %1$d: %2$s', 'cater-ice-cream-core' ), $r['line'], $r['reason'] ) ) . '</li>';
			}
			echo '</ul>';
		}
		if ( $report['actions'] ) {
			echo '<h3>' . esc_html__( 'Changes', 'cater-ice-cream-core' ) . '</h3><ul>';
			foreach ( array_slice( $report['actions'], 0, 100 ) as $a ) {
				echo '<li><code>' . esc_html( $a['action'] ) . '</code> ' . esc_html( $a['key'] ) . ( isset( $a['diff'] ) ? ' (' . esc_html( implode( ', ', array_keys( $a['diff'] ) ) ) . ')' : '' ) . '</li>';
			}
			echo '</ul>';
		}
		if ( $can_apply && $report['dry_run'] && ( $c['created'] || $c['updated'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_location_import"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
			wp_nonce_field( 'cic_location_import' );
			submit_button( __( 'Apply this import', 'cater-ice-cream-core' ), 'primary', 'apply' );
			echo '</form>';
		}
		echo '</div>';
	}

	public function import(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_location_import' ) ) {
			self::deny();
		}
		$back = add_query_arg( array( 'page' => self::SLUG . '-import' ), admin_url( 'admin.php' ) );
		if ( isset( $_POST['token'] ) ) {
			$token  = sanitize_key( wp_unslash( $_POST['token'] ) );
			$stored = get_transient( self::transient( $token ) );
			if ( ! is_array( $stored ) || ! empty( $stored['applied'] ) ) {
				wp_safe_redirect( add_query_arg( 'cic_err', rawurlencode( __( 'That preview expired or was already applied. Upload the file again.', 'cater-ice-cream-core' ) ), $back ) );
				exit;
			}
			$importer = new Importer( $stored['type'], $stored['dataset'], $stored['source'], $stored['apply_status'] );
			$report   = $importer->run( $stored['csv'], false );
			if ( is_wp_error( $report ) ) {
				wp_safe_redirect( add_query_arg( 'cic_err', rawurlencode( $report->get_error_message() ), $back ) );
				exit;
			}
			unset( $report['actions'] );
			$report['actions'] = array();
			set_transient( self::transient( $token ), array( 'report' => $report, 'applied' => true ), HOUR_IN_SECONDS );
			wp_safe_redirect( add_query_arg( array( 'token' => $token, 'cic_msg' => rawurlencode( __( 'Import applied.', 'cater-ice-cream-core' ) ) ), $back ) );
			exit;
		}
		$file = $_FILES['csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > Importer::MAX_SIZE ) {
			wp_safe_redirect( add_query_arg( 'cic_err', rawurlencode( __( 'Upload a CSV file (up to 5 MB).', 'cater-ice-cream-core' ) ), $back ) );
			exit;
		}
		$csv     = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( wp_unslash( $_POST['dataset'] ) ) : '';
		$source  = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$status  = ! empty( $_POST['apply_status'] );
		$report  = ( new Importer( $type, $dataset, $source, $status ) )->run( $csv, true );
		if ( is_wp_error( $report ) ) {
			wp_safe_redirect( add_query_arg( 'cic_err', rawurlencode( $report->get_error_message() ), $back ) );
			exit;
		}
		$token = strtolower( wp_generate_password( 16, false ) );
		foreach ( $report['actions'] as &$a ) {
			unset( $a['data'], $a['existing'] );
		}
		unset( $a );
		set_transient(
			self::transient( $token ),
			array(
				'report'       => $report,
				'csv'          => $csv,
				'type'         => $type,
				'dataset'      => $dataset,
				'source'       => $source,
				'apply_status' => $status,
			),
			HOUR_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'token', $token, $back ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Log
	 * ------------------------------------------------------------------ */

	public function render_log(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage locations.', 'cater-ice-cream-core' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Location log', 'cater-ice-cream-core' ) . '</h1>';
		echo '<p>' . esc_html( sprintf( /* translators: 1: environment, 2: counted/ignored. */ __( 'Environment: %1$s. Fixture rows are %2$s here.', 'cater-ice-cream-core' ), wp_get_environment_type(), Tables::fixtures_allowed() ? __( 'counted', 'cater-ice-cream-core' ) : __( 'ignored', 'cater-ice-cream-core' ) ) ) . ' ' . esc_html( sprintf( /* translators: %s: class name. */ __( 'Active repository: %s.', 'cater-ice-cream-core' ), get_class( Service::repository() ) ) ) . '</p>';
		echo '<h2>' . esc_html__( 'Recent changes', 'cater-ice-cream-core' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'User', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Action', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Detail', 'cater-ice-cream-core' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( (array) get_option( CityStore::AUDIT_OPTION, array() ) ) as $e ) {
			$user = get_userdata( (int) $e['user'] );
			echo '<tr><td>' . esc_html( $e['time'] ) . '</td><td>' . esc_html( $user ? $user->display_name : 'CLI' ) . '</td><td>' . esc_html( $e['action'] ) . '</td><td>' . esc_html( $e['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Recent imports', 'cater-ice-cream-core' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Contents', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Dataset', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Source', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Mode', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Counts', 'cater-ice-cream-core' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( (array) get_option( Importer::LOG, array() ) ) as $e ) {
			$c = $e['counts'];
			echo '<tr><td>' . esc_html( $e['time'] ) . '</td><td>' . esc_html( $e['type'] ) . '</td><td>' . esc_html( $e['dataset'] ) . '</td><td>' . esc_html( $e['source'] ) . '</td><td>' . esc_html( $e['dry_run'] ? 'dry run' : 'applied' ) . '</td><td>' . esc_html( sprintf( '+%d ~%d =%d ×%d !%d', $c['created'], $c['updated'], $c['unchanged'], $c['rejected'], $c['conflicts'] ?? 0 ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
