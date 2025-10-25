<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$chat_id = isset($input['chat_id']) && is_numeric($input['chat_id']) ? (int)$input['chat_id'] : 0;
$message_ids = isset($input['message_ids']) && is_array($input['message_ids']) ? array_map('intval', $input['message_ids']) : [];
$type = isset($input['type']) && in_array($input['type'], ['read']) ? $input['type'] : '';

if ($chat_id <= 0 || empty($message_ids) || empty($type)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

// Check if user is in the chat
$sql_check = "SELECT 1 FROM user_chats WHERE user_id = ? AND chat_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $current_user_id, $chat_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$column = $type === 'read' ? 'read_at' : 'delivered_at';
$placeholders = implode(',', array_fill(0, count($message_ids), '?'));

$sql = "
    INSERT INTO message_receipts (message_id, user_id, $column)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE $column = NOW()
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed send_receipt: " . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}

$conn->begin_transaction();
try {
    foreach ($message_ids as $message_id) {
        $stmt->bind_param("ii", $message_id, $current_user_id);
        $stmt->execute();
    }
    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("send_receipt.php transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$stmt->close();
$conn->close();
?>