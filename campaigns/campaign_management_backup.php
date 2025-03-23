<?php

// campaign_management.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

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
                            <h2 class="page-title">Campaign Manager</h2>
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
                    <div class="row row-cards">


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
                            <div class="col-md-6 col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><?= htmlspecialchars($campaign['name']) ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Base URL:</strong> <?= htmlspecialchars($campaign['base_url']) ?></p>
                                        <p><strong>Verification:</strong> <span class="verification-frequency"> <?= htmlspecialchars(ucfirst($campaign['verification_frequency'])) ?></span> </p>

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

    <!-- ... (keep all existing PHP and HTML up to the script tag unchanged) ... -->

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {

            // Update csrf token when ajax request completes
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
                $.ajax({
                    url: 'campaign_management_crud.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const campaign = response.campaign;
                            const newCard = createCampaignCard(campaign);
                            $('.row-cards').append(newCard);
                            $('#add-campaign-modal').modal('hide');
                            $('#add-campaign-form')[0].reset();
                            $("div .empty").hide();
                            alert(response.message);

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

            // Handle Edit button click
            $(document).on('click', '.edit-campaign-btn', function(e) {
                const campaignId = $(this).attr('data-campaign-id');
                const campaignName = $(this).attr('data-campaign-name');
                const verificationFrequency = $(this).attr('data-verification-frequency');
                // Populate modal fields
                $('#edit_campaign_id').val(campaignId);
                $('#edit_campaign_name').val(campaignName);
                $('#edit_verification_frequency').val(verificationFrequency);

            });


            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }


            // Handle Edit form submission
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
                            //updateCampaignCard(campaignCard, campaign);

                            //alert(JSON.stringify(campaignCard, null, 2));

                            campaignCard.find('.card-title').text(escapeHtml(campaign.name));
                            campaignCard.find('.verification-frequency').text(escapeHtml(capitalizeFirstLetter(campaign.verification_frequency)));
                            campaignCard.find('.edit-campaign-btn')
                                .attr('data-campaign-name', escapeHtml(campaign.name))
                                .attr('data-verification-frequency', escapeHtml(campaign.verification_frequency));

                            $('#edit-campaign-modal').modal('hide');
                            alert(response.message);
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
                            <p><strong>Verification:</strong> <span class="verification-frequency">${escapeHtml(capitalizeFirstLetter(campaign.verification_frequency) )}</span></p>
                            <div class="mt-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col">
                                        <div class="text-muted">Backlinks Status</div>
                                        <div class="progress progress-separated mb-3">
                                            <!-- Progress bars will be updated dynamically -->
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
                                <a href="backlink_management.php?campaign_id=${campaign.id}&csrf_token=${window.csrfToken}" class="btn btn-primary">
                                    Manage Backlinks
                                </a>
                                <button type="button" class="btn btn-ghost-warning edit-campaign-btn" 
                                    data-campaign-id="${campaign.id}"
                                    data-campaign-name="${escapeHtml(campaign.name)}"
                                    data-verification-frequency="${escapeHtml( campaign.verification_frequency)}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#edit-campaign-modal">
                                    Edit
                                </button>
                                <form class="delete-campaign-form d-inline">
                                    <input type="hidden" name="csrf_token" value="${window.csrfToken}">
                                    <input type="hidden" name="action" value="delete_campaign">
                                    <input type="hidden" name="campaign_id" value="${campaign.id}">
                                    <button type="submit" class="btn btn-ghost-danger">
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