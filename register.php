<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'components/php/db_connect.php';

$error = '';
$success = '';
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;

if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 1) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Заполните все поля.";
    } elseif (strlen($password) < 6) {
        $error = "Пароль должен быть не менее 6 символов.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат Email.";
    } else {
        $_SESSION['reg_username'] = $username;
        $_SESSION['reg_email'] = $email;
        $_SESSION['reg_password'] = $password;
        $step = 2;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 2) {
    $password_confirm = $_POST['password_confirm'];
    $username = $_SESSION['reg_username'] ?? '';
    $email = $_SESSION['reg_email'] ?? '';
    $password = $_SESSION['reg_password'] ?? '';
    $avatar_url = 'components/media/images/user.png';

    if ($password !== $password_confirm) {
        $error = "Пароли не совпадают.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password_hash, avatar_url) VALUES ('$username', '$email', '$password_hash', '$avatar_url')";
        if ($conn->query($sql) === TRUE) {
            unset($_SESSION['reg_username'], $_SESSION['reg_email'], $_SESSION['reg_password']);
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
    <link rel="stylesheet" type="text/css" href="components/css/login.css">
</head>
<body>
    <div class='logreg-container'>
        <div class='headlogin'>
            <div>
                <img src="<?php echo $logo; ?>" alt="" style='width: 10vh;'>
            </div>
        </div>
        <div class="selection-container">
            <h2>Register new account</h2>
            <h3>Quick registration to start chatting</h3>
            <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
            <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>

            <?php if ($step === 1): ?>
            <form method="POST" class="form-style">
                <input type="hidden" name="step" value="1">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required placeholder="Your email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Your password (minimum 6 characters)">
                <button type="submit" class="submit-button">Next</button>
            </form>
            <?php elseif ($step === 2): ?>
            <form method="POST" class="form-style">
                <input type="hidden" name="step" value="2">
                <label for="password_confirm">Confirm password:</label>
                <input type="password" id="password_confirm" name="password_confirm" required placeholder="Confirm your password">
                <button type="submit" class="submit-button">Register</button>
            </form>
            <?php endif; ?>

            <p class="back-link-area">
                Already have an account? <a href="login.php" class="action-link">Login</a>
            </p>
        </div>
    </div>
</body>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</html>
