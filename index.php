<?php
require_once 'db.php';

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'A-Z';
$selectedGenre = $_GET['genre'] ?? '';

$whereClauses = [];
$params = [];

if ($search) {
    $whereClauses[] = "a.judul LIKE ?";
    $params[] = "%$search%";
}
if ($selectedGenre) {
    $whereClauses[] = "a.genre LIKE ?";
    $params[] = "%$selectedGenre%";
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Mendukung password yg ter-hash (aman), atau password text biasa kalau diubah manual lewat phpMyAdmin
    if ($admin && ($password === $admin['password'] || password_verify($password, $admin['password']))) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Username atau password salah!";
    }
}

$whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";
$orderSQL = "ORDER BY a.judul ASC";
switch ($filter) {
    case 'Z-A':   $orderSQL = "ORDER BY a.judul DESC"; break;
    case '10-1':  $orderSQL = "ORDER BY a.rating DESC"; break;
    case '1-10':  $orderSQL = "ORDER BY a.rating ASC"; break;
}

// Fetch Anime List (exclude blob column for performance)
$stmt = $pdo->prepare("SELECT a.id, a.judul, a.rating, a.jumlah_episode, a.genre, a.rekomendasi, (a.cover_image IS NOT NULL AND a.cover_image != '') AS has_cover FROM anime a $whereSQL $orderSQL");
$stmt->execute($params);
$animes = $stmt->fetchAll();

// Fetch Recommendations (exclude blob)
$stmtRec = $pdo->query("SELECT id, judul, rating, rekomendasi, urutan_rekomendasi, (cover_image IS NOT NULL AND cover_image != '') AS has_cover FROM anime WHERE rekomendasi = 1 ORDER BY urutan_rekomendasi ASC");
$recommendations = $stmtRec->fetchAll();

// Fetch all genres
$stmtGenre = $pdo->query("SELECT genre FROM anime WHERE genre IS NOT NULL AND genre != ''");
$rawGenres = $stmtGenre->fetchAll(PDO::FETCH_COLUMN);
$allGenres = [];
foreach ($rawGenres as $gStr) {
    foreach (explode(',', $gStr) as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $allGenres)) $allGenres[] = $p;
    }
}
sort($allGenres);

$laporan_rusak = [];
$stats = null;

if (isAdmin()) {
    // --- Laporan Video Rusak ---
    try {
        $stmtLapor = $pdo->query("SELECT e.id, e.nomor_episode, a.judul, a.id as anime_id, e.laporan_rusak, e.laporan_pesan FROM episode e JOIN anime a ON e.anime_id = a.id WHERE e.laporan_rusak > 0 ORDER BY e.laporan_rusak DESC");
        if ($stmtLapor) {
            $laporan_rusak = $stmtLapor->fetchAll();
        }
    } catch(PDOException $e) {
        try {
            $pdo->query("ALTER TABLE episode ADD COLUMN laporan_pesan TEXT");
            $stmtLapor = $pdo->query("SELECT e.id, e.nomor_episode, a.judul, a.id as anime_id, e.laporan_rusak, e.laporan_pesan FROM episode e JOIN anime a ON e.anime_id = a.id WHERE e.laporan_rusak > 0 ORDER BY e.laporan_rusak DESC");
            if ($stmtLapor) { $laporan_rusak = $stmtLapor->fetchAll(); }
        } catch(PDOException $e2) {}
    }
    
    // --- Statistik Pengunjung ---
    try {
        // Pastikan tabel ada
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_log (id INT AUTO_INCREMENT PRIMARY KEY, page VARCHAR(255) NOT NULL, anime_id INT DEFAULT NULL, ip_hash VARCHAR(64) NOT NULL, user_agent TEXT, is_mobile TINYINT(1) DEFAULT 0, visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_visited_at (visited_at), INDEX idx_anime_id (anime_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stats = [];
        
        // Total kunjungan hari ini (unique IP hash)
        $r = $pdo->query("SELECT COUNT(DISTINCT ip_hash) as total FROM visitor_log WHERE DATE(visited_at) = CURDATE()");
        $stats['today_unique'] = $r->fetchColumn();
        
        // Total kunjungan hari ini (semua hit)
        $r = $pdo->query("SELECT COUNT(*) as total FROM visitor_log WHERE DATE(visited_at) = CURDATE()");
        $stats['today_hits'] = $r->fetchColumn();
        
        // Total all-time unik
        $r = $pdo->query("SELECT COUNT(DISTINCT ip_hash) as total FROM visitor_log");
        $stats['alltime_unique'] = $r->fetchColumn();
        
        // Mobile vs Desktop hari ini
        $r = $pdo->query("SELECT is_mobile, COUNT(*) as cnt FROM visitor_log WHERE DATE(visited_at) = CURDATE() GROUP BY is_mobile");
        $devData = ['mobile' => 0, 'desktop' => 0];
        foreach ($r->fetchAll() as $row) {
            if ($row['is_mobile']) $devData['mobile'] = $row['cnt'];
            else $devData['desktop'] = $row['cnt'];
        }
        $stats['devices'] = $devData;
        
        // Top 5 anime paling ditonton (berdasarkan kunjungan page nonton)
        $r = $pdo->query("SELECT a.judul, COUNT(v.id) as views FROM visitor_log v JOIN anime a ON v.anime_id = a.id WHERE v.anime_id IS NOT NULL GROUP BY v.anime_id, a.judul ORDER BY views DESC LIMIT 5");
        $stats['top_anime'] = $r->fetchAll();
        
        // Trafik per jam hari ini (24 jam)
        $r = $pdo->query("SELECT HOUR(visited_at) as jam, COUNT(*) as cnt FROM visitor_log WHERE DATE(visited_at) = CURDATE() GROUP BY jam ORDER BY jam ASC");
        $hourData = array_fill(0, 24, 0);
        foreach ($r->fetchAll() as $row) { $hourData[(int)$row['jam']] = (int)$row['cnt']; }
        $stats['hourly'] = $hourData;
        
        // Total Anime di database
        $stats['total_anime'] = $pdo->query("SELECT COUNT(*) FROM anime")->fetchColumn();
        
        // Total Episode
        $stats['total_episode'] = $pdo->query("SELECT COUNT(*) FROM episode")->fetchColumn();
        
    } catch(PDOException $e) {
        $stats = null; // Abaikan, tabel mungkin belum ada
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KAnime — Nonton Anime Terlengkap</title>
    <meta name="description" content="KAnime — temukan dan tonton anime favoritmu dengan mudah dan gratis.">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .lapor-msg-block {
            margin-top: 8px;
            background: rgba(0,0,0,0.2);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #dcdde1;
            line-height: 1.5;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
        }
        /* ===== Statistik Admin ===== */
        .stats-panel {
            max-width: 1400px;
            margin: 0 auto 0;
            padding: 20px clamp(14px, 3vw, 28px) 4px;
        }
        .stats-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-3);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: border-color var(--t);
        }
        .stat-card:hover { border-color: var(--primary); }
        .stat-card-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-3);
        }
        .stat-card-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
        }
        .stat-card-sub {
            font-size: 11px;
            color: var(--text-3);
        }
        .stat-card.primary .stat-card-value { color: var(--primary); }
        .stat-card.green .stat-card-value { color: #2ed573; }
        .stat-card.orange .stat-card-value { color: #ffa502; }
        .stat-card.red .stat-card-value { color: #ff4757; }
        
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        .stats-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 16px;
        }
        .stats-box-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 12px;
        }
        /* Top Anime List */
        .top-anime-list { display: flex; flex-direction: column; gap: 8px; }
        .top-anime-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-anime-rank {
            width: 22px; height: 22px;
            background: var(--bg-surface);
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-3);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .top-anime-rank.gold { background: rgba(255,165,0,0.2); color: #ffa502; }
        .top-anime-rank.silver { background: rgba(192,192,192,0.2); color: #b2bec3; }
        .top-anime-rank.bronze { background: rgba(205,127,50,0.2); color: #cd7f32; }
        .top-anime-name { font-size: 13px; font-weight: 500; color: var(--text-2); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .top-anime-views { font-size: 11px; color: var(--primary); font-weight: 700; flex-shrink: 0; }
        /* Device Bar */
        .device-bar-wrap { display: flex; flex-direction: column; gap: 6px; }
        .device-bar-label { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-2); }
        .device-bar-track { height: 8px; background: var(--bg-surface); border-radius: 99px; overflow: hidden; }
        .device-bar-fill { height: 100%; border-radius: 99px; }
        .device-bar-fill.mobile { background: var(--primary); }
        .device-bar-fill.desktop { background: #2ed573; }
        /* Hourly mini chart */
        .hour-chart {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            height: 50px;
            margin-top: 8px;
        }
        .hour-bar {
            flex: 1;
            background: rgba(139,108,247,0.5);
            border-radius: 3px 3px 0 0;
            min-height: 2px;
            transition: background var(--t);
            cursor: default;
            position: relative;
        }
        .hour-bar:hover { background: var(--primary); }
        .hour-bar[data-hour]:hover::after {
            content: attr(data-tip);
            position: absolute;
            bottom: calc(100% + 4px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-elevated);
            color: var(--text);
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10;
        }
        @media (max-width: 640px) {
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo-link">
            <img src="assets/logo.png" alt="KAnime" class="logo-img">
        </a>
        
        <div class="nav-center">
            <form action="index.php" method="GET" style="margin:0;">
                <div class="search-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Cari anime..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </form>
            <div class="dropdown">
                <button type="button" class="nav-pill" onclick="toggleDropdown('genreDropdown', event)">
                    Genre <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div id="genreDropdown" class="dropdown-content">
                    <div class="dropdown-inner">
                        <span class="dropdown-label">Pilih Genre</span>
                        <div class="genre-grid">
                            <?php foreach($allGenres as $gName): ?>
                                <a href="index.php?genre=<?= urlencode($gName) ?>" class="genre-tag <?= $selectedGenre == $gName ? 'active' : '' ?>"><?= htmlspecialchars($gName) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dropdown filter-dropdown">
                <button type="button" class="nav-pill" onclick="toggleDropdown('filterDropdown', event)">
                    Urutkan <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div id="filterDropdown" class="dropdown-content">
                    <div class="dropdown-inner">
                        <span class="dropdown-label">Urutan</span>
                        <a href="index.php?filter=A-Z" class="filter-opt <?= $filter=='A-Z'?'active':'' ?>">A → Z</a>
                        <a href="index.php?filter=Z-A" class="filter-opt <?= $filter=='Z-A'?'active':'' ?>">Z → A</a>
                        <a href="index.php?filter=10-1" class="filter-opt <?= $filter=='10-1'?'active':'' ?>">⭐ Rating Tertinggi</a>
                        <a href="index.php?filter=1-10" class="filter-opt <?= $filter=='1-10'?'active':'' ?>">⭐ Rating Terendah</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="nav-right">
            <?php if(isAdmin()): ?>
                <a href="edit_admin.php" class="nav-action-btn ghost-btn" title="Pengaturan Akun" style="display:flex; align-items:center;">⚙️ Akun</a>
                <a href="tambah.php" class="nav-action-btn primary-btn">+ Tambah</a>
                <a href="logout.php" class="nav-action-btn ghost-btn">Keluar</a>
            <?php else: ?>
                <button onclick="document.getElementById('loginOverlay').style.display='flex';" class="nav-action-btn primary-btn">Masuk</button>
            <?php endif; ?>
        </div>

        <!-- Hamburger (mobile) -->
        <button class="hamburger" id="hamburger" aria-label="Menu" onclick="toggleMobileNav()">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav" id="mobileNav">
        <form action="index.php" method="GET" class="mobile-search">
            <div class="search-wrap" style="width:100%;">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="search-input" style="width:100%;" placeholder="Cari anime..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
        <div class="mobile-nav-row">
            <a href="index.php?filter=A-Z" class="mobile-nav-btn">A–Z</a>
            <a href="index.php?filter=10-1" class="mobile-nav-btn">⭐ Rating</a>
            <?php foreach(array_slice($allGenres, 0, 6) as $gName): ?>
            <a href="index.php?genre=<?= urlencode($gName) ?>" class="mobile-nav-btn <?= $selectedGenre==$gName?'genre-tag active':'' ?>"><?= htmlspecialchars($gName) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="mobile-nav-row" style="margin-top:10px;">
            <?php if(isAdmin()): ?>
                <a href="edit_admin.php" class="mobile-nav-btn ghost-btn">⚙️ Pengaturan Akun</a>
                <a href="tambah.php" class="mobile-nav-btn primary-btn">+ Tambah Anime</a>
                <a href="logout.php" class="mobile-nav-btn ghost-btn">Keluar</a>
            <?php else: ?>
                <button onclick="document.getElementById('mobileNav').classList.remove('open'); document.getElementById('hamburger').classList.remove('open'); document.getElementById('loginOverlay').style.display='flex';" class="mobile-nav-btn primary-btn">Masuk Admin</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Genre/Search active filter indicator -->
    <?php if($selectedGenre || $search): ?>
    <div class="active-filter-bar">
        <?php if($selectedGenre): ?>
            <span class="filter-badge">Genre: <strong><?= htmlspecialchars($selectedGenre) ?></strong> <a href="index.php" class="filter-clear">×</a></span>
        <?php endif; ?>
        <?php if($search): ?>
            <span class="filter-badge">Pencarian: <strong><?= htmlspecialchars($search) ?></strong> <a href="index.php" class="filter-clear">×</a></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Admin Notification (Lapor Rusak) -->
    <?php if(isAdmin() && !empty($laporan_rusak)): ?>
    <div class="admin-lapor-alert">
        <div class="lapor-header">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <h3>⚠️ PERHATIAN: <?= count($laporan_rusak) ?> Video Dilaporkan Rusak!</h3>
        </div>
        <div class="lapor-list">
            <?php foreach($laporan_rusak as $lapor): ?>
            <div class="lapor-item" style="flex-direction:column; align-items:stretch;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span class="lapor-judul"><?= htmlspecialchars($lapor['judul']) ?> (Eps <?= $lapor['nomor_episode'] ?>)</span>
                        <span class="lapor-count" style="margin-left:8px;"><?= $lapor['laporan_rusak'] ?> laporan</span>
                    </div>
                    <a href="tambah.php?id=<?= $lapor['anime_id'] ?>#eps-<?= $lapor['id'] ?>" class="lapor-btn-fix">Perbaiki</a>
                </div>
                <?php if(!empty($lapor['laporan_pesan'])): ?>
                <div class="lapor-msg-block">
                    <?= $lapor['laporan_pesan'] ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recommendation Section -->
    <?php if(empty($search) && empty($selectedGenre) && !empty($recommendations)): ?>
    <section class="recommendation-sec">
        <div class="carousel-wrapper">
            <button class="carousel-btn prev-btn" onclick="slideCarousel(-1)" aria-label="Sebelumnya">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="rec-carousel" id="recCarousel">
                <?php $rank = 1; foreach($recommendations as $rec): ?>
                <div class="rec-item-slide">
                    <a href="detail.php?id=<?= $rec['id'] ?>" class="rec-item-content">
                        <div class="rec-backdrop" style="background-image: url('<?= $rec['has_cover'] ? 'cover_image.php?id='.$rec['id'] : '' ?>');"></div>
                        <div class="rec-gradient-overlay"></div>
                        <div class="rec-slide-inner">
                            <?php if($rec['has_cover']): ?>
                            <img src="cover_image.php?id=<?= $rec['id'] ?>" alt="<?= htmlspecialchars($rec['judul']) ?>" class="rec-poster">
                            <?php else: ?>
                            <div class="rec-poster-placeholder">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <?php endif; ?>
                            <div class="rec-info-box">
                                <div class="rec-rank-text">TOP #<?= $rank++ ?></div>
                                <h2 class="rec-title-huge"><?= htmlspecialchars($rec['judul']) ?></h2>
                                <?php if(isset($rec['rating'])): ?>
                                    <div class="rec-rating-box">⭐ <?= htmlspecialchars($rec['rating']) ?></div>
                                <?php endif; ?>
                                <div class="rec-watch-btn">Tonton Sekarang →</div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-btn next-btn" onclick="slideCarousel(1)" aria-label="Selanjutnya">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <div class="carousel-dots" id="carouselDots"></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Anime Section -->
    <div class="main-section-header">
        <h2 class="main-section-title">
            <?= $selectedGenre ? 'Genre: '.htmlspecialchars($selectedGenre) : ($search ? 'Hasil: '.htmlspecialchars($search) : 'Koleksi Anime') ?>
        </h2>
        <span class="anime-count"><?= count($animes) ?> anime</span>
    </div>

    <main class="anime-container">
        <div class="anime-grid">
            <?php foreach($animes as $a): ?>
            <div class="anime-card">
                <?php if(isAdmin()): ?>
                <div class="admin-controls">
                    <a href="tambah.php?id=<?= $a['id'] ?>" class="admin-btn edit-btn" title="Edit anime">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <button type="button" class="admin-btn delete-btn" title="Hapus anime" onclick="showDeleteModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['judul'])) ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>
                <?php endif; ?>
                <a href="detail.php?id=<?= $a['id'] ?>" class="card-link">
                    <div class="anime-cover-wrap">
                        <?php if($a['has_cover']): ?>
                        <img src="cover_image.php?id=<?= $a['id'] ?>" alt="<?= htmlspecialchars($a['judul']) ?>" class="anime-cover">
                        <?php else: ?>
                        <div class="cover-fallback">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <?php endif; ?>
                        <div class="card-rating-overlay">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <?= htmlspecialchars($a['rating']) ?>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="anime-title"><?= htmlspecialchars($a['judul']) ?></h3>
                        <div class="card-meta">
                            <span class="eps-badge"><?= $a['jumlah_episode'] > 0 ? $a['jumlah_episode'].' Eps' : '? Eps' ?></span>
                            <span class="genre-mini"><?= htmlspecialchars(explode(',', $a['genre'] ?? '?')[0]) ?></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($animes)): ?>
            <div class="empty-state">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>Tidak ada anime ditemukan.</p>
                <a href="index.php" class="empty-reset-link">Reset pencarian</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay" style="display:none;">
        <div class="modal-box danger-modal">
            <button class="modal-close-x" onclick="closeDeleteModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="modal-icon-danger">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </div>
            <h3>Hapus Anime?</h3>
            <p>Kamu akan menghapus <strong id="deleteAnimeName"></strong>. Semua episode akan ikut terhapus dan tidak bisa dikembalikan.</p>
            <div class="modal-actions">
                <button onclick="closeDeleteModal()" class="modal-cancel-btn">Batal</button>
                <a id="deleteLink" href="#" class="modal-confirm-btn">Hapus Permanen</a>
            </div>
        </div>
    </div>

    <!-- Login Modal Overlay -->
    <div id="loginOverlay" class="login-overlay">
        <div class="login-modal">
            <button class="modal-close-x" onclick="document.getElementById('loginOverlay').style.display='none';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="login-logo-mark">K</div>
            <h2 class="login-title">Admin Login</h2>
            
            <?php if(isset($login_error)): ?>
                <div class="login-error-msg"><?= $login_error ?></div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <div class="login-field">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="masukkan username" required class="login-input">
                </div>
                <div class="login-field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required class="login-input">
                </div>
                <button type="submit" name="login_submit" class="login-btn">Masuk</button>
            </form>
        </div>
    </div>

    <script>
        <?php if(isset($login_error)): ?>
            document.getElementById('loginOverlay').style.display='flex';
        <?php endif; ?>

        function toggleMobileNav() {
            const nav = document.getElementById('mobileNav');
            const btn = document.getElementById('hamburger');
            nav.classList.toggle('open');
            btn.classList.toggle('open');
        }

        function toggleDropdown(id, event) {
            event.stopPropagation();
            const el = document.getElementById(id);
            const isOpen = el.classList.contains('show');
            document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            if (!isOpen) el.classList.add('show');
        }
        window.onclick = function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('.hamburger')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            }
        };

        // Carousel
        const carousel = document.getElementById('recCarousel');
        const dotsContainer = document.getElementById('carouselDots');
        let currentSlide = 0;

        function buildDots() {
            if (!carousel || !dotsContainer) return;
            const slides = carousel.querySelectorAll('.rec-item-slide');
            dotsContainer.innerHTML = '';
            slides.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                dot.onclick = () => goToSlide(i);
                dotsContainer.appendChild(dot);
            });
        }

        function goToSlide(index) {
            if (!carousel) return;
            const slides = carousel.querySelectorAll('.rec-item-slide');
            index = Math.max(0, Math.min(index, slides.length - 1));
            currentSlide = index;
            carousel.scrollTo({ left: slides[index].offsetWidth * index, behavior: 'smooth' });
            dotsContainer.querySelectorAll('.carousel-dot').forEach((d, i) => d.classList.toggle('active', i === index));
        }

        function slideCarousel(dir) {
            const slides = carousel?.querySelectorAll('.rec-item-slide');
            if (!slides) return;
            const next = (currentSlide + dir + slides.length) % slides.length;
            goToSlide(next);
        }

        buildDots();

        setInterval(() => {
            const slides = carousel?.querySelectorAll('.rec-item-slide');
            if (!slides) return;
            goToSlide((currentSlide + 1) % slides.length);
        }, 4000);

        // Delete Modal
        function showDeleteModal(id, name) {
            document.getElementById('deleteAnimeName').textContent = name;
            document.getElementById('deleteLink').href = 'hapus.php?id=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
</body>
</html>
