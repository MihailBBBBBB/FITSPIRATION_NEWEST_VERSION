<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'csrf.inc.php';

function isAtLeast13YearsOld(string $birthdate): bool {
    $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
    if (!$birthDate || $birthDate->format('Y-m-d') !== $birthdate) {
        return false;
    }

    $today = new DateTimeImmutable('today');
    $minimumBirthdate = $today->modify('-13 years');

    return $birthDate <= $minimumBirthdate;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    requireValidCsrfToken();
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $birthdate = $_POST["birthdate"] ?? '';

    if ($email === '' || $password === '' || $birthdate === '') {
        $_SESSION['form_data'] = [
            'email' => $email,
            'birthdate' => $birthdate
        ];
        $_SESSION['registration_error'] = "All fields are required.";
        header("Location: ../HTML/Registration.php?error=missingfields");
        exit();
    }

    if (!isAtLeast13YearsOld($birthdate)) {
        $_SESSION['form_data'] = [
            'email' => $email,
            'birthdate' => $birthdate
        ];
        $_SESSION['registration_error'] = 'You must be at least 13 years old to register.';
        header("Location: ../HTML/Registration.php?error=underage");
        exit();
    }

    try {
        require_once "dbh.inc.php";
        require_once "auth_password.inc.php";
        require_once "image_storage.inc.php";
        ensurePasswordStorageCapacity($pdo);

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
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Unable to hash password.');
        }

        $defaultAvatar = getFitspirationSharedDefaultAvatarFileName();

        // Insert user with username into database and a valid default avatar image.
        $query = "INSERT INTO registration (email, password, birthdate, username, img, banned) VALUES (?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email, $passwordHash, $birthdate, $username, $defaultAvatar, 0]);

        $user_id = $pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['username'] = $username; // Store username in session

        header("Location: ../HTML/Home.php");
        exit(); 

    } catch (PDOException $e) {
        error_log("Registration failed: " . $e->getMessage());
        $_SESSION['form_data'] = [
            'email' => $email,
            'birthdate' => $birthdate
        ];
        $_SESSION['registration_error'] = "Registration failed. Please try again.";
        header("Location: ../HTML/Registration.php?error=servererror");
        exit();
    } catch (RuntimeException $e) {
        error_log("Registration failed: " . $e->getMessage());
        $_SESSION['form_data'] = [
            'email' => $email,
            'birthdate' => $birthdate
        ];
        $_SESSION['registration_error'] = "Registration failed. Please try again.";
        header("Location: ../HTML/Registration.php?error=servererror");
        exit();
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

    throw new RuntimeException('Unable to generate a unique username.');
}