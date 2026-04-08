<?php
require_once 'db.php';

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if ($id && is_numeric($id)) {
    // Episodes will cascade delete via foreign key constraint
    $pdo->prepare("DELETE FROM anime WHERE id = ?")->execute([$id]);
}

header("Location: index.php");
exit;
?>
