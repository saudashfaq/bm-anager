<?php
// campaign_management.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';



// Check if user is logged in and has access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'user') {
    header("Location:" . BASE_URL . "login.php");
    exit;
}

// Get campaigns with backlink counts
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
                            <h2 class="page-title">Campaign Management</h2>
                        </div>
                        <div class="col-auto ms-auto">
                            <button class="btn btn-ghost-primary" data-bs-toggle="modal" data-bs-target="#add-campaign-modal">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 5l0 14" />
                                    <path d="M5 12l14 0" />
                                </svg>
                                Add Campaign
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="container-xl">
                    <div class="row row-cards">
                        <?php foreach ($campaigns as $campaign): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><?= htmlspecialchars($campaign['name']) ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Base URL:</strong> <?= htmlspecialchars($campaign['base_url']) ?></p>
                                        <p><strong>Verification:</strong> <?= htmlspecialchars($campaign['verification_frequency']) ?></p>

                                        <div class="mt-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col">
                                                    <div class="text-muted">Backlinks Status</div>
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
                                                Manage Backlinks
                                            </a>
                                            <button type="button" class="btn btn-ghost-warning edit-campaign-btn"
                                                data-campaign-id="<?= $campaign['id'] ?>"
                                                data-campaign-name="<?= htmlspecialchars($campaign['name']) ?>"
                                                data-verification-frequency="<?= htmlspecialchars($campaign['verification_frequency']) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#edit-campaign-modal">
                                                Edit
                                            </button>
                                            <form class="delete-campaign-form" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="delete_campaign">
                                                <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                <button type="submit" class="btn btn-ghost-danger" onclick="return confirm('Are you sure?')">Delete</button>
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
                    <h5 class="modal-title">Add New Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="add-campaign-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="create_campaign">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="campaign_name" class="form-control" required>
                            <span class="error-message" style="color: red;"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base URL</label>
                            <input type="url" name="base_url" class="form-control" required>
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
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary ms-auto">Create Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Edit Campaign Modal -->
    <div class="modal modal-blur fade" id="edit-campaign-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Campaign</h5>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary ms-auto">Update Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {

            //Update csrf token when ajax request completes
            $(document).ajaxComplete(function(event, xhr) {

                let newCsrfToken = xhr.getResponseHeader("X-CSRF-TOKEN");

                if (newCsrfToken) {
                    // Update the CSRF token in the <meta> tag
                    $("meta[name='csrf-token']").attr("content", newCsrfToken);

                    // Update all hidden CSRF token fields in forms
                    $("input[name='csrf_token']").val(newCsrfToken);
                }

            });

            // Create Campaign
            let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

            $('#add-campaign-form').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Create new campaign card
                            const campaign = response.campaign;
                            const newCard = createCampaignCard(campaign);
                            $('.row-cards').append(newCard);

                            // Close modal and reset form
                            $('#add-campaign-modal').modal('hide');
                            $('#add-campaign-form')[0].reset();

                            // Show success message
                            alert(response.message);
                        } else {
                            alert(response.message);

                            let errorResponse = response.errors;

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
                    error: function() {
                        alert('An error occurred while creating the campaign');
                    }
                });
            });

            // Handle delete form submission
            $('.delete-campaign-form').on('submit', function(e) {
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
                            campaignCard.remove();
                            alert(response.message);
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

            /* Update Campaign
            $(document).on('submit', '.update-campaign-form', function(e) {
                e.preventDefault();
                alert('form submit 1');
                const campaignCard = $(this).closest('.col-md-6');
                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update campaign card with new data
                            updateCampaignCard(campaignCard, response.campaign);
                            $('#edit-campaign-modal').modal('hide');
                            alert(response.message);
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the campaign');
                    }
                });
            });*/

            // Handle Edit button click
            $(document).on('click', '.edit-campaign-btn', function() {
                const campaignId = $(this).data('campaign-id');
                const campaignName = $(this).data('campaign-name');
                const verificationFrequency = $(this).data('verification-frequency');

                $('#edit_campaign_id').val(campaignId);
                $('#edit_campaign_name').val(campaignName);
                $('#edit_verification_frequency').val(verificationFrequency);
            });

            // Handle Edit form submission
            $('#edit-campaign-form').on('submit', function(e) {
                e.preventDefault();
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

                            // Update card content
                            campaignCard.find('.card-title').text(campaign.name);
                            campaignCard.find('.verification-frequency').text(campaign.verification_frequency);

                            // Update edit button data attributes
                            const editBtn = campaignCard.find('.edit-campaign-btn');
                            editBtn.attr('data-campaign-name', campaign.name);
                            editBtn.attr('data-verification-frequency', campaign.verification_frequency);

                            // Update the entire card with new data
                            const newCard = $(createCampaignCard(campaign));
                            campaignCard.replaceWith(newCard);

                            // Close modal and show success message
                            $('#edit-campaign-modal').modal('hide');
                            alert(response.message);
                        } else {
                            alert(response.message);
                            let errorResponse = response.errors;

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
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        alert('An error occurred while updating the campaign');
                    }
                });
            });

            // Helper function to create campaign card HTML
            function createCampaignCard(campaign) {
                return `
                <div class="col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">${escapeHtml(campaign.name)}</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Base URL:</strong> ${escapeHtml(campaign.base_url)}</p>
                            <p><strong>Verification:</strong> <span class="verification-frequency">${escapeHtml(campaign.verification_frequency)}</span></p>
                            <div class="mt-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col">
                                        <div class="text-muted">Backlinks Status</div>
                                        <div class="progress progress-separated mb-3">
                                            <!-- Initial empty progress bars -->
                                        </div>
                                        <div class="row">
                                            <div class="col-auto d-flex align-items-center pe-2">
                                                <span class="legend me-2 bg-success"></span>
                                                <span>Alive (0)</span>
                                            </div>
                                            <div class="col-auto d-flex align-items-center px-2">
                                                <span class="legend me-2 bg-danger"></span>
                                                <span>Dead (0)</span>
                                            </div>
                                            <div class="col-auto d-flex align-items-center ps-2">
                                                <span class="legend me-2 bg-warning"></span>
                                                <span>Pending (0)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-list">
                                <a href="backlink_management.php?campaign_id=${campaign.id}&csrf_token=${window.csrfToken}" class="btn btn-primary">
                                    Manage Backlinks
                                </a>
                                <button type="button" class="btn btn-warning edit-campaign-btn" 
                                    data-campaign-id="${campaign.id}"
                                    data-campaign-name="${escapeHtml(campaign.name)}"
                                    data-verification-frequency="${escapeHtml(campaign.verification_frequency)}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#edit-campaign-modal">
                                    Edit
                                </button>
                                <form class="delete-campaign-form d-inline">
                                    <input type="hidden" name="csrf_token" value="${window.csrfToken}">
                                    <input type="hidden" name="action" value="delete_campaign">
                                    <input type="hidden" name="campaign_id" value="${campaign.id}">
                                    <button type="submit" class="btn btn-danger">
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
                card.find('.verification-frequency').text(campaign.verification_frequency);
            }

            // Helper function to escape HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
</body>

</html>