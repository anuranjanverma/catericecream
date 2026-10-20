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
		'home'       => 'House',
		'sundae'     => 'Sundae',
		'banana'     => 'Banana',
		'spoon'      => 'Spoon',
		'cup'        => 'Dessert cup',
		'handshake'  => 'Handshake',
		'building'   => 'Office building',
		'rings'      => 'Rings',
		'briefcase'  => 'Briefcase',
		'cake'       => 'Birthday cake',
		'gift-cups'  => 'Two dessert cups',
		'clipboard'  => 'Checklist',
		'mail'       => 'Envelope',
		'phone'      => 'Phone',
	);

	public const FAQ_SLOTS = 6;

	/** Treat compositions (09–12). DipN' Bananas shares the Dippin' Dots composition. */
	public const TREAT_LAYOUTS = array(
		'split'   => 'Split hero (Dippin\' Dots / DipN\' Bananas)',
		'classic' => 'Centred hero (Classic Ice Cream)',
		'yogurt'  => 'Fresh hero (Frozen Yogurt)',
	);

	/** Approved section order per treat composition (FAQ sits inside the quote area in 11/12). */
	public const TREAT_LAYOUT_SECTIONS = array(
		'split'   => array( 'hero', 'feelings', 'occasions', 'themes', 'detail', 'faq', 'quote' ),
		'classic' => array( 'hero', 'feelings', 'occasions', 'themes', 'detail', 'quote' ),
		'yogurt'  => array( 'hero', 'feelings', 'themes', 'occasions', 'detail', 'quote' ),
	);

	/** Treat sections (labels for the section controls). */
	public const TREAT_SECTIONS = array(
		'hero'      => 'Hero',
		'feelings'  => 'Feelings',
		'occasions' => 'Occasion pairings',
		'themes'    => 'Theme inspiration',
		'detail'    => 'Product detail',
		'faq'       => 'FAQs',
		'quote'     => 'Quote starter',
	);

	/** Event sections (labels for the section controls); each layout uses its own subset. */
	public const EVENT_SECTIONS = array(
		'hero'     => 'Hero',
		'starter'  => 'Quote starter strip (Birthday)',
		'ribbon'   => 'Ribbon phrases (Wedding)',
		'values'   => 'Value points (Anniversary, Corporate)',
		'feature'  => 'Story with photo (Wedding, Anniversary, Corporate)',
		'treats'   => 'Featured treats',
		'stories'  => 'Occasion stories (Birthday)',
		'gallery'  => 'Wide photo band (Wedding)',
		'planning' => 'Planning steps',
		'quote'    => 'Quote and FAQ',
	);

	/** Approved section order per event composition (04–07). Not one reordered template. */
	public const EVENT_LAYOUT_SECTIONS = array(
		'birthday'    => array( 'hero', 'starter', 'treats', 'stories', 'planning', 'quote' ),
		'wedding'     => array( 'hero', 'ribbon', 'feature', 'treats', 'gallery', 'planning', 'quote' ),
		'anniversary' => array( 'hero', 'values', 'treats', 'feature', 'planning', 'quote' ),
		'corporate'   => array( 'hero', 'values', 'treats', 'feature', 'planning', 'quote' ),
		'standard'    => array( 'hero', 'treats', 'feature', 'planning', 'quote' ),
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

	/** Service Areas hub sections in approved order (15_service_areas.png). */
	public const HUB_SECTIONS = array(
		'areas_hero'      => 'Hero, search and map',
		'areas_counties'  => 'County directory',
		'areas_steps'     => 'What we need to check',
		'areas_call'      => 'Call panel',
		'areas_occasions' => 'Occasions',
		'areas_faq'       => 'FAQ',
		'areas_start'     => 'Quote starter',
	);

	public const CITY_FAQ_SLOTS = 4;

	/**
	 * City page fields (13_city_pasadena.png): suffix => [type, label]. Defaults live on the
	 * Service Areas page (cic_citydef_*); city content records override them (cic_city_*).
	 * Tokens: {city}, {state}, {state_code}, {county}.
	 */
	public const CITY_FIELDS = array(
		'eyebrow'           => array( 'text', 'Hero eyebrow' ),
		'title'             => array( 'heading', 'Hero headline' ),
		'subtitle'          => array( 'text', 'Hero subheading' ),
		'intro'             => array( 'area', 'Hero introduction' ),
		'image_main'        => array( 'image', 'Hero main photo' ),
		'image_inset_1'     => array( 'image', 'Hero inset photo 1 (top)' ),
		'image_inset_2'     => array( 'image', 'Hero inset photo 2 (bottom)' ),
		'search_cta'        => array( 'text', 'Hero search button' ),
		'search_note'       => array( 'text', 'Hero search note' ),
		'plans_heading'     => array( 'heading', 'Plans heading' ),
		'plans_intro'       => array( 'area', 'Plans introduction' ),
		'treats_heading'    => array( 'heading', 'Treats heading' ),
		'call_heading'      => array( 'area', 'Call panel heading (one line per row)' ),
		'call_text'         => array( 'area', 'Call panel text' ),
		'call_image'        => array( 'image', 'Call panel photo' ),
		'call_note'         => array( 'text', 'Call panel note' ),
		'occasions_heading' => array( 'heading', 'Occasions heading' ),
		'check_heading'     => array( 'heading', 'Location check heading' ),
		'check_intro'       => array( 'text', 'Location check introduction' ),
		'faq_heading'       => array( 'heading', 'FAQ heading' ),
		'faq_1_q'           => array( 'text', 'Question 1' ),
		'faq_1_a'           => array( 'area', 'Answer 1' ),
		'faq_2_q'           => array( 'text', 'Question 2' ),
		'faq_2_a'           => array( 'area', 'Answer 2' ),
		'faq_3_q'           => array( 'text', 'Question 3' ),
		'faq_3_a'           => array( 'area', 'Answer 3' ),
		'faq_4_q'           => array( 'text', 'Question 4' ),
		'faq_4_a'           => array( 'area', 'Answer 4' ),
		'quote_heading'     => array( 'heading', 'Quote panel heading' ),
		'quote_note'        => array( 'text', 'Quote panel note' ),
		'seo_title'         => array( 'text', 'Search result title' ),
		'seo_description'   => array( 'area', 'Search result description' ),
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
			self::f( 'message', 'cic_sections_help', 'How sections work', array( 'message' => 'Sections appear in the approved order of this page\'s composition by default (leave the order empty to keep it). Untick a section to hide it, enter an order number to move it, or choose a preset to restyle just that section. Empty sections, and sections that are not part of the chosen composition, are not shown.' ) ),
		);
		$i = 0;
		foreach ( $sections as $key => $label ) {
			$i++;
			$fields[] = self::f( 'true_false', 'cic_sec_' . $key . '_on', $label . ': show', array( 'default_value' => 1, 'ui' => 1, 'wrapper' => array( 'width' => '30' ) ) );
			$fields[] = self::f( 'number', 'cic_sec_' . $key . '_order', $label . ': order', array( 'default_value' => '', 'placeholder' => (string) ( $i * 10 ), 'instructions' => 'Empty = approved order.', 'min' => 0, 'max' => 999, 'step' => 1, 'wrapper' => array( 'width' => '30' ) ) );
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
		acf_add_local_field_group( self::hub_group() );
		acf_add_local_field_group( self::city_group() );
		acf_add_local_field_group( self::shop_group() );
		acf_add_local_field_group( self::about_group() );
		acf_add_local_field_group( self::contact_group() );
		acf_add_local_field_group( self::policy_group() );
	}

	/**
	 * Location rule for one Page found by its stable key (or a value that never matches).
	 *
	 * @return array<int, array<int, array<string, string>>>
	 */
	private static function page_rule( string $key ): array {
		$id = 'shop' === $key && function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : Catalog::find_by_key( 'page', $key );
		return array( array( array( 'param' => 'page', 'operator' => '==', 'value' => (string) ( $id > 0 ? $id : -1 ) ) ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<string, mixed>
	 */
	private static function page_group( string $key, string $title, array $fields, array $location ): array {
		return array(
			'key'                   => 'group_cic_' . $key,
			'title'                 => $title,
			'fields'                => $fields,
			'location'              => $location,
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'show_in_rest'          => 1,
			'instruction_placement' => 'label',
		);
	}

	/**
	 * Our Treats page (the WooCommerce shop page, 08_treats_catalogue.png). Rows come from the
	 * published treats; each treat's row text is on the product ("Our Treats row" tab).
	 *
	 * @return array<string, mixed>
	 */
	public static function shop_group(): array {
		$p      = 'cic_shop_';
		$fields = array(
			self::tab( 'shop_hero', 'Hero' ),
			self::text( $p . 'eyebrow', 'Eyebrow' ),
			self::area( $p . 'headline', 'Headline', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 ),
			self::area( $p . 'intro', 'Introduction', '', 2 ),
			self::image( $p . 'image', 'Hero photo' ),
			self::text( $p . 'jump_label', 'Jump links label', 'e.g. Jump to a treat:' ),
			self::text( $p . 'note', 'Note under the treats', 'No stock, flavour or dietary promises.' ),
			self::tab( 'shop_help', '"Not sure" panel' ),
			self::heading( $p . 'help_heading', 'Heading' ),
			self::area( $p . 'help_text', 'Text', '', 2 ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "{$p}help_{$i}_icon", "Item {$i}: icon" );
			$fields[] = self::text( "{$p}help_{$i}_title", "Item {$i}: title" );
			$fields[] = self::text( "{$p}help_{$i}_text", "Item {$i}: text" );
		}
		$fields[] = self::text( $p . 'help_cta', 'Button label' );
		$fields[] = self::tab( 'shop_start', 'Quote starter' );
		$fields[] = self::heading( $p . 'start_heading', 'Heading' );
		$fields[] = self::text( $p . 'start_subheading', 'Subheading' );
		$fields[] = self::text( $p . 'start_cta', 'Button label' );
		$fields[] = self::text( $p . 'start_note', 'Note under the fields' );
		return self::page_group( 'shop', 'Our Treats composition', $fields, self::page_rule( 'shop' ) );
	}

	/**
	 * About page (14_about.png). Verified facts only; the initials graphic is a placeholder until a
	 * real portrait is supplied.
	 *
	 * @return array<string, mixed>
	 */
	public static function about_group(): array {
		$p      = 'cic_about_';
		$fields = array(
			self::tab( 'about_hero', 'Hero' ),
			self::text( $p . 'eyebrow', 'Eyebrow' ),
			self::area( $p . 'headline', 'Headline', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 3 ),
			self::area( $p . 'intro', 'Introduction', '', 2 ),
			self::image( $p . 'image', 'Main photo' ),
			self::image( $p . 'inset_1', 'Inset photo 1' ),
			self::image( $p . 'inset_2', 'Inset photo 2' ),
			self::tab( 'about_story', 'Story' ),
			self::heading( $p . 'story_heading', 'Heading' ),
			self::area( $p . 'story_text', 'Text', 'Blank line between paragraphs. Verified facts only: no invented history or claims.', 5 ),
			self::image( $p . 'story_image', 'Photo' ),
			self::area( $p . 'quote', 'Highlighted line', 'One line per row.', 2 ),
			self::tab( 'about_brands', 'Treats' ),
			self::heading( $p . 'brands_heading', 'Heading' ),
			self::text( $p . 'brands_line', 'Line under the treats' ),
			self::text( $p . 'brands_link', 'Link label' ),
			self::tab( 'about_approach', 'Approach' ),
			self::heading( $p . 'approach_heading', 'Heading' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "{$p}step_{$i}_icon", "Step {$i}: icon" );
			$fields[] = self::text( "{$p}step_{$i}_title", "Step {$i}: title" );
			$fields[] = self::text( "{$p}step_{$i}_text", "Step {$i}: text" );
		}
		$fields[] = self::tab( 'about_meet', 'Meet' );
		$fields[] = self::heading( $p . 'meet_heading', 'Heading' );
		$fields[] = self::area( $p . 'meet_text', 'Text', 'Verified role only. Business name, location, phone and email come from the global contact settings.', 2 );
		$fields[] = self::text( $p . 'meet_initials', 'Initials (placeholder graphic)', 'Shown until a real portrait is supplied.' );
		$fields[] = self::image( $p . 'meet_portrait', 'Portrait (optional)', 'Only a real, approved portrait. Replaces the initials.' );
		$fields[] = self::image( $p . 'meet_image', 'Service photo' );
		$fields[] = self::tab( 'about_areas', 'Service areas' );
		$fields[] = self::heading( $p . 'areas_heading', 'Heading' );
		$fields[] = self::text( $p . 'areas_intro', 'Introduction' );
		$fields[] = self::text( $p . 'areas_note_title', 'Map note title' );
		$fields[] = self::text( $p . 'areas_note', 'Map note text' );
		$fields[] = self::text( $p . 'areas_link', 'Link label' );
		$fields[] = self::tab( 'about_cta', 'Closing call to action' );
		$fields[] = self::heading( $p . 'cta_heading', 'Heading' );
		return self::page_group( 'about', 'About composition', $fields, self::page_rule( 'about' ) );
	}

	/**
	 * Contact page (16_contact.png). The general message form is Phase 06 (CF7); until then the
	 * page offers the configured call/email routes and the quote route.
	 *
	 * @return array<string, mixed>
	 */
	public static function contact_group(): array {
		$p      = 'cic_contact_';
		$fields = array(
			self::tab( 'contact_hero', 'Hero' ),
			self::area( $p . 'headline', 'Headline', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 ),
			self::area( $p . 'intro', 'Introduction', '', 2 ),
			self::image( $p . 'image', 'Photo' ),
			self::text( $p . 'call_title', 'Call card title', 'Phone number comes from the global contact settings.' ),
			self::text( $p . 'email_title', 'Email card title', 'Email address comes from the global contact settings.' ),
			self::tab( 'contact_help', 'Message panel' ),
			self::heading( $p . 'help_heading', 'Heading' ),
			self::text( $p . 'help_intro', 'Introduction' ),
			self::area( $p . 'help_pending', 'Text while the message form is pending (Phase 06)', '', 2 ),
			self::tab( 'contact_plan', 'Planning panel' ),
			self::heading( $p . 'plan_heading', 'Heading' ),
			self::area( $p . 'plan_text', 'Text', '', 2 ),
			self::image( $p . 'plan_image_1', 'Photo 1 (large)' ),
			self::image( $p . 'plan_image_2', 'Photo 2' ),
			self::image( $p . 'plan_image_3', 'Photo 3' ),
			self::text( $p . 'plan_cta', 'Button label' ),
			self::text( $p . 'plan_link', 'Treats link label' ),
			self::tab( 'contact_where', 'Where we cater' ),
			self::heading( $p . 'where_heading', 'Heading' ),
			self::text( $p . 'where_text', 'Text' ),
			self::text( $p . 'where_link', 'Button label' ),
			self::tab( 'contact_faq', 'FAQ' ),
			self::heading( $p . 'faq_heading', 'Heading' ),
		);
		for ( $i = 1; $i <= self::FAQ_SLOTS; $i++ ) {
			$fields[] = self::text( "{$p}faq_{$i}_q", "Question {$i}" );
			$fields[] = self::area( "{$p}faq_{$i}_a", "Answer {$i}", '', 2 );
		}
		$fields[] = self::tab( 'contact_close', 'Closing band' );
		$fields[] = self::area( $p . 'close_heading', 'Heading', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 );
		$fields[] = self::text( $p . 'close_text', 'Text' );
		$fields[] = self::text( $p . 'close_quote_title', 'Quote card title' );
		$fields[] = self::text( $p . 'close_quote_text', 'Quote card text' );
		return self::page_group( 'contact', 'Contact composition', $fields, self::page_rule( 'contact' ) );
	}

	/**
	 * Policy pages (19_policy_and_404.png, template "Policy"). Approval is explicit: until it is
	 * ticked the page shows "Content pending approval" and should stay a draft.
	 *
	 * @return array<string, mixed>
	 */
	public static function policy_group(): array {
		$fields = array(
			self::f( 'message', 'cic_policy_help', 'Policy pages', array( 'message' => 'Write the policy in the editor using one heading per section; the "On this page" list is built from those headings. Keep the page as a Draft until the legal wording is approved. Never publish placeholder text as a final policy.' ) ),
			self::f( 'true_false', 'cic_policy_approved', 'Legal wording approved', array( 'default_value' => 0, 'ui' => 1, 'instructions' => 'Removes the "Content pending approval" label.' ) ),
			self::text( 'cic_policy_updated', 'Last updated (optional)', 'e.g. 1 November 2026.' ),
		);
		return self::page_group( 'policy', 'Policy page', $fields, array( array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'template-policy.php' ) ) ) );
	}

	/**
	 * City fields with a name prefix, grouped into tabs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function city_fields( string $prefix, string $tab_prefix ): array {
		$tabs   = array(
			'eyebrow'           => 'Hero and search',
			'plans_heading'     => 'Plans',
			'treats_heading'    => 'Treats',
			'call_heading'      => 'Call panel',
			'occasions_heading' => 'Occasions',
			'check_heading'     => 'Location check',
			'faq_heading'       => 'FAQ and quote',
			'seo_title'         => 'Search engines',
		);
		$fields = array();
		foreach ( self::CITY_FIELDS as $suffix => $def ) {
			if ( isset( $tabs[ $suffix ] ) ) {
				$fields[] = self::tab( $tab_prefix . $suffix, $tabs[ $suffix ] );
			}
			$name = $prefix . $suffix;
			switch ( $def[0] ) {
				case 'heading':
					$fields[] = self::heading( $name, $def[1] );
					break;
				case 'area':
					$fields[] = self::area( $name, $def[1], '', 2 );
					break;
				case 'image':
					$fields[] = self::image( $name, $def[1] );
					break;
				default:
					$fields[] = self::text( $name, $def[1] );
			}
		}
		return $fields;
	}

	/**
	 * Service Areas hub Page (15_service_areas.png) plus the city page template every city
	 * inherits. State hubs reuse this page's content.
	 *
	 * @return array<string, mixed>
	 */
	public static function hub_group(): array {
		$p      = 'cic_sa_';
		$fields = array(
			self::tab( 'sa_hero', 'Hero, search and map' ),
			self::text( $p . 'eyebrow', 'Eyebrow' ),
			self::area( $p . 'headline', 'Headline', 'One line per row. Wrap accent words in &lt;em&gt;…&lt;/em&gt;.', 2 ),
			self::area( $p . 'intro', 'Introduction', '', 2 ),
			self::text( $p . 'search_ph', 'Search placeholder' ),
			self::text( $p . 'search_button', 'Search button label' ),
			self::text( $p . 'search_hint', 'Hint under the search' ),
			self::image( $p . 'image', 'Hero photo' ),
			self::text( $p . 'map_caption', 'Map caption', 'Points are approximate ZIP centres from the reviewed location data, not service boundaries.' ),
			self::tab( 'sa_counties', 'County directory' ),
			self::heading( $p . 'counties_heading', 'Heading' ),
			self::area( $p . 'counties_intro', 'Introduction', '', 2 ),
			self::f( 'message', 'cic_sa_counties_help', 'Which cities appear', array( 'message' => 'Each county card lists published cities from the location data (Service Areas → Cities), in their directory order. The county name must match the county used in the location data.' ) ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::text( "{$p}county_{$i}_name", "County {$i}: name" );
			$fields[] = self::image( "{$p}county_{$i}_image", "County {$i}: photo" );
			$fields[] = self::text( "{$p}county_{$i}_text", "County {$i}: line under the name" );
			$fields[] = self::text( "{$p}county_{$i}_link", "County {$i}: check link label" );
		}
		$fields[] = self::tab( 'sa_steps', 'What we need' );
		$fields[] = self::heading( $p . 'steps_heading', 'Heading' );
		$fields[] = self::text( $p . 'steps_intro', 'Introduction' );
		for ( $i = 1; $i <= 4; $i++ ) {
			$fields[] = self::icon( "{$p}step_{$i}_icon", "Step {$i}: icon" );
			$fields[] = self::text( "{$p}step_{$i}_title", "Step {$i}: title" );
			$fields[] = self::text( "{$p}step_{$i}_text", "Step {$i}: text" );
		}
		$fields[] = self::tab( 'sa_call', 'Call panel' );
		$fields[] = self::heading( $p . 'call_heading', 'Heading' );
		$fields[] = self::text( $p . 'call_text', 'Text' );
		$fields[] = self::image( $p . 'call_image', 'Photo' );
		$fields[] = self::text( $p . 'call_quote_label', 'Quote button label' );
		$fields[] = self::tab( 'sa_occasions', 'Occasions' );
		$fields[] = self::heading( $p . 'occasions_heading', 'Heading' );
		$fields[] = self::area( $p . 'occasions_intro', 'Introduction', '', 2 );
		$fields[] = self::tab( 'sa_faq', 'FAQ' );
		$fields[] = self::area( $p . 'faq_heading', 'Heading', 'One line per row.', 2 );
		for ( $i = 1; $i <= self::FAQ_SLOTS; $i++ ) {
			$fields[] = self::text( "{$p}faq_{$i}_q", "Question {$i}" );
			$fields[] = self::area( "{$p}faq_{$i}_a", "Answer {$i}", '', 2 );
		}
		$fields[] = self::tab( 'sa_start', 'Quote starter' );
		$fields[] = self::heading( $p . 'start_heading', 'Heading' );
		$fields[] = self::text( $p . 'start_intro', 'Introduction' );
		$fields[] = self::text( $p . 'start_cta', 'Button label' );
		$fields[] = self::text( $p . 'start_note', 'Note under the fields' );
		$fields[] = self::tab( 'sa_sections', 'Sections' );
		$fields   = array_merge( $fields, self::section_controls( self::HUB_SECTIONS ) );

		$fields[] = self::tab( 'sa_citydef', 'City page template' );
		$fields[] = self::f( 'message', 'cic_citydef_help', 'How city pages inherit', array( 'message' => 'Every city page uses these defaults. A city content record (Service Areas → City content) can override any field. Tokens: {city}, {state}, {state_code}, {county}. City pages stay out of search engines until their own content is reviewed.' ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_citydef_plan_{$i}_icon", "Plans card {$i}: icon" );
			$fields[] = self::text( "cic_citydef_plan_{$i}_title", "Plans card {$i}: title" );
			$fields[] = self::text( "cic_citydef_plan_{$i}_text", "Plans card {$i}: text" );
		}
		foreach ( self::city_fields( 'cic_citydef_', 'citydef_' ) as $field ) {
			if ( 'tab' !== $field['type'] ) {
				$fields[] = $field;
			}
		}

		$hub = Catalog::find_by_key( 'page', 'service-areas' );
		return array(
			'key'                   => 'group_cic_service_areas',
			'title'                 => 'Service Areas composition',
			'fields'                => $fields,
			'location'              => array( array( array( 'param' => 'page', 'operator' => '==', 'value' => (string) ( $hub ? $hub : -1 ) ) ) ),
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'show_in_rest'          => 1,
			'instruction_placement' => 'label',
		);
	}

	/**
	 * City content record (private, revisioned): optional overrides of the city template.
	 *
	 * @return array<string, mixed>
	 */
	public static function city_group(): array {
		$fields = array_merge(
			array(
				self::f( 'message', 'cic_city_help', 'How city content works', array( 'message' => 'Leave a field empty to inherit the city template from the Service Areas page. Write genuinely local, useful content: pages that only swap the city name stay out of search engines. Tick “Content reviewed” only after checking every fact.' ) ),
				self::f( 'true_false', \CIC\Locations\Editorial::REVIEWED, 'Content reviewed (eligible for search engines)', array( 'default_value' => 0, 'ui' => 1 ) ),
			),
			self::city_fields( 'cic_city_', 'city_' )
		);
		return array(
			'key'                   => 'group_cic_city',
			'title'                 => 'City page content',
			'fields'                => $fields,
			'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => \CIC\Locations\Editorial::POST_TYPE ) ) ),
			'position'              => 'normal',
			'style'                 => 'default',
			'menu_order'            => 5,
			'instruction_placement' => 'label',
		);
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
			self::f( 'select', 'cic_treat_layout', 'Composition', array( 'choices' => self::TREAT_LAYOUTS, 'default_value' => 'split', 'instructions' => 'Approved page composition (references 09–12): hero style, section order and occasion card style.' ) ),
			self::text( 'cic_hero_eyebrow', 'Eyebrow', 'Small capitals line above the headline.' ),
			self::area( 'cic_hero_headline', 'Emotional headline', 'One line per row. Wrap accent words in &lt;em&gt;…&lt;/em&gt;.', 2 ),
			self::area( 'cic_hero_intro', 'Introduction' ),
			self::text( 'cic_hero_cta', 'Quote button label', 'Opens the shared quote flow with this treat selected.' ),
			self::text( 'cic_hero_jump', 'Jump link label', 'Scrolls to the occasion pairings, e.g. "Find your occasion".' ),
			self::image( 'cic_hero_image', 'Hero photo', 'Large photo (Classic: the scoops at the bottom centre).' ),
			self::image( 'cic_hero_image_2', 'Second hero photo (optional)', 'Classic: left photo. Frozen Yogurt: people photo behind the product.' ),
			self::image( 'cic_hero_image_3', 'Third hero photo (optional)', 'Classic: right photo.' ),
			self::text( 'cic_card_tagline', 'Catalogue card tagline', 'Short line on homepage and catalogue cards, e.g. Little beads. Big smiles.' ),
			self::text( 'cic_card_link', 'Catalogue card link label', 'e.g. Explore Dippin Dots.' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_chip_{$i}_icon", "Feeling chip {$i}: icon" );
			$fields[] = self::text( "cic_chip_{$i}_label", "Feeling chip {$i}: label" );
		}
		$fields[] = self::tab( 'treat_catalogue', 'Our Treats row' );
		$fields[] = self::f( 'message', 'cic_arch_help', 'Catalogue row', array( 'message' => 'How this treat appears on the Our Treats page. The description is the product\'s long description; the photo is its product image.' ) );
		$fields[] = self::text( 'cic_arch_badge', 'Badge', 'e.g. Brand favourite / Frozen favourite.' );
		$fields[] = self::text( 'cic_arch_subtitle', 'Subtitle', 'One line under the name, e.g. Tiny beads. A different kind of ice cream.' );
		$fields[] = self::text( 'cic_arch_experience', '"The experience" line' );
		$fields[] = self::text( 'cic_arch_discuss', '"Let\'s discuss" line', 'What is confirmed with the quote; no stock or flavour promise.' );
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
			$fields[] = self::icon( "cic_theme_{$i}_icon", "Theme {$i}: icon (optional, shown instead of swatches)" );
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
		$fields[] = self::text( 'cic_treat_faq_intro', 'FAQ introduction (optional)' );
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
			self::f( 'select', 'cic_layout', 'Composition', array( 'choices' => self::EVENT_LAYOUTS, 'default_value' => 'standard', 'instructions' => 'Approved page composition: its own sections, order and occasion styling (references 04–07).' ) ),
			self::text( 'cic_hero_eyebrow', 'Eyebrow' ),
			self::area( 'cic_hero_headline', 'Headline', 'One line per row. &lt;em&gt;…&lt;/em&gt; uses the accent colour; &lt;strong&gt;…&lt;/strong&gt; uses the second accent.', 3 ),
			self::area( 'cic_hero_intro', 'Introduction', '', 2 ),
			self::text( 'cic_hero_cta', 'Quote button label', 'Opens the shared quote flow with this occasion selected.' ),
			self::text( 'cic_hero_link_label', 'Treats link label' ),
		);
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::image( "cic_hero_image_{$i}", "Hero photo {$i}", 1 === $i ? 'Birthday: left of the collage. Wedding: full-width background. Anniversary/Corporate: main photo.' : ( 2 === $i ? 'Birthday: centre (largest). Anniversary/Corporate: framed inset.' : 'Birthday: right.' ) );
		}
		$fields[] = self::image( 'cic_ev_card_image', 'Events overview card photo (optional)', 'Shown on /events/. Leave empty to use the featured image.' );
		$fields[] = self::tab( 'event_ribbon', 'Ribbon and values' );
		$fields[] = self::area( 'cic_ev_ribbon', 'Ribbon phrases (Wedding)', 'One phrase per line, e.g. Favourite treats / Thoughtful service / A moment to savour.', 3 );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_ev_value_{$i}_icon", "Value {$i}: icon (Anniversary, Corporate)" );
			$fields[] = self::text( "cic_ev_value_{$i}_title", "Value {$i}: title" );
			$fields[] = self::text( "cic_ev_value_{$i}_text", "Value {$i}: text" );
		}
		$fields[] = self::tab( 'event_feature', 'Story with photo' );
		$fields[] = self::text( 'cic_ev_feature_eyebrow', 'Eyebrow' );
		$fields[] = self::area( 'cic_ev_feature_heading', 'Heading', 'One line per row; &lt;em&gt;…&lt;/em&gt; for accent words.', 2 );
		$fields[] = self::area( 'cic_ev_feature_text', 'Text', '', 3 );
		$fields[] = self::image( 'cic_ev_feature_image', 'Photo' );
		$fields[] = self::image( 'cic_ev_feature_image_2', 'Inset photo (Wedding)' );
		for ( $i = 1; $i <= 3; $i++ ) {
			$fields[] = self::icon( "cic_ev_feature_{$i}_icon", "Point {$i}: icon" );
			$fields[] = self::text( "cic_ev_feature_{$i}_title", "Point {$i}: title" );
			$fields[] = self::text( "cic_ev_feature_{$i}_text", "Point {$i}: text (optional)" );
		}
		$fields[] = self::tab( 'event_gallery', 'Wide photo band' );
		$fields[] = self::image( 'cic_ev_gallery_image', 'Wide photo (Wedding)' );
		$fields[] = self::text( 'cic_ev_gallery_caption', 'Caption line under the photo' );
		$fields[] = self::tab( 'event_starter', 'Quote starter strip' );
		$fields[] = self::text( 'cic_starter_label_location', 'Location label' );
		$fields[] = self::text( 'cic_starter_label_date', 'Date label' );
		$fields[] = self::text( 'cic_starter_label_guests', 'Guests label' );
		$fields[] = self::text( 'cic_starter_cta', 'Button label' );
		$fields[] = self::tab( 'event_treats', 'Featured treats' );
		$fields[] = self::text( 'cic_ev_treats_eyebrow', 'Eyebrow (optional)' );
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
