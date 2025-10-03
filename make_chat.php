<?php
include 'components/php/db_connect.php'; 
check_login(); 

$error = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $chat_name = $conn->real_escape_string(trim($_POST['chat_name']));

    if (!empty($chat_name)) {
        $sql = "INSERT INTO chats (chat_name) VALUES ('$chat_name')";
        
        if ($conn->query($sql) === TRUE) {
            header("Location: select_chat.php"); 
            exit();
        } else {
            $error = "Ошибка при создании комнаты. Возможно, название слишком длинное или занято.";
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
</head>
<body>
    <header class="chat-header">
        <h1>New chat</h1>
    </header>
    <div class="selection-container">
        <h2>Create a new chat room</h2>
        <h3>Come up with a unique name</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <form method="POST" class="form-style">
            <label for="chat_name">Chat room name:</label>
            <input type="text" id="chat_name" name="chat_name" required placeholder="For example, 'General Questions'"><br>

            <button type="submit" class="submit-button">Create</button>
        </form>

        <p class="back-link-area"><a href="select_chat.php" class="action-link">Back to chat selection</a></p>
    </div>
</body>
</html>