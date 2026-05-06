<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "csrf.inc.php";
require_once "image_storage.inc.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Registration.php?error=notloggedin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    requireValidCsrfToken();
    $title = trim((string)($_POST["title"] ?? ''));
    $description = trim((string)($_POST["description"] ?? ''));
    $privacy = strtolower(trim((string)($_POST["privacy"] ?? '')));
    $user_id = $_SESSION['user_id'];

    if ($title === '' || !in_array($privacy, ['public', 'private'], true)) {
        $_SESSION['collection_error'] = "Invalid collection data.";
        header("Location: ../HTML/CreateCollection.php?error=invalidinput");
        exit();
    }

    $privacy = $privacy === 'public' ? 'Public' : 'Private';

    // Handle image upload
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $result = saveFitspirationUploadedImage($_FILES['cover_image'], 'collection_');
        if (!$result['success']) {
            error_log('CreateCollection image upload failed: ' . (string) $result['error']);
            $_SESSION['collection_error'] = $result['error'];
            header("Location: ../HTML/CreateCollection.php?error=invalidimage");
            exit();
        }
        $cover_image = $result['path'];
    }

    try {
        require_once "dbh.inc.php";

        // Insert the new collection
        $query = "INSERT INTO collections (img, title, description, privacy, user_id) VALUES (?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$cover_image, $title, $description, $privacy, $user_id]);

        header("Location: ../HTML/Profile.php?collection=created");
        exit();
    } catch (PDOException $e) {
        error_log('CreateCollection database error: ' . $e->getMessage());
        $_SESSION['collection_error'] = "Database error. Please try again later.";
        header("Location: ../HTML/CreateCollection.php?error=dberror");
        exit();
    }
}
?>