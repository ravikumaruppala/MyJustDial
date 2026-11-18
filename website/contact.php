<?php
require __DIR__ . '/includes/bootstrap.php';
render_header(['title'=>'Contact Us']);
?>
<section class="page-banner hero-page"><div class="container hero-inner"><p class="eyebrow">HAVE A QUESTION?</p><h1>Contact Us</h1></div></section>
<section class="container content-page contact-page">
  <div class="contact-intro"><h2>How can we help?</h2><p>Send us your question and our team will get back to you.</p></div>
  <div class="card form-card"><h2>Contact Us</h2><?= do_shortcodes('[form=contact_form]') ?></div>
</section>
<?php render_footer(); ?>
