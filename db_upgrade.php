<?php
require 'config.php';

try {
    $pdo->exec('ALTER TABLE locations ADD COLUMN is_24_7 TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added is_24_7 to locations.\n";
} catch (PDOException $e) {
    echo "Column is_24_7 might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN color_hex VARCHAR(7) DEFAULT '#4f46e5'");
    echo "Added color_hex to users.\n";
} catch (PDOException $e) {
    echo "Column color_hex might already exist: " . $e->getMessage() . "\n";
}
?>
