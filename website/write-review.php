<?php
require __DIR__ . '/includes/bootstrap.php';
$memberId = get_int('member');
$member = $memberId ? get_member($pdo,$memberId) : null;
render_header(['title'=>'Write a Review']);
?>
<section class="container content-page narrow-page">
<?php if (!$member): ?>
  <div class="no-results">The member listing could not be found.</div>
<?php else: ?>
  <a class="back-link" href="<?= e(site_url('search.php?listing='.$memberId)) ?>">&laquo; Back to Listing</a>
  <div class="card form-card"><h1>Write a Review for <?= e(member_name($member)) ?></h1>
    <?= do_shortcodes('[form=member_review_form]',['member_id'=>$memberId]) ?>
  </div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
