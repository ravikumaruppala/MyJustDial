<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
$token = $_GET['token'] ?? '';
if (!hash_equals(csrfToken(), $token)) { http_response_code(419); exit('Invalid action token. Return to Form Manager and try again.'); }
/* Bulk Actions toolbar: enable / disable / delete every ticked custom form. */
if (($_GET['action'] ?? '') === 'bulk') {
    $op = $_GET['op'] ?? '';
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))))));
    if (!$ids || !in_array($op, ['enable', 'disable', 'delete'], true)) { flash('warning', 'Choose a bulk action and tick at least one form.'); redirect('/admin/toolbox/form-manager/index.php'); }
    if ($op !== 'delete' && !tableExists($pdo, 'users_meta')) { flash('warning', 'Form status cannot be stored because the users_meta table is missing.'); redirect('/admin/toolbox/form-manager/index.php'); }
    $done = 0;
    $find = $pdo->prepare('SELECT form_id, form_name FROM forms WHERE form_id=?');
    $pdo->beginTransaction();
    foreach ($ids as $formId) {
        $find->execute([$formId]); $row = $find->fetch();
        if (!$row) { continue; }
        if ($op === 'delete') {
            $pdo->prepare('DELETE FROM form_fields WHERE form_name=?')->execute([$row['form_name']]);
            $pdo->prepare('DELETE FROM forms WHERE form_id=?')->execute([$formId]);
            if (tableExists($pdo, 'users_meta')) { $pdo->prepare("DELETE FROM users_meta WHERE `database`='forms' AND database_id=?")->execute([$formId]); }
        } else {
            $pdo->prepare("DELETE FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_status'")->execute([$formId]);
            $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('forms',?,'form_status',?,?)")->execute([$formId, $op === 'enable' ? 'enabled' : 'disabled', date('YmdHis')]);
        }
        $done++;
    }
    $pdo->commit();
    $verb = ['enable' => 'enabled', 'disable' => 'disabled', 'delete' => 'deleted'][$op];
    flash('success', $done . ' form' . ($done === 1 ? '' : 's') . ' ' . $verb . '.');
    redirect('/admin/toolbox/form-manager/index.php');
}
$id = (int)($_GET['id'] ?? 0); $action = $_GET['action'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM forms WHERE form_id=?"); $stmt->execute([$id]); $form = $stmt->fetch();
if (!$form) { flash('warning','The selected custom form was not found.'); redirect('/admin/toolbox/form-manager/index.php'); }
if ($action === 'delete') {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM form_fields WHERE form_name=?')->execute([$form['form_name']]);
    $pdo->prepare('DELETE FROM forms WHERE form_id=?')->execute([$id]);
    if (tableExists($pdo,'users_meta')) { $pdo->prepare("DELETE FROM users_meta WHERE `database`='forms' AND database_id=?")->execute([$id]); }
    $pdo->commit(); flash('success','Form and its fields were deleted.');
} elseif ($action === 'toggle') {
    $meta = $pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_status' LIMIT 1"); $meta->execute([$id]);
    $next = $meta->fetchColumn() === 'disabled' ? 'enabled' : 'disabled';
    $pdo->prepare("DELETE FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_status'")->execute([$id]);
    $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('forms',?,'form_status',?,?)")->execute([$id,$next,date('YmdHis')]);
    flash('success','Form status changed to '.$next.'.');
} elseif ($action === 'clone') {
    $pdo->beginTransaction();
    $newName = $form['form_name'].'_copy'; $suffix = 2;
    $check = $pdo->prepare('SELECT COUNT(*) FROM forms WHERE form_name=?');
    while (true) { $check->execute([$newName]); if (!(int)$check->fetchColumn()) break; $newName = $form['form_name'].'_copy_'.$suffix++; }
    $insert = $pdo->prepare("INSERT INTO forms (form_name,form_desc,form_url,form_target,form_action,form_database,form_table,table_index,form_email_recipient,form_title,form_email_on,email_template,email_admin,email_from,email_template_admin,form_class,form_style,form_element_id,short_code,form_layout,trigger_data_flow,data_flow_name,form_action_type,form_action_div) VALUES (?,?,? ,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insert->execute([$newName,$form['form_desc'],$form['form_url'],$form['form_target'],$form['form_action'],$form['form_database'],$form['form_table'],$form['table_index'],$form['form_email_recipient'],$form['form_title'].' (Copy)',(int)$form['form_email_on'],$form['email_template'],(int)$form['email_admin'],$form['email_from'],$form['email_template_admin'],$form['form_class'],$form['form_style'],$form['form_element_id'],'[form='.$newName.']',$form['form_layout'],$form['trigger_data_flow'],$form['data_flow_name'],'widget',$form['form_action_div']]);
    $newId = (int)$pdo->lastInsertId();
    $fields = $pdo->prepare('SELECT * FROM form_fields WHERE form_name=? ORDER BY field_order,field_id'); $fields->execute([$form['form_name']]);
    $copy = $pdo->prepare('INSERT INTO form_fields (form_name,field_text,field_name,field_type,field_order,field_sdesc,field_ldesc,field_placeholder,field_required,field_options,input_class,display_class,default_value,field_display_view,field_input_view,field_grid_view,field_email_view,form_section,field_search_view,display_div_id,input_div_id,field_icon,json_meta) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($fields as $field) { $copy->execute([$newName,$field['field_text'],$field['field_name'],$field['field_type'],$field['field_order'],$field['field_sdesc'],$field['field_ldesc'],$field['field_placeholder'],$field['field_required'],$field['field_options'],$field['input_class'],$field['display_class'],$field['default_value'],$field['field_display_view'],$field['field_input_view'],$field['field_grid_view'],$field['field_email_view'],$field['form_section'],$field['field_search_view'],$field['display_div_id'],$field['input_div_id'],$field['field_icon'],$field['json_meta']]); }
    $pdo->commit(); flash('success','Form cloned successfully.');
} else { flash('warning','Unknown form action.'); }
redirect('/admin/toolbox/form-manager/index.php');
