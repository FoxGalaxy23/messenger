<?php
// send.php: Обрабатывает отправку текстовых сообщений и медиафайлов.
session_start();
include 'db_connect.php'; // Подключение к базе данных

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не разрешен.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Не авторизован.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// --- НОВАЯ ГЛОБАЛЬНАЯ ПРОВЕРКА НА БАН (USER_BANS): СТАРТ ---
// Проверяем, не забанен ли пользователь ГЛОБАЛЬНО
$sql_global_ban = "
    SELECT 1 
    FROM user_bans 
    WHERE banned_user_id = ? AND is_active = 1 
    AND (ban_end_date IS NULL OR ban_end_date > NOW())
";
$stmt_global_ban = $conn->prepare($sql_global_ban);
$stmt_global_ban->bind_param("i", $user_id);
$stmt_global_ban->execute();
$result_global_ban = $stmt_global_ban->get_result();

if ($result_global_ban->num_rows > 0) {
    $stmt_global_ban->close();
    $conn->close();
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Вам запрещено отправлять сообщения. Ваш аккаунт заблокирован.']);
    exit();
}
$stmt_global_ban->close();
// --- НОВАЯ ГЛОБАЛЬНАЯ ПРОВЕРКА НА БАН (USER_BANS): КОНЕЦ ---


$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
// ID сообщения, на которое отвечаем
$reply_to = isset($_POST['reply_to']) && is_numeric($_POST['reply_to']) ? (int)$_POST['reply_to'] : null; 

if ($chat_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неверный ID чата.']);
    exit();
}

// --- НАСТРОЙКИ ЗАГРУЗКИ ФАЙЛОВ ---
$MAX_FILES = 5;
$MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
$ALLOWED_EXT = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', // Изображения
    'mp4', 'mov', 'webm', // Видео
    'mp3', 'wav', 'ogg', // Аудио
    'pdf', 'txt', 'zip' // Документы/Архивы
];
$ALLOWED_MIME = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/quicktime', 'video/webm',
    'audio/mpeg', 'audio/wav', 'audio/ogg',
    'application/pdf', 'text/plain', 'application/zip'
];

// --- ПРОВЕРКА НА БАН В КОНКРЕТНОМ ЧАТЕ (CHAT_BANS): СТАРТ ---
$sql_ban = "
    SELECT 1 
    FROM chat_bans 
    WHERE banned_user_id = ? AND chat_id = ? AND is_active = 1 
    AND (ban_end_date IS NULL OR ban_end_date > NOW())
";
$stmt_ban = $conn->prepare($sql_ban);
$stmt_ban->bind_param("ii", $user_id, $chat_id);
$stmt_ban->execute();
$result_ban = $stmt_ban->get_result();

if ($result_ban->num_rows > 0) {
    $stmt_ban->close();
    $conn->close();
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Вам запрещено отправлять сообщения в этот чат.']);
    exit();
}
$stmt_ban->close();
// --- ПРОВЕРКА НА БАН В КОНКРЕТНОМ ЧАТЕ (CHAT_BANS): КОНЕЦ ---

// Проверка: есть ли текст ИЛИ есть файлы
$has_files = !empty($_FILES['media_files']['name'][0]);
if (empty($message_text) && !$has_files) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Сообщение не может быть пустым.']);
    exit();
}

// Проверка членства в чате (нельзя отправлять, если не член)
$sql_member_check = "SELECT 1 FROM user_chats WHERE user_id = ? AND chat_id = ?";
$stmt_member = $conn->prepare($sql_member_check);
$stmt_member->bind_param("ii", $user_id, $chat_id);
$stmt_member->execute();
if ($stmt_member->get_result()->num_rows === 0) {
    $stmt_member->close();
    $conn->close();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Вы не состоите в этом чате.']);
    exit();
}
$stmt_member->close();


// --- ПОДГОТОВКА СНИМКА ДЛЯ ОТВЕТА (REPLY SNAPSHOT) ---
$reply_snapshot = null;
if ($reply_to) {
    $sql_reply_snapshot = "
        SELECT 
            m.message, 
            u.username,
            (SELECT COUNT(*) FROM message_media WHERE message_id = m.id AND is_deleted = 0) AS media_count
        FROM messages m
        JOIN users u ON m.user_id = u.user_id
        WHERE m.id = ? AND m.chat_id = ?
    ";
    $stmt_reply = $conn->prepare($sql_reply_snapshot);
    $stmt_reply->bind_param("ii", $reply_to, $chat_id);
    $stmt_reply->execute();
    $result_reply = $stmt_reply->get_result();

    if ($result_reply->num_rows > 0) {
        $reply_data = $result_reply->fetch_assoc();
        $reply_snapshot = json_encode([
            'username' => $reply_data['username'],
            // Ограничиваем текст ответа для snapshot
            'message' => mb_substr(strip_tags($reply_data['message']), 0, 100), 
            'media_count' => $reply_data['media_count']
        ]);
    } else {
        // Если сообщения нет или оно не в этом чате, сбрасываем $reply_to
        $reply_to = null;
    }
    $stmt_reply->close();
}


// --- НАЧАЛО ТРАНЗАКЦИИ ---
$conn->begin_transaction();
$message_id = null;
$error = null;

try {
    // 1. Вставка основного сообщения
    $sql_insert_message = "
        INSERT INTO messages (chat_id, user_id, message, reply_to, reply_snapshot) 
        VALUES (?, ?, ?, ?, ?)
    ";
    
    $stmt_message = $conn->prepare($sql_insert_message);
    $stmt_message->bind_param("iisss", $chat_id, $user_id, $message_text, $reply_to, $reply_snapshot);
    
    if (!$stmt_message->execute()) {
        throw new Exception("Insert message failed: " . $stmt_message->error);
    }
    
    $message_id = $conn->insert_id;
    $stmt_message->close();

    // 2. Вставка записи о прочтении для самого отправителя
    $sql_receipt = "
        INSERT INTO message_receipts (message_id, user_id, read_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE read_at = NOW()
    ";
    $stmt_receipt = $conn->prepare($sql_receipt);
    $stmt_receipt->bind_param("ii", $message_id, $user_id);
    if (!$stmt_receipt->execute()) {
         // Это не критическая ошибка, но стоит записать в лог
         error_log("Failed to insert self-receipt for message {$message_id}: " . $stmt_receipt->error);
    }
    $stmt_receipt->close();
    
    // 3. Обработка медиафайлов, если они есть
    if ($has_files) {
        $files_to_upload = $_FILES['media_files'];
        $count = count($files_to_upload['name']);

        if ($count > $MAX_FILES) {
            throw new Exception("Слишком много файлов. Максимум: {$MAX_FILES}.");
        }

        // --- ИЗМЕНЕННЫЕ ПУТИ ДЛЯ ОТПРАВКИ В КОРЕНЬ СЕРВЕРА С УЧЕТОМ ID ЧАТА ---
        // __DIR__ . '/../../' - это поднимет на два уровня выше (предполагая, что send.php в подпапке /api/ или /handlers/)
        // и создаст путь к папке /uploads/chats/{chat_id}/
        $upload_base_dir = __DIR__ . '/../../uploads/chats/' . $chat_id . '/'; 
        // Путь, который будет использоваться в HTML/браузере (от корня сайта)
        $public_base = '/uploads/chats/' . $chat_id . '/'; 
        // --- КОНЕЦ ИЗМЕНЕННЫХ ПУТЕЙ ---
        
        if (!is_dir($upload_base_dir) && !mkdir($upload_base_dir, 0755, true)) {
            throw new Exception("Не удалось создать директорию для загрузки: " . $upload_base_dir);
        }
        
        $sql_insert_media = "
            INSERT INTO message_media (message_id, file_path, file_type) 
            VALUES (?, ?, ?)
        ";
        $stmt_media = $conn->prepare($sql_insert_media);
        
        for ($i = 0; $i < $count; $i++) {
            $orig_name = $files_to_upload['name'][$i];
            $tmp_name = $files_to_upload['tmp_name'][$i];
            $size = $files_to_upload['size'][$i];
            $type = $files_to_upload['type'][$i]; // MIME type

            if ($files_to_upload['error'][$i] !== UPLOAD_ERR_OK) {
                // Пропускаем или обрабатываем ошибки загрузки (например, UPLOAD_ERR_NO_FILE)
                continue; 
            }

            if ($size > $MAX_FILE_SIZE) {
                throw new Exception("Файл '{$orig_name}' слишком большой (макс. " . round($MAX_FILE_SIZE / 1024 / 1024, 2) . " MB).");
            }

            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED_EXT)) {
                throw new Exception("Файл '{$orig_name}' имеет запрещенное расширение '{$ext}'.");
            }
            if (!in_array($type, $ALLOWED_MIME)) {
                error_log("Warning: MIME type '{$type}' for file '{$orig_name}' is not in allowed list");
            }

            // Создаем уникальное имя файла
            $unique_name = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest_path = $upload_base_dir . $unique_name;
            $public_path = $public_base . $unique_name;

            // Перемещаем загруженный файл
            if (!move_uploaded_file($tmp_name, $dest_path)) {
                throw new Exception("Не удалось переместить загруженный файл '{$orig_name}'.");
            }

            // Устанавливаем права доступа
            @chmod($dest_path, 0644);

            // Сохраняем метаданные медиафайла в БД
            $stmt_media->bind_param('iss', $message_id, $public_path, $type);
            if (!$stmt_media->execute()) {
                throw new Exception("Insert media failed: " . $stmt_media->error);
            }
        }
        $stmt_media->close();
    }

    // Если все прошло успешно, фиксируем транзакцию
    $conn->commit();
    echo json_encode(['status' => 'success', 'message_id' => $message_id]);
    
    // После успешной отправки можно отправить уведомление через WebSocket (если он настроен)
    if (isset($message_id)) {
        // Здесь должен быть код для отправки WS-уведомления на ваш сервер, например:
        // send_websocket_notification(['type' => 'new_message', 'chat_id' => $chat_id, 'message_id' => $message_id]);
    }

} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    $conn->rollback();
    $error = $e->getMessage();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $error]);
} finally {
    $conn->close();
}
?>