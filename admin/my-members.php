<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$pageTitle='My Members';$activeMenu='members';$pageClass='my-members-page';
$search=trim((string)($_GET['q']??''));$planFilter=(int)($_GET['plan']??0);
$statusFilter=(string)($_GET['status']??'');$categoryFilter=(int)($_GET['category']??0);
$cityFilter=trim((string)($_GET['location']??''));$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));
$where=[];$params=[];
if($search!==''){$where[]="(u.user_id=? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.company LIKE ?)";$like='%'.$search.'%';$params[]=ctype_digit($search)?(int)$search:0;array_push($params,$like,$like,$like,$like);}
if($planFilter>0){$where[]='u.subscription_id=?';$params[]=$planFilter;}
if($statusFilter==='active')$where[]='u.active=2';elseif($statusFilter==='inactive')$where[]='u.active<>2';
if($categoryFilter>0){$where[]='u.profession_id=?';$params[]=$categoryFilter;}
if($cityFilter!==''){$where[]='(u.city LIKE ? OR u.state_ln LIKE ? OR u.zip_code LIKE ?)';$like='%'.$cityFilter.'%';array_push($params,$like,$like,$like);}
if($from!==''){$where[]='u.signup_date>=?';$params[]=preg_replace('/[^0-9]/','',$from).'000000';}
if($to!==''){$where[]='u.signup_date<=?';$params[]=preg_replace('/[^0-9]/','',$to).'235959';}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$plans=$pdo->query('SELECT subscription_id,subscription_name,monthly_amount FROM subscription_types ORDER BY subscription_id')->fetchAll();
$categories=$pdo->query('SELECT profession_id,name FROM list_professions ORDER BY sort_order,name')->fetchAll();
$q=$pdo->prepare('SELECT COUNT(*) FROM users_data u'.$whereSql);$q->execute($params);$total=(int)$q->fetchColumn();
$page=max(1,(int)($_GET['page']??1));$perPage=20;$offset=($page-1)*$perPage;
$q=$pdo->prepare("SELECT u.user_id,u.first_name,u.last_name,u.email,u.company,u.phone_number,u.city,u.state_ln,u.country_ln,u.active,u.subscription_id,u.signup_date,u.profession_id,u.filename,p.subscription_name,p.monthly_amount,p.yearly_amount,c.name AS category_name
 FROM users_data u LEFT JOIN subscription_types p ON p.subscription_id=u.subscription_id LEFT JOIN list_professions c ON c.profession_id=u.profession_id
 $whereSql ORDER BY u.user_id DESC LIMIT $perPage OFFSET $offset");
$q->execute($params);$members=$q->fetchAll();
$extraHead=<<<'HTML'
<style>
.my-members-page{background:#f7f8fa}.member-manager{background:#fff;border:1px solid #e2e6eb;border-radius:7px;padding:16px}.member-manager h1{font-size:22px;margin:0 0 5px}.member-manager .intro{color:#718093;margin:0 0 16px;font-size:13px}.member-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:8px;margin-bottom:10px}.member-filter-grid input,.member-filter-grid select{width:100%;height:38px;border:1px solid #d5dce4;border-radius:5px;background:#fff;padding:0 10px;color:#34485c;font:13px Arial}.member-filter-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:20px}.member-filter-actions button,.member-filter-actions a{border:0;border-radius:5px;padding:10px 18px;background:#286bd6;color:#fff;text-decoration:none;font-weight:600;cursor:pointer}.member-filter-actions a{background:#e9edf2;color:#33495e}.member-manager-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:12px 0;color:#64748b;font-size:13px}.member-manager-toolbar select{padding:7px;border:1px solid #d5dce4;border-radius:5px}.member-table-wrap{overflow:auto;border:1px solid #e2e6eb;border-radius:5px}.member-manager table{width:100%;border-collapse:collapse;min-width:850px}.member-manager th{background:#24384b;color:#fff;text-align:left;font-size:13px;padding:12px}.member-manager td{padding:12px;border-bottom:1px solid #e8ebef;vertical-align:top;font-size:12px;color:#42566b}.member-manager tr:nth-child(even){background:#fafbfc}.member-manager td a{color:#286bd6;text-decoration:none}.member-state{display:inline-block;padding:4px 8px;border-radius:4px;background:#d6f4ed;color:#087a61;font-weight:600}.member-state.inactive{background:#fbe5e5;color:#a33}.member-avatar{width:52px;height:52px;border-radius:50%;background:#e3e8ed;display:grid;place-items:center;color:#9aa7b4;font-size:24px;margin-bottom:7px}.member-no-revenue{color:#d22;font-weight:700}.member-action{display:inline-block;padding:6px 10px;border:1px solid #d7dfe7;border-radius:4px;background:#fff}.member-pager{display:flex;gap:8px;margin-top:15px}.member-pager a{padding:7px 12px;border-radius:4px;background:#edf2f7;color:#2c4b67;text-decoration:none}.member-pager a.current{background:#286bd6;color:#fff}@media(max-width:900px){.member-filter-grid{grid-template-columns:repeat(2,minmax(140px,1fr))}}@media(max-width:520px){.member-filter-grid{grid-template-columns:1fr}.member-manager-toolbar{align-items:flex-start;flex-direction:column}}
</style>
HTML;
require __DIR__ . '/../includes/header.php';
?>
<section class="member-manager">
  <h1>Search Members</h1><p class="intro">Find and manage member accounts, membership plans and listing information.</p>
  <form method="get">
    <div class="member-filter-grid">
      <input name="q" value="<?=e($search)?>" placeholder="Search Name, ID, Email or Company">
      <select name="plan"><option value="0">All Membership Plans</option><?php foreach($plans as $pl):?><option value="<?= (int)$pl['subscription_id'] ?>" <?=$planFilter===(int)$pl['subscription_id']?'selected':''?>><?=e($pl['subscription_name'])?></option><?php endforeach?></select>
      <select name="category"><option value="0">Select a Top-Level Category</option><?php foreach($categories as $cat):?><option value="<?= (int)$cat['profession_id'] ?>" <?=$categoryFilter===(int)$cat['profession_id']?'selected':''?>><?=e($cat['name'])?></option><?php endforeach?></select>
      <input name="location" value="<?=e($cityFilter)?>" placeholder="Search Location">
      <select name="status"><option value="">All Account Statuses</option><option value="active" <?=$statusFilter==='active'?'selected':''?>>Active</option><option value="inactive" <?=$statusFilter==='inactive'?'selected':''?>>Inactive</option></select>
      <input type="date" name="from" value="<?=e($from)?>" aria-label="Joined from">
      <input type="date" name="to" value="<?=e($to)?>" aria-label="Joined to">
      <select disabled><option>All Member Types</option></select>
    </div>
    <div class="member-filter-actions"><a href="my-members.php">Reset</a><button type="submit"><i class="fa fa-search"></i> Search Members</button></div>
  </form>
  <div class="member-manager-toolbar"><span>Showing <?=count($members)?($offset+1).'–'.min($total,$offset+count($members)):0?> of <?=number_format($total)?> member results</span><span>Sort: <select disabled><option>Newest First</option></select></span></div>
  <div class="member-table-wrap"><table><thead><tr><th>Status</th><th>Contact Information</th><th>Membership</th><th>Revenue</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($members as $m):?>
    <tr>
      <td><div class="member-avatar"><i class="fa fa-user"></i></div><span class="member-state <?=((int)$m['active']===2?'':'inactive')?>"><?=((int)$m['active']===2?'Active':'Inactive')?></span></td>
      <td><strong><a href="<?=e($config['base_url'])?>/website/search.php?listing=<?=(int)$m['user_id']?>" target="_blank"><?=e(trim($m['first_name'].' '.$m['last_name']))?></a></strong><br><?=e($m['company'])?><br><a href="mailto:<?=e($m['email'])?>"><?=e($m['email'])?></a><br><?=e($m['phone_number'])?><br><?=e(trim($m['city'].', '.$m['state_ln'].', '.$m['country_ln']))?><br><?=e($m['category_name']??'')?></td>
      <td>Member ID: #<?=(int)$m['user_id']?><br><?=e($m['subscription_name']??'Membership Plan')?><br>Joined: <?=e(substr((string)$m['signup_date'],6,2).'/'.substr((string)$m['signup_date'],4,2).'/'.substr((string)$m['signup_date'],0,4))?></td>
      <td><span class="member-no-revenue">No Revenue</span></td>
      <td><a class="member-action" href="<?=e($config['base_url'])?>/website/search.php?listing=<?=(int)$m['user_id']?>" target="_blank">View Listing</a></td>
    </tr>
  <?php endforeach?>
  <?php if(!$members):?><tr><td colspan="5">No members match these filters.</td></tr><?php endif?>
  </tbody></table></div>
  <div class="member-pager"><?php if($page>1):?><a href="?<?=e(http_build_query(array_merge($_GET,['page'=>$page-1])))?>">Previous</a><?php endif?><?php if($offset+$perPage<$total):?><a href="?<?=e(http_build_query(array_merge($_GET,['page'=>$page+1])))?>">Next</a><?php endif?></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
