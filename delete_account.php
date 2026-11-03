<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получаем номер счета для удаления
    $account_number = $_POST['account_number'];

    // Проверяем, существует ли файл с данными о счете
    $file = 'accounts/' . $account_number . '.txt';
    if (file_exists($file)) {
        // Удаляем файл с данными о счете
        unlink($file);
        echo "Account $account_number has been successfully deleted.";
    } else {
        echo "Error: Account $account_number not found.";
    }
}
    // Перенаправление на главную страницу
    header("Location: index.php");
    exit;
?>
