<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

include_once 'dbh.inc.php';
include_once 'csrf.inc.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

requireValidCsrfToken(true);

$user_id = $_SESSION['user_id'];
$collection_id = isset($_POST['collection_id']) ? $_POST['collection_id'] : null;

if (!$collection_id) {
    echo json_encode(['success' => false, 'error' => 'No collection ID provided']);
    exit();
}

try {
    $query = "SELECT user_id FROM collections WHERE collection_id = ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$collection) {
        echo json_encode(['success' => false, 'error' => 'Collection not found.']);
        exit();
    }

    if ((int) ($collection['user_id'] ?? 0) !== (int) $user_id) {
        echo json_encode(['success' => false, 'error' => 'You can only delete your own collections.']);
        exit();
    }

    $query = "DELETE FROM collections WHERE collection_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$collection_id, $user_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Delete collection error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
