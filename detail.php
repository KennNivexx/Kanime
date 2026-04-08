<?php
require_once 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, judul, rating, jumlah_episode, genre, sinopsis, rekomendasi, (cover_image IS NOT NULL AND cover_image != '') AS has_cover FROM anime WHERE id = ?");
$stmt->execute([$id]);
$anime = $stmt->fetch();
if (!$anime) die("Anime tidak ditemukan!");

$genreStr = $anime['genre'] ?: '-';
$genreTags = array_filter(array_map('trim', explode(',', $genreStr)));

$stmtE = $pdo->prepare("SELECT * FROM episode WHERE anime_id = ? ORDER BY nomor_episode ASC");
$stmtE->execute([$id]);
$episodes = $stmtE->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($anime['judul']) ?> — KAnime</title>
    <meta name="description" content="<?= htmlspecialchars(substr($anime['sinopsis'] ?? '', 0, 155)) ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="detail-body">
    <header class="navbar detail-nav">
        <a href="index.php" class="logo-link">
            <img src="assets/logo.png" alt="KAnime" class="logo-img">
        </a>
        <a href="index.php" class="back-arrow-nav">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Kembali
        </a>
        <?php if(isAdmin()): ?>
        <a href="tambah.php?id=<?= $id ?>" class="nav-action-btn primary-btn" style="margin-left:auto;">✏️ Edit</a>
        <?php endif; ?>
    </header>
    
    <div class="detail-hero">
        <!-- Blurred background -->
        <?php if($anime['has_cover']): ?>
        <div class="detail-bg-blur" style="background-image: url('cover_image.php?id=<?= $id ?>');"></div>
        <?php endif; ?>
        <div class="detail-bg-overlay"></div>

        <div class="detail-content">
            <div class="detail-cover-wrap">
                <?php if($anime['has_cover']): ?>
                <img src="cover_image.php?id=<?= $id ?>" alt="<?= htmlspecialchars($anime['judul']) ?>" class="detail-cover">
                <?php else: ?>
                <div class="detail-cover cover-fallback-large">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="detail-info">
                <div class="detail-genre-tags">
                    <?php foreach($genreTags as $tag): ?>
                    <a href="index.php?genre=<?= urlencode(trim($tag)) ?>" class="genre-tag-pill"><?= htmlspecialchars(trim($tag)) ?></a>
                    <?php endforeach; ?>
                </div>
                <h1 class="detail-h1"><?= htmlspecialchars($anime['judul']) ?></h1>
                
                <div class="detail-meta-row">
                    <div class="meta-chip rating-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <span><?= htmlspecialchars($anime['rating']) ?></span>
                    </div>
                    <div class="meta-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
                        <span><?= htmlspecialchars($anime['jumlah_episode']) ?> Episode</span>
                    </div>
                </div>

                <?php if(!empty($anime['sinopsis'])): ?>
                <div class="sinopsis-box">
                    <h3>Sinopsis</h3>
                    <p><?= nl2br(htmlspecialchars($anime['sinopsis'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if(!empty($episodes)): ?>
                <a href="#eps-section" class="watch-now-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Mulai Nonton
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Episode List -->
    <div class="eps-section" id="eps-section">
        <h2 class="eps-title">Daftar Episode <span class="eps-count"><?= count($episodes) ?></span></h2>
        <div class="eps-grid">
            <?php foreach($episodes as $eps): ?>
            <a href="nonton.php?id=<?= $anime['id'] ?>&eps=<?= $eps['nomor_episode'] ?>" class="eps-btn">
                <div class="eps-btn-inner">
                    <div class="eps-play-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </div>
                    <div>
                        <div class="eps-num">Episode <?= htmlspecialchars($eps['nomor_episode']) ?></div>
                        <?php if(!empty($eps['judul_episode'])): ?>
                        <div class="eps-sub-title"><?= htmlspecialchars($eps['judul_episode']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if(empty($episodes)): ?>
            <div class="empty-eps-msg">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <p>Belum ada episode tersedia.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
