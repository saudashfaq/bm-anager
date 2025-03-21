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
    $activeCampaigns = $campaignsStats['enabled'] ?? 0;
    $inactiveCampaigns = $campaignsStats['disabled'] ?? 0;

    // Alive/Dead backlinks
    $backlinksStmt = $pdo->query("SELECT status, COUNT(*) as count FROM backlinks GROUP BY status");
    $backlinksStats = [];
    while ($row = $backlinksStmt->fetch(PDO::FETCH_ASSOC)) {
        $backlinksStats[$row['status']] = $row['count'];
    }
    $aliveBacklinks = $backlinksStats['alive'] ?? 0;
    $deadBacklinks = $backlinksStats['dead'] ?? 0;
    $pendingBacklinks = $backlinksStats['pending'] ?? 0;
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
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .welcome-message h2 {
            color: #206bc4;
            font-size: 2.25rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .welcome-message p {
            color: #495057;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .welcome-message .btn {
            background-color: #206bc4;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .welcome-message .btn:hover {
            background-color: #1a5aa3;
        }

        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 0.75rem;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stats-card .card-body {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stats-card .icon-container {
            flex-shrink: 0;
        }

        .stats-card .icon-container svg {
            width: 40px;
            height: 40px;
            color: #206bc4;
        }

        .stats-card .card-title {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .stats-card .card-value {
            color: #212529;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .chart-container {
            position: relative;
            margin-top: 2rem;
            padding: 1.5rem;
            background: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .chart-container canvas {
            max-height: 300px;
        }

        .no-data-message {
            text-align: center;
            color: #6c757d;
            padding: 2rem;
            font-size: 1.1rem;
        }
    </style>
</head>

<body class="theme-light">
    <div class="page">
        <?php include_once __DIR__ . '/includes/navbar.php'; ?>
        <div class="container mt-4">
            <?php if ($totalCampaigns == 0): ?>
                <div class="welcome-message mt-4">
                    <h2>Welcome to Your Backlink Manager!</h2>
                    <p>It looks like you haven’t created any campaigns yet. Start by creating your first campaign to manage your backlinks effectively.</p>
                    <a href="<?= BASE_URL ?>campaigns/campaign_management.php" class="btn btn-primary">Create Your First Campaign</a>
                </div>
            <?php elseif ($totalBacklinks == 0): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-campaign" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M3 3h18v18h-18z" />
                                        <path d="M9 9h6v6h-6z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Total Campaigns</div>
                                    <div class="card-value"><?= $totalCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M5 12l5 5l10 -10" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Active Campaigns</div>
                                    <div class="card-value"><?= $activeCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M18 6l-12 12" />
                                        <path d="M6 6l12 12" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Inactive Campaigns</div>
                                    <div class="card-value"><?= $inactiveCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-message mt-4">
                    <p>You have campaigns, but no backlinks yet! Add backlinks to start tracking their status.</p>
                    <a href="<?= BASE_URL ?>campaigns/backlink_management.php" class="btn btn-primary">Add Backlinks</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-campaign" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M3 3h18v18h-18z" />
                                        <path d="M9 9h6v6h-6z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Total Campaigns</div>
                                    <div class="card-value"><?= $totalCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                        <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Total Backlinks</div>
                                    <div class="card-value"><?= $totalBacklinks ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M5 12l5 5l10 -10" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Active Campaigns</div>
                                    <div class="card-value"><?= $activeCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M18 6l-12 12" />
                                        <path d="M6 6l12 12" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Inactive Campaigns</div>
                                    <div class="card-value"><?= $inactiveCampaigns ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="card-body">
                                <div class="icon-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                        <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="card-title">Backlink Status</div>
                                    <div class="card-value">
                                        <span style="color: #2fb344;"><?= $aliveBacklinks ?> Alive</span> /
                                        <span style="color: #d63939;"><?= $deadBacklinks ?> Dead</span>
                                    </div>
                                </div>
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
                    type: 'doughnut',
                    data: {
                        labels: ['Alive', 'Dead', 'Pending'],
                        datasets: [{
                            data: [<?= $aliveBacklinks ?>, <?= $deadBacklinks ?>, <?= $pendingBacklinks ?>],
                            backgroundColor: ['#2fb344', '#d63939', '#f1c40f'],
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Backlink Status Distribution',
                                font: {
                                    size: 18,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }
        });
    </script>
</body>

</html>