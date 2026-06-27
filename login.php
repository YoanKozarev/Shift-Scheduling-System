<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_scheduler.php");
    } else {
        header("Location: worker_dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] == 0) {
                $error = 'Профилът ви не е активиран.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_scheduler.php");
                } else {
                    header("Location: worker_dashboard.php");
                }
                exit;
            }
        } else {
            $error = 'Грешен ПИН код.';
        }
    } else {
        $error = 'Моля, изберете потребител и въведете ПИН.';
    }
}

// Fetch users grouped by location
$users = $pdo->query("
    SELECT u.id, u.email, u.full_name, u.role, l.name as location_name
    FROM users u
    LEFT JOIN user_locations ul ON u.id = ul.user_id
    LEFT JOIN locations l ON ul.location_id = l.id
    WHERE u.is_active = 1
    ORDER BY u.role DESC, l.name ASC, u.full_name ASC
")->fetchAll();

// Group by location
$grouped = [];
foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $grouped['Администрация'][] = $u;
    } else {
        $loc = $u['location_name'] ?: 'Неразпределени';
        $grouped[$loc][] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Вход - Система за Графици</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="theme.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            height: 100vh;
            background: #f4f6fb;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            overflow: hidden;
        }

        /* Main container - split layout */
        .login-container {
            display: flex;
            width: 1100px;
            max-width: 96vw;
            height: 650px;
            max-height: 92vh;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 70px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0,0,0,0.04);
            animation: slideUp 0.5s ease forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ══════════ LEFT PANEL - User List ══════════ */
        .left-panel {
            width: 480px;
            min-width: 480px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e5e7eb;
        }

        .left-header {
            padding: 28px 28px 20px;
            border-bottom: 1px solid #f0f2f5;
        }
        .left-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .left-header p {
            font-size: 0.85rem;
            color: #94a3b8;
            margin: 0;
        }

        .user-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px 16px;
        }
        .user-list::-webkit-scrollbar { width: 4px; }
        .user-list::-webkit-scrollbar-track { background: transparent; }
        .user-list::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

        /* Location group header */
        .location-group {
            padding: 10px 12px 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            font-variant: small-caps;
        }
        .location-group i {
            margin-right: 4px;
            font-size: 0.72rem;
        }

        .user-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 2px solid transparent;
            margin: 2px 0;
            touch-action: manipulation;
        }
        .user-option:hover {
            background: #f6f8fa;
        }
        .user-option.selected {
            background: #eef2ff;
            border-color: #4f46e5;
        }
        .user-option .user-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #94a3b8;
            font-size: 1rem;
        }
        .user-option .user-name {
            color: #1e293b;
            font-weight: 500;
            font-size: 0.92rem;
            line-height: 1.3;
        }
        .user-option .user-role {
            color: #94a3b8;
            font-size: 0.75rem;
        }

        /* ══════════ RIGHT PANEL - PIN Entry ══════════ */
        .right-panel {
            flex: 1;
            background: #1e293b;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle geometric pattern */
        .right-panel::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, transparent 70%);
            top: -60px;
            right: -60px;
            pointer-events: none;
        }
        .right-panel::after {
            content: '';
            position: absolute;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.06) 0%, transparent 70%);
            bottom: -40px;
            left: -40px;
            pointer-events: none;
        }

        .pin-section {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 320px;
            text-align: center;
        }

        .selected-user-display {
            margin-bottom: 28px;
        }
        .selected-user-display .no-user {
            color: rgba(255,255,255,0.3);
            font-size: 0.95rem;
            font-weight: 400;
        }
        .selected-user-display .user-selected-name {
            color: #ffffff;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .selected-user-display .user-selected-loc {
            color: rgba(255,255,255,0.4);
            font-size: 0.8rem;
        }

        /* PIN dots */
        .pin-display {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 28px;
            min-height: 48px;
            align-items: center;
        }
        .pin-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .pin-dot.filled {
            background: #4f46e5;
            box-shadow: 0 0 12px rgba(79, 70, 229, 0.5);
            transform: scale(1.2);
        }
        .pin-dot.shake {
            animation: shake 0.5s ease-in-out;
            background: #ef4444;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.5);
        }
        .pin-placeholder {
            color: rgba(255,255,255,0.2);
            font-size: 0.85rem;
            letter-spacing: 2px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-6px); }
            80% { transform: translateX(6px); }
        }

        /* Numpad */
        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
        }
        .numpad-btn {
            width: 100%;
            aspect-ratio: 1.4;
            border: none;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.07);
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.12s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            user-select: none;
            border: 1px solid rgba(255,255,255,0.04);
            min-height: 44px;
        }
        .numpad-btn:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .numpad-btn:active {
            background: rgba(79, 70, 229, 0.3);
            transform: scale(0.92);
        }
        .numpad-btn.btn-action {
            font-size: 1.1rem;
        }
        .numpad-btn.btn-clear {
            color: #f87171;
            background: rgba(239, 68, 68, 0.08);
        }
        .numpad-btn.btn-clear:hover {
            background: rgba(239, 68, 68, 0.15);
        }
        .numpad-btn.btn-enter {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
            font-size: 1rem;
            font-weight: 600;
        }
        .numpad-btn.btn-enter:hover {
            box-shadow: 0 6px 28px rgba(79, 70, 229, 0.4);
        }
        .numpad-btn.btn-enter:active {
            transform: scale(0.92);
        }

        /* Error message */
        .error-toast {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            animation: fadeToast 0.3s ease;
            z-index: 10;
            white-space: nowrap;
        }

        @keyframes fadeToast {
            from { opacity: 0; transform: translateX(-50%) translateY(-10px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* Demo info */
        .demo-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 8px;
            font-size: 0.75rem;
            z-index: 100;
        }
        .demo-bar strong { color: rgba(255,255,255,0.7); }

        /* Hidden form */
        .hidden-form { position: absolute; left: -9999px; }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                height: auto;
                max-height: 95vh;
                width: 95vw;
            }
            .left-panel {
                width: 100%;
                min-width: 100%;
                max-height: 35vh;
            }
            .right-panel {
                padding: 30px 24px;
            }
        }

        /* Floating theme toggle button */
        .theme-toggle-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.05);
            border: 1px solid rgba(15, 23, 42, 0.08);
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .theme-toggle-floating:hover {
            background: rgba(15, 23, 42, 0.1);
            transform: scale(1.05);
        }
        .theme-toggle-floating:active {
            transform: scale(0.95);
        }

        /* ══════════ DARK MODE SUPPORT FOR LOGIN ══════════ */
        [data-theme="dark"] body {
            background: #0f172a;
        }
        [data-theme="dark"] .login-container {
            background: #1e293b;
            box-shadow: 0 20px 70px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255,255,255,0.05);
        }
        [data-theme="dark"] .left-panel {
            background: #1e293b;
            border-right: 1px solid #334155;
        }
        [data-theme="dark"] .left-header {
            border-bottom: 1px solid #334155;
        }
        [data-theme="dark"] .left-header h2 {
            color: #f1f5f9;
        }
        [data-theme="dark"] .user-option:hover {
            background: #334155;
        }
        [data-theme="dark"] .user-option.selected {
            background: rgba(79, 70, 229, 0.15);
            border-color: #6366f1;
        }
        [data-theme="dark"] .user-option .user-icon {
            background: #0f172a;
            color: #64748b;
        }
        [data-theme="dark"] .user-option .user-name {
            color: #f1f5f9;
        }
        [data-theme="dark"] .user-option.selected .user-name {
            color: #ffffff;
        }
        [data-theme="dark"] .right-panel {
            background: #0f172a;
        }
        [data-theme="dark"] .theme-toggle-floating {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.1);
            color: #e2e8f0;
        }
        [data-theme="dark"] .theme-toggle-floating:hover {
            background: rgba(255,255,255,0.15);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <button class="theme-toggle-floating" id="themeToggleBtn" onclick="toggleTheme()" title="Тъмна тема"><i class="bi bi-moon-stars"></i></button>
    <!-- Hidden form for actual submission -->
    <form method="post" action="login.php" id="loginForm" class="hidden-form">
        <input type="hidden" name="email" id="hiddenEmail">
        <input type="hidden" name="password" id="hiddenPassword">
    </form>

    <div class="login-container">
        <!-- ═══════ LEFT: User List ═══════ -->
        <div class="left-panel">
            <div class="left-header">
                <h2><i class="bi bi-calendar3" style="margin-right:8px;"></i>Система за Графици <span style="display:inline-block;background:linear-gradient(135deg,#f59e0b,#f97316);color:#451a03;font-weight:700;font-size:0.55rem;padding:0.15rem 0.45rem;border-radius:5px;letter-spacing:1.2px;text-transform:uppercase;margin-left:6px;vertical-align:middle;">ДЕМО РЕЖИМ</span></h2>
                <p>Изберете вашия профил</p>
            </div>
            <div class="user-list" id="userList">
                <?php foreach ($grouped as $group_name => $group_users): ?>
                    <div class="location-group">
                        <i class="bi bi-geo-alt"></i><?php echo htmlspecialchars(mb_strtoupper($group_name, 'UTF-8')); ?>
                    </div>
                    <?php foreach ($group_users as $u): ?>
                        <div class="user-option" 
                             data-email="<?php echo htmlspecialchars($u['email']); ?>"
                             data-name="<?php echo htmlspecialchars($u['full_name']); ?>"
                             data-location="<?php echo htmlspecialchars($u['location_name'] ?? 'Администрация'); ?>"
                             onclick="selectUser(this)">
                            <div class="user-icon">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                <div class="user-role"><?php echo $u['role'] === 'admin' ? 'Администратор' : htmlspecialchars($u['location_name'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ═══════ RIGHT: PIN Entry ═══════ -->
        <div class="right-panel">
            <?php if ($error): ?>
                <div class="error-toast"><i class="bi bi-exclamation-circle" style="margin-right:4px;"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="pin-section">
                <div class="selected-user-display" id="selectedDisplay">
                    <div class="no-user">← Изберете потребител</div>
                </div>

                <!-- PIN dots -->
                <div class="pin-display" id="pinDisplay">
                    <span class="pin-placeholder">Въведете ПИН</span>
                </div>

                <!-- Touch Numpad -->
                <div class="numpad">
                    <button class="numpad-btn" onclick="pressKey('1')">1</button>
                    <button class="numpad-btn" onclick="pressKey('2')">2</button>
                    <button class="numpad-btn" onclick="pressKey('3')">3</button>
                    <button class="numpad-btn" onclick="pressKey('4')">4</button>
                    <button class="numpad-btn" onclick="pressKey('5')">5</button>
                    <button class="numpad-btn" onclick="pressKey('6')">6</button>
                    <button class="numpad-btn" onclick="pressKey('7')">7</button>
                    <button class="numpad-btn" onclick="pressKey('8')">8</button>
                    <button class="numpad-btn" onclick="pressKey('9')">9</button>
                    <button class="numpad-btn btn-action btn-clear" onclick="clearPin()"><i class="bi bi-x-lg"></i></button>
                    <button class="numpad-btn" onclick="pressKey('0')">0</button>
                    <button class="numpad-btn btn-action btn-enter" onclick="submitLogin()"><i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="demo-bar">
        <span style="display:inline-block;background:linear-gradient(135deg,#f59e0b,#f97316);color:#451a03;font-weight:700;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:5px;letter-spacing:1px;margin-right:8px;">ДЕМО РЕЖИМ</span>
        Администратор ПИН: <strong>0000</strong> &nbsp;|&nbsp; Работници ПИН: <strong>1234</strong>
    </div>

    <script>
        let selectedEmail = '';
        let selectedName = '';
        let selectedLocation = '';
        let pin = '';
        const maxLen = 10;
        const minLen = 4;

        function selectUser(el) {
            document.querySelectorAll('.user-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            selectedEmail = el.dataset.email;
            selectedName = el.dataset.name;
            selectedLocation = el.dataset.location;

            document.getElementById('selectedDisplay').innerHTML = `
                <div class="user-selected-name">${selectedName}</div>
                <div class="user-selected-loc">${selectedLocation}</div>
            `;

            clearPin();
        }

        function pressKey(digit) {
            if (pin.length >= maxLen) return;
            if (!selectedEmail) {
                const list = document.getElementById('userList');
                list.style.boxShadow = 'inset 0 0 0 2px #4f46e5';
                setTimeout(() => list.style.boxShadow = '', 600);
                return;
            }

            pin += digit;
            updateDisplay();
        }

        function clearPin() {
            pin = '';
            updateDisplay();
        }

        function updateDisplay() {
            const container = document.getElementById('pinDisplay');
            if (pin.length === 0) {
                container.innerHTML = '<span class="pin-placeholder">Въведете ПИН</span>';
                return;
            }
            let html = '';
            for (let i = 0; i < pin.length; i++) {
                html += '<div class="pin-dot filled"></div>';
            }
            container.innerHTML = html;
        }

        function submitLogin() {
            if (!selectedEmail) {
                const list = document.getElementById('userList');
                list.style.boxShadow = 'inset 0 0 0 2px #4f46e5';
                setTimeout(() => list.style.boxShadow = '', 600);
                return;
            }
            if (pin.length < minLen) {
                document.querySelectorAll('.pin-dot').forEach(d => d.classList.add('shake'));
                setTimeout(() => document.querySelectorAll('.pin-dot').forEach(d => d.classList.remove('shake')), 600);
                return;
            }

            document.getElementById('hiddenEmail').value = selectedEmail;
            document.getElementById('hiddenPassword').value = pin;
            document.getElementById('loginForm').submit();
        }

        // Keyboard support
        document.addEventListener('keydown', (e) => {
            if (e.key >= '0' && e.key <= '9') pressKey(e.key);
            else if (e.key === 'Backspace') { pin = pin.slice(0, -1); updateDisplay(); }
            else if (e.key === 'Enter') submitLogin();
            else if (e.key === 'Escape') clearPin();
        });

        <?php if ($error): ?>
        setTimeout(() => {
            const toast = document.querySelector('.error-toast');
            if (toast) toast.style.display = 'none';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
