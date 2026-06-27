<?php
require_once 'config.php';

$code = $_GET['code'] ?? '';
$error = '';
$success = '';
$user = null;

if ($code) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE auth_code = ? AND is_active = 0 LIMIT 1");
    $stmt->execute([$code]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Невалиден или вече използван код за активация.';
    }
} else {
    $error = 'Липсва код за активация.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 4 || !ctype_digit($password)) {
        $error = 'ПИН кодът трябва да е поне 4 цифри.';
    } elseif ($password !== $confirm_password) {
        $error = 'ПИН кодовете не съвпадат.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1, auth_code = NULL WHERE id = ?");
        if ($stmt->execute([$hash, $user['id']])) {
            $success = 'Профилът ви е активиран успешно! Може да влезете в системата.';
            $user = null; // Hide the form
        } else {
            $error = 'Възникна грешка при активацията.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Активация на профил</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>
<body class="d-flex align-items-center py-4">
    <main class="form-signin w-100 m-auto animate-fade-in" style="max-width: 450px; padding: 15px;">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="h3 mb-3 fw-bold text-center" style="color: var(--primary);">Активация на профил</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-3 text-center">
                            <a href="login.php" class="btn btn-primary">Към вход</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($user && !$success): ?>
                    <p class="text-muted text-center mb-4">Здравейте, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>. Моля, създайте 4-цифрен ПИН код.</p>
                    <form method="post" action="activate.php?code=<?php echo htmlspecialchars($code); ?>">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" placeholder="PIN" required pattern="\d{4}" maxlength="4" inputmode="numeric">
                            <label for="password">Нов ПИН код (4 цифри)</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm PIN" required pattern="\d{4}" maxlength="4" inputmode="numeric">
                            <label for="confirm_password">Повторете ПИН кода</label>
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Активирай</button>
                    </form>
                <?php endif; ?>
                
                <?php if (!$user && !$success && !$error): ?>
                     <div class="alert alert-warning">Невалиден достъп.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
