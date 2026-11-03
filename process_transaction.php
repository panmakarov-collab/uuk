<?php
// ================================================================
// БАНК: обработка транзакции
// Поддержка валют: чякө и песета (₧). 1 чякө = 10 ₧
// Формат файла счёта:
//   0: организация
//   1: банк
//   2: баланс
//   3: тип счёта
//   4: валюта (чякө | песета) — если нет, считается чякө
// ================================================================

define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('CHAKO_PER_PESETA', 10);

header('Content-Type: application/json; charset=utf-8');

function get_account_data($account_number) {
    $filename = "accounts/{$account_number}.txt";
    if (!file_exists($filename)) return false;
    $data = file($filename, FILE_IGNORE_NEW_LINES);
    if (count($data) < 3) return false;
    $currency = $data[4] ?? CURRENCY_CHAKO;
    if ($currency !== CURRENCY_PESETA) $currency = CURRENCY_CHAKO;
    return [
        'organization' => $data[0] ?? '',
        'bank'         => $data[1] ?? '',
        'balance'      => floatval($data[2] ?? 0),
        'account_type' => $data[3] ?? '',
        'currency'     => $currency,
    ];
}

function update_account_balance($account_number, $new_balance) {
    $filename = "accounts/{$account_number}.txt";
    if (!file_exists($filename)) return false;
    $data = file($filename, FILE_IGNORE_NEW_LINES);
    if (count($data) < 3) return false;
    $data[2] = number_format($new_balance, 2, '.', '');
    return file_put_contents($filename, implode("\n", $data) . "\n") !== false;
}

// Перевод суммы ИЗ валюты $from В валюту $to
function convert_amount($amount, $from, $to) {
    if ($from === $to) return $amount;
    if ($from === CURRENCY_CHAKO && $to === CURRENCY_PESETA) {
        return $amount * CHAKO_PER_PESETA;
    }
    if ($from === CURRENCY_PESETA && $to === CURRENCY_CHAKO) {
        return $amount / CHAKO_PER_PESETA;
    }
    return $amount;
}

// ===== Получаем данные =====
$sender_account   = trim($_POST['sender_account'] ?? '');
$receiver_account = trim($_POST['receiver_account'] ?? '');
$amount           = floatval($_POST['amount'] ?? 0);
$description      = trim($_POST['description'] ?? '');

if ($sender_account === '' || $receiver_account === '') {
    echo json_encode(['success' => false, 'message' => 'Не указаны счета']);
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

$sender   = get_account_data($sender_account);
$receiver = get_account_data($receiver_account);

if (!$sender) {
    echo json_encode(['success' => false, 'message' => 'Счёт отправителя не найден']);
    exit;
}
if (!$receiver) {
    echo json_encode(['success' => false, 'message' => 'Счёт получателя не найден']);
    exit;
}

// Минимальный перевод в песетах
if ($sender['currency'] === CURRENCY_PESETA && $amount < 10) {
    echo json_encode(['success' => false, 'message' => 'Минимальный перевод в песетах — 10 ₧']);
    exit;
}

if ($sender['balance'] < $amount) {
    echo json_encode([
        'success' => false,
        'message' => "Недостаточно средств. На счёте: {$sender['balance']} "
            . ($sender['currency'] === CURRENCY_PESETA ? '₧' : 'чякө')
            . ", требуется: $amount"
    ]);
    exit;
}

// Сколько получит получатель в СВОЕЙ валюте
$amount_to_receiver = round(convert_amount($amount, $sender['currency'], $receiver['currency']), 2);

$new_sender_balance   = round($sender['balance'] - $amount, 2);
$new_receiver_balance = round($receiver['balance'] + $amount_to_receiver, 2);

if (!update_account_balance($sender_account, $new_sender_balance)) {
    echo json_encode(['success' => false, 'message' => 'Ошибка списания со счёта отправителя']);
    exit;
}
if (!update_account_balance($receiver_account, $new_receiver_balance)) {
    // Откат
    update_account_balance($sender_account, $sender['balance']);
    echo json_encode(['success' => false, 'message' => 'Ошибка зачисления на счёт получателя']);
    exit;
}

$receiver_sym = $receiver['currency'] === CURRENCY_PESETA ? '₧' : 'чякө';
$sender_sym   = $sender['currency'] === CURRENCY_PESETA ? '₧' : 'чякө';

$msg = "Транзакция успешно выполнена: списано $amount $sender_sym, "
    . "зачислено $amount_to_receiver $receiver_sym";

if ($description !== '') $msg .= " ($description)";

echo json_encode([
    'success' => true,
    'message' => $msg,
    'amount_sent' => $amount,
    'amount_received' => $amount_to_receiver,
    'sender_currency' => $sender['currency'],
    'receiver_currency' => $receiver['currency'],
]);