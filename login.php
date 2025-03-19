<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf_helper.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= getCSRFToken() ?>">
    <title>Login - Backlink Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .login-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .image-section {
            flex: 1;
            position: relative;
            background: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') no-repeat center center;
            background-size: cover;
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .image-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(32, 107, 196, 0.7), rgba(0, 0, 0, 0.5));
            z-index: 1;
        }

        .image-text {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 2rem;
        }

        .image-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .image-text p {
            font-size: 1.2rem;
            opacity: 0.9;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .form-section {
            flex: 1;
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
        }

        .login-form {
            width: 100%;
            max-width: 400px;
        }

        .login-form .card {
            border: none;
            box-shadow: none;
        }

        .login-form .card-header {
            background: transparent;
            border-bottom: none;
            padding-bottom: 0;
        }

        .login-form .card-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #206bc4;
            margin-bottom: 1.5rem;
        }

        .login-form .form-label {
            font-weight: 500;
            color: #495057;
        }

        .login-form .form-control {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .login-form .form-control:focus {
            border-color: #206bc4;
            box-shadow: 0 0 0 3px rgba(32, 107, 196, 0.1);
        }

        .login-form .btn-primary {
            background-color: #206bc4;
            border: none;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .login-form .btn-primary:hover {
            background-color: #1a5aa3;
        }

        .login-form .alert {
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
            }

            .image-section {
                min-height: 300px;
            }

            .image-text h1 {
                font-size: 1.75rem;
            }

            .image-text p {
                font-size: 1rem;
            }

            .form-section {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Image Section with Overlay Text -->
        <div class="image-section">
            <div class="image-text">
                <h1>Welcome to Backlink Manager</h1>
                <p>Effortlessly manage your campaigns and track your backlinks with ease.</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <div class="login-form">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>
                        <form action="process_login.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>