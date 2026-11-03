<?php
// pay.php — страница приёма перевода (японский стиль, морская волна)
// Параметры URL: recipient (обязательный), amount, currency, description
$recipient   = trim($_GET['recipient'] ?? $_GET['account'] ?? '');
$amount      = trim($_GET['amount'] ?? '');
$currency    = trim($_GET['currency'] ?? 'чякө');
$description = trim($_GET['description'] ?? $_GET['desc'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Приём перевода</title>
<style>
  :root {
    --ink: #1c1c1c;
    --paper: #f7f3ea;
    --paper-2: #eef4f3;
    --vermilion: #c8352a;
    --vermilion-dark: #a5281f;
    --sea: #2a7f8f;
    --sea-dark: #1f6470;
    --sea-light: #e3f0f2;
    --sage: #6b7f6e;
    --line: #d8cfb8;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', 'Hiragino Sans', 'Noto Sans', 'Helvetica Neue', Arial, sans-serif;
    font-weight: 400;
    background: var(--paper);
    background-image:
      radial-gradient(circle at 20% 20%, rgba(42,127,143,0.05) 0%, transparent 40%),
      radial-gradient(circle at 80% 80%, rgba(200,53,42,0.04) 0%, transparent 40%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    color: var(--ink);
  }
  .card {
    position: relative;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 4px;
    width: 100%; max-width: 500px;
    padding: 40px 36px 34px;
    box-shadow: 0 2px 0 var(--line), 0 18px 50px rgba(28,28,28,0.12);
  }
  .card::before {
    content: '';
    position: absolute; left: 0; top: 36px; bottom: 36px;
    width: 3px; background: var(--sea);
  }
  .card::after {
    content: '';
    position: absolute; top: 22px; right: 22px;
    width: 26px; height: 26px; border-radius: 50%;
    border: 2px solid var(--vermilion);
    opacity: 0.55;
  }
  .brand {
    display: flex; align-items: center; gap: 18px;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--line);
  }
  .brand img {
    width: 80px; height: 80px; object-fit: contain;
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.1));
  }
  .brand .brand-title {
    font-size: 1.35rem; font-weight: 600; letter-spacing: 0.3px;
  }
  .brand .brand-sub {
    font-size: 0.72rem; color: var(--sea); letter-spacing: 1.5px;
    text-transform: uppercase; margin-top: 3px;
  }
  .field { margin-bottom: 18px; }
  label {
    display: block; font-size: 0.72rem; color: var(--sage);
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1.2px;
    font-weight: 500;
  }
  input, select {
    width: 100%; padding: 12px 14px; border-radius: 3px;
    border: 1px solid var(--line); background: #fffdf8;
    color: var(--ink); font-size: 1rem; font-family: inherit;
    outline: none; transition: border-color 0.2s, box-shadow 0.2s;
  }
  input:focus, select:focus {
    border-color: var(--sea);
    box-shadow: 0 0 0 3px rgba(42,127,143,0.12);
  }
  .recipient-box {
    background: var(--sea-light);
    border: 1px solid rgba(42,127,143,0.35);
    border-left: 4px solid var(--sea);
    border-radius: 3px;
    padding: 16px 18px;
    margin-bottom: 22px;
    position: relative;
  }
  .recipient-box .owner { font-weight: 600; margin-bottom: 4px; font-size: 1rem; color: var(--sea-dark); }
  .recipient-box .num {
    font-family: 'SF Mono', 'Consolas', monospace;
    font-size: 0.88rem; color: #3a5b62;
  }
  .recipient-box .meta { font-size: 0.78rem; color: #4d7178; margin-top: 6px; }
  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 2px;
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.5px;
    text-transform: uppercase;
  }
  .badge.ok { background: rgba(42,127,143,0.15); color: var(--sea-dark); }
  .badge.err { background: rgba(200,53,42,0.12); color: var(--vermilion-dark); }
  .row { display: flex; gap: 12px; }
  .row .field { flex: 1; }
  button {
    width: 100%; padding: 15px; border: none; border-radius: 3px;
    background: var(--sea); color: #fff;
    font-size: 0.95rem; font-weight: 600; letter-spacing: 1px;
    text-transform: uppercase; cursor: pointer; margin-top: 10px;
    transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
    font-family: inherit;
    box-shadow: 0 2px 0 var(--sea-dark);
  }
  button:hover { background: var(--sea-dark); }
  button:active { transform: translateY(2px); box-shadow: none; }
  button:disabled { opacity: 0.5; cursor: not-allowed; }
  .result {
    margin-top: 22px; padding: 16px; border-radius: 3px;
    font-size: 0.9rem; display: none;
  }
  .result.ok {
    display: block; background: var(--sea-light);
    border-left: 3px solid var(--sea);
  }
  .result.err {
    display: block; background: rgba(200,53,42,0.08);
    border-left: 3px solid var(--vermilion);
  }
  .result .title { font-weight: 600; margin-bottom: 8px; font-size: 0.95rem; }
  .result .detail {
    font-family: 'SF Mono', 'Consolas', monospace;
    font-size: 0.82rem; color: #444; line-height: 1.7;
  }
  .spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.6s linear infinite;
    vertical-align: middle; margin-right: 6px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <img src="images/uuk-pay.png" alt="Логотип" onerror="this.style.display='none'">
    <div>
      <div class="brand-title">Приём перевода</div>
      <div class="brand-sub">Secure Transfer</div>
    </div>
  </div>

  <div class="recipient-box" id="recipientBox">
    <div class="owner" id="recipientOwner">Загрузка…</div>
    <div class="num">Счёт: <span id="recipientNum">—</span></div>
    <div class="meta" id="recipientMeta"></div>
  </div>

  <form id="transferForm">
    <div class="field">
      <label>Счёт списания</label>
      <input type="text" id="sourceAccount" placeholder="Номер счёта, с которого списать" required>
    </div>

    <div class="row">
      <div class="field">
        <label>Сумма</label>
        <input type="number" id="amount" step="0.01" min="0.01" placeholder="0.00" required>
      </div>
      <div class="field">
        <label>Валюта</label>
        <select id="currency">
          <option value="чякө">чякө</option>
          <option value="песета">песета (₧)</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label>Описание</label>
      <input type="text" id="description" placeholder="Назначение платежа">
    </div>

    <button type="submit" id="submitBtn">Перевести</button>
  </form>

  <div class="result" id="result">
    <div class="title" id="resultTitle"></div>
    <div class="detail" id="resultDetail"></div>
  </div>
</div>

<script>
const API_URL = 'process_transaction.php';

const recipient         = <?= json_encode($recipient, JSON_UNESCAPED_UNICODE) ?>;
const presetAmount      = <?= json_encode($amount, JSON_UNESCAPED_UNICODE) ?>;
const presetCurrency    = <?= json_encode($currency, JSON_UNESCAPED_UNICODE) ?>;
const presetDescription = <?= json_encode($description, JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('recipientNum').textContent = recipient || 'не указан';

// ===== ПРОВЕРКА ПОЛУЧАТЕЛЯ =====
async function verifyRecipient() {
  const ownerEl = document.getElementById('recipientOwner');
  const metaEl = document.getElementById('recipientMeta');

  if (!recipient) {
    ownerEl.innerHTML = 'Получатель не указан <span class="badge err">ошибка</span>';
    document.getElementById('submitBtn').disabled = true;
    return;
  }

  try {
    const res = await fetch(`get_account.php?account=${encodeURIComponent(recipient)}`);
    const d = await res.json();
    if (d && d.success && d.currency) {
      ownerEl.innerHTML = `${d.organization || 'Счёт найден'} <span class="badge ok">найден</span>`;
      metaEl.textContent = `Валюта счёта: ${d.currency}` + (d.balance !== undefined ? ` · Баланс: ${d.balance}` : '');
      if (!presetCurrency && d.currency) {
        document.getElementById('currency').value = d.currency;
      }
    } else {
      ownerEl.innerHTML = `Счёт ${recipient} <span class="badge ok">принят</span>`;
      metaEl.textContent = 'Проверка владельца недоступна';
    }
  } catch (e) {
    ownerEl.innerHTML = `Счёт ${recipient} <span class="badge ok">принят</span>`;
    metaEl.textContent = 'Проверка владельца недоступна';
  }
}

// ===== ПРЕДЗАПОЛНЕНИЕ =====
if (presetAmount) document.getElementById('amount').value = presetAmount;
if (presetCurrency) document.getElementById('currency').value = presetCurrency;
if (presetDescription) document.getElementById('description').value = presetDescription;

// ===== ГЕНЕРАЦИЯ И СКАЧИВАНИЕ ЧЕКА =====
function downloadReceipt(data) {
  const symS = data.sender_currency === 'песета' ? '₧' : 'чякө';
  const symR = data.receiver_currency === 'песета' ? '₧' : 'чякө';
  const line = '─'.repeat(44);
  const dbl  = '═'.repeat(44);

  const receipt = [
    dbl,
    '            ЧЕК ОБ ОПЛАТЕ',
    dbl,
    '',
    `Дата:            ${new Date().toLocaleString('ru-RU')}`,
    `ID транзакции:   ${data.external_id || 'web_' + Date.now()}`,
    '',
    line,
    'ПОЛУЧАТЕЛЬ',
    line,
    `Счёт:            ${recipient}`,
    `Зачислено:       ${data.amount_received} ${symR}`,
    '',
    line,
    'ОТПРАВИТЕЛЬ',
    line,
    `Счёт:            ${data.sender || document.getElementById('sourceAccount').value}`,
    `Списано:         ${data.amount_sent} ${symS}`,
    '',
    line,
    'НАЗНАЧЕНИЕ',
    line,
    `${document.getElementById('description').value.trim() || 'Перевод через сайт'}`,
    '',
    dbl,
    '  Перевод выполнен успешно. Спасибо!',
    dbl,
    ''
  ].join('\n');

  const blob = new Blob([receipt], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `receipt_${Date.now()}.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// ===== ОТПРАВКА =====
document.getElementById('transferForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  const result = document.getElementById('result');
  const title = document.getElementById('resultTitle');
  const detail = document.getElementById('resultDetail');

  const source = document.getElementById('sourceAccount').value.trim();
  const amount = parseFloat(document.getElementById('amount').value);
  const description = document.getElementById('description').value.trim() || 'Перевод через сайт';

  if (!source) { alert('Укажите счёт списания'); return; }
  if (!amount || amount <= 0) { alert('Укажите корректную сумму'); return; }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>Отправка…';
  result.className = 'result';

  const body = new URLSearchParams();
  body.append('sender_account', source);
  body.append('receiver_account', recipient);
  body.append('amount', amount);
  body.append('description', description);

  try {
    const res = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    const json = await res.json();

    if (json.success) {
      json.sender = source;
      json.external_id = 'web_' + Date.now();

      result.className = 'result ok';
      title.textContent = '✅ Перевод выполнен';
      const symS = json.sender_currency === 'песета' ? '₧' : 'чякө';
      const symR = json.receiver_currency === 'песета' ? '₧' : 'чякө';
      detail.innerHTML =
        `Получатель: ${recipient}<br>` +
        `Списано: ${json.amount_sent} ${symS}<br>` +
        `Зачислено: ${json.amount_received} ${symR}<br>` +
        `<span style="color:#2a7f8f">${json.message}</span>`;

      downloadReceipt(json);

      // Возврат через 8 секунд
      let seconds = 8;
      const countdown = document.createElement('div');
      countdown.className = 'detail';
      countdown.style.marginTop = '10px';
      countdown.style.color = '#2a7f8f';
      result.appendChild(countdown);

      const goBack = () => {
        if (history.length > 1) history.back();
        else if (document.referrer) window.location.href = document.referrer;
        else window.location.href = 'index.php';
      };

      countdown.textContent = `Возврат через ${seconds} с…`;
      const tick = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
          clearInterval(tick);
          goBack();
          return;
        }
        countdown.textContent = `Возврат через ${seconds} с…`;
      }, 1000);
    } else {
      result.className = 'result err';
      title.textContent = '❌ Ошибка';
      detail.textContent = json.message || json.error || 'Неизвестная ошибка';
    }
  } catch (err) {
    result.className = 'result err';
    title.textContent = '❌ Ошибка сети';
    detail.textContent = err.message;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Перевести';
  }
});

verifyRecipient();
</script>
</body>
</html>