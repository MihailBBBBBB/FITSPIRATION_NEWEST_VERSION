<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/csrf.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../HTML/Messages.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

requireValidCsrfToken(true);

require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/messages_repository.inc.php';
require_once __DIR__ . '/notifications.inc.php';

ensureNotificationsTable($pdo);

$senderId = (int) $_SESSION['user_id'];
$action = trim((string) $_POST['action']);

try {
    if ($action === 'send') {
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $recipientUsername = trim((string) ($_POST['recipient_username'] ?? ''));
        $messageText = trim((string) ($_POST['message_text'] ?? ''));

        if ($messageText === '') {
            echo json_encode(['success' => false, 'message' => 'Message text is required']);
            exit();
        }

        if (strlen($messageText) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Message too long (max 5000 characters)']);
            exit();
        }

        if ($recipientId <= 0 && $recipientUsername !== '') {
            $recipientId = findUserIdByUsername($pdo, $recipientUsername) ?? 0;
        }

        if ($recipientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Recipient not found']);
            exit();
        }

        if ($recipientId === $senderId) {
            echo json_encode(['success' => false, 'message' => 'You cannot message yourself']);
            exit();
        }

        $message = sendMessageRecord($pdo, $senderId, $recipientId, $messageText);
        addNotification($pdo, $recipientId, $senderId, 'message', null);

        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ]);
        exit();
    }

    if ($action === 'mark_read') {
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $updateQuery = 'UPDATE messages SET is_read = TRUE WHERE message_id = ? AND recipient_id = ?';
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([$messageId, $senderId]);

        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'mark_conversation_read') {
        $otherUserId = (int) ($_POST['other_user_id'] ?? 0);
        if ($otherUserId > 0) {
            markConversationAsRead($pdo, $senderId, $otherUserId);
            $clearNotifications = $pdo->prepare(
                'UPDATE notifications
                 SET is_read = TRUE
                 WHERE user_id = ?
                   AND actor_user_id = ?
                   AND type = ?
                   AND is_read = FALSE'
            );
            $clearNotifications->execute([$senderId, $otherUserId, 'message']);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete') {
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $result = deleteMessageForUser($pdo, $messageId, $senderId);

        echo json_encode([
            'success' => true,
            'message' => 'Message deleted',
            'data' => $result,
        ]);
        exit();
    }

    if ($action === 'delete_conversation') {
        $otherUserId = (int) ($_POST['other_user_id'] ?? 0);
        $scope = trim((string) ($_POST['scope'] ?? 'self'));
        $result = $scope === 'everyone'
            ? deleteConversationForEveryone($pdo, $senderId, $otherUserId)
            : clearConversationForUser($pdo, $senderId, $otherUserId);

        echo json_encode([
            'success' => true,
            'message' => 'Chat deleted',
            'data' => $result,
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported action']);
    exit();
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
