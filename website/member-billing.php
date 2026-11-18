<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
render_header(['title'=>'Billing Information']);
?>
<section class="container content-page narrow-page member-form-page"><div class="card form-card"><h1>Billing Information</h1>
<p><strong>Membership Plan:</strong> <?= e($member['plan_name']) ?></p>
<p><strong>Member ID:</strong> #<?= (int)$member['user_id'] ?></p>
<p><strong>Status:</strong> <?= (int)$member['active'] === (int)$site['member_active'] ? 'Active' : 'Inactive' ?></p>
<p>Payment processing is not configured in the current project.</p>
<a class="btn btn-primary" href="<?= e(site_url('member-dashboard.php')) ?>">Back to Dashboard</a></div></section>
<?php render_footer(); ?>
