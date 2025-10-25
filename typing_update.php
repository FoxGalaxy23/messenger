<?php
// typing_update.php
// Обновляет статус "печатает" пользователя

// Параметры
$chatId = $_GET['chat_id'] ?? 'default';
$user   = $_GET['user'] ?? 'anon';
$type   = $_GET['type'] ?? 'typing'; // typing | stop

$storage = __DIR__ . "/typing_status.json";

// Читаем текущее состояние
$data = [];
if (file_exists($storage)) {
    $data = json_decode(file_get_contents($storage), true);
    if (!is_array($data)) $data = [];
}

// Если ещё нет секции для чата — создаём
if (!isset($data[$chatId])) {
    $data[$chatId] = [];
}

// Обновляем статус
if ($type === 'typing') {
    $data[$chatId][$user] = time();
} else {
    unset($data[$chatId][$user]);
}

// Сохраняем
file_put_contents($storage, json_encode($data));

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>