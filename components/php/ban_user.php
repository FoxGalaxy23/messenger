<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация.']);
    exit();
}

include 'db_connect.php'; 

$current_user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

$chat_id = isset($data['chat_id']) ? (int)$data['chat_id'] : 0;
$banned_user_id = isset($data['banned_user_id']) ? (int)$data['banned_user_id'] : 0;
$reason = isset($data['reason']) ? trim($data['reason']) : 'Не указана';
$duration_code = isset($data['duration']) ? $data['duration'] : 'perm'; 
$action = isset($data['action']) ? $action = $data['action'] : 'ban'; // 'ban' или 'unban'

if ($chat_id <= 0 || $banned_user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Неверные ID чата или пользователя.']);
    exit();
}

if ($banned_user_id === $current_user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Нельзя забанить самого себя.']);
    exit();
}


try {
    $conn->begin_transaction();

    if ($action === 'ban') {
        $ban_end_date = null;

        if ($duration_code !== 'perm') {
            $interval = '';
            switch ($duration_code) {
                case '1h': $interval = '+1 hour'; break;
                case '3h': $interval = '+3 hours'; break;
                case '1d': $interval = '+1 day'; break;
                case '7d': $interval = '+7 days'; break;
                default:
                    break;
            }
            if ($interval) {
                $ban_end_date_obj = new DateTime($interval);
                $ban_end_date = $ban_end_date_obj->format('Y-m-d H:i:s');
            }
        }

        $sql_deactivate_all = "
            UPDATE chat_bans 
            SET is_active = NULL 
            WHERE 
                banned_user_id = ? AND chat_id = ? AND is_active = 1
        ";
        $stmt_deactivate_all = $conn->prepare($sql_deactivate_all);
        if ($stmt_deactivate_all === false) {
            throw new Exception('Ошибка сервера (deactivate all prepare).');
        }
        $stmt_deactivate_all->bind_param("ii", $banned_user_id, $chat_id);
        if (!$stmt_deactivate_all->execute()) {
            throw new Exception('Ошибка базы данных при деактивации старых банов: ' . $stmt_deactivate_all->error);
        }
        $stmt_deactivate_all->close();
        
        $sql_insert_ban = "
            INSERT INTO chat_bans 
                (chat_id, banned_user_id, banner_user_id, ban_reason, ban_end_date, is_active) 
            VALUES 
                (?, ?, ?, ?, ?, 1)
        ";

        $stmt_insert = $conn->prepare($sql_insert_ban);
        if ($stmt_insert === false) {
            throw new Exception('Ошибка сервера (ban insert prepare).');
        }

        $stmt_insert->bind_param("iiiss", $chat_id, $banned_user_id, $current_user_id, $reason, $ban_end_date);

        if (!$stmt_insert->execute()) {
            throw new Exception('Ошибка базы данных при вставке бана: ' . $stmt_insert->error);
        }
        $stmt_insert->close();
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Пользователь успешно забанен.']);

    } elseif ($action === 'unban') {
        $sql_unban = "
            UPDATE chat_bans 
            SET is_active = NULL 
            WHERE 
                banned_user_id = ? AND chat_id = ? AND is_active = 1 AND (ban_end_date IS NULL OR ban_end_date > NOW())
        ";
        
        $stmt_unban = $conn->prepare($sql_unban);
        if ($stmt_unban === false) {
            throw new Exception('Ошибка сервера (unban prepare).');
        }
        $stmt_unban->bind_param("ii", $banned_user_id, $chat_id);
        
        if (!$stmt_unban->execute()) {
            throw new Exception('Ошибка базы данных при разбане: ' . $stmt_unban->error);
        }
        $affected_rows = $stmt_unban->affected_rows;
        $stmt_unban->close();

        $conn->commit();
        
        if ($affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Пользователь успешно разблокирован.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Активный бан не найден, но операция завершена.']);
        }

    } else {
        throw new Exception('Неизвестное действие.');
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log('Ban/Unban Error: ' . $e->getMessage()); 
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>