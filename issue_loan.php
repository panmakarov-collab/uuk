<?php
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account = trim($_POST['account_number'] ?? '');
    $amount = floatval($_POST['loan_amount'] ?? 0);
    if ($account === '' || $amount <= 0) { echo "Error: invalid data."; exit; }

    $file = 'accounts/' . $account . '.txt';
    if (!file_exists($file)) { echo "Error: account not found."; exit; }
    $d = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $current = floatval($d[2] ?? 0);
    $d[2] = number_format($current + $amount, 2, '.', '');
    file_put_contents($file, implode("\n", $d) . "\n");
    echo "Loan of $amount issued to account $account.";
    header("Location: index.php");
    exit;
}
?>