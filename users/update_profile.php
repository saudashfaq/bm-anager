<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';


// Function to validate form data
function validateProfileData(array $data): ValidationHelper
{
    $validator = new ValidationHelper($data);

    $validator
        ->required('username', 'Username is required')
        ->minLength('username', 3, 'Username must be at least 3 characters')
        ->maxLength('username', 50, 'Username must not exceed 50 characters')
        ->regex('username', '/^[a-zA-Z0-9_]+$/', 'Username can only contain letters, numbers, and underscores')
        ->required('email', 'Email is required')
        ->email('email', 'Please enter a valid email address')
        ->maxLength('email', 255, 'Email must not exceed 255 characters');

    return $validator;
}

// Function to check for duplicate username or email
function checkDuplicates(PDO $pdo, string $username, string $email, int $userId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        sendErrorResponse(500, 'Error checking duplicates: ' . $e->getMessage());
        exit;
    }
}

// Function to update the user's profile
function updateProfile(PDO $pdo, int $userId, string $username, string $email): void
{
    try {

        if (empty($_SESSION['user_id']) || $_SESSION['user_id'] !== $userId) {

            throw new Exception('Unauthorised attempt to update profile');
        }

        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->execute([$username, $email, $userId]);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            throw new Exception('Failed to update profile');
        }

        $_SESSION['username'] = $username;
        sendSuccessResponse('Profile updated successfully');
    } catch (Exception $e) {
        sendErrorResponse(500, 'Error updating profile: ' . $e->getMessage());
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


$userId = (int)$_SESSION['user_id'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validate that the user exists and is an admin
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    sendErrorResponse(403, 'Unauthorized access');
    exit;
}

$validator = validateProfileData($_POST);
if (!$validator->passes()) {
    sendErrorResponse(200, 'Validation failed', $validator->getErrors());
    exit;
}

if (checkDuplicates($pdo, $username, $email, $userId)) {
    sendErrorResponse(400, 'Username or email already exists');
    exit;
}

updateProfile($pdo, $userId, $username, $email);
