<?php
/* VEGHA & ASSOCIATES blog admin library.
   Stores posts in SQLite; publishing writes static HTML using the templates in
   ./templates so public pages stay plain files. Requires PHP 8.0+ with
   pdo_sqlite and gd (standard on shared hosting). */

declare(strict_types=1);

/* Harden the admin session cookie before it is issued. "secure" is conditional so
   the panel still works over plain HTTP on a local test server; on the live HTTPS
   site the flag is set. Behind a load balancer PHP sees HTTP, so trust the
   forwarded-proto header too. */
$https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_start();

const SITE_URL   = 'https://www.veghaandassociates.com';
const ROOT       = __DIR__ . '/..';            /* web root */
const DB_PATH    = __DIR__ . '/data/blog.sqlite';
const TPL_DIR    = __DIR__ . '/templates';
const UPLOAD_DIR = ROOT . '/assets/img/blog';
const UPLOAD_URL = 'assets/img/blog';
const STATIC_PAGES = ['', 'about.html', 'services.html', 'industries.html', 'insights.html',
                      'careers.html', 'contact.html', 'query.html', 'calculators/'];
const CALC_PAGES = ['calculators/home-loan-emi.html', 'calculators/home-loan-prepayment.html',
                    'calculators/car-loan-emi.html', 'calculators/sip.html', 'calculators/lumpsum.html',
                    'calculators/mutual-fund-returns.html', 'calculators/fd.html', 'calculators/ppf.html',
                    'calculators/gst.html', 'calculators/hra.html', 'calculators/income-tax.html'];

const SEED_PATH = __DIR__ . '/data/seed.sql';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        /* The database is not in version control -- this site deploys from git, and a
           tracked blog.sqlite would overwrite the live one on every push, wiping the
           password and every published article. So the first run on a new host builds
           it from seed.sql instead. An existing database is never touched. */
        $fresh = !is_file(DB_PATH) || filesize(DB_PATH) === 0;
        if ($fresh && !is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($fresh && is_readable(SEED_PATH)) $pdo->exec(file_get_contents(SEED_PATH));
    }
    return $pdo;
}
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function setting(string $k): ?string {
    $st = db()->prepare('SELECT value FROM settings WHERE key=?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings(key,value) VALUES(?,?)
                   ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$k, $v]);
}

/* ------------------------------------------------------------------ auth -- */
function is_configured(): bool { return setting('password_hash') !== null; }
function is_logged_in(): bool { return !empty($_SESSION['vegha_admin']); }
function require_login(): void {
    if (!is_logged_in()) { header('Location: index.php'); exit; }
}
function rate_limited(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    db()->prepare('DELETE FROM attempts WHERE ts < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM attempts WHERE ip=?');
    $st->execute([$ip]);
    return (int)$st->fetchColumn() >= 8;
}
function record_attempt(): void {
    db()->prepare('INSERT INTO attempts(ip,ts) VALUES(?,?)')
        ->execute([$_SERVER['REMOTE_ADDR'] ?? '0', time()]);
}
/* A correct password clears the record, so a few typos followed by a successful
   sign-in cannot leave the user one attempt away from a lockout. */
function clear_attempts(): void {
    db()->prepare('DELETE FROM attempts WHERE ip=?')->execute([$_SERVER['REMOTE_ADDR'] ?? '0']);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Invalid request token. Go back and retry.');
    }
}

/* ----------------------------------------------------------------- helpers - */
function slugify(string $t): string {
    $t = strtolower(trim($t));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim(preg_replace('/-+/', '-', $t), '-') ?: 'post';
}
/* The slug is the article's filename, so a duplicate would overwrite another
   post's page -- and unpublishing either would delete the survivor's. Suffix
   collisions instead. $ignoreId lets a post keep its own slug when re-saved. */
function unique_slug(string $slug, int $ignoreId = 0): string {
    $st = db()->prepare('SELECT COUNT(*) FROM posts WHERE slug=? AND id<>?');
    $base = $slug;
    for ($n = 2; ; $n++) {
        $st->execute([$slug, $ignoreId]);
        if ((int)$st->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n;
    }
}
function read_mins(string $html): int {
    $words = str_word_count(strip_tags($html));
    return max(1, (int)round($words / 220));
}
function date_h(string $ymd): string {
    $ts = strtotime($ymd) ?: time();
    return date('j F Y', $ts);
}
function tpl(string $name): string { return file_get_contents(TPL_DIR . "/$name.html"); }
function render(string $tpl, array $map): string {
    foreach ($map as $k => $v) $tpl = str_replace('{{' . $k . '}}', (string)$v, $tpl);
    return $tpl;
}
function json_ld(array $o): string {
    return '<script type="application/ld+json">' . json_encode($o, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/* --------------------------------------------------------------- publishing - */
function published_posts(): array {
    return db()->query("SELECT * FROM posts WHERE status='published' ORDER BY published_at DESC, id DESC")->fetchAll();
}
function listing_item(array $p): string {
    $img = $p['featured_img'] ?: 'assets/img/corporate-regulatory-sheet-600.webp';
    return render(tpl('listing-item'), [
        'SLUG' => $p['slug'], 'CATEGORY' => e($p['category'] ?: 'Insights'),
        'DATE_H' => date_h($p['published_at'] ?: $p['created_at']),
        'TITLE' => e($p['title']), 'EXCERPT' => e(mb_substr($p['excerpt'] ?? '', 0, 180)),
        'IMG' => $img,
        'SEARCH' => e(mb_strtolower($p['title'] . ' ' . ($p['excerpt'] ?? '') . ' ' . ($p['tags'] ?? ''))),
    ]);
}
function write_article(array $p, array $all): void {
    $others = array_values(array_filter($all, fn($q) => $q['slug'] !== $p['slug']));
    $related = '';
    foreach (array_slice($others, 0, 3) as $q) {
        $related .= str_replace(['href="insights/', 'src="assets/'],
                                ['href="', 'src="../assets/'], listing_item($q));
    }
    $date = $p['published_at'] ?: date('Y-m-d');
    $ld = json_ld([
        '@context' => 'https://schema.org', '@type' => 'Article',
        'headline' => $p['title'], 'description' => $p['meta_desc'] ?: $p['excerpt'],
        'datePublished' => $date, 'dateModified' => substr($p['updated_at'] ?: $date, 0, 10),
        'author' => ['@type' => 'Organization', 'name' => 'VEGHA & ASSOCIATES', 'url' => SITE_URL],
        'publisher' => ['@id' => SITE_URL . '/#organization'],
        'mainEntityOfPage' => SITE_URL . '/insights/' . $p['slug'] . '.html',
        'image' => SITE_URL . '/' . ($p['featured_img'] ?: 'assets/img/hero-home-desktop-1600.webp'),
    ]) . json_ld([
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Insights', 'item' => SITE_URL . '/insights.html'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $p['short'] ?: $p['title'],
             'item' => SITE_URL . '/insights/' . $p['slug'] . '.html'],
        ]]);
    $html = render(tpl('article'), [
        'META_TITLE' => e($p['meta_title'] ?: $p['title'] . ' | VEGHA & ASSOCIATES'),
        'META_DESC' => e($p['meta_desc'] ?: $p['excerpt']),
        'SLUG' => $p['slug'], 'LD' => $ld,
        'OG_IMG' => $p['featured_img'] ?: 'assets/img/hero-home-desktop-1600.webp',
        'SHORT' => e($p['short'] ?: $p['title']), 'CATEGORY' => e($p['category'] ?: 'Insights'),
        'H1' => $p['h1'] ?: e($p['title']), 'AUTHOR' => e($p['author'] ?: 'The Partners, VEGHA & ASSOCIATES'),
        'DATE_H' => date_h($date), 'READ' => $p['read_mins'] ?: read_mins($p['body_html']),
        'BODY' => $p['body_html'], 'RELATED' => $related,
    ]);
    file_put_contents(ROOT . '/insights/' . $p['slug'] . '.html', $html);
}
function regen_all(): void {
    $posts = published_posts();
    foreach ($posts as $p) write_article($p, $posts);

    /* listing */
    $cats = array_values(array_unique(array_filter(array_column($posts, 'category'))));
    sort($cats);
    $catsHtml = '';
    foreach ($cats as $c) $catsHtml .= '<button type="button" data-cat="' . e($c) . '">' . e($c) . '</button>';
    $items = '';
    foreach ($posts as $p) $items .= listing_item($p);
    file_put_contents(ROOT . '/insights.html', render(tpl('listing'),
        ['CATS' => $catsHtml, 'ITEMS' => $items, 'BUILD' => build_fingerprint()]));

    /* search index */
    $idx = array_map(fn($p) => ['slug' => $p['slug'], 'title' => $p['title'],
        'excerpt' => $p['excerpt'], 'category' => $p['category'],
        'date' => substr($p['published_at'] ?? '', 0, 10)], $posts);
    file_put_contents(ROOT . '/insights/search-index.json', json_encode($idx, JSON_UNESCAPED_UNICODE));

    /* rss */
    $rss = '';
    foreach ($posts as $p) {
        $rss .= '<item><title>' . e($p['title']) . '</title>'
              . '<link>' . SITE_URL . '/insights/' . $p['slug'] . '.html</link>'
              . '<guid>' . SITE_URL . '/insights/' . $p['slug'] . '.html</guid>'
              . '<pubDate>' . date(DATE_RSS, strtotime($p['published_at'] ?: 'now')) . '</pubDate>'
              . '<description>' . e($p['meta_desc'] ?: $p['excerpt']) . '</description></item>';
    }
    file_put_contents(ROOT . '/rss.xml', '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0"><channel><title>VEGHA &amp; ASSOCIATES Insights</title>'
        . '<link>' . SITE_URL . '/insights.html</link>'
        . '<description>Practical notes on Indian tax, audit and business finance.</description>'
        . $rss . '</channel></rss>' . "\n");

    /* sitemap */
    $urls = '';
    foreach (STATIC_PAGES as $pg) {
        $pr = $pg === '' ? '1.0' : '0.8';
        $urls .= '<url><loc>' . SITE_URL . '/' . $pg . '</loc><changefreq>monthly</changefreq><priority>' . $pr . '</priority></url>';
    }
    foreach (CALC_PAGES as $pg) {
        $urls .= '<url><loc>' . SITE_URL . '/' . $pg . '</loc><changefreq>monthly</changefreq><priority>0.8</priority></url>';
    }
    foreach ($posts as $p) {
        $urls .= '<url><loc>' . SITE_URL . '/insights/' . $p['slug'] . '.html</loc>'
               . '<lastmod>' . substr($p['updated_at'] ?: $p['published_at'], 0, 10) . '</lastmod>'
               . '<changefreq>yearly</changefreq><priority>0.7</priority></url>';
    }
    file_put_contents(ROOT . '/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $urls . '</urlset>' . "\n");
}
function unpublish_cleanup(string $slug): void {
    $f = ROOT . '/insights/' . $slug . '.html';
    if (is_file($f)) unlink($f);
}

/* ------------------------------------------------ deploy self-healing ------ *
 * insights.html, rss.xml, sitemap.xml and the article pages are generated but
 * still tracked in git, so a deploy overwrites them with the repository's copies
 * and any article published since silently drops off the listing.
 *
 * Timestamps cannot detect this: a deploy writes stale content with a fresh
 * mtime. So regen_all() stamps the listing with a fingerprint of the published
 * set, and we compare that against what the database says it should be. A deploy
 * restores an older fingerprint, which no longer matches, and the next admin page
 * load rebuilds. When nothing has changed the fingerprints agree and no work is
 * done -- so this does not churn the files on every request. */
function build_fingerprint(): string {
    $parts = array_map(
        fn($p) => $p['id'] . '|' . $p['slug'] . '|' . ($p['updated_at'] ?? ''),
        published_posts()
    );
    return substr(sha1(implode("\n", $parts)), 0, 16);
}
function ensure_generated_fresh(): bool {
    $listing = ROOT . '/insights.html';
    $stamped = null;
    if (is_file($listing)
        && preg_match('/<!-- build:([a-f0-9]{16}) -->/', (string)file_get_contents($listing), $m)) {
        $stamped = $m[1];
    }
    if ($stamped === build_fingerprint()) return false;
    regen_all();
    return true;
}

/* ------------------------------------------------------------- admin chrome - */
function admin_page(string $title, string $body): void {
    $nav = is_logged_in()
        ? '<nav><a href="index.php">Posts</a> <a href="edit.php">+ New post</a> <a href="../insights.html" target="_blank" rel="noopener">View site</a> <a href="logout.php">Log out</a></nav>'
        : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . e($title) . ' | Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a1c38;--navy2:#0f2a52;--gold:#c29a4e;--paper:#fbf9f5;--rule:#e0dacd;--ink:#1b2434;--soft:#6d7687}
*{box-sizing:border-box}body{margin:0;font:15.5px/1.6 Inter,sans-serif;color:var(--ink);background:var(--paper)}
header.bar{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.9rem 4vw;background:var(--navy);color:#fff}
header.bar h1{font:560 1.15rem Fraunces,serif;margin:0;color:#fff}
header.bar nav a{color:#dbe3ee;text-decoration:none;margin-left:1.1rem;font-size:.9rem}
header.bar nav a:hover{color:var(--gold)}
main{max-width:1080px;margin:2rem auto;padding:0 4vw}
.card{background:#fff;border:1px solid var(--rule);border-radius:6px;padding:1.6rem;margin-bottom:1.4rem;box-shadow:0 2px 10px rgba(7,18,36,.05)}
h2{font:560 1.5rem Fraunces,serif;color:var(--navy);margin:.2rem 0 1rem}
label{display:block;font-weight:600;font-size:.85rem;color:var(--navy);margin:.9rem 0 .3rem}
input[type=text],input[type=password],input[type=date],select,textarea{width:100%;padding:.6rem .8rem;border:1px solid var(--rule);border-radius:4px;font:inherit;background:#fff}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(194,154,78,.18)}
.btn{display:inline-block;padding:.65rem 1.3rem;border-radius:4px;border:1px solid transparent;font-weight:600;font-size:.9rem;cursor:pointer;text-decoration:none}
.btn-primary{background:var(--navy);color:#fff}.btn-primary:hover{background:var(--navy2)}
.btn-gold{background:var(--gold);color:var(--navy)}.btn-gold:hover{filter:brightness(1.08)}
.btn-ghost{background:none;border-color:var(--navy);color:var(--navy)}
.btn-danger{background:#fff;border-color:#b3403c;color:#a33531}
table{width:100%;border-collapse:collapse;font-size:.92rem}
th{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--soft);text-align:left;padding:.5rem .6rem;border-bottom:2px solid var(--rule)}
td{padding:.6rem;border-bottom:1px solid var(--rule);vertical-align:middle}
.pill{display:inline-block;padding:.15rem .6rem;border-radius:99px;font-size:.75rem;font-weight:600}
.pill-pub{background:#eef5ee;color:#245b32}.pill-draft{background:#f2e8d2;color:#6b5218}
.grid2{display:grid;gap:1rem 1.4rem;grid-template-columns:1fr 1fr}
@media(max-width:760px){.grid2{grid-template-columns:1fr}}
.msg{padding:.8rem 1rem;border-radius:4px;margin-bottom:1rem;font-size:.92rem}
.msg-ok{background:#eef5ee;color:#245b32}.msg-bad{background:#fbecec;color:#a33531}
.hint{font-size:.78rem;color:var(--soft);margin:.25rem 0 0}
#editor{background:#fff;min-height:340px}
.ql-toolbar{border-radius:4px 4px 0 0;border-color:var(--rule)!important}
.ql-container{border-radius:0 0 4px 4px;border-color:var(--rule)!important;font:inherit}
.thumb{max-width:220px;border-radius:4px;border:1px solid var(--rule);margin-top:.4rem}
</style></head><body>
<header class="bar"><h1>VEGHA &amp; ASSOCIATES · Insights Admin</h1>' . $nav . '</header>
<main>' . $body . '</main></body></html>';
}
