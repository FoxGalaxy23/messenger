<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';


class TypingServer implements MessageComponentInterface {
protected $clients;


public function __construct() {
$this->clients = new \SplObjectStorage();
echo "TypingServer создан.\n";
}


public function onOpen(ConnectionInterface $conn) {
// можно передать chat_id и user в query: ws://host:8080/ws?chat_id=123&user=Misha
$query = $conn->httpRequest->getUri()->getQuery();
parse_str($query, $params);
$conn->chat_id = $params['chat_id'] ?? null;
$conn->user = $params['user'] ?? 'unknown';


$this->clients->attach($conn);
echo "Новое подключение: user={$conn->user}, chat={$conn->chat_id}\n";
}


public function onMessage(ConnectionInterface $from, $msg) {
$data = json_decode($msg, true);
if (!$data) return;


// Простейшая валидация
$type = $data['type'] ?? null;
$chat_id = $data['chat_id'] ?? ($from->chat_id ?? null);
$user = $data['user'] ?? ($from->user ?? 'unknown');


// Подготовим сообщение для рассылки (можно оставить как пришло)
$out = json_encode([
'type' => $type,
'chat_id' => $chat_id,
'user' => $user,
'ts' => time(),
]);


// Рассылка только участникам того же chat_id
foreach ($this->clients as $client) {
if ($client === $from) continue; // не шлём обратно
if (($client->chat_id ?? null) !== $chat_id) continue; // только те же чаты
$client->send($out);
}
}


public function onClose(ConnectionInterface $conn) {
$this->clients->detach($conn);
echo "Отключение: user={$conn->user}, chat={$conn->chat_id}\n";
}


public function onError(ConnectionInterface $conn, \Exception $e) {
echo "Ошибка: {$e->getMessage()}\n";
$conn->close();
}
}


// Запуск сервера через Ratchet App
$app = new Ratchet\App('0.0.0.0', 8080);
$app->route('/ws', new TypingServer(), ['*']);
$app->run();
?>