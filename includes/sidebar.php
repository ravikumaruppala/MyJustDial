<?php
/* Sidebar: every item with children opens a fly-out beside it on hover and expands downward on click. */
$base = e($config['base_url']);
$nav = [
    ['key' => 'members', 'icon' => 'fa-users', 'label' => 'My Members', 'items' => [['Manage Members', '#'], ['Add New Member', '#'], ['Membership Plans', '#'], ['Smart Lists', '#']]],
    ['key' => 'content', 'icon' => 'fa-folder-o', 'label' => 'My Content', 'items' => [['Web Pages', '#'], ['Post Types', '#'], ['Blog Articles', '#'], ['Categories', '#']]],
    ['key' => 'interactions', 'icon' => 'fa-comment-o', 'label' => 'Interactions', 'items' => [['Leads', '#'], ['Inquiries', '#'], ['Reviews', '#'], ['Comments', '#']]],
    ['key' => 'finance', 'icon' => 'fa-bar-chart', 'label' => 'Finance', 'items' => [['Transactions', '#'], ['Invoices', '#'], ['Coupons', '#'], ['Payment Settings', '#']]],
    ['key' => 'emails', 'icon' => 'fa-envelope-o', 'label' => 'Emails', 'items' => [['Email Templates', '#'], ['Newsletters', '#'], ['Email Logs', '#'], ['Email Settings', '#']]],
    ['key' => 'toolbox', 'icon' => 'fa-wrench', 'label' => 'Toolbox', 'items' => [['Widget Manager', '#'], ['Sidebar Manager', '#'], ['Menu Manager', '#'], ['Form Manager', $config['base_url'] . '/admin/toolbox/form-manager/index.php', 'forms'], ['Sitemap Generator', '#']]],
    ['key' => 'settings', 'icon' => 'fa-cog', 'label' => 'Settings', 'items' => [['General Settings', '#'], ['Design & Layout', '#'], ['Domain Settings', '#'], ['Integrations', '#']]],
];
?>
<aside class="sidebar" id="sidebar">
<div class="brand"><img class="brand-logo" src="<?= $base ?>/assets/images/myjustdial-logo.svg" alt="MyJustDial logo"><span class="brand-name">MyJustDial</span></div>
<nav class="side-nav">
<a class="<?= $activeMenu==='dashboard'?'active':'' ?>" href="<?= $base ?>/admin/dashboard.php" data-label="Dashboard"><span class="nav-icon"><i class="fa fa-home"></i></span><span class="nav-label">Dashboard</span></a>
<?php foreach ($nav as $group):
    $isCurrent = !empty(array_filter($group['items'], static function ($i) use ($activeMenu) { return ($i[2] ?? '') === $activeMenu; })); ?>
<div class="nav-group <?= $isCurrent ? 'current' : '' ?>">
<button class="nav-group-toggle" type="button" data-menu-toggle="<?= e($group['key']) ?>" aria-expanded="<?= $isCurrent ? 'true' : 'false' ?>"><span class="nav-icon"><i class="fa <?= e($group['icon']) ?>"></i></span><span class="nav-label"><?= e($group['label']) ?></span><span class="nav-arrow"><i class="fa fa-angle-right"></i></span></button>
<div class="sub-nav <?= $isCurrent ? 'is-open' : '' ?>" data-menu="<?= e($group['key']) ?>"><?php foreach ($group['items'] as $item): ?><a class="<?= (($item[2] ?? '') === $activeMenu) ? 'active' : '' ?>" href="<?= e($item[1]) ?>"><?= e($item[0]) ?></a><?php endforeach; ?></div>
</div>
<?php endforeach; ?>
<a href="#" data-label="Developer Hub"><span class="nav-icon"><i class="fa fa-th-large"></i></span><span class="nav-label">Developer Hub</span></a>
<button class="collapse-sidebar" id="collapseSidebar" type="button" data-label="Expand" aria-label="Collapse sidebar"><span class="nav-icon"><i class="fa fa-columns"></i></span><span class="nav-label">Collapse</span></button>
</nav></aside>
