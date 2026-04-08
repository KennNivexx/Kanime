<?php
@ob_start();
@ini_set('session.cookie_httponly', 1);
@ini_set('session.use_strict_mode', 1);
@ini_set('session.cookie_samesite', 'Lax'); 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
@header_remove('X-Frame-Options');

$host = 'localhost';
$db   = 'anime_web';
$user = 'root';   // ← ganti sesuai database InfinityFree kamu
$pass = '';       // ← ganti sesuai password database InfinityFree kamu

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal. Error: " . htmlspecialchars($e->getMessage()));
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

// Function to save image as binary blob to DB
// Returns the binary data or false on failure
function getCoverImageBinary($file) {
    $allowTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($fileType, $allowTypes) && $file['error'] == 0) {
        $data = file_get_contents($file['tmp_name']);
        return $data;
    }
    return false;
}

// Function to output image from binary data as base64 data URI
function coverDataUri($binaryData) {
    if (!$binaryData) return '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($binaryData);
    if (!$mime || !str_starts_with($mime, 'image/')) $mime = 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($binaryData);
}

// Normalize video link to embeddable URL
function normalizeVideoLink($link) {
    $link = trim($link);
    
    // Already an iframe tag
    if (stripos($link, '<iframe') !== false) {
        return ['type' => 'iframe', 'src' => $link];
    }
    
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=0&rel=0'];
    }
    
    // Dailymotion — logo=0 hide branding, related=0 & queue-enable=0 disable random videos
    $dmParams = '?logo=0&related=0&queue-enable=0&endscreen-enable=0&sharing-enable=0';
    if (preg_match('/dai\.ly\/([a-zA-Z0-9]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://www.dailymotion.com/embed/video/' . $m[1] . $dmParams];
    }
    if (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://www.dailymotion.com/embed/video/' . $m[1] . $dmParams];
    }
    // Already an embed URL (e.g. dailymotion.com/embed/video/xxx)
    if (preg_match('/dailymotion\.com\/embed\/video\/([a-zA-Z0-9]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://www.dailymotion.com/embed/video/' . $m[1] . $dmParams];
    }
    
    // Google Drive
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_\-]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://drive.google.com/file/d/' . $m[1] . '/preview'];
    }
    if (preg_match('/drive\.google\.com\/open\?id=([a-zA-Z0-9_\-]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://drive.google.com/file/d/' . $m[1] . '/preview'];
    }
    
    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
    }
    
    // Streamable
    if (preg_match('/streamable\.com\/([a-zA-Z0-9]+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://streamable.com/e/' . $m[1]];
    }
    
    // Bilibili
    if (preg_match('/bilibili\.com\/video\/(BV[a-zA-Z0-9]+|av\d+)/', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://player.bilibili.com/player.html?bvid=' . $m[1]];
    }
    
    // DoodStream (Otomatis ubah link /d/ atau /v/ jadi format embed /e/)
    if (preg_match('/(https?:\/\/(?:dood[^\/]+|d[0o]+d[^\/]+|ds2play\.com|ds2video\.com))\/[dve]\/([a-zA-Z0-9]+)/i', $link, $m)) {
        return ['type' => 'embed', 'src' => $m[1] . '/e/' . $m[2]];
    }
    
    // Streamtape (Otomatis ubah /v/ ke /e/ untuk embed)
    if (preg_match('/(https?:\/\/streamtape\.[a-z]+)\/[ve]\/([a-zA-Z0-9_-]+)/i', $link, $m)) {
        return ['type' => 'embed', 'src' => $m[1] . '/e/' . $m[2]];
    }
    
    // Mp4upload
    if (preg_match('/mp4upload\.com\/(?:embed-)?([a-zA-Z0-9]+)/i', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://www.mp4upload.com/embed-' . $m[1] . '.html'];
    }
    
    // OK.ru (Odnoklassniki)
    if (preg_match('/ok\.ru\/video\/(\d+)/i', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://ok.ru/videoembed/' . $m[1]];
    }
    
    // VK.com (Vkontakte)
    if (preg_match('/vk\.com\/video_ext\.php\?(.*)/i', $link, $m)) {
        return ['type' => 'embed', 'src' => 'https://vk.com/video_ext.php?' . $m[1]];
    }
    
    // Direct video link (mp4, mkv, webm, etc.)
    if (preg_match('/\.(mp4|webm|ogg|mkv)(\?.*)?$/i', $link)) {
        return ['type' => 'video', 'src' => $link];
    }
    
    // Fallback: treat as embed URL
    return ['type' => 'embed', 'src' => $link];
}
?>
