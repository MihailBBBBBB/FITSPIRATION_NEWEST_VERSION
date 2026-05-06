
<?php session_start(); require_once '../includes/csrf.inc.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up &mdash; Fitspiration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Registration.css?v=2"/>
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
        <h1 class="brand-tagline">Unlimited access<br><em>to creative ideas.</em></h1>
        <p class="brand-sub">Join thousands of fashion lovers. Build mood boards, save pins, discover trends, and create outfits that are uniquely you.</p>
        <div class="brand-badges">
            <span class="brand-badge">Free to join</span>
            <span class="brand-badge">Outfit Builder</span>
            <span class="brand-badge">Collections</span>
            <span class="brand-badge">Style Community</span>
        </div>
    </div>

    <!-- Form panel -->
    <div class="form-panel">
        <div class="form-inner">
            <h1>Create account</h1>
            <p class="form-subtitle">Start building your style today.</p>

            <?php if (isset($_SESSION['registration_error'])): ?>
                <div class="server-error-message"><?php echo htmlspecialchars($_SESSION['registration_error']); ?></div>
                <?php unset($_SESSION['registration_error']); ?>
            <?php endif; ?>

            <form id="registrationForm" action="../includes/formhandler.inc.php" method="post">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" placeholder="you@example.com" name="email" autocomplete="email"
                           value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
                    <span id="emailError" class="error-message"></span>
                </div>
                <div class="form-group">
                    <label for="password">Create a password</label>
                    <input type="password" id="password" placeholder="At least 8 characters" name="password" autocomplete="new-password">
                    <span id="passwordError" class="error-message"></span>
                </div>
                <div class="form-group">
                    <label for="dob">Date of birth</label>
                    <input type="text" id="dob" placeholder="yyyy-mm-dd" name="birthdate"
                           value="<?php echo isset($_SESSION['form_data']['birthdate']) ? htmlspecialchars($_SESSION['form_data']['birthdate']) : ''; ?>">
                    <span id="dobError" class="error-message"></span>
                </div>
                <button type="submit" class="continue-button">Create Account</button>
                <button type="button" class="login-button" onclick="window.location.href='LogIn.php'">Already have an account? Log In</button>
            </form>
        </div>
    </div>

    <script src="../JS/Registration.js"></script>
    <?php unset($_SESSION['form_data']); ?>
</body>
</html>
