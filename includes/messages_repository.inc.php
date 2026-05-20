<?php

function ensureMessageConversationClearsTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS message_conversation_clears (
            user_id INT NOT NULL,
            other_user_id INT NOT NULL,
            cleared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, other_user_id),
            CONSTRAINT fk_message_clears_user FOREIGN KEY (user_id) REFERENCES registration(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_clears_other_user FOREIGN KEY (other_user_id) REFERENCES registration(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function getConversationClearCutoff(PDO $pdo, int $userId, int $otherUserId): ?string {
    ensureMessageConversationClearsTable($pdo);

    if ($userId <= 0 || $otherUserId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT cleared_at
         FROM message_conversation_clears
         WHERE user_id = ? AND other_user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId, $otherUserId]);

    $cutoff = $stmt->fetchColumn();
    return $cutoff === false ? null : (string) $cutoff;
}

function clearConversationForUser(PDO $pdo, int $userId, int $otherUserId): array {
    ensureMessageConversationClearsTable($pdo);

    if ($userId <= 0 || $otherUserId <= 0 || $userId === $otherUserId) {
        throw new RuntimeException('Invalid conversation selected.');
    }

    $existsStmt = $pdo->prepare('SELECT id FROM registration WHERE id = ? LIMIT 1');
    $existsStmt->execute([$otherUserId]);
    if (!$existsStmt->fetchColumn()) {
        throw new RuntimeException('Conversation user not found.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO message_conversation_clears (user_id, other_user_id, cleared_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE cleared_at = VALUES(cleared_at)'
    );
    $stmt->execute([$userId, $otherUserId]);

    return [
        'user_id' => $userId,
        'other_user_id' => $otherUserId,
        'cleared_at' => date('Y-m-d H:i:s'),
    ];
}

function getConversationSummaries(PDO $pdo, int $userId): array {
    ensureMessagePresenceTable($pdo);
    ensureMessageConversationClearsTable($pdo);

    if ($userId <= 0) {
        return [];
    }

    $query = '
        SELECT
            p.other_user_id,
            u.username,
            u.img,
            COALESCE(mp.is_online, 0) AS is_online,
            mp.last_seen,
            MAX(vm.created_at) AS last_message_time,
            (
                SELECT m2.message_text
                FROM messages m2
                WHERE (
                    (m2.sender_id = :user_id_a AND m2.recipient_id = p.other_user_id)
                    OR (m2.sender_id = p.other_user_id AND m2.recipient_id = :user_id_b)
                )
                  AND (cc.cleared_at IS NULL OR m2.created_at > cc.cleared_at)
                ORDER BY m2.created_at DESC, m2.message_id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT COUNT(*)
                FROM messages m3
                WHERE m3.recipient_id = :user_id_c
                  AND m3.sender_id = p.other_user_id
                  AND m3.is_read = FALSE
                  AND (cc.cleared_at IS NULL OR m3.created_at > cc.cleared_at)
            ) AS unread_count
        FROM (
            SELECT DISTINCT
                CASE
                    WHEN m.sender_id = :user_id_d THEN m.recipient_id
                    ELSE m.sender_id
                END AS other_user_id
            FROM messages m
            WHERE m.sender_id = :user_id_e OR m.recipient_id = :user_id_f
        ) p
        INNER JOIN registration u ON u.id = p.other_user_id
        LEFT JOIN message_presence mp ON mp.user_id = u.id
        LEFT JOIN message_conversation_clears cc
               ON cc.user_id = :user_id_g
              AND cc.other_user_id = p.other_user_id
        LEFT JOIN messages vm
               ON (
                    (
                        (vm.sender_id = :user_id_h AND vm.recipient_id = p.other_user_id)
                        OR (vm.sender_id = p.other_user_id AND vm.recipient_id = :user_id_i)
                    )
                    AND (cc.cleared_at IS NULL OR vm.created_at > cc.cleared_at)
               )
        GROUP BY p.other_user_id, u.username, u.img, mp.is_online, mp.last_seen, cc.cleared_at
        HAVING MAX(vm.created_at) IS NOT NULL
        ORDER BY MAX(vm.created_at) DESC
    ';

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'user_id_a' => $userId,
        'user_id_b' => $userId,
        'user_id_c' => $userId,
        'user_id_d' => $userId,
        'user_id_e' => $userId,
        'user_id_f' => $userId,
        'user_id_g' => $userId,
        'user_id_h' => $userId,
        'user_id_i' => $userId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function findUserIdByUsername(PDO $pdo, string $username): ?int {
    $query = 'SELECT id FROM registration WHERE username = ? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([trim($username)]);

    $userId = $stmt->fetchColumn();
    return $userId === false ? null : (int) $userId;
}

function findUsernameById(PDO $pdo, int $userId): ?string {
    $query = 'SELECT username FROM registration WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);

    $username = $stmt->fetchColumn();
    return $username === false ? null : (string) $username;
}

function normalizeMessageRecord(array $message): array {
    $isDeleted = (bool) (($message['deleted_by_sender'] ?? false) || ($message['deleted_by_recipient'] ?? false));

    return [
        'message_id' => (int) ($message['message_id'] ?? 0),
        'sender_id' => (int) ($message['sender_id'] ?? 0),
        'recipient_id' => (int) ($message['recipient_id'] ?? 0),
        'message_text' => $isDeleted ? 'Message deleted' : (string) ($message['message_text'] ?? ''),
        'created_at' => (string) ($message['created_at'] ?? ''),
        'is_read' => $isDeleted ? true : (bool) ($message['is_read'] ?? false),
        'is_deleted' => $isDeleted,
        'deleted_by_sender' => (bool) ($message['deleted_by_sender'] ?? false),
        'deleted_by_recipient' => (bool) ($message['deleted_by_recipient'] ?? false),
        'sender_username' => (string) ($message['sender_username'] ?? ''),
        'sender_img' => (string) ($message['sender_img'] ?? ''),
        'recipient_username' => (string) ($message['recipient_username'] ?? ''),
        'recipient_img' => (string) ($message['recipient_img'] ?? ''),
    ];
}

function fetchMessageById(PDO $pdo, int $messageId): ?array {
    $query = '
        SELECT
            m.message_id,
            m.sender_id,
            m.recipient_id,
            m.message_text,
            m.created_at,
            m.is_read,
            m.deleted_by_sender,
            m.deleted_by_recipient,
            sender.username AS sender_username,
            sender.img AS sender_img,
            recipient.username AS recipient_username,
            recipient.img AS recipient_img
        FROM messages m
        INNER JOIN registration sender ON sender.id = m.sender_id
        INNER JOIN registration recipient ON recipient.id = m.recipient_id
        WHERE m.message_id = ?
        LIMIT 1
    ';

    $stmt = $pdo->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        return null;
    }

    return normalizeMessageRecord($message);
}

function getConversationMessages(PDO $pdo, int $currentUserId, int $otherUserId): array {
    $cutoff = getConversationClearCutoff($pdo, $currentUserId, $otherUserId);

    $query = '
        SELECT
            m.message_id,
            m.sender_id,
            m.recipient_id,
            m.message_text,
            m.created_at,
            m.is_read,
            m.deleted_by_sender,
            m.deleted_by_recipient,
            sender.username AS sender_username,
            sender.img AS sender_img,
            recipient.username AS recipient_username,
            recipient.img AS recipient_img
        FROM messages m
        INNER JOIN registration sender ON sender.id = m.sender_id
        INNER JOIN registration recipient ON recipient.id = m.recipient_id
                WHERE (
                                (m.sender_id = ? AND m.recipient_id = ?)
                                OR (m.sender_id = ? AND m.recipient_id = ?)
                            )
                    AND (? IS NULL OR m.created_at > ?)
        ORDER BY m.created_at ASC, m.message_id ASC
    ';

    $stmt = $pdo->prepare($query);
        $stmt->execute([$currentUserId, $otherUserId, $otherUserId, $currentUserId, $cutoff, $cutoff]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map('normalizeMessageRecord', $messages);
}

function markConversationAsReadAndGetIds(PDO $pdo, int $recipientId, int $senderId): array {
    $selectStmt = $pdo->prepare(
        'SELECT message_id
         FROM messages
            WHERE recipient_id = ? AND sender_id = ? AND is_read = FALSE AND deleted_by_sender = FALSE AND deleted_by_recipient = FALSE'
    );
    $selectStmt->execute([$recipientId, $senderId]);
    $messageIds = array_map('intval', $selectStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($messageIds)) {
        return [];
    }

    $updateQuery = 'UPDATE messages SET is_read = TRUE WHERE recipient_id = ? AND sender_id = ? AND is_read = FALSE AND deleted_by_sender = FALSE AND deleted_by_recipient = FALSE';
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([$recipientId, $senderId]);

    return $messageIds;
}

function markConversationAsRead(PDO $pdo, int $recipientId, int $senderId): int {
    return count(markConversationAsReadAndGetIds($pdo, $recipientId, $senderId));
}

function sendMessageRecord(PDO $pdo, int $senderId, int $recipientId, string $messageText): array {
    $insertQuery = 'INSERT INTO messages (sender_id, recipient_id, message_text) VALUES (?, ?, ?)';
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([$senderId, $recipientId, trim($messageText)]);

    $messageId = (int) $pdo->lastInsertId();
    $message = fetchMessageById($pdo, $messageId);

    if ($message === null) {
        throw new RuntimeException('Unable to load the inserted message.');
    }

    return $message;
}

function deleteMessageForUser(PDO $pdo, int $messageId, int $actorUserId): array {
    $checkQuery = '
        SELECT message_id, sender_id, recipient_id, deleted_by_sender, deleted_by_recipient
        FROM messages
        WHERE message_id = ?
        LIMIT 1
    ';
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$messageId]);
    $message = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        throw new RuntimeException('Message not found.');
    }

    if ((int) $message['sender_id'] !== $actorUserId && (int) $message['recipient_id'] !== $actorUserId) {
        throw new RuntimeException('Unauthorized message deletion.');
    }

    $softDeleteStmt = $pdo->prepare('UPDATE messages SET deleted_by_sender = TRUE, deleted_by_recipient = TRUE, is_read = TRUE WHERE message_id = ? LIMIT 1');
    $softDeleteStmt->execute([$messageId]);

    return [
        'message_id' => (int) $message['message_id'],
        'sender_id' => (int) $message['sender_id'],
        'recipient_id' => (int) $message['recipient_id'],
        'actor_user_id' => $actorUserId,
        'deleted_for_everyone' => true,
        'placeholder_text' => 'Message deleted',
    ];
}

function ensureMessagePresenceTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS message_presence (
            user_id INT NOT NULL PRIMARY KEY,
            is_online TINYINT(1) NOT NULL DEFAULT 0,
            last_seen DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_message_presence_user FOREIGN KEY (user_id) REFERENCES registration(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function setUserPresence(PDO $pdo, int $userId, bool $isOnline): void {
    ensureMessagePresenceTable($pdo);

    $query = '
        INSERT INTO message_presence (user_id, is_online, last_seen)
        VALUES (?, ?, CASE WHEN ? = 1 THEN NULL ELSE NOW() END)
        ON DUPLICATE KEY UPDATE
            is_online = VALUES(is_online),
            last_seen = CASE WHEN VALUES(is_online) = 1 THEN last_seen ELSE NOW() END
    ';

    $stmt = $pdo->prepare($query);
    $onlineInt = $isOnline ? 1 : 0;
    $stmt->execute([$userId, $onlineInt, $onlineInt]);
}

function getUserPresence(PDO $pdo, int $userId): array {
    ensureMessagePresenceTable($pdo);

    $stmt = $pdo->prepare('SELECT is_online, last_seen FROM message_presence WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'is_online' => false,
            'last_seen' => '',
        ];
    }

    return [
        'is_online' => (bool) ($row['is_online'] ?? false),
        'last_seen' => (string) ($row['last_seen'] ?? ''),
    ];
}

function getConversationPartnerIds(PDO $pdo, int $userId): array {
    return array_map(
        static function (array $row): int {
            return (int) ($row['other_user_id'] ?? 0);
        },
        getConversationSummaries($pdo, $userId)
    );
}

function getUnreadMessagesCount(PDO $pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM messages
         WHERE recipient_id = ?
           AND is_read = FALSE
           AND deleted_by_sender = FALSE
           AND deleted_by_recipient = FALSE'
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}