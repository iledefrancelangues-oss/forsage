# Test plan — PR #1 (forsage)

Verified on the live server `https://forsage.ct.ws/` (FTP-deployed).
Three user-facing fixes:

1. **Scroll-animation плиток** — 3D-эффект играется один раз, при hover только наклон + подсветка (без повторного полёта). Новое: резкие 90° развороты заменены на мягкое всплывание (auc-card).
2. **Оплата при регистрации в статусе «Ответственный»** — QR + квитанция доступны сразу при выборе статуса.
3. **Telegram-вход** — был сломан (placeholders); теперь виджет бота `era_etp_bot` должен рендериться на `/telegram_auth.php`.

Code references:
- Scroll-anim CSS/JS: `htdocs/index.php` lines ~792–836 (IO + .settled), ~894–1028 (CSS hover).
- Modal payment: `htdocs/auth_modal.php` lines ~410–490 (payment block), ~611–672 (`authSelectStatus` / `authSelectPayMethod` / `authOpenReceipt`).
- Receipt page: `htdocs/upgrade_receipt.php` lines ~155–195 (Close button + style + JS).

---

## Test 1 — Tile scroll-animation 3D-once + hover

**Steps**
1. Open `http://127.0.0.1:8080/` in Chrome (window maximized).
2. Scroll down past the hero until the «Роли» section (3 plitka cards: «Уважаемый», «Ответственный», «Организатор») is fully in view.
3. Wait ~2s for the 3D entrance animation to finish.
4. Hover over the middle tile («Ответственный»).
5. Move cursor away. Hover again on a different tile. Scroll up and back down.

**Pass/fail**
- During step 3 the 3D flip-in animation plays exactly once (rotateX/rotateY entrance, opacity 0→1).
- After step 3, all three tiles are fully opaque, no transform applied (devtools: `transform: none`, `opacity: 1`, class includes `settled`).
- On hover (step 4): tile gets a slight 3D tilt (small rotateX/Y ≤ 7°) AND a glowing blue box-shadow. **NOT** the entrance flip-in animation.
- During hover the tile remains visible the entire time (no flicker / no opacity drop). **This is the regression that the fix targets** — broken implementation would show a brief disappearance.
- Re-scrolling does not re-trigger the 3D entrance animation (IO unobserves on first hit).

**Adversarial check**: a broken implementation (the original code) would either replay the flip-in on hover, or briefly drop opacity to 0 (because `animation:none` removed fill-mode). The fixed implementation should look stable.

---

## Test 2 — Live payment block on selecting «Ответственный»

**Steps**
1. From the homepage, click "ВХОД" (top-right) to open the auth modal.
2. Switch to the "РЕГИСТРАЦИЯ" tab.
3. Verify default state: «Уважаемый» is selected, no payment block visible.
4. Click the «Ответственный» status card.
5. Observe what appears immediately, **before** filling any form fields or clicking «Зарегистрироваться».
6. Click the «Квитанция» tab inside the payment block.
7. Click the «Открыть квитанцию» button.

**Pass/fail**
- After step 4: the payment block becomes visible with header «Оплата статуса «Ответственный» 8 000 ₽».
- Two tab buttons «QR-код» and «Квитанция» are visible. «QR-код» is active.
- **A QR-code image is rendered immediately** (white box with black QR pattern, ~240×240 px). This is the primary fix — before, QR appeared only after submitting the registration form.
- Mini-details block shows: «Получатель: ООО «Форсаж» · ИНН 7728282160», «Счёт: 40702810101500033019, ООО Банк Точка, БИК 044525104», «Сумма: 8 000 ₽ (в т.ч. НДС 22%)».
- After step 6: the QR panel is hidden, a hint text + button «Открыть квитанцию» appears.
- After step 7: a new browser window/tab opens at `upgrade_receipt.php?status=Ответственный&sum=8000&close=1`.
- Selecting «Уважаемый» or «Организатор» again hides the payment block.

**Adversarial check**: a broken implementation would either (a) not show any QR until form submission, (b) show QR but without the static recipient details, or (c) the «Открыть квитанцию» button does nothing.

---

## Test 3 — Printable receipt window (Print + Close)

Follows directly from Test 2 step 7 (new window already open).

**Steps**
1. In the receipt window, verify the layout (heading «Квитанция на оплату», recipient details, QR code on the right, sum 8 000 ₽, purpose).
2. Click the «🖨️ Печать квитанции» button.
3. Cancel the print dialog.
4. Click the «✕ Закрыть» button.

**Pass/fail**
- A QR-code image (220×220) is rendered next to bank details.
- Both buttons «🖨️ Печать квитанции» and «✕ Закрыть» are present and styled differently (blue vs white outline).
- Clicking «Печать квитанции» triggers the browser's native print dialog.
- After step 4: the popup window closes (or, if blocked, navigates back / to '/'). **Window must NOT remain open with no visible action** — that would mean `receiptClose()` is broken.
- Sum on receipt matches "8 000,00 ₽". VAT shown as «1 442,62 ₽» (8000 × 22/122).
- Status field shows «Ответственный».

**Adversarial check**: a broken implementation would either lack the close button entirely (original behaviour) or `receiptClose()` would fail silently. The fix must produce a visibly closed window.

---

---

## Test 4 — Telegram-вход открывается

**Steps**
1. Из главной открыть модалку SIGN IN.
2. Нажать «Войти через Telegram».
3. На `/telegram_auth.php` должен подгрузиться официальный Telegram-виджет (`telegram-widget.js`).

**Pass/fail**
- Страница открывается без сообщения «Не настроено». Это доказывает, что `tg_config.php` подхватился.
- Виджет Telegram пытается загрузиться. Если видим кнопку «Log in via Telegram» — всё работает. Если виджет не появляется — это значит у бота не прописан домен в @BotFather (`/setdomain forsage.ct.ws`).

**Adversarial check**: until this fix, `/telegram_auth.php` would render a widget with `data-telegram-login="YOUR_BOT_USERNAME"` which Telegram silently ignores.

---

## Out of scope (not retested)

- DB-dependent flows: actual registration submission (`register_handler.php`), `upgrade_qr.php?id=N`. The PR does not change their behavior — only the modal preview and receipt UI were touched.
- Other pages (lots, profile, admin) — unrelated to this PR.
