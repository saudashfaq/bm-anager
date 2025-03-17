<?php
// At the top of get_campaign_data.php after the includes
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$campaign_id = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['campaign_id'] : $_GET['campaign_id'];

if (!$campaign_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Campaign ID required']);
    exit;
}

// Fetch campaign details
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    http_response_code(404);
    echo json_encode(['error' => 'Campaign not found']);
    exit('Campaign not found');
}

// Fetch related backlinks
$stmt = $pdo->prepare("SELECT b.*, c.name AS campaign_name, u.username AS created_by_username 
    FROM backlinks b 
    JOIN campaigns c ON b.campaign_id = c.id
    JOIN users u ON b.created_by = u.id
    WHERE b.campaign_id = ?
    ORDER BY b.created_at DESC");
$stmt->execute([$campaign_id]);
$backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$totalBacklinks = count($backlinks);
$activeBacklinks = count(array_filter($backlinks, fn($bl) => $bl['status'] === 'alive'));
$deadBacklinks = $totalBacklinks - $activeBacklinks;

// Return JSON response
$response = [
    'campaign' => $campaign,
    'stats' => [
        'total' => $totalBacklinks,
        'active' => $activeBacklinks,
        'dead' => $deadBacklinks
    ],
    'backlinks' => $backlinks
];

// For debugging
//error_log('Campaign Data: ' . print_r($response, true));

// Return json data
// Additional security headers
//header("X-CSRF-TOKEN: " . $_SESSION['csrf_token']);

//var_dump($http_response_header);
echo json_encode($response);
exit;
