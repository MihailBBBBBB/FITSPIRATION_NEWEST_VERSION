<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'csrf.inc.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

requireValidCsrfToken(true);

require_once 'dbh.inc.php';

$input = json_decode(file_get_contents('php://input'), true);
$outfit_id = isset($input['outfit_id']) ? (int)$input['outfit_id'] : 0;

if ($outfit_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid outfit ID']);
    exit();
}

try {
    // Fetch outfit and verify ownership before deleting
    $stmt = $pdo->prepare("SELECT id, img, user_id FROM outfits WHERE id = ?");
    $stmt->execute([$outfit_id]);
    $outfit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$outfit) {
        echo json_encode(['success' => false, 'error' => 'Outfit not found']);
        exit();
    }

    if ((int)$outfit['user_id'] !== (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Not authorised to delete this outfit']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM outfits WHERE id = ? AND user_id = ?");
    $stmt->execute([$outfit_id, (int)$_SESSION['user_id']]);

    // Remove the image file
    $imgPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $outfit['img'];
    if (file_exists($imgPath)) {
        @unlink($imgPath);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Error deleting outfit: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
