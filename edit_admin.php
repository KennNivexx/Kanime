<?php
require_once 'db.php';

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

$id = $_SESSION['admin_id'];
$success_msg = "";
$error_msg = "";

$stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'] ?? '';
    $new_password = $_POST['password'] ?? '';

    if (!empty($new_username)) {
        if (!empty($new_password)) {
            // Update username & password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE admin SET username = ?, password = ? WHERE id = ?");
            if ($stmtUpdate->execute([$new_username, $hashed, $id])) {
                $_SESSION['username'] = $new_username; // Update session
                $success_msg = "Username dan Password berhasil diganti!";
            } else {
                $error_msg = "Gagal memperbarui data.";
            }
        } else {
            // Update username only
            $stmtUpdate = $pdo->prepare("UPDATE admin SET username = ? WHERE id = ?");
            if ($stmtUpdate->execute([$new_username, $id])) {
                $_SESSION['username'] = $new_username; // Update session
                $success_msg = "Username berhasil diganti!";
            } else {
                $error_msg = "Gagal memperbarui username.";
            }
        }
        // Refresh admin data
        $stmt->execute([$id]);
        $admin = $stmt->fetch();
    } else {
        $error_msg = "Username tidak boleh kosong!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Akun Admin — KAnime</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setting-container {
            max-width: 500px;
            margin: 40px auto;
            background: #252836;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .setting-h1 {
            color: #f1f2f6;
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }
        .msg-box {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .msg-success { background: rgba(46, 213, 115, 0.1); color: #2ed573; border: 1px solid rgba(46,213,115,0.3); }
        .msg-error { background: rgba(255, 71, 87, 0.1); color: #ff4757; border: 1px solid rgba(255,71,87,0.3); }
    </style>
</head>
<body class="tambah-body">

    <!-- Navbar Minimalis -->
    <nav class="tambah-navbar">
        <a href="index.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Kembali
        </a>
        <div class="tambah-title">Pengaturan Akun</div>
        <div style="width:75px"></div> <!-- Spacer -->
    </nav>

    <div class="setting-container">
        <h1 class="setting-h1">🔑 Ubah Data Admin</h1>
        
        <?php if($success_msg): ?>
            <div class="msg-box msg-success"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="msg-box msg-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username Admin</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required class="form-input">
            </div>
            
            <div class="form-group">
                <label for="password">Password Baru <small>(Kosongkan jika tidak ingin mengubah password)</small></label>
                <input type="password" id="password" name="password" placeholder="Ketik kata sandi baru..." class="form-input">
            </div>

            <button type="submit" class="submit-btn" style="width: 100%; justify-content: center; margin-top:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Simpan Perubahan
            </button>
        </form>
    </div>

</body>
</html>
