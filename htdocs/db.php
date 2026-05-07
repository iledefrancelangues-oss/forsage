<?php
$host = 'sql102.infinityfree.com';
$db   = 'if0_41359384_era';
$user = 'if0_41359384';
$pass = 'wd41sm3f';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Ошибка базы: ' . $e->getMessage());
}

/* Session-bootstrap: fill $_SESSION['role']/'usertype' from DB once per session.
   Needed because old login sessions lack these keys; admin gates rely on them. */
if (session_status() !== PHP_SESSION_NONE && !empty($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    try {
        $__st = $pdo->prepare("SELECT role, user_type FROM users WHERE id = ? LIMIT 1");
        $__st->execute([(int)$_SESSION['user_id']]);
        if ($__row = $__st->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['role']     = $__row['role']      ?? 'user';
            $_SESSION['usertype'] = $__row['user_type'] ?? 'user';
            if (($__row['role'] ?? '') === 'admin' || ($__row['user_type'] ?? '') === 'admin') {
                $_SESSION['role']     = 'admin';
                $_SESSION['usertype'] = 'admin';
            }
        }
    } catch (Throwable $__e) { /* ignore */ }
}
