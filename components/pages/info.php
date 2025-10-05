<?php
include '../php/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About <?php echo htmlspecialchars($name); ?></title>
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($logo); ?>"/>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            padding: 20px;
        }
        h1 {
            color: #007BFF;
        }
        a {
            color: #007BFF;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .window {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: auto;
        }
        p {
            line-height: 1.6;
        }
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #181a1b;
                color: #e0e0e0;
            }
            .window {
                background: #23272a;
                box-shadow: 0 2px 4px rgba(0,0,0,0.4);
            }
            a {
                color: #4fc3f7;
            }
        }
        a {
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>
    <div class="window">
        <img src="<?php echo htmlspecialchars($logo); ?>" alt="">
        <h1>About <?php echo htmlspecialchars($name); ?></h1>
        <p><?php echo htmlspecialchars($description); ?></p>
        <p>Version: <?php echo htmlspecialchars($version); ?></p>
        <p>&copy; 2025 <?php echo htmlspecialchars($author); ?>. All rights reserved.</p>
        <div>
            <a href="<?php echo htmlspecialchars($author_website); ?>">Author Website</a>
            <a href="/settings.php">Back</a>
        </div>
    </div>
</body>
</html>