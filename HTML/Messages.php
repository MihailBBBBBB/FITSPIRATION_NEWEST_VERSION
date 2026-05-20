<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/csrf.inc.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LogIn.php");
    exit();
}

require_once "../includes/GetMessages.inc.php";
require_once "../JS/headerFooter.php";
require_once "../includes/dbh.inc.php";
require_once "../includes/messages_repository.inc.php";
require_once "../includes/websocket_auth.inc.php";
require_once "../includes/image_storage.inc.php";

// Get selected conversation if viewing a chat
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$selected_username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$conversation_messages = [];
$current_user_id = (int) $_SESSION['user_id'];
$websocketConfig = buildWebSocketConnectionPayload($current_user_id, session_id());
$selectedConversationPresence = [
    'is_online' => false,
    'last_seen' => '',
];

// If username is provided but not user_id, look up the user_id
if ($selected_username && !$selected_user_id) {
    try {
        $selected_user_id = findUserIdByUsername($pdo, $selected_username);
    } catch (PDOException $e) {
        error_log("User lookup failed: " . $e->getMessage());
    }
}

if ($selected_user_id) {
    try {
        $conversation_messages = getConversationMessages($pdo, $current_user_id, $selected_user_id);

        if (!$selected_username) {
            $selected_username = findUsernameById($pdo, $selected_user_id);
        }
        
    } catch (PDOException $e) {
        error_log("Conversation query failed: " . $e->getMessage());
    }
}

foreach ($conversations as $convRow) {
    if ((int) ($convRow['other_user_id'] ?? 0) === (int) ($selected_user_id ?? 0)) {
        $selectedConversationPresence = [
            'is_online' => !empty($convRow['is_online']),
            'last_seen' => (string) ($convRow['last_seen'] ?? ''),
        ];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FITSPIRATION</title>
    <link rel="stylesheet" href="../CSS/Main.css?v=16">
    <link rel="stylesheet" href="../CSS/Messages.css?v=22">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <!-- Header -->
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content messages-page">
        <div class="messages-container<?php echo $selected_user_id ? ' chat-active' : ''; ?>">
        <!-- Sidebar with conversations list -->
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages</h2>
                <div class="search-container">
                    <input
                        type="text"
                        id="userSearch"
                        placeholder="Search people..."
                        autocomplete="off"
                        oninput="searchUsers(this.value)">
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>

            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <div class="no-conversations" id="noConversations">
                        <p>No conversations yet</p>
                        <p class="text-muted">Start a conversation by messaging someone</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php $conversationAvatar = buildFitspirationAvatarUrl($conv['img'] ?? '', (string) ($conv['username'] ?? 'User')); ?>
                        <?php $isOnline = !empty($conv['is_online']); ?>
                        <?php $lastSeen = (string) ($conv['last_seen'] ?? ''); ?>
                        <div class="conversation-item <?php echo ($selected_user_id == $conv['other_user_id']) ? 'active' : ''; ?>"
                             data-user-id="<?php echo (int) $conv['other_user_id']; ?>"
                             data-username="<?php echo htmlspecialchars($conv['username'] ?? 'User', ENT_QUOTES); ?>"
                             data-avatar="<?php echo htmlspecialchars($conversationAvatar, ENT_QUOTES); ?>"
                            data-is-online="<?php echo $isOnline ? '1' : '0'; ?>"
                            data-last-seen="<?php echo htmlspecialchars($lastSeen, ENT_QUOTES); ?>"
                                onclick='selectConversation(<?php echo (int) $conv['other_user_id']; ?>, <?php echo htmlspecialchars(json_encode($conv['username'] ?? 'User'), ENT_QUOTES); ?>)'>
                            
                            <div class="conversation-header">
                                <span class="conversation-user">
                                    <img class="conversation-avatar" src="<?php echo $conversationAvatar; ?>" alt="User avatar">
                                    <span class="username"><?php echo isset($conv['username']) && $conv['username'] ? htmlspecialchars($conv['username']) : 'User ' . $conv['other_user_id']; ?></span>
                                </span>
                                <span class="conversation-status" data-presence-wrap>
                                    <span class="presence-dot <?php echo $isOnline ? 'online' : 'offline'; ?>" data-presence-dot></span>
                                    <span class="presence-label" data-presence-label><?php echo $isOnline ? 'Online' : (($lastSeen !== '') ? ('Last seen ' . timeAgo($lastSeen)) : 'Offline'); ?></span>
                                </span>
                                <span class="badge" data-unread-badge <?php echo (!isset($conv['unread_count']) || $conv['unread_count'] <= 0) ? 'style="display:none;"' : ''; ?>><?php echo (int) ($conv['unread_count'] ?? 0); ?></span>
                            </div>
                            
                            <div class="conversation-preview">
                                <p class="no-translate" data-user-content="true" data-last-message><?php 
                                    $lastMsg = isset($conv['last_message']) ? $conv['last_message'] : '';
                                    echo substr(htmlspecialchars($lastMsg), 0, 50) . (strlen($lastMsg) > 50 ? '...' : '');
                                ?></p>
                                <span class="time" data-last-message-time="<?php echo htmlspecialchars((string) ($conv['last_message_time'] ?? ''), ENT_QUOTES); ?>"><?php echo isset($conv['last_message_time']) ? timeAgo($conv['last_message_time']) : 'now'; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat area -->
        <div class="messages-main"
             id="messagesApp"
             data-current-user-id="<?php echo $current_user_id; ?>"
             data-selected-user-id="<?php echo (int) ($selected_user_id ?? 0); ?>"
             data-selected-username="<?php echo htmlspecialchars((string) ($selected_username ?? ''), ENT_QUOTES); ?>"
             data-ws-url="<?php echo htmlspecialchars($websocketConfig['url'], ENT_QUOTES); ?>"
             data-ws-user-id="<?php echo (int) $websocketConfig['userId']; ?>"
             data-ws-session-id="<?php echo htmlspecialchars($websocketConfig['sessionId'], ENT_QUOTES); ?>"
             data-ws-expires-at="<?php echo (int) $websocketConfig['expiresAt']; ?>"
             data-ws-token="<?php echo htmlspecialchars($websocketConfig['token'], ENT_QUOTES); ?>"
             data-selected-user-online="<?php echo !empty($selectedConversationPresence['is_online']) ? '1' : '0'; ?>"
             data-selected-user-last-seen="<?php echo htmlspecialchars((string) ($selectedConversationPresence['last_seen'] ?? ''), ENT_QUOTES); ?>">
            <?php if ($selected_user_id): ?>
                <div class="messages-header">
                    <div class="header-info">
                        <h3 class="no-translate" data-user-content="true"><?php echo htmlspecialchars((string) $selected_username); ?></h3>
                        <p class="chat-presence" id="chatPresenceLabel"><?php echo !empty($selectedConversationPresence['is_online']) ? 'Online' : (((string) ($selectedConversationPresence['last_seen'] ?? '')) !== '' ? ('Last seen ' . timeAgo((string) $selectedConversationPresence['last_seen'])) : 'Offline'); ?></p>
                    </div>
                </div>

                <div class="messages-toolbar">
                    <input type="search" id="conversationSearch" placeholder="Search in this chat..." autocomplete="off">
                    <button type="button" id="clearConversationSearch" data-translate="Clear">Clear</button>
                    <button type="button" id="deleteConversationBtn" class="messages-toolbar-delete" data-translate="Delete chat">Delete chat</button>
                </div>

                <div class="messages-list" id="messagesList">
                    <?php foreach ($conversation_messages as $msg): ?>
                        <?php $isSentByCurrentUser = ((int) $msg['sender_id'] === $current_user_id); ?>
                        <?php $isDeletedMessage = !empty($msg['is_deleted']); ?>
                        <div class="message-item <?php echo $isSentByCurrentUser ? 'sent' : 'received'; ?><?php echo $isDeletedMessage ? ' deleted' : ''; ?>" data-message-id="<?php echo (int) $msg['message_id']; ?>" data-is-read="<?php echo !empty($msg['is_read']) ? '1' : '0'; ?>" data-is-deleted="<?php echo $isDeletedMessage ? '1' : '0'; ?>">
                            <div class="message-content">
                                <p><?php echo htmlspecialchars($isDeletedMessage ? 'Message deleted' : $msg['message_text']); ?></p>
                                <span class="message-time"><?php echo date('M d, H:i', strtotime($msg['created_at'])); ?></span>
                                <?php if ($isSentByCurrentUser && !$isDeletedMessage): ?>
                                    <span class="message-status<?php echo !empty($msg['is_read']) ? ' is-read' : ''; ?>" data-message-status><?php echo !empty($msg['is_read']) ? 'Read' : 'Sent'; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isDeletedMessage): ?>
                                <span class="message-actions" onclick="deleteMessage(<?php echo (int) $msg['message_id']; ?>)">×</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="message-input-area">
                    <div class="typing-indicator" id="typingIndicator" aria-live="polite"></div>
                    <form id="messageForm" onsubmit="sendMessage(event)">
                        <textarea id="messageInput" 
                                  name="message_text" 
                                  placeholder="Type your message..." 
                                  maxlength="5000"
                                  rows="3"></textarea>
                        <button type="submit" class="btn-send">Send</button>
                    </form>
                </div>

                <div class="modal" id="deleteConversationModal" aria-hidden="true">
                    <div class="modal-content delete-conversation-modal-content">
                        <span class="modal-close" id="deleteConversationModalClose">&times;</span>
                        <h2 data-translate="Delete chat">Delete chat</h2>
                        <p class="delete-conversation-modal-text" data-translate="Choose how you want to delete this chat.">Choose how you want to delete this chat.</p>
                        <div class="delete-conversation-modal-actions">
                            <button type="button" id="deleteConversationSelfBtn" data-translate="Delete for me">Delete for me</button>
                            <button type="button" id="deleteConversationEveryoneBtn" class="messages-toolbar-delete" data-translate="Delete for both">Delete for both</button>
                            <button type="button" id="deleteConversationCancelBtn" data-translate="Cancel">Cancel</button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-conversation-selected">
                    <div class="empty-state">
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the list or search for someone to start chatting</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </main>
    </div>

    <!-- Footer -->
    <special-footer></special-footer>

    <script src="../JS/Messages.js?v=11"></script>
</body>
</html>

<?php
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $ago = $now - $time;

    if ($ago < 60) return 'now';
    if ($ago < 3600) return floor($ago / 60) . 'm';
    if ($ago < 86400) return floor($ago / 3600) . 'h';
    if ($ago < 604800) return floor($ago / 86400) . 'd';
    
    return date('M d', $time);
}
?>




