<?php
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');
define('CHAKO_PER_PESETA', 10);

function get_account_data($num) {
    $f = 'accounts/' . $num . '.txt';
    if (!file_exists($f)) return false;
    $d = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($d) < 3) return false;
    return [
        'organization' => $d[0] ?? '',
        'bank' => $d[1] ?? '',
        'balance' => floatval($d[2] ?? 0),
        'account_type' => $d[3] ?? '',
        'currency' => ($d[4] ?? CURRENCY_CHAKO) === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO
    ];
}
function update_balance($num, $new) {
    $f = 'accounts/' . $num . '.txt';
    if (!file_exists($f)) return false;
    $d = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $d[2] = number_format($new, 2, '.', '');
    return file_put_contents($f, implode("\n", $d) . "\n") !== false;
}
function convert_amount($amount, $from, $to) {
    if ($from === $to) return $amount;
    if ($from === CURRENCY_CHAKO && $to === CURRENCY_PESETA) return $amount * CHAKO_PER_PESETA;
    if ($from === CURRENCY_PESETA && $to === CURRENCY_CHAKO) return $amount / CHAKO_PER_PESETA;
    return $amount;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sender = trim($_POST['sender_account'] ?? '');
    $recipient = trim($_POST['recipient_account'] ?? '');
    $amount = floatval($_POST['transfer_amount'] ?? 0);

    $sender_data = get_account_data($sender);
    $recipient_data = get_account_data($recipient);
    if (!$sender_data || !$recipient_data) {
        echo "Error: account not found."; exit;
    }
    if ($sender === $recipient) { echo "Error: same account."; exit; }
    if ($amount <= 0) { echo "Error: amount must be > 0."; exit; }
    if ($sender_data['currency'] === CURRENCY_PESETA && $amount < 10) {
        echo "Error: minimum 10 ₧."; exit;
    }
    if ($sender_data['balance'] < $amount) { echo "Error: insufficient funds."; exit; }

    $amount_to_recipient = round(convert_amount($amount, $sender_data['currency'], $recipient_data['currency']), 2);

    if (!update_balance($sender, $sender_data['balance'] - $amount)) { echo "Error."; exit; }
    if (!update_balance($recipient, $recipient_data['balance'] + $amount_to_recipient)) {
        update_balance($sender, $sender_data['balance']);
        echo "Error."; exit;
    }
    header("Location: index.php");
    exit;
}
echo "Error: Invalid request method.";
?>