<?php

function createNotificationsTable(PDO $pdo): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            actor_user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            pin_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user_read_created (user_id, is_read, created_at),
            INDEX idx_notifications_actor (actor_user_id),
            INDEX idx_notifications_pin (pin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
}

function isBrokenNotificationsTableError(Throwable $error): bool {
    $message = strtolower((string) $error->getMessage());
    $errorInfo = $error instanceof PDOException ? ($error->errorInfo ?? []) : [];
    $sqlState = (string) ($errorInfo[0] ?? $error->getCode() ?? '');
    $driverCode = (string) ($errorInfo[1] ?? '');

    if ($sqlState === '42S02' || $driverCode === '1932') {
        return true;
    }

    return str_contains($message, 'notifications')
        && (str_contains($message, "doesn't exist in engine") || str_contains($message, 'base table or view not found'));
}

function ensureNotificationsTable(PDO $pdo) {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    try {
        createNotificationsTable($pdo);
        $pdo->query('SELECT 1 FROM notifications LIMIT 1');
        $initialized = true;
        return;
    } catch (Throwable $error) {
        if (!isBrokenNotificationsTableError($error)) {
            throw $error;
        }

        error_log('Notifications table appears broken, recreating it: ' . $error->getMessage());
    }

    $pdo->exec('DROP TABLE IF EXISTS notifications');
    createNotificationsTable($pdo);
    $pdo->query('SELECT 1 FROM notifications LIMIT 1');
    $initialized = true;
}

function getPinOwnerId(PDO $pdo, $pin_id) {
    $query = "
        SELECT COALESCE(p.user_id, c.user_id) AS owner_id
        FROM pins p
        LEFT JOIN collections c ON p.collection_id = c.collection_id
        WHERE p.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$pin_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['owner_id'] : null;
}

function addNotification(PDO $pdo, $recipient_user_id, $actor_user_id, $type, $pin_id = null) {
    if (!$recipient_user_id || !$actor_user_id || $recipient_user_id == $actor_user_id) {
        return false;
    }

    ensureNotificationsTable($pdo);

    if ($type === 'follow') {
        $deleteQuery = "DELETE FROM notifications WHERE user_id = ? AND actor_user_id = ? AND type = 'follow'";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$recipient_user_id, $actor_user_id]);
    }

    if ($type === 'like' && $pin_id) {
        $deleteQuery = "DELETE FROM notifications WHERE user_id = ? AND actor_user_id = ? AND type = 'like' AND pin_id = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$recipient_user_id, $actor_user_id, $pin_id]);
    }

    $query = "
        INSERT INTO notifications (user_id, actor_user_id, type, pin_id, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ";

    $stmt = $pdo->prepare($query);
    return $stmt->execute([$recipient_user_id, $actor_user_id, $type, $pin_id]);
}

function removeNotification(PDO $pdo, $recipient_user_id, $actor_user_id, $type, $pin_id = null) {
    if (!$recipient_user_id || !$actor_user_id) {
        return false;
    }

    ensureNotificationsTable($pdo);

    if ($pin_id === null) {
        $query = "DELETE FROM notifications WHERE user_id = ? AND actor_user_id = ? AND type = ?";
        $stmt = $pdo->prepare($query);
        return $stmt->execute([$recipient_user_id, $actor_user_id, $type]);
    }

    $query = "DELETE FROM notifications WHERE user_id = ? AND actor_user_id = ? AND type = ? AND pin_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$recipient_user_id, $actor_user_id, $type, $pin_id]);
}

function getUnreadNotificationsCount(PDO $pdo, $user_id) {
    if (!$user_id) {
        return 0;
    }

    ensureNotificationsTable($pdo);

    $query = "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['cnt'] : 0;
}

function markNotificationAsRead(PDO $pdo, $notification_id, $user_id) {
    ensureNotificationsTable($pdo);

    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$notification_id, $user_id]);
}

function markAllNotificationsAsRead(PDO $pdo, $user_id) {
    ensureNotificationsTable($pdo);

    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id]);
}

function getNotificationsForUser(PDO $pdo, $user_id, $limit = 50) {
    if (!$user_id) {
        return [];
    }

    ensureNotificationsTable($pdo);

    $limit = (int)$limit;
    if ($limit < 1) {
        $limit = 50;
    }

    $query = "
        SELECT n.id, n.type, n.pin_id, n.is_read,
               UNIX_TIMESTAMP(n.created_at) AS created_ts,
               n.actor_user_id,
               r.username AS actor_username,
               r.img AS actor_img
        FROM notifications n
        LEFT JOIN registration r ON n.actor_user_id = r.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
