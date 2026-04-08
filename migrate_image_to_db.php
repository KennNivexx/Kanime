<?php
require 'db.php';

try {
    // Modify column
    $pdo->exec("ALTER TABLE anime MODIFY COLUMN cover_image LONGTEXT");
    echo "Column cover_image changed to LONGTEXT.\n";

    // Migrate existing images
    $stmt = $pdo->query("SELECT id, cover_image FROM anime");
    $animes = $stmt->fetchAll();
    $migratedCount = 0;

    foreach($animes as $a) {
        $cover = $a['cover_image'];
        if ($cover && strpos($cover, 'data:image') === false) {
            $path = __DIR__ . '/uploads/' . $cover;
            if (file_exists($path)) {
                $type = mime_content_type($path);
                if (!$type) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $type = 'image/' . $ext;
                }
                $data = file_get_contents($path);
                $base64 = 'data:' . $type . ';base64,' . base64_encode($data);
                
                $upd = $pdo->prepare("UPDATE anime SET cover_image = ? WHERE id = ?");
                $upd->execute([$base64, $a['id']]);
                
                // Try deleting old file to clean up uploads folder
                @unlink($path);
                $migratedCount++;
            }
        }
    }
    echo "Successfully migrated $migratedCount images to database and cleaned up folder.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
