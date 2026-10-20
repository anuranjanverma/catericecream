<?php
/**
 * Template data for treat and event pages. Templates receive plain arrays built from post meta,
 * WooCommerce fields and the pairing service, never ACF calls, so pages keep rendering if ACF is
 * deactivated. Public views include only published counterpart records.
 *
 * @package CIC
 */

namespace CIC\Content;

use CIC\Design\Schema;
use CIC\Site\Contact;

defined( 'ABSPATH' ) || exit;

final class ViewModel {

	/** Limited inline markup for headings: <em> accent, <strong> second accent. */
	public static function rich( string $text ): string {
		return wp_kses( trim( $text ), array( 'em' => array(), 'strong' => array() ) );
	}

	/** Multi-line heading: one <span class="cic-line"> per row. */
	public static function lines( string $text ): string {
		$rows = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ) );
		return implode( ' ', array_map( static fn( $row ) => '<span class="cic-line">' . self::rich( $row ) . '</span>', $rows ) );
	}

	private static function m( int $id, string $key ): string {
		return trim( (string) get_post_meta( $id, $key, true ) );
	}

	private static function img( int $id, string $key ): int {
		$value = (int) get_post_meta( $id, $key, true );
		return $value && wp_attachment_is_image( $value ) ? $value : 0;
	}

	/**
	 * @param array<int, array<string, mixed>> $items Items to filter.
	 * @return array<int, array<string, mixed>>
	 */
	private static function filled( array $items, string $required ): array {
		return array_values( array_filter( $items, static fn( $i ) => '' !== (string) $i[ $required ] ) );
	}

	/**
	 * Enabled, non-empty sections sorted by their order number. An empty order keeps the approved
	 * position; $layout (a composition's approved key list) limits and orders the candidates.
	 *
	 * @param array<string, string> $defined Section definitions (labels; default order).
	 * @param array<string, bool>   $has     Section key => has content.
	 * @param string[]|null         $layout  Approved keys for the page's composition, in order.
	 * @return array<int, array{key: string, preset: string}>
	 */
	private static function sections( int $id, array $defined, array $has, ?array $layout = null ): array {
		$out  = array();
		$i    = 0;
		$keys = null === $layout ? array_keys( $defined ) : array_values( array_intersect( $layout, array_keys( $defined ) ) );
		foreach ( $keys as $key ) {
			$i++;
			$on = get_post_meta( $id, 'cic_sec_' . $key . '_on', true );
			if ( '0' === (string) $on || empty( $has[ $key ] ) ) {
				continue;
			}
			$order  = get_post_meta( $id, 'cic_sec_' . $key . '_order', true );
			$preset = self::m( $id, 'cic_sec_' . $key . '_preset' );
			$out[]  = array(
				'key'    => $key,
				'preset' => isset( Schema::contexts()[ $preset ] ) ? $preset : '',
				'order'  => is_numeric( $order ) ? (int) $order : $i * 10,
				'index'  => $i,
			);
		}
		usort( $out, static fn( $a, $b ) => array( $a['order'], $a['index'] ) <=> array( $b['order'], $b['index'] ) );
		return $out;
	}

	/** Quote link carrying an explicit selection (the quote flow arrives in Phase 06). */
	public static function quote_url( string $param, string $key ): string {
		return add_query_arg( $param, rawurlencode( $key ), Contact::get()['quote_url'] );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function faqs( int $id ): array {
		$items = array();
		for ( $i = 1; $i <= Fields::FAQ_SLOTS; $i++ ) {
			$items[] = array(
				'q' => self::m( $id, "cic_faq_{$i}_q" ),
				'a' => self::m( $id, "cic_faq_{$i}_a" ),
			);
		}
		return array_values( array_filter( $items, static fn( $f ) => '' !== $f['q'] && '' !== $f['a'] ) );
	}

	/**
	 * Published treats as cards (homepage, hub and city treat sections).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function treat_cards(): array {
		$treats = array();
		foreach ( Catalog::selectable_products() as $item ) {
			$product  = wc_get_product( $item['id'] );
			$treats[] = array(
				'id'      => $item['id'],
				'key'     => $item['key'],
				'title'   => $item['title'],
				'url'     => get_permalink( $item['id'] ),
				'image'   => $product ? (int) $product->get_image_id() : 0,
				'tagline' => self::m( $item['id'], 'cic_card_tagline' ),
				/* translators: %s: treat name. */
				'link'    => self::m( $item['id'], 'cic_card_link' ) ?: sprintf( __( 'Explore %s', 'cater-ice-cream-core' ), $item['title'] ),
			);
		}
		return $treats;
	}

	/**
	 * Published events as cards.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function event_cards(): array {
		$events = array();
		foreach ( Catalog::selectable_events() as $item ) {
			$events[] = array(
				'id'      => $item['id'],
				'key'     => $item['key'],
				'title'   => $item['title'],
				'url'     => get_permalink( $item['id'] ),
				'image'   => (int) get_post_thumbnail_id( $item['id'] ),
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $item['id'] ) ),
				/* translators: %s: event name in lowercase, e.g. "birthdays". */
				'link'    => sprintf( __( 'Explore %s', 'cater-ice-cream-core' ), function_exists( 'mb_strtolower' ) ? mb_strtolower( $item['title'] ) : strtolower( $item['title'] ) ),
			);
		}
		return $events;
	}

	/** @return array<string, string> Event key => label, plus "Other occasion". */
	public static function event_options(): array {
		$options = array();
		foreach ( Catalog::selectable_events() as $item ) {
			$options[ $item['key'] ] = $item['title'];
		}
		$options['other'] = __( 'Other occasion', 'cater-ice-cream-core' );
		return $options;
	}

	/**
	 * A county's published cities from the location repository (linked to their city pages).
	 * Only when no location data is connected does the editor's plain list (approved names only)
	 * appear, unlinked.
	 *
	 * @return array{name: string, slug: string, cities: array<int, array{name: string, url: string}>, total: int}
	 */
	private static function county_cities( string $name, string $fallback, int $limit, int $page = 1 ): array {
		$repo   = \CIC\Locations\Service::repository();
		$slug   = sanitize_title( $name );
		$cities = array();
		$total  = 0;
		if ( '' !== $name && $repo->is_connected() ) {
			$list   = $repo->list_cities( 'CA', $slug, $page, $limit );
			$total  = $list['total'];
			$cities = array_map( static fn( $c ) => array( 'name' => $c['name'], 'url' => $c['url'] ), $list['items'] );
		} elseif ( '' !== $fallback ) {
			foreach ( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $fallback ) ) ) as $city ) {
				$cities[] = array( 'name' => $city, 'url' => '' );
			}
			$total = count( $cities );
		}
		return array(
			'name'   => $name,
			'slug'   => $slug,
			'cities' => $cities,
			'total'  => $total,
		);
	}

	/**
	 * Project a coordinate onto the 300×300 California outline used by the location maps
	 * (equirectangular approximation; points are approximate, never boundaries).
	 *
	 * @return array{x: float, y: float}
	 */
	public static function map_xy( float $lat, float $lng ): array {
		return array(
			'x' => round( ( $lng + 124.6 ) * 28, 1 ),
			'y' => round( ( 42.2 - $lat ) * 30, 1 ),
		);
	}

	/**
	 * Replace city tokens ({city}, {state}, {state_code}, {county}) with plain-text values.
	 * Callers escape (plain fields) or kses-filter (headings) the result afterwards.
	 *
	 * @param array<string, string> $tokens Token => plain text value.
	 */
	private static function tokens( string $text, array $tokens ): string {
		return strtr( $text, $tokens );
	}

	/** Hub URL (Service Areas Page) or the state hub fallback. */
	public static function hub_url(): string {
		$hub = Catalog::find_by_key( 'page', 'service-areas' );
		return $hub ? (string) get_permalink( $hub ) : home_url( '/service-areas/' );
	}

	/**
	 * Service Areas hub (Page) and state hub (/service-areas/{state}/) composition.
	 * The state hub uses the same content with a full, paginated county directory.
	 *
	 * @param string $mode   'hub' or 'state'.
	 * @param string $county County slug filter (state hub directory view).
	 * @return array<string, mixed>
	 */
	public static function hub( int $id, string $state = 'CA', string $mode = 'hub', string $county = '', int $page = 1 ): array {
		static $memo = array();
		$memo_key = "{$id}:{$state}:{$mode}:{$county}:{$page}";
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}
		$p          = 'cic_sa_';
		$contact    = Contact::get();
		$repo       = \CIC\Locations\Service::repository();
		$state_name = \CIC\Locations\Tables::state_name( $state );
		$state_slug = \CIC\Locations\Tables::state_slug( $state );
		$state_url  = home_url( user_trailingslashit( 'service-areas/' . $state_slug ) );
		$per_card   = 'state' === $mode ? 12 : 6;

		$counties = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$name = self::m( $id, "{$p}county_{$i}_name" );
			if ( '' === $name ) {
				continue;
			}
			$slug = sanitize_title( $name );
			if ( '' !== $county && $slug !== $county ) {
				continue;
			}
			$per          = '' !== $county ? 48 : $per_card;
			$data         = self::county_cities( $name, '', $per, '' !== $county ? $page : 1 );
			$data['image'] = self::img( $id, "{$p}county_{$i}_image" );
			$data['text']  = self::m( $id, "{$p}county_{$i}_text" );
			$data['link']  = self::m( $id, "{$p}county_{$i}_link" ) ?: __( 'Check your exact ZIP code', 'cater-ice-cream-core' );
			$data['all']   = $data['total'] > count( $data['cities'] ) && '' === $county ? add_query_arg( 'county', $slug, $state_url ) : '';
			$data['pages'] = '' !== $county ? (int) ceil( $data['total'] / $per ) : 1;
			$counties[]    = $data;
		}

		$points = array();
		foreach ( $repo->get_map_points( $state ) as $point ) {
			$points[] = array_merge( $point, self::map_xy( $point['latitude'], $point['longitude'] ) );
		}

		$steps = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$steps[] = array(
				'icon'  => self::m( $id, "{$p}step_{$i}_icon" ),
				'title' => self::m( $id, "{$p}step_{$i}_title" ),
				'text'  => self::m( $id, "{$p}step_{$i}_text" ),
			);
		}
		$faqs = array();
		for ( $i = 1; $i <= Fields::FAQ_SLOTS; $i++ ) {
			$faqs[] = array( 'q' => self::m( $id, "{$p}faq_{$i}_q" ), 'a' => self::m( $id, "{$p}faq_{$i}_a" ) );
		}
		$faqs = array_values( array_filter( $faqs, static fn( $f ) => '' !== $f['q'] && '' !== $f['a'] ) );

		$hub_url = self::hub_url();
		$crumbs  = array( array( __( 'Home', 'cater-ice-cream-core' ), home_url( '/' ) ) );
		if ( 'state' === $mode ) {
			$crumbs[] = array( get_the_title( $id ) ?: __( 'Service Areas', 'cater-ice-cream-core' ), $hub_url );
			$crumbs[] = array( $state_name, '' );
		} else {
			$crumbs[] = array( get_the_title( $id ) ?: __( 'Service Areas', 'cater-ice-cream-core' ), '' );
		}

		$search = array(
			'placeholder' => self::m( $id, $p . 'search_ph' ) ?: __( 'Enter city or ZIP code', 'cater-ice-cream-core' ),
			'button'      => self::m( $id, $p . 'search_button' ) ?: __( 'Check my area', 'cater-ice-cream-core' ),
			'hint'        => self::m( $id, $p . 'search_hint' ),
			'endpoint'    => rest_url( 'cic/v1/coverage' ),
		);

		$title = 'state' === $mode
			/* translators: %s: state name. */
			? sprintf( __( 'Ice Cream Catering Service Areas in %s', 'cater-ice-cream-core' ), $state_name )
			: ( get_the_title( $id ) ?: __( 'Service Areas', 'cater-ice-cream-core' ) );
		$vm = array(
			'id'        => $id,
			'mode'      => $mode,
			'state'     => $state,
			'state_url' => $state_url,
			'county'    => $county,
			'hero'      => array(
				'eyebrow'  => self::m( $id, $p . 'eyebrow' ),
				'headline' => self::lines( self::m( $id, $p . 'headline' ) ?: $title ),
				'intro'    => self::m( $id, $p . 'intro' ),
				'image'    => self::img( $id, $p . 'image' ),
				'caption'  => self::m( $id, $p . 'map_caption' ),
				'points'   => $points,
				'search'   => $search,
			),
			'counties'  => array(
				'heading' => self::rich( self::m( $id, $p . 'counties_heading' ) ),
				'intro'   => self::m( $id, $p . 'counties_intro' ),
				'items'   => $counties,
			),
			'steps'     => array(
				'heading' => self::rich( self::m( $id, $p . 'steps_heading' ) ),
				'intro'   => self::m( $id, $p . 'steps_intro' ),
				'items'   => self::filled( $steps, 'title' ),
			),
			'call'      => array(
				'heading'     => self::rich( self::m( $id, $p . 'call_heading' ) ),
				'text'        => self::m( $id, $p . 'call_text' ),
				'image'       => self::img( $id, $p . 'call_image' ),
				'quote_label' => self::m( $id, $p . 'call_quote_label' ) ?: $contact['quote_label'],
				'quote_url'   => $contact['quote_url'],
			),
			'occasions' => array(
				'heading' => self::rich( self::m( $id, $p . 'occasions_heading' ) ),
				'intro'   => self::m( $id, $p . 'occasions_intro' ),
				'items'   => self::event_cards(),
			),
			'faq'       => array(
				'heading' => self::lines( self::m( $id, $p . 'faq_heading' ) ),
				'items'   => $faqs,
			),
			'start'     => array(
				'heading'        => self::rich( self::m( $id, $p . 'start_heading' ) ),
				'intro'          => self::m( $id, $p . 'start_intro' ),
				'note'           => self::m( $id, $p . 'start_note' ),
				'search'         => array(
					'location_label' => __( 'City or ZIP code', 'cater-ice-cream-core' ),
					'location_ph'    => $search['placeholder'],
					'event_label'    => __( 'Event type', 'cater-ice-cream-core' ),
					'event_ph'       => __( 'Select event type', 'cater-ice-cream-core' ),
					'date_label'     => __( 'Date', 'cater-ice-cream-core' ),
					'cta'            => self::m( $id, $p . 'start_cta' ) ?: $contact['quote_label'],
					'note'           => self::m( $id, $p . 'start_note' ),
					'events'         => self::event_options(),
					'action'         => $contact['quote_url'],
				),
			),
			'breadcrumbs' => $crumbs,
			'connected'   => $repo->is_connected(),
		);
		$vm['sections'] = self::sections(
			$id,
			Fields::HUB_SECTIONS,
			array(
				'areas_hero'      => true,
				'areas_counties'  => (bool) $counties,
				'areas_steps'     => (bool) $vm['steps']['items'],
				'areas_call'      => '' !== $vm['call']['heading'],
				'areas_occasions' => '' !== $vm['occasions']['heading'] && $vm['occasions']['items'],
				'areas_faq'       => (bool) $faqs,
				'areas_start'     => '' !== $vm['start']['heading'],
			)
		);
		$canonical = 'state' === $mode ? $state_url : $hub_url;
		$vm['seo'] = array(
			'title'       => $title . ' | ' . get_bloginfo( 'name' ),
			'description' => wp_strip_all_tags( self::m( $id, $p . 'intro' ) ),
			'canonical'   => $canonical,
			'image'       => $vm['hero']['image'],
			'breadcrumbs' => array_map( static fn( $c ) => array( $c[0], '' === $c[1] ? $canonical : $c[1] ), $crumbs ),
			'schema'      => array(),
		);
		$memo[ $memo_key ] = $vm;
		return $vm;
	}

	/**
	 * Virtual city page (13_city_pasadena.png): city content record overrides, falling back to the
	 * city template on the Service Areas page, with {city}/{state}/{state_code}/{county} tokens.
	 *
	 * @param array<string, mixed> $city    City from the location repository.
	 * @param int                  $preview Editorial record being previewed (0 = public view).
	 * @return array<string, mixed>
	 */
	public static function city( array $city, int $preview = 0 ): array {
		static $memo = array();
		$memo_key = $city['id'] . ':' . $preview;
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}
		$repo      = \CIC\Locations\Service::repository();
		$contact   = Contact::get();
		$hub       = Catalog::find_by_key( 'page', 'service-areas' );
		// $preview > 0: content record preview; -1: city-level preview without a record (template only).
		$editorial = $preview > 0 ? \CIC\Locations\Editorial::preview_source( $preview ) : ( $preview < 0 ? 0 : (int) $repo->get_city_content( (int) $city['id'] )['editorial_id'] );
		if ( ! $preview && $editorial && 'publish' !== get_post_status( $editorial ) ) {
			$editorial = 0; // Unpublished overrides are visible only in preview.
		}
		$tokens = array(
			'{city}'       => $city['name'],
			'{state}'      => \CIC\Locations\Tables::state_name( $city['state_code'] ),
			'{state_code}' => $city['state_code'],
			'{county}'     => $city['county_name'],
		);
		$get = static function ( string $suffix ) use ( $editorial, $hub ): string {
			$value = $editorial ? trim( (string) get_post_meta( $editorial, 'cic_city_' . $suffix, true ) ) : '';
			return '' !== $value ? $value : ( $hub ? self::m( $hub, 'cic_citydef_' . $suffix ) : '' );
		};
		$text = static fn( string $suffix ): string => self::tokens( $get( $suffix ), $tokens );
		$rich = static fn( string $suffix ): string => self::rich( self::tokens( $get( $suffix ), $tokens ) );
		$img  = static function ( string $suffix ) use ( $get ): int {
			$value = (int) $get( $suffix );
			return $value && wp_attachment_is_image( $value ) ? $value : 0;
		};

		$plans = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$plans[] = array(
				'icon'  => $hub ? self::m( $hub, "cic_citydef_plan_{$i}_icon" ) : '',
				'title' => $hub ? self::tokens( self::m( $hub, "cic_citydef_plan_{$i}_title" ), $tokens ) : '',
				'text'  => $hub ? self::tokens( self::m( $hub, "cic_citydef_plan_{$i}_text" ), $tokens ) : '',
			);
		}
		$faqs = array();
		for ( $i = 1; $i <= Fields::CITY_FAQ_SLOTS; $i++ ) {
			$faqs[] = array( 'q' => $text( "faq_{$i}_q" ), 'a' => $text( "faq_{$i}_a" ) );
		}
		$faqs = array_values( array_filter( $faqs, static fn( $f ) => '' !== $f['q'] && '' !== $f['a'] ) );

		$hub_url   = self::hub_url();
		$state_url = home_url( user_trailingslashit( 'service-areas/' . $city['state_slug'] ) );
		$canonical = \CIC\Locations\DatabaseRepository::url( $city['state_slug'], $city['slug'] );
		$crumbs    = array(
			array( __( 'Home', 'cater-ice-cream-core' ), home_url( '/' ) ),
			array( $hub ? get_the_title( $hub ) : __( 'Service Areas', 'cater-ice-cream-core' ), $hub_url ),
			array( $tokens['{state}'], $state_url ),
			array( $city['name'], '' ),
		);
		$nearby = $repo instanceof \CIC\Locations\DatabaseRepository ? $repo->nearby_cities( $city, 3 ) : array();
		$check  = $repo instanceof \CIC\Locations\DatabaseRepository ? $repo->city_coverage( $city ) : $repo->evaluate_coverage( $city['key'] );

		$treats = self::treat_cards();
		$main   = $img( 'image_main' );
		$vm     = array(
			'city'      => $city,
			'preview'   => $preview,
			'hero'      => array(
				'eyebrow'  => $text( 'eyebrow' ),
				'headline' => $rich( 'title' ) ?: esc_html( $city['name'] ),
				'subtitle' => $text( 'subtitle' ),
				'intro'    => $text( 'intro' ),
				'images'   => array( $main, $img( 'image_inset_1' ), $img( 'image_inset_2' ) ),
			),
			'search'    => array(
				'location_label' => __( 'Event ZIP code', 'cater-ice-cream-core' ),
				'location_ph'    => __( 'Enter ZIP code', 'cater-ice-cream-core' ),
				'event_label'    => __( 'Event type', 'cater-ice-cream-core' ),
				'event_ph'       => __( 'Select event', 'cater-ice-cream-core' ),
				'date_label'     => __( 'Date', 'cater-ice-cream-core' ),
				'cta'            => $text( 'search_cta' ) ?: $contact['quote_label'],
				'note'           => $text( 'search_note' ) ?: $contact['availability_note'],
				'events'         => self::event_options(),
				'action'         => $contact['quote_url'],
				'city_key'       => $city['key'],
			),
			'plans'     => array(
				'heading' => $rich( 'plans_heading' ),
				'intro'   => $text( 'plans_intro' ),
				'items'   => self::filled( $plans, 'title' ),
			),
			'treats'    => array(
				'heading'    => $rich( 'treats_heading' ),
				'intro'      => '',
				'items'      => $treats,
				'more_label' => __( 'Explore all treats', 'cater-ice-cream-core' ),
				'more_image' => 'page' === get_option( 'show_on_front' ) ? self::img( (int) get_option( 'page_on_front' ), 'cic_treats_more_image' ) : 0,
				'more_url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'call'      => array(
				'heading' => self::lines( self::tokens( $get( 'call_heading' ), $tokens ) ),
				'text'    => $text( 'call_text' ),
				'image'   => $img( 'call_image' ),
				'note'    => $text( 'call_note' ),
			),
			'occasions' => array(
				'heading' => $rich( 'occasions_heading' ),
				'items'   => self::event_cards(),
			),
			'check'     => array(
				'heading'  => $rich( 'check_heading' ),
				'intro'    => $text( 'check_intro' ),
				'endpoint' => rest_url( 'cic/v1/coverage' ),
				'coverage' => $check,
			),
			'faq'       => array(
				'heading' => $rich( 'faq_heading' ),
				'items'   => $faqs,
			),
			'quote'     => array(
				'heading' => $rich( 'quote_heading' ),
				'note'    => $text( 'quote_note' ),
				'events'  => self::event_options(),
				'action'  => $contact['quote_url'],
			),
			'nearby'    => $nearby,
			'hub_url'   => $hub_url,
			'breadcrumbs' => $crumbs,
		);
		$title       = $text( 'seo_title' ) ?: sprintf( /* translators: %s: city. */ __( 'Ice Cream Catering in %s', 'cater-ice-cream-core' ), $city['name'] );
		$description = $text( 'seo_description' );
		$vm['seo']   = array(
			'title'       => $title . ' | ' . get_bloginfo( 'name' ),
			'description' => $description,
			'canonical'   => $canonical,
			'image'       => $main,
			'breadcrumbs' => array_map( static fn( $c ) => array( $c[0], '' === $c[1] ? $canonical : $c[1] ), $crumbs ),
			'schema'      => array(
				'@type'      => 'Service',
				'name'       => sprintf( /* translators: %s: city. */ __( 'Ice cream catering in %s', 'cater-ice-cream-core' ), $city['name'] ),
				'serviceType' => __( 'Ice cream catering', 'cater-ice-cream-core' ),
				'provider'   => array_filter(
					array(
						'@type'     => 'Organization',
						'name'      => $contact['business_name'],
						'url'       => home_url( '/' ),
						'telephone' => $contact['phone'],
					)
				),
				'areaServed' => array(
					'@type' => 'City',
					'name'  => $city['name'] . ', ' . $tokens['{state}'],
				),
				'url'        => $canonical,
			),
		);
		$memo[ $memo_key ] = $vm;
		return $vm;
	}

	/**
	 * Paragraphs from a textarea (blank line between paragraphs).
	 *
	 * @return string[]
	 */
	private static function paragraphs( string $text ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R\s*\R/', $text ) ) ) );
	}

	/**
	 * Our Treats page (08): one row per published treat from the live product query.
	 *
	 * @return array<string, mixed>
	 */
	public static function catalogue( int $page_id ): array {
		$p       = 'cic_shop_';
		$contact = Contact::get();
		$rows    = array();
		foreach ( Catalog::selectable_products() as $item ) {
			$product = wc_get_product( $item['id'] );
			$rows[]  = array(
				'id'         => $item['id'],
				'key'        => $item['key'],
				'title'      => $item['title'],
				'anchor'     => 'treat-' . $item['key'],
				'context'    => isset( Schema::contexts()[ (string) get_post_meta( $item['id'], 'cic_preset', true ) ] ) ? (string) get_post_meta( $item['id'], 'cic_preset', true ) : 'brand',
				'url'        => get_permalink( $item['id'] ),
				'quote_url'  => self::quote_url( 'treat', $item['key'] ),
				'image'      => $product ? (int) $product->get_image_id() : 0,
				'badge'      => self::m( $item['id'], 'cic_arch_badge' ),
				'subtitle'   => self::m( $item['id'], 'cic_arch_subtitle' ) ?: self::m( $item['id'], 'cic_card_tagline' ),
				'text'       => $product ? wp_strip_all_tags( (string) $product->get_description() ) : '',
				'experience' => self::m( $item['id'], 'cic_arch_experience' ),
				'discuss'    => self::m( $item['id'], 'cic_arch_discuss' ),
				/* translators: %s: treat name. */
				'cta'        => sprintf( __( 'Explore %s', 'cater-ice-cream-core' ), $item['title'] ),
			);
		}
		$help = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$help[] = array( 'icon' => self::m( $page_id, "{$p}help_{$i}_icon" ), 'title' => self::m( $page_id, "{$p}help_{$i}_title" ), 'text' => self::m( $page_id, "{$p}help_{$i}_text" ) );
		}
		return array(
			'title'  => get_the_title( $page_id ),
			'hero'   => array(
				'eyebrow'  => self::m( $page_id, $p . 'eyebrow' ),
				'headline' => self::lines( self::m( $page_id, $p . 'headline' ) ?: get_the_title( $page_id ) ),
				'intro'    => self::m( $page_id, $p . 'intro' ),
				'image'    => self::img( $page_id, $p . 'image' ),
			),
			'jump'   => self::m( $page_id, $p . 'jump_label' ) ?: __( 'Jump to a treat:', 'cater-ice-cream-core' ),
			'rows'   => $rows,
			'note'   => self::m( $page_id, $p . 'note' ),
			'help'   => array(
				'heading' => self::rich( self::m( $page_id, $p . 'help_heading' ) ),
				'text'    => self::m( $page_id, $p . 'help_text' ),
				'items'   => self::filled( $help, 'title' ),
				'cta'     => self::m( $page_id, $p . 'help_cta' ) ?: $contact['quote_label'],
				'url'     => $contact['quote_url'],
			),
			'start'  => array(
				'heading'    => self::rich( self::m( $page_id, $p . 'start_heading' ) ),
				'subheading' => self::m( $page_id, $p . 'start_subheading' ),
				'cta'        => self::m( $page_id, $p . 'start_cta' ) ?: $contact['quote_label'],
				'note'       => self::m( $page_id, $p . 'start_note' ) ?: $contact['availability_note'],
				'events'     => self::event_options(),
			),
		);
	}

	/**
	 * Events overview (03): editable text (EventsOverview option) + live event and treat queries.
	 *
	 * @return array<string, mixed>
	 */
	public static function events_overview(): array {
		$o       = EventsOverview::get();
		$contact = Contact::get();
		$cards   = array();
		foreach ( self::event_cards() as $card ) {
			$custom        = self::img( $card['id'], 'cic_ev_card_image' );
			$card['image'] = $custom ?: $card['image'];
			$cards[]       = $card;
		}
		$treats = self::treat_cards();
		$steps  = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$steps[] = array( 'title' => (string) $o[ "step_{$i}_title" ], 'text' => (string) $o[ "step_{$i}_text" ] );
		}
		return array(
			'hero'   => array(
				'eyebrow'  => (string) $o['eyebrow'],
				'headline' => self::lines( (string) $o['headline'] ),
				'intro'    => (string) $o['intro'],
				'image'    => (int) $o['image'],
			),
			'cards'  => array(
				'heading'    => self::rich( (string) $o['cards_heading'] ),
				'intro'      => (string) $o['cards_intro'],
				'items'      => $cards,
				'other_text' => (string) $o['other_text'],
				'other_link' => (string) $o['other_link'],
				'other_url'  => self::quote_url( 'event', 'other' ),
			),
			'treats' => array(
				'heading'    => self::rich( (string) $o['treats_heading'] ),
				'intro'      => (string) $o['treats_intro'],
				'large'      => array_slice( $treats, 0, 2 ),
				'small'      => array_slice( $treats, 2 ),
				'more_url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'steps'  => array(
				'heading' => self::rich( (string) $o['steps_heading'] ),
				'items'   => self::filled( $steps, 'title' ),
			),
			'start'  => array(
				'heading' => self::rich( (string) $o['start_heading'] ),
				'cta'     => (string) $o['start_cta'] ?: $contact['quote_label'],
				'note'    => (string) $o['start_note'] ?: $contact['availability_note'],
				'events'  => self::event_options(),
			),
		);
	}

	/**
	 * About page (14). Business facts come from the global contact settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function about( int $id ): array {
		$p       = 'cic_about_';
		$contact = Contact::get();
		$steps   = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$steps[] = array( 'icon' => self::m( $id, "{$p}step_{$i}_icon" ), 'title' => self::m( $id, "{$p}step_{$i}_title" ), 'text' => self::m( $id, "{$p}step_{$i}_text" ) );
		}
		$repo     = \CIC\Locations\Service::repository();
		$counties = array();
		$hub      = Catalog::find_by_key( 'page', 'service-areas' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$name = $hub ? self::m( $hub, "cic_sa_county_{$i}_name" ) : '';
			if ( '' !== $name ) {
				$counties[] = array( 'name' => $name, 'slug' => sanitize_title( $name ) );
			}
		}
		$points = array();
		foreach ( $repo->get_map_points( 'CA' ) as $point ) {
			$points[] = array_merge( $point, self::map_xy( $point['latitude'], $point['longitude'] ) );
		}
		return array(
			'title'    => get_the_title( $id ),
			'hero'     => array(
				'eyebrow'  => self::m( $id, $p . 'eyebrow' ),
				'headline' => self::lines( self::m( $id, $p . 'headline' ) ?: get_the_title( $id ) ),
				'intro'    => self::m( $id, $p . 'intro' ),
				'images'   => array( self::img( $id, $p . 'image' ), self::img( $id, $p . 'inset_1' ), self::img( $id, $p . 'inset_2' ) ),
			),
			'story'    => array(
				'heading'    => self::rich( self::m( $id, $p . 'story_heading' ) ),
				'paragraphs' => self::paragraphs( self::m( $id, $p . 'story_text' ) ),
				'image'      => self::img( $id, $p . 'story_image' ),
				'quote'      => self::lines( self::m( $id, $p . 'quote' ) ),
			),
			'brands'   => array(
				'heading' => self::rich( self::m( $id, $p . 'brands_heading' ) ),
				'items'   => array_slice( self::treat_cards(), 0, 2 ),
				'line'    => self::m( $id, $p . 'brands_line' ),
				'link'    => self::m( $id, $p . 'brands_link' ) ?: __( 'Explore our treats', 'cater-ice-cream-core' ),
				'url'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'approach' => array(
				'heading' => self::rich( self::m( $id, $p . 'approach_heading' ) ),
				'items'   => self::filled( $steps, 'title' ),
			),
			'meet'     => array(
				'heading'  => self::rich( self::m( $id, $p . 'meet_heading' ) ),
				'byline'   => trim( $contact['legal_entity'] . ( '' !== $contact['legal_entity'] && '' !== $contact['location'] ? ' · ' : '' ) . $contact['location'] ),
				'text'     => self::m( $id, $p . 'meet_text' ),
				'initials' => self::m( $id, $p . 'meet_initials' ),
				'portrait' => self::img( $id, $p . 'meet_portrait' ),
				'image'    => self::img( $id, $p . 'meet_image' ),
			),
			'areas'    => array(
				'heading'    => self::rich( self::m( $id, $p . 'areas_heading' ) ),
				'intro'      => self::m( $id, $p . 'areas_intro' ),
				'counties'   => $counties,
				'points'     => $points,
				'note_title' => self::m( $id, $p . 'areas_note_title' ),
				'note'       => self::m( $id, $p . 'areas_note' ),
				'link'       => self::m( $id, $p . 'areas_link' ) ?: __( 'Explore service areas', 'cater-ice-cream-core' ),
				'url'        => self::hub_url(),
			),
			'cta'      => array(
				'heading' => self::rich( self::m( $id, $p . 'cta_heading' ) ),
			),
		);
	}

	/**
	 * Contact page (16). Phone, email and location come from the global contact settings; the
	 * message form itself is Phase 06.
	 *
	 * @return array<string, mixed>
	 */
	public static function contact( int $id ): array {
		$p     = 'cic_contact_';
		$faqs  = array();
		for ( $i = 1; $i <= Fields::FAQ_SLOTS; $i++ ) {
			$faqs[] = array( 'q' => self::m( $id, "{$p}faq_{$i}_q" ), 'a' => self::m( $id, "{$p}faq_{$i}_a" ) );
		}
		$repo   = \CIC\Locations\Service::repository();
		$points = array();
		foreach ( $repo->get_map_points( 'CA' ) as $point ) {
			$points[] = array_merge( $point, self::map_xy( $point['latitude'], $point['longitude'] ) );
		}
		return array(
			'title' => get_the_title( $id ),
			'hero'  => array(
				'headline'    => self::lines( self::m( $id, $p . 'headline' ) ?: get_the_title( $id ) ),
				'intro'       => self::m( $id, $p . 'intro' ),
				'image'       => self::img( $id, $p . 'image' ),
				'call_title'  => self::m( $id, $p . 'call_title' ),
				'email_title' => self::m( $id, $p . 'email_title' ),
			),
			'help'  => array(
				'heading' => self::rich( self::m( $id, $p . 'help_heading' ) ),
				'intro'   => self::m( $id, $p . 'help_intro' ),
				'pending' => self::m( $id, $p . 'help_pending' ),
			),
			'plan'  => array(
				'heading' => self::rich( self::m( $id, $p . 'plan_heading' ) ),
				'text'    => self::m( $id, $p . 'plan_text' ),
				'images'  => array_values( array_filter( array( self::img( $id, $p . 'plan_image_1' ), self::img( $id, $p . 'plan_image_2' ), self::img( $id, $p . 'plan_image_3' ) ) ) ),
				'cta'     => self::m( $id, $p . 'plan_cta' ) ?: Contact::get()['quote_label'],
				'link'    => self::m( $id, $p . 'plan_link' ),
				'shop'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'where' => array(
				'heading' => self::rich( self::m( $id, $p . 'where_heading' ) ),
				'text'    => self::m( $id, $p . 'where_text' ),
				'link'    => self::m( $id, $p . 'where_link' ) ?: __( 'Check your area', 'cater-ice-cream-core' ),
				'url'     => self::hub_url(),
				'points'  => $points,
			),
			'faq'   => array(
				'heading' => self::rich( self::m( $id, $p . 'faq_heading' ) ),
				'items'   => array_values( array_filter( $faqs, static fn( $f ) => '' !== $f['q'] && '' !== $f['a'] ) ),
			),
			'close' => array(
				'heading'     => self::lines( self::m( $id, $p . 'close_heading' ) ),
				'text'        => self::m( $id, $p . 'close_text' ),
				'quote_title' => self::m( $id, $p . 'close_quote_title' ),
				'quote_text'  => self::m( $id, $p . 'close_quote_text' ),
			),
		);
	}

	/**
	 * Policy page (19): approval state and an "On this page" list built from the content's H2s
	 * (ids are added to those headings by the theme).
	 *
	 * @return array<string, mixed>
	 */
	public static function policy( int $id ): array {
		$content  = (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $id ) );
		$sections = array();
		$used     = array();
		$content  = preg_replace_callback(
			'#<h2([^>]*)>(.*?)</h2>#s',
			static function ( $m ) use ( &$sections, &$used ) {
				$label = trim( wp_strip_all_tags( $m[2] ) );
				$slug  = sanitize_title( $label ) ?: 'section';
				$base  = $slug;
				$n     = 2;
				while ( isset( $used[ $slug ] ) ) {
					$slug = $base . '-' . $n++;
				}
				$used[ $slug ] = true;
				$sections[]    = array( 'id' => $slug, 'label' => $label );
				$attrs         = preg_replace( '/\sid="[^"]*"/', '', $m[1] );
				return '<h2' . $attrs . ' id="' . esc_attr( $slug ) . '">' . $m[2] . '</h2>';
			},
			$content
		);
		return array(
			'title'    => get_the_title( $id ),
			'approved' => '1' === (string) get_post_meta( $id, 'cic_policy_approved', true ),
			'updated'  => self::m( $id, 'cic_policy_updated' ),
			'content'  => (string) $content,
			'sections' => $sections,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function treat( int $id ): array {
		$product = wc_get_product( $id );
		$key     = Catalog::key( $id );
		$title   = get_the_title( $id );

		$chips = $feelings = $themes = $points = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$chips[]  = array( 'icon' => self::m( $id, "cic_chip_{$i}_icon" ), 'label' => self::m( $id, "cic_chip_{$i}_label" ) );
			$points[] = array( 'icon' => self::m( $id, "cic_point_{$i}_icon" ), 'label' => self::m( $id, "cic_point_{$i}_label" ) );
			$palette  = get_post_meta( $id, "cic_theme_{$i}_palette", true );
			$themes[] = array(
				'title'   => self::m( $id, "cic_theme_{$i}_title" ),
				'text'    => self::m( $id, "cic_theme_{$i}_text" ),
				'image'   => self::img( $id, "cic_theme_{$i}_image" ),
				'icon'    => self::m( $id, "cic_theme_{$i}_icon" ),
				'palette' => array_values( array_filter( (array) $palette, static fn( $p ) => isset( Schema::colors()[ $p ] ) ) ),
			);
		}
		for ( $i = 1; $i <= 4; $i++ ) {
			$feelings[] = array( 'icon' => self::m( $id, "cic_feel_{$i}_icon" ), 'title' => self::m( $id, "cic_feel_{$i}_title" ), 'text' => self::m( $id, "cic_feel_{$i}_text" ) );
		}

		$occasions = array();
		foreach ( Pairings::for_product( $id, true ) as $row ) {
			$occasions[] = array(
				'event_id'    => $row['event_id'],
				'event_key'   => Catalog::key( (int) $row['event_id'] ),
				'event_title' => get_the_title( $row['event_id'] ),
				'event_url'   => get_permalink( $row['event_id'] ),
				'headline'    => $row['headline'],
				'description' => $row['description'],
				'image'       => $row['image_id'] ? $row['image_id'] : (int) get_post_thumbnail_id( $row['event_id'] ),
				'link_label'  => '' !== $row['link_label'] ? $row['link_label'] : sprintf( __( 'Explore %s', 'cater-ice-cream-core' ), get_the_title( $row['event_id'] ) ),
			);
		}

		$related = array_values(
			array_filter(
				array_map( 'intval', (array) get_post_meta( $id, 'cic_related_products', true ) ),
				static fn( $rid ) => $rid !== $id && 'publish' === get_post_status( $rid ) && Catalog::is_active( $rid )
			)
		);

		$layout = self::m( $id, 'cic_treat_layout' );
		$layout = isset( Fields::TREAT_LAYOUTS[ $layout ] ) ? $layout : 'split';

		$vm = array(
			'id'        => $id,
			'key'       => $key,
			'title'     => $title,
			'layout'    => $layout,
			'url'       => get_permalink( $id ),
			'quote_url' => self::quote_url( 'treat', $key ),
			'hero'      => array(
				'eyebrow'  => self::m( $id, 'cic_hero_eyebrow' ),
				'headline' => self::lines( self::m( $id, 'cic_hero_headline' ) ?: $title ),
				'intro'    => self::m( $id, 'cic_hero_intro' ) ?: wp_strip_all_tags( (string) $product->get_short_description() ),
				'cta'      => self::m( $id, 'cic_hero_cta' ) ?: Contact::get()['quote_label'],
				'jump'     => self::m( $id, 'cic_hero_jump' ),
				'image'    => self::img( $id, 'cic_hero_image' ) ?: (int) $product->get_image_id(),
				'image_2'  => self::img( $id, 'cic_hero_image_2' ),
				'image_3'  => self::img( $id, 'cic_hero_image_3' ),
				'chips'    => self::filled( $chips, 'label' ),
			),
			'feelings'  => array(
				'heading' => self::rich( self::m( $id, 'cic_feel_heading' ) ),
				'items'   => self::filled( $feelings, 'title' ),
				'note'    => self::m( $id, 'cic_feel_note' ),
			),
			'occasions' => array(
				'heading' => self::rich( self::m( $id, 'cic_occ_heading' ) ?: __( 'What could it bring to your celebration?', 'cater-ice-cream-core' ) ),
				'items'   => $occasions,
			),
			'themes'    => array(
				'eyebrow' => self::m( $id, 'cic_theme_eyebrow' ),
				'heading' => self::rich( self::m( $id, 'cic_theme_heading' ) ),
				'items'   => self::filled( $themes, 'title' ),
				'note'    => self::m( $id, 'cic_theme_note' ),
			),
			'detail'    => array(
				'heading'    => self::rich( self::m( $id, 'cic_detail_heading' ) ),
				'body'       => (string) $product->get_description(),
				'texture'    => self::m( $id, 'cic_detail_texture' ),
				'options'    => self::m( $id, 'cic_detail_options' ),
				'image'      => self::img( $id, 'cic_detail_image' ),
				'points'     => self::filled( $points, 'label' ),
				'ingredient' => self::m( $id, 'cic_ingredient_note' ),
			),
			'faq'       => array(
				'heading' => self::rich( self::m( $id, 'cic_faq_heading' ) ?: __( 'Good to know.', 'cater-ice-cream-core' ) ),
				'intro'   => self::m( $id, 'cic_treat_faq_intro' ),
				'items'   => self::faqs( $id ),
			),
			'quote'     => array(
				'heading' => self::rich( self::m( $id, 'cic_quote_heading' ) ?: __( 'What kind of moment are you <em>planning?</em>', 'cater-ice-cream-core' ) ),
				'intro'   => self::m( $id, 'cic_quote_intro' ),
				'cta'     => self::m( $id, 'cic_quote_cta' ) ?: Contact::get()['quote_label'],
				'note'    => self::m( $id, 'cic_quote_note' ) ?: Contact::get()['availability_note'],
				'events'  => self::event_options(),
			),
			'related'   => $related,
		);

		$vm['sections'] = self::sections(
			$id,
			Fields::TREAT_SECTIONS,
			array(
				'hero'      => true,
				'feelings'  => (bool) $vm['feelings']['items'],
				'occasions' => (bool) $vm['occasions']['items'],
				'themes'    => (bool) $vm['themes']['items'],
				'detail'    => '' !== $vm['detail']['heading'] && ( '' !== trim( $vm['detail']['body'] ) || $vm['detail']['points'] ),
				'faq'       => (bool) $vm['faq']['items'],
				'quote'     => true,
			),
			Fields::TREAT_LAYOUT_SECTIONS[ $layout ]
		);
		return $vm;
	}

	/**
	 * Homepage (static front page) composition. Treats and events come from published records.
	 *
	 * @return array<string, mixed>
	 */
	public static function home( int $id ): array {
		$contact = Contact::get();
		$values  = $steps = $counties = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$values[]   = array( 'icon' => self::m( $id, "cic_value_{$i}_icon" ), 'label' => self::m( $id, "cic_value_{$i}_label" ) );
			$steps[]    = array( 'title' => self::m( $id, "cic_step_{$i}_title" ), 'text' => self::m( $id, "cic_step_{$i}_text" ) );
			$counties[] = self::county_cities(
				self::m( $id, "cic_cov_county_{$i}_name" ),
				self::m( $id, "cic_cov_county_{$i}_cities" ),
				6
			);
		}

		$treats        = self::treat_cards();
		$events        = self::event_cards();
		$event_options = self::event_options();

		$vm = array(
			'id'       => $id,
			'hero'     => array(
				'eyebrow'  => self::m( $id, 'cic_home_eyebrow' ),
				'headline' => self::lines( self::m( $id, 'cic_home_headline' ) ?: get_bloginfo( 'name' ) ),
				'intro'    => self::m( $id, 'cic_home_intro' ),
				'images'   => array( self::img( $id, 'cic_home_image_main' ), self::img( $id, 'cic_home_image_inset_1' ), self::img( $id, 'cic_home_image_inset_2' ) ),
			),
			'search'   => array(
				'location_label' => self::m( $id, 'cic_search_location_label' ) ?: __( 'Location', 'cater-ice-cream-core' ),
				'location_ph'    => self::m( $id, 'cic_search_location_ph' ),
				'event_label'    => self::m( $id, 'cic_search_event_label' ) ?: __( 'Event type', 'cater-ice-cream-core' ),
				'event_ph'       => self::m( $id, 'cic_search_event_ph' ) ?: __( 'Select event', 'cater-ice-cream-core' ),
				'date_label'     => self::m( $id, 'cic_search_date_label' ) ?: __( 'Date', 'cater-ice-cream-core' ),
				'cta'            => self::m( $id, 'cic_search_cta' ) ?: $contact['quote_label'],
				'note'           => self::m( $id, 'cic_search_note' ) ?: $contact['availability_note'],
				'events'         => $event_options,
				'action'         => $contact['quote_url'],
			),
			'values'   => self::filled( $values, 'label' ),
			'treats'   => array(
				'heading'    => self::rich( self::m( $id, 'cic_treats_heading' ) ),
				'intro'      => self::m( $id, 'cic_treats_intro' ),
				'items'      => $treats,
				'more_label' => self::m( $id, 'cic_treats_more_label' ),
				'more_image' => self::img( $id, 'cic_treats_more_image' ),
				'more_url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'events'   => array(
				'heading' => self::rich( self::m( $id, 'cic_events_heading' ) ),
				'items'   => $events,
			),
			'process'  => array(
				'heading' => self::rich( self::m( $id, 'cic_process_heading' ) ),
				'steps'   => self::filled( $steps, 'title' ),
				'cta'     => self::m( $id, 'cic_process_cta' ) ?: $contact['quote_label'],
				'url'     => $contact['quote_url'],
			),
			'faq'      => array(
				'heading' => self::lines( self::m( $id, 'cic_faq_heading' ) ),
				'items'   => self::faqs( $id ),
			),
			'coverage' => array(
				'heading'     => self::lines( self::m( $id, 'cic_cov_heading' ) ),
				'intro'       => self::m( $id, 'cic_cov_intro' ),
				'placeholder' => self::m( $id, 'cic_cov_placeholder' ),
				'button'      => self::m( $id, 'cic_cov_button' ) ?: __( 'Check Area', 'cater-ice-cream-core' ),
				'counties'    => self::filled( $counties, 'name' ),
				'note'        => self::m( $id, 'cic_cov_note' ),
				'caption'     => self::m( $id, 'cic_cov_map_caption' ),
				'connected'   => \CIC\Locations\Service::repository()->is_connected(),
				'endpoint'    => rest_url( 'cic/v1/coverage' ),
			),
			'cta'      => array(
				'heading' => self::rich( self::m( $id, 'cic_cta_heading' ) ),
				'label'   => self::m( $id, 'cic_cta_label' ) ?: $contact['quote_label'],
				'url'     => $contact['quote_url'],
				'phone'   => '1' === self::m( $id, 'cic_cta_phone' ),
			),
		);

		$vm['sections'] = self::sections(
			$id,
			Fields::HOME_SECTIONS,
			array(
				'hero'     => true,
				'values'   => (bool) $vm['values'],
				'treats'   => (bool) $treats,
				'events'   => (bool) $events,
				'process'  => (bool) $vm['process']['steps'],
				'faq'      => (bool) $vm['faq']['items'],
				'coverage' => '' !== $vm['coverage']['heading'],
				'cta'      => '' !== $vm['cta']['heading'],
			)
		);
		return $vm;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function event( int $id ): array {
		$key   = Catalog::key( $id );
		$title = get_the_title( $id );

		$treats = array();
		foreach ( Pairings::for_event( $id, true ) as $row ) {
			$product  = wc_get_product( $row['product_id'] );
			$treats[] = array(
				'product_id'  => $row['product_id'],
				'title'       => get_the_title( $row['product_id'] ),
				'url'         => get_permalink( $row['product_id'] ),
				'image'       => $product ? (int) $product->get_image_id() : 0,
				'headline'    => $row['headline'],
				'description' => $row['description'],
				'intro'       => $row['event_intro'],
			);
		}

		$stories = $steps = $values = $points = array();
		for ( $i = 1; $i <= 2; $i++ ) {
			$stories[] = array( 'title' => self::lines( self::m( $id, "cic_story_{$i}_title" ) ), 'text' => self::m( $id, "cic_story_{$i}_text" ), 'image' => self::img( $id, "cic_story_{$i}_image" ) );
		}
		for ( $i = 1; $i <= 3; $i++ ) {
			$values[] = array( 'icon' => self::m( $id, "cic_ev_value_{$i}_icon" ), 'title' => self::m( $id, "cic_ev_value_{$i}_title" ), 'text' => self::m( $id, "cic_ev_value_{$i}_text" ) );
			$points[] = array( 'icon' => self::m( $id, "cic_ev_feature_{$i}_icon" ), 'title' => self::m( $id, "cic_ev_feature_{$i}_title" ), 'text' => self::m( $id, "cic_ev_feature_{$i}_text" ) );
		}
		for ( $i = 1; $i <= 3; $i++ ) {
			$steps[] = array( 'title' => self::m( $id, "cic_plan_{$i}_title" ), 'text' => self::m( $id, "cic_plan_{$i}_text" ) );
		}
		$images = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$images[] = self::img( $id, "cic_hero_image_{$i}" );
		}
		$layout = self::m( $id, 'cic_layout' );
		$layout = isset( Fields::EVENT_LAYOUTS[ $layout ] ) ? $layout : 'standard';
		$ribbon = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', self::m( $id, 'cic_ev_ribbon' ) ) ) ) );

		$vm = array(
			'id'        => $id,
			'key'       => $key,
			'title'     => $title,
			'layout'    => $layout,
			'url'       => get_permalink( $id ),
			'quote_url' => self::quote_url( 'event', $key ),
			'hero'      => array(
				'eyebrow'    => self::m( $id, 'cic_hero_eyebrow' ),
				'headline'   => self::lines( self::m( $id, 'cic_hero_headline' ) ?: $title ),
				'intro'      => self::m( $id, 'cic_hero_intro' ) ?: wp_strip_all_tags( get_the_excerpt( $id ) ),
				'cta'        => self::m( $id, 'cic_hero_cta' ) ?: Contact::get()['quote_label'],
				'link_label' => self::m( $id, 'cic_hero_link_label' ),
				'images'     => array_values( array_filter( $images ) ),
			),
			'starter'   => array(
				'location' => self::m( $id, 'cic_starter_label_location' ),
				'date'     => self::m( $id, 'cic_starter_label_date' ),
				'guests'   => self::m( $id, 'cic_starter_label_guests' ),
				'cta'      => self::m( $id, 'cic_starter_cta' ) ?: Contact::get()['quote_label'],
				'note'     => Contact::get()['availability_note'],
			),
			'ribbon'    => $ribbon,
			'values'    => self::filled( $values, 'title' ),
			'feature'   => array(
				'eyebrow' => self::m( $id, 'cic_ev_feature_eyebrow' ),
				'heading' => self::lines( self::m( $id, 'cic_ev_feature_heading' ) ),
				'text'    => self::m( $id, 'cic_ev_feature_text' ),
				'image'   => self::img( $id, 'cic_ev_feature_image' ),
				'inset'   => self::img( $id, 'cic_ev_feature_image_2' ),
				'points'  => self::filled( $points, 'title' ),
			),
			'gallery'   => array(
				'image'   => self::img( $id, 'cic_ev_gallery_image' ),
				'caption' => self::m( $id, 'cic_ev_gallery_caption' ),
			),
			'treats'    => array(
				'eyebrow'    => self::m( $id, 'cic_ev_treats_eyebrow' ),
				'heading'    => self::rich( self::m( $id, 'cic_treats_heading' ) ),
				'intro'      => self::m( $id, 'cic_treats_intro' ),
				'items'      => $treats,
				'more_label' => self::m( $id, 'cic_treats_more_label' ),
				'more_image' => self::img( $id, 'cic_treats_more_image' ),
				'more_url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ),
			),
			'stories'   => array(
				'heading' => self::rich( self::m( $id, 'cic_story_heading' ) ),
				'items'   => self::filled( $stories, 'text' ),
			),
			'planning'  => array(
				'heading' => self::lines( self::m( $id, 'cic_plan_heading' ) ),
				'intro'   => self::m( $id, 'cic_plan_intro' ),
				'image'   => self::img( $id, 'cic_plan_image' ),
				'steps'   => self::filled( $steps, 'title' ),
			),
			'quote'     => array(
				'heading'    => self::rich( self::m( $id, 'cic_quote_heading' ) ?: sprintf( __( 'Plan your %s', 'cater-ice-cream-core' ), strtolower( $title ) ) ),
				'cta'        => self::m( $id, 'cic_quote_cta' ) ?: Contact::get()['quote_label'],
				'note'       => Contact::get()['availability_note'],
				'faq_title'  => self::rich( self::m( $id, 'cic_faq_heading' ) ?: __( 'Good to know.', 'cater-ice-cream-core' ) ),
				'faqs'       => self::faqs( $id ),
				'area_label' => self::m( $id, 'cic_area_link_label' ),
				'area_url'   => home_url( '/service-areas/' ),
				'starter'    => array(
					'location_label' => __( 'Venue city or ZIP code', 'cater-ice-cream-core' ),
					'date_label'     => __( 'Event date', 'cater-ice-cream-core' ),
					'guests_label'   => __( 'Estimated guests', 'cater-ice-cream-core' ),
				),
			),
		);

		$vm['sections'] = self::sections(
			$id,
			Fields::EVENT_SECTIONS,
			array(
				'hero'     => true,
				'starter'  => '' !== $vm['starter']['location'],
				'ribbon'   => (bool) $ribbon,
				'values'   => (bool) $vm['values'],
				'feature'  => '' !== $vm['feature']['heading'],
				'treats'   => (bool) $vm['treats']['items'],
				'stories'  => (bool) $vm['stories']['items'],
				'gallery'  => (bool) $vm['gallery']['image'],
				'planning' => (bool) $vm['planning']['steps'],
				'quote'    => true,
			),
			Fields::EVENT_LAYOUT_SECTIONS[ $layout ]
		);
		return $vm;
	}
}
