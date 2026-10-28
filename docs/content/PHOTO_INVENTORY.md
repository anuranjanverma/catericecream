# Photo inventory: temporary images to replace

Status 2026-10-22 (updated 2026-10-24: the first authentic photos and videos are on the Dippin' Dots and DipN' Bananas pages; see below). Apart from those, **no real client photographs have been supplied yet.** Every photo on the site is a temporary AI recreation from the image pack (`handoff/Cater_Ice_Cream_WebP_Assets`). They are never presented as real Cater Ice Cream events, customers, staff or venues, and they show unbranded cups only.

How to replace a photo: upload a real WebP/JPEG photo in **Media → Add New** (WordPress creates the responsive sizes), then choose it in the matching field (the page or event edit screen, or the pairing on each treat). Keep the aspect ratio below so the approved layout and crop stay the same. Supply at least the "delivered size"; larger originals are fine. Real photos of people need the people's permission to publish; school events also need the school's permission.

## Authentic media supplied by the owner (2026-10-24)

The first real photos and videos are on the treat pages (originals in `handoff/Cater_Ice_Cream_WebP_Assets/treats/`, unchanged; web copies in the Media Library with `_cic_asset_id` keys; manifest `dev/tools/content/treat-media-20261024.json`).

| Page | Placement | Asset key | Original | Web copy | Notes |
|---|---|---|---|---|---|
| /treats/dippin-dots/ | Product detail photo (was AI `dots-detail-macro`) | dots-wedding-setup | WhatsApp Image 2026-10-12 at 6.20.50 PM.jpeg (1600×1066) | WebP 1240×1066 | Cropped to leave out the easel sign that shows the couple's first names and wedding date. Owner: confirm the couple agreed to publication. |
| /treats/dippin-dots/ | Photos and videos, item 1 (largest) | dots-serving-video + dots-serving-poster | WhatsApp Video 2026-10-19 at 7.36.00 AM.mp4 (45 s; coded 1024×576, rotation 90° → 576×1024) | MP4 as supplied; poster WebP 576×1024 (frame at 25.7 s) | Children's faces are visible: owner to confirm parents' permission. |
| /treats/dippin-dots/ | Photos and videos, item 2 | dots-birthday-video + dots-birthday-poster | WhatsApp Video 2026-10-19 at 8.42.08 PM.mp4 (24 s, 576×1024) | MP4 as supplied; poster WebP 576×1024 (frame at 4.0 s) | The cake and the closing banner show a child's first name (in the video, not in the poster or captions). Owner to confirm permission or supply a trimmed clip. |
| /treats/dipn-bananas/ | Photos and videos, item 1 | bananas-storage-trays | DipN' Bananas Storage.jpeg (1200×1600) | WebP 1200×1600 | Behind-the-scenes photo; not the hero. |
| /treats/dipn-bananas/ | Photos and videos, item 2 (small secondary clip) | bananas-dipping-video + bananas-dipping-poster | WhatsApp Video 2026-10-15 at 11.42.13 PM.mp4 (100 s; coded 224×128, rotation 90° → 128×224) | MP4 as supplied; poster WebP 128×224 (frame at 32.5 s) | **Higher-quality original needed:** only 128×224 px, shown at most 192 px wide (1.5×) and never as a feature. It is also 14 MB for 100 s, so a shorter, sharper clip would be better. |

The AI images these replace stay in the Media Library (for example `dots-detail-macro`), unused on these placements.

## New pages (2026-10-22): no suitable photos exist yet

The pack has no graduation or prom imagery and every person in it is an adult, so these pages use the closest neutral photos until real ones arrive.

| Page | Section / placement | Temporary image | Aspect | Delivered size (px) | Max CSS width | Required subject |
|---|---|---|---|---|---|---|
| /events/graduation-parties/ | hero.main (split hero) | yogurt-friends | 4:3 | 1120×840 | 560 | Graduates (high-school age) or a graduation family celebrating with ice cream from the catering service, outdoors; caps/gowns welcome. No school names or logos. |
| /events/graduation-parties/ | hero.inset | service-garden | 4:3 | 640×480 | 300 | Your staff serving treats at a real event (same subject as the existing inset). |
| /events/graduation-parties/ | stories.1 (backyard party) | classic-family | 4:3 | 640×480 | 300 | A backyard graduation party: family and guests with treats. |
| /events/graduation-parties/ | stories.2 (school celebration) | yogurt-theme-social | 4:3 | 640×480 | 300 | A school or class celebration with your dessert station (only with the school's permission to publish). |
| /events/graduation-parties/ | card / featured image | yogurt-friends | 16:9 | 960×540 | 400 | Overview and homepage card: a graduation party moment with treats. |
| /events/proms/ | hero.photo (full-bleed) | banana-theme-evening | 4:3 (mobile) / wide | 1440×1080 | 1440 | Evening prom setting with your dessert station or treats, formal wear, no alcohol, faces optional. Real photo with publishing permission. |
| /events/proms/ | feature.photo (arched) | quote-treat-assortment | 4:5 | 960×1200 | 480 | Your treats presented for an evening event (product/presentation shot). |
| /events/proms/ | feature.inset | yogurt-theme-modern | 4:3 | 640×480 | 240 | Close-up of a served treat at the evening setup. |
| /events/proms/ | gallery (wide band) | banana-theme-modern | 16:9 | 1440×810 | 1440 | Wide evening shot of the dessert stop or guests enjoying treats. |
| /events/proms/ | card / featured image | banana-theme-evening | 16:9 | 960×540 | 400 | Overview and homepage card: prom dessert moment. |

Graduation and Prom occasion cards on the treat pages (pairing photos) also use temporary pack images: Dippin' Dots `treat-dots-cup` / `dots-theme-retro`, DipN' Bananas `banana-theme-garden` / `banana-theme-evening`, Classic `classic-family` / `classic-detail-macro`, Frozen Yogurt `yogurt-friends` / `yogurt-theme-modern` (4:3, 640×480 delivered; edit on each treat → Occasion pairings).

## Existing approved placements (all temporary AI recreations)

From `ASSET_MANIFEST.json`; the subject column is the photo's current description, i.e. what a real replacement should show.

| Page | Placement | Current image | Aspect | Delivered size (px) | Max CSS width | Subject to photograph |
|---|---|---|---|---|---|---|
| / | hero.main | service-garden | 4:3 | 1120×840 | 560 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| / | hero.inset-wedding | wedding-couple | 4:3 | 480×360 | 200 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| / | hero.inset-corporate | corporate-friends | 4:3 | 480×360 | 220 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| / | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| / | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| / | treats.classic | treat-classic-scoops | 4:3 | 768×576 | 360 | Three realistic ice cream scoops, strawberry pink, vanilla cream and chocolate brown, in simple white bowls on a pale tabletop, strawberries and… |
| / | treats.yogurt | treat-frozen-yogurt | 4:3 | 768×576 | 360 | Two unbranded plain white paper cups of strawberry pink and ivory frozen yogurt soft serve, garnished with fresh raspberries, blueberries, strawberry slices… |
| / | treats.more | treat-more-frozen | 4:3 | 720×540 | 360 | Bright cheerful close product photograph of sprinkle-covered frozen ice cream bars and a scoop-topped waffle cone on pale lavender tabletop |
| / | events.birthday | birthday-child | 16:9 | 768×432 | 360 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| / | events.wedding | wedding-couple | 16:9 | 768×432 | 360 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| / | events.corporate | corporate-friends | 16:9 | 768×432 | 360 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| / | events.anniversary | anniversary-couple | 16:9 | 768×432 | 360 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /events/ | hero | events-treat-trio | 3:2 | 1120×747 | 560 | Three separate human hands lifting celebratory frozen treats against a pale lavender studio background: left small plain white cup of ice cream… |
| /events/ | cards.birthday | birthday-child | 16:9 | 960×540 | 560 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /events/ | cards.wedding | wedding-couple | 16:9 | 1120×630 | 560 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /events/ | cards.corporate | corporate-caterer | 16:9 | 1120×630 | 560 | Friendly female caterer in plain black cap and apron over white shirt handing a small cup of ice cream to smartly dressed… |
| /events/ | cards.anniversary | anniversary-couple | 16:9 | 1120×630 | 560 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /events/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /events/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /events/birthday/ | hero.collage-left | birthday-adult-friends | 4:3 | 768×576 | 340 | Three young adult friends laughing together at a colorful outdoor birthday gathering while holding small ice cream cups |
| /events/birthday/ | hero.collage-centre | birthday-father-daughter | 4:3 | 960×720 | 520 | Smiling father leaning beside his delighted curly-haired daughter who holds a small cup of ice cream, colorful garden birthday party, soft pink… |
| /events/birthday/ | hero.collage-right | quote-treat-assortment | 4:3 | 640×480 | 300 | Appetizing still life of a small unbranded white bowl of pink and vanilla ice cream with sprinkles, a tiny clear cup of… |
| /events/birthday/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /events/birthday/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /events/birthday/ | treats.classic | treat-classic-scoops | 1:1 | 480×480 | 180 | Three realistic ice cream scoops, strawberry pink, vanilla cream and chocolate brown, in simple white bowls on a pale tabletop, strawberries and… |
| /events/birthday/ | treats.yogurt | treat-frozen-yogurt | 1:1 | 480×480 | 180 | Two unbranded plain white paper cups of strawberry pink and ivory frozen yogurt soft serve, garnished with fresh raspberries, blueberries, strawberry slices… |
| /events/birthday/ | treats.more | treat-more-frozen | 1:1 | 480×480 | 180 | Bright cheerful close product photograph of sprinkle-covered frozen ice cream bars and a scoop-topped waffle cone on pale lavender tabletop |
| /events/birthday/ | age-stories.little-birthdays | birthday-child | 4:3 | 640×480 | 280 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /events/birthday/ | age-stories.milestones | birthday-milestone-friends | 4:3 | 640×480 | 280 | Group of four friends in their forties and fifties celebrating a milestone birthday outdoors, raising small cups of ice cream, bright pink… |
| /events/birthday/ | service-story | service-garden | 4:3 | 960×720 | 480 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /events/wedding/ | hero.background-photo | wedding-couple | 4:3 | 1440×1080 | 720 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /events/wedding/ | sweet-moment.main | wedding-dessert-hands | 4:3 | 960×720 | 480 | Close-up of bride and groom's hands holding two small plain cups of tiny pastel frozen ice cream beads, ivory lace dress and… |
| /events/wedding/ | sweet-moment.inset | wedding-friends-inset | 4:3 | 480×360 | 220 | Three bridesmaids in elegant pale blush dresses smiling with a bride at a garden wedding, holding small white cups of ice cream,… |
| /events/wedding/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /events/wedding/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /events/wedding/ | reception-wide | wedding-reception | 16:9 | 1440×810 | 1200 | Wide candid photograph of bride and groom at centre surrounded by wedding friends at a long garden reception table, everyone smiling with… |
| /events/anniversary/ | hero.main | anniversary-couple | 4:3 | 1120×840 | 560 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /events/anniversary/ | hero.inset | quote-treat-assortment | 4:3 | 480×360 | 200 | Appetizing still life of a small unbranded white bowl of pink and vanilla ice cream with sprinkles, a tiny clear cup of… |
| /events/anniversary/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /events/anniversary/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /events/anniversary/ | story.celebration | anniversary-garden-party | 4:3 | 1120×840 | 560 | Mature anniversary couple laughing with family and adult friends at a garden dinner table, everyone holding small white cups of ice cream,… |
| /events/corporate/ | hero.main | corporate-caterer | 4:3 | 1120×840 | 560 | Friendly female caterer in plain black cap and apron over white shirt handing a small cup of ice cream to smartly dressed… |
| /events/corporate/ | hero.inset | corporate-friends | 4:3 | 480×360 | 230 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /events/corporate/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /events/corporate/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /events/corporate/ | story.team-break | corporate-team-break | 4:3 | 1120×840 | 560 | Four diverse colleagues in smart business attire gathered around a courtyard table enjoying little cups of ice cream, casual friendly conversation during… |
| /treats/ | hero.assortment | quote-treat-assortment | 4:3 | 720×540 | 560 | Appetizing still life of a small unbranded white bowl of pink and vanilla ice cream with sprinkles, a tiny clear cup of… |
| /treats/ | rows.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /treats/ | rows.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /treats/ | rows.classic | treat-classic-scoops | 4:3 | 1120×840 | 560 | Three realistic ice cream scoops, strawberry pink, vanilla cream and chocolate brown, in simple white bowls on a pale tabletop, strawberries and… |
| /treats/ | rows.yogurt | treat-frozen-yogurt | 4:3 | 1120×840 | 560 | Two unbranded plain white paper cups of strawberry pink and ivory frozen yogurt soft serve, garnished with fresh raspberries, blueberries, strawberry slices… |
| /treats/dippin-dots/ | hero | dots-hero | 4:3 | 1120×840 | 560 | A small clear cup overflowing with hundreds of TINY pastel rainbow frozen ice cream beads only 2-4mm each, translucent spoon angled upward |
| /treats/dippin-dots/ | occasion.birthday | dots-family | 4:3 | 640×480 | 280 | Happy curly-haired girl holding a small plain white cup filled with tiny rainbow frozen ice cream beads, mother beside her, colorful garden… |
| /treats/dippin-dots/ | occasion.wedding | dots-wedding | 4:3 | 640×480 | 280 | Bride and groom in ivory lace and black tuxedo smiling while tasting tiny pastel frozen ice cream beads from small plain white… |
| /treats/dippin-dots/ | occasion.corporate | dots-corporate | 4:3 | 640×480 | 280 | Diverse business colleagues laughing with small plain cups of tiny rainbow frozen ice cream beads in a landscaped office courtyard |
| /treats/dippin-dots/ | occasion.anniversary | dots-anniversary | 4:3 | 640×480 | 280 | Loving couple in their sixties tasting tiny pastel frozen ice cream beads from little plain white cups, warm garden anniversary celebration, flowers… |
| /treats/dippin-dots/ | themes.playful | dots-theme-playful | 3:2 | 768×512 | 360 | Dessert table at a colorful playful garden celebration, several small clear cups of TINY rainbow frozen ice cream beads, pastel balloons and… |
| /treats/dippin-dots/ | themes.retro | dots-theme-retro | 3:2 | 768×512 | 360 | Close food photo of small cups of tiny pastel frozen ice cream beads at a nostalgic pink dessert table, soft pink striped… |
| /treats/dippin-dots/ | themes.elegant | dots-theme-elegant | 3:2 | 768×512 | 360 | Several small clear cups of delicate pastel frozen ice cream beads on an elegant ivory garden reception table with blush roses and… |
| /treats/dippin-dots/ | detail.spoonful | dots-detail-macro | 4:3 | 960×720 | 560 | Close macro photograph of a clear cup of tiny pastel pink, blue, yellow and white frozen ice cream beads with a clear… |
| /treats/dipn-bananas/ | hero | banana-hero | 4:3 | 1120×840 | 560 | Extreme appetizing close photograph of one chocolate-covered frozen banana on wooden stick angled from lower right to upper left, topped with chopped… |
| /treats/dipn-bananas/ | occasion.birthday | banana-birthday | 4:3 | 640×480 | 280 | Happy girl with curly brown hair enjoying a chocolate-dipped frozen banana on wooden stick with chopped nuts at a colorful garden birthday,… |
| /treats/dipn-bananas/ | occasion.wedding | banana-wedding | 4:3 | 640×480 | 280 | Elegant bride and groom smiling together while holding chocolate-covered frozen bananas on wooden sticks, ivory flowers and romantic sunny garden wedding background |
| /treats/dipn-bananas/ | occasion.corporate | banana-corporate | 4:3 | 640×480 | 280 | Diverse smartly dressed coworkers laughing with chocolate-covered frozen bananas on sticks during an outdoor company event, office courtyard greenery, natural professional atmosphere,… |
| /treats/dipn-bananas/ | occasion.anniversary | banana-anniversary | 4:3 | 640×480 | 280 | Warm loving mature couple smiling and sharing a moment with chocolate-covered frozen bananas on wooden sticks at a garden anniversary celebration, blush… |
| /treats/dipn-bananas/ | themes.garden | banana-theme-garden | 3:2 | 768×512 | 360 | Chocolate-covered frozen banana pops upright on a pale dessert stand amid pink roses, white flowers and fresh garden greenery, chocolate with finely… |
| /treats/dipn-bananas/ | themes.modern | banana-theme-modern | 3:2 | 768×512 | 360 | Refined close photograph of chocolate-covered frozen banana pops with finely chopped roasted nuts on a matte ivory plate, minimalist contemporary dessert table,… |
| /treats/dipn-bananas/ | themes.evening | banana-theme-evening | 3:2 | 768×512 | 360 | Chocolate-covered frozen banana pops with delicate toppings on an elegant cream dessert stand at an intimate evening celebration, candlelight and golden string-light… |
| /treats/dipn-bananas/ | detail.banana-chocolate | banana-detail-macro | 4:3 | 960×720 | 560 | Macro still life of two frozen banana halves coated in dark chocolate and chopped roasted nuts on wooden sticks, one cut end… |
| /treats/classic-ice-cream/ | hero.family | classic-family | 4:3 | 960×720 | 400 | Happy young girl and her silver-haired grandmother smiling together while eating classic ice cream from little white cups in a sunlit garden,… |
| /treats/classic-ice-cream/ | hero.couple | classic-couple | 4:3 | 960×720 | 400 | Young couple laughing warmly while holding classic ice cream cones, woman long wavy brown hair, man dark hair and light beard, soft… |
| /treats/classic-ice-cream/ | hero.scoops | treat-classic-scoops | 4:3 | 1120×840 | 560 | Three realistic ice cream scoops, strawberry pink, vanilla cream and chocolate brown, in simple white bowls on a pale tabletop, strawberries and… |
| /treats/classic-ice-cream/ | occasion.birthday | birthday-child | 16:9 | 960×540 | 440 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /treats/classic-ice-cream/ | occasion.wedding | wedding-couple | 16:9 | 960×540 | 440 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /treats/classic-ice-cream/ | occasion.corporate | corporate-friends | 16:9 | 960×540 | 440 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /treats/classic-ice-cream/ | occasion.anniversary | anniversary-couple | 16:9 | 960×540 | 440 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /treats/classic-ice-cream/ | themes.retro | classic-theme-retro | 3:2 | 768×512 | 360 | Vanilla and strawberry ice cream scoops in small glass sundae bowls on a pink retro dessert table with pale pink striped napkins,… |
| /treats/classic-ice-cream/ | themes.garden | classic-theme-garden | 3:2 | 768×512 | 360 | Three classic waffle cones with vanilla, strawberry and peach-colored ice cream in a simple stand on a garden party table, white flowers,… |
| /treats/classic-ice-cream/ | themes.elegant | classic-theme-elegant | 3:2 | 768×512 | 360 | Elegant vanilla ice cream scoops in white porcelain dessert bowls with raspberries, a little chocolate drizzle and fine champagne gold spoons, ivory… |
| /treats/classic-ice-cream/ | detail.texture | classic-detail-macro | 4:3 | 960×720 | 560 | Macro photograph of strawberry pink ice cream with soft scoop ridges in a simple white bowl, fresh strawberries and softly blurred vanilla… |
| /treats/frozen-yogurt/ | hero.product | treat-frozen-yogurt | 4:3 | 1120×840 | 560 | Two unbranded plain white paper cups of strawberry pink and ivory frozen yogurt soft serve, garnished with fresh raspberries, blueberries, strawberry slices… |
| /treats/frozen-yogurt/ | hero.friends | yogurt-friends | 4:3 | 960×720 | 400 | Three young adult women laughing together outdoors, each holding a small plain white cup of pink and ivory frozen yogurt topped with… |
| /treats/frozen-yogurt/ | themes.garden | yogurt-theme-garden | 3:2 | 768×512 | 360 | Small white bowls of pink and ivory frozen yogurt swirls topped with berries on a sunny garden party table, fresh green foliage,… |
| /treats/frozen-yogurt/ | themes.modern | yogurt-theme-modern | 3:2 | 768×512 | 360 | Three small plain white cups of neat pink and ivory frozen yogurt swirls with raspberries blueberries and strawberry slices on a clean… |
| /treats/frozen-yogurt/ | themes.social | yogurt-theme-social | 3:2 | 768×512 | 360 | Friends casually gathered around a garden table with several small cups of berry-topped frozen yogurt in foreground, greenery and soft warm string… |
| /treats/frozen-yogurt/ | occasion.birthday | yogurt-birthday | 4:3 | 640×480 | 280 | Happy curly-haired girl tasting strawberry frozen yogurt with a spoon from a small plain white cup at a garden birthday party, colorful… |
| /treats/frozen-yogurt/ | occasion.wedding | yogurt-wedding | 4:3 | 640×480 | 280 | Bride and groom smiling together with little cups of berry-topped ivory frozen yogurt at an elegant outdoor wedding, ivory lace dress and… |
| /treats/frozen-yogurt/ | occasion.corporate | yogurt-corporate | 4:3 | 640×480 | 280 | Two diverse colleagues in business casual clothing laughing over small cups of berry-topped frozen yogurt in an office garden courtyard, professional friendly… |
| /treats/frozen-yogurt/ | occasion.anniversary | yogurt-anniversary | 4:3 | 640×480 | 280 | Mature couple in their sixties smiling closely with small plain cups of pink frozen yogurt and berries at a garden anniversary gathering,… |
| /treats/frozen-yogurt/ | detail.texture | yogurt-detail-macro | 4:3 | 960×720 | 560 | Extreme appetizing macro food photograph of soft strawberry-pink frozen yogurt swirl with smooth ridges, fresh raspberries around it, a hint of ivory… |
| /service-areas/california/pasadena/ | hero.main | service-garden | 4:3 | 1120×840 | 560 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /service-areas/california/pasadena/ | hero.inset-wedding | wedding-couple | 4:3 | 480×360 | 200 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /service-areas/california/pasadena/ | hero.inset-corporate | corporate-friends | 4:3 | 480×360 | 200 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /service-areas/california/pasadena/ | treats.dots | treat-dots-cup | 4:3 | 1120×840 | 560 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /service-areas/california/pasadena/ | treats.bananas | treat-dipped-bananas | 4:3 | 1120×840 | 560 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /service-areas/california/pasadena/ | call-panel.photo | service-garden | 4:3 | 960×720 | 480 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /service-areas/california/pasadena/ | occasions.birthday | birthday-child | 16:9 | 640×360 | 280 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /service-areas/california/pasadena/ | occasions.wedding | wedding-couple | 16:9 | 640×360 | 280 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /service-areas/california/pasadena/ | occasions.corporate | corporate-friends | 16:9 | 640×360 | 280 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /service-areas/california/pasadena/ | occasions.anniversary | anniversary-couple | 16:9 | 640×360 | 280 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /about/ | hero.main | service-garden | 4:3 | 1120×840 | 560 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /about/ | hero.inset-family | classic-family | 4:3 | 480×360 | 200 | Happy young girl and her silver-haired grandmother smiling together while eating classic ice cream from little white cups in a sunlit garden,… |
| /about/ | hero.inset-guests | corporate-friends | 4:3 | 480×360 | 200 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /about/ | story.occasion | birthday-adult-friends | 4:3 | 800×600 | 560 | Three young adult friends laughing together at a colorful outdoor birthday gathering while holding small ice cream cups |
| /about/ | treats.dots | treat-dots-cup | 4:3 | 960×720 | 400 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /about/ | treats.bananas | treat-dipped-bananas | 4:3 | 960×720 | 400 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /about/ | meet-yan.service-photo | service-garden | 4:3 | 960×720 | 400 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /service-areas/ | hero.service-photo | service-garden | 4:3 | 960×720 | 450 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /service-areas/ | counties.los-angeles | county-los-angeles | 16:9 | 768×432 | 360 | Photorealistic Southern California cityscape of downtown Los Angeles skyline at golden hour with tall palm trees in foreground, warm lavender-pink sky, crisp… |
| /service-areas/ | counties.ventura | county-ventura | 16:9 | 768×432 | 360 | Photorealistic Ventura County Southern California coast, Pacific Ocean blue waves, sandy beach and green coastal hills in warm afternoon light, natural realistic… |
| /service-areas/ | counties.santa-barbara | county-santa-barbara | 16:9 | 768×432 | 360 | Photorealistic Santa Barbara Southern California coastline with tall palms, cream Spanish-style waterfront buildings and distant mountains, clear blue sky and sunny welcoming… |
| /service-areas/ | call-panel.photo | corporate-friends | 16:9 | 960×540 | 480 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /service-areas/ | occasions.birthday | birthday-child | 16:9 | 640×360 | 260 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /service-areas/ | occasions.wedding | wedding-couple | 16:9 | 640×360 | 260 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /service-areas/ | occasions.anniversary | anniversary-couple | 16:9 | 640×360 | 260 | Happy couple in their early sixties laughing and sharing small plain cups of ice cream at their garden anniversary celebration |
| /service-areas/ | occasions.corporate | corporate-friends | 16:9 | 640×360 | 260 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /contact/ | hero.main | service-garden | 4:3 | 1120×840 | 560 | Friendly dark-haired female caterer wearing plain black cap and apron over a white shirt serving a small cup of tiny colorful frozen… |
| /contact/ | quote-panel.wedding | wedding-couple | 4:3 | 480×360 | 240 | Bride in an ivory lace dress and groom in black tuxedo smiling at each other, holding small plain white cups of ice… |
| /contact/ | quote-panel.birthday | birthday-child | 4:3 | 480×360 | 240 | Delighted curly-haired young girl at the centre holding a little cup of ice cream with sprinkles, other children softly visible behind, outdoor… |
| /contact/ | quote-panel.corporate | corporate-friends | 4:3 | 480×360 | 240 | Four diverse colleagues in smart business casual clothing laughing together with small unbranded ice cream cups and spoons in a sunny landscaped… |
| /get-a-quote/ | step-1.sidebar-treats | quote-treat-assortment | 4:3 | 640×480 | 300 | Appetizing still life of a small unbranded white bowl of pink and vanilla ice cream with sprinkles, a tiny clear cup of… |
| /get-a-quote/ | step-2.treat-dots | treat-dots-cup | 4:3 | 640×480 | 250 | Appetizing macro product photograph of one small plain white paper cup filled with MANY TINY round pastel rainbow frozen ice cream beads,… |
| /get-a-quote/ | step-2.treat-bananas | treat-dipped-bananas | 4:3 | 640×480 | 250 | Two frozen banana halves on wooden sticks coated in rich dark chocolate and chopped roasted nuts on a warm ivory tabletop, sliced… |
| /get-a-quote/ | step-2.treat-classic | treat-classic-scoops | 4:3 | 640×480 | 250 | Three realistic ice cream scoops, strawberry pink, vanilla cream and chocolate brown, in simple white bowls on a pale tabletop, strawberries and… |
| /get-a-quote/ | step-2.treat-yogurt | treat-frozen-yogurt | 4:3 | 640×480 | 250 | Two unbranded plain white paper cups of strawberry pink and ivory frozen yogurt soft serve, garnished with fresh raspberries, blueberries, strawberry slices… |

125 existing placements + 10 new-page placements.
