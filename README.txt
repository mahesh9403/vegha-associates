VEGHA & ASSOCIATES website (2026 redesign, deployable build)
===============================================================

This folder is the complete website. Upload its entire contents to the web
root. Every public page is a plain static file. The only server-side code is
the /admin/ publishing area, which needs PHP 8.0+ with pdo_sqlite and gd
(standard on shared hosting).

CONTENTS
  index.html            Home
  about.html            About Us & Team (7 partners)
  services.html         9 services with capability-sheet lightbox
  industries.html       5 industries with capability-sheet lightbox
  insights.html         Insights listing -- GENERATED, do not hand-edit
  insights/             Published articles + search index -- GENERATED
  insight-*.html        Redirect stubs to the old article URLs
  rss.xml               Insights RSS feed -- GENERATED
  admin/                Publishing area (PHP + SQLite)
  query.html            Submit a Query form
  careers.html          Careers + application form (resume upload)
  contact.html          Contact details, form and Google Map
  calculators/          11 financial calculators + a listing page
  robots.txt            Search engine directives
  sitemap.xml           All pages -- GENERATED on publish
  llms.txt              Plain-text firm summary for AI answer engines
  assets/css/site.css   One stylesheet (navy/ivory/gold design system)
  assets/js/site.js     One script, no libraries
  assets/js/calc.js     Calculator maths + charts (loaded only on /calculators/)
  assets/img/           All images as AVIF + WebP at multiple widths

CALCULATORS
  Home Loan EMI, Home Loan Prepayment, Car Loan EMI, SIP, Lumpsum,
  Mutual Fund Returns, FD, PPF, GST, HRA, and Income Tax (old vs new
  regime). Each is its own SEO page with sliders, live charts, a schedule
  where relevant, explainer copy and FAQs. Everything computes in the
  browser -- no figures are transmitted anywhere.

  Rates and slabs are hard-coded to current law. They need a review after
  each Union Budget: the tax slabs and rebate live in assets/js/calc.js,
  and the PPF/FD reference rates in the copy of their pages.

GOING LIVE, AND EVERY UPDATE AFTER  ** READ THIS BEFORE DEPLOYING **

  The live server owns the blog. The database (admin/data/blog.sqlite), the
  uploaded images (assets/img/blog/) and the pages written on publish exist
  ONLY there -- deliberately, so that shipping code cannot overwrite the
  client's articles. Neither is in version control.

  Git auto-deploy REPLACES the whole public_html folder and deletes anything
  not in the repository. That is verified behaviour, not a guess: a test push
  erased a password that had been set minutes earlier. So:

    AUTO-DEPLOY IS SAFE ONLY WHILE THE SITE HAS NO CONTENT.

  First deployment, in this order:
    1. Push the finished site to GitHub.
    2. Turn auto-deploy ON. The site lands complete; the blog database builds
       itself from admin/data/seed.sql on the first visit to /admin/.
    3. Check the site over.
    4. TURN AUTO-DEPLOY OFF. Do this BEFORE step 5 -- not after.
    5. Only now set the admin password and hand the panel to the client.

  Every update after that -- auto-deploy stays off, permanently:
    1. Commit and push as usual.
    2. Run:  bash deploy/make-release.sh
       That builds deploy/dist/code-update-*.zip, which deliberately omits the
       database, the uploaded images and the generated pages.
    3. Upload and extract it into public_html, overwriting when asked.
    4. Log in to /admin/ and press "Rebuild public pages" so the listing,
       article pages, feed and sitemap pick up the new code.

  DO NOT switch auto-deploy back on to ship an update. One push would delete
  every article and image the client has added. Upload the zip instead.

  (full-install-*.zip from the same script is for setting up a fresh host from
  scratch -- a new server, or a rebuild. It is not for updates.)

BEFORE GOING LIVE
  1. FORMS DO NOT SEND YET.
     Open assets/js/site.js and set ACCESS_KEY to a Web3Forms access key
     (free, from web3forms.com). Submissions then arrive at
     admin@veghaandassociates.com, resume attachment included.
     Until then the forms validate but show a "demo mode" message.
  2. UPDATE THE DOMAIN if it differs from www.veghaandassociates.com;
     canonical, Open Graph, sitemap and article URLs assume it.
  3. SET THE ADMIN PASSWORD as soon as auto-deploy is off (see above).
     Visit https://yourdomain/admin/ -- the first visit shows a "first-time
     setup" screen that sets the password. Until that is done, ANYONE who
     reaches /admin/ can claim it, and the path is discoverable because this
     project is on a public repository. Do it in the same sitting.
  4. SUBMIT sitemap.xml in Google Search Console so the calculator and
     article pages get indexed.
  5. IF YOUR HOST RUNS NGINX (rare on shared hosting), admin/data/.htaccess
     is ignored and blog.sqlite -- which holds the admin password hash --
     becomes downloadable. Ask support to add:
         location ^~ /admin/data/ { deny all; }
     On Apache the supplied .htaccess already handles this.

PUBLISHING
  Log in at /admin/, write a post (title, rich-text body, category, excerpt,
  SEO fields, optional featured image) and press Publish. That regenerates
  the article page, insights.html, the search index, rss.xml and sitemap.xml
  as static files. Nothing else needs doing.

  Those generated files are overwritten on every publish -- edit articles in
  the admin, never by hand.

  BACK UP admin/data/blog.sqlite occasionally. That single file is the whole
  blog: every article plus the admin password hash. Never commit a copy taken
  from the live server back into a public git repository.

  Back up assets/img/blog/ alongside it. Neither is in version control -- the
  server holds the only copy of both, by design, so that deploying code cannot
  overwrite the articles.

KNOWN CONTENT ITEMS FOR THE CLIENT
  - The Services and Industries hero photographs are the same image
    (differently cropped in CSS as an interim treatment).
  - The About group photo shows six people; the Firm lists seven partners.
  - The Accounting & Business Support capability sheet contains a rendered
    P&L table with a misaligned variance column (source image issue).

DESIGN NOTES
  - Fonts: Fraunces (display) + Inter (body), loaded from Google Fonts.
  - Motion (scroll reveals, counters, hero zoom) is fully disabled for
    users with prefers-reduced-motion.
  - The email address is a plain mailto: link (the previous build depended
    on a Cloudflare email-protection script that only works behind
    Cloudflare).
