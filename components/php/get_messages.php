<?php
include 'db_connect.php'; 
check_login();

$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;

if ($chat_id > 0) {
    $sql = "SELECT m.id, m.user_id, m.message, m.post_date, u.username 
            FROM messages m 
            JOIN users u ON m.user_id = u.user_id
            WHERE m.chat_id = $chat_id
            ORDER BY m.id ASC LIMIT 20";
            
    $result = $conn->query($sql);
    $messages = [];

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $messages[] = [
                'message_id' => $row["id"],
                'user_id' => $row["user_id"], 
                'username' => htmlspecialchars($row["username"]),
                'message' => htmlspecialchars($row["message"]),
                'time' => date("H:i", strtotime($row["post_date"]))
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($messages);
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}

$conn->close();
?>
