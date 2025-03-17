<?php
require_once __DIR__ . '/middleware.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Dashboard - Backlink Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
    <div class="page">
        <?php include_once __DIR__ . '/includes/navbar.php'; ?>
        <div class="container mt-4">
            <h2>Dashboard</h2>
            <p>Welcome to the Backlink Management System.</p>
        </div>
    </div>
</body>

</html>