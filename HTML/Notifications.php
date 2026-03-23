<?php
session_start();
include_once '../JS/headerFooter.php';
include_once '../includes/NotificationsPage.inc.php';

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
    <link rel="stylesheet" href="../CSS/Main.css"/>
    <link rel="stylesheet" href="../CSS/Notifications.css"/>
    <script src="../JS/translator.js"></script>
</head>
<body>
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
                            $actorImg = !empty($notification['actor_img'])
                                ? '../images/' . htmlspecialchars($notification['actor_img'])
                                : '../images/no_image.jpg';
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
                            }

                            $pinId = !empty($notification['pin_id']) ? (int)$notification['pin_id'] : null;
                            $viewPinUrl = null;
                            if ($pinId && in_array($type, ['like', 'comment'])) {
                                $viewPinUrl = 'Profile.php?user_id=' . (int)$_SESSION['user_id'] . '&pin_id=' . $pinId . '#pinModal';
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
                                        <a href="<?php echo $viewPinUrl; ?>" class="notification-view-pin">View pin</a>
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
