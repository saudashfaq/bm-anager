<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../generalfunctions/helper_functions.php';

// Ensure campaign_id is provided and valid
if (empty($_GET['campaign_id']) || !is_numeric($_GET['campaign_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$campaignId = (int)$_GET['campaign_id'];

// Validate campaign existence
try {
    $stmt = $pdo->prepare("SELECT id, name, base_url FROM campaigns WHERE id = :campaign_id");
    $stmt->execute(['campaign_id' => $campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        header('Location:' . BASE_URL . 'campaigns/campaign_management.php?error=Campaign not found');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die('Database error occurred');
}

// Initialize variables for messages
$csvErrors = [];
$manualErrors = [];
$successMessage = '';

// Function to sanitize and validate URLs
function sanitizeUrl($url)
{
    $url = trim($url);
    if (empty($url)) return '';
    // Remove http, https, www
    $url = preg_replace('#^(https?://|www\.)#i', '', $url);
    // Sanitize the URL
    $url = filter_var($url, FILTER_SANITIZE_URL);
    return $url;
}

// Function to validate URL
function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Process CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }

    if ($_POST['campaign_id'] != $campaignId) {
        die('Campaign ID mismatch');
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'text/csv') {
        $csvFile = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($csvFile); // Skip header row

        // Validate header
        if (
            count($header) < 3 || !in_array('backlink_url', array_map('strtolower', $header)) ||
            !in_array('anchor_text', array_map('strtolower', $header)) ||
            !in_array('target_url', array_map('strtolower', $header))
        ) {
            $csvErrors[] = 'CSV must have at least 3 columns: backlink_url, anchor_text, target_url';
        } else {
            $backlinksToInsert = [];
            $rowNumber = 1;

            while (($row = fgetcsv($csvFile)) !== false) {
                $rowNumber++;
                if (count($row) < 3) {
                    $csvErrors[] = "Row $rowNumber: Insufficient columns";
                    continue;
                }

                $backlinkUrl = trim($row[0]);
                $anchorText = trim($row[1]);
                $targetUrl = trim($row[2]);

                // Validate backlink URL
                if (!isValidUrl($backlinkUrl)) {
                    $csvErrors[] = "Row $rowNumber: Invalid backlink URL: $backlinkUrl";
                    continue;
                }

                // Sanitize and validate target URL if provided
                if (!empty($targetUrl)) {
                    $sanitizedTargetUrl = sanitizeUrl($targetUrl);
                    if (!str_contains($sanitizedTargetUrl, sanitizeUrl($campaign['base_url']))) {
                        $csvErrors[] = "Row $rowNumber: Target URL must belong to the campaign base URL: " . $campaign['base_url'];
                        continue;
                    }
                    $targetUrl = $sanitizedTargetUrl;
                }

                // Sanitize anchor text
                $anchorText = htmlspecialchars($anchorText, ENT_QUOTES, 'UTF-8');

                $backlinksToInsert[] = [
                    'campaign_id' => $campaignId,
                    'backlink_url' => $backlinkUrl,
                    'anchor_text' => $anchorText,
                    'target_url' => $targetUrl,
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'pending' // Default status
                ];
            }

            fclose($csvFile);

            // Insert backlinks into the database
            if (empty($csvErrors) && !empty($backlinksToInsert)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO backlinks (campaign_id, backlink_url, anchor_text, target_url, created_by, created_at, status) VALUES (:campaign_id, :backlink_url, :anchor_text, :target_url, :created_by, :created_at, :status)");
                    foreach ($backlinksToInsert as $backlink) {
                        $stmt->execute($backlink);
                    }
                    $successMessage = "Successfully uploaded " . count($backlinksToInsert) . " backlinks.";
                } catch (PDOException $e) {
                    $csvErrors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } else {
        $csvErrors[] = "Please upload a valid CSV file.";
    }
}

// Process manual backlink entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_backlinks'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }

    if ($_POST['campaign_id'] != $campaignId) {
        die('Campaign ID mismatch');
    }

    $backlinksText = trim($_POST['manual_backlinks']);
    $backlinks = array_filter(array_map('trim', explode("\n", $backlinksText)));

    if (count($backlinks) > 10) {
        $manualErrors[] = "You can only add up to 10 backlinks at once.";
    } else {
        $backlinksToInsert = [];
        foreach ($backlinks as $index => $backlink) {
            $lineNumber = $index + 1;
            $parts = array_map('trim', explode(',', $backlink));
            if (count($parts) < 1) {
                $manualErrors[] = "Line $lineNumber: Invalid format. Please provide at least a backlink URL.";
                continue;
            }

            $backlinkUrl = $parts[0];
            $anchorText = isset($parts[1]) ? $parts[1] : '';
            $targetUrl = isset($parts[2]) ? $parts[2] : '';

            // Validate backlink URL
            if (!isValidUrl($backlinkUrl)) {
                $manualErrors[] = "Line $lineNumber: Invalid backlink URL: $backlinkUrl";
                continue;
            }

            // Sanitize and validate target URL if provided
            if (!empty($targetUrl)) {
                $sanitizedTargetUrl = sanitizeUrl($targetUrl);
                if (!str_contains($sanitizedTargetUrl, sanitizeUrl($campaign['base_url']))) {
                    $manualErrors[] = "Line $lineNumber: Target URL must belong to the campaign base URL: " . $campaign['base_url'];
                    continue;
                }
                $targetUrl = $sanitizedTargetUrl;
            }

            // Sanitize anchor text
            $anchorText = htmlspecialchars($anchorText, ENT_QUOTES, 'UTF-8');

            $backlinksToInsert[] = [
                'campaign_id' => $campaignId,
                'backlink_url' => $backlinkUrl,
                'anchor_text' => $anchorText,
                'target_url' => $targetUrl,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 'pending' // Default status
            ];
        }

        // Insert backlinks into the database
        if (empty($manualErrors) && !empty($backlinksToInsert)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO backlinks (campaign_id, backlink_url, anchor_text, target_url, created_by, created_at, status) VALUES (:campaign_id, :backlink_url, :anchor_text, :target_url, :created_by, :created_at, :status)");
                foreach ($backlinksToInsert as $backlink) {
                    $stmt->execute($backlink);
                }
                $successMessage = "Successfully added " . count($backlinksToInsert) . " backlinks.";
            } catch (PDOException $e) {
                $manualErrors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Bulk Upload Backlinks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.875rem;
        }

        .campaign-details .card {
            transition: all 0.3s ease;
        }

        .campaign-details .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
        <div class="container mt-4">
            <!-- Campaign Details -->
            <div class="campaign-details card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 class="h4 mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-rocket me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 13a8 8 0 0 1 7 7a6 6 0 0 0 3 -5a9 9 0 0 0 6 -8a3 3 0 0 0 -3 -3a9 9 0 0 0 -8 6a6 6 0 0 0 -5 3" />
                                    <path d="M7 14a6 6 0 0 0 -3 3a6 6 0 0 0 3 3" />
                                    <path d="M15 9m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                </svg>
                                Campaign: <?= htmlspecialchars($campaign['name']) ?>
                            </h3>
                            <p class="text-muted mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                    <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                </svg>
                                Base URL: <?= htmlspecialchars($campaign['base_url']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible" role="alert">
                    <div class="d-flex">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12l5 5l10 -10" />
                            </svg>
                        </div>
                        <div><?= htmlspecialchars($successMessage) ?></div>
                    </div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                </div>
            <?php endif; ?>

            <!-- CSV Upload Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M12 11v6" />
                            <path d="M9.5 13.5l2.5 -2.5l2.5 2.5" />
                        </svg>
                        Upload Backlinks via CSV
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                        <div class="mb-3">
                            <label class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-spreadsheet me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M8 11h8" />
                                    <path d="M8 15h8" />
                                    <path d="M12 11v8" />
                                </svg>
                                Select CSV File
                            </label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <small class="form-text text-muted">CSV must have columns: backlink_url, anchor_text, target_url</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                <path d="M7 9l5 -5l5 5" />
                                <path d="M12 4v12" />
                            </svg>
                            Upload CSV
                        </button>
                    </form>
                    <?php if (!empty($csvErrors)): ?>
                        <div class="mt-3">
                            <?php foreach ($csvErrors as $error): ?>
                                <p class="error-message"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Manual Entry Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                        </svg>
                        Add Backlinks Manually
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                        <div class="mb-3">
                            <label class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                    <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                </svg>
                                Backlinks (One per line, format: backlink_url,anchor_text,target_url)
                            </label>
                            <textarea name="manual_backlinks" class="form-control" rows="5" placeholder="https://example.com/page,Anchor Text,https://your-site.com/target"></textarea>
                            <small class="form-text text-muted">Add up to 10 backlinks, one per line. Anchor text and target URL are optional.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12l5 5l10 -10" />
                            </svg>
                            Add Backlinks
                        </button>
                    </form>
                    <?php if (!empty($manualErrors)): ?>
                        <div class="mt-3">
                            <?php foreach ($manualErrors as $error): ?>
                                <p class="error-message"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
</body>

</html>