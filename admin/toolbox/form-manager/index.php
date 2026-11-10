<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require __DIR__ . '/../../../includes/form_fields.php';
$pageTitle='Form Manager'; $activeMenu='forms';

/* The two rows already in forms are custom forms, regardless of legacy action_type.
   The 35 system forms shown in the BD reference are stored separately to protect them. */
$pdo->exec("CREATE TABLE IF NOT EXISTS default_forms_catalog (
 default_form_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 form_name VARCHAR(255) NOT NULL, short_code VARCHAR(255) NOT NULL,
 last_update VARCHAR(20) NOT NULL DEFAULT '', UNIQUE KEY uq_default_form_name(form_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$defaults = [
['Account Billing Address Update','whmcs_billing_address','12/24/2025'],
['Feature - Blog Articles','blog_article_fields','10/02/2026'],
['Feature - Classifieds','classified_fields','03/16/2022'],
['Feature - Coupons','coupon_fields','03/16/2022'],
['Feature - Digital Products','digital_products_fields','05/23/2024'],
['Feature - Discussions','discussion_fields','01/03/2022'],
['Feature - Events','event_fields','08/06/2022'],
['Feature - Jobs','job_fields','03/16/2022'],
['Feature - Member Articles','member_article_fields','02/02/2022'],
['Feature - Photo Fields','photo_fields','07/29/2026'],
['Feature - Photos','photo_album_fields','09/15/2022'],
['Feature - Products','product_album_fields','07/05/2022'],
['Feature - Properties','property_listing','05/07/2022'],
['Feature - Soundcloud','soundcloud_fields','01/03/2022'],
['Feature - Videos','video_fields','06/05/2025'],
['Listing - About - Member','about','09/02/2022'],
['Listing - Additional Details - Member','member_listing_details','03/13/2021'],
['Listing - Contact Details - General User','contact_general_user','01/15/2024'],
['Listing - Contact Details - Member','member_contact_details','08/20/2025'],
['Listing - Password Retrieval','password_retrieval','07/09/2021'],
['Listing - Profile Photo & Logo','profile','12/10/2014'],
['Listing - Verify Account','verify_business','08/23/2021'],
['Listing - Write Review','member_review','10/05/2025'],
['Member - Login Form','member_login','12/23/2025'],
['Member - Sign Up - Free','signup_free','11/20/2025'],
['Member - Sign Up - Paid','whmcs_signup_paid','11/20/2025'],
['Member - Sign Up - Paid - External Gateway','whmcs_signup_external','06/07/2022'],
['Member - Upgrade Form','whmcs_upgrade_account','11/28/2025'],
['Sub Accounts','sub_accounts_form','11/28/2025'],
['Unsubscribe Email','unsubscribe_email','02/15/2023'],
['Website - Coming Soon','coming_soon_form','11/19/2019'],
['Website - Contact Us','contact_form','08/13/2025'],
['Website - Lead Form - Get Matched','bootstrap_get_match','06/30/2025'],
['Website - Newsletter Signup Form','newsletter_modal_signup','10/05/2025'],
['Website - Report This Post (add-on)','report_post_form','03/28/2019']];
$seed=$pdo->prepare("INSERT IGNORE INTO default_forms_catalog(form_name,short_code,last_update) VALUES(?,?,?)");
foreach($defaults as $row){$seed->execute($row);}
$q=trim($_GET['q']??''); $statusRaw=$_GET['status']??'all'; $typeRaw=$_GET['type']??'all'; $sort=$_GET['sort']??'name';
$status=in_array($statusRaw,['enabled','disabled'],true)?$statusRaw:'all';
$type=in_array($typeRaw,['member','posts','general'],true)?$typeRaw:'all';
if(!in_array($sort,['name','name_desc','newest','oldest'],true)){$sort='name';}
$orderMap=['name'=>'form_title ASC, form_name ASC','name_desc'=>'form_title DESC, form_name DESC','newest'=>'form_id DESC','oldest'=>'form_id ASC'];
$order=$orderMap[$sort];
$sql="SELECT * FROM forms WHERE 1=1"; $params=[];
if($q!==''){$sql.=" AND (form_title LIKE ? OR form_name LIKE ? OR short_code LIKE ?)";$like='%'.$q.'%';$params=[$like,$like,$like];}
$sql.=" ORDER BY ".$order; $stmt=$pdo->prepare($sql);$stmt->execute($params);$custom=$stmt->fetchAll();
if($status!=='all' && tableExists($pdo,'users_meta')){$custom=array_values(array_filter($custom,function($f)use($pdo,$status){$s=$pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_status' LIMIT 1");$s->execute([$f['form_id']]);$v=$s->fetchColumn();return ($v===false?'enabled':$v)===$status;}));}
if($type==='member'){$custom=array_values(array_filter($custom,function($f){return stripos($f['form_name'],'signup')!==false || stripos($f['form_name'],'member')!==false || stripos($f['form_name'],'listing')!==false;}));}
if($type==='posts'){$custom=array_values(array_filter($custom,function($f){return stripos($f['form_name'],'post')!==false || stripos($f['form_name'],'article')!==false || stripos($f['form_name'],'blog')!==false;}));}
if($type==='general'){$custom=array_values(array_filter($custom,function($f){return stripos($f['form_name'],'signup')===false && stripos($f['form_name'],'member')===false && stripos($f['form_name'],'listing')===false && stripos($f['form_name'],'post')===false && stripos($f['form_name'],'article')===false && stripos($f['form_name'],'blog')===false;}));}
$catalog=$pdo->query("SELECT * FROM default_forms_catalog ORDER BY default_form_id")->fetchAll();
if($q!==''){$catalog=array_values(array_filter($catalog,function($f)use($q){return stripos($f['form_name'],$q)!==false||stripos($f['short_code'],$q)!==false;}));}
if($type==='member'){$catalog=array_values(array_filter($catalog,function($f){return stripos($f['form_name'],'Member')!==false || stripos($f['form_name'],'Listing')!==false;}));}
if($type==='posts'){$catalog=array_values(array_filter($catalog,function($f){return stripos($f['form_name'],'Feature -')===0;}));}
if($type==='general'){$catalog=array_values(array_filter($catalog,function($f){return stripos($f['form_name'],'Member')===false && stripos($f['form_name'],'Listing')===false && stripos($f['form_name'],'Feature -')!==0;}));}
// System default forms are always enabled, so a "Disabled" filter leaves none of them.
if($status==='disabled'){$catalog=[];}
$catalogDate=function($f){$d=DateTime::createFromFormat('m/d/Y',$f['last_update']);return $d?$d->getTimestamp():0;};
if($sort==='name'){usort($catalog,function($a,$b){return strcasecmp($a['form_name'],$b['form_name']);});}
elseif($sort==='name_desc'){usort($catalog,function($a,$b){return strcasecmp($b['form_name'],$a['form_name']);});}
elseif($sort==='newest'){usort($catalog,function($a,$b)use($catalogDate){return $catalogDate($b)<=>$catalogDate($a);});}
elseif($sort==='oldest'){usort($catalog,function($a,$b)use($catalogDate){return $catalogDate($a)<=>$catalogDate($b);});}

require __DIR__ . '/../../../includes/header.php';
?>
<div class="fm-page-heading"><div class="fm-info"><span class="info-icon"><i class="fa fa-info-circle"></i></span><div><strong>Form Manager</strong> – Build and manage website forms. Add or remove input fields when editing individual forms.</div></div><a class="fm-new-button" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/index.php?new=1">New Form <i class="fa fa-plus"></i></a></div>
<form class="fm-toolbar" method="get" id="fmToolbar">
<div class="fm-bulk"><select id="bulkAction" aria-label="Bulk actions"><option value="">Bulk Actions</option><option value="enable">Enable Selected</option><option value="disable">Disable Selected</option><option value="delete">Delete Selected</option></select><button type="button" id="applyBulk" data-url="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php" data-token="<?= e(csrfToken()) ?>">Apply</button></div>
<div class="fm-filters"><select name="status" aria-label="Filter by status"><option value="all">Filter by Status</option><option value="any" <?= $statusRaw==='any'?'selected':'' ?>>Any Status</option><option value="enabled" <?= $status==='enabled'?'selected':'' ?>>Enabled</option><option value="disabled" <?= $status==='disabled'?'selected':'' ?>>Disabled</option></select>
<select name="type" aria-label="Filter by type"><option value="all">Filter by Type</option><option value="any" <?= $typeRaw==='any'?'selected':'' ?>>Any Form Type</option><option value="member" <?= $type==='member'?'selected':'' ?>>Member Listings</option><option value="posts" <?= $type==='posts'?'selected':'' ?>>Post Types</option><option value="general" <?= $type==='general'?'selected':'' ?>>General</option></select>
<select name="sort" aria-label="Sort forms"><option value="name" <?= $sort==='name'?'selected':'' ?>>Name A - Z</option><option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>>Name Z - A</option><option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option><option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option></select>
<div class="fm-search"><input name="q" value="<?= e($q) ?>" placeholder="Search by Keyword" aria-label="Search by keyword"><button type="submit" aria-label="Search"><i class="fa fa-search"></i></button></div></div></form>
<div class="fm-table-wrap"><table class="fm-table custom-table"><thead><tr><th class="check-col"><input type="checkbox" id="selectAll"></th><th>Actions</th><th>Preview</th><th>Custom Forms (<?= count($custom) ?>)</th><th>Shortcode</th><th>Last Update</th></tr></thead><tbody>
<?php if(!$custom): ?><tr><td colspan="6" class="fm-empty">No custom forms found.</td></tr><?php else: foreach($custom as $form):
$ss=$pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_status' LIMIT 1");$ss->execute([$form['form_id']]);$state=$ss->fetchColumn();$enabled=$state===false||$state==='enabled';$code=$form['short_code']?:'[form='.$form['form_name'].']'; ?>
<tr><td><input type="checkbox" class="form-row-check" value="<?= (int)$form['form_id'] ?>"></td><td class="fm-actions-cell"><a class="fm-edit-button" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/edit.php?id=<?= (int)$form['form_id'] ?>">Edit</a>
<div class="fm-action-menu"><button type="button" class="fm-action-toggle" data-menu-toggle="form-<?= (int)$form['form_id'] ?>">Actions<i class="fa fa-caret-down"></i></button><div class="fm-action-dropdown" data-menu="form-<?= (int)$form['form_id'] ?>">
<a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/preview.php?id=<?= (int)$form['form_id'] ?>"><i class="fa fa-eye"></i>Preview</a><a href="#" class="copy-shortcode" data-code="<?= e($code) ?>"><i class="fa fa-files-o"></i>Copy Shortcode</a>
<a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=clone&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>"><i class="fa fa-clone"></i>Clone Form</a>
<a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=toggle&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>"><?= $enabled?'<i class="fa fa-power-off"></i>Disable':'<i class="fa fa-toggle-on"></i>Enable' ?></a>
<a class="danger-action" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=delete&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>" onclick="return confirm('Delete this form and its fields?')"><i class="fa fa-times"></i>Delete</a></div></div></td>
<td><a class="fm-preview-thumb" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/preview.php?id=<?= (int)$form['form_id'] ?>"><div class="fm-mini"><?= renderMiniForm(loadBuilderFields($pdo,$form['form_name'])) ?></div></a></td><td><?= e($form['form_title']?:$form['form_name']) ?></td><td><code><?= e($code) ?></code></td><td class="fm-date"><?= e(date('m/d/Y',strtotime($form['revision_timestamp']))) ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div>
<div class="fm-showing">Showing 1 to <?= count($custom) ?> of <?= count($custom) ?> entries</div>
<section class="fm-default-section"><h3>Default Forms (<?= count($catalog) ?>)</h3><table class="fm-table default-table"><thead><tr><th>Actions</th><th>Form Name</th><th>Shortcode</th><th>Last Update</th></tr></thead><tbody>
<?php foreach($catalog as $item): ?><tr><td><div class="fm-action-menu"><button type="button" class="fm-action-toggle" data-menu-toggle="default-<?= (int)$item['default_form_id'] ?>">Actions<i class="fa fa-caret-down"></i></button><div class="fm-action-dropdown" data-menu="default-<?= (int)$item['default_form_id'] ?>"><a href="#" class="copy-shortcode" data-code="[form=<?= e($item['short_code']) ?>]"><i class="fa fa-files-o"></i>Copy Shortcode</a><a href="#" onclick="alert('System default forms are protected in this task.');return false;"><i class="fa fa-info-circle"></i>View Details</a></div></div></td><td><span class="default-badge">DEFAULT FORM</span> &nbsp;<?= e($item['form_name']) ?></td><td><code>[form=<?= e($item['short_code']) ?>]</code></td><td class="fm-date"><?= e($item['last_update']) ?></td></tr><?php endforeach; ?>
</tbody></table><div class="fm-showing">Showing 1 to <?= count($catalog) ?> of <?= count($catalog) ?> entries</div></section>

<?php if (isset($_GET['new'])): ?>
<div class="create-modal-backdrop">
 <section class="create-modal" role="dialog" aria-modal="true" aria-labelledby="createFormTitle">
  <div class="create-modal-title"><span id="createFormTitle">Create New Form</span><a class="create-modal-close" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/index.php" aria-label="Close dialog">&times;</a></div>
  <form method="post" action="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/create.php" class="create-modal-body" id="newFormStart">
   <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
   <div class="create-tip"><b>●</b> On the next page you can create new fields for your form.</div>
   <label for="formNickname">* Form Nickname</label>
   <input type="text" name="form_nickname" id="formNickname" autocomplete="off" required placeholder="Enter form nickname">
   <small>Form nickname is for internal use only and will not be seen by website visitors.</small>
   <label for="formVariable">* Form Variable Name</label>
   <input type="text" id="formVariable" readonly placeholder="form_variable_name">
   <small>Name used by the system. Variable name can include lowercase letters and underscores.</small>
   <div class="create-modal-footer"><button type="submit" class="save-continue">Save &amp; Continue</button></div>
  </form>
 </section>
</div>
<script>
(function(){var n=document.getElementById('formNickname'),v=document.getElementById('formVariable');
function variable(s){return s.toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').replace(/_+/g,'_');}
n.addEventListener('input',function(){v.value=variable(n.value);});
document.getElementById('newFormStart').addEventListener('submit',function(e){if(!v.value){e.preventDefault();n.focus();alert('Enter a nickname containing at least one letter or number.');}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){window.location.href=<?= json_encode($config['base_url'].'/admin/toolbox/form-manager/index.php') ?>;}});
document.querySelector('.create-modal-backdrop').addEventListener('click',function(e){if(e.target===this){window.location.href=<?= json_encode($config['base_url'].'/admin/toolbox/form-manager/index.php') ?>;}});
n.focus();
}());
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
