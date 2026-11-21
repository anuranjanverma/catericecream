<?php
/**
 * Service Areas → Venues (capability manage_locations; every action nonce-checked, validated in
 * VenueStore, audited and cache-invalidating). Venue names feed the "Popular Event Venues" section
 * on city pages (that city's venues, max 12) and event pages (venues tagged with the occasion in
 * the served cities, max 32).
 *
 * Draft = not verified yet (shown only on local/development for layout review). Approved = shown on
 * the live site. Hidden = never shown.
 *
 * @package CIC
 */

namespace CIC\Locations\Admin;

use CIC\Locations\CityStore;
use CIC\Locations\Tables;
use CIC\Locations\VenueStore;

defined( 'ABSPATH' ) || exit;

final class VenuesPage {

	public const SLUG = 'cic-locations-venues';

	private const PER_PAGE = 50;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_cic_venue_save', array( $this, 'save' ) );
		add_action( 'admin_post_cic_venue_bulk', array( $this, 'bulk' ) );
	}

	public function menu(): void {
		add_submenu_page( LocationsPage::SLUG, __( 'Event venues', 'cater-ice-cream-core' ), __( 'Venues', 'cater-ice-cream-core' ), Tables::CAPABILITY, self::SLUG, array( $this, 'render' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function deny(): void {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
	}

	/** @return array<string, string> */
	private static function status_labels(): array {
		return array(
			'draft'    => __( 'Draft (not verified)', 'cater-ice-cream-core' ),
			'approved' => __( 'Approved (shown)', 'cater-ice-cream-core' ),
			'hidden'   => __( 'Hidden', 'cater-ice-cream-core' ),
		);
	}

	private static function pill( string $status ): string {
		$tone  = array( 'approved' => 'good', 'draft' => 'warn', 'hidden' => 'neutral' )[ $status ] ?? 'neutral';
		$label = self::status_labels()[ $status ] ?? $status;
		return '<span class="cic-pill cic-pill--' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function styles(): void {
		echo '<style>
.cic-pill{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;line-height:18px;background:#f0f0f1;color:#3c434a;white-space:nowrap}
.cic-pill--good{background:#edfaef;color:#00450c}.cic-pill--warn{background:#fcf9e8;color:#614200}
.cic-venue-filters{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:1em 0}
.cic-venue-occasions{display:flex;flex-wrap:wrap;gap:4px 16px}.cic-venue-occasions label{white-space:nowrap}
#cic-venue-list td,#cic-venue-list th{vertical-align:top}#cic-venue-list .check-column{width:2.2em}
</style>';
	}

	private function notices(): void {
		foreach ( array( 'cic_msg' => 'notice-success is-dismissible', 'cic_err' => 'notice-error' ) as $key => $class ) {
			$text = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			if ( '' !== $text ) {
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
			}
		}
	}

	public function render(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage locations.', 'cater-ice-cream-core' ) );
		}
		echo '<div class="wrap">';
		self::styles();
		if ( ! Tables::ready() || ! VenueStore::exists() ) {
			echo '<h1>' . esc_html__( 'Event venues', 'cater-ice-cream-core' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The venue table is not installed yet. Reload this screen to run the pending database update.', 'cater-ice-cream-core' ) . '</p></div></div>';
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['venue'] ) ? absint( $_GET['venue'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'new' === $action || $id ) {
			$this->render_form( $id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$filter = array(
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'city_id'  => isset( $_GET['city'] ) ? absint( $_GET['city'] ) : 0,
			'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'occasion' => isset( $_GET['occasion'] ) ? sanitize_key( wp_unslash( $_GET['occasion'] ) ) : '',
		);
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$result    = VenueStore::search( $filter, $paged, self::PER_PAGE );
		$counts    = VenueStore::counts();
		$cities    = VenueStore::city_options();
		$occasions = VenueStore::occasions();
		$labels    = self::status_labels();

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Event venues', 'cater-ice-cream-core' ) . '</h1> ';
		echo '<a class="page-title-action" id="cic-add-venue" href="' . esc_url( self::url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Add Venue', 'cater-ice-cream-core' ) . '</a><hr class="wp-header-end">';
		$this->notices();
		echo '<p class="description">' . esc_html__( 'Names shown under “Popular Event Venues”: each city page lists up to 12 of its own venues, and each event page lists up to 32 venues tagged with that occasion across the cities we serve. Only the name is shown.', 'cater-ice-cream-core' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'Check every Draft before approving it:', 'cater-ice-cream-core' ) . '</strong> ' . esc_html__( 'the venue exists, is open, is in this city and hosts this kind of event. Drafts are never shown on the live site. Lower “Order” numbers are listed first.', 'cater-ice-cream-core' ) . '</p>';
		echo '<ul class="subsubsub">';
		$links = array( '<a href="' . esc_url( self::url() ) . '"' . ( '' === $filter['status'] ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'cater-ice-cream-core' ) . ' <span class="count">(' . (int) array_sum( $counts ) . ')</span></a>' );
		foreach ( $counts as $status => $n ) {
			$links[] = '<a href="' . esc_url( self::url( array( 'status' => $status ) ) ) . '"' . ( $status === $filter['status'] ? ' class="current"' : '' ) . '>' . esc_html( $labels[ $status ] ) . ' <span class="count">(' . (int) $n . ')</span></a>';
		}
		echo '<li>' . implode( ' | </li><li>', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.

		echo '<form method="get" class="cic-venue-filters"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label class="screen-reader-text" for="cic-venue-search">' . esc_html__( 'Search venues', 'cater-ice-cream-core' ) . '</label><input type="search" id="cic-venue-search" name="s" value="' . esc_attr( $filter['s'] ) . '" placeholder="' . esc_attr__( 'Venue name', 'cater-ice-cream-core' ) . '">';
		echo '<label class="screen-reader-text" for="cic-venue-city">' . esc_html__( 'City', 'cater-ice-cream-core' ) . '</label><select id="cic-venue-city" name="city"><option value="0">' . esc_html__( 'All cities', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $cities as $cid => $label ) {
			echo '<option value="' . (int) $cid . '"' . selected( $filter['city_id'], $cid, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><label class="screen-reader-text" for="cic-venue-occasion">' . esc_html__( 'Occasion', 'cater-ice-cream-core' ) . '</label><select id="cic-venue-occasion" name="occasion"><option value="">' . esc_html__( 'Any occasion', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $occasions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $filter['occasion'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><label class="screen-reader-text" for="cic-venue-status">' . esc_html__( 'Status', 'cater-ice-cream-core' ) . '</label><select id="cic-venue-status" name="status"><option value="">' . esc_html__( 'Any status', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $labels as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $filter['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><button class="button">' . esc_html__( 'Filter', 'cater-ice-cream-core' ) . '</button></form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="cic-venue-bulk">';
		wp_nonce_field( 'cic_venue_bulk' );
		echo '<input type="hidden" name="action" value="cic_venue_bulk"><input type="hidden" name="back" value="' . esc_attr( remove_query_arg( array( 'cic_msg', 'cic_err' ), wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ) . '">'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- escaped; validated on redirect.
		echo '<div class="tablenav top"><div class="alignleft actions bulkactions"><label class="screen-reader-text" for="cic-venue-bulk-action">' . esc_html__( 'Bulk action', 'cater-ice-cream-core' ) . '</label><select id="cic-venue-bulk-action" name="bulk"><option value="">' . esc_html__( 'Bulk actions', 'cater-ice-cream-core' ) . '</option>';
		echo '<option value="approved">' . esc_html__( 'Approve (show on site)', 'cater-ice-cream-core' ) . '</option><option value="draft">' . esc_html__( 'Back to Draft', 'cater-ice-cream-core' ) . '</option><option value="hidden">' . esc_html__( 'Hide', 'cater-ice-cream-core' ) . '</option><option value="delete">' . esc_html__( 'Delete permanently', 'cater-ice-cream-core' ) . '</option>';
		echo '</select> <button class="button action">' . esc_html__( 'Apply', 'cater-ice-cream-core' ) . '</button></div></div>';

		$heads = array( __( 'Venue', 'cater-ice-cream-core' ), __( 'City', 'cater-ice-cream-core' ), __( 'Occasions', 'cater-ice-cream-core' ), __( 'Order', 'cater-ice-cream-core' ), __( 'Status', 'cater-ice-cream-core' ), __( 'Source', 'cater-ice-cream-core' ) );
		echo '<table class="widefat striped" id="cic-venue-list"><thead><tr><td class="manage-column check-column"><label class="screen-reader-text" for="cic-venue-all">' . esc_html__( 'Select all', 'cater-ice-cream-core' ) . '</label><input type="checkbox" id="cic-venue-all"></td>';
		foreach ( $heads as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $result['rows'] as $v ) {
			$names = array_map( static fn( $k ) => $occasions[ $k ] ?? $k, $v['occasions'] );
			echo '<tr data-venue="' . (int) $v['id'] . '"><th scope="row" class="check-column"><label class="screen-reader-text" for="cic-venue-' . (int) $v['id'] . '">' . esc_html( $v['name'] ) . '</label><input type="checkbox" id="cic-venue-' . (int) $v['id'] . '" name="ids[]" value="' . (int) $v['id'] . '"></th>';
			echo '<td><a class="row-title" href="' . esc_url( self::url( array( 'venue' => $v['id'] ) ) ) . '">' . esc_html( $v['name'] ) . '</a></td>';
			echo '<td>' . esc_html( $v['city_label'] ) . ( $v['city_published'] ? '' : ' <span class="description">(' . esc_html__( 'city page not published', 'cater-ice-cream-core' ) . ')</span>' ) . '</td>';
			echo '<td>' . esc_html( $names ? implode( ', ', $names ) : '—' ) . '</td>';
			echo '<td>' . (int) $v['sort_order'] . '</td>';
			echo '<td>' . self::pill( $v['status'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill().
			echo '<td>' . esc_html( $v['source'] ) . '</td></tr>';
		}
		if ( ! $result['rows'] ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No venues found.', 'cater-ice-cream-core' ) . '</td></tr>';
		}
		echo '</tbody></table></form>';
		echo "<script>(function(){var a=document.getElementById('cic-venue-all');if(a){a.addEventListener('change',function(){document.querySelectorAll('#cic-venue-list tbody input[type=checkbox]').forEach(function(c){c.checked=a.checked;});});}var f=document.getElementById('cic-venue-bulk');f&&f.addEventListener('submit',function(e){if(f.bulk.value==='delete'&&!window.confirm(" . wp_json_encode( __( 'Delete the selected venues permanently?', 'cater-ice-cream-core' ) ) . ")){e.preventDefault();}});})();</script>";
		$pages = (int) ceil( $result['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) ) . '</p>';
		}
	}

	private function render_form( int $id ): void {
		$venue = $id ? VenueStore::get( $id ) : null;
		if ( $id && ! $venue ) {
			echo '<h1>' . esc_html__( 'Event venues', 'cater-ice-cream-core' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'That venue no longer exists.', 'cater-ice-cream-core' ) . '</p></div>';
			return;
		}
		$venue = $venue ?? array( 'id' => 0, 'city_id' => isset( $_GET['city'] ) ? absint( $_GET['city'] ) : 0, 'name' => '', 'occasions' => array(), 'sort_order' => 0, 'status' => 'draft', 'source' => '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<h1>' . esc_html( $id ? __( 'Edit venue', 'cater-ice-cream-core' ) : __( 'Add Venue', 'cater-ice-cream-core' ) ) . '</h1>';
		echo '<p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All venues', 'cater-ice-cream-core' ) . '</a></p>';
		$this->notices();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="cic-venue-form">';
		wp_nonce_field( 'cic_venue_save_' . $venue['id'] );
		echo '<input type="hidden" name="action" value="cic_venue_save"><input type="hidden" name="venue_id" value="' . (int) $venue['id'] . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="cic-venue-name">' . esc_html__( 'Venue name', 'cater-ice-cream-core' ) . '</label></th><td><input class="regular-text" id="cic-venue-name" name="name" maxlength="' . (int) VenueStore::NAME_MAX . '" required value="' . esc_attr( $venue['name'] ) . '"><span class="description" style="display:block">' . esc_html__( 'Only the name is shown on the site.', 'cater-ice-cream-core' ) . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="cic-venue-city-field">' . esc_html__( 'City', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-venue-city-field" name="city_id" required><option value="">' . esc_html__( 'Choose a city', 'cater-ice-cream-core' ) . '</option>';
		foreach ( VenueStore::city_options() as $cid => $label ) {
			echo '<option value="' . (int) $cid . '"' . selected( (int) $venue['city_id'], $cid, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Suited to', 'cater-ice-cream-core' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Suited to', 'cater-ice-cream-core' ) . '</legend><div class="cic-venue-occasions">';
		foreach ( VenueStore::occasions() as $key => $label ) {
			echo '<label><input type="checkbox" name="occasions[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $venue['occasions'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><span class="description" style="display:block">' . esc_html__( 'The venue appears on these event pages (if its city is served). City pages show it either way.', 'cater-ice-cream-core' ) . '</span></fieldset></td></tr>';
		echo '<tr><th scope="row"><label for="cic-venue-order">' . esc_html__( 'Order', 'cater-ice-cream-core' ) . '</label></th><td><input type="number" class="small-text" id="cic-venue-order" name="sort_order" min="-9999" max="9999" value="' . (int) $venue['sort_order'] . '"><span class="description" style="display:block">' . esc_html__( 'Lower numbers are listed first.', 'cater-ice-cream-core' ) . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="cic-venue-status-field">' . esc_html__( 'Status', 'cater-ice-cream-core' ) . '</label></th><td><select id="cic-venue-status-field" name="status">';
		foreach ( self::status_labels() as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $venue['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><span class="description" style="display:block">' . esc_html__( 'Approve only after checking that the venue exists, is open, is in this city and hosts this kind of event.', 'cater-ice-cream-core' ) . '</span></td></tr>';
		if ( '' !== $venue['source'] ) {
			echo '<tr><th scope="row">' . esc_html__( 'Source', 'cater-ice-cream-core' ) . '</th><td>' . esc_html( $venue['source'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( $id ? __( 'Save venue', 'cater-ice-cream-core' ) : __( 'Add venue', 'cater-ice-cream-core' ) );
		echo '</form>';
	}

	public function save(): void {
		$id = isset( $_POST['venue_id'] ) ? absint( $_POST['venue_id'] ) : 0;
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_venue_save_' . $id ) ) {
			self::deny();
		}
		$data   = array(
			'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'city_id'    => isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0,
			'occasions'  => isset( $_POST['occasions'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['occasions'] ) ) : array(),
			'sort_order' => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0,
			'status'     => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft',
		);
		$result = VenueStore::save( $data, $id );
		if ( is_wp_error( $result ) ) {
			$back = $id ? array( 'venue' => $id ) : array( 'action' => 'new', 'city' => $data['city_id'] );
			wp_safe_redirect( self::url( $back + array( 'cic_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		CityStore::audit( $id ? 'venue_update' : 'venue_create', sprintf( 'venue %d (%s) city %d status %s', $result, $data['name'], $data['city_id'], $data['status'] ) );
		wp_safe_redirect( self::url( array( 'cic_msg' => rawurlencode( $id ? __( 'Venue saved.', 'cater-ice-cream-core' ) : __( 'Venue added.', 'cater-ice-cream-core' ) ) ) ) );
		exit;
	}

	public function bulk(): void {
		if ( ! current_user_can( Tables::CAPABILITY ) || ! check_admin_referer( 'cic_venue_bulk' ) ) {
			self::deny();
		}
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$op   = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
		$back = remove_query_arg( array( 'cic_msg', 'cic_err' ), wp_validate_redirect( $back, self::url() ) );
		if ( ! $ids || '' === $op ) {
			wp_safe_redirect( add_query_arg( 'cic_err', rawurlencode( __( 'Choose venues and an action.', 'cater-ice-cream-core' ) ), $back ) );
			exit;
		}
		if ( 'delete' === $op ) {
			$n = VenueStore::delete( $ids );
			/* translators: %d: number of venues. */
			$msg = sprintf( _n( '%d venue deleted.', '%d venues deleted.', $n, 'cater-ice-cream-core' ), $n );
		} elseif ( in_array( $op, VenueStore::STATUSES, true ) ) {
			$n = VenueStore::set_status( $ids, $op );
			/* translators: 1: number of venues, 2: status. */
			$msg = sprintf( _n( '%1$d venue set to %2$s.', '%1$d venues set to %2$s.', $n, 'cater-ice-cream-core' ), $n, self::status_labels()[ $op ] );
		} else {
			self::deny();
		}
		CityStore::audit( 'venue_bulk', sprintf( '%s: %s', $op, implode( ',', array_slice( $ids, 0, 40 ) ) ) );
		wp_safe_redirect( add_query_arg( 'cic_msg', rawurlencode( $msg ), $back ) );
		exit;
	}
}
