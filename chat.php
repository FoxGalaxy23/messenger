<?php
session_start();

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

$is_chat_banned = false;
$chat_ban_details = [];

$ban_sql = "
    SELECT 
        cb.ban_reason, 
        cb.ban_end_date,
        u.username AS banner_username 
    FROM 
        chat_bans cb
    JOIN 
        users u ON cb.banner_user_id = u.user_id 
    WHERE 
        cb.banned_user_id = ? 
        AND cb.chat_id = ? 
        AND cb.is_active = 1 
        AND (cb.ban_end_date IS NULL OR cb.ban_end_date > NOW()) 
    LIMIT 1
";

$ban_stmt = $conn->prepare($ban_sql);
if ($ban_stmt === false) {
    die("Ошибка подготовки SQL-запроса на проверку бана: " . $conn->error);
}
$ban_stmt->bind_param("ii", $current_user_id, $chat_id);
$ban_stmt->execute();
$ban_result = $ban_stmt->get_result();

if ($ban_result->num_rows > 0) {
    $is_chat_banned = true;
    $chat_ban_details = $ban_result->fetch_assoc();
    
    $end_date_raw = $chat_ban_details['ban_end_date'];
    if ($end_date_raw) {
        $chat_ban_details['end_date_display'] = date('d.m.Y H:i:s', strtotime($end_date_raw));
    } else {
        $chat_ban_details['end_date_display'] = 'Навсегда';
    }
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
if ($stmt_details === false) {
    die("Ошибка подготовки SQL-запроса на детали чата: " . $conn->error);
}
$stmt_details->bind_param("i", $chat_id);
$stmt_details->execute();
$chat_result = $stmt_details->get_result();
$chat_details = $chat_result->fetch_assoc();

if (!$chat_details) {
    header("Location: index.php?error=chat_not_found");
    exit();
}

$chat_name = htmlspecialchars($chat_details['chat_name']);
$chat_avatar = htmlspecialchars($chat_details['avatar_url'] ?? 'default_chat_avatar.png'); 
$participant_count = (int)$chat_details['participant_count'];

$is_chat_admin = false;

$sql_admin = "
    SELECT 
        is_admin 
    FROM 
        user_chats 
    WHERE 
        user_id = ? AND chat_id = ?
";

$stmt_admin = $conn->prepare($sql_admin);

if ($stmt_admin === false) {
    die("Ошибка подготовки SQL-запроса на проверку админ-статуса: " . $conn->error);
}
$stmt_admin->bind_param("ii", $current_user_id, $chat_id);
$stmt_admin->execute();
$admin_result = $stmt_admin->get_result();
$admin_row = $admin_result->fetch_assoc();

if ($admin_row && isset($admin_row['is_admin']) && (int)$admin_row['is_admin'] === 1) {
    $is_chat_admin = true;
}

$stmt_admin->close();


$conn->close(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat: <?php echo htmlspecialchars($chat_name); ?></title>
    <link rel="stylesheet" type="text/css" href="components/css/style.css">
    <link rel="stylesheet" type="text/css" href="components/css/chat.css">
    <link rel="stylesheet" type="text/css" href="components/css/context.css">
    <style>
        .youtube-embed{
            border-radius: 0px;
        }
        /*Убрать полосу прокрутки у элемента*/
.element::-webkit-scrollbar {
  width: 0;
}

/*Убрать полосу прокрутки для всей страницы*/
::-webkit-scrollbar {
  width: 0;
}
    </style>
    </head>
<body>
    <div id="chatSidebar" class="chat-sidebar1" style='background-color: #23272b; border-bottom: 0px solid #23272b; position: fixed; top: 0; z-index: 20; margin-right: 5vh;'>
        
<header>
    <h3>
        <img src="<?php echo $chat_avatar; ?>" alt="Avatar" class="chat-avatar-small" style='width: 30px; height: 30px; object-fit: cover; border-radius: 20%; vertical-align: middle; margin-right: 10px;'>
        <span><?php echo $chat_name; ?> (<?php echo $participant_count; ?> memebers.)<div id="typingArea" style="color:#69c3ff;min-height:1px;font-size: 1.00rem;"></div></span>
    </h3>
</header>
</div>
<?php if (!$is_chat_banned): ?>
<div class="chat-container" style="padding-top:70px;">

    <div id="messages-display" class="messages-display">
        <div style='margin-top: 10vh;
    font-size: 0.4em;
    text-align: center;
    margin-left: 15%;
    margin-right: 15%;
    margin-bottom: 2vh;'><h1 style="background-color: gray; border-radius: 3px;">Это начало истории</h1></div>
        </div>

    <div style='margin-bottom: 10vh;'></div>
<div class="chat-input-area" style='background: white; position:fixed;bottom:0;left:0;right:0;padding:8px;border-top:0px solid rgba(248, 248, 248, 0.28); display:flex; flex-direction:column;'>
    <div id="selected-media-preview" class="selected-media-preview" style='padding: 5px 10px; font-size: 0.8em; width: 100%; box-sizing: border-box; border-radius: 8px 8px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;'></div>
    
    <form id="chat-form" class="chat-form" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:flex-end; width:100%;">
        
        <label for="media-input" style="display:flex; justify-content:center; align-items:center; width: 36px; height: 36px; background-color: rgba(248, 248, 248, 0.28); border-radius: 18px; color: #555; cursor: pointer; font-size: 1.5em; flex-shrink: 0; margin-bottom: 2px;">
            🖼️
        </label>
        
        <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
            <div id="reply-preview" style="display:none;padding:6px 10px;border-left:4px solid rgba(248, 248, 248, 0.28);background:rgba(248, 248, 248, 0.28);margin-bottom:6px;border-radius:6px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong id="reply-preview-username" style="font-size:0.8em; color: #2b8aee;"></strong>
                        <div id="reply-preview-text" style="font-size:0.85em;opacity:0.9;margin-top:2px; max-height: 40px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;"></div>
                    </div>
                    <button id="reply-cancel-btn" type="button" style="background:none;border:none;font-size:1.2em;cursor:pointer; padding: 0 4px; line-height: 1;">✖</button>
                </div>
            </div>
            
            <textarea 
                id="message-input" 
                placeholder="Сообщение" 
                rows="1" 
                required 
                style="width: 100%; 
                       min-height:36px;
                       max-height: 150px; /* Ограничение по высоте */
                       padding: 8px 12px;
                       box-sizing: border-box; 
                       border-radius: 18px;
                       border: 0px solid #ccc;
                       resize: none; /* Убираем стандартный ресайз */
                       font-size: 1em;"></textarea>
        </div>
        
        <input type="file" id="media-input" name="media_files[]" multiple accept="image/*,video/*" style="display: none;">
        
        <button type="submit" style="border-radius:18px;background:#2b8aee;color:#fff;border:none; width: 36px; height: 36px; font-size: 1.2em; padding: 0; flex-shrink: 0; margin-bottom: 2px; margin-right: 2vh;">
            ›
        </button>
        
    </form>
</div>
</div>
<?php else: ?>
    <div class="chat-input-area" style='margin-bottom: 10vh;'></div>
    
    <div style='color: white; padding: 15px; text-align: center; border-top: 0px solid #444; margin-top: 10vh;'>
        <p style='color: #ff5555; font-weight: bold;'>
            <i class="fas fa-ban"></i> Вы заблокированы в этом чате.
        </p>
        <p style='font-size: 0.9em; margin-top: 5px;'>
            Причина: <?php echo htmlspecialchars($chat_ban_details['ban_reason'] ?? 'Не указана'); ?>
        </p>
        <p style='font-size: 0.9em;'>
            Заблокировал: <?php echo htmlspecialchars($chat_ban_details['banner_username']); ?>
        </p>
        <p style='font-size: 0.9em;'>
            Срок: <?php echo $chat_ban_details['end_date_display']; ?>
        </p>
    </div>
<?php endif; ?>

<!-- HTML для контекстного меню -->
<div id="context-menu">
    <ul>
        <li id="reply-message-btn">Ответить</li>
        <li id="delete-message-btn">Удалить это сообщение</li>
        <!-- Эта кнопка будет динамически менять текст и цвет -->
        <li id="ban-user-btn" class="ban-option" style="display: none;">Забанить пользователя</li>
    </ul>
</div>


<!-- Модальное окно для бана -->
<div id="ban-modal" class="modal">
    <div class="modal-content">
        <h2 style="color: #dc3545;">Бан пользователя <span id="ban-username-display"></span></h2>
        
        <label for="ban-reason">Причина:</label>
        <textarea id="ban-reason" placeholder="Причина бана (например, спам, оскорбления)" rows="3" required></textarea>
        
        <label for="ban-duration">Срок:</label>
        <select id="ban-duration">
            <option value="perm">Навсегда</option>
            <option value="1h">1 час</option>
            <option value="1d">1 день</option>
            <option value="1w">1 неделя</option>
            <option value="1m">1 месяц</option>
        </select>
        
        <div class="modal-actions">
            <button id="ban-cancel-btn">Отмена</button>
            <button id="ban-confirm-btn" style="background-color: #dc3545;">Забанить</button>
        </div>
        <p id="ban-error-message" style="color: red; margin-top: 10px; display: none;"></p>
    </div>
</div>

<!-- Модальное окно для разбана -->
<div id="unban-modal" class="modal">
    <div class="modal-content">
        <h2 style="color: #28a745;">Разблокировка пользователя <span id="unban-username-display"></span></h2>
        <p>Вы уверены, что хотите разблокировать <strong id="unban-username-confirm"></strong> в этом чате?</p>
        <div class="modal-actions">
            <button id="unban-cancel-btn" style="background-color: #ccc;">Отмена</button>
            <button id="unban-confirm-btn" style="background-color: #28a745;">Разблокировать</button>
        </div>
        <p id="unban-error-message" style="color: red; margin-top: 10px; display: none;"></p>
    </div>
</div>

<script>
const chatId = <?php echo $chat_id; ?>;
const currentUserId = Number(<?php echo $current_user_id; ?>);
const isChatAdmin = <?php echo $is_chat_admin ? 'true' : 'false'; ?>; 

const messagesDisplay = document.getElementById('messages-display');
const chatForm = document.getElementById('chat-form');
const messageInput = document.getElementById('message-input');
const mediaInput = document.getElementById('media-input');
const selectedMediaPreview = document.getElementById('selected-media-preview');

let firstLoad = true;
let isFetching = false;
const pollInterval = 3000;

let replyToMessageId = null;
let replySnapshot = null;

const replyPreviewEl = document.getElementById('reply-preview');
const replyPreviewUsername = document.getElementById('reply-preview-username');
const replyPreviewText = document.getElementById('reply-preview-text');
const replyCancelBtn = document.getElementById('reply-cancel-btn');

function showReplyPreview(snapshot) {
    if (!snapshot) return;
    replyToMessageId = snapshot.message_id || null;
    replySnapshot = snapshot;
    replyPreviewUsername.textContent = snapshot.username || 'Unknown';
    const txt = snapshot.message ? escapeHtml(snapshot.message) : (snapshot.media && snapshot.media.length > 0 ? '[Медиа]' : '');
    replyPreviewText.innerHTML = txt;
    replyPreviewEl.style.display = 'block';
}

mediaInput.addEventListener('change', function() {
    selectedMediaPreview.innerHTML = '';
    
    if (this.files.length > 0) {
        const count = this.files.length;
        const totalSize = Array.from(this.files).reduce((sum, file) => sum + file.size, 0);

        const previewText = document.createElement('span');
        previewText.textContent = `Выбрано: ${count} файл(ов) (${(totalSize / 1024 / 1024).toFixed(2)} MB)`;
        
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

function hideReplyPreview() {
    replyToMessageId = null;
    replySnapshot = null;
    replyPreviewEl.style.display = 'none';
    replyPreviewUsername.textContent = '';
    replyPreviewText.textContent = '';
}

replyCancelBtn.addEventListener('click', function(){ hideReplyPreview(); });

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}
function linkify(text) {
    if (text === null || text === undefined) return '';
    const urlRegex = /((https?:\/\/[^\s<]+)|(www\.[^\s<]+))/gi;
    let lastIndex = 0; let result = ''; let match;
    while ((match = urlRegex.exec(text)) !== null) {
        const url = match[0]; const offset = match.index;
        result += escapeHtml(text.slice(lastIndex, offset));
        let href = url;
        if (/^www\./i.test(href)) href = 'http://' + href;
        const safeHref = escapeHtml(href); const safeText = escapeHtml(url);
        result += `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">${safeText}</a>`;
        lastIndex = offset + url.length;
    }
    result += escapeHtml(text.slice(lastIndex));
    return result;
}
function extractYouTubeIds(text) {
    if (!text) return [];
    const ids = new Set();
    const watchRegex = /(?:youtube\.com\/watch\?[^ \n\r]*v=)([A-Za-z0-9_\-]{11})/gi;
    let m;
    while ((m = watchRegex.exec(text)) !== null) ids.add(m[1]);
    const embedRegex = /(?:youtube\.com\/embed\/)([A-Za-z0-9_\-]{11})/gi;
    while ((m = embedRegex.exec(text)) !== null) ids.add(m[1]);
    const shortRegex = /(?:youtu\.be\/)([A-Za-z0-9_\-]{11})/gi;
    while ((m = shortRegex.exec(text)) !== null) ids.add(m[1]);
    return Array.from(ids);
}

function findMessageElementById(msgId) {
    if (!msgId) return null;
    return messagesDisplay.querySelector(`.message[data-msg-id="${msgId}"]`) || messagesDisplay.querySelector(`.message[data-msg-id='${msgId}']`);
}

function scrollToMessageById(msgId) {
    if (!msgId) return;
    const idStr = String(msgId);
    const tryScroll = () => {
        const target = findMessageElementById(idStr);
        if (target) {
            try {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                messagesDisplay.scrollTop = target.offsetTop - messagesDisplay.clientHeight/2;
            }
            target.classList.add('message-highlight');
            setTimeout(() => target.classList.remove('message-highlight'), 1600);
            return true;
        }
        return false;
    };

    if (tryScroll()) return;

    firstLoad = true;
    fetchMessages();

    setTimeout(() => {
        if (!tryScroll()) {
            console.warn('Цитируемое сообщение не оказалось в текущей истории (возможно, очень старое).');
        }
    }, 400);
}

function buildMessageElement(msg) {
    const isSelf = Number(msg.user_id) === currentUserId;
    const wrapper = document.createElement('div');
    wrapper.className = 'message ' + (isSelf ? 'self' : 'other');
    wrapper.dataset.msgId = msg.message_id;
    wrapper.dataset.userId = msg.user_id;
    wrapper.dataset.username = msg.username || '';

    const avatar = document.createElement('img');
    avatar.className = 'message-avatar';
    avatar.src = msg.avatar_url ? msg.avatar_url : 'components/img/default_avatar.png';
    avatar.alt = msg.username || 'User';

    const contentBox = document.createElement('div');
    contentBox.className = 'message-content-box';

    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';

    if (!isSelf) {
        const usernameSpan = document.createElement('span');
        usernameSpan.className = 'msg-username';
        usernameSpan.textContent = msg.username || '';
        bubble.appendChild(usernameSpan);
    }

    if (msg.reply_snapshot) {
        let snap = msg.reply_snapshot;
        if (typeof snap === 'string') {
            try { snap = JSON.parse(snap); } catch(e) { snap = null; }
        }
        if (snap) {
            const quoteWrap = document.createElement('div');
            quoteWrap.className = 'message-quote';
            quoteWrap.style.cssText = 'border-left:0px solid #ccc;padding:6px;margin-bottom:6px;background: rgb(248 248 248 / 28%); border-radius:7px; cursor: pointer;';
            if (snap.message_id) {
                quoteWrap.dataset.refId = String(snap.message_id);
            }

            const qUser = document.createElement('div');
            qUser.style.fontWeight = '600'; qUser.style.fontSize = '0.85em'; qUser.textContent = snap.username || 'Unknown';
            const qText = document.createElement('div');
            qText.style.fontSize = '0.9em'; qText.style.color = '#444'; qText.style.marginTop = '4px';
            qText.innerHTML = snap.message ? linkify(snap.message) : (snap.media && snap.media.length > 0 ? '[Медиа]' : '');
            quoteWrap.appendChild(qUser); quoteWrap.appendChild(qText);
            bubble.appendChild(quoteWrap);

            quoteWrap.addEventListener('click', function(e) {
                e.stopPropagation();
                const refId = this.dataset.refId;
                if (!refId) return;
                scrollToMessageById(refId);
            });
        }
    }

    if (msg.media && msg.media.length > 0) {
        const mediaContainer = document.createElement('div');
        msg.media.forEach(media => {
            let el;
            if (media.type && media.type.startsWith('image/')) {
                el = document.createElement('img'); el.src = media.path; el.className = 'chat-media-image'; el.alt = '';
            } else if (media.type && media.type.startsWith('video/')) {
                el = document.createElement('video'); el.src = media.path; el.controls = true; el.className = 'chat-media-video';
            } else {
                el = document.createElement('a'); el.href = media.path; el.target = '_blank'; el.textContent = 'File';
            }
            mediaContainer.appendChild(el);
        });
        bubble.appendChild(mediaContainer);
    }

    if (msg.message && msg.message.trim() !== '') {
        const p = document.createElement('p'); p.className = 'msg-text'; p.innerHTML = linkify(msg.message);
        bubble.appendChild(p);
    }

    const ytIds = extractYouTubeIds(msg.message || '');
    if (ytIds.length > 0) {
        ytIds.forEach(id => {
            if (typeof id === 'string' && id.length === 11) {
                const wrap = document.createElement('div'); wrap.className = 'youtube-embed';
                const iframe = document.createElement('iframe');
                iframe.src = 'https://www.youtube.com/embed/' + id;
                iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                iframe.allowFullscreen = true; iframe.loading = 'lazy';
                wrap.appendChild(iframe); bubble.appendChild(wrap);
            }
        });
    }

    const meta = document.createElement('span'); meta.className = 'msg-meta'; meta.textContent = msg.time || '';

    bubble.appendChild(meta);
    contentBox.appendChild(bubble);

    if (isSelf) {
        wrapper.appendChild(contentBox); wrapper.appendChild(avatar);
    } else {
        wrapper.appendChild(avatar); wrapper.appendChild(contentBox);
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
    }, 150); 
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
            if (firstLoad) {
                messagesDisplay.innerHTML = `<div style='margin-top: 10vh;
                    font-size: 0.4em;
                    text-align: center;
                    margin-left: 15%;
                    margin-right: 15%;
                    margin-bottom: 2vh;'>
                    <h1 style="background-color: gray; border-radius: 0px;">Это начало истории</h1>
                </div>`;
                firstLoad = false;
            }
            const existingIds = new Set(Array.from(messagesDisplay.children).map(el => String(el.dataset?.msgId)));
            const fragment = document.createDocumentFragment();
            let added = false;

            messages.forEach(msg => {
                if (msg.reply_snapshot && typeof msg.reply_snapshot === 'string') {
                    try { msg.reply_snapshot = JSON.parse(msg.reply_snapshot); } catch(e) { msg.reply_snapshot = null; }
                }
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
            console.error('Error fetching messages:', err);
        })
        .finally(() => {
            isFetching = false;
        });
}

const contextMenu = document.getElementById('context-menu');
const replyMessageBtn = document.getElementById('reply-message-btn');
const deleteMessageBtn = document.getElementById('delete-message-btn');
const banUserBtn = document.getElementById('ban-user-btn'); 

let currentContextMessage = null;
let currentMessageToDelete = null;
let currentMessageAuthorId = null;
let currentMessageAuthorName = null;
let isTargetUserBanned = false; 

window.addEventListener('click', () => {
    contextMenu.style.display = 'none';
});

async function checkBanStatus(userId) {
    try {
        const response = await fetch(`components/php/check_ban_status.php?chat_id=${chatId}&user_id=${userId}`);
        const data = await response.json();
        return data.is_banned;
    } catch (error) {
        console.error("Ошибка при проверке статуса бана:", error);
        return false;
    }
}

messagesDisplay.addEventListener('contextmenu', async function(e) {
    const messageElement = e.target.closest('.message');
    if (!messageElement) {
        return; 
    }
    
    const isSelf = messageElement.classList.contains('self');
    
    const authorId = Number(messageElement.dataset.userId);
    const authorName = messageElement.dataset.username;
    
    e.preventDefault(); 
    
    currentContextMessage = messageElement; 
    currentMessageToDelete = messageElement; 
    currentMessageAuthorId = authorId;
    currentMessageAuthorName = authorName;

    replyMessageBtn.style.display = 'list-item';

    deleteMessageBtn.style.display = (isSelf || isChatAdmin) ? 'list-item' : 'none';
    
    if (isChatAdmin && !isSelf) {
        banUserBtn.style.display = 'list-item';
        banUserBtn.textContent = 'Проверка...';
        banUserBtn.classList.remove('ban', 'unban');
        
        isTargetUserBanned = await checkBanStatus(authorId); 
        
        if (isTargetUserBanned) {
            banUserBtn.textContent = 'Разблокировать пользователя';
            banUserBtn.classList.add('unban');
        } else {
            banUserBtn.textContent = 'Забанить пользователя';
            banUserBtn.classList.add('ban');
        }
    } else {
        banUserBtn.style.display = 'none';
    }
    
    contextMenu.style.top = `${e.pageY}px`;
    contextMenu.style.left = `${e.pageX}px`;
    contextMenu.style.display = 'block';
});

replyMessageBtn.addEventListener('click', function() {
    contextMenu.style.display = 'none';
    if (!currentContextMessage) return;

    const messageId = currentContextMessage.dataset.msgId;
    const authorId = Number(currentContextMessage.dataset.userId);
    const authorName = currentContextMessage.dataset.username || '';

    const textEl = currentContextMessage.querySelector('.msg-text');
    const messageText = textEl ? textEl.innerText.trim() : '';

    const mediaEls = currentContextMessage.querySelectorAll('.chat-media-image, .chat-media-video, a');
    const mediaArr = [];
    if (mediaEls && mediaEls.length > 0) {
        mediaEls.forEach(m => {
            const src = m.src || m.href || '';
            const isImg = m.tagName.toLowerCase() === 'img';
            const isVideo = m.tagName.toLowerCase() === 'video';
            mediaArr.push({
                path: src,
                type: isImg ? 'image/*' : (isVideo ? 'video/*' : 'file/*')
            });
        });
    }

    const snapshot = {
        message_id: messageId,
        user_id: authorId,
        username: authorName,
        message: messageText,
        media: mediaArr,
        created_at: (currentContextMessage.querySelector('.msg-meta') || {}).textContent || null
    };

    showReplyPreview(snapshot);
    messageInput.focus();

    currentContextMessage = null;
    currentMessageToDelete = null;
    currentMessageAuthorId = null;
    currentMessageAuthorName = null;
});

deleteMessageBtn.addEventListener('click', function() {
    if (!currentMessageToDelete) return;
    
    const messageId = currentMessageToDelete.dataset.msgId;
    if (!messageId) {
        console.error("Не найден ID сообщения.");
        return;
    }

    currentMessageToDelete.remove();
    contextMenu.style.display = 'none';
    
    fetch('components/php/delete_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'success') {
            console.error('Ошибка при удалении сообщения:', data.message);
            firstLoad = true;
            fetchMessages();
        }
    })
    .catch(error => {
        console.error('Сетевая ошибка при удалении:', error);
        firstLoad = true;
        fetchMessages();
    });
    
    currentMessageToDelete = null;
    currentMessageAuthorId = null;
    currentMessageAuthorName = null;
});

const banModal = document.getElementById('ban-modal');
const unbanModal = document.getElementById('unban-modal');

const banUsernameDisplay = document.getElementById('ban-username-display');
const banReasonInput = document.getElementById('ban-reason');
const banDurationSelect = document.getElementById('ban-duration');
const banConfirmBtn = document.getElementById('ban-confirm-btn');
const banCancelBtn = document.getElementById('ban-cancel-btn');
const banErrorMessage = document.getElementById('ban-error-message');

const unbanUsernameDisplay = document.getElementById('unban-username-display');
const unbanUsernameConfirm = document.getElementById('unban-username-confirm');
const unbanConfirmBtn = document.getElementById('unban-confirm-btn');
const unbanCancelBtn = document.getElementById('unban-cancel-btn');
const unbanErrorMessage = document.getElementById('unban-error-message');

let targetUserIdForAction = null;

banUserBtn.addEventListener('click', function() {
    contextMenu.style.display = 'none';
    if (!currentMessageAuthorId || !currentMessageAuthorName) return;
    
    targetUserIdForAction = currentMessageAuthorId;
    
    if (isTargetUserBanned) {
        showUnbanModal(currentMessageAuthorName);
    } else {
        showBanModal(currentMessageAuthorName);
    }
});

function showBanModal(username) {
    banUsernameDisplay.textContent = username;
    banReasonInput.value = '';
    banDurationSelect.value = '1d';
    banErrorMessage.style.display = 'none';
    banModal.style.display = 'flex';
}

function hideBanModal() {
    banModal.style.display = 'none';
}

function showUnbanModal(username) {
    unbanUsernameDisplay.textContent = username;
    unbanUsernameConfirm.textContent = username;
    unbanErrorMessage.style.display = 'none';
    unbanModal.style.display = 'flex';
}

function hideUnbanModal() {
    unbanModal.style.display = 'none';
}

banCancelBtn.addEventListener('click', hideBanModal);
unbanCancelBtn.addEventListener('click', hideUnbanModal);

banConfirmBtn.addEventListener('click', function() {
    const reason = banReasonInput.value.trim();
    const duration = banDurationSelect.value;
    
    if (reason === '') {
        banErrorMessage.textContent = 'Пожалуйста, укажите причину бана.';
        banErrorMessage.style.display = 'block';
        return;
    }
    
    sendActionRequest('ban', targetUserIdForAction, reason, duration);
});

unbanConfirmBtn.addEventListener('click', function() {
    sendActionRequest('unban', targetUserIdForAction);
});

chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const messageText = messageInput.value.trim();
    const files = mediaInput.files;

    if (messageText === '' && files.length === 0) return;

    const formData = new FormData();
    formData.append('chat_id', chatId);
    formData.append('message', messageText);
    if (replyToMessageId) formData.append('reply_to', replyToMessageId);

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
            hideReplyPreview();
            firstLoad = true;
            fetchMessages(); 
        } else {
            console.error('Error sending message:', data.message || data);
        }
    })
    .catch(err => {
        console.error('AJAX error:', err);
    });
});

messageInput.addEventListener('input', function() {
    this.style.height = 'auto'; 
    const newHeight = Math.min(this.scrollHeight, 150);
    this.style.height = (newHeight) + 'px';
});

function sendActionRequest(action, userId, reason = '', duration = 'perm') {
    const isBan = action === 'ban';
    const confirmButton = isBan ? banConfirmBtn : unbanConfirmBtn;
    const modalToHide = isBan ? hideBanModal : hideUnbanModal;
    const errorMessageEl = isBan ? banErrorMessage : unbanErrorMessage;

    if (!userId) return;
    
    confirmButton.disabled = true;
    confirmButton.textContent = isBan ? 'Баню...' : 'Разблокирую...';
    errorMessageEl.style.display = 'none';

    const payload = {
        chat_id: chatId,
        banned_user_id: userId,
        action: action,
    };
    
    if (isBan) {
        payload.reason = reason;
        payload.duration = duration;
    }

    fetch('components/php/ban_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        confirmButton.disabled = false;
        confirmButton.textContent = isBan ? 'Забанить' : 'Разблокировать';
        
        if (data.status === 'success') {
            modalToHide();
            isTargetUserBanned = (action === 'ban');
            firstLoad = true; 
            fetchMessages(); 
        } else {
            errorMessageEl.textContent = data.message || `Неизвестная ошибка при ${isBan ? 'бане' : 'разбане'}.`;
            errorMessageEl.style.display = 'block';
            console.error(`${isBan ? 'Бан' : 'Разбан'} ошибка:`, data.message);
        }
    })
    .catch(error => {
        confirmButton.disabled = false;
        confirmButton.textContent = isBan ? 'Забанить' : 'Разблокировать';
        errorMessageEl.textContent = 'Ошибка сети: Не удалось связаться с сервером.';
        errorMessageEl.style.display = 'block';
        console.error('AJAX error:', error);
    });
}
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


<!-- TYPING-INTEGRATION-INSERTION -->
<!-- Typing integration UI (added by assistant) -->

<script>
(function(){
  // Минимальная интеграция индикатора набора текста (сначала WebSocket, затем Fallback)
  const STOP_DELAY = 3000; // ms
  // Берем ID чата и имя пользователя из переменных основного скрипта chat.php
  const CHAT_ID = String(<?php echo $chat_id; ?>);
  const USERNAME = '<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>';

  // Находим поле ввода
  const input = document.getElementById('message-input'); // Используем ID поля ввода из chat.php
  const typingArea = document.getElementById('typingArea');

  // Выбираем схему websocket
  const wsScheme = (location.protocol === 'https:') ? 'wss' : 'ws';
  const wsHost = location.hostname;
  const wsPort = 8080; // порт вашего Ratchet сервера
  const WS_URL = wsScheme + '://' + wsHost + ':' + wsPort + '/ws?chat_id=' + encodeURIComponent(CHAT_ID) + '&user=' + encodeURIComponent(USERNAME);

  let ws = null;
  let isTyping = false;
  let stopTimer = null;

  function startWebSocket() {
    try {
      ws = new WebSocket(WS_URL);
    } catch (e) {
      console.warn('WS creation failed', e);
      ws = null;
      return;
    }
    ws.addEventListener('open', function(){ console.log('Typing WS open'); });
    ws.addEventListener('message', function(ev){
      try {
        const data = JSON.parse(ev.data);
        // Проверяем, чтобы не отображать свой же статус
        if (data.user === USERNAME) return; 

        if (data.type === 'typing') {
          // Если кто-то печатает, отображаем его имя
          typingArea.textContent = data.user + ' печатает...';
        } else if (data.type === 'stop_typing') {
          // Когда прекращает, очищаем
          typingArea.textContent = '';
        }
      } catch(e) { console.error('Typing WS parse error', e); }
    });
    // Повторное подключение при закрытии или ошибке
    ws.addEventListener('close', function(){ console.log('Typing WS closed. Retrying...'); ws = null; setTimeout(startWebSocket, 5000); });
    ws.addEventListener('error', function(){ console.warn('Typing WS error'); ws = null; });
  }

  // ... (Остальной код функций отправки WS и Fallback остается прежним) ...

  function sendTypingWS() {
    if (!ws || ws.readyState !== WebSocket.OPEN) return false;
    // Отправляем тип события, ID чата и реальное имя пользователя
    ws.send(JSON.stringify({type:'typing', chat_id: CHAT_ID, user: USERNAME}));
    return true;
  }
  function sendStopTypingWS() {
    if (!ws || ws.readyState !== WebSocket.OPEN) return false;
    ws.send(JSON.stringify({type:'stop_typing', chat_id: CHAT_ID, user: USERNAME}));
    return true;
  }

  // Fallback endpoints (пути к вашим файлам)
  const typingUpdateUrl = 'typing_update.php'; // Убедитесь, что это правильный путь
  const typingGetUrl = 'typing_get.php'; // Убедитесь, что это правильный путь

  function sendTypingFallback() {
    const url = typingUpdateUrl + '?chat_id=' + encodeURIComponent(CHAT_ID) + '&user=' + encodeURIComponent(USERNAME) + '&type=typing';
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url);
    } else {
      fetch(url).catch(()=>{});
    }
  }
  function sendStopTypingFallback() {
    const url = typingUpdateUrl + '?chat_id=' + encodeURIComponent(CHAT_ID) + '&user=' + encodeURIComponent(USERNAME) + '&type=stop';
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url);
    } else {
      fetch(url).catch(()=>{});
    }
  }

  // Polling для получения печатающих пользователей (fallback)
  function startPolling() {
    setInterval(function(){
      fetch(typingGetUrl + '?chat_id=' + encodeURIComponent(CHAT_ID)).then(r=>r.json()).then(data=>{
        if (data.typing && data.typing.length) {
          // Фильтруем самого себя, чтобы не показывать, что "ты печатаешь"
          const othersTyping = data.typing.filter(user => user !== USERNAME);
          if (othersTyping.length) {
              typingArea.textContent = othersTyping.join(', ') + ' печатает...';
          } else {
              typingArea.textContent = '';
          }
        } else {
          typingArea.textContent = '';
        }
      }).catch(()=>{});
    }, 1000);
  }

  // Привязка к событиям ввода
  function attachInputHandlers() {
    if (!input) {
      console.warn('No message input found for typing integration.');
      return;
    }
    input.addEventListener('input', function(){
      if (!isTyping) {
        isTyping = true;
        // пытаемся WS, иначе fallback
        if (!sendTypingWS()) sendTypingFallback();
      }
      clearTimeout(stopTimer);
      stopTimer = setTimeout(function(){
        isTyping = false;
        if (!sendStopTypingWS()) sendStopTypingFallback();
      }, STOP_DELAY);
    });
    // при уходе фокуса, отправляем stop немедленно
    input.addEventListener('blur', function(){
      if (isTyping) {
        isTyping = false;
        if (!sendStopTypingWS()) sendStopTypingFallback();
      }
    });
  }

  // Инициализация
  startWebSocket();
  attachInputHandlers();
  startPolling(); // Fallback-опрос (можно отключить, если вы уверены в WS)
})();
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>