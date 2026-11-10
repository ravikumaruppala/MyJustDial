<?php
/**
 * Home page.
 * Blocks: hero with the directory search, 3-step feature band, newest + featured members,
 * a highlight card, and the two "join" cards.
 */
require __DIR__ . '/includes/bootstrap.php';

$categories = get_categories($pdo);                              // for the two category drop-downs
$newest     = newest_members($pdo, (int)$site['home_members']);  // "Our Newest Members"
$featured   = featured_members($pdo, (int)$site['home_members']);// "Featured Members"

render_header(['title' => '', 'active' => 'home']);
?>

<!-- Hero: headline + directory search (submits to search.php) -->
<section class="hero hero-home" id="search">
    <div class="container hero-inner">
        <h1>Your Search. Your City.<br>Find Local Businesses<br>FOR ANY Need or Location</h1>
        <a class="hero-link" href="<?= e(site_url('how-it-works.php')) ?>">See How Directory Search Works</a>
        <form class="hero-search" action="<?= e(site_url('search.php')) ?>" method="get">
            <div>
                <label for="catSelect">What do you need:</label>
                <select class="control" name="category" id="catSelect">
                    <option value="">Select Category</option>
                    <?php foreach ($categories['professions'] as $c): ?>
                        <option value="<?= (int)$c['profession_id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="subSelect">Specializing in:</label>
                <select class="control" name="sub" id="subSelect">
                    <option value="">Select Sub-Category</option>
                    <?php foreach ($categories['services'] as $s): ?>
                        <option value="<?= (int)$s['service_id'] ?>" data-profession="<?= (int)$s['profession_id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="locInput">Search by location:</label>
                <div class="input-icon"><span><i class="fa fa-location-arrow"></i></span>
                    <input class="control" type="text" name="location" id="locInput" placeholder="City or Post Code"></div>
            </div>
            <button class="btn btn-coral" type="submit" style="height:48px">Search Now</button>
        </form>
    </div>
</section>

<!-- Feature band -->
<section class="feature-band">
    <div class="container feature-grid">
        <a class="feature-item" href="<?= e(site_url('search.php')) ?>">
            <div class="circle"><i class="fa fa-search"></i></div><h3>Search Members</h3><p>Browse To Find Members</p></a>
        <a class="feature-item" <?= modal_attrs($site['forms']['contact'], 'Contact Us') ?>>
            <div class="circle"><i class="fa fa-comments-o"></i></div><h3>Contact Members</h3><p>Connect with Members You Like</p></a>
        <a class="feature-item" href="<?= e(site_url('search.php')) ?>">
            <div class="circle"><i class="fa fa-check-square-o"></i></div><h3>Review Members</h3><p>Share Your Experience Online</p></a>
    </div>
</section>

<!-- Newest members (read from users_data) -->
<section class="members-section">
    <div class="container">
        <h2 class="section-title">Our Newest Members</h2>
        <div class="member-grid">
            <?php foreach ($newest as $m) { echo render_member_card($m); } ?>
            <?php if (!$newest): ?><p class="empty-note">No members have been listed yet.</p><?php endif; ?>
        </div>
    </div>
</section>

<!-- Highlight card -->
<section class="container">
    <div class="card info-block">
        <div>
            <h2>Your Business Deserves To Be Found</h2>
            <p>Find trusted professionals who put you first. Whether you need a quick service or a specialist, connect with experienced local members who are committed to helping you in a friendly and reliable way.</p>
        </div>
        <img class="art-photo" src="<?= e(asset_url('images/canyon.svg')) ?>" alt="" width="640" height="480" loading="lazy">
    </div>
</section>

<!-- Featured members -->
<section class="members-section" style="padding-top:24px">
    <div class="container">
        <h2 class="section-title">Featured Members</h2>
        <div class="member-grid">
            <?php foreach ($featured as $m) { echo render_member_card($m); } ?>
            <?php if (!$featured): ?><p class="empty-note">No members have been listed yet.</p><?php endif; ?>
        </div>
    </div>
</section>

<!-- Join / About cards -->
<section class="container">
    <div class="cta-grid">
        <div class="card cta-card">
            <h3>Members - Join Today</h3>
            <p>Finding professionals is easy with <?= e($site['name']) ?>. Search our website to instantly connect with professionals. For professionals, our website works as a powerful tool for attracting more clients.</p>
            <a class="btn btn-primary" <?= join_attrs() ?>>Get Listed Today</a>
        </div>
        <div class="card cta-card">
            <h3>About <?= e($site['name']) ?></h3>
            <p>Finding members is easy with <?= e($site['name']) ?>. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p>
            <a class="btn btn-primary" <?= join_attrs() ?>>Get Listed Today</a>
        </div>
    </div>
</section>

<?php render_footer();
