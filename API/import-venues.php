<?php
/**
 * Browser importer for the "Popular Event Venues" starter list (owner request, 2026-11-21).
 * Open https://<site>/API/import-venues.php while logged in as an administrator.
 *
 * Safety:
 *   - Logged-out visitors are sent to the WordPress login; only users with manage_locations
 *     (administrators) can use it. Nothing is written on a GET: the page shows a preview first.
 *   - Writing needs the form button (POST + nonce). Never cached, never indexed.
 *   - Add-only (CIC\Locations\VenueImporter): existing venues and admin edits are never changed,
 *     venues an admin deleted are never re-added. Creates the venue table if it is missing.
 *   - New venues are Draft by default (never shown on staging/production); "Approved" is an
 *     explicit owner choice on the form. Rollback removes only untouched starter Drafts.
 *
 * @package CIC
 */

// Load WordPress from the site root (this file lives in /API/).
require_once dirname( __DIR__ ) . '/wp-load.php';

nocache_headers();
header( 'X-Robots-Tag: noindex, nofollow', true );
header( 'Content-Type: text/html; charset=utf-8' );

if ( ! is_user_logged_in() ) {
	auth_redirect(); // Sends to wp-login.php and back here; exits.
}
if ( ! class_exists( '\CIC\Locations\VenueImporter' ) || ! current_user_can( \CIC\Locations\Tables::CAPABILITY ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to import venues.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
}

use CIC\Locations\VenueImporter;
use CIC\Locations\VenueStore;

$cic_ready  = VenueImporter::ensure_table();
$cic_action = '';
$cic_result = null;
$cic_status = 'draft';

if ( $cic_ready && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	check_admin_referer( 'cic_api_import_venues' );
	$cic_action = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	$cic_status = isset( $_POST['status'] ) && 'approved' === $_POST['status'] ? 'approved' : 'draft';
	if ( 'import' === $cic_action ) {
		$cic_result = VenueImporter::run( false, $cic_status );
	} elseif ( 'rollback' === $cic_action ) {
		$cic_result = VenueImporter::rollback( false );
	}
	if ( $cic_result ) {
		VenueImporter::purge_page_cache();
	}
}
$cic_preview = $cic_ready ? VenueImporter::run( true ) : null;
$cic_counts  = $cic_ready ? VenueStore::counts() : array();
$cic_self    = home_url( '/API/import-venues.php' );
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Import venues</title>
<style>
body{margin:0;background:#f0f0f1;color:#1d2327;font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:760px;margin:32px auto;padding:0 16px}
.card{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:20px 24px;margin:0 0 16px}
h1{font-size:24px;margin:0 0 16px}h2{font-size:17px;margin:0 0 10px}
.ok{border-left:4px solid #00a32a}.warn{border-left:4px solid #dba617}.err{border-left:4px solid #d63638}
table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:4px 8px;border-bottom:1px solid #f0f0f1}
label{display:block;margin:6px 0}button{font:inherit;padding:8px 16px;border-radius:4px;border:1px solid #2271b1;background:#2271b1;color:#fff;cursor:pointer}
button.secondary{background:#fff;color:#b32d2e;border-color:#b32d2e}small{color:#50575e}
</style>
</head>
<body>
<main>
<h1>Import “Popular Event Venues” starter list</h1>

<?php if ( ! $cic_ready ) : ?>
	<div class="card err"><p>The venue table could not be created. Check that the Cater Ice Cream Core plugin is active and up to date.</p></div>
<?php else : ?>

	<?php if ( 'import' === $cic_action && $cic_result ) : ?>
		<div class="card <?php echo $cic_result['errors'] ? 'warn' : 'ok'; ?>">
			<h2>Done</h2>
			<p><strong><?php echo (int) $cic_result['added']; ?></strong> venues added as <strong><?php echo esc_html( ucfirst( $cic_status ) ); ?></strong>;
			<?php echo (int) $cic_result['exists']; ?> already present (unchanged);
			<?php echo (int) $cic_result['removed_before']; ?> previously deleted (not re-added).</p>
			<?php foreach ( $cic_result['errors'] as $cic_e ) : ?>
				<p><?php echo esc_html( $cic_e ); ?></p>
			<?php endforeach; ?>
			<p>Page caches were refreshed. Next: <a href="<?php echo esc_url( admin_url( 'admin.php?page=cic-locations-venues' ) ); ?>">Service Areas → Venues</a>.</p>
		</div>
	<?php elseif ( 'rollback' === $cic_action && $cic_result ) : ?>
		<div class="card ok"><h2>Rolled back</h2><p><?php echo (int) $cic_result['removed']; ?> starter drafts removed; <?php echo (int) $cic_result['kept']; ?> kept (approved, hidden or edited).</p></div>
	<?php endif; ?>

	<div class="card">
		<h2>Current venues on this site</h2>
		<p>Draft: <strong><?php echo (int) ( $cic_counts['draft'] ?? 0 ); ?></strong> · Approved (shown): <strong><?php echo (int) ( $cic_counts['approved'] ?? 0 ); ?></strong> · Hidden: <strong><?php echo (int) ( $cic_counts['hidden'] ?? 0 ); ?></strong></p>
		<p><small>Environment: <?php echo esc_html( wp_get_environment_type() ); ?>. Drafts are shown on pages only on local/development; on this environment they are <?php echo VenueStore::drafts_visible() ? 'shown' : '<strong>not shown</strong> until approved'; ?>.</small></p>
	</div>

	<div class="card <?php echo $cic_preview['added'] ? 'warn' : 'ok'; ?>">
		<h2>Preview</h2>
		<?php if ( $cic_preview['added'] ) : ?>
			<p><strong><?php echo (int) $cic_preview['added']; ?></strong> venues in <?php echo count( $cic_preview['by_city'] ); ?> cities would be added. <?php echo (int) $cic_preview['exists']; ?> are already here and will not change.</p>
		<?php else : ?>
			<p>Nothing to add: all <?php echo (int) $cic_preview['exists']; ?> starter venues are already on this site<?php echo $cic_preview['removed_before'] ? ' (' . (int) $cic_preview['removed_before'] . ' were deleted by an admin and stay deleted)' : ''; ?>.</p>
		<?php endif; ?>
		<?php if ( $cic_preview['unknown_cities'] ) : ?>
			<p><small>Cities not on this site (skipped): <?php echo esc_html( implode( ', ', $cic_preview['unknown_cities'] ) ); ?></small></p>
		<?php endif; ?>
		<?php if ( $cic_preview['by_city'] ) : ?>
			<details><summary>Per city</summary><table><?php foreach ( $cic_preview['by_city'] as $cic_city => $cic_n ) : ?><tr><td><?php echo esc_html( $cic_city ); ?></td><td><?php echo (int) $cic_n; ?></td></tr><?php endforeach; ?></table></details>
		<?php endif; ?>

		<?php if ( $cic_preview['added'] ) : ?>
			<form method="post" action="<?php echo esc_url( $cic_self ); ?>">
				<?php wp_nonce_field( 'cic_api_import_venues' ); ?>
				<input type="hidden" name="do" value="import">
				<p><strong>Status for the new venues</strong></p>
				<label><input type="radio" name="status" value="draft" checked> Draft — not shown on staging or the live site until approved in Service Areas → Venues (recommended)</label>
				<label><input type="radio" name="status" value="approved"> Approved — shown immediately. <small>The names were drafted from general knowledge and are not verified.</small></label>
				<p><button type="submit">Import venues</button></p>
			</form>
		<?php endif; ?>
	</div>

	<div class="card">
		<h2>Undo</h2>
		<p><small>Removes the starter venues that are still Draft and unedited. Approved, hidden and your own venues stay.</small></p>
		<form method="post" action="<?php echo esc_url( $cic_self ); ?>" onsubmit="return confirm('Remove the untouched starter drafts?');">
			<?php wp_nonce_field( 'cic_api_import_venues' ); ?>
			<input type="hidden" name="do" value="rollback">
			<button type="submit" class="secondary">Remove starter drafts</button>
		</form>
	</div>
<?php endif; ?>
</main>
</body>
</html>
