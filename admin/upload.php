<?php
require __DIR__ . '/lib.php';
require_login();
header('Content-Type: application/json');
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    echo json_encode(['error' => 'Invalid token']); exit;
}
if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    echo json_encode(['error' => 'No file received']); exit;
}
require_once __DIR__ . '/edit_helpers.php';
$url = save_image_shared($_FILES['image'], 'inline');
echo json_encode($url ? ['url' => $url] : ['error' => 'Not a valid JPG, PNG or WebP under 5 MB']);
