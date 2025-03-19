<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Add backlink
    $validator = new ValidationHelper($_POST);
    $validator
        ->required('campaign_id', 'Refresh the page and try again.')
        ->integer('campaign_id', 'Refresh the page and try again.')
        ->numeric('campaign_id', 'Refresh the page and try again.')
        ->url('target_url', 'Target URL format is incorrect') //Your website link if provided 
        ->required('backlink_url', 'Backlink URL of the site that contains refrence link of your website')
        ->url('backlink_url', 'Backlink URL format is incorrect')
        ->required('campaign_base_url', 'Refresh the page and try again in some time.')
        //->url('campaign_base_url', 'Refresh the page and try again.')
        //->matchesBaseUrl('target_url', 'campaign_base_url')
        //if target URL (your website link of any post/page) is empty we will verify the base URL of your website
    ;

    if (!$validator->passes()) {
        $errors = $validator->getErrors();
        echo json_encode(['success' => false, 'message' => "Backlink was not created.", 'errors' => $errors]);
        exit;
    }

    $campaign_id = $_POST['campaign_id'];
    $target_url = $_POST['target_url']; //campaign website link of any post
    $backlink_url = $_POST['backlink_url']; //other website link that contains the campaign url
    $anchor_text = $validator->sanitize('anchor_text');
    $created_by = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT base_url FROM campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign || (!empty($target_url) && !str_starts_with($target_url, $campaign['base_url']))) {
        echo json_encode(['success' => false, 'message' => "Your website link URL must match the campaign's base URL pattern."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO backlinks (campaign_id, target_url, backlink_url, anchor_text, created_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$campaign_id, $target_url, $backlink_url, $anchor_text, $created_by]);

        $backlinksQuery = "SELECT * FROM backlinks WHERE campaign_id = ?";
        $backlinksStmt = $pdo->prepare($backlinksQuery);
        $backlinksStmt->execute([$campaign_id]);
        $backlinks = $backlinksStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'message' => 'Backlink added successfully.', 'backlinks' => $backlinks]);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'We are experiencing a service outage. Please try again later or report to support.']);
        exit;
    }
} elseif ($method === 'DELETE') {
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    // Validate input
    $validator = new ValidationHelper($input);
    $validator
        //->required('ids', 'No backlinks selected for deletion')
        //->array('ids', 'Invalid backlink selection format')
        ->required('csrf_token', 'CSRF token is missing');

    if (!$validator->passes()) {
        $errors = $validator->getErrors();
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    // Verify CSRF token
    if ($input['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
        exit;
    }

    $ids = $input['ids'];
    if (!is_array($ids)) {
        $ids = [$ids];
    }

    // Validate each ID
    foreach ($ids as $id) {
        if (!is_numeric($id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid backlink ID: ' . $id]);
            exit;
        }
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM backlinks WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        echo json_encode(['success' => true, 'message' => 'Backlink(s) deleted successfully']);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete backlink(s): ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
