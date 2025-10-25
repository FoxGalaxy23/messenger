<?php
include 'components/php/db_connect.php'; 
check_login(); 
$current_user_id = $_SESSION['user_id']; 
include 'components/php/check_ban.php'; 

$sql = "
    SELECT 
        c.chat_id, 
        c.chat_name,
        c.avatar_url
    FROM 
        chats c
    JOIN 
        user_chats uc ON c.chat_id = uc.chat_id
    WHERE 
        uc.user_id = ?  
    ORDER BY 
        c.chat_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id); 
$stmt->execute();
$result = $stmt->get_result();

$initial_chat_id = isset($_GET['chat_id']) && is_numeric($_GET['chat_id']) ? (int)$_GET['chat_id'] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="shortcut icon" href="<?php echo $logo; ?>"/>
    <title><?php echo $name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" type="text/css" href="components/css/index.css">
    <meta name="description" content="<?php echo $description; ?>">
    <link rel="stylesheet" type="text/css" href="components/css/context.css">
</head>
<body>

<div class="desktop-layout">
    
    <div id="chatSidebar" class="chat-sidebar" style="position: relative;">
        
        <header class="p-4 border-b border-gray-200 bg-white sticky top-0 z-10 flex-shrink-0">
            <h1 class="text-xl font-bold text-gray-800 flex items-center">
                <span class="text-indigo-500 mr-2"><?php echo($name); ?></span>
            </h1>
        </header>

        <div class="selection-container" style="padding-bottom: 70px;">
            <div class="selection-container">
            <?php
            if ($result->num_rows > 0) {
                $result->data_seek(0);
                echo "<ul class='chat-list pt-1'>";
                while($row = $result->fetch_assoc()) {
                    $avatar_url = htmlspecialchars($row['avatar_url'] ?? 'https://placehold.co/40x40/95a5a6/ffffff?text=C');
                    $chat_name = htmlspecialchars($row['chat_name']);
                    $chat_id = htmlspecialchars($row['chat_id']);
                    
                    $active_class = $initial_chat_id == $chat_id ? ' active' : '';
                    
                    echo "<li class='chat-list-item' data-chat-id='{$chat_id}'>";
                    echo "<a href='#' data-chat-id='{$chat_id}' data-chat-url='chat.php?chat_id={$chat_id}' class='chat-link chat-item rounded-lg mx-2 my-1{$active_class}'>"; 
                    echo "<img src='{$avatar_url}' alt='Аватар чата {$chat_name}' class='chat-list-avatar rounded-full shadow-sm'>"; 
                    echo "<span class='chat-name-text'>{$chat_name}</span>";
                    echo "</a></li>";
                }
                echo "</ul>";
            } else {
                echo "<p id='noChatsMessage' class='p-4 text-center text-gray-500'>У вас пока нет чатов.</p>";
            }
            ?>
        </div>
        <div id="bottomNav" class="flex justify-around items-center flex-shrink-0"
             style="bottom: 0; left: 0; width: 100%; z-index: 20;">
            
            <a href="#" data-url="make_chat.php" class="nav-link flex flex-col items-center text-indigo-500 p-2 hover:text-indigo-700 transition duration-150">
                <span class="text-xl">✈️</span>
                <span class="text-xs mt-1 font-medium">Make chat</span>
            </a>
            
            <a href="#" data-url="settings.php" class="nav-link flex flex-col items-center text-gray-600 p-2 hover:text-gray-800 transition duration-150">
                <span class="text-xl">
                    <img src="<?php echo htmlspecialchars($_SESSION['avatar_url'] ?? 'https://placehold.co/40x40/95a5a6/ffffff?text=U'); ?>" style='border-radius: 50%; width: 4.5vh; height: 4.5vh;' alt="Аватар пользователя">
                </span>
                <span class="text-xs mt-1 font-medium"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
            </a>

            <a href="logout.php" class="flex flex-col items-center text-red-500 p-2 hover:text-red-700 transition duration-150">
                <span class="text-xl">🚪</span>
                <span class="text-xs mt-1 font-medium">Logout</span>
            </a>
            
        </div>
        </div>
        
    </div>

    <div id="chatMainArea" class="chat-main-area">
        
        <iframe id="chatIframe" class="chat-iframe" src="" allow="clipboard-read; clipboard-write"></iframe>
        
        <div id="welcomePlaceholder" class="welcome-page">
            <div style='text-align: center;'>
                <h2 class="text-2xl font-semibold mb-2 text-gray-400">Hi there, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h2>
                <p class="text-lg text-gray-500">
                    Choose any available chat.
                </p>
            </div>
        </div>
        
    </div>
    
</div>

<!-- Контекстное меню -->
<div id="contextMenu" class="context-menu">
    <div id="unsubscribeItem" class="context-menu-item danger">Отписаться</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chatLinks = document.querySelectorAll('.chat-link');
        const chatListItems = document.querySelectorAll('.chat-list-item');
        const navLinks = document.querySelectorAll('.nav-link');
        const chatIframe = document.getElementById('chatIframe');
        const placeholder = document.getElementById('welcomePlaceholder');
        const chatMainArea = document.getElementById('chatMainArea');
        const chatSidebar = document.getElementById('chatSidebar');
        const bottomNav = document.getElementById('bottomNav'); 
        const mediaQuery = window.matchMedia('(min-width: 768px)');
        const contextMenu = document.getElementById('contextMenu');
        const unsubscribeItem = document.getElementById('unsubscribeItem');
        const noChatsMessage = document.getElementById('noChatsMessage');
        const chatListContainer = document.querySelector('.chat-list');

        let activeChatId = null;
        let contextMenuChatId = null;

        /**
         * 
         * @param {string} url
         * @param {string|null} [chatId=null]
         */
        function setActiveContent(url, chatId = null) {
            chatIframe.src = url;
            placeholder.classList.add('hidden');
            chatIframe.style.display = 'block';
            
            document.querySelectorAll('.chat-link').forEach(link => {
                link.classList.remove('active');
            });
            activeChatId = chatId;

            if (chatId) {
                const activeLink = document.querySelector(`.chat-link[data-chat-id="${chatId}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
                history.pushState(null, '', `?chat_id=${chatId}`);
            } else {
                history.pushState(null, '', window.location.pathname);
            }
            
            if (!mediaQuery.matches) {
                chatSidebar.style.display = 'none';
                chatMainArea.style.display = 'flex';
                bottomNav.style.display = 'none';
            }
        }
        
        function hideContextMenu() {
            contextMenu.style.display = 'none';
            contextMenuChatId = null;
        }

        document.querySelectorAll('.chat-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const chatUrl = link.getAttribute('data-chat-url');
                const chatId = link.getAttribute('data-chat-id');
                setActiveContent(chatUrl, chatId);
            });
        });

        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = link.getAttribute('data-url');
                
                if (url) {
                    setActiveContent(url); 
                }
            });
        });

        document.addEventListener('contextmenu', (e) => {
            const chatListItem = e.target.closest('.chat-list-item');
            if (chatListItem) {
                e.preventDefault(); 
                
                contextMenuChatId = chatListItem.getAttribute('data-chat-id');
                
                contextMenu.style.left = `${e.clientX}px`;
                contextMenu.style.top = `${e.clientY}px`;
                contextMenu.style.display = 'block';
            } else {
                hideContextMenu();
            }
        });
        
        document.addEventListener('click', (e) => {
            if (contextMenu.style.display === 'block' && !contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });

        unsubscribeItem.addEventListener('click', async () => {
            if (!contextMenuChatId) {
                hideContextMenu();
                return;
            }
            
            if (activeChatId === contextMenuChatId) {
                activeChatId = null;
                chatIframe.src = '';
                chatIframe.style.display = 'none';
                placeholder.classList.remove('hidden');
                history.pushState(null, '', window.location.pathname);
            }

            try {
                const response = await fetch('components/php/unsubscribe_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `chat_id=${contextMenuChatId}`
                });
                
                const result = await response.json();

                if (result.success) {
                    const itemToRemove = document.querySelector(`.chat-list-item[data-chat-id="${contextMenuChatId}"]`);
                    if (itemToRemove) {
                        itemToRemove.remove();
                    }
                    
                    if (chatListContainer && chatListContainer.children.length === 0) {
                         if (!noChatsMessage) {
                            const newP = document.createElement('p');
                            newP.id = 'noChatsMessage';
                            newP.className = 'p-4 text-center text-gray-500';
                            newP.textContent = 'У вас пока нет чатов.';
                            document.querySelector('.selection-container').appendChild(newP);
                        }
                    }

                } else {
                    console.error('Ошибка при отписке:', result.message);
                }
            } catch (error) {
                console.error('Сетевая ошибка при отписке:', error);
            }

            hideContextMenu();
        });


        window.addEventListener('message', (event) => {
            if (event.data === 'CHAT_CLOSE_REQUEST' && !mediaQuery.matches) {
                
                activeChatId = null;
                chatIframe.src = '';
                chatIframe.style.display = 'none';
                placeholder.classList.remove('hidden');

                chatSidebar.style.display = 'block';
                chatMainArea.style.display = 'none';
                bottomNav.style.display = 'flex';
                
                history.pushState(null, '', window.location.pathname);

                document.querySelectorAll('.chat-link').forEach(link => link.classList.remove('active'));
            }
        }, false);
        
        const initialChatId = <?php echo json_encode($initial_chat_id); ?>;
        if (initialChatId) {
            const initialLink = document.querySelector(`.chat-link[data-chat-id="${initialChatId}"]`);
            if (initialLink) {
                const initialUrl = initialLink.getAttribute('data-chat-url');
                setActiveContent(initialUrl, initialChatId); 
            }
        } 

        function handleMediaQueryChange(e) {
            if (e.matches) {
                chatSidebar.style.display = 'block';
                chatMainArea.style.display = 'flex';
                bottomNav.style.display = 'flex';
            } else if (activeChatId) {
                chatSidebar.style.display = 'none';
                chatMainArea.style.display = 'flex';
                bottomNav.style.display = 'none';
            } else {
                chatSidebar.style.display = 'block';
                chatMainArea.style.display = 'none';
                bottomNav.style.display = 'flex';
            }
        }

        handleMediaQueryChange(mediaQuery);
        mediaQuery.addListener(handleMediaQueryChange);
    });
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</body>
</html>
