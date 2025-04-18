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

// Get all backlinks for this campaign
$stmt = $pdo->prepare("
    SELECT * FROM backlinks WHERE campaign_id = ? ORDER BY created_at DESC
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
fputcsv($output, ['Created At', $campaign['created_at']]);
fputcsv($output, ['Last Updated', $campaign['updated_at']]);
fputcsv($output, ['']);
fputcsv($output, ['BACKLINKS DATA']);
fputcsv($output, ['']);

// Add backlinks data headers
fputcsv($output, [
    'Source URL',
    'Target URL',
    'Anchor Text',
    'Status',
    'Follow Status',
    'Domain Authority',
    'Page Authority',
    'Last Verified',
    'Created At',
    'Notes'
]);

// Add backlinks data
foreach ($backlinks as $backlink) {
    fputcsv($output, [
        $backlink['source_url'],
        $backlink['target_url'],
        $backlink['anchor_text'],
        ucfirst($backlink['status']),
        $backlink['follow_status'],
        $backlink['domain_authority'],
        $backlink['page_authority'],
        $backlink['last_verified'],
        $backlink['created_at'],
        $backlink['notes']
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
