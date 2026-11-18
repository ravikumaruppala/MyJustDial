<?php
require __DIR__ . '/../includes/bootstrap.php';

$message = '';
$error = '';
$token = trim((string)($_GET['token'] ?? ''));
$resetAdminId = 0;

if ($token !== '' && tableExists($pdo, 'users_meta')) {
    $stmt = $pdo->prepare("SELECT database_id FROM users_meta WHERE `database`='administrators' AND `key`='admin_reset_token' AND `value`=? LIMIT 1");
    $stmt->execute([hash('sha256', $token)]);
    $resetAdminId = (int)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['new_password'])) {
        $id = (int)($_POST['admin_id'] ?? 0);
        $postedToken = trim((string)($_POST['token'] ?? ''));
        $stmt = $pdo->prepare("SELECT database_id FROM users_meta WHERE `database`='administrators' AND `key`='admin_reset_token' AND `value`=? AND database_id=? LIMIT 1");
        $stmt->execute([hash('sha256',$postedToken),$id]);
        $validId = (int)$stmt->fetchColumn();
        $exp = $pdo->prepare("SELECT `value` FROM users_meta WHERE `database`='administrators' AND `key`='admin_reset_expires' AND database_id=? LIMIT 1");
        $exp->execute([$id]);
        $expires = (int)$exp->fetchColumn();
        $password = (string)$_POST['new_password'];
        $confirm = (string)$_POST['confirm_password'];
        if ($validId <= 0 || $expires < time()) {
            $error = 'This password reset link is invalid or has expired. Request a new link.';
        } elseif (strlen($password) < 8 || $password !== $confirm) {
            $error = 'Passwords must match and contain at least 8 characters.';
        } else {
            $pdo->prepare('UPDATE administrators SET password_hash=? WHERE admin_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            $pdo->prepare("DELETE FROM users_meta WHERE `database`='administrators' AND database_id=? AND `key` IN ('admin_reset_token','admin_reset_expires')")->execute([$id]);
            $message = 'Your administrator password has been updated. You can now log in.';
            $resetAdminId = 0;
            $token = '';
        }
    } else {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if ($email) {
            $stmt = $pdo->prepare("SELECT admin_id FROM administrators WHERE email=? AND status='active' LIMIT 1");
            $stmt->execute([$email]);
            $id = (int)$stmt->fetchColumn();
            if ($id > 0 && tableExists($pdo, 'users_meta')) {
                $raw = bin2hex(random_bytes(32));
                $hash = hash('sha256',$raw);
                $now = date('YmdHis');
                $pdo->prepare("DELETE FROM users_meta WHERE `database`='administrators' AND database_id=? AND `key` IN ('admin_reset_token','admin_reset_expires')")->execute([$id]);
                $meta = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('administrators',?,?,?,?)");
                $meta->execute([$id,'admin_reset_token',$hash,$now]);
                $meta->execute([$id,'admin_reset_expires',(string)(time()+3600),$now]);
                $url = rtrim($config['base_url'],'/') . '/admin/forgot-password.php?token=' . urlencode($raw);
                $host = preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $from = 'noreply@' . ($host ?: 'localhost');
                $body = "A password reset was requested for your administrator account.\n\nOpen this link within one hour:\n".$url."\n\nIf you did not request this, ignore this email.";
                @mail($email,'MyJustDial administrator password reset',$body,"From: ".$from);
            }
        }
        $message = 'If an active administrator account matches that email and mail delivery is configured, a reset link has been sent.';
    }
}

$base = e($config['base_url']);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password | MyJustDial</title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/ui-refresh.css">
</head><body class="lg-page">
<main class="lg-card">
<h1>Reset Administrator Password</h1>
<?php if ($message): ?><div class="lg-alert" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="lg-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($resetAdminId > 0 && $token !== ''): ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
<input type="hidden" name="admin_id" value="<?= $resetAdminId ?>">
<input type="hidden" name="token" value="<?= e($token) ?>">
<div class="lg-field"><input type="password" name="new_password" placeholder="New password (minimum 8 characters)" minlength="8" required></div>
<div class="lg-field"><input type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required></div>
<button class="lg-btn" type="submit">Update Password</button>
</form>
<?php elseif ($token === '' && !$message): ?>
<p class="lg-sub">Enter your administrator email address. We'll send a secure reset link if the account exists.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
<div class="lg-field"><input type="email" name="email" placeholder="Admin email address" required></div>
<button class="lg-btn" type="submit">Send Reset Link</button>
</form>
<?php endif; ?>
<a class="lg-link" href="<?= $base ?>/admin/login.php">Back to Log In</a>
</main></body></html>
