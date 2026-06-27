<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE schedules MODIFY COLUMN shift_type ENUM('day', 'night', 'off', 'training') NOT NULL");
    echo "Successfully updated shift_type ENUM to include 'training'.\n";
} catch (PDOException $e) {
    echo "Error modifying schedules table: " . $e->getMessage() . "\n";
}
?>
