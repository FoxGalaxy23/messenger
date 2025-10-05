<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['is_banned' => false, 'message' => 'Not authorized']);
    exit();
}

include 'db_connect.php';

$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($chat_id <= 0 || $user_id <= 0) {
    echo json_encode(['is_banned' => false, 'message' => 'Invalid IDs']);
    exit();
}

$ban_sql = "
    SELECT 
        1
    FROM 
        chat_bans
    WHERE 
        banned_user_id = ? 
        AND chat_id = ? 
        AND is_active = 1
        AND (ban_end_date IS NULL OR ban_end_date > NOW()) 
    LIMIT 1
";

$ban_stmt = $conn->prepare($ban_sql);
if ($ban_stmt === false) {
    $conn->close();
    echo json_encode(['is_banned' => false, 'message' => 'DB Error']);
    exit();
}

$ban_stmt->bind_param("ii", $user_id, $chat_id);
$ban_stmt->execute();
$ban_result = $ban_stmt->get_result();

$is_banned = $ban_result->num_rows > 0;

$ban_stmt->close();
$conn->close();

echo json_encode(['is_banned' => $is_banned]);
?>
