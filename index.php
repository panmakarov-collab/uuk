<?php
// ================================================================
// ОБЪЕДИНЕННЫЙ БАНКОВСКИЙ СИСТЕМНЫЙ ФАЙЛ
// Поддержка валют: чякө и песета (₧). 1 чякө = 10 ₧
// ================================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
mb_internal_encoding('UTF-8');

// ===== НАСТРОЙКИ =====
define('ACCOUNTS_DIR', 'accounts/');
define('PROPERTIES_FILE', 'properties.txt');
define('PURCHASES_FILE', 'purchases.txt');
define('SOLD_PROPERTIES_DIR', 'sold_properties/');
define('SALARY_EMPLOYERS_DIR', 'salary_employers/');
define('SALARY_EMPLOYEES_DIR', 'salary_employees/');
define('SALARY_HISTORY_DIR', 'salary_history/');
define('BANKS_FILE', 'banks.json');
define('LOANS_DIR', 'loans/');
define('LOAN_HOLDERS_DIR', 'loan_holders/');

define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('PESETA_SYMBOL', '₧');
define('CHAKO_PER_PESETA', 10);
define('BOND_PRICE', 5);
define('EXTERNAL_TRANSFERS_FILE', 'external_transfers.txt');

// ===== ЖУРНАЛ ТРАНЗАКЦИЙ =====
// Единый формат строки external_transfers.txt (используют index.php, api.php, process_transaction.php):
// дата|ID транзакции|получатель|отправитель|сумма|описание|валюта
function log_transaction($external_id, $recipient, $sender, $amount, $description, $currency) {
    $line = date('Y-m-d H:i:s') . '|' . $external_id . '|' . $recipient . '|' . $sender . '|'
          . rtrim(rtrim(number_format((float)$amount, 2, '.', ''), '0'), '.') . '|'
          . str_replace(["\n", "\r", '|'], ' ', $description) . '|' . normalize_currency($currency) . "\n";
    file_put_contents(EXTERNAL_TRANSFERS_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ===== ВАЛЮТНЫЕ ХЕЛПЕРЫ =====
function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}
function currency_symbol($cur) {
    return $cur === CURRENCY_PESETA ? PESETA_SYMBOL : 'чякө';
}
function currency_short($cur) {
    return $cur === CURRENCY_PESETA ? '₧' : 'ч.';
}
function currency_full($cur) {
    return $cur === CURRENCY_PESETA ? 'песета (₧)' : 'чякө';
}
function to_chako($amount, $currency) {
    return $currency === CURRENCY_PESETA ? $amount / CHAKO_PER_PESETA : $amount;
}
function convert_from_chako($amount, $to_currency) {
    return $to_currency === CURRENCY_PESETA ? round($amount * CHAKO_PER_PESETA, 2) : round($amount, 2);
}
function convert_amount($amount, $from, $to) {
    if ($from === $to) return $amount;
    if ($from === CURRENCY_CHAKO && $to === CURRENCY_PESETA) return $amount * CHAKO_PER_PESETA;
    if ($from === CURRENCY_PESETA && $to === CURRENCY_CHAKO) return $amount / CHAKO_PER_PESETA;
    return $amount;
}

// ===== БАНКИ =====
function get_banks() {
    if (file_exists(BANKS_FILE)) {
        $content = file_get_contents(BANKS_FILE);
        $data = json_decode($content, true);
        if (is_array($data) && !empty($data)) return $data;
    }
    $default_banks = [
        ['id' => 'bank_1', 'name' => 'Чұнат пәнкә', 'icon' => 'fa-university', 'color' => '#4facfe'],
        ['id' => 'bank_2', 'name' => 'Анкел пәнкә', 'icon' => 'fa-building', 'color' => '#43e97b'],
        ['id' => 'bank_3', 'name' => 'Инвестызыйны пәнкә', 'icon' => 'fa-chart-line', 'color' => '#f5a623'],
        ['id' => 'bank_4', 'name' => 'Тәжашұт пәнкә', 'icon' => 'fa-handshake', 'color' => '#a855f7'],
        ['id' => 'bank_5', 'name' => 'Ёратүрат пәнкә', 'icon' => 'fa-heart', 'color' => '#f5576c'],
        ['id' => 'bank_6', 'name' => 'Лагбөнат пәнкә', 'icon' => 'fa-gem', 'color' => '#14b8a6'],
        ['id' => 'bank_7', 'name' => 'Banco Bolivariano', 'icon' => 'fa-flag', 'color' => '#f97316']
    ];
    file_put_contents(BANKS_FILE, json_encode($default_banks, JSON_PRETTY_PRINT));
    return $default_banks;
}
function save_banks($banks) {
    file_put_contents(BANKS_FILE, json_encode($banks, JSON_PRETTY_PRINT));
}

// ===== СЧЕТА =====
function generate_account_number() {
    return mt_rand(100000000000, 999999999999);
}
function get_account_data($account_number) {
    $filename = ACCOUNTS_DIR . $account_number . '.txt';
    if (!file_exists($filename)) return false;
    // FILE_IGNORE_NEW_LINES без FILE_SKIP_EMPTY_LINES — пустые строки
    // (например, пустое поле last_payment у сотрудников) сохраняют индексы полей
    $data = file($filename, FILE_IGNORE_NEW_LINES);
    if (count($data) < 3) return false;
    $currency = normalize_currency($data[4] ?? CURRENCY_CHAKO);
    return [
        'account_number' => $account_number,
        'organization' => $data[0] ?? '',
        'bank' => $data[1] ?? '',
        'balance' => floatval($data[2] ?? 0),
        'account_type' => $data[3] ?? 'Сұкромны',
        'currency' => $currency
    ];
}
function get_account_currency($num) {
    $d = get_account_data($num);
    return $d ? $d['currency'] : false;
}
function get_account_balance($num) {
    $d = get_account_data($num);
    return $d ? $d['balance'] : false;
}
function update_account_balance($num, $new_balance) {
    $filename = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($filename)) return false;
    $data = file($filename, FILE_IGNORE_NEW_LINES);
    if (count($data) < 3) return false;
    $new_balance = round((float)$new_balance, 2);
    if ($new_balance < 0) $new_balance = 0.0; // балансы не могут быть отрицательными
    $data[2] = number_format($new_balance, 2, '.', '');
    return file_put_contents($filename, implode("\n", $data) . "\n") !== false;
}
function get_all_accounts() {
    $accounts = [];
    if (!is_dir(ACCOUNTS_DIR)) return $accounts;
    foreach (scandir(ACCOUNTS_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $data = file(ACCOUNTS_DIR . $file, FILE_IGNORE_NEW_LINES);
        if (count($data) >= 3) {
            $accounts[] = [
                'account_number' => pathinfo($file, PATHINFO_FILENAME),
                'organization' => $data[0] ?? 'Unknown',
                'bank' => $data[1] ?? 'Unknown',
                'balance' => floatval($data[2] ?? 0),
                'account_type' => $data[3] ?? 'Сұкромны',
                'currency' => normalize_currency($data[4] ?? CURRENCY_CHAKO)
            ];
        }
    }
    return $accounts;
}

// ===== СТАТИСТИКА ПО БАНКАМ (все суммы в чякө) =====
function get_bank_statistics() {
    $accounts = get_all_accounts();
    $banks = get_banks();
    $stats = [];
    $total_balance_chako = 0;
    $total_accounts = count($accounts);

    foreach ($banks as $bank) {
        $stats[$bank['name']] = [
            'count' => 0, 'total_balance' => 0, 'accounts' => [],
            'id' => $bank['id'],
            'icon' => $bank['icon'] ?? 'fa-university',
            'color' => $bank['color'] ?? '#4facfe'
        ];
    }
    $stats['Не указан'] = [
        'count' => 0, 'total_balance' => 0, 'accounts' => [],
        'id' => 'unknown', 'icon' => 'fa-question-circle', 'color' => '#6b7280'
    ];

    foreach ($accounts as $acc) {
        $bank = $acc['bank'] ?: 'Не указан';
        if (!isset($stats[$bank])) {
            $stats[$bank] = [
                'count' => 0, 'total_balance' => 0, 'accounts' => [],
                'id' => 'custom_' . uniqid(), 'icon' => 'fa-university', 'color' => '#8b5cf6'
            ];
        }
        $stats[$bank]['count']++;
        $balance_chako = to_chako($acc['balance'], $acc['currency']);
        $stats[$bank]['total_balance'] += $balance_chako;
        $stats[$bank]['accounts'][] = $acc;
        $total_balance_chako += $balance_chako;
    }

    foreach ($stats as $key => $value) {
        if ($value['count'] === 0 && $key !== 'Не указан') {
            $exists = false;
            foreach ($banks as $b) if ($b['name'] === $key) { $exists = true; break; }
            if (!$exists) unset($stats[$key]);
        }
    }

    uasort($stats, function($a, $b) { return $b['total_balance'] <=> $a['total_balance']; });

    return [
        'banks' => $stats,
        'total_balance' => $total_balance_chako,
        'total_accounts' => $total_accounts,
        'bank_count' => count($stats)
    ];
}

// ================================================================
// ЗАРПЛАТНЫЙ СЕРВИС
// Формат файла сотрудника (salary_employees/<id>.txt):
//   0: employer_id
//   1: name
//   2: account
//   3: salary
//   4: hire_date
//   5: last_payment
//   6: salary_currency (чякө | песета), если нет — чякө
// ================================================================
function get_salary_employers() {
    $employers = [];
    if (!is_dir(SALARY_EMPLOYERS_DIR)) return $employers;
    foreach (scandir(SALARY_EMPLOYERS_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $data = file(SALARY_EMPLOYERS_DIR . $file, FILE_IGNORE_NEW_LINES);
        if (count($data) >= 3) {
            $employers[] = [
                'id' => pathinfo($file, PATHINFO_FILENAME),
                'name' => $data[0] ?? '',
                'account' => $data[1] ?? '',
                'created' => $data[2] ?? date('Y-m-d')
            ];
        }
    }
    return $employers;
}
function get_salary_employees($employer_id = null) {
    $employees = [];
    if (!is_dir(SALARY_EMPLOYEES_DIR)) return $employees;
    foreach (scandir(SALARY_EMPLOYEES_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $data = file(SALARY_EMPLOYEES_DIR . $file, FILE_IGNORE_NEW_LINES);
        if (count($data) >= 5) {
            $emp = [
                'id' => pathinfo($file, PATHINFO_FILENAME),
                'employer_id' => $data[0] ?? '',
                'name' => $data[1] ?? '',
                'account' => $data[2] ?? '',
                'salary' => floatval($data[3] ?? 0),
                'hire_date' => $data[4] ?? date('Y-m-d'),
                'last_payment' => $data[5] ?? '',
                'salary_currency' => normalize_currency($data[6] ?? CURRENCY_CHAKO)
            ];
            if ($employer_id === null || $emp['employer_id'] === $employer_id) {
                $employees[] = $emp;
            }
        }
    }
    return $employees;
}
function get_salary_history($employee_id) {
    $history = [];
    $file = SALARY_HISTORY_DIR . $employee_id . '.txt';
    if (file_exists($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $history[] = [
                    'date' => $parts[0],
                    'amount' => floatval($parts[1]),  // всегда в чякө
                    'month' => $parts[2],
                    'year' => $parts[3]
                ];
            }
        }
    }
    return $history;
}
function get_last_payment_months($employee_id) {
    $history = get_salary_history($employee_id);
    $months = [];
    foreach ($history as $h) {
        $months[] = $h['year'] . '-' . str_pad($h['month'], 2, '0', STR_PAD_LEFT);
    }
    return array_unique($months);
}
function get_missing_months($employee_id) {
    $employees = get_salary_employees();
    $employee = null;
    foreach ($employees as $emp) if ($emp['id'] === $employee_id) { $employee = $emp; break; }
    if (!$employee) return [];

    $hire_ts = strtotime($employee['hire_date']);
    $hy = intval(date('Y', $hire_ts));
    $hm = intval(date('m', $hire_ts));
    $cy = intval(date('Y'));
    $cm = intval(date('m'));
    $paid = get_last_payment_months($employee_id);
    $missing = [];

    for ($y = $hy; $y <= $cy; $y++) {
        $start_m = ($y == $hy) ? $hm : 1;
        $end_m = ($y == $cy) ? $cm : 12;
        for ($m = $start_m; $m <= $end_m; $m++) {
            $key = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            if (!in_array($key, $paid)) $missing[] = ['year' => $y, 'month' => $m];
        }
    }
    return $missing;
}
function get_total_debt($employee_id) {
    $missing = get_missing_months($employee_id);
    $employees = get_salary_employees();
    $employee = null;
    foreach ($employees as $emp) if ($emp['id'] === $employee_id) { $employee = $emp; break; }
    if (!$employee) return 0;
    $salary_chako = to_chako($employee['salary'], $employee['salary_currency']);
    return count($missing) * $salary_chako;
}

// ================================================================
// ОБЛИГАЦИИ
// ================================================================
function create_loan($owner_account, $total_amount) {
    if (!is_dir(LOANS_DIR)) mkdir(LOANS_DIR, 0777, true);
    if (!is_dir(LOAN_HOLDERS_DIR)) mkdir(LOAN_HOLDERS_DIR, 0777, true);

    $owner_data = get_account_data($owner_account);
    if (!$owner_data) return ['success' => false, 'message' => 'Счёт владельца не найден'];

    $amount_chako = to_chako($total_amount, $owner_data['currency']);
    $bond_count = intval($amount_chako / BOND_PRICE);
    if ($bond_count < 1) return ['success' => false, 'message' => 'Минимальная сумма займа — ' . BOND_PRICE . ' чякө'];

    $loan_id = 'loan_' . uniqid();
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    $loan_data = [
        'id' => $loan_id, 'owner_account' => $owner_account,
        'total_amount' => $amount_chako, 'bond_price' => BOND_PRICE,
        'bond_count' => $bond_count, 'remaining_bonds' => $bond_count,
        'created_at' => date('Y-m-d H:i:s'), 'expires_at' => $expires_at,
        'status' => 'active'
    ];
    file_put_contents(LOANS_DIR . $loan_id . '.txt', json_encode($loan_data));
    file_put_contents(LOAN_HOLDERS_DIR . $loan_id . '.txt', json_encode([]));

    return ['success' => true, 'message' => "Займ создан: {$bond_count} облигаций по " . BOND_PRICE . " чякө", 'loan' => $loan_data];
}
function get_loans($status = null) {
    $loans = [];
    if (!is_dir(LOANS_DIR)) return $loans;
    foreach (scandir(LOANS_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $data = json_decode(file_get_contents(LOANS_DIR . $file), true);
        if ($data) {
            if ($data['status'] === 'active' && strtotime($data['expires_at']) < time()) {
                $data['status'] = 'expired';
                file_put_contents(LOANS_DIR . $file, json_encode($data));
            }
            if ($status === null || $data['status'] === $status) $loans[] = $data;
        }
    }
    usort($loans, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
    return $loans;
}
function get_my_loans($num) {
    $all = get_loans();
    $my = [];
    foreach ($all as $l) if ($l['owner_account'] === $num) $my[] = $l;
    return $my;
}
function get_loan_holders($loan_id) {
    $file = LOAN_HOLDERS_DIR . $loan_id . '.txt';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    return [];
}
function save_loan_holders($loan_id, $holders) {
    file_put_contents(LOAN_HOLDERS_DIR . $loan_id . '.txt', json_encode($holders));
}
function buy_bond($loan_id, $buyer_account, $quantity = 1) {
    $filename = LOANS_DIR . $loan_id . '.txt';
    if (!file_exists($filename)) return ['success' => false, 'message' => 'Займ не найден'];
    $loan = json_decode(file_get_contents($filename), true);
    if (!$loan) return ['success' => false, 'message' => 'Ошибка чтения'];
    if ($loan['status'] !== 'active') return ['success' => false, 'message' => 'Займ не активен'];
    if (strtotime($loan['expires_at']) < time()) {
        $loan['status'] = 'expired'; file_put_contents($filename, json_encode($loan));
        return ['success' => false, 'message' => 'Срок истёк'];
    }
    if ($buyer_account === $loan['owner_account']) return ['success' => false, 'message' => 'Нельзя покупать свои'];
    if ($quantity < 1 || $quantity > $loan['remaining_bonds']) return ['success' => false, 'message' => 'Неверное количество'];

    $buyer = get_account_data($buyer_account);
    $owner = get_account_data($loan['owner_account']);
    if (!$buyer) return ['success' => false, 'message' => 'Счёт покупателя не найден'];
    if (!$owner) return ['success' => false, 'message' => 'Счёт владельца не найден'];

    $total_price_chako = $quantity * $loan['bond_price'];
    $price_for_buyer = convert_from_chako($total_price_chako, $buyer['currency']);
    $price_for_owner = convert_from_chako($total_price_chako, $owner['currency']);

    if ($buyer['balance'] < $price_for_buyer) {
        return ['success' => false, 'message' => "Недостаточно средств. Нужно: {$price_for_buyer} "
            . currency_short($buyer['currency'])];
    }

    if (!update_account_balance($buyer_account, $buyer['balance'] - $price_for_buyer)) return ['success' => false, 'message' => 'Ошибка списания'];
    if (!update_account_balance($loan['owner_account'], $owner['balance'] + $price_for_owner)) {
        update_account_balance($buyer_account, $buyer['balance']);
        return ['success' => false, 'message' => 'Ошибка зачисления'];
    }

    $loan['remaining_bonds'] -= $quantity;
    $holders = get_loan_holders($loan_id);
    if (!isset($holders[$buyer_account])) $holders[$buyer_account] = 0;
    $holders[$buyer_account] += $quantity;
    save_loan_holders($loan_id, $holders);
    if ($loan['remaining_bonds'] <= 0) $loan['status'] = 'completed';
    file_put_contents($filename, json_encode($loan));

    return ['success' => true, 'message' => "Куплено {$quantity} облигаций на {$total_price_chako} чякө", 'remaining' => $loan['remaining_bonds'], 'status' => $loan['status']];
}
function repay_loan($loan_id, $owner_account, $quantity = null) {
    $filename = LOANS_DIR . $loan_id . '.txt';
    if (!file_exists($filename)) return ['success' => false, 'message' => 'Займ не найден'];
    $loan = json_decode(file_get_contents($filename), true);
    if (!$loan) return ['success' => false, 'message' => 'Ошибка чтения'];
    if ($loan['owner_account'] !== $owner_account) return ['success' => false, 'message' => 'Вы не владелец'];
    if ($loan['status'] !== 'active') return ['success' => false, 'message' => 'Займ не активен'];

    $holders = get_loan_holders($loan_id);
    if (empty($holders)) return ['success' => false, 'message' => 'Нет держателей'];
    if ($quantity === null) $quantity = $loan['remaining_bonds'];
    if ($quantity < 1 || $quantity > $loan['remaining_bonds']) return ['success' => false, 'message' => 'Неверное количество'];

    $holders_list = [];
    foreach ($holders as $acc => $cnt) if ($cnt > 0) $holders_list[] = ['account' => $acc, 'count' => $cnt];
    if (empty($holders_list)) return ['success' => false, 'message' => 'Нет держателей'];

    $total_held = array_sum(array_column($holders_list, 'count'));
    $to_repay = $quantity;
    $repayments = [];
    foreach ($holders_list as $h) {
        if ($to_repay <= 0) break;
        $share = round(($h['count'] / $total_held) * $quantity);
        if ($share < 1) $share = 1;
        if ($share > $h['count']) $share = $h['count'];
        if ($share > $to_repay) $share = $to_repay;
        $repayments[] = ['account' => $h['account'], 'count' => $share, 'amount_chako' => $share * $loan['bond_price']];
        $to_repay -= $share;
    }
    if ($to_repay > 0 && !empty($repayments)) {
        $repayments[0]['count'] += $to_repay;
        $repayments[0]['amount_chako'] = $repayments[0]['count'] * $loan['bond_price'];
    }

    $owner = get_account_data($owner_account);
    if (!$owner) return ['success' => false, 'message' => 'Счёт владельца не найден'];
    $total_pay_chako = array_sum(array_column($repayments, 'amount_chako'));
    $total_pay_owner = convert_from_chako($total_pay_chako, $owner['currency']);
    if ($owner['balance'] < $total_pay_owner) {
        return ['success' => false, 'message' => "Недостаточно средств. Нужно: {$total_pay_owner} "
            . currency_short($owner['currency'])];
    }

    foreach ($repayments as $r) {
        $h_data = get_account_data($r['account']);
        if (!$h_data) return ['success' => false, 'message' => 'Счёт держателя не найден'];
        $amount = convert_from_chako($r['amount_chako'], $h_data['currency']);
        if (!update_account_balance($r['account'], $h_data['balance'] + $amount)) {
            return ['success' => false, 'message' => 'Ошибка зачисления'];
        }
        $holders[$r['account']] -= $r['count'];
        if ($holders[$r['account']] <= 0) unset($holders[$r['account']]);
    }

    if (!update_account_balance($owner_account, $owner['balance'] - $total_pay_owner)) {
        foreach ($repayments as $r) {
            $h_data = get_account_data($r['account']);
            if ($h_data) {
                $amount = convert_from_chako($r['amount_chako'], $h_data['currency']);
                update_account_balance($r['account'], $h_data['balance'] - $amount);
            }
        }
        return ['success' => false, 'message' => 'Ошибка списания'];
    }

    $loan['remaining_bonds'] -= $quantity;
    save_loan_holders($loan_id, $holders);
    if ($loan['remaining_bonds'] <= 0) $loan['status'] = 'completed';
    file_put_contents($filename, json_encode($loan));

    return ['success' => true, 'message' => "Погашено {$quantity} облигаций на {$total_pay_chako} чякө", 'repayments' => $repayments, 'remaining' => $loan['remaining_bonds'], 'status' => $loan['status']];
}

// ================================================================
// API ROUTING
// ================================================================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'get_banks') { header('Content-Type: application/json'); echo json_encode(get_banks()); exit; }

if ($action === 'add_bank' && $method === 'POST') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-university');
    $color = trim($_POST['color'] ?? '#4facfe');
    if (empty($name)) { echo json_encode(['success' => false, 'message' => 'Название обязательно']); exit; }
    $banks = get_banks();
    foreach ($banks as $bank) if (strtolower($bank['name']) === strtolower($name)) { echo json_encode(['success' => false, 'message' => 'Банк существует']); exit; }
    $new = ['id' => 'bank_' . uniqid(), 'name' => $name, 'icon' => $icon, 'color' => $color];
    $banks[] = $new;
    save_banks($banks);
    echo json_encode(['success' => true, 'message' => 'Банк добавлен', 'bank' => $new]);
    exit;
}

if ($action === 'update_bank' && $method === 'POST') {
    header('Content-Type: application/json');
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-university');
    $color = trim($_POST['color'] ?? '#4facfe');
    if (empty($id) || empty($name)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    $banks = get_banks();
    $found = false;
    foreach ($banks as $k => $b) if ($b['id'] === $id) { $banks[$k]['name'] = $name; $banks[$k]['icon'] = $icon; $banks[$k]['color'] = $color; $found = true; break; }
    if (!$found) { echo json_encode(['success' => false, 'message' => 'Банк не найден']); exit; }
    save_banks($banks);
    echo json_encode(['success' => true, 'message' => 'Обновлено']);
    exit;
}

if ($action === 'delete_bank' && $method === 'POST') {
    header('Content-Type: application/json');
    $id = trim($_POST['id'] ?? '');
    if (empty($id)) { echo json_encode(['success' => false, 'message' => 'ID обязателен']); exit; }
    $banks = get_banks();
    $found = false; $bn = '';
    foreach ($banks as $k => $b) if ($b['id'] === $id) { $bn = $b['name']; unset($banks[$k]); $found = true; break; }
    if (!$found) { echo json_encode(['success' => false, 'message' => 'Банк не найден']); exit; }
    save_banks(array_values($banks));
    echo json_encode(['success' => true, 'message' => "Банк '$bn' удалён"]);
    exit;
}

if ($action === 'open_account' && $method === 'POST') {
    header('Content-Type: application/json');
    $owner = trim($_POST['owner_name'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $balance = floatval($_POST['initial_balance'] ?? 0);
    $type = trim($_POST['account_type'] ?? 'Сұкромны');
    $currency = normalize_currency($_POST['currency'] ?? CURRENCY_CHAKO);
    if (empty($owner) || empty($bank)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    $num = generate_account_number();
    $data = "$owner\n$bank\n" . number_format($balance, 2, '.', '') . "\n$type\n$currency";
    if (!is_dir(ACCOUNTS_DIR)) mkdir(ACCOUNTS_DIR, 0777, true);
    if (file_put_contents(ACCOUNTS_DIR . $num . '.txt', $data)) {
        echo json_encode(['success' => true, 'message' => 'Счёт создан', 'account_number' => $num, 'currency' => $currency]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка создания']);
    }
    exit;
}

if ($action === 'get_accounts') { header('Content-Type: application/json'); echo json_encode(get_all_accounts()); exit; }

if ($action === 'get_account' && isset($_GET['account'])) {
    header('Content-Type: application/json');
    $data = get_account_data(trim($_GET['account']));
    echo $data ? json_encode(['success' => true] + $data) : json_encode(['success' => false, 'message' => 'Не найден']);
    exit;
}

if ($action === 'update_account' && $method === 'POST') {
    header('Content-Type: application/json');
    $num = trim($_POST['account_number'] ?? '');
    $org = trim($_POST['organization'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $balance = floatval($_POST['balance'] ?? 0);
    $type = trim($_POST['account_type'] ?? 'Сұкромны');
    $currency = normalize_currency($_POST['currency'] ?? CURRENCY_CHAKO);
    if (empty($num) || empty($org) || empty($bank)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    $file = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($file)) { echo json_encode(['success' => false, 'message' => 'Не найден']); exit; }
    $data = "$org\n$bank\n" . number_format($balance, 2, '.', '') . "\n$type\n$currency";
    if (file_put_contents($file, $data)) echo json_encode(['success' => true, 'message' => 'Обновлено']);
    else echo json_encode(['success' => false, 'message' => 'Ошибка']);
    exit;
}

if ($action === 'delete_account' && $method === 'POST') {
    header('Content-Type: application/json');
    $num = trim($_POST['account_number'] ?? '');
    if (empty($num)) { echo json_encode(['success' => false, 'message' => 'Номер обязателен']); exit; }
    $file = ACCOUNTS_DIR . $num . '.txt';
    if (file_exists($file)) {
        if (unlink($file)) echo json_encode(['success' => true, 'message' => "Счёт $num удалён"]);
        else echo json_encode(['success' => false, 'message' => 'Ошибка']);
    } else echo json_encode(['success' => false, 'message' => 'Не найден']);
    exit;
}

if ($action === 'get_stats') {
    header('Content-Type: application/json');
    $accounts = get_all_accounts();
    $total_chako = 0;
    $p_acc = 0;
    $p_bal = 0;
    foreach ($accounts as $a) {
        $total_chako += to_chako($a['balance'], $a['currency']);
        if ($a['currency'] === CURRENCY_PESETA) { $p_acc++; $p_bal += $a['balance']; }
    }
    $gold = ['164933742944','335207322657','351533922628','450860884054','530860227991','685391813377','700285295323','810905614575','939325520387','956048230780'];
    $gold_chako = 0;
    foreach ($gold as $acc) {
        $d = get_account_data($acc);
        if ($d) $gold_chako += to_chako($d['balance'], $d['currency']);
    }
    echo json_encode([
        'success' => true,
        'account_count' => count($accounts),
        'total_balance' => $total_chako,
        'gold_reserve' => $gold_chako,
        'peseta_account_count' => $p_acc,
        'peseta_balance' => $p_bal
    ]);
    exit;
}

if ($action === 'get_bank_stats') { header('Content-Type: application/json'); echo json_encode(get_bank_statistics()); exit; }

if ($action === 'transfer' && $method === 'POST') {
    header('Content-Type: application/json');
    $sender = trim($_POST['sender_account'] ?? '');
    $rec = trim($_POST['recipient_account'] ?? '');
    $amount = floatval($_POST['transfer_amount'] ?? 0);
    if (empty($sender) || empty($rec) || $amount <= 0) { echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit; }
    if ($sender === $rec) { echo json_encode(['success' => false, 'message' => 'Нельзя на свой счёт']); exit; }

    $s = get_account_data($sender);
    $r = get_account_data($rec);
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Счёт отправителя не найден']); exit; }
    if (!$r) { echo json_encode(['success' => false, 'message' => 'Счёт получателя не найден']); exit; }
    if ($s['currency'] === CURRENCY_PESETA && $amount < 10) {
        echo json_encode(['success' => false, 'message' => 'Минимум 10 ₧']); exit;
    }
    if ($s['balance'] < $amount) { echo json_encode(['success' => false, 'message' => 'Недостаточно средств']); exit; }

    $amount_to_rec = round(convert_amount($amount, $s['currency'], $r['currency']), 2);
    if (update_account_balance($sender, $s['balance'] - $amount)
        && update_account_balance($rec, $r['balance'] + $amount_to_rec)) {
        log_transaction('web_' . round(microtime(true) * 1000), $rec, $sender, $amount, 'Перевод внутри банка', $s['currency']);
        $msg = "Перевод выполнен: {$amount} " . currency_short($s['currency']);
        if ($s['currency'] !== $r['currency']) {
            $msg .= " → {$amount_to_rec} " . currency_short($r['currency']) . " (курс 1 чякө = " . CHAKO_PER_PESETA . " ₧)";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка обновления']);
    }
    exit;
}

if ($action === 'issue_loan' && $method === 'POST') {
    header('Content-Type: application/json');
    $acc = trim($_POST['account_number'] ?? '');
    $amount = floatval($_POST['loan_amount'] ?? 0);
    if (empty($acc) || $amount <= 0) { echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit; }
    $d = get_account_data($acc);
    if (!$d) { echo json_encode(['success' => false, 'message' => 'Не найден']); exit; }
    // Сумма кредита указывается в валюте счёта заёмщика
    if (update_account_balance($acc, $d['balance'] + $amount)) {
        log_transaction('loan_' . round(microtime(true) * 1000), $acc, 'банк', $amount, 'Выдача кредита', $d['currency']);
        echo json_encode(['success' => true, 'message' => "Кредит на $amount " . currency_short($d['currency']) . " выдан"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка']);
    }
    exit;
}

// ===== НЕДВИЖИМОСТЬ =====
if ($action === 'add_property' && $method === 'POST') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? ''); $id = trim($_POST['id'] ?? '');
    $addr = trim($_POST['address'] ?? ''); $area = trim($_POST['area'] ?? '');
    $prov = trim($_POST['province'] ?? ''); $year = trim($_POST['year_built'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $currency = normalize_currency($_POST['currency'] ?? CURRENCY_CHAKO);
    if (empty($name) || empty($id) || empty($addr) || empty($area) || empty($prov) || empty($year) || empty($price)) {
        echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit;
    }
    $data = "$name|$id|$addr|$area|$prov|$year|$price|$currency\n";
    if (file_put_contents(PROPERTIES_FILE, $data, FILE_APPEND)) echo json_encode(['success' => true, 'message' => 'Добавлено']);
    else echo json_encode(['success' => false, 'message' => 'Ошибка']);
    exit;
}

// Удаление объекта недвижимости (по ID или названию) — тот же алгоритм, что в delete_property.php
if ($action === 'delete_property' && $method === 'POST') {
    header('Content-Type: application/json');
    $property_id = trim($_POST['property_id'] ?? '');
    if (empty($property_id)) { echo json_encode(['success' => false, 'message' => 'ID объекта обязателен']); exit; }
    if (!file_exists(PROPERTIES_FILE)) { echo json_encode(['success' => false, 'message' => 'Файл объектов не найден']); exit; }
    $lines = file(PROPERTIES_FILE, FILE_IGNORE_NEW_LINES);
    $kept = []; $removed = null;
    foreach ($lines as $line) {
        if (trim((string)$line) === '') continue;
        $d = explode('|', $line);
        if (isset($d[1]) && ($d[1] === $property_id || $d[0] === $property_id)) { $removed = $d[0]; continue; }
        $kept[] = $line;
    }
    if ($removed === null) { echo json_encode(['success' => false, 'message' => 'Объект не найден']); exit; }
    $content = $kept ? implode("\n", $kept) . "\n" : "";
    if (file_put_contents(PROPERTIES_FILE, $content, LOCK_EX) !== false)
        echo json_encode(['success' => true, 'message' => "Объект '$removed' удалён"]);
    else
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения']);
    exit;
}

if ($action === 'get_properties') {
    header('Content-Type: application/json');
    $props = [];
    if (file_exists(PROPERTIES_FILE)) {
        foreach (file(PROPERTIES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $d = explode('|', $line);
            if (count($d) >= 7) {
                $props[] = [
                    'name' => $d[0], 'id' => $d[1], 'address' => $d[2], 'area' => $d[3],
                    'province' => $d[4], 'year_built' => $d[5], 'price' => floatval($d[6]),
                    'currency' => normalize_currency($d[7] ?? CURRENCY_CHAKO)
                ];
            }
        }
    }
    echo json_encode($props); exit;
}

if ($action === 'buy_property' && $method === 'POST') {
    header('Content-Type: application/json');
    $prop = trim($_POST['propertyToBuy'] ?? '');
    $num = trim($_POST['accountNumber'] ?? '');
    if (empty($prop) || empty($num)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    if (!file_exists(PROPERTIES_FILE)) { echo json_encode(['success' => false, 'message' => 'Файл не найден']); exit; }
    $buyer = get_account_data($num);
    if (!$buyer) { echo json_encode(['success' => false, 'message' => 'Счёт не найден']); exit; }

    $props = file(PROPERTIES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false; $price = 0; $cur = CURRENCY_CHAKO; $pdata = []; $idx = -1;
    foreach ($props as $k => $line) {
        $d = explode('|', $line);
        if ($d[0] === $prop) {
            $found = true; $price = floatval($d[6]);
            $cur = normalize_currency($d[7] ?? CURRENCY_CHAKO);
            $pdata = $d; $idx = $k; break;
        }
    }
    if (!$found) { echo json_encode(['success' => false, 'message' => 'Объект не найден']); exit; }

    $price_for_buyer = round(convert_amount($price, $cur, $buyer['currency']), 2);
    if ($buyer['balance'] < $price_for_buyer) {
        echo json_encode(['success' => false, 'message' => "Недостаточно средств. Нужно: {$price_for_buyer} "
            . currency_short($buyer['currency'])]); exit;
    }

    if (!is_dir(SOLD_PROPERTIES_DIR)) mkdir(SOLD_PROPERTIES_DIR, 0777, true);
    $sale = uniqid();
    $sold_file = SOLD_PROPERTIES_DIR . 'sold_' . $sale . '.txt';
    $sold_data = "Sale Number: $sale\nProperty: " . implode(' | ', $pdata) . "\nBuyer Account: $num\nDate: " . date('Y-m-d H:i:s') . "\n";
    file_put_contents($sold_file, $sold_data);
    // Единый формат purchases.txt: название|ID продажи|счёт покупателя|цена для покупателя|валюта покупателя
    file_put_contents(PURCHASES_FILE, "$prop|$sale|$num|$price_for_buyer|{$buyer['currency']}\n", FILE_APPEND);
    unset($props[$idx]);
    file_put_contents(PROPERTIES_FILE, implode("\n", array_filter($props, fn($l) => trim((string)$l) !== '')));
    update_account_balance($num, $buyer['balance'] - $price_for_buyer);
    log_transaction('sale_' . $sale, '983902849056', $num, $price_for_buyer, "Покупка объекта: $prop", $buyer['currency']);

    $special = '983902849056';
    $sp = get_account_data($special);
    if ($sp) {
        $amount_sp = round(convert_amount($price, $cur, $sp['currency']), 2);
        update_account_balance($special, $sp['balance'] + $amount_sp);
    }
    echo json_encode(['success' => true, 'message' => "Объект '$prop' куплен"]);
    exit;
}

// ===== ОБЛИГАЦИИ =====
if ($action === 'create_loan' && $method === 'POST') {
    header('Content-Type: application/json');
    $acc = trim($_POST['owner_account'] ?? '');
    $amt = floatval($_POST['total_amount'] ?? 0);
    if (empty($acc) || $amt <= 0) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    echo json_encode(create_loan($acc, $amt)); exit;
}
if ($action === 'get_loans') { header('Content-Type: application/json'); echo json_encode(get_loans($_GET['status'] ?? null)); exit; }
if ($action === 'get_my_loans' && isset($_GET['account'])) { header('Content-Type: application/json'); echo json_encode(get_my_loans(trim($_GET['account']))); exit; }
if ($action === 'buy_bond' && $method === 'POST') {
    header('Content-Type: application/json');
    $lid = trim($_POST['loan_id'] ?? ''); $bacc = trim($_POST['buyer_account'] ?? ''); $q = intval($_POST['quantity'] ?? 1);
    if (empty($lid) || empty($bacc) || $q < 1) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    echo json_encode(buy_bond($lid, $bacc, $q)); exit;
}
if ($action === 'repay_loan' && $method === 'POST') {
    header('Content-Type: application/json');
    $lid = trim($_POST['loan_id'] ?? ''); $oacc = trim($_POST['owner_account'] ?? '');
    $q = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? intval($_POST['quantity']) : null;
    if (empty($lid) || empty($oacc)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    echo json_encode(repay_loan($lid, $oacc, $q)); exit;
}
if ($action === 'get_loan_holders' && isset($_GET['loan_id'])) { header('Content-Type: application/json'); echo json_encode(get_loan_holders(trim($_GET['loan_id']))); exit; }

// ===== ЖУРНАЛ ТРАНЗАКЦИЙ =====
// GET ?action=get_transactions&account=<номер>&limit=50
// account не указан — все последние операции. Ответ: массив записей (суммы в валюте отправителя).
if ($action === 'get_transactions') {
    header('Content-Type: application/json');
    $acc = trim($_GET['account'] ?? '');
    $limit = max(1, intval($_GET['limit'] ?? 100));
    $result = [];
    if (file_exists(EXTERNAL_TRANSFERS_FILE)) {
        $lines = file(EXTERNAL_TRANSFERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach (array_reverse($lines) as $line) {
            $p = explode('|', $line);
            if (count($p) < 6) continue;
            $entry = [
                'date' => $p[0],
                'id' => $p[1],
                'recipient' => $p[2],
                'sender' => $p[3],
                'amount' => floatval($p[4]),
                'description' => $p[5],
                'currency' => normalize_currency($p[6] ?? CURRENCY_CHAKO),
            ];
            if ($acc !== '' && $entry['recipient'] !== $acc && $entry['sender'] !== $acc) continue;
            $result[] = $entry;
            if (count($result) >= $limit) break;
        }
    }
    echo json_encode(['success' => true, 'data' => $result, 'count' => count($result)]);
    exit;
}

// ===== ЗАРПЛАТА: работодатели =====
if ($action === 'create_employer' && $method === 'POST') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? ''); $acc = trim($_POST['account'] ?? '');
    if (empty($name) || empty($acc)) { echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit; }
    if (!file_exists(ACCOUNTS_DIR . $acc . '.txt')) { echo json_encode(['success' => false, 'message' => 'Счёт не найден']); exit; }
    $id = uniqid('emp_');
    if (!is_dir(SALARY_EMPLOYERS_DIR)) mkdir(SALARY_EMPLOYERS_DIR, 0777, true);
    $data = "$name\n$acc\n" . date('Y-m-d H:i:s') . "\n";
    if (file_put_contents(SALARY_EMPLOYERS_DIR . $id . '.txt', $data)) echo json_encode(['success' => true, 'message' => 'Создан', 'id' => $id]);
    else echo json_encode(['success' => false, 'message' => 'Ошибка']);
    exit;
}
if ($action === 'get_employers') { header('Content-Type: application/json'); echo json_encode(get_salary_employers()); exit; }

// ===== ЗАРПЛАТА: сотрудники =====
if ($action === 'create_employee' && $method === 'POST') {
    header('Content-Type: application/json');
    $eid = trim($_POST['employer_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $acc = trim($_POST['account'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);
    $salary_currency = normalize_currency($_POST['salary_currency'] ?? CURRENCY_CHAKO);
    $hire = trim($_POST['hire_date'] ?? date('Y-m-d'));

    if (empty($eid) || empty($name) || empty($acc) || $salary <= 0) {
        echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit;
    }
    if (!file_exists(ACCOUNTS_DIR . $acc . '.txt')) {
        echo json_encode(['success' => false, 'message' => 'Счёт не найден']); exit;
    }

    $id = uniqid('emp_');
    if (!is_dir(SALARY_EMPLOYEES_DIR)) mkdir(SALARY_EMPLOYEES_DIR, 0777, true);
    if (!is_dir(SALARY_HISTORY_DIR)) mkdir(SALARY_HISTORY_DIR, 0777, true);

    $data = "$eid\n$name\n$acc\n$salary\n$hire\n\n$salary_currency\n";
    if (file_put_contents(SALARY_EMPLOYEES_DIR . $id . '.txt', $data)) {
        echo json_encode(['success' => true, 'message' => 'Сотрудник добавлен', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка']);
    }
    exit;
}

// >>> НОВЫЙ ЭНДПОИНТ: редактирование сотрудника (включая валюту оклада)
if ($action === 'update_employee' && $method === 'POST') {
    header('Content-Type: application/json');
    $eid = trim($_POST['employee_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $acc = trim($_POST['account'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);
    $salary_currency = normalize_currency($_POST['salary_currency'] ?? CURRENCY_CHAKO);
    $hire = trim($_POST['hire_date'] ?? date('Y-m-d'));

    if (empty($eid) || empty($name) || empty($acc) || $salary <= 0) {
        echo json_encode(['success' => false, 'message' => 'Все поля обязательны']); exit;
    }
    $file = SALARY_EMPLOYEES_DIR . $eid . '.txt';
    if (!file_exists($file)) {
        echo json_encode(['success' => false, 'message' => 'Сотрудник не найден']); exit;
    }
    if (!file_exists(ACCOUNTS_DIR . $acc . '.txt')) {
        echo json_encode(['success' => false, 'message' => 'Счёт не найден']); exit;
    }
    $emp_data = file($file, FILE_IGNORE_NEW_LINES);
    if (count($emp_data) < 5) {
        echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit;
    }
    // employer_id оставляем прежним (история)
    $employer_id  = $emp_data[0];
    $last_payment = $emp_data[5] ?? '';

    $new_data = "$employer_id\n$name\n$acc\n$salary\n$hire\n$last_payment\n$salary_currency\n";
    if (file_put_contents($file, $new_data)) {
        echo json_encode(['success' => true, 'message' => 'Сотрудник обновлён']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения']);
    }
    exit;
}

if ($action === 'get_employees') {
    header('Content-Type: application/json');
    echo json_encode(get_salary_employees($_GET['employer_id'] ?? null)); exit;
}
if ($action === 'get_salary_history') {
    header('Content-Type: application/json');
    $eid = $_GET['employee_id'] ?? '';
    echo json_encode($eid ? get_salary_history($eid) : []); exit;
}
if ($action === 'get_missing_months') {
    header('Content-Type: application/json');
    $eid = $_GET['employee_id'] ?? '';
    echo json_encode($eid ? get_missing_months($eid) : []); exit;
}
if ($action === 'get_total_debt') {
    header('Content-Type: application/json');
    $eid = $_GET['employee_id'] ?? '';
    echo json_encode(['debt' => $eid ? get_total_debt($eid) : 0]); exit;
}

// ===== ЗАРПЛАТА: выплата =====
if ($action === 'pay_salary' && $method === 'POST') {
    header('Content-Type: application/json');
    $eid = trim($_POST['employee_id'] ?? '');
    $months = intval($_POST['months'] ?? 1);
    if (empty($eid) || $months < 1) { echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit; }

    $emp_file = SALARY_EMPLOYEES_DIR . $eid . '.txt';
    if (!file_exists($emp_file)) { echo json_encode(['success' => false, 'message' => 'Сотрудник не найден']); exit; }
    $emp_data = file($emp_file, FILE_IGNORE_NEW_LINES);
    if (count($emp_data) < 5) { echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit; }

    $employer_id = $emp_data[0];
    $employee_name = $emp_data[1];
    $employee_account = $emp_data[2];
    $salary = floatval($emp_data[3]);
    $salary_currency = normalize_currency($emp_data[6] ?? CURRENCY_CHAKO);

    $employer_file = SALARY_EMPLOYERS_DIR . $employer_id . '.txt';
    if (!file_exists($employer_file)) { echo json_encode(['success' => false, 'message' => 'Работодатель не найден']); exit; }
    $employer_data = file($employer_file, FILE_IGNORE_NEW_LINES);
    if (count($employer_data) < 2) { echo json_encode(['success' => false, 'message' => 'Некорректные данные']); exit; }
    $employer_account = $employer_data[1];

    $employer_acc = get_account_data($employer_account);
    $employee_acc = get_account_data($employee_account);
    if (!$employer_acc) { echo json_encode(['success' => false, 'message' => 'Счёт работодателя не найден']); exit; }
    if (!$employee_acc) { echo json_encode(['success' => false, 'message' => 'Счёт сотрудника не найден']); exit; }

    // Всё считаем в чякө
    $salary_chako = to_chako($salary, $salary_currency);
    $total_chako = round($salary_chako * $months, 2);

    $amount_from_employer = convert_from_chako($total_chako, $employer_acc['currency']);
    $amount_to_employee = convert_from_chako($total_chako, $employee_acc['currency']);

    if ($employer_acc['balance'] < $amount_from_employer) {
        echo json_encode(['success' => false, 'message' => "Недостаточно средств. Нужно: {$amount_from_employer} "
            . currency_short($employer_acc['currency']) . ", доступно: {$employer_acc['balance']}"]);
        exit;
    }

    if (!update_account_balance($employer_account, $employer_acc['balance'] - $amount_from_employer)) {
        echo json_encode(['success' => false, 'message' => 'Ошибка списания']); exit;
    }
    if (!update_account_balance($employee_account, $employee_acc['balance'] + $amount_to_employee)) {
        update_account_balance($employer_account, $employer_acc['balance']);
        echo json_encode(['success' => false, 'message' => 'Ошибка зачисления']); exit;
    }

    $emp_data[5] = date('Y-m-d H:i:s');
    while (count($emp_data) < 7) $emp_data[] = ''; // нормализуем длину до 7 полей
    $emp_data[6] = $salary_currency;
    file_put_contents($emp_file, implode("\n", $emp_data) . "\n");

    log_transaction('salary_' . round(microtime(true) * 1000), $employee_account, $employer_account,
        $amount_to_employee, "Зарплата: $employee_name ($months мес.)", $employee_acc['currency']);

    // История — в чякө
    $history_file = SALARY_HISTORY_DIR . $eid . '.txt';
    $cm = intval(date('m')); $cy = intval(date('Y'));
    for ($i = 0; $i < $months; $i++) {
        $month = $cm - $i; $year = $cy;
        if ($month <= 0) { $month += 12; $year--; }
        file_put_contents($history_file, date('Y-m-d H:i:s') . "|$salary_chako|$month|$year\n", FILE_APPEND);
    }

    echo json_encode([
        'success' => true,
        'message' => "Выплачено $amount_to_employee " . currency_short($employee_acc['currency'])
            . " ($months мес.) сотруднику $employee_name"
    ]);
    exit;
}

if ($action === 'delete_employee' && $method === 'POST') {
    header('Content-Type: application/json');
    $eid = trim($_POST['employee_id'] ?? '');
    if (empty($eid)) { echo json_encode(['success' => false, 'message' => 'ID обязателен']); exit; }
    $file = SALARY_EMPLOYEES_DIR . $eid . '.txt';
    if (file_exists($file)) { unlink($file); echo json_encode(['success' => true, 'message' => 'Удалён']); }
    else echo json_encode(['success' => false, 'message' => 'Не найден']);
    exit;
}

if ($action === 'delete_employer' && $method === 'POST') {
    header('Content-Type: application/json');
    $eid = trim($_POST['employer_id'] ?? '');
    if (empty($eid)) { echo json_encode(['success' => false, 'message' => 'ID обязателен']); exit; }
    $file = SALARY_EMPLOYERS_DIR . $eid . '.txt';
    if (!file_exists($file)) { echo json_encode(['success' => false, 'message' => 'Не найден']); exit; }
    unlink($file);
    foreach (get_salary_employees($eid) as $emp) {
        $ef = SALARY_EMPLOYEES_DIR . $emp['id'] . '.txt';
        if (file_exists($ef)) unlink($ef);
        $hf = SALARY_HISTORY_DIR . $emp['id'] . '.txt';
        if (file_exists($hf)) unlink($hf);
    }
    echo json_encode(['success' => true, 'message' => 'Удалено']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ҮРДЖАН ҮРДАТ КОНТООРЁМӘ — Banking</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
    :root {
        /* Японский минимализм: белый фон, тёплые акценты */
        --bg: #ffffff;
        --surface: #ffffff;
        --surface-soft: #fafaf7;
        --surface-hover: #f5f4ef;
        --ink: #1f2328;
        --ink-soft: #4a535e;
        --ink-mute: #9099a3;
        --line: #ebeae4;
        --line-strong: #dcdad1;
        --accent: #d96a4a;         /* сакура-терракота */
        --accent-soft: #fbf0ec;
        --accent-2: #b98a3c;       /* warm wood */
        --accent-2-soft: #faf3e6;
        --accent-3: #5a7a8c;       /* blue haze */
        --accent-3-soft: #eef3f6;
        --matcha: #7a9a6a;         /* matcha */
        --matcha-soft: #f0f4ec;
        --peseta: #b98a3c;
        --peseta-soft: #faf3e6;

        --radius-xs: 8px;
        --radius-sm: 12px;
        --radius: 18px;
        --radius-lg: 24px;

        --shadow-xs: 0 1px 2px rgba(31,35,40,0.04);
        --shadow-sm: 0 1px 3px rgba(31,35,40,0.04), 0 4px 12px rgba(31,35,40,0.03);
        --shadow-md: 0 4px 20px rgba(31,35,40,0.06), 0 2px 6px rgba(31,35,40,0.03);
        --shadow-lg: 0 20px 60px rgba(31,35,40,0.10), 0 6px 16px rgba(31,35,40,0.05);

        --transition: all .25s cubic-bezier(.2,.7,.2,1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--bg);
        color: var(--ink);
        min-height: 100vh;
        font-feature-settings: "ss01", "cv11";
        -webkit-font-smoothing: antialiased;
        line-height: 1.55;
    }
    body { padding: 12px; display: flex; flex-direction: column; }

    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e3e0d7; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #d1cec4; }

    /* ===== NAVBAR ===== */
    .navbar-wrapper {
        position: sticky; top: 0; z-index: 1000;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: saturate(140%) blur(14px);
        -webkit-backdrop-filter: saturate(140%) blur(14px);
        margin: -12px -12px 0 -12px; padding: 10px 12px;
        border-bottom: 1px solid var(--line);
    }
    .navbar {
        display: flex; align-items: center; padding: 10px 20px; gap: 16px;
        flex-wrap: wrap;
        background: transparent;
        max-width: 1440px; margin: 0 auto;
    }
    .logo {
        display: flex; align-items: center; gap: 12px;
        font-weight: 800; font-size: 17px; color: var(--ink);
        text-decoration: none; letter-spacing: -0.01em;
        margin-right: auto; flex-shrink: 0;
    }
    .logo img { height: 34px; width: auto; object-fit: contain; }
    .logo-text {
        font-weight: 800; font-size: 17px; color: var(--ink);
        letter-spacing: -0.01em;
    }

    .nav-links { display: flex; flex-wrap: wrap; gap: 2px; list-style: none; }
    .nav-links a {
        color: var(--ink-soft);
        text-decoration: none; padding: 8px 14px;
        border-radius: 10px; font-size: 13px; font-weight: 500;
        transition: var(--transition); letter-spacing: 0.01em;
        cursor: pointer; white-space: nowrap;
        border: 1px solid transparent;
    }
    .nav-links a:hover {
        color: var(--ink);
        background: var(--surface-soft);
        border-color: var(--line);
    }
    .nav-links a.active {
        color: var(--ink);
        background: var(--surface-hover);
        border-color: var(--line-strong);
    }
    .nav-links a i { margin-right: 6px; font-size: 12px; opacity: 0.8; }

    /* ===== LAYOUT ===== */
    .app-wrapper {
        display: flex; flex-direction: column; gap: 16px;
        max-width: 1440px; margin: 16px auto 0 auto;
        width: 100%; flex: 1;
    }
    .main-grid {
        display: grid; grid-template-columns: 260px 1fr;
        gap: 20px; flex: 1;
    }

    /* ===== SIDEBAR ===== */
    .sidebar { display: flex; flex-direction: column; gap: 12px; }
    .sidebar-card {
        padding: 18px 20px;
        background: var(--surface);
        border-radius: var(--radius);
        border: 1px solid var(--line);
        box-shadow: var(--shadow-xs);
    }
    .sidebar-card h3 {
        font-size: 11px; text-transform: uppercase;
        letter-spacing: 0.14em; color: var(--ink-mute);
        margin-bottom: 14px; font-weight: 600;
    }
    .sidebar-card h3 i { margin-right: 6px; opacity: 0.6; }

    .sidebar-actions { display: flex; flex-direction: column; gap: 6px; }
    .sidebar-action-btn {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 12px; border-radius: var(--radius-sm);
        font-family: inherit; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: var(--transition); text-align: left;
        background: var(--surface); color: var(--ink);
        border: 1px solid var(--line);
    }
    .sidebar-action-btn:hover {
        background: var(--surface-soft);
        border-color: var(--line-strong);
        transform: translateX(2px);
    }
    .sidebar-action-btn .action-icon {
        width: 34px; height: 34px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 14px; flex-shrink: 0;
    }
    .sidebar-action-btn .action-label { display: flex; flex-direction: column; gap: 1px; }
    .sidebar-action-btn .action-label .title { font-size: 13px; font-weight: 700; color: var(--ink); }
    .sidebar-action-btn .action-label .subtitle { font-size: 11px; color: var(--ink-mute); font-weight: 500; }

    .sidebar-action-btn.open-account-btn .action-icon { background: linear-gradient(135deg, #e8836a, #d96a4a); }
    .sidebar-action-btn.transfer-btn .action-icon { background: linear-gradient(135deg, #7a93a8, #5a7a8c); }
    .sidebar-action-btn.loan-btn .action-icon { background: linear-gradient(135deg, #9cb98a, #7a9a6a); }

    /* Currency + date */
    .currency-table { width: 100%; font-size: 13px; border-collapse: collapse; }
    .currency-table td { padding: 8px 0; border-bottom: 1px solid var(--line); color: var(--ink-soft); }
    .currency-table tr:last-child td { border-bottom: none; }
    .currency-table td:first-child { font-weight: 500; color: var(--ink); }
    .currency-table td:last-child { text-align: right; color: var(--ink-mute); font-variant-numeric: tabular-nums; }
    .currency-table td:nth-child(2) { text-align: right; color: var(--ink-soft); font-variant-numeric: tabular-nums; }
    .currency-table .title-row td {
        font-weight: 700; font-size: 11px; color: var(--ink-mute);
        border-bottom: 1px solid var(--line-strong);
        padding-bottom: 10px; text-align: center; letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .date-display { text-align: center; padding: 4px 0; }
    .date-display .day {
        font-size: 17px; font-weight: 700; color: var(--ink);
        letter-spacing: -0.01em;
    }
    .date-display .date {
        font-size: 12px; color: var(--ink-mute);
        margin-top: 6px; display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .date-display .time-icon { font-size: 14px; color: var(--accent-2); }

    /* ===== CONTENT ===== */
    .content-area { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .content-card {
        padding: 28px;
        background: var(--surface);
        border-radius: var(--radius);
        border: 1px solid var(--line);
        box-shadow: var(--shadow-xs);
    }
    .content-card h2 {
        font-size: 19px; font-weight: 700; margin-bottom: 20px;
        color: var(--ink); letter-spacing: -0.01em;
        display: flex; align-items: center; gap: 10px;
    }
    .content-card h2 i { color: var(--ink-mute); font-size: 16px; }

    /* ===== STATS ===== */
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .stat-card {
        padding: 20px 22px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
        box-shadow: var(--shadow-xs);
        position: relative;
    }
    .stat-card:hover {
        border-color: var(--line-strong);
        box-shadow: var(--shadow-sm);
    }
    .stat-card .stat-value {
        font-size: 26px; font-weight: 700; color: var(--ink);
        letter-spacing: -0.02em; line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }
    .stat-card .stat-label {
        font-size: 11px; color: var(--ink-mute); margin-top: 6px;
        text-transform: uppercase; letter-spacing: 0.1em;
        font-weight: 500;
    }
    .stat-card .stat-icon {
        float: right; font-size: 22px; opacity: 0.35;
        position: absolute; top: 18px; right: 20px;
    }
    .stat-card.accent-blue .stat-icon { color: var(--accent-3); }
    .stat-card.accent-gold .stat-icon { color: var(--accent-2); }
    .stat-card.accent-green .stat-icon { color: var(--matcha); }
    .stat-card.accent-purple .stat-icon { color: #8b7aa8; }
    .stat-card.accent-peseta .stat-icon { color: var(--peseta); }

    /* ===== BANKS LIST ===== */
    .bank-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; margin-top: 14px; }
    .bank-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 14px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
    }
    .bank-item:hover {
        border-color: var(--line-strong);
        background: var(--surface-soft);
    }
    .bank-item .bank-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .bank-item .bank-icon {
        width: 32px; height: 32px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 14px; flex-shrink: 0;
        opacity: 0.95;
    }
    .bank-item .bank-name {
        font-weight: 600; font-size: 13px; color: var(--ink);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .bank-item .bank-actions { display: flex; gap: 2px; opacity: 0; transition: opacity 0.2s; }
    .bank-item:hover .bank-actions { opacity: 1; }
    .bank-item .bank-actions button {
        padding: 5px 7px; border: none; border-radius: 6px;
        font-size: 11px; cursor: pointer; transition: var(--transition);
        background: transparent; color: var(--ink-mute);
    }
    .bank-item .bank-actions button:hover { background: var(--surface-hover); color: var(--ink); }
    .bank-item .bank-actions .delete-bank-btn:hover { background: #fdf0ec; color: var(--accent); }

    /* ===== CHARTS ===== */
    .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
    .analytics-grid .full-width { grid-column: 1 / -1; }
    .chart-container {
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        padding: 20px;
        transition: var(--transition);
    }
    .chart-container:hover { border-color: var(--line-strong); }
    .chart-container h3 {
        font-size: 13px; font-weight: 600; color: var(--ink-soft);
        margin-bottom: 14px; letter-spacing: 0.01em;
    }
    .chart-container h3 i { color: var(--ink-mute); margin-right: 6px; }
    .chart-container canvas { max-height: 300px; max-width: 100%; }

    /* ===== BANK CARDS (детали) ===== */
    .bank-card {
        padding: 16px 18px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
        margin-bottom: 8px;
    }
    .bank-card:hover { border-color: var(--line-strong); background: var(--surface-soft); }
    .bank-card .bank-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 10px; gap: 10px;
    }
    .bank-card .bank-name { font-size: 15px; font-weight: 700; color: var(--ink); }
    .bank-card .bank-name i { margin-right: 8px; font-size: 14px; }
    .bank-card .bank-stats { display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 10px; }
    .bank-card .bank-stat { font-size: 12.5px; color: var(--ink-mute); }
    .bank-card .bank-stat strong { color: var(--ink); font-weight: 600; }
    .bank-card .progress-bar {
        width: 100%; height: 4px; background: var(--surface-hover);
        border-radius: 4px; overflow: hidden;
    }
    .bank-card .progress-bar .fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
    .bank-card .bank-accounts {
        margin-top: 12px; font-size: 12px; color: var(--ink-mute);
        display: flex; flex-wrap: wrap; gap: 6px;
    }
    .bank-card .bank-accounts span {
        display: inline-block; background: var(--surface-soft);
        padding: 3px 9px; border-radius: 6px;
        border: 1px solid var(--line);
        font-size: 11.5px; color: var(--ink-soft);
    }

    /* ===== FORMS ===== */
    .form-group { margin-bottom: 14px; }
    .form-group label {
        display: block; font-size: 12px; font-weight: 500;
        color: var(--ink-soft); margin-bottom: 6px; letter-spacing: 0.01em;
    }
    .form-control {
        width: 100%; padding: 10px 14px;
        background: var(--surface);
        border: 1px solid var(--line-strong);
        border-radius: 10px; color: var(--ink);
        font-size: 13.5px; font-family: inherit;
        transition: var(--transition);
    }
    .form-control:focus {
        outline: none;
        border-color: var(--accent-2);
        box-shadow: 0 0 0 3px rgba(185,138,60,0.12);
        background: #fff;
    }
    .form-control::placeholder { color: var(--ink-mute); }
    select.form-control option { background: #fff; color: var(--ink); }

    /* ===== BUTTONS ===== */
    .btn {
        padding: 10px 20px; border: 1px solid transparent;
        border-radius: 10px; font-size: 13px; font-weight: 600;
        font-family: inherit; cursor: pointer;
        transition: var(--transition);
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        background: var(--ink); color: #fff;
        box-shadow: var(--shadow-xs);
    }
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
        background: #2c333b;
    }
    .btn:active { transform: translateY(0); }

    .btn-secondary {
        background: var(--surface); color: var(--ink);
        border-color: var(--line-strong);
        box-shadow: none;
    }
    .btn-secondary:hover {
        background: var(--surface-hover);
        border-color: #cfcabc;
        box-shadow: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #8fae7c, #7a9a6a);
        color: #fff;
        box-shadow: 0 2px 8px rgba(122,154,106,0.25);
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #85a472, #6f8f5f);
        box-shadow: 0 4px 14px rgba(122,154,106,0.35);
    }

    .btn-warning {
        background: linear-gradient(135deg, #e0b96a, #cf9f4a);
        color: #fff;
        box-shadow: 0 2px 8px rgba(207,159,74,0.25);
    }
    .btn-warning:hover {
        background: linear-gradient(135deg, #d6ad5a, #c39540);
        box-shadow: 0 4px 14px rgba(207,159,74,0.35);
    }

    .btn-danger {
        background: linear-gradient(135deg, #e8836a, #d96a4a);
        color: #fff;
        box-shadow: 0 2px 8px rgba(217,106,74,0.25);
    }
    .btn-danger:hover {
        background: linear-gradient(135deg, #de775d, #cb5f40);
        box-shadow: 0 4px 14px rgba(217,106,74,0.35);
    }

    .btn-block { width: 100%; }

    /* ===== ACCOUNT CARDS ===== */
    .accounts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; margin-top: 14px; }
    .account-card {
        padding: 18px 20px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
        position: relative;
    }
    .account-card:hover {
        border-color: var(--line-strong);
        box-shadow: var(--shadow-sm);
    }
    .account-card .acc-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        margin-bottom: 10px; gap: 8px;
    }
    .account-card .acc-number {
        font-size: 12.5px; font-weight: 600; color: var(--ink);
        font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
        letter-spacing: 0.02em;
        background: var(--surface-soft);
        padding: 4px 10px; border-radius: 7px;
        border: 1px solid var(--line);
    }
    .account-card .acc-type {
        font-size: 10.5px; font-weight: 600; color: var(--ink-mute);
        text-transform: uppercase; letter-spacing: 0.1em;
        background: var(--surface-soft);
        padding: 4px 10px; border-radius: 7px;
        border: 1px solid var(--line);
    }
    .account-card .acc-name {
        font-size: 17px; font-weight: 700; color: var(--ink);
        margin: 4px 0 4px 0; letter-spacing: -0.01em;
    }
    .account-card .acc-bank {
        font-size: 12.5px; color: var(--ink-mute);
        margin-bottom: 14px;
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    }
    .account-card .acc-bank i { font-size: 11px; }
    .account-card .acc-balance-row {
        display: flex; justify-content: space-between; align-items: flex-end;
        padding-top: 12px; border-top: 1px solid var(--line);
    }
    .account-card .acc-balance {
        font-size: 22px; font-weight: 700; color: var(--ink);
        letter-spacing: -0.02em; line-height: 1.1;
        font-variant-numeric: tabular-nums;
    }
    .account-card .acc-balance.peseta { color: var(--peseta); }
    .account-card .acc-balance-label {
        font-size: 10.5px; color: var(--ink-mute);
        text-transform: uppercase; letter-spacing: 0.1em;
        margin-top: 4px; font-weight: 500;
    }
    .account-card .acc-actions {
        display: flex; gap: 2px; opacity: 0; transition: opacity 0.2s;
    }
    .account-card:hover .acc-actions { opacity: 1; }
    .account-card .acc-actions button {
        padding: 6px 9px; border: none; border-radius: 7px;
        font-size: 11px; cursor: pointer; transition: var(--transition);
        background: transparent; color: var(--ink-mute);
    }
    .account-card .acc-actions button:hover { background: var(--surface-hover); color: var(--ink); }
    .account-card .acc-actions .delete-btn:hover { background: #fdf0ec; color: var(--accent); }

    /* ===== CURRENCY BADGES ===== */
    .currency-badge {
        display: inline-block; font-size: 10.5px; font-weight: 700;
        padding: 3px 9px; border-radius: 100px; letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .currency-badge.chako {
        background: var(--accent-3-soft); color: var(--accent-3);
    }
    .currency-badge.peseta {
        background: var(--peseta-soft); color: var(--peseta);
    }

    /* ===== PROPERTY ===== */
    .property-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; margin-top: 14px; }
    .property-card {
        padding: 18px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
    }
    .property-card:hover { border-color: var(--line-strong); box-shadow: var(--shadow-sm); }
    .property-card h4 {
        font-size: 15px; font-weight: 700; color: var(--ink);
        margin-bottom: 6px; letter-spacing: -0.01em;
    }
    .property-card p { font-size: 12.5px; color: var(--ink-mute); margin: 3px 0; }
    .property-card .price {
        font-size: 18px; font-weight: 700; color: var(--ink);
        margin-top: 10px; font-variant-numeric: tabular-nums;
    }
    .property-card .price.peseta { color: var(--peseta); }
    .property-card .btn { margin-top: 12px; padding: 8px 16px; font-size: 12px; }

    /* ===== LOANS ===== */
    .loan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 14px; margin-top: 14px; }
    .loan-card {
        padding: 18px 20px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        transition: var(--transition);
    }
    .loan-card:hover { border-color: var(--line-strong); box-shadow: var(--shadow-sm); }
    .loan-card .loan-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        margin-bottom: 10px; gap: 8px;
    }
    .loan-card .loan-id {
        font-size: 11.5px; font-weight: 600; color: var(--ink-mute);
        font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
    }
    .loan-card .loan-status {
        padding: 3px 11px; border-radius: 100px;
        font-size: 10.5px; font-weight: 700;
        letter-spacing: 0.06em; text-transform: uppercase;
    }
    .loan-card .loan-status.active { background: var(--matcha-soft); color: var(--matcha); }
    .loan-card .loan-status.completed { background: var(--accent-3-soft); color: var(--accent-3); }
    .loan-card .loan-status.expired { background: #fdf0ec; color: var(--accent); }

    .loan-card .loan-owner {
        font-size: 12.5px; color: var(--ink-mute);
        margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
    }
    .loan-card .loan-owner i { font-size: 11px; }
    .loan-card .loan-amount {
        font-size: 22px; font-weight: 700; color: var(--ink);
        letter-spacing: -0.02em; font-variant-numeric: tabular-nums;
        margin-bottom: 10px;
    }
    .loan-card .loan-details {
        display: flex; gap: 16px; margin-bottom: 12px;
        flex-wrap: wrap; font-size: 12px; color: var(--ink-mute);
    }
    .loan-card .loan-details strong { color: var(--ink); font-weight: 600; }
    .loan-card .loan-progress {
        width: 100%; height: 4px; background: var(--surface-hover);
        border-radius: 4px; overflow: hidden; margin-bottom: 12px;
    }
    .loan-card .loan-progress .fill {
        height: 100%; border-radius: 4px;
        background: linear-gradient(90deg, var(--matcha), var(--accent-3));
        transition: width 0.6s ease;
    }
    .loan-card .loan-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .loan-card .loan-actions .btn { padding: 7px 14px; font-size: 12px; }

    /* ===== SALARY ===== */
    .salary-employer-card {
        padding: 16px 20px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        margin-bottom: 8px; transition: var(--transition);
    }
    .salary-employer-card:hover { border-color: var(--line-strong); background: var(--surface-soft); }
    .salary-employer-card .emp-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .salary-employer-card .emp-name { font-size: 15px; font-weight: 700; color: var(--ink); }
    .salary-employer-card .emp-account {
        font-size: 12px; color: var(--ink-mute);
        font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
        margin-top: 3px;
    }
    .salary-employer-card .emp-actions { display: flex; gap: 6px; }

    .salary-employee-card {
        padding: 14px 18px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        margin-bottom: 8px; transition: var(--transition);
    }
    .salary-employee-card:hover { border-color: var(--line-strong); background: var(--surface-soft); }
    .salary-employee-card .emp-info {
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }
    .salary-employee-card .emp-name { font-weight: 700; font-size: 14px; color: var(--ink); }
    .salary-employee-card .emp-detail {
        font-size: 12px; color: var(--ink-mute);
        margin-left: 12px; display: inline-block;
    }
    .salary-employee-card .emp-status {
        font-size: 10.5px; padding: 3px 11px;
        border-radius: 100px; letter-spacing: 0.06em; text-transform: uppercase;
        background: var(--matcha-soft); color: var(--matcha);
        font-weight: 700;
    }
    .salary-employee-card .emp-status.missing { background: #fdf0ec; color: var(--accent); }
    .salary-employee-card .emp-debt {
        font-size: 11.5px; font-weight: 600;
        color: var(--accent-2);
    }

    /* ===== TABS ===== */
    .tab-bar {
        display: inline-flex; gap: 2px; margin-bottom: 18px; flex-wrap: wrap;
        padding: 3px; background: var(--surface-soft);
        border-radius: 100px; border: 1px solid var(--line);
    }
    .tab-btn {
        padding: 7px 16px; border: none; border-radius: 100px;
        background: transparent; color: var(--ink-soft);
        font-family: inherit; font-size: 12.5px; font-weight: 600;
        cursor: pointer; transition: var(--transition);
        letter-spacing: 0.01em;
    }
    .tab-btn:hover { color: var(--ink); }
    .tab-btn.active {
        background: var(--surface); color: var(--ink);
        box-shadow: var(--shadow-xs);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn .25s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* ===== MODALS ===== */
    .modal {
        display: none; position: fixed; inset: 0;
        background: rgba(31,35,40,0.28);
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center; justify-content: center;
        padding: 20px;
        opacity: 0; transition: opacity 0.2s ease;
    }
    .modal.show { display: flex; opacity: 1; }
    .modal-content {
        background: var(--surface);
        border-radius: var(--radius-lg);
        border: 1px solid var(--line);
        padding: 28px 30px; max-width: 500px; width: 100%;
        box-shadow: var(--shadow-lg);
        animation: modalIn 0.3s cubic-bezier(.2,.7,.2,1);
        max-height: 90vh; overflow-y: auto;
        transform: translateY(0);
    }
    @keyframes modalIn {
        from { transform: translateY(16px) scale(0.98); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    .modal-close {
        float: right; font-size: 20px; color: var(--ink-mute);
        cursor: pointer; transition: var(--transition);
        background: none; border: none;
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
    }
    .modal-close:hover { color: var(--ink); background: var(--surface-soft); }
    .modal-content h2 {
        font-size: 17px; font-weight: 700; color: var(--ink);
        margin-bottom: 20px; letter-spacing: -0.01em;
        display: flex; align-items: center; gap: 10px;
    }
    .modal-content h2 i { color: var(--ink-mute); font-size: 15px; }

    /* ===== ICON / COLOR PICKERS ===== */
    .icon-picker { display: grid; grid-template-columns: repeat(8, 1fr); gap: 6px; margin-top: 4px; }
    .icon-picker .icon-option {
        padding: 8px; border: 1px solid transparent; border-radius: 8px;
        text-align: center; cursor: pointer; transition: var(--transition);
        background: var(--surface-soft); font-size: 15px; color: var(--ink-mute);
    }
    .icon-picker .icon-option:hover { background: var(--surface-hover); color: var(--ink); }
    .icon-picker .icon-option.active {
        border-color: var(--ink); background: #fff; color: var(--ink);
    }
    .color-picker-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 6px; margin-top: 4px; }
    .color-picker-grid .color-option {
        width: 100%; aspect-ratio: 1; border-radius: 8px;
        border: 2px solid transparent; cursor: pointer;
        transition: var(--transition);
    }
    .color-picker-grid .color-option:hover { transform: scale(1.08); }
    .color-picker-grid .color-option.active {
        border-color: var(--ink);
        box-shadow: 0 0 0 2px #fff, 0 0 0 3px var(--ink);
    }

    /* ===== HISTORY TABLE ===== */
    .history-table { width: 100%; font-size: 12.5px; border-collapse: collapse; margin-top: 12px; }
    .history-table th {
        text-align: left; padding: 10px;
        border-bottom: 1px solid var(--line-strong);
        font-weight: 600; color: var(--ink-mute);
        font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.1em;
    }
    .history-table td {
        padding: 10px; border-bottom: 1px solid var(--line);
        color: var(--ink-soft); font-variant-numeric: tabular-nums;
    }
    .history-table td strong { color: var(--ink); font-weight: 600; }
    .history-table tr:hover td { background: var(--surface-soft); }
    .tx-alt { font-size: 11px; color: var(--ink-mute); margin-top: 2px; font-weight: 500; }

    /* ===== MISC ===== */
    .footer {
        text-align: center; padding: 24px 16px;
        font-size: 11px; color: var(--ink-mute);
        letter-spacing: 0.1em; text-transform: uppercase;
    }
    .text-muted { color: var(--ink-mute); }
    .mt-8 { margin-top: 8px; } .mt-16 { margin-top: 16px; }
    .mb-8 { margin-bottom: 8px; } .mb-16 { margin-bottom: 16px; }
    .flex { display: flex; } .gap-8 { gap: 8px; } .gap-16 { gap: 16px; }
    .currency-label {
        font-size: 12px; font-weight: 500;
        color: var(--ink-mute); margin-left: 4px;
    }
    .currency-label.peseta { color: var(--peseta); }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .main-grid { grid-template-columns: 1fr; }
        .sidebar { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sidebar-actions { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; }
        .sidebar-action-btn { flex: 1; min-width: 180px; }
        .analytics-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .navbar { padding: 10px 12px; flex-direction: column; align-items: stretch; }
        .logo { margin-right: 0; justify-content: center; }
        .nav-links { justify-content: center; }
        .nav-links a { font-size: 12px; padding: 6px 10px; }
        .accounts-grid { grid-template-columns: 1fr; }
        .loan-grid { grid-template-columns: 1fr; }
        .bank-list { grid-template-columns: 1fr 1fr; }
        .content-card { padding: 20px; }
        .icon-picker { grid-template-columns: repeat(5, 1fr); }
        .color-picker-grid { grid-template-columns: repeat(5, 1fr); }
    }
    @media (max-width: 640px) {
        .sidebar { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr; }
        .property-grid { grid-template-columns: 1fr; }
        .content-card { padding: 16px; }
        .modal-content { padding: 20px; }
        .nav-links a { font-size: 11px; padding: 5px 8px; }
        .nav-links a i { margin-right: 4px; font-size: 10px; }
        .logo img { height: 28px; }
        .bank-list { grid-template-columns: 1fr; }
        .icon-picker { grid-template-columns: repeat(4, 1fr); }
        .color-picker-grid { grid-template-columns: repeat(4, 1fr); }
        .sidebar-actions { flex-direction: column; }
        .tab-bar { width: 100%; justify-content: center; }
    }
</style>
</head>
<body>

<div class="navbar-wrapper">
    <nav class="navbar">
        <a href="#" class="logo">
            <img src="./images/logo.png" alt="ҮРДЖАН ҮРДАТ" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span class="logo-text">ҮРДЖАН ҮРДАТ</span>
        </a>
        <ul class="nav-links">
            <li><a class="active" data-page="dashboard"><i class="fas fa-home"></i> Тәтә</a></li>
            <li><a data-page="accounts"><i class="fas fa-list"></i> Жәколәв</a></li>
            <li><a data-page="bonds"><i class="fas fa-chart-pie"></i> Облигации</a></li>
            <li><a data-page="property"><i class="fas fa-building"></i> Маеток</a></li>
            <li><a data-page="salary"><i class="fas fa-wallet"></i> Сөләр</a></li>
            <li><a data-page="transactions"><i class="fas fa-exchange-alt"></i> Операции</a></li>
            <li><a data-page="analytics"><i class="fas fa-chart-pie"></i> Аналитика</a></li>
            <li><a data-page="banks"><i class="fas fa-university"></i> Пәнкәв</a></li>
        </ul>
    </nav>
</div>

<div class="app-wrapper">
    <div class="main-grid">
        <aside class="sidebar">
            <div class="sidebar-card">
                <h3><i class="fas fa-bolt"></i> Япартат операчив</h3>
                <div class="sidebar-actions">
                    <button class="sidebar-action-btn open-account-btn" onclick="openModal('openAccountModal')">
                        <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                        <div class="action-label">
                            <span class="title">Рәәстрачә</span>
                            <span class="subtitle">Открыть счёт</span>
                        </div>
                    </button>
                    <button class="sidebar-action-btn transfer-btn" onclick="openModal('transferModal')">
                        <div class="action-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="action-label">
                            <span class="title">Ұқәж</span>
                            <span class="subtitle">Перевод средств</span>
                        </div>
                    </button>
                    <button class="sidebar-action-btn loan-btn" onclick="openModal('loanModal')">
                        <div class="action-icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="action-label">
                            <span class="title">Навьера</span>
                            <span class="subtitle">Выдать кредит</span>
                        </div>
                    </button>
                </div>
            </div>
            <div class="sidebar-card">
                <table class="currency-table">
                    <tr class="title-row"><td colspan="3"><i class="fas fa-coins"></i> ЧҰЖДЕНСКИ ҮРДМӘ</td></tr>
                    <tr><td>$ USD</td><td>0.69</td><td>0.73</td></tr>
                    <tr><td>€ EUR</td><td>0.64</td><td>0.68</td></tr>
                    <tr><td>₧ PES</td><td>10</td><td>10</td></tr>
                </table>
            </div>
            <div class="sidebar-card">
                <div class="date-display">
                    <div class="day" id="dayOfWeek">Рүджан</div>
                    <div class="date" id="dateDisplay"></div>
                </div>
            </div>
        </aside>

        <main class="content-area">

            <div id="page-dashboard" class="tab-content active">
                <div class="content-card">
                    <h2><i class="fas fa-home"></i> Нёңгә, касұтя</h2>
                    <div class="stats-grid">
                        <div class="stat-card accent-blue">
                            <span class="stat-icon"><i class="fas fa-users"></i></span>
                            <div class="stat-value" id="accountCount">—</div>
                            <div class="stat-label">жәколәв рәәстрачәне</div>
                        </div>
                        <div class="stat-card accent-gold">
                            <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                            <div>
                                <div class="stat-value" id="totalBalance">— <span class="currency-label">чякө</span></div>
                                <div class="stat-label">итог в чякө (все валюты)</div>
                            </div>
                        </div>
                        <div class="stat-card accent-peseta">
                            <span class="stat-icon"><i class="fas fa-coins"></i></span>
                            <div>
                                <div class="stat-value" id="pesetaBalance" style="color:#b45309;">— <span class="currency-label peseta">₧</span></div>
                                <div class="stat-label">сумма в песетах</div>
                            </div>
                        </div>
                        <div class="stat-card accent-peseta">
                            <span class="stat-icon"><i class="fas fa-exchange-alt"></i></span>
                            <div class="stat-value" id="pesetaCount">—</div>
                            <div class="stat-label">жәколәв в песетах</div>
                        </div>
                    </div>
                    <div style="margin-top:12px;font-size:12px;color:rgba(26,26,46,0.45);">
                        Курс: <strong>1 чякө = 10 ₧ песет</strong>. Все суммы в аналитике приводятся к чякө.
                    </div>
                </div>
                <div class="content-card" style="margin-top:16px;">
                    <h2><i class="fas fa-crown"></i> Златият запас</h2>
                    <div class="stat-card" style="background:rgba(245,166,35,0.06);border-color:rgba(245,166,35,0.12);">
                        <div>
                            <div class="stat-value" id="goldReserve" style="color:#d4a017;">— <span class="currency-label" style="color:#d4a017;">чякө</span></div>
                            <div class="stat-label">златият шаңдьеңлөмана</div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="page-accounts" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-list"></i> Жәколәв</h2>
                    <div class="flex gap-8 mb-16">
                        <input type="text" class="form-control" id="searchAccount" placeholder="Поиск..." style="max-width:320px;">
                        <button class="btn btn-secondary" onclick="searchAccounts()"><i class="fas fa-search"></i></button>
                        <button class="btn btn-secondary" onclick="loadAccounts()"><i class="fas fa-sync"></i></button>
                    </div>
                    <div id="accountsList" class="accounts-grid"></div>
                </div>
            </div>

            <div id="page-bonds" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-chart-pie"></i> Облигации</h2>
                    <p class="text-muted mb-16">Облигация — <strong>5 чякө</strong>. Покупка/погашение — с конвертацией.</p>
                    <div class="tab-bar">
                        <button class="tab-btn active" data-bonds-tab="create">Создать заём</button>
                        <button class="tab-btn" data-bonds-tab="active">Активные</button>
                        <button class="tab-btn" data-bonds-tab="my">Мои займы</button>
                    </div>
                    <div id="bonds-tab-create" class="tab-content active">
                        <form id="createLoanForm">
                            <div class="form-group">
                                <label>Номер счёта (владелец)</label>
                                <input type="text" class="form-control" id="loanOwnerAccount" required>
                            </div>
                            <div class="form-group">
                                <label>Сумма займа (в валюте счёта)</label>
                                <input type="number" class="form-control" id="loanTotalAmount" required min="1" step="0.01">
                            </div>
                            <button type="submit" class="btn btn-success btn-block">Создать заём</button>
                        </form>
                        <div id="createLoanResult" class="mt-16"></div>
                    </div>
                    <div id="bonds-tab-active" class="tab-content">
                        <div class="flex gap-8 mb-16">
                            <button class="btn btn-secondary" onclick="loadActiveLoans()"><i class="fas fa-sync"></i> Обновить</button>
                        </div>
                        <div id="activeLoansList" class="loan-grid"></div>
                    </div>
                    <div id="bonds-tab-my" class="tab-content">
                        <div class="flex gap-8 mb-16">
                            <div class="form-group" style="flex:1;max-width:300px;margin:0;">
                                <input type="text" class="form-control" id="myLoansAccount" placeholder="Номер счёта">
                            </div>
                            <button class="btn btn-secondary" onclick="loadMyLoans()"><i class="fas fa-search"></i> Показать</button>
                        </div>
                        <div id="myLoansList" class="loan-grid"></div>
                    </div>
                </div>
            </div>

            <div id="page-property" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-building"></i> Кашұм маетокёмә</h2>
                    <div class="tab-bar">
                        <button class="tab-btn active" data-prop-tab="list">Объекты</button>
                        <button class="tab-btn" data-prop-tab="add">Добавить</button>
                    </div>
                    <div id="prop-tab-list" class="tab-content active">
                        <div id="propertyList" class="property-grid"></div>
                    </div>
                    <div id="prop-tab-add" class="tab-content">
                        <form id="addPropertyForm">
                            <div class="form-group"><label>Күгә</label><input type="text" class="form-control" id="propName" required></div>
                            <div class="form-group"><label>ID</label><input type="text" class="form-control" id="propId" required></div>
                            <div class="form-group"><label>Чұсо</label><input type="text" class="form-control" id="propAddress" required></div>
                            <div class="form-group"><label>Плочади (м2)</label><input type="text" class="form-control" id="propArea" required></div>
                            <div class="form-group"><label>Бәзирк</label><input type="text" class="form-control" id="propProvince" required></div>
                            <div class="form-group"><label>Зұт</label><input type="text" class="form-control" id="propYear" required></div>
                            <div class="form-group"><label>Чұл</label><input type="text" class="form-control" id="propPrice" required></div>
                            <div class="form-group">
                                <label>Валюта</label>
                                <select class="form-control" id="propCurrency">
                                    <option value="чякө">чякө</option>
                                    <option value="песета">песета (₧)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-block">Добавить объект</button>
                        </form>
                        <div id="addPropResult" class="mt-16"></div>
                    </div>
                </div>
            </div>

            <div id="page-salary" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-wallet"></i> Сөләрат сәрвис</h2>
                    <div class="tab-bar">
                        <button class="tab-btn active" data-salary-tab="employers">Работодатели</button>
                        <button class="tab-btn" data-salary-tab="employees">Сотрудники</button>
                        <button class="tab-btn" data-salary-tab="history">История</button>
                    </div>
                    <div id="salary-tab-employers" class="tab-content active">
                        <div class="flex gap-8 mb-16">
                            <button class="btn btn-success" onclick="openEmployerModal()"><i class="fas fa-plus"></i> Добавить</button>
                            <button class="btn btn-secondary" onclick="loadEmployers()"><i class="fas fa-sync"></i></button>
                        </div>
                        <div id="employersList"></div>
                    </div>
                    <div id="salary-tab-employees" class="tab-content">
                        <div class="flex gap-8 mb-16">
                            <select class="form-control" id="employeeEmployerFilter" style="max-width:300px;" onchange="loadEmployees()">
                                <option value="">Все работодатели</option>
                            </select>
                            <button class="btn btn-success" onclick="openEmployeeModal()"><i class="fas fa-user-plus"></i> Сотрудник</button>
                            <button class="btn btn-secondary" onclick="loadEmployees()"><i class="fas fa-sync"></i></button>
                        </div>
                        <div id="employeesList"></div>
                    </div>
                    <div id="salary-tab-history" class="tab-content">
                        <div class="flex gap-8 mb-16">
                            <select class="form-control" id="historyEmployeeFilter" style="max-width:300px;" onchange="loadSalaryHistory()">
                                <option value="">Выберите сотрудника</option>
                            </select>
                            <button class="btn btn-secondary" onclick="loadSalaryHistory()"><i class="fas fa-sync"></i></button>
                        </div>
                        <div id="historyContent"><p class="text-muted">Выберите сотрудника</p></div>
                    </div>
                </div>
            </div>

            <div id="page-transactions" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-exchange-alt"></i> Операции</h2>
                    <p class="text-muted mb-16">Единый журнал транзакций: переводы, зарплаты, кредиты, покупка объектов. Суммы указаны в валюте отправителя, автоматически пересчитаны во вторую валюту (курс 1 чякө = 10 ₧).</p>
                    <div class="flex gap-8 mb-16" style="flex-wrap:wrap;">
                        <input type="text" class="form-control" id="txAccount" placeholder="Номер счёта (отправитель или получатель)" style="max-width:320px;" onkeydown="if(event.key==='Enter'){event.preventDefault();loadTransactions();}">
                        <select class="form-control" id="txLimit" style="max-width:140px;">
                            <option value="50">50 записей</option>
                            <option value="100" selected>100 записей</option>
                            <option value="200">200 записей</option>
                            <option value="500">500 записей</option>
                        </select>
                        <button class="btn btn-secondary" onclick="loadTransactions()"><i class="fas fa-search"></i> Показать</button>
                        <button class="btn btn-secondary" onclick="document.getElementById('txAccount').value='';loadTransactions()"><i class="fas fa-sync"></i> Все операции</button>
                    </div>
                    <div id="transactionsInfo" class="text-muted" style="font-size:12px;margin-bottom:8px;"></div>
                    <div id="transactionsList"><p class="text-muted">Загрузка…</p></div>
                </div>
            </div>

            <div id="page-analytics" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-chart-pie"></i> Аналитика</h2>
                    <p class="text-muted mb-16">Все суммы приводятся к чякө по курсу 1 чякө = 10 ₧.</p>
                    <div class="stats-grid" style="margin-bottom:16px;">
                        <div class="stat-card accent-blue">
                            <span class="stat-icon"><i class="fas fa-university"></i></span>
                            <div class="stat-value" id="analyticsBankCount">—</div>
                            <div class="stat-label">банков</div>
                        </div>
                        <div class="stat-card accent-purple">
                            <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                            <div>
                                <div class="stat-value" id="analyticsTotalBalance">— <span class="currency-label">чякө</span></div>
                                <div class="stat-label">общий баланс (в чякө)</div>
                            </div>
                        </div>
                        <div class="stat-card accent-green">
                            <span class="stat-icon"><i class="fas fa-users"></i></span>
                            <div class="stat-value" id="analyticsTotalAccounts">—</div>
                            <div class="stat-label">счетов</div>
                        </div>
                        <div class="stat-card accent-gold">
                            <span class="stat-icon"><i class="fas fa-chart-line"></i></span>
                            <div class="stat-value" id="analyticsAvgBalance">— <span class="currency-label">чякө</span></div>
                            <div class="stat-label">средний баланс</div>
                        </div>
                    </div>
                    <div class="analytics-grid">
                        <div class="chart-container full-width">
                            <h3><i class="fas fa-chart-bar"></i> Распределение средств по банкам (в чякө)</h3>
                            <canvas id="balanceChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3><i class="fas fa-chart-pie"></i> Доля каждого банка</h3>
                            <canvas id="pieChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3><i class="fas fa-chart-line"></i> Количество счетов</h3>
                            <canvas id="countChart"></canvas>
                        </div>
                    </div>
                    <div class="mt-16">
                        <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;color:rgba(26,26,46,0.6);">
                            <i class="fas fa-list"></i> Детальная информация по банкам
                        </h3>
                        <div id="bankDetails"></div>
                    </div>
                </div>
            </div>

            <div id="page-banks" class="tab-content">
                <div class="content-card">
                    <h2><i class="fas fa-university"></i> Управление банками</h2>
                    <div class="flex gap-8 mb-16">
                        <button class="btn btn-success" onclick="openAddBankModal()"><i class="fas fa-plus"></i> Добавить</button>
                        <button class="btn btn-secondary" onclick="loadBanks()"><i class="fas fa-sync"></i></button>
                    </div>
                    <div class="bank-list" id="bankList"></div>
                </div>
            </div>
        </main>
    </div>
    <div class="footer">&copy; ҮРДЖАН ҮРДАТ КОНТООРЁМӘ 2569</div>
</div>

<!-- ===== МОДАЛЬНЫЕ ОКНА ===== -->
<div class="modal" id="openAccountModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('openAccountModal')">&times;</button>
        <h2><i class="fas fa-user-plus"></i> Открыть счёт</h2>
        <form id="openAccountForm">
            <div class="form-group"><label>Имя владельца</label><input type="text" class="form-control" id="ownerName" required></div>
            <div class="form-group"><label>Банк</label><select class="form-control" id="bankName"></select></div>
            <div class="form-group"><label>Баланс</label><input type="number" class="form-control" id="initialBalance" step="0.01" required></div>
            <div class="form-group"><label>Тип счёта</label>
                <select class="form-control" id="accountType">
                    <option value="Сұкромны">Сұкромны</option>
                    <option value="Йұридически">Йұридически</option>
                    <option value="Лагбөнат">Лагбөнат</option>
                </select>
            </div>
            <div class="form-group"><label>Валюта счёта</label>
                <select class="form-control" id="accountCurrency">
                    <option value="чякө">чякө</option>
                    <option value="песета">песета (₧)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-block">Создать</button>
        </form>
        <div id="openAccountResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="transferModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('transferModal')">&times;</button>
        <h2><i class="fas fa-exchange-alt"></i> Перевод средств</h2>
        <form id="transferForm">
            <div class="form-group"><label>Счёт отправителя</label><input type="text" class="form-control" id="senderAccount" required></div>
            <div class="form-group"><label>Счёт получателя</label><input type="text" class="form-control" id="recipientAccount" required></div>
            <div class="form-group"><label>Сумма</label>
                <input type="number" class="form-control" id="transferAmount" step="0.01" required>
                <small style="color:rgba(26,26,46,0.45);font-size:12px;">В валюте счёта отправителя. Для песет минимум 10 ₧.</small>
            </div>
            <button type="submit" class="btn btn-block">Перевести</button>
        </form>
        <div id="transferResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="loanModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('loanModal')">&times;</button>
        <h2><i class="fas fa-hand-holding-usd"></i> Выдать кредит</h2>
        <form id="loanForm">
            <div class="form-group"><label>Номер счёта</label><input type="text" class="form-control" id="loanAccount" required></div>
            <div class="form-group"><label>Сумма</label><input type="number" class="form-control" id="loanAmount" step="0.01" required></div>
            <button type="submit" class="btn btn-success btn-block">Выдать</button>
        </form>
        <div id="loanResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="editAccountModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
        <h2><i class="fas fa-pen"></i> Редактировать счёт</h2>
        <form id="editAccountForm">
            <input type="hidden" id="editAccountNumber">
            <div class="form-group"><label>Владелец</label><input type="text" class="form-control" id="editOwnerName" required></div>
            <div class="form-group"><label>Банк</label><select class="form-control" id="editBankName"></select></div>
            <div class="form-group"><label>Баланс</label><input type="number" class="form-control" id="editBalance" step="0.01" required></div>
            <div class="form-group"><label>Тип</label>
                <select class="form-control" id="editAccountType">
                    <option value="Сұкромны">Сұкромны</option>
                    <option value="Йұридически">Йұридически</option>
                    <option value="Лагбөнат">Лагбөнат</option>
                </select>
            </div>
            <div class="form-group"><label>Валюта</label>
                <select class="form-control" id="editAccountCurrency">
                    <option value="чякө">чякө</option>
                    <option value="песета">песета (₧)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success btn-block">Сохранить</button>
        </form>
        <div id="editResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="addBankModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeAddBankModal()">&times;</button>
        <h2><i class="fas fa-plus-circle"></i> Добавить банк</h2>
        <form id="addBankForm">
            <div class="form-group"><label>Название</label><input type="text" class="form-control" id="bankNameInput" required></div>
            <div class="form-group"><label>Иконка</label>
                <div class="icon-picker" id="iconPicker">
                    <div class="icon-option active" data-icon="fa-university"><i class="fas fa-university"></i></div>
                    <div class="icon-option" data-icon="fa-building"><i class="fas fa-building"></i></div>
                    <div class="icon-option" data-icon="fa-chart-line"><i class="fas fa-chart-line"></i></div>
                    <div class="icon-option" data-icon="fa-handshake"><i class="fas fa-handshake"></i></div>
                    <div class="icon-option" data-icon="fa-heart"><i class="fas fa-heart"></i></div>
                    <div class="icon-option" data-icon="fa-gem"><i class="fas fa-gem"></i></div>
                    <div class="icon-option" data-icon="fa-flag"><i class="fas fa-flag"></i></div>
                    <div class="icon-option" data-icon="fa-star"><i class="fas fa-star"></i></div>
                </div>
                <input type="hidden" id="selectedIcon" value="fa-university">
            </div>
            <div class="form-group"><label>Цвет</label>
                <div class="color-picker-grid" id="colorPicker">
                    <div class="color-option active" style="background:#4facfe;" data-color="#4facfe"></div>
                    <div class="color-option" style="background:#43e97b;" data-color="#43e97b"></div>
                    <div class="color-option" style="background:#f5a623;" data-color="#f5a623"></div>
                    <div class="color-option" style="background:#a855f7;" data-color="#a855f7"></div>
                    <div class="color-option" style="background:#f5576c;" data-color="#f5576c"></div>
                    <div class="color-option" style="background:#14b8a6;" data-color="#14b8a6"></div>
                    <div class="color-option" style="background:#f97316;" data-color="#f97316"></div>
                    <div class="color-option" style="background:#8b5cf6;" data-color="#8b5cf6"></div>
                </div>
                <input type="hidden" id="selectedColor" value="#4facfe">
            </div>
            <button type="submit" class="btn btn-success btn-block">Добавить</button>
        </form>
        <div id="addBankResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="editBankModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEditBankModal()">&times;</button>
        <h2><i class="fas fa-edit"></i> Редактировать банк</h2>
        <form id="editBankForm">
            <input type="hidden" id="editBankId">
            <div class="form-group"><label>Название</label><input type="text" class="form-control" id="editBankNameInput" required></div>
            <div class="form-group"><label>Иконка</label>
                <div class="icon-picker" id="editIconPicker">
                    <div class="icon-option" data-icon="fa-university"><i class="fas fa-university"></i></div>
                    <div class="icon-option" data-icon="fa-building"><i class="fas fa-building"></i></div>
                    <div class="icon-option" data-icon="fa-chart-line"><i class="fas fa-chart-line"></i></div>
                    <div class="icon-option" data-icon="fa-handshake"><i class="fas fa-handshake"></i></div>
                    <div class="icon-option" data-icon="fa-heart"><i class="fas fa-heart"></i></div>
                    <div class="icon-option" data-icon="fa-gem"><i class="fas fa-gem"></i></div>
                    <div class="icon-option" data-icon="fa-flag"><i class="fas fa-flag"></i></div>
                    <div class="icon-option" data-icon="fa-star"><i class="fas fa-star"></i></div>
                </div>
                <input type="hidden" id="editSelectedIcon">
            </div>
            <div class="form-group"><label>Цвет</label>
                <div class="color-picker-grid" id="editColorPicker">
                    <div class="color-option" style="background:#4facfe;" data-color="#4facfe"></div>
                    <div class="color-option" style="background:#43e97b;" data-color="#43e97b"></div>
                    <div class="color-option" style="background:#f5a623;" data-color="#f5a623"></div>
                    <div class="color-option" style="background:#a855f7;" data-color="#a855f7"></div>
                    <div class="color-option" style="background:#f5576c;" data-color="#f5576c"></div>
                    <div class="color-option" style="background:#14b8a6;" data-color="#14b8a6"></div>
                    <div class="color-option" style="background:#f97316;" data-color="#f97316"></div>
                    <div class="color-option" style="background:#8b5cf6;" data-color="#8b5cf6"></div>
                </div>
                <input type="hidden" id="editSelectedColor">
            </div>
            <button type="submit" class="btn btn-success btn-block">Сохранить</button>
        </form>
        <div id="editBankResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="employerModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEmployerModal()">&times;</button>
        <h2><i class="fas fa-building"></i> Работодатель</h2>
        <form id="employerForm">
            <div class="form-group"><label>Название</label><input type="text" class="form-control" id="employerName" required></div>
            <div class="form-group"><label>Номер счёта</label><input type="text" class="form-control" id="employerAccount" required></div>
            <button type="submit" class="btn btn-success btn-block">Создать</button>
        </form>
        <div id="employerResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="employeeModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEmployeeModal()">&times;</button>
        <h2><i class="fas fa-user"></i> Сотрудник</h2>
        <form id="employeeForm">
            <div class="form-group"><label>Работодатель</label><select class="form-control" id="employeeEmployer" required></select></div>
            <div class="form-group"><label>Имя</label><input type="text" class="form-control" id="employeeName" required></div>
            <div class="form-group"><label>Номер счёта</label><input type="text" class="form-control" id="employeeAccount" required></div>
            <div class="form-group"><label>Оклад (в месяц)</label>
                <input type="number" class="form-control" id="employeeSalary" step="0.01" required>
            </div>
            <div class="form-group"><label>Валюта оклада</label>
                <select class="form-control" id="employeeSalaryCurrency">
                    <option value="чякө">чякө</option>
                    <option value="песета">песета (₧)</option>
                </select>
                <small style="color:rgba(26,26,46,0.4);font-size:12px;">В какой валюте указан оклад. При выплате конвертируется в валюту счёта сотрудника.</small>
            </div>
            <div class="form-group"><label>Дата приёма</label><input type="date" class="form-control" id="employeeHireDate" required></div>
            <button type="submit" class="btn btn-success btn-block">Добавить</button>
        </form>
        <div id="employeeResult" class="mt-16"></div>
    </div>
</div>

<!-- >>> НОВОЕ МОДАЛЬНОЕ ОКНО: редактирование сотрудника (включая валюту оклада) -->
<div class="modal" id="editEmployeeModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEditEmployeeModal()">&times;</button>
        <h2><i class="fas fa-pen"></i> Редактировать сотрудника</h2>
        <form id="editEmployeeForm">
            <input type="hidden" id="editEmployeeId">
            <div class="form-group"><label>Имя</label>
                <input type="text" class="form-control" id="editEmployeeName" required>
            </div>
            <div class="form-group"><label>Номер счёта</label>
                <input type="text" class="form-control" id="editEmployeeAccount" required>
            </div>
            <div class="form-group"><label>Оклад (в месяц)</label>
                <input type="number" class="form-control" id="editEmployeeSalary" step="0.01" required>
            </div>
            <div class="form-group"><label>Валюта оклада</label>
                <select class="form-control" id="editEmployeeSalaryCurrency">
                    <option value="чякө">чякө</option>
                    <option value="песета">песета (₧)</option>
                </select>
                <small style="color:rgba(26,26,46,0.4);font-size:12px;">При выплате оклад конвертируется в валюту счёта сотрудника.</small>
            </div>
            <div class="form-group"><label>Дата приёма</label>
                <input type="date" class="form-control" id="editEmployeeHireDate" required>
            </div>
            <button type="submit" class="btn btn-success btn-block">Сохранить</button>
        </form>
        <div id="editEmployeeResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="paySalaryModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closePaySalaryModal()">&times;</button>
        <h2><i class="fas fa-hand-holding-usd"></i> Выплата зарплаты</h2>
        <div id="paySalaryInfo" class="mb-16"></div>
        <div id="payDebtInfo" class="mb-16"></div>
        <form id="paySalaryForm">
            <input type="hidden" id="payEmployeeId">
            <div class="form-group"><label>Количество месяцев</label>
                <select class="form-control" id="payMonths">
                    <option value="1">1 месяц</option>
                    <option value="2">2 месяца</option>
                    <option value="3">3 месяца</option>
                    <option value="6">6 месяцев</option>
                    <option value="12">12 месяцев</option>
                </select>
            </div>
            <div id="payMissingInfo" class="mb-16"></div>
            <button type="submit" class="btn btn-success btn-block">Выплатить</button>
        </form>
        <div id="payResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="purchaseModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closePurchaseModal()">&times;</button>
        <h2><i class="fas fa-shopping-cart"></i> Купить объект</h2>
        <form id="purchaseForm">
            <div class="form-group"><label>Ваш номер счёта</label><input type="text" class="form-control" id="purchaseAccount" required></div>
            <input type="hidden" id="purchasePropertyName">
            <button type="submit" class="btn btn-success btn-block">Купить</button>
        </form>
        <div id="purchaseResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="deleteAccountModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        <h2><i class="fas fa-trash"></i> Удалить счёт</h2>
        <p class="mb-16">Удалить счёт <strong id="deleteAccountDisplay"></strong>?</p>
        <form id="deleteAccountForm">
            <input type="hidden" id="deleteAccountNumber">
            <div class="flex gap-8">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Отмена</button>
                <button type="submit" class="btn btn-danger">Удалить</button>
            </div>
        </form>
        <div id="deleteResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="buyBondModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeBuyBondModal()">&times;</button>
        <h2><i class="fas fa-shopping-cart"></i> Купить облигации</h2>
        <div id="buyBondInfo" class="mb-16"></div>
        <form id="buyBondForm">
            <input type="hidden" id="buyLoanId">
            <div class="form-group"><label>Ваш счёт</label><input type="text" class="form-control" id="buyerAccount" required></div>
            <div class="form-group"><label>Количество</label>
                <input type="number" class="form-control" id="buyQuantity" value="1" min="1" required>
                <small style="color:rgba(26,26,46,0.4);font-size:12px;">Цена облигации: 5 чякө (с конверсией)</small>
            </div>
            <button type="submit" class="btn btn-success btn-block">Купить</button>
        </form>
        <div id="buyBondResult" class="mt-16"></div>
    </div>
</div>

<div class="modal" id="repayBondModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeRepayBondModal()">&times;</button>
        <h2><i class="fas fa-hand-holding-usd"></i> Погасить облигации</h2>
        <div id="repayBondInfo" class="mb-16"></div>
        <form id="repayBondForm">
            <input type="hidden" id="repayLoanId">
            <div class="form-group"><label>Ваш счёт (владелец)</label><input type="text" class="form-control" id="repayOwnerAccount" required></div>
            <div class="form-group"><label>Количество</label>
                <select class="form-control" id="repayQuantity">
                    <option value="all">Все оставшиеся</option>
                </select>
            </div>
            <button type="submit" class="btn btn-warning btn-block">Погасить</button>
        </form>
        <div id="repayBondResult" class="mt-16"></div>
    </div>
</div>

<script>
// ===== УТИЛИТЫ =====
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function cSym(cur) { return cur === 'песета' ? '₧' : 'чякө'; }
function fmtMoney(n, cur) { return Number(n).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' ' + cSym(cur); }
function cBadge(cur) {
    return cur === 'песета'
        ? '<span class="currency-badge peseta">песета</span>'
        : '<span class="currency-badge chako">чякө</span>';
}
async function apiCall(action, method = 'GET', data = null) {
    const url = '?action=' + action;
    const opts = { method };
    if (data) opts.body = data;
    const res = await fetch(url, opts);
    return res.json();
}

// ===== DATE =====
(function () {
    const d = new Date();
    const days = ["Көлан","Пүтшан","Вөлдан","Рүджаң","Мирдәан","Бохтаң","Чабан"];
    const months = ["коложогё","лүтәйё","чуәтарё","трәвәнё","косарё","жатварё","липәнё","сәрпәнё","версәнё","листопадё","стұдәнё","сьнежанё"];
    document.getElementById('dayOfWeek').textContent = days[d.getDay()];
    const icon = (d.getHours() >= 6 && d.getHours() < 18) ? '<i class="fas fa-sun time-icon"></i>' : '<i class="fas fa-moon time-icon"></i>';
    document.getElementById('dateDisplay').innerHTML = d.getDate() + ' ' + months[d.getMonth()] + ' ' + (d.getFullYear() + 543) + ' з. ' + icon;
})();

// ===== NAV =====
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        const page = link.dataset.page;
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        const t = document.getElementById('page-' + page);
        if (t) t.classList.add('active');
        if (page === 'dashboard') updateDashboard();
        if (page === 'accounts') loadAccounts();
        if (page === 'property') loadProperties();
        if (page === 'salary') { loadEmployers(); loadEmployees(); loadSalaryHistory(); }
        if (page === 'transactions') loadTransactions();
        if (page === 'analytics') loadAnalytics();
        if (page === 'banks') loadBanks();
        if (page === 'bonds') loadActiveLoans();
    });
});
document.querySelectorAll('[data-prop-tab]').forEach(b => b.addEventListener('click', function() {
    document.querySelectorAll('[data-prop-tab]').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    document.querySelectorAll('#page-property .tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById('prop-tab-' + this.dataset.propTab).classList.add('active');
}));
document.querySelectorAll('[data-bonds-tab]').forEach(b => b.addEventListener('click', function() {
    document.querySelectorAll('[data-bonds-tab]').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    document.querySelectorAll('#page-bonds .tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById('bonds-tab-' + this.dataset.bondsTab).classList.add('active');
    if (this.dataset.bondsTab === 'active') loadActiveLoans();
}));
document.querySelectorAll('[data-salary-tab]').forEach(b => b.addEventListener('click', function() {
    document.querySelectorAll('[data-salary-tab]').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    document.querySelectorAll('#page-salary .tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById('salary-tab-' + this.dataset.salaryTab).classList.add('active');
    if (this.dataset.salaryTab === 'employers') loadEmployers();
    if (this.dataset.salaryTab === 'employees') loadEmployees();
    if (this.dataset.salaryTab === 'history') loadSalaryHistory();
}));

// ===== BANKS =====
async function loadBanks() {
    const data = await apiCall('get_banks');
    const c = document.getElementById('bankList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Нет банков</p>'; return; }
    data.forEach(b => {
        const d = document.createElement('div');
        d.className = 'bank-item';
        d.innerHTML = `
            <div class="bank-info">
                <div class="bank-icon" style="background:${b.color || '#4facfe'}"><i class="fas ${b.icon || 'fa-university'}"></i></div>
                <span class="bank-name">${esc(b.name)}</span>
            </div>
            <div class="bank-actions">
                <button onclick="openEditBankModal('${b.id}')"><i class="fas fa-pen"></i></button>
                <button class="delete-bank-btn" onclick="deleteBank('${b.id}')"><i class="fas fa-trash"></i></button>
            </div>`;
        c.appendChild(d);
    });
    updateBankSelects(data);
}
function updateBankSelects(banks) {
    ['bankName','editBankName'].forEach(id => {
        const s = document.getElementById(id);
        if (!s) return;
        const v = s.value;
        s.innerHTML = '';
        banks.forEach(b => {
            const o = document.createElement('option');
            o.value = b.name; o.textContent = b.name;
            s.appendChild(o);
        });
        if (v) s.value = v;
    });
}
function openAddBankModal() { document.getElementById('addBankForm').reset(); openModal('addBankModal'); }
function closeAddBankModal() { closeModal('addBankModal'); }
document.getElementById('addBankForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('name', document.getElementById('bankNameInput').value);
    fd.append('icon', document.getElementById('selectedIcon').value);
    fd.append('color', document.getElementById('selectedColor').value);
    const r = await apiCall('add_bank', 'POST', fd);
    if (r.success) { closeAddBankModal(); loadBanks(); }
    else alert(r.message);
});
async function openEditBankModal(id) {
    const banks = await apiCall('get_banks');
    const b = banks.find(x => x.id === id);
    if (!b) return;
    document.getElementById('editBankId').value = b.id;
    document.getElementById('editBankNameInput').value = b.name;
    document.getElementById('editSelectedIcon').value = b.icon;
    document.getElementById('editSelectedColor').value = b.color;
    openModal('editBankModal');
}
function closeEditBankModal() { closeModal('editBankModal'); }
document.getElementById('editBankForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('editBankId').value);
    fd.append('name', document.getElementById('editBankNameInput').value);
    fd.append('icon', document.getElementById('editSelectedIcon').value);
    fd.append('color', document.getElementById('editSelectedColor').value);
    const r = await apiCall('update_bank', 'POST', fd);
    if (r.success) { closeEditBankModal(); loadBanks(); }
    else alert(r.message);
});
async function deleteBank(id) {
    if (!confirm('Удалить банк?')) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await apiCall('delete_bank', 'POST', fd);
    if (r.success) loadBanks(); else alert(r.message);
}

// ===== DASHBOARD =====
async function updateDashboard() {
    const d = await apiCall('get_stats');
    document.getElementById('accountCount').textContent = d.account_count || 0;
    document.getElementById('totalBalance').innerHTML = Number(d.total_balance || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' <span class="currency-label">чякө</span>';
    document.getElementById('pesetaBalance').innerHTML = Number(d.peseta_balance || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' <span class="currency-label peseta">₧</span>';
    document.getElementById('pesetaCount').textContent = d.peseta_account_count || 0;
    document.getElementById('goldReserve').innerHTML = Number(d.gold_reserve || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' <span class="currency-label" style="color:#d4a017;">чякө</span>';
}

// ===== ACCOUNTS =====
async function loadAccounts(search = '') {
    const data = await apiCall('get_accounts');
    const c = document.getElementById('accountsList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Счетов нет</p>'; return; }
    let list = data;
    if (search) {
        const s = search.toLowerCase();
        list = data.filter(a => (a.organization||'').toLowerCase().includes(s) || (a.account_number||'').includes(s) || (a.bank||'').toLowerCase().includes(s));
    }
    list.forEach(a => {
        const isP = a.currency === 'песета';
        const div = document.createElement('div');
        div.className = 'account-card';
        div.innerHTML = `
            <div class="acc-header">
                <span class="acc-number">№ ${esc(a.account_number)}</span>
                <span class="acc-type">${esc(a.account_type)}</span>
            </div>
            <div class="acc-name">${esc(a.organization)}</div>
            <div class="acc-bank"><i class="fas fa-university"></i> ${esc(a.bank)} ${cBadge(a.currency)}</div>
            <div class="acc-balance-row">
                <div>
                    <div class="acc-balance ${isP ? 'peseta' : ''}">${fmtMoney(a.balance, a.currency)}</div>
                    <div class="acc-balance-label">Баланс</div>
                </div>
                <div class="acc-actions">
                    <button onclick="openEditModal('${a.account_number}')"><i class="fas fa-pen"></i></button>
                    <button class="delete-btn" onclick="openDeleteModal('${a.account_number}','${esc(a.organization)}')"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        c.appendChild(div);
    });
}
function searchAccounts() { loadAccounts(document.getElementById('searchAccount').value); }

document.getElementById('openAccountForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('owner_name', document.getElementById('ownerName').value);
    fd.append('bank_name', document.getElementById('bankName').value);
    fd.append('initial_balance', document.getElementById('initialBalance').value);
    fd.append('account_type', document.getElementById('accountType').value);
    fd.append('currency', document.getElementById('accountCurrency').value);
    const r = await apiCall('open_account', 'POST', fd);
    const el = document.getElementById('openAccountResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)} · № ${r.account_number} · ${cSym(r.currency)}</div>`;
        e.target.reset();
        updateDashboard();
        loadAccounts();
        setTimeout(() => { closeModal('openAccountModal'); el.innerHTML = ''; }, 2000);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});

async function openEditModal(num) {
    const d = await apiCall('get_account&account=' + num);
    if (!d.success) return alert(d.message);
    document.getElementById('editAccountNumber').value = d.account_number;
    document.getElementById('editOwnerName').value = d.organization;
    const banks = await apiCall('get_banks');
    const s = document.getElementById('editBankName');
    s.innerHTML = '';
    banks.forEach(b => {
        const o = document.createElement('option');
        o.value = b.name; o.textContent = b.name;
        s.appendChild(o);
    });
    s.value = d.bank;
    document.getElementById('editBalance').value = d.balance;
    document.getElementById('editAccountType').value = d.account_type;
    document.getElementById('editAccountCurrency').value = d.currency;
    openModal('editAccountModal');
}
function closeEditModal() { closeModal('editAccountModal'); }
document.getElementById('editAccountForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('account_number', document.getElementById('editAccountNumber').value);
    fd.append('organization', document.getElementById('editOwnerName').value);
    fd.append('bank', document.getElementById('editBankName').value);
    fd.append('balance', document.getElementById('editBalance').value);
    fd.append('account_type', document.getElementById('editAccountType').value);
    fd.append('currency', document.getElementById('editAccountCurrency').value);
    const r = await apiCall('update_account', 'POST', fd);
    if (r.success) { closeEditModal(); loadAccounts(); updateDashboard(); }
    else alert(r.message);
});

function openDeleteModal(num, name) {
    document.getElementById('deleteAccountNumber').value = num;
    document.getElementById('deleteAccountDisplay').textContent = num + ' (' + name + ')';
    openModal('deleteAccountModal');
}
function closeDeleteModal() { closeModal('deleteAccountModal'); }
document.getElementById('deleteAccountForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('account_number', document.getElementById('deleteAccountNumber').value);
    const r = await apiCall('delete_account', 'POST', fd);
    if (r.success) { closeDeleteModal(); loadAccounts(); updateDashboard(); }
    else alert(r.message);
});

document.getElementById('transferForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('sender_account', document.getElementById('senderAccount').value);
    fd.append('recipient_account', document.getElementById('recipientAccount').value);
    fd.append('transfer_amount', document.getElementById('transferAmount').value);
    const r = await apiCall('transfer', 'POST', fd);
    const el = document.getElementById('transferResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        e.target.reset();
        updateDashboard();
        loadAccounts();
        setTimeout(() => { closeModal('transferModal'); el.innerHTML = ''; }, 2500);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});

document.getElementById('loanForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('account_number', document.getElementById('loanAccount').value);
    fd.append('loan_amount', document.getElementById('loanAmount').value);
    const r = await apiCall('issue_loan', 'POST', fd);
    const el = document.getElementById('loanResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        e.target.reset();
        updateDashboard();
        loadAccounts();
        setTimeout(() => { closeModal('loanModal'); el.innerHTML = ''; }, 2000);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});

// ===== PROPERTY =====
async function loadProperties() {
    const data = await apiCall('get_properties');
    const c = document.getElementById('propertyList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Объектов нет</p>'; return; }
    data.forEach(p => {
        const isP = p.currency === 'песета';
        const d = document.createElement('div');
        d.className = 'property-card';
        d.innerHTML = `
            <h4>${esc(p.name)}</h4>
            <p>ID: ${esc(p.id)}</p>
            <p>Чұсо: ${esc(p.address)}</p>
            <p>${esc(p.area)} м² • ${esc(p.province)}</p>
            <p>Зұт: ${esc(p.year_built)}</p>
            <div class="price ${isP ? 'peseta' : ''}">${fmtMoney(p.price, p.currency)}</div>
            <button class="btn btn-secondary" onclick="openPurchaseModal('${esc(p.name)}')">Купить</button>
            <button class="btn btn-secondary" style="margin-left:6px;color:#c0392b;border-color:rgba(192,57,43,0.3);" title="Удалить объект" onclick="deleteProperty('${esc(p.id)}','${esc(p.name)}')"><i class="fas fa-trash"></i></button>`;
        c.appendChild(d);
    });
}
async function deleteProperty(id, name) {
    if (!confirm(`Удалить объект «${name}» (${id}) из реестра?`)) return;
    const fd = new FormData();
    fd.append('property_id', id);
    const r = await apiCall('delete_property', 'POST', fd);
    if (r.success) loadProperties();
    else alert(r.message || 'Ошибка удаления');
}
document.getElementById('addPropertyForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('name', document.getElementById('propName').value);
    fd.append('id', document.getElementById('propId').value);
    fd.append('address', document.getElementById('propAddress').value);
    fd.append('area', document.getElementById('propArea').value);
    fd.append('province', document.getElementById('propProvince').value);
    fd.append('year_built', document.getElementById('propYear').value);
    fd.append('price', document.getElementById('propPrice').value);
    fd.append('currency', document.getElementById('propCurrency').value);
    const r = await apiCall('add_property', 'POST', fd);
    const el = document.getElementById('addPropResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        e.target.reset();
        loadProperties();
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});
function openPurchaseModal(name) {
    document.getElementById('purchasePropertyName').value = name;
    document.getElementById('purchaseAccount').value = '';
    document.getElementById('purchaseResult').innerHTML = '';
    openModal('purchaseModal');
}
function closePurchaseModal() { closeModal('purchaseModal'); }
document.getElementById('purchaseForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('propertyToBuy', document.getElementById('purchasePropertyName').value);
    fd.append('accountNumber', document.getElementById('purchaseAccount').value);
    const r = await apiCall('buy_property', 'POST', fd);
    const el = document.getElementById('purchaseResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        setTimeout(() => { closePurchaseModal(); loadProperties(); updateDashboard(); }, 1500);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});

// ===== BONDS =====
document.getElementById('createLoanForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('owner_account', document.getElementById('loanOwnerAccount').value);
    fd.append('total_amount', document.getElementById('loanTotalAmount').value);
    const r = await apiCall('create_loan', 'POST', fd);
    const el = document.getElementById('createLoanResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        e.target.reset();
        loadActiveLoans();
        updateDashboard();
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});
async function loadActiveLoans() {
    const data = await apiCall('get_loans&status=active');
    const c = document.getElementById('activeLoansList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Активных займов нет</p>'; return; }
    data.forEach(l => {
        const sold = l.bond_count - l.remaining_bonds;
        const pct = l.bond_count ? Math.round(sold / l.bond_count * 100) : 0;
        const d = document.createElement('div');
        d.className = 'loan-card';
        d.innerHTML = `
            <div class="loan-header">
                <span class="loan-id">${esc(l.id)}</span>
                <span class="loan-status ${l.status}">${l.status === 'active' ? 'Активен' : l.status}</span>
            </div>
            <div class="loan-owner"><i class="fas fa-user"></i> Владелец: ${esc(l.owner_account)}</div>
            <div class="loan-amount">${Number(l.total_amount).toLocaleString()} чякө</div>
            <div class="loan-details">
                <span>💰 ${l.bond_count} облигаций</span>
                <span>💵 ${l.bond_price} чякө / шт.</span>
                <span>📦 Осталось: ${l.remaining_bonds}</span>
                <span>📅 До: ${esc(l.expires_at)}</span>
            </div>
            <div class="loan-progress"><div class="fill" style="width:${pct}%;"></div></div>
            <div class="loan-actions">
                <button class="btn btn-success" onclick="openBuyBondModal('${l.id}', ${l.bond_count}, ${l.remaining_bonds})"><i class="fas fa-shopping-cart"></i> Купить</button>
                <button class="btn btn-warning" onclick="openRepayBondModal('${l.id}', ${l.remaining_bonds})"><i class="fas fa-hand-holding-usd"></i> Погасить</button>
            </div>`;
        c.appendChild(d);
    });
}
async function loadMyLoans() {
    const acc = document.getElementById('myLoansAccount').value;
    if (!acc) return;
    const data = await apiCall('get_my_loans&account=' + acc);
    const c = document.getElementById('myLoansList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">У вас нет займов</p>'; return; }
    data.forEach(l => {
        const sold = l.bond_count - l.remaining_bonds;
        const pct = l.bond_count ? Math.round(sold / l.bond_count * 100) : 0;
        const d = document.createElement('div');
        d.className = 'loan-card';
        d.innerHTML = `
            <div class="loan-header">
                <span class="loan-id">${esc(l.id)}</span>
                <span class="loan-status ${l.status}">${l.status}</span>
            </div>
            <div class="loan-amount">${Number(l.total_amount).toLocaleString()} чякө</div>
            <div class="loan-details">
                <span>Продано: ${sold}</span>
                <span>Осталось: ${l.remaining_bonds}</span>
                <span>До: ${esc(l.expires_at)}</span>
            </div>
            <div class="loan-progress"><div class="fill" style="width:${pct}%;"></div></div>`;
        c.appendChild(d);
    });
}
function openBuyBondModal(id, total, rem) {
    document.getElementById('buyLoanId').value = id;
    document.getElementById('buyerAccount').value = '';
    document.getElementById('buyQuantity').value = 1;
    document.getElementById('buyBondResult').innerHTML = '';
    document.getElementById('buyBondInfo').innerHTML = `<div style="font-weight:600;">Займ: ${id}</div>
        <div style="font-size:13px;color:rgba(26,26,46,0.5);">Доступно: <strong>${rem}</strong> из ${total}</div>`;
    openModal('buyBondModal');
}
function closeBuyBondModal() { closeModal('buyBondModal'); }
document.getElementById('buyBondForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('loan_id', document.getElementById('buyLoanId').value);
    fd.append('buyer_account', document.getElementById('buyerAccount').value);
    fd.append('quantity', document.getElementById('buyQuantity').value);
    const r = await apiCall('buy_bond', 'POST', fd);
    const el = document.getElementById('buyBondResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        setTimeout(() => { closeBuyBondModal(); loadActiveLoans(); updateDashboard(); }, 1500);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});
async function openRepayBondModal(id, rem) {
    document.getElementById('repayLoanId').value = id;
    document.getElementById('repayOwnerAccount').value = '';
    const sel = document.getElementById('repayQuantity');
    sel.innerHTML = '<option value="all">Все оставшиеся (' + rem + ')</option>';
    for (let i = 1; i <= Math.min(rem, 20); i++) {
        const o = document.createElement('option');
        o.value = i; o.textContent = i + ' шт. (' + i * 5 + ' чякө)';
        sel.appendChild(o);
    }
    document.getElementById('repayBondInfo').innerHTML = `<div style="font-weight:600;">Займ: ${id}</div>
        <div style="font-size:13px;color:rgba(26,26,46,0.5);">Осталось: <strong>${rem}</strong></div>`;
    openModal('repayBondModal');
}
function closeRepayBondModal() { closeModal('repayBondModal'); }
document.getElementById('repayBondForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('loan_id', document.getElementById('repayLoanId').value);
    fd.append('owner_account', document.getElementById('repayOwnerAccount').value);
    const q = document.getElementById('repayQuantity').value;
    fd.append('quantity', q === 'all' ? '' : q);
    const r = await apiCall('repay_loan', 'POST', fd);
    const el = document.getElementById('repayBondResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        setTimeout(() => { closeRepayBondModal(); loadActiveLoans(); updateDashboard(); }, 2000);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});

// ===== SALARY =====
async function loadEmployers() {
    const data = await apiCall('get_employers');
    const c = document.getElementById('employersList');
    c.innerHTML = '';
    const ef = document.getElementById('employeeEmployerFilter');
    const hf = document.getElementById('historyEmployeeFilter');
    ef.innerHTML = '<option value="">Все работодатели</option>';
    hf.innerHTML = '<option value="">Сотрудник</option>';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Нет работодателей</p>'; return; }
    data.forEach(e => {
        const div = document.createElement('div');
        div.className = 'salary-employer-card';
        div.innerHTML = `
            <div class="emp-header">
                <div>
                    <div class="emp-name">${esc(e.name)}</div>
                    <div class="emp-account"><i class="fas fa-university"></i> ${esc(e.account)}</div>
                </div>
                <div class="emp-actions">
                    <button class="btn btn-secondary" onclick="deleteEmployer('${e.id}')" style="padding:4px 10px;font-size:11px;"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        c.appendChild(div);
        const o = document.createElement('option'); o.value = e.id; o.textContent = e.name; ef.appendChild(o);
        const o2 = document.createElement('option'); o2.value = e.id; o2.textContent = e.name; hf.appendChild(o2);
    });
    loadEmployeesForHistory();
}
function openEmployerModal() { document.getElementById('employerForm').reset(); openModal('employerModal'); }
function closeEmployerModal() { closeModal('employerModal'); }
document.getElementById('employerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('name', document.getElementById('employerName').value);
    fd.append('account', document.getElementById('employerAccount').value);
    const r = await apiCall('create_employer', 'POST', fd);
    if (r.success) { closeEmployerModal(); loadEmployers(); }
    else alert(r.message);
});
async function deleteEmployer(id) {
    if (!confirm('Удалить?')) return;
    const fd = new FormData(); fd.append('employer_id', id);
    const r = await apiCall('delete_employer', 'POST', fd);
    if (r.success) loadEmployers(); else alert(r.message);
}
async function loadEmployees() {
    const eid = document.getElementById('employeeEmployerFilter').value;
    const data = await apiCall('get_employees' + (eid ? '&employer_id=' + eid : ''));
    const c = document.getElementById('employeesList');
    c.innerHTML = '';
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">Нет сотрудников</p>'; return; }

    for (const e of data) {
        const salaryCurrency = e.salary_currency || 'чякө';
        const acc = await apiCall('get_account&account=' + e.account);
        const accCur = acc.success ? acc.currency : 'чякө';

        // === Пропущенные месяцы и долг ===
        let missing = [];
        let debtChako = 0;
        try {
            missing = await apiCall('get_missing_months&employee_id=' + e.id);
            const debtData = await apiCall('get_total_debt&employee_id=' + e.id);
            debtChako = Number(debtData.debt) || 0;
        } catch (err) {
            console.warn('Не удалось получить долг для', e.id, err);
        }
        const missingCount = Array.isArray(missing) ? missing.length : 0;
        const hasMissing = missingCount > 0;

        // Переводим долг из чякё в валюту оклада
        const debtInSalaryCurrency = salaryCurrency === 'песета'
            ? debtChako * 10
            : debtChako;

        const debtHtml = hasMissing
            ? `<span class="emp-debt" title="Пропущено ${missingCount} мес. по ${fmtMoney(e.salary, salaryCurrency)}">
                   💰 Долг: ${fmtMoney(debtInSalaryCurrency, salaryCurrency)}
                   <small style="color:var(--ink-mute);font-weight:500;"> (${missingCount} мес.)</small>
               </span>`
            : '';

        const statusHtml = hasMissing
            ? `<span class="emp-status missing">⚠ Пропущено ${missingCount} мес.</span>`
            : `<span class="emp-status">✓ Все выплачено</span>`;

        const div = document.createElement('div');
        div.className = 'salary-employee-card';
        div.innerHTML = `
            <div class="emp-info">
                <div>
                    <span class="emp-name">${esc(e.name)}</span>
                    <span class="emp-detail">Оклад: ${fmtMoney(e.salary, salaryCurrency)}</span>
                    <span class="emp-detail">Счёт: ${esc(e.account)} ${cBadge(accCur)}</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    ${debtHtml}
                    ${statusHtml}
                    <button class="btn btn-secondary" onclick="openEditEmployeeModal('${e.id}')" style="padding:4px 12px;font-size:11px;" title="Редактировать">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn btn-warning" onclick="openPaySalaryModal('${e.id}')" style="padding:4px 12px;font-size:11px;">
                        <i class="fas fa-hand-holding-usd"></i> Выплатить
                    </button>
                    <button class="btn btn-danger" onclick="deleteEmployee('${e.id}')" style="padding:4px 12px;font-size:11px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div style="font-size:11px;color:var(--ink-mute);margin-top:6px;">
                Принят: ${esc(e.hire_date || '—')} · Последняя выплата: ${esc(e.last_payment || 'никогда')}
            </div>`;
        c.appendChild(div);
    }
}
function openEmployeeModal() {
    apiCall('get_employers').then(list => {
        const s = document.getElementById('employeeEmployer');
        s.innerHTML = '';
        (list || []).forEach(e => {
            const o = document.createElement('option');
            o.value = e.id; o.textContent = e.name;
            s.appendChild(o);
        });
    });
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeHireDate').value = new Date().toISOString().split('T')[0];
    openModal('employeeModal');
}
function closeEmployeeModal() { closeModal('employeeModal'); }
document.getElementById('employeeForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('employer_id', document.getElementById('employeeEmployer').value);
    fd.append('name', document.getElementById('employeeName').value);
    fd.append('account', document.getElementById('employeeAccount').value);
    fd.append('salary', document.getElementById('employeeSalary').value);
    fd.append('salary_currency', document.getElementById('employeeSalaryCurrency').value);
    fd.append('hire_date', document.getElementById('employeeHireDate').value);
    const r = await apiCall('create_employee', 'POST', fd);
    if (r.success) { closeEmployeeModal(); loadEmployees(); loadEmployers(); }
    else alert(r.message);
});
async function deleteEmployee(id) {
    if (!confirm('Удалить сотрудника?')) return;
    const fd = new FormData(); fd.append('employee_id', id);
    const r = await apiCall('delete_employee', 'POST', fd);
    if (r.success) loadEmployees(); else alert(r.message);
}

// >>> НОВОЕ: редактирование сотрудника
async function openEditEmployeeModal(id) {
    const emps = await apiCall('get_employees');
    const emp = emps.find(e => e.id === id);
    if (!emp) { alert('Сотрудник не найден'); return; }

    document.getElementById('editEmployeeId').value = emp.id;
    document.getElementById('editEmployeeName').value = emp.name || '';
    document.getElementById('editEmployeeAccount').value = emp.account || '';
    document.getElementById('editEmployeeSalary').value = emp.salary || 0;
    document.getElementById('editEmployeeSalaryCurrency').value = emp.salary_currency || 'чякө';
    document.getElementById('editEmployeeHireDate').value = emp.hire_date || new Date().toISOString().split('T')[0];
    document.getElementById('editEmployeeResult').innerHTML = '';
    openModal('editEmployeeModal');
}
function closeEditEmployeeModal() { closeModal('editEmployeeModal'); }

document.getElementById('editEmployeeForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('employee_id', document.getElementById('editEmployeeId').value);
    fd.append('name', document.getElementById('editEmployeeName').value);
    fd.append('account', document.getElementById('editEmployeeAccount').value);
    fd.append('salary', document.getElementById('editEmployeeSalary').value);
    fd.append('salary_currency', document.getElementById('editEmployeeSalaryCurrency').value);
    fd.append('hire_date', document.getElementById('editEmployeeHireDate').value);
    const r = await apiCall('update_employee', 'POST', fd);
    const el = document.getElementById('editEmployeeResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        setTimeout(() => { closeEditEmployeeModal(); loadEmployees(); }, 800);
    } else {
        el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
    }
});

async function openPaySalaryModal(id) {
    const emps = await apiCall('get_employees');
    const emp = emps.find(e => e.id === id);
    if (!emp) return;
    const acc = await apiCall('get_account&account=' + emp.account);
    const accCur = acc.success ? acc.currency : 'чякө';
    const salaryCur = emp.salary_currency || 'чякө';

    const salaryInChako = salaryCur === 'песета' ? emp.salary / 10 : emp.salary;
    const amountToEmployee = accCur === 'песета' ? salaryInChako * 10 : salaryInChako;

    document.getElementById('payEmployeeId').value = id;
    document.getElementById('paySalaryInfo').innerHTML = `
        <div style="font-weight:600;">${esc(emp.name)}</div>
        <div style="font-size:13px;color:rgba(26,26,46,0.5);">
            Оклад: ${fmtMoney(emp.salary, salaryCur)}<br>
            Счёт: ${esc(emp.account)} ${cBadge(accCur)}<br>
            За 1 мес. зачислится: <strong>${fmtMoney(amountToEmployee, accCur)}</strong>
        </div>`;
    openModal('paySalaryModal');
}
function closePaySalaryModal() { closeModal('paySalaryModal'); }
document.getElementById('paySalaryForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('employee_id', document.getElementById('payEmployeeId').value);
    fd.append('months', document.getElementById('payMonths').value);
    const r = await apiCall('pay_salary', 'POST', fd);
    const el = document.getElementById('payResult');
    if (r.success) {
        el.innerHTML = `<div style="color:#2e7d32;">✅ ${esc(r.message)}</div>`;
        setTimeout(() => { closePaySalaryModal(); loadEmployees(); updateDashboard(); }, 1500);
    } else el.innerHTML = `<div style="color:#c62828;">❌ ${esc(r.message)}</div>`;
});
async function loadEmployeesForHistory() {
    const data = await apiCall('get_employees');
    const f = document.getElementById('historyEmployeeFilter');
    const v = f.value;
    f.innerHTML = '<option value="">Сотрудник</option>';
    (data || []).forEach(e => {
        const o = document.createElement('option');
        o.value = e.id; o.textContent = e.name;
        f.appendChild(o);
    });
    if (v) f.value = v;
}
async function loadSalaryHistory() {
    const id = document.getElementById('historyEmployeeFilter').value;
    const c = document.getElementById('historyContent');
    if (!id) { c.innerHTML = '<p class="text-muted">Выберите сотрудника</p>'; return; }
    const data = await apiCall('get_salary_history&employee_id=' + id);
    if (!data || !data.length) { c.innerHTML = '<p class="text-muted">История пуста</p>'; return; }
    const months = ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'];
    let h = '<table class="history-table"><thead><tr><th>Дата</th><th>Сумма (в чякө)</th><th>Месяц</th><th>Год</th></tr></thead><tbody>';
    [...data].reverse().forEach(x => {
        h += `<tr><td>${esc(x.date)}</td><td><strong>${Number(x.amount).toLocaleString()}</strong> чякө</td><td>${months[(x.month||1)-1]}</td><td>${x.year||'—'}</td></tr>`;
    });
    c.innerHTML = h + '</tbody></table>';
}

// ===== ЖУРНАЛ ОПЕРАЦИЙ (вкладка «Операции») =====
async function loadTransactions() {
    const acc = (document.getElementById('txAccount').value || '').trim();
    const limit = document.getElementById('txLimit').value || '100';
    const listEl = document.getElementById('transactionsList');
    const infoEl = document.getElementById('transactionsInfo');
    listEl.innerHTML = '<p class="text-muted">Загрузка…</p>';

    let query = 'get_transactions&limit=' + encodeURIComponent(limit);
    if (acc) query += '&account=' + encodeURIComponent(acc);
    const d = await apiCall(query);
    const rows = (d && d.success && Array.isArray(d.data)) ? d.data : [];

    if (!rows.length) {
        infoEl.textContent = '';
        listEl.innerHTML = '<p class="text-muted">' + (acc ? 'Для счёта № ' + esc(acc) + ' операций не найдено.' : 'Журнал операций пуст.') + '</p>';
        return;
    }

    infoEl.innerHTML = acc
        ? `Показано <strong>${rows.length}</strong> опер. по счёту № <strong>${esc(acc)}</strong>`
        : `Показано <strong>${rows.length}</strong> последних операций`;

    // Сумма в основной валюте + пересчёт во вторую (÷10 для песеты → чякө, ×10 для чякө → песета)
    const fmt = n => Number(n).toLocaleString(undefined, {maximumFractionDigits: 2});
    const amountCell = t => {
        const cur = t.currency === 'песета' ? 'песета' : 'чякө';
        const main = `<strong>${fmt(t.amount)}</strong> ${cBadge(cur)}`;
        if (cur === 'песета') return `${main}<div class="tx-alt">≈ ${fmt(Math.round(t.amount / 10 * 100) / 100)} ч.</div>`;
        return `${main}<div class="tx-alt">≈ ${fmt(Math.round(t.amount * 10 * 100) / 100)} ₧</div>`;
    };

    let h = '<table class="history-table"><thead><tr>' +
        '<th>Дата</th><th>ID операции</th><th>Отправитель → Получатель</th>' +
        '<th style="text-align:right;">Сумма</th><th>Описание</th></tr></thead><tbody>';
    rows.forEach(t => {
        const isIncome = acc && t.recipient === acc;
        const arrow = isIncome
            ? `<span style="color:#1565c0;">${esc(t.sender)} <i class="fas fa-arrow-right"></i> <strong>Вы</strong></span>`
            : `<span style="color:#b45309;"><strong>${esc(t.sender)}</strong> <i class="fas fa-arrow-right"></i> ${esc(t.recipient)}</span>`;
        h += `<tr>
            <td style="white-space:nowrap;">${esc(t.date)}</td>
            <td style="font-family:monospace;font-size:11px;color:var(--ink-mute);">${esc(t.id)}</td>
            <td>${arrow}</td>
            <td style="text-align:right;white-space:nowrap;">${amountCell(t)}</td>
            <td>${esc(t.description) || '—'}</td>
        </tr>`;
    });
    listEl.innerHTML = h + '</tbody></table>';
}

// ===== ANALYTICS =====
async function loadAnalytics() {
    try {
        const d = await apiCall('get_bank_stats');

        document.getElementById('analyticsBankCount').textContent = d.bank_count || 0;
        document.getElementById('analyticsTotalBalance').innerHTML =
            Number(d.total_balance || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) +
            ' <span class="currency-label">чякө</span>';
        document.getElementById('analyticsTotalAccounts').textContent = d.total_accounts || 0;
        const avg = d.total_accounts > 0 ? d.total_balance / d.total_accounts : 0;
        document.getElementById('analyticsAvgBalance').innerHTML =
            Math.round(avg).toLocaleString() + ' <span class="currency-label">чякө</span>';

        const banks = d.banks || {};
        const names = Object.keys(banks);
        const balances = names.map(n => banks[n].total_balance);
        const counts = names.map(n => banks[n].count);
        const colors = ['#4facfe','#43e97b','#f5a623','#a855f7','#f5576c','#14b8a6','#f97316','#8b5cf6','#06b6d4','#ec4899','#22d3ee','#f472b6','#34d399'];
        const map = {};
        names.forEach((n, i) => map[n] = banks[n].color || colors[i % colors.length]);

        if (window.chart1) { window.chart1.destroy(); window.chart1 = null; }
        if (window.chart2) { window.chart2.destroy(); window.chart2 = null; }
        if (window.chart3) { window.chart3.destroy(); window.chart3 = null; }

        const ctx1 = document.getElementById('balanceChart');
        if (ctx1) {
            window.chart1 = new Chart(ctx1.getContext('2d'), {
                type: 'bar',
                data: { labels: names, datasets: [{
                    label: 'Баланс (чякө)', data: balances,
                    backgroundColor: names.map(n => map[n] + '80'),
                    borderColor: names.map(n => map[n]),
                    borderWidth: 2, borderRadius: 6
                }]},
                options: { responsive: true, plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString() } } } }
            });
        }
        const ctx2 = document.getElementById('pieChart');
        if (ctx2) {
            window.chart2 = new Chart(ctx2.getContext('2d'), {
                type: 'doughnut',
                data: { labels: names, datasets: [{
                    data: balances, backgroundColor: names.map(n => map[n]),
                    borderColor: 'rgba(255,255,255,0.8)', borderWidth: 2
                }]},
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }
        const ctx3 = document.getElementById('countChart');
        if (ctx3) {
            window.chart3 = new Chart(ctx3.getContext('2d'), {
                type: 'bar',
                data: { labels: names, datasets: [{
                    label: 'Счетов', data: counts,
                    backgroundColor: names.map(n => map[n] + '60'),
                    borderColor: names.map(n => map[n]),
                    borderWidth: 2, borderRadius: 6
                }]},
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        }

        const details = document.getElementById('bankDetails');
        if (!details) return;
        details.innerHTML = '';

        if (!names.length) {
            details.innerHTML = '<p class="text-muted">Нет данных по банкам</p>';
            return;
        }

        const sorted = names.sort((a, b) => banks[b].total_balance - banks[a].total_balance);
        const max = sorted.length ? (banks[sorted[0]].total_balance || 1) : 1;

        sorted.forEach(n => {
            const b = banks[n] || { total_balance: 0, count: 0, accounts: [] };
            const pct = max > 0 ? ((b.total_balance / max) * 100).toFixed(1) : '0.0';
            const col = b.color || '#4facfe';

            const accountsHtml = (b.accounts || []).slice(0, 5).map(a =>
                `<span>${esc(a.organization)} (${fmtMoney(a.balance, a.currency)})</span>`
            ).join('');
            const moreHtml = (b.accounts || []).length > 5
                ? `<span>+ ещё ${b.accounts.length - 5}</span>` : '';

            const div = document.createElement('div');
            div.className = 'bank-card';
            div.innerHTML = `
                <div class="bank-header">
                    <div class="bank-name"><i class="fas ${b.icon || 'fa-university'}" style="color:${col}"></i> ${esc(n)}</div>
                    <span style="font-size:14px;font-weight:600;color:${col};">
                        ${Number(b.total_balance || 0).toLocaleString(undefined, {maximumFractionDigits: 2})} чякө
                    </span>
                </div>
                <div class="bank-stats">
                    <span class="bank-stat"><strong>${b.count || 0}</strong> счетов</span>
                    <span class="bank-stat">Доля: <strong>${pct}%</strong></span>
                </div>
                <div class="progress-bar"><div class="fill" style="width:${pct}%;background:${col}"></div></div>
                <div class="bank-accounts">${accountsHtml}${moreHtml}</div>`;
            details.appendChild(div);
        });
    } catch (err) {
        console.error('Analytics error:', err);
        const details = document.getElementById('bankDetails');
        if (details) details.innerHTML = '<p class="text-muted">Ошибка загрузки аналитики: ' + esc(err.message) + '</p>';
    }
}

// ===== INIT =====
loadBanks();
updateDashboard();
loadAccounts();
loadActiveLoans();

setInterval(() => {
    if (document.getElementById('page-dashboard').classList.contains('active')) updateDashboard();
    if (document.getElementById('page-analytics').classList.contains('active')) loadAnalytics();
}, 30000);
</script>
</body>
</html>