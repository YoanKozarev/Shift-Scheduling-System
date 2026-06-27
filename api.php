<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper to respond
function jsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

if ($action === 'get_shifts' && $method === 'GET') {
    $location_id = $_GET['location_id'] ?? 1;
    $month = $_GET['month'] ?? date('Y-m'); // Format YYYY-MM

    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.date, s.shift_type, u.full_name, u.is_underage, u.color_hex 
        FROM schedules s
        JOIN users u ON s.user_id = u.id
        WHERE s.location_id = ? AND s.date LIKE ?
    ");
    $stmt->execute([$location_id, "$month-%"]);
    $shifts = $stmt->fetchAll();

    jsonResponse('success', '', $shifts);
}

if ($action === 'get_absences' && $method === 'GET') {
    $month = $_GET['month'] ?? date('Y-m');
    $stmt = $pdo->prepare("SELECT user_id, date, reason FROM absences WHERE date LIKE ?");
    $stmt->execute(["$month-%"]);
    $absences = $stmt->fetchAll();
    jsonResponse('success', '', $absences);
}

if ($action === 'save_shift' && $method === 'POST') {
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse('error', 'Only admins can save shifts.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    $date = $input['date'] ?? null;
    $shift_type = $input['shift_type'] ?? null;
    $location_id = $input['location_id'] ?? 1;

    if (!$user_id || !$date || !$shift_type) {
        jsonResponse('error', 'Missing data');
    }

    // Check underage constraint
    if ($shift_type === 'night') {
        $stmt = $pdo->prepare("SELECT is_underage FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && $user['is_underage'] == 1) {
            jsonResponse('error', 'underage_night_shift'); // Handled specially on frontend
        }
    }

    // Upsert shift
    $stmt = $pdo->prepare("
        INSERT INTO schedules (location_id, user_id, date, shift_type) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE shift_type = ?
    ");
    $stmt->execute([$location_id, $user_id, $date, $shift_type, $shift_type]);
    
    $shift_id = $pdo->lastInsertId();
    if ($shift_id == 0) {
        // Find existing id
        $st = $pdo->prepare("SELECT id FROM schedules WHERE user_id = ? AND date = ?");
        $st->execute([$user_id, $date]);
        $shift_id = $st->fetchColumn();
    }

    jsonResponse('success', 'Shift saved', ['id' => $shift_id]);
}

if ($action === 'delete_shift' && $method === 'POST') {
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse('error', 'Only admins can delete shifts.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $shift_id = $input['id'] ?? null;

    if ($shift_id) {
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
        $stmt->execute([$shift_id]);
        jsonResponse('success', 'Shift deleted');
    }
    jsonResponse('error', 'Missing ID');
}

if ($action === 'auto_fill' && $method === 'POST') {
     if ($_SESSION['role'] !== 'admin') {
        jsonResponse('error', 'Only admins can auto-fill.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    $start_date = $input['start_date'] ?? null;
    $location_id = $input['location_id'] ?? 1;

    if (!$user_id || !$start_date) {
        jsonResponse('error', 'Missing parameters for auto-fill');
    }

    // Determine underage status to avoid adding night shifts automatically if underage
    $stmt = $pdo->prepare("SELECT is_underage FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $is_underage = $user['is_underage'] == 1;

    // Determine location 24/7 status
    $stmt = $pdo->prepare("SELECT is_24_7 FROM locations WHERE id = ?");
    $stmt->execute([$location_id]);
    $loc = $stmt->fetch();
    $is_24_7 = $loc && $loc['is_24_7'] == 1;

    $date_obj = new DateTime($start_date);
    $month = $date_obj->format('m');
    $year = $date_obj->format('Y');
    
    // Pattern: Day, Night, Off, Off (or Day, Off, Off, Off if underage) for 24/7 locations
    // Pattern: Day, Off, Off, Off for non-24/7 locations
    if ($is_24_7) {
        $pattern = ['day', 'night', 'off', 'off'];
        if ($is_underage) {
            $pattern = ['day', 'off', 'off', 'off'];
        }
    } else {
        $pattern = ['day', 'off', 'off', 'off'];
    }

    $pattern_length = count($pattern);
    $pattern_index = 0;
    
    $pdo->beginTransaction();
    try {
        while ($date_obj->format('m') === $month) {
            $current_date_str = $date_obj->format('Y-m-d');
            $shift_type = $pattern[$pattern_index];
            
            if ($shift_type !== 'off') {
                $stmt = $pdo->prepare("
                    INSERT INTO schedules (location_id, user_id, date, shift_type) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE shift_type = ?
                ");
                $stmt->execute([$location_id, $user_id, $current_date_str, $shift_type, $shift_type]);
            }

            $date_obj->modify('+1 day');
            $pattern_index = ($pattern_index + 1) % $pattern_length;
        }
        $pdo->commit();
        jsonResponse('success', '2/2 pattern applied successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', $e->getMessage());
    }
}

jsonResponse('error', 'Invalid action');
