<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_member()) { header('Location: ' . site_url('member-dashboard.php')); exit; }
$planKey = get_str('plan', 'free');
$plan = plan_by_key($planKey);
if (!$plan) { $planKey='free'; $plan=plan_by_key('free'); }
$formName = $planKey === 'free' ? 'checkout_free_form' : 'checkout_paid_form';
render_header(['title'=>'Create Account']);
?>
<section class="container account-page">
  <p class="eyebrow">Create Account</p>
  <h1><?= e($plan['name']) ?> Membership</h1>
  <p class="account-plan"><?= e($site['currency']) . number_format((float)$plan['monthly'],0) ?>/month · <?= e($site['currency']) . number_format((float)$plan['yearly'],0) ?>/year</p>
  <div class="card form-card account-form-card">
    <h2><?= $planKey === 'free' ? 'Create Your Free Account' : 'Enter Your Details' ?></h2>
    <p><?= $planKey === 'free' ? 'Complete the required fields to create your member account.' : 'Complete the required fields to create your membership account. Payment processing is not enabled in this project yet.' ?></p>
    <?= do_shortcodes('[form='.$formName.']', ['plan'=>$planKey]) ?>
  </div>
</section>
<?php render_footer(); ?>
