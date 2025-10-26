<?php
// make_chat.php
include 'components/php/db_connect.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!function_exists('check_login')) {
    function check_login() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }
    }
}
check_login(); 

$error = ''; 
$success_message = '';
$current_user_id = $_SESSION['user_id']; 
$target_dir = "uploads/chat_avatars/"; 
$default_avatar_url = 'components/media/images/chat.png';

if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        $error = "Ошибка сервера: Не удалось создать папку для загрузки аватаров. Обратитесь к администратору.";
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) { 

    $chat_name = trim($_POST['chat_name']);
    $avatar_url = $default_avatar_url; 
    // НОВОЕ: Получаем статус приватности. Если чекбокс отмечен, будет 'on', иначе - не передастся.
    $is_private = isset($_POST['is_private']) ? 1 : 0; 

    if (!empty($chat_name)) {
        
        if (isset($_FILES["chat_avatar"]) && $_FILES["chat_avatar"]["error"] == 0) {
            
            $file_name = basename($_FILES["chat_avatar"]["name"]);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $unique_file_name = uniqid('chat_') . '.' . $file_extension;
            $target_file = $target_dir . $unique_file_name;
            $uploadOk = 1;

            $check = getimagesize($_FILES["chat_avatar"]["tmp_name"]);
            if ($check === false) {
                $error = "Загруженный файл не является изображением.";
                $uploadOk = 0;
            }

            if ($_FILES["chat_avatar"]["size"] > 500000) {
                $error = "Размер файла слишком большой (макс. 500KB).";
                $uploadOk = 0;
            }

            if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif") {
                $error = "Разрешены только JPG, JPEG, PNG и GIF файлы.";
                $uploadOk = 0;
            }

            if ($uploadOk == 1) {
                if (move_uploaded_file($_FILES["chat_avatar"]["tmp_name"], $target_file)) {
                    $avatar_url = $target_file;
                } else {
                    $error = "Ошибка при загрузке файла.";
                }
            }
        }
        
        if (empty($error)) {
            $sql_check_name = "SELECT chat_id FROM chats WHERE chat_name = ?";
            $stmt_check = $conn->prepare($sql_check_name);
            $stmt_check->bind_param("s", $chat_name);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows == 0) {
                $stmt_check->close();

                // ИЗМЕНЕНИЕ SQL: Добавлен is_private
                $sql_insert_chat = "INSERT INTO chats (chat_name, avatar_url, is_private) VALUES (?, ?, ?)";
                $stmt_insert_chat = $conn->prepare($sql_insert_chat);
                // ИЗМЕНЕНИЕ BIND: Добавлен int-параметр для is_private
                $stmt_insert_chat->bind_param("ssi", $chat_name, $avatar_url, $is_private); 
                
                if ($stmt_insert_chat->execute()) {
                    $new_chat_id = $conn->insert_id; 
                    $stmt_insert_chat->close();

                    $sql_add_user_admin = "INSERT INTO user_chats (user_id, chat_id, is_admin) VALUES (?, ?, 1)";
                    $stmt_user = $conn->prepare($sql_add_user_admin);

                    if ($stmt_user === false) {
                         $error = "Ошибка сервера: Не удалось подготовить запрос для установки прав администратора. Возможно, в таблице user_chats отсутствует столбец is_admin.";
                    } else {
                        $stmt_user->bind_param("ii", $current_user_id, $new_chat_id);
                        if ($stmt_user->execute()) {
                            $success_message = "Чат '$chat_name' успешно создан. Вы назначены администратором.";
                            header("Location: chat.php?chat_id={$new_chat_id}");
                            exit();

                        } else {
                            $error = "Ошибка при добавлении создателя в чат.";
                        }
                        $stmt_user->close();
                    }
                } else {
                    $error = "Ошибка базы данных при создании чата: " . $conn->error;
                }

            } else {
                $error = "Название комнаты занято.";
                $stmt_check->close();
            }
        }
    } else {
        $error = "Введите название комнаты.";
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
    <style>
        /* Стиль для чекбокса, чтобы он выглядел хорошо */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
    </style>
    <script>
        if (window.self === window.top) {
            window.location = 'index.php';
        }
    </script>
</head>
<body>
    <header class="chat-header">
        <h1>Создание чата</h1>
    </header>
    <div class="selection-container">
        <h2>Создать новую комнату</h2>
        <h3>Придумайте уникальное имя и задайте аватар</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <form method="POST" class="form-style" enctype="multipart/form-data"> 
            <label for="chat_name">Название чата:</label>
            <input type="text" id="chat_name" name="chat_name" required placeholder="Например, 'Общие вопросы'"><br>
            
            <label for="chat_avatar">Аватар чата (необязательно):</label>
            <input type="file" id="chat_avatar" name="chat_avatar" accept="image/*"><br>
            
            <div class="checkbox-container">
                <input type="checkbox" id="is_private" name="is_private" value="1">
                <label for="is_private" style="margin: 0; font-weight: normal;">Сделать чат приватным (вход только по приглашению)</label>
            </div>
            
            <button type="submit" class="button-style">Создать чат</button>
        </form>
    </div>
</body>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</html>