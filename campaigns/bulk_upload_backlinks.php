<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../generalfunctions/general_functions.php';

if (empty($_GET['campaign_id']) || !is_numeric($_GET['campaign_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$campaignId = (int)$_GET['campaign_id'];

// Check if the campaign exists and fetch its base_url
$stmt = $pdo->prepare("SELECT id, name, base_url FROM campaigns WHERE id = ?");
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header('Location: ' . BASE_URL . 'campaigns/campaign_management.php?error=Campaign not found');
    exit();
}

// Extract the base domain of the campaign's base_url
$backlinkUtils = new BacklinkUtils($pdo);
$campaignBaseDomain = $campaign['base_url'];
// if ($campaignBaseDomain === null) {
//     header('Location: ' . BASE_URL . 'campaigns/campaign_management.php?error=Invalid campaign base URL');
//     exit();
// }

$errors = [];
$successMessage = '';
$processedRows = 0;
$failedRows = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $pdo->beginTransaction();
        try {
            // Handle CSV upload
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');

                if ($handle === false) {
                    throw new Exception('Unable to open the uploaded file');
                }

                // Skip the header row
                $header = fgetcsv($handle);
                if (!$header || !in_array('backlink_url', array_map('strtolower', $header))) {
                    throw new Exception('Invalid CSV format: "backlink_url" column is required');
                }

                while (($row = fgetcsv($handle)) !== false) {
                    $data = array_combine(array_map('strtolower', $header), $row);

                    $backlinkUrl = trim($data['backlink_url'] ?? '');
                    $targetUrl = trim($data['target_url'] ?? '');
                    $anchorText = trim($data['anchor_text'] ?? '');

                    if (empty($backlinkUrl)) {
                        $failedRows++;
                        continue;
                    }

                    if (!filter_var($backlinkUrl, FILTER_VALIDATE_URL)) {
                        $failedRows++;
                        continue;
                    }

                    // If target_url is provided, validate its base domain against the campaign's base_url
                    if (!empty($targetUrl)) {
                        if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                            $failedRows++;
                            continue;
                        }
                        $targetBaseDomain = $backlinkUtils->getBaseDomain($targetUrl);
                        if ($targetBaseDomain !== $campaignBaseDomain) {
                            $failedRows++;
                            continue;
                        }
                    }

                    // Insert the backlink using BacklinkUtils
                    $backlinkUtils->insertBacklink(
                        $campaignId,
                        $backlinkUrl,
                        $targetUrl,
                        $anchorText,
                        $_SESSION['user_id']
                    );

                    $processedRows++;
                }

                fclose($handle);
            }
            // Handle textarea input
            elseif (!empty($_POST['backlinks_text'])) {
                $lines = array_filter(array_map('trim', explode("\n", $_POST['backlinks_text'])));
                if (empty($lines)) {
                    throw new Exception('Please enter at least one backlink in the textarea');
                }

                foreach ($lines as $line) {
                    // Parse the line as a CSV row
                    $data = str_getcsv($line);
                    if (empty($data[0])) {
                        $failedRows++;
                        continue;
                    }

                    $backlinkUrl = trim($data[0]); // backlink_url is required
                    $targetUrl = trim($data[1] ?? ''); // target_url is optional
                    $anchorText = trim($data[2] ?? ''); // anchor_text is optional

                    if (!filter_var($backlinkUrl, FILTER_VALIDATE_URL)) {
                        $failedRows++;
                        continue;
                    }

                    // If target_url is provided, validate its base domain against the campaign's base_url
                    if (!empty($targetUrl)) {
                        if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                            $failedRows++;
                            continue;
                        }
                        $targetBaseDomain = $backlinkUtils->getBaseDomain($targetUrl);
                        if ($targetBaseDomain !== $campaignBaseDomain) {
                            $failedRows++;
                            continue;
                        }
                    }

                    // Insert the backlink using BacklinkUtils
                    $backlinkUtils->insertBacklink(
                        $campaignId,
                        $backlinkUrl,
                        $targetUrl,
                        $anchorText,
                        $_SESSION['user_id']
                    );

                    $processedRows++;
                }
            } else {
                throw new Exception('Please upload a CSV file or enter backlinks in the textarea');
            }

            $pdo->commit();
            $successMessage = "Processed $processedRows backlinks successfully.";
            if ($failedRows > 0) {
                $successMessage .= " $failedRows rows failed to process (invalid URL, missing backlink_url, or target_url base domain mismatch).";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
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
        .required:after {
            content: " *";
            color: red;
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 9l5 -5l5 5" />
                            <path d="M12 4v12" />
                        </svg>
                        Bulk Upload Backlinks for Campaign:<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($campaign['name']) ?><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($campaign['base_url']) ?>
                    </h3>



                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="alert alert-success">
                            <p class="mb-0"><?= htmlspecialchars($successMessage) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M12 11v6" />
                                    <path d="M9.5 13.5l2.5 -2.5l2.5 2.5" />
                                </svg>
                                Upload CSV File
                            </label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv">
                            <small class="form-hint">
                                CSV file must contain a "backlink_url" column. Optional columns: "target_url", "anchor_text".
                                <a href="sample_backlinks.csv" download>Download sample CSV</a>
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-text-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 5h11" />
                                    <path d="M3 10h11" />
                                    <path d="M3 15h11" />
                                    <path d="M3 20h11" />
                                    <path d="M19 15v6" />
                                    <path d="M16 18h6" />
                                </svg>
                                Or Enter Backlinks (One per line, CSV format: backlink_url,target_url,anchor_text)
                            </label>
                            <textarea name="backlinks_text" class="form-control" rows="5" placeholder="https://example.com,https://target.com,Example Anchor
https://another.com,https://target.com,Another Anchor
https://third.com,,"></textarea>
                            <small class="form-hint">
                                Enter one backlink per line in the format: backlink_url,target_url,anchor_text. target_url and anchor_text are optional. If target_url is provided, its base domain must match the campaign's base URL (<?= htmlspecialchars($campaign['base_url']) ?>).
                            </small>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="backlink_management.php?campaign_id=<?= $campaignId ?>" class="btn btn-link link-secondary me-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M5 12h14" />
                                    <path d="M5 12l6 6" />
                                    <path d="M5 12l6 -6" />
                                </svg>
                                Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                    <path d="M7 9l5 -5l5 5" />
                                    <path d="M12 4v12" />
                                </svg>
                                Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
</body>

</html>