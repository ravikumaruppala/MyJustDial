<?php
/**
 * Stand-alone page for one shortcode form: form.php?name=FORM_NAME
 * It is the no-JavaScript fallback of the pop-up links, and a quick way to try any form built in the admin.
 */
require __DIR__ . '/includes/bootstrap.php';

$name = get_str('name', $site['forms']['contact']);
$form = get_form($pdo, $name);
render_header(['title' => $form ? ($form['title'] ?: 'Form') : 'Form not found', 'active' => '']);
?>
<section class="container narrow page-section">
    <?php if ($form): ?>
        <h1 class="page-title"><?= e($form['title'] !== '' ? $form['title'] : $form['name']) ?></h1>
        <div class="card form-card">
            <?= (get_str('plan') !== '' ? render_form($name, ['plan' => get_str('plan')]) : do_shortcodes('[form=' . $name . ']')) /* [form=name] works in any page */ ?>
        </div>
    <?php else: ?>
        <h1 class="page-title">Form not found</h1>
        <div class="mjd-notice">There is no enabled form called &ldquo;<?= e($name) ?>&rdquo;.</div>
    <?php endif; ?>
</section>
<?php render_footer();
