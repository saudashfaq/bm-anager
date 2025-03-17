<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';


if ($_SESSION['role'] !== 'admin') {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

$id = $_POST['id'] ?? '';
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$role = $_POST['role'];

if ($id) {
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password=?, role=? WHERE id=?");
        $stmt->execute([$username, $email, $hashedPassword, $role, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
        $stmt->execute([$username, $email, $role, $id]);
    }
} else {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $role]);
}

header("Location: index.php");
exit;
