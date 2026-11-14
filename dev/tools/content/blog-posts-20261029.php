<?php
/**
 * Blog drafts of 2026-10-29: imports docs/content/blog/0*.md as WordPress posts (status Draft).
 *
 * - Markdown (headings, paragraphs, lists, tables, quotes, bold/italic, links) becomes core blocks,
 *   so the posts are edited normally in the block editor. The front matter, the H1 (= post title)
 *   and the "Editor notes" after the last horizontal rule are not imported.
 * - Site links ("/events/wedding-ice-cream-catering/") become absolute for this site; "/blog/{slug}/" links point to
 *   the post's published address /{slug}/ (the site's /%postname%/ permalinks).
 * - Yoast SEO title (brand as %%sep%% %%sitename%%), meta description, focus keyphrase, excerpt,
 *   category "Planning Tips".
 * - Add-only: a post whose slug already exists (any status) is reported and never changed.
 *   Nothing is published; publish from Posts after review (the theme has no post template yet).
 *
 * Usage: wp eval-file dev/tools/content/blog-posts-20261029.php [dry-run] [report=/path/report.json]
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_report = '';
foreach ( $cic_args as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'report=' ) ) {
		$cic_report = substr( $cic_arg, 7 );
	}
}
$cic_dir   = dirname( __DIR__, 3 ) . '/docs/content/blog';
$cic_files = glob( $cic_dir . '/0*.md' );
if ( ! $cic_files ) {
	WP_CLI::error( 'No drafts found in ' . $cic_dir );
}
$cic_yoast = defined( 'WPSEO_VERSION' ) && class_exists( 'WPSEO_Meta' );
WP_CLI::log( ( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' ) . ( $cic_yoast ? '' : ' Yoast SEO is not active: SEO fields are skipped.' ) );

/** Inline Markdown → HTML (text is escaped first). */
$cic_inline = static function ( string $text ): string {
	$html = esc_html( $text );
	$html = preg_replace_callback(
		'/\[([^\]]+)\]\(([^)\s]+)\)/',
		static function ( $m ) {
			$url = html_entity_decode( $m[2] );
			if ( preg_match( '#^/blog/([a-z0-9-]+)/$#', $url, $b ) ) {
				// The post's final address (drafts only have ?p= links): /%postname%/ permalinks.
				$url = home_url( user_trailingslashit( $b[1] ) );
			} elseif ( '/' === substr( $url, 0, 1 ) ) {
				$url = home_url( $url );
			}
			return '<a href="' . esc_url( $url ) . '">' . $m[1] . '</a>';
		},
		$html
	);
	$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
	return preg_replace( '/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $html );
};
$cic_block = static fn( string $name, string $inner, array $attrs = array() ): string => '<!-- wp:' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . " -->\n" . $inner . "\n<!-- /wp:" . $name . ' -->';

/**
 * Markdown body → block markup.
 */
$cic_blocks = static function ( string $md ) use ( $cic_inline, $cic_block ): string {
	$lines = preg_split( '/\R/', trim( $md ) );
	$out   = array();
	$count = count( $lines );
	for ( $i = 0; $i < $count; $i++ ) {
		$line = rtrim( $lines[ $i ] );
		if ( '' === trim( $line ) ) {
			continue;
		}
		if ( preg_match( '/^(#{2,4})\s+(.+)$/', $line, $m ) ) {
			$level = strlen( $m[1] );
			$out[] = $cic_block( 'heading', '<h' . $level . ' class="wp-block-heading">' . $cic_inline( $m[2] ) . '</h' . $level . '>', 2 === $level ? array() : array( 'level' => $level ) );
			continue;
		}
		if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+/', $line, $m ) ) {
			$ordered = ctype_digit( rtrim( $m[2], '.' ) );
			$items   = array();
			while ( $i < $count && preg_match( '/^\s*([-*]|\d+\.)\s+(.+)$/', rtrim( $lines[ $i ] ), $li ) ) {
				$items[] = $cic_block( 'list-item', '<li>' . $cic_inline( $li[2] ) . '</li>' );
				$i++;
			}
			$i--;
			$tag   = $ordered ? 'ol' : 'ul';
			$out[] = $cic_block( 'list', '<' . $tag . ' class="wp-block-list">' . implode( "\n", $items ) . '</' . $tag . '>', $ordered ? array( 'ordered' => true ) : array() );
			continue;
		}
		if ( '|' === substr( ltrim( $line ), 0, 1 ) ) {
			$rows = array();
			while ( $i < $count && '|' === substr( ltrim( $lines[ $i ] ), 0, 1 ) ) {
				$rows[] = rtrim( $lines[ $i ] );
				$i++;
			}
			$i--;
			$cells = static fn( string $row ): array => array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) );
			$head  = $cells( array_shift( $rows ) );
			$rows  = array_filter( $rows, static fn( $r ) => ! preg_match( '/^\|?\s*:?-{3,}/', trim( $r ) ) );
			$html  = '<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr>';
			foreach ( $head as $c ) {
				$html .= '<th>' . $cic_inline( $c ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$html .= '<tr>';
				foreach ( $cells( $r ) as $c ) {
					$html .= '<td>' . $cic_inline( $c ) . '</td>';
				}
				$html .= '</tr>';
			}
			$out[] = $cic_block( 'table', $html . '</tbody></table></figure>' );
			continue;
		}
		if ( '>' === substr( ltrim( $line ), 0, 1 ) ) {
			$text = array();
			while ( $i < $count && '>' === substr( ltrim( $lines[ $i ] ), 0, 1 ) ) {
				$text[] = trim( ltrim( trim( $lines[ $i ] ), '>' ) );
				$i++;
			}
			$i--;
			$out[] = $cic_block( 'quote', '<blockquote class="wp-block-quote">' . $cic_block( 'paragraph', '<p>' . $cic_inline( implode( ' ', $text ) ) . '</p>' ) . '</blockquote>' );
			continue;
		}
		$text = array( trim( $line ) );
		while ( $i + 1 < $count && '' !== trim( $lines[ $i + 1 ] ) && ! preg_match( '/^(#{2,4}\s|\s*([-*]|\d+\.)\s|\s*[|>])/', $lines[ $i + 1 ] ) ) {
			$text[] = trim( $lines[ ++$i ] );
		}
		$out[] = $cic_block( 'paragraph', '<p>' . $cic_inline( implode( ' ', $text ) ) . '</p>' );
	}
	return implode( "\n\n", $out );
};

/* Category. */
$cic_cat = get_term_by( 'slug', 'planning-tips', 'category' );
if ( ! $cic_cat ) {
	WP_CLI::log( ( $cic_dry ? 'would add' : 'add' ) . '  category "Planning Tips"' );
	if ( ! $cic_dry ) {
		$cic_term = wp_insert_term( 'Planning Tips', 'category', array( 'slug' => 'planning-tips' ) );
		$cic_cat  = is_wp_error( $cic_term ) ? null : get_term( $cic_term['term_id'], 'category' );
	}
}
$cic_author = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
$cic_author = $cic_author ? (int) $cic_author[0] : 0;

$cic_log = array();
foreach ( $cic_files as $cic_file ) {
	$cic_raw = (string) file_get_contents( $cic_file );
	if ( ! preg_match( '/^---\R(.*?)\R---\R(.*)$/s', $cic_raw, $cic_m ) ) {
		WP_CLI::warning( basename( $cic_file ) . ': no front matter, skipped.' );
		continue;
	}
	$cic_meta = array();
	foreach ( preg_split( '/\R/', $cic_m[1] ) as $cic_row ) {
		if ( preg_match( '/^([a-z_]+):\s*"?(.*?)"?\s*$/', $cic_row, $cic_kv ) ) {
			$cic_meta[ $cic_kv[1] ] = $cic_kv[2];
		}
	}
	$cic_body = preg_replace( '/^#\s+.+\R/m', '', $cic_m[2], 1 );           // H1 = post title.
	$cic_body = preg_replace( '/\R---\R\s*\*\*Editor notes.*$/s', '', $cic_body ); // Editor notes.
	$cic_slug = sanitize_title( $cic_meta['slug'] ?? '' );
	$cic_name = basename( $cic_file );
	if ( '' === $cic_slug || empty( $cic_meta['title'] ) ) {
		WP_CLI::warning( $cic_name . ': title or slug missing, skipped.' );
		continue;
	}
	// Direct slug lookup, any status (WP_Query hides unpublished single posts without a logged-in user).
	$cic_found    = get_page_by_path( $cic_slug, OBJECT, 'post' );
	$cic_existing = $cic_found ? array( $cic_found ) : array();
	if ( $cic_existing ) {
		$cic_log[] = array( 'file' => $cic_name, 'result' => 'kept', 'post' => $cic_existing[0]->ID, 'status' => $cic_existing[0]->post_status );
		WP_CLI::log( sprintf( 'kept      #%d %s (%s; already exists, not changed)', $cic_existing[0]->ID, $cic_slug, $cic_existing[0]->post_status ) );
		continue;
	}
	$cic_content = $cic_blocks( $cic_body );
	$cic_parsed  = parse_blocks( $cic_content );
	$cic_free    = array_filter( $cic_parsed, static fn( $b ) => null === $b['blockName'] && '' !== trim( $b['innerHTML'] ) );
	if ( $cic_free ) {
		WP_CLI::warning( $cic_name . ': content outside blocks; check the Markdown.' );
	}
	$cic_entry = array( 'file' => $cic_name, 'result' => $cic_dry ? 'would add' : 'added', 'slug' => $cic_slug, 'title' => $cic_meta['title'], 'blocks' => count( array_filter( $cic_parsed, static fn( $b ) => null !== $b['blockName'] ) ) );
	if ( ! $cic_dry ) {
		$cic_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'draft',
				'post_title'    => $cic_meta['title'],
				'post_name'     => $cic_slug,
				'post_content'  => $cic_content,
				'post_excerpt'  => $cic_meta['meta_description'] ?? '',
				'post_author'   => $cic_author,
				'post_category' => $cic_cat ? array( (int) $cic_cat->term_id ) : array(),
			),
			true
		);
		if ( is_wp_error( $cic_id ) ) {
			WP_CLI::warning( $cic_name . ': ' . $cic_id->get_error_message() );
			continue;
		}
		if ( $cic_yoast ) {
			WPSEO_Meta::set_value( 'title', preg_replace( '/\s*\|\s*CaterIceCream\.com$/', ' %%sep%% %%sitename%%', (string) ( $cic_meta['yoast_title'] ?? '' ) ), $cic_id );
			WPSEO_Meta::set_value( 'metadesc', (string) ( $cic_meta['meta_description'] ?? '' ), $cic_id );
			WPSEO_Meta::set_value( 'focuskw', (string) ( $cic_meta['focus_keyword'] ?? '' ), $cic_id );
		}
		$cic_entry['post'] = $cic_id;
	}
	$cic_log[] = $cic_entry;
	WP_CLI::log( sprintf( '%-9s %s "%s" (%d blocks, draft)', $cic_entry['result'], $cic_slug, $cic_meta['title'], $cic_entry['blocks'] ) );
}

if ( '' !== $cic_report ) {
	file_put_contents( $cic_report, wp_json_encode( array( 'run' => gmdate( 'c' ), 'site' => home_url( '/' ), 'dry_run' => $cic_dry, 'posts' => $cic_log ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	WP_CLI::log( 'Report: ' . $cic_report );
}
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done. Posts are Drafts: review and publish in Posts.' );
