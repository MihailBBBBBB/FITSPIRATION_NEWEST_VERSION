<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/csrf.inc.php';
require_once '../includes/dbh.inc.php';
require_once '../includes/outfits_schema.inc.php';
include_once '../JS/headerFooter.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../HTML/Login.php?error=notloggedin');
    exit();
}

function inferBuilderCategory(string $name): string {
    $value = strtolower($name);
    if (preg_match('/hat|cap|beanie|helmet|hood/', $value)) return 'head';
    if (preg_match('/coat|jacket|hoodie|blazer|cardigan|outer/', $value)) return 'outerwear';
    if (preg_match('/jean|pant|trouser|bottom|skirt|short|legging/', $value)) return 'bottoms';
    if (preg_match('/shoe|boot|heel|sneaker|loafer|slipper/', $value)) return 'shoes';
    if (preg_match('/bag|purse|tote|backpack/', $value)) return 'bag';
    if (preg_match('/belt|necklace|glove|scarf|chain|ring|bracelet|glass|accessor/', $value)) return 'accessory';
    return 'top';
}

$likedPinsForBuilder = [];
$editingOutfit = null;
$builderLoadError = '';
$remixOutfit = null;

try {
    $likedPinsStmt = $pdo->prepare(
        "SELECT p.id, p.title, p.img
         FROM pins p
         INNER JOIN likes l ON p.id = l.pin_id
         WHERE l.user_id = ? AND p.img IS NOT NULL AND p.img <> ''
         ORDER BY l.date DESC"
    );
    $likedPinsStmt->execute([(int) $_SESSION['user_id']]);

    foreach ($likedPinsStmt->fetchAll(PDO::FETCH_ASSOC) as $pin) {
        $fileName = basename((string) $pin['img']);
        if ($fileName === '' || strcasecmp($fileName, 'no_image.jpg') === 0) {
            continue;
        }

        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($absolutePath)) {
            continue;
        }

        $likedPinsForBuilder[] = [
            'id' => (int) $pin['id'],
            'title' => (string) ($pin['title'] ?? 'Untitled'),
            'img' => '../images/' . rawurlencode($fileName),
        ];
    }
} catch (PDOException $e) {
    error_log('OutfitBuilder liked pins load failed: ' . $e->getMessage());
}

try {
    ensureOutfitsTable($pdo);

    $requestedOutfitId = isset($_GET['outfit_id']) ? (int) $_GET['outfit_id'] : 0;
    $requestedRemixOutfitId = isset($_GET['remix_outfit_id']) ? (int) $_GET['remix_outfit_id'] : 0;
    if ($requestedOutfitId > 0) {
        $outfitStmt = $pdo->prepare(
            'SELECT id, name, img, builder_state,
                    EXISTS(
                        SELECT 1
                        FROM pins p
                        INNER JOIN collections c ON c.collection_id = p.collection_id
                        WHERE p.user_id = outfits.user_id
                          AND p.link = CONCAT("outfit://", outfits.id)
                          AND c.privacy = "Public"
                    ) AS is_shared
             FROM outfits
             WHERE id = ? AND user_id = ?
             LIMIT 1'
        );
        $outfitStmt->execute([$requestedOutfitId, (int) $_SESSION['user_id']]);
        $outfitRow = $outfitStmt->fetch(PDO::FETCH_ASSOC);

        if ($outfitRow) {
            $decodedState = json_decode((string) ($outfitRow['builder_state'] ?? ''), true);
            if (is_array($decodedState) && !empty($decodedState['items']) && is_array($decodedState['items'])) {
                $editingOutfit = [
                    'id' => (int) $outfitRow['id'],
                    'name' => (string) ($outfitRow['name'] ?? 'My Outfit'),
                    'img' => (string) ($outfitRow['img'] ?? ''),
                    'isShared' => !empty($outfitRow['is_shared']),
                    'builderState' => $decodedState,
                ];
            } else {
                $builderLoadError = 'This outfit cannot be edited because its saved builder data is missing.';
            }
        } else {
            header('Location: ../HTML/Profile.php?user_id=' . urlencode((string) $_SESSION['user_id']) . '&error=outfitaccess');
            exit();
        }
    } elseif ($requestedRemixOutfitId > 0) {
        $remixStmt = $pdo->prepare(
            'SELECT o.id, o.name, o.builder_state, owner.username AS owner_name
             FROM outfits o
             INNER JOIN registration owner ON owner.id = o.user_id
             INNER JOIN pins p ON p.user_id = o.user_id AND p.link = CONCAT("outfit://", o.id)
             INNER JOIN collections c ON c.collection_id = p.collection_id
             WHERE o.id = ? AND c.privacy = "Public"
             LIMIT 1'
        );
        $remixStmt->execute([$requestedRemixOutfitId]);
        $remixRow = $remixStmt->fetch(PDO::FETCH_ASSOC);

        if ($remixRow) {
            $decodedState = json_decode((string) ($remixRow['builder_state'] ?? ''), true);
            if (is_array($decodedState) && !empty($decodedState['items']) && is_array($decodedState['items'])) {
                $remixOutfit = [
                    'id' => (int) $remixRow['id'],
                    'name' => (string) ($remixRow['name'] ?? 'Remix Outfit'),
                    'ownerName' => (string) ($remixRow['owner_name'] ?? 'Creator'),
                    'builderState' => $decodedState,
                ];
            } else {
                $builderLoadError = 'This outfit cannot be remixed because its saved builder data is missing.';
            }
        } else {
            $builderLoadError = 'This outfit is not available for remix.';
        }
    }
} catch (PDOException $e) {
    error_log('OutfitBuilder editable load failed: ' . $e->getMessage());
    $builderLoadError = 'Could not load the saved outfit.';
}

$initialBuilderPayload = [
    'mode' => $editingOutfit ? 'edit' : 'create',
    'outfitId' => $editingOutfit['id'] ?? null,
    'name' => $editingOutfit['name'] ?? ($remixOutfit ? ('Remix: ' . $remixOutfit['name']) : ''),
    'isShared' => !empty($editingOutfit['isShared']),
    'builderState' => $editingOutfit['builderState'] ?? ($remixOutfit['builderState'] ?? null),
    'remixSource' => $remixOutfit ? [
        'outfitId' => $remixOutfit['id'],
        'name' => $remixOutfit['name'],
        'ownerName' => $remixOutfit['ownerName'],
    ] : null,
    'loadError' => $builderLoadError,
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outfit Builder</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="../CSS/Main.css?v=14"/>
    <link rel="stylesheet" href="../CSS/OutfitBuilder.css?v=12"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content outfit-page">
            <div class="builder-shell">
                <section class="builder-panel left-panel">
                    <h2>Wardrobe</h2>

                    <div class="wardrobe-tabs">
                        <button class="wardrobe-tab active" id="tabUpload" type="button">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                        </button>
                        <button class="wardrobe-tab" id="tabLiked" type="button">
                            <i class="fa-solid fa-heart"></i> Liked Pins
                        </button>
                    </div>

                    <div id="uploadPane">
                        <p class="helper-text">Upload clothing photos, then drag them into the slot boxes near the mannequin.</p>
                        <label class="upload-action" for="wardrobeUpload">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Upload items</span>
                        </label>
                        <input id="wardrobeUpload" type="file" accept="image/*" multiple>
                        <div class="wardrobe-list" id="wardrobeList"></div>
                    </div>

                    <div id="likedPane" class="hidden">
                        <p class="helper-text">Drag a liked pin into a slot box to duplicate it onto the mannequin.</p>
                        <div class="wardrobe-list" id="likedPinsList">
                            <?php foreach ($likedPinsForBuilder as $pin): ?>
                                <div class="wardrobe-card liked-pin-card"
                                     data-item-id="<?php echo (int) $pin['id']; ?>"
                                     data-item-name="<?php echo htmlspecialchars($pin['title'], ENT_QUOTES); ?>"
                                     data-item-src="<?php echo htmlspecialchars($pin['img'], ENT_QUOTES); ?>"
                                     data-item-category="<?php echo htmlspecialchars(inferBuilderCategory($pin['title']), ENT_QUOTES); ?>">
                                    <img src="<?php echo htmlspecialchars($pin['img'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($pin['title'], ENT_QUOTES); ?>">
                                    <div class="wardrobe-meta">
                                        <span class="wardrobe-title"><?php echo htmlspecialchars($pin['title']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p id="likedPinsEmpty" class="helper-text<?php echo !empty($likedPinsForBuilder) ? ' hidden' : ''; ?>">No liked pins with images yet.</p>
                    </div>
                </section>

                <section class="builder-stage-wrap">
                    <div class="stage-toolbar">
                        <h1><?php echo $editingOutfit ? 'Edit Outfit' : 'Outfit Builder'; ?></h1>
                        <p><?php echo $editingOutfit ? 'Adjust the saved outfit, then save your changes back to your profile.' : 'Drag wardrobe items into the equipment panels around the mannequin. Main clothing slots replace the current piece, accessory slots can stack, and filled panels can now remove or cycle items.'; ?></p>
                    </div>
                    <div class="builder-session-bar">
                        <span id="editModeBadge" class="session-chip<?php echo $editingOutfit ? '' : ' hidden'; ?>">Editing saved outfit</span>
                        <span id="remixModeBadge" class="session-chip accent<?php echo $remixOutfit ? '' : ' hidden'; ?>">Remix mode</span>
                        <span id="draftStateLabel" class="session-chip neutral">Draft clean</span>
                    </div>
                    <div class="stage-hint-bar">
                        <span class="hint-chip">Head Slot</span>
                        <span class="hint-chip">Base Top</span>
                        <span class="hint-chip">Outer Layer</span>
                        <span class="hint-chip">Bottom Layer</span>
                        <span class="hint-chip">Footwear</span>
                        <span class="hint-chip">Front and Back Accessories</span>
                    </div>
                    <div class="builder-stage" id="builderStage">
                        <div class="mannequin" aria-hidden="true">
                            <div class="head"></div>
                            <div class="torso"></div>
                            <div class="arm left"></div>
                            <div class="arm right"></div>
                            <div class="leg left"></div>
                            <div class="leg right"></div>
                        </div>
                        <div class="outfit-canvas" id="outfitCanvas"></div>
                    </div>
                    <p id="statusMessage" class="status-message"></p>
                </section>

                <section class="builder-panel right-panel">
                    <h2>Item Controls</h2>
                    <p id="selectedLabel" class="helper-text">No item selected</p>

                    <div class="control-group">
                        <label for="itemCategorySelect">Item category</label>
                        <select id="itemCategorySelect">
                            <option value="head">Headwear</option>
                            <option value="top">Top</option>
                            <option value="outerwear">Outerwear</option>
                            <option value="bottoms">Bottoms</option>
                            <option value="shoes">Shoes</option>
                            <option value="accessory">Accessory</option>
                            <option value="bag">Bag</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="itemSlotSelect">Snap slot</label>
                        <select id="itemSlotSelect">
                            <option value="head">Head Slot</option>
                            <option value="top">Base Top</option>
                            <option value="outerwear">Outer Layer</option>
                            <option value="bottoms">Bottom Layer</option>
                            <option value="shoes">Footwear</option>
                            <option value="accessory_front">Front Accessory</option>
                            <option value="accessory_back">Back Accessory</option>
                        </select>
                    </div>

                    <div class="layer-order-card">
                        <span class="layer-order-title">Layer Order</span>
                        <div class="layer-order-list">
                            <button type="button" class="layer-order-row back" data-slot-id="accessory_back">Back Accessory</button>
                            <button type="button" class="layer-order-row" data-slot-id="bottoms">Bottom Layer</button>
                            <button type="button" class="layer-order-row" data-slot-id="top">Base Top</button>
                            <button type="button" class="layer-order-row" data-slot-id="outerwear">Outer Layer</button>
                            <button type="button" class="layer-order-row" data-slot-id="head">Head Slot</button>
                            <button type="button" class="layer-order-row front" data-slot-id="accessory_front">Front Accessory</button>
                        </div>
                    </div>

                    <div class="action-row compact-actions">
                        <button id="snapItemBtn" type="button">Snap to slot</button>
                        <button id="fitToSlotBtn" type="button">Refit item</button>
                    </div>

                    <div class="control-group">
                        <label for="scaleRange">Scale</label>
                        <input id="scaleRange" type="range" min="0.2" max="2.8" step="0.05" value="1">
                    </div>

                    <div class="control-group">
                        <label for="rotateRange">Rotation</label>
                        <input id="rotateRange" type="range" min="-180" max="180" step="1" value="0">
                    </div>

                    <div class="nudge-panel">
                        <span class="nudge-label">Position nudge</span>
                        <div class="nudge-grid">
                            <button type="button" data-nudge="up">Up</button>
                            <button type="button" data-nudge="left">Left</button>
                            <button type="button" data-nudge="down">Down</button>
                            <button type="button" data-nudge="right">Right</button>
                        </div>
                    </div>

                    <div class="smart-match-card">
                        <div class="smart-match-head">
                            <span class="smart-match-title">Smart Match</span>
                            <span class="smart-match-sub">From selected item</span>
                        </div>
                        <p id="selectedMetaSummary" class="smart-meta-summary">Select an item to see metadata and generate a matching outfit.</p>
                        <button id="suggestOutfitBtn" type="button" class="primary" disabled>Auto-generate outfit</button>
                        <ul id="matchWhyList" class="match-why-list"></ul>
                    </div>

                    <div class="action-grid">
                        <button id="removeBgBtn" type="button">Remove background</button>
                        <button id="bringFrontBtn" type="button">Bring front</button>
                        <button id="sendBackBtn" type="button">Send back</button>
                        <button id="deleteItemBtn" type="button" class="danger">Delete item</button>
                        <button id="clearOutfitBtn" type="button" class="muted">Clear outfit</button>
                        <button id="downloadBtn" type="button" class="primary">Download outfit</button>
                    </div>

                    <div class="save-outfit-section">
                        <input id="outfitNameInput" type="text" placeholder="Outfit name..." maxlength="120" value="<?php echo htmlspecialchars($editingOutfit['name'] ?? ($remixOutfit ? ('Remix: ' . $remixOutfit['name']) : ''), ENT_QUOTES); ?>">
                        <label class="publish-toggle" for="publishOutfitToggle">
                            <input id="publishOutfitToggle" type="checkbox" <?php echo !isset($editingOutfit['isShared']) || $editingOutfit['isShared'] ? 'checked' : ''; ?>>
                            <span>Publish as public outfit post</span>
                        </label>
                        <div class="save-actions-row">
                            <button id="saveOutfitBtn" type="button" class="primary"><?php echo $editingOutfit ? 'Save Changes' : 'Save to Profile'; ?></button>
                            <button id="saveAsNewBtn" type="button" class="secondary<?php echo $editingOutfit ? '' : ' hidden'; ?>">Save as New Outfit</button>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <special-footer></special-footer>
    <script id="builderInitialState" type="application/json"><?php echo json_encode($initialBuilderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="../JS/OutfitBuilder.js?v=11"></script>
</body>
</html>
