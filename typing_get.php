<?php
// typing_get.php
// Возвращает список пользователей, которые ещё "печатают"

$chatId = $_GET['chat_id'] ?? 'default';
$storage = __DIR__ . "/typing_status.json";
$timeout = 5; // сколько секунд после последнего события считаем "печатает"

$result = [];

if (file_exists($storage)) {
    $data = json_decode(file_get_contents($storage), true);
    if (isset($data[$chatId])) {
        foreach ($data[$chatId] as $user => $ts) {
            if (time() - $ts <= $timeout) {
                $result[] = $user;
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['typing' => $result]);
