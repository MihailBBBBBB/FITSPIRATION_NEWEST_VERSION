

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Registration.css"/>
</head>
<body>

    <div class="container">
        <h1>Unlimited Access to Creative Ideas</h1>
        <?php if (isset($_SESSION['registration_error'])): ?>
            <div class="server-error-message"><?php echo htmlspecialchars($_SESSION['registration_error']); ?></div>
            <?php unset($_SESSION['registration_error']); ?>
        <?php endif; ?>
        <form id="registrationForm" action="../includes/formhandler.inc.php" method="post">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" placeholder="Email address" name="email" 
                       value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
                <span id="emailError" class="error-message"></span>
            </div>
            <div class="form-group">
                <label for="password">Create a password</label>
                <input type="password" id="password" placeholder="Create a password" name="password">
                <span id="passwordError" class="error-message"></span>
            </div>
            <div class="form-group">
                <label for="dob">Date of birth</label>
                <input type="text" id="dob" placeholder="yyyy-mm-dd" name="birthdate"
                       value="<?php echo isset($_SESSION['form_data']['birthdate']) ? htmlspecialchars($_SESSION['form_data']['birthdate']) : ''; ?>">
                <span id="dobError" class="error-message"></span>
            </div>
            <button type="submit" class="continue-button">Continue</button>
            <button type="button" class="login-button" onclick="window.location.href='LogIn.php'">Already have an account? Log In</button>
        </form>
    </div>
    <script src="../JS/Registration.js"></script>
    <?php unset($_SESSION['form_data']); ?>
</body>
</html>