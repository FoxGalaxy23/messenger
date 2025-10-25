<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'components/php/db_connect.php'; 

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    $sql = "SELECT user_id, username, email, password_hash, avatar_url FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            header("Location: index.php"); 
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
    <link rel="stylesheet" type="text/css" href="components/css/login.css">
</head>
<body>
    <div class='logreg-container'>
        <div class='headlogin'>
            <img src="<?php echo $logo; ?>" alt="">
        </div>
        <div class="selection-container">
        <h2>Login to your account</h2>
        <h3>Welcome back!</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <form method="POST" class="form-style">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required placeholder="Your username">
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Your password">

            <button type="submit" class="submit-button">Login</button>
        </form>
        
        <p class="back-link-area">
            No account? <a href="register.php" class="action-link">Register</a>
        </p>
    </div>
    </div>
</body>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</html>