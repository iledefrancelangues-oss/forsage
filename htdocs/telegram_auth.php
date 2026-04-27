<?php
// telegram_auth.php — Авторизация через Telegram Login Widget.
//
// Конфигурация бота берётся из локального файла tg_config.php (он в .gitignore
// и не коммитится в публичный репозиторий) или из переменных окружения
// TELEGRAM_BOT_TOKEN / TELEGRAM_BOT_USERNAME.
//
// Чтобы настроить:
//   1) Создайте бота через @BotFather и получите токен и @username.
//   2) В @BotFather → /setdomain → укажите домен сайта (forsage.ct.ws и т.п.).
//   3) Создайте htdocs/tg_config.php со строками:
//        <?php
//        $bot_token    = '123456:AA...';
//        $bot_username = 'my_login_bot';   // без @
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Загрузка конфигурации бота ---------------------------------------------
$bot_token    = '';
$bot_username = '';

$config_path = __DIR__ . '/tg_config.php';
if (is_file($config_path)) {
    require $config_path; // ожидается, что выставит $bot_token и $bot_username
}
if (!$bot_token)    { $bot_token    = getenv('TELEGRAM_BOT_TOKEN')    ?: ''; }
if (!$bot_username) { $bot_username = getenv('TELEGRAM_BOT_USERNAME') ?: ''; }

$is_configured =
    $bot_token && $bot_username
    && $bot_token    !== 'YOUR_BOT_TOKEN'
    && $bot_username !== 'YOUR_BOT_USERNAME';

// --- Проверка подписи Telegram ----------------------------------------------
function checkTelegramAuthorization(array $auth_data, string $bot_token): bool {
    if (!isset($auth_data['hash'], $auth_data['auth_date'])) return false;
    $check_hash = $auth_data['hash'];
    unset($auth_data['hash']);

    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        if ($value === '' || $value === null) continue;
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);

    $data_check_string = implode("\n", $data_check_arr);
    $secret_key = hash('sha256', $bot_token, true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);

    if (!hash_equals($hash, $check_hash)) {
        return false;
    }
    // Данные не должны быть старше 24 часов.
    if ((time() - (int)$auth_data['auth_date']) > 86400) {
        return false;
    }
    return true;
}

// --- Обработка callback от Telegram -----------------------------------------
if ($is_configured && isset($_GET['id'], $_GET['hash'], $_GET['auth_date'])) {
    require_once 'db.php';
    $auth_data = [
        'id'         => $_GET['id'],
        'first_name' => $_GET['first_name'] ?? '',
        'last_name'  => $_GET['last_name']  ?? '',
        'username'   => $_GET['username']   ?? '',
        'photo_url'  => $_GET['photo_url']  ?? '',
        'auth_date'  => $_GET['auth_date'],
        'hash'       => $_GET['hash'],
    ];

    if (!checkTelegramAuthorization($auth_data, $bot_token)) {
        http_response_code(403);
        die('Ошибка проверки данных Telegram');
    }

    try {
        $telegram_id       = (int)$auth_data['id'];
        $telegram_username = $auth_data['username'] ?: ('user_' . $telegram_id);
        $full_name         = trim(($auth_data['first_name'] ?? '') . ' ' . ($auth_data['last_name'] ?? ''));

        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            $pdo->prepare("UPDATE users SET telegram_username = ?, last_login = NOW() WHERE id = ?")
                ->execute([$telegram_username, $user['id']]);
        } else {
            // Если username из Telegram уже занят — добавим суффикс.
            $candidate = $telegram_username;
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$candidate]);
            if ($check->fetch()) {
                $candidate = $telegram_username . '_' . $telegram_id;
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (username, telegram_id, telegram_username, full_name, entity_type,
                     user_status, balance, created_at, last_login)
                VALUES (?, ?, ?, ?, 'individual', 'base', 0, NOW(), NOW())
            ");
            $stmt->execute([$candidate, $telegram_id, $telegram_username, $full_name]);

            $new_user_id = $pdo->lastInsertId();
            $_SESSION['user_id']  = $new_user_id;
            $_SESSION['username'] = $candidate;
        }

        header('Location: profile.php?telegram_auth=success');
        exit;

    } catch (Exception $e) {
        die('Ошибка БД: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход через Telegram</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1e293b;
        }
        .container {
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        }
        h1 { font-size: 24px; margin-bottom: 8px; color: #0f172a; }
        p  { color: #475569; margin-bottom: 28px; font-size: 14px; line-height: 1.5; }
        .telegram-widget { margin: 0 auto 20px; min-height: 56px; }
        .back-link {
            display: inline-block; margin-top: 8px;
            color: #2563eb; text-decoration: none;
            font-size: 14px; font-weight: 600;
            padding: 10px 20px; border-radius: 8px;
            transition: background 0.2s;
        }
        .back-link:hover { background: #eff6ff; }
        .err {
            text-align: left; background: #fef2f2; color: #991b1b;
            border: 1px solid #fecaca; border-radius: 12px;
            padding: 16px; font-size: 13px; line-height: 1.6;
        }
        .err code { background: #fff; padding: 1px 6px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Вход через Telegram</h1>

        <?php if ($is_configured): ?>
            <p>Нажмите кнопку ниже для авторизации через Telegram.</p>
            <div class="telegram-widget">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="<?= htmlspecialchars($bot_username) ?>"
                        data-size="large"
                        data-radius="10"
                        data-auth-url="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']) ?>"
                        data-request-access="write"></script>
            </div>
        <?php else: ?>
            <p>Авторизация через Telegram пока не настроена.</p>
            <div class="err">
                <strong>Как включить:</strong><br>
                1) Создайте бота в <a href="https://t.me/BotFather" target="_blank">@BotFather</a>, получите токен и <code>username</code>.<br>
                2) В <code>@BotFather → /setdomain</code> укажите домен сайта.<br>
                3) Создайте файл <code>htdocs/tg_config.php</code>:<br>
                <code style="display:block;margin-top:8px;white-space:pre-wrap;">&lt;?php
$bot_token    = '123456:AA...';
$bot_username = 'my_login_bot';</code>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Вернуться на главную</a>
    </div>
</body>
</html>
