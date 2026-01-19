<?php
session_start();
include_once "../JS/headerFooter.php";
include_once "../includes/AdminPanel.inc.php";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Fitspiration</title>
    <link rel="stylesheet" href="../CSS/Main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>
<body>
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <div class="main-content">
            <h2>Admin Panel</h2>
            <h3>Manage Users</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Admin</th>
                        <th>Banned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo $user['is_admin'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $user['banned'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="admin-btn delete-btn" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="ban_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="ban_user" class="admin-btn ban-btn" onclick="return confirm('Are you sure you want to ban this user?');">Ban</button>
                            </form>
                            <?php if (!$user['is_admin']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="admin_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="make_admin" class="admin-btn make-admin-btn" onclick="return confirm('Are you sure you want to make this user an admin?');">Make Admin</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="remove_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="remove_admin" class="admin-btn remove-admin-btn" onclick="return confirm('Are you sure you want to remove admin privileges from this user?');">Remove Admin</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <special-footer></special-footer>
</body>
</html>

<style>
.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    margin-left: 10px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.admin-table th,
.admin-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.admin-table th {
    background: #007bff;
    color: white;
    font-weight: 600;
}

.admin-table td {
    color: #333;
}

.admin-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-right: 10px;
}

.delete-btn {
    background: #dc3545;
    color: white;
}

.delete-btn:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
}

.ban-btn {
    background: #28a745;
    color: white;
}

.ban-btn:hover {
    background: #218838;
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
}

.make-admin-btn {
    background: #ffc107;
    color: #333;
}

.make-admin-btn:hover {
    background: #e0a800;
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2);
}

.remove-admin-btn {
    background: #6c757d;
    color: white;
}

.remove-admin-btn:hover {
    background: #5a6268;
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
}
</style>