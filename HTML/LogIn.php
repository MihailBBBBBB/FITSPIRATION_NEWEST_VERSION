<?php
require_once '../includes/csrf.inc.php';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In &mdash; Fitspiration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Login.css?v=3"/>
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">

    <!-- Brand panel -->
    <div class="brand-panel">
        <button class="translate-btn" id="translate-btn" onclick="window.translator?.toggleTranslation()">LV</button>
        <div class="brand-deco" aria-hidden="true">
            <div class="brand-deco-circle" style="width:520px;height:520px;top:-160px;right:-160px;"></div>
            <div class="brand-deco-circle" style="width:200px;height:200px;bottom:100px;right:60px;"></div>
        </div>
        <p class="brand-logo no-translate">FITS<span>PIRATION</span></p>
        <h1 class="brand-tagline">Your style,<br><em>your story.</em></h1>
        <p class="brand-sub">Rediscover fashion every day. Save looks, build outfits, and get inspired by a community that loves style as much as you do.</p>
        <div class="brand-badges">
            <span class="brand-badge">Outfit Builder</span>
            <span class="brand-badge">Collections</span>
            <span class="brand-badge">Trending Pins</span>
            <span class="brand-badge">Style Community</span>
        </div>
    </div>

    <!-- Form panel -->
    <div class="form-panel">
        <div class="form-inner">
            <h1>Welcome back</h1>
            <p class="form-subtitle">Sign in to continue to your wardrobe.</p>

            <?php if ($error === 'wrongpassword' || $error === 'wrongcredentials'): ?>
                <div class="server-error-message">Incorrect email or password. Please try again.</div>
            <?php elseif ($error === 'notloggedin'): ?>
                <div class="server-error-message">Please log in to continue.</div>
            <?php elseif ($error): ?>
                <div class="server-error-message">Something went wrong. Please try again.</div>
            <?php endif; ?>

            <form id="loginForm" action="../includes/Login.inc.php" method="post">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" placeholder="you@example.com" name="email" autocomplete="email">
                    <span id="emailError" class="error-message"></span>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" name="password" autocomplete="current-password">
                    <span id="passwordError" class="error-message"></span>
                </div>
                <button type="submit" class="continue-button">Log In</button>
                <button type="button" class="signup-button" onclick="window.location.href='Registration.php'">Don't have an account? Sign Up</button>
            </form>
        </div>
    </div>

    <script src="../JS/Login.js"></script>
</body>
</html>

