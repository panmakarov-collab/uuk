<?php
// ================================================================
// ЗАРПЛАТНЫЙ СЕРВИС (библиотека функций)
// Формат файла сотрудника (salary_employees/<id>.txt):
//   0: employer_id
//   1: name
//   2: account
//   3: salary
//   4: hire_date
//   5: last_payment
//   6: salary_currency (чякө | песета), если нет — чякө
// ================================================================

define('SALARY_EMPLOYERS_DIR', 'salary_employers/');
define('SALARY_EMPLOYEES_DIR', 'salary_employees/');
define('SALARY_HISTORY_DIR', 'salary_history/');
define('ACCOUNTS_DIR', 'accounts/');

define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('CHAKO_PER_PESETA', 10);

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}
function to_chako($amount, $currency) {
    return $currency === CURRENCY_PESETA ? $amount / CHAKO_PER_PESETA : $amount;
}
function convert_from_chako($amount, $to_currency) {
    return $to_currency === CURRENCY_PESETA ? round($amount * CHAKO_PER_PESETA, 2) : round($amount, 2);
}
function currency_short_local($cur) {
    return $cur === CURRENCY_PESETA ? '₧' : 'ч.';
}

function get_account_data_local($num) {
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
function update_account_balance_local($num, $new) {
    $f = ACCOUNTS_DIR . $num . '.txt';
    if (!file_exists($f)) return false;
    $data = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($data) < 3) return false;
    $data[2] = number_format($new, 2, '.', '');
    return file_put_contents($f, implode("\n", $data) . "\n") !== false;
}

/**
 * Добавить работодателя
 */
function add_salary_account($account_number, $employee_name, $employer_name, $monthly_salary, $salary_currency = CURRENCY_CHAKO) {
    if (!is_dir(SALARY_EMPLOYERS_DIR)) mkdir(SALARY_EMPLOYERS_DIR, 0777, true);
    $id = uniqid('emp_');
    $data = "$employer_name\n$account_number\n" . date('Y-m-d H:i:s') . "\n";
    file_put_contents(SALARY_EMPLOYERS_DIR . $id . '.txt', $data);
    return $id;
}

/**
 * Создать сотрудника
 */
function create_employee($employer_id, $name, $account, $salary, $salary_currency, $hire_date) {
    if (!is_dir(SALARY_EMPLOYEES_DIR)) mkdir(SALARY_EMPLOYEES_DIR, 0777, true);
    if (!is_dir(SALARY_HISTORY_DIR)) mkdir(SALARY_HISTORY_DIR, 0777, true);
    $salary_currency = normalize_currency($salary_currency);
    $id = uniqid('emp_');
    $data = "$employer_id\n$name\n$account\n$salary\n$hire_date\n\n$salary_currency\n";
    if (!file_put_contents(SALARY_EMPLOYEES_DIR . $id . '.txt', $data)) return false;
    return $id;
}

/**
 * НОВОЕ: обновить сотрудника (включая валюту оклада)
 * Работодатель не меняется — чтобы не ломать историю.
 */
function update_employee($employee_id, $name, $account, $salary, $salary_currency, $hire_date) {
    $file = SALARY_EMPLOYEES_DIR . $employee_id . '.txt';
    if (!file_exists($file)) return ['success' => false, 'message' => 'Сотрудник не найден'];

    $emp_data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($emp_data) < 5) return ['success' => false, 'message' => 'Некорректные данные'];

    $employer_id  = $emp_data[0];
    $last_payment = $emp_data[5] ?? '';
    $salary_currency = normalize_currency($salary_currency);

    $new_data = "$employer_id\n$name\n$account\n$salary\n$hire_date\n$last_payment\n$salary_currency\n";
    if (file_put_contents($file, $new_data)) {
        return ['success' => true, 'message' => 'Сотрудник обновлён'];
    }
    return ['success' => false, 'message' => 'Ошибка сохранения'];
}

/**
 * Получить сотрудника по ID
 */
function get_employee($id) {
    $f = SALARY_EMPLOYEES_DIR . $id . '.txt';
    if (!file_exists($f)) return null;
    $d = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($d) < 5) return null;
    return [
        'id' => $id,
        'employer_id' => $d[0] ?? '',
        'name' => $d[1] ?? '',
        'account' => $d[2] ?? '',
        'salary' => floatval($d[3] ?? 0),
        'hire_date' => $d[4] ?? '',
        'last_payment' => $d[5] ?? '',
        'salary_currency' => normalize_currency($d[6] ?? CURRENCY_CHAKO)
    ];
}

/**
 * Получить сотрудников (можно фильтровать по работодателю)
 */
function get_employees($employer_id = null) {
    $out = [];
    if (!is_dir(SALARY_EMPLOYEES_DIR)) return $out;
    foreach (scandir(SALARY_EMPLOYEES_DIR) as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'txt') continue;
        $emp = get_employee(pathinfo($file, PATHINFO_FILENAME));
        if ($emp && ($employer_id === null || $emp['employer_id'] === $employer_id)) {
            $out[] = $emp;
        }
    }
    return $out;
}

/**
 * Получить работодателя
 */
function get_employer($id) {
    $f = SALARY_EMPLOYERS_DIR . $id . '.txt';
    if (!file_exists($f)) return null;
    $d = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($d) < 3) return null;
    return [
        'id' => $id,
        'name' => $d[0] ?? '',
        'account' => $d[1] ?? '',
        'created' => $d[2] ?? ''
    ];
}

/**
 * История зарплат (в чякө)
 */
function get_salary_history($employee_id) {
    $out = [];
    $f = SALARY_HISTORY_DIR . $employee_id . '.txt';
    if (!file_exists($f)) return $out;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $p = explode('|', $line);
        if (count($p) >= 4) {
            $out[] = [
                'date' => $p[0],
                'amount' => floatval($p[1]),  // в чякө
                'month' => $p[2],
                'year' => $p[3]
            ];
        }
    }
    return $out;
}

/**
 * Выплатить зарплату за N месяцев
 * Всё считается в чякө, потом конвертируется:
 *  - с работодателя списывается в валюте его счёта
 *  - сотруднику зачисляется в валюте его счёта
 */
function pay_salary($employee_id, $months = 1) {
    $emp = get_employee($employee_id);
    if (!$emp) return ['success' => false, 'message' => 'Сотрудник не найден'];

    $employer = get_employer($emp['employer_id']);
    if (!$employer) return ['success' => false, 'message' => 'Работодатель не найден'];

    $emp_acc = get_account_data_local($emp['account']);
    $er_acc = get_account_data_local($employer['account']);
    if (!$emp_acc) return ['success' => false, 'message' => 'Счёт сотрудника не найден'];
    if (!$er_acc) return ['success' => false, 'message' => 'Счёт работодателя не найден'];

    // Оклад в чякө
    $salary_chako = to_chako($emp['salary'], $emp['salary_currency']);
    $total_chako = round($salary_chako * $months, 2);

    $amount_from_employer = convert_from_chako($total_chako, $er_acc['currency']);
    $amount_to_employee = convert_from_chako($total_chako, $emp_acc['currency']);

    if ($er_acc['balance'] < $amount_from_employer) {
        return ['success' => false, 'message' => "Недостаточно средств. Нужно: {$amount_from_employer} "
            . currency_short_local($er_acc['currency']) . ", доступно: {$er_acc['balance']}"];
    }

    if (!update_account_balance_local($employer['account'], $er_acc['balance'] - $amount_from_employer)) {
        return ['success' => false, 'message' => 'Ошибка списания с работодателя'];
    }
    if (!update_account_balance_local($emp['account'], $emp_acc['balance'] + $amount_to_employee)) {
        update_account_balance_local($employer['account'], $er_acc['balance']);
        return ['success' => false, 'message' => 'Ошибка зачисления сотруднику'];
    }

    // Обновляем last_payment
    $f = SALARY_EMPLOYEES_DIR . $employee_id . '.txt';
    $d = file($f, FILE_IGNORE_NEW_LINES);
    $d[5] = date('Y-m-d H:i:s');
    file_put_contents($f, implode("\n", $d) . "\n");

    // История — в чякө
    $history_file = SALARY_HISTORY_DIR . $employee_id . '.txt';
    $cm = intval(date('m')); $cy = intval(date('Y'));
    for ($i = 0; $i < $months; $i++) {
        $month = $cm - $i; $year = $cy;
        if ($month <= 0) { $month += 12; $year--; }
        file_put_contents($history_file, date('Y-m-d H:i:s') . "|$salary_chako|$month|$year\n", FILE_APPEND);
    }

    return [
        'success' => true,
        'message' => "Выплачено $amount_to_employee " . currency_short_local($emp_acc['currency'])
            . " ($months мес.)",
        'amount_from_employer' => $amount_from_employer,
        'amount_to_employee' => $amount_to_employee,
        'total_chako' => $total_chako
    ];
}

function get_all_salary_accounts() {
    return get_employees();
}
?>