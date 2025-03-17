<?php
// Include required files
require_once __DIR__ . "/config/auth.php"; // Authentication functions (if needed)
require_once __DIR__ . "/config/csrf_helper.php"; // CSRF token functions


// CSRF Protection for POST, PUT, DELETE, and AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? null;
    //print_r([$csrf_token, $_SESSION['csrf_token']]);

    if (empty($csrf_token) || !verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        header("X-CSRF-TOKEN: " . $_SESSION['csrf_token']);
        echo json_encode(["success" => false, "message" => "Forbidden: Invalid CSRF token"]);
        exit;
    }
}

generateCSRFToken();
// Additional security headers

// Continue execution (middleware should not halt requests unless necessary)
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

header("X-CSRF-TOKEN: " . $_SESSION['csrf_token']);
