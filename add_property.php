<?php
define('CURRENCY_CHAKO', 'чякө');
define('CURRENCY_PESETA', 'песета');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $id = trim($_POST['id'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $year_built = trim($_POST['year_built'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $currency = ($_POST['currency'] ?? CURRENCY_CHAKO) === CURRENCY_PESETA ? CURRENCY_PESETA : CURRENCY_CHAKO;

    if (empty($name) || empty($id) || empty($address) || empty($area) || empty($province) || empty($year_built) || empty($price)) {
        echo "Error: All fields are required.";
    } else {
        // name|id|address|area|province|year|price|currency
        $data = "$name|$id|$address|$area|$province|$year_built|$price|$currency\n";
        file_put_contents('properties.txt', $data, FILE_APPEND);
        echo "Property added successfully.";
    }
    header("Location: property.php");
    exit;
}
echo "Error: Invalid request method.";
?>