<?php
// invite_user.php
include 'components/php/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];
$message = '';
$chat_id = null;
$chat_name = 'Ошибка чата';
$is_admin = false; // Флаг, является ли пользователь админом
$is_private_chat = true; // По умолчанию считаем чат приватным
$is_member = false; // Флаг, является ли пользователь участником
$can_generate_link = false; // Флаг, имеет ли право генерировать ссылку/код

// --- 1. Получение chat_id и проверка прав ---

if (isset($_GET['chat_id']) && is_numeric($_GET['chat_id'])) {
    $chat_id = (int)$_GET['chat_id'];

    // Проверка, является ли пользователь участником чата и админом
    $sql_check_member = "
        SELECT 
            c.chat_name, 
            uc.is_admin,
            c.is_private
        FROM 
            chats c
        JOIN 
            user_chats uc ON c.chat_id = uc.chat_id
        WHERE 
            c.chat_id = ? AND uc.user_id = ?
    ";
    $stmt_check_member = $conn->prepare($sql_check_member);
    $stmt_check_member->bind_param("ii", $chat_id, $current_user_id);
    $stmt_check_member->execute();
    $result_check_member = $stmt_check_member->get_result();
    
    if ($result_check_member->num_rows === 0) {
        // Участник не найден или чат не существует
        $message = '<p class="error-message">Чат не найден или у вас нет доступа.</p>';
        $chat_id = null;
    } else {
        $chat_data = $result_check_member->fetch_assoc();
        $chat_name = htmlspecialchars($chat_data['chat_name']);
        $is_admin = (bool)$chat_data['is_admin'];
        $is_private_chat = (bool)$chat_data['is_private'];
        $is_member = true; // Пользователь участник

        // Логика прав на генерацию/просмотр ссылок
        if ($is_admin) {
            // Админ всегда может генерировать и смотреть
            $can_generate_link = true;
        } elseif (!$is_private_chat) {
            // Участник может генерировать/смотреть ссылку, если чат публичный
            $can_generate_link = true;
        } else {
            // Приватный чат, но пользователь не админ
            // Согласно вашему пожеланию, здесь мы его перенаправим ниже
        }
    }
} else {
    $message = '<p class="error-message">Не указан ID чата.</p>';
}

// --- * Изменение 1: Перенаправление, если нет прав или чат приватный и не админ ---
// Перенаправляем, если:
// 1. Не удалось получить chat_id (чат не существует, нет доступа).
// 2. Пользователь участник, но чат приватный И он НЕ админ.
if (!$chat_id || ($is_member && $is_private_chat && !$is_admin)) {
    // В случае ошибки или недостаточных прав для приватного чата (не админ)
    if ($chat_id && $is_member && $is_private_chat && !$is_admin) {
        // Если это именно случай "не админ в приватном чате"
        $message = '<p class="error-message">Для этого приватного чата только администраторы могут создавать приглашения.</p>';
    }
    // Перенаправляем на index.php
    header("Location: index.php");
    exit();
}
// ---------------------------------------------------------------------------------


// --- 2. Логика генерации кода (только для POST-запроса и при наличии прав) ---
// Генерировать код можно ТОЛЬКО, если пользователь админ И чат приватный
// Для публичных чатов (is_private_chat = false) генерировать код не нужно/нельзя

$invite_link = '';
$public_link = "invite.php?chat_id={$chat_id}";

if ($chat_id && $is_admin && $is_private_chat && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_code') {
    
    // Генерируем уникальный код (например, 32 символа)
    // Генерируйте уникальные коды, пока не найдете тот, которого нет в БД
    do {
        $invite_code = bin2hex(random_bytes(16)); // 32 символа
        $sql_check_code = "SELECT 1 FROM chat_invites WHERE invite_code = ?";
        $stmt_check_code = $conn->prepare($sql_check_code);
        $stmt_check_code->bind_param("s", $invite_code);
        $stmt_check_code->execute();
        $result_check_code = $stmt_check_code->get_result();
    } while ($result_check_code->num_rows > 0);
    
    try {
        // Сохраняем код в БД
        $sql_insert = "INSERT INTO chat_invites (chat_id, invite_code, created_by_user_id) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("isi", $chat_id, $invite_code, $current_user_id);
        $stmt_insert->execute();

        $invite_link = "invite.php?code={$invite_code}";
        $message = '<p class="success-message">**Одноразовый** код приглашения успешно создан!</p>';

    } catch (\mysqli_sql_exception $e) {
        $message = '<p class="error-message">Ошибка при сохранении кода: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// --- 3. Получение и отображение существующих активных кодов ---
// Активные коды показываем только, если чат приватный И пользователь админ

$active_invites = [];
if ($chat_id && $is_admin && $is_private_chat) {
    $sql_active_invites = "
        SELECT 
            invite_code, 
            created_at
        FROM 
            chat_invites
        WHERE 
            chat_id = ? AND is_used = 0
        ORDER BY 
            created_at DESC
    ";
    $stmt_active_invites = $conn->prepare($sql_active_invites);
    $stmt_active_invites->bind_param("i", $chat_id);
    $stmt_active_invites->execute();
    $result_active_invites = $stmt_active_invites->get_result();
    
    while($row = $result_active_invites->fetch_assoc()) {
        $active_invites[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пригласить в чат: <?php echo $chat_name; ?></title>
    <link rel="stylesheet" href="components/css/style.css"> 
    <style>
        .invite-form-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .invite-form-container h3 {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #343a40;
        }
        .invite-button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
            margin-bottom: 15px;
        }
        .invite-button:hover {
            background-color: #0056b3;
        }
        .link-box {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            margin-bottom: 15px;
            border: 1px solid #ced4da;
        }
        .link-box h4 {
            margin-top: 0;
            color: #495057;
        }
        .active-invites-list {
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .active-invites-list ul {
            list-style: none;
            padding: 0;
        }
        .active-invites-list li {
            background-color: #fff;
            margin-bottom: 8px;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .active-invites-list .code-link {
            font-family: monospace;
            font-size: 0.9em;
        }
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="invite-form-container">
    <h2>Пригласить в чат "<?php echo $chat_name; ?>"</h2>

    <?php echo $message; ?>

    <?php if ($chat_id && $can_generate_link): // Проверка на общее право просмотра/генерации ?>
        
        <?php if ($is_private_chat): // Приватный чат - только админ может генерировать ?>
        
            <?php if ($invite_link): ?>
                <div class="link-box">
                    <h4>Новая одноразовая ссылка (только для админов)</h4>
                    <p id="newCodeLink" class="code-link"><?php echo "http://" . $_SERVER['HTTP_HOST'] . "/" . $invite_link; ?></p>
                    <button onclick="copyToClipboard('newCodeLink')" class="invite-button" style="background-color: #28a745;">Копировать ссылку</button>
                </div>
            <?php endif; ?>

            <h3>Сгенерировать одноразовый код</h3>
            <p>Этот код может быть использован только **один раз** для входа.</p>
            <form method="POST">
                <input type="hidden" name="action" value="generate_code">
                <button type="submit" class="invite-button">Создать одноразовый код</button>
            </form>
            
            <div class="active-invites-list">
                <h3>Активные (неиспользованные) коды</h3>
                <?php if (!empty($active_invites)): ?>
                    <ul>
                        <?php foreach ($active_invites as $invite): ?>
                            <?php 
                                $full_link = "http://" . $_SERVER['HTTP_HOST'] . "/invite.php?code=" . htmlspecialchars($invite['invite_code']);
                                $element_id = 'code_' . htmlspecialchars($invite['invite_code']);
                            ?>
                            <li>
                                <span>Создан: <?php echo date('d.m.Y H:i', strtotime($invite['created_at'])); ?></span>
                                <div>
                                    <span id="<?php echo $element_id; ?>" class="code-link"><?php echo $full_link; ?></span>
                                    <button onclick="copyToClipboard('<?php echo $element_id; ?>')" style="background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Копировать</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Активных одноразовых кодов нет.</p>
                <?php endif; ?>
            </div>

        <?php else: // Публичный чат - все участники могут видеть публичную ссылку ?>
            <h3>Публичная ссылка для приглашения</h3>
            <p>Для публичных чатов можно использовать простую ссылку. Она всегда активна.</p>
            <div class="link-box">
                <h4>Постоянная ссылка</h4>
                <p id="publicLink" class="code-link"><?php echo "http://" . $_SERVER['HTTP_HOST'] . "/" . $public_link; ?></p>
                <button onclick="copyToClipboard('publicLink')" class="invite-button">Копировать публичную ссылку</button>
            </div>
            <p>Так как чат **публичный**, одноразовые коды не требуются.</p>
        <?php endif; ?>

    <?php endif; ?>
    
    <a href="index.php" class="back-link" style="display: block; text-align: center; margin-top: 20px;">Назад к чатам</a>
</div>

<script>
    // Функция для копирования текста
    function copyToClipboard(elementId) {
        const textToCopy = document.getElementById(elementId).innerText;
        
        // Используем современный Clipboard API
        if (navigator.clipboard) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                alert('Ссылка скопирована: ' + textToCopy);
            }).catch(err => {
                fallbackCopy(textToCopy);
            });
        } else {
            fallbackCopy(textToCopy);
        }
    }

    // Запасной метод копирования для старых браузеров
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Ссылка скопирована (запасной метод): ' + text);
        } catch (err) {
            console.error('Не удалось скопировать текст: ', err);
            alert('Не удалось скопировать ссылку. Пожалуйста, скопируйте вручную: ' + text);
        }
        document.body.removeChild(textarea);
    }
</script>

<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>