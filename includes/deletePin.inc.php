<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'csrf.inc.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

requireValidCsrfToken(true);

if (!isset($_POST['pin_id'])) {
    echo json_encode(['success' => false, 'error' => 'No pin ID provided']);
    exit();
}

$pin_id = $_POST['pin_id'];
require_once "dbh.inc.php";

// Verify the pin belongs to the user
$query = "SELECT user_id, img FROM pins WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$pin_id]);
$pin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pin || $pin['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'Pin not found or not authorized']);
    exit();
}

// Delete the image file if it exists
if ($pin['img']) {
    $file_path = '../images/' . $pin['img'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Delete the pin
$delete_query = "DELETE FROM pins WHERE id = ? AND user_id = ?";
$delete_stmt = $pdo->prepare($delete_query);
$success = $delete_stmt->execute([$pin_id, $user_id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete pin']);
}
?>