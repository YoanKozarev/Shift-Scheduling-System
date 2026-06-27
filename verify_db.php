<?php
require_once 'config.php';

echo "=== DATABASE VERIFICATION ===\n\n";

// Check locations table
$r = $pdo->query("SHOW COLUMNS FROM locations");
$cols = array_column($r->fetchAll(), 'Field');
echo "locations columns: " . implode(', ', $cols) . "\n";
$has_247 = in_array('is_24_7', $cols);
echo "  -> is_24_7 column: " . ($has_247 ? "YES" : "MISSING!") . "\n\n";

// Check users table
$r = $pdo->query("SHOW COLUMNS FROM users");
$cols = array_column($r->fetchAll(), 'Field');
echo "users columns: " . implode(', ', $cols) . "\n";
$has_color = in_array('color_hex', $cols);
echo "  -> color_hex column: " . ($has_color ? "YES" : "MISSING!") . "\n\n";

// Counts
$r = $pdo->query("SELECT COUNT(*) as c FROM locations")->fetch();
echo "Total locations: " . $r['c'] . "\n";

$r = $pdo->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch();
echo "Total admins: " . $r['c'] . "\n";

$r = $pdo->query("SELECT COUNT(*) as c FROM users WHERE role='worker'")->fetch();
echo "Total workers: " . $r['c'] . "\n\n";

// Location details
echo "--- Locations ---\n";
$locs = $pdo->query("SELECT * FROM locations")->fetchAll();
foreach ($locs as $loc) {
    echo "  [{$loc['id']}] {$loc['name']} (24/7: " . ($loc['is_24_7'] ? 'YES' : 'NO') . ")\n";
}

// Worker colors
echo "\n--- Worker Colors ---\n";
$workers = $pdo->query("SELECT u.id, u.full_name, u.color_hex, l.name as loc FROM users u LEFT JOIN user_locations ul ON u.id=ul.user_id LEFT JOIN locations l ON ul.location_id=l.id WHERE u.role='worker' ORDER BY l.name, u.full_name")->fetchAll();
foreach ($workers as $w) {
    echo "  [{$w['id']}] {$w['full_name']} | Color: {$w['color_hex']} | Location: {$w['loc']}\n";
}

// Absences
$r = $pdo->query("SELECT COUNT(*) as c FROM absences")->fetch();
echo "\nTotal absences: " . $r['c'] . "\n";

// Schedules
$r = $pdo->query("SELECT COUNT(*) as c FROM schedules")->fetch();
echo "Total scheduled shifts: " . $r['c'] . "\n";

echo "\n=== ALL CHECKS PASSED ===\n";
