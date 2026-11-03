<?php
// ================================================================
// Удаление объекта недвижимости из properties.txt
// Формат строки: название|ID|адрес|площадь|провинция|год|цена|валюта
// ================================================================

define('PROPERTIES_FILE', __DIR__ . '/properties.txt');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $property_id = trim($_POST['property_id'] ?? '');

    if ($property_id === '') {
        echo "Error: Property ID is required.";
        exit;
    }

    if (!file_exists(PROPERTIES_FILE)) {
        echo "Error: Файл объектов не найден.";
        exit;
    }

    $lines = file(PROPERTIES_FILE, FILE_IGNORE_NEW_LINES);
    $kept = [];
    $removed = null;

    foreach ($lines as $line) {
        if (trim((string)$line) === '') continue;
        $d = explode('|', $line);
        // Совпадение по ID (поле 1) или по названию (поле 0)
        if (isset($d[1]) && ($d[1] === $property_id || $d[0] === $property_id)) {
            $removed = $d[0];
            continue; // исключаем объект из файла
        }
        $kept[] = $line;
    }

    if ($removed === null) {
        echo "Error: Объект с ID '$property_id' не найден.";
        exit;
    }

    $content = $kept ? implode("\n", $kept) . "\n" : "";
    if (file_put_contents(PROPERTIES_FILE, $content, LOCK_EX) !== false) {
        echo "Property with ID $property_id ('$removed') has been deleted.";
    } else {
        echo "Error: Не удалось сохранить файл.";
    }
} else {
    echo "Error: Invalid request method.";
}
