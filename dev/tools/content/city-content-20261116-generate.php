<?php
/**
 * City landing-page content generator (2026-11-16). Builds a per-city brief and field copy for every
 * eligible city (production, published, Index, served) that has no content record yet, and writes:
 *   - dev/tools/content/data/city-content-20261116.json  (applied by city-content-20261116.php)
 *   - docs/content/city-seo/briefs.csv                   (one brief per city, for review)
 *
 * Evidence used per city, all from the location tables: county, number of served ZIP codes, other
 * published cities that share its ZIP codes (the coverage check needs the venue city there), nearby
 * cities in the same county, and a short list of places with special logistics (island, mountain
 * summit, military base) that get a careful planning note and are reported for owner confirmation.
 * No other local facts are claimed (no venues, customers, travel times, prices or availability).
 *
 * Copy comes from hand-written planning angles (home party, booked venue, reception schedule,
 * workplace, school, choosing treats, outdoor timing, larger guest list, mixed ages, first-time
 * organizer). Angles are spread so neighboring cities in a county get different ones; every page
 * also gets its own treat and occasion emphasis, coverage answer and FAQ set.
 *
 * Usage: wp eval-file dev/tools/content/city-content-20261116-generate.php
 * Read-only for the database; writes only the two files above. Deterministic (same input → same output).
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\CityStore;
use CIC\Locations\DatabaseRepository;

$cic_root = dirname( __DIR__, 3 );
$cic_json = $cic_root . '/dev/tools/content/data/city-content-20261116.json';
$cic_csv  = $cic_root . '/docs/content/city-seo/briefs.csv';
$cic_site = ' | ' . get_bloginfo( 'name' );

/* ------------------------------------------------------------------ *
 * Library. Placeholders: {city} {county} {partners} {treat}
 * ------------------------------------------------------------------ */

$cic_treats = array(
	'dippin-dots'       => array(
		'name'  => 'Dippin’ Dots',
		'lines' => array(
			'Dippin’ Dots, the tiny beads of ice cream, are a fun pick when kids are on the guest list.',
			'Dippin’ Dots bring a playful, colorful moment that guests of every age notice.',
			'If you want something guests don’t see every day, Dippin’ Dots are an easy conversation starter.',
		),
		'faq'   => array(
			array( 'Why choose Dippin’ Dots for a {city} event?', 'They’re a little different from a regular scoop: tiny beads of ice cream that kids and adults both enjoy. You can pair them with another treat if you want a classic option too.' ),
			array( 'Can we serve Dippin’ Dots alongside other treats?', 'Yes. Many organizers pair Dippin’ Dots with classic ice cream or frozen yogurt so every guest finds a favorite. Choose them together in your quote request.' ),
		),
	),
	'dipn-bananas'      => array(
		'name'  => 'DipN’ Bananas',
		'lines' => array(
			'DipN’ Bananas, chocolate-coated frozen bananas, are a fun twist when you want something besides a scoop.',
			'DipN’ Bananas add a playful, hand-held treat that works well while guests mingle.',
			'For a dessert guests can carry while they talk, chocolate-coated DipN’ Bananas are a good fit.',
		),
		'faq'   => array(
			array( 'What are DipN’ Bananas?', 'Frozen bananas with a chocolate coating, served by our team. They’re easy to hold, which helps when guests are standing and mingling.' ),
			array( 'Are DipN’ Bananas a good fit for an adult crowd?', 'They often are: a hand-held treat that feels a little nostalgic. Many organizers offer them beside classic ice cream so guests can choose.' ),
		),
	),
	'classic-ice-cream' => array(
		'name'  => 'classic ice cream',
		'lines' => array(
			'Classic ice cream is the safe, familiar choice when your guest list spans every age.',
			'Classic, creamy ice cream suits nearly any crowd and pairs well with a second treat.',
			'When you’re not sure what everyone likes, classic ice cream is the treat few guests turn down.',
		),
		'faq'   => array(
			array( 'Is classic ice cream enough on its own?', 'For many events, yes. It suits every age. If you want a playful extra, add Dippin’ Dots or DipN’ Bananas in your quote request.' ),
			array( 'Which flavors are available?', 'Flavors and serving options are discussed with your quote, so tell us about your guests and any favorites when you get in touch.' ),
		),
	),
	'frozen-yogurt'     => array(
		'name'  => 'frozen yogurt',
		'lines' => array(
			'Frozen yogurt, smooth with a gentle tang, is a lighter option many adults appreciate.',
			'Frozen yogurt gives guests a lighter choice beside richer treats.',
			'If your crowd leans toward lighter desserts, frozen yogurt is worth adding to the menu.',
		),
		'faq'   => array(
			array( 'Should we offer frozen yogurt as well as ice cream?', 'It’s a nice option when guests like a lighter dessert. Many organizers offer it beside classic ice cream so everyone has a choice.' ),
			array( 'Is frozen yogurt good for an afternoon event?', 'It’s a refreshing, lighter choice for daytime gatherings. Tell us your timing and we’ll suggest how to pair it with other treats.' ),
		),
	),
);

$cic_service = array(
	'Our team arrives with freezer equipment, sets up the serving station and serves every guest.',
	'We bring the freezer equipment and the staff, so the treats stay frozen and you don’t have to scoop.',
	'Our staff handle setup and serving with freezer equipment that keeps everything frozen until it’s served.',
	'You choose the treats; our team brings the freezer equipment and serves your guests.',
	'From setup to the last serving, our staff look after the dessert while you look after your guests.',
);

$cic_occasion_sets = array(
	array( 'birthday', 'graduation' ),
	array( 'wedding', 'anniversary' ),
	array( 'corporate', 'birthday' ),
	array( 'graduation', 'prom' ),
	array( 'anniversary', 'birthday' ),
	array( 'prom', 'graduation' ),
	array( 'birthday', 'wedding' ),
	array( 'corporate', 'graduation' ),
);
$cic_occasion_names = array(
	'birthday'    => 'birthday party',
	'wedding'     => 'wedding reception',
	'graduation'  => 'graduation party',
	'prom'        => 'prom',
	'corporate'   => 'company event',
	'anniversary' => 'anniversary celebration',
);
$cic_occasion_plural = array(
	'birthday'    => 'birthdays',
	'wedding'     => 'weddings',
	'graduation'  => 'graduation parties',
	'prom'        => 'proms',
	'corporate'   => 'corporate events',
	'anniversary' => 'anniversaries',
);
// Event keys → existing event pages (internal links recommended in the brief).
$cic_event_paths = array(
	'birthday'    => '/events/birthday-party-ice-cream-catering/',
	'wedding'     => '/events/wedding-ice-cream-catering/',
	'graduation'  => '/events/graduation-party-ice-cream-catering/',
	'prom'        => '/events/prom-ice-cream-catering/',
	'corporate'   => '/events/corporate-event-ice-cream-catering/',
	'anniversary' => '/events/anniversary-ice-cream-catering/',
);
$cic_treat_paths = array(
	'dippin-dots'       => '/treats/dippin-dots-catering/',
	'dipn-bananas'      => '/treats/dipn-bananas-catering/',
	'classic-ice-cream' => '/treats/classic-ice-cream-catering/',
	'frozen-yogurt'     => '/treats/frozen-yogurt-catering/',
);

$cic_angles = array(
	'home'      => array(
		'label'    => 'Dessert service at a private home or backyard',
		'terms'    => 'backyard party, ice cream party, home celebration',
		'h1'       => array( 'Ice cream catering for <em>{city}</em> backyard parties.', 'A staffed ice cream party at your <em>{city}</em> home.' ),
		'subtitle' => array( 'Treats served in your own backyard, without the scooping.', 'Bring the ice cream party home and let our team serve.' ),
		'intro'    => array( 'Hosting at home in {city} means you already know the space, so dessert should be the easy part.', 'A party at your {city} home feels relaxed, and a staffed ice cream station keeps it that way.' ),
		'plans_h'  => array( 'Plan a home party in {city} that runs itself.', 'Your {city} backyard, ready for dessert.' ),
		'plans'    => array( 'Tell us where in the yard or home we can set up, how guests will reach the serving spot and roughly when you want dessert. We’ll suggest treats for your crowd and plan the timing.', 'Share the guest count, the spot you have in mind and when the party starts. We’ll plan where the station goes and when to serve, so you can stay with your guests.' ),
		'call_h'   => array( "Hosting at home in {city}?\nLet’s plan the dessert.", "A backyard party in {city}?\nWe’ll handle the treats." ),
		'call'     => array( 'Share your date, guest count and where we can set up. A flat, shaded spot near the party works well; tell us about steps, gates or narrow paths so we can plan arrival.', 'Tell us about the setup spot, parking near the house and your party timeline. We’ll plan arrival and serving so dessert fits naturally into the day.' ),
		'faqs'     => array(
			array( 'What space do you need at a home party?', 'A level spot near your guests, ideally with some shade. Tell us about the space, gates and steps when you request a quote and we’ll plan the setup.' ),
			array( 'Do we need to clear our freezer?', 'No. Our team brings the freezer equipment and serves the treats, so your kitchen stays free for everything else.' ),
			array( 'When should dessert be served at a backyard party?', 'Many hosts serve after food or right after the cake. Share your timeline and we’ll suggest a serving window that fits.' ),
		),
		'seo_t'    => array( 'Backyard Party Ice Cream Catering in {city}', '{city} Ice Cream Catering for Home Parties', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Staffed ice cream catering for backyard parties and home celebrations in {city}, CA. {treatA} and more, served by our team. Check your ZIP and get a quote.', 'Planning a party at home in {city}, CA? Our staff serve {treatA}, classic ice cream and more right in your backyard. Check your ZIP and request a quote.' ),
	),
	'venue'     => array(
		'label'    => 'Dessert service at a booked venue or hall',
		'terms'    => 'event venue, banquet hall, ice cream station',
		'h1'       => array( 'Ice cream catering at <em>{city}</em> event venues.', 'An ice cream station for your <em>{city}</em> venue.' ),
		'subtitle' => array( 'Dessert that fits your venue’s rules and schedule.', 'Staffed treats planned around your venue’s timeline.' ),
		'intro'    => array( 'Booked a hall, restaurant room or event space in {city}? Venues often have arrival windows and setup rules, and dessert service should fit them.', 'When your {city} event is at a booked venue, the details matter: load-in times, where the station can go and when the room must be cleared.' ),
		'plans_h'  => array( 'Dessert planned around your {city} venue.', 'Your venue’s rules, our ice cream station.' ),
		'plans'    => array( 'Send us the venue name, your event hours and any rules the venue has shared about outside vendors. We’ll plan arrival, setup and serving within them.', 'Tell us the venue, the room and your schedule. If the venue has a coordinator, we’re happy to plan load-in and setup space around their guidance.' ),
		'call_h'   => array( "Booked a venue in {city}?\nLet’s plan the treats.", "Event at a {city} venue?\nWe’ll fit your schedule." ),
		'call'     => array( 'Share the venue, date and guest count. Ask your venue about vendor arrival times, loading access and where a serving station can go, and pass those details on.', 'Tell us the venue and its vendor rules. Load-in times, elevator or loading access and the serving spot help us plan a smooth setup.' ),
		'faqs'     => array(
			array( 'What should we ask our {city} venue before booking dessert service?', 'Ask about vendor arrival times, loading access, where a serving station can go and whether outside dessert vendors are allowed. Share the answers with your quote.' ),
			array( 'Can you work with our venue coordinator?', 'Yes. Share the coordinator’s timeline and rules, and we’ll plan arrival, setup and serving to fit.' ),
			array( 'How much space does an ice cream station need?', 'It depends on the treats and guest count. Tell us the room and the spot you have in mind, and we’ll confirm the setup with your quote.' ),
		),
		'seo_t'    => array( '{city} Ice Cream Catering for Event Venues', 'Ice Cream Station Catering in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Ice cream catering for event venues and halls in {city}, CA: a staffed ice cream station planned around your venue’s rules. Check your ZIP and get a quote.', 'Booked a venue in {city}, CA? Our staffed ice cream station fits your schedule, with {treatA} and more. Check your ZIP code and request a quote.' ),
	),
	'reception' => array(
		'label'    => 'Dessert timed around a reception schedule',
		'terms'    => 'wedding dessert bar, reception dessert, anniversary party',
		'h1'       => array( 'Wedding and reception ice cream in <em>{city}.</em>', 'A dessert bar for <em>{city}</em> receptions.' ),
		'subtitle' => array( 'Dessert timed to your reception, served by our team.', 'A staffed dessert bar that fits your celebration’s timeline.' ),
		'intro'    => array( 'A reception in {city} has its own rhythm: dinner, toasts, the first dance and then dessert. A staffed ice cream dessert bar can sit beside the cake or replace it.', 'For a wedding or anniversary reception in {city}, dessert works best when it’s timed to the moments that matter, not rushed between them.' ),
		'plans_h'  => array( 'Your {city} reception, with dessert on cue.', 'Plan dessert around your {city} reception timeline.' ),
		'plans'    => array( 'Share your reception timeline, from guest arrival to send-off, and tell us whether ice cream joins the cake or replaces it. We’ll suggest when to open the dessert bar.', 'Tell us about the venue, the guest count and the order of the evening. We’ll plan setup before guests arrive and serving at the moment you choose.' ),
		'call_h'   => array( "Planning a reception in {city}?\nLet’s time the dessert.", "Celebrating in {city}?\nLet’s plan your dessert bar." ),
		'call'     => array( 'Share your date, venue and reception timeline. Your planner or venue coordinator’s schedule helps us plan quiet setup and serving on cue.', 'Tell us the venue, guest count and when you want dessert served. We’ll plan setup around the venue’s vendor rules.' ),
		'faqs'     => array(
			array( 'Can ice cream replace a wedding cake?', 'It can, or it can sit beside the cake. Many couples open an ice cream dessert bar after the cake cutting. Tell us your plan and we’ll suggest timing.' ),
			array( 'When should the dessert bar open at a reception?', 'Often after dinner and the cake cutting, when guests start dancing. Share your timeline and we’ll suggest a serving window.' ),
			array( 'Is an ice cream bar a good fit for an anniversary party?', 'Yes. It suits mixed-age guests and relaxed celebrations. Tell us about the guest list and we’ll suggest treats.' ),
		),
		'seo_t'    => array( 'Wedding Ice Cream Catering in {city}', '{city} Wedding & Reception Ice Cream', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Wedding and reception ice cream catering in {city}, CA. A staffed dessert bar timed to your celebration, with {treatA} and more. Check your ZIP and get a quote.', 'Plan a reception dessert bar in {city}, CA: staffed ice cream catering for weddings and anniversaries, timed to your schedule. Request a quote.' ),
	),
	'office'    => array(
		'label'    => 'Coordinating an office or workplace gathering',
		'terms'    => 'ice cream social, employee appreciation, corporate ice cream catering',
		'h1'       => array( 'Ice cream socials for <em>{city}</em> workplaces.', 'Corporate ice cream catering in <em>{city}.</em>' ),
		'subtitle' => array( 'A staffed treat break your team will look forward to.', 'Employee appreciation, served by our team.' ),
		'intro'    => array( 'An ice cream social is a simple way to thank a {city} team: everyone steps away from their desks for a few minutes and our staff do the serving.', 'For a workplace in {city}, an ice cream break fits into the day without a big production, whether it’s a team milestone or a client gathering.' ),
		'plans_h'  => array( 'An ice cream social that fits the {city} workday.', 'Plan a team treat break in {city}.' ),
		'plans'    => array( 'Tell us the headcount, the room or outdoor area we can use and the time window that works for your team. We’ll plan serving so people can drop by between meetings.', 'Share your building’s check-in steps, the space and the schedule. If people work in shifts, tell us the break times and we’ll plan around them.' ),
		'call_h'   => array( "Treating a team in {city}?\nLet’s plan the social.", "A workplace event in {city}?\nLet’s talk treats." ),
		'call'     => array( 'Share your address, date and headcount. Building check-in, parking or loading access and the serving room help us arrive on time.', 'Tell us the space, the headcount and your time window. Security or reception check-in details help us plan arrival.' ),
		'faqs'     => array(
			array( 'What do you need to know about our {city} office?', 'The headcount, the room or outdoor area we’ll use, building check-in steps and parking or loading access. Share them with your quote.' ),
			array( 'Can people drop by over a few hours?', 'Tell us the time window you have in mind. We’ll plan a serving window with you before confirming the quote.' ),
			array( 'Is an ice cream social a good employee appreciation idea?', 'It’s an easy one to organize: our staff serve, so managers can join their teams instead of handing out treats.' ),
		),
		'seo_t'    => array( '{city} Corporate Ice Cream Catering', 'Office Ice Cream Socials in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Corporate ice cream catering in {city}, CA: staffed ice cream socials for employee appreciation and team events. Check your ZIP and request a quote.', 'Plan an office ice cream social in {city}, CA. Our staff serve {treatA} and more for your team. Check your ZIP code and get a quote.' ),
	),
	'school'    => array(
		'label'    => 'Coordinating a school celebration, prom or graduation',
		'terms'    => 'graduation party dessert, prom dessert, school celebration',
		'h1'       => array( 'Graduation and prom ice cream in <em>{city}.</em>', 'Ice cream catering for <em>{city}</em> school celebrations.' ),
		'subtitle' => array( 'Treats for grads, prom nights and school events.', 'A staffed dessert stop for students, families and staff.' ),
		'intro'    => array( 'Graduation parties, proms and end-of-year celebrations in {city} bring big, happy crowds, and dessert is where everyone gathers.', 'Whether it’s a graduation party at home or a school dance, a staffed ice cream stop gives {city} students and families something to celebrate with.' ),
		'plans_h'  => array( 'Celebrate {city} grads and prom nights.', 'Plan a school celebration in {city}.' ),
		'plans'    => array( 'For school events, tell us the check-in process, the event hours and where we can set up. For a graduation party at home, share the guest count and timeline.', 'Share the occasion, the number of guests and the schedule. Schools often have visitor rules; pass them on and we’ll plan around them.' ),
		'call_h'   => array( "Celebrating a grad in {city}?\nLet’s plan the treats.", "A school event in {city}?\nWe’ll plan the dessert." ),
		'call'     => array( 'Share your date, venue and guest count. For campuses, let us know the visitor check-in steps and where a serving station can go.', 'Tell us whether it’s a school event or a family party, how many guests to expect and the timing. We’ll suggest treats for the crowd.' ),
		'faqs'     => array(
			array( 'Is ice cream a good graduation party dessert?', 'Yes. A staffed ice cream station suits guests of every age and frees you to celebrate with your graduate.' ),
			array( 'What do schools usually need from dessert vendors?', 'Often a visitor check-in, an arrival time and a set serving area. Ask the school and share the details with your quote.' ),
			array( 'Can you serve at a prom or school dance?', 'Yes. Share the event hours, the expected number of students and the school’s rules, and we’ll plan serving with you.' ),
		),
		'seo_t'    => array( '{city} Graduation & Prom Ice Cream', 'Graduation Party Ice Cream in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Ice cream catering for graduation parties, proms and school events in {city}, CA. Staffed service with {treatA} and more. Check your ZIP and get a quote.', 'Celebrate grads and prom night in {city}, CA with staffed ice cream catering. Four frozen treats, served by our team. Request a quote.' ),
	),
	'compare'   => array(
		'label'    => 'Helping an organizer compare treat experiences',
		'terms'    => 'Dippin’ Dots catering, frozen yogurt catering, dessert options',
		'h1'       => array( 'Choose your treats for a <em>{city}</em> event.', 'Ice cream catering in <em>{city}</em>, four ways.' ),
		'subtitle' => array( 'Dippin’ Dots, DipN’ Bananas, ice cream or frozen yogurt: pick one or mix.', 'Four frozen treats, served by our team. Which suits your guests?' ),
		'intro'    => array( 'Not sure which dessert fits your {city} event? Each of our four treats creates a different moment, and you can mix them.', 'Planning dessert for a {city} celebration starts with your guests: who’s coming and what they’ll enjoy most.' ),
		'plans_h'  => array( 'Find the right treat mix for {city}.', 'Match the treats to your {city} guests.' ),
		'plans'    => array( 'Tell us who’s coming, from kids to grandparents, and the feel you want. We’ll recommend one treat or a mix, or you can choose Not sure yet.', 'Share the occasion and guest count. A playful treat, a classic one, or both? We’ll help you decide before you book.' ),
		'call_h'   => array( "Choosing treats in {city}?\nWe’ll help you decide.", "Not sure which treat?\nLet’s talk it through." ),
		'call'     => array( 'Tell us your guest count and the mood of the event. We’ll suggest a treat mix that suits everyone, then confirm the details with your quote.', 'Share your date, venue and guests. You can choose treats now or leave it to the conversation with our team.' ),
		'faqs'     => array(
			array( 'Which treat is best for a mixed crowd?', 'Classic ice cream suits every age; Dippin’ Dots and DipN’ Bananas add a playful moment; frozen yogurt is a lighter choice. Many organizers pick two.' ),
			array( 'Can we choose more than one treat?', 'Yes. Pick any of our four treats in your quote request, or choose Not sure yet and we’ll recommend options.' ),
			array( 'What if we don’t know yet?', 'Choose Not sure yet in your quote request. Tell us about your guests and we’ll suggest treats that fit.' ),
		),
		'seo_t'    => array( '{city} Ice Cream & Dessert Catering', 'Dippin’ Dots & Ice Cream Catering in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Compare Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt for your {city}, CA event. Staffed catering. Check your ZIP and get a quote.', 'Dessert catering in {city}, CA: four frozen treats, served by our team. Mix and match for your guests. Check your ZIP code and request a quote.' ),
	),
	'outdoor'   => array(
		'label'    => 'Outdoor events: shade, sun and timing',
		'terms'    => 'outdoor party dessert, park party, summer event',
		'h1'       => array( 'Ice cream catering for <em>{city}</em> outdoor events.', 'Frozen treats for <em>{city}</em> parties in the sun.' ),
		'subtitle' => array( 'Outdoor celebrations, planned around shade and timing.', 'Staffed treats for gardens, parks and patios.' ),
		'intro'    => array( 'Outdoor events in {city} are made for frozen treats, and a little planning around shade and timing keeps dessert at its best.', 'A garden, patio or park celebration in {city} is the perfect setting for ice cream, as long as the serving spot is planned with the sun in mind.' ),
		'plans_h'  => array( 'Outdoor plans in {city}, made for frozen treats.', 'Plan your {city} outdoor celebration with us.' ),
		'plans'    => array( 'Tell us whether the serving spot has shade, how far it is from parking and when you want dessert. We’ll plan setup and serving time around the weather.', 'Share the location, the time of day and the guest count. Afternoon sun changes how we plan, so let us know what shade is available.' ),
		'call_h'   => array( "Celebrating outdoors in {city}?\nLet’s plan around the sun.", "An outdoor party in {city}?\nLet’s talk shade and treats." ),
		'call'     => array( 'Share your date, location and guest count. Shade, a level surface and access from parking help us plan a smooth setup.', 'Tell us where the party is and when. If it’s a park, share any permit or vendor rules so we can plan within them.' ),
		'faqs'     => array(
			array( 'How do you plan for a hot afternoon?', 'Tell us whether the serving spot has shade and when you want dessert. We plan the station position and serving time with you before confirming.' ),
			array( 'Can you serve at a park in {city}?', 'Often, yes, within the park’s rules. Check whether the park needs a permit for vendors and share the details with your quote.' ),
			array( 'What surface does the station need outdoors?', 'A level, stable spot, ideally shaded and close to where guests gather. Describe the area and we’ll confirm the setup.' ),
		),
		'seo_t'    => array( 'Outdoor Party Ice Cream Catering in {city}', '{city} Ice Cream Catering for Outdoor Events', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Ice cream catering for outdoor parties in {city}, CA: gardens, patios and parks. Staffed service planned around shade and timing. Get a quote.', 'Planning an outdoor event in {city}, CA? Our staff serve {treatA} and more, with setup planned around the sun. Check your ZIP and request a quote.' ),
	),
	'crowd'     => array(
		'label'    => 'Larger guest lists and serving flow',
		'terms'    => 'large party catering, event dessert station, serving line',
		'h1'       => array( 'Ice cream catering for big <em>{city}</em> celebrations.', 'Treats for every guest at your <em>{city}</em> event.' ),
		'subtitle' => array( 'Staffed serving that keeps a big crowd moving.', 'Planned so every guest gets a treat, and the line keeps moving.' ),
		'intro'    => array( 'When the guest list in {city} is long, dessert needs a plan: where guests line up, when serving starts and how to keep things moving.', 'A large {city} celebration deserves a dessert station that can keep up, so guests spend their time celebrating, not waiting.' ),
		'plans_h'  => array( 'A dessert plan for a big {city} guest list.', 'Plan serving for your {city} crowd.' ),
		'plans'    => array( 'Tell us your expected guest count and how the event flows. We’ll suggest treats and a serving setup that keeps the line short.', 'Share the guest count, the venue layout and when dessert should start. We’ll plan the station position and timing around your crowd.' ),
		'call_h'   => array( "A big event in {city}?\nLet’s plan the serving.", "Hosting a crowd in {city}?\nWe’ll keep the line moving." ),
		'call'     => array( 'Share your date, venue and expected guest count. The layout and when guests arrive help us plan where the station goes.', 'Tell us how many guests you expect and how long the event runs. We’ll plan serving so everyone gets a treat.' ),
		'faqs'     => array(
			array( 'How do you serve a large guest list?', 'Tell us your guest count and the event schedule. We’ll plan the station setup and serving window so the line keeps moving.' ),
			array( 'Should dessert be served all at once or over time?', 'It depends on your event. Some organizers serve after a key moment; others open the station for a longer window. We’ll suggest what fits.' ),
			array( 'Does the treat choice affect serving speed?', 'It can. Tell us your guest count and we’ll suggest treats and a setup that suit a bigger crowd.' ),
		),
		'seo_t'    => array( '{city} Ice Cream Catering for Large Events', 'Event Ice Cream Catering in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Ice cream catering for large parties and events in {city}, CA. A staffed ice cream station that keeps the line moving. Check your ZIP and get a quote.', 'Hosting a big celebration in {city}, CA? Our staff serve {treatA} and more to every guest. Check your ZIP code and request a quote.' ),
	),
	'mixed'     => array(
		'label'    => 'Milestones with guests of every age',
		'terms'    => 'milestone birthday, family celebration, anniversary party',
		'h1'       => array( 'Ice cream catering for <em>{city}</em> milestones.', 'Treats for every generation in <em>{city}.</em>' ),
		'subtitle' => array( 'From grandkids to grandparents, a treat for everyone.', 'Milestone birthdays and family celebrations, served with care.' ),
		'intro'    => array( 'Milestone birthdays and family celebrations in {city} bring every generation together, so dessert should please the youngest guests and the oldest.', 'When a {city} celebration brings grandparents, parents and kids together, a staffed ice cream station gives everyone the same sweet moment.' ),
		'plans_h'  => array( 'Celebrate a {city} milestone with every generation.', 'Plan a family celebration in {city}.' ),
		'plans'    => array( 'Tell us who you’re celebrating, the age range of your guests and where you’re gathering. We’ll suggest a treat mix that works for everyone.', 'Share the occasion, guest count and setting. Some guests may prefer a lighter option, so we’ll help you choose a mix.' ),
		'call_h'   => array( "Celebrating a milestone in {city}?\nLet’s plan the treats.", "A family celebration in {city}?\nWe’ll help you choose." ),
		'call'     => array( 'Share your date, venue and guest count. Let us know if older guests will be seated so we can plan how treats reach them.', 'Tell us about the guest list and the timeline. We’ll suggest when to serve so the dessert moment brings everyone together.' ),
		'faqs'     => array(
			array( 'Which treats suit guests of all ages?', 'Classic ice cream suits nearly everyone, Dippin’ Dots are popular with kids and frozen yogurt is a lighter option. Many families choose two.' ),
			array( 'Can treats be brought to seated guests?', 'Tell us about your guests and seating when you request a quote, and we’ll discuss serving options with you.' ),
			array( 'Is ice cream a good fit for an anniversary party?', 'Yes. It suits relaxed, mixed-age celebrations. Share your timeline and we’ll suggest when to serve.' ),
		),
		'seo_t'    => array( '{city} Ice Cream Catering for Milestones', 'Family Party Ice Cream Catering in {city}', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'Ice cream catering in {city}, CA for milestone birthdays, anniversaries and family celebrations. Treats for every age, served by our team. Get a quote.', 'Bring every generation together in {city}, CA with staffed ice cream catering: {treatA}, classic ice cream and more. Request a quote.' ),
	),
	'first'     => array(
		'label'    => 'First-time organizer: what to know before requesting a quote',
		'terms'    => 'how to plan ice cream catering, event dessert planning',
		'h1'       => array( 'Planning ice cream catering in <em>{city}?</em>', 'Ice cream catering in <em>{city},</em> made simple.' ),
		'subtitle' => array( 'Here’s what to know before you request a quote.', 'A few details, and our team takes it from there.' ),
		'intro'    => array( 'If you’ve never booked dessert catering before, here’s the short version for {city}: share a few details, choose treats (or don’t yet) and our team takes care of serving.', 'Booking ice cream catering for a {city} event is simpler than it sounds. The details that matter most are your date, the event ZIP code and roughly how many guests are coming.' ),
		'plans_h'  => array( 'Three details to start your {city} plans.', 'Start planning your {city} event.' ),
		'plans'    => array( 'Start with your date, the event ZIP code and an estimated guest count. Venue details, start time and treats can follow; we’ll fill in the rest together.', 'You don’t need every detail. A date, a ZIP code and a rough guest count are enough to start, and we’ll help with the rest.' ),
		'call_h'   => array( "First time planning in {city}?\nWe’ll walk you through it.", "New to dessert catering?\nLet’s make it easy." ),
		'call'     => array( 'Share your date, ZIP code and guest count, even if they’re estimates. We’ll explain the options and confirm details with your quote.', 'Call or send a quote request with the basics. We’ll ask about the venue, timing and treats when we talk.' ),
		'faqs'     => array(
			array( 'What do I need to request a quote?', 'Your event date, the ZIP code and an estimated guest count. Venue details, start time and treats can be shared later.' ),
			array( 'Do I have to choose treats right away?', 'No. Choose Not sure yet and we’ll recommend treats after hearing about your guests.' ),
			array( 'What happens after I send a request?', 'Our team reviews your details, confirms availability for your ZIP code and date, and follows up with your quote.' ),
		),
		'seo_t'    => array( 'Ice Cream Catering in {city}: How It Works', '{city} Ice Cream Catering, Made Simple', '{city} Ice Cream Catering' ),
		'seo_d'    => array( 'How ice cream catering works in {city}, CA: share your date, ZIP code and guest count, and our staff serve the treats. Check your ZIP and get a quote.', 'Planning dessert in {city}, CA? Staffed ice cream catering with {treatA} and more. Start with your date and ZIP code, then request a quote.' ),
	),
);
$cic_angle_keys = array_keys( $cic_angles );

$cic_treats_h = array(
	'The treats you’ll serve in {city}.',
	'Four frozen treats for {city} celebrations.',
	'Pick your {city} favorites.',
	'Frozen treats, served in {city}.',
	'What’s on the menu for {city}?',
	'Treats your {city} guests will enjoy.',
	'Choose a treat, or two, for {city}.',
	'Our treats, brought to {city}.',
	'The dessert lineup for your {city} event.',
	'Frozen favorites for {city} guests.',
	'Something for every {city} guest.',
	'Four ways to serve dessert in {city}.',
);
$cic_occasions_h = array(
	'Find your {city} occasion.',
	'What are you celebrating in {city}?',
	'Occasions we cater in {city}.',
	'Pick the {city} celebration you’re planning.',
	'From birthdays to anniversaries in {city}.',
	'Every {city} occasion deserves dessert.',
	'Choose your occasion.',
	'Celebrations in {city}, big and small.',
	'Start with your {city} occasion.',
	'Your {city} event, your way.',
);
$cic_quote_h = array(
	'Tell us about your {city} event.',
	'Ready to plan dessert in {city}?',
	'Start your {city} quote.',
	'Let’s plan your {city} celebration.',
	'Request a quote for {city}.',
	'Your {city} event starts here.',
	'Get a quote for your {city} occasion.',
	'Share your {city} plans with us.',
);
$cic_quote_n = array(
	'Date, location and serving details are confirmed with your quote.',
	'Share what you know now; details can be confirmed later.',
	'Estimates are fine: we confirm the details with your quote.',
	'Your ZIP code, date and guest count are enough to start.',
	'Availability is confirmed for your exact ZIP code and date.',
	'Tell us the basics and we’ll take it from there.',
);
$cic_check_plain = array(
	'Enter your event ZIP code. Availability is confirmed for your exact ZIP code, date and venue.',
	'Start with your event ZIP code; our team confirms availability for your date and venue.',
	'Type your event ZIP code to check service for your {city} date and venue.',
	'Check your event ZIP code first, then share your date and venue details.',
);
$cic_check_shared = array(
	'Enter your event ZIP code. Some ZIP codes here are shared with {partners}, so choose {city} as the venue city if asked.',
	'Some {city} ZIP codes are shared with {partners}. Enter your ZIP code and, if asked, pick {city} as the venue city.',
);
$cic_note_plain = array(
	'Service is confirmed for your exact {city} ZIP code, event date and venue.',
	'Availability in {city} is confirmed by ZIP code, date and venue arrangements.',
	'We confirm service for your exact ZIP code and date before your quote.',
);
$cic_note_multi = array(
	'{city} covers several ZIP codes; service is confirmed for your exact ZIP code, date and venue.',
	'With several ZIP codes in {city}, availability is confirmed for your exact address area and date.',
);

/* Places that need a careful logistics note (and owner confirmation). Keyed by city name. */
$cic_special = array(
	'Avalon'                => array( 'island', 'Events on Santa Catalina Island involve ferry or other transport planning. Share your venue and date, and our team will confirm what’s possible before quoting.' ),
	'Santa Catalina Island' => array( 'island', 'Events on Santa Catalina Island involve ferry or other transport planning. Share your venue and date, and our team will confirm what’s possible before quoting.' ),
	'Mount Wilson'          => array( 'mountain', 'Mountain locations can have long access roads and weather changes. Share the exact location and access details, and our team will confirm what’s possible.' ),
	'Mount Baldy'           => array( 'mountain', 'Mountain locations can have long access roads and weather changes. Share the exact location and access details, and our team will confirm what’s possible.' ),
	'Wrightwood'            => array( 'mountain', 'Mountain locations can have long access roads and weather changes. Share the exact location and access details, and our team will confirm what’s possible.' ),
	'Point Mugu'            => array( 'base', 'Some locations here are on a military installation with restricted access. Share the venue and any access requirements, and our team will confirm what’s possible.' ),
);

/* ------------------------------------------------------------------ *
 * Evidence per city.
 * ------------------------------------------------------------------ */
$cic_repo   = new DatabaseRepository();
$cic_served = $cic_repo->served_city_ranking( PHP_INT_MAX ); // published + Index + production + served.
$cic_pub    = array();
foreach ( $cic_served as $cic_c ) {
	$cic_pub[ (int) $cic_c['id'] ] = $cic_c;
}
usort( $cic_served, static fn( $a, $b ) => strcmp( $a['county_name'] ?? '', $b['county_name'] ?? '' ) ?: strcasecmp( $a['name'], $b['name'] ) );

$cic_pick = static function ( array $list, string $seed ) {
	return $list[ abs( crc32( $seed ) ) % count( $list ) ];
};
$cic_fill = static function ( string $text, array $vars ): string {
	return strtr( $text, $vars );
};
$cic_and = static function ( array $names ): string {
	return count( $names ) > 1 ? implode( ', ', array_slice( $names, 0, -1 ) ) . ' and ' . end( $names ) : (string) ( $names[0] ?? '' );
};

// Cities whose content records existed before this content set (written 2026-11-15/16 and Los
// Angeles curated in wp-admin). Their current copy is exported unchanged; every other eligible city
// is (re)generated, so the output stays identical however often this runs.
$cic_preexisting = array( 'los-angeles-ca', 'santa-barbara-ca', 'long-beach-ca', 'glendale-ca', 'pasadena-ca', 'burbank-ca', 'ventura-ca', 'oxnard-ca', 'santa-clarita-ca', 'thousand-oaks-ca', 'city-of-industry-ca', 'pomona-ca', 'santa-monica-mountains-ca', 'torrance-ca', 'whittier-ca', 'carson-ca', 'goleta-ca' );
$cic_out         = array();
$cic_angle_n     = array();
$cic_briefs  = array();
$cic_i      = 0;
$cic_county = '';
$cic_skip   = array();
foreach ( $cic_served as $cic_c ) {
	$cic_id = (int) $cic_c['id'];
	$cic_ed = CityStore::editorial_id( $cic_id );
	if ( in_array( (string) $cic_c['key'], $cic_preexisting, true ) && $cic_ed && get_post( $cic_ed ) ) {
		// Existing content record: its current values are exported unchanged (so live gets the same
		// copy), never regenerated.
		$cic_skip[]    = $cic_c['name'] . " (#$cic_ed)";
		$cic_existing  = array();
		foreach ( array_keys( \CIC\Content\Fields::CITY_FIELDS ) as $cic_field ) {
			$cic_v = trim( (string) get_post_meta( $cic_ed, 'cic_city_' . $cic_field, true ) );
			if ( '' !== $cic_v && ! in_array( \CIC\Content\Fields::CITY_FIELDS[ $cic_field ][0], array( 'image' ), true ) ) {
				$cic_existing[ $cic_field ] = $cic_v; // Images are local attachment IDs: never exported.
			}
		}
		$cic_out[ (string) $cic_c['key'] ] = array( 'city' => $cic_c['name'], 'county' => $cic_c['county_name'] ?? '', 'source' => 'existing', 'fields' => $cic_existing );
		continue;
	}
	if ( ( $cic_c['county_name'] ?? '' ) !== $cic_county ) {
		$cic_county = (string) ( $cic_c['county_name'] ?? '' );
		$cic_i      = abs( crc32( $cic_county ) ) % 10; // Different starting angle per county.
	}
	++$cic_i;

	$cic_name = (string) $cic_c['name'];
	$cic_zips = (int) $cic_c['served_zips'];
	// Other published, served cities sharing this city's ZIP codes.
	$cic_partners = array();
	foreach ( CityStore::mappings( $cic_id ) as $cic_m ) {
		if ( (int) $cic_m['shared'] < 2 ) {
			continue;
		}
		foreach ( CityStore::zip_cities( (string) $cic_m['zip'], $cic_id ) as $cic_z ) {
			if ( isset( $cic_pub[ $cic_z['city_id'] ] ) ) {
				$cic_partners[ $cic_z['city_id'] ] = $cic_pub[ $cic_z['city_id'] ]['name'];
			}
		}
	}
	asort( $cic_partners );
	$cic_partner_names = array_slice( array_values( $cic_partners ), 0, 2 );
	$cic_near          = array_map( static fn( $n ) => $n['name'], $cic_repo->nearby_cities( $cic_repo->city_by_id( $cic_id ) ?? $cic_c, 3 ) );

	$cic_angle_key = $cic_angle_keys[ $cic_i % count( $cic_angle_keys ) ];
	if ( isset( $cic_special[ (string) $cic_c['name'] ] ) ) {
		$cic_angle_key = 'first'; // Island / summit / base: planning-first copy, no audience assumption.
	}
	$cic_a         = $cic_angles[ $cic_angle_key ];
	// Within one angle, every page gets a different treat × occasion pair (32 combinations).
	$cic_n                       = $cic_angle_n[ $cic_angle_key ] = ( $cic_angle_n[ $cic_angle_key ] ?? -1 ) + 1;
	$cic_treat_key               = array_keys( $cic_treats )[ $cic_n % 4 ];
	$cic_treat                   = $cic_treats[ $cic_treat_key ];
	// Occasion emphasis follows the angle where the angle implies one; otherwise it rotates.
	$cic_occ_by_angle = array(
		'school'    => array( array( 'graduation', 'prom' ), array( 'prom', 'graduation' ) ),
		'reception' => array( array( 'wedding', 'anniversary' ), array( 'anniversary', 'wedding' ) ),
		'office'    => array( array( 'corporate', 'graduation' ), array( 'corporate', 'birthday' ) ),
		'mixed'     => array( array( 'birthday', 'anniversary' ), array( 'anniversary', 'birthday' ) ),
		'home'      => array( array( 'birthday', 'graduation' ), array( 'graduation', 'birthday' ), array( 'anniversary', 'birthday' ) ),
	);
	$cic_occ_list = $cic_occ_by_angle[ $cic_angle_key ] ?? $cic_occasion_sets;
	$cic_occ      = $cic_occ_list[ ( $cic_n + intdiv( $cic_n, 4 ) ) % count( $cic_occ_list ) ];
	// Secondary angle: supplies the plans text and one FAQ, so pages sharing a main angle differ.
	$cic_others = array_values( array_diff( $cic_angle_keys, array( $cic_angle_key ) ) );
	$cic_b      = $cic_angles[ $cic_others[ ( $cic_n * 5 + 3 ) % count( $cic_others ) ] ];
	$cic_seed                    = $cic_name . '|' . $cic_id;
	$cic_vars      = array(
		'{city}'     => $cic_name,
		'{county}'   => (string) ( $cic_c['county_name'] ?? '' ),
		'{partners}' => $cic_and( $cic_partner_names ),
		'{treatA}'   => $cic_treat['name'] === 'classic ice cream' ? 'Dippin’ Dots' : $cic_treat['name'],
	);
	// Two-variant angle fields alternate by position within the angle (bits of $cic_n), so pages
	// sharing an angle differ in their wording too; longer shared lists use the city hash.
	$cic_bit = 0;
	$cic_f   = static function ( $list, $salt ) use ( $cic_fill, $cic_pick, $cic_seed, $cic_vars, $cic_n, &$cic_bit ) {
		if ( 2 === count( $list ) ) {
			$cic_v = ( intdiv( $cic_n, 1 << ( $cic_bit++ % 4 ) ) + abs( crc32( $salt ) ) ) % 2;
			return $cic_fill( $list[ $cic_v ], $cic_vars );
		}
		return $cic_fill( $cic_pick( $list, $cic_seed . $salt ), $cic_vars );
	};

	// Intro: angle opener + service line + treat line (+ occasion line).
	$cic_occ_line = sprintf(
		'%s and %s are a natural fit, and we also serve %s.',
		ucfirst( $cic_occasion_plural[ $cic_occ[0] ] ),
		$cic_occasion_plural[ $cic_occ[1] ],
		$cic_and( array_values( array_diff_key( array( 'birthday' => 'birthdays', 'wedding' => 'weddings', 'graduation' => 'graduation parties', 'prom' => 'proms', 'corporate' => 'corporate events', 'anniversary' => 'anniversaries' ), array_flip( $cic_occ ) ) ) )
	);
	$cic_intro = $cic_f( $cic_a['intro'], 'intro' ) . ' ' . $cic_pick( $cic_service, $cic_seed . 'svc' ) . ' ' . $cic_pick( $cic_treat['lines'], $cic_seed . 'tl' );
	$cic_special_note = $cic_special[ $cic_name ][1] ?? '';

	// FAQs: two angle questions, a coverage question, a treat question.
	$cic_af   = $cic_a['faqs'];
	$cic_off  = abs( crc32( $cic_seed . 'faq' ) ) % 3;
	$cic_q1   = $cic_af[ $cic_off ];
	$cic_q2   = $cic_b['faqs'][ ( $cic_off + $cic_n ) % 3 ];
	if ( $cic_special_note ) {
		$cic_q3 = array( 'Is there anything special about events in {city}?', $cic_special_note );
	} elseif ( $cic_partner_names ) {
		$cic_q3 = $cic_pick(
			array(
				array( 'Why does the ZIP check ask for the venue city?', 'Some ZIP codes in {city} are shared with {partners}. Choosing {city} as the venue city lets us confirm availability for the right area.' ),
				array( 'Our ZIP code also covers {partners}. Does that matter?', 'It can. For shared ZIP codes, the coverage check asks for the venue city; pick {city} and our team confirms availability for your date and venue.' ),
			),
			$cic_seed . 'q3'
		);
	} elseif ( $cic_zips >= 5 ) {
		$cic_q3 = array( 'Do you serve every part of {city}?', 'Service is confirmed by exact ZIP code. Enter your event ZIP code on this page or in your quote request, and our team confirms availability for your date and venue.' );
	} else {
		$cic_q3 = $cic_pick(
			array(
				array( 'How do I check availability in {city}?', 'Enter your event ZIP code on this page or in your quote request. Our team confirms availability for your date and venue.' ),
				array( 'Is my {city} address in your service area?', 'Enter your event ZIP code above. Service is confirmed for the exact ZIP code, your date and the venue arrangements.' ),
			),
			$cic_seed . 'q3'
		);
	}
	$cic_q4 = $cic_pick( $cic_treat['faq'], $cic_seed . 'q4' );

	// SEO title: first pattern that fits (≤ 62 characters with the site name).
	$cic_title = '';
	foreach ( $cic_a['seo_t'] as $cic_pattern ) {
		$cic_try = $cic_fill( $cic_pattern, $cic_vars );
		if ( mb_strlen( $cic_try . $cic_site ) <= 62 ) {
			$cic_title = $cic_try;
			break;
		}
	}
	$cic_title = '' !== $cic_title ? $cic_title : $cic_fill( end( $cic_a['seo_t'] ), $cic_vars );
	$cic_desc  = '';
	foreach ( array( $cic_f( $cic_a['seo_d'], 'sd' ), $cic_fill( $cic_a['seo_d'][0], $cic_vars ), $cic_fill( $cic_a['seo_d'][1], $cic_vars ), sprintf( 'Staffed ice cream catering in %s, CA for parties and events. Check your ZIP code and request a quote.', $cic_name ) ) as $cic_try ) {
		if ( mb_strlen( $cic_try ) <= 160 ) {
			$cic_desc = $cic_try;
			break;
		}
	}

	$cic_fields = array(
		'title'             => $cic_f( $cic_a['h1'], 'h1' ),
		'subtitle'          => $cic_f( $cic_a['subtitle'], 'sub' ),
		'intro'             => $cic_fill( $cic_intro, $cic_vars ),
		'search_note'       => $cic_special_note ? $cic_special_note : ( $cic_zips >= 5 ? $cic_f( $cic_note_multi, 'note' ) : $cic_f( $cic_note_plain, 'note' ) ),
		'plans_heading'     => $cic_f( $cic_a['plans_h'], 'ph' ),
		'plans_intro'       => $cic_f( $cic_b['plans'], 'pi' ) . ' ' . $cic_occ_line,
		'treats_heading'    => $cic_f( $cic_treats_h, 'th' ),
		'call_heading'      => $cic_f( $cic_a['call_h'], 'ch' ),
		'call_text'         => $cic_f( $cic_a['call'], 'ct' ),
		'occasions_heading' => $cic_f( $cic_occasions_h, 'oh' ),
		'check_intro'       => $cic_partner_names ? $cic_f( $cic_check_shared, 'ci' ) : $cic_f( $cic_check_plain, 'ci' ),
		'faq_1_q'           => $cic_fill( $cic_q1[0], $cic_vars ),
		'faq_1_a'           => $cic_fill( $cic_q1[1], $cic_vars ),
		'faq_2_q'           => $cic_fill( $cic_q2[0], $cic_vars ),
		'faq_2_a'           => $cic_fill( $cic_q2[1], $cic_vars ),
		'faq_3_q'           => $cic_fill( $cic_q3[0], $cic_vars ),
		'faq_3_a'           => $cic_fill( $cic_q3[1], $cic_vars ),
		'faq_4_q'           => $cic_fill( $cic_q4[0], $cic_vars ),
		'faq_4_a'           => $cic_fill( $cic_q4[1], $cic_vars ),
		'quote_heading'     => $cic_f( $cic_quote_h, 'qh' ),
		'quote_note'        => $cic_f( $cic_quote_n, 'qn' ),
		'seo_title'         => $cic_title,
		'seo_description'   => $cic_desc,
	);
	$cic_out[ (string) $cic_c['key'] ] = array(
		'city'   => $cic_name,
		'county' => $cic_c['county_name'] ?? '',
		'source' => 'generated',
		'fields' => $cic_fields,
	);
	$cic_briefs[] = array(
		'id'                 => $cic_id,
		'city'               => $cic_name,
		'county'             => $cic_c['county_name'] ?? '',
		'served_zips'        => $cic_zips,
		'shares_zips_with'   => implode( '; ', array_values( $cic_partners ) ),
		'nearby_links'       => implode( '; ', $cic_near ),
		'intent'             => "Find staffed ice cream catering for an event in $cic_name, CA and check availability",
		'angle'              => $cic_a['label'] . ' (secondary: ' . $cic_b['label'] . ')',
		'supporting_terms'   => $cic_a['terms'] . ', ' . $cic_treat['name'] . ' catering',
		'occasion_emphasis'  => $cic_occasion_names[ $cic_occ[0] ] . ', ' . $cic_occasion_names[ $cic_occ[1] ],
		'treat_emphasis'     => $cic_treat['name'],
		'local_context'      => $cic_special_note ? 'Special logistics (' . $cic_special[ $cic_name ][0] . '): owner to confirm' : ( $cic_partner_names ? 'Shared ZIP codes (venue city needed in the coverage check)' : ( $cic_zips >= 5 ? 'Several served ZIP codes' : 'No verified local facts used' ) ),
		'planning_questions' => implode( ' | ', array( $cic_fields['faq_1_q'], $cic_fields['faq_2_q'], $cic_fields['faq_3_q'], $cic_fields['faq_4_q'] ) ),
		'internal_links'     => implode( '; ', array( $cic_treat_paths[ $cic_treat_key ], $cic_event_paths[ $cic_occ[0] ], $cic_event_paths[ $cic_occ[1] ], 'nearby: ' . implode( ', ', $cic_near ), 'state directory, coverage check' ) ),
		'h1'                 => wp_strip_all_tags( $cic_fields['title'] ),
		'title'              => $cic_title . $cic_site,
		'title_len'          => mb_strlen( $cic_title . $cic_site ),
		'description'        => $cic_desc,
		'description_len'    => mb_strlen( $cic_desc ),
	);
}

if ( ! is_dir( dirname( $cic_json ) ) ) {
	wp_mkdir_p( dirname( $cic_json ) );
}
file_put_contents( $cic_json, wp_json_encode( array( 'generated' => gmdate( 'c' ), 'version' => '20261116', 'cities' => $cic_out ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
$cic_fh = fopen( $cic_csv, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
fputcsv( $cic_fh, array_keys( $cic_briefs[0] ), ',', '"', '' );
foreach ( $cic_briefs as $cic_b ) {
	fputcsv( $cic_fh, $cic_b, ',', '"', '' );
}
fclose( $cic_fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

WP_CLI::log( sprintf( 'Cities in the data file: %d (generated %d; existing records exported unchanged %d: %s)', count( $cic_out ), count( $cic_out ) - count( $cic_skip ), count( $cic_skip ), implode( ', ', $cic_skip ) ) );
WP_CLI::log( 'Angles: ' . wp_json_encode( array_count_values( array_column( $cic_briefs, 'angle' ) ) ) );
WP_CLI::log( sprintf( 'Titles > 62 chars: %d; descriptions > 160: %d; empty descriptions: %d', count( array_filter( $cic_briefs, static fn( $b ) => $b['title_len'] > 62 ) ), count( array_filter( $cic_briefs, static fn( $b ) => $b['description_len'] > 160 ) ), count( array_filter( $cic_briefs, static fn( $b ) => '' === $b['description'] ) ) ) );
WP_CLI::success( "Wrote $cic_json and $cic_csv" );
