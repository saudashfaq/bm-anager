<?php
session_start();

// Define constants for file paths
const CONFIG_FILE = __DIR__ . '/../config/config.php';
const PROXIES_FILE = __DIR__ . '/../config/proxies.json';
const SCHEMA_FILE = __DIR__ . '/schema.sql';
const LOGIN_URL = '../login.php';
const INDEX_URL = 'index.php';
const CONFIRM_REINSTALL_URL = 'confirm_reinstall.php';

// Utility function to redirect with a message
function redirectWithMessage($url, $message, $type = 'error')
{
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

// Validate and sanitize input data
function validateInput($data)
{
    $data = trim($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Normalize site_url and base_url
function normalizeUrls($siteUrl, $baseUrl)
{
    // Normalize site_url: Remove trailing slash
    $siteUrl = rtrim($siteUrl, '/');

    // Normalize base_url: Ensure it starts and ends with exactly one slash
    $baseUrl = trim($baseUrl, '/'); // Remove leading/trailing slashes first
    $baseUrl = $baseUrl ? "/$baseUrl/" : '/'; // Add leading and trailing slashes, default to '/' if empty

    // Combine to form BASE_URL
    $fullBaseUrl = rtrim($siteUrl . $baseUrl, '/'); // Ensure no double slashes at the junction

    return [$siteUrl, $fullBaseUrl];
}

// Check if the application is already installed
function isAppInstalled()
{
    if (!file_exists(CONFIG_FILE)) {
        return false;
    }

    require_once CONFIG_FILE;
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        return false;
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $tables = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "`")->fetchAll(PDO::FETCH_COLUMN);
        return !empty($tables);
    } catch (PDOException $e) {
        return false;
    }
}

// Install the database and create tables
function installDatabase($dbHost, $dbUser, $dbPass, $dbName, $siteUrl, $baseUrl)
{
    try {
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
        $pdo->exec("USE `$dbName`");

        // Drop existing tables to avoid conflicts
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Create tables from schema.sql
        if (!file_exists(SCHEMA_FILE)) {
            throw new Exception("Schema file (schema.sql) not found.");
        }
        $sql = file_get_contents(SCHEMA_FILE);
        $pdo->exec($sql);

        // Normalize URLs before writing to config.php
        [$normalizedSiteUrl, $normalizedBaseUrl] = normalizeUrls($siteUrl, $baseUrl);

        // Write configuration to config.php
        $configContent = "<?php\n";
        $configContent .= "define('DB_HOST', '$dbHost');\n";
        $configContent .= "define('DB_USER', '$dbUser');\n";
        $configContent .= "define('DB_PASS', '$dbPass');\n";
        $configContent .= "define('DB_NAME', '$dbName');\n";
        $configContent .= "define('SITE_URL', '$normalizedSiteUrl');\n";
        $configContent .= "define('BASE_URL', '$normalizedBaseUrl');\n";
        file_put_contents(CONFIG_FILE, $configContent);

        // Clear proxies.json if it exists
        if (file_exists(PROXIES_FILE)) {
            file_put_contents(PROXIES_FILE, '');
        }

        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception("Something went wrong: " . $e->getMessage());
    }
}

// Create or update the admin user
function createOrUpdateAdminUser($pdo, $username, $email, $password)
{
    try {
        // Check if the admin user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($existingUser) {
            // Update the existing admin user
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = 'admin' WHERE id = ?");
            $stmt->execute([$username, $email, $hashedPassword, $existingUser['id']]);
        } else {
            // Create a new admin user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$username, $email, $hashedPassword]);
        }
    } catch (PDOException $e) {
        throw new Exception("Failed to create/update admin user: " . $e->getMessage());
    }
}

// Main installation logic
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage(INDEX_URL, "Invalid request method.");
}

// Validate and sanitize inputs
$requiredFields = ['purchase_key', 'db_host', 'db_name', 'db_user', 'db_pass', 'site_url', 'base_url', 'admin_username', 'admin_email', 'admin_password'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field])) {
        redirectWithMessage(INDEX_URL, "Missing required field: $field.");

        //validating site url
        if ($field === 'site_url' && !filter_var($_POST[$field], FILTER_VALIDATE_URL)) {
            redirectWithMessage(INDEX_URL, "Please provide a valid Site URL");
        }
    }
    $_POST[$field] = validateInput($_POST[$field]);
}

extract($_POST);

// Check if the application is already installed and confirmation is required
if (isAppInstalled() && empty($_SESSION['skip_install_check'])) {
    $_SESSION['form_data'] = $_POST;
    $_SESSION['reinstall'] = true;
    $_SESSION['warning'] = "The application is already installed. Proceeding will overwrite the existing database. Do you want to continue?";
    header("Location: " . CONFIRM_REINSTALL_URL);
    exit;
}

// Clear the skip check flag
unset($_SESSION['skip_install_check']);

// Proceed with installation
try {
    $pdo = installDatabase($db_host, $db_user, $db_pass, $db_name, $site_url, $base_url);
    createOrUpdateAdminUser($pdo, $admin_username, $admin_email, $admin_password);

    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['reinstall']);

    redirectWithMessage(LOGIN_URL, "Installation completed successfully! Please log in.", 'success');
} catch (Exception $e) {
    redirectWithMessage(INDEX_URL, "Installation failed: " . $e->getMessage());
}
