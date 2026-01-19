<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    try {
        require_once "dbh.inc.php";

        $query = "SELECT * FROM registration WHERE email = ?;";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if password matches
            if ($password === $user['password']) {  
                session_start();
                $_SESSION['user_id'] = $user['id']; 
                $_SESSION['user_email'] = $user['email'];

                header("Location: ../HTML/Home.php"); 
                exit();
            } else {
                header("Location: ../HTML/LogIn.php?error=wrongpassword");
                exit();
            }
        } else {
            // User not found
            header("Location: ../HTML/LogIn.php?error=usernotfound");
            exit();
        }

    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    header("Location: ../HTML/LogIn.php");
    exit();
}

