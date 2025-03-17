<?php
// campaign_management.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and has access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'user') {
    header("Location: ../dashboard.php");
    exit;
}

// Handle CRUD Operations (Add, Update, Delete campaigns & backlinks)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
        $campaign_name = trim($_POST['campaign_name']);
        $base_url = trim($_POST['base_url']);
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("INSERT INTO campaigns (user_id, campaign_name, base_url, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $campaign_name, $base_url]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_campaign') {
        $campaign_id = intval($_POST['campaign_id']);
        if ($_SESSION['role'] === 'admin' || checkCampaignOwnership($campaign_id, $_SESSION['user_id'])) {
            $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
            $stmt->execute([$campaign_id]);
        }
    }
}

// Function to check campaign ownership
function checkCampaignOwnership($campaign_id, $user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$campaign_id, $user_id]);
    return $stmt->fetch() ? true : false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core/dist/css/tabler.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2>Campaign Management</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_campaign">
            <div class="mb-3">
                <label for="campaign_name" class="form-label">Campaign Name</label>
                <input type="text" name="campaign_name" id="campaign_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="base_url" class="form-label">Base URL</label>
                <input type="url" name="base_url" id="base_url" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Verification Frequency:</label>
                <select name="verification_frequency" required>
                    <option value="daily">Daily</option>
                    <option value="twice_a_day">Twice a Day</option>
                    <option value="two_days">Every 2 Days</option>
                    <option value="weekly">Weekly</option>
                    <option value="two_weeks">Every 2 Weeks</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Campaign</button>
        </form>

        <h3 class="mt-5">Existing Campaigns</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Campaign Name</th>
                    <th>Base URL</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT * FROM campaigns");
                while ($row = $stmt->fetch()) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['campaign_name']}</td>
                            <td>{$row['base_url']}</td>
                            <td>
                                <form method='POST' style='display:inline;'>
                                    <input type='hidden' name='action' value='delete_campaign'>
                                    <input type='hidden' name='campaign_id' value='{$row['id']}'>
                                    <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>

</html>