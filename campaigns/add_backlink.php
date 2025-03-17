<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // print_r([$_POST['backlink_url'], $_POST['campaign_base_url']]);
    // die();
    $validator = new ValidationHelper($_POST);
    $validator
        ->required('campaign_id', 'Refresh the page and try again.')
        ->integer('campaign_id', 'Refresh the page and try again.')
        ->numeric('campaign_id', 'Refresh the page and try again.')
        ->required('target_url', 'Target URL is required, where you hosted your website link.')
        ->url('target_url', 'Target URL format is incorrect')
        ->required('backlink_url', 'Backlink URL is required.')
        ->url('backlink_url', 'Backlink URL format is incorrect')
        ->required('campaign_base_url', 'Refresh the page and try again in some time.')
        ->url('campaign_base_url', 'Refresh the page and try again.')
        ->matchesBaseUrl('backlink_url', 'campaign_base_url', 'The backlink URL must match the base URL of the campaign.');

    if (!$validator->passes()) {
        $errors = $validator->getErrors();
        echo json_encode(['success' => false, 'message' => "Backlink was not created.", 'errors' => $errors]);
        exit;
    }

    $campaign_id = $_POST['campaign_id'];
    $target_url = $_POST['target_url'];
    $backlink_url = $_POST['backlink_url'];
    $anchor_text = $validator->sanitize('anchor_text'); //$_POST['anchor_text'];
    $created_by = $_SESSION['user_id'];

    // Verify if the backlink_url matches the campaign's base_url pattern
    $stmt = $pdo->prepare("SELECT base_url FROM campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign || !str_starts_with($backlink_url, $campaign['base_url'])) {
        echo json_encode(['success' => false, 'message' => "The backlink URL must match the campaign's base URL pattern."]);
        exit;
    }

    try {

        $stmt = $pdo->prepare("INSERT INTO backlinks (campaign_id, target_url, backlink_url, anchor_text, created_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$campaign_id, $target_url, $backlink_url, $anchor_text, $created_by]);

        $backlinksQuery = "SELECT * FROM backlinks WHERE campaign_id = ?";
        $backlinksStmt = $pdo->prepare($backlinksQuery);
        $backlinksStmt->execute([$campaign_id]);
        $backlinks = $backlinksStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true,  'message' => 'Backlink added successfully.', 'backlinks' => $backlinks]);
        //echo json_encode(['success' => true, 'message' => 'Backlink added successfully.']);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'We are experience a serivce outage. Please try again in a while or report to the support.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
