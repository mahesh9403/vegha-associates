VEGHA & ASSOCIATES website (2026 redesign, deployable build)
===============================================================

This folder is the complete, self-contained website. Upload its entire
contents to the web root. Nothing needs to be installed, compiled or run
on the server. Plain HTML, CSS, JavaScript and images.

CONTENTS
  index.html            Home
  about.html            About Us & Team (7 partners)
  services.html         9 services with capability-sheet lightbox
  industries.html       5 industries with capability-sheet lightbox
  insights.html         Insights listing (3 articles)
  insight-*.html        Three partner-written articles
  query.html            Submit a Query form
  careers.html          Careers + application form (resume upload)
  contact.html          Contact details, form and Google Map
  calculators/          11 financial calculators + a listing page
  robots.txt            Search engine directives
  sitemap.xml           All 23 pages (11 site + 12 calculator)
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

BEFORE GOING LIVE
  1. FORMS DO NOT SEND YET.
     Open assets/js/site.js and set ACCESS_KEY to a Web3Forms access key
     (free, from web3forms.com). Submissions then arrive at
     admin@veghaandassociates.com, resume attachment included.
     Until then the forms validate but show a "demo mode" message.
  2. UPDATE THE DOMAIN if it differs from www.veghaandassociates.com;
     canonical, Open Graph, sitemap and article URLs assume it.
  3. SUBMIT sitemap.xml in Google Search Console so the calculator pages
     get indexed.

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
