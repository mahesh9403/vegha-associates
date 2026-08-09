<?php
/* Shared image processing for edit.php (featured) and upload.php (inline). */
function save_image_shared(array $file, string $base): ?string {
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $info = @getimagesize($file['tmp_name']);
    if (!$info) return null;
    $img = match ($info['mime']) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default => null,
    };
    if (!$img) return null;
    $w = imagesx($img); $h = imagesy($img);
    if ($w > 1200) {
        $nh = (int)round($h * 1200 / $w);
        $r = imagecreatetruecolor(1200, $nh);
        imagecopyresampled($r, $img, 0, 0, 0, 0, 1200, $nh, $w, $h);
        imagedestroy($img); $img = $r;
    }
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = $base . '-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.webp';
    $ok = function_exists('imagewebp') && imagewebp($img, UPLOAD_DIR . '/' . $name, 82);
    if (!$ok) { $name = str_replace('.webp', '.jpg', $name); imagejpeg($img, UPLOAD_DIR . '/' . $name, 84); }
    imagedestroy($img);
    return UPLOAD_URL . '/' . $name;
}
