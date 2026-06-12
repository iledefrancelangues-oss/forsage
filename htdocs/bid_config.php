<?php
/**
 * Централизованный конфиг цен скандинавского аукциона.
 * Дефолтные значения определены ниже, а при наличии записей в таблице
 * `system_settings` (управляется из users_control.php админом) — берутся оттуда.
 */

if (!defined('BID_DEFAULTS')) define('BID_DEFAULTS', [
    // ── Уважаемый (бесплатная регистрация) ───────────
    'respected' => [
        'reg_cost'       => 0,
        'cash'           => 2490,
        'balance'        => 1990,
        'pack_size'      => 20,
        'pack_discount'  => 0.25,
    ],
    // ── Ответственный (регистрация 8 000 ₽) ──────────
    'responsible' => [
        'reg_cost'       => 8000,
        'cash'           => 1890,
        'balance'        => 1490,
        'pack_size'      => 20,
        'pack_discount'  => 0.40,
    ],
]);

if (!defined('ORGANIZER_BONUS_PCT_DEFAULT')) define('ORGANIZER_BONUS_PCT_DEFAULT', 0.15);

/**
 * Загружает все настройки из system_settings один раз и кэширует.
 * Возвращает массив той же формы, что BID_DEFAULTS, плюс ключ 'organizer_bonus_pct' (доля).
 */
function _bidLoadSettings(): array {
    static $cached = null;
    if ($cached !== null) return $cached;

    $defaults = BID_DEFAULTS;
    $bonus    = ORGANIZER_BONUS_PCT_DEFAULT;

    try {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $rows = $GLOBALS['pdo']->query("SELECT skey, sval FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $map = [
                'bid_respected_reg_cost'        => ['respected',   'reg_cost',     'int'],
                'bid_respected_cash'            => ['respected',   'cash',         'int'],
                'bid_respected_balance'         => ['respected',   'balance',      'int'],
                'bid_respected_pack_size'       => ['respected',   'pack_size',    'int'],
                'bid_respected_pack_discount'   => ['respected',   'pack_discount','pct'],
                'bid_responsible_reg_cost'      => ['responsible', 'reg_cost',     'int'],
                'bid_responsible_cash'          => ['responsible', 'cash',         'int'],
                'bid_responsible_balance'       => ['responsible', 'balance',      'int'],
                'bid_responsible_pack_size'     => ['responsible', 'pack_size',    'int'],
                'bid_responsible_pack_discount' => ['responsible', 'pack_discount','pct'],
            ];
            foreach ($map as $key => [$grp, $field, $kind]) {
                if (!isset($rows[$key]) || !is_numeric($rows[$key])) continue;
                $v = (float)$rows[$key];
                if ($kind === 'pct') {
                    $defaults[$grp][$field] = max(0, min(0.9, $v / 100.0));
                } else {
                    $defaults[$grp][$field] = (int)max(0, $v);
                }
            }
            if (isset($rows['organizer_bonus_pct']) && is_numeric($rows['organizer_bonus_pct'])) {
                $bonus = max(0, min(50, (float)$rows['organizer_bonus_pct'])) / 100.0;
            }
        }
    } catch (Throwable $e) { /* fallback на дефолты */ }

    $defaults['organizer_bonus_pct'] = $bonus;
    return $cached = $defaults;
}

if (!defined('BID_PRICES')) {
    $__bid_loaded = _bidLoadSettings();
    define('BID_PRICES', [
        'respected'   => $__bid_loaded['respected'],
        'responsible' => $__bid_loaded['responsible'],
    ]);
}

function getOrganizerBonusPct(): float {
    $s = _bidLoadSettings();
    return $s['organizer_bonus_pct'];
}

if (!defined('ORGANIZER_BONUS_PCT')) {
    define('ORGANIZER_BONUS_PCT', getOrganizerBonusPct());
}

/**
 * Возвращает стоимость одной ставки для пользователя.
 *
 * @param string $user_type      'respected' | 'responsible'
 * @param string $payment_method 'cash' | 'balance' | 'pack'
 * @return int
 */
function getBidCost(string $user_type, string $payment_method): int {
    $cfg = BID_PRICES[$user_type] ?? BID_PRICES['respected'];
    if ($payment_method === 'pack') {
        return (int)round($cfg['cash'] * (1 - $cfg['pack_discount']));
    }
    return (int)($cfg[$payment_method] ?? $cfg['cash']);
}

/**
 * Возвращает стоимость пакета.
 *
 * @return array ['per_bid' => int, 'total' => int, 'size' => int]
 */
function getPackPrice(string $user_type): array {
    $cfg     = BID_PRICES[$user_type] ?? BID_PRICES['respected'];
    $per_bid = (int)round($cfg['cash'] * (1 - $cfg['pack_discount']));
    return [
        'per_bid' => $per_bid,
        'total'   => $per_bid * (int)$cfg['pack_size'],
        'size'    => (int)$cfg['pack_size'],
    ];
}

/**
 * Рассчитывает бонус организатора (% от суммарной выручки за ставки).
 */
function getOrganizerBonus(int $total_bids, int $bid_cost): int {
    return (int)round($total_bids * $bid_cost * ORGANIZER_BONUS_PCT);
}
