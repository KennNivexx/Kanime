<?php
require_once 'db.php';

$animeId = $_GET['id'] ?? null;
$eps     = $_GET['eps'] ?? 1;

if (!$animeId) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, judul, (cover_image IS NOT NULL AND cover_image != '') AS has_cover FROM anime WHERE id = ?");
$stmt->execute([$animeId]);
$anime = $stmt->fetch();
if (!$anime) die("Anime tidak ditemukan!");

$stmtEps = $pdo->prepare("SELECT * FROM episode WHERE anime_id = ? ORDER BY nomor_episode ASC");
$stmtEps->execute([$animeId]);
$allEpisodes = $stmtEps->fetchAll();

$currentEpisode = null;
$prevEpsNum = null;
$nextEpsNum = null;
$currentIndex = 0;

foreach ($allEpisodes as $i => $e) {
    if ($e['nomor_episode'] == $eps) {
        $currentEpisode = $e;
        $currentIndex   = $i;
        if ($i > 0) $prevEpsNum = $allEpisodes[$i-1]['nomor_episode'];
        if ($i < count($allEpisodes) - 1) $nextEpsNum = $allEpisodes[$i+1]['nomor_episode'];
        break;
    }
}

// Fallback to first episode
if (!$currentEpisode && !empty($allEpisodes)) {
    $currentEpisode = $allEpisodes[0];
    $currentIndex   = 0;
    if (count($allEpisodes) > 1) $nextEpsNum = $allEpisodes[1]['nomor_episode'];
}

if (!$currentEpisode) die("Episode tidak ditemukan!");

// Normalize the video link
$videoInfo = normalizeVideoLink($currentEpisode['link_video'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($anime['judul']) ?> — Eps <?= htmlspecialchars($currentEpisode['nomor_episode']) ?> | KAnime</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="nonton-body">
    <header class="navbar nonton-nav">
        <a href="index.php" class="logo-link">
            <img src="assets/logo.png" alt="KAnime" class="logo-img">
        </a>
        <div class="nonton-breadcrumb">
            <a href="detail.php?id=<?= $animeId ?>" class="breadcrumb-link"><?= htmlspecialchars($anime['judul']) ?></a>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="breadcrumb-current">Episode <?= htmlspecialchars($currentEpisode['nomor_episode']) ?></span>
        </div>
        <a href="detail.php?id=<?= $animeId ?>" class="back-arrow-nav">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Kembali
        </a>
    </header>

    <!-- Video Player Container -->
    <div class="video-section">
        <div class="video-container">
            <div class="video-wrapper" id="videoWrapper">
                <?php if($videoInfo['type'] === 'iframe'): ?>
                    <?= $videoInfo['src'] ?>
                <?php elseif($videoInfo['type'] === 'video'): ?>
                    <video controls class="embedded-video" autoplay>
                        <source src="<?= htmlspecialchars($videoInfo['src']) ?>">
                        Browser kamu tidak mendukung pemutar video langsung.
                    </video>
                <?php else: ?>
                    <iframe 
                        src="<?= htmlspecialchars($videoInfo['src']) ?>" 
                        class="embedded-video" 
                        allowfullscreen 
                        allow="autoplay; fullscreen; picture-in-picture"
                        referrerpolicy="no-referrer-when-downgrade"
                        loading="lazy">
                    </iframe>
                <?php endif; ?>
            </div>
        </div>

        <!-- Episode Info -->
        <div class="video-info-bar">
            <div class="video-info-left">
                <h1 class="video-title"><?= htmlspecialchars($anime['judul']) ?></h1>
                <p class="video-eps-label">Episode <?= htmlspecialchars($currentEpisode['nomor_episode']) ?>
                    <?php if(!empty($currentEpisode['judul_episode'])): ?>
                        — <?= htmlspecialchars($currentEpisode['judul_episode']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="video-actions">
                <button onclick="laporRusak(<?= $currentEpisode['id'] ?>)" class="video-nav-btn lapor-btn" id="btnLapor" title="Lapor Video Rusak">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span class="lapor-txt">Lapor Error</span>
                </button>
                <?php if($prevEpsNum): ?>
                <a href="nonton.php?id=<?= $animeId ?>&eps=<?= $prevEpsNum ?>" class="video-nav-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    Prev
                </a>
                <?php endif; ?>
                <?php if($nextEpsNum): ?>
                <a href="nonton.php?id=<?= $animeId ?>&eps=<?= $nextEpsNum ?>" class="video-nav-btn primary">
                    Next
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Episode navigation panel -->
    <div class="nonton-eps-panel">
        <div class="nonton-eps-header">
            <h2>Daftar Episode</h2>
            <span class="eps-count"><?= count($allEpisodes) ?> Episode</span>
        </div>
        <div class="nonton-eps-list">
            <?php foreach($allEpisodes as $i => $e): ?>
            <a href="nonton.php?id=<?= $animeId ?>&eps=<?= $e['nomor_episode'] ?>" 
               class="nonton-eps-item <?= $e['nomor_episode'] == $currentEpisode['nomor_episode'] ? 'active' : '' ?>">
                <div class="nonton-eps-play">
                    <?php if($e['nomor_episode'] == $currentEpisode['nomor_episode']): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <?php else: ?>
                    <span><?= $e['nomor_episode'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="nonton-eps-info">
                    <span class="nonton-eps-num">Episode <?= $e['nomor_episode'] ?></span>
                    <?php if(!empty($e['judul_episode'])): ?>
                    <span class="nonton-eps-title"><?= htmlspecialchars($e['judul_episode']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function laporRusak(epsId) {
            // Tanya penonton pesan tambahannya
            let userMsg = prompt('Kasih tahu admin masalahnya apa (Opsional):', 'Videonya gabisa diputar nih min...');
            if (userMsg === null) return; // Batal laporan jika user klik Cancel
            
            const btn = document.getElementById('btnLapor');
            btn.innerHTML = 'Melaporkan...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.7';

            // 1. Tembak Notifikasi ke NTFY langsung dari Browser (Notif KILAT ke HP Admin)
            const animeTitle = "<?= addslashes($anime['judul']) ?>";
            const epsNomor = "<?= addslashes($currentEpisode['nomor_episode']) ?>";
            const currentHost = window.location.host;
            
            // 👉 NAMA TOPIK RAHASIA LU:
            const ntfyTopic = "satpamkanimee"; 
            const fixUrl = `${window.location.protocol}//${currentHost}/tambah.php?id=<?= $animeId ?>#eps-<?= $currentEpisode['id'] ?>`;
            
            // Kita bungkus formatnya jadi JSON rapi, tapi dikirim polos biar nembus Browser HP (Tanpa CORS Error)
            const payload = JSON.stringify({
                topic: ntfyTopic,
                title: "⚠️ VIDEO DILAPORKAN RUSAK!",
                message: `Anime: ${animeTitle} (Eps ${epsNomor})\nCurhatan: "${userMsg || 'Gak ada pesan tambahan'}"`,
                tags: ["warning", "tv", "rotating_light"],
                priority: 4, // Bunyi kenceng di HP
                click: fixUrl // Otomatis ngebuka web pas dipencet
            });

            fetch('https://ntfy.sh/', {
                method: 'POST',
                body: payload
            }).catch(e => console.log('Abaikan'));

            // 2. Beri tahu database web kita (Pesan akan muncul di Banner Merah halaman Admin)
            fetch('lapor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'eps_id=' + epsId + '&pesan=' + encodeURIComponent(userMsg)
            }).then(r => r.json()).then(res => {
                btn.innerHTML = '✅ Laporan & Pesan Tersimpan';
                btn.style.color = '#2ed573';
                btn.style.background = 'rgba(46, 213, 115, 0.1)';
            }).catch(e => {
                btn.innerHTML = 'Gagal Lapor';
            });
        }
    </script>
</body>
</html>
