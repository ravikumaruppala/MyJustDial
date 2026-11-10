<?php
/**
 * Membership Plans page.
 * Every "Members - Join Today" / "Get Listed Today" button on the website leads here (see join_attrs() in
 * includes/layout.php). "SELECT PLAN" opens the sign-up form ([form=signup_free]) in a pop-up with the chosen
 * plan attached; the plan is stored in users_data.subscription_id when the account is created.
 * The plans themselves (names, prices, text, features) are edited in config/site.php -> 'plans'.
 */
require __DIR__ . '/includes/bootstrap.php';

render_header(['title' => 'Membership Plans', 'active' => '']);
?>
<!-- Banner -->
<section class="hero hero-plans" aria-hidden="true"></section>

<section class="container plans">
    <?php foreach ($site['plans'] as $key => $plan): ?>
        <article class="plan" id="plan-<?= e($key) ?>">
            <!-- Left: name, prices and the select button -->
            <div class="plan-box">
                <h2><?= e($plan['name']) ?></h2>
                <p class="plan-price"><?= e($site['currency']) . (int)$plan['monthly'] ?>/month</p>
                <p class="plan-price"><?= e($site['currency']) . (int)$plan['yearly'] ?>/year</p>
                <a class="btn btn-primary btn-select" <?= plan_attrs($key, $plan['name']) ?>>SELECT PLAN</a>
            </div>
            <!-- Right: description + feature list (two columns) -->
            <div class="plan-info">
                <p class="plan-desc"><?= e(str_replace('{site}', $site['name'], $plan['description'])) ?></p>
                <ul class="plan-features">
                    <?php foreach ($plan['features'] as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php render_footer();
