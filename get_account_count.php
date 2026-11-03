<?php
// ================================================================
// get_account_count.php — список всех счетов (JSON)
// Единый формат файла счёта (как в index.php / api.php):
//   0: организация | 1: банк | 2: баланс | 3: тип счёта | 4: валюта
// Чтение БЕЗ FILE_SKIP_EMPTY_LINES — пустые строки не сдвигают индексы.
// ================================================================

define('ACCOUNTS_DIR', __DIR__ . '/accounts/');
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}

$accounts = [];
if (is_dir(ACCOUNTS_DIR)) {
    foreach (scandir(ACCOUNTS_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $data = file(ACCOUNTS_DIR . $file, FILE_IGNORE_NEW_LINES);
        if (count($data) < 3) continue;
        $accounts[] = [
            'account_number' => pathinfo($file, PATHINFO_FILENAME),
            'organization'   => $data[0] ?? '',
            'bank'           => $data[1] ?? '',
            'balance'        => floatval($data[2] ?? 0),
            'account_type'   => $data[3] ?? 'Сұкромны',
            'currency'       => normalize_currency($data[4] ?? CURRENCY_CHAKO),
        ];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'count' => count($accounts), 'data' => $accounts]);
