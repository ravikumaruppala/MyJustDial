<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
render_header(['title'=>'Listing Details','member_portal'=>true,'member_section'=>'listing']);
?>
<section class="member-form-page member-listing-page">
  <div class="member-form-kicker">Basic Information</div>
  <div class="card form-card"><h1>Share More Details</h1><?= render_form('listing_additional_details_form', ['member_id'=>$member['user_id']]) ?></div>
</section>
<?php render_footer(); ?>
