<?php
// At the top of get_campaign_data.php after the includes
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/Pagination.php';

header('Content-Type: application/json');

/**
 * Get the campaign ID from the request (POST or GET).
 *
 * @return int|null The campaign ID, or null if not provided
 */
function getCampaignId()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['campaign_id'] ?? null) : ($_GET['campaign_id'] ?? null);
}

/**
 * Fetch campaign details by ID.
 *
 * @param PDO $pdo The PDO database connection
 * @param int $campaignId The campaign ID
 * @return array|null The campaign details, or null if not found
 */
function fetchCampaign($pdo, $campaignId)
{
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get the total number of backlinks for a campaign, applying filters if provided.
 *
 * @param PDO $pdo The PDO database connection
 * @param int $campaignId The campaign ID
 * @param string|null $filterType The type of filter (status,

 duplicate)
 * @param string|null $filterValue The value to filter by
 * @return int The total number of backlinks
 */
function getTotalBacklinks($pdo, $campaignId, $filterType = null, $filterValue = null)
{
    $countQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id";
    $params = ['campaign_id' => $campaignId];

    if ($filterType === 'status' && in_array($filterValue, ['alive', 'dead'])) {
        $countQuery .= " AND status = :status";
        $params['status'] = $filterValue;
    } elseif ($filterType === 'duplicate' && $filterValue === 'yes') {
        $countQuery .= " AND is_duplicate = :is_duplicate";
        $params['is_duplicate'] = 'yes';
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    return (int)$countStmt->fetchColumn();
}

/**
 * Fetch backlinks for a campaign, with optional pagination and filters.
 *
 * @param PDO $pdo The PDO database connection
 * @param int $campaignId The campaign ID
 * @param int|null $page The page number for pagination (null for no pagination)
 * @param int $itemsPerPage Number of items per page
 * @param Pagination $pagination The Pagination object for calculating offset
 * @param string|null $filterType The type of filter (status, duplicate)
 * @param string|null $filterValue The value to filter by
 * @return array The list of backlinks
 */
function fetchBacklinks($pdo, $campaignId, $page, $itemsPerPage, $pagination, $filterType = null, $filterValue = null)
{
    $backlinksQuery = "SELECT b.*, c.name AS campaign_name, u.username AS created_by_username 
        FROM backlinks b 
        JOIN campaigns c ON b.campaign_id = c.id
        JOIN users u ON b.created_by = u.id
        WHERE b.campaign_id = :campaign_id";
    $params = ['campaign_id' => $campaignId];

    if ($filterType === 'status' && in_array($filterValue, ['alive', 'dead'])) {
        $backlinksQuery .= " AND b.status = :status";
        $params['status'] = $filterValue;
    } elseif ($filterType === 'duplicate' && $filterValue === 'yes') {
        $backlinksQuery .= " AND b.is_duplicate = :is_duplicate";
        $params['is_duplicate'] = 'yes';
    }

    $backlinksQuery .= " ORDER BY b.created_at DESC";

    if ($page !== null) {
        // Apply pagination
        $offset = $pagination->getOffset();
        $backlinksQuery .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($backlinksQuery);
        $stmt->bindValue(':campaign_id', $campaignId, PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue(':status', $params['status'], PDO::PARAM_STR);
        }
        if (isset($params['is_duplicate'])) {
            $stmt->bindValue(':is_duplicate', $params['is_duplicate'], PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        // Fetch all backlinks (no pagination)
        $stmt = $pdo->prepare($backlinksQuery);
        $stmt->bindValue(':campaign_id', $campaignId, PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue(':status', $params['status'], PDO::PARAM_STR);
        }
        if (isset($params['is_duplicate'])) {
            $stmt->bindValue(':is_duplicate', $params['is_duplicate'], PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate backlink stats (total, active, dead, duplicate) using server-side queries.
 *
 * @param PDO $pdo The PDO database connection
 * @param int $campaignId The campaign ID
 * @return array The stats array
 */
function calculateStats($pdo, $campaignId)
{
    // Total backlinks
    $totalQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id";
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute(['campaign_id' => $campaignId]);
    $totalBacklinks = (int)$totalStmt->fetchColumn();

    // Active backlinks
    $activeQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id AND status = 'alive'";
    $activeStmt = $pdo->prepare($activeQuery);
    $activeStmt->execute(['campaign_id' => $campaignId]);
    $activeBacklinks = (int)$activeStmt->fetchColumn();

    // Dead backlinks
    $deadQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id AND status = 'dead'";
    $deadStmt = $pdo->prepare($deadQuery);
    $deadStmt->execute(['campaign_id' => $campaignId]);
    $deadBacklinks = (int)$deadStmt->fetchColumn();

    // Duplicate backlinks
    $duplicateQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id AND is_duplicate = 'yes'";
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->execute(['campaign_id' => $campaignId]);
    $duplicateBacklinks = (int)$duplicateStmt->fetchColumn();

    return [
        'total' => $totalBacklinks,
        'active' => $activeBacklinks,
        'dead' => $deadBacklinks,
        'duplicate' => $duplicateBacklinks
    ];
}

/**
 * Main logic to handle the request and return the response.
 */
function handleRequest($pdo)
{
    // Get campaign ID
    $campaignId = getCampaignId();
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        exit;
    }

    // Fetch campaign details
    $campaign = fetchCampaign($pdo, $campaignId);
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        exit('Campaign not found');
    }

    // Get filter parameters
    $filterType = isset($_POST['filter_type']) ? $_POST['filter_type'] : null;
    $filterValue = isset($_POST['filter_value']) ? $_POST['filter_value'] : null;

    // Pagination setup (optional)
    $itemsPerPage = 10;
    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : null;

    // Get total number of backlinks (with filters)
    $totalBacklinks = getTotalBacklinks($pdo, $campaignId, $filterType, $filterValue);

    // Initialize Pagination class
    $pagination = new Pagination($totalBacklinks, $itemsPerPage, $page ?? 1, '');

    // Fetch backlinks (with pagination and filters if provided)
    $backlinks = fetchBacklinks($pdo, $campaignId, $page, $itemsPerPage, $pagination, $filterType, $filterValue);

    // Calculate stats (using server-side queries)
    $stats = calculateStats($pdo, $campaignId);

    // Build response
    $response = [
        'campaign' => $campaign,
        'stats' => $stats,
        'backlinks' => $backlinks,
        'pagination' => $page !== null ? $pagination->render() : '' // Include pagination HTML only when paginating
    ];

    // For debugging
    //error_log('Campaign Data: ' . print_r($response, true));

    // Return json data
    // Additional security headers
    //header("X-CSRF-TOKEN: " . $_SESSION['csrf_token']);

    //var_dump($http_response_header);
    echo json_encode($response);
    exit;
}

// Execute the main logic
handleRequest($pdo);
