<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  verifyCsrf();
  $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
  $q=$pdo->prepare('SELECT password FROM users_data WHERE user_id=?');$q->execute([(int)$member['user_id']]);$stored=(string)$q->fetchColumn();
  if(!password_verify($current,$stored))$error='Current password is incorrect.';
  elseif(strlen($new)<8||!preg_match('/[A-Za-z]/',$new)||!preg_match('/[0-9]/',$new))$error='New password must be at least 8 characters and contain letters and numbers.';
  elseif($new!==$confirm)$error='New passwords do not match.';
  else{$pdo->prepare('UPDATE users_data SET password=? WHERE user_id=?')->execute([password_hash($new,PASSWORD_BCRYPT),(int)$member['user_id']]);$success='Your password has been updated.';}
}
render_header(['title'=>'Change Password']);
?>
<section class="container content-page narrow-page member-form-page"><div class="card form-card"><h1>Change Password</h1>
<?php if($error):?><div class="mjd-form-msg is-error"><?=e($error)?></div><?php endif?>
<?php if($success):?><div class="mjd-form-msg is-success"><?=e($success)?></div><?php endif?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>">
<label>Current Password<input class="mjd-control" type="password" name="current_password" required></label>
<label>New Password<input class="mjd-control" type="password" name="new_password" minlength="8" required></label>
<label>Confirm New Password<input class="mjd-control" type="password" name="confirm_password" minlength="8" required></label>
<button class="btn btn-primary" type="submit">Update Password</button></form></div></section>
<?php render_footer(); ?>
