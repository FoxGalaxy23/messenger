<?php
include 'config.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

session_start();

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

$userAgent = $_SERVER['HTTP_USER_AGENT'];

if (stripos($userAgent, 'Chromium') === false && stripos($userAgent, 'Chrome') === false) {
    header('Location: /components/pages/agent_error.php');
    exit;
}
?>
