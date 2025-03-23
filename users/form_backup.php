<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

$id = $username = $email = $role = "";
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch();
    if ($user) {
        $id = $user['id'];
        $username = $user['username'];
        $email = $user['email'];
        $role = $user['role'];
    }
}

$title = $id ? 'Edit User' : 'Add User';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.1.1/dist/css/tabler.min.css" />
</head>

<body class="page">
    <?php include_once __DIR__ . '/../includes/navbar.php' ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $title ?></h2>
            </div>
            <div class="card-body">
                <!-- Alert container for success/error messages -->
                <div class="alert alert-important alert-success alert-dismissible" role="alert" style="display: none;">
                    <div class="d-flex">
                        <div>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="icon alert-icon"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                fill="none"
                                stroke-linecap="round"
                                stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M5 12l5 5l10 -10"></path>
                            </svg>
                        </div>
                        <div id="alert-message">Wow! Everything worked!</div>
                    </div>
                    <a class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="close"></a>
                </div>

                <form id="user-form" method="POST">
                    <input type="hidden" name="id" value="<?= $id ?? '' ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                        <span class="error-message text-danger" style="display: none;"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                        <span class="error-message text-danger" style="display: none;"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <?= $id ? "(Leave blank to keep unchanged)" : "" ?></label>
                        <input type="password" name="password" class="form-control" <?= $id ? "" : "required" ?>>
                        <span class="error-message text-danger" style="display: none;"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="admin" <?= $role === "admin" ? "selected" : "" ?>>Admin</option>
                            <option value="user" <?= $role === "user" ? "selected" : "" ?>>User</option>
                        </select>
                        <span class="error-message text-danger" style="display: none;"></span>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle form submission with AJAX
            $('#user-form').on('submit', function(e) {
                e.preventDefault();

                // Clear previous messages
                $('#alert-container').hide();
                $('.error-message').hide().text('');

                $.ajax({
                    url: 'save.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        // Update alert classes and message
                        const alertDiv = $('div .alert');
                        const alertMessage = $('div #alert-message')
                        alertDiv.removeClass('alert-success alert-danger');

                        if (response.success) {
                            // Show success message
                            alertDiv.addClass('alert-success');
                            alertMessage.text(response.message);
                            alertDiv.show();

                            // Redirect to index.php after a short delay
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 5000);
                        } else {
                            // Show error message
                            alertDiv.addClass('alert-danger')
                            alertMessage.text(response.message);
                            alertDiv.show();

                            // Display validation errors if any
                            if (response.errors) {
                                $.each(response.errors, function(field, errors) {
                                    let $inputField = $(`[name="${field}"]`);
                                    if ($inputField.length) {
                                        let $errorContainer = $inputField.siblings('.error-message');
                                        $errorContainer.text(errors[0]).show();
                                    }
                                });
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle server errors
                        alertDiv.removeClass('alert-success').addClass('alert-danger')
                        alertMessage.text('An error occurred while processing your request. Please try again. ' + error);
                        alertDiv.show();
                    }
                });
            });
        });
    </script>
</body>

</html>