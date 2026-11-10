<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/toolbox/form-manager/index.php?new=1');
}

verifyCsrf();

$nickname = trim((string)($_POST['form_nickname'] ?? ''));
$variable = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $nickname), '_'));
$variable = preg_replace('/_+/', '_', $variable);

if ($nickname === '' || $variable === '') {
    flash('danger', 'Enter a form nickname containing at least one letter or number.');
    redirect('/admin/toolbox/form-manager/index.php?new=1');
}
if (strlen($nickname) > 255 || strlen($variable) > 255) {
    flash('danger', 'The form nickname is too long. Use 255 characters or fewer.');
    redirect('/admin/toolbox/form-manager/index.php?new=1');
}

try {
    $pdo->beginTransaction();
    $collision = $pdo->prepare('SELECT form_id FROM forms WHERE form_name = ? LIMIT 1 FOR UPDATE');
    $collision->execute([$variable]);
    if ($collision->fetchColumn()) {
        $pdo->rollBack();
        flash('danger', 'That form variable is already in use. Choose a different nickname.');
        redirect('/admin/toolbox/form-manager/index.php?new=1');
    }

    $insert = $pdo->prepare(
        "INSERT INTO forms (
            form_name, form_desc, form_url, form_target, form_action, form_database,
            form_table, table_index, form_email_recipient, form_title, form_email_on,
            email_template, email_admin, email_from, email_template_admin, form_class,
            form_style, form_element_id, short_code, form_layout, trigger_data_flow,
            data_flow_name, form_action_type, form_action_div
        ) VALUES (?, '', '', '', 'post', '', '', '', '', ?, 0, '', 0, '', '', 'form-control', '', '', ?, 'bootstrapvertical', '', '', 'widget', '')"
    );
    $insert->execute([$variable, $nickname, '[form=' . $variable . ']']);
    $formId = (int)$pdo->lastInsertId();

    if (tableExists($pdo, 'users_meta')) {
        $meta = $pdo->prepare(
            "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
             VALUES ('forms', ?, ?, ?, ?)"
        );
        $meta->execute([$formId, 'form_nickname', $nickname, date('YmdHis')]);
        $meta->execute([$formId, 'form_status', 'enabled', date('YmdHis')]);
    }

    $pdo->commit();
    redirect('/admin/toolbox/form-manager/edit.php?id=' . $formId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('danger', 'Unable to start the form: ' . $exception->getMessage());
    redirect('/admin/toolbox/form-manager/index.php?new=1');
}
