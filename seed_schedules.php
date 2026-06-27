<?php
require_once 'config.php';

$month = date('Y-m');
$date_obj = new DateTime($month . '-01');
$days_in_month = (int)$date_obj->format('t');

// Clear all shifts for the current month first to make sure there are no leftovers
try {
    $pdo->exec("DELETE FROM schedules WHERE date LIKE '" . $month . "-%'");
} catch (PDOException $e) {
    // Ignore if query fails
}

// Get ALL workers with their locations and location's 24/7 status
$stmt = $pdo->query("
    SELECT u.id, u.is_underage, ul.location_id, l.is_24_7 
    FROM users u 
    JOIN user_locations ul ON u.id = ul.user_id 
    JOIN locations l ON ul.location_id = l.id
    WHERE u.role = 'worker' AND u.is_active = 1
");
$all_workers = $stmt->fetchAll();

// Group workers by location
$by_location = [];
foreach ($all_workers as $w) {
    $by_location[$w['location_id']][] = $w;
}

$summary = [];
$total_shifts = 0;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO schedules (location_id, user_id, date, shift_type)
        VALUES (:location_id, :user_id, :date, :shift_type)
        ON DUPLICATE KEY UPDATE shift_type = VALUES(shift_type)
    ");

    foreach ($by_location as $loc_id => $workers) {
        $num_workers = count($workers);
        if ($num_workers === 0) continue;

        $is_24_7 = $workers[0]['is_24_7'] == 1;

        for ($worker_index = 0; $worker_index < $num_workers; $worker_index++) {
            $worker = $workers[$worker_index];
            $is_underage = $worker['is_underage'] == 1;

            $offset = $worker_index;
            $worker_shifts = 0;

            for ($d = 1; $d <= $days_in_month; $d++) {
                $pattern_length = $num_workers;
                $pattern_index = ($d - 1 - $offset) % $pattern_length;
                if ($pattern_index < 0) {
                    $pattern_index += $pattern_length;
                }

                $shift_type = 'off';
                if ($is_24_7) {
                    if ($pattern_index === 0) {
                        $shift_type = 'day';
                    } elseif ($pattern_index === 1 && !$is_underage) {
                        $shift_type = 'night';
                    }
                } else {
                    if ($pattern_index === 0) {
                        $shift_type = 'day';
                    }
                }

                if ($shift_type === 'off') {
                    continue;
                }

                $date_str = sprintf('%s-%02d', $month, $d);

                $stmt->execute([
                    ':location_id' => $loc_id,
                    ':user_id'     => $worker['id'],
                    ':date'        => $date_str,
                    ':shift_type'  => $shift_type,
                ]);

                $worker_shifts++;
            }

            $summary[] = [
                'user_id' => $worker['id'],
                'location_id' => $loc_id,
                'shifts' => $worker_shifts,
                'is_underage' => $worker['is_underage'],
            ];
            $total_shifts += $worker_shifts;
        }
    }

    $pdo->commit();
    $success = true;
    $error_msg = '';
} catch (PDOException $e) {
    $pdo->rollBack();
    $success = false;
    $error_msg = $e->getMessage();
}

// Fetch worker names for the summary display
$worker_names = [];
if (!empty($summary)) {
    $ids = array_unique(array_column($summary, 'user_id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $name_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id IN ($placeholders)");
    $name_stmt->execute(array_values($ids));
    foreach ($name_stmt->fetchAll() as $row) {
        $worker_names[$row['id']] = $row['full_name'];
    }
}

// Fetch location names
$location_names = [];
if (!empty($summary)) {
    $loc_ids = array_unique(array_column($summary, 'location_id'));
    $placeholders = implode(',', array_fill(0, count($loc_ids), '?'));
    $loc_stmt = $pdo->prepare("SELECT id, name FROM locations WHERE id IN ($placeholders)");
    $loc_stmt->execute(array_values($loc_ids));
    foreach ($loc_stmt->fetchAll() as $row) {
        $location_names[$row['id']] = $row['name'];
    }
}

$months_bg = [
    1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
    5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
    9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
];
$month_label = $months_bg[(int)$date_obj->format('n')] . ' ' . $date_obj->format('Y');
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Попълване на графици</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: #1e293b;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            width: 100%;
        }
        .card {
            background: #0f172a;
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .gradient-bar {
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            border-radius: 2px;
            margin-bottom: 2rem;
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }
        .icon-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .icon-error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            flex: 1;
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        thead th {
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        tbody td {
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        tbody tr:hover { background: rgba(255,255,255,0.03); }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-underage { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .badge-shifts { background: rgba(79, 70, 229, 0.15); color: #a78bfa; }
        .btn {
            display: inline-block;
            padding: 0.85rem 2rem;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }
        .text-center { text-align: center; }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            color: #fca5a5;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="gradient-bar"></div>

            <?php if ($success): ?>
                <div class="icon-circle icon-success">✓</div>
                <h1>Графиците са попълнени успешно!</h1>
                <p class="subtitle"><?php echo $month_label; ?> &mdash; автоматичен 2/2 график</p>

                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($summary); ?></div>
                        <div class="stat-label">Работници</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $total_shifts; ?></div>
                        <div class="stat-label">Общо смени</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $days_in_month; ?></div>
                        <div class="stat-label">Дни в месеца</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Работник</th>
                            <th>Обект</th>
                            <th>Тип</th>
                            <th>Смени</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($worker_names[$s['user_id']] ?? 'ID: ' . $s['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($location_names[$s['location_id']] ?? 'ID: ' . $s['location_id']); ?></td>
                                <td>
                                    <?php if ($s['is_underage']): ?>
                                        <span class="badge badge-underage">Непълнолетен</span>
                                    <?php else: ?>
                                        <span class="badge badge-shifts">Стандартен</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo $s['shifts']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <div class="icon-circle icon-error">✗</div>
                <h1>Грешка при попълване!</h1>
                <p class="subtitle">Възникна проблем при записа в базата данни.</p>
                <div class="error-msg"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="text-center">
                <a href="admin_scheduler.php" class="btn btn-primary">← Обратно към графика</a>
            </div>
        </div>
    </div>
</body>
</html>
