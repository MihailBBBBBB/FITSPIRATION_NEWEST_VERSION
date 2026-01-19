<?php
include_once '../includes/dbh.inc.php';

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; // По умолчанию сортировка по дате (убывание)
error_log('Received search term: ' . $searchTerm . ', sort: ' . $sort);

$user_id = $_SESSION['user_id'] ?? null;
error_log('Current user_id from session: ' . ($user_id ?? 'Not set'));

try {
    // Определяем условие сортировки
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

    $query = "
        SELECT p.id, p.title, p.img,
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c WHERE c.pin_id = p.id) as comment_count
        FROM pins p
        INNER JOIN collections c ON p.collection_id = c.collection_id
        WHERE c.privacy = 'Public'
        AND p.title LIKE :search
        ORDER BY $orderBy
    ";
    $stmt = $pdo->prepare($query);
    $searchPattern = "%" . $searchTerm . "%";
    $stmt->bindParam(':search', $searchPattern, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT, 11);
    $stmt->execute();
    $pins1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('Public pins fetched for search "' . $searchTerm . '", sort "' . $sort . '": ' . count($pins1));
} catch (PDOException $e) {
    error_log('Error fetching public pins: ' . $e->getMessage());
    $pins1 = [];
}


// Обработка лайка/дизлайка
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    error_log("Attempting to toggle like: user_id={$user_id}, pin_id={$pin_id}");

    try {
        // Проверяем, существует ли пин
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            header("Location: Home.php" . ($searchTerm ? "?search=" . urlencode($searchTerm) : "") . "&sort=" . urlencode($sort) . "?error=pinnotfound");
            exit();
        }

        // Проверяем, лайкнул ли пользователь этот пин
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

        // Сохраняем pin_id и sort для перенаправления
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        $redirect_url = "Home.php?error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
}

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $comment = trim($_POST['comment']);
    
    try {
        // Проверяем, существует ли пин
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            $redirect_url = "Home.php?error=pinnotfound&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        // Добавляем комментарий
        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        // Сохраняем pin_id и sort для перенаправления
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        $redirect_url = "Home.php?error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
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
            $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        // Проверяем, является ли пользователь автором комментария или владельцем пина
        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        // Удаляем комментарий
        $query = "DELETE FROM comments WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id]);
        error_log("Comment deleted: comment_id={$comment_id}, user_id={$user_id}");

        // Перенаправляем с сохранением состояния модала
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
}

// Загрузка данных пина для модала
$modal_pin_data = ['image' => '', 'title' => '', 'like_count' => 0, 'user_liked' => false];
if (isset($_GET['pin_id'])) {
    $pin_id = filter_var($_GET['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];

    // Загружаем данные пина
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
            'image' => $pin_data['img'] ? '../images/' . htmlspecialchars($pin_data['img']) : '../images/no_image.jpg',
            'title' => htmlspecialchars($pin_data['title'] ?? 'Pin'),
            'like_count' => $pin_data['like_count'],
            'user_liked' => $pin_data['user_liked']
        ];
    }
    
    // Загружаем комментарии
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

