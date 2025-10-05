<?php
include 'components/php/db_connect.php'; 
check_login(); 

$error = ''; 
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

    if (!empty($chat_name)) {
        
        if (isset($_FILES["chat_avatar"]) && $_FILES["chat_avatar"]["error"] == 0) {
            
            $file_name = basename($_FILES["chat_avatar"]["name"]);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $unique_file_name = uniqid('chat_') . '.' . $file_extension;
            $target_file = $target_dir . $unique_file_name;
            $uploadOk = 1;

            $check = getimagesize($_FILES["chat_avatar"]["tmp_name"]);
            if($check === false) {
                $error = "Файл не является изображением.";
                $uploadOk = 0;
            }

            if ($_FILES["chat_avatar"]["size"] > 5000000) { 
                $error = "Извините, ваш файл слишком большой.";
                $uploadOk = 0;
            }

            if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg"
            && $file_extension != "gif" ) {
                $error = "Разрешены только JPG, JPEG, PNG и GIF файлы.";
                $uploadOk = 0;
            }

            if ($uploadOk) {
                if (move_uploaded_file($_FILES["chat_avatar"]["tmp_name"], $target_file)) {
                    $avatar_url = $target_file;
                } else {
                    $error = "Извините, произошла ошибка при загрузке файла. Возможно, нет прав записи.";
                }
            }
        }
        if (empty($error)) {
            
            $sql = "INSERT INTO chats (chat_name, avatar_url) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $chat_name, $avatar_url);
            
            if ($stmt->execute()) {
                
                $new_chat_id = $conn->insert_id; 
                $stmt->close();
                
                $sql_subscribe = "INSERT INTO user_chats (user_id, chat_id) VALUES (?, ?)";
                $stmt_subscribe = $conn->prepare($sql_subscribe);
                $stmt_subscribe->bind_param("ii", $current_user_id, $new_chat_id);
                
                if ($stmt_subscribe->execute()) {
                    echo "<script>window.top.location.reload();</script>";
                    exit();
                } else {
                    $error = "Ошибка при добавлении в комнату. Попробуйте еще раз.";
                }

                $stmt_subscribe->close();

            } else {
                $error = "Ошибка при создании комнаты. Возможно, название слишком длинное или занято.";
                $stmt->close();
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
    <title>New chat</title>
    <link rel="stylesheet" href="components/css/style.css"> 
    <script>
        if (window.self === window.top) {
            window.location = 'index.php';
        }
    </script>
</head>
<body>
    <header class="chat-header">
        <h1>New chat</h1>
    </header>
    <div class="selection-container">
        <h2>Create a new chat room</h2>
        <h3>Come up with a unique name and set an avatar</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <form method="POST" class="form-style" enctype="multipart/form-data"> 
            <label for="chat_name">Chat room name:</label>
            <input type="text" id="chat_name" name="chat_name" required placeholder="For example, 'General Questions'"><br>
            
            <label for="chat_avatar">Chat avatar (optional):</label>
            <input type="file" id="chat_avatar" name="chat_avatar" accept="image/*"><br>

            <button type="submit" class="submit-button">Create</button>
        </form>

        <p class="back-link-area"><a href="index.php" class="action-link">Back to chat selection</a></p>
    </div>
</body>
</html>