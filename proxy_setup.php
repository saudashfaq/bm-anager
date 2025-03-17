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
            <h2>Proxy Manager</h2>
            <p>Manage your proxy pool for backlink verification.</p>

            <!-- Add Proxy Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Add New Proxy</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_proxy" value="1">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" name="ip" required
                                    placeholder="192.168.1.1" pattern="\d+\.\d+\.\d+\.\d+">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" class="form-control" name="port" required
                                    min="1" max="65535" placeholder="8080">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Username (optional)</label>
                                <input type="text" class="form-control" name="username"
                                    placeholder="Username">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Password (optional)</label>
                                <input type="text" class="form-control" name="password"
                                    placeholder="Password">
                            </div>
                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Proxy List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Current Proxies</h3>
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
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proxies)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No proxies configured</td>
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