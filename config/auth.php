<?php

require_once __DIR__ . '/session_manager.php';
require_once __DIR__ . '/config.php';

$request_uri = filter_var(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), FILTER_SANITIZE_URL);

if (preg_match("/login\.php$/", $request_uri)) {
    if (!empty($_SESSION['user_id'])) {
        header("Location:" . BASE_URL . "dashboard.php");
        exit;
    }
} else {
    //some other URL
    if (empty($_SESSION['user_id'])) {
        header("Location:" . BASE_URL . "login.php");
        exit;
    }
}
