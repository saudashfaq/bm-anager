<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

// Check if user is Admin
if ($_SESSION['role'] !== 'admin') {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

$stmt = $pdo->query("SELECT id, username, email, `role`, created_at FROM users where role = 'user'");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
    <div class="page">
        <?php include '../includes/navbar.php'; ?>
        <div class="container mt-5">
            <h2>User Management</h2>
            <a href="form.php" class="btn btn-primary mb-3">Add User</a>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= ucfirst($user['role']) ?></td>
                            <td>
                                <a href="form.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>