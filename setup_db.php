<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Run setup.sql
    $sql = file_get_contents('setup.sql');
    if ($sql === false) {
        die("Could not read setup.sql file.");
    }
    $pdo->exec($sql);

    // 2. Force update passwords to PIN 0000
    $correct_hash = password_hash('0000', PASSWORD_DEFAULT);
    $pdo->query("UPDATE grafik_db.users SET password_hash = '$correct_hash'");

    // 3. Seed demo schedules (2/2 pattern for ALL workers, current month)
    $pdo->query("USE grafik_db");
    
    $month = date('Y-m');
    $date_obj = new DateTime($month . '-01');
    $days_in_month = (int)$date_obj->format('t');

    $workers = $pdo->query("
        SELECT u.id, u.is_underage, ul.location_id 
        FROM users u 
        JOIN user_locations ul ON u.id = ul.user_id 
        WHERE u.role = 'worker' AND u.is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pattern_normal   = ['day', 'day', 'night', 'night', 'off', 'off'];
    $pattern_underage  = ['day', 'day', 'off', 'off', 'off', 'off'];

    $stmt = $pdo->prepare("
        INSERT INTO schedules (location_id, user_id, date, shift_type)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE shift_type = VALUES(shift_type)
    ");

    $total_shifts = 0;

    foreach ($workers as $idx => $w) {
        $pattern = $w['is_underage'] ? $pattern_underage : $pattern_normal;
        $offset = ($idx * 2) % 6;

        for ($d = 1; $d <= $days_in_month; $d++) {
            $shift_type = $pattern[($d - 1 + $offset) % 6];
            if ($shift_type === 'off') continue;

            $date_str = sprintf('%s-%02d', $month, $d);
            $stmt->execute([$w['location_id'], $w['id'], $date_str, $shift_type]);
            $total_shifts++;
        }
    }

    $worker_count = count($workers);

} catch (PDOException $e) {
    die("DB Setup Failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инсталация - Система за Графици</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            max-width: 560px;
            width: 100%;
            background: #1e293b;
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            text-align: center;
        }
        .gradient-bar {
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
            border-radius: 2px;
            margin-bottom: 2rem;
        }
        .icon {
            width: 72px; height: 72px;
            background: rgba(16,185,129,0.12);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .sub { color: #94a3b8; margin-bottom: 2rem; font-size: 0.92rem; }
        .stats {
            display: flex; gap: 0.75rem; margin-bottom: 2rem;
        }
        .stat {
            flex: 1;
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.15);
            border-radius: 14px;
            padding: 1rem 0.5rem;
        }
        .stat-num {
            font-size: 1.8rem; font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .stat-label { font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem; }
        .info {
            background: rgba(99,102,241,0.06);
            border: 1px solid rgba(99,102,241,0.12);
            border-radius: 12px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
            color: #c7d2fe;
        }
        .info strong { color: #a5b4fc; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 0.85rem 2rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white; border: none; border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem; font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(99,102,241,0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(99,102,241,0.4);
        }
        .demo-tag {
            display: inline-block;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: #451a03;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="gradient-bar"></div>
        <div class="demo-tag">ДЕМО РЕЖИМ</div>
        <div class="icon">✓</div>
        <h1>Системата е инсталирана!</h1>
        <p class="sub">Базата данни е създадена и графиците са попълнени автоматично.</p>

        <div class="stats">
            <div class="stat">
                <div class="stat-num"><?php echo $worker_count; ?></div>
                <div class="stat-label">Работници</div>
            </div>
            <div class="stat">
                <div class="stat-num"><?php echo $total_shifts; ?></div>
                <div class="stat-label">Смени</div>
            </div>
            <div class="stat">
                <div class="stat-num"><?php echo $days_in_month; ?></div>
                <div class="stat-label">Дни</div>
            </div>
        </div>

        <div class="info">
            ПИН код за вход: <strong>0000</strong> (за всички потребители)
        </div>

        <a href="login.php" class="btn">→ Влез в системата</a>
    </div>
</body>
</html>
