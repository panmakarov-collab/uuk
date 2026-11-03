<?php
$accounts_dir = "accounts";
$accounts = [];

if (is_dir($accounts_dir)) {
    $files = scandir($accounts_dir);

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $data = explode('|', file_get_contents("$accounts_dir/$file"));
            $accounts[] = [
                'organization' => $data[0],
                'bank' => $data[1],
                'balance' => $data[2],
                'payday' => trim($data[3]),
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($accounts);
?>
