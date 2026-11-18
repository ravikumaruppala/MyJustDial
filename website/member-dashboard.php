<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
$required = ['first_name','last_name','email','phone_number','company','city','about_me'];
$completed = 0;
foreach ($required as $field) if (trim(strip_tags((string)($member[$field] ?? ''))) !== '') $completed++;
$completion = (int)round(($completed / count($required)) * 100);
$joinedDate = DateTime::createFromFormat('YmdHis', (string)$member['signup_date']);
$joinDateLabel = $joinedDate ? $joinedDate->format('d/m/Y') : '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$publicListingUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . site_url('search.php?listing='.(int)$member['user_id']);
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=190x190&data=' . rawurlencode($publicListingUrl);
$badgeImageUrl = asset_url('images/member-badge.svg');
$badgeCode = '<a href="'.$publicListingUrl.'" target="_blank" title="Find me on '.$site['name'].'"><img src="'.$badgeImageUrl.'" alt="Member badge"></a>';
render_header(['title'=>'My Dashboard','member_portal'=>true,'member_section'=>'dashboard']);
?>
<section class="member-dashboard-page">
  <div class="member-dashboard-title"><h1><i class="fa fa-home"></i> My Dashboard</h1></div>
  <div class="member-completion"><p>Your listing is <?= $completion ?>% Complete</p><div class="member-progress"><span style="width:<?= $completion ?>%"></span></div></div>
  <div class="member-dashboard-grid">
    <section class="member-dash-card">
      <h2><i class="fa fa-user"></i> Manage Listing</h2>
      <a href="<?= e(site_url('contact-details.php')) ?>">Contact Details</a>
      <a href="<?= e(site_url('profile-photo.php')) ?>">Profile Photo</a>
      <a href="<?= e(site_url('listing-details.php')) ?>">Additional Details</a>
      <a href="<?= e(site_url('about-member.php')) ?>">About Me</a>
    </section>
    <section class="member-dash-card">
      <h2><i class="fa fa-cog"></i> Account Details</h2>
      <p class="member-plan"><?= e($member['plan_name']) ?></p>
      <p><i class="fa fa-check-circle"></i> Status: <?= (int)$member['active'] === (int)$site['member_active'] ? 'Active' : 'Inactive' ?></p>
      <p><i class="fa fa-calendar"></i> Join Date: <?= e($joinDateLabel) ?></p>
      <a class="member-account-link" href="<?= e(site_url('member-billing.php')) ?>">Billing Information</a>
      <a class="member-account-link" href="<?= e(site_url('member-change-password.php')) ?>">Change Password</a>
      <a class="member-account-link" href="<?= e(site_url('logout.php')) ?>">Logout</a>
    </section>
    <section class="member-dash-card member-qr-card">
      <h2><i class="fa fa-qrcode"></i> Free QR Code Image</h2>
      <p>Links To Your listing</p>
      <a class="member-qr-link" href="<?= e($publicListingUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($qrImageUrl) ?>" alt="QR code linking to your public listing"></a>
      <small>Use the QR code image to attract people directly to your listing.</small>
    </section>
    <section class="member-dash-card member-badge-card">
      <h2>Attract More Clients<br>Add This To Your Website</h2>
      <p>Increases Trust &amp; Credibility</p>
      <img class="member-badge-image" src="<?= e($badgeImageUrl) ?>" alt="Member badge">
      <p>Copy and paste the code below into your website's source code.</p>
      <textarea readonly rows="3" onclick="this.select()"><?= e($badgeCode) ?></textarea>
    </section>
  </div>
</section>
<?php render_footer(); ?>
