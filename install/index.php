<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Backlink Management Software</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <style>
        .page-center {
            min-height: 100vh;
            padding: 1rem;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <h2>Backlink Management Software Installation</h2>
            </div>

            <div class="card card-md">

                <div class="card-body">

                    <h2 class="card-title text-center mb-4">Backlink Management Software Installation</h2>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>

                    <?php
                        unset($_SESSION['error']);
                    endif;
                    ?>

                    <form action="process_install.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Purchase Key</label>
                            <input type="text" name="purchase_key" class="form-control" required value="123456789">
                        </div>

                        <div class="hr-text">Database Configuration</div>

                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control" required value="localhost">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control" required value="backlinks_manager">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Database User</label>
                            <input type="text" name="db_user" class="form-control" required value="root">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control" value="root">
                        </div>

                        <div class="hr-text">Site Configuration</div>

                        <div class="mb-3">
                            <label class="form-label">Site URL</label>
                            <input type="text" name="site_url" class="form-control" required value="https://example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Base URL</label>
                            <input type="text" name="base_url" class="form-control" placeholder="Sub folder if any" value="">
                        </div>


                        <div class="hr-text">Admin Account</div>

                        <div class="mb-3">
                            <label class="form-label">Admin Username</label>
                            <input type="text" name="admin_username" class="form-control" required value="admin">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Admin Email</label>
                            <input type="email" name="admin_email" class="form-control" required value="admin@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Admin Password</label>
                            <input type="password" name="admin_password" class="form-control" required value="12345678">
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M5 12l5 5l10 -10"></path>
                                </svg>
                                Install Software
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
</body>

</html>