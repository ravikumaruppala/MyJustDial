<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_member()) { header('Location: ' . site_url('member-dashboard.php')); exit; }
render_header(['title'=>'Member Login']);
?>
<section class="container content-page narrow-page">
  <div class="card form-card"><h1>Member Login</h1><?= do_shortcodes('[form=member_login]') ?>
  <p><a href="<?= e(site_url('member-forgot-password.php')) ?>">Forgot your password?</a></p></div>
</section>
<?php render_footer(); ?>
