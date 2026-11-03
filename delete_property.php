<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получаем ID объекта недвижимости для удаления
    $property_id = $_POST['property_id'];

    // Проверяем, что ID не пустой
    if (empty($property_id)) {
        echo "Error: Property ID is required.";
    } else {
        // Поиск объекта недвижимости по ID
        // Если объект найден, его можно удалить из базы данных или файла
        // Например, можно использовать файл для хранения данных и удалить строку с соответствующим ID

        echo "Property with ID $property_id has been deleted.";
    }
} else {
    echo "Error: Invalid request method.";
}
?>
