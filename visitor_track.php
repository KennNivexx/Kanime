<?php
require_once 'db.php';

// Auto-create visitor log table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page VARCHAR(255) NOT NULL,
            anime_id INT DEFAULT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent TEXT,
            is_mobile TINYINT(1) DEFAULT 0,
            visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visited_at (visited_at),
            INDEX idx_page (page),
            INDEX idx_anime_id (anime_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch(PDOException $e) {
    // Abaikan jika sudah ada
}

// Ambil data pengunjung
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]); // Ambil IP pertama jika ada proxy
$ipHash = hash('sha256', $ip . date('Y-m-d')); // Anonymize: hash harian (privacy-safe)

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Deteksi HP vs PC
$isMobile = (int) preg_match('/(Android|iPhone|iPad|iPod|BlackBerry|Windows Phone|Mobile)/i', $userAgent);

$page = $_GET['page'] ?? 'index';
$animeId = isset($_GET['anime_id']) && is_numeric($_GET['anime_id']) ? (int)$_GET['anime_id'] : null;

// Simpan kunjungan (max 1 kunjungan per IP per halaman per menit utk mencegah spam)
try {
    $check = $pdo->prepare("SELECT id FROM visitor_log WHERE ip_hash = ? AND page = ? AND visited_at > (NOW() - INTERVAL 1 MINUTE)");
    $check->execute([$ipHash, $page]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO visitor_log (page, anime_id, ip_hash, user_agent, is_mobile) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$page, $animeId, $ipHash, $userAgent, $isMobile]);
    }
} catch(PDOException $e) {
    // error tracking bukan merupakan blocker halaman
}

// Return 1x1 invisible pixel (tracking pixel style)
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=');
exit;
