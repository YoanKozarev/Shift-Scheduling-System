<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $is_24_7 = isset($_POST['is_24_7']) ? 1 : 0;
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO locations (name, is_24_7) VALUES (?, ?)");
            $stmt->execute([$name, $is_24_7]);
            $success_msg = "Обектът е добавен успешно.";
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $is_24_7 = isset($_POST['is_24_7']) ? 1 : 0;
        if ($name && $id) {
            $stmt = $pdo->prepare("UPDATE locations SET name = ?, is_24_7 = ? WHERE id = ?");
            $stmt->execute([$name, $is_24_7, $id]);
            $success_msg = "Обектът е преименуван успешно.";
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
            $stmt->execute([$id]);
            $success_msg = "Обектът е изтрит успешно.";
        }
    }
}

$locations = $pdo->query("SELECT * FROM locations")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление на Обекти</title>
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
                    <li class="nav-item"><a class="nav-link active py-2 px-3" href="admin_locations.php"><i class="bi bi-geo-alt me-1"></i>Обекти</a></li>
                    <li class="nav-item"><a class="nav-link py-2 px-3" href="admin_workers.php"><i class="bi bi-people me-1"></i>Работници</a></li>
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

    <div class="container mt-4 animate-fade-in" style="max-width: 800px;">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Добави Нов Обект</div>
            <div class="card-body">
                <form method="post" class="d-flex flex-wrap align-items-center gap-3">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="name" class="form-control form-control-lg flex-grow-1" placeholder="Име на обекта (напр. Магазин Младост)" required style="min-width: 200px;">
                    <div class="form-check text-nowrap" style="padding-top: 0.5rem; padding-bottom: 0.5rem;">
                        <input class="form-check-input" type="checkbox" name="is_24_7" id="is247Add" value="1" style="width: 1.4em; height: 1.4em;">
                        <label class="form-check-label fs-6 ms-2" for="is247Add">Денонощен (24/7)</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-plus-lg me-1"></i> Добави</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Списък с Обекти</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($locations as $loc): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-3">
                            <div>
                                <span class="fw-bold fs-5"><?php echo htmlspecialchars($loc['name']); ?></span>
                                <?php if ($loc['is_24_7']): ?>
                                    <span class="badge bg-warning text-dark ms-2">24/7 Денонощен</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary px-3 py-2" onclick="editLoc(<?php echo $loc['id']; ?>, '<?php echo htmlspecialchars(addslashes($loc['name'])); ?>', <?php echo $loc['is_24_7']; ?>)"><i class="bi bi-pencil me-1"></i> Редактирай</button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Сигурни ли сте? Изтриването на обект ще изтрие и свързаните с него графици.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $loc['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger px-3 py-2"><i class="bi bi-trash me-1"></i> Изтрий</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($locations)): ?>
                        <li class="list-group-item text-muted text-center py-4">Няма въведени обекти.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editLocModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Редакция на Обект</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editLocId">
            <div class="mb-3">
                <label class="form-label">Име на обект</label>
                <input type="text" name="name" id="editLocName" class="form-control form-control-lg" required>
            </div>
            <div class="mb-3 form-check" style="padding-top: 0.5rem; padding-bottom: 0.5rem;">
                <input class="form-check-input" type="checkbox" name="is_24_7" id="editLocIs247" value="1" style="width: 1.5em; height: 1.5em;">
                <label class="form-check-label fs-5 ms-2" for="editLocIs247">Денонощен (24/7)</label>
            </div>
          </div>
          <div class="modal-footer">
             <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">Отказ</button>
             <button type="submit" class="btn btn-primary px-4 py-2">Запази Промените</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editLocModal = new bootstrap.Modal(document.getElementById('editLocModal'));
        function editLoc(id, currentName, is247) {
            document.getElementById('editLocId').value = id;
            document.getElementById('editLocName').value = currentName;
            document.getElementById('editLocIs247').checked = (is247 == 1);
            editLocModal.show();
        }
    </script>
</body>
</html>
