<?php
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/config/ProxyManager.php';

// Initialize ProxyManager
$proxyManager = new ProxyManager();

// Handle proxy addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_proxy'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }

    $ip = filter_input(INPUT_POST, 'ip', FILTER_VALIDATE_IP);
    $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($ip && $port) {
        $proxyManager->addProxy($ip, $port, $username, $password);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle proxy removal (we'll use a simple GET request with CSRF protection for demo)
// Handle proxy removal
if (isset($_GET['remove_proxy']) && isset($_GET['ip']) && isset($_GET['port'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('CSRF validation failed');
    }
    $ip = filter_input(INPUT_GET, 'ip', FILTER_VALIDATE_IP);
    $port = filter_input(INPUT_GET, 'port', FILTER_VALIDATE_INT);
    if ($ip && $port) {
        $proxyManager->removeProxy($ip, $port);
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
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-network me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M6 9a6 6 0 1 0 12 0a6 6 0 0 0 -12 0" />
                                        <path d="M12 15v6" />
                                        <path d="M12 21h-6v-6h6z" />
                                        <path d="M12 21h6v-6h-6z" />
                                    </svg>
                                    IP Address
                                </label>
                                <input type="text" class="form-control" name="ip" required
                                    placeholder="192.168.1.1" pattern="\d+\.\d+\.\d+\.\d+">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plug me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M9.785 6l8.215 8.215l-2.054 2.054a5.81 5.81 0 1 1 -8.215 -8.215l2.054 -2.054z" />
                                        <path d="M4 20l3.5 -3.5" />
                                        <path d="M15 4l-3.5 3.5" />
                                        <path d="M20 9l-3.5 3.5" />
                                    </svg>
                                    Port
                                </label>
                                <input type="number" class="form-control" name="port" required
                                    min="1" max="65535" placeholder="8080">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="7" r="4" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    Username (optional)
                                </label>
                                <input type="text" class="form-control" name="username"
                                    placeholder="Username">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <rect x="5" y="11" width="14" height="10" rx="2" />
                                        <circle cx="12" cy="16" r="1" />
                                        <path d="M8 11v-4a4 4 0 0 1 8 0v4" />
                                    </svg>
                                    Password (optional)
                                </label>
                                <input type="text" class="form-control" name="password"
                                    placeholder="Password">
                            </div>
                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plus me-1" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 5l0 14" />
                                        <path d="M5 12l14 0" />
                                    </svg>
                                    Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Proxy List -->
            <div class="card">
                <div class="card-header">
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
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Port</th>
                                <th>Username</th>
                                <th>Last Used</th>
                                <th>Usage Stats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proxies as $proxy): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proxy['ip']) ?></td>
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
                                        <a href="?remove_proxy=1&ip=<?= urlencode($proxy['ip']) ?>&port=<?= urlencode($proxy['port']) ?>&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>"
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proxies)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
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
</body>

</html>