<?php
include 'components/php/db_connect.php';
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$current_user_id = $_SESSION['user_id'];

$error = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];

    // ===== Создание сервера =====
    if ($type === "server") {
        $name = trim($_POST['server_name']);
        $logo = "components/media/images/server.png";

        if (!empty($_FILES['server_logo']['name'])) {
            $upload_dir = "uploads/server_logos/";
            @mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['server_logo']['name'], PATHINFO_EXTENSION);
            $file = uniqid("srv_") . "." . $ext;
            $path = $upload_dir . $file;
            if (move_uploaded_file($_FILES['server_logo']['tmp_name'], $path)) $logo = $path;
        }

        $stmt = $conn->prepare("INSERT INTO servers (name, owner_id, logo) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $name, $current_user_id, $logo);
        $stmt->execute() ? $success_message = "Сервер создан" : $error = $conn->error;
    }

    // ===== Создание секции =====
    if ($type === "section") {
        $server_id = intval($_POST['server_id']);
        $name = trim($_POST['section_name']);
        $stmt = $conn->prepare("INSERT INTO sections (server_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $server_id, $name);
        $stmt->execute() ? $success_message = "Секция создана" : $error = $conn->error;
    }

    // ===== Создание чата =====
    if ($type === "chat") {
        $chat_name = trim($_POST['chat_name']);
        $avatar = "components/media/images/chat.png";

        if (!empty($_FILES['chat_avatar']['name'])) {
            $upload_dir = "uploads/chat_avatars/";
            @mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['chat_avatar']['name'], PATHINFO_EXTENSION);
            $file = uniqid("chat_") . "." . $ext;
            $path = $upload_dir . $file;
            if (move_uploaded_file($_FILES['chat_avatar']['tmp_name'], $path)) $avatar = $path;
        }

        // 1. создаём чат
        $stmt = $conn->prepare("INSERT INTO chats (chat_name, avatar_url) VALUES (?, ?)");
        $stmt->bind_param("ss", $chat_name, $avatar);
        if ($stmt->execute()) {
            $chat_id = $conn->insert_id;

            // 2. привязываем его через chat_links
            $server_id = !empty($_POST['server_id']) ? intval($_POST['server_id']) : NULL;
            $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : NULL;
            $position = 0;
            $stmt2 = $conn->prepare("INSERT INTO chat_links (chat_id, server_id, section_id, position) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiii", $chat_id, $server_id, $section_id, $position);
            $stmt2->execute();

            // 3. добавляем создателя в user_chats
            $stmt3 = $conn->prepare("INSERT INTO user_chats (user_id, chat_id, is_admin) VALUES (?, ?, 1)");
            $stmt3->bind_param("ii", $current_user_id, $chat_id);
            $stmt3->execute();

            $success_message = "Чат создан и прикреплён";
        } else {
            $error = $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый чат</title>
    <link rel="stylesheet" href="components/css/style.css"> 
    <script>
        if (window.self === window.top) {
            window.location = 'index.php';
        }
    </script>
</head>
<body>
    <header class="chat-header">
        <h1>Создание сервера</h1>
    </header>
<form method="POST" enctype="multipart/form-data">
    <label>Что создать:</label>
    <select name="type" id="create_type" onchange="switchForm(this.value)">
        <option value="server">Сервер</option>
        <option value="section">Секция</option>
        <option value="chat">Чат</option>
    </select>

    <!-- Сервер -->
    <div id="form_server">
        <label>Название сервера:</label>
        <input type="text" name="server_name" required>
        <label>Логотип:</label>
        <input type="file" name="server_logo" accept="image/*">
    </div>

    <!-- Секция -->
    <div id="form_section" style="display:none">
        <label>ID сервера:</label>
        <input type="number" name="server_id" required>
        <label>Название секции:</label>
        <input type="text" name="section_name" required>
    </div>

    <!-- Чат -->
    <div id="form_chat" style="display:none">
        <label>Название чата:</label>
        <input type="text" name="chat_name" required>
        <label>Аватар:</label>
        <input type="file" name="chat_avatar" accept="image/*">
        <label>ID сервера:</label>
        <input type="number" name="server_id">
        <label>ID секции (опционально):</label>
        <input type="number" name="section_id">
    </div>

    <button type="submit">Создать</button>
</form>

<script>
function switchForm(type) {
    document.getElementById("form_server").style.display = (type === "server") ? "block" : "none";
    document.getElementById("form_section").style.display = (type === "section") ? "block" : "none";
    document.getElementById("form_chat").style.display = (type === "chat") ? "block" : "none";
}
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>
