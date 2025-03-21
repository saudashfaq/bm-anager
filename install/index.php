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
        body {
            background: #f4f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .install-container {
            max-width: 600px;
            width: 100%;
            margin: 1rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .install-header {
            background: linear-gradient(135deg, #206bc4 0%, #1a5aa3 100%);
            color: #ffffff;
            padding: 2rem;
            text-align: center;
        }

        .install-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }

        .install-header p {
            font-size: 1rem;
            opacity: 0.8;
            margin: 0.5rem 0 0;
        }

        .install-body {
            padding: 2rem;
        }

        .wizard-step {
            display: none;
        }

        .wizard-step.active {
            display: block;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label svg {
            width: 20px;
            height: 20px;
            color: #206bc4;
        }

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #206bc4;
            box-shadow: 0 0 0 3px rgba(32, 107, 196, 0.1);
        }

        .hr-text {
            margin: 1.5rem 0;
            color: #6c757d;
            font-weight: 500;
            position: relative;
            text-align: center;
        }

        .hr-text::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
            z-index: 0;
        }

        .hr-text span {
            background: #ffffff;
            padding: 0 1rem;
            position: relative;
            z-index: 1;
        }

        .wizard-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .wizard-nav .btn {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .wizard-nav .btn-primary {
            background-color: #206bc4;
            border: none;
        }

        .wizard-nav .btn-primary:hover {
            background-color: #1a5aa3;
        }

        .wizard-nav .btn-secondary {
            background-color: #6c757d;
            border: none;
        }

        .wizard-nav .btn-secondary:hover {
            background-color: #5a6268;
        }

        .wizard-nav .btn svg {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
        }

        .alert {
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            margin-bottom: 2rem;
            background-color: #e9ecef;
        }

        .progress-bar {
            background-color: #206bc4;
            transition: width 0.3s ease;
        }
    </style>
</head>

<body>
    <div class="install-container">
        <div class="install-header">
            <h2>Backlink Management Software Installation</h2>
            <p>Follow the steps to set up your application</p>
        </div>
        <div class="install-body">
            <!-- Progress Bar -->
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="install-form" action="process_install.php" method="POST">
                <!-- Step 1: Purchase Key -->
                <div class="wizard-step active" data-step="1">
                    <h3 class="card-title mb-4">Step 1: Purchase Key</h3>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-key" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <circle cx="8" cy="15" r="4" />
                                <path d="M10.85 12.15l6.15 -6.15" />
                                <path d="M18 5l2 2" />
                                <path d="M15 8l2 2" />
                            </svg>
                            Purchase Key
                        </label>
                        <input type="text" name="purchase_key" class="form-control" required value="123456789">
                    </div>
                    <div class="wizard-nav">
                        <button type="button" class="btn btn-secondary" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M5 12l6 6" />
                                <path d="M5 12l6 -6" />
                            </svg>
                            Back
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-right" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M13 18l6 -6" />
                                <path d="M13 6l6 6" />
                            </svg>
                            Next
                        </button>
                    </div>
                </div>

                <!-- Step 2: Database Configuration -->
                <div class="wizard-step" data-step="2">
                    <h3 class="card-title mb-4">Step 2: Database Configuration</h3>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-database" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <ellipse cx="12" cy="6" rx="8" ry="3" />
                                <path d="M4 6v6a8 3 0 0 0 16 0v-6" />
                                <path d="M4 12v6a8 3 0 0 0 16 0v-6" />
                            </svg>
                            Database Host
                        </label>
                        <input type="text" name="db_host" class="form-control" required value="localhost">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-database" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <ellipse cx="12" cy="6" rx="8" ry="3" />
                                <path d="M4 6v6a8 3 0 0 0 16 0v-6" />
                                <path d="M4 12v6a8 3 0 0 0 16 0v-6" />
                            </svg>
                            Database Name
                        </label>
                        <input type="text" name="db_name" class="form-control" required value="backlinks_manager">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <circle cx="12" cy="7" r="4" />
                                <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                            </svg>
                            Database User
                        </label>
                        <input type="text" name="db_user" class="form-control" required value="root">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <circle cx="12" cy="16" r="1" />
                                <path d="M8 11v-4a4 4 0 0 1 8 0v4" />
                            </svg>
                            Database Password
                        </label>
                        <input type="password" name="db_pass" class="form-control" value="root">
                    </div>
                    <div class="wizard-nav">
                        <button type="button" class="btn btn-secondary prev-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M5 12l6 6" />
                                <path d="M5 12l6 -6" />
                            </svg>
                            Back
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-right" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M13 18l6 -6" />
                                <path d="M13 6l6 6" />
                            </svg>
                            Next
                        </button>
                    </div>
                </div>

                <!-- Step 3: Site Configuration -->
                <div class="wizard-step" data-step="3">
                    <h3 class="card-title mb-4">Step 3: Site Configuration</h3>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-world" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <circle cx="12" cy="12" r="9" />
                                <path d="M3.6 9h16.8" />
                                <path d="M3.6 15h16.8" />
                                <path d="M11.5 3a17 17 0 0 0 0 18" />
                                <path d="M12.5 3a17 17 0 0 1 0 18" />
                            </svg>
                            Site URL
                        </label>
                        <input type="text" name="site_url" class="form-control" required value="https://example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-link" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                            </svg>
                            Base URL
                        </label>
                        <input type="text" name="base_url" class="form-control" placeholder="Sub folder if any" value="">
                    </div>
                    <div class="wizard-nav">
                        <button type="button" class="btn btn-secondary prev-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M5 12l6 6" />
                                <path d="M5 12l6 -6" />
                            </svg>
                            Back
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-right" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M13 18l6 -6" />
                                <path d="M13 6l6 6" />
                            </svg>
                            Next
                        </button>
                    </div>
                </div>

                <!-- Step 4: Admin Account -->
                <div class="wizard-step" data-step="4">
                    <h3 class="card-title mb-4">Step 4: Admin Account</h3>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <circle cx="12" cy="7" r="4" />
                                <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                            </svg>
                            Admin Username
                        </label>
                        <input type="text" name="admin_username" class="form-control" required value="admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-mail" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="M3 7l9 6l9 -6" />
                            </svg>
                            Admin Email
                        </label>
                        <input type="email" name="admin_email" class="form-control" required value="admin@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <circle cx="12" cy="16" r="1" />
                                <path d="M8 11v-4a4 4 0 0 1 8 0v4" />
                            </svg>
                            Admin Password
                        </label>
                        <input type="password" name="admin_password" class="form-control" required value="12345678">
                    </div>
                    <div class="wizard-nav">
                        <button type="button" class="btn btn-secondary prev-step">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12h14" />
                                <path d="M5 12l6 6" />
                                <path d="M5 12l6 -6" />
                            </svg>
                            Back
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 12l5 5l10 -10" />
                            </svg>
                            Install Software
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.wizard-step');
            const progressBar = document.querySelector('.progress-bar');
            let currentStep = 1;
            const totalSteps = steps.length;

            function updateProgress() {
                const progress = (currentStep / totalSteps) * 100;
                progressBar.style.width = `${progress}%`;
                progressBar.setAttribute('aria-valuenow', progress);
            }

            function showStep(step) {
                steps.forEach(s => s.classList.remove('active'));
                steps[step - 1].classList.add('active');
                currentStep = step;
                updateProgress();
            }

            document.querySelectorAll('.next-step').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentStep < totalSteps) {
                        showStep(currentStep + 1);
                    }
                });
            });

            document.querySelectorAll('.prev-step').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentStep > 1) {
                        showStep(currentStep - 1);
                    }
                });
            });

            // Initialize the first step
            showStep(1);
        });
    </script>
</body>

</html>