<?php
session_start();

// Check if we actually came from the installation process
if (!isset($_SESSION['reinstall']) || !$_SESSION['reinstall']) {
    header("Location: index.php");
    exit;
}

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'proceed') {
            // Restore form data from session
            if (isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])) {
                foreach ($_SESSION['form_data'] as $key => $value) {
                    $_POST[$key] = $value;
                }

                // Set a flag to skip the initial checks in process_install.php
                $_SESSION['skip_install_check'] = true;

                // Include the installation process
                require_once __DIR__ . '/process_install.php';
                exit;
            }
        } elseif ($_POST['action'] === 'cancel') {
            // Cancel installation
            unset($_SESSION['reinstall']);
            unset($_SESSION['form_data']);
            unset($_SESSION['warning']);
            header("Location: index.php");
            exit;
        }
    }
}

// Check if warning message exists
if (!isset($_SESSION['warning'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Reinstallation</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
    <div class="page">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <h2>Confirm Reinstallation</h2>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <div class="alert alert-warning">
                        <?= htmlspecialchars($_SESSION['warning']) ?>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" style="margin-right: 10px;">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M18 6l-12 12"></path>
                                    <path d="M6 6l12 12"></path>
                                </svg>
                                Cancel Installation
                            </button>
                        </form>

                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                            <input type="hidden" name="action" value="proceed">
                            <button type="submit" class="btn btn-success w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M5 12l5 5l10 -10"></path>
                                </svg>
                                Proceed with Reinstall
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
</body>

</html>