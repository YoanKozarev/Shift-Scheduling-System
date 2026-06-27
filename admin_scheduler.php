<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$locations = $pdo->query("SELECT * FROM locations")->fetchAll();
$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : ($locations[0]['id'] ?? 1);
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

// Fetch Location Info
$stmt = $pdo->prepare("SELECT name, is_24_7 FROM locations WHERE id = ?");
$stmt->execute([$location_id]);
$location_info = $stmt->fetch();
$is_24_7 = $location_info ? (int)$location_info['is_24_7'] : 0;
$location_name = $location_info ? $location_info['name'] : 'Неизвестен обект';

// Fetch Workers for Sidebar & Legend
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.is_underage, u.color_hex 
    FROM users u 
    JOIN user_locations ul ON u.id = ul.user_id 
    WHERE ul.location_id = ? AND u.role = 'worker' AND u.is_active = 1
");
$stmt->execute([$location_id]);
$workers = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>График - Админ</title>
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
                    <li class="nav-item"><a class="nav-link py-2 px-3" href="admin_workers.php"><i class="bi bi-people me-1"></i>Работници</a></li>
                    <li class="nav-item"><a class="nav-link active py-2 px-3" href="admin_scheduler.php"><i class="bi bi-calendar3 me-1"></i>График</a></li>
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

    <div class="container-fluid mt-4 animate-fade-in px-4">
        <div class="row">
            <!-- Sidebar: Workers -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" style="border-radius: 15px 15px 0 0;">
                        Работници
                    </div>
                    <div class="card-body p-3" id="worker-list" style="max-height: 75vh; overflow-y: auto;">
                        <?php foreach ($workers as $w): ?>
                            <div class="worker-template" data-id="<?php echo $w['id']; ?>" data-name="<?php echo htmlspecialchars($w['full_name']); ?>" data-underage="<?php echo $w['is_underage']; ?>" data-color="<?php echo htmlspecialchars($w['color_hex']); ?>" style="padding: 0.85rem 1rem;">
                                <div class="worker-info d-flex align-items-center gap-2">
                                    <div class="legend-color-box" style="background-color: <?php echo htmlspecialchars($w['color_hex']); ?>; width: 14px; height: 14px; margin: 0;"></div>
                                    <div>
                                        <span class="worker-name" style="font-size: 0.95rem; font-weight: 500;"><?php echo htmlspecialchars($w['full_name']); ?></span>
                                        <div class="worker-badges">
                                            <?php if ($w['is_underage']): ?>
                                                <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">Непълнолетен</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-light border px-3 py-2" onclick="openAutoFillModal(<?php echo $w['id']; ?>, '<?php echo htmlspecialchars(addslashes($w['full_name'])); ?>')" title="Генерирай 2/2">
                                    <i class="bi bi-magic text-primary"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($workers)): ?>
                            <div class="text-muted text-center py-4 small">Няма активни работници за този обект.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Calendar Area -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h3 mb-1 text-capitalize fw-bold" style="color: var(--primary);"><?php echo mb_convert_case($month_name, MB_CASE_TITLE, "UTF-8"); ?></h2>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fw-medium">Обект:</span>
                            <form method="get" class="d-inline-block">
                                <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                                <select name="location_id" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>" <?php echo $loc['id'] == $location_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php if ($is_24_7): ?>
                                <span class="badge bg-warning text-dark ms-1">24/7 Денонощен</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <a href="seed_schedules.php" class="btn btn-primary px-3 py-2 me-2" title="Генериране на демо график за всички обекти"><i class="bi bi-lightning-charge-fill"></i> Демо График</a>
                        <a href="?month=<?php echo date('Y-m', strtotime($month . ' -1 month')); ?>&location_id=<?php echo $location_id; ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2">&larr; Предишен</a>
                        <a href="?month=<?php echo date('Y-m', strtotime($month . ' +1 month')); ?>&location_id=<?php echo $location_id; ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 ms-1">Следващ &rarr;</a>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-3">
                        <div class="calendar-grid">
                            <?php
                            $days_of_week = ['Пон', 'Вто', 'Сря', 'Чет', 'Пет', 'Съб', 'Нед'];
                            foreach ($days_of_week as $dow) {
                                echo "<div class='calendar-header'>$dow</div>";
                            }

                            // Pad empty days
                            $first_day_w = (int)$date_obj->format('N');
                            for ($i = 1; $i < $first_day_w; $i++) {
                                echo "<div></div>";
                            }

                            $today = date('Y-m-d');

                            for ($d = 1; $d <= $days_in_month; $d++) {
                                $current_date_str = sprintf('%s-%02d', $month, $d);
                                $is_today = ($current_date_str === $today) ? 'today' : '';
                                
                                echo "<div class='calendar-cell $is_today' data-date='$current_date_str'>";
                                echo "<div class='calendar-date'>$d</div>";
                                echo "<div class='shifts-container' style='flex-grow: 1; min-height: 50px;'></div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="card bg-light border-0 shadow-sm mb-5">
                    <div class="card-body d-flex flex-wrap gap-4 align-items-center">
                        <span class="fw-bold text-muted me-2"><i class="bi bi-info-circle me-1"></i> Легенда:</span>
                        <?php foreach ($workers as $w): ?>
                            <div class="d-flex align-items-center">
                                <div class="legend-color-box shadow-sm" style="background-color: <?php echo htmlspecialchars($w['color_hex']); ?>;"></div>
                                <span class="small fw-medium"><?php echo htmlspecialchars($w['full_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for Shift Selection -->
    <div class="modal fade" id="shiftModal" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Избор на смяна</h5>
            <button type="button" class="btn-close" onclick="cancelDrop()"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded" style="background: var(--bg-light); border: 1px solid var(--border);">
                <div>
                    <span class="text-muted d-block small">Работник</span>
                    <strong id="modalWorkerName" class="fs-5"></strong>
                </div>
                <div class="text-end">
                    <span class="text-muted d-block small">Дата</span>
                    <strong id="modalDateStr" class="fs-5 text-primary"></strong>
                </div>
            </div>
            
            <div class="alert alert-danger d-none align-items-center shadow-sm" id="absenceWarning" style="border-radius: 12px;">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i> 
                <div>
                    <strong>Внимание:</strong> Този работник е в отпуск/болничен за избраната дата!
                </div>
            </div>

            <div class="d-grid gap-3">
                <button class="btn btn-warning btn-lg text-dark shadow-sm fw-bold border-0" id="btnDayShift" onclick="saveShift('day')">Дневна смяна</button>
                <button class="btn btn-primary btn-lg shadow-sm fw-bold border-0" id="btnNightShift" onclick="saveShift('night')">Нощна смяна</button>
                <button class="btn btn-info btn-lg text-white shadow-sm fw-bold border-0" id="btnTrainingShift" onclick="saveShift('training')" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">Обучение</button>
                <button class="btn btn-light border btn-lg shadow-sm fw-bold" id="btnOffShift" onclick="saveShift('off')">Почивка</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal for Auto-Fill 2/2 -->
    <div class="modal fade" id="autoFillModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Генериране на График</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="mb-4">Автоматично попълване на <strong>2/2 график</strong> за <strong id="autoFillWorkerName" class="text-primary"></strong>.</p>
            <div class="mb-3">
                <label class="form-label fw-medium">Изберете стартова дата</label>
                <input type="date" class="form-control form-control-lg" id="autoFillStartDate" min="<?php echo $month; ?>-01" max="<?php echo $month; ?>-<?php echo str_pad($days_in_month, 2, '0', STR_PAD_LEFT); ?>">
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Отказ</button>
            <button type="button" class="btn btn-primary px-4" onclick="submitAutoFill()">Генерирай</button>
          </div>
        </div>
      </div>
    </div>

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const currentMonth = '<?php echo $month; ?>';
        const locationId = <?php echo $location_id; ?>;
        const is24_7 = <?php echo $is_24_7 ? 'true' : 'false'; ?>;
        
        let absencesData = [];
        
        let pendingDrop = {
            userId: null,
            userName: null,
            isUnderage: false,
            colorHex: null,
            date: null,
            targetContainer: null,
            itemEl: null
        };

        const shiftModal = new bootstrap.Modal(document.getElementById('shiftModal'));
        const autoFillModal = new bootstrap.Modal(document.getElementById('autoFillModal'));
        let autoFillUserId = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadAbsences();
            loadShifts();
            initSortable();
        });

        function loadAbsences() {
            fetch(`api.php?action=get_absences&month=${currentMonth}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        absencesData = res.data;
                    }
                });
        }

        function loadShifts() {
            fetch(`api.php?action=get_shifts&month=${currentMonth}&location_id=${locationId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        document.querySelectorAll('.shifts-container').forEach(c => c.innerHTML = '');
                        res.data.forEach(shift => {
                            renderShift(shift);
                        });
                    }
                });
        }

        function renderShift(shift) {
            const cell = document.querySelector(`.calendar-cell[data-date="${shift.date}"] .shifts-container`);
            if (!cell) return;

            const div = document.createElement('div');
            div.className = `shift-item ${shift.shift_type === 'training' ? 'shift-training' : ''}`;
            div.dataset.shiftId = shift.id;
            div.style.backgroundColor = shift.color_hex || '#4f46e5';
            
            let label = 'П';
            if (shift.shift_type === 'day') label = is24_7 ? 'Д (07:30-19:30)' : 'Дневна';
            if (shift.shift_type === 'night') label = is24_7 ? 'Н (19:30-07:30)' : 'Нощна';
            if (shift.shift_type === 'training') label = 'Обучение';

            div.innerHTML = `
                <span class="text-truncate me-2" title="${shift.full_name}">${shift.full_name} <span class="opacity-75 small">(${label})</span></span>
                <i class="bi bi-x-circle-fill delete-shift" onclick="deleteShift(${shift.id}, this)"></i>
            `;
            cell.appendChild(div);
        }

        function initSortable() {
            new Sortable(document.getElementById('worker-list'), {
                group: { name: 'shared', pull: 'clone', put: false },
                sort: false,
                animation: 150
            });

            document.querySelectorAll('.shifts-container').forEach(container => {
                new Sortable(container, {
                    group: 'shared',
                    animation: 150,
                    onAdd: function (evt) {
                        const itemEl = evt.item;
                        const cell = evt.to.closest('.calendar-cell');
                        
                        pendingDrop.userId = itemEl.dataset.id;
                        pendingDrop.userName = itemEl.dataset.name;
                        pendingDrop.isUnderage = itemEl.dataset.underage === '1';
                        pendingDrop.colorHex = itemEl.dataset.color;
                        pendingDrop.date = cell.dataset.date;
                        pendingDrop.targetContainer = evt.to;
                        pendingDrop.itemEl = itemEl;

                        const isAbsent = absencesData.some(a => a.user_id == pendingDrop.userId && a.date == pendingDrop.date);
                        const warningDiv = document.getElementById('absenceWarning');
                        if (isAbsent) {
                            cell.classList.add('absent');
                            warningDiv.classList.remove('d-none');
                            warningDiv.classList.add('d-flex');
                        } else {
                            cell.classList.remove('absent');
                            warningDiv.classList.add('d-none');
                            warningDiv.classList.remove('d-flex');
                        }

                        document.getElementById('modalWorkerName').innerText = pendingDrop.userName;
                        document.getElementById('modalDateStr').innerText = pendingDrop.date.split('-').reverse().join('.');
                        
                        const btnDay = document.getElementById('btnDayShift');
                        const btnNight = document.getElementById('btnNightShift');

                        btnDay.innerText = is24_7 ? "Дневна (07:30 - 19:30)" : "Дневна смяна";

                        if (pendingDrop.isUnderage) {
                            btnNight.disabled = true;
                            btnNight.innerText = "Нощна смяна (Забранено!)";
                            btnNight.classList.replace('btn-primary', 'btn-secondary');
                        } else {
                            btnNight.disabled = false;
                            btnNight.innerText = is24_7 ? "Нощна (19:30 - 07:30)" : "Нощна смяна";
                            if(btnNight.classList.contains('btn-secondary')) {
                                btnNight.classList.replace('btn-secondary', 'btn-primary');
                            }
                        }

                        shiftModal.show();
                    }
                });
            });
        }

        function cancelDrop() {
            if (pendingDrop.itemEl && pendingDrop.itemEl.parentNode) {
                pendingDrop.itemEl.parentNode.removeChild(pendingDrop.itemEl);
            }
            document.querySelectorAll('.calendar-cell').forEach(c => c.classList.remove('absent'));
            shiftModal.hide();
        }

        function saveShift(type) {
            if (type === 'night' && pendingDrop.isUnderage) {
                alert("Непълнолетни лица не могат да работят нощни смени според трудовото законодателство!");
                return;
            }

            const payload = {
                user_id: pendingDrop.userId,
                date: pendingDrop.date,
                shift_type: type,
                location_id: locationId
            };

            fetch('api.php?action=save_shift', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    if (pendingDrop.itemEl && pendingDrop.itemEl.parentNode) {
                        pendingDrop.itemEl.parentNode.removeChild(pendingDrop.itemEl);
                    }
                    pendingDrop.itemEl = null;
                    shiftModal.hide();
                    loadShifts(); 
                } else {
                    alert(res.message);
                }
            });
        }

        function deleteShift(id, el) {
            if (!confirm('Сигурни ли сте, че искате да изтриете тази смяна?')) return;
            fetch('api.php?action=delete_shift', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({id: id})
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    el.closest('.shift-item').style.transform = 'scale(0)';
                    setTimeout(() => el.closest('.shift-item').remove(), 200);
                }
            });
        }

        function openAutoFillModal(userId, userName) {
            autoFillUserId = userId;
            document.getElementById('autoFillWorkerName').innerText = userName;
            autoFillModal.show();
        }

        function submitAutoFill() {
            const startDate = document.getElementById('autoFillStartDate').value;
            if (!startDate) {
                alert('Моля, изберете начална дата.');
                return;
            }

            fetch('api.php?action=auto_fill', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: autoFillUserId,
                    start_date: startDate,
                    location_id: locationId
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    autoFillModal.hide();
                    loadShifts();
                } else {
                    alert(res.message);
                }
            });
        }
    </script>
</body>
</html>
