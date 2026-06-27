<?php
require_once 'config.php';

// All passwords will be numeric PINs: hash of "1234"
$pin_hash = password_hash('1234', PASSWORD_DEFAULT);
$admin_pin = password_hash('0000', PASSWORD_DEFAULT);

try {
    // Update admin password to numeric PIN "0000"
    $pdo->exec("UPDATE users SET password_hash = '$admin_pin' WHERE email = 'admin@grafik.local'");
    echo "Admin password updated to PIN: 0000\n";

    // Add 2 more locations
    $pdo->exec("INSERT IGNORE INTO locations (id, name, is_24_7) VALUES 
        (2, 'Обект Юг', 1),
        (3, 'Обект Север', 0)
    ");
    echo "Added 2 new locations.\n";

    // Add more workers with colors and numeric PINs
    $workers = [
        // Location 1 - Обект Център (already has workers 2-6)
        [7, 'worker7@grafik.local', 'Стоян Димитров', 0, '#0ea5e9', 1],
        [8, 'worker8@grafik.local', 'Николай Тодоров', 0, '#f59e0b', 1],
        // Location 2 - Обект Юг
        [9, 'worker9@grafik.local', 'Красимир Василев', 0, '#10b981', 2],
        [10, 'worker10@grafik.local', 'Даниела Маринова', 0, '#ec4899', 2],
        [11, 'worker11@grafik.local', 'Борис Колев', 0, '#8b5cf6', 2],
        [12, 'worker12@grafik.local', 'Светлана Попова', 0, '#f97316', 2],
        [13, 'worker13@grafik.local', 'Димитър Атанасов', 0, '#06b6d4', 2],
        [14, 'worker14@grafik.local', 'Антон Ненков', 1, '#d946ef', 2],
        // Location 3 - Обект Север
        [15, 'worker15@grafik.local', 'Пламен Христов', 0, '#84cc16', 3],
        [16, 'worker16@grafik.local', 'Ивайло Стефанов', 0, '#3b82f6', 3],
        [17, 'worker17@grafik.local', 'Росица Кирилова', 0, '#f43f5e', 3],
        [18, 'worker18@grafik.local', 'Тодор Младенов', 0, '#14b8a6', 3],
        [19, 'worker19@grafik.local', 'Калина Георгиева', 0, '#eab308', 3],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, email, password_hash, role, full_name, is_underage, auth_code, is_active, color_hex) VALUES (?, ?, ?, 'worker', ?, ?, NULL, 1, ?)");
    $stmtLoc = $pdo->prepare("INSERT IGNORE INTO user_locations (user_id, location_id) VALUES (?, ?)");

    foreach ($workers as $w) {
        $stmt->execute([$w[0], $w[1], $pin_hash, $w[2], $w[3], $w[4]]);
        $stmtLoc->execute([$w[0], $w[5]]); 
    }

    // Fix: location_id mapping
    $locationMap = [
        7 => 1, 8 => 1,
        9 => 2, 10 => 2, 11 => 2, 12 => 2, 13 => 2, 14 => 2,
        15 => 3, 16 => 3, 17 => 3, 18 => 3, 19 => 3,
    ];

    // Delete wrong mappings and re-insert
    foreach ($locationMap as $uid => $lid) {
        $pdo->exec("DELETE FROM user_locations WHERE user_id = $uid");
        $stmtLoc->execute([$uid, $lid]);
    }

    echo "Added " . count($workers) . " new workers.\n";

    // Update existing workers' passwords to PIN "1234"
    $pdo->exec("UPDATE users SET password_hash = '$pin_hash' WHERE role = 'worker'");
    echo "All worker passwords updated to PIN: 1234\n";

    // Add some demo shifts for the new locations
    $today = date('Y-m-d');
    $month = date('Y-m');

    // Обект Юг - some shifts
    for ($d = 1; $d <= 28; $d++) {
        $date = sprintf('%s-%02d', $month, $d);
        // Красимир - alternating day/night
        if ($d % 4 < 2) {
            $type = ($d % 2 == 0) ? 'day' : 'night';
            $pdo->exec("INSERT IGNORE INTO schedules (location_id, user_id, date, shift_type) VALUES (2, 9, '$date', '$type')");
        }
        // Даниела
        if ($d % 4 >= 2) {
            $type = ($d % 2 == 0) ? 'day' : 'night';
            $pdo->exec("INSERT IGNORE INTO schedules (location_id, user_id, date, shift_type) VALUES (2, 10, '$date', '$type')");
        }
        // Борис
        if ($d % 3 == 0) {
            $pdo->exec("INSERT IGNORE INTO schedules (location_id, user_id, date, shift_type) VALUES (2, 11, '$date', 'day')");
        }
    }

    // Обект Север - some shifts
    for ($d = 1; $d <= 28; $d++) {
        $date = sprintf('%s-%02d', $month, $d);
        if ($d % 4 < 2) {
            $pdo->exec("INSERT IGNORE INTO schedules (location_id, user_id, date, shift_type) VALUES (3, 15, '$date', 'day')");
        }
        if ($d % 4 >= 2) {
            $pdo->exec("INSERT IGNORE INTO schedules (location_id, user_id, date, shift_type) VALUES (3, 16, '$date', 'day')");
        }
    }

    echo "\nDone! Demo data seeded successfully.\n";
    echo "\n=== LOGIN CREDENTIALS ===\n";
    echo "Admin: admin@grafik.local / PIN: 0000\n";
    echo "All Workers: PIN: 1234\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
