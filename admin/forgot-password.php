<?php
require __DIR__ . '/../includes/bootstrap.php';
$base = e($config['base_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password | MyJustDial</title>
<link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/images/myjustdial-logo.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap">
<link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/ui-refresh.css">
</head>
<body class="lg-page">
<main class="lg-card">
    <h1>Reset Password</h1>
    <p class="lg-sub">Password reset by email is not set up on this installation yet.</p>
    <p class="lg-note">Ask the person who manages this site to reset your administrator password, then log in with the new one.</p>
    <a class="lg-link" href="<?= $base ?>/admin/login.php">Back to Log In</a>
</main>
</body>
</html>
