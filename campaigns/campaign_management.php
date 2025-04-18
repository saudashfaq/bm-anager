<?php
// campaign_management.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// Get campaigns with backlink counts, sorted by created_at DESC (latest first)
$stmt = $pdo->query("
    SELECT 
        c.*,
        COUNT(DISTINCT b.id) as total_backlinks,
        SUM(CASE WHEN b.status = 'alive' THEN 1 ELSE 0 END) as alive_backlinks,
        SUM(CASE WHEN b.status = 'dead' THEN 1 ELSE 0 END) as dead_backlinks,
        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_backlinks
    FROM campaigns c
    LEFT JOIN backlinks b ON c.id = b.campaign_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= getCSRFToken() ?>">
    <title>Campaign Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.875rem;
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <!-- Include Navbar -->
        <?php include_once __DIR__ . '/../includes/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <h2 class="page-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-building-store me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 21l18 0" />
                                    <path d="M3 7v1a3 3 0 0 0 6 0v-1m4 0v1a3 3 0 0 0 6 0v-1" />
                                    <path d="M5 21v-12a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v12" />
                                    <path d="M10 14v-2a2 2 0 1 1 4 0v2" />
                                    <path d="M10 21v-4a2 2 0 1 1 4 0v4" />
                                </svg>
                                Campaign Manager
                            </h2>
                        </div>
                        <div class="col-auto ms-auto">
                            <button class="btn btn-ghost-primary" data-bs-toggle="modal" data-bs-target="#add-campaign-modal">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 5l0 14" />
                                    <path d="M5 12l14 0" />
                                </svg>
                                Add New Campaign
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="container-xl">
                    <!-- Filter and Search Options -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-search" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="10" cy="10" r="7" />
                                        <path d="M21 21l-6 -6" />
                                    </svg>
                                </span>
                                <input type="text" id="search-campaign" class="form-control" placeholder="Search by name or URL">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sort-ascending-letters" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M15 10v-5h-3l3 -3l3 3h-3v5z" />
                                        <path d="M15 14v7h3l-3 3l-3 -3h3v-7z" />
                                        <path d="M4 15h7" />
                                        <path d="M4 9h6" />
                                        <path d="M4 4h5" />
                                    </svg>
                                </span>
                                <select id="sort-campaigns" class="form-select">
                                    <option value="name-asc">Alphabetically (A-Z)</option>
                                    <option value="name-desc">Alphabetically (Z-A)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-filter" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227z" />
                                    </svg>
                                </span>
                                <select id="filter-status" class="form-select">
                                    <option value="all">All Campaigns</option>
                                    <option value="enabled">Enabled</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row row-cards" id="campaigns-container">
                        <!-- Display message if no campaigns are found -->
                        <?php if (empty($campaigns)): ?>
                            <div class="col-12 text-center">
                                <div class="empty">
                                    <div class="empty-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-folder-off" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M3 3l18 18" />
                                            <path d="M19 7h-8.5l-4.015 -4.015a2 2 0 0 0 -1.985 -.985h-2.5a2 2 0 0 0 -2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2 -2v-10" />
                                        </svg>
                                    </div>
                                    <p class="empty-title">No campaigns found</p>
                                    <p class="empty-subtitle text-muted">
                                        It looks like you haven't created any campaigns yet. Let's get started!
                                    </p>
                                    <div class="empty-action">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-campaign-modal">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M12 5l0 14" />
                                                <path d="M5 12l14 0" />
                                            </svg>
                                            Create Your First Campaign
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($campaigns as $campaign): ?>
                            <div class="col-md-6 col-lg-4 campaign-card" data-name="<?= htmlspecialchars(strtolower($campaign['name'])) ?>" data-base-url="<?= htmlspecialchars(strtolower($campaign['base_url'])) ?>" data-status="<?= htmlspecialchars($campaign['status']) ?>" data-created-at="<?= htmlspecialchars($campaign['created_at']) ?>">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><?= htmlspecialchars($campaign['name']) ?></h3>
                                        <span class="badge ms-auto bg-<?= $campaign['status'] === 'enabled' ? 'green-lt' : 'red-lt' ?>">
                                            <?= ucfirst($campaign['status']) ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p>
                                            <strong>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                                    <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                                </svg>
                                                Base URL:
                                            </strong>
                                            <?= htmlspecialchars($campaign['base_url']) ?>
                                        </p>
                                        <p>
                                            <strong>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-clock me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path d="M12 7v5l3 3" />
                                                </svg>
                                                Verification:
                                            </strong>
                                            <span class="verification-frequency"> <?= htmlspecialchars(ucfirst($campaign['verification_frequency'])) ?></span>
                                        </p>

                                        <div class="mt-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col">
                                                    <div class="text-muted">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-chart-bar me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                            <path d="M3 12h4v8h-4z" />
                                                            <path d="M10 8h4v12h-4z" />
                                                            <path d="M17 4h4v16h-4z" />
                                                            <path d="M3 20h18" />
                                                        </svg>
                                                        Backlinks Status
                                                    </div>
                                                    <div class="progress progress-separated mb-3">
                                                        <?php if ($campaign['total_backlinks'] > 0): ?>
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($campaign['alive_backlinks'] / $campaign['total_backlinks']) * 100 ?>%"></div>
                                                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($campaign['dead_backlinks'] / $campaign['total_backlinks']) * 100 ?>%"></div>
                                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= ($campaign['pending_backlinks'] / $campaign['total_backlinks']) * 100 ?>%"></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-auto d-flex align-items-center pe-2">
                                                            <span class="legend me-2 bg-success"></span>
                                                            <span>Alive (<?= $campaign['alive_backlinks'] ?>)</span>
                                                        </div>
                                                        <div class="col-auto d-flex align-items-center px-2">
                                                            <span class="legend me-2 bg-danger"></span>
                                                            <span>Dead (<?= $campaign['dead_backlinks'] ?>)</span>
                                                        </div>
                                                        <div class="col-auto d-flex align-items-center ps-2">
                                                            <span class="legend me-2 bg-warning"></span>
                                                            <span>Pending (<?= $campaign['pending_backlinks'] ?>)</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-list">
                                            <a href="backlink_management.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                                    <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                                </svg>
                                                Manage Backlinks
                                            </a>
                                            <a href="export_campaign_report.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-ghost-success">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-spreadsheet me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                                    <path d="M8 11h8" />
                                                    <path d="M8 15h8" />
                                                </svg>
                                                Download Report
                                            </a>
                                            <button type="button" class="btn btn-ghost-warning edit-campaign-btn"
                                                data-campaign-id="<?= $campaign['id'] ?>"
                                                data-campaign-name="<?= htmlspecialchars($campaign['name']) ?>"
                                                data-verification-frequency="<?= htmlspecialchars($campaign['verification_frequency']) ?>"
                                                data-status="<?= htmlspecialchars($campaign['status']) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#edit-campaign-modal">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                                    <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                                    <path d="M16 5l3 3" />
                                                </svg>
                                                Edit
                                            </button>
                                            <form class="delete-campaign-form" style="display:inline;" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="delete_campaign">
                                                <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                <button type="submit" class="btn btn-ghost-danger" onclick="return confirm('Do you really want to delete this campaign? This process is irreversible.')">
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
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Campaign Modal -->
    <div class="modal modal-blur fade" id="add-campaign-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg>
                        Add New Campaign
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="add-campaign-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="create_campaign">
                    <input type="hidden" name="status" value="enabled"> <!-- Default status for new campaigns -->
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="campaign_name" class="form-control" required>
                            <span class="error-message" style="color: red;"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base URL</label>
                            <input type="text" id="base_url" name="base_url" class="form-control" required>
                            <span class="error-message" style="color: red;"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification Frequency</label>
                            <select name="verification_frequency" class="form-select" required>
                                <?php foreach ($campaign_frequency as $key => $title): ?>
                                    <option value="<?= $key ?>"><?= $title ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <button type="submit" class="btn btn-primary ms-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12l5 5l10 -10" />
                            </svg>
                            Create Campaign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Campaign Modal -->
    <div class="modal modal-blur fade" id="edit-campaign-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                        </svg>
                        Edit Campaign
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="edit-campaign-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_campaign">
                    <input type="hidden" name="campaign_id" id="edit_campaign_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="campaign_name" id="edit_campaign_name" class="form-control" required>
                            <span class="error-message" style="color: red;"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification Frequency</label>
                            <select name="verification_frequency" id="edit_verification_frequency" class="form-select" required>
                                <?php foreach ($campaign_frequency as $key => $title): ?>
                                    <option value="<?= $key ?>"><?= $title ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error-message" style="color: red;"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="edit_campaign_status" value="enabled">
                                <label class="form-check-label" for="edit_campaign_status">Enabled</label>
                            </div>
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
                        <button type="submit" class="btn btn-primary ms-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12l5 5l10 -10" />
                            </svg>
                            Update Campaign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../includes/genralfunc.js"></script>
    <script>
        $(document).ready(function() {
            // Store the original list of campaign cards
            let originalCampaignCards = $('.campaign-card').get();

            // Sort originalCampaignCards by created_at DESC initially
            originalCampaignCards.sort((a, b) => {
                const createdAtA = $(a).data('created-at');
                const createdAtB = $(b).data('created-at');
                return new Date(createdAtB) - new Date(createdAtA); // DESC order
            });

            // Update CSRF token when AJAX request completes
            $(document).ajaxComplete(function(event, xhr) {
                let newCsrfToken = xhr.getResponseHeader("X-CSRF-TOKEN");
                if (newCsrfToken) {
                    $("meta[name='csrf-token']").attr("content", newCsrfToken);
                    $("input[name='csrf_token']").val(newCsrfToken);
                }
            });

            // Create Campaign
            let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

            $('#add-campaign-form').on('submit', function(e) {
                e.preventDefault();
                sanitizeURL('#base_url');

                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const campaign = response.campaign;
                            const newCard = createCampaignCard(campaign);
                            $('#campaigns-container').prepend(newCard); // Prepend to show as first item
                            $('#add-campaign-modal').modal('hide');
                            $('#add-campaign-form')[0].reset();
                            $("div .empty").hide();
                            alert(response.message);
                            // Add the new card to originalCampaignCards and sort by created_at
                            originalCampaignCards.unshift($(newCard).get(0));
                            originalCampaignCards.sort((a, b) => {
                                const createdAtA = $(a).data('created-at');
                                const createdAtB = $(b).data('created-at');
                                return new Date(createdAtB) - new Date(createdAtA); // DESC order
                            });
                            applyFiltersAndSort(); // Reapply filters and sorting after adding
                        } else {
                            alert(response.message);
                            let errorResponse = response.errors;
                            $('.error-message').text('');
                            $.each(errorResponse, function(fieldName, errors) {
                                let $inputField = $('input[name="' + fieldName + '"]');
                                if ($inputField.length) {
                                    let $errorContainer = $inputField.siblings('.error-message');
                                    $errorContainer.text(errors[0]);
                                    setTimeout(function() {
                                        $errorContainer.text('');
                                    }, 8000);
                                }
                            });
                        }
                    },
                    error: function() {
                        alert('An error occurred while creating the campaign');
                    }
                });
            });

            // Handle delete form submission with event delegation
            $(document).on('submit', '.delete-campaign-form', function(e) {
                e.preventDefault();
                const form = $(this);
                const campaignCard = form.closest('.col-md-6');
                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove the card from the original list
                            const index = originalCampaignCards.indexOf(campaignCard.get(0));
                            if (index !== -1) {
                                originalCampaignCards.splice(index, 1);
                            }
                            campaignCard.remove();
                            alert(response.message);
                            if ($('#campaigns-container').children().length === 0) {
                                $("div .empty").show();
                            }
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the campaign');
                    }
                });
            });

            // Handle Edit button click
            $(document).on('click', '.edit-campaign-btn', function(e) {
                const campaignId = $(this).attr('data-campaign-id');
                const campaignName = $(this).attr('data-campaign-name');
                const verificationFrequency = $(this).attr('data-verification-frequency');
                const status = $(this).attr('data-status');
                // Populate modal fields
                $('#edit_campaign_id').val(campaignId);
                $('#edit_campaign_name').val(campaignName);
                $('#edit_verification_frequency').val(verificationFrequency);
                $('#edit_campaign_status').prop('checked', status === 'enabled');
            });

            // Handle Edit form submission
            $('#edit-campaign-form').on('submit', function(e) {
                e.preventDefault();
                // Ensure status is sent as 'enabled' or 'disabled'
                const statusCheckbox = $('#edit_campaign_status');
                statusCheckbox.val(statusCheckbox.is(':checked') ? 'enabled' : 'disabled');
                const formData = $(this).serialize();
                const campaignId = $('#edit_campaign_id').val();
                const campaignCard = $(`.edit-campaign-btn[data-campaign-id="${campaignId}"]`).closest('.col-md-6');

                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const campaign = response.campaign;
                            campaignCard.find('.card-title').text(escapeHtml(campaign.name));
                            campaignCard.find('.verification-frequency').text(escapeHtml(capitalizeFirstLetter(campaign.verification_frequency)));
                            campaignCard.find('.badge').text(campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1))
                                .removeClass('bg-green-lt bg-red-lt')
                                .addClass(campaign.status === 'enabled' ? 'bg-green-lt' : 'bg-red-lt');
                            campaignCard.find('.edit-campaign-btn')
                                .attr('data-campaign-name', escapeHtml(campaign.name))
                                .attr('data-verification-frequency', escapeHtml(campaign.verification_frequency))
                                .attr('data-status', campaign.status);
                            campaignCard.data('name', campaign.name.toLowerCase());
                            campaignCard.data('status', campaign.status);
                            // Update created_at in case it changes (though typically it shouldn't)
                            campaignCard.data('created-at', campaign.created_at);
                            $('#edit-campaign-modal').modal('hide');
                            alert(response.message);
                            // Sort originalCampaignCards by created_at to maintain order
                            originalCampaignCards.sort((a, b) => {
                                const createdAtA = $(a).data('created-at');
                                const createdAtB = $(b).data('created-at');
                                return new Date(createdAtB) - new Date(createdAtA); // DESC order
                            });
                            applyFiltersAndSort(); // Reapply filters and sorting after editing
                        } else {
                            alert(response.message);
                            let errorResponse = response.errors;
                            $('.error-message').text('');
                            $.each(errorResponse, function(fieldName, errors) {
                                let $inputField = $('input[name="' + fieldName + '"]');
                                if ($inputField.length) {
                                    let $errorContainer = $inputField.siblings('.error-message');
                                    $errorContainer.text(errors[0]);
                                    setTimeout(function() {
                                        $errorContainer.text('');
                                    }, 8000);
                                }
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        alert('An error occurred while updating the campaign');
                    }
                });
            });

            // Search functionality
            $('#search-campaign').on('input', function() {
                applyFiltersAndSort();
            });

            // Sort functionality
            $('#sort-campaigns').on('change', function() {
                applyFiltersAndSort();
            });

            // Filter by status
            $('#filter-status').on('change', function() {
                applyFiltersAndSort();
            });

            // Function to apply filters and sorting
            function applyFiltersAndSort() {
                const searchQuery = $('#search-campaign').val().toLowerCase();
                const sortOption = $('#sort-campaigns').val();
                const statusFilter = $('#filter-status').val();

                // Start with the full list of campaigns
                let campaignCards = originalCampaignCards.slice();

                // Filter by search query (only if search query is not empty)
                if (searchQuery) {
                    campaignCards = campaignCards.filter(function(card) {
                        const name = $(card).data('name');
                        const baseUrl = $(card).data('base-url');
                        return name.includes(searchQuery) || baseUrl.includes(searchQuery);
                    });
                }

                // Filter by status
                if (statusFilter !== 'all') {
                    campaignCards = campaignCards.filter(function(card) {
                        return $(card).data('status') === statusFilter;
                    });
                }

                // Sort campaigns
                campaignCards.sort(function(a, b) {
                    const nameA = $(a).data('name');
                    const nameB = $(b).data('name');
                    const createdAtA = $(a).data('created-at');
                    const createdAtB = $(b).data('created-at');

                    // If sortOption is alphabetical, sort by name
                    if (sortOption === 'name-asc') {
                        return nameA.localeCompare(nameB);
                    } else if (sortOption === 'name-desc') {
                        return nameB.localeCompare(nameA);
                    } else {
                        // Default: sort by created_at DESC
                        return new Date(createdAtB) - new Date(createdAtA);
                    }
                });

                // Re-render campaigns
                $('#campaigns-container').empty();
                if (campaignCards.length === 0) {
                    $("div .empty").show();
                } else {
                    $("div .empty").hide();
                    campaignCards.forEach(function(card) {
                        $('#campaigns-container').append(card);
                    });
                }
            }

            // Helper function to create campaign card HTML
            function createCampaignCard(campaign) {
                // Get the current CSRF token from the meta tag
                const currentCsrfToken = $("meta[name='csrf-token']").attr("content");
                return `
    <div class="col-md-6 col-lg-4 campaign-card" data-name="${escapeHtml(campaign.name.toLowerCase())}" data-base-url="${escapeHtml(campaign.base_url.toLowerCase())}" data-status="${campaign.status}" data-created-at="${campaign.created_at}">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">${escapeHtml(campaign.name)}</h3>
                <span class="badge ms-auto bg-${campaign.status === 'enabled' ? 'green-lt' : 'red-lt'}">${campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1)}</span>
            </div>
            <div class="card-body">
                <p>
                    <strong>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                            <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                        </svg>
                        Base URL:
                    </strong>
                    ${escapeHtml(campaign.base_url)}
                </p>
                <p>
                    <strong>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-clock me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                        Verification:
                    </strong>
                    <span class="verification-frequency">${escapeHtml(capitalizeFirstLetter(campaign.verification_frequency))}</span>
                </p>
                <div class="mt-3">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <div class="text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-chart-bar me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 12h4v8h-4z" />
                                    <path d="M10 8h4v12h-4z" />
                                    <path d="M17 4h4v16h-4z" />
                                    <path d="M3 20h18" />
                                </svg>
                                Backlinks Status
                            </div>
                            <div class="progress progress-separated mb-3">
                                ${campaign.total_backlinks > 0 ? `
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${(campaign.alive_backlinks / campaign.total_backlinks) * 100}%"></div>
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: ${(campaign.dead_backlinks / campaign.total_backlinks) * 100}%"></div>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: ${(campaign.pending_backlinks / campaign.total_backlinks) * 100}%"></div>
                                ` : ''}
                            </div>
                            <div class="row">
                                <div class="col-auto d-flex align-items-center pe-2">
                                    <span class="legend me-2 bg-success"></span>
                                    <span>Alive (<span class="alive-count">${campaign.alive_backlinks || 0}</span>)</span>
                                </div>
                                <div class="col-auto d-flex align-items-center px-2">
                                    <span class="legend me-2 bg-danger"></span>
                                    <span>Dead (<span class="dead-count">${campaign.dead_backlinks || 0}</span>)</span>
                                </div>
                                <div class="col-auto d-flex align-items-center ps-2">
                                    <span class="legend me-2 bg-warning"></span>
                                    <span>Pending (<span class="pending-count">${campaign.pending_backlinks || 0}</span>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="btn-list">
                    <a href="backlink_management.php?campaign_id=${campaign.id}&csrf_token=${currentCsrfToken}" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                            <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                        </svg>
                        Manage Backlinks
                    </a>
                    <a href="export_campaign_report.php?campaign_id=${campaign.id}" class="btn btn-ghost-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-spreadsheet me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M8 11h8" />
                            <path d="M8 15h8" />
                        </svg>
                        Download Report
                    </a>
                    <button type="button" class="btn btn-ghost-warning edit-campaign-btn"
                        data-campaign-id="${campaign.id}"
                        data-campaign-name="${escapeHtml(campaign.name)}"
                        data-verification-frequency="${escapeHtml(campaign.verification_frequency)}"
                        data-status="${campaign.status}"
                        data-bs-toggle="modal"
                        data-bs-target="#edit-campaign-modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                        </svg>
                        Edit
                    </button>
                    <form class="delete-campaign-form d-inline" method="POST">
                        <input type="hidden" name="csrf_token" value="${currentCsrfToken}">
                        <input type="hidden" name="action" value="delete_campaign">
                        <input type="hidden" name="campaign_id" value="${campaign.id}">
                        <button type="submit" class="btn btn-ghost-danger">
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
                    </form>
                </div>
            </div>
        </div>
    </div>
    `;
            }

            // Helper function to update campaign card
            function updateCampaignCard(card, campaign) {
                card.find('.card-title').text(campaign.name);
                card.find('.verification-frequency').text(capitalizeFirstLetter(campaign.verification_frequency));
            }

            // Helper function to escape HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&")
                    .replace(/</g, "<")
                    .replace(/>/g, ">")
                    .replace(/"/g, "\"")
                    .replace(/'/g, "'");
            }

            // Helper function to capitalize first letter
            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
        });
    </script>
</body>

</html>