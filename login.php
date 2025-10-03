<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'components/php/db_connect.php'; 

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    $sql = "SELECT user_id, username, password_hash FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        

        if (password_verify($password, $user['password_hash'])) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            

            header("Location: select_chat.php"); 
            exit();
        } else {
            $error = "Неверный пароль.";
        }
    } else {
        $error = "Пользователь с таким именем не найден.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="components/css/style.css"> 
</head>
<body>
    <header class="chat-header">
        <h1>Login</h1>
    </header>
    <div class="selection-container">
        <h2>Login in your account</h2>
        <h3>Welcome back!</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <form method="POST" class="form-style">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required placeholder="Ваше имя">
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Your password">
            
            <button type="submit" class="submit-button">Log in</button>
        </form>
        
        <p class="back-link-area">
            No account? <a href="register.php" class="action-link">Register</a>
        </p>
    </div>
</body>
</html>