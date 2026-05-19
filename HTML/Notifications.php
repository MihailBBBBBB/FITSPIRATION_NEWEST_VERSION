<?php
session_start();
include_once '../includes/NotificationsPage.inc.php';
include_once '../JS/headerFooter.php';
require_once '../includes/image_storage.inc.php';

function formatRelativeTime($ts) {
    $diff = time() - (int)$ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' h ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';
    return date('M j, Y', (int)$ts);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="../CSS/Main.css?v=14"/>
    <link rel="stylesheet" href="../CSS/Notifications.css?v=5"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content notifications-page">
            <div class="notifications-header">
                <h1>My Notifications</h1>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" action="" class="mark-all-form">
                        <button type="submit" name="mark_all_read" class="mark-all-btn">Mark all as read</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="notifications-empty">No notifications yet.</div>
            <?php else: ?>
                <ul class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                            $actorName = htmlspecialchars($notification['actor_username'] ?? 'Someone');
                            $actorImg = buildFitspirationAvatarUrl($notification['actor_img'] ?? '', (string) ($notification['actor_username'] ?? 'Someone'));
                            $type = $notification['type'] ?? '';
                            $message = 'did something.';
                            $actorId = (int)($notification['actor_user_id'] ?? 0);
                            $profileUrl = $actorId ? 'Profile.php?user_id=' . $actorId : '#';

                            if ($type === 'follow') {
                                $message = 'started following you.';
                            } elseif ($type === 'like') {
                                $message = 'liked your pin.';
                            } elseif ($type === 'comment') {
                                $message = 'commented on your pin.';
                            } elseif ($type === 'collab_invite') {
                                $message = 'invited you to collaborate on a collection.';
                            } elseif ($type === 'collab_role_change') {
                                $message = 'updated your role in a collection.';
                            } elseif ($type === 'message') {
                                $message = 'sent you a message.';
                            }

                            $pinId = !empty($notification['pin_id']) ? (int)$notification['pin_id'] : null;
                            $viewPinUrl = null;
                            if ($pinId && in_array($type, ['like', 'comment'])) {
                                $viewPinUrl = 'Profile.php?user_id=' . (int)$_SESSION['user_id'] . '&pin_id=' . $pinId . '#pinModal';
                            } elseif ($pinId && in_array($type, ['collab_invite', 'collab_role_change'], true)) {
                                $viewPinUrl = 'collectionDetails.php?collection_id=' . $pinId;
                            } elseif ($type === 'message' && $actorId > 0) {
                                $viewPinUrl = 'Messages.php?user_id=' . $actorId;
                            }

                            $relTime = formatRelativeTime($notification['created_ts']);
                        ?>
                        <li class="notification-item <?php echo (int)$notification['is_read'] === 0 ? 'unread' : 'read'; ?>">
                            <a href="<?php echo $profileUrl; ?>" class="notification-avatar-link">
                                <img src="<?php echo $actorImg; ?>" alt="Actor" class="notification-avatar">
                            </a>

                            <div class="notification-content">
                                <p>
                                    <a href="<?php echo $profileUrl; ?>" class="notification-actor no-translate" data-user-content="true"><?php echo $actorName; ?></a>
                                    <span class="notification-text"><?php echo htmlspecialchars($message); ?></span>
                                    <?php if ($viewPinUrl): ?>
                                        <a href="<?php echo $viewPinUrl; ?>" class="notification-view-pin"><?php echo in_array($type, ['collab_invite', 'collab_role_change'], true) ? 'View collection' : ($type === 'message' ? 'Open chat' : 'View pin'); ?></a>
                                    <?php endif; ?>
                                </p>
                                <span class="notification-time"><?php echo htmlspecialchars($relTime); ?></span>
                            </div>

                            <?php if ((int)$notification['is_read'] === 0): ?>
                                <form method="POST" action="" class="mark-read-form">
                                    <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                    <button type="submit" name="mark_read" class="mark-read-btn">Mark as read</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
