<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($chat_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid chat_id']);
    exit();
}

$MAX_FILES = 5;
$MAX_FILE_SIZE = 10 * 1024 * 1024;
$ALLOWED_EXT = [
    'jpg','jpeg','png','gif','webp','mp4','webm','mov','pdf','txt','mp3'
];
$ALLOWED_MIME = [
    'image/jpeg','image/png','image/gif','image/webp',
    'video/mp4','video/webm','video/quicktime',
    'application/pdf','text/plain','audio/mpeg'
];

function human_upload_error($code){
    $map = [
        UPLOAD_ERR_OK => 'No error',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    return $map[$code] ?? 'Unknown upload error';
}

$hasFiles = isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'][0]);
if (empty($message_text) && !$hasFiles) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data (no text and no files)']);
    exit();
}

$docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$upload_base_dir = $docroot . '/uploads/chats/' . $chat_id . '/';

$public_base = '/uploads/chats/' . $chat_id . '/';

$conn->begin_transaction();
try {
    $sql_msg = "INSERT INTO messages (user_id, chat_id, message) VALUES (?, ?, ?)";
    $stmt_msg = $conn->prepare($sql_msg);
    if (!$stmt_msg) throw new Exception("Prepare message failed: " . $conn->error);
    $stmt_msg->bind_param('iis', $user_id, $chat_id, $message_text);
    if (!$stmt_msg->execute()) throw new Exception("Execute message failed: " . $stmt_msg->error);
    $message_id = $conn->insert_id;
    $stmt_msg->close();

    if ($hasFiles) {
        $file_count = count($_FILES['media_files']['name']);
        if ($file_count > $MAX_FILES) throw new Exception("Too many files (max {$MAX_FILES})");

        if (!is_dir($upload_base_dir)) {
            if (!mkdir($upload_base_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory: {$upload_base_dir}");
            }
        }

        $sql_media = "INSERT INTO message_media (message_id, file_path, file_type, is_deleted) VALUES (?, ?, ?, 0)";
        $stmt_media = $conn->prepare($sql_media);
        if (!$stmt_media) throw new Exception("Prepare media failed: " . $conn->error);

        for ($i = 0; $i < $file_count; $i++) {
            $orig_name = $_FILES['media_files']['name'][$i];
            $tmp_name  = $_FILES['media_files']['tmp_name'][$i];
            $error     = $_FILES['media_files']['error'][$i];
            $size      = $_FILES['media_files']['size'][$i];
            $type      = $_FILES['media_files']['type'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error for file '{$orig_name}': " . human_upload_error($error));
            }
            if ($size > $MAX_FILE_SIZE) {
                throw new Exception("File '{$orig_name}' exceeds max size ({$MAX_FILE_SIZE} bytes)");
            }

            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED_EXT)) {
                throw new Exception("File '{$orig_name}' has disallowed extension '{$ext}'");
            }
            if (!in_array($type, $ALLOWED_MIME)) {
                error_log("Warning: MIME type '{$type}' for file '{$orig_name}' is not in allowed list");
            }

            $unique_name = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest_path = $upload_base_dir . $unique_name;
            $public_path = $public_base . $unique_name;

            if (!move_uploaded_file($tmp_name, $dest_path)) {
                throw new Exception("Failed to move uploaded file '{$orig_name}' to '{$dest_path}'");
            }

            @chmod($dest_path, 0644);

            $stmt_media->bind_param('iss', $message_id, $public_path, $type);
            if (!$stmt_media->execute()) {
                throw new Exception("Insert media failed: " . $stmt_media->error);
            }
        }
        $stmt_media->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message_id' => $message_id]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("send.php transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB/Upload error: ' . $e->getMessage()]);
}

$conn->close();
