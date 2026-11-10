<?php
/**
 * Page chrome shared by every public page: <head>, navigation bar, header, footer and the pop-up window.
 * Pages call render_header([...]) first and render_footer() last.
 */

/** Items of the main navigation: key => [label, file]. */
function site_nav(): array {
    return [
        'home'   => ['Home', 'index.php'],
        'search' => ['Search Directory', 'search.php'],
        'how'    => ['How It Works', 'how-it-works.php'],
        'blog'   => ['Website Blog', 'blog.php'],
    ];
}

/** Attributes that make a link open one of the shortcode forms in the pop-up (falls back to form.php without JavaScript). */
function modal_attrs(string $formName, string $title, int $memberId = 0): string {
    $href = site_url('form.php?name=' . urlencode($formName) . ($memberId ? '&member=' . $memberId : ''));
    return 'href="' . e($href) . '" data-modal-form="' . e($formName) . '" data-modal-title="' . e($title) . '"'
         . ($memberId ? ' data-member-id="' . $memberId . '"' : '');
}

/**
 * Attributes for every "Members - Join Today" / "Get Listed Today" button on the website:
 * they open the membership plans page, where the visitor picks a plan and then signs up.
 */
function join_attrs(): string {
    return 'href="' . e(site_url('membership-plans.php')) . '"';
}

/** "SELECT PLAN" button: opens the sign-up form in the pop-up with the chosen plan attached. */
function plan_attrs(string $planKey, string $planName): string {
    global $site;
    $form = $site['forms']['join'];
    return 'href="' . e(site_url('form.php?name=' . urlencode($form) . '&plan=' . urlencode($planKey))) . '"'
         . ' data-modal-form="' . e($form) . '" data-modal-title="' . e('Join - ' . $planName . ' Plan') . '" data-plan="' . e($planKey) . '"';
}

/**
 * Print everything above the page content.
 * $page keys: title (string), active (nav key), description (string), body_class (string).
 */
function render_header(array $page = []): void {
    global $site;
    $title  = ($page['title'] ?? '') !== '' ? $page['title'] . ' | ' . $site['name'] : $site['name'] . ' - ' . $site['tagline'];
    $active = $page['active'] ?? '';
    $member = current_member();
    $forms  = $site['forms'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($page['description'] ?? ($site['name'] . ' - ' . $site['tagline'])) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?= e(asset_url('css/website.css')) ?>">
</head>
<body class="<?= e($page['body_class'] ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<!-- Logo + contact actions -->
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(site_url('index.php')) ?>">
            <img src="<?= e(asset_url('images/logo.svg')) ?>" alt="" width="52" height="52">
            <span class="brand-text"><strong><?= e($site['name']) ?></strong><small><?= e($site['tagline']) ?></small></span>
        </a>
        <div class="header-actions">
            <a class="hdr-link hdr-phone" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $site['phone'])) ?>"><?= e($site['phone']) ?></a>
            <a class="hdr-link" <?= modal_attrs($forms['contact'], 'Contact Us') ?>><i class="fa fa-envelope-o"></i> Contact</a>
            <?php if ($member): ?>
                <span class="hdr-link hdr-user"><i class="fa fa-user-circle-o"></i> Hi, <?= e($member['name']) ?></span>
                <a class="hdr-link" href="<?= e(site_url('logout.php')) ?>"><i class="fa fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a class="hdr-link" <?= modal_attrs($forms['login'], 'Member Login') ?>><i class="fa fa-sign-in"></i> Login</a>
            <?php endif; ?>
            <a class="btn btn-join" <?= join_attrs() ?>><i class="fa fa-plus-circle"></i> Members &ndash; Join Today</a>
        </div>
    </div>
</header>

<!-- Dark navigation bar: full-width links on desktop, hamburger on small screens -->
<nav class="topnav" aria-label="Main navigation">
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="navList" aria-label="Open menu"><i class="fa fa-bars"></i></button>
    <ul class="nav-list" id="navList">
        <?php foreach (site_nav() as $key => $item): ?>
            <li><a href="<?= e(site_url($item[1])) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= e($item[0]) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
<main id="main">
<?php
}

/** Print the footer, the pop-up window and the scripts. */
function render_footer(): void {
    global $site;
    $forms = $site['forms'];
    ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <h4>About</h4>
                <p>Finding members is easy with <?= e($site['name']) ?>. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p>
            </div>
            <div>
                <h4>Website</h4>
                <ul>
                    <li><a href="<?= e(site_url('how-it-works.php')) ?>">How It Works</a></li>
                    <li><a <?= modal_attrs($forms['contact'], 'Contact Us') ?>>Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h4>Search</h4>
                <ul>
                    <li><a href="<?= e(site_url('search.php')) ?>">Browse Directory</a></li>
                    <li><a href="<?= e(site_url('index.php#search')) ?>">Search by Category</a></li>
                </ul>
            </div>
            <div>
                <h4>Support</h4>
                <ul>
                    <li><a <?= modal_attrs($forms['login'], 'Member Login') ?>>Member Login</a></li>
                    <li><a href="#" data-modal-static="reset">Reset Password</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-cta">
            <a class="footer-mail" <?= modal_attrs($forms['newsletter'], 'Join Our Newsletter') ?> aria-label="Join our newsletter"><i class="fa fa-envelope-o"></i></a>
            <a class="btn btn-footer-join" <?= join_attrs() ?>><i class="fa fa-plus-circle"></i> Members &ndash; Join Today</a>
        </div>
        <p class="copyright">&copy; <?= date('Y') ?> <?= e($site['name']) ?> <span>All Rights Reserved</span>
            <a href="#" data-modal-static="terms">Terms of Use</a> <a href="#" data-modal-static="privacy">Privacy Policy</a></p>
    </div>
</footer>

<!-- Pop-up window used by every shortcode form and the small info notices -->
<div class="mjd-modal" id="mjdModal" hidden>
    <div class="mjd-modal-backdrop" data-close></div>
    <div class="mjd-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mjdModalTitle">
        <button class="mjd-modal-close" type="button" data-close aria-label="Close">&times;</button>
        <h2 id="mjdModalTitle"></h2>
        <div class="mjd-modal-body"></div>
    </div>
</div>

<!-- Static notices (text only) -->
<template id="tpl-terms"><p>By using <?= e($site['name']) ?> you agree to use the directory lawfully and to provide accurate information when you register or contact a member. Listings are provided by members; please verify details before you rely on them.</p></template>
<template id="tpl-privacy"><p>We only collect the details you type into our forms (for example name, email and message) so that members and our team can reply to you. We do not sell your information.</p></template>
<template id="tpl-reset"><p>To reset your password please contact the website administrator. Automatic reset by email is not enabled on this website yet.</p></template>

<script>window.MJD = { formEndpoint: <?= json_encode(site_url('form-submit.php')) ?> };</script>
<script src="<?= e(asset_url('js/website.js')) ?>"></script>
</body>
</html>
<?php
}
