<?php
// ================================================================
// API ДЛЯ ПРИЁМА ПЕРЕВОДОВ С ДРУГИХ САЙТОВ
// Поддержка валют: чякө и песета (₧), 1 чякө = 10 ₧
// ================================================================

define('ACCOUNTS_DIR', 'accounts/');
define('API_KEY', 'my_secret_key_2024');
define('API_LOG_FILE', 'api_log.txt');

define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('CHAKO_PER_PESETA', 10);

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}
function convert_amount($amount, $from, $to) {
    if ($from === $to) return $amount;
    if ($from === CURRENCY_CHAKO && $to === CURRENCY_PESETA) return $amount * CHAKO_PER_PESETA;
    if ($from === CURRENCY_PESETA && $to === CURRENCY_CHAKO) return $amount / CHAKO_PER_PESETA;
    return $amount;
}

function get_account_data($num) {
    $f = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($f)) return false;
    $data = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($data) < 3) return false;
    return [
        'organization' => $data[0] ?? '',
        'bank' => $data[1] ?? '',
        'balance' => floatval($data[2] ?? 0),
        'account_type' => $data[3] ?? '',
        'currency' => normalize_currency($data[4] ?? CURRENCY_CHAKO)
    ];
}
function update_balance($num, $new_balance) {
    $f = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($f)) return false;
    $data = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($data) < 3) return false;
    $data[2] = number_format($new_balance, 2, '.', '');
    return file_put_contents($f, implode("\n", $data) . "\n") !== false;
}
function generate_account_number() { return mt_rand(100000000000, 999999999999); }

function api_log($msg, $data = null) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $log .= ' | DATA: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents(API_LOG_FILE, $log . "\n", FILE_APPEND);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

if ($method === 'OPTIONS') { http_response_code(200); exit; }

$input = null;
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
}
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? ($input['api_key'] ?? ($_GET['api_key'] ?? ''));
if ($api_key !== API_KEY) {
    echo json_encode(['success' => false, 'error' => 'Invalid API key', 'code' => 401]);
    exit;
}

// ===== 1. TRANSFER =====
if ($action === 'transfer' && $method === 'POST') {
    $recipient = trim($input['recipient'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $currency = normalize_currency($input['currency'] ?? CURRENCY_CHAKO);
    $sender = trim($input['sender'] ?? 'external_system');
    $description = trim($input['description'] ?? 'External transfer');
    $external_id = trim($input['external_id'] ?? uniqid('ext_'));

    if ($recipient === '') { echo json_encode(['success' => false, 'error' => 'Recipient required', 'code' => 400]); exit; }
    if ($amount <= 0) { echo json_encode(['success' => false, 'error' => 'Amount must be > 0', 'code' => 400]); exit; }

    $acc = get_account_data($recipient);
    if (!$acc) { echo json_encode(['success' => false, 'error' => 'Recipient not found', 'code' => 404]); exit; }

    $amount_to_receiver = convert_amount($amount, $currency, $acc['currency']);
    $amount_to_receiver = round($amount_to_receiver, 2);
    $new_balance = round($acc['balance'] + $amount_to_receiver, 2);

    if (update_balance($recipient, $new_balance)) {
        $history_file = 'external_transfers.txt';
        file_put_contents($history_file,
            date('Y-m-d H:i:s') . "|$external_id|$recipient|$sender|$amount|$description|$currency\n",
            FILE_APPEND);
        api_log('TRANSFER_SUCCESS', [
            'recipient' => $recipient, 'amount' => $amount, 'currency' => $currency
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Transfer completed',
            'data' => [
                'recipient' => $recipient,
                'amount_sent' => $amount,
                'amount_received' => $amount_to_receiver,
                'currency_sent' => $currency,
                'currency_received' => $acc['currency'],
                'new_balance' => $new_balance,
                'external_id' => $external_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update balance', 'code' => 500]);
    }
    exit;
}

// ===== 2. BALANCE =====
if ($action === 'balance' && $method === 'GET') {
    $account = trim($_GET['account'] ?? '');
    if ($account === '') { echo json_encode(['success' => false, 'error' => 'Account required']); exit; }
    $acc = get_account_data($account);
    if (!$acc) { echo json_encode(['success' => false, 'error' => 'Account not found', 'code' => 404]); exit; }
    echo json_encode(['success' => true, 'data' => [
        'account' => $account,
        'balance' => $acc['balance'],
        'currency' => $acc['currency']
    ]]);
    exit;
}

// ===== 3. VERIFY =====
if ($action === 'verify' && $method === 'GET') {
    $account = trim($_GET['account'] ?? '');
    $acc = get_account_data($account);
    echo json_encode(['success' => true, 'data' => [
        'exists' => $acc !== false,
        'account' => $account,
        'owner' => $acc['organization'] ?? null,
        'balance' => $acc['balance'] ?? null,
        'currency' => $acc['currency'] ?? null
    ]]);
    exit;
}

// ===== 4. HISTORY =====
if ($action === 'history' && $method === 'GET') {
    $account = trim($_GET['account'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);
    if (!file_exists('external_transfers.txt')) {
        echo json_encode(['success' => true, 'data' => [], 'count' => 0]); exit;
    }
    $lines = file('external_transfers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $history = [];
    foreach (array_reverse($lines) as $line) {
        $p = explode('|', $line);
        if (count($p) >= 6 && $p[2] === $account) {
            $history[] = [
                'date' => $p[0], 'external_id' => $p[1], 'recipient' => $p[2],
                'sender' => $p[3], 'amount' => floatval($p[4]),
                'description' => $p[5] ?? '', 'currency' => $p[6] ?? CURRENCY_CHAKO
            ];
            if (count($history) >= $limit) break;
        }
    }
    echo json_encode(['success' => true, 'data' => $history, 'count' => count($history)]);
    exit;
}

// ===== 5. CREATE ACCOUNT =====
if ($action === 'create_account' && $method === 'POST') {
    $owner_name = trim($input['owner_name'] ?? '');
    $bank_name = trim($input['bank_name'] ?? 'External Bank');
    $initial_balance = floatval($input['initial_balance'] ?? 0);
    $account_type = trim($input['account_type'] ?? 'Сұкромны');
    $currency = normalize_currency($input['currency'] ?? CURRENCY_CHAKO);
    if ($owner_name === '') { echo json_encode(['success' => false, 'error' => 'Owner required']); exit; }
    $account_number = generate_account_number();
    $account_data = "$owner_name\n$bank_name\n$initial_balance\n$account_type\n$currency";
    if (!is_dir(ACCOUNTS_DIR)) mkdir(ACCOUNTS_DIR, 0777, true);
    if (file_put_contents(ACCOUNTS_DIR . $account_number . '.txt', $account_data)) {
        api_log('CREATE_ACCOUNT_SUCCESS', ['account' => $account_number, 'owner' => $owner_name, 'currency' => $currency]);
        echo json_encode(['success' => true, 'message' => 'Account created', 'data' => [
            'account_number' => $account_number,
            'owner_name' => $owner_name,
            'bank' => $bank_name,
            'balance' => $initial_balance,
            'currency' => $currency
        ]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create account', 'code' => 500]);
    }
    exit;
}

// ===== 6. LIST ACCOUNTS =====
if ($action === 'list_accounts' && $method === 'GET') {
    $accounts = [];
    if (is_dir(ACCOUNTS_DIR)) {
        foreach (scandir(ACCOUNTS_DIR) as $f) {
            if ($f === '.' || $f === '..' || pathinfo($f, PATHINFO_EXTENSION) !== 'txt') continue;
            $acc = get_account_data(basename($f, '.txt'));
            if ($acc) {
                $accounts[] = [
                    'account_number' => basename($f, '.txt'),
                    'owner' => $acc['organization'],
                    'bank' => $acc['bank'],
                    'balance' => $acc['balance'],
                    'type' => $acc['account_type'],
                    'currency' => $acc['currency']
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'data' => $accounts, 'count' => count($accounts)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action', 'available_actions' => [
    'transfer', 'balance', 'verify', 'history', 'create_account', 'list_accounts'
]]);
exit;