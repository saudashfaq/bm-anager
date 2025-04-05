<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../generalfunctions/general_functions.php';

header('Content-Type: application/json');
header('X-CSRF-TOKEN: ' . $_SESSION['csrf_token']);

$response = ['success' => false, 'message' => ''];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $backlinkUtils = new BacklinkUtils($pdo);

    if ($method === 'POST') {
        // Add a new backlink
        $data = $_POST;

        if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        $requiredFields = ['campaign_id', 'backlink_url'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $campaignId = (int)$data['campaign_id'];
        $backlinkUrl = trim($data['backlink_url']);
        $targetUrl = trim($data['target_url'] ?? '');
        $anchorText = trim($data['anchor_text'] ?? '');
        $campaignBaseUrl = trim($data['campaign_base_url'] ?? '');

        // Validate URL
        if (!filter_var($backlinkUrl, FILTER_VALIDATE_URL)) {
            $response['errors'] = ['backlink_url' => ['Invalid URL format']];
            throw new Exception('Invalid backlink URL');
        }

        // Check if the campaign exists
        $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        if (!$stmt->fetch()) {
            throw new Exception('Campaign not found');
        }

        // Start a transaction
        $pdo->beginTransaction();

        // Insert the backlink using BacklinkUtils
        $backlinkUtils->insertBacklink(
            $campaignId,
            $backlinkUrl,
            $targetUrl,
            $anchorText,
            $_SESSION['user_id']
        );

        // Commit the transaction
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Backlink added successfully';
    } elseif ($method === 'DELETE') {
        // Delete backlinks
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
            throw new Exception('No backlinks selected for deletion');
        }

        $ids = array_map('intval', $input['ids']);

        // Start a transaction
        $pdo->beginTransaction();

        // Fetch the base domains and campaign IDs of the backlinks to be deleted
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, campaign_id, base_domain FROM backlinks WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $backlinksToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Delete the backlinks
        $stmt = $pdo->prepare("DELETE FROM backlinks WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        // Update duplicate status for each deleted backlink's base domain
        foreach ($backlinksToDelete as $backlink) {
            $backlinkUtils->updateDuplicateStatusAfterDelete($backlink['campaign_id'], $backlink['base_domain']);
        }

        // Commit the transaction
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Backlink(s) deleted successfully';
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
