<?php
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$msg = '';
$post = ['id' => 0, 'slug' => '', 'title' => '', 'h1' => '', 'short' => '', 'excerpt' => '',
         'meta_title' => '', 'meta_desc' => '', 'category' => '', 'tags' => '',
         'author' => 'The Partners, VEGHA & ASSOCIATES', 'featured_img' => '',
         'body_html' => '', 'status' => 'draft', 'published_at' => ''];

if ($id) {
    $st = db()->prepare('SELECT * FROM posts WHERE id=?');
    $st->execute([$id]);
    $found = $st->fetch();
    if ($found) $post = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['title','h1','short','excerpt','meta_title','meta_desc','category','tags','author','body_html','published_at'] as $f) {
        $post[$f] = trim($_POST[$f] ?? '');
    }
    $post['slug'] = unique_slug(slugify($_POST['slug'] ?: $post['title']), (int)$post['id']);
    if ($post['title'] === '' || trim(strip_tags($post['body_html'])) === '') {
        $msg = '<div class="msg msg-bad">A title and article body are required.</div>';
    } else {
        /* featured image upload */
        if (!empty($_FILES['featured']['tmp_name']) && is_uploaded_file($_FILES['featured']['tmp_name'])) {
            require_once __DIR__ . '/edit_helpers.php';
            $saved = save_image_shared($_FILES['featured'], $post['slug'] . '-featured');
            if ($saved) $post['featured_img'] = $saved;
            else $msg .= '<div class="msg msg-bad">Featured image was not a valid JPG, PNG or WebP under 5 MB.</div>';
        }
        $now = date('Y-m-d H:i:s');
        $wantPublish = ($_POST['do'] ?? '') === 'publish';
        if ($wantPublish) {
            $post['status'] = 'published';
            if (!$post['published_at']) $post['published_at'] = date('Y-m-d');
        }
        $read = read_mins($post['body_html']);
        if ($post['id']) {
            db()->prepare('UPDATE posts SET slug=?,title=?,h1=?,short=?,excerpt=?,meta_title=?,meta_desc=?,
                category=?,tags=?,author=?,featured_img=?,body_html=?,read_mins=?,status=?,published_at=?,updated_at=?
                WHERE id=?')->execute([
                $post['slug'],$post['title'],$post['h1'],$post['short'],$post['excerpt'],$post['meta_title'],
                $post['meta_desc'],$post['category'],$post['tags'],$post['author'],$post['featured_img'],
                $post['body_html'],$read,$post['status'],$post['published_at'] ?: null,$now,$post['id']]);
        } else {
            db()->prepare('INSERT INTO posts(slug,title,h1,short,excerpt,meta_title,meta_desc,category,tags,author,
                featured_img,body_html,read_mins,status,published_at,created_at,updated_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                $post['slug'],$post['title'],$post['h1'],$post['short'],$post['excerpt'],$post['meta_title'],
                $post['meta_desc'],$post['category'],$post['tags'],$post['author'],$post['featured_img'],
                $post['body_html'],$read,$post['status'],$post['published_at'] ?: null,$now,$now]);
            $post['id'] = (int)db()->lastInsertId();
        }
        if ($post['status'] === 'published') {
            regen_all();
            $msg .= '<div class="msg msg-ok">Saved and published. Public pages regenerated.</div>';
        } else {
            $msg .= '<div class="msg msg-ok">Draft saved.</div>';
        }
        if (($_POST['do'] ?? '') === 'preview') {
            header('Location: preview.php?id=' . $post['id']); exit;
        }
    }
}


$tok = csrf_token();
$thumb = $post['featured_img'] ? '<img class="thumb" src="../' . e($post['featured_img']) . '" alt="Current featured image">' : '';
$body = $msg . '<form method="post" enctype="multipart/form-data" id="postform">
<input type="hidden" name="csrf" value="' . $tok . '">
<input type="hidden" name="id" value="' . (int)$post['id'] . '">
<input type="hidden" name="body_html" id="body_html">
<div class="card">
<h2>' . ($post['id'] ? 'Edit article' : 'New article') . '</h2>
<label for="title">Title *</label>
<input type="text" id="title" name="title" required value="' . e($post['title']) . '">
<div class="grid2">
<div><label for="slug">URL slug</label>
<input type="text" id="slug" name="slug" value="' . e($post['slug']) . '" placeholder="auto-generated from title">
<p class="hint">Lowercase letters, numbers and hyphens. Changing it after publishing changes the article URL.</p></div>
<div><label for="category">Category</label>
<input type="text" id="category" name="category" list="cats" value="' . e($post['category']) . '" placeholder="e.g. GST">
<datalist id="cats"><option>Assurance</option><option>GST</option><option>Direct Tax</option><option>Business Advisory</option><option>Markets</option><option>Compliance</option></datalist></div>
</div>
<div class="grid2">
<div><label for="tags">Tags (comma separated)</label>
<input type="text" id="tags" name="tags" value="' . e($post['tags']) . '"></div>
<div><label for="author">Author</label>
<input type="text" id="author" name="author" value="' . e($post['author']) . '"></div>
</div>
<div class="grid2">
<div><label for="published_at">Publish date</label>
<input type="date" id="published_at" name="published_at" value="' . e(substr((string)$post['published_at'], 0, 10)) . '">
<p class="hint">Left blank, the date you press Publish is used.</p></div>
<div><label for="featured">Featured image</label>
<input type="file" id="featured" name="featured" accept=".jpg,.jpeg,.png,.webp">' . $thumb . '
<p class="hint">Landscape works best (around 1200 x 675). Resized automatically.</p></div>
</div>
</div>

<div class="card">
<h2>Article body *</h2>
<div id="editor">' . $post['body_html'] . '</div>
<p class="hint">Use Heading 2 for section headings inside the article. Images inserted here are uploaded and resized automatically.</p>
</div>

<div class="card">
<h2>SEO &amp; listing</h2>
<label for="excerpt">Excerpt (listing card and default meta description)</label>
<textarea id="excerpt" name="excerpt" rows="2" maxlength="200">' . e($post['excerpt']) . '</textarea>
<div class="grid2">
<div><label for="meta_title">Meta title</label>
<input type="text" id="meta_title" name="meta_title" maxlength="70" value="' . e($post['meta_title']) . '" placeholder="defaults to the article title">
<p class="hint">Aim for under 60 characters.</p></div>
<div><label for="meta_desc">Meta description</label>
<input type="text" id="meta_desc" name="meta_desc" maxlength="170" value="' . e($post['meta_desc']) . '" placeholder="defaults to the excerpt">
<p class="hint">Aim for under 155 characters.</p></div>
</div>
<div class="grid2">
<div><label for="short">Short name (breadcrumb)</label>
<input type="text" id="short" name="short" maxlength="40" value="' . e($post['short']) . '" placeholder="defaults to the title"></div>
<div><label for="h1">Display heading (optional HTML h1)</label>
<input type="text" id="h1" name="h1" value="' . e($post['h1']) . '" placeholder="defaults to the title">
<p class="hint">May contain &lt;em class="accent"&gt;…&lt;/em&gt; for a gold italic word.</p></div>
</div>
</div>

<p style="display:flex;gap:.7rem;flex-wrap:wrap">
<button class="btn btn-ghost" name="do" value="draft">Save draft</button>
<button class="btn btn-ghost" name="do" value="preview">Save &amp; preview</button>
<button class="btn btn-gold" name="do" value="publish" onclick="return confirm(\'Publish this article to the public site?\')">' .
($post['status'] === 'published' ? 'Update published article' : 'Publish') . '</button>
<a class="btn btn-danger" href="index.php" style="margin-left:auto">Back</a>
</p>
</form>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
var quill = new Quill("#editor", {
  theme: "snow",
  modules: { toolbar: {
    container: [
      [{ header: [2, 3, false] }],
      ["bold", "italic", "underline"],
      [{ list: "ordered" }, { list: "bullet" }],
      ["blockquote", "link", "image"],
      ["clean"]
    ],
    handlers: {
      image: function () {
        var input = document.createElement("input");
        input.type = "file"; input.accept = "image/*";
        input.onchange = function () {
          var fd = new FormData();
          fd.append("image", input.files[0]);
          fd.append("csrf", ' . json_encode($tok) . ');
          fetch("upload.php", { method: "POST", body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              if (d.url) {
                var range = quill.getSelection(true);
                quill.insertEmbed(range.index, "image", "../" + d.url);
              } else alert(d.error || "Upload failed");
            });
        };
        input.click();
      }
    }
  } }
});
document.getElementById("postform").addEventListener("submit", function () {
  document.getElementById("body_html").value = quill.getSemanticHTML();
});
</script>';
admin_page($post['id'] ? 'Edit article' : 'New article', $body);
