const messagesApp = document.getElementById('messagesApp');

const messageState = {
    app: messagesApp,
    currentUserId: Number(messagesApp?.dataset.currentUserId || 0),
    selectedUserId: Number(messagesApp?.dataset.selectedUserId || 0),
    selectedUsername: messagesApp?.dataset.selectedUsername || '',
    selectedUserOnline: Number(messagesApp?.dataset.selectedUserOnline || 0) === 1,
    selectedUserLastSeen: messagesApp?.dataset.selectedUserLastSeen || '',
    wsUrl: messagesApp?.dataset.wsUrl || '',
    wsUserId: Number(messagesApp?.dataset.wsUserId || 0),
    wsSessionId: messagesApp?.dataset.wsSessionId || '',
    wsExpiresAt: Number(messagesApp?.dataset.wsExpiresAt || 0),
    wsToken: messagesApp?.dataset.wsToken || '',
    socket: null,
    reconnectTimer: null,
    reconnectAttempts: 0,
    intentionallyClosed: false,
    typingStopTimer: null,
    typingIndicatorHideTimer: null,
    presenceRefreshTimer: null,
    hasSentTypingStart: false,
    activeSearchQuery: '',
};

function selectConversation(userId, username) {
    window.location.href = `Messages.php?user_id=${userId}&username=${encodeURIComponent(username)}`;
}

function startChat(userId, username) {
    window.location.href = `Messages.php?user_id=${userId}&username=${encodeURIComponent(username)}`;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getMessagesList() {
    return document.getElementById('messagesList');
}

function getConversationList() {
    return document.getElementById('conversationsList');
}

function getMessageInput() {
    return document.getElementById('messageInput');
}

function getTypingIndicator() {
    return document.getElementById('typingIndicator');
}

function getChatPresenceLabel() {
    return document.getElementById('chatPresenceLabel');
}

function scrollMessagesToBottom() {
    const messagesList = getMessagesList();
    if (messagesList) {
        messagesList.scrollTop = messagesList.scrollHeight;
    }
}

function formatMessageTime(dateString) {
    if (!dateString) {
        return 'now';
    }

    const date = new Date(dateString.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return 'now';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatConversationTime(dateString) {
    if (!dateString) {
        return 'now';
    }

    const date = new Date(dateString.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return 'now';
    }

    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();
    if (sameDay) {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
    }).format(date);
}

function formatLastSeen(dateString) {
    if (!dateString) {
        return 'Offline';
    }

    const date = new Date(dateString.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return 'Offline';
    }

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) {
        return 'Last seen just now';
    }
    if (seconds < 3600) {
        return `Last seen ${Math.floor(seconds / 60)}m ago`;
    }
    if (seconds < 86400) {
        return `Last seen ${Math.floor(seconds / 3600)}h ago`;
    }
    if (seconds < 604800) {
        return `Last seen ${Math.floor(seconds / 86400)}d ago`;
    }

    return `Last seen ${new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
    }).format(date)}`;
}

function formatPresenceLabel(isOnline, lastSeen) {
    if (isOnline) {
        return 'Online';
    }

    return formatLastSeen(lastSeen);
}

function searchUsers(query) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) {
        return;
    }

    const trimmedQuery = query.trim();
    if (trimmedQuery.length < 2) {
        searchResults.innerHTML = '';
        searchResults.classList.remove('show');
        return;
    }

    fetch(`../includes/searchUsers.inc.php?search=${encodeURIComponent(trimmedQuery)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.users) || data.users.length === 0) {
                searchResults.innerHTML = '<div class="search-result-item disabled">No users found</div>';
                searchResults.classList.add('show');
                return;
            }

            searchResults.innerHTML = data.users.map(user => {
                const safeUsername = escapeHtml(user.username || 'User');
                const encodedUsername = encodeURIComponent(user.username || 'User');
                const avatarName = (user.img || '').replace(/[^a-zA-Z0-9._-]/g, '');
                const avatarSvg = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160"><rect width="160" height="160" rx="80" fill="#e5e7eb"/><circle cx="80" cy="58" r="24" fill="#9ca3af"/><path d="M34 136c8-24 28-38 46-38s38 14 46 38" fill="#9ca3af"/></svg>`)}`;
                const avatarSrc = avatarName ? `../images/${avatarName}` : avatarSvg;
                return `
                    <button type="button" class="search-result-item" onclick="startChat(${user.id}, decodeURIComponent('${encodedUsername}'))">
                        <span class="search-user-row">
                            <img class="search-avatar" src="${avatarSrc}" alt="${safeUsername} avatar" onerror="this.src='${avatarSvg}'">
                            <span class="username">${safeUsername}</span>
                        </span>
                    </button>
                `;
            }).join('');
            searchResults.classList.add('show');
        })
        .catch(() => {
            searchResults.innerHTML = '';
            searchResults.classList.remove('show');
        });
}

function buildSocketUrl() {
    if (!messageState.wsUrl) {
        return '';
    }

    const url = new URL(messageState.wsUrl);
    url.searchParams.set('user_id', String(messageState.wsUserId));
    url.searchParams.set('session_id', messageState.wsSessionId);
    url.searchParams.set('expires_at', String(messageState.wsExpiresAt));
    url.searchParams.set('token', messageState.wsToken);
    return url.toString();
}

function isSocketOpen() {
    return messageState.socket && messageState.socket.readyState === WebSocket.OPEN;
}

function scheduleReconnect() {
    if (messageState.intentionallyClosed || !messageState.wsUrl) {
        return;
    }

    clearTimeout(messageState.reconnectTimer);
    const delay = Math.min(1000 * (messageState.reconnectAttempts + 1), 5000);
    messageState.reconnectAttempts += 1;
    messageState.reconnectTimer = window.setTimeout(connectWebSocket, delay);
}

function sendSocketPayload(payload) {
    if (!isSocketOpen()) {
        return false;
    }

    messageState.socket.send(JSON.stringify(payload));
    return true;
}

function hideUnreadBadge(userId) {
    const badge = document.querySelector(`.conversation-item[data-user-id="${String(userId)}"] [data-unread-badge]`);
    if (!badge) {
        return;
    }

    badge.textContent = '';
    badge.style.display = 'none';
}

function incrementUnreadBadge(userId) {
    const badge = document.querySelector(`.conversation-item[data-user-id="${String(userId)}"] [data-unread-badge]`);
    if (!badge) {
        return;
    }

    const currentValue = Number(badge.textContent || 0);
    badge.textContent = String(currentValue + 1);
    badge.style.display = '';
}

function updatePresenceElement(conversationItem, isOnline, lastSeen) {
    if (!conversationItem) {
        return;
    }

    conversationItem.dataset.isOnline = isOnline ? '1' : '0';
    conversationItem.dataset.lastSeen = lastSeen || '';

    const presenceDot = conversationItem.querySelector('[data-presence-dot]');
    if (presenceDot) {
        presenceDot.classList.toggle('online', !!isOnline);
        presenceDot.classList.toggle('offline', !isOnline);
    }

    const presenceLabel = conversationItem.querySelector('[data-presence-label]');
    if (presenceLabel) {
        presenceLabel.textContent = formatPresenceLabel(isOnline, lastSeen);
    }
}

function updateActiveHeaderPresence(isOnline, lastSeen) {
    const label = getChatPresenceLabel();
    if (!label) {
        return;
    }

    label.textContent = formatPresenceLabel(isOnline, lastSeen);
    label.classList.toggle('online', !!isOnline);
}

function refreshConversationPresenceLabels() {
    const items = document.querySelectorAll('.conversation-item[data-user-id]');
    if (!items.length) {
        return;
    }

    items.forEach(item => {
        const isOnline = Number(item.dataset.isOnline || 0) === 1;
        const lastSeen = String(item.dataset.lastSeen || '');
        updatePresenceElement(item, isOnline, lastSeen);
    });

    if (messageState.selectedUserId) {
        const selectedItem = document.querySelector(`.conversation-item[data-user-id="${String(messageState.selectedUserId)}"]`);
        if (selectedItem) {
            const isOnline = Number(selectedItem.dataset.isOnline || 0) === 1;
            const lastSeen = String(selectedItem.dataset.lastSeen || '');
            messageState.selectedUserOnline = isOnline;
            messageState.selectedUserLastSeen = lastSeen;
            updateActiveHeaderPresence(isOnline, lastSeen);
        }
    }
}

function handlePresenceUpdate(payload) {
    const userId = Number(payload.user_id || 0);
    if (!userId) {
        return;
    }

    const isOnline = !!payload.is_online;
    const lastSeen = payload.last_seen || '';

    const item = document.querySelector(`.conversation-item[data-user-id="${String(userId)}"]`);
    updatePresenceElement(item, isOnline, lastSeen);

    if (userId === messageState.selectedUserId) {
        messageState.selectedUserOnline = isOnline;
        messageState.selectedUserLastSeen = lastSeen;
        updateActiveHeaderPresence(isOnline, lastSeen);
    }
}

function hideTypingIndicator() {
    const typingIndicator = getTypingIndicator();
    if (!typingIndicator) {
        return;
    }

    typingIndicator.textContent = '';
    typingIndicator.classList.remove('show');
}

function showTypingIndicator(username) {
    const typingIndicator = getTypingIndicator();
    if (!typingIndicator) {
        return;
    }

    typingIndicator.textContent = `${username || 'User'} is typing...`;
    typingIndicator.classList.add('show');

    clearTimeout(messageState.typingIndicatorHideTimer);
    messageState.typingIndicatorHideTimer = window.setTimeout(hideTypingIndicator, 2500);
}

function handleTypingIndicator(payload) {
    const fromUserId = Number(payload.from_user_id || 0);
    if (!fromUserId || fromUserId !== messageState.selectedUserId) {
        return;
    }

    if (payload.is_typing) {
        showTypingIndicator(messageState.selectedUsername || 'User');
        return;
    }

    hideTypingIndicator();
}

function sendTypingEvent(type) {
    if (!messageState.selectedUserId) {
        return;
    }

    sendSocketPayload({
        type,
        recipient_id: messageState.selectedUserId,
    });
}

function scheduleTypingStop() {
    clearTimeout(messageState.typingStopTimer);
    messageState.typingStopTimer = window.setTimeout(() => {
        if (!messageState.hasSentTypingStart) {
            return;
        }

        sendTypingEvent('typing_stop');
        messageState.hasSentTypingStart = false;
    }, 1200);
}

function markConversationRead(useSocket = true) {
    if (!messageState.selectedUserId) {
        return;
    }

    const payload = {
        type: 'mark_conversation_read',
        other_user_id: messageState.selectedUserId,
    };

    if (useSocket && sendSocketPayload(payload)) {
        hideUnreadBadge(messageState.selectedUserId);
        return;
    }

    const formData = new FormData();
    formData.append('action', 'mark_conversation_read');
    formData.append('other_user_id', String(messageState.selectedUserId));
    if (typeof appendCsrfToken === 'function') {
        appendCsrfToken(formData);
    }

    fetch('../includes/messages.inc.php', {
        method: 'POST',
        body: formData,
    }).catch(() => {});

    hideUnreadBadge(messageState.selectedUserId);
}

function connectWebSocket() {
    const socketUrl = buildSocketUrl();
    if (!socketUrl || typeof WebSocket === 'undefined') {
        return;
    }

    try {
        messageState.socket = new WebSocket(socketUrl);
    } catch (error) {
        scheduleReconnect();
        return;
    }

    messageState.socket.addEventListener('open', () => {
        messageState.reconnectAttempts = 0;
        markConversationRead(true);
    });

    messageState.socket.addEventListener('message', event => {
        handleSocketMessage(event.data);
    });

    messageState.socket.addEventListener('close', () => {
        messageState.socket = null;
        scheduleReconnect();
    });

    messageState.socket.addEventListener('error', () => {
        if (messageState.socket) {
            messageState.socket.close();
        }
    });
}

function handleSocketMessage(rawMessage) {
    let payload;

    try {
        payload = JSON.parse(rawMessage);
    } catch (error) {
        return;
    }

    if (payload.type === 'message_created' && payload.message) {
        handleIncomingMessage(payload.message);
        return;
    }

    if (payload.type === 'message_deleted' && payload.message_id) {
        markMessageDeleted(payload.message_id, payload.placeholder_text || 'Message deleted');
        return;
    }

    if (payload.type === 'presence_update') {
        handlePresenceUpdate(payload);
        return;
    }

    if (payload.type === 'typing_indicator') {
        handleTypingIndicator(payload);
        return;
    }

    if (payload.type === 'conversation_read') {
        handleConversationRead(payload);
        return;
    }

    if (payload.type === 'error' && payload.message) {
        alert(payload.message);
    }
}

function createMessageElement(message) {
    const isSentByCurrentUser = Number(message.sender_id) === messageState.currentUserId;
    const isRead = !!message.is_read;
    const isDeleted = !!message.is_deleted;
    const messageText = isDeleted ? 'Message deleted' : String(message.message_text || '');

    const wrapper = document.createElement('div');
    wrapper.className = `message-item ${isSentByCurrentUser ? 'sent' : 'received'}${isDeleted ? ' deleted' : ''}`;
    wrapper.dataset.messageId = String(message.message_id);
    wrapper.dataset.isRead = isRead ? '1' : '0';
    wrapper.dataset.isDeleted = isDeleted ? '1' : '0';
    wrapper.innerHTML = `
        <div class="message-content">
            <p>${escapeHtml(messageText)}</p>
            <span class="message-time">${escapeHtml(formatMessageTime(message.created_at))}</span>
            ${isSentByCurrentUser && !isDeleted ? `<span class="message-status${isRead ? ' is-read' : ''}" data-message-status>${isRead ? 'Read' : 'Sent'}</span>` : ''}
        </div>
        ${!isDeleted ? `<span class="message-actions" onclick="deleteMessage(${Number(message.message_id)})">×</span>` : ''}
    `;
    return wrapper;
}

function setMessageReadState(messageId, isRead) {
    const messageElement = document.querySelector(`.message-item[data-message-id="${String(messageId)}"]`);
    if (!messageElement || !messageElement.classList.contains('sent')) {
        return;
    }

    messageElement.dataset.isRead = isRead ? '1' : '0';
    const statusEl = messageElement.querySelector('[data-message-status]');
    if (!statusEl) {
        return;
    }

    statusEl.textContent = isRead ? 'Read' : 'Sent';
    statusEl.classList.toggle('is-read', !!isRead);
}

function handleConversationRead(payload) {
    const readerUserId = Number(payload.reader_user_id || 0);
    if (!readerUserId || readerUserId !== messageState.selectedUserId) {
        return;
    }

    const messageIds = Array.isArray(payload.message_ids) ? payload.message_ids : [];
    messageIds.forEach(messageId => {
        setMessageReadState(Number(messageId), true);
    });
}

function applyConversationSearch(query) {
    const messagesList = getMessagesList();
    if (!messagesList) {
        return;
    }

    const normalized = String(query || '').trim().toLowerCase();
    messageState.activeSearchQuery = normalized;

    const messageItems = messagesList.querySelectorAll('.message-item');
    messageItems.forEach(item => {
        const textNode = item.querySelector('.message-content p');
        const text = (textNode?.textContent || '').toLowerCase();
        const isMatch = normalized === '' || text.includes(normalized);

        item.classList.toggle('message-filter-hidden', !isMatch);
        item.classList.toggle('message-match', normalized !== '' && isMatch);
    });
}

function appendMessage(message) {
    const messagesList = getMessagesList();
    if (!messagesList || !message.message_id) {
        return;
    }

    if (messagesList.querySelector(`[data-message-id="${String(message.message_id)}"]`)) {
        return;
    }

    messagesList.appendChild(createMessageElement(message));

    if (messageState.activeSearchQuery) {
        applyConversationSearch(messageState.activeSearchQuery);
    }

    scrollMessagesToBottom();
}

function removeMessageElement(messageId) {
    const messageElement = document.querySelector(`[data-message-id="${String(messageId)}"]`);
    if (messageElement) {
        messageElement.remove();
    }
}

function markMessageDeleted(messageId, placeholderText = 'Message deleted') {
    const messageElement = document.querySelector(`.message-item[data-message-id="${String(messageId)}"]`);
    if (!messageElement) {
        return;
    }

    messageElement.dataset.isDeleted = '1';
    messageElement.classList.add('deleted');

    const textNode = messageElement.querySelector('.message-content p');
    if (textNode) {
        textNode.textContent = placeholderText;
    }

    const statusEl = messageElement.querySelector('[data-message-status]');
    if (statusEl) {
        statusEl.remove();
    }

    const actionEl = messageElement.querySelector('.message-actions');
    if (actionEl) {
        actionEl.remove();
    }
}

function getConversationPartnerId(message) {
    return Number(message.sender_id) === messageState.currentUserId
        ? Number(message.recipient_id)
        : Number(message.sender_id);
}

function getConversationIdentity(message, partnerId) {
    if (partnerId === Number(message.sender_id)) {
        return {
            username: message.sender_username || 'User',
            img: message.sender_img || '',
        };
    }

    return {
        username: message.recipient_username || 'User',
        img: message.recipient_img || '',
    };
}

function buildConversationItem(message, partnerId) {
    const conversationList = getConversationList();
    if (!conversationList) {
        return null;
    }

    const identity = getConversationIdentity(message, partnerId);
    const avatarName = String(identity.img || '').replace(/[^a-zA-Z0-9._-]/g, '');
    const avatarSrc = avatarName ? `../images/${avatarName}` : '../images/no_image.jpg';
    const safeUsername = escapeHtml(identity.username || 'User');

    const element = document.createElement('div');
    element.className = `conversation-item ${partnerId === messageState.selectedUserId ? 'active' : ''}`;
    element.dataset.userId = String(partnerId);
    element.dataset.username = identity.username || 'User';
    element.dataset.avatar = avatarSrc;
    element.dataset.isOnline = '0';
    element.dataset.lastSeen = '';
    element.onclick = () => selectConversation(partnerId, identity.username || 'User');
    element.innerHTML = `
        <div class="conversation-header">
            <span class="conversation-user">
                <img class="conversation-avatar" src="${avatarSrc}" alt="${safeUsername} avatar" onerror="this.src='../images/no_image.jpg'">
                <span class="username">${safeUsername}</span>
            </span>
            <span class="conversation-status" data-presence-wrap>
                <span class="presence-dot offline" data-presence-dot></span>
                <span class="presence-label" data-presence-label>Offline</span>
            </span>
            <span class="badge" data-unread-badge style="display:none;"></span>
        </div>
        <div class="conversation-preview">
            <p data-last-message></p>
            <span class="time" data-last-message-time=""></span>
        </div>
    `;

    const noConversations = document.getElementById('noConversations');
    if (noConversations) {
        noConversations.remove();
    }

    conversationList.prepend(element);
    return element;
}

function updateConversationPreview(item, message) {
    const preview = item.querySelector('[data-last-message]');
    const time = item.querySelector('[data-last-message-time]');
    if (preview) {
        const text = String(message.message_text || '');
        preview.textContent = text.length > 50 ? `${text.slice(0, 50)}...` : text;
    }
    if (time) {
        time.dataset.lastMessageTime = message.created_at || '';
        time.textContent = formatConversationTime(message.created_at);
    }
}

function updateConversationList(message) {
    const partnerId = getConversationPartnerId(message);
    if (!partnerId) {
        return;
    }

    const conversationList = getConversationList();
    if (!conversationList) {
        return;
    }

    let item = conversationList.querySelector(`.conversation-item[data-user-id="${String(partnerId)}"]`);
    if (!item) {
        item = buildConversationItem(message, partnerId);
    }

    if (!item) {
        return;
    }

    updateConversationPreview(item, message);

    if (item.parentElement) {
        item.parentElement.prepend(item);
    }

    if (Number(message.recipient_id) === messageState.currentUserId && partnerId !== messageState.selectedUserId) {
        incrementUnreadBadge(partnerId);
    } else {
        hideUnreadBadge(partnerId);
    }
}

function handleIncomingMessage(message) {
    updateConversationList(message);

    const partnerId = getConversationPartnerId(message);
    if (partnerId === messageState.selectedUserId) {
        appendMessage(message);
        if (Number(message.recipient_id) === messageState.currentUserId) {
            markConversationRead(true);
        }
    }
}

function sendMessageFallback(messageText) {
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('recipient_id', String(messageState.selectedUserId));
    formData.append('message_text', messageText);
    if (typeof appendCsrfToken === 'function') {
        appendCsrfToken(formData);
    }

    fetch('../includes/messages.inc.php', {
        method: 'POST',
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.data) {
                alert('Error sending message: ' + (data.message || 'Unknown error'));
                return;
            }

            handleIncomingMessage(data.data);
        })
        .catch(() => {
            alert('Network error. Please try again.');
        });
}

function sendMessage(event) {
    if (event) {
        event.preventDefault();
    }

    if (!messageState.selectedUserId) {
        return;
    }

    const messageInput = getMessageInput();
    if (!messageInput) {
        return;
    }

    const messageText = messageInput.value.trim();
    if (!messageText) {
        return;
    }

    messageInput.value = '';

    if (messageState.hasSentTypingStart) {
        sendTypingEvent('typing_stop');
        messageState.hasSentTypingStart = false;
    }

    const sentBySocket = sendSocketPayload({
        type: 'send_message',
        recipient_id: messageState.selectedUserId,
        message_text: messageText,
    });

    if (!sentBySocket) {
        sendMessageFallback(messageText);
    }
}

function deleteMessage(messageId) {
    if (!confirm('Delete this message?')) {
        return;
    }

    const currentElement = document.querySelector(`.message-item[data-message-id="${String(messageId)}"]`);
    if (currentElement && currentElement.dataset.isDeleted === '1') {
        return;
    }

    const deletedBySocket = sendSocketPayload({
        type: 'delete_message',
        message_id: Number(messageId),
    });

    if (deletedBySocket) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('message_id', String(messageId));
    if (typeof appendCsrfToken === 'function') {
        appendCsrfToken(formData);
    }

    fetch('../includes/messages.inc.php', {
        method: 'POST',
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error deleting message');
                return;
            }

            markMessageDeleted(messageId, 'Message deleted');
        })
        .catch(() => {
            alert('Network error. Please try again.');
        });
}

function initializeTypingPublisher() {
    const messageInput = getMessageInput();
    if (!messageInput) {
        return;
    }

    messageInput.addEventListener('input', () => {
        if (!messageState.selectedUserId) {
            return;
        }

        const hasText = messageInput.value.trim() !== '';
        if (!hasText) {
            if (messageState.hasSentTypingStart) {
                sendTypingEvent('typing_stop');
                messageState.hasSentTypingStart = false;
            }
            clearTimeout(messageState.typingStopTimer);
            return;
        }

        if (!messageState.hasSentTypingStart) {
            sendTypingEvent('typing_start');
            messageState.hasSentTypingStart = true;
        }

        scheduleTypingStop();
    });

    messageInput.addEventListener('blur', () => {
        if (!messageState.hasSentTypingStart) {
            return;
        }

        sendTypingEvent('typing_stop');
        messageState.hasSentTypingStart = false;
        clearTimeout(messageState.typingStopTimer);
    });
}

function initializeConversationSearch() {
    const searchInput = document.getElementById('conversationSearch');
    const clearButton = document.getElementById('clearConversationSearch');

    if (!searchInput || !clearButton) {
        return;
    }

    searchInput.addEventListener('input', () => {
        applyConversationSearch(searchInput.value);
    });

    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        applyConversationSearch('');
        searchInput.focus();
    });
}

function initializeMobileComposerFocus() {
    const messageInput = getMessageInput();
    if (!messageInput) {
        return;
    }

    const bringComposerIntoView = () => {
        window.setTimeout(() => {
            scrollMessagesToBottom();
            messageInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 120);
    };

    messageInput.addEventListener('focus', bringComposerIntoView);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            if (document.activeElement === messageInput) {
                bringComposerIntoView();
            }
        });
    }
}

window.addEventListener('click', event => {
    const searchContainer = document.querySelector('.search-container');
    const searchResults = document.getElementById('searchResults');

    if (searchContainer && searchResults && !searchContainer.contains(event.target)) {
        searchResults.classList.remove('show');
    }
});

window.addEventListener('beforeunload', () => {
    messageState.intentionallyClosed = true;

    if (messageState.hasSentTypingStart) {
        sendTypingEvent('typing_stop');
        messageState.hasSentTypingStart = false;
    }

    if (messageState.socket) {
        messageState.socket.close();
    }

    if (messageState.presenceRefreshTimer) {
        clearInterval(messageState.presenceRefreshTimer);
        messageState.presenceRefreshTimer = null;
    }
});

window.addEventListener('load', () => {
    scrollMessagesToBottom();
    connectWebSocket();
    initializeTypingPublisher();
    initializeConversationSearch();
    initializeMobileComposerFocus();
    refreshConversationPresenceLabels();
    updateActiveHeaderPresence(messageState.selectedUserOnline, messageState.selectedUserLastSeen);

    if (!messageState.presenceRefreshTimer) {
        messageState.presenceRefreshTimer = window.setInterval(refreshConversationPresenceLabels, 30000);
    }

    const messageInput = getMessageInput();
    if (messageInput) {
        messageInput.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });
    }
});
