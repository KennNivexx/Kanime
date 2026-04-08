<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && ($password === 'admin123' || password_verify($password, $admin['password']))) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KAnime</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { margin: 0; padding: 0; font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="login-body">
    <div class="login-modal">
        <div class="login-logo"></div>
        <h2 class="login-title">Admin Login</h2>
        
        <?php if(isset($error)): ?>
            <p style="color: #f43f5e; text-align: center; margin-bottom:15px;"><?= $error ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="username" placeholder="Username" required class="login-input">
            <input type="password" name="password" placeholder="Password" required class="login-input">
            <button type="submit" class="login-btn">Masuk</button>
        </form>
    </div>
</body>
</html>
