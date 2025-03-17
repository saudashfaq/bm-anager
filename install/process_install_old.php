<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect installation data
    $purchase_key = $_POST['purchase_key'];
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $site_url = $_POST['site_url'];
    $admin_user = $_POST['admin_user'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT);

    try {
        // Connect to MySQL
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
        $pdo->exec("USE `$db_name`");

        // Create Tables
        $sql = file_get_contents("schema.sql");
        $pdo->exec($sql);

        // Insert Admin User
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$admin_user, $admin_email, $admin_pass]);

        // Insert Settings
        $stmt = $pdo->prepare("INSERT INTO settings (purchase_key, site_url, verification_interval) VALUES (?, ?, 24)");
        $stmt->execute([$purchase_key, $site_url]);

        // Generate Config File
        $configContent = "<?php
        define('DB_HOST', '$db_host');
        define('DB_NAME', '$db_name');
        define('DB_USER', '$db_user');
        define('DB_PASS', '$db_pass');
        ?>";
        file_put_contents("../config.php", $configContent);

        // Redirect to login page
        header("Location: /../login.php");
        exit;
    } catch (Exception $e) {
        die("Installation failed: " . $e->getMessage());
    }
} else {
    die("Invalid Request");
}
