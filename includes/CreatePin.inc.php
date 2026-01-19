<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Registration.php?error=notloggedin");
    exit();
}

// Fetch user's collections for the dropdown
$user_id = $_SESSION['user_id'];
require_once "dbh.inc.php";
$collections = $pdo->prepare("SELECT collection_id, title FROM collections WHERE user_id = ?");
$collections->execute([$user_id]);
$collection_options = $collections->fetchAll(PDO::FETCH_ASSOC);

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
    $file_name = uniqid('pin_') . '.jpg';
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
    $link = $_POST["link"];
    $collection_id = $_POST["collection_id"];
    $user_id = $_SESSION['user_id'];

    // Handle image upload
    $pin_image = null;
    if (isset($_FILES['pin_image']) && $_FILES['pin_image']['error'] == 0) {
        $result = validateAndSaveImage($_FILES['pin_image']);
        if (!$result['success']) {
            $_SESSION['pin_error'] = $result['error'];
            header("Location: ../HTML/CreatePin.php?error=invalidimage");
            exit();
        }
        $pin_image = $result['path'];
    }

    try {
        // Insert the new pin
        $query = "INSERT INTO pins (img, title, description, link, collection_id, user_id) VALUES (?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_image, $title, $description, $link, $collection_id, $user_id]);

        header("Location: ../HTML/Profile.php?pin=created");
        exit();
    } catch (PDOException $e) {
        $_SESSION['pin_error'] = "Database error. Please try again later.";
        header("Location: ../HTML/CreatePin.php?error=dberror");
        exit();
    }
}
?>