<?php
session_start();
include_once '../JS/headerFooter.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../HTML/Login.php?error=notloggedin');
    exit();
}
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
    <link rel="stylesheet" href="../CSS/Main.css"/>
    <link rel="stylesheet" href="../CSS/OutfitBuilder.css"/>
    <script src="../JS/translator.js"></script>
</head>
<body>
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content outfit-page">
            <div class="builder-shell">
                <section class="builder-panel left-panel">
                    <h2>Wardrobe</h2>
                    <p class="helper-text">Upload clothing photos and add pieces to your outfit.</p>
                    <label class="upload-action" for="wardrobeUpload">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Upload items</span>
                    </label>
                    <input id="wardrobeUpload" type="file" accept="image/*" multiple>
                    <div class="wardrobe-list" id="wardrobeList"></div>
                </section>

                <section class="builder-stage-wrap">
                    <div class="stage-toolbar">
                        <h1>Outfit Builder</h1>
                        <p>Drag pieces onto the mannequin and style your look.</p>
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
                        <label for="scaleRange">Scale</label>
                        <input id="scaleRange" type="range" min="0.2" max="2.8" step="0.05" value="1">
                    </div>

                    <div class="control-group">
                        <label for="rotateRange">Rotation</label>
                        <input id="rotateRange" type="range" min="-180" max="180" step="1" value="0">
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
                        <input id="outfitNameInput" type="text" placeholder="Outfit name..." maxlength="120">
                        <button id="saveOutfitBtn" type="button" class="primary">Save to Profile</button>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <special-footer></special-footer>

    <script src="../JS/OutfitBuilder.js"></script>
</body>
</html>
