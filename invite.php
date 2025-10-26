<?php
// invite.php
include 'components/php/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];
$message = '';
$chat_id = null;
$chat_name = 'Ошибка';
$chat_avatar = 'components/media/images/chat.png';
$participant_count = 0;
$is_subscribed = false;
$is_private = false;
$mark_code_as_used = false;

// --- ГЛАВНАЯ ЛОГИКА ПРОВЕРКИ ---

if (isset($_GET['code']) && !empty($_GET['code'])) {
    // СЦЕНАРИЙ 1: Вход по ОДНОРАЗОВОМУ КОДУ (Приватный чат)
    $invite_code = $_GET['code'];

    $sql_invite = "
        SELECT 
            ci.chat_id, 
            ci.is_used,
            c.chat_name, 
            c.avatar_url,
            c.is_private
        FROM 
            chat_invites ci
        JOIN 
            chats c ON ci.chat_id = c.chat_id
        WHERE 
            ci.invite_code = ?
    ";
    $stmt_invite = $conn->prepare($sql_invite);
    $stmt_invite->bind_param("s", $invite_code);
    $stmt_invite->execute();
    $result_invite = $stmt_invite->get_result();

    if ($result_invite->num_rows === 0) {
        // Код не найден или истек - СРАЗУ ВЫКИДЫВАЕМ В index.php
        header("Location: index.php");
        exit(); 
    }

    $invite_data = $result_invite->fetch_assoc();
    $chat_id = (int)$invite_data['chat_id'];
    $chat_name = htmlspecialchars($invite_data['chat_name']);
    $chat_avatar = htmlspecialchars($invite_data['avatar_url']);
    $is_used = (bool)$invite_data['is_used'];
    $is_private = (bool)$invite_data['is_private']; 

    if ($is_used) {
        // Код уже был использован - СРАЗУ ВЫКИДЫВАЕМ В index.php
        header("Location: index.php"); 
        exit(); 
    }
    
    // В этом сценарии код будет помечен как использованный при успешном POST-запросе.
    $mark_code_as_used = true;

} elseif (isset($_GET['chat_id']) && is_numeric($_GET['chat_id'])) {
    // СЦЕНАРИЙ 2: Вход по СТАНДАРТНОЙ ССЫЛКЕ (chat_id)
    $chat_id = (int)$_GET['chat_id'];
    
    $sql_chat = "
        SELECT 
            chat_name, 
            avatar_url, 
            is_private
        FROM 
            chats
        WHERE 
            chat_id = ?
    ";
    $stmt_chat = $conn->prepare($sql_chat);
    $stmt_chat->bind_param("i", $chat_id);
    $stmt_chat->execute();
    $result_chat = $stmt_chat->get_result();
    
    if ($result_chat->num_rows === 0) {
        // Чат не найден - СРАЗУ ВЫКИДЫВАЕМ В index.php
        header("Location: index.php"); 
        exit(); 
    }
    
    $chat_data = $result_chat->fetch_assoc();
    $chat_name = htmlspecialchars($chat_data['chat_name']);
    $chat_avatar = htmlspecialchars($chat_data['avatar_url']);
    $is_private = (bool)$chat_data['is_private'];

    if ($is_private) {
        // Если чат приватный, но нет кода-приглашения - СРАЗУ ВЫКИДЫВАЕМ В index.php
        header("Location: index.php"); 
        exit(); 
    }
    
    // Для публичного чата код не помечается как использованный
    $mark_code_as_used = false; 

} else {
    // Ни chat_id, ни code не предоставлены - СРАЗУ ВЫКИДЫВАЕМ В index.php
    header("Location: index.php");
    exit();
}

// --- ОБЩАЯ ЛОГИКА ДЛЯ ОБОИХ СЦЕНАРИЕВ (Если chat_id найден и доступ разрешен) ---

if ($chat_id) {
    // 1. Проверка, является ли пользователь уже участником чата
    $sql_is_subscribed = "SELECT 1 FROM user_chats WHERE user_id = ? AND chat_id = ?";
    $stmt_is_subscribed = $conn->prepare($sql_is_subscribed);
    $stmt_is_subscribed->bind_param("ii", $current_user_id, $chat_id);
    $stmt_is_subscribed->execute();
    $is_subscribed = ($stmt_is_subscribed->get_result()->num_rows > 0);

    // 2. Обработка POST-запроса на присоединение
    if (!$is_subscribed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join') {
        try {
            $conn->begin_transaction();
            
            // Добавление пользователя в чат
            $sql_join = "INSERT INTO user_chats (user_id, chat_id) VALUES (?, ?)";
            $stmt_join = $conn->prepare($sql_join);
            $stmt_join->bind_param("ii", $current_user_id, $chat_id);
            $stmt_join->execute();
            
            // Если это было приглашение (СЦЕНАРИЙ 1), помечаем его как использованное
            if ($mark_code_as_used === true) {
                // $invite_code должен быть установлен в СЦЕНАРИИ 1
                $sql_mark_used = "UPDATE chat_invites SET is_used = 1, used_by_user_id = ? WHERE invite_code = ?"; 
                $stmt_mark_used = $conn->prepare($sql_mark_used);
                $stmt_mark_used->bind_param("is", $current_user_id, $invite_code); 
                $stmt_mark_used->execute();
            }

            $conn->commit();

            // Перенаправление в чат после успешного присоединения
            header("Location: chat.php?chat_id={$chat_id}");
            exit();
            
        } catch (\mysqli_sql_exception $e) {
            $conn->rollback();
            $message = '<p class="error-message">Ошибка присоединения к чату: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }

    // 3. Получение количества участников для отображения
    $sql_count = "SELECT COUNT(*) FROM user_chats WHERE chat_id = ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $chat_id);
    $stmt_count->execute();
    $participant_count = $stmt_count->get_result()->fetch_row()[0];
}

$conn->close(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Приглашение в <?php echo $chat_name; ?></title>
    <link rel="stylesheet" href="components/css/style.css">
</head>
<body>

<div class="invite-container">
    <?php echo $message; ?>
    
    <img src="<?php echo $chat_avatar; ?>" alt="Аватар чата" class="invite-avatar">
    
    <h2 class="invite-name"><?php echo $chat_name; ?></h2>
    <p class="invite-count">Участников: **<?php echo $participant_count; ?>**</p>
    
    <?php if ($chat_id && $is_subscribed): ?>
        <p>Вы **уже** являетесь участником этого чата.</p>
        <a href="chat.php?chat_id=<?php echo $chat_id; ?>" class="join-button" style="background-color: #28a745;">Перейти в чат</a>
    <?php elseif ($chat_id): // Показываем кнопку "Присоединиться" только если ID чата известен и вход разрешен?>
        <p>Чтобы увидеть сообщения и начать писать, присоединитесь к чату.</p>
        <form method="POST">
            <input type="hidden" name="action" value="join">
            <button type="submit" class="join-button">Присоединиться к чату</button>
        </form>
    <?php endif; ?>
    
    <a href="index.php" class="back-link">Назад к списку чатов</a>
</div>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>