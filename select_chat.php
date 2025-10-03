<?php
include 'components/php/db_connect.php'; 
check_login(); 

$sql = "SELECT chat_id, chat_name FROM chats ORDER BY chat_name";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select chat</title>
    <link rel="stylesheet" href="components/css/style.css"> 
</head>
<body>
    <header class="chat-header">
        <h1>Hi there <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    </header>
    <div class="selection-container">

        <?php
        if ($result->num_rows > 0) {
            echo "<ul class='chat-list'>";
            while($row = $result->fetch_assoc()) {
                echo "<li><a href='chat.php?chat_id={$row['chat_id']}' class='chat-link'>" . htmlspecialchars($row['chat_name']) . "</a></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Пока нет ни одной комнаты. Создайте первую!</p>";
        }
        ?>
                <div class="chat-actions">
            <p><a href="make_chat.php" class="action-link">Create new chat room</a></p>
            <p><a href="logout.php" class="action-link logout-link">Log out</a></p>
        </div>
    </div>
</body>
</html>