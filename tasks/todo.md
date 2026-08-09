# Add the 11 financial calculators to the site

Source package: `~/Downloads/vegha-website-redesign (1)/` (Aug 9), described in
`Nextsteps.md`. That package contained **two** deliverables: the calculators and a
PHP blog platform. **Only the calculators are shipped here** — the blog is deferred.

## Key finding

The package was built from an **older lineage** than this repo. Copying it in wholesale
would have silently reverted five local fixes, so this was a selective merge:

- `about.html` + `site.css` — mobile hero fix (commit 9dccd4b)
- `site.css` — `--stat-overlap` custom property + `.hero--with-stats` rule
- `site.css` — `.contact-item__ic svg` sizing
- `index.html` — `hero--with-stats` class on the hero
- `contact.html` — real SVG contact icons (package downgrades them to dingbats ✆ ✉ ⏰)
- `assets/img/partner-anil-kumar-*` — repo has a newer headshot (navy blazer); both
  ZIPs carry the older brown-blazer shot

## Shipped

- [x] `calculators/` — 11 calculators + listing page
- [x] `assets/js/calc.js` — maths, charts, schedules
- [x] `assets/css/site.css` — calculators block appended
- [x] `assets/js/site.js` — slider fill + quick-rate buttons
- [x] Header nav (7 pages): Calculators added; "About & Team" → "About" for nav room
- [x] Footer "Firm" list (7 pages): Calculators added
- [x] `index.html` — "Free tools" calculators band
- [x] `sitemap.xml` — 23 URLs (11 site + 12 calculator)
- [x] `README.txt` — calculators section + Budget-review note

## Deliberately held back (blog platform — "take care later")

`admin/` (PHP CMS + SQLite), `insights/` (article pages at new slugs),
`insights.html` rewrite with search/category filters, `insight-*.html` → redirect
stubs, `rss.xml`, `assets/img/blog/`, and `robots.txt`'s `Disallow: /admin/`.

The package **interleaves** blog and calculator code, so this needed unpicking rather
than just skipping files:
- `site.js` — one appended block held both blog filtering *and* the calculator slider
  fill. Kept the slider half, dropped the blog half.
- `site.css` — the appended tail ended with `.blog-toolbar` / `.blog-cats` /
  `.post--hidden` / `.post__thumb`. Dropped those 15 lines; verified `.post__thumb`
  is used by nothing else and the original `insights.html` never referenced them.
- `sitemap.xml` — rebuilt as original 11 + 12 calculator URLs, with the blog's
  `/insights/*` URLs excluded.

## Review

**One real bug caught during the merge.** The site.js additions belong *inside* the
IIFE, before the closing `})();` — not at end-of-file. A naive `>>` append put them
outside the closure where `$$` is undefined, so the sliders would have failed silently
with a ReferenceError. Rebuilt with the correct insertion point.

**Verified**
- `node --check` passes on `site.js` and `calc.js`; CSS braces balanced (348/348)
- 862 internal refs crawled → 0 broken; 23 sitemap URLs → all resolve
- No stray references anywhere to the removed `admin/`, `insights/` or `rss.xml`
- Home Loan EMI: ₹50L @ 8.5% / 20y → **₹43,391**, checked by hand against
  P·r(1+r)^n/((1+r)^n−1); totals self-consistent; 20 schedule rows
- Income Tax defaults (₹16L salary, 80C ₹1.5L, 80D ₹25k): new ₹1,13,100 vs old
  ₹2,34,000 → **₹1,20,900 saving**, reproduced by hand on FY2025-26 slabs
  (new 4/8/12/16/20/24 L with ₹75k SD; old 2.5/5/10 L with ₹50k SD; 4% cess)
- Zero console errors
- Repo-only fixes confirmed live in-browser: stats band overlaps hero by exactly
  74px, about hero mobile rule present, 5 contact SVGs at 21×21

**Still outstanding**
- Web3Forms key in `assets/js/site.js` (`ACCESS_KEY`) — forms are in demo mode
- Tax rates need a ~10-minute review after each Union Budget (see README)
- Client content items unchanged: shared partner headshot, duplicated
  Services/Industries hero photo
