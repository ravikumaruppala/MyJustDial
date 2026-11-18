<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
render_header(['title'=>'Profile Photo','member_portal'=>true,'member_section'=>'profile']);
?>
<section class="member-form-page member-profile-photo-page">
  <div class="member-form-kicker">Basic Information</div>
  <div class="card form-card"><?= render_form('profile_photo_handler_form', ['member_id'=>$member['user_id']]) ?></div>
</section>
<?php render_footer(); ?>
