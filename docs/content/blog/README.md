# Blog drafts (2026-10-29), for owner review

Four planning guides written for real search demand. Keyword data: Semrush, US national database, monthly searches (national, not Southern California only), pulled 2026-10-29. No prices, minimums, equipment, allergen or certification claims about CaterIceCream.com; only facts already on the site.

| # | Post | Focus keyword (searches/mo · difficulty) | Converts to |
| --- | --- | --- | --- |
| 1 | Ice Cream Truck, Cart or Staffed Catering? | ice cream truck rental (3,600 · 7), ice cream cart rental (1,900 · 10) | Treats, Service Areas, quote |
| 2 | Wedding Ice Cream Bar Ideas | wedding ice cream bar (390 · 13), wedding dessert ideas (1,900 · 18) | Weddings, quote `?event=wedding` |
| 3 | Office Ice Cream Social Ideas | ice cream social ideas (590 · 4) | Corporate Events, quote `?event=corporate` |
| 4 | Ice Cream Party Ideas for Birthdays | ice cream party ideas (480 · 16) | Birthdays, quote `?event=birthday` |

Next candidates: graduation party dessert ideas (260 · 20, publish by April for May–June), frozen yogurt catering (210 · 8), Dippin' Dots catering (170 · 24).

## In WordPress (2026-10-29)

Imported as **Drafts** in Posts (#1245–#1248 locally, category "Planning Tips") with Yoast title, meta description and focus keyphrase, by `wp eval-file dev/tools/content/blog-posts-20261029.php [dry-run] [report=…]` (add-only: an existing slug is never changed; backup `C:\laragon\backup\catericecream\blog-posts-20261029-002634\`). On live, run the same script after deploying the files; edit the posts in WordPress from then on (the Markdown files are the original drafts only). Publish post 1 no later than post 4, which links to it.

## Before publishing

1. **Blog design (separate task):** the theme has no single-post or blog-list template, so posts would show in the generic fallback layout. Needs a blog page + post template in the approved style, a "Blog" (or "Planning Tips") link (footer is enough), and a decision on the URL (`/blog/{slug}/` needs a permalink change; currently posts would be `/{slug}/`). Fix the internal link in post 4 accordingly.
2. **Indexing:** posts are indexable (Yoast "Posts" = show in search). Category archives are noindex since 2026-10-28; keep posts in one category, or switch categories back to indexable only if they become useful hubs.
3. **Images:** each post needs one real photo (featured image) with factual alt text; no AI images presented as real events.
4. **Author name:** Yoast shows the post author in the schema graph (Article → Person). The admin account's display name is currently "anur.v"; set a public display name in Users → Profile (e.g. the public contact name) before publishing.
5. **Review:** wording, tone and any detail you want to add from real experience (that is what makes these better than competitor pages).
