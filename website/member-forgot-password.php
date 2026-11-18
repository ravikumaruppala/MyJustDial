<?php
require __DIR__ . '/includes/bootstrap.php';
$token = get_str('token');
render_header(['title'=>'Reset Member Password']);
?>
<section class="container content-page narrow-page">
  <div class="card form-card">
    <h1>Reset Member Password</h1>
    <?php if ($token !== ''): ?>
      <p>Choose a new password. Reset links expire after one hour.</p>
      <?= do_shortcodes('[form=member_password_reset_form]', ['reset_token'=>$token]) ?>
    <?php else: ?>
      <p>Enter your account email address and, if the account exists, we will send a secure reset link.</p>
      <?= do_shortcodes('[form=member_password_reset_request_form]') ?>
    <?php endif; ?>
    <p><a href="<?= e(site_url('member-login.php')) ?>">Back to member login</a></p>
  </div>
</section>
<?php render_footer(); ?>
