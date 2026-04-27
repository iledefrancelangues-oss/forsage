<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
date_default_timezone_set('Europe/Moscow');

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : '');

// Если пользователь уже авторизован – перенаправляем на редирект или на главную
if (!empty($_SESSION['user_id'])) {
    if ($redirect) {
        header('Location: ' . $redirect);
    } else {
        header('Location: index.php');
    }
    exit;
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $stmt = $pdo->prepare("SELECT id, password, user_type, balance, username FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['usertype']  = $user['user_type'] ?? 'user';
            $_SESSION['balance']   = (float)$user['balance'];

            if ($redirect) {
                header('Location: ' . $redirect);
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход в личный кабинет — Форсаж</title>
    <style>
        body {
            background: #0a0f1e;
            color: #fff;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-box {
            background: #1e293b;
            border-radius: 24px;
            border: 1px solid #334155;
            padding: 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 35px -8px rgba(0,0,0,0.5);
        }
        .login-box h2 {
            text-align: center;
            margin: 0 0 24px;
            font-size: 24px;
            font-weight: 800;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #94a3b8;
        }
        input {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #fff;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #f59e0b;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        button:hover {
            opacity: 0.9;
        }
        .error {
            background: #7f1a1a;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            text-align: center;
        }
        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #94a3b8;
        }
        .register-link a {
            color: #f59e0b;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h2>Вход в личный кабинет</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div class="form-group">
            <label>Логин или Email</label>
            <input type="text" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
    <div class="register-link">
        Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
    </div>
</div>
</body>
</html>