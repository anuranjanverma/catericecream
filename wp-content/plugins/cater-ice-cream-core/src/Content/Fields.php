<?php
/**
 * Structured fields for treats (products) and events, registered as ACF Free local field groups.
 *
 * Only ACF Free field types are used (text, textarea, image, select, true/false, number,
 * relationship, tab). No Repeater, Flexible Content, Gallery, Blocks or Options Pages. Repeating
 * content uses a small number of fixed, named slots; empty slots are not rendered.
 * Values are plain post meta, so templates read them with get_post_meta() via get() and keep
 * working if ACF is deactivated.
 *
 * @package CIC
 */

namespace CIC\Content;

use CIC\Design\Schema;

defined( 'ABSPATH' ) || exit;

final class Fields {

	/** Bundled line icons (rendered by the theme as inline SVG). */
	public const ICONS = array(
		''           => '— none —',
		'sparkle'    => 'Sparkle',
		'heart'      => 'Heart',
		'people'     => 'People',
		'star'       => 'Star',
		'sun'        => 'Sun',
		'beads'      => 'Tiny beads',
		'cone'       => 'Ice cream cone',
		'person'     => 'Person',
		'gift'       => 'Gift',
		'hand-heart' => 'Hand with heart',
		'calendar'   => 'Calendar',
		'pin'        => 'Location pin',
		'chat'       => 'Conversation',
		'leaf'       => 'Leaf',
		'cupcake'    => 'Cupcake',
		'wand'       => 'Magic wand',
	);

	public const FAQ_SLOTS = 6;

	/** Treat sections in approved order (09_treat_dippin_dots.png). */
	public const TREAT_SECTIONS = array(
		'hero'      => 'Hero',
		'feelings'  => 'Feelings',
		'occasions' => 'Occasion pairings',
		'themes'    => 'Theme inspiration',
		'detail'    => 'Product detail',
		'faq'       => 'FAQs',
		'quote'     => 'Quote starter',
	);

	/** Event sections in approved order (04_birthday_final.png). */
	public const EVENT_SECTIONS = array(
		'hero'     => 'Hero',
		'starter'  => 'Quote starter strip',
		'treats'   => 'Featured treats',
		'stories'  => 'Occasion stories',
		'planning' => 'Planning prompts',
		'quote'    => 'Quote and FAQ',
	);

	/** Homepage sections in approved order (01/02 home references). */
	public const HOME_SECTIONS = array(
		'hero'     => 'Hero and search',
		'values'   => 'Value points',
		'treats'   => 'Treats',
		'events'   => 'Events',
		'process'  => 'How it works',
		'faq'      => 'FAQ',
		'coverage' => 'Service areas',
		'cta'      => 'Closing call to action',
	);

	public const EVENT_LAYOUTS = array(
		'birthday'    => 'Birthday composition',
		'wedding'     => 'Wedding composition',
		'anniversary' => 'Anniversary composition',
		'corporate'   => 'Corporate composition',
		'standard'    => 'Standard event composition',
	);

	public function register(): void {
		add_action( 'acf/init', array( $this, 'register_groups' ) );
	}

	/**
	 * Read a field value as stored post meta (independent of ACF).
	 *
	 * @return mixed
	 */
	public static function get( int $post_id, string $name ) {
		return get_post_meta( $post_id, $name, true );
	}

	/* ------------------------------------------------------------------ *
	 * Field builders
	 * ------------------------------------------------------------------ */

	/** @return array<string, mixed> */
	private static function f( string $type, string $name, string $label, array $extra = array() ): array {
		return array_merge(
			array(
				'key'   => 'field_' . $name,
				'name'  => $name,
				'label' => $label,
				'type'  => $type,
			),
			$extra
		);
	}

	/** @return array<string, mixed> */
	private static function tab( string $id, string $label ): array {
		return array(
			'key'       => 'field_tab_' . $id,
			'label'     => $label,
			'name'      => '',
			'type'      => 'tab',
			'placement' => 'top',
		);
	}

	/** @return array<string, mixed> */
	private static function text( string $name, string $label, string $instructions = '' ): array {
		return self::f( 'text', $name, $label, array( 'instructions' => $instructions ) );
	}

	/** @return array<string, mixed> */
	private static function area( string $name, string $label, string $instructions = '', int $rows = 3 ): array {
		return self::f( 'textarea', $name, $label, array( 'instructions' => $instructions, 'rows' => $rows, 'new_lines' => '' ) );
	}

	/** @return array<string, mixed> */
	private static function image( string $name, string $label, string $instructions = '' ): array {
		return self::f( 'image', $name, $label, array( 'instructions' => $instructions, 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'mime_types' => 'jpg,jpeg,png,webp' ) );
	}

	/** @return array<string, mixed> */
	private static function icon( string $name, string $label ): array {
		return self::f( 'select', $name, $label, array( 'choices' => self::ICONS, 'allow_null' => 0, 'ui' => 0, 'default_value' => '' ) );
	}

	/** @return array<string, mixed> */
	private static function heading( string $name, string $label ): array {
		return self::text( $name, $label, 'Wrap accent words in &lt;em&gt;…&lt;/em&gt; (for example: More than dessert. &lt;em&gt;A little shared delight.&lt;/em&gt;).' );
	}

	/**
	 * Section visibility, order and optional preset for a list of sections.
	 *
	 * @param array<string, string> $sections Section key => label.
	 * @return array<int, array<string, mixed>>
	 */
	public static function section_controls( array $sections ): array {
		$contexts = array( '' => 'Inherit page preset' );
		foreach ( Schema::contexts() as $key => $def ) {
			$contexts[ $key ] = $def['label'];
		}
		$fields = array(
			self::f( 'message', 'cic_sections_help', 'How sections work', array( 'message' => 'Sections appear in the approved order by default. Untick a section to hide it, change its order number to move it, or choose a preset to restyle just that section. Empty sections are hidden automatically.' ) ),
		);
		$i = 0;
		foreach ( $sections as $key => $label ) {
			$i++;
			$fields[] = self::f( 'true_false', 'cic_sec_' . $key . '_on', $label . ': show', array( 'default_value' => 1, 'ui' => 1, 'wrapper' => array( 'width' => '30' ) ) );
			$fields[] = self::f( 'number', 'cic_sec_' . $key . '_order', $label . ': order', array( 'default_value' => $i * 10, 'min' => 0, 'max' => 999, 'step' => 1, 'wrapper' => array( 'width' => '30' ) ) );
			$fields[] = self::f( 'select', 'cic_sec_' . $key . '_preset', $label . ': preset', array( 'choices' => $contexts, 'default_value' => '', 'wrapper' => array( 'width' => '40' ) ) );
		}
		return $fields;
	}

	/* ------------------------------------------------------------------ *
	 * Groups
	 * ------------------------------------------------------------------ */

	public function register_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		acf_add_local_field_group( self::treat_group() );
		acf_add_local_field_group( self::event_group() );
		acf_add_local_field_group( self::home_group() );
	}

	/** @return array<string, mixed> */
	public static function home_group(): array {
		$fields = array(
			self::tab( 'home_hero', 'Hero and search' ),
			self::text( 'cic_home_eyebrow', 'Eyebrow' ),
			self::area( 'cic_home_headline', 'Headline', 'One line per row. Wrap accent words in &lt;em&gt;…&lt;/em&gt;.', 2 ),
			self::area( 'cic_home_intro', 'Introduction', '', 2 ),
			self::image( 'cic_home_image_main', 'Main photo', 'Large rounded photo.' ),
			self::image( 'cic_home_image_inset_1', 'Inset photo 1', 'Top right.' ),
			self::image( 'cic_home_image_inset_2', 'Inset photo 2', 'Bottom right.' ),
			self::text( 'cic_search_location_label', 'Search: location label' ),
			self::text( 'cic_search_location_ph', 'Search: location placeholder' ),
			self::text( 'cic_search_event_label', 'Search: event type label' ),
			self::text( 'cic_search_event_ph', 'Search: event type placeholder' ),
			self::text( 'cic_search_date_label', 'Search: date label' ),
			self::text( 'cic_search_cta', 'Search: button label' ),
			self::text( 'cic_search_note', 'Search: note under the fields', 'Never mention prices or minimum orders. Leave empty to use the global availability note.' ),
			self::tab( 'home_values', 'Value points' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_value_{$i}_icon", "Value {$i}: icon" );
			$fields[] = self::text( "cic_value_{$i}_label", "Value {$i}: label" );
		}
		$fields[] = self::tab( 'home_treats', 'Treats' );
		$fields[] = self::heading( 'cic_treats_heading', 'Section heading' );
		$fields[] = self::text( 'cic_treats_intro', 'Introduction' );
		$fields[] = self::f( 'message', 'cic_home_treats_help', 'Which treats appear', array( 'message' => 'Published, active treats appear in their menu order: the first two as large brand cards (with their catalogue card tagline), the rest as small cards.' ) );
		$fields[] = self::text( 'cic_treats_more_label', 'More treats card label' );
		$fields[] = self::image( 'cic_treats_more_image', 'More treats card photo' );
		$fields[] = self::tab( 'home_events', 'Events' );
		$fields[] = self::heading( 'cic_events_heading', 'Section heading' );
		$fields[] = self::f( 'message', 'cic_home_events_help', 'Which events appear', array( 'message' => 'Published events appear in their menu order with their featured image and excerpt.' ) );
		$fields[] = self::tab( 'home_process', 'How it works' );
		$fields[] = self::heading( 'cic_process_heading', 'Section heading' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::text( "cic_step_{$i}_title", "Step {$i}: title" );
			$fields[] = self::text( "cic_step_{$i}_text", "Step {$i}: text" );
		}
		$fields[] = self::text( 'cic_process_cta', 'Button label' );
		$fields[] = self::tab( 'home_faq', 'FAQ' );
		$fields[] = self::area( 'cic_faq_heading', 'Heading', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 );
		for ( $i = 1; $i <= self::FAQ_SLOTS; $i++ ) {
			$fields[] = self::text( "cic_faq_{$i}_q", "Question {$i}" );
			$fields[] = self::area( "cic_faq_{$i}_a", "Answer {$i}", 'No prices or minimum-order amounts.', 2 );
		}
		$fields[] = self::tab( 'home_coverage', 'Service areas' );
		$fields[] = self::area( 'cic_cov_heading', 'Heading', 'One line per row.', 2 );
		$fields[] = self::area( 'cic_cov_intro', 'Introduction', '', 2 );
		$fields[] = self::text( 'cic_cov_placeholder', 'Search placeholder' );
		$fields[] = self::text( 'cic_cov_button', 'Search button label' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::text( "cic_cov_county_{$i}_name", "County {$i}: name" );
			$fields[] = self::area( "cic_cov_county_{$i}_cities", "County {$i}: cities", 'One city per line. Only list cities confirmed in the approved service-area data. They link to city pages once those exist.', 3 );
		}
		$fields[] = self::text( 'cic_cov_note', 'Note under the directory' );
		$fields[] = self::text( 'cic_cov_map_caption', 'Map caption', 'The map is an illustration, not a coverage boundary.' );
		$fields[] = self::tab( 'home_cta', 'Closing call to action' );
		$fields[] = self::heading( 'cic_cta_heading', 'Heading' );
		$fields[] = self::text( 'cic_cta_label', 'Button label' );
		$fields[] = self::f( 'true_false', 'cic_cta_phone', 'Also show the phone number', array( 'default_value' => 0, 'ui' => 1 ) );
		$fields[] = self::tab( 'home_sections', 'Sections' );
		$fields   = array_merge( $fields, self::section_controls( self::HOME_SECTIONS ) );

		return array(
			'key'                   => 'group_cic_home',
			'title'                 => 'Homepage composition',
			'fields'                => $fields,
			'location'              => array( array( array( 'param' => 'page_type', 'operator' => '==', 'value' => 'front_page' ) ) ),
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'show_in_rest'          => 1,
			'instruction_placement' => 'label',
		);
	}

	/** @return array<string, mixed> */
	public static function treat_group(): array {
		$palette = array();
		foreach ( Schema::colors() as $key => $def ) {
			$palette[ $key ] = $def['label'];
		}
		$fields = array(
			self::tab( 'treat_hero', 'Hero' ),
			self::f( 'message', 'cic_treat_reuse_help', 'Standard WooCommerce fields', array( 'message' => 'Title, short description (catalogue card text), long description (shown as "what it is" in Product detail), featured image and the product gallery stay in the normal WooCommerce fields.' ) ),
			self::text( 'cic_hero_eyebrow', 'Eyebrow', 'Small capitals line above the headline.' ),
			self::area( 'cic_hero_headline', 'Emotional headline', 'One line per row. Wrap accent words in &lt;em&gt;…&lt;/em&gt;.', 2 ),
			self::area( 'cic_hero_intro', 'Introduction' ),
			self::text( 'cic_hero_cta', 'Quote button label', 'Opens the shared quote flow with this treat selected.' ),
			self::text( 'cic_hero_jump', 'Jump link label', 'Scrolls to the occasion pairings, e.g. "Find your occasion".' ),
			self::image( 'cic_hero_image', 'Hero photo', 'Large photo on the right.' ),
			self::text( 'cic_card_tagline', 'Catalogue card tagline', 'Short line on homepage and catalogue cards, e.g. Little beads. Big smiles.' ),
			self::text( 'cic_card_link', 'Catalogue card link label', 'e.g. Explore Dippin Dots.' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_chip_{$i}_icon", "Feeling chip {$i}: icon" );
			$fields[] = self::text( "cic_chip_{$i}_label", "Feeling chip {$i}: label" );
		}
		$fields[] = self::tab( 'treat_feelings', 'Feelings' );
		$fields[] = self::heading( 'cic_feel_heading', 'Section heading' );
		for ( $i = 1; $i <= 4; $i++ ) {
			$fields[] = self::icon( "cic_feel_{$i}_icon", "Quality {$i}: icon" );
			$fields[] = self::text( "cic_feel_{$i}_title", "Quality {$i}: title" );
			$fields[] = self::area( "cic_feel_{$i}_text", "Quality {$i}: explanation", '', 2 );
		}
		$fields[] = self::text( 'cic_feel_note', 'Note under the qualities' );
		$fields[] = self::tab( 'treat_occasions', 'Occasion pairings' );
		$fields[] = self::heading( 'cic_occ_heading', 'Section heading' );
		$fields[] = self::f( 'message', 'cic_occ_help', 'Pairings', array( 'message' => 'Each pairing (event, headline, explanation, photo, link label) is edited in the "Occasion pairings" box below. The same text appears on the event page.' ) );
		$fields[] = self::tab( 'treat_themes', 'Theme inspiration' );
		$fields[] = self::text( 'cic_theme_eyebrow', 'Eyebrow' );
		$fields[] = self::heading( 'cic_theme_heading', 'Section heading' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::text( "cic_theme_{$i}_title", "Theme {$i}: title" );
			$fields[] = self::area( "cic_theme_{$i}_text", "Theme {$i}: description", '', 2 );
			$fields[] = self::image( "cic_theme_{$i}_image", "Theme {$i}: photo" );
			$fields[] = self::f( 'select', "cic_theme_{$i}_palette", "Theme {$i}: palette swatches", array( 'choices' => $palette, 'multiple' => 1, 'ui' => 1, 'return_format' => 'value', 'instructions' => 'Up to three Design System colours shown as swatches. Styling inspiration only.' ) );
		}
		$fields[] = self::text( 'cic_theme_note', 'Note under the themes', 'Keep it clear that decor and options are discussed separately; no stock promise.' );
		$fields[] = self::tab( 'treat_detail', 'Product detail' );
		$fields[] = self::heading( 'cic_detail_heading', 'Section heading' );
		$fields[] = self::area( 'cic_detail_texture', 'Texture', 'Optional extra paragraph about texture.' );
		$fields[] = self::area( 'cic_detail_options', 'Options to discuss', 'Optional. Describe what is confirmed with the quote; do not promise flavours or stock.' );
		$fields[] = self::image( 'cic_detail_image', 'Detail photo' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_point_{$i}_icon", "Detail point {$i}: icon" );
			$fields[] = self::text( "cic_point_{$i}_label", "Detail point {$i}: label" );
		}
		$fields[] = self::area( 'cic_ingredient_note', 'Ingredient / dietary note', 'Client-approved wording only. No dietary guarantees.', 2 );
		$fields[] = self::tab( 'treat_faq', 'FAQs' );
		$fields[] = self::heading( 'cic_faq_heading', 'FAQ heading' );
		for ( $i = 1; $i <= self::FAQ_SLOTS; $i++ ) {
			$fields[] = self::text( "cic_faq_{$i}_q", "Question {$i}" );
			$fields[] = self::area( "cic_faq_{$i}_a", "Answer {$i}", '', 2 );
		}
		$fields[] = self::tab( 'treat_quote', 'Quote starter' );
		$fields[] = self::heading( 'cic_quote_heading', 'Heading' );
		$fields[] = self::area( 'cic_quote_intro', 'Introduction', '', 2 );
		$fields[] = self::text( 'cic_quote_cta', 'Button label' );
		$fields[] = self::text( 'cic_quote_note', 'Note under the button' );
		$fields[] = self::tab( 'treat_related', 'Related' );
		$fields[] = self::f( 'relationship', 'cic_related_products', 'Related treats', array( 'post_type' => array( 'product' ), 'post_status' => array( 'publish' ), 'filters' => array( 'search' ), 'return_format' => 'id', 'max' => 4 ) );
		$fields[] = self::tab( 'treat_sections', 'Sections' );
		$fields   = array_merge( $fields, self::section_controls( self::TREAT_SECTIONS ) );

		return array(
			'key'                   => 'group_cic_treat',
			'title'                 => 'Treat page composition',
			'fields'                => $fields,
			'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'product' ) ) ),
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'show_in_rest'          => 1,
			'instruction_placement' => 'label',
		);
	}

	/** @return array<string, mixed> */
	public static function event_group(): array {
		$fields = array(
			self::tab( 'event_hero', 'Hero' ),
			self::f( 'select', 'cic_layout', 'Composition', array( 'choices' => self::EVENT_LAYOUTS, 'default_value' => 'standard', 'instructions' => 'Approved page composition. Birthday is built in Phase 03; the others use the standard composition until Phase 07.' ) ),
			self::text( 'cic_hero_eyebrow', 'Eyebrow' ),
			self::area( 'cic_hero_headline', 'Headline', 'One line per row. &lt;em&gt;…&lt;/em&gt; uses the accent colour; &lt;strong&gt;…&lt;/strong&gt; uses the second accent.', 3 ),
			self::area( 'cic_hero_intro', 'Introduction', '', 2 ),
			self::text( 'cic_hero_cta', 'Quote button label', 'Opens the shared quote flow with this occasion selected.' ),
			self::text( 'cic_hero_link_label', 'Treats link label' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::image( "cic_hero_image_{$i}", "Hero photo {$i}", 1 === $i ? 'Left of the collage.' : ( 2 === $i ? 'Centre (largest).' : 'Right.' ) );
		}
		$fields[] = self::tab( 'event_starter', 'Quote starter strip' );
		$fields[] = self::text( 'cic_starter_label_location', 'Location label' );
		$fields[] = self::text( 'cic_starter_label_date', 'Date label' );
		$fields[] = self::text( 'cic_starter_label_guests', 'Guests label' );
		$fields[] = self::text( 'cic_starter_cta', 'Button label' );
		$fields[] = self::tab( 'event_treats', 'Featured treats' );
		$fields[] = self::heading( 'cic_treats_heading', 'Section heading' );
		$fields[] = self::text( 'cic_treats_intro', 'Introduction' );
		$fields[] = self::f( 'message', 'cic_treats_help', 'Which treats appear', array( 'message' => 'Treats paired with this event appear here automatically, using the pairing order (the first two are shown large). Edit pairings on each treat.' ) );
		$fields[] = self::text( 'cic_treats_more_label', '"More treats" card label' );
		$fields[] = self::image( 'cic_treats_more_image', '"More treats" card photo' );
		$fields[] = self::tab( 'event_stories', 'Occasion stories' );
		$fields[] = self::heading( 'cic_story_heading', 'Section heading' );
		for ( $i = 1; $i <= 2; $i++ ) {
			$fields[] = self::area( "cic_story_{$i}_title", "Story {$i}: title", 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 );
			$fields[] = self::area( "cic_story_{$i}_text", "Story {$i}: text", '', 3 );
			$fields[] = self::image( "cic_story_{$i}_image", "Story {$i}: photo" );
		}
		$fields[] = self::tab( 'event_planning', 'Planning prompts' );
		$fields[] = self::area( 'cic_plan_heading', 'Heading', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 );
		$fields[] = self::area( 'cic_plan_intro', 'Introduction', '', 2 );
		$fields[] = self::image( 'cic_plan_image', 'Photo' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::text( "cic_plan_{$i}_title", "Step {$i}: title" );
			$fields[] = self::area( "cic_plan_{$i}_text", "Step {$i}: text", '', 2 );
		}
		$fields[] = self::tab( 'event_quote', 'Quote and FAQ' );
		$fields[] = self::heading( 'cic_quote_heading', 'Quote heading' );
		$fields[] = self::text( 'cic_quote_cta', 'Quote button label' );
		$fields[] = self::heading( 'cic_faq_heading', 'FAQ heading' );
		for ( $i = 1; $i <= self::FAQ_SLOTS; $i++ ) {
			$fields[] = self::text( "cic_faq_{$i}_q", "Question {$i}" );
			$fields[] = self::area( "cic_faq_{$i}_a", "Answer {$i}", '', 2 );
		}
		$fields[] = self::text( 'cic_area_link_label', 'Service-area link label' );
		$fields[] = self::tab( 'event_sections', 'Sections' );
		$fields   = array_merge( $fields, self::section_controls( self::EVENT_SECTIONS ) );

		return array(
			'key'                   => 'group_cic_event',
			'title'                 => 'Event page composition',
			'fields'                => $fields,
			'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => EventType::POST_TYPE ) ) ),
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'show_in_rest'          => 1,
			'instruction_placement' => 'label',
		);
	}

	/**
	 * Every field name of a group (used by the seeder and tests).
	 *
	 * @return string[]
	 */
	public static function names( array $group ): array {
		$data = array_filter( $group['fields'], static fn( $f ) => ! in_array( $f['type'], array( 'tab', 'message' ), true ) );
		return array_values( array_map( static fn( $f ) => $f['name'], $data ) );
	}
}
