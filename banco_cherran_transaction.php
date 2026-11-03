<?php
// ================================================================
// Banco Cherrán — внешний API переводов (POST)
// Единый формат файла счёта: 0:организация | 1:банк | 2:баланс | 3:тип | 4:валюта
// Чтение БЕЗ FILE_SKIP_EMPTY_LINES; запись в журнал external_transfers.txt:
// дата|ID|получатель|отправитель|сумма|описание|валюта
// Валюты: чякө / песета (₧), 1 чякө = 10 ₧. Перевод — внутривалютный.
// ================================================================

define('ACCOUNTS_DIR', __DIR__ . '/accounts/');
define('TRANSFERS_FILE', __DIR__ . '/external_transfers.txt');
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('CHAKO_PER_PESETA', 10);
define('API_KEY', 'banco_cherran_secret_2025'); // Замените на свой секретный ключ

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

$data = [];
parse_str(file_get_contents('php://input'), $data);
if (!isset($data['api_key']) || $data['api_key'] !== API_KEY) {
    echo json_encode(['success' => false, 'message' => 'Неверный API-ключ']);
    exit;
}

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}
function get_account_data($num) {
    $f = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($f)) return false;
    $d = file($f, FILE_IGNORE_NEW_LINES); // без SKIP_EMPTY_LINES — индексы полей стабильны
    if (count($d) < 3) return false;
    return [
        'organization' => $d[0] ?? '',
        'bank'         => $d[1] ?? '',
        'balance'      => floatval($d[2] ?? 0),
        'account_type' => $d[3] ?? 'Сұкромны',
        'currency'     => normalize_currency($d[4] ?? CURRENCY_CHAKO),
    ];
}
function update_account_balance($num, $new_balance) {
    $f = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($f)) return false;
    $d = file($f, FILE_IGNORE_NEW_LINES);
    if (count($d) < 3) return false;
    $new_balance = round((float)$new_balance, 2);
    if ($new_balance < 0) $new_balance = 0.0; // балансы не могут быть отрицательными
    $d[2] = number_format($new_balance, 2, '.', '');
    return file_put_contents($f, implode("\n", $d) . "\n") !== false;
}
function log_transaction($external_id, $recipient, $sender, $amount, $description, $currency) {
    $line = date('Y-m-d H:i:s') . '|' . $external_id . '|' . $recipient . '|' . $sender . '|'
          . rtrim(rtrim(number_format((float)$amount, 2, '.', ''), '0'), '.') . '|'
          . str_replace(["\n", "\r", '|'], ' ', $description) . '|' . normalize_currency($currency) . "\n";
    file_put_contents(TRANSFERS_FILE, $line, FILE_APPEND | LOCK_EX);
}

$sender_account   = isset($data['sender_account']) ? trim($data['sender_account']) : '';
$receiver_account = isset($data['receiver_account']) ? trim($data['receiver_account']) : '';
$amount           = isset($data['amount']) ? floatval($data['amount']) : 0;
$description      = isset($data['description']) ? trim($data['description']) : 'Banco Cherrán transfer';

if (!preg_match('/^[0-9]+$/', $sender_account) || !preg_match('/^[0-9]+$/', $receiver_account)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный номер счета']);
    exit;
}
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Сумма должна быть больше 0']);
    exit;
}
if ($sender_account === $receiver_account) {
    echo json_encode(['success' => false, 'message' => 'Нельзя переводить на тот же счёт']);
    exit;
}

$sender = get_account_data($sender_account);
if (!$sender) {
    echo json_encode(['success' => false, 'message' => 'Счет отправителя не найден']);
    exit;
}
$receiver = get_account_data($receiver_account);
if (!$receiver) {
    echo json_encode(['success' => false, 'message' => 'Счет получателя не найден']);
    exit;
}
if ($sender['balance'] < $amount) {
    echo json_encode(['success' => false, 'message' => 'Недостаточно средств на счете отправителя']);
    exit;
}

// Конвертация валют при переводе между разными валютами счетов
$amount_to_receiver = $amount;
if ($sender['currency'] !== $receiver['currency']) {
    if ($sender['currency'] === CURRENCY_CHAKO) $amount_to_receiver = $amount * CHAKO_PER_PESETA;
    else $amount_to_receiver = $amount / CHAKO_PER_PESETA;
    $amount_to_receiver = round($amount_to_receiver, 2);
}

$new_sender_balance   = round($sender['balance'] - $amount, 2);
$new_receiver_balance = round($receiver['balance'] + $amount_to_receiver, 2);

if (update_account_balance($sender_account, $new_sender_balance) &&
    update_account_balance($receiver_account, $new_receiver_balance)) {
    $tx_id = 'cherran_' . round(microtime(true) * 1000);
    log_transaction($tx_id, $receiver_account, $sender_account, $amount, $description, $sender['currency']);
    echo json_encode([
        'success'           => true,
        'message'           => 'Транзакция успешно выполнена',
        'transaction_id'    => $tx_id,
        'amount_sent'       => $amount,
        'amount_received'   => $amount_to_receiver,
        'sender_currency'   => $sender['currency'],
        'receiver_currency' => $receiver['currency'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка обновления баланса']);
}
