<?php
// campaign_management.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';
require_once __DIR__ . '/../config/constants.php';

// Check if user is logged in and has access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'user') {
    header("Location:" . BASE_URL . "../index.php");
    exit;
}

// Add at the beginning after includes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    /*
    //die('inside post method');
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
        */

    //Handle create new campaign request
    if (isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
        try {

            $validator = new ValidationHelper($_POST);
            $validator
                ->required('campaign_name', 'Campaign name is required')
                ->minLength('campaign_name', 4)
                ->maxLength('campaign_name', 255)
                ->required('base_url')
                ->url('base_url')
                ->minLength('base_url', 11)
                ->maxLength('base_url', 255)
                ->required('verification_frequency')
                ->in('verification_frequency', array_keys($campaign_frequency));

            if (!$validator->passes()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Campaign was not created.',
                    'errors' => $validator->getErrors(),
                ]);
                exit;
            }
            $campaign_name = trim($_POST['campaign_name']);
            $base_url = trim($_POST['base_url']);
            $verification_frequency = trim($_POST['verification_frequency']);
            $user_id = $_SESSION['user_id'];

            //check if campaign is already available with the same base URL
            $stmt = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM campaigns WHERE base_url = ?) as campaign_exists");
            $stmt->execute([$base_url]);
            $exists = $stmt->fetchColumn();
            if ($exists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You already have a campaign with the provided Base URL.',

                ]);
                exit;
            }



            $stmt = $pdo->prepare("INSERT INTO campaigns (user_id, `name`, base_url, verification_frequency, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $campaign_name, $base_url, $verification_frequency]);

            // Fetch the newly created campaign
            $campaign_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
            $stmt->execute([$campaign_id]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Campaign created successfully',
                'campaign' => $campaign
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error creating campaign: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // Handle update campaign functionality
    if (isset($_POST['action']) && $_POST['action'] === 'update_campaign') {
        try {

            $validator = new ValidationHelper($_POST);
            $validator
                ->required('campaign_name', 'Campaign name is required')
                ->minLength('campaign_name', 4)
                ->maxLength('campaign_name', 255)
                ->required('verification_frequency')
                ->in('verification_frequency', array_keys($campaign_frequency));

            if (!$validator->passes()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Campaign was not updated.',
                    'errors' => $validator->getErrors(),
                ]);
                exit;
            }

            $campaign_id = intval($_POST['campaign_id']);
            $campaign_name = trim($_POST['campaign_name']);
            $verification_frequency = trim($_POST['verification_frequency']);

            // Check if campaign exists and user has permission
            if ($_SESSION['role'] === 'admin' || checkCampaignOwnership($campaign_id, $_SESSION['user_id'])) {
                // Validate inputs
                if (empty($campaign_name) || empty($verification_frequency)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Campaign name and verification frequency are required'
                    ]);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE campaigns SET `name` = ?, verification_frequency = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$campaign_name, $verification_frequency, $campaign_id]);

                // Fetch updated campaign data
                $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
                $stmt->execute([$campaign_id]);
                $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($campaign) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Campaign updated successfully',
                        'campaign' => $campaign
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Campaign not found after update'
                    ]);
                }
                exit;
            } else {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error updating campaign: ' . $e->getMessage()
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error updating campaign: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    //handle delete campaign request
    if (isset($_POST['action']) && $_POST['action'] === 'delete_campaign') {
        try {
            $campaign_id = intval($_POST['campaign_id']);

            // Check ownership
            if ($_SESSION['role'] !== 'admin') {

                //checkOwnership use function

                $ownership = checkCampaignOwnership($campaign_id, $_SESSION['user_id']);

                if (empty($ownership)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'You do not have permission to delete this campaign'
                    ]);
                    exit;
                }
            }

            // Delete the campaign
            $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
            $stmt->execute([$campaign_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting campaign: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

// Function to check campaign ownership
function checkCampaignOwnership($campaign_id, $user_id)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campaign_id, $user_id]);
        return $stmt->fetch() ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}
