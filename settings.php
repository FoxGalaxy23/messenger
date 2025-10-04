<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'components/php/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$current_user = [];

$sql_fetch = "SELECT username, email, avatar_url FROM users WHERE user_id = '$user_id'";
$result_fetch = $conn->query($sql_fetch);

if ($result_fetch && $result_fetch->num_rows > 0) {
    $current_user = $result_fetch->fetch_assoc();
} else {
    $error = "Ошибка: не удалось найти данные пользователя.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $new_username = $conn->real_escape_string(trim($_POST['username']));
    $new_email = $conn->real_escape_string(trim($_POST['email']));
    $new_avatar_url = $conn->real_escape_string(trim($_POST['avatar_url']));
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $updates = [];

    if ($new_username != $current_user['username']) {
        $check_name_sql = "SELECT user_id FROM users WHERE username = '$new_username' AND user_id != '$user_id'";
        if ($conn->query($check_name_sql)->num_rows > 0) {
            $error = "Имя пользователя уже занято.";
        } else {
            $updates[] = "username = '$new_username'";
            $_SESSION['username'] = $new_username; 
        }
    }
    
    if (empty($error) && $new_email != $current_user['email']) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Неверный формат Email.";
        } else {
            $check_email_sql = "SELECT user_id FROM users WHERE email = '$new_email' AND user_id != '$user_id'";
            if ($conn->query($check_email_sql)->num_rows > 0) {
                $error = "Email уже занят.";
            } else {
                $updates[] = "email = '$new_email'";
                $_SESSION['email'] = $new_email;
            }
        }
    }
    
    if (empty($error) && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = "Новый пароль и его подтверждение не совпадают.";
        } elseif (strlen($new_password) < 6) {
            $error = "Пароль должен быть не менее 6 символов.";
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updates[] = "password_hash = '$password_hash'";
        }
    }

    if (empty($error) && $new_avatar_url != $current_user['avatar_url']) {
        $updates[] = "avatar_url = '$new_avatar_url'";
        $_SESSION['avatar_url'] = $new_avatar_url;
    }

    if (empty($error) && !empty($updates)) {
        $update_sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = '$user_id'";
        
        if ($conn->query($update_sql) === TRUE) {
            $success = "Настройки успешно обновлены!";
            $current_user['username'] = $new_username;
            $current_user['email'] = $new_email;
            $current_user['avatar_url'] = $new_avatar_url;
        } else {
            $error = "Ошибка при сохранении настроек.";
        }
    } elseif (empty($error)) {
        $success = "Нет изменений для сохранения.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="components/css/style.css"> 
</head>
<body>
    <header class="chat-header">
        <h1>Profile Settings</h1>
    </header>
    <div class="selection-container">
        <h2>Edit Profile</h2>
        <h3>Your Current Settings</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>

        <form method="POST" class="form-style">
            <?php if (!empty($current_user['avatar_url'])): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="<?= htmlspecialchars($current_user['avatar_url']) ?>" alt="Аватар" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">
            </div>
            <?php endif; ?>

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required 
                   value="<?= htmlspecialchars($current_user['username'] ?? '') ?>">
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required 
                   value="<?= htmlspecialchars($current_user['email'] ?? '') ?>">

            <label for="avatar_url">URL:</label>
            <input type="text" id="avatar_url" name="avatar_url"
                   value="<?= htmlspecialchars($current_user['avatar_url'] ?? 'default_avatar.png') ?>">
            
            <hr>
            
            <h4>Change password</h4>

            <label for="new_password">New password:</label>
            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current">

            <label for="confirm_password">Confirm new password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password">

            <button type="submit" class="submit-button">Save changes</button>
        </form>
        
        <p class="back-link-area">
            <a href="select_chat.php" class="action-link">Back to chat</a>
        </p>
    </div>
</body>
</html>