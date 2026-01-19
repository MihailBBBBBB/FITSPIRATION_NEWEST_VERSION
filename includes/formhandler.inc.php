<?php
session_start(); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $birthdate = $_POST["birthdate"];

    try {
        require_once "dbh.inc.php";

        // Check if email exists
        $checkQuery = "SELECT email FROM registration WHERE email = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$email]);
        
        if ($checkStmt->rowCount() > 0) {
            // Store form data and error in session to repopulate form
            $_SESSION['form_data'] = [
                'email' => $email,
                'birthdate' => $birthdate
            ];
            $_SESSION['registration_error'] = "This email is already registered.";
            header("Location: ../HTML/Registration.php?error=emailtaken");
            exit();
        }

        // Generate random username
        $username = generateUniqueUsername($pdo);

        // Insert user with username into database
        $query = "INSERT INTO registration (email, password, birthdate, username) VALUES (?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email, $password, $birthdate, $username]);

        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username; // Store username in session

        header("Location: ../HTML/Home.php");
        exit(); 

    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    header("Location: ../HTML/Registration.php");
    exit();
}

// Function to generate a unique random username
function generateUniqueUsername($pdo) {
    $prefixes = ['Creative', 'Spark', 'Dream', 'Star', 'Vibe', 'Quest'];
    $maxAttempts = 10;

    for ($i = 0; $i < $maxAttempts; $i++) {
        $prefix = $prefixes[array_rand($prefixes)];
        $randomString = bin2hex(random_bytes(4)); // Generate 8-character random string
        $username = $prefix . $randomString;

        // Check if username exists
        $checkQuery = "SELECT username FROM registration WHERE username = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$username]);

        if ($checkStmt->rowCount() == 0) {
            return $username; // Username is unique
        }
    }
}