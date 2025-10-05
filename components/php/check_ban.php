<?php

if (!isset($conn) || !isset($current_user_id)) {
    die("Error: Ban check could not be performed. No database connection or user ID.");
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

if ($ban_result->num_rows > 0) {
    $ban_info = $ban_result->fetch_assoc();
    $reason = htmlspecialchars($ban_info['ban_reason'] ?? 'Нет причины');
    $end_date_raw = $ban_info['ban_end_date'];
    
    if ($end_date_raw) {
        $end_date = date('d.m.Y H:i:s', strtotime($end_date_raw));
    } else {
        $end_date = 'навсегда';
    }
        header('Location: /components/pages/ban.php');
    exit();
}