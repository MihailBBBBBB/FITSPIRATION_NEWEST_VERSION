<?php
include_once '../includes/notifications.inc.php';

function canInteractWithUser(PDO $pdo, $user_id) {
    if (!$user_id) {
        return false;
    }

    $query = "SELECT id FROM registration WHERE id = ? AND banned = 0 LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function getFollowersCount($pdo, $user_id) {
    $query = "SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['cnt'] : 0;
}

function getFollowingCount($pdo, $user_id) {
    $query = "SELECT COUNT(*) AS cnt FROM follows WHERE follower_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['cnt'] : 0;
}

function isFollowing($pdo, $follower_id, $following_id) {
    if (!$follower_id || !$following_id || $follower_id == $following_id) {
        return false;
    }
    $query = "SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$follower_id, $following_id]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function followUser($pdo, $follower_id, $following_id) {
    if (!$follower_id || !$following_id || $follower_id == $following_id) {
        return false;
    }
    if (!canInteractWithUser($pdo, $follower_id) || !canInteractWithUser($pdo, $following_id)) {
        return false;
    }
    if (isFollowing($pdo, $follower_id, $following_id)) {
        return true;
    }

    $query = "INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)";
    $stmt = $pdo->prepare($query);
    $ok = $stmt->execute([$follower_id, $following_id]);

    if ($ok) {
        addNotification($pdo, $following_id, $follower_id, 'follow', null);
    }

    return $ok;
}

function unfollowUser($pdo, $follower_id, $following_id) {
    if (!$follower_id || !$following_id || $follower_id == $following_id) {
        return false;
    }
    if (!canInteractWithUser($pdo, $follower_id) || !canInteractWithUser($pdo, $following_id)) {
        return false;
    }
    $query = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$follower_id, $following_id]);
}
