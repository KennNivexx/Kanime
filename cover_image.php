<?php
/**
 * Serve cover image binary dari database
 * Usage: cover_image.php?id=<anime_id>
 */
require_once 'db.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("SELECT cover_image FROM anime WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || empty($row['cover_image'])) {
    // Return placeholder image (1x1 transparent pixel)
    http_response_code(404);
    exit;
}

$data = $row['cover_image'];
if (is_resource($data)) {
    $data = stream_get_contents($data);
}

// Detect MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($data);
if (!$mime || !str_starts_with($mime, 'image/')) {
    $mime = 'image/jpeg';
}

// Cache headers (1 day)
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . strlen($data));
echo $data;
exit;
?>
