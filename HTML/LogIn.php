<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Login.css"/>
</head>
<body>
    <div class="container"> 
        <h1>Welcome Back!</h1>
        <form id="loginForm" action="../includes/Login.inc.php" method="post">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" placeholder="Enter your email" name="email">
                <span id="emailError" class="error-message"></span>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" placeholder="Enter your password" name="password">
                <span id="passwordError" class="error-message"></span>
            </div>
            <button type="submit" class="continue-button">Log In</button>
            <button type="button" class="signup-button" onclick="window.location.href='Registration.php'">Don't have an account? Sign Up</button>
        </form>
    </div>
    <script src="../JS/Login.js"></script>
</body>
</html>

