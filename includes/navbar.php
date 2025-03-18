<nav class="navbar navbar-expand navbar-light">
    <div class="container-fluid">
        <!-- Navbar Brand -->
        <a class="navbar-brand" href="#">Backlink Manager</a>

        <!-- Navbar Menu -->
        <div class="navbar-nav ms-auto">
            <!-- User Greeting -->
            <span class="nav-item navbar-text me-3">
                Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
            </span>
            <!-- Navigation Links with Active Check -->
            <a class="nav-item nav-link <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php">Dashboard</a>
            <a class="nav-item nav-link <?= (basename($_SERVER['PHP_SELF']) == 'campaign_management.php' || basename($_SERVER['PHP_SELF']) == 'backlink_management.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>campaigns/campaign_management.php">Campaign Management</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a class="nav-item nav-link <?= ((basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'form.php') && strpos($_SERVER['REQUEST_URI'], 'users') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>users/index.php">User Management</a>
            <?php endif; ?>
            <a class="nav-item nav-link <?= (basename($_SERVER['PHP_SELF']) == 'proxymanager.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>proxymanager.php">Proxy Management</a>
            <a class="nav-item nav-link <?= (basename($_SERVER['PHP_SELF']) == 'logout.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>logout.php">Logout</a>
        </div>
    </div>
</nav>