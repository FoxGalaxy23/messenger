<?php
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (
    stripos($userAgent, 'Chrome') !== false ||
    stripos($userAgent, 'Chromium') !== false ||
    stripos($userAgent, 'Edg') !== false ||
    stripos($userAgent, 'Opera') !== false
) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsupported Browser</title>
    <link rel="shortcut icon" href="/components/media/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .windows-error {
            max-width: 600px;
            padding: 20px;
            border-radius: 8px;
        }
        .error-container {
            text-align: center;
            margin-top: 0;
        }
        .choose-option {
            text-align: center;
            margin-top: 20px;
        }
        @media (prefers-color-scheme: light) {
            body {
                background: #f5f5f5;
                color: #222;
            }
            .windows-error {
                background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            a {
                color: #0078d7;
            }
        }
        @media (prefers-color-scheme: dark) {
            body {
                background: #181a1b;
                color: #e0e0e0;
            }
            .windows-error {
                background: #23272a;
                box-shadow: 0 2px 8px rgba(0,0,0,0.4);
            }
            a {
                color: #4fc3f7;
            }
        }
        a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class='windows-error'>
        <div class="error-container">
            <h1 style='color: orange;'>Unsupported Browser</h1>
            <p>We're sorry, but your browser is not supported. Please use a modern browser.</p>
        </div>
        <div class='choose-option'>
            <p>
                But you can download 
                <a href="https://www.google.com/chrome/" target="_blank">
                    <i class="fab fa-chrome"></i> Google Chrome
                </a>
                for a better experience.
            </p>
        </div>
    </div>
</body>
</html>