<?php
require_once 'csrf.inc.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    requireValidCsrfToken();
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($email === '' || $password === '') {
        header("Location: ../HTML/LogIn.php?error=missingfields");
        exit();
    }

    try {
        require_once "dbh.inc.php";
        require_once "auth_password.inc.php";
        ensurePasswordStorageCapacity($pdo);

        $query = "SELECT * FROM registration WHERE email = ?;";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (!empty($user['banned'])) {
                header("Location: ../HTML/LogIn.php?error=banned");
                exit();
            }

            $storedPassword = $user['password'] ?? '';
            $passwordInfo = password_get_info($storedPassword);
            $isPasswordValid = false;
            $needsLegacyMigration = false;

            if (($passwordInfo['algo'] ?? null) !== null && ($passwordInfo['algo'] ?? 0) !== 0) {
                $isPasswordValid = password_verify($password, $storedPassword);

                if ($isPasswordValid && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                    $rehash = password_hash($password, PASSWORD_DEFAULT);
                    if ($rehash !== false) {
                        $updateStmt = $pdo->prepare("UPDATE registration SET password = ? WHERE id = ?");
                        $updateStmt->execute([$rehash, $user['id']]);
                    }
                }
            } else {
                $isPasswordValid = hash_equals($storedPassword, $password);
                $needsLegacyMigration = $isPasswordValid;
            }

            if ($isPasswordValid) {
                if ($needsLegacyMigration) {
                    $migratedHash = password_hash($password, PASSWORD_DEFAULT);
                    if ($migratedHash === false) {
                        throw new RuntimeException('Unable to migrate legacy password.');
                    }

                    $updateStmt = $pdo->prepare("UPDATE registration SET password = ? WHERE id = ?");
                    $updateStmt->execute([$migratedHash, $user['id']]);
                }

                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id']; 
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['username'] = $user['username'] ?? '';

                header("Location: ../HTML/Home.php"); 
                exit();
            } else {
                header("Location: ../HTML/LogIn.php?error=wrongpassword");
                exit();
            }
        } else {
            header("Location: ../HTML/LogIn.php?error=usernotfound");
            exit();
        }

    } catch (PDOException $e) {
        error_log("Login failed: " . $e->getMessage());
        header("Location: ../HTML/LogIn.php?error=servererror");
        exit();
    } catch (RuntimeException $e) {
        error_log("Login failed: " . $e->getMessage());
        header("Location: ../HTML/LogIn.php?error=servererror");
        exit();
    }
} else {
    header("Location: ../HTML/LogIn.php");
    exit();
}

