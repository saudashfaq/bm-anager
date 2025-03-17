<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // For GET requests, validate token from URL
    if (!verifyCSRFToken($_GET['csrf_token'])) {
        die("CSRF validation failed");
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For POST requests, validate token from POST data
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF validation failed");
    }
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM backlinks WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header('Location: backlink_management.php');
exit;
