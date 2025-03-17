<?php
session_start();
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/config/db.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    try {


        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            header("Location:" . BASE_URL . "dashboard.php");
            exit;
        } else {
            header("Location:" . BASE_URL . "login.php?error=Invalid Credentials");
            exit;
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {

    header("Location:" . BASE_URL . "login.php?error=form not posted");
    exit;
}
