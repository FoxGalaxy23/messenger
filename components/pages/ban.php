<?php
include $_SERVER['DOCUMENT_ROOT'] . '/components/php/db_connect.php'; 

$current_user_id = $_SESSION['user_id']; 

if (!$current_user_id) {
    header('Location: /index.php');
    exit();
}

$ban_sql = "
    SELECT 
        ban_reason, 
        ban_end_date
    FROM 
        user_bans
    WHERE 
        banned_user_id = ? 
        AND is_active = 1 
        AND (ban_end_date IS NULL OR ban_end_date > NOW()) 
    LIMIT 1
";

$ban_stmt = $conn->prepare($ban_sql);
$ban_stmt->bind_param("i", $current_user_id);
$ban_stmt->execute();
$ban_result = $ban_stmt->get_result();

if ($ban_result->num_rows === 0) {
    header('Location: /index.php');
    exit();
}

$ban_info = $ban_result->fetch_assoc();
$reason = htmlspecialchars($ban_info['ban_reason'] ?? 'Not specified');
$end_date_raw = $ban_info['ban_end_date'];
$end_date = $end_date_raw ? date('M d, Y H:i', strtotime($end_date_raw)) : 'permanent';

$mail = $mail ?? "support@yourdomain.com";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Banned</title>
    <link rel="shortcut icon" href="/components/media/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f6f7fb;
            color: #222;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ban-card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 4px 32px rgba(220,53,69,0.08);
            padding: 40px 32px;
            max-width: 370px;
            width: 100%;
            text-align: center;
            border: 1px solid #dc3545;
        }
        .ban-card h1 {
            color: #dc3545;
            font-size: 2rem;
            margin-bottom: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .ban-card .reason, .ban-card .end-date {
            margin: 18px 0;
            font-size: 1.05rem;
        }
        .ban-card .support {
            margin-top: 24px;
            font-size: 0.97rem;
            color: #555;
        }
        .ban-card a.mail {
            color: #dc3545;
            text-decoration: underline;
            font-weight: 500;
        }
        .ban-card .logout-btn {
            display: inline-block;
            margin-top: 32px;
            padding: 12px 32px;
            background: #dc3545;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.18s;
        }
        .ban-card .logout-btn:hover {
            background: #b02a37;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #18191a; color: #eee; }
            .ban-card { background: #23272a; color: #eee; border-color: #dc3545; }
            .ban-card .support { color: #bbb; }
        }
    </style>
</head>
<body>
    <div class="ban-card">
        <h1><i class="fas fa-ban"></i> Account Banned</h1>
        <div class="reason">
            <strong>Reason:</strong> <?php echo $reason; ?>
        </div>
        <div class="end-date">
            <strong>Ban expires:</strong> <?php echo $end_date; ?>
        </div>
        <div class="support">
            For more information, please contact support.<br>
            <a class="mail" href="mailto:<?php echo $mail; ?>"><?php echo $mail; ?></a>
        </div>
        <a href="/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Log out
        </a>
    </div>
</body>
</html>