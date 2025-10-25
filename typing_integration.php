<?php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

class TypingServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        echo "TypingServer created.\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Query example: ws://host:8080/ws?chat_id=123&user=Misha
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);
        $conn->chat_id = $params['chat_id'] ?? null;
        $conn->user = $params['user'] ?? 'unknown';

        $this->clients->attach($conn);
        echo "New connection: user={$conn->user}, chat={$conn->chat_id}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        $type = $data['type'] ?? null;
        $chat_id = $data['chat_id'] ?? ($from->chat_id ?? null);
        $user = $data['user'] ?? ($from->user ?? 'unknown');

        $out = json_encode([
            'type' => $type,
            'chat_id' => $chat_id,
            'user' => $user,
            'ts' => time(),
        ]);

        // Broadcast only to clients in same chat
        foreach ($this->clients as $client) {
            if ($client === $from) continue;
            if (($client->chat_id ?? null) !== $chat_id) continue;
            $client->send($out);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection closed: user={$conn->user}, chat={$conn->chat_id}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// If the file is executed directly (CLI), start the Ratchet App
if (php_sapi_name() === 'cli' && realpath($argv[0]) === __FILE__) {
    $host = '0.0.0.0';
    $port = 8080;
    echo "Starting TypingServer on {$host}:{$port}\n";
    $app = new App($host, $port);
    $app->route('/ws', new TypingServer(), ['*']);
    $app->run();
    exit;
}