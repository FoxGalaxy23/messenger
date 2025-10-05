<?php
include 'db_connect.php'; 

check_login(); 

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id']; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['chat_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request or missing chat ID.']);
    exit;
}

$chat_id = filter_var($_POST['chat_id'], FILTER_VALIDATE_INT);

if ($chat_id === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid chat ID format.']);
    exit;
}

$conn->begin_transaction();

try {
    $sql_unsubscribe = "DELETE FROM user_chats WHERE user_id = ? AND chat_id = ?";
    $stmt_unsubscribe = $conn->prepare($sql_unsubscribe);
    $stmt_unsubscribe->bind_param("ii", $current_user_id, $chat_id);
    $stmt_unsubscribe->execute();
    $rows_unsubscribed = $stmt_unsubscribe->affected_rows;
    $stmt_unsubscribe->close();

    if ($rows_unsubscribed === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'User was not a member of this chat or chat does not exist.']);
        exit;
    }

    $sql_count = "SELECT COUNT(*) AS remaining_members FROM user_chats WHERE chat_id = ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $chat_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $remaining_members = $row_count['remaining_members'];
    $stmt_count->close();

    if ($remaining_members == 0) {
        $sql_delete_chat = "DELETE FROM chats WHERE chat_id = ?";
        $stmt_delete_chat = $conn->prepare($sql_delete_chat);
        $stmt_delete_chat->bind_param("i", $chat_id);
        $stmt_delete_chat->execute();
        $stmt_delete_chat->close();
        
    }

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Successfully unsubscribed from chat.',
        'chat_deleted' => ($remaining_members == 0)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Database error during unsubscribe: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>