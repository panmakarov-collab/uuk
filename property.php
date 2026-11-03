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
    <title>Недвижимость</title>
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h2 { color: #1a1a2e; margin-bottom: 20px; }
        .property { background: #fff; padding: 20px; border-radius: 14px; margin-bottom: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .property h3 { font-size: 16px; color: #1a1a2e; margin-bottom: 8px; }
        .property p { font-size: 13px; color: #5a6470; margin: 2px 0; }
        .property .price { font-size: 18px; font-weight: 700; color: #4facfe; margin: 8px 0; }
        .property .price.peseta { color: #f59e0b; }
        .property button { padding: 8px 16px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #4facfe, #3a7bd5); color: #fff; cursor: pointer; font-size: 13px; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            align-items: center; justify-content: center; z-index: 100; }
        .modal.show { display: flex; }
        .modal-box { background: #fff; padding: 24px; border-radius: 14px; max-width: 400px; width: 90%; }
        .modal-box input { width: 100%; padding: 10px; border: 1px solid #ecebe6;
            border-radius: 10px; font-size: 14px; margin: 10px 0; }
        .modal-box button { padding: 10px 20px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #4facfe, #3a7bd5); color: #fff; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h2>Кашұм маетокёмә</h2>
    <?php
    if (file_exists('properties.txt')) {
        $lines = file('properties.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $d = explode('|', $line);
                if (count($d) < 7) continue;
                $currency = normalize_currency($d[7] ?? CURRENCY_CHAKO);
                $sym = $currency === CURRENCY_PESETA ? '₧' : 'чякө';
                echo '<div class="property">';
                echo '<h3>' . htmlspecialchars($d[0]) . '</h3>';
                echo '<p>ID: ' . htmlspecialchars($d[1]) . '</p>';
                echo '<p>Чұсо: ' . htmlspecialchars($d[2]) . '</p>';
                echo '<p>Площади: ' . htmlspecialchars($d[3]) . ' м²</p>';
                echo '<p>Ишұбар: ' . htmlspecialchars($d[4]) . '</p>';
                echo '<p>Зұт: ' . htmlspecialchars($d[5]) . '</p>';
                $cls = $currency === CURRENCY_PESETA ? 'price peseta' : 'price';
                echo '<div class="' . $cls . '">' . number_format((float)$d[6], 2) . ' ' . $sym . '</div>';
                echo '<button onclick="openPurchaseModal(\'' . htmlspecialchars($d[0], ENT_QUOTES) . '\')">Кұпити</button>';
                echo '</div>';
            }
        } else {
            echo '<p>Объектов нет</p>';
        }
    }
    ?>
</div>

<div class="modal" id="purchaseModal">
    <div class="modal-box">
        <h3>Мәшәфанө нәмөр жәколә</h3>
        <form method="post" action="buy_property.php">
            <input type="text" id="purchaseAccount" name="accountNumber" placeholder="Номер счёта" required>
            <input type="hidden" id="purchaseProperty" name="propertyToBuy">
            <button type="submit">Кұпити</button>
            <button type="button" onclick="closeModal()" style="background:#ecebe6;color:#1a1a2e;margin-left:8px;">Отмена</button>
        </form>
    </div>
</div>

<script>
    function openPurchaseModal(name) {
        document.getElementById('purchaseProperty').value = name;
        document.getElementById('purchaseModal').classList.add('show');
    }
    function closeModal() {
        document.getElementById('purchaseModal').classList.remove('show');
    }
</script>
</body>
</html>