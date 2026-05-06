<?php
session_start();
include_once '../JS/headerFooter.php';
include_once '../includes/CreateCollection.inc.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="../CSS/Main.css?v=14"/>
    <link rel="stylesheet" href="../CSS/CreateCollection.css?v=9"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content">
            <div class="container">
                <?php if (isset($_SESSION['collection_error'])): ?>
                    <p class="form-error"><?php echo $_SESSION['collection_error']; unset($_SESSION['collection_error']); ?></p>
                <?php endif; ?>
                <form action="../includes/CreateCollection.inc.php" method="post" enctype="multipart/form-data" class="container">
                    <?php echo csrfInput(); ?>
                    <div class="upload-box">
                        <label for="file-upload">
                            <div style="font-size: 2rem;">⬆️</div>
                            <p>Choose a cover image</p>
                        </label>
                        <input type="file" id="file-upload" class="preview-input" name="cover_image" accept="image/jpeg, image/png" />
                        <p class="upload-hint" style="font-size: 0.85rem; margin-top: 20px;">Optional: Add a high-quality .jpg or .png image (less than 20 MB) as your collection cover.</p>
                    </div>
                    
                    <div class="form-section">
                        <input type="text" name="title" placeholder="Add a collection title" required />
                        <textarea rows="4" name="description" placeholder="Add a detailed description"></textarea>
                        <select name="privacy" required>
                            <option value="" disabled selected>Choose privacy setting</option>
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                        </select>
                        <button type="submit" class="url-button">Create Collection</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <special-footer></special-footer>
    <script src="../JS/CreateMediaPreview.js"></script>

</body>
</html>

