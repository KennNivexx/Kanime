<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eps_id = $_POST['eps_id'] ?? 0;
    $pesan = trim($_POST['pesan'] ?? '');
    
    // Auto buat kolom kalau belum ada 
    try {
        $pdo->query("ALTER TABLE episode ADD COLUMN laporan_rusak INT DEFAULT 0");
        $pdo->query("ALTER TABLE episode ADD COLUMN laporan_pesan TEXT");
    } catch(PDOException $e) {
        // Abaikan
    }
    
    if ($eps_id) {
        // Tambah hitungan laporan dan tambahkan pesan baru di kolom text
        $stmt = $pdo->prepare("UPDATE episode SET laporan_rusak = laporan_rusak + 1, laporan_pesan = CONCAT(COALESCE(laporan_pesan, ''), ?) WHERE id = ?");
        $pesan_simpan = $pesan ? "💬 " . htmlspecialchars($pesan) . "<br>" : "";
        $stmt->execute([$pesan_simpan, $eps_id]);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
