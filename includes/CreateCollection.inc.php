<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Registration.php?error=notloggedin");
    exit();
}

// Function to validate and save image
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

    // Generate unique filename
    $file_name = uniqid('collection_') . '.jpg';
    $file_path = $upload_dir . $file_name;

    // Save file to images folder
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Failed to save image.'];
    }

    return ['success' => true, 'path' => $file_name];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST["title"];
    $description = $_POST["description"];
    $privacy = $_POST["privacy"];
    $user_id = $_SESSION['user_id'];

    // Handle image upload
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $result = validateAndSaveImage($_FILES['cover_image']);
        if (!$result['success']) {
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
        $_SESSION['collection_error'] = "Database error. Please try again later.";
        header("Location: ../HTML/CreateCollection.php?error=dberror");
        exit();
    }
}
?>