<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

$id = $username = $email = $role = "";
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch();
    if ($user) {
        $id = $user['id'];
        $username = $user['username'];
        $email = $user['email'];
        $role = $user['role'];
    }
}

$title = $id ? 'Edit User' : 'Add User';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body class="page">

    <?= include_once __DIR__ . '/../includes/navbar.php' ?>

    <div class="container mt-5">

        <h2>
            <?= $title ?>
        </h2>

        <form action="save.php" method="POST">

            <input type="hidden" name="id" value="<?= $id ?? '' ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= $username ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= $email ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <?= $id ? "(Leave blank to keep unchanged)" : "" ?></label>
                <input type="password" name="password" class="form-control" <?= $id ? "" : "required" ?>>
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="admin" <?= $role === "admin" ? "selected" : "" ?>>Admin</option>
                    <option value="user" <?= $role === "user" ? "selected" : "" ?>>User</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Save</button>
        </form>
    </div>
</body>

</html>