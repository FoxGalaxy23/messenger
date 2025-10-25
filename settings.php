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

$target_dir = "uploads/user_avatars/default.png"; 
$default_avatar_url = 'components/media/images/user.png'; 

if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        $error = "Error server: Failed to create upload directory.";
    }
}


$sql_fetch = "SELECT username, email, avatar_url FROM users WHERE user_id = '$user_id'";
$result_fetch = $conn->query($sql_fetch);

if ($result_fetch && $result_fetch->num_rows > 0) {
    $current_user = $result_fetch->fetch_assoc();
} else {
    $error = "Error: Failed to find user data.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $new_username = $conn->real_escape_string(trim($_POST['username']));
    $new_email = $conn->real_escape_string(trim($_POST['email']));
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $updates = [];
    $new_avatar_url = $current_user['avatar_url'];
    
    if (isset($_FILES["avatar_file"]) && $_FILES["avatar_file"]["error"] == 0) {
        
        $file_name = basename($_FILES["avatar_file"]["name"]);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $unique_file_name = uniqid('user_') . '.' . $file_extension;
        $target_file = $target_dir . $unique_file_name;
        $uploadOk = 1;

        $check = getimagesize($_FILES["avatar_file"]["tmp_name"]);
        if($check === false) { $error = "This is not a valid image file."; $uploadOk = 0; }
        if ($_FILES["avatar_file"]["size"] > 5000000) { $error = "We apologize, your file is too large (max. 5MB)."; $uploadOk = 0; }
        if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg"
        && $file_extension != "gif" ) { $error = "Only JPG, JPEG, PNG, and GIF files are allowed."; $uploadOk = 0; }

        if ($uploadOk) {
            if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target_file)) {
                $new_avatar_url = $target_file;
            } else {
                $error = "We apologize, there was an error uploading your file. Please try again later.";
            }
        }
    }


    if ($new_username != $current_user['username']) {
        $check_name_sql = "SELECT user_id FROM users WHERE username = '$new_username' AND user_id != '$user_id'";
        if ($conn->query($check_name_sql)->num_rows > 0) {
            $error = "Username is already taken.";
        } else {
            $updates[] = "username = '$new_username'";
            $_SESSION['username'] = $new_username; 
        }
    }
    
    if (empty($error) && $new_email != $current_user['email']) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            $check_email_sql = "SELECT user_id FROM users WHERE email = '$new_email' AND user_id != '$user_id'";
            if ($conn->query($check_email_sql)->num_rows > 0) {
                $error = "Email is already taken.";
            } else {
                $updates[] = "email = '$new_email'";
                $_SESSION['email'] = $new_email;
            }
        }
    }
    
    if (empty($error) && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
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
            $success = "Settings successfully updated!";
            $current_user['username'] = $new_username;
            $current_user['email'] = $new_email;
            $current_user['avatar_url'] = $new_avatar_url;
            echo "<script>window.top.location.reload();</script>";
            exit();
        } else {
            $error = "Error updating settings.";
        }
    } elseif (empty($error)) {
        $success = "No changes to save.";
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
    <script>
        if (window.self === window.top) {
            window.location = 'index.php';
        }
    </script>
</head>
<body>
    <div id="chatSidebar" class="chat-sidebar1" style='background-color: #23272b; border-bottom: 1px solid #23272b; position: fixed; top: 0; z-index: 20; margin-right: 5vh;'>
        
        <header>
            <h3>
                <span>Settings</span>
            </h3>
        </header>
</div>
<h1>dc</h1>
    <div class="selection-container">
        <h2>Edit Profile</h2>
        <h3>Your Current Settings</h3>

        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>

        <form method="POST" class="form-style" enctype="multipart/form-data"> 
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

            <label for="avatar_file">Change Avatar (Upload file):</label>
            <input type="file" id="avatar_file" name="avatar_file" accept="image/*"> 
            
            <hr>
            
            <h4>Change password</h4>

            <label for="new_password">New password:</label>
            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current">

            <label for="confirm_password">Confirm new password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password">

            <button type="submit" class="submit-button">Save changes</button>
        </form>
        <a href="components/pages/info.php" class="action-link">Info page</a>
    </div>
</body>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</html>