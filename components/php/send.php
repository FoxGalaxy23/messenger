<?php

include 'db_connect.php'; 
check_login();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405); 
    exit();
}

$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$message = isset($_POST['message']) ? $conn->real_escape_string(trim($_POST['message'])) : '';
$user_id = $_SESSION['user_id'];

if ($chat_id > 0 && !empty($message)) {
    $sql = "INSERT INTO messages (user_id, chat_id, message) VALUES ($user_id, $chat_id, '$message')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500); 
        echo json_encode(['status' => 'error', 'message' => 'DB error']);
    }
} else {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
$conn->close();
?>