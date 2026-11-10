<?php
require __DIR__ . '/../includes/bootstrap.php';
// This one-time page creates the administrators table when the SQL dump does not contain it.
$pdo->exec("CREATE TABLE IF NOT EXISTS administrators (
    admin_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
$error = '';
if ($adminCount > 0) {
    redirect('/admin/login.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['admin_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($name === '' || !$email || strlen($password) < 8 || $password !== $confirm) {
        $error = 'Enter a valid name and email. Passwords must match and contain at least 8 characters.';
    } else {
        $statement = $pdo->prepare('INSERT INTO administrators (admin_name, email, password_hash) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Admin account created. You can now sign in.');
        redirect('/admin/login.php');
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Setup | MyJustDial</title><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"><link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/admin.css"></head><body class="login-page"><div class="login-card"><div class="login-logo"><i class="fa fa-lightbulb-o"></i> MyJustDial</div><h2>Create Admin Account</h2><p>This setup page works only until the first administrator is created.</p><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><div class="form-group"><input class="form-control input-lg" name="admin_name" placeholder="Admin Name" required></div><div class="form-group"><input class="form-control input-lg" type="email" name="email" placeholder="Email Address" required></div><div class="form-group"><input class="form-control input-lg" type="password" name="password" placeholder="Password (minimum 8 characters)" minlength="8" required></div><div class="form-group"><input class="form-control input-lg" type="password" name="confirm_password" placeholder="Confirm Password" minlength="8" required></div><button class="btn btn-primary btn-lg btn-block">Create Admin</button></form></div></body></html>
