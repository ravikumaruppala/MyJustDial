<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';
$pageClass = $pageClass ?? '';
$flashMessage = getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | MyJustDial</title>
    <link rel="icon" type="image/svg+xml" href="<?= e($config['base_url']) ?>/assets/images/myjustdial-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/ui-refresh.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <?= $extraHead ?? '' ?>
</head>

<body>
    <script>try{if(localStorage.getItem('mjd_sidebar')==='collapsed'&&window.innerWidth>650)document.body.classList.add('sidebar-collapsed');}catch(e){}</script>
    <div class="admin-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="main-area">
            <header class="topbar">
                <div class="topbar-left"><button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open menu">☰</button><span class="site-switch"><i class="fa fa-arrow-circle-o-left"></i>Switch Website</span><select class="top-select" aria-label="Website filter">
                        <option>All</option>
                    </select>
                    <div class="top-search-wrap"><input class="top-search" placeholder="Search Website..." aria-label="Search website"><i class="fa fa-search"></i></div>
                </div>
                <div class="topbar-right"><a class="quick-create" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/create.php"><i class="fa fa-magic"></i>Quick Create<i class="fa fa-angle-down"></i></a><a class="visit-site" href="<?= e($config['base_url']) ?>/website/index.php" target="_blank" rel="noopener">Visit Website<i class="fa fa-external-link"></i></a>
                    <div class="account-menu"><button class="account-toggle" type="button" data-menu-toggle="account"><i class="fa fa-user-circle-o"></i>My Account<i class="fa fa-angle-down"></i></button>
                        <div class="small-dropdown" data-menu="account"><a href="<?= e($config['base_url']) ?>/admin/logout.php">Log Out</a></div>
                    </div>
                </div>
            </header>
            <div class="sale-strip"><strong><i class="fa fa-bolt"></i>24-Hour Flash Sale</strong>
                <div class="sale-right"><div class="sale-text"><strong>[FLASH SALE] Lifetime Sites $249</strong><span>Ends Today · November 3, 2026</span></div><b>$249 BUY NOW<i class="fa fa-external-link"></i></b></div>
            </div>
            <div class="page-meta"><span>Build Version: 4.0</span><span>Website Time: <?= e(date('D, M d Y h:i A')) ?> UTC+05:30</span><a class="refresh-site" href=""><i class="fa fa-refresh"></i>Refresh Live Site</a></div>
            <div class="page-content <?= e($pageClass) ?>">
                <?php if ($flashMessage): ?><div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?><button class="notice-close" type="button" data-dismiss-notice>×</button></div><?php endif; ?>
