<?php
session_start();
include_once '../includes/dbh.inc.php';
include_once '../includes/follow.inc.php';
include_once '../includes/notifications.inc.php';

$session_user_id = $_SESSION['user_id'] ?? null;
$view_user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT) : $session_user_id;
if (!$view_user_id) {
    header("Location: ../HTML/Login.php?error=notloggedin");
    exit();
}

$user_id = $session_user_id; // keep for current logged-in user actions
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; 
error_log('Received sort: ' . $sort);

function validateAndSaveImage($file, $upload_dir = null) {
    if ($file['error'] !== 0) {
        return ['success' => false, 'error' => 'File upload error code: ' . (int)$file['error']];
    }

    // Validate file type and size (less than 20MB)
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 20 * 1024 * 1024; // 20MB
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false || !in_array($image_info['mime'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid image. Must be a .jpg or .png file.'];
    }
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Image must be under 20MB.'];
    }

    if ($upload_dir === null) {
        $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
    }

    if (!is_dir($upload_dir)) {
        return ['success' => false, 'error' => 'Upload directory not found.'];
    }

    if (!is_writable($upload_dir)) {
        return ['success' => false, 'error' => 'Upload directory is not writable.'];
    }

    $extension = $image_info['mime'] === 'image/png' ? 'png' : 'jpg';
    $file_name = uniqid('avatar_', true) . '.' . $extension;
    $file_path = $upload_dir . $file_name;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Failed to save image.'];
    }

    return ['success' => true, 'path' => $file_name];
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


// Обработка удаления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
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
            $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort) . "#pinModal";
            header("Location: $redirect_url");
            exit();
        }

        // Проверяем, является ли пользователь автором комментария или владельцем пина
        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort) . "#pinModal";
            header("Location: $redirect_url");
            exit();
        }

        // Удаляем комментарий
        $query = "DELETE FROM comments WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id]);
        error_log("Comment deleted: comment_id={$comment_id}, user_id={$user_id}");

        // Перенаправляем с сохранением состояния модала
        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort) . "#pinModal";
        header("Location: $redirect_url");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort) . "#pinModal";
        header("Location: $redirect_url");
        exit();
    }
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
        $result = validateAndSaveImage($_FILES['avatar']);
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
        SELECT c.collection_id, c.img, c.title, c.user_id, COUNT(p.id) as pin_count 
        FROM collections c 
        LEFT JOIN pins p ON c.collection_id = p.collection_id 
        WHERE c.user_id = ? 
        GROUP BY c.collection_id, c.img, c.title
    ";
    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([$view_user_id]);
    $collections = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    error_log("Collections fetched for user_id {$user_id}: " . count($collections));
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS outfits (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        name        VARCHAR(255) NOT NULL,
        img         VARCHAR(255) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare("SELECT id, name, img, created_at FROM outfits WHERE user_id = ? ORDER BY created_at DESC");
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
    if ($view_user_id !== $session_user_id) {
        header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=unauthorized&sort=" . urlencode($sort));
        exit();
    }
    $new_username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $new_description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

    try {
        $query = "UPDATE registration SET username = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$new_username, $new_description, $session_user_id]);
        error_log("Profile updated for user_id {$session_user_id}: username={$new_username}");
        header("Location: Profile.php?user_id=" . urlencode($session_user_id) . "&sort=" . urlencode($sort));
        exit();
    } catch (PDOException $e) {
        error_log('Error updating profile: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    if (!$session_user_id) {
        header("Location: ../HTML/Login.php?error=notloggedin");
        exit();
    }
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("Attempting to toggle like: user_id={$session_user_id}, pin_id={$pin_id}");

    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $query = "SELECT * FROM likes WHERE user_id = ? AND pin_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$session_user_id, $pin_id]);
        $like = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($like) {
            $query = "DELETE FROM likes WHERE user_id = ? AND pin_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$session_user_id, $pin_id]);
            error_log("Like removed: user_id={$session_user_id}, pin_id={$pin_id}");

            $pin_owner_id = getPinOwnerId($pdo, $pin_id);
            if ($pin_owner_id) {
                removeNotification($pdo, $pin_owner_id, $session_user_id, 'like', $pin_id);
            }
        } else {
            $query = "INSERT INTO likes (user_id, pin_id, date) VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$session_user_id, $pin_id]);
            error_log("Like added: user_id={$session_user_id}, pin_id={$pin_id}");

            $pin_owner_id = getPinOwnerId($pdo, $pin_id);
            if ($pin_owner_id) {
                addNotification($pdo, $pin_owner_id, $session_user_id, 'like', $pin_id);
            }
        }

        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
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
    $comment = trim($_POST['comment']);

    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: Profile.php?user_id=" . urlencode($view_user_id) . "&error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        $pin_owner_id = getPinOwnerId($pdo, $pin_id);
        if ($pin_owner_id) {
            addNotification($pdo, $pin_owner_id, $user_id, 'comment', $pin_id);
        }

        $redirect_url = "Profile.php?user_id=" . urlencode($view_user_id) . "&pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
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
        SELECT c.comment, c.created_at, r.username, r.img as user_img
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