<?php
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

function generate_account_number() {
    return mt_rand(100000000000, 999999999999);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_number = generate_account_number();
    $owner_name = trim($_POST['owner_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $initial_balance = floatval($_POST['initial_balance'] ?? 0);
    $account_type = trim($_POST['account_type'] ?? 'Сұкромны');
    $currency = ($_POST['currency'] ?? CURRENCY_CHAKO) === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;

    // owner / bank / balance / type / currency
    $account_data = "$owner_name\n$bank_name\n$initial_balance\n$account_type\n$currency";
    if (!file_exists('accounts')) mkdir('accounts');
    file_put_contents('accounts/' . $account_number . '.txt', $account_data);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рәәстрачә жәколә</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        h2 { color: #1a1a2e; margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; font-size: 13px; color: #5a6470; }
        input, select { width: 100%; padding: 10px 14px; border: 1px solid #ecebe6; border-radius: 10px; font-size: 14px; background: #fafafa; }
        input:focus, select:focus { outline: none; border-color: #4facfe; background: #fff; }
        button { width: 100%; padding: 12px; border: none; border-radius: 10px; background: linear-gradient(135deg, #4facfe, #3a7bd5); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 16px; }
        button:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,172,254,0.3); }
    </style>
</head>
<body>
<div class="container">
    <h2>Рәәстрачә жәколә</h2>
    <form method="post">
        <label for="owner_name">Нөмә онровё</label>
        <input type="text" id="owner_name" name="owner_name" required>

        <label for="bank_name">Нөмә пәнкәлә</label>
        <select id="bank_name" name="bank_name" required>
            <option value="Цұнат пәнкә">Цұнат пәнкә</option>
            <option value="Анкел пәнкә">Анкел пәнкә</option>
            <option value="Инвестыцыйны пәнкә">Инвестыцыйны пәнкә</option>
            <option value="Тәжашұт пәнкә">Тәжашұт пәнкә</option>
            <option value="Ёратүрат пәнкә">Ёратүрат пәнкә</option>
            <option value="Лагбөнат пәнкә">Лагбөнат пәнкә</option>
            <option value="Banco Bolivariano">Banco Bolivariano</option>
        </select>

        <label for="initial_balance">Баланс</label>
        <input type="number" id="initial_balance" name="initial_balance" step="0.01" required>

        <label for="account_type">Көлерә жәколә</label>
        <select id="account_type" name="account_type" required>
            <option value="Сұкромны">Сұкромны</option>
            <option value="Йұридически">Йұридически</option>
            <option value="Лагбөнат">Лагбөнат</option>
        </select>

        <label for="currency">Валюта счёта</label>
        <select id="currency" name="currency" required>
            <option value="чякө">чякө</option>
            <option value="песета">песета (₧) — 1 чякө = 10 ₧</option>
        </select>

        <button type="submit">Рәәстрачә жәколә</button>
    </form>
</div>
</body>
</html>