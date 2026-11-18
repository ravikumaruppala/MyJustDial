<?php
/**
 * Reusable page pieces (cards, result rows, side panels).
 * Each function returns HTML as a string so pages can print it where they need it.
 */

/** Card used in "Our Newest Members" on the home page. */
function render_member_card(array $m): string {
    ob_start(); ?>
    <article class="member-card">
        <h3><a href="<?= e(site_url('search.php?listing=' . (int)$m['user_id'])) ?>"><?= e(member_name($m)) ?></a></h3>
        <p class="located"><b>Located in</b> <?= e(member_location_short($m)) ?></p>
        <a class="btn btn-primary" href="<?= e(site_url('search.php?listing=' . (int)$m['user_id'])) ?>">View Listing</a>
    </article>
    <?php
    return ob_get_clean();
}

/** One row of the Search Directory results. */
function render_member_result(array $m): string {
    global $site;
    $id = (int)$m['user_id'];
    $phone = trim((string)$m['phone_number']);
    ob_start(); ?>
    <article class="result">
        <img class="result-avatar" src="<?= e(asset_url('images/avatar.svg')) ?>" alt="" width="176" height="176">
        <div class="result-main">
            <h2><a href="<?= e(site_url('search.php?listing=' . $id)) ?>"><?= e(member_name($m)) ?></a></h2>
            <?php if (trim((string)$m['company']) !== ''): ?><div class="result-company"><?= e($m['company']) ?></div><?php endif; ?>
            <div class="result-loc"><i class="fa fa-map-marker"></i><?= e(member_location_long($m)) ?></div>
        </div>
        <button class="save-btn" type="button" data-save-member="<?= $id ?>" aria-pressed="false"><i class="fa fa-bookmark"></i> SAVE</button>
        <div class="result-actions">
            <a class="btn btn-primary" href="<?= e(site_url('search.php?listing=' . $id)) ?>">View Listing</a>
            <a class="btn btn-dark" href="<?= e(site_url('member-message.php?member=' . $id)) ?>">Send Message</a>
            <?php if ($phone !== ''): ?>
                <button class="btn btn-outline" type="button" data-reveal-phone="<?= e($phone) ?>"><i class="fa fa-phone"></i> See Phone Number</button>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/** "Join Our Newsletter" + "Join Our Community" cards shown under the search forms. */
function render_side_cards(): string {
    global $site;
    ob_start(); ?>
    <div class="side-card center">
        <h3>Join Our Newsletter</h3>
        <a class="btn btn-primary" <?= modal_attrs($site['forms']['newsletter'], 'Join Our Newsletter') ?>>Click to Subscribe</a>
    </div>
    <div class="side-card">
        <h3>Join Our Community</h3>
        <ol>
            <li>Register an account</li>
            <li>Create a searchable listing</li>
            <li>Connect with more clients</li>
        </ol>
        <a class="btn btn-primary" <?= join_attrs() ?>>Get Listed Today</a>
    </div>
    <?php
    return ob_get_clean();
}

/** Rotating promo banner shown under the directory results (static text, four slides). */
function render_promo(): string {
    global $site;
    $slides = [
        ['Become a Featured Member', 'Increase your visibility and get noticed before other members. Featured Members receive more exposure and more inquiries from customers.', 'Upgrade to Featured', 'fa-star'],
        ['Get Verified', 'A verified badge builds trust. Customers are more likely to contact members whose details have been checked.', 'Get Verified', 'fa-check-circle'],
        ['Collect Customer Reviews', 'Happy customers can share their experience online so that new visitors know what to expect.', 'Join Today', 'fa-comments-o'],
        ['Reach More Customers', 'Create a searchable listing and let people in your area find you by name, category or location.', 'Get Listed Today', 'fa-bullhorn'],
    ];
    ob_start(); ?>
    <section class="promo" aria-label="Member promotions">
        <?php foreach ($slides as $i => $s): ?>
            <div class="promo-slide <?= $i === 0 ? 'is-active' : '' ?>">
                <div class="promo-star"><i class="fa <?= e($s[3]) ?>"></i></div>
                <div><h3><?= e($s[0]) ?></h3><p><?= e($s[1]) ?></p></div>
                <a class="btn btn-primary" <?= join_attrs() ?>><?= e($s[2]) ?></a>
            </div>
        <?php endforeach; ?>
        <div class="promo-dots">
            <?php foreach ($slides as $i => $s): ?><button type="button" aria-label="Show promotion <?= $i + 1 ?>"></button><?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
