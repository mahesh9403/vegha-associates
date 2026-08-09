<?php
require __DIR__ . '/lib.php';
require_login();
$st = db()->prepare('SELECT * FROM posts WHERE id=?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) { http_response_code(404); exit('Post not found'); }

/* Render with the real article template, but do not write anything to disk. */
$others = array_values(array_filter(published_posts(), fn($q) => $q['slug'] !== $p['slug']));
$related = '';
foreach (array_slice($others, 0, 3) as $q) {
    $related .= str_replace(['href="insights/', 'src="assets/'], ['href="../insights/', 'src="../assets/'], listing_item($q));
}
$date = $p['published_at'] ?: date('Y-m-d');
$html = render(tpl('article'), [
    'META_TITLE' => e($p['meta_title'] ?: $p['title']), 'META_DESC' => e($p['meta_desc'] ?: $p['excerpt']),
    'SLUG' => $p['slug'], 'LD' => '', 'OG_IMG' => $p['featured_img'] ?: 'assets/img/hero-home-desktop-1600.webp',
    'SHORT' => e($p['short'] ?: $p['title']), 'CATEGORY' => e($p['category'] ?: 'Insights'),
    'H1' => $p['h1'] ?: e($p['title']), 'AUTHOR' => e($p['author'] ?: 'The Partners, VEGHA & ASSOCIATES'),
    'DATE_H' => date_h($date), 'READ' => $p['read_mins'] ?: read_mins($p['body_html']),
    'BODY' => $p['body_html'], 'RELATED' => $related,
]);
/* The article template's paths are all "../" relative, and /admin/ sits at the same
   depth as /insights/, so they already resolve correctly here -- nothing to rewrite. */
$banner = '<div style="position:sticky;top:0;z-index:999;background:#c29a4e;color:#071224;font:600 13px/1.4 Inter,sans-serif;text-align:center;padding:8px 16px">PREVIEW · ' .
          ($p['status'] === 'published' ? 'published article' : 'unpublished draft') .
          ' · <a href="edit.php?id=' . (int)$p['id'] . '" style="color:#071224">back to editor</a></div>';
echo str_replace('<body>', '<body>' . $banner, $html);
