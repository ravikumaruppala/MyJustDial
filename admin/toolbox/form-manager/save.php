<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/form_fields.php';

/* The builder saves with fetch() and expects JSON; a plain form post still works as a fallback. */
$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function respond(bool $wantsJson, bool $ok, string $message, array $extra = [], string $redirectTo = '/admin/toolbox/form-manager/index.php'): void {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($ok ? 'success' : 'danger', $message);
    redirect($redirectTo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/admin/toolbox/form-manager/index.php'); }
if ($wantsJson && !hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    respond($wantsJson, false, 'Your session token expired. Refresh the page and try again.');
}
if (!$wantsJson) { verifyCsrf(); }

$id = (int)($_POST['form_id'] ?? 0);
$nickname = trim($_POST['form_nickname'] ?? '');
$title = trim($_POST['form_title'] ?? '');
$description = trim($_POST['form_desc'] ?? '');
$variable = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $nickname), '_'));
$variable = preg_replace('/_+/', '_', $variable);
$back = $id > 0 ? '/admin/toolbox/form-manager/edit.php?id=' . $id : '/admin/toolbox/form-manager/index.php';
if ($id <= 0) { respond($wantsJson, false, 'This form does not exist yet. Create it from Form Manager first.', [], $back); }
if ($nickname === '' || $variable === '') { respond($wantsJson, false, 'Enter a form nickname containing at least one letter or number.', [], $back); }

try {
    $fields = json_decode($_POST['fields_json'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($fields)) { throw new RuntimeException('Invalid fields data.'); }
    $pdo->beginTransaction();

    $check = $pdo->prepare("SELECT form_name, form_title FROM forms WHERE form_id=? FOR UPDATE"); $check->execute([$id]);
    $currentForm = $check->fetch(PDO::FETCH_ASSOC);
    if (!$currentForm) { throw new RuntimeException('This form cannot be edited.'); }
    $oldName = $currentForm['form_name'];
    $currentNickname = (string)($currentForm['form_title'] ?: $oldName);
    if (tableExists($pdo, 'users_meta')) {
        $nicknameStmt = $pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_nickname' LIMIT 1");
        $nicknameStmt->execute([$id]);
        $savedNickname = $nicknameStmt->fetchColumn();
        if ($savedNickname !== false) { $currentNickname = (string)$savedNickname; }
    }
    if ($nickname === $currentNickname) { $variable = $oldName; }
    $collision = $pdo->prepare('SELECT form_id FROM forms WHERE form_name=? AND form_id<>?'); $collision->execute([$variable, $id]);
    if ($collision->fetchColumn()) { throw new RuntimeException('That form variable is already in use. Choose a different nickname.'); }

    $stmt = $pdo->prepare("UPDATE forms SET form_name=?, form_title=?, form_desc=?, short_code=?, form_action='post', form_table='', table_index='', form_class='form-control', form_layout='bootstrapvertical', form_action_type='widget', form_action_div='' WHERE form_id=?");
    $stmt->execute([$variable, $title ?: $nickname, $description, '[form=' . $variable . ']', $id]);
    $pdo->prepare('DELETE FROM form_fields WHERE form_name=?')->execute([$oldName]);
    if ($oldName !== $variable) { $pdo->prepare('DELETE FROM form_fields WHERE form_name=?')->execute([$variable]); }

    $insert = $pdo->prepare('INSERT INTO form_fields (form_name,field_text,field_name,field_type,field_order,field_sdesc,field_ldesc,field_placeholder,field_required,field_options,input_class,display_class,default_value,field_display_view,field_input_view,field_grid_view,field_email_view,form_section,field_search_view,display_div_id,input_div_id,field_icon,json_meta) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $nonInput = ['section', 'html', 'help_alert', 'honeypot', 'captcha', 'submit'];
    $flag = static function ($settings, string $key, bool $default): string {
        return (array_key_exists($key, $settings) ? !empty($settings[$key]) : $default) ? '1' : '0';
    };
    foreach (array_values($fields) as $index => $field) {
        if (!is_array($field)) { continue; }
        $type = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($field['type'] ?? 'text')) ?: 'text';
        $label = trim((string)($field['label'] ?? ''));
        if ($label === '' && !in_array($type, ['honeypot', 'captcha'], true)) { $label = 'Field ' . ($index + 1); }
        $fieldName = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)($field['name'] ?? '')), '_'));
        if ($fieldName === '' && !in_array($type, $nonInput, true)) { $fieldName = 'field_' . ($index + 1); }
        $options = $field['options'] ?? '';
        if (is_array($options)) { $options = implode("\n", $options); }
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
        $meta = [
            'adminOnly' => !empty($settings['adminOnly']),
            'altInput' => (string)($settings['altInput'] ?? ''),
            'altDisplay' => (string)($settings['altDisplay'] ?? ''),
            'altEmail' => (string)($settings['altEmail'] ?? ''),
            'altTable' => (string)($settings['altTable'] ?? ''),
        ];
        $insert->execute([
            $variable, $label, $fieldName,
            formFieldStoredType($type, (string)($field['origType'] ?? '')),
            $index + 1,
            (string)($field['help'] ?? ''), (string)($field['description'] ?? ''), (string)($field['placeholder'] ?? ''),
            !empty($field['required']) ? 1 : 0, (string)$options,
            (string)($field['inputClass'] ?? ''), (string)($field['displayClass'] ?? ''), (string)($field['defaultValue'] ?? ''),
            $flag($settings, 'displayView', true), $flag($settings, 'inputView', true),
            $flag($settings, 'tableView', true),          // field_grid_view   = Table View
            $flag($settings, 'includeEmails', true),
            '',
            $flag($settings, 'leadPreviews', false),      // field_search_view = Lead Previews
            substr((string)($field['outerId'] ?? ''), 0, 50), '', '',
            json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    if (tableExists($pdo, 'users_meta')) {
        $pdo->prepare("DELETE FROM users_meta WHERE `database`='forms' AND database_id=? AND `key` IN ('form_nickname','form_status')")->execute([$id]);
        $meta = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('forms',?,?,?,?)");
        $now = date('YmdHis');
        $meta->execute([$id, 'form_nickname', $nickname, $now]);
        $meta->execute([$id, 'form_status', 'enabled', $now]);
    }
    $pdo->commit();

    // Return what the database now holds so the preview shows the stored form.
    respond($wantsJson, true, 'Form saved. Its fields are stored in the database.', [
        'fields' => loadBuilderFields($pdo, $variable),
        'form' => ['variable' => $variable, 'shortcode' => '[form=' . $variable . ']', 'title' => $title ?: $nickname, 'description' => $description],
    ], '/admin/toolbox/form-manager/edit.php?id=' . $id);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    respond($wantsJson, false, 'Unable to save the form: ' . $exception->getMessage(), [], $back);
}
