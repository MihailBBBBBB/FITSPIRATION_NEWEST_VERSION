<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../includes/dbh.inc.php';
include_once '../includes/follow.inc.php';
include_once '../includes/notifications.inc.php';
include_once '../includes/reports.inc.php';
include_once '../includes/likes.inc.php';
include_once '../includes/csrf.inc.php';
include_once '../includes/outfits_schema.inc.php';
include_once '../includes/collection_collaboration.inc.php';
include_once '../includes/image_storage.inc.php';

ensureModerationTables($pdo);
ensureCollectionCollaborationTables($pdo);

$is_ajax_request = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
);

if ($is_ajax_request && ob_get_level() === 0) {
    ob_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken($is_ajax_request);
}

function sendAjaxJson(array $payload): void {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

$session_user_id = $_SESSION['user_id'] ?? null;
$view_user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT) : $session_user_id;
if (!$view_user_id) {
    header("Location: ../HTML/Login.php?error=notloggedin");
    exit();
}

$user_id = $session_user_id; // keep for current logged-in user actions
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; 
error_log('Received sort: ' . $sort);

$current_user_name = 'You';
$current_user_image = '../images/no_image.jpg';
if ($session_user_id) {
    try {
        $currentUserStmt = $pdo->prepare('SELECT username, img FROM registration WHERE id = ? LIMIT 1');
        $currentUserStmt->execute([$session_user_id]);
        $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
        if ($currentUser) {
            if (!empty($currentUser['username'])) {
                $current_user_name = $currentUser['username'];
            }
            if (!empty($currentUser['img'])) {
                $current_user_image = '../images/' . htmlspecialchars($currentUser['img']);
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching current user profile: ' . $e->getMessage());
    }
}

try {
    $query2 = "SELECT username, description, img FROM registration WHERE id = ?";
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([$view_user_id]);
    $users = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$users) {
        header("Location: ../HTML/Login.php?error=usernotfound");
        exit();
    }
    error_log("User data fetched for user_id {$view_user_id}: username={$users['username']}");
} catch (PDOException $e) {
    error_log('Error fetching user data: ' . $e->getMessage());
    header("Location: ../HTML/Login.php?error=dberror");
    exit();
}

$profileChallengeStats = getUserChallengeBadgeStats($pdo, (int) $view_user_id);

$followers_count = getFollowersCount($pdo, $view_user_id);
$following_count = getFollowingCount($pdo, $view_user_id);
$is_following = $session_user_id ? isFollowing($pdo, $session_user_id, $view_user_id) : false;

try {
    $query = "SELECT r.id, r.username, r.img FROM follows f JOIN registration r ON f.follower_id = r.id WHERE f.following_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$view_user_id]);
    $follower_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching followers users: ' . $e->getMessage());
    $follower_users = [];
}

try {
    $query = "SELECT r.id, r.username, r.img FROM follows f JOIN registration r ON f.following_id = r.id WHERE f.follower_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$view_user_id]);
    $following_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching following users: ' . $e->getMessage());
    $following_users = [];
}

if ($is_ajax_request && $_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['action'] ?? '') === 'get_pin_modal') {
    $pin_id = filter_var($_GET['pin_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    if ((int)$pin_id <= 0) {
        sendAjaxJson(['success' => false, 'message' => 'Invalid pin id.']);
    }

    try {
        $pinQuery = "
            SELECT p.id, p.img, p.title,
                   p.user_id as creator_id,
                   COALESCE(r.username, 'Unknown') as creator_name,
                   COALESCE(r.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
                   EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked
            FROM pins p
            LEFT JOIN registration r ON p.user_id = r.id
            WHERE p.id = ?
            LIMIT 1
        ";
        $pinStmt = $pdo->prepare($pinQuery);
        $pinStmt->execute([(int)$session_user_id, (int)$pin_id]);
        $pinData = $pinStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pinData) {
            sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
        }

        $commentQuery = "
            SELECT c.id, c.comment, c.created_at, c.user_id, r.username, r.img as user_img
            FROM comments c
            JOIN registration r ON c.user_id = r.id
            WHERE c.pin_id = ?
            ORDER BY c.created_at DESC
        ";
        $commentStmt = $pdo->prepare($commentQuery);
        $commentStmt->execute([(int)$pin_id]);
        $commentsData = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

        $creatorId = (int)($pinData['creator_id'] ?? 0);
        $sessionId = (int)($session_user_id ?? 0);
        $commentsPayload = [];
        foreach ($commentsData as $commentRow) {
            $commentAuthorId = (int)($commentRow['user_id'] ?? 0);
            $commentsPayload[] = [
                'id' => (int)($commentRow['id'] ?? 0),
                'comment' => (string)($commentRow['comment'] ?? ''),
                'user_id' => $commentAuthorId,
                'username' => (string)($commentRow['username'] ?? 'Unknown'),
                'user_img' => !empty($commentRow['user_img']) ? '../images/' . (string)$commentRow['user_img'] : '../images/no_image.jpg',
                'can_delete' => ($commentAuthorId === $sessionId) || ($creatorId === $sessionId),
            ];
        }

        sendAjaxJson([
            'success' => true,
            'pin' => [
                'id' => (int)$pinData['id'],
                'image' => !empty($pinData['img']) ? '../images/' . (string)$pinData['img'] : '../images/no_image.jpg',
                'title' => (string)($pinData['title'] ?? 'Pin'),
                'creator_id' => $creatorId,
                'creator_name' => (string)($pinData['creator_name'] ?? 'Unknown'),
                'creator_img' => !empty($pinData['creator_img']) ? '../images/' . (string)$pinData['creator_img'] : '../images/no_image.jpg',
                'like_count' => (int)($pinData['like_count'] ?? 0),
                'user_liked' => !empty($pinData['user_liked']),
            ],
            'comments' => $commentsPayload,
        ]);
    } catch (PDOException $e) {
        error_log('Error loading profile pin modal data: ' . $e->getMessage());
        sendAjaxJson(['success' => false, 'message' => 'Database error while loading pin modal.']);
    }
}


// Обработка удаления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to delete comment: user_id={$user_id}, comment_id={$comment_id}, pin_id={$pin_id}");

    try {
        // Проверяем, существует ли комментарий и имеет ли пользователь право на удаление
        $query = "
            SELECT c.id, c.user_id, p.user_id as pin_owner_id
            FROM comments c
            JOIN pins p ON c.pin_id = p.id
            WHERE c.id = ? AND c.pin_id = ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id, $pin_id]);
        $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment_data) {
            error_log("Comment or pin not found: comment_id={$comment_id}, pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Comment not found.']);
            }
            $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort) . "#pinModal";
            header("Location: $redirect_url");
            exit();
        }

        // Проверяем, является ли пользователь автором комментария или владельцем пина
        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'You are not allowed to delete this comment.']);
            }
            $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort) . "#pinModal";
            header("Location: $redirect_url");
            exit();
        }

        // Удаляем комментарий
        $query = "DELETE FROM comments WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id]);
        error_log("Comment deleted: comment_id={$comment_id}, user_id={$user_id}");

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'comment_id' => (int) $comment_id,
                'pin_id' => (int) $pin_id,
            ]);
        }

        // Перенаправляем с сохранением состояния модала
        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort) . "#pinModal";
        header("Location: $redirect_url");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while deleting comment.']);
        }
        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal";
        header("Location: $redirect_url");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }

    $reportTargetType = trim($_POST['report_target_type'] ?? '');
    $reportTargetId = filter_var($_POST['report_target_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $reportReason = trim($_POST['report_reason'] ?? '');
    $reportCategory = trim($_POST['report_category'] ?? 'other');

    if (!in_array($reportTargetType, ['pin', 'comment'], true)) {
        $result = ['ok' => false, 'message' => 'Invalid report target.'];
    } else {
        $result = createContentReport($pdo, (int)$session_user_id, $reportTargetType, (int)$reportTargetId, $reportReason, $reportCategory);
    }

    $status = $result['ok'] ? 'ok' : 'error';
    $redirect_url = "Profile.php?user_id=" . urlencode((string)$view_user_id) . "&pin_id=" . urlencode((string)$pin_id) . "&sort=" . urlencode($sort) . "&report_status=" . urlencode($status) . "&report_msg=" . urlencode($result['message']);
    header("Location: $redirect_url#pinModal");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_action'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }

    $target_user_id = filter_var($_POST['target_user_id'], FILTER_SANITIZE_NUMBER_INT);
    if (!$target_user_id || $target_user_id == $session_user_id) {
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&sort=" . urlencode($sort));
        exit();
    }

    if ($_POST['follow_action'] === 'follow') {
        followUser($pdo, $session_user_id, $target_user_id);
    } elseif ($_POST['follow_action'] === 'unfollow') {
        unfollowUser($pdo, $session_user_id, $target_user_id);
    }

    header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&sort=" . urlencode($sort));
    exit();
}

// Fallback path when form submits file but submit button name is not posted.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_avatar']) && isset($_FILES['avatar'])) {
    $_POST['update_avatar'] = '1';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }
    if ((int)$view_user_id !== (int)$session_user_id) {
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=unauthorized&sort=" . urlencode($sort));
        exit();
    }
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $result = saveFitspirationUploadedImage($_FILES['avatar'], 'avatar_');
        if (!$result['success']) {
            error_log('Avatar upload error: ' . $result['error']);
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=" . urlencode($result['error']) . "&sort=" . urlencode($sort));
            exit();
        }
        $new_avatar = $result['path'];

        try {
            // Delete old avatar if it exists and is not the shared placeholder.
            if (!empty($users['img']) && $users['img'] !== 'no_image.jpg') {
                $old_avatar_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $users['img'];
                if (file_exists($old_avatar_path)) {
                    unlink($old_avatar_path);
                }
            }

            // Update avatar in database
            $query = "UPDATE registration SET img = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$new_avatar, $session_user_id]);
            error_log("Avatar updated for user_id {$session_user_id}: img={$new_avatar}");
            header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&sort=" . urlencode($sort));
            exit();
        } catch (PDOException $e) {
            error_log('Error updating avatar: ' . $e->getMessage());
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=dberror&sort=" . urlencode($sort));
            exit();
        }
    } else {
        error_log('No file uploaded or upload error');
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=nofile&sort=" . urlencode($sort));
        exit();
    }
}

$orderBy = 'p.id DESC'; // По умолчанию
switch ($sort) {
    case 'likes_asc':
        $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) ASC';
        break;
    case 'likes_desc':
        $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) DESC';
        break;
    case 'date_asc':
        $orderBy = 'p.id ASC';
        break;
    case 'date_desc':
        $orderBy = 'p.id DESC';
        break;
}

try {
    $query = "
        SELECT p.id, p.img, p.title,
               p.user_id as creator_id,
               COALESCE(r.username, 'Unknown') as creator_name,
               COALESCE(r.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.user_id = :session_user_id AND l.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p 
        INNER JOIN collections c ON p.collection_id = c.collection_id 
        LEFT JOIN registration r ON p.user_id = r.id
        WHERE c.user_id = :view_user_id
        ORDER BY $orderBy
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':session_user_id', $session_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':view_user_id', $view_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Pins fetched for user_id {$user_id}, sort {$sort}: " . count($pins));
} catch (PDOException $e) {
    error_log('Error fetching pins: ' . $e->getMessage());
    $pins = [];
}

try {
    $query1 = "
        SELECT c.collection_id,
               c.img,
               c.title,
               c.user_id,
               COUNT(DISTINCT p.id) AS pin_count,
               CASE
                   WHEN c.user_id = :view_user_id THEN 'owner'
                   ELSE COALESCE(cc_view.role, 'viewer')
               END AS access_role,
               COALESCE(owner_user.username, 'Unknown') AS owner_username,
               COALESCE(owner_user.img, '') AS owner_img
        FROM collections c
        LEFT JOIN pins p ON c.collection_id = p.collection_id
        LEFT JOIN collection_collaborators cc_view
               ON cc_view.collection_id = c.collection_id
              AND cc_view.user_id = :view_user_id
        LEFT JOIN registration owner_user ON owner_user.id = c.user_id
        WHERE c.user_id = :view_user_id
           OR cc_view.user_id = :view_user_id
        GROUP BY c.collection_id, c.img, c.title, c.user_id, cc_view.role, owner_user.username, owner_user.img
        ORDER BY c.collection_id DESC
    ";
    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute(['view_user_id' => (int) $view_user_id]);
    $collections = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $collectionIds = [];
    foreach ($collections as $collectionRow) {
        $collectionId = (int) ($collectionRow['collection_id'] ?? 0);
        if ($collectionId > 0) {
            $collectionIds[] = $collectionId;
        }
    }

    $collectionCollaboratorsMap = [];
    if (!empty($collectionIds)) {
        $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));
        $collabQuery = "
            SELECT cc.collection_id,
                   cc.role,
                   r.id AS user_id,
                   r.username,
                   r.img AS user_img
            FROM collection_collaborators cc
            INNER JOIN registration r ON r.id = cc.user_id
            WHERE cc.collection_id IN ($placeholders)
            ORDER BY cc.collection_id ASC,
                     FIELD(cc.role, 'editor', 'viewer'),
                     cc.created_at ASC,
                     cc.user_id ASC
        ";
        $collabStmt = $pdo->prepare($collabQuery);
        $collabStmt->execute($collectionIds);

        foreach ($collabStmt->fetchAll(PDO::FETCH_ASSOC) as $collabRow) {
            $mapCollectionId = (int) ($collabRow['collection_id'] ?? 0);
            if ($mapCollectionId <= 0) {
                continue;
            }

            if (!isset($collectionCollaboratorsMap[$mapCollectionId])) {
                $collectionCollaboratorsMap[$mapCollectionId] = [];
            }

            $collectionCollaboratorsMap[$mapCollectionId][] = [
                'user_id' => (int) ($collabRow['user_id'] ?? 0),
                'username' => (string) ($collabRow['username'] ?? 'Unknown'),
                'role' => normalizeCollectionRole((string) ($collabRow['role'] ?? 'viewer')),
                'user_img' => !empty($collabRow['user_img']) ? '../images/' . (string) $collabRow['user_img'] : '../images/no_image.jpg',
            ];
        }
    }

    foreach ($collections as &$collectionRow) {
        $currentCollectionId = (int) ($collectionRow['collection_id'] ?? 0);
        $ownerImage = !empty($collectionRow['owner_img']) ? '../images/' . (string) $collectionRow['owner_img'] : '../images/no_image.jpg';

        $collectionRow['owner'] = [
            'user_id' => (int) ($collectionRow['user_id'] ?? 0),
            'username' => (string) ($collectionRow['owner_username'] ?? 'Unknown'),
            'user_img' => $ownerImage,
        ];
        $collectionRow['collaborators'] = $collectionCollaboratorsMap[$currentCollectionId] ?? [];
    }
    unset($collectionRow);

    error_log("Collections fetched for user_id {$view_user_id}: " . count($collections));
} catch (PDOException $e) {
    error_log('Error fetching collections: ' . $e->getMessage());
    $collections = [];
}

try {
    $query = "
        SELECT p.id, p.img, p.title, l.date,
               p.user_id as creator_id,
               COALESCE(r.username, 'Unknown') as creator_name,
               COALESCE(r.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes l2 WHERE l2.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l2 WHERE l2.user_id = :session_user_id AND l2.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p 
        INNER JOIN likes l ON p.id = l.pin_id 
        LEFT JOIN registration r ON p.user_id = r.id
        WHERE l.user_id = :view_user_id 
        ORDER BY $orderBy
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':session_user_id', $session_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':view_user_id', $view_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $liked_pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Liked pins fetched for user_id {$user_id}, sort {$sort}: " . count($liked_pins));
} catch (PDOException $e) {
    error_log('Error fetching liked pins: ' . $e->getMessage());
    $liked_pins = [];
}

// Ensure outfits table exists and fetch outfits for this profile
try {
    ensureOutfitsTable($pdo);
    $stmt = $pdo->prepare("SELECT id, name, img, created_at, updated_at FROM outfits WHERE user_id = ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC");
    $stmt->execute([$view_user_id]);
    $outfits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching outfits: ' . $e->getMessage());
    $outfits = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }
    if ((int) $view_user_id !== (int) $session_user_id) {
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=unauthorized&sort=" . urlencode($sort));
        exit();
    }
    $new_username = trim((string) ($_POST['username'] ?? ''));
    $new_description = trim((string) ($_POST['description'] ?? ''));

    if ($new_username === '') {
        header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&error=emptyusername&sort=" . urlencode($sort));
        exit();
    }

    if (mb_strlen($new_username) > 50) {
        header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&error=usernametoolong&sort=" . urlencode($sort));
        exit();
    }

    if (mb_strlen($new_description) > 500) {
        $new_description = mb_substr($new_description, 0, 500);
    }

    try {
        $checkQuery = "SELECT id FROM registration WHERE username = ? AND id != ? LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$new_username, (int) $session_user_id]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&error=usernametaken&sort=" . urlencode($sort));
            exit();
        }

        $query = "UPDATE registration SET username = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$new_username, $new_description, (int) $session_user_id]);
        $_SESSION['username'] = $new_username;
        error_log("Profile updated for user_id {$session_user_id}: username={$new_username}");
        header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&sort=" . urlencode($sort) . "&status=profileupdated");
        exit();
    } catch (PDOException $e) {
        error_log('Error updating profile: ' . $e->getMessage());
        header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&error=dberror&sort=" . urlencode($sort));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to toggle like: user_id={$session_user_id}, pin_id={$pin_id}");

    try {
        if (!pinExists($pdo, (int) $pin_id)) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $likeData = togglePinLike($pdo, (int) $session_user_id, (int) $pin_id);

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'like' => $likeData,
            ]);
        }

        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error.']);
        }
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $comment = trim(strip_tags((string)($_POST['comment'] ?? '')));
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );

    if ($comment === '') {
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Comment cannot be empty.']);
        }
        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=emptycomment&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    }

    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        $new_comment_id = (int)$pdo->lastInsertId();
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        $pin_owner_id = getPinOwnerId($pdo, $pin_id);
        if ($pin_owner_id) {
            addNotification($pdo, $pin_owner_id, $user_id, 'comment', $pin_id);
        }

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'comment' => [
                    'id' => $new_comment_id,
                    'username' => $current_user_name,
                    'user_img' => $current_user_image,
                    'comment' => $comment,
                    'pin_id' => (int)$pin_id,
                ],
            ]);
        }

        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while adding comment.']);
        }
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

$modal_pin_data = ['image' => '', 'title' => '', 'like_count' => 0, 'user_liked' => false, 'creator_name' => '', 'creator_id' => '', 'creator_img' => ''];
if (isset($_GET['pin_id'])) {
    $pin_id = filter_var($_GET['pin_id'], FILTER_SANITIZE_NUMBER_INT);

    $query = "
        SELECT p.id, p.img, p.title, 
               p.user_id as creator_id,
               COALESCE(r.username, 'Unknown') as creator_name,
               COALESCE(r.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
               EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked
        FROM pins p
        LEFT JOIN registration r ON p.user_id = r.id
        WHERE p.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $pin_id]);
    $pin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pin_data) {
        $modal_pin_data = [
            'image' => $pin_data['img'] ? '../images/' . htmlspecialchars($pin_data['img']) : '../images/no_image.jpg',
            'title' => htmlspecialchars($pin_data['title'] ?? 'Pin'),
            'like_count' => $pin_data['like_count'],
            'user_liked' => $pin_data['user_liked'],
            'creator_name' => htmlspecialchars($pin_data['creator_name'] ?? 'Unknown'),
            'creator_id' => $pin_data['creator_id'] ? htmlspecialchars($pin_data['creator_id']) : '',
            'creator_img' => $pin_data['creator_img'] ? '../images/' . htmlspecialchars($pin_data['creator_img']) : '../images/no_image.jpg'
        ];
    }

    $query = "
        SELECT c.id, c.comment, c.created_at, c.user_id, r.username, r.img as user_img
        FROM comments c
        JOIN registration r ON c.user_id = r.id
        WHERE c.pin_id = ?
        ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$pin_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Loaded comments for pin_id {$pin_id}: " . count($comments));
} else {
    $comments = [];
}
?>