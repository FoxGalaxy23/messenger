<?php
include 'components/php/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    header("Location: index.php");
    exit();
}
$chat_id = (int)$_GET['chat_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join') {
    $sql_check = "SELECT 1 FROM user_chats WHERE user_id = ? AND chat_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $current_user_id, $chat_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        header("Location: chat.php?chat_id={$chat_id}");
        exit();
    }

    $sql_join = "INSERT INTO user_chats (user_id, chat_id) VALUES (?, ?)";
    $stmt_join = $conn->prepare($sql_join);
    $stmt_join->bind_param("ii", $current_user_id, $chat_id);

    if ($stmt_join->execute()) {
        header("Location: chat.php?chat_id={$chat_id}");
        exit();
    } else {
        $message = '<p class="error">Ошибка подписки: ' . htmlspecialchars($conn->error) . '</p>';
    }
}
$sql_details = "
    SELECT 
        c.chat_name, 
        c.avatar_url,
        (SELECT COUNT(*) FROM user_chats WHERE chat_id = c.chat_id) AS participant_count
    FROM 
        chats c
    WHERE 
        c.chat_id = ?
";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $chat_id);
$stmt_details->execute();
$chat_result = $stmt_details->get_result();
$chat_details = $chat_result->fetch_assoc();

if (!$chat_details) {
    header("Location: index.php?error=chat_not_found");
    exit();
}

$chat_name = htmlspecialchars($chat_details['chat_name']);
$chat_avatar = htmlspecialchars($chat_details['avatar_url'] ?? 'default_chat_avatar.png');
$participant_count = (int)$chat_details['participant_count'];

$sql_is_subscribed = "SELECT 1 FROM user_chats WHERE user_id = ? AND chat_id = ?";
$stmt_is_subscribed = $conn->prepare($sql_is_subscribed);
$stmt_is_subscribed->bind_param("ii", $current_user_id, $chat_id);
$stmt_is_subscribed->execute();
$is_subscribed = ($stmt_is_subscribed->get_result()->num_rows > 0);

$conn->close(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite <?php echo $chat_name; ?></title>
    <link rel="stylesheet" href="components/css/style.css">
</head>
<body>

<div class="invite-container">
    <?php echo $message; ?>
    
    <img src="<?php echo $chat_avatar; ?>" alt="Аватар чата" class="invite-avatar">
    
    <h2 class="invite-name"><?php echo $chat_name; ?></h2>
    <p class="invite-count">Members: **<?php echo $participant_count; ?>**</p>
    
    <?php if ($is_subscribed): ?>
        <p>You are **already** a member of this chat.</p>
        <a href="chat.php?chat_id=<?php echo $chat_id; ?>" class="join-button" style="background-color: #28a745;">Go to chat</a>
    <?php else: ?>
        <p>To see messages and start writing, join the chat.</p>
        <form method="POST">
            <input type="hidden" name="action" value="join">
            <button type="submit" class="join-button">Join chat</button>
        </form>
    <?php endif; ?>
    
    <a href="index.php" class="back-link">Back to chat list</a>
</div>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>