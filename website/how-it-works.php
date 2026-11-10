<?php
/** How It Works page - static content: banner, picture, the three steps and two buttons. */
require __DIR__ . '/includes/bootstrap.php';
render_header(['title' => 'How It Works', 'active' => 'how']);
?>
<section class="hero hero-page">
    <div class="container hero-inner">
        <p class="eyebrow">How It Works</p>
        <h1>About <?= e($site['name']) ?></h1>
    </div>
</section>

<section class="container how">
    <img class="art-photo" src="<?= e(asset_url('images/canyon.svg')) ?>" alt="" width="620" height="413" loading="lazy">
    <div class="steps">
        <h2>1. Search Members</h2>
        <p>Start by searching members by name, specialty or location.</p>
        <h2>2. Compare Members</h2>
        <p>Compare details and view profiles of the members you find.</p>
        <h2>3. Connect with Members</h2>
        <p>Send messages to contact the members you like most.</p>
        <div class="btn-row">
            <a class="btn btn-dark" href="<?= e(site_url('search.php')) ?>">Search Directory</a>
            <a class="btn btn-outline" <?= join_attrs() ?>>Members - Join Today</a>
        </div>
    </div>
</section>
<?php render_footer();
