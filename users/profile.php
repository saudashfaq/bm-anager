<?php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/validationHelper.php';

// Ensure the user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

// Fetch the logged-in admin's details
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location:" . BASE_URL . "index.php");
    exit;
}

$username = $user['username'];
$email = $user['email'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body class="page">
    <!-- Navbar -->
    <?php require_once __DIR__ . '/../includes/navbar.php' ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Update Profile</h2>
            </div>
            <div class="card-body">
                <!-- Alert container for success/error messages -->
                <div id="alert-container" class="mb-3" style="display: none;">
                    <div id="alert-message" class="alert alert-dismissible" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>

                <form id="profile-form" method="POST">
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
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">Update Profile</button>
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
            $('#profile-form').on('submit', function(e) {
                e.preventDefault();

                // Clear previous messages
                $('#alert-container').hide();
                $('.error-message').hide().text('');

                $.ajax({
                    url: 'update_profile.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        const alertMessage = $('#alert-message');
                        alertMessage.removeClass('alert-success alert-danger');

                        if (response.success) {
                            alertMessage
                                .addClass('alert-success')
                                .html(response.message + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                            $('#alert-container').show();
                        } else {
                            alertMessage
                                .addClass('alert-danger')
                                .html(response.message + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                            $('#alert-container').show();

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
                        const alertMessage = $('#alert-message');
                        alertMessage
                            .removeClass('alert-success alert-danger')
                            .addClass('alert-danger')
                            .html('An error occurred while processing your request. Please try again. <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                        $('#alert-container').show();
                    }
                });
            });
        });
    </script>
</body>

</html>