<?php
include 'components/php/db_connect.php'; 
check_login(); 

$current_user_id = $_SESSION['user_id']; 

$sql = "
    SELECT 
        c.chat_id, 
        c.chat_name,
        c.avatar_url          /* <-- НОВОЕ: выбираем аватар */
    FROM 
        chats c
    JOIN 
        user_chats uc ON c.chat_id = uc.chat_id
    WHERE 
        uc.user_id = ?  
    ORDER BY 
        c.chat_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id); 
$stmt->execute();
$result = $stmt->get_result(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose chat</title>
    <link rel="stylesheet" href="components/css/style.css"> 
    <style>
    </style>
</head>
<body>
    <header class="chat-header">
        <h1>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    </header>
    <div class="selection-container">

        <?php
        if ($result->num_rows > 0) {
            echo "<ul class='chat-list'>";
            while($row = $result->fetch_assoc()) {
                $avatar_url = htmlspecialchars($row['avatar_url']);
                $chat_name = htmlspecialchars($row['chat_name']);
                $chat_id = htmlspecialchars($row['chat_id']);
                
                echo "<li>";
                echo "<a href='chat.php?chat_id={$chat_id}' class='chat-link chat-item'>"; 
                echo "<img style='width: 5vh; height: 5vh; border-radius: 20%;' src='{$avatar_url}' alt='Аватар чата {$chat_name}' class='chat-list-avatar'>"; 
                echo "<span class='chat-name-text'>{$chat_name}</span>";
                echo "</a></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>You don't have any chats yet. Use the link below to find and join chats.</p>";
        }
        ?>
    </div>

    <div class="chat-input-area"> 
        <form id="chat-form" class="chat-form">
            <p class="centered-action">
                <a href="make_chat.php" class="action-link">
                    <span class="icon">✈️</span> Create new chat
                </a>
            </p>
            <p class="centered-action">
                <a href="logout.php" class="action-link logout-link">
                    <span class="icon">🚪</span> Logout
                </a>
            </p>
        </form>
    </div>
    </body>
</html>