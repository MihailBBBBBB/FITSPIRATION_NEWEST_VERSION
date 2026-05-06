<?php

require_once __DIR__ . '/notifications.inc.php';

function pinExists(PDO $pdo, int $pinId): bool {
    $query = 'SELECT 1 FROM pins WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$pinId]);
    return (bool) $stmt->fetchColumn();
}

function pinExistsInCollection(PDO $pdo, int $pinId, int $collectionId): bool {
    $query = 'SELECT 1 FROM pins WHERE id = ? AND collection_id = ? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$pinId, $collectionId]);
    return (bool) $stmt->fetchColumn();
}

function getPinLikeSummary(PDO $pdo, int $pinId, int $userId): array {
    $query = '
        SELECT
            (SELECT COUNT(*) FROM likes WHERE pin_id = :pin_id) AS like_count,
            EXISTS(SELECT 1 FROM likes WHERE pin_id = :pin_id AND user_id = :user_id) AS user_liked
    ';
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'pin_id' => $pinId,
        'user_id' => $userId,
    ]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'pin_id' => $pinId,
        'like_count' => (int) ($summary['like_count'] ?? 0),
        'user_liked' => (bool) ($summary['user_liked'] ?? false),
    ];
}

function togglePinLike(PDO $pdo, int $userId, int $pinId): array {
    $query = 'SELECT 1 FROM likes WHERE user_id = ? AND pin_id = ? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $pinId]);
    $alreadyLiked = (bool) $stmt->fetchColumn();

    if ($alreadyLiked) {
        $deleteQuery = 'DELETE FROM likes WHERE user_id = ? AND pin_id = ?';
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$userId, $pinId]);

        $pinOwnerId = getPinOwnerId($pdo, $pinId);
        if ($pinOwnerId) {
            removeNotification($pdo, $pinOwnerId, $userId, 'like', $pinId);
        }
    } else {
        $insertQuery = 'INSERT INTO likes (user_id, pin_id, date) VALUES (?, ?, NOW())';
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([$userId, $pinId]);

        $pinOwnerId = getPinOwnerId($pdo, $pinId);
        if ($pinOwnerId) {
            addNotification($pdo, $pinOwnerId, $userId, 'like', $pinId);
        }
    }

    return getPinLikeSummary($pdo, $pinId, $userId);
}