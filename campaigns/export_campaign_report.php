<?php
// export_campaign_report.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// Check if campaign_id is provided
if (!isset($_GET['campaign_id']) || !is_numeric($_GET['campaign_id'])) {
    die('Invalid campaign ID');
}

$campaign_id = (int)$_GET['campaign_id'];

// Get campaign details
$stmt = $pdo->prepare("
    SELECT * FROM campaigns WHERE id = ?
");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    die('Campaign not found');
}

// Get all backlinks for this campaign with creator username
$stmt = $pdo->prepare("
    SELECT b.*, u.username as created_by_username 
    FROM backlinks b
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.campaign_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$campaign_id]);
$backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . sanitize_filename($campaign['name']) . '_backlinks_report_' . date('Y-m-d') . '.csv"');

// Create a file pointer connected to PHP output
$output = fopen('php://output', 'w');

// Set UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Add campaign information as header
fputcsv($output, ['CAMPAIGN REPORT']);
fputcsv($output, ['']);
fputcsv($output, ['Campaign Name', $campaign['name']]);
fputcsv($output, ['Base URL', $campaign['base_url']]);
fputcsv($output, ['Status', ucfirst($campaign['status'])]);
fputcsv($output, ['Verification Frequency', ucfirst($campaign['verification_frequency'])]);
fputcsv($output, ['Last Checked', $campaign['last_checked'] ? date('Y-m-d H:i:s', strtotime($campaign['last_checked'])) : 'Never']);
fputcsv($output, ['Created At', date('Y-m-d H:i:s', strtotime($campaign['created_at']))]);
fputcsv($output, ['Last Updated', $campaign['updated_at'] ? date('Y-m-d H:i:s', strtotime($campaign['updated_at'])) : 'Never']);
fputcsv($output, ['']);
fputcsv($output, ['BACKLINKS DATA']);
fputcsv($output, ['']);

// Add backlinks data headers
fputcsv($output, [
    'Backlink URL',
    'Base Domain',
    'Target URL',
    'Anchor Text',
    'Status',
    'Anchor Text Found',
    'Is Duplicate',
    'Created By',
    'Last Checked',
    'Created At'
]);

// Add backlinks data
foreach ($backlinks as $backlink) {
    fputcsv($output, [
        $backlink['backlink_url'],
        $backlink['base_domain'],
        $backlink['target_url'],
        $backlink['anchor_text'],
        ucfirst($backlink['status']),
        $backlink['anchor_text_found'] ? 'Yes' : 'No',
        ucfirst($backlink['is_duplicate']),
        $backlink['created_by_username'],
        $backlink['last_checked'] ? date('Y-m-d H:i:s', strtotime($backlink['last_checked'])) : 'Never',
        date('Y-m-d H:i:s', strtotime($backlink['created_at']))
    ]);
}

// Close the file pointer
fclose($output);
exit;

/**
 * Sanitize filename to remove special characters
 */
function sanitize_filename($filename)
{
    // Replace spaces with underscores
    $filename = str_replace(' ', '_', $filename);

    // Remove any character that is not alphanumeric, underscore, or hyphen
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename);

    return $filename;
}
