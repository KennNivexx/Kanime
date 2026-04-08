<?php
require 'db.php';
$stmt = $pdo->query("SELECT * FROM admin");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
