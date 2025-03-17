<?php
require_once __DIR__ . '/middleware.php';

//die('on logout page');
if (!empty($_SESSION)) {
    unset($_SESSION);
}
session_destroy();
header("Location:" . BASE_URL . "login.php");

exit;
