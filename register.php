<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'components/php/db_connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm']; 
    $avatar_url = !empty($_POST['avatar_url']) ? $conn->real_escape_string(trim($_POST['avatar_url'])) : 'components/media/images/user.png'; 

    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Заполните все обязательные поля (Имя, Email, Пароль).";
    } elseif ($password !== $password_confirm) {
        $error = "Пароли не совпадают.";
    } elseif (strlen($password) < 6) {
        $error = "Пароль должен быть не менее 6 символов.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат Email.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, avatar_url) VALUES ('$username', '$email', '$password_hash', '$avatar_url')";
        
        if ($conn->query($sql) === TRUE) {
            $success = "Аккаунт успешно создан! Теперь вы можете войти.";
             header("Location: login.php");
             exit();
        } else {
            $error = "Ошибка при создании аккаунта. Возможно, имя или Email уже заняты.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link rel="stylesheet" href="components/css/style.css"> 
</head>
<body>
    <header class="chat-header">
        <h1>Register</h1>
    </header>
    <div class="selection-container">
        <h2>Register new account</h2>
        <h3>Quick registration to start chatting</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>

        <form method="POST" class="form-style">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required placeholder="Choose a username">
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required placeholder="Your email address">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Your password (minimum 6 characters)">

            <label for="password_confirm">Confirm password:</label>
            <input type="password" id="password_confirm" name="password_confirm" required placeholder="Confirm your password">
            <button type="submit" class="submit-button">Register</button>
        </form>
        
        <p class="back-link-area">
            Already have an account? <a href="login.php" class="action-link">Login</a>
        </p>
    </div>
</body>
</html>