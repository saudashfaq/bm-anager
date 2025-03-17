<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function installDatabase($dbHost, $dbUser, $dbPass, $dbName, $siteUrl, $baseURL)
{
    try {

        /*
         * Everything works like this
         * $pdo = new PDO("mysql:host=" . '127.0.0.1' . ";dbname=" . 'u968063071_bmanager', 'u968063071_bmanager', 'C:16/JU$b9', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            if ($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) {
                echo "Connected successfully!";
            }
         * 
         * 
         */

        global $pdo;
        $pdo = new PDO("mysql:host=" . $dbHost . ";dbname=" . $dbName, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
        $pdo->exec("USE `{$dbName}`");

        // Create tables
        $sql = file_get_contents("schema.sql");
        $pdo->exec($sql);

        // Store configuration
        $config_content = "<?php
        define('DB_HOST', '{$dbHost}');
        define('DB_USER', '{$dbUser}');
        define('DB_PASS', '{$dbPass}');
        define('DB_NAME', '{$dbName}');
        define('SITE_URL', '{$siteUrl}');
        define('BASE_URL', '{$siteUrl}/{$baseURL}/');
        
        ?>";

        file_put_contents(__DIR__ . '/../config/config.php', $config_content);

        //return $pdo; // Return the PDO instance for further use
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception("Something went wrong: " . $e->getMessage());
    }
}

function createAdminUser($pdo, $username, $email, $password)
{
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'admin')");
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
    ]);
}

// Skip the installation check if we're coming from confirm_reinstall.php
if (empty($_SESSION['skip_install_check'])) {
    require_once __DIR__ . '/../config/config.php';

    // Store form data in session first
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['form_data'] = $_POST;

        // Store admin credentials in session
        if (isset($_POST['admin_username'], $_POST['admin_email'], $_POST['admin_password'])) {
            $_SESSION['admin_username'] = $_POST['admin_username'];
            $_SESSION['admin_email'] = $_POST['admin_email'];
            $_SESSION['admin_password'] = $_POST['admin_password'];
        }

        // Debugging: Check session values
        error_log("Admin Username: " . $_SESSION['admin_username']);
        error_log("Admin Email: " . $_SESSION['admin_email']);
        error_log("Admin Password: " . $_SESSION['admin_password']);
    }

    // Check if the system is already installed
    if (file_exists('../config.php') && defined('DB_HOST')) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
            $tables = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "`")->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tables)) {
                $_SESSION['warning'] = "The database already contains tables. This will override the existing schema. Do you want to proceed?";
                $_SESSION['reinstall'] = true;
                header("Location: confirm_reinstall.php");
                exit;
            }
        } catch (PDOException $e) {
            // Attempt to use the newly provided values from the form submission
            if (isset($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'])) {
                installDatabase($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['site_url'],  $_POST['base_url']);

                // Use stored admin credentials from session
                createAdminUser($pdo, $_SESSION['admin_username'], $_SESSION['admin_email'], $_SESSION['admin_password']);

                // Installation successful
                $_SESSION['success'] = "Installation completed successfully! Please log in.";
                unset($_SESSION['form_data']); // Clear stored form data
                header("Location: ../login.php");
                exit;
            } else {
                $_SESSION['error'] = "Database connection failed: " . $e->getMessage();
                header("Location: index.php");
                exit;
            }
        }
    }
}

// Clear the skip check flag
unset($_SESSION['skip_install_check']);

// Continue with the installation process
try {
    installDatabase($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['site_url'], $_POST['base_url']);

    // Create admin user with form data
    if (!empty($_POST['admin_username']) && !empty($_POST['admin_email']) && !empty($_POST['admin_password'])) {
        createAdminUser(
            $pdo,
            $_POST['admin_username'],
            $_POST['admin_email'],
            $_POST['admin_password']
        );
    } else {
        $_SESSION['error'] = "Admin credentials are missing.";
        header("Location: index.php");
        exit;
    }

    // Installation successful
    $_SESSION['success'] = "Installation completed successfully! Please log in.";
    header("Location: ../login.php");
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = "Installation failed: " . $e->getMessage();
    header("Location: index.php");
    exit;
}
