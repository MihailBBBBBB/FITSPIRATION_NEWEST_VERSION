<?php
include_once '../includes/dbh.inc.php';
include_once '../includes/notifications.inc.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../HTML/Login.php?error=notloggedin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        markAllNotificationsAsRead($pdo, $user_id);
        header('Location: Notifications.php');
        exit();
    }

    if (isset($_POST['mark_read'])) {
        $notification_id = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);
        if ($notification_id) {
            markNotificationAsRead($pdo, $notification_id, $user_id);
        }
        header('Location: Notifications.php');
        exit();
    }
}

$notifications = getNotificationsForUser($pdo, $user_id, 100);
$unread_count = getUnreadNotificationsCount($pdo, $user_id);
