<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!tableExists($pdo, 'administrators')) {
    redirect('/admin/setup.php');
}
if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $statement = $pdo->prepare('SELECT admin_id, admin_name, email, password_hash, status FROM administrators WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $admin = $statement->fetch();
    if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['admin_id'];
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['admin_email'] = $admin['email'];
        redirect('/admin/dashboard.php');
    }
    $error = 'Invalid email address or password.';
}
$base = e($config['base_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | MyJustDial</title>
<link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/images/myjustdial-logo.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/ui-refresh.css">
</head>
<body class="lg-page">
<main class="lg-card">
    <h1>Admin Area Login</h1>
    <p class="lg-sub">Enter <b>Account Email Address</b> and <b>Password</b></p>
    <?php if ($error): ?><div class="lg-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="lg-field"><input type="email" name="email" placeholder="Enter Email Address" aria-label="Email address" required autofocus></div>
        <div class="lg-field">
            <input type="password" name="password" id="loginPassword" placeholder="Enter Password" aria-label="Password" required>
            <button type="button" class="lg-eye" id="togglePassword" aria-label="Show password" aria-pressed="false" title="Show password"><i class="fa fa-eye"></i></button>
        </div>
        <button class="lg-btn" type="submit">Log In Now</button>
    </form>
    <a class="lg-link" href="<?= $base ?>/admin/forgot-password.php">Forgot Password? Click to Reset</a>
</main>
<script>
(function () {
    var input = document.getElementById('loginPassword'), btn = document.getElementById('togglePassword'), icon = btn.querySelector('i');
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        var label = show ? 'Hide password' : 'Show password';
        btn.setAttribute('aria-label', label); btn.title = label;
        input.focus();
    });
}());
</script>
</body>
</html>
