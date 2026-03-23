<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

require_once 'dbh.inc.php';

// Create outfits table if it does not exist yet
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS outfits (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        name        VARCHAR(255) NOT NULL,
        img         VARCHAR(255) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    error_log('Error creating outfits table: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database setup error']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['image_data'])) {
    echo json_encode(['success' => false, 'error' => 'No image data provided']);
    exit();
}

$imageData = $input['image_data'];
$name = isset($input['name']) ? trim($input['name']) : 'My Outfit';
$name = substr(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), 0, 255);
if ($name === '') {
    $name = 'My Outfit';
}

// Accept only PNG data URLs
if (!preg_match('/^data:image\/png;base64,/', $imageData)) {
    echo json_encode(['success' => false, 'error' => 'Invalid image format. Only PNG is accepted.']);
    exit();
}

$base64 = preg_replace('/^data:image\/png;base64,/', '', $imageData);
$base64 = str_replace(' ', '+', $base64);
$decoded = base64_decode($base64, true);

if ($decoded === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to decode image data']);
    exit();
}

// Limit file size to 10 MB
if (strlen($decoded) > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image is too large (max 10 MB)']);
    exit();
}

// Verify the decoded content is actually a PNG
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($decoded);
if ($mimeType !== 'image/png') {
    echo json_encode(['success' => false, 'error' => 'Decoded content is not a valid PNG image']);
    exit();
}

$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Upload directory is not available']);
    exit();
}

$fileName = uniqid('outfit_', true) . '.png';
$filePath = $uploadDir . $fileName;

if (file_put_contents($filePath, $decoded) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to save image file']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO outfits (user_id, name, img) VALUES (?, ?, ?)");
    $stmt->execute([(int)$_SESSION['user_id'], $name, $fileName]);
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (PDOException $e) {
    error_log('Error saving outfit: ' . $e->getMessage());
    @unlink($filePath);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
