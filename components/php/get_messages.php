<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

include 'db_connect.php';

if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid chat ID.']);
    exit();
}

$chat_id = (int)$_GET['chat_id'];

$sql_messages = "
    SELECT 
        m.id AS message_id, 
        m.user_id, 
        m.message, 
        u.username, 
        u.avatar_url, 
        DATE_FORMAT(m.post_date, '%H:%i') AS time,
        m.reply_to,
        m.reply_snapshot
    FROM messages m
    JOIN users u ON m.user_id = u.user_id
    WHERE m.chat_id = ?
    ORDER BY m.post_date ASC
";

$stmt_messages = $conn->prepare($sql_messages);
if (!$stmt_messages) {
    error_log("Prepare failed get_messages: " . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
    exit();
}
$stmt_messages->bind_param("i", $chat_id);
$stmt_messages->execute();
$result_messages = $stmt_messages->get_result();
$messages = [];
$message_ids = [];

if ($result_messages) {
    while ($row = $result_messages->fetch_assoc()) {
        $message_id = $row['message_id'];
        $messages[$message_id] = $row;
        $messages[$message_id]['media'] = [];
        $message_ids[] = $message_id;
    }
} else {
    error_log("SQL Error: " . $conn->error);
}

if (!empty($message_ids)) {
    $ids_list = implode(',', array_map('intval', $message_ids));
    $sql_media = "
        SELECT 
            message_id, 
            file_path, 
            file_type 
        FROM 
            message_media 
        WHERE 
            message_id IN ($ids_list) AND is_deleted = 0
    ";
    $result_media = $conn->query($sql_media);
    if ($result_media) {
        while ($media_row = $result_media->fetch_assoc()) {
            $msg_id = $media_row['message_id'];
            if (isset($messages[$msg_id])) {
                $messages[$msg_id]['media'][] = [
                    'path' => $media_row['file_path'],
                    'type' => $media_row['file_type']
                ];
            }
        }
    } else {
        error_log("Media query failed: " . $conn->error);
    }
}

foreach ($messages as &$m) {
    if (!empty($m['reply_snapshot'])) {
        $decoded = json_decode($m['reply_snapshot'], true);
        $m['reply_snapshot'] = $decoded !== null ? $decoded : $m['reply_snapshot'];
    } else {
        $m['reply_snapshot'] = null;
    }
    $m['username'] = htmlspecialchars($m['username']);
    $m['avatar_url'] = $m['avatar_url'] ? htmlspecialchars($m['avatar_url']) : null;
    $m['message'] = $m['message'] !== null ? $m['message'] : '';
    $m['time'] = $m['time'] ?? '';
}

$conn->close();

echo json_encode(array_values($messages), JSON_UNESCAPED_UNICODE);
?>
