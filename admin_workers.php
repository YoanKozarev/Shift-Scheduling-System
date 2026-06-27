<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

function getRandomHexColor() {
    $colors = ['#f43f5e', '#ec4899', '#d946ef', '#a855f7', '#8b5cf6', '#6366f1', '#3b82f6', '#0ea5e9', '#06b6d4', '#14b8a6', '#10b981', '#84cc16', '#eab308', '#f59e0b', '#f97316'];
    return $colors[array_rand($colors)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. ADD WORKER
    if ($action === 'add_worker') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $location_id = (int)$_POST['location_id'];
        $is_underage = isset($_POST['is_underage']) ? 1 : 0;
        $is_training = isset($_POST['is_training']) ? 1 : 0;
        $color_hex = $_POST['color_hex'] ?: getRandomHexColor();
        $auth_code = sprintf("%06d", mt_rand(1, 999999));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (email, role, full_name, is_underage, auth_code, is_active, color_hex, is_training) VALUES (?, 'worker', ?, ?, ?, 0, ?, ?)");
            $stmt->execute([$email, $full_name, $is_underage, $auth_code, $color_hex, $is_training]);
            $user_id = $pdo->lastInsertId();

            $stmt2 = $pdo->prepare("INSERT INTO user_locations (user_id, location_id) VALUES (?, ?)");
            $stmt2->execute([$user_id, $location_id]);
            $pdo->commit();
            
            $link = "http://localhost/xampp/Grafik/activate.php?code=" . $auth_code;
            $success_msg = "Работникът е добавен. <br><strong>Линк за активация:</strong> <a href='$link' target='_blank'>$link</a>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Грешка: " . $e->getMessage();
        }
    }
    
    // 2. EDIT WORKER
    elseif ($action === 'edit_worker') {
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $location_id = (int)$_POST['location_id'];
        $is_underage = isset($_POST['is_underage']) ? 1 : 0;
        $is_training = isset($_POST['is_training']) ? 1 : 0;
        $color_hex = $_POST['color_hex'] ?: getRandomHexColor();

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, is_underage = ?, color_hex = ?, is_training = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $is_underage, $color_hex, $is_training, $user_id]);

            $stmt2 = $pdo->prepare("UPDATE user_locations SET location_id = ? WHERE user_id = ?");
            $stmt2->execute([$location_id, $user_id]);
            $pdo->commit();
            
            $success_msg = "Профилът е обновен успешно.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Грешка: " . $e->getMessage();
        }
    }
    
    // 3. ADD ABSENCE
    elseif ($action === 'add_absence') {
        $user_id = (int)$_POST['worker_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);

        if (strtotime($start_date) && strtotime($end_date) && strtotime($start_date) <= strtotime($end_date)) {
            try {
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $interval = DateInterval::createFromDateString('1 day');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO absences (user_id, date, reason) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = ?");
                
                foreach ($period as $dt) {
                    $d = $dt->format('Y-m-d');
                    $stmt->execute([$user_id, $d, $reason, $reason]);
                }
                
                $pdo->commit();
                $success_msg = "Отсъствието е въведено успешно за избрания период.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Грешка при обработка на датите: " . $e->getMessage();
            }
        } else {
             $error_msg = "Невалиден период. Крайната дата трябва да е след началната.";
        }
    }

    // 4. DELETE WORKER
    elseif ($action === 'delete_worker') {
        $user_id = (int)$_POST['user_id'];
        if ($user_id) {
            try {
                $pdo->beginTransaction();
                // Delete from user_locations
                $stmt = $pdo->prepare("DELETE FROM user_locations WHERE user_id = ?");
                $stmt->execute([$user_id]);
                // Delete from absences
                $stmt = $pdo->prepare("DELETE FROM absences WHERE user_id = ?");
                $stmt->execute([$user_id]);
                // Delete from schedules
                $stmt = $pdo->prepare("DELETE FROM schedules WHERE user_id = ?");
                $stmt->execute([$user_id]);
                // Delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'worker'");
                $stmt->execute([$user_id]);
                $pdo->commit();
                $success_msg = "Профилът на работника е изтрит успешно.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Грешка при триене: " . $e->getMessage();
            }
        }
    }
}

$locations = $pdo->query("SELECT * FROM locations")->fetchAll();
$workers = $pdo->query("SELECT u.id, u.full_name, u.email, u.is_active, u.is_underage, u.color_hex, u.is_training, l.name as location_name, l.id as location_id 
                        FROM users u 
                        LEFT JOIN user_locations ul ON u.id = ul.user_id 
                        LEFT JOIN locations l ON ul.location_id = l.id 
                        WHERE u.role = 'worker'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление на Работници</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
    <script src="theme.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Система за Графици</a><span class="demo-badge"><i class="bi bi-lightning-fill"></i> ДЕМО РЕЖИМ</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link py-2 px-3" href="admin_locations.php"><i class="bi bi-geo-alt me-1"></i>Обекти</a></li>
                    <li class="nav-item"><a class="nav-link active py-2 px-3" href="admin_workers.php"><i class="bi bi-people me-1"></i>Работници</a></li>
                    <li class="nav-item"><a class="nav-link py-2 px-3" href="admin_scheduler.php"><i class="bi bi-calendar3 me-1"></i>График</a></li>
                    <li class="nav-item"><a class="nav-link py-2 px-3" href="http://localhost/phpmyadmin/index.php?route=/database/structure&db=grafik_db" target="_blank"><i class="bi bi-database me-1"></i>База данни</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" title="Тъмна тема"><i class="bi bi-moon-stars"></i></button>
                    <span class="me-3">Здравейте, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                    <a href="logout.php" class="btn btn-outline-danger px-3 py-2">Изход</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 animate-fade-in">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Worker -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">Добави Работник</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_worker">
                            <div class="mb-3">
                                <label class="form-label">Име</label>
                                <input type="text" name="full_name" class="form-control form-control-lg" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Имейл</label>
                                <input type="email" name="email" class="form-control form-control-lg" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Обект</label>
                                <select name="location_id" class="form-select" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Персонален цвят в графика</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" name="color_hex" class="form-control form-control-color" value="<?php echo getRandomHexColor(); ?>" title="Избери цвят" style="width: 60px; height: 48px;">
                                    <span class="small text-muted">За бързо разпознаване</span>
                                </div>
                            </div>
                            <div class="mb-3 form-check" style="padding-top: 0.5rem; padding-bottom: 0.25rem;">
                                <input type="checkbox" class="form-check-input" id="is_underage_add" name="is_underage" value="1" style="width: 1.4em; height: 1.4em;">
                                <label class="form-check-label text-danger fs-6 ms-2" for="is_underage_add">Непълнолетен</label>
                            </div>
                            <div class="mb-3 form-check" style="padding-top: 0.25rem; padding-bottom: 0.5rem;">
                                <input type="checkbox" class="form-check-input" id="is_training_add" name="is_training" value="1" style="width: 1.4em; height: 1.4em;">
                                <label class="form-check-label text-info fs-6 ms-2" for="is_training_add">Обучава се (Обучение)</label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Създай профил</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add Absence -->
            <div class="col-md-4 mb-4">
                <div class="card h-100" style="border-color: var(--danger);">
                    <div class="card-header" style="background: var(--danger); color: white;">Добави Отсъствие</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_absence">
                            <div class="mb-3">
                                <label class="form-label">Работник</label>
                                <select name="worker_id" class="form-select" required>
                                    <?php foreach ($workers as $w): ?>
                                        <option value="<?php echo $w['id']; ?>"><?php echo htmlspecialchars($w['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">От дата</label>
                                    <input type="date" name="start_date" class="form-control form-control-lg" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">До дата (вкл.)</label>
                                    <input type="date" name="end_date" class="form-control form-control-lg" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Причина (Болничен, Отпуск)</label>
                                <input type="text" name="reason" class="form-control form-control-lg" required>
                            </div>
                            <button type="submit" class="btn btn-lg text-white w-100 fw-bold" style="background: var(--danger);">Въведи Отсъствие</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- List Workers -->
            <div class="col-md-4 mb-4">
                 <div class="card h-100">
                    <div class="card-header">Списък Работници</div>
                    <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($workers as $w): ?>
                                <li class="list-group-item py-3 px-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="legend-color-box" style="background-color: <?php echo htmlspecialchars($w['color_hex'] ?? '#ccc'); ?>"></div>
                                            <strong class="fs-5"><?php echo htmlspecialchars($w['full_name']); ?></strong>
                                        </div>
                                        <?php if ($w['is_active']): ?>
                                            <span class="badge bg-success rounded-pill">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill">Неактивен</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted mb-2">Обект: <?php echo htmlspecialchars($w['location_name']); ?></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex gap-1">
                                            <?php if ($w['is_underage']): ?>
                                                <span class="badge bg-danger rounded-pill" style="font-size: 0.72rem;">Непълнолетен</span>
                                            <?php endif; ?>
                                            <?php if ($w['is_training']): ?>
                                                <span class="badge bg-info text-white rounded-pill" style="font-size: 0.72rem;">Обучава се</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-outline-primary px-3 py-2" onclick="editWorker(<?php echo htmlspecialchars(json_encode($w)); ?>)"><i class="bi bi-pencil me-1"></i> Редакция</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Worker Modal -->
    <div class="modal fade" id="editWorkerModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Редакция на Работник</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_worker">
            <input type="hidden" name="user_id" id="editWorkerId">
            <div class="mb-3">
                <label class="form-label">Име</label>
                <input type="text" name="full_name" id="editWorkerName" class="form-control form-control-lg" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Имейл</label>
                <input type="email" name="email" id="editWorkerEmail" class="form-control form-control-lg" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Обект</label>
                <select name="location_id" id="editWorkerLocation" class="form-select form-select-lg" required>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Цвят в графика</label>
                <input type="color" name="color_hex" id="editWorkerColor" class="form-control form-control-color" required style="width: 60px; height: 48px;">
            </div>
            <div class="mb-3 form-check" style="padding-top: 0.5rem; padding-bottom: 0.25rem;">
                <input type="checkbox" class="form-check-input" id="editWorkerUnderage" name="is_underage" value="1" style="width: 1.8em; height: 1.8em;">
                <label class="form-check-label text-danger fs-6 ms-2" for="editWorkerUnderage">Непълнолетен (Забрана за нощни смени)</label>
            </div>
            <div class="mb-3 form-check" style="padding-top: 0.25rem; padding-bottom: 0.5rem;">
                <input type="checkbox" class="form-check-input" id="editWorkerTraining" name="is_training" value="1" style="width: 1.8em; height: 1.8em;">
                <label class="form-check-label text-info fs-6 ms-2" for="editWorkerTraining">Обучава се (Обучение)</label>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-between">
             <button type="button" class="btn btn-outline-danger px-3 py-2" id="btnDeleteWorker" onclick="deleteWorker()"><i class="bi bi-trash me-1"></i> Изтрий профила</button>
             <div class="d-flex gap-2">
                 <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">Отказ</button>
                 <button type="submit" class="btn btn-primary px-4 py-2">Запази Промените</button>
             </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Worker Hidden Form -->
    <form method="post" id="deleteWorkerForm">
        <input type="hidden" name="action" value="delete_worker">
        <input type="hidden" name="user_id" id="deleteWorkerId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editWorkerModal = new bootstrap.Modal(document.getElementById('editWorkerModal'));
        let currentWorkerName = '';

        function editWorker(worker) {
            document.getElementById('editWorkerId').value = worker.id;
            document.getElementById('editWorkerName').value = worker.full_name;
            document.getElementById('editWorkerEmail').value = worker.email;
            document.getElementById('editWorkerLocation').value = worker.location_id;
            document.getElementById('editWorkerColor').value = worker.color_hex || '#4f46e5';
            document.getElementById('editWorkerUnderage').checked = worker.is_underage == 1;
            document.getElementById('editWorkerTraining').checked = worker.is_training == 1;
            document.getElementById('deleteWorkerId').value = worker.id;
            currentWorkerName = worker.full_name;
            editWorkerModal.show();
        }

        function deleteWorker() {
            if (confirm('Сигурни ли сте, че искате да изтриете профила на ' + currentWorkerName + '?\n\nТова ще изтрие всички негови смени и отсъствия!')) {
                document.getElementById('deleteWorkerForm').submit();
            }
        }
    </script>
</body>
</html>
