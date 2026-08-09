<?php
require __DIR__ . '/lib.php';

$msg = '';

/* ---- first-run setup: create the admin password ---------------------------- */
if (!is_configured()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $p1 = $_POST['new_password'] ?? '';
        $p2 = $_POST['confirm_password'] ?? '';
        if (strlen($p1) < 10) {
            $msg = '<div class="msg msg-bad">Use at least 10 characters.</div>';
        } elseif ($p1 !== $p2) {
            $msg = '<div class="msg msg-bad">Passwords do not match.</div>';
        } else {
            set_setting('password_hash', password_hash($p1, PASSWORD_DEFAULT));
            $_SESSION['vegha_admin'] = true;
            header('Location: index.php'); exit;
        }
    }
    admin_page('Set up', $msg . '<div class="card" style="max-width:460px;margin-inline:auto">
<h2>First-time setup</h2>
<p>Create the administrator password for the Insights publishing area. Store it in a password manager; it cannot be recovered, only reset by deleting the <code>password_hash</code> row in the database.</p>
<form method="post">
<label for="p1">New password</label><input type="password" id="p1" name="new_password" required minlength="10" autocomplete="new-password">
<label for="p2">Confirm password</label><input type="password" id="p2" name="confirm_password" required autocomplete="new-password">
<p style="margin-top:1.2rem"><button class="btn btn-primary" type="submit">Create password &amp; sign in</button></p>
</form></div>');
    exit;
}

/* ---- login ------------------------------------------------------------------ */
if (!is_logged_in()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (rate_limited()) {
            $msg = '<div class="msg msg-bad">Too many attempts. Wait 15 minutes and try again.</div>';
        } elseif (password_verify($_POST['password'], setting('password_hash'))) {
            clear_attempts();
            session_regenerate_id(true);
            $_SESSION['vegha_admin'] = true;
            header('Location: index.php'); exit;
        } else {
            record_attempt();
            $msg = '<div class="msg msg-bad">Incorrect password.</div>';
        }
    }
    admin_page('Sign in', $msg . '<div class="card" style="max-width:420px;margin-inline:auto">
<h2>Sign in</h2>
<form method="post">
<label for="pw">Password</label><input type="password" id="pw" name="password" required autocomplete="current-password" autofocus>
<p style="margin-top:1.2rem"><button class="btn btn-primary" type="submit">Sign in</button></p>
</form></div>');
    exit;
}

/* ---- actions (publish / unpublish / delete) ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $st = db()->prepare('SELECT * FROM posts WHERE id=?');
    $st->execute([$id]);
    $post = $st->fetch();
    if ($post) {
        if ($_POST['action'] === 'publish') {
            db()->prepare("UPDATE posts SET status='published',
                published_at=COALESCE(published_at, date('now')), updated_at=datetime('now') WHERE id=?")
              ->execute([$id]);
            regen_all();
            $msg = '<div class="msg msg-ok">Published. The article page, listing, feed and sitemap were regenerated.</div>';
        } elseif ($_POST['action'] === 'unpublish') {
            db()->prepare("UPDATE posts SET status='draft', updated_at=datetime('now') WHERE id=?")->execute([$id]);
            unpublish_cleanup($post['slug']);
            regen_all();
            $msg = '<div class="msg msg-ok">Unpublished and removed from the public site.</div>';
        } elseif ($_POST['action'] === 'delete') {
            db()->prepare('DELETE FROM posts WHERE id=?')->execute([$id]);
            unpublish_cleanup($post['slug']);
            regen_all();
            $msg = '<div class="msg msg-ok">Deleted.</div>';
        }
    }
}

/* ---- dashboard ----------------------------------------------------------------- */
/* A deploy overwrites the generated pages with the repository's copies, which can be
   older than the database. Rebuild them if so; a no-op when they already agree. */
if ($msg === '' && ensure_generated_fresh()) {
    $msg = '<div class="msg msg-ok">The public pages were older than your articles &mdash; usually after a site update &mdash; so they were rebuilt.</div>';
}

$posts = db()->query('SELECT * FROM posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC')->fetchAll();
$rows = '';
$tok = csrf_token();
foreach ($posts as $p) {
    $pill = $p['status'] === 'published'
        ? '<span class="pill pill-pub">Published</span>' : '<span class="pill pill-draft">Draft</span>';
    $view = $p['status'] === 'published'
        ? ' <a href="../insights/' . e($p['slug']) . '.html" target="_blank" rel="noopener">View</a>' : '';
    $toggle = $p['status'] === 'published'
        ? '<button class="btn btn-ghost" name="action" value="unpublish">Unpublish</button>'
        : '<button class="btn btn-gold" name="action" value="publish">Publish</button>';
    $rows .= '<tr><td><strong>' . e($p['title']) . '</strong><br>
<span class="hint">' . e($p['category'] ?: 'Uncategorised') . ' · ' . e(substr($p['published_at'] ?? $p['created_at'] ?? '', 0, 10)) . '</span></td>
<td>' . $pill . '</td>
<td style="white-space:nowrap">
<a class="btn btn-ghost" href="edit.php?id=' . $p['id'] . '">Edit</a>
<a class="btn btn-ghost" href="preview.php?id=' . $p['id'] . '" target="_blank" rel="noopener">Preview</a>' . $view . '
</td>
<td style="white-space:nowrap"><form method="post" style="display:inline" onsubmit="return this.action_confirm ? confirm(\'Delete permanently?\') : true">
<input type="hidden" name="csrf" value="' . $tok . '"><input type="hidden" name="id" value="' . $p['id'] . '">'
. $toggle .
' <button class="btn btn-danger" name="action" value="delete" onclick="return confirm(\'Delete this post permanently?\')">Delete</button>
</form></td></tr>';
}
admin_page('Posts', $msg . '<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
<h2 style="margin:0">Articles</h2>
<a class="btn btn-primary" href="edit.php">+ New article</a>
</div>
<table style="margin-top:1.2rem">
<thead><tr><th>Title</th><th>Status</th><th>Open</th><th>Actions</th></tr></thead>
<tbody>' . ($rows ?: '<tr><td colspan="4">No posts yet. Create your first article.</td></tr>') . '</tbody>
</table>
<p class="hint" style="margin-top:1rem">Publishing regenerates the article page, the Insights listing, the search index, the RSS feed and sitemap.xml automatically. No other step is needed.</p>
</div>');
