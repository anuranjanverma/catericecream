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
	 * Enabled, non-empty sections sorted by their order number.
	 *
	 * @param array<string, string> $defined Section definitions (approved order).
	 * @param array<string, bool>   $has     Section key => has content.
	 * @return array<int, array{key: string, preset: string}>
	 */
	private static function sections( int $id, array $defined, array $has ): array {
		$out = array();
		$i   = 0;
		foreach ( array_keys( $defined ) as $key ) {
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

		$vm = array(
			'id'        => $id,
			'key'       => $key,
			'title'     => $title,
			'url'       => get_permalink( $id ),
			'quote_url' => self::quote_url( 'treat', $key ),
			'hero'      => array(
				'eyebrow'  => self::m( $id, 'cic_hero_eyebrow' ),
				'headline' => self::lines( self::m( $id, 'cic_hero_headline' ) ?: $title ),
				'intro'    => self::m( $id, 'cic_hero_intro' ) ?: wp_strip_all_tags( (string) $product->get_short_description() ),
				'cta'      => self::m( $id, 'cic_hero_cta' ) ?: Contact::get()['quote_label'],
				'jump'     => self::m( $id, 'cic_hero_jump' ),
				'image'    => self::img( $id, 'cic_hero_image' ) ?: (int) $product->get_image_id(),
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
				'items'   => self::faqs( $id ),
			),
			'quote'     => array(
				'heading' => self::rich( self::m( $id, 'cic_quote_heading' ) ?: __( 'What kind of moment are you <em>planning?</em>', 'cater-ice-cream-core' ) ),
				'intro'   => self::m( $id, 'cic_quote_intro' ),
				'cta'     => self::m( $id, 'cic_quote_cta' ) ?: Contact::get()['quote_label'],
				'note'    => self::m( $id, 'cic_quote_note' ) ?: Contact::get()['availability_note'],
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

		$stories = $steps = array();
		for ( $i = 1; $i <= 2; $i++ ) {
			$stories[] = array( 'title' => self::lines( self::m( $id, "cic_story_{$i}_title" ) ), 'text' => self::m( $id, "cic_story_{$i}_text" ), 'image' => self::img( $id, "cic_story_{$i}_image" ) );
		}
		for ( $i = 1; $i <= 3; $i++ ) {
			$steps[] = array( 'title' => self::m( $id, "cic_plan_{$i}_title" ), 'text' => self::m( $id, "cic_plan_{$i}_text" ) );
		}
		$images = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$images[] = self::img( $id, "cic_hero_image_{$i}" );
		}
		$layout = self::m( $id, 'cic_layout' );

		$vm = array(
			'id'        => $id,
			'key'       => $key,
			'title'     => $title,
			'layout'    => isset( Fields::EVENT_LAYOUTS[ $layout ] ) ? $layout : 'standard',
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
			'treats'    => array(
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
			),
		);

		$vm['sections'] = self::sections(
			$id,
			Fields::EVENT_SECTIONS,
			array(
				'hero'     => true,
				'starter'  => '' !== $vm['starter']['location'],
				'treats'   => (bool) $vm['treats']['items'],
				'stories'  => (bool) $vm['stories']['items'],
				'planning' => (bool) $vm['planning']['steps'],
				'quote'    => true,
			)
		);
		return $vm;
	}
}
