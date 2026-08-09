# Blog platform (/admin/ + Insights)

Second half of the delivered package (`~/Downloads/vegha-website-redesign (1)/`).
The calculators shipped first in `2c10195`; this adds the publishing side.

## Shipped

- [x] `admin/` — PHP + SQLite publishing area (auth, editor, image upload, preview)
- [x] `insights/` — 3 published articles + search index
- [x] `insights.html` — listing with live search + category filters (GENERATED)
- [x] `insight-*.html` — redirect stubs to the new `/insights/<slug>.html` URLs
- [x] `rss.xml`, `assets/img/blog/` upload dir
- [x] `robots.txt` — `Disallow: /admin/`
- [x] `site.js` / `site.css` — blog filtering + blog listing styles re-added
- [x] `sitemap.xml` — 23 URLs (9 static + 11 calculator + 3 articles)
- [x] `README.txt` — publishing, backup and go-live steps

## Security review of the vendor's PHP (repo is PUBLIC)

Sound already: image upload does `getimagesize` + MIME allowlist + full GD
re-encode + random name + forced extension (no PHP-in-a-JPEG vector); CSRF via
`hash_equals`; `password_hash`/`PASSWORD_DEFAULT`; login rate-limited; slugs always
pass through `slugify()`, so the file writes and deletes cannot traverse.

Fixed here (all four verified against a live PHP server):
1. **Session cookie flags** — `session_start()` set none. Now `HttpOnly` +
   `SameSite=Lax`, with `Secure` conditional on HTTPS (and `X-Forwarded-Proto`, for
   hosts that terminate TLS at a proxy) so local HTTP testing still works.
2. **Slug collisions** — two posts with the same title produced the same
   `insights/<slug>.html`; the second overwrote the first, and unpublishing either
   deleted the survivor's live page. Added `unique_slug()`, which suffixes `-2`, `-3`
   and takes an `$ignoreId` so a post keeps its own slug when re-saved.
5. **Attempts not cleared on success** — a few typos then a correct password left the
   user one attempt from a 15-minute lockout. `clear_attempts()` on successful login.
6. **Dead code** — `preview.php` replaced two strings with themselves. Removed, with a
   comment explaining why no rewriting is needed (/admin/ and /insights/ sit at the
   same depth, so the template's `../` paths already resolve).

Left alone deliberately:
- **#3 nginx** — `admin/data/.htaccess` (`Require all denied`) is Apache-only. On nginx
  it is ignored and `blog.sqlite`, which holds the password hash, becomes downloadable.
  Cannot be fixed in code; documented in README as a go-live step.
- **#4** — `H1` renders unescaped while its fallback is escaped. Admin-only input on a
  single-author CMS; changing it would alter existing rendered headings.

## Verified against a real PHP server (8.5.9, pdo_sqlite + gd + webp)

- `php -l` clean on all 7 files
- First-run setup → password set (bcrypt `$2y$12$`) → dashboard lists the 3 seeded posts
- Cookie asserted as `HttpOnly; SameSite=Lax`, `Secure` absent over HTTP (correct)
- **Publish loop**: created and published an article → `insights/<slug>.html` written,
  `insights.html` 3→4 posts, `rss.xml` 3→4 items, sitemap 3→4 article URLs,
  search index 3→4 entries, no unfilled `{{...}}`, 3 JSON-LD blocks, canonical correct
- **Slug fix**: duplicate title → `...-msmes-2`, both files survive; re-saving a post
  keeps its own slug (no self-collision, no orphan)
- **Attempts fix**: 3 failures recorded → correct password → 0; lockout still fires at 8
- CSRF with a bad token → 403; `preview.php` renders with correct asset paths
- **Delete** → both test posts removed, all generated files back to the seeded baseline,
  no orphan files
- Front end: listing renders, search "gst" → 1 of 3, category filter works, 885 internal
  refs → 0 broken, 23 sitemap URLs resolve, zero console errors

Test DB was restored from a pre-test backup — the committed `blog.sqlite` has 0 rows in
`settings` and `attempts` (no password hash, no IPs) and the original 3 posts.

## Known quirks (vendor package, not introduced here)

- The shipped `insights.html` shows post 1's card headline using its `h1` text, but
  `listing_item()` builds cards from `title`. On the client's first publish that one
  headline changes from "Statutory audit or tax audit? What actually applies to your
  business" to "Statutory Audit or Tax Audit? What Applies to Your Business". Cosmetic;
  fix by editing the post's Title field if the sentence-case version is preferred.
- `rss.xml` `pubDate` offsets follow the *server's* PHP timezone. The vendor's seed file
  carries `+0530`; a host set to UTC will emit `+0000` on the next publish.

## Deployment: database is no longer tracked

The site auto-deploys from GitHub, so a committed `blog.sqlite` meant every push
overwrote the live database -- wiping the admin password and every article published
since, and leaving `/admin/` unclaimed for anyone to seize. Fixed by removing it from
git (`.gitignore`) and having `db()` build the database from `admin/data/seed.sql` when
it finds none. A fresh host self-seeds; an existing host is never touched.

Verified: with no database present, a request to `/admin/` created it with all three
tables and the three seeded posts; after setting a password, a further request left the
password and posts intact (previously this is where a deploy would have wiped them);
publish and delete still regenerate every output correctly.

Made while the live database was still at seed state with no password set, so nothing
could be lost. Doing it later would have been riskier.

## Deployment: generated pages self-heal after a deploy

Same trap as the database, one layer up. `insights.html`, `rss.xml`, `sitemap.xml` and
the article pages are generated but still tracked, so a deploy restores the repository's
copies and any article published since drops off the listing. The database keeps the
article, so nothing is lost -- it just stops being linked, which reads as data loss.

Timestamps cannot detect this: a deploy writes stale content with a *fresh* mtime. So
`regen_all()` now stamps the listing with `<!-- build:HASH -->`, a fingerprint of the
published set (id, slug, updated_at). `ensure_generated_fresh()` compares that with what
the database implies and rebuilds on mismatch. It runs on the admin dashboard, so the
first login after a deploy repairs the site.

Verified: three dashboard loads with nothing changed triggered no rebuild and left the
files byte-identical (no churn); publishing an article then restoring the pre-publish
copies made it vanish from the listing, and the next dashboard load rebuilt listing, RSS
and sitemap and brought it back.

The shipped `insights.html` was stamped by hand rather than regenerated, so the vendor's
sentence-case headline for post 1 survives -- the cosmetic flip noted above still only
happens on the client's first real publish.

## Still outstanding

- Web3Forms key in `assets/js/site.js` (`ACCESS_KEY`) — forms are in demo mode
- Set the admin password immediately after upload (see README step 3)
- Tax slabs need a review after each Union Budget
- Uploaded blog images live only on the server (`assets/img/blog/` is gitignored) --
  include them in backups alongside `blog.sqlite`
