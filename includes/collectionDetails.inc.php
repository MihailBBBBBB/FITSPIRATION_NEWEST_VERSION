<?php
session_start();
require_once "dbh.inc.php";
require_once "notifications.inc.php";

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

// Fetch collection details
try {
    $query = "SELECT collection_id, user_id, title, description, privacy FROM collections WHERE collection_id = ? OR user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$collection_id, $user_id]);
    $collection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$collection) {
        header("Location: ../HTML/Home.php?error=invalidcollection");
        exit();
    }
    error_log("Collection fetched: collection_id={$collection_id}, title={$collection['title']}");
} catch (PDOException $e) {
    error_log('Error fetching collection: ' . $e->getMessage());
    header("Location: ../HTML/Home.php?error=dberror");
    exit();
}

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
        WHERE p.collection_id = :collection_id AND p.user_id = :user_id
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

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("Attempting to toggle like: user_id={$user_id}, pin_id={$pin_id}");

    try {
        // Check if pin exists
        $query = "SELECT id FROM pins WHERE id = ? AND collection_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $collection_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        // Check if user has liked this pin
        $query = "SELECT * FROM likes WHERE user_id = ? AND pin_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $pin_id]);
        $like = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($like) {
            $query = "DELETE FROM likes WHERE user_id = ? AND pin_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $pin_id]);
            error_log("Like removed: user_id={$user_id}, pin_id={$pin_id}");

            $pin_owner_id = getPinOwnerId($pdo, $pin_id);
            if ($pin_owner_id) {
                removeNotification($pdo, $pin_owner_id, $user_id, 'like', $pin_id);
            }
        } else {
            $query = "INSERT INTO likes (user_id, pin_id, date) VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $pin_id]);
            error_log("Like added: user_id={$user_id}, pin_id={$pin_id}");

            $pin_owner_id = getPinOwnerId($pdo, $pin_id);
            if ($pin_owner_id) {
                addNotification($pdo, $pin_owner_id, $user_id, 'like', $pin_id);
            }
        }

        // Redirect to preserve pin modal state
        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle comment addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $comment = trim(filter_var($_POST['comment'], FILTER_SANITIZE_STRING));

    if (empty($comment)) {
        error_log("Empty comment submitted: user_id={$user_id}, pin_id={$pin_id}");
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
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        // Add comment
        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        $pin_owner_id = getPinOwnerId($pdo, $pin_id);
        if ($pin_owner_id) {
            addNotification($pdo, $pin_owner_id, $user_id, 'comment', $pin_id);
        }

        // Redirect to preserve pin modal state
        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
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
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort) . "#pinModal");
            exit();
        }

        // Check if user is comment author or pin owner
        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort) . "#pinModal");
            exit();
        }

        // Delete comment
        $query = "DELETE FROM comments WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id]);
        error_log("Comment deleted: comment_id={$comment_id}, user_id={$user_id}");

        // Redirect to preserve pin modal state
        $redirect_url = "collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        header("Location: collectionDetails.php?collection_id=" . urlencode($collection_id) . "&pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

// Handle pin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pin'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("Attempting to delete pin: user_id={$user_id}, pin_id={$pin_id}");

    try {
        // Check if pin exists and belongs to user
        $query = "SELECT id FROM pins WHERE id = ? AND user_id = ? AND collection_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $collection_id]);
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
    'creator_img' => '../images/no_image.jpg'
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
            'creator_img' => !empty($pin_data['creator_img']) ? '../images/' . htmlspecialchars($pin_data['creator_img']) : '../images/no_image.jpg'
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
