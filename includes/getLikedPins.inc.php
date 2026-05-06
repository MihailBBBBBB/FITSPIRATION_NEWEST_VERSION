<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit();
}

require_once __DIR__ . '/dbh.inc.php';

$user_id = (int)$_SESSION['user_id'];

function normalizeLikedPinImagePath(?string $imageValue): ?string {
    if (!$imageValue) {
        return null;
    }

    $imageValue = str_replace('\\', '/', trim($imageValue));
    if ($imageValue === '') {
        return null;
    }

    if (preg_match('~^(https?://|data:image/|blob:)~i', $imageValue)) {
        return $imageValue;
    }

    $fileName = basename($imageValue);
    if ($fileName === '' || $fileName === '.' || $fileName === '..') {
        return null;
    }

    if (strcasecmp($fileName, 'no_image.jpg') === 0) {
        return null;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($absolutePath)) {
        return null;
    }

    return '/FITSPIRATION/images/' . rawurlencode($fileName);
}

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.img
        FROM likes l
        INNER JOIN pins p ON l.pin_id = p.id
        WHERE l.user_id = ?
        ORDER BY l.date DESC
    ");
    $stmt->execute([$user_id]);
    $pins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_values(array_filter(array_map(function($pin) {
        $imagePath = normalizeLikedPinImagePath($pin['img']);
        if ($imagePath === null) {
            return null;
        }

        return [
            'id'    => (int)$pin['id'],
            'title' => $pin['title'],
            'img'   => $imagePath
        ];
    }, $pins)));

    echo json_encode($result);
} catch (PDOException $e) {
    error_log('getLikedPins error: ' . $e->getMessage());
    echo json_encode(['error' => 'db_error']);
}
