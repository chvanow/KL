<?php
// admin/index.php
require_once '../config.php';

// Простая авторизация (замените на свою)
$admins = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    'lawyer1' => password_hash('lawyer123', PASSWORD_DEFAULT)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($admins[$username]) && password_verify($password, $admins[$username])) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Личный кабинет юриста</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">⚖️</div>
                <h1>Личный кабинет юриста</h1>
                <p>Портал deti.gov.ru</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Войти в систему
                </button>
            </form>
        </div>
    </div>
</body>
</html>