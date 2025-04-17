<?php

session_start();
require_once __DIR__ . '/config/auth.php';

// Check if BASE_URL is defined, if not redirect to installation page
if (!defined('BASE_URL')) {
    header("Location: install/index.php");
    exit;
}


if (isset($_SESSION['user_id'])) {
    header("Location:" . BASE_URL . "dashboard.php");
    exit;
}
