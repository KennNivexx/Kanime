<?php
/**
 * Migrasi cover_image dari file ke blob database
 * Jalankan sekali, lalu hapus file ini.
 */
require_once 'db.php';

// Step 1: Ubah tipe kolom cover_image -> LONGBLOB
try {
    $pdo->exec("ALTER TABLE anime MODIFY COLUMN cover_image LONGBLOB NULL");
    echo "<p style='color:lime'>✔ Kolom cover_image berhasil diubah ke LONGBLOB.</p>";
} catch (Exception $e) {
    // Already blob or other issue
    echo "<p style='color:orange'>⚠ Alter kolom: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 2: Migrate existing file-based images to blob
$rows = $pdo->query("SELECT id, cover_image FROM anime")->fetchAll();
$migrated = 0;
$skipped  = 0;

foreach ($rows as $row) {
    $filename = $row['cover_image'];
    // Already binary / empty / already migrated (long string = binary)
    if (empty($filename)) { $skipped++; continue; }
    // If it looks like a filename (not binary), try to load file
    if (strlen($filename) < 255 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', $filename)) {
        $path = __DIR__ . '/uploads/' . $filename;
        if (file_exists($path)) {
            $data = file_get_contents($path);
            $stmt = $pdo->prepare("UPDATE anime SET cover_image = ? WHERE id = ?");
            $stmt->bindParam(1, $data, PDO::PARAM_LOB);
            $stmt->bindValue(2, $row['id']);
            $stmt->execute();
            $migrated++;
            echo "<p style='color:cyan'>↗ Migrated ID {$row['id']}: $filename</p>";
        } else {
            // No file found, set to NULL
            $pdo->prepare("UPDATE anime SET cover_image = NULL WHERE id = ?")->execute([$row['id']]);
            echo "<p style='color:orange'>⚠ File tidak ditemukan untuk ID {$row['id']}: $filename, set NULL</p>";
            $skipped++;
        }
    } else {
        // Already binary
        $skipped++;
    }
}

echo "<hr><p style='color:white'>Selesai. Migrated: $migrated | Skipped: $skipped</p>";
echo "<p style='color:red'><strong>Hapus file ini setelah migrasi!</strong></p>";
?>
