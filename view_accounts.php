<?php
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

function normalize_currency($cur) {
    return $cur === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тұоң жәколәв</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h2 { color: #1a1a2e; margin-bottom: 20px; }
        .account { background: #fff; padding: 20px; border-radius: 14px; margin-bottom: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .account h3 { font-size: 16px; color: #1a1a2e; margin-bottom: 8px; }
        .account p { font-size: 13px; color: #5a6470; margin: 2px 0; }
        .account p b { color: #1a1a2e; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; margin-left: 6px; }
        .badge.chako { background: rgba(79,172,254,0.12); color: #1565c0; }
        .badge.peseta { background: rgba(245,158,11,0.15); color: #b45309; }
    </style>
</head>
<body>
<div class="container">
    <h2>Тұоң жәколәв</h2>
    <?php
    if (is_dir('accounts')) {
        foreach (scandir('accounts') as $file) {
            if ($file === '.' || $file === '..') continue;
            $data = file('accounts/' . $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($data) < 3) continue;
            $account_number = basename($file, '.txt');
            $currency = normalize_currency($data[4] ?? CURRENCY_CHAKO);
            $badge = $currency === CURRENCY_PESETA
                ? '<span class="badge peseta">₧ песета</span>'
                : '<span class="badge chako">чякө</span>';
            $sym = $currency === CURRENCY_PESETA ? '₧' : 'чякө';
            echo '<div class="account">';
            echo '<h3>Нәмөр жәколә: ' . htmlspecialchars($account_number) . ' ' . $badge . '</h3>';
            echo '<p>Нөмә онровё: <b>' . htmlspecialchars($data[0]) . '</b></p>';
            echo '<p>Нөмә пәнкә: <b>' . htmlspecialchars($data[1]) . '</b></p>';
            echo '<p>Баланс: <b>' . number_format((float)$data[2], 2) . ' ' . $sym . '</b></p>';
            echo '<p>Көлерә жәколә: <b>' . htmlspecialchars($data[3]) . '</b></p>';
            echo '</div>';
        }
    }
    ?>
</div>
</body>
</html>