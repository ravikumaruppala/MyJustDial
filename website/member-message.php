<?php
require __DIR__ . '/includes/bootstrap.php';
$memberId = get_int('member');
$member = $memberId ? get_member($pdo,$memberId) : null;
render_header(['title'=>'Send a Message']);
?>
<section class="container content-page narrow-page">
<?php if (!$member): ?>
  <div class="no-results">The member listing could not be found.</div>
<?php else: ?>
  <h1>Contact <?= e(member_name($member)) ?></h1>
  <p>Use this form to send an inquiry to this member.</p>
  <div class="card form-card"><?= do_shortcodes('[form=contact_form]',['member_id'=>$memberId]) ?></div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
