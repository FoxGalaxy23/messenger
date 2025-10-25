<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JavaScript отключен</title>
    <!-- Подключаем Tailwind CSS для стилизации -->
    <style>
        /* Устанавливаем шрифт Inter для всего документа */
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Фон светлой темы */
            background-color: #f7f9fb; 
        }

        /* Адаптивные стили для тёмной темы на уровне body */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1f2937; /* Темно-серый фон */
            }
        }

        /* Стили для сообщения об отключении JS (светлая тема) */
        .js-warning {
            border: 3px solid #ef4444; 
        }

        /* Стили для сообщения об отключении JS (тёмная тема) */
        @media (prefers-color-scheme: dark) {
            .js-warning {
                border-color: #fca5a5; /* Более светлый красный для контраста */
            }
        }
    </style>
</head>
<body>
    <!-- 
        Этот блок <noscript> будет отображаться ТОЛЬКО, 
        если JavaScript отключен в браузере пользователя. 
    -->
    <noscript>
        <div class='error'>
            <h1>JavaScript error!</h1>
            <p>This web resource can't work fine without JS in your Web Browser!</p>
            <p>Please turn on JS or switch your web browser!</p>
        </div>
    </noscript>

    <!-- 
        Этот скрипт выполнится ТОЛЬКО, если JS включен. 
        Он немедленно перенаправит пользователя на /index.php, как вы просили.
    -->
    <script>
        // Перенаправляем пользователя на /index.php, так как JS включен
        window.location.replace("/index.php");
    </script>
</body>
</html>
