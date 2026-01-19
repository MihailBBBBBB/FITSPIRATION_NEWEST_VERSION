<?php
session_start();
include_once '../includes/dbh.inc.php';

$user_id = $_SESSION['user_id'];
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; 
error_log('Received sort: ' . $sort);

function validateAndSaveImage($file, $upload_dir = '../images/') {
    if ($file['error'] !== 0) {
        return ['success' => false, 'error' => 'File upload error'];
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

    $file_name = uniqid('avatar_') . '.jpg';
    $file_path = $upload_dir . $file_name;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Failed to save image.'];
    }

    return ['success' => true, 'path' => $file_name];
}

try {
    $query2 = "SELECT username, description, img FROM registration WHERE id = ?";
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([$user_id]);
    $users = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$users) {
        header("Location: ../HTML/Login.php?error=usernotfound");
        exit();
    }
    error_log("User data fetched for user_id {$user_id}: username={$users['username']}");
} catch (PDOException $e) {
    error_log('Error fetching user data: ' . $e->getMessage());
    header("Location: ../HTML/Login.php?error=dberror");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $result = validateAndSaveImage($_FILES['avatar']);
        if (!$result['success']) {
            error_log('Avatar upload error: ' . $result['error']);
            header("Location: Profile.php?error=" . urlencode($result['error']) . "&sort=" . urlencode($sort));
            exit();
        }
        $new_avatar = $result['path'];

        try {
            // Delete old avatar if it exists
            if ($users['img'] && file_exists('../images/' . $users['img'])) {
                unlink('../images/' . $users['img']);
            }

            // Update avatar in database
            $query = "UPDATE registration SET img = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$new_avatar, $user_id]);
            error_log("Avatar updated for user_id {$user_id}: img={$new_avatar}");
            header("Location: Profile.php?sort=" . urlencode($sort));
            exit();
        } catch (PDOException $e) {
            error_log('Error updating avatar: ' . $e->getMessage());
            header("Location: Profile.php?error=dberror&sort=" . urlencode($sort));
            exit();
        }
    } else {
        error_log('No file uploaded or upload error');
        header("Location: Profile.php?error=nofile&sort=" . urlencode($sort));
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
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p 
        INNER JOIN collections c ON p.collection_id = c.collection_id 
        WHERE c.user_id = :user_id
        ORDER BY $orderBy
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
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
    $stmt1->execute([$user_id]);
    $collections = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    error_log("Collections fetched for user_id {$user_id}: " . count($collections));
} catch (PDOException $e) {
    error_log('Error fetching collections: ' . $e->getMessage());
    $collections = [];
}

try {
    $query = "
        SELECT p.id, p.img, p.title, l.date,
               (SELECT COUNT(*) FROM likes l2 WHERE l2.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l2 WHERE l2.user_id = :user_id AND l2.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p 
        INNER JOIN likes l ON p.id = l.pin_id 
        WHERE l.user_id = :user_id 
        ORDER BY $orderBy
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $liked_pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Liked pins fetched for user_id {$user_id}, sort {$sort}: " . count($liked_pins));
} catch (PDOException $e) {
    error_log('Error fetching liked pins: ' . $e->getMessage());
    $liked_pins = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $new_description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

    try {
        $query = "UPDATE registration SET username = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$new_username, $new_description, $user_id]);
        error_log("Profile updated for user_id {$user_id}: username={$new_username}");
        header("Location: Profile.php?sort=" . urlencode($sort));
        exit();
    } catch (PDOException $e) {
        error_log('Error updating profile: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("Attempting to toggle like: user_id={$user_id}, pin_id={$pin_id}");

    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: Profile.php?error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $query = "SELECT * FROM likes WHERE user_id = ? AND pin_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $pin_id]);
        $like = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($like) {
            $query = "DELETE FROM likes WHERE user_id = ? AND pin_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $pin_id]);
            error_log("Like removed: user_id={$user_id}, pin_id={$pin_id}");
        } else {
            $query = "INSERT INTO likes (user_id, pin_id, date) VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $pin_id]);
            error_log("Like added: user_id={$user_id}, pin_id={$pin_id}");
        }

        $redirect_url = "Profile.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        header("Location: Profile.php?error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $comment = trim($_POST['comment']);

    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: Profile.php?error=pinnotfound&sort=" . urlencode($sort));
            exit();
        }

        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        $redirect_url = "Profile.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        header("Location: Profile.php?error=dberror&sort=" . urlencode($sort) . "#pinModal");
        exit();
    }
}

$modal_pin_data = ['image' => '', 'title' => '', 'like_count' => 0, 'user_liked' => false];
if (isset($_GET['pin_id'])) {
    $pin_id = filter_var($_GET['pin_id'], FILTER_SANITIZE_NUMBER_INT);

    $query = "
        SELECT p.id, p.img, p.title, 
               (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
               EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked
        FROM pins p
        WHERE p.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $pin_id]);
    $pin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pin_data) {
        $modal_pin_data = [
            'image' => $pin_data['img'] ? '../images/' . htmlspecialchars($pin_data['img']) : 'https://via.placeholder.com/600x800',
            'title' => htmlspecialchars($pin_data['title'] ?? 'Pin'),
            'like_count' => $pin_data['like_count'],
            'user_liked' => $pin_data['user_liked']
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