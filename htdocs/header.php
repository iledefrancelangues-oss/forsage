<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['lang'])) {
    $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'ru';
    $_SESSION['lang'] = (substr($accept_lang, 0, 2) === 'ru') ? 'ru' : 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'en') ? 'en' : 'ru';
}
$lang = $_SESSION['lang'];

if (!function_exists('t')) {
    function t(string $ru, string $en = ''): string {
        global $lang;
        return ($lang === 'en' && $en !== '') ? $en : $ru;
    }
}

$t = [
    'ru' => [
        'login'    => 'ВХОД',
        'logout'   => 'ВЫЙТИ',
        'msc'      => 'МСК',
        'main'     => 'ГЛАВНАЯ',
        'auctions' => 'ТОРГИ',
        'comm'     => 'КОМИССИОННАЯ ПРОДАЖА',
        'order'    => 'МАРКЕТПЛЕЙС',
        'franchise'=> 'ФРАНШИЗА',
        'profile'  => 'ЛК',
    ],
    'en' => [
        'login'    => 'SIGN IN',
        'logout'   => 'SIGN OUT',
        'msc'      => 'MSK',
        'main'     => 'HOME',
        'auctions' => 'AUCTIONS',
        'comm'     => 'COMMISSION SALES',
        'order'    => 'MARKETPLACE',
        'franchise'=> 'FRANCHISE',
        'profile'  => 'PROFILE',
    ],
];

$is_auth      = !empty($_SESSION['user_id']);
$user_name    = $_SESSION['user_name']    ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

/* Свежий баланс из БД (синхронизация с profile.php).
 * Если $pdo ещё не загружен страницей — подключаем db.php сами.
 * При недоступной БД — fallback на сессионный кэш.
 */
$user_bal = $_SESSION['user_balance'] ?? 0;
if ($is_auth) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $__db_path = __DIR__ . '/db.php';
        if (is_file($__db_path)) { @include_once $__db_path; }
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $__st = $pdo->prepare('SELECT balance FROM users WHERE id=? LIMIT 1');
            $__st->execute([(int)$_SESSION['user_id']]);
            $__bal = $__st->fetchColumn();
            if ($__bal !== false) {
                $user_bal = (float)$__bal;
                $_SESSION['user_balance'] = $user_bal;
            }
        } catch (Throwable $e) { /* fallback на сессию */ }
    }
}

$current_url = strtok($_SERVER['REQUEST_URI'], '?');
$query       = $_GET;
unset($query['lang']);
$qs       = http_build_query($query);
$base_url = $current_url . ($qs ? '?' . $qs . '&' : '?');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ERA ETP</title>

<?php if ($is_auth): ?>
<meta name="user-id" content="<?= (int)($_SESSION['user_id'] ?? 0) ?>">
<?php endif; ?>

<!-- PWA -->
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0088cc">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ЭРА ЭТП">
<meta name="application-name" content="ЭРА ЭТП">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png">

<!-- Перфоманс: preconnect + display=swap для шрифтов, defer для Lucide. -->
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://unpkg.com" crossorigin>
<link rel="preconnect" href="https://api.qrserver.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest" defer></script>


<!--
    ВАЖНО: объявляем заглушку openAuth ДО того, как появится кнопка в шапке.
    Заглушка ставит вызов в очередь. После того как auth_modal.php загрузит
    настоящую openAuth — очередь автоматически сбрасывается.
    Это решает гонку: кнопка «Войти» на десктопе рендерится до того,
    как скрипт auth_modal.php (в конце страницы) успевает объявить openAuth.
-->
<script>
(function () {
    /* Очередь вызовов, сделанных до готовности модалки */
    var _queue = [];
    var _ready = false;

    /* Временная заглушка — складывает вызовы в очередь */
    window.openAuth = function (tab) {
        if (_ready) return; /* на случай двойного вызова */
        _queue.push(tab || 'login');
    };

    /* Вызывается auth_modal.php сразу после объявления настоящей openAuth */
    window.__authModalReady = function (realOpenAuth) {
        _ready = true;
        /* Переопределяем глобальный openAuth настоящей функцией */
        window.openAuth = realOpenAuth;
        /* Сбрасываем накопленную очередь */
        _queue.forEach(function (tab) { realOpenAuth(tab); });
        _queue = [];
    };
}());
</script>

<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
html{overflow-x:hidden}
body{font-family:'Inter',sans-serif;background:#fff;display:flex;flex-direction:column;padding-top:80px;overflow-x:hidden;max-width:100vw}
header{position:fixed;top:0;left:0;right:0;height:80px;background:#e5e4e2;display:flex;justify-content:space-between;align-items:center;padding:0 4%;box-shadow:0 4px 20px rgba(0,0,0,.08);z-index:1000;width:100%;max-width:100vw}
.logo-img{height:40px;flex-shrink:0}
.nav-menu{display:flex;gap:24px;align-items:center;min-width:0;flex:1 1 auto;justify-content:center}
.nav-link{text-decoration:none;color:#1e293b;font-weight:800;font-size:13px;white-space:nowrap}
.nav-link:hover,.nav-link.active{color:#0088cc}
.header-right{display:flex;align-items:center;gap:10px;min-width:0;flex-shrink:0}
.header-auth-block{display:flex;align-items:center;gap:10px}
.msc-box{display:flex;align-items:center;gap:6px;background:#fff;padding:5px 12px;border-radius:50px;border:1px solid #d1d5db;font-weight:800;font-size:12px;white-space:nowrap}
.dot{width:7px;height:7px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;flex-shrink:0}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
.lang-switcher{display:flex;gap:4px}
.lang-btn{text-decoration:none;color:#64748b;font-size:11px;font-weight:800;padding:5px 10px;border-radius:6px;border:1px solid #d1d5db;background:#fff;cursor:pointer;white-space:nowrap}
.lang-btn.active{background:#1e293b;color:#fff;border-color:#1e293b}
.btn-login{background:#0088cc;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:900;cursor:pointer;font-size:13px;white-space:nowrap}
.btn-login:hover{background:#0077b3}
.burger-trigger{display:none;cursor:pointer;width:auto;min-width:44px;height:38px;padding:0 14px;align-items:center;justify-content:center;background:#0088cc;border:none;flex-shrink:0;color:#fff;border-radius:999px;-webkit-tap-highlight-color:transparent;box-shadow:0 4px 14px rgba(0,136,204,.25)}
.burger-trigger:hover{background:#0077b3}
.burger-trigger:active{background:#006aa0}
.burger-trigger i,.burger-trigger svg{color:#fff!important;stroke:#fff!important;stroke-width:2.5!important;width:24px!important;height:24px!important;display:block}
#mobileMenu{position:fixed;top:0;left:0;right:0;bottom:0;height:100vh;height:100dvh;background:#fff;z-index:2001;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:max(72px,calc(env(safe-area-inset-top,0px) + 64px)) 20px max(32px,calc(env(safe-area-inset-bottom,0px) + 24px));overflow-y:auto;-webkit-overflow-scrolling:touch;transform:translateX(100%);transition:transform .35s ease}
#mobileMenu.open{transform:translateX(0)}
.mob-close{position:absolute;top:18px;right:18px;cursor:pointer;width:44px;height:44px;display:inline-flex;align-items:center;justify-content:center;background:transparent;border:none;color:#1e293b;-webkit-tap-highlight-color:transparent;padding:0}
.mob-close i,.mob-close svg{color:#1e293b!important;stroke:#1e293b!important;stroke-width:2.5!important;width:30px!important;height:30px!important}
.mob-nav-link{display:block;text-decoration:none;color:#1e293b;font-size:20px;font-weight:800;padding:14px 0;text-align:center;width:100%;border-bottom:1px solid #f1f5f9}
.mob-nav-link:hover{color:#0088cc}
.mob-lang-block{display:flex;gap:10px;margin:24px 0 10px}
.mob-lang-btn{text-decoration:none;font-size:15px;font-weight:800;padding:10px 28px;border-radius:8px;border:2px solid #d1d5db;background:#fff;color:#64748b;cursor:pointer}
.mob-lang-btn.active{background:#1e293b;color:#fff;border-color:#1e293b}
.mob-auth-block{margin-top:auto;width:100%;text-align:center;border-top:1px solid #f1f5f9;padding-top:20px;flex-shrink:0}
.mob-username{font-size:20px;font-weight:900;color:#1e293b;margin-bottom:4px}
.mob-balance{font-size:16px;color:#22c55e;font-weight:700;margin-bottom:16px}
.mob-links{display:flex;gap:10px;justify-content:center}
.mob-link{text-decoration:none;font-size:15px;font-weight:700;padding:10px 28px;border-radius:10px}
.mob-link-lk{color:#0088cc;border:2px solid rgba(0,136,204,.2)}
.mob-link-out{color:#ef4444;border:2px solid #fee2e2}
@media(max-width:1100px){.nav-menu{display:none}.lang-switcher{display:none}.burger-trigger{display:inline-flex}}
@media(max-width:768px){.header-auth-block{display:none}}
@media(max-width:600px){header{height:70px;padding:0 3%}body{padding-top:70px}.logo-img{height:32px}.msc-box{font-size:11px;padding:4px 9px}.btn-login{padding:7px 14px;font-size:12px}.header-right{gap:8px}}
@media(max-width:480px){.msc-box{font-size:10px;padding:3px 8px;gap:4px}.btn-login{padding:6px 12px;font-size:12px}}
@media(max-width:380px){.btn-login{padding:6px 10px;font-size:11px}.logo-img{height:28px}.header-right{gap:6px}.msc-box{font-size:9px;padding:3px 7px}}

/* Кнопка «Наверх» — фикс справа внизу, появляется после прокрутки. */
#to-top{position:fixed;right:22px;bottom:22px;z-index:9999;width:48px;height:48px;border-radius:50%;border:1px solid rgba(56,189,248,.5);background:linear-gradient(135deg,#0088cc,#38bdf8);color:#fff;cursor:pointer;box-shadow:0 12px 30px rgba(8,145,178,.45),0 0 24px rgba(56,189,248,.5);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transform:translateY(12px);transition:opacity .25s ease,transform .25s ease,box-shadow .25s ease;padding:0}
#to-top.visible{opacity:1;pointer-events:auto;transform:translateY(0)}
#to-top:hover{box-shadow:0 16px 38px rgba(8,145,178,.65),0 0 32px rgba(56,189,248,.8);transform:translateY(-3px)}
@media(max-width:480px){#to-top{right:14px;bottom:14px;width:44px;height:44px}}
@media print{#to-top{display:none}}
</style>
</head>
<body>
<header>
    <a href="index.php"><img src="logo-forsage-modified.png" class="logo-img" alt="ERA ETP"></a>
    <nav class="nav-menu">
        <a href="index.php"      class="nav-link <?= $current_page === 'index.php'      ? 'active' : '' ?>"><?= $t[$lang]['main'] ?></a>
        <a href="reestr.php"     class="nav-link <?= $current_page === 'reestr.php'     ? 'active' : '' ?>"><?= $t[$lang]['auctions'] ?></a>
        <a href="torgi_list.php" class="nav-link <?= $current_page === 'torgi_list.php' ? 'active' : '' ?>"><?= $t[$lang]['comm'] ?></a>
        <a href="order.php"      class="nav-link <?= $current_page === 'order.php'      ? 'active' : '' ?>"><?= $t[$lang]['order'] ?></a>
        <a href="franchise.php"  class="nav-link <?= $current_page === 'franchise.php'  ? 'active' : '' ?>"><?= $t[$lang]['franchise'] ?></a>
    </nav>
    <div class="header-right">
        <div class="msc-box">
            <span class="dot"></span>
            <span id="msc_val">00:00:00</span>
            <span class="msc-label">&nbsp;<?= $t[$lang]['msc'] ?></span>
        </div>
        <div class="lang-switcher">
            <a href="<?= htmlspecialchars($base_url) ?>lang=ru" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">RU</a>
            <a href="<?= htmlspecialchars($base_url) ?>lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
        </div>
        <?php if ($is_auth): ?>
        <div class="header-auth-block">
            <div style="text-align:right;line-height:1.3;">
                <div style="font-weight:800;font-size:13px;"><?= htmlspecialchars($user_name) ?></div>
                <div id="header-balance" style="font-size:11px;color:#22c55e;">
                    <?= number_format((float)$user_bal, 2, '.', "\xc2\xa0") ?>&nbsp;₽
                </div>
            </div>
            <a href="profile.php"><i data-lucide="user" style="width:18px;height:18px;color:#0088cc;"></i></a>
            <a href="logout.php"><i data-lucide="log-out" style="width:18px;height:18px;color:#ef4444;"></i></a>
        </div>
        <?php else: ?>
        <button class="btn-login" onclick="openAuth('login')"><?= $t[$lang]['login'] ?></button>
        <?php endif; ?>
        <button class="burger-trigger" onclick="toggleMobileMenu()" aria-label="Меню">
            <i data-lucide="menu" style="width:24px;height:24px;color:#fff;"></i>
        </button>
    </div>
</header>

<div id="mobileMenu">
    <button class="mob-close" onclick="toggleMobileMenu()">
        <i data-lucide="x" style="width:32px;height:32px;color:#1e293b;"></i>
    </button>
    <a href="index.php"      class="mob-nav-link" onclick="toggleMobileMenu()"><?= $t[$lang]['main'] ?></a>
    <a href="reestr.php"     class="mob-nav-link" onclick="toggleMobileMenu()"><?= $t[$lang]['auctions'] ?></a>
    <a href="torgi_list.php" class="mob-nav-link" onclick="toggleMobileMenu()"><?= $t[$lang]['comm'] ?></a>
    <a href="order.php"      class="mob-nav-link" onclick="toggleMobileMenu()"><?= $t[$lang]['order'] ?></a>
    <a href="franchise.php"  class="mob-nav-link" onclick="toggleMobileMenu()"><?= $t[$lang]['franchise'] ?></a>
    <div class="mob-lang-block">
        <a href="<?= htmlspecialchars($base_url) ?>lang=ru" class="mob-lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">RU</a>
        <a href="<?= htmlspecialchars($base_url) ?>lang=en" class="mob-lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
    </div>
    <div class="mob-auth-block">
        <?php if ($is_auth): ?>
        <div class="mob-username"><?= htmlspecialchars($user_name) ?></div>
        <div id="mob-balance" class="mob-balance">
            <?= number_format((float)$user_bal, 2, '.', "\xc2\xa0") ?>&nbsp;₽
        </div>
        <div class="mob-links">
            <a href="profile.php" class="mob-link mob-link-lk"><?= $t[$lang]['profile'] ?></a>
            <a href="logout.php"  class="mob-link mob-link-out"><?= $t[$lang]['logout'] ?></a>
        </div>
        <?php else: ?>
        <button class="btn-login" style="padding:14px 48px;font-size:16px;margin-top:8px;"
            onclick="toggleMobileMenu(); setTimeout(function(){ openAuth('login'); }, 350);">
            <?= $t[$lang]['login'] ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php include 'auth_modal.php'; ?>

<script>
/*
    Сообщаем системе очереди, что настоящая openAuth уже объявлена
    в auth_modal.php (выше по коду). Передаём её через __authModalReady —
    это мгновенно заменяет заглушку и сбрасывает накопленную очередь кликов.
*/
if (typeof __authModalReady === 'function' && typeof openAuth === 'function') {
    __authModalReady(openAuth);
}

/* Часы МСК */
(function tickMSK() {
    var el = document.getElementById('msc_val');
    if (el) {
        var now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Moscow' }));
        el.textContent =
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0') + ':' +
            String(now.getSeconds()).padStart(2, '0');
    }
    setTimeout(tickMSK, 1000);
})();

function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}

/* Lucide грузится с defer — ждём готовности DOM и наличия глобала. */
function __initLucide() {
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    } else {
        setTimeout(__initLucide, 50);
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', __initLucide, { once: true });
} else {
    __initLucide();
}

/* Синхронизация баланса */
window.addEventListener('balance:updated', function (e) {
    var d = e.detail;
    var hb = document.getElementById('header-balance');
    if (hb) hb.textContent = d.formatted;
    var mb = document.getElementById('mob-balance');
    if (mb) mb.textContent = d.formatted;
});

/* Service Worker */
if ('serviceWorker' in navigator &&
    (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/service-worker.js').catch(function (err) {
            console.warn('SW registration failed:', err);
        });
    });
}
</script>

<?php @include __DIR__ . '/cookie_banner.php'; ?>
<?php @include __DIR__ . '/pwa_install.php'; ?>

<!-- Кнопка «Наверх» — общая для всех страниц, появляется после прокрутки. -->
<button id="to-top" type="button" aria-label="<?= $lang === 'en' ? 'Back to top' : 'Наверх' ?>" title="<?= $lang === 'en' ? 'Back to top' : 'Наверх' ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 19V5"/><path d="M5 12l7-7 7 7"/>
    </svg>
</button>
<script>
(function(){
    var btn = document.getElementById('to-top');
    if (!btn) return;
    var sc = document.scrollingElement || document.documentElement;
    function check(){
        var y = window.scrollY || window.pageYOffset || sc.scrollTop || 0;
        btn.classList.toggle('visible', y > 400);
    }
    window.addEventListener('scroll', check, { passive: true });
    document.addEventListener('scroll', check, true);
    btn.addEventListener('click', function(){
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        catch (e) { window.scrollTo(0, 0); }
    });
    check();
})();
</script>
</body>
</html>