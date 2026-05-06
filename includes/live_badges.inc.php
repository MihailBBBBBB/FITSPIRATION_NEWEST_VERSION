<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'unread_notifications' => 0,
        'unread_messages' => 0,
    ]);
    exit();
}

require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/notifications.inc.php';
require_once __DIR__ . '/messages_repository.inc.php';

$userId = (int) $_SESSION['user_id'];

try {
    ensureNotificationsTable($pdo);

    echo json_encode([
        'success' => true,
        'unread_notifications' => getUnreadNotificationsCount($pdo, $userId),
        'unread_messages' => getUnreadMessagesCount($pdo, $userId),
    ]);
} catch (Throwable $error) {
    echo json_encode([
        'success' => false,
        'unread_notifications' => 0,
        'unread_messages' => 0,
    ]);
}
