<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

// Fetch campaigns
$stmt = $pdo->prepare("SELECT * FROM campaigns");
$stmt->execute();
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch backlinks
$stmt = $pdo->prepare("SELECT b.*, c.name AS campaign_name, u.username AS created_by_username FROM backlinks b 
    JOIN campaigns c ON b.campaign_id = c.id
    JOIN users u ON b.created_by = u.id
    ORDER BY b.created_at DESC");
$stmt->execute();
$backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Backlink Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
</head>

<body>
    <div class="container mt-4">
        <h2>Backlink Management</h2>

        <!-- Add Backlink Form -->
        <form action="add_backlink.php" method="post" class="card p-4 mb-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

            <div class="mb-3">
                <label class="form-label">Campaign:</label>
                <select name="campaign_id" class="form-select" required>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?= $campaign['id']; ?>"><?= htmlspecialchars($campaign['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Target URL (where to look for the backlink):</label>
                <input type="url" name="target_url" class="form-control" required>
                <small class="text-muted">This is the webpage where we should find your backlink</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Backlink URL:</label>
                <input type="url" name="backlink_url" class="form-control" required>
                <small class="text-muted">This is the URL being linked to (should match campaign's base URL)</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Anchor Text:</label>
                <input type="text" name="anchor_text" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Add Backlink</button>
        </form>

        <!-- Backlink List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Backlinks</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
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
                                <td><?= htmlspecialchars($backlink['target_url']); ?></td>
                                <td><?= htmlspecialchars($backlink['backlink_url']); ?></td>
                                <td><?= htmlspecialchars($backlink['anchor_text']); ?></td>
                                <td><?= htmlspecialchars($backlink['campaign_name']); ?></td>
                                <td><span class="badge bg-<?= $backlink['status'] === 'alive' ? 'success' : ($backlink['status'] === 'dead' ? 'danger' : 'warning') ?>"><?= $backlink['status']; ?></span></td>
                                <td><?= htmlspecialchars($backlink['created_by_username']); ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($backlink['created_at'])); ?></td>
                                <td>
                                    <a href="delete_backlink.php?id=<?= $backlink['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>