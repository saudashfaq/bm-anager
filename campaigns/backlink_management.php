<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/Pagination.php';

if (empty($_GET['campaign_id']) || !is_numeric($_GET['campaign_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

try {
    $stmt = $pdo->prepare("SELECT id, `name` FROM campaigns");
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $campaignId = $_GET['campaign_id'];
    $campaignExists = false;

    foreach ($campaigns as $campaign) {
        if ($campaign['id'] == $campaignId) {
            $campaignExists = true;
            break;
        }
    }

    if (!$campaignExists) {
        header('Location:' . BASE_URL . 'campaigns/campaign_management.php?error=Campaign not found');
        exit();
    }

    // Pagination setup
    $itemsPerPage = 10; // Number of backlinks per page
    $currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

    // Get total number of backlinks for the campaign
    $countQuery = "SELECT COUNT(*) FROM backlinks WHERE campaign_id = :campaign_id";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute(['campaign_id' => $campaignId]);
    $totalBacklinks = $countStmt->fetchColumn();

    // Initialize Pagination class
    $pagination = new Pagination($totalBacklinks, $itemsPerPage, $currentPage, '');

    // Fetch backlinks with pagination
    $offset = $pagination->getOffset();
    $backlinkQuery = "SELECT b.*, c.name AS campaign_name, c.base_url, 
        u.username AS created_by_username FROM backlinks b 
        JOIN campaigns c ON b.campaign_id = c.id
        JOIN users u ON b.created_by = u.id
        WHERE (b.campaign_id = :campaign_id)
        ORDER BY b.created_at DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($backlinkQuery);
    $stmt->bindValue(':campaign_id', $campaignId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die('Database error occurred');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Backlink Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <style>
        .campaign-details,
        .loading {
            display: none;
        }

        .skeleton-loading {
            animation: pulse 1.5s infinite;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.6
            }

            50% {
                opacity: 1
            }
        }

        .required:after {
            content: " *";
            color: red;
        }

        .campaign-details .card {
            transition: all 0.3s ease;
        }

        .campaign-details .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .subheader {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.035);
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
        <div class="container mt-4">
            <div class="mb-3">
                <label class="form-label text-muted small">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-filter me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227z" />
                    </svg>
                    Filter by Campaign
                </label>
                <select id="campaign-select" class="form-select">
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?= $campaign['id'] ?>" <?= (isset($_GET['campaign_id']) && $_GET['campaign_id'] == $campaign['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($campaign['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campaign-details card mb-4">
                <div class="card-body">
                    <div class="loading skeleton-loading p-4"></div>
                    <div class="campaign-info">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h3 class="campaign-name h4 mb-1"></h3>
                                <p class="text-muted mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                        <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                    </svg>
                                    Base URL: <span class="campaign-base-url"></span>
                                </p>
                            </div>
                            <div class="btn-list">
                                <button type="button" class="btn btn-ghost-primary" data-bs-toggle="modal" data-bs-target="#add-backlink-modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                    Add Backlink
                                </button>
                                <a id="bulk-upload-link" type="button" class="btn btn-ghost-yellow" href="bulk_upload_backlinks.php?campaign_id=<?= $campaignId ?>" target="_blank">
                                    <svg xmlns=" http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-upload me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                        <path d="M7 9l5 -5l5 5" />
                                        <path d="M12 4v12" />
                                    </svg>
                                    Bulk Upload
                                </a>

                            </div>
                        </div>
                        <div class="row g-3 mt-2 stats-container">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-list me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M9 6h11" />
                                                <path d="M9 12h11" />
                                                <path d="M9 18h11" />
                                                <path d="M5 6v.01" />
                                                <path d="M5 12v.01" />
                                                <path d="M5 18v.01" />
                                            </svg>
                                            Total Backlinks
                                        </div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M5 12l5 5l10 -10" />
                                            </svg>
                                            Active Backlinks
                                        </div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M18 6l-12 12" />
                                                <path d="M6 6l12 12" />
                                            </svg>
                                            Dead Backlinks
                                        </div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal modal-blur fade" id="add-backlink-modal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 15l6 -6" />
                                    <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .463" />
                                    <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.531 -.463" />
                                    <path d="M20 17v5" />
                                    <path d="M22 19h-5" />
                                </svg>
                                Add New Backlink
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="add-backlink-form" action="backlink_management_crud.php" method="post">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="campaign_id" id="hidden-campaign-id">
                                <input type="hidden" name="campaign_base_url" id="hidden-campaign-url">
                                <div class="mb-3">
                                    <label class="form-label required">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                            <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                        </svg>
                                        Backlink <small>(Webpage Link that contains link back to your website)</small>
                                    </label>
                                    <input type="url" name="backlink_url" class="form-control" required maxlength="255">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-target me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="12" r="9" />
                                            <circle cx="12" cy="12" r="6" />
                                            <circle cx="12" cy="12" r="3" />
                                            <path d="M12 3v18" />
                                            <path d="M3 12h18" />
                                        </svg>
                                        Target <small>(Your Website Link of Post/Page)</small>
                                    </label>
                                    <input type="text" name="target_url" class="form-control" maxlength="255">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-anchor me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 21v-9m-3 0h6m-6 0a2 2 0 1 1 -4 0a2 2 0 0 1 4 0" />
                                            <path d="M4 8v-2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2" />
                                            <path d="M4 16l8 5l8 -5" />
                                        </svg>
                                        Anchor Text
                                    </label>
                                    <input type="text" name="anchor_text" class="form-control" maxlength="255">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M18 6l-12 12" />
                                        <path d="M6 6l12 12" />
                                    </svg>
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M5 12l5 5l10 -10" />
                                    </svg>
                                    Add Backlink
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                            <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                        </svg>
                        Backlinks
                    </h3>
                    <button id="bulk-delete-btn" class="btn btn-danger" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7h16" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                        </svg>
                        Delete Selected
                    </button>
                </div>
                <div class="card-body backlinks-card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                            <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                        </svg>
                                        Backlink
                                    </th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-anchor me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 21v-9m-3 0h6m-6 0a2 2 0 1 1 -4 0a2 2 0 0 1 4 0" />
                                            <path d="M4 8v-2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2" />
                                            <path d="M4 16l8 5l8 -5" />
                                        </svg>
                                        Anchor
                                    </th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-target me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="12" r="9" />
                                            <circle cx="12" cy="12" r="6" />
                                            <circle cx="12" cy="12" r="3" />
                                            <path d="M12 3v18" />
                                            <path d="M3 12h18" />
                                        </svg>
                                        ReferringLink
                                    </th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-status-change me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M6 18m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M18 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M6 16v-4a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v4" />
                                            <path d="M6 8v-2" />
                                            <path d="M18 16v2" />
                                        </svg>
                                        Status
                                    </th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-clock me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 7v5l3 3" />
                                        </svg>
                                        Created At
                                    </th>
                                    <th>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-settings me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                        Actions
                                    </th>
                                    <!--<th>Campaign</th> -->
                                    <!--<th>Created By</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backlinks as $backlink): ?>
                                    <tr data-id="<?= htmlspecialchars($backlink['id']) ?>">
                                        <td><input type="checkbox" class="backlink-select" value="<?= htmlspecialchars($backlink['id']) ?>"></td>
                                        <td><?= htmlspecialchars($backlink['backlink_url']) ?></td>
                                        <td><?= htmlspecialchars($backlink['anchor_text']) ?></td>
                                        <td><?= htmlspecialchars($backlink['target_url']) ?></td>
                                        <td><span class="badge bg-<?= match ($backlink['status']) {
                                                                        'alive' => 'success',
                                                                        'dead' => 'danger',
                                                                        default => 'warning'
                                                                    } ?>"><?= htmlspecialchars($backlink['status']) ?></span></td>
                                        <td><?= date('Y-m-d H:i', strtotime($backlink['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger delete-single" data-id="<?= htmlspecialchars($backlink['id']) ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M4 7h16" />
                                                    <path d="M10 11v6" />
                                                    <path d="M14 11v6" />
                                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                                </svg>
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <?= $pagination->render(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            $(document).ajaxComplete(function(event, xhr) {
                const newCsrfToken = xhr.getResponseHeader("X-CSRF-TOKEN");
                if (newCsrfToken) {
                    $('meta[name="csrf-token"]').attr('content', newCsrfToken);
                    $('input[name="csrf_token"]').val(newCsrfToken);
                }
            });

            $('#campaign-select, select[name="campaign_id"]').select2({
                placeholder: 'Select Campaign',
                width: '100%'
            });

            $('#campaign-select').on('change', function() {
                const campaignId = $(this).val();
                $('#hidden-campaign-id').val(campaignId);
                $('.campaign-details').toggle(!!campaignId);
                if (!campaignId) return;

                // Update the href of the Bulk Upload link
                if (campaignId) {
                    $('#bulk-upload-link').attr('href', 'bulk_upload_backlinks.php?campaign_id=' + campaignId);
                }

                loadBacklinks(campaignId, 1); // Load first page by default
            });

            function loadBacklinks(campaignId, page) {
                $('.loading').show();
                $('.campaign-info').hide();

                $.ajax({
                    url: 'get_campaign_data.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        campaign_id: campaignId,
                        page: page,
                        csrf_token: csrfToken
                    },
                    success: function(data) {
                        $('.loading').hide();
                        $('.campaign-info').show();
                        updateCampaignDetails(data);
                        updateBacklinksTable(data.backlinks);
                        $('#hidden-campaign-url').val(data.campaign.base_url);

                        // Update pagination
                        $('.pagination').remove();
                        $('.backlinks-card-body').append(data.pagination);
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Error loading campaign data');
                    }
                });
            }

            const selectedCampaignId = $('#campaign-select').val();
            if (selectedCampaignId) {
                $('#campaign-select').trigger('change');
            }

            function updateCampaignDetails(data) {
                $('.campaign-name').text(data.campaign.name);
                $('.campaign-base-url').text(data.campaign.base_url);
                // Calculate stats based on the current page backlinks
                const backlinks = data.backlinks || [];
                const totalBacklinks = backlinks.length;
                const activeBacklinks = backlinks.filter(bl => bl.status === 'alive').length;
                const deadBacklinks = totalBacklinks - activeBacklinks;

                const statsHtml = `
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-list me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M9 6h11"/>
                                <path d="M9 12h11"/>
                                <path d="M9 18h11"/>
                                <path d="M5 6v.01"/>
                                <path d="M5 12v.01"/>
                                <path d="M5 18v.01"/>
                            </svg>
                            Total Backlinks
                        </div>
                        <div class="h1 mb-0 mt-2">${totalBacklinks}</div>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 12l5 5l10 -10"/>
                            </svg>
                            Active Backlinks
                        </div>
                        <div class="h1 mb-0 mt-2">${activeBacklinks}</div>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M18 6l-12 12"/>
                                <path d="M6 6l12 12"/>
                            </svg>
                            Dead Backlinks
                        </div>
                        <div class="h1 mb-0 mt-2">${deadBacklinks}</div>
                    </div></div></div>`;
                $('.stats-container').html(statsHtml);
            }

            function updateBacklinksTable(backlinks) {
                const tbody = $('.table tbody').empty();
                backlinks.forEach(function(bl) {
                    tbody.append(`
                        <tr data-id="${escapeHtml(bl.id)}" >
                            <td><input type="checkbox" class="backlink-select" value="${escapeHtml(bl.id)}"></td>
                            <td>${escapeHtml(bl.backlink_url)}</td>
                            <td>${escapeHtml(bl.anchor_text)}</td>
                            <td>${escapeHtml(bl.target_url)}</td>
                            <td><span class="status status-${bl.status === 'alive' ? 'success' : bl.status === 'dead' ? 'danger' : 'warning'}">${escapeHtml(bl.status)}</span></td>
                            <td>${new Date(bl.created_at).toLocaleString()}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger delete-single" data-id="${escapeHtml(bl.id)}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M4 7h16"/>
                                        <path d="M10 11v6"/>
                                        <path d="M14 11v6"/>
                                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                                        <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                                    </svg>
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `);
                });
                updateDeleteButtonState();
            }

            function escapeHtml(unsafe) {
                if (unsafe === undefined || unsafe === null) return '';
                return unsafe.toString().replace(/[&<"'>]/g, function(m) {
                    return {
                        '&': '&',
                        '<': '<',
                        '>': '>',
                        '"': '"',
                        "'": "'"
                    } [m];
                });
            }

            $('#add-backlink-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                //$('.loading').show();

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('.btn-close')[0].click();
                            $('#campaign-select').trigger('change');
                        } else {
                            alert(data.message);
                            $('.error-message').text('');
                            $.each(data.errors || {}, function(fieldName, errors) {
                                const $inputField = $('input[name="' + fieldName + '"]');
                                if ($inputField.length) {
                                    const $errorContainer = $inputField.siblings('.error-message');
                                    $errorContainer.text(errors[0]);
                                    setTimeout(function() {
                                        $errorContainer.text('');
                                    }, 8000);
                                }
                            });
                            //$('.loading').hide();
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Error adding backlink');
                    }
                });
            });

            // Single delete functionality
            $(document).on('click', '.delete-single', function() {
                const backlinkId = $(this).data('id');
                if (!confirm('Are you sure you want to delete this backlink?')) return;

                $.ajax({
                    url: 'backlink_management_crud.php',
                    method: 'DELETE',
                    data: JSON.stringify({
                        ids: [backlinkId],
                        csrf_token: csrfToken
                    }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $(`tr[data-id="${backlinkId}"]`).remove();
                            $('#campaign-select').trigger('change');
                        } else {
                            alert(data.message || 'Error deleting backlink');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Error deleting backlink');
                    }
                });
            });

            // Bulk delete functionality
            $('#select-all').on('change', function() {
                $('.backlink-select').prop('checked', this.checked);
                updateDeleteButtonState();
            });

            $(document).on('change', '.backlink-select', function() {
                updateDeleteButtonState();
            });

            function updateDeleteButtonState() {
                const checkedCount = $('.backlink-select:checked').length;
                $('#bulk-delete-btn').prop('disabled', checkedCount === 0)
                    .text(`Delete Selected (${checkedCount})`);
            }

            $('#bulk-delete-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete the selected backlinks?')) return;

                const selectedIds = $('.backlink-select:checked').map(function() {
                    return $(this).val();
                }).get();

                $.ajax({
                    url: 'backlink_management_crud.php',
                    method: 'DELETE',
                    data: JSON.stringify({
                        ids: selectedIds,
                        csrf_token: csrfToken
                    }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('#campaign-select').trigger('change');
                        } else {
                            alert(data.message || 'Error deleting backlinks');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Error deleting backlinks');
                    }
                });
            });

            // Pagination click handler
            $(document).on('click', '.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                const campaignId = $('#campaign-select').val();
                if (page && campaignId) {
                    loadBacklinks(campaignId, page);
                }
            });
        });
    </script>
</body>

</html>