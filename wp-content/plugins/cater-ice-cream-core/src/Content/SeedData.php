<?php
/**
 * Initial catalogue identities and starting copy (BUILD_SPECIFICATION §7–8, DATA_CONTRACTS content_seeds).
 *
 * Dippin' Dots and Birthday are fully composed from their approved mockups (09, 04) for the
 * vertical slice. The other six records carry identity and minimal copy until Phase 07.
 * Copy corrections applied: no prices, no minimum-order text, no supplier trademarks symbols,
 * no Austin/TX sample values. Image values are manifest asset IDs ("asset:…").
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class SeedData {

	public const VERSION = 1;

	/** Manifest pages whose masters this phase imports. */
	public const MEDIA_PAGES = array( 'dippin-dots', 'birthday' );

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function products(): array {
		return array(
			'dippin-dots'       => array(
				'title'      => "Dippin' Dots",
				'slug'       => 'dippin-dots',
				'order'      => 10,
				'excerpt'    => 'Little beads. Big smiles. Tiny flash-frozen beads of ice cream that bring a colourful, unexpected twist to the dessert moment.',
				'content'    => "Dippin' Dots is ice cream in tiny, flash-frozen beads: a distinctive texture that gives this familiar frozen treat its character. Ask us about the flavours and serving arrangements available for your event.",
				'image'      => 'asset:treat-dots-cup',
				'gallery'    => array( 'asset:dots-detail-macro' ),
				'brand_type' => 'brand',
				'brand'      => "Dippin' Dots",
				'preset'     => 'dots',
				'meta'       => array(
					'cic_hero_eyebrow'      => "Dippin' Dots event catering",
					'cic_hero_headline'     => "Little dots.\nBig <em>party energy.</em>",
					'cic_hero_intro'        => "A little colour, a little curiosity and, for some guests, a happy memory. Dippin' Dots brings an unexpected twist to the dessert moment.",
					'cic_hero_cta'          => "Bring Dippin' Dots to my event",
					'cic_hero_jump'         => 'Find your occasion',
					'cic_hero_image'        => 'asset:dots-hero',
					'cic_chip_1_icon'       => 'sun',
					'cic_chip_1_label'      => 'Playful',
					'cic_chip_2_icon'       => 'heart',
					'cic_chip_2_label'      => 'Nostalgic',
					'cic_chip_3_icon'       => 'star',
					'cic_chip_3_label'      => 'Unexpected',
					'cic_feel_heading'      => 'More than dessert. <em>A little shared delight.</em>',
					'cic_feel_1_icon'       => 'sparkle',
					'cic_feel_1_title'      => 'A little wonder',
					'cic_feel_1_text'       => 'The tiny beads look different from a traditional scoop, inviting guests to discover something fun.',
					'cic_feel_2_icon'       => 'heart',
					'cic_feel_2_title'      => 'A familiar memory',
					'cic_feel_2_text'       => "For guests who grew up with Dippin' Dots, the first spoonful can bring back a favourite day out.",
					'cic_feel_3_icon'       => 'people',
					'cic_feel_3_title'      => 'A reason to gather',
					'cic_feel_3_text'       => 'A shared treat gives people a relaxed moment to pause, smile and enjoy the occasion together.',
					'cic_feel_note'         => 'A feeling to explore, not a rule for your event.',
					'cic_occ_heading'       => 'What could it bring to your celebration?',
					'cic_theme_eyebrow'     => 'Find your feeling',
					'cic_theme_heading'     => 'Match the mood, <em>not just the menu.</em>',
					'cic_theme_1_title'     => 'Colourful & playful',
					'cic_theme_1_text'      => 'Think bright invitations, happy table details and a treat that joins in the fun.',
					'cic_theme_1_image'     => 'asset:dots-theme-playful',
					'cic_theme_1_palette'   => array( 'action_primary', 'strawberry', 'birthday_sky' ),
					'cic_theme_2_title'     => 'Retro & nostalgic',
					'cic_theme_2_text'      => 'A familiar favourite for a celebration inspired by shared memories and favourite days out.',
					'cic_theme_2_image'     => 'asset:dots-theme-retro',
					'cic_theme_2_palette'   => array( 'brand_raspberry', 'treat_blush', 'birthday_mint' ),
					'cic_theme_3_title'     => 'Elegant, with a playful twist',
					'cic_theme_3_text'      => 'Keep the setting refined and let the dessert be the unexpected detail.',
					'cic_theme_3_image'     => 'asset:dots-theme-elegant',
					'cic_theme_3_palette'   => array( 'lavender', 'wedding_blush', 'birthday_mint' ),
					'cic_theme_note'        => 'Theme ideas only. Decor, packaging and product options are discussed separately.',
					'cic_detail_heading'    => 'And in every spoonful?',
					'cic_detail_image'      => 'asset:dots-detail-macro',
					'cic_point_1_icon'      => 'beads',
					'cic_point_1_label'     => 'Tiny frozen beads',
					'cic_point_2_icon'      => 'cone',
					'cic_point_2_label'     => 'Flavours confirmed with your quote',
					'cic_point_3_icon'      => 'person',
					'cic_point_3_label'     => 'Staffed event service',
					'cic_ingredient_note'   => 'Tell us about dietary requirements so we can review current product ingredients with you.',
					'cic_quote_heading'     => 'What kind of moment are you <em>planning?</em>',
					'cic_quote_intro'       => "Tell us about your occasion, your theme and the people you're bringing together.",
					'cic_quote_cta'         => "Request a Dippin' Dots quote",
					'cic_quote_note'        => 'Availability, flavours and serving details confirmed with your quote.',
				),
				'pairings'   => array(
					'birthday'    => array( 'headline' => 'A colourful little moment of wonder.', 'description' => 'For younger guests, the tiny beads can feel like a discovery. At a milestone birthday, they can bring a playful nod to childhood favourites.', 'image' => 'asset:dots-family', 'link_label' => 'Explore birthday catering', 'product_sort' => 10, 'event_sort' => 10 ),
					'wedding'     => array( 'headline' => 'An unexpected break from tradition.', 'description' => 'A playful dessert moment can sit beautifully alongside an elegant celebration, giving guests something familiar to enjoy in a fresh setting.', 'image' => 'asset:dots-wedding', 'link_label' => 'Explore wedding catering', 'product_sort' => 20, 'event_sort' => 10 ),
					'corporate'   => array( 'headline' => 'A relaxed way to break the ice.', 'description' => 'Step away from the agenda for a moment. A recognisable, playful treat can give colleagues and clients an easy shared experience.', 'image' => 'asset:dots-corporate', 'link_label' => 'Explore corporate catering', 'product_sort' => 30, 'event_sort' => 10 ),
					'anniversary' => array( 'headline' => 'A sweet connection to shared memories.', 'description' => 'For a couple who loves this treat, it can add a personal, light-hearted detail to a celebration of the years together.', 'image' => 'asset:dots-anniversary', 'link_label' => 'Explore anniversary catering', 'product_sort' => 40, 'event_sort' => 10 ),
				),
			),
			'dipn-bananas'      => array(
				'title'      => "DipN' Bananas",
				'slug'       => 'dipn-bananas',
				'order'      => 20,
				'excerpt'    => 'A delicious twist on dessert: familiar fruit with a chocolate finish.',
				'image'      => 'asset:treat-dipped-bananas',
				'brand_type' => 'brand',
				'brand'      => "DipN' Bananas",
				'preset'     => 'banana',
				'meta'       => array(
					'cic_hero_headline' => "A little indulgence.\n<em>A delicious twist.</em>",
				),
				'pairings'   => array(
					'birthday' => array( 'headline' => 'A playful twist on a birthday favourite.', 'description' => 'The familiar fruit and chocolate combination adds discovery without needing an elaborate dessert ritual.', 'link_label' => 'Explore birthdays', 'product_sort' => 10, 'event_sort' => 20 ),
				),
			),
			'classic-ice-cream' => array(
				'title'      => 'Classic Ice Cream',
				'slug'       => 'classic-ice-cream',
				'order'      => 30,
				'excerpt'    => 'Familiar scoops for shared memories. Flavours are discussed with your quote.',
				'image'      => 'asset:treat-classic-scoops',
				'brand_type' => 'generic',
				'preset'     => 'classic',
				'meta'       => array(),
				'pairings'   => array(
					'birthday' => array( 'headline' => 'A familiar favourite for every age.', 'description' => 'Classic scoops keep the dessert moment easy and familiar for guests of every age.', 'link_label' => 'Explore birthdays', 'product_sort' => 10, 'event_sort' => 30 ),
				),
			),
			'frozen-yogurt'     => array(
				'title'      => 'Frozen Yogurt',
				'slug'       => 'frozen-yogurt',
				'order'      => 40,
				'excerpt'    => 'A bright, relaxed dessert moment with a smooth texture and a recognisable tang.',
				'image'      => 'asset:treat-frozen-yogurt',
				'brand_type' => 'generic',
				'preset'     => 'yogurt',
				'meta'       => array(
					'cic_hero_headline' => "A bright little\npause <em>in your\ncelebration.</em>",
				),
				'pairings'   => array(
					'birthday' => array( 'headline' => 'A bright change of pace.', 'description' => 'For a birthday host who loves frozen yogurt, its distinctive taste can make the dessert choice feel personal and a little different.', 'link_label' => 'Explore birthdays', 'product_sort' => 10, 'event_sort' => 40 ),
				),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function events(): array {
		return array(
			'birthday'    => array(
				'title'   => 'Birthdays',
				'slug'    => 'birthday',
				'order'   => 10,
				'excerpt' => 'Sweet treats for their special day, from little celebrations to big milestones.',
				'image'   => 'asset:birthday-child',
				'preset'  => 'birthday',
				'meta'    => array(
					'cic_layout'                 => 'birthday',
					'cic_hero_eyebrow'           => 'Birthday ice cream catering',
					'cic_hero_headline'          => "More candles.\n<em>More smiles.</em>\n<strong>More ice cream.</strong>",
					'cic_hero_intro'             => 'Birthday ice cream catering for little celebrations, big milestones and everyone in between.',
					'cic_hero_cta'               => 'Plan a birthday',
					'cic_hero_link_label'        => 'Explore the treats',
					'cic_hero_image_1'           => 'asset:birthday-adult-friends',
					'cic_hero_image_2'           => 'asset:birthday-father-daughter',
					'cic_hero_image_3'           => 'asset:quote-treat-assortment',
					'cic_starter_label_location' => 'Party location',
					'cic_starter_label_date'     => 'Birthday date',
					'cic_starter_label_guests'   => 'Guests',
					'cic_starter_cta'            => 'Start my quote',
					'cic_treats_heading'         => 'Pick your party <em>favourites.</em>',
					'cic_treats_intro'           => 'Big-name treats and classic frozen favourites.',
					'cic_treats_more_label'      => 'More Frozen Treats',
					'cic_treats_more_image'      => 'asset:treat-more-frozen',
					'cic_story_heading'          => 'Every age deserves a <em>sweet celebration.</em>',
					'cic_story_1_title'          => "Little birthdays.\n<em>Big wonder.</em>",
					'cic_story_1_text'           => 'From first birthdays to school celebrations, share their favourite treats with the people they love.',
					'cic_story_1_image'          => 'asset:birthday-child',
					'cic_story_2_title'          => "Milestone birthdays.\n<em>Sweet memories.</em>",
					'cic_story_2_text'           => 'From big milestones to simple get-togethers, bring people together with delicious treats and great company.',
					'cic_story_2_image'          => 'asset:birthday-milestone-friends',
					'cic_plan_heading'           => "You enjoy the party.\n<em>We'll talk treats.</em>",
					'cic_plan_intro'             => "Share your date, location and guest count. We'll help you explore the treats and staffed service for your celebration.",
					'cic_plan_image'             => 'asset:service-garden',
					'cic_plan_1_title'           => 'Tell us your plans',
					'cic_plan_1_text'            => 'Share your date, location and guest count.',
					'cic_plan_2_title'           => 'Explore your favourites',
					'cic_plan_2_text'            => 'Discover our ice cream, frozen yogurt and more treats.',
					'cic_plan_3_title'           => 'Receive your quote',
					'cic_plan_3_text'            => "We'll confirm availability and serving details with you.",
					'cic_quote_heading'          => "Let's make their birthday <em>a little sweeter.</em>",
					'cic_quote_cta'              => 'Request a quote',
					'cic_faq_heading'            => 'Party planning questions',
					'cic_faq_1_q'                => 'Can we choose our treats?',
					'cic_faq_1_a'                => 'Yes. Tell us which treats you are interested in, or choose "Not sure yet", and we will talk through the options for your party.',
					'cic_faq_2_q'                => 'Do you serve our party location?',
					'cic_faq_2_a'                => 'We serve selected ZIP codes across Los Angeles, Ventura and Santa Barbara counties. Check your service area or include your city or ZIP code in your quote request.',
					'cic_faq_3_q'                => 'How far ahead should we enquire?',
					'cic_faq_3_a'                => 'As early as you can. Availability is confirmed with your quote, and a date is only held once we have confirmed it with you.',
					'cic_area_link_label'        => 'Check your service area',
				),
			),
			'wedding'     => array(
				'title'   => 'Weddings',
				'slug'    => 'wedding',
				'order'   => 20,
				'excerpt' => 'A memorable treat for your big day.',
				'preset'  => 'wedding',
				'meta'    => array(
					'cic_layout'        => 'wedding',
					'cic_hero_eyebrow'  => 'Wedding ice cream catering',
					'cic_hero_headline' => "A love story,\nwith a <em>sweeter</em>\nending.",
				),
			),
			'corporate'   => array(
				'title'   => 'Corporate Events',
				'slug'    => 'corporate',
				'order'   => 30,
				'excerpt' => 'A refreshing way to bring people together.',
				'preset'  => 'corporate',
				'meta'    => array(
					'cic_layout'        => 'corporate',
					'cic_hero_eyebrow'  => 'Corporate ice cream catering',
					'cic_hero_headline' => "Good teams deserve\n<em>a sweet moment.</em>",
				),
			),
			'anniversary' => array(
				'title'   => 'Anniversaries',
				'slug'    => 'anniversary',
				'order'   => 40,
				'excerpt' => 'Celebrate your story with favourite frozen treats.',
				'preset'  => 'anniversary',
				'meta'    => array(
					'cic_layout'        => 'anniversary',
					'cic_hero_eyebrow'  => 'Anniversary ice cream catering',
					'cic_hero_headline' => "Another year.\n<em>Another sweet\nmemory.</em>",
				),
			),
		);
	}
}
