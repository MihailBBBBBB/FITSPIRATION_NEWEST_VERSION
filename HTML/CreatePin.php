<?php

session_start();
include_once '../JS/headerFooter.php';
include_once '../includes/CreatePin.inc.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Pin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="../CSS/Main.css?v=14"/>
    <link rel="stylesheet" href="../CSS/Create.css?v=10"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js?v=3"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content">
            <div class="container">
                <?php if (isset($_SESSION['pin_error'])): ?>
                    <p class="form-error"><?php echo $_SESSION['pin_error']; unset($_SESSION['pin_error']); ?></p>
                <?php endif; ?>
                <!-- Displaying any pin creation errors -->
                <form action="../includes/CreatePin.inc.php" method="post" enctype="multipart/form-data" class="container">
                    <div class="upload-box">
                        <label for="file-upload">
                            <div style="font-size: 2rem;">⬆️</div>
                            <p>Choose a file</p>
                        </label>
                        <input type="file" id="file-upload" class="preview-input" name="pin_image" accept="image/png,image/jpeg" />
                        <p class="upload-hint" style="font-size: 0.85rem; margin-top: 20px;">We recommend using high-quality files in .jpg or .png format (less than 20 MB).</p>
                        <!-- File upload section for pin image -->
                    </div>
                    
                    <div class="form-section">
                        <input type="text" name="title" placeholder="Add a pin title" required/>
                        <textarea rows="4" name="description" placeholder="Add a detailed description"></textarea>
                        <input type="text" name="link" placeholder="Add a link" />
                        <div class="discovery-meta-grid">
                            <select name="dominant_color">
                                <option value="">Color (optional)</option>
                                <?php foreach (($discoveryOptionSets['colors'] ?? []) as $colorOption): ?>
                                    <option value="<?php echo htmlspecialchars($colorOption); ?>"><?php echo htmlspecialchars(ucfirst($colorOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="style_tag">
                                <option value="">Style (optional)</option>
                                <?php foreach (($discoveryOptionSets['styles'] ?? []) as $styleOption): ?>
                                    <option value="<?php echo htmlspecialchars($styleOption); ?>"><?php echo htmlspecialchars(ucfirst($styleOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="season">
                                <option value="">Season (optional)</option>
                                <?php foreach (($discoveryOptionSets['seasons'] ?? []) as $seasonOption): ?>
                                    <option value="<?php echo htmlspecialchars($seasonOption); ?>"><?php echo htmlspecialchars(ucfirst($seasonOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="category">
                                <option value="">Category (optional)</option>
                                <?php foreach (($discoveryOptionSets['categories'] ?? []) as $categoryOption): ?>
                                    <option value="<?php echo htmlspecialchars($categoryOption); ?>"><?php echo htmlspecialchars(ucfirst($categoryOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <select name="collection_id" required>
                            <option value="" disabled selected>Select a collection</option>
                            <?php foreach ($collection_options as $collection): ?>
                                <option value="<?php echo htmlspecialchars($collection['collection_id']); ?>">
                                    <?php
                                    $roleLabel = 'Owner';
                                    if (($collection['access_role'] ?? 'owner') === 'editor') {
                                        $roleLabel = 'Editor';
                                    }
                                    ?>
                                    <?php echo htmlspecialchars($collection['title'] . ' (' . $roleLabel . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="url-button">Create Pin</button>
                        <!-- Form fields for pin details and collection selection -->
                    </div>
                </form>
            </div>
        </main>
    </div>

    <special-footer></special-footer>
    <script src="../JS/CreateMediaPreview.js"></script>
</body>
</html>



