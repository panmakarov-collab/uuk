<?php
// ================================================================
// get_account.php — тонкая обёртка над внутренним API index.php
// Используется страницей pay.php: GET get_account.php?account=<номер>
// Ответ идентичен ?action=get_account (см. index.php):
//   { "success": true, "account_number":..., "organization":..., "bank":...,
//     "balance":..., "account_type":..., "currency":"чякө|песета" }
// ================================================================

header('Content-Type: application/json; charset=utf-8');

$account = trim($_GET['account'] ?? '');
if ($account === '' || !preg_match('/^\d{1,20}$/', $account)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный номер счёта']);
    exit;
}

define('ACCOUNTS_DIR', __DIR__ . '/accounts/');
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}

// Формат файла счёта (единый для всей системы):
// 0: организация | 1: банк | 2: баланс | 3: тип счёта | 4: валюта
// Читаем БЕЗ FILE_SKIP_EMPTY_LINES, чтобы пустые строки не сдвигали индексы полей.
$filename = ACCOUNTS_DIR . $account . '.txt';
if (!file_exists($filename)) {
    echo json_encode(['success' => false, 'message' => 'Счёт не найден']);
    exit;
}
$data = file($filename, FILE_IGNORE_NEW_LINES);
if (count($data) < 3) {
    echo json_encode(['success' => false, 'message' => 'Данные счёта повреждены']);
    exit;
}

echo json_encode([
    'success'        => true,
    'account_number' => $account,
    'organization'   => $data[0] ?? '',
    'bank'           => $data[1] ?? '',
    'balance'        => floatval($data[2] ?? 0),
    'account_type'   => $data[3] ?? 'Сұкромны',
    'currency'       => normalize_currency($data[4] ?? CURRENCY_CHAKO),
]);
