<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';


// Function to validate form data
function validateUserData(array $data, bool $isUpdate = false): ValidationHelper
{
    $validator = new ValidationHelper($data);

    $validator
        ->required('username', 'Username is required')
        ->minLength('username', 3, 'Username must be at least 3 characters')
        ->maxLength('username', 50, 'Username must not exceed 50 characters')
        ->regex('username', '/^[a-zA-Z0-9_]+$/', 'Username can only contain letters, numbers, and underscores')
        ->required('email', 'Email is required')
        ->email('email', 'Please enter a valid email address')
        ->maxLength('email', 255, 'Email must not exceed 255 characters')
        ->required('role', 'Role is required')
        ->in('role', ['admin', 'user'], 'Invalid role selected');

    if (!$isUpdate) { // Only validate password for new users
        $validator
            ->required('password', 'Password is required')
            ->minLength('password', 8, 'Password must be at least 8 characters');
        //->regex('password', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', 'Password must contain at least one uppercase letter, one lowercase letter, and one number');
    }

    return $validator;
}

// Function to check for duplicate username or email
function checkDuplicates(PDO $pdo, string $username, string $email, int $id): bool
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $id]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        sendErrorResponse(500, 'Error checking duplicates: ' . $e->getMessage());
        exit;
    }
}

// Function to create a new user
function createUser(PDO $pdo, string $username, string $email, string $password, string $role): void
{
    try {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $email, $hashedPassword, $role]);

        $newId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$newId]);
        if (!$stmt->fetch()) {
            throw new Exception('Failed to create user');
        }

        sendSuccessResponse('User created successfully');
    } catch (Exception $e) {
        sendErrorResponse(500, 'Error creating user: ' . $e->getMessage());
        exit;
    }
}

// Function to update an existing user
function updateUser(PDO $pdo, int $id, string $username, string $email, string $role): void
{
    try {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $email, $role, $id]);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new Exception('Failed to update user');
        }

        sendSuccessResponse('User updated successfully');
    } catch (Exception $e) {
        sendErrorResponse(500, 'Error updating user: ' . $e->getMessage());
        exit;
    }
}

// Function to send a success response
function sendSuccessResponse(string $message): void
{
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
}

// Function to send an error response
function sendErrorResponse(int $code, string $message, array $errors = []): void
{
    http_response_code($code);
    $response = ['success' => false, 'message' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse(405, 'Method Not Allowed');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    sendErrorResponse(403, 'Unauthorized access');
    exit;
}

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? '');

$validator = validateUserData($_POST, $id > 0);
if (!$validator->passes()) {
    sendErrorResponse(200, 'Validation failed', $validator->getErrors());
    exit;
}

if (checkDuplicates($pdo, $username, $email, $id)) {
    sendErrorResponse(200, 'Username or email already exists');
    exit;
}

if ($id) {
    updateUser($pdo, $id, $username, $email, $role);
} else {
    createUser($pdo, $username, $email, $password, $role);
}
