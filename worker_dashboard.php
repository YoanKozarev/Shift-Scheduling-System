<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$date_obj = new DateTime($month . '-01');
$days_in_month = (int)$date_obj->format('t');
$months_bg = [
    1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
    5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
    9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
];
$month_num = (int)$date_obj->format('n');
$year_num = $date_obj->format('Y');
$month_name = $months_bg[$month_num] . ' ' . $year_num;

// Fetch user info and location
$stmt_info = $pdo->prepare("
    SELECT u.full_name, u.color_hex, l.id as location_id, l.name as location_name, l.is_24_7 
    FROM users u 
    LEFT JOIN user_locations ul ON u.id = ul.user_id 
    LEFT JOIN locations l ON ul.location_id = l.id 
    WHERE u.id = ?
");
$stmt_info->execute([$user_id]);
$user_info = $stmt_info->fetch();
$location_id = $user_info['location_id'] ?? 0;
$location_name = $user_info['location_name'] ?? 'Неразпределен';
$is_24_7 = ($user_info['is_24_7'] ?? 0) == 1;
$my_color = $user_info['color_hex'] ?? '#4f46e5';

// Fetch ALL workers in this location
$stmt_workers = $pdo->prepare("
    SELECT u.id, u.full_name, u.color_hex 
    FROM users u 
    JOIN user_locations ul ON u.id = ul.user_id 
    WHERE ul.location_id = ? AND u.role = 'worker' AND u.is_active = 1
    ORDER BY u.full_name
");
$stmt_workers->execute([$location_id]);
$workers = $stmt_workers->fetchAll();

// Fetch shifts for ALL workers in this location
$stmt = $pdo->prepare("
    SELECT s.date, s.shift_type, u.id as user_id, u.full_name, u.color_hex 
    FROM schedules s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.location_id = ? AND s.date LIKE ?
    ORDER BY s.shift_type ASC
");
$stmt->execute([$location_id, "$month-%"]);
$shifts_raw = $stmt->fetchAll();

$shifts_by_date = [];
foreach ($shifts_raw as $s) {
    $shifts_by_date[$s['date']][] = $s;
}

// Fetch absences
$stmt_abs = $pdo->prepare("
    SELECT a.date, a.reason, u.id as user_id, u.full_name, u.color_hex
    FROM absences a
    JOIN user_locations ul ON a.user_id = ul.user_id
    JOIN users u ON a.user_id = u.id
    WHERE ul.location_id = ? AND a.date LIKE ?
");
$stmt_abs->execute([$location_id, "$month-%"]);
$absences_raw = $stmt_abs->fetchAll();

$absences_by_date = [];
foreach ($absences_raw as $a) {
    $absences_by_date[$a['date']][] = $a;
}

// Count my shifts
$my_day = 0; $my_night = 0;
foreach ($shifts_raw as $s) {
    if ($s['user_id'] == $user_id) {
        if ($s['shift_type'] === 'day') $my_day++;
        if ($s['shift_type'] === 'night') $my_night++;
    }
}

$today = date('Y-m-d');
$prev_month = date('Y-m', strtotime($month . ' -1 month'));
$next_month = date('Y-m', strtotime($month . ' +1 month'));
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Моят График</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
    <script src="theme.js"></script>
    <style>
        /* Worker dashboard specific premium styles */
        .hero-header {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            border-radius: 24px;
            padding: 2rem 2.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .hero-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -30px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(124,58,237,0.3), transparent 70%);
            border-radius: 50%;
        }
        .hero-header::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: 30%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(79,70,229,0.2), transparent 70%);
            border-radius: 50%;
        }
        .hero-header .hero-content { position: relative; z-index: 2; }
        .hero-month {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .hero-location {
            opacity: 0.7;
            font-size: 0.95rem;
            font-weight: 400;
        }
        .hero-stats {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        .hero-stat {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        .hero-stat .num {
            font-size: 1.2rem;
            font-weight: 800;
            margin-right: 4px;
        }
        .hero-nav {
            position: absolute;
            right: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 3;
            display: flex;
            gap: 8px;
        }
        .hero-nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.2s ease;
        }
        .hero-nav a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        /* Enhanced legend */
        .legend-strip {
            background: linear-gradient(135deg, #f8faff, #fdf2f8);
            border: 1px solid rgba(79,70,229,0.06);
            border-radius: 16px;
            padding: 14px 20px;
            margin-bottom: 1.5rem;
        }
        .legend-strip .legend-title {
            font-weight: 700;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-right: 14px;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .legend-item:hover {
            background: rgba(79,70,229,0.06);
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            position: relative;
        }
        .legend-dot::after {
            content: '';
            position: absolute;
            top: 1px; left: 1px; right: 1px; bottom: 1px;
            border-radius: 3px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), transparent);
        }
        .legend-name {
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text);
        }

        /* Enhanced calendar wrapper */
        .calendar-wrapper {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.7);
        }

        /* Weekend column styling */
        .worker-day.weekend {
            background: #fafaff;
        }
        [data-theme="dark"] .worker-day.weekend {
            background: rgba(255, 255, 255, 0.03) !important;
        }
        .worker-day.today-cell {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.15), 0 4px 12px rgba(79,70,229,0.1);
        }
        .today-badge {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            box-shadow: 0 3px 8px rgba(79,70,229,0.3);
        }

        /* My shifts get highlighted */
        .my-shift {
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            font-weight: 600 !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Система за Графици</a><span class="demo-badge"><i class="bi bi-lightning-fill"></i> ДЕМО РЕЖИМ</span>
            <div class="d-flex align-items-center ms-auto">
                <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" title="Тъмна тема"><i class="bi bi-moon-stars"></i></button>
                <span class="me-3">Здравейте, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger px-3 py-2">
                    <i class="bi bi-box-arrow-right me-1"></i> Изход
                </a>
            </div>
        </div>
    </nav>

    <div class="container worker-dashboard-container animate-fade-in">
        <!-- Hero Header -->
        <div class="hero-header">
            <div class="hero-content">
                <div class="hero-month"><?php echo mb_convert_case($month_name, MB_CASE_TITLE, "UTF-8"); ?></div>
                <div class="hero-location">
                    <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($location_name); ?>
                    <?php if ($is_24_7): ?>
                        <span class="badge bg-warning text-dark ms-2" style="font-size: 0.72rem;">24/7</span>
                    <?php endif; ?>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="num"><?php echo $my_day; ?></span>
                        <i class="bi bi-sun me-1"></i> Дневни
                    </div>
                    <div class="hero-stat">
                        <span class="num"><?php echo $my_night; ?></span>
                        <i class="bi bi-moon me-1"></i> Нощни
                    </div>
                    <div class="hero-stat">
                        <span class="num"><?php echo count($workers); ?></span>
                        <i class="bi bi-people me-1"></i> Екип
                    </div>
                </div>
            </div>
            <div class="hero-nav">
                <a href="?month=<?php echo $prev_month; ?>" title="Предишен месец">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="?month=<?php echo $next_month; ?>" title="Следващ месец">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend-strip d-flex flex-wrap align-items-center gap-2">
            <span class="legend-title"><i class="bi bi-palette me-1"></i> Легенда</span>
            <?php foreach ($workers as $w): ?>
                <div class="legend-item <?php echo ($w['id'] == $user_id) ? 'fw-bold' : ''; ?>">
                    <div class="legend-dot" style="background-color: <?php echo htmlspecialchars($w['color_hex']); ?>;"></div>
                    <span class="legend-name"><?php echo htmlspecialchars($w['full_name']); ?><?php echo ($w['id'] == $user_id) ? ' (Вие)' : ''; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Calendar -->
        <div class="calendar-wrapper">
            <div class="worker-calendar">
                <?php
                $days_of_week = ['Пон', 'Вто', 'Сря', 'Чет', 'Пет', 'Съб', 'Нед'];
                foreach ($days_of_week as $idx => $dow) {
                    $weekend_style = ($idx >= 5) ? 'color: var(--accent);' : '';
                    echo "<div class='calendar-header' style='$weekend_style'>$dow</div>";
                }

                // Pad empty days
                $first_day_w = (int)$date_obj->format('N');
                for ($i = 1; $i < $first_day_w; $i++) {
                    echo "<div></div>";
                }

                for ($d = 1; $d <= $days_in_month; $d++) {
                    $current_date_str = sprintf('%s-%02d', $month, $d);
                    $day_shifts = $shifts_by_date[$current_date_str] ?? [];
                    $day_absences = $absences_by_date[$current_date_str] ?? [];
                    $is_today = ($current_date_str === $today);
                    
                    // Determine day of week for weekend styling
                    $dow_num = (int)(new DateTime($current_date_str))->format('N');
                    $is_weekend = ($dow_num >= 6);
                    
                    $classes = 'worker-day';
                    if ($is_today) $classes .= ' today-cell';
                    if ($is_weekend) $classes .= ' weekend';

                    echo "<div class='$classes'>";
                    
                    // Date
                    if ($is_today) {
                        echo "<div class='mb-1'><span class='today-badge'>$d</span></div>";
                    } else {
                        echo "<div class='calendar-date' style='font-weight: 700;'>$d</div>";
                    }
                    
                    // Shifts
                    foreach ($day_shifts as $shift) {
                        $label = '';
                        $time = '';
                        if ($shift['shift_type'] === 'day') {
                            $label = $is_24_7 ? 'Д' : 'Д';
                            $time = $is_24_7 ? '07:30-19:30' : '';
                        } else if ($shift['shift_type'] === 'night') {
                            $label = $is_24_7 ? 'Н' : 'Н';
                            $time = $is_24_7 ? '19:30-07:30' : '';
                        } else if ($shift['shift_type'] === 'training') {
                            $label = 'Обуч.';
                            $time = '';
                        } else {
                            continue;
                        }

                        $bg = htmlspecialchars($shift['color_hex'] ?? '#4f46e5');
                        $name_parts = explode(' ', $shift['full_name']);
                        $short = htmlspecialchars($name_parts[0] . (isset($name_parts[1]) ? ' ' . mb_substr($name_parts[1], 0, 1, 'UTF-8') . '.' : ''));
                        $is_me = ($shift['user_id'] == $user_id);
                        $me_class = $is_me ? ' my-shift' : '';
                        $is_training = ($shift['shift_type'] === 'training');
                        $training_class = $is_training ? ' shift-training' : '';
                        $font = $is_me ? '0.76rem' : '0.7rem';
                        $pad = $is_me ? '4px 6px' : '3px 5px';
                        $time_display = $time ? " <span style='opacity:0.7;font-size:0.62rem;'>$time</span>" : '';

                        echo "<div class='shift-badge text-white text-truncate text-start$me_class$training_class' style='background:$bg; font-size:$font; padding:$pad;' title='" . htmlspecialchars($shift['full_name']) . " - $label'>
                            <strong>$short</strong> <span style='opacity:0.8;'>$label</span>$time_display
                        </div>";
                    }

                    // Absences
                    foreach ($day_absences as $abs) {
                        $name_parts = explode(' ', $abs['full_name']);
                        $short = htmlspecialchars($name_parts[0] . (isset($name_parts[1]) ? ' ' . mb_substr($name_parts[1], 0, 1, 'UTF-8') . '.' : ''));
                        $reason = htmlspecialchars($abs['reason']);
                        $abg = htmlspecialchars($abs['color_hex'] ?? '#ccc');
                        $is_me = ($abs['user_id'] == $user_id);
                        $fw = $is_me ? 'fw-bold' : '';

                        echo "<div class='shift-badge text-truncate text-start $fw' style='background:#fef2f2; color:#991b1b; border-left:3px solid $abg; font-size:0.7rem; padding:3px 5px;' title='" . htmlspecialchars($abs['full_name'] . ' - ' . $abs['reason']) . "'>
                            <strong style='color:inherit;'>$short</strong> <span style='opacity:0.65; color:inherit;'>$reason</span>
                        </div>";
                    }
                    
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
