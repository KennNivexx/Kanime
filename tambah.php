<?php
require_once 'db.php';

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
$anime = [
    'judul' => '', 'rating' => '', 'jumlah_episode' => '', 
    'genre' => '', 'sinopsis' => '', 'cover_image' => '', 'rekomendasi' => 0
];
$episodes = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT id, judul, rating, jumlah_episode, genre, sinopsis, rekomendasi FROM anime WHERE id = ?");
    $stmt->execute([$id]);
    $anime = $stmt->fetch();
    if (!$anime) die("Anime tidak ditemukan");

    $stmtE = $pdo->prepare("SELECT * FROM episode WHERE anime_id = ? ORDER BY nomor_episode ASC");
    $stmtE->execute([$id]);
    $episodes = $stmtE->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul            = trim($_POST['judul']);
    $rating           = $_POST['rating'] ?: 0.0;
    $jumlah_episode   = $_POST['jumlah_episode'] ?: 0;
    $sinopsis         = $_POST['sinopsis'] ?: '';
    $genre_str        = $_POST['genre'] ?: '';
    $rekomendasi      = isset($_POST['rekomendasi']) && $_POST['rekomendasi'] == 'Yap' ? 1 : 0;

    $eps_nomors = $_POST['eps_nomor'] ?? [];
    $eps_juduls = $_POST['eps_judul'] ?? [];
    $eps_links  = $_POST['eps_link']  ?? [];

    try {
        $pdo->beginTransaction();
        
        if ($id) {
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                $imgData = getCoverImageBinary($_FILES['cover_image']);
                if ($imgData !== false) {
                    $stmt = $pdo->prepare("UPDATE anime SET judul=?, rating=?, jumlah_episode=?, genre=?, sinopsis=?, cover_image=?, rekomendasi=? WHERE id=?");
                    $stmt->bindValue(1, $judul);
                    $stmt->bindValue(2, $rating);
                    $stmt->bindValue(3, $jumlah_episode);
                    $stmt->bindValue(4, $genre_str);
                    $stmt->bindValue(5, $sinopsis);
                    $stmt->bindParam(6, $imgData, PDO::PARAM_LOB);
                    $stmt->bindValue(7, $rekomendasi);
                    $stmt->bindValue(8, $id);
                    $stmt->execute();
                }
            } else {
                $stmt = $pdo->prepare("UPDATE anime SET judul=?, rating=?, jumlah_episode=?, genre=?, sinopsis=?, rekomendasi=? WHERE id=?");
                $stmt->execute([$judul, $rating, $jumlah_episode, $genre_str, $sinopsis, $rekomendasi, $id]);
            }
        } else {
            $imgData = null;
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                $imgData = getCoverImageBinary($_FILES['cover_image']);
            }
            $stmt = $pdo->prepare("INSERT INTO anime (judul, rating, jumlah_episode, genre, sinopsis, cover_image, rekomendasi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $judul);
            $stmt->bindValue(2, $rating);
            $stmt->bindValue(3, $jumlah_episode);
            $stmt->bindValue(4, $genre_str);
            $stmt->bindValue(5, $sinopsis);
            $stmt->bindParam(6, $imgData, PDO::PARAM_LOB);
            $stmt->bindValue(7, $rekomendasi);
            $stmt->execute();
            $id = $pdo->lastInsertId();
        }

        $pdo->prepare("DELETE FROM episode WHERE anime_id = ?")->execute([$id]);
        if (!empty($eps_nomors)) {
            $stmtE = $pdo->prepare("INSERT INTO episode (anime_id, nomor_episode, judul_episode, link_video) VALUES (?, ?, ?, ?)");
            foreach ($eps_nomors as $index => $nomor) {
                $link = $eps_links[$index] ?? '';
                $jdl  = $eps_juduls[$index] ?? '';
                if (!empty($nomor) && !empty($link)) {
                    $stmtE->execute([$id, $nomor, $jdl, $link]);
                }
            }
        }

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi Kesalahan: " . $e->getMessage();
    }
}

// Fetch current cover for preview (only id needed, not blob)
$hasCover = false;
if ($id) {
    $cStmt = $pdo->prepare("SELECT (cover_image IS NOT NULL AND cover_image != '') AS has_cover FROM anime WHERE id = ?");
    $cStmt->execute([$id]);
    $hasCover = (bool) $cStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Edit' : 'Tambah' ?> Anime — KAnime</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="tambah-body">

    <nav class="tambah-navbar">
        <a href="index.php" class="back-link-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Kembali
        </a>
        <a href="index.php" class="logo-link">
            <img src="assets/logo.png" alt="KAnime" class="logo-img">
        </a>
    </nav>

    <div class="tambah-container">
        <div class="tambah-header">
            <h2 class="tambah-title"><?= $id ? '✏️ Edit Anime' : '+ Tambah Anime Baru' ?></h2>
            <p class="tambah-sub">Isi semua informasi anime dengan lengkap dan benar.</p>
        </div>
        
        <?php if(isset($error)) echo "<div class='form-error'>⚠ $error</div>"; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="tambah-form">
            <div class="form-grid">
                <!-- Left Column -->
                <div class="col-left">
                    <div class="form-group">
                        <label for="judul">Judul Anime</label>
                        <input type="text" id="judul" name="judul" value="<?= htmlspecialchars($anime['judul']) ?>" placeholder="Contoh: Attack on Titan" required>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="rating">Rating</label>
                            <input type="number" step="0.1" min="0" max="10" id="rating" name="rating" value="<?= htmlspecialchars($anime['rating']) ?>" placeholder="0.0 – 10.0">
                        </div>
                        <div class="form-group">
                            <label for="jumlah_episode">Jumlah Episode</label>
                            <input type="number" min="0" id="jumlah_episode" name="jumlah_episode" value="<?= htmlspecialchars($anime['jumlah_episode']) ?>" placeholder="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="genre">Genre <small>(pisahkan dengan koma)</small></label>
                        <input type="text" id="genre" name="genre" value="<?= htmlspecialchars($anime['genre']) ?>" placeholder="Action, Romance, Fantasy">
                    </div>
                    
                    <div class="form-group">
                        <label>Cover Image</label>
                        <div class="cover-upload-area" id="coverUploadArea">
                            <?php if ($hasCover): ?>
                                <img src="cover_image.php?id=<?= $id ?>" class="cover-preview" id="coverPreview" alt="Cover saat ini">
                                <p class="cover-hint">Klik atau seret gambar baru untuk mengganti</p>
                            <?php else: ?>
                                <div class="cover-placeholder" id="coverPlaceholder">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <p>Klik atau seret gambar ke sini</p>
                                    <span>JPG, PNG, WEBP, GIF — maks 10MB</span>
                                </div>
                                <img class="cover-preview hidden" id="coverPreview" alt="Preview">
                            <?php endif; ?>
                            <input type="file" name="cover_image" id="coverInput" accept="image/*" class="cover-file-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Direkomendasikan?</label>
                        <div class="radio-group">
                            <label class="radio-btn <?= $anime['rekomendasi'] ? 'active' : '' ?>">
                                <input type="radio" name="rekomendasi" value="Yap" <?= $anime['rekomendasi'] ? 'checked' : '' ?>>
                                <span>✓ Ya</span>
                            </label>
                            <label class="radio-btn <?= !$anime['rekomendasi'] ? 'active' : '' ?>">
                                <input type="radio" name="rekomendasi" value="No" <?= !$anime['rekomendasi'] ? 'checked' : '' ?>>
                                <span>✗ Tidak</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-right">
                    <div class="form-group" style="flex:1; display:flex; flex-direction:column;">
                        <label for="sinopsis">Sinopsis</label>
                        <textarea name="sinopsis" id="sinopsis" rows="7" placeholder="Tulis sinopsis anime..."><?= htmlspecialchars($anime['sinopsis']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Daftar Episode <small>(Nomor, Judul (opsional), Link Video)</small></label>
                        <div id="eps-container" class="eps-container">
                            <?php foreach($episodes as $eps): ?>
                            <div class="eps-row" id="eps-<?= $eps['id'] ?>">
                                <input type="number" name="eps_nomor[]" value="<?= $eps['nomor_episode'] ?>" placeholder="No" class="eps-no">
                                <input type="text" name="eps_judul[]" value="<?= htmlspecialchars($eps['judul_episode']) ?>" placeholder="Judul (opsional)" class="eps-title">
                                <input type="text" name="eps_link[]" value="<?= htmlspecialchars($eps['link_video']) ?>" placeholder="Link video..." class="eps-link">
                                <button type="button" class="eps-remove-btn" onclick="removeEpsRow(this)" title="Hapus baris">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <?php if(isset($eps['laporan_rusak']) && $eps['laporan_rusak'] > 0): ?>
                                    <span style="color:#ff4757; font-size:12px; margin-left: 5px;" title="Dilaporkan rusak <?= $eps['laporan_rusak'] ?> kali">⚠️ Error</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addEpsRow()" class="add-eps-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Episode
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Anime
                </button>
                <?php if($id): ?>
                <button type="button" class="delete-btn-form" onclick="showDeleteConfirm(<?= $id ?>)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Hapus Anime Ini
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display:none;">
        <div class="modal-box danger-modal">
            <div class="modal-icon-danger">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </div>
            <h3>Hapus Anime?</h3>
            <p>Semua data termasuk episode akan dihapus permanen dan tidak bisa dikembalikan.</p>
            <div class="modal-actions">
                <button onclick="closeDeleteConfirm()" class="modal-cancel-btn">Batal</button>
                <a id="deleteConfirmLink" href="#" class="modal-confirm-btn">Ya, Hapus</a>
            </div>
        </div>
    </div>

    <script>
        // Cover upload preview
        const coverInput = document.getElementById('coverInput');
        const coverPreview = document.getElementById('coverPreview');
        const coverPlaceholder = document.getElementById('coverPlaceholder');
        const coverArea = document.getElementById('coverUploadArea');

        if (coverInput) {
            coverArea.addEventListener('click', () => coverInput.click());
            coverInput.addEventListener('change', handleCoverSelect);
            coverArea.addEventListener('dragover', e => { e.preventDefault(); coverArea.classList.add('drag-over'); });
            coverArea.addEventListener('dragleave', () => coverArea.classList.remove('drag-over'));
            coverArea.addEventListener('drop', e => {
                e.preventDefault();
                coverArea.classList.remove('drag-over');
                if (e.dataTransfer.files[0]) {
                    coverInput.files = e.dataTransfer.files;
                    handleCoverSelect();
                }
            });
        }

        function handleCoverSelect() {
            const file = coverInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    coverPreview.src = e.target.result;
                    coverPreview.classList.remove('hidden');
                    if (coverPlaceholder) coverPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }

        // Episode rows
        function addEpsRow() {
            const container = document.getElementById('eps-container');
            const row = document.createElement('div');
            row.className = 'eps-row';
            row.innerHTML = `
                <input type="number" name="eps_nomor[]" placeholder="No" class="eps-no">
                <input type="text" name="eps_judul[]" placeholder="Judul episode (opsional)" class="eps-title">
                <input type="text" name="eps_link[]" placeholder="Link video / iframe / YouTube / GDrive..." class="eps-link">
                <button type="button" class="eps-remove-btn" onclick="removeEpsRow(this)" title="Hapus baris">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            `;
            container.appendChild(row);
            row.querySelector('.eps-no').focus();
        }

        function removeEpsRow(btn) {
            btn.closest('.eps-row').remove();
        }

        // Radio button active state
        document.querySelectorAll('.radio-btn input').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.radio-btn').forEach(l => l.classList.remove('active'));
                radio.closest('.radio-btn').classList.add('active');
            });
        });

        // Delete modal
        function showDeleteConfirm(animeId) {
            document.getElementById('deleteConfirmLink').href = 'hapus.php?id=' + animeId;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        }
        function closeDeleteConfirm() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }
        document.getElementById('deleteConfirmModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDeleteConfirm();
        });
    </script>
</body>
</html>
