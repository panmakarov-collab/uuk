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
    $property_to_buy = trim($_POST['propertyToBuy'] ?? '');
    $account_number = trim($_POST['accountNumber'] ?? '');

    if ($property_to_buy === '' || $account_number === '') {
        echo "Error: All fields required.";
        exit;
    }

    $properties = file('properties.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($properties === false) { echo "Error: cannot read properties."; exit; }

    $buyer = get_account_data($account_number);
    if (!$buyer) { echo "Error: account not found."; exit; }

    $found = false;
    $found_index = -1;
    $property_price = 0;
    $property_currency = CURRENCY_CHAKO;
    $property_data = [];

    foreach ($properties as $idx => $line) {
        $d = explode('|', $line);
        if ($d[0] === $property_to_buy) {
            $found = true;
            $found_index = $idx;
            $property_data = $d;
            $property_price = floatval($d[6]);
            $property_currency = ($d[7] ?? CURRENCY_CHAKO) === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
            break;
        }
    }
    if (!$found) { echo "Error: property not found."; exit; }

    $price_for_buyer = convert_amount($property_price, $property_currency, $buyer['currency']);
    $price_for_buyer = round($price_for_buyer, 2);

    if ($buyer['balance'] < $price_for_buyer) {
        echo "Error: insufficient funds.";
        exit;
    }

    if (!is_dir('sold_properties/')) mkdir('sold_properties/', 0777, true);

    $sale_number = uniqid();
    $sold_file = 'sold_properties/sold_property_' . $property_to_buy . '_' . $sale_number . '.txt';
    $sold_data = "Sale Number: $sale_number\n";
    $sold_data .= "Property Name: {$property_data[0]}\n";
    $sold_data .= "ID: {$property_data[1]}\n";
    $sold_data .= "Address: {$property_data[2]}\n";
    $sold_data .= "Area: {$property_data[3]}\n";
    $sold_data .= "Province: {$property_data[4]}\n";
    $sold_data .= "Year Built: {$property_data[5]}\n";
    $sold_data .= "Price: {$property_data[6]} " . ($property_currency === CURRENCY_PESETA ? '₧' : 'чякө') . "\n";
    $sold_data .= "Buyer Account: $account_number\n";
    $sold_data .= "Date: " . date('Y-m-d H:i:s') . "\n";
    file_put_contents($sold_file, $sold_data);

    file_put_contents('purchases.txt',
        "$property_to_buy|$sale_number|$account_number|$price_for_buyer|{$buyer['currency']}\n",
        FILE_APPEND);

    unset($properties[$found_index]);
    file_put_contents('properties.txt', implode("\n", $properties));

    update_balance($account_number, $buyer['balance'] - $price_for_buyer);

    // Зачисление на специальный счёт (в чякө)
    $special = '983902849056';
    $sp = get_account_data($special);
    if ($sp) {
        $amount_for_special = convert_amount($property_price, $property_currency, $sp['currency']);
        $amount_for_special = round($amount_for_special, 2);
        update_balance($special, $sp['balance'] + $amount_for_special);
    }

    header("Location: property.php");
    exit;
}
echo "Error: Invalid request method.";
?>