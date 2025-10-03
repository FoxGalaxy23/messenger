<?php
include 'components/php/db_connect.php'; 
check_login(); 

if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    header("Location: select_chat.php");
    exit();
}
$chat_id = (int)$_GET['chat_id'];
$username = $_SESSION['username']; 

$chat_info = $conn->query("SELECT chat_name FROM chats WHERE chat_id = $chat_id")->fetch_assoc();
if (!$chat_info) {
    header("Location: select_chat.php");
    exit();
}
$chat_name = $chat_info['chat_name'];
$current_user_id = $_SESSION['user_id'];
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
        <h1><?php echo htmlspecialchars($chat_name); ?></h1>
        <a href="select_chat.php">Back</a>
    </header>

    <div id="messages-display" class="messages-display">
        </div>

    <div class="chat-input-area" style='background: white;'>
        <form id="chat-form" class="chat-form">
            <textarea id="message-input" placeholder="Message..." rows="1" required></textarea>
            <button type="submit">›</button> </form>
    </div>
</div>

<script>
const chatId = <?php echo $chat_id; ?>;
const currentUserId = Number(<?php echo $current_user_id; ?>);
const messagesDisplay = document.getElementById('messages-display');
const chatForm = document.getElementById('chat-form');
const messageInput = document.getElementById('message-input');

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

    const content = document.createElement('div');
    content.className = 'message-content';

    content.innerHTML = escapeHtml(msg.message);

    const meta = document.createElement('span');
    meta.className = 'msg-meta';
    meta.textContent = `${isSelf ? '' : (msg.username + ' · ')} ${msg.time}`;

    content.appendChild(meta);
    wrapper.appendChild(content);
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

    const wasAtBottom = (messagesDisplay.scrollHeight - messagesDisplay.clientHeight) <= (messagesDisplay.scrollTop + 5);

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
            }
        })
        .catch(err => {
            console.error('Ошибка при получении сообщений:', err);
        })
        .finally(() => {
            isFetching = false;
        });
}

chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const messageText = messageInput.value.trim();
    if (messageText === '') return;

    const formData = new FormData();
    formData.append('chat_id', chatId);
    formData.append('message', messageText);

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