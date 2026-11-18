<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
render_header(['title'=>'About Me','member_portal'=>true,'member_section'=>'about']);
?>
<section class="member-form-page member-about-page">
  <div class="member-form-kicker">Basic Information</div>
  <div class="card form-card"><h1>Write about you and your company</h1><?= render_form('about_member_form', ['member_id'=>$member['user_id']]) ?></div>
</section>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
document.querySelectorAll('.mjd-ckeditor, textarea[name="about_me"]').forEach(function(el){ClassicEditor.create(el).catch(function(error){console.error(error);});});
</script>
<?php render_footer(); ?>
