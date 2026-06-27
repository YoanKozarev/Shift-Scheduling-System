<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_training TINYINT(1) NOT NULL DEFAULT 0");
    echo "Successfully added is_training column to users table.\n";
} catch (PDOException $e) {
    echo "Error modifying users table: " . $e->getMessage() . "\n";
}
?>
