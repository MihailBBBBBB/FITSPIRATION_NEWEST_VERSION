<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'csrf.inc.php';
require_once 'image_storage.inc.php';

function respondJson(array $payload, int $status = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

set_exception_handler(function (Throwable $exception): void {
    error_log('saveOutfit uncaught error: ' . $exception->getMessage());
    respondJson(['success' => false, 'error' => 'Unexpected server error while saving outfit.'], 500);
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log('saveOutfit fatal error: ' . ($error['message'] ?? 'Unknown fatal error'));

    if (!headers_sent()) {
        respondJson(['success' => false, 'error' => 'Unexpected server error while saving outfit.'], 500);
    }
});

if (!isset($_SESSION['user_id'])) {
    respondJson(['success' => false, 'error' => 'Not logged in'], 401);
}

requireValidCsrfToken(true);

require_once 'dbh.inc.php';
require_once 'outfits_schema.inc.php';
require_once 'collection_collaboration.inc.php';

function clampUtf8String(string $value, int $maxLength): string {
    if ($maxLength <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function ensurePublicOutfitCollection(PDO $pdo, int $userId, ?int $preferredCollectionId = null): int {
    if (($preferredCollectionId ?? 0) > 0) {
        $collectionStmt = $pdo->prepare(
            'SELECT c.collection_id, c.user_id, c.privacy
             FROM collections c
             WHERE c.collection_id = ?
             LIMIT 1'
        );
        $collectionStmt->execute([(int) $preferredCollectionId]);
        $collection = $collectionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) {
            throw new RuntimeException('Selected collection was not found.');
        }

        $accessRole = resolveCollectionAccessRole($pdo, (int) $collection['collection_id'], (int) ($collection['user_id'] ?? 0), $userId);
        if (!canUserEditCollectionWithRole($accessRole)) {
            throw new RuntimeException('You cannot add posts to the selected collection.');
        }

        if ((string) ($collection['privacy'] ?? '') !== 'Public') {
            throw new RuntimeException('Please choose a public collection for a public outfit post.');
        }

        return (int) $collection['collection_id'];
    }

    $existing = $pdo->prepare('SELECT collection_id FROM collections WHERE user_id = ? AND privacy = "Public" ORDER BY collection_id ASC LIMIT 1');
    $existing->execute([$userId]);
    $collectionId = (int) $existing->fetchColumn();
    if ($collectionId > 0) {
        return $collectionId;
    }

    $title = 'Outfit Posts';
    $description = 'Public outfit posts generated from Outfit Builder';
    $insert = $pdo->prepare('INSERT INTO collections (img, title, description, privacy, user_id) VALUES (?, ?, ?, "Public", ?)');
    $insert->execute([null, $title, $description, $userId]);
    return (int) $pdo->lastInsertId();
}

try {
    ensureOutfitsTable($pdo);
    ensureCollectionCollaborationTables($pdo);
} catch (Throwable $e) {
    error_log('Error creating outfits table: ' . $e->getMessage());
    respondJson(['success' => false, 'error' => 'Database setup error'], 500);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['image_data'])) {
    respondJson(['success' => false, 'error' => 'No image data provided'], 400);
}

$imageData = $input['image_data'];
$name = isset($input['name']) ? trim($input['name']) : 'My Outfit';
$outfitId = isset($input['outfit_id']) ? (int) $input['outfit_id'] : 0;
$builderState = $input['builder_state'] ?? null;
$publishPost = !empty($input['publish_post']);
$publishCollectionId = isset($input['publish_collection_id']) ? (int) $input['publish_collection_id'] : 0;
$remixSourceOutfitId = isset($input['remix_source_outfit_id']) ? (int) $input['remix_source_outfit_id'] : 0;
$name = substr(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), 0, 255);
if ($name === '') {
    $name = 'My Outfit';
}

if (!is_array($builderState) || empty($builderState['items']) || !is_array($builderState['items'])) {
    respondJson(['success' => false, 'error' => 'Missing editable outfit data'], 400);
}

$normalizedItems = [];
foreach ($builderState['items'] as $item) {
    if (!is_array($item)) {
        continue;
    }

    $src = trim((string) ($item['src'] ?? ''));
    $slotId = trim((string) ($item['slotId'] ?? ''));
    $category = trim((string) ($item['category'] ?? 'top'));
    $itemName = trim((string) ($item['name'] ?? 'Item'));
    $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
    $metaCategory = trim((string) ($meta['category'] ?? $category));
    $metaColor = trim((string) ($meta['color'] ?? 'neutral'));
    $metaSeason = trim((string) ($meta['season'] ?? 'all-season'));
    $metaStyle = trim((string) ($meta['style'] ?? 'casual'));
    $metaOccasion = trim((string) ($meta['occasion'] ?? 'daily'));

    if ($src === '' || $slotId === '') {
        continue;
    }

    $normalizedItems[] = [
        'name' => substr($itemName, 0, 255),
        'src' => $src,
        'category' => substr($category, 0, 50),
        'meta' => [
            'category' => substr($metaCategory, 0, 50),
            'color' => substr($metaColor, 0, 40),
            'season' => substr($metaSeason, 0, 40),
            'style' => substr($metaStyle, 0, 40),
            'occasion' => substr($metaOccasion, 0, 40),
        ],
        'slotId' => substr($slotId, 0, 50),
        'offsetX' => (int) round((float) ($item['offsetX'] ?? 0)),
        'offsetY' => (int) round((float) ($item['offsetY'] ?? 0)),
        'scale' => (float) ($item['scale'] ?? 1),
        'rotation' => (float) ($item['rotation'] ?? 0),
        'z' => (int) ($item['z'] ?? 1),
        'layerLocked' => !empty($item['layerLocked']),
    ];
}

if (empty($normalizedItems)) {
    respondJson(['success' => false, 'error' => 'Editable outfit is empty'], 400);
}

$builderStateJson = json_encode([
    'version' => 1,
    'items' => $normalizedItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($builderStateJson === false) {
    respondJson(['success' => false, 'error' => 'Failed to encode outfit state'], 500);
}

// Accept only PNG data URLs
if (!preg_match('/^data:image\/png;base64,/', $imageData)) {
    respondJson(['success' => false, 'error' => 'Invalid image format. Only PNG is accepted.'], 400);
}

$base64 = preg_replace('/^data:image\/png;base64,/', '', $imageData);
$base64 = str_replace(' ', '+', $base64);
$decoded = base64_decode($base64, true);

if ($decoded === false) {
    respondJson(['success' => false, 'error' => 'Failed to decode image data'], 400);
}

// Limit file size to 10 MB
if (strlen($decoded) > 10 * 1024 * 1024) {
    respondJson(['success' => false, 'error' => 'Image is too large (max 10 MB)'], 413);
}

// Verify the decoded content is actually a PNG
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($decoded);
if ($mimeType !== 'image/png') {
    respondJson(['success' => false, 'error' => 'Decoded content is not a valid PNG image'], 400);
}

$directoryState = ensureFitspirationImagesDirectory();
if (!$directoryState['success']) {
    respondJson(['success' => false, 'error' => (string) $directoryState['error']], 500);
}

$uploadDir = $directoryState['path'];

$fileName = uniqid('outfit_', true) . '.png';
$filePath = $uploadDir . $fileName;

if (file_put_contents($filePath, $decoded) === false) {
    respondJson(['success' => false, 'error' => 'Failed to save image file'], 500);
}

try {
    $pdo->beginTransaction();

    $existingOutfit = null;
    if ($outfitId > 0) {
        $existingStmt = $pdo->prepare("SELECT id, img FROM outfits WHERE id = ? AND user_id = ? LIMIT 1");
        $existingStmt->execute([$outfitId, (int) $_SESSION['user_id']]);
        $existingOutfit = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingOutfit) {
            $pdo->rollBack();
            @unlink($filePath);
            respondJson(['success' => false, 'error' => 'Outfit not found'], 404);
        }
    }

    if ($existingOutfit) {
        $stmt = $pdo->prepare("UPDATE outfits SET name = ?, img = ?, builder_state = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $fileName, $builderStateJson, $outfitId, (int) $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO outfits (user_id, name, img, builder_state) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int) $_SESSION['user_id'], $name, $fileName, $builderStateJson]);
        $outfitId = (int) $pdo->lastInsertId();
    }

    if ($publishPost) {
        $publicCollectionId = ensurePublicOutfitCollection($pdo, (int) $_SESSION['user_id'], $publishCollectionId > 0 ? $publishCollectionId : null);
        $link = clampUtf8String('outfit://' . $outfitId, 150);
        $pinTitle = clampUtf8String($name, 50);
        $description = 'Outfit post from Outfit Builder';
        if ($remixSourceOutfitId > 0) {
            $description .= ' (Remix of #' . $remixSourceOutfitId . ')';
        }
        $description = clampUtf8String($description, 300);

        $pinStmt = $pdo->prepare('SELECT id FROM pins WHERE user_id = ? AND link = ? LIMIT 1');
        $pinStmt->execute([(int) $_SESSION['user_id'], $link]);
        $existingPinId = (int) $pinStmt->fetchColumn();

        if ($existingPinId > 0) {
            $updatePin = $pdo->prepare('UPDATE pins SET img = ?, title = ?, description = ?, collection_id = ? WHERE id = ? AND user_id = ?');
            $updatePin->execute([$fileName, $pinTitle, $description, $publicCollectionId, $existingPinId, (int) $_SESSION['user_id']]);
        } else {
            $insertPin = $pdo->prepare('INSERT INTO pins (img, title, description, link, collection_id, user_id) VALUES (?, ?, ?, ?, ?, ?)');
            $insertPin->execute([$fileName, $pinTitle, $description, $link, $publicCollectionId, (int) $_SESSION['user_id']]);
        }
    }

    $pdo->commit();

    if ($existingOutfit && !empty($existingOutfit['img']) && $existingOutfit['img'] !== $fileName) {
        $oldPath = $uploadDir . $existingOutfit['img'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    respondJson(['success' => true, 'id' => $outfitId, 'mode' => $existingOutfit ? 'updated' : 'created']);
} catch (RuntimeException $e) {
    error_log('Error saving outfit: ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($filePath);
    respondJson(['success' => false, 'error' => $e->getMessage()], 400);
} catch (PDOException $e) {
    error_log('Error saving outfit (PDO): ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($filePath);
    respondJson(['success' => false, 'error' => 'Database error'], 500);
} catch (Throwable $e) {
    error_log('Error saving outfit: ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($filePath);
    respondJson(['success' => false, 'error' => 'Database error'], 500);
}
