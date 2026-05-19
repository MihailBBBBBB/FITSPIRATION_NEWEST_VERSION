<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "dbh.inc.php";
require_once "notifications.inc.php";
require_once "reports.inc.php";
require_once "likes.inc.php";
require_once "csrf.inc.php";
require_once "collection_collaboration.inc.php";
require_once __DIR__ . '/image_storage.inc.php';

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

ensureModerationTables($pdo);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Registration.php?error=notloggedin");
    exit();
}

// Ensure collection_id is provided
if (!isset($_GET['collection_id'])) {
    header("Location: ../HTML/Home.php?error=nocollection");
    exit();
}

$user_id = $_SESSION['user_id'];
$collection_id = filter_var($_GET['collection_id'], FILTER_SANITIZE_NUMBER_INT);
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; // Default sorting
error_log('Received sort: ' . $sort);

$collaborationNotice = '';
$collaborationNoticeType = '';
$collection_owner_name = 'Owner';
$collection_owner_image = buildFitspirationDefaultAvatarDataUrl($collection_owner_name);

$current_user_name = 'You';
$current_user_image = buildFitspirationDefaultAvatarDataUrl($current_user_name);
try {
    $currentUserStmt = $pdo->prepare('SELECT username, img FROM registration WHERE id = ? LIMIT 1');
    $currentUserStmt->execute([$user_id]);
    $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
    if ($currentUser) {
        if (!empty($currentUser['username'])) {
            $current_user_name = $currentUser['username'];
        }
        $current_user_image = buildFitspirationAvatarUrl($currentUser['img'] ?? '', $current_user_name);
    }
} catch (PDOException $e) {
    error_log('Error fetching current user profile: ' . $e->getMessage());
}

// Fetch collection details
try {
    $query = "SELECT collection_id, user_id, title, description, privacy FROM collections WHERE collection_id = ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$collection) {
        header("Location: ../HTML/Home.php?error=invalidcollection");
        exit();
    }

    $collection_owner_id = (int) ($collection['user_id'] ?? 0);
    $collection_access_role = resolveCollectionAccessRole($pdo, (int) $collection_id, $collection_owner_id, (int) $user_id);
    $can_manage_collection = $collection_access_role === 'owner';
    $can_edit_collection = canUserEditCollectionWithRole($collection_access_role);
    $can_view_collection = canUserViewCollectionWithRole($collection_access_role);
    $is_public_collection = (($collection['privacy'] ?? '') === 'Public');

    if (!$can_view_collection && !$is_public_collection) {
        header("Location: ../HTML/Home.php?error=unauthorizedcollection");
        exit();
    }

    $ownerInfoStmt = $pdo->prepare('SELECT username, img FROM registration WHERE id = ? LIMIT 1');
    $ownerInfoStmt->execute([$collection_owner_id]);
    $ownerInfo = $ownerInfoStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($ownerInfo) {
        $collection_owner_name = (string) ($ownerInfo['username'] ?? 'Owner');
        $collection_owner_image = buildFitspirationAvatarUrl($ownerInfo['img'] ?? '', $collection_owner_name);
    }

    error_log("Collection fetched: collection_id={$collection_id}, title={$collection['title']}");
} catch (PDOException $e) {
    error_log('Error fetching collection: ' . $e->getMessage());
    header("Location: ../HTML/Home.php?error=dberror");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_collaborator'])) {
    if (!$can_manage_collection) {
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=unauthorizedcollection");
        exit();
    }

    $inviteUsername = trim((string) ($_POST['invite_username'] ?? ''));
    $inviteRole = trim((string) ($_POST['invite_role'] ?? 'viewer'));
    $inviteResult = inviteCollectionCollaboratorByUsername($pdo, (int) $collection_id, (int) $user_id, $inviteUsername, $inviteRole);

    if (!empty($inviteResult['ok']) && !empty($inviteResult['user_id'])) {
        // Reuse notifications table: store collection_id in pin_id for collaboration events.
        addNotification($pdo, (int) $inviteResult['user_id'], (int) $user_id, 'collab_invite', (int) $collection_id);
    }

    $collaborationNotice = (string) ($inviteResult['message'] ?? 'Invite processed.');
    $collaborationNoticeType = !empty($inviteResult['ok']) ? 'ok' : 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_collaborator_role'])) {
    if (!$can_manage_collection) {
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=unauthorizedcollection");
        exit();
    }

    $targetUserId = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
    $targetRole = trim((string) ($_POST['target_role'] ?? 'viewer'));

    if ($targetUserId <= 0 || $targetUserId === (int) $collection_owner_id) {
        $collaborationNotice = 'Invalid collaborator selected.';
        $collaborationNoticeType = 'error';
    } else {
        $updated = updateCollectionCollaboratorRole($pdo, (int) $collection_id, $targetUserId, $targetRole);
        if ($updated) {
            // Reuse notifications table: store collection_id in pin_id for collaboration events.
            addNotification($pdo, $targetUserId, (int) $user_id, 'collab_role_change', (int) $collection_id);
        }
        $collaborationNotice = $updated ? 'Collaborator role updated.' : 'Role update failed.';
        $collaborationNoticeType = $updated ? 'ok' : 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_collaborator'])) {
    if (!$can_manage_collection) {
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=unauthorizedcollection");
        exit();
    }

    $targetUserId = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
    if ($targetUserId <= 0 || $targetUserId === (int) $collection_owner_id) {
        $collaborationNotice = 'Invalid collaborator selected.';
        $collaborationNoticeType = 'error';
    } else {
        $removed = removeCollectionCollaborator($pdo, (int) $collection_id, $targetUserId);
        $collaborationNotice = $removed ? 'Collaborator removed.' : 'Remove failed.';
        $collaborationNoticeType = $removed ? 'ok' : 'error';
    }
}

$collectionCollaborators = getCollectionCollaborators($pdo, (int) $collection_id);

// Determine sorting condition
$orderBy = 'id DESC'; // Default
switch ($sort) {
    case 'likes_asc':
        $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) ASC';
        break;
    case 'likes_desc':
        $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) DESC';
        break;
    case 'date_asc':
        $orderBy = 'id ASC';
        break;
    case 'date_desc':
        $orderBy = 'id DESC';
        break;
}

// Fetch pins for this collection
try {
    $pin_query = "
        SELECT p.id, p.img, p.title,
               p.user_id as creator_id,
               COALESCE(r.username, 'Unknown') as creator_name,
               COALESCE(r.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p
        LEFT JOIN registration r ON p.user_id = r.id
         WHERE p.collection_id = :collection_id
        ORDER BY $orderBy
    ";
    $pin_stmt = $pdo->prepare($pin_query);
    $pin_stmt->bindParam(':collection_id', $collection_id, PDO::PARAM_INT);
    $pin_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $pin_stmt->execute();
    $pins = $pin_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Pins fetched for collection_id {$collection_id}, sort {$sort}: " . count($pins));
} catch (PDOException $e) {
    error_log('Error fetching pins: ' . $e->getMessage());
    $pins = [];
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
            WHERE p.id = ? AND p.collection_id = ?
            LIMIT 1
        ";
        $pinStmt = $pdo->prepare($pinQuery);
        $pinStmt->execute([(int)$user_id, (int)$pin_id, (int)$collection_id]);
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
        $sessionId = (int)($user_id ?? 0);
        $commentsPayload = [];
        foreach ($commentsData as $commentRow) {
            $commentAuthorId = (int)($commentRow['user_id'] ?? 0);
            $commentsPayload[] = [
                'id' => (int)($commentRow['id'] ?? 0),
                'comment' => (string)($commentRow['comment'] ?? ''),
                'user_id' => $commentAuthorId,
                'username' => (string)($commentRow['username'] ?? 'Unknown'),
                'user_img' => buildFitspirationAvatarUrl($commentRow['user_img'] ?? '', (string) ($commentRow['username'] ?? 'Unknown')),
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
                'creator_img' => buildFitspirationAvatarUrl($pinData['creator_img'] ?? '', (string) ($pinData['creator_name'] ?? 'Unknown')),
                'like_count' => (int)($pinData['like_count'] ?? 0),
                'user_liked' => !empty($pinData['user_liked']),
            ],
            'comments' => $commentsPayload,
        ]);
    } catch (PDOException $e) {
        error_log('Error loading collection pin modal data: ' . $e->getMessage());
        sendAjaxJson(['success' => false, 'message' => 'Database error while loading pin modal.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $reportTargetType = trim($_POST['report_target_type'] ?? '');
    $reportTargetId = filter_var($_POST['report_target_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $reportReason = trim($_POST['report_reason'] ?? '');
    $reportCategory = trim($_POST['report_category'] ?? 'other');

    if (!in_array($reportTargetType, ['pin', 'comment'], true)) {
        $result = ['ok' => false, 'message' => 'Invalid report target.'];
    } else {
        $result = createContentReport($pdo, (int)$user_id, $reportTargetType, (int)$reportTargetId, $reportReason, $reportCategory);
    }

    $status = $result['ok'] ? 'ok' : 'error';
    $redirect_url = "collectionDetails.php?collection_id=" . urlencode((string)$collection_id) . "&pin_id=" . urlencode((string)$pin_id) . "&sort=" . urlencode($sort) . "&report_status=" . urlencode($status) . "&report_msg=" . urlencode($result['message']);
    header("Location: $redirect_url#pinModal");
    exit();
}

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to toggle like: user_id={$user_id}, pin_id={$pin_id}");

    try {
        if (!pinExistsInCollection($pdo, (int) $pin_id, (int) $collection_id)) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $likeData = togglePinLike($pdo, (int) $user_id, (int) $pin_id);

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'like' => $likeData,
            ]);
        }

        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error.']);
        }
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle comment addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $comment = trim(strip_tags((string)($_POST['comment'] ?? '')));
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );

    if (empty($comment)) {
        error_log("Empty comment submitted: user_id={$user_id}, pin_id={$pin_id}");
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Comment cannot be empty.']);
        }
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=emptycomment&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }

    try {
        // Check if pin exists
        $query = "SELECT id FROM pins WHERE id = ? AND collection_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $collection_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        // Add comment
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

        // Redirect to preserve pin modal state
        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while adding comment.']);
        }
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to delete comment: user_id={$user_id}, comment_id={$comment_id}, pin_id={$pin_id}");

    try {
        // Check if comment exists and user has permission
        $query = "
            SELECT c.id, c.user_id, p.user_id as pin_owner_id
            FROM comments c
            JOIN pins p ON c.pin_id = p.id
            WHERE c.id = ? AND c.pin_id = ? AND p.collection_id = ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id, $pin_id, $collection_id]);
        $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment_data) {
            error_log("Comment or pin not found: comment_id={$comment_id}, pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Comment not found.']);
            }
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort) . "#pinModal");
            exit();
        }

        // Check if user is comment author or pin owner
        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'You are not allowed to delete this comment.']);
            }
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort) . "#pinModal");
            exit();
        }

        // Delete comment
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

        // Redirect to preserve pin modal state
        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while deleting comment.']);
        }
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle pin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pin'])) {
    if (!$can_edit_collection) {
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=unauthorizedcollection&sort=" . urlencode($sort));
        exit();
    }

    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("Attempting to delete pin: user_id={$user_id}, pin_id={$pin_id}");

    try {
        // Check if pin exists in this collection.
        $query = "SELECT id FROM pins WHERE id = ? AND collection_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $collection_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist or unauthorized: pin_id={$pin_id}");
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        // Delete related likes and comments
        $pdo->prepare("DELETE FROM likes WHERE pin_id = ?")->execute([$pin_id]);
        $pdo->prepare("DELETE FROM comments WHERE pin_id = ?")->execute([$pin_id]);

        // Delete pin
        $query = "DELETE FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        error_log("Pin deleted: pin_id={$pin_id}");

        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&sort=" . urlencode($sort));
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting pin: ' . $e->getMessage());
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=dberror&sort=" . urlencode($sort));
        exit();
    }
}

// Load pin data for modal
$modal_pin_data = [
    'image' => '',
    'title' => '',
    'like_count' => 0,
    'user_liked' => false,
    'creator_id' => '',
    'creator_name' => 'Unknown',
    'creator_img' => buildFitspirationDefaultAvatarDataUrl('Unknown')
];
$comments = [];
if (isset($_GET['pin_id'])) {
    $pin_id = filter_var($_GET['pin_id'], FILTER_SANITIZE_NUMBER_INT);

    // Load pin data
    $query = "
         SELECT p.id, p.img, p.title,
             p.user_id as creator_id,
             COALESCE(r.username, 'Unknown') as creator_name,
             COALESCE(r.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
               EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked
        FROM pins p
         LEFT JOIN registration r ON p.user_id = r.id
        WHERE p.id = ? AND p.collection_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $pin_id, $collection_id]);
    $pin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pin_data) {
        $modal_pin_data = [
            'image' => $pin_data['img'] ? '../images/' . htmlspecialchars($pin_data['img']) : 'https://via.placeholder.com/600x800',
            'title' => htmlspecialchars($pin_data['title'] ?? 'Pin'),
            'like_count' => $pin_data['like_count'],
            'user_liked' => $pin_data['user_liked'],
            'creator_id' => $pin_data['creator_id'] ?? '',
            'creator_name' => htmlspecialchars($pin_data['creator_name'] ?? 'Unknown'),
            'creator_img' => buildFitspirationAvatarUrl($pin_data['creator_img'] ?? '', (string) ($pin_data['creator_name'] ?? 'Unknown'))
        ];
    }

    // Load comments
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
}
