<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: LogIn.php");
    exit();
}

if (!($result && $result['is_admin'] == 1)) {
    header("Location: Profile.php");
}

$user_id = $_SESSION['user_id'];
$query = "SELECT is_admin FROM registration WHERE id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || $result['is_admin'] != 1) {
    header("Location: Main.php");
    exit();
}

// Handle delete, ban, make admin, or remove admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $delete_id = $_POST['delete_id'];
        $delete_query = "DELETE FROM registration WHERE id = :id";
        $stmt = $pdo->prepare($delete_query);
        $stmt->execute(['id' => $delete_id]);
    } elseif (isset($_POST['ban_user'])) {
        $ban_id = $_POST['ban_id'];
        $ban_query = "UPDATE registration SET is_admin = 0, banned = 1 WHERE id = :id";
        $stmt = $pdo->prepare($ban_query);
        $stmt->execute(['id' => $ban_id]);
    } elseif (isset($_POST['make_admin'])) {
        $admin_id = $_POST['admin_id'];
        $admin_query = "UPDATE registration SET is_admin = 1 WHERE id = :id";
        $stmt = $pdo->prepare($admin_query);
        $stmt->execute(['id' => $admin_id]);
    } elseif (isset($_POST['remove_admin'])) {
        $remove_id = $_POST['remove_id'];
        $remove_query = "UPDATE registration SET is_admin = 0 WHERE id = :id";
        $stmt = $pdo->prepare($remove_query);
        $stmt->execute(['id' => $remove_id]);
    }
}

// Fetch all users
$users_query = "SELECT id, username, email, is_admin, banned FROM registration";
$stmt = $pdo->query($users_query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);