<?php
include 'components/php/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    header("Location: index.php");
    exit();
}
$chat_id = (int)$_GET['chat_id'];
$username = $_SESSION['username']; 

$sql_check = "
    SELECT 
        1 
    FROM 
        user_chats 
    WHERE 
        user_id = ? AND chat_id = ?
";

$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $current_user_id, $chat_id); 
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    header("Location: invite.php?chat_id={$chat_id}");
    exit(); 
}

$sql_details = "
    SELECT 
        c.chat_name, 
        c.avatar_url,
        (SELECT COUNT(*) FROM user_chats WHERE chat_id = c.chat_id) AS participant_count
    FROM 
        chats c
    WHERE 
        c.chat_id = ?
";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $chat_id);
$stmt_details->execute();
$chat_result = $stmt_details->get_result();
$chat_details = $chat_result->fetch_assoc();

if (!$chat_details) {
    header("Location: index.php?error=chat_not_found");
    exit();
}

$chat_name = htmlspecialchars($chat_details['chat_name']);
$chat_avatar = htmlspecialchars($chat_details['avatar_url'] ?? 'default_chat_avatar.png'); // Заглушка, если в БД пусто
$participant_count = (int)$chat_details['participant_count'];


$conn->close(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat: <?php echo htmlspecialchars($chat_name); ?></title>
    <link rel="stylesheet" type="text/css" href="components/css/style.css">
    </head>
<body>

<div class="chat-container">
    <header class="chat-header">
        <h1>
            <img style='width: 5vh; height: 5vh; border-radius: 20%;' 
                 src="<?php echo $chat_avatar; ?>" 
                 alt="<?php echo $chat_name; ?>" 
                 class="chat-header-avatar">
            
            <?php echo $chat_name; ?>
            
            <small style='font-size: 0.5em; color: #888; margin-left: 10px; display: inline-block;'>(<?php echo $participant_count; ?> уч.)</small>
        </h1>
    </header>

    <div id="messages-display" class="messages-display">
        </div>
    <div style='margin-bottom: 10vh;'></div>

    <div class="chat-input-area" style='background: white;'>
        <form id="chat-form" class="chat-form">
            
            <label for="media-input" class="media-upload-label" style='cursor: pointer; padding: 0 10px; font-size: 1.5em;'>🖼️</label>
            <input type="file" id="media-input" name="media_files[]" multiple accept="image/*,video/*" style="display: none;">
            
            <textarea id="message-input" placeholder="Сообщение..." rows="1" required></textarea>
            <button type="submit">›</button> 
        </form>
        <div id="selected-media-preview" class="selected-media-preview" style='padding: 5px 10px; font-size: 0.8em;'></div>
    </div>
</div>

<script>
const chatId = <?php echo $chat_id; ?>;
const currentUserId = Number(<?php echo $current_user_id; ?>);
const messagesDisplay = document.getElementById('messages-display');
const chatForm = document.getElementById('chat-form');
const messageInput = document.getElementById('message-input');
const mediaInput = document.getElementById('media-input');
const selectedMediaPreview = document.getElementById('selected-media-preview');

let firstLoad = true;
let isFetching = false;
const pollInterval = 3000;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function buildMessageElement(msg) {
    const isSelf = Number(msg.user_id) === currentUserId;
    const wrapper = document.createElement('div');
    wrapper.className = `message ${isSelf ? 'self' : 'other'}`;
    wrapper.dataset.msgId = msg.message_id;

    const avatar = document.createElement('img');
    avatar.className = 'message-avatar';
    avatar.src = escapeHtml(msg.avatar_url || 'default_avatar.png'); 
    avatar.alt = escapeHtml(msg.username || 'User');
    
    const contentBox = document.createElement('div');
    contentBox.className = 'message-content-box';
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';

    if (!isSelf) {
        const usernameSpan = document.createElement('span');
        usernameSpan.className = 'msg-username bubble-username-right';
        usernameSpan.textContent = escapeHtml(msg.username);
        bubble.appendChild(usernameSpan); 
    }

    if (msg.media && msg.media.length > 0) {
        const mediaContainer = document.createElement('div');
        mediaContainer.className = 'message-media-container';
        
        msg.media.forEach(media => {
            let mediaElement;
            const path = media.path;
            
            if (media.type.startsWith('image/')) {
                mediaElement = document.createElement('img');
                mediaElement.src = path;
                mediaElement.alt = 'Прикрепленное изображение';
                mediaElement.className = 'chat-media-image';
            } 
            else if (media.type.startsWith('video/')) {
                mediaElement = document.createElement('video');
                mediaElement.src = path;
                mediaElement.controls = true;
                mediaElement.className = 'chat-media-video';
            }
            else {
                mediaElement = document.createElement('a');
                mediaElement.href = path;
                mediaElement.textContent = 'Файл: ' + path.split('/').pop();
                mediaElement.target = '_blank';
                mediaElement.style.display = 'block';
            }

            if (mediaElement) {
                mediaContainer.appendChild(mediaElement);
            }
        });

        bubble.appendChild(mediaContainer);
    }

    const messageText = document.createElement('p');
    messageText.className = 'msg-text';
    if (msg.message && msg.message.trim() !== "") {
        messageText.innerHTML = escapeHtml(msg.message); 
        bubble.appendChild(messageText);
    }

    const meta = document.createElement('span');
    meta.className = 'msg-meta';
    meta.textContent = msg.time; 

    bubble.appendChild(meta);
    
    contentBox.appendChild(bubble);

    if (isSelf) {
        wrapper.appendChild(contentBox);
        wrapper.appendChild(avatar);
    } else {
        wrapper.appendChild(avatar);
        wrapper.appendChild(contentBox);
    }
    
    return wrapper;
}
function scrollToBottomReliable() {
    requestAnimationFrame(() => {
        const last = messagesDisplay.lastElementChild;
        if (last) {
            try {
                last.scrollIntoView({ block: 'end', behavior: 'auto' });
            } catch (e) {
                messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
            }
        } else {
            messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
        }
    });

    setTimeout(() => {
        const last = messagesDisplay.lastElementChild;
        if (last) {
            try { last.scrollIntoView({ block: 'end', behavior: 'auto' }); }
            catch (e) { messagesDisplay.scrollTop = messagesDisplay.scrollHeight; }
        } else {
            messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
        }
    }, 80); 
}

function fetchMessages() {
    if (isFetching) return;
    isFetching = true;

    fetch(`components/php/get_messages.php?chat_id=${chatId}`)
        .then(resp => {
            if (!resp.ok) throw new Error('Network response was not ok');
            return resp.json();
        })
        .then(messages => {
            const existingIds = new Set(Array.from(messagesDisplay.children).map(el => String(el.dataset.msgId)));
            const fragment = document.createDocumentFragment();
            let added = false;

            messages.forEach(msg => {
                if (existingIds.has(String(msg.message_id))) return;
                const el = buildMessageElement(msg);
                fragment.appendChild(el);
                added = true;
            });

            if (added) {
                messagesDisplay.appendChild(fragment);
                scrollToBottomReliable(); 
            }
        })
        .catch(err => {
            console.error('Ошибка при получении сообщений:', err);
        })
        .finally(() => {
            isFetching = false;
        });
}

mediaInput.addEventListener('change', function() {
    selectedMediaPreview.innerHTML = '';
    
    if (this.files.length > 0) {
        const count = this.files.length;
        const totalSize = Array.from(this.files).reduce((sum, file) => sum + file.size, 0);

        const previewText = document.createElement('span');
        previewText.textContent = `Выбрано: ${count} файл(а/ов) (${(totalSize / 1024 / 1024).toFixed(2)} МБ)`;
        
        const clearButton = document.createElement('button');
        clearButton.textContent = '✖';
        clearButton.className = 'clear-media-button';
        clearButton.style.cssText = 'margin-left: 10px; cursor: pointer; border: none; background: none; color: #f00; font-weight: bold;';
        clearButton.onclick = (e) => {
            e.preventDefault();
            mediaInput.value = null;
            selectedMediaPreview.innerHTML = '';
        };

        selectedMediaPreview.appendChild(previewText);
        selectedMediaPreview.appendChild(clearButton);
    }
});


chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const messageText = messageInput.value.trim();
    const files = mediaInput.files;

    if (messageText === '' && files.length === 0) return;

    const formData = new FormData();
    formData.append('chat_id', chatId);
    formData.append('message', messageText);

    for(let i = 0; i < files.length; i++) {
        formData.append('media_files[]', files[i]); 
    }

    fetch('components/php/send.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => {
        if (!resp.ok) throw new Error('Network response was not ok');
        return resp.json();
    })
    .then(data => {
        if (data.status === 'success') {
            messageInput.value = '';
            messageInput.style.height = '';
            mediaInput.value = null;
            selectedMediaPreview.innerHTML = '';
            fetchMessages(); 
        } else {
            console.error('Ошибка отправки:', data.message || data);
        }
    })
    .catch(err => {
        console.error('Ошибка AJAX:', err);
    });
});

    messageInput.focus(); 


    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

fetchMessages();
scrollToBottomReliable();
setInterval(() => { if (!isFetching) fetchMessages(); }, pollInterval);
</script>

</body>
</html>