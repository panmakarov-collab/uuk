<?php
header('Content-Type: application/json');

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

// Проверка API-ключа
$api_key = 'banco_cherran_secret_2025'; // Замените на свой секретный ключ
$data = [];
parse_str(file_get_contents('php://input'), $data);
if (!isset($data['api_key']) || $data['api_key'] !== $api_key) {
    echo json_encode(['success' => false, 'message' => 'Неверный API-ключ']);
    exit;
}

// Функция для получения баланса
function get_account_balance($account_number) {
    $filename = "accounts/{$account_number}.txt";
    if (file_exists($filename)) {
        $data = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return isset($data[2]) ? floatval($data[2]) : false; // 3-я строка (индекс 2)
    }
    return false;
}

// Функция для обновления баланса
function update_account_balance($account_number, $new_balance) {
    $filename = "accounts/{$account_number}.txt";
    if (file_exists($filename)) {
        $data = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data[2] = number_format($new_balance, 2, '.', ''); // Форматируем с 2 знаками
        file_put_contents($filename, implode("\n", $data) . "\n");
        return true;
    }
    return false;
}

// Получение данных
$sender_account = isset($data['sender_account']) ? trim($data['sender_account']) : '';
$receiver_account = isset($data['receiver_account']) ? trim($data['receiver_account']) : '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

// Валидация
if (!preg_match('/^[0-9]+$/', $sender_account) || !preg_match('/^[0-9]+$/', $receiver_account)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный номер счета']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Сумма должна быть больше 0']);
    exit;
}

// Проверка баланса отправителя
$sender_balance = get_account_balance($sender_account);
if ($sender_balance === false) {
    echo json_encode(['success' => false, 'message' => 'Счет отправителя не найден']);
    exit;
}

if ($sender_balance < $amount) {
    echo json_encode(['success' => false, 'message' => 'Недостаточно средств на счете отправителя']);
    exit;
}

// Проверка счета получателя
$receiver_balance = get_account_balance($receiver_account);
if ($receiver_balance === false) {
    echo json_encode(['success' => false, 'message' => 'Счет получателя не найден']);
    exit;
}

// Обновление балансов
$new_sender_balance = $sender_balance - $amount;
$new_receiver_balance = $receiver_balance + $amount;

if (update_account_balance($sender_account, $new_sender_balance) && update_account_balance($receiver_account, $new_receiver_balance)) {
    echo json_encode(['success' => true, 'message' => 'Транзакция успешно выполнена']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка обновления баланса']);
}
?>