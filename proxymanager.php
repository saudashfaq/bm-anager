<?php
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/config/ProxyManager.php';


//TODO: do the real time testing of proxymanager.php
//TODO: Implement emails and notifications
//TODO: verify csrf token refreshes and verified on all post requests either ajax or POST




// Initialize ProxyManager
$proxyManager = new ProxyManager();

// Fetch and update free proxies if this is an automated job
if (isset($_GET['auto_update']) && $_GET['auto_update'] === '1') {
    // This is an automated job, fetch free proxies and update them
    $newFreeProxies = []; // This would be populated with newly scraped free proxies

    // Example of how to populate $newFreeProxies from a scraping source
    // This is just a placeholder - you would implement your actual scraping logic here
    try {
        // Example: Fetch free proxies from a public API or website
        // $newFreeProxies = fetchFreeProxiesFromSource();

        // For demonstration, let's assume we have some free proxies
        // In a real implementation, you would replace this with actual scraping logic
        $newFreeProxies = [
            [
                'ip' => '192.168.1.100',
                'port' => 8080,
                'type' => 'http',
                'username' => '',
                'password' => ''
            ],
            // Add more proxies as needed
        ];

        // Update the free proxies
        $removedIds = $proxyManager->updateAutoAddedFreeProxies($newFreeProxies);

        // Log the results
        error_log("Updated free proxies. Removed " . count($removedIds) . " proxies.");

        // If this is an AJAX request, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'removed_count' => count($removedIds),
                'removed_ids' => $removedIds
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Error updating free proxies: " . $e->getMessage());

        // If this is an AJAX request, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
}

// Handle proxy addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_proxy'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }

    $ipOrHost = filter_input(INPUT_POST, 'ip', FILTER_UNSAFE_RAW);
    $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
    $type = htmlspecialchars(strip_tags($_POST['type'] ?? 'http'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $is_free = filter_input(INPUT_POST, 'is_free', FILTER_VALIDATE_BOOLEAN);

    // Validate if it's an IP or a valid hostname
    if (
        filter_var($ipOrHost, FILTER_VALIDATE_IP) ||
        filter_var($ipOrHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
    ) {
        try {
            // Explicitly set auto_added to false for manually added proxies
            $proxyManager->addProxy($ipOrHost, $port, $type, $username, $password, $is_free, false);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            die($e->getMessage());
        }
    } else {
        die("Invalid IP or Hostname: " . htmlspecialchars($ipOrHost));
    }
}

// Handle proxy removal
if (isset($_GET['remove_proxy']) && isset($_GET['id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('CSRF validation failed');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($id) {
        $proxyManager->removeProxyById($id);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle multiple proxy removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_multiple') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }

    if (isset($_POST['proxy_ids']) && is_array($_POST['proxy_ids'])) {
        foreach ($_POST['proxy_ids'] as $proxyId) {
            $proxyId = filter_var($proxyId, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($proxyId) {
                $proxyManager->removeProxyById($proxyId);
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$proxies = json_decode(file_get_contents(__DIR__ . '/config/proxies.json'), true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Proxy Manager - Backlink Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
    <div class="page">
        <?php include_once __DIR__ . '/includes/navbar.php'; ?>
        <div class="container mt-4">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shield me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -3 7.5a12 12 0 0 1 -10 0a12 12 0 0 1 -3 -7.5a12 12 0 0 0 8.5 -3z" />
                </svg>
                Proxy Manager
            </h2>
            <p>Manage your proxy pool for backlink verification.</p>

            <!-- Add Proxy Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg>
                        Add New Proxy
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_proxy" value="1">

                        <!-- First Row: Basic Info -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-network me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M6 9a6 6 0 1 0 12 0a6 6 0 0 0 -12 0" />
                                        <path d="M12 15v6" />
                                        <path d="M12 21h-6v-6h6z" />
                                        <path d="M12 21h6v-6h-6z" />
                                    </svg>
                                    IP Address
                                </label>
                                <input type="text" class="form-control" name="ip" required placeholder="127.0.0.1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plug me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M9.785 6l8.215 8.215l-2.054 2.054a5.81 5.81 0 1 1 -8.215 -8.215l2.054 -2.054z" />
                                        <path d="M4 20l3.5 -3.5" />
                                        <path d="M15 4l-3.5 3.5" />
                                        <path d="M20 9l-3.5 3.5" />
                                    </svg>
                                    Port
                                </label>
                                <input type="number" class="form-control" name="port" required min="1" max="65535" placeholder="8080">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-network me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 9v-4" />
                                        <path d="M12 19v-4" />
                                        <path d="M4 12h4" />
                                        <path d="M16 12h4" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    Type
                                </label>
                                <select name="type" class="form-select" required>
                                    <option value="http">HTTP</option>
                                    <option value="https">HTTPS</option>
                                    <option value="socks4">SOCKS4</option>
                                    <option value="socks5">SOCKS5</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-coin me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                        <path d="M12 7v10" />
                                    </svg>
                                    Category
                                </label>
                                <select name="is_free" class="form-select" required>
                                    <option value="1">Free Proxy</option>
                                    <option value="0" selected>Paid Proxy</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="7" r="4" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    Username
                                </label>
                                <input type="text" class="form-control" name="username" placeholder="Username (optional)">
                            </div>
                        </div>

                        <!-- Second Row: Password and Submit -->
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label class="form-label d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <rect x="5" y="11" width="14" height="10" rx="2" />
                                        <circle cx="12" cy="16" r="1" />
                                        <path d="M8 11v-4a4 4 0 0 1 8 0v4" />
                                    </svg>
                                    Password
                                </label>
                                <input type="text" class="form-control" name="password" placeholder="Password (optional)">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 5l0 14" />
                                        <path d="M5 12l14 0" />
                                    </svg>
                                    Add Proxy
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Proxy List -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-list me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 6h11" />
                                <path d="M9 12h11" />
                                <path d="M9 18h11" />
                                <path d="M5 6v.01" />
                                <path d="M5 12v.01" />
                                <path d="M5 18v.01" />
                            </svg>
                            Current Proxies
                        </h3>
                        <div class="d-flex gap-2">
                            <select class="form-select m-2" id="proxyFilter">
                                <option value="all">All Proxies</option>
                                <option value="free">Free Proxies</option>
                                <option value="paid">Paid Proxies</option>
                            </select>
                            <button type="button" class="btn btn-primary m-2" id="updateFreeProxies">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-refresh me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                </svg>
                                Update Free Proxies
                            </button>
                            <button type="button" class="btn btn-danger d-none" id="removeSelected">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 7h16" />
                                    <path d="M10 11v6" />
                                    <path d="M14 11v6" />
                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                </svg>
                                Remove Selected (<span id="selectedCount">0</span>)
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th class="w-1">
                                    <input type="checkbox" class="form-check-input m-0 align-middle" id="selectAll">
                                </th>
                                <th>Type</th>
                                <th>IP Address</th>
                                <th>Port</th>
                                <th>Username</th>
                                <th>Last Used</th>
                                <th>Usage Stats</th>
                                <th>Failed Attempts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proxies as $proxy): ?>
                                <tr class="proxy-row <?= isset($proxy['is_free']) && $proxy['is_free'] ? 'free-proxy' : 'paid-proxy' ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input m-0 align-middle proxy-select"
                                            data-proxy-id="<?= htmlspecialchars($proxy['id']) ?>">
                                    </td>
                                    <td><?= htmlspecialchars(strtoupper($proxy['type'] ?? 'HTTP')) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (isset($proxy['is_free']) && $proxy['is_free']): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-coin me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" data-bs-toggle="tooltip" title="Free Proxy">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                                    <path d="M12 7v10" />
                                                </svg>
                                            <?php else: ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-coin-filled me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="currentColor" stroke-linecap="round" stroke-linejoin="round" data-bs-toggle="tooltip" title="Paid Proxy">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                                    <path d="M12 7v10" />
                                                </svg>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($proxy['ip']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($proxy['port']) ?></td>
                                    <td><?= htmlspecialchars($proxy['username'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($proxy['last_used'] ?: 'Never') ?></td>
                                    <td>
                                        <?php
                                        $usage = $proxy['usage'] ?? [];
                                        echo count($usage) > 0
                                            ? htmlspecialchars(count($usage) . ' URLs')
                                            : 'Not used';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $failedAttempts = $proxy['failed_attempts'] ?? 0;
                                        $maxAttempts = 5;
                                        $percentage = ($failedAttempts / $maxAttempts) * 100;

                                        if ($failedAttempts > 0):
                                            $colorClass = $failedAttempts >= 3 ? 'bg-danger' : ($failedAttempts >= 2 ? 'bg-warning' : 'bg-info');
                                        ?>
                                            <div class="progress" style="height: 20px;" data-bs-toggle="tooltip" title="<?= $failedAttempts ?> failed attempts">
                                                <div class="progress-bar <?= $colorClass ?>" role="progressbar" style="width: <?= $percentage ?>%;" aria-valuenow="<?= $failedAttempts ?>" aria-valuemin="0" aria-valuemax="<?= $maxAttempts ?>">
                                                    <?= $failedAttempts ?>/<?= $maxAttempts ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($proxy['id'])): ?>
                                            <a href="?remove_proxy=1&id=<?= urlencode($proxy['id']) ?>&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure you want to remove this proxy?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M4 7h16" />
                                                    <path d="M10 11v6" />
                                                    <path d="M14 11v6" />
                                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                                </svg>
                                                Remove
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No ID</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proxies)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plug-x me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M13.55 17.733a5.806 5.806 0 0 1 -7.356 -4.052a5.81 5.81 0 0 1 1.537 -5.627l2.054 -2.054l7.165 7.165" />
                                            <path d="M4 20l3.5 -3.5" />
                                            <path d="M15 4l-3.5 3.5" />
                                            <path d="M20 9l-3.5 3.5" />
                                            <path d="M16 16l4 4" />
                                            <path d="M20 16l-4 4" />
                                        </svg>
                                        No proxies configured
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const removeSelected = document.getElementById('removeSelected');
            const selectedCount = document.getElementById('selectedCount');
            const proxyFilter = document.getElementById('proxyFilter');
            const proxyRows = document.querySelectorAll('.proxy-row');
            const checkboxes = document.querySelectorAll('.proxy-select');

            // Handle select all
            selectAll.addEventListener('change', function() {
                const visibleCheckboxes = document.querySelectorAll('.proxy-row:not(.d-none) .proxy-select');
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            // Handle individual checkbox changes
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Handle proxy type filter
            proxyFilter.addEventListener('change', function() {
                const filterValue = this.value;
                proxyRows.forEach(row => {
                    if (filterValue === 'all') {
                        row.classList.remove('d-none');
                    } else if (filterValue === 'free') {
                        row.classList.toggle('d-none', !row.classList.contains('free-proxy'));
                    } else if (filterValue === 'paid') {
                        row.classList.toggle('d-none', !row.classList.contains('paid-proxy'));
                    }
                });
                // Reset select all checkbox when filter changes
                selectAll.checked = false;
                updateSelectedCount();
            });

            // Handle remove selected
            removeSelected.addEventListener('click', function() {
                if (!confirm('Are you sure you want to remove the selected proxies?')) {
                    return;
                }

                const selectedProxies = Array.from(document.querySelectorAll('.proxy-select:checked'))
                    .map(checkbox => checkbox.dataset.proxyId);

                if (selectedProxies.length === 0) return;

                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrfInput);

                // Add action type
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove_multiple';
                form.appendChild(actionInput);

                // Add selected proxy IDs
                selectedProxies.forEach(proxyId => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'proxy_ids[]';
                    input.value = proxyId;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            });

            function updateSelectedCount() {
                const selectedCount = document.querySelectorAll('.proxy-select:checked').length;
                document.getElementById('selectedCount').textContent = selectedCount;
                removeSelected.classList.toggle('d-none', selectedCount === 0);
            }

            // Handle update free proxies button
            const updateFreeProxiesBtn = document.getElementById('updateFreeProxies');
            if (updateFreeProxiesBtn) {
                updateFreeProxiesBtn.addEventListener('click', function() {
                    if (!confirm('Are you sure you want to update the free proxies? This will remove any automatically added free proxies that are no longer available.')) {
                        return;
                    }

                    // Show loading state
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Updating...';

                    // Make AJAX request to run the ProxyScraperValidator job
                    fetch('jobs/ProxyScraperValidator.php', {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(data => {
                            // The job doesn't return JSON, so we'll just show a success message
                            alert('Successfully updated free proxies. The page will now reload to show the updated proxies.');
                            // Reload the page to show updated proxies
                            window.location.reload();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating free proxies: ' + error.message);
                        })
                        .finally(() => {
                            // Reset button state
                            this.disabled = false;
                            this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-refresh me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg> Update Free Proxies';
                        });
                });
            }
        });
    </script>
</body>

</html>