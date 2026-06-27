<?php
require_once 'config.php';

try {
    // Add location_id to users
    $pdo->exec("ALTER TABLE users ADD COLUMN location_id INT NULL");
    echo "Successfully added location_id to users.\n";
    
    // Add foreign key constraint
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_user_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL");
    echo "Successfully added foreign key constraint.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
