<?php
/**
 * Service Areas admin (capability manage_locations; every action nonce-checked and audited):
 * - Cities: searchable/filterable list; edit name, slug (old slug kept as a redirect), county,
 *   publish and index status, order, coordinates, aliases and reviewed ZIP decisions with
 *   internal notes; link to the private editorial record and its authenticated preview.
 * - Import: upload a UTF-8 CSV, review the dry run, then apply the same file.
 * - Log: audit trail and recent imports.
 * Fixture rows are labelled everywhere; they are local test data, not approved coverage.
 *
 * @package CIC
 */

namespace CIC\Locations\Admin;

use CIC\Locations\CityStore;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Importer;
use CIC\Locations\Service;
use CIC\Locations\Tables;

defined( 'ABSPATH' ) || exit;

final class LocationsPage {

	public const SLUG = 'cic-locations';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_cic_city_save', array( $this, 'save_city' ) );
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

	private static function fixture_badge(): string {
		return '<span class="cic-badge" style="display:inline-block;padding:0 6px;border-radius:3px;background:#fcf0c3;color:#5c4400;font-size:11px;font-weight:600">' . esc_html__( 'FIXTURE — local test data', 'cater-ice-cream-core' ) . '</span>';
	}

	private function notices(): void {
		$msg = isset( $_GET['cic_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['cic_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$err = isset( $_GET['cic_err'] ) ? sanitize_text_field( wp_unslash( $_GET['cic_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( '' !== $err ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
		}
		if ( ! Tables::ready() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Location tables are not installed yet. Reload this screen to run the pending database update.', 'cater-ice-cream-core' ) . '</p></div>';
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Provisional location system: the owner’s historical city template and rewrite code is still pending and will be audited and connected through the location adapter. A listed city is not a promise that every ZIP is served; coverage comes only from reviewed ZIP decisions.', 'cater-ice-cream-core' ) . '</p></div>';
	}

	/* ------------------------------------------------------------------ *
	 * Cities
	 * ------------------------------------------------------------------ */

	public function render_cities(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage locations.', 'cater-ice-cream-core' ) );
		}
		$city_id = isset( $_GET['city'] ) ? absint( $_GET['city'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap">';
		if ( $city_id && Tables::ready() ) {
			$this->render_city( $city_id );
		} else {
			$this->render_city_list();
		}
		echo '</div>';
	}

	private function render_city_list(): void {
		global $wpdb;
		echo '<h1>' . esc_html__( 'Service area cities', 'cater-ice-cream-core' ) . '</h1>';
		$this->notices();
		if ( ! Tables::ready() ) {
			return;
		}
		$s      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$county = isset( $_GET['county'] ) ? sanitize_title( wp_unslash( $_GET['county'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$t      = Tables::name( 'cities' );
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
		$list_q  = "SELECT * FROM {$t} WHERE {$where} ORDER BY state_code, county_slug, display_order, city_name LIMIT %d OFFSET %d";
		$total   = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_q, $params ) ) : $wpdb->get_var( $count_q ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows    = $wpdb->get_results( $wpdb->prepare( $list_q, array_merge( $params, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counties = $wpdb->get_results( "SELECT DISTINCT county_slug, county_name FROM {$t} ORDER BY county_name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		echo '<form method="get" style="margin:1em 0"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label class="screen-reader-text" for="cic-city-search">' . esc_html__( 'Search cities', 'cater-ice-cream-core' ) . '</label><input type="search" id="cic-city-search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'City name', 'cater-ice-cream-core' ) . '"> ';
		echo '<select name="county"><option value="">' . esc_html__( 'All counties', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $counties as $c ) {
			echo '<option value="' . esc_attr( $c['county_slug'] ) . '"' . selected( $county, $c['county_slug'], false ) . '>' . esc_html( $c['county_name'] ) . '</option>';
		}
		echo '</select> <select name="status"><option value="">' . esc_html__( 'Any status', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Tables::PUBLISH_STATUSES as $st ) {
			echo '<option value="' . esc_attr( $st ) . '"' . selected( $status, $st, false ) . '>' . esc_html( ucfirst( $st ) ) . '</option>';
		}
		echo '</select> <button class="button">' . esc_html__( 'Filter', 'cater-ice-cream-core' ) . '</button></form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'City', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'County', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Page', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Search engines', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Coverage', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Data', 'cater-ice-cream-core' ) . '</th></tr></thead><tbody>';
		$repo = new DatabaseRepository();
		foreach ( $rows as $row ) {
			$city = DatabaseRepository::city( $row );
			$cov  = $repo->city_coverage( $city );
			$idx  = $repo->is_indexable( $city );
			echo '<tr><td><a class="row-title" href="' . esc_url( self::url( array( 'city' => $city['id'] ) ) ) . '">' . esc_html( $city['label'] ) . '</a></td>';
			echo '<td>' . esc_html( $city['county_name'] ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $city['publish_status'] ) ) . '</td>';
			echo '<td>' . esc_html( $idx ? __( 'Indexable', 'cater-ice-cream-core' ) : __( 'Noindex', 'cater-ice-cream-core' ) ) . '</td>';
			echo '<td><code>' . esc_html( $cov['status'] ) . '</code></td>';
			echo '<td>' . ( $city['fixture'] ? self::fixture_badge() : esc_html__( 'Production', 'cater-ice-cream-core' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No cities found. Import a reviewed CSV under Service Areas → Import.', 'cater-ice-cream-core' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) ) . '</p>';
		}
	}

	private function render_city( int $id ): void {
		$row = CityStore::row( $id );
		if ( ! $row ) {
			echo '<h1>' . esc_html__( 'City not found', 'cater-ice-cream-core' ) . '</h1>';
			return;
		}
		$repo      = new DatabaseRepository();
		$city      = DatabaseRepository::city( $row );
		$cov       = $repo->city_coverage( $city );
		$editorial = CityStore::editorial_id( $id );
		$content   = $repo->get_city_content( $id );
		$canonical = DatabaseRepository::url( $city['state_slug'], $city['slug'] );

		echo '<h1>' . esc_html( $city['label'] ) . ' ' . ( $city['fixture'] ? self::fixture_badge() : '' ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
		echo '<p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All cities', 'cater-ice-cream-core' ) . '</a></p>';
		$this->notices();

		echo '<p>' . esc_html__( 'Public URL:', 'cater-ice-cream-core' ) . ' <code>' . esc_html( $canonical ) . '</code>';
		if ( 'published' === $city['publish_status'] ) {
			echo ' <a href="' . esc_url( $canonical ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'cater-ice-cream-core' ) . '</a>';
		}
		echo '</p>';
		/* translators: 1: status, 2: explanation. */
		echo '<p>' . esc_html( sprintf( __( 'Whole-city coverage result: %1$s. %2$s', 'cater-ice-cream-core' ), $cov['status'], $cov['message'] ) ) . '</p>';
		echo '<p>' . ( $repo->is_indexable( $city ) ? esc_html__( 'Search engines: indexable (published, set to Index, content reviewed).', 'cater-ice-cream-core' ) : esc_html__( 'Search engines: noindex. Indexing needs Published + Index + reviewed, published city content.', 'cater-ice-cream-core' ) ) . '</p>';

		echo '<h2>' . esc_html__( 'City content', 'cater-ice-cream-core' ) . '</h2>';
		if ( $editorial && get_post( $editorial ) ) {
			echo '<p><a class="button" href="' . esc_url( get_edit_post_link( $editorial, 'url' ) ) . '">' . esc_html__( 'Edit city content', 'cater-ice-cream-core' ) . '</a> ';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( Editorial::preview_url( $editorial ) ) . '">' . esc_html__( 'Preview city page', 'cater-ice-cream-core' ) . '</a> ';
			/* translators: 1: post status, 2: reviewed yes/no. */
			echo esc_html( sprintf( __( 'Record: %1$s; reviewed: %2$s.', 'cater-ice-cream-core' ), get_post_status( $editorial ), $content['reviewed'] ? __( 'yes', 'cater-ice-cream-core' ) : __( 'no', 'cater-ice-cream-core' ) ) ) . '</p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_city_editorial"><input type="hidden" name="city_id" value="' . (int) $id . '">';
			wp_nonce_field( 'cic_city_editorial_' . $id );
			echo '<p>' . esc_html__( 'This city uses the city template from the Service Areas page.', 'cater-ice-cream-core' ) . ' <button class="button">' . esc_html__( 'Create city content', 'cater-ice-cream-core' ) . '</button></p></form>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="cic_city_save"><input type="hidden" name="city_id" value="' . (int) $id . '">';
		wp_nonce_field( 'cic_city_save_' . $id );
		echo '<h2>' . esc_html__( 'City settings', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		$text = static function ( string $name, string $label, string $value, string $help = '' ): void {
			echo '<tr><th scope="row"><label for="cic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" id="cic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$select = static function ( string $name, string $label, string $value, array $options, string $help = '' ): void {
			echo '<tr><th scope="row"><label for="cic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><select id="cic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$text( 'city_name', __( 'City name', 'cater-ice-cream-core' ), $row['city_name'] );
		$text( 'city_slug', __( 'URL slug', 'cater-ice-cream-core' ), $row['city_slug'], __( 'Changing the slug keeps the old URL as a one-hop redirect.', 'cater-ice-cream-core' ) );
		$text( 'county_name', __( 'County', 'cater-ice-cream-core' ), $row['county_name'] );
		$select( 'publish_status', __( 'Page status', 'cater-ice-cream-core' ), $row['publish_status'], array( 'draft' => __( 'Draft (not public, 404)', 'cater-ice-cream-core' ), 'published' => __( 'Published', 'cater-ice-cream-core' ) ) );
		$select( 'index_status', __( 'Search engines', 'cater-ice-cream-core' ), $row['index_status'], array( 'noindex' => __( 'Noindex', 'cater-ice-cream-core' ), 'index' => __( 'Index (only once content is reviewed)', 'cater-ice-cream-core' ) ), __( 'Separate from publishing: a published city can stay out of search engines.', 'cater-ice-cream-core' ) );
		$text( 'display_order', __( 'Order in directories', 'cater-ice-cream-core' ), (string) $row['display_order'] );
		$text( 'latitude', __( 'Latitude', 'cater-ice-cream-core' ), (string) $row['latitude'], __( 'Approximate centre for nearby-city ordering. Never a venue or customer address.', 'cater-ice-cream-core' ) );
		$text( 'longitude', __( 'Longitude', 'cater-ice-cream-core' ), (string) $row['longitude'] );
		$text( 'alias', __( 'Add alias slug', 'cater-ice-cream-core' ), '', __( 'Optional. An alias URL redirects once to this city.', 'cater-ice-cream-core' ) );
		echo '</table>';

		$redirects = CityStore::redirects( $id );
		if ( $redirects ) {
			echo '<h3>' . esc_html__( 'Redirecting slugs', 'cater-ice-cream-core' ) . '</h3><ul>';
			foreach ( $redirects as $r ) {
				echo '<li><code>' . esc_html( $r['slug'] ) . '</code> (' . esc_html( $r['kind'] ) . ') <label><input type="checkbox" name="remove_redirect[]" value="' . esc_attr( $r['slug'] ) . '"> ' . esc_html__( 'remove', 'cater-ice-cream-core' ) . '</label></li>';
			}
			echo '</ul>';
		}

		echo '<h2>' . esc_html__( 'ZIP coverage decisions', 'cater-ice-cream-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Disabled always wins (a disabled ZIP or mapping cannot be re-enabled by another city). A ZIP shared with other communities whose decisions differ asks visitors for the venue city. Notes are internal and never public.', 'cater-ice-cream-core' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>ZIP</th><th>' . esc_html__( 'ZIP status (all cities)', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Decision for this city', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Internal note', 'cater-ice-cream-core' ) . '</th><th>' . esc_html__( 'Shared', 'cater-ice-cream-core' ) . '</th></tr></thead><tbody>';
		foreach ( CityStore::mappings( $id ) as $m ) {
			$zip = esc_attr( $m['zip'] );
			echo '<tr><td><code>' . esc_html( $m['zip'] ) . '</code>' . ( 'fixture' === $m['dataset'] ? ' ' . self::fixture_badge() : '' ) . '</td><td><select name="zip_status[' . $zip . ']" aria-label="' . esc_attr( sprintf( 'ZIP %s status', $m['zip'] ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
			foreach ( Tables::ZIP_STATUSES as $st ) {
				echo '<option value="' . esc_attr( $st ) . '"' . selected( (string) $m['service_status'], $st, false ) . '>' . esc_html( $st ) . '</option>';
			}
			echo '</select></td><td><select name="override[' . $zip . ']" aria-label="' . esc_attr( sprintf( 'ZIP %s decision for this city', $m['zip'] ) ) . '">';
			foreach ( Tables::OVERRIDES as $st ) {
				echo '<option value="' . esc_attr( $st ) . '"' . selected( (string) $m['service_override'], $st, false ) . '>' . esc_html( $st ) . '</option>';
			}
			echo '</select></td><td><input type="text" class="regular-text" name="note[' . $zip . ']" value="' . esc_attr( $m['review_note'] ) . '" aria-label="' . esc_attr( sprintf( 'ZIP %s note', $m['zip'] ) ) . '"></td><td>' . ( (int) $m['shared'] > 1 ? esc_html( sprintf( /* translators: %d: number of cities. */ __( '%d cities', 'cater-ice-cream-core' ), (int) $m['shared'] ) ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save city', 'cater-ice-cream-core' ) );
		echo '</form>';
	}

	public function save_city(): void {
		$id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_save_' . $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cater-ice-cream-core' ) );
		}
		$fields = array();
		foreach ( array( 'city_name', 'city_slug', 'county_name', 'publish_status', 'index_status', 'display_order', 'latitude', 'longitude' ) as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				$fields[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
			}
		}
		$result = CityStore::update_city( $id, $fields );
		$errors = is_wp_error( $result ) ? array( $result->get_error_message() ) : array();

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
		$overrides = (array) ( $_POST['override'] ?? array() );
		$notes     = (array) ( $_POST['note'] ?? array() );
		$statuses  = (array) ( $_POST['zip_status'] ?? array() );
		$current   = array();
		foreach ( CityStore::mappings( $id ) as $m ) {
			$current[ $m['zip'] ] = $m;
		}
		foreach ( $current as $zip => $m ) {
			$override = isset( $overrides[ $zip ] ) ? sanitize_key( wp_unslash( $overrides[ $zip ] ) ) : $m['service_override'];
			$note     = isset( $notes[ $zip ] ) ? sanitize_text_field( wp_unslash( $notes[ $zip ] ) ) : $m['review_note'];
			if ( $override !== $m['service_override'] || $note !== $m['review_note'] ) {
				CityStore::set_override( $id, (string) $zip, $override, $note );
			}
			$status = isset( $statuses[ $zip ] ) ? sanitize_key( wp_unslash( $statuses[ $zip ] ) ) : (string) $m['service_status'];
			if ( $status !== (string) $m['service_status'] ) {
				CityStore::set_zip_status( (string) $zip, $status );
			}
		}
		$args = array( 'city' => $id );
		if ( $errors ) {
			$args['cic_err'] = implode( ' ', $errors );
		} else {
			$args['cic_msg'] = __( 'City saved.', 'cater-ice-cream-core' );
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public function create_editorial(): void {
		$id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_city_editorial_' . $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cater-ice-cream-core' ) );
		}
		$post_id = CityStore::ensure_editorial( $id );
		wp_safe_redirect( $post_id ? get_edit_post_link( $post_id, 'url' ) : self::url( array( 'city' => $id, 'cic_err' => __( 'Could not create the content record.', 'cater-ice-cream-core' ) ) ) );
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
		echo '<p class="description">' . esc_html__( 'Import cities first, then ZIP geography, then city-ZIP mappings. Geography never becomes coverage on its own: new ZIPs start as “unreviewed” unless this is a reviewed coverage dataset. Every upload is a dry run first.', 'cater-ice-cream-core' ) . '</p>';
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
		/* translators: 1-5: counts. */
		echo '<p><strong>' . esc_html( sprintf( __( '%1$d to create, %2$d to update, %3$d unchanged, %4$d rejected (%5$d duplicates).', 'cater-ice-cream-core' ), $c['created'], $c['updated'], $c['unchanged'], $c['rejected'], $c['duplicates'] ) ) . '</strong></p>';
		if ( $report['ignored_columns'] ) {
			echo '<p>' . esc_html__( 'Ignored columns:', 'cater-ice-cream-core' ) . ' <code>' . esc_html( implode( ', ', $report['ignored_columns'] ) ) . '</code></p>';
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
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cater-ice-cream-core' ) );
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
			echo '<tr><td>' . esc_html( $e['time'] ) . '</td><td>' . esc_html( $e['type'] ) . '</td><td>' . esc_html( $e['dataset'] ) . '</td><td>' . esc_html( $e['source'] ) . '</td><td>' . esc_html( $e['dry_run'] ? 'dry run' : 'applied' ) . '</td><td>' . esc_html( sprintf( '+%d ~%d =%d ×%d', $c['created'], $c['updated'], $c['unchanged'], $c['rejected'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
