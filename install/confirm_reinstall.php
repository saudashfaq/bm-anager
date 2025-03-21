<?php
session_start();

// Redirect to index.php if no reinstallation is needed
if (empty($_SESSION['reinstall'])) {
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
    <style>
        body {
            background: #f4f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .confirm-container {
            max-width: 600px;
            width: 100%;
            margin: 1rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .confirm-header {
            background: linear-gradient(135deg, #d63939 0%, #b32d2d 100%);
            color: #ffffff;
            padding: 2rem;
            text-align: center;
        }

        .confirm-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }

        .confirm-header p {
            font-size: 1rem;
            opacity: 0.8;
            margin: 0.5rem 0 0;
        }

        .confirm-body {
            padding: 2rem;
            text-align: center;
        }

        .confirm-body .alert {
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .confirm-body .btn {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .confirm-body .btn-primary {
            background-color: #206bc4;
            border: none;
        }

        .confirm-body .btn-primary:hover {
            background-color: #1a5aa3;
        }

        .confirm-body .btn-secondary {
            background-color: #6c757d;
            border: none;
        }

        .confirm-body .btn-secondary:hover {
            background-color: #5a6268;
        }

        .confirm-body .btn svg {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="confirm-container">
        <div class="confirm-header">
            <h2>Confirm Reinstallation</h2>
            <p>Proceed with caution</p>
        </div>
        <div class="confirm-body">
            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-alert-triangle me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 9v2m0 4v.01" />
                        <path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.48 0l-7.1 12.25a2 2 0 0 0 1.84 2.75z" />
                    </svg>
                    <?= htmlspecialchars($_SESSION['warning']) ?>
                </div>
            <?php endif; ?>
            <form action="process_install.php" method="POST" style="display: inline;">
                <input type="hidden" name="skip_install_check" value="1">
                <?php
                // Preserve form data
                if (isset($_SESSION['form_data'])) {
                    foreach ($_SESSION['form_data'] as $key => $value) {
                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                }
                ?>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M5 12l5 5l10 -10" />
                    </svg>
                    Yes, Proceed
                </button>
            </form>
            <a href="index.php" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M18 6l-12 12" />
                    <path d="M6 6l12 12" />
                </svg>
                Cancel
            </a>
        </div>
    </div>
</body>

</html>