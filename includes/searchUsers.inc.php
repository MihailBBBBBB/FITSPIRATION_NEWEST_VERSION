<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once 'dbh.inc.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (strlen($search) < 2) {
    echo json_encode(['users' => []]);
    exit();
}

try {
    $query = "SELECT id, username, img FROM registration 
              WHERE username LIKE ? 
              AND id != ? 
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $searchTerm = '%' . $search . '%';
    $stmt->execute([$searchTerm, $_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'users' => $users]);
} catch (PDOException $e) {
    error_log('User search failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
