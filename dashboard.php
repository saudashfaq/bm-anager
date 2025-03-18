<?php
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/config/db.php';

try {
    // Total campaigns
    $totalCampaignsStmt = $pdo->query("SELECT COUNT(*) FROM campaigns");
    $totalCampaigns = $totalCampaignsStmt->fetchColumn();

    // Total backlinks
    $totalBacklinksStmt = $pdo->query("SELECT COUNT(*) FROM backlinks");
    $totalBacklinks = $totalBacklinksStmt->fetchColumn();

    // Active/Inactive campaigns
    $campaignsStmt = $pdo->query("SELECT status, COUNT(*) as count FROM campaigns GROUP BY status");
    $campaignsStats = [];
    while ($row = $campaignsStmt->fetch(PDO::FETCH_ASSOC)) {
        $campaignsStats[$row['status']] = $row['count'];
    }
    $activeCampaigns = $campaignsStats['active'] ?? 0;
    $inactiveCampaigns = $campaignsStats['inactive'] ?? 0;

    // Alive/Dead backlinks
    $backlinksStmt = $pdo->query("SELECT status, COUNT(*) as count FROM backlinks GROUP BY status");
    $backlinksStats = [];
    while ($row = $backlinksStmt->fetch(PDO::FETCH_ASSOC)) {
        $backlinksStats[$row['status']] = $row['count'];
    }
    $aliveBacklinks = $backlinksStats['alive'] ?? 0;
    $deadBacklinks = $backlinksStats['dead'] ?? 0;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die('Database error occurred');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .welcome-message {
            text-align: center;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .welcome-message h2 {
            color: #206bc4;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .welcome-message p {
            color: #495057;
            font-size: 1.1rem;
        }

        .welcome-message .btn {
            margin-top: 1rem;
        }

        .stats-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .stats-card .card-body {
            padding: 1.5rem;
            text-align: center;
        }

        .stats-card .card-title {
            color: #495057;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stats-card .card-value {
            color: #206bc4;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .chart-container {
            position: relative;
            margin-top: 2rem;
            height: 300px;
        }

        .no-data-message {
            text-align: center;
            color: #6c757d;
            padding: 2rem;
        }

        .centered-row {
            display: flex;
            justify-content: center;
            gap: 1rem;
            /* Space between cards */
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <?php include_once __DIR__ . '/includes/navbar.php'; ?>
        <div class="container mt-4">
            <?php if ($totalCampaigns == 0): ?>
                <div class="welcome-message">
                    <h2>Welcome to Your Backlink Manager!</h2>
                    <p>It looks like you haven’t created any campaigns yet. Start by creating your first campaign to manage your backlinks effectively.</p>
                    <a href="<?= BASE_URL ?>campaigns/campaign_management.php" class="btn btn-primary">Create Your First Campaign</a>
                </div>
            <?php elseif ($totalBacklinks == 0): ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Total Campaigns</div>
                                <div class="card-value"><?= $totalCampaigns ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Active Campaigns</div>
                                <div class="card-value"><?= $activeCampaigns ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Inactive Campaigns</div>
                                <div class="card-value"><?= $inactiveCampaigns ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-message mt-4">
                    <p>You have campaigns, but no backlinks yet! Add backlinks to start tracking their status.</p>
                    <a href="<?= BASE_URL ?>campaigns/backlink_management.php" class="btn btn-primary">Add Backlinks</a>
                </div>
            <?php else: ?>
                <div class="centered-row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Total Campaigns</div>
                                <div class="card-value"><?= $totalCampaigns ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Total Backlinks</div>
                                <div class="card-value"><?= $totalBacklinks ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Alive Backlinks</div>
                                <div class="card-value"><?= $aliveBacklinks ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="card-title">Dead Backlinks</div>
                                <div class="card-value"><?= $deadBacklinks ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="backlinksChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>

    <script>
        $(document).ready(function() {
            if (document.getElementById('backlinksChart')) {
                const ctx = document.getElementById('backlinksChart').getContext('2d');
                const backlinksChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Alive Backlinks', 'Dead Backlinks'],
                        datasets: [{
                            data: [<?= $aliveBacklinks ?>, <?= $deadBacklinks ?>],
                            backgroundColor: ['#2fb344', '#d63939'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Backlink Status Distribution'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>