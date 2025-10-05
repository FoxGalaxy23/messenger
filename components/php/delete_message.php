<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация']);
    exit();
}

include 'db_connect.php'; 

$current_user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;

if ($message_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Неверный ID сообщения']);
    exit();
}

if (!$conn->begin_transaction()) {
    echo json_encode(['status' => 'error', 'message' => 'Не удалось начать транзакцию.']);
    exit();
}


try {
    $sql_fetch_details = "SELECT user_id, chat_id FROM messages WHERE id = ?";
    $stmt_fetch_details = $conn->prepare($sql_fetch_details);
    if ($stmt_fetch_details === false) {
        throw new Exception("SQL prepare failed (fetch details): " . $conn->error);
    }
    $stmt_fetch_details->bind_param("i", $message_id);
    $stmt_fetch_details->execute();
    $result_details = $stmt_fetch_details->get_result();
    $message_row = $result_details->fetch_assoc();
    $stmt_fetch_details->close();

    if (!$message_row) {
        throw new Exception("Сообщение не найдено.");
    }
    
    $message_owner_id = $message_row['user_id'];
    $chat_id = $message_row['chat_id'];

    $is_admin = false;
    $sql_check_admin = "SELECT is_admin FROM user_chats WHERE user_id = ? AND chat_id = ?";
    $stmt_check_admin = $conn->prepare($sql_check_admin);
    if ($stmt_check_admin === false) {
        throw new Exception("SQL prepare failed (check admin): " . $conn->error);
    }
    $stmt_check_admin->bind_param("ii", $current_user_id, $chat_id);
    $stmt_check_admin->execute();
    $admin_result = $stmt_check_admin->get_result();
    $admin_row = $admin_result->fetch_assoc();
    $stmt_check_admin->close();

    if ($admin_row && (int)$admin_row['is_admin'] === 1) {
        $is_admin = true;
    }
    if ($message_owner_id != $current_user_id && !$is_admin) {
        throw new Exception("Недостаточно прав для удаления этого сообщения.");
    }

    $media_files_to_delete = [];
    $sql_get_media = "SELECT file_path FROM message_media WHERE message_id = ?";
    $stmt_get_media = $conn->prepare($sql_get_media);
    if ($stmt_get_media === false) {
        throw new Exception("SQL prepare failed (get media paths): " . $conn->error);
    }
    $stmt_get_media->bind_param("i", $message_id);
    $stmt_get_media->execute();
    $result_media = $stmt_get_media->get_result();
    
    while ($row = $result_media->fetch_assoc()) {
        $full_path = 'uploads/' . basename($row['file_path']);
        $media_files_to_delete[] = $full_path;
    }
    $stmt_get_media->close();

    $sql_delete_media = "DELETE FROM message_media WHERE message_id = ?";
    $stmt_delete_media = $conn->prepare($sql_delete_media);
    if ($stmt_delete_media === false) {
         throw new Exception("SQL prepare failed (delete media): " . $conn->error);
    }
    $stmt_delete_media->bind_param("i", $message_id);
    if (!$stmt_delete_media->execute()) {
         throw new Exception("Ошибка при удалении медиафайлов: " . $stmt_delete_media->error);
    }
    $stmt_delete_media->close();

    $sql_delete_message = "DELETE FROM messages WHERE id = ?";
    $stmt_delete_message = $conn->prepare($sql_delete_message);
    if ($stmt_delete_message === false) {
        throw new Exception("SQL prepare failed (delete message): " . $conn->error);
    }
    $stmt_delete_message->bind_param("i", $message_id);
    if (!$stmt_delete_message->execute()) {
         throw new Exception("Ошибка при удалении сообщения: " . $stmt_delete_message->error);
    }

    if ($stmt_delete_message->affected_rows > 0) {
        $conn->commit();

        foreach ($media_files_to_delete as $file_to_delete) {
            if (file_exists($file_to_delete)) {
                @unlink($file_to_delete);
            }
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Сообщение успешно удалено.']);
    } else {
        throw new Exception("Не удалось удалить запись о сообщении (affected_rows = 0). Возможно, оно уже было удалено.");
    }
    
    $stmt_delete_message->close();

} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>
