<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';


if (empty($_GET['campaign_id']) ||  !is_numeric($_GET['campaign_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Secure database queries with try-catch
try {
    $stmt = $pdo->prepare("SELECT id, `name` FROM campaigns");
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //verify if the campaign exists 
    $campaignId = $_GET['campaign_id'];
    $campaignExists = false;

    foreach ($campaigns as $campaign) {
        if ($campaign['id'] == $campaignId) {
            $campaignExists = true;
            break;
        }
    }
    //if campaign doesn't exist let's exit.
    if (!$campaignExists) {
        header('Location:' . BASE_URL . 'campaigns/campaign_management.php?error=Campaign not found');
        exit();
    }

    $backlinkQuery = "SELECT b.*, c.name AS campaign_name, c.base_url, 
        u.username AS created_by_username FROM backlinks b 
        JOIN campaigns c ON b.campaign_id = c.id
        JOIN users u ON b.created_by = u.id
        WHERE (b.campaign_id = :campaign_id)
        ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($backlinkQuery);
    $stmt->execute(['campaign_id' => $_GET['campaign_id'] ?? null]);
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
                <label class="form-label text-muted small">Filter by Campaign</label>
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
                                <p class="text-muted mb-0">Base URL: <span class="campaign-base-url"></span></p>
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
                            </div>
                        </div>
                        <div class="row g-3 mt-2 stats-container">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">Total Backlinks</div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">Active Backlinks</div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="subheader">Dead Backlinks</div>
                                        <div class="h1 mb-0 mt-2">1</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--
        <div class="text-end mb-4">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-backlink-modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Add New Backlink
            </button>
        </div>
                    -->
            <div class="modal modal-blur fade" id="add-backlink-modal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Backlink</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="add-backlink-form" action="add_backlink.php" method="post">
                            <div class="modal-body">

                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="campaign_id" id="hidden-campaign-id">
                                <input type="hidden" name="campaign_base_url" id="hidden-campaign-url">

                                <div class="mb-3">
                                    <label class="form-label required">Target URL</label>
                                    <input type="url" name="target_url" class="form-control" required pattern="https?://.+">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Backlink URL</label>
                                    <input type="url" name="backlink_url" class="form-control" required pattern="https?://.+">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Anchor Text</label>
                                    <input type="text" name="anchor_text" class="form-control" required maxlength="255">
                                    <span class="error-message" style="color: red;"></span>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Backlink</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Backlinks</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Target URL</th>
                                    <th>Backlink URL</th>
                                    <th>Anchor Text</th>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backlinks as $backlink): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($backlink['target_url']) ?></td>
                                        <td><?= htmlspecialchars($backlink['backlink_url']) ?></td>
                                        <td><?= htmlspecialchars($backlink['anchor_text']) ?></td>
                                        <td><?= htmlspecialchars($backlink['campaign_name']) ?></td>
                                        <td><span class="badge bg-<?= match ($backlink['status']) {
                                                                        'alive' => 'success',
                                                                        'dead' => 'danger',
                                                                        default => 'warning'
                                                                    } ?>"><?= $backlink['status'] ?></span></td>
                                        <td><?= htmlspecialchars($backlink['created_by_username']) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($backlink['created_at'])) ?></td>
                                        <td>
                                            <form class="delete-form" method="POST" action="delete_backlink.php" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?= $backlink['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required JavaScript -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>



    <script>
        $(document).ready(function() {

            $(document).ajaxComplete(function(event, xhr) {
                let newCsrfToken = xhr.getResponseHeader("X-CSRF-TOKEN");

                if (newCsrfToken) {
                    // Update the CSRF token in the <meta> tag
                    $("meta[name='csrf-token']").attr("content", newCsrfToken);

                    // Update all hidden CSRF token fields in forms
                    $("input[name='csrf_token']").val(newCsrfToken);
                }
            });

            // Grab csrfToken
            let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

            // Initialize Select2 for both dropdowns
            $('#campaign-select, select[name="campaign_id"]').select2({
                placeholder: 'Select Campaign',
                width: '100%'
            });


            $('#campaign-select').on('change', function() {
                const campaignId = $(this).val();
                $('#hidden-campaign-id').val(campaignId);
                $('.campaign-details').toggle(!!campaignId);
                if (!campaignId) return;

                $('.loading').show();
                $('.campaign-info').hide();

                console.log('Fetching data for campaign ID:', campaignId);

                $.ajax({
                    url: 'get_campaign_data.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        campaign_id: campaignId,
                        csrf_token: csrfToken
                    },
                    success: (data) => {
                        console.log('Received data:', data);
                        $('.loading').hide();
                        $('.campaign-info').show();
                        updateCampaignDetails(data);
                        updateBacklinksTable(data.backlinks);
                        $('#hidden-campaign-url').val(data.campaign.base_url);
                    },
                    error: (xhr) => {
                        console.error('Error:', xhr.responseText);
                        alert('Error loading campaign data');
                    }
                });
            });

            // Trigger change event on page load for the selected campaign

            const selectedCampaignId = $('#campaign-select').val();
            if (selectedCampaignId) {
                $('#campaign-select').trigger('change');
            }


            function updateCampaignDetails(data) {
                $('.campaign-name').text(data.campaign.name);
                $('.campaign-base-url').text(data.campaign.base_url);

                // Check if data.stats is defined before using it
                const totalBacklinks = data.stats?.total || 0;
                const activeBacklinks = data.stats?.active || 0;
                const deadBacklinks = data.stats?.dead || 0;

                const statsHtml = `
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">Total Backlinks</div>
                        <div class="h1 mb-0 mt-2">${totalBacklinks}</div>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">Active Backlinks</div>
                        <div class="h1 mb-0 mt-2">${activeBacklinks}</div>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body p-3">
                        <div class="subheader">Dead Backlinks</div>
                        <div class="h1 mb-0 mt-2">${deadBacklinks}</div>
                    </div></div></div>`;
                $('.stats-container').html(statsHtml);
            }

            function updateBacklinksTable(backlinks) {
                const tbody = $('.table tbody').empty();
                backlinks.forEach(bl => {
                    tbody.append(`
                        <tr>
                            <td>${escapeHtml(bl.target_url)}</td>
                            <td>${escapeHtml(bl.backlink_url)}</td>
                            <td>${escapeHtml(bl.anchor_text)}</td>
                            <td>${escapeHtml(bl.campaign_name)}</td>
                            <td><span class="badge bg-${bl.status === 'alive' ? 'success' : bl.status === 'dead' ? 'danger' : 'warning'}">${bl.status}</span></td>
                            <td>${escapeHtml(bl.created_by_username)}</td>
                            <td>${new Date(bl.created_at).toLocaleString()}</td>
                            <td>
                                <form class="delete-form" method="POST" action="delete_backlink.php">
                                    <input type="hidden" name="id" value="${bl.id}">
                                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    `);
                });
            }

            function escapeHtml(unsafe) {
                return unsafe?.toString()?.replace(/[&<"'>]/g, m => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [m])) || '';
            }

            // Handle form submission for adding a backlink
            $('#add-backlink-form').on('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission

                const formData = $(this).serialize(); // Serialize form data

                $('.loading').show(); // Show loading indicator

                $.ajax({
                    url: $(this).attr('action'), // Use the form's action attribute
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: (data) => {
                        if (data.success) {
                            $('.btn-close')[0].click();
                            $('#campaign-select').trigger('change');
                        } else {

                            let errorResponse = data.errors;
                            // Clear previous error messages
                            $('.error-message').text('');

                            $.each(errorResponse, function(fieldName, errors) {
                                // Find the input field by name
                                let $inputField = $('input[name="' + fieldName + '"]');
                                if ($inputField.length) {
                                    // Find the error message container next to the field
                                    let $errorContainer = $inputField.siblings('.error-message');
                                    // Set the first error message (you can join multiple if needed)
                                    $errorContainer.text(errors[0]);

                                    setTimeout(function() {
                                        $errorContainer.text('');
                                    }, 8000);

                                }
                            });

                        }


                    },
                    error: (xhr) => {
                        console.error('Error:', xhr.responseText);
                        alert('Error loading campaign data');
                    }

                });
            });
        });
    </script>
</body>

</html>