<?php

session_start();

require_once __DIR__ . '/config/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location:" . BASE_URL . "dashboard.php");
    exit;
}
