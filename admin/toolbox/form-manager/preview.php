<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../website/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forms WHERE form_id = ?');
$stmt->execute([$id]);
$form = $stmt->fetch();
if (!$form) {
    flash('warning', 'Form not found.');
    redirect('/admin/toolbox/form-manager/index.php');
}

$fieldStmt = $pdo->prepare('SELECT * FROM form_fields WHERE form_name = ? ORDER BY field_order, field_id');
$fieldStmt->execute([$form['form_name']]);
$fields = $fieldStmt->fetchAll();
$fieldData = ['countries' => [], 'states' => [], 'categories' => []];
if (tableExists($pdo, 'list_countries')) {
    $fieldData['countries'] = $pdo->query('SELECT country_name FROM list_countries WHERE active = 1 ORDER BY country_name')->fetchAll(PDO::FETCH_COLUMN);
}
if (tableExists($pdo, 'list_states')) {
    $fieldData['states'] = $pdo->query("SELECT DISTINCT name FROM list_states WHERE name IS NOT NULL AND name <> '' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}
if (tableExists($pdo, 'data_categories')) {
    $fieldData['categories'] = $pdo->query("SELECT data_name FROM data_categories WHERE data_active = 1 AND data_name <> '' ORDER BY display_order, data_name")->fetchAll(PDO::FETCH_COLUMN);
}

$renderField = static function (array $field) use ($fieldData): string {
    $originalType = (string)$field['field_type'];
    $normalizedType = strtolower((string)preg_replace('/[\s_-]+/', '', $originalType));
    $aliases = [
        'textbox' => 'text', 'emailaddress' => 'email', 'phone' => 'tel',
        'phonenumber' => 'tel', 'yesno' => 'yes_no', 'button' => 'submit',
        'submitformbutton' => 'submit', 'sectiontitle' => 'section',
        'checkbox' => 'checkbox_group', 'checkboxes' => 'checkbox_group',
        'checkboxgroup' => 'checkbox_group', 'dropdownlist' => 'select',
        'multiplechoiceradios' => 'radio', 'countrylist' => 'country',
        'statelist' => 'state', 'topcategory' => 'top_category',
        'datetimelocal' => 'datetime-local', 'recaptcha' => 'captcha',
        'custom' => 'custom', 'html' => 'html', 'honeypot' => 'honeypot',
    ];
    $type = $aliases[$normalizedType] ?? $originalType;
    $label = e($field['field_text']);
    $placeholder = e($field['field_placeholder']);
    $value = e($field['default_value']);
    $name = e($field['field_name']);
    $inputClass = e($field['input_class'] ?: 'form-control');
    $displayClass = e($field['display_class'] ?: 'form-group');
    $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$field['field_options']))));
    if (!$options) {
        if ($type === 'country') $options = $fieldData['countries'];
        if ($type === 'state') $options = $fieldData['states'];
        if ($type === 'top_category') $options = $fieldData['categories'];
        if ($type === 'years') {
            $year = (int)date('Y');
            $options = array_map(static function ($offset) use ($year) { return (string)($year - $offset); }, range(0, 29));
        }
    }

    if ($type === 'section') return '<h4 class="preview-section-title">' . $label . '</h4>';
    if ($type === 'html') {
        $template = form_php_template_name((string)$field['field_text']);
        $content = $template !== '' ? (string)$field['field_text'] : (string)($field['field_ldesc'] ?: $field['field_text']);
        if ($template === '') $template = form_php_template_name($content);
        if ($template !== '') return '<div class="preview-custom-html">' . render_form_php_template($template, ['mjd_member_photos'=>['photo'=>'','logo'=>''], 'mjd_member_id'=>0, 'mjd_form_errors'=>[]]) . '</div>';
        return '<div class="preview-custom-html">' . e($content) . '</div>';
    }
    if ($type === 'custom') return '<div class="preview-special-field">Custom field: ' . $label . '</div>';
    if ($type === 'help_alert') return '<div class="preview-help-alert">' . e($field['field_ldesc'] ?: $field['field_sdesc'] ?: $field['field_text']) . '</div>';
    if ($type === 'honeypot') return '<div class="preview-special-field">Honeypot spam protection field</div>';
    if ($type === 'captcha') return '<div class="captcha-placeholder"><input type="checkbox" disabled> I am not a robot (preview only)</div>';
    if ($type === 'submit') return '<button class="btn btn-primary preview-submit" type="button" disabled>' . ($label ?: 'Submit Form') . '</button>';
    if ($type === 'hidden') return '<div class="preview-special-field">Hidden field · default value: ' . ($value ?: '(empty)') . '</div>';

    $required = (int)$field['field_required'] ? ' <span class="required-star">*</span>' : '';
    $fieldLabel = '<label class="preview-field-label">' . $label . $required . '</label>';
    $help = $field['field_sdesc'] ? '<small class="preview-field-help">' . e($field['field_sdesc']) . '</small>' : '';
    $disabled = ' disabled';

    if (in_array($type, ['select', 'country', 'state', 'top_category', 'years'], true)) {
        $control = '<select class="' . $inputClass . '"' . $disabled . '><option value="">' . ($placeholder ?: 'Select an option') . '</option>';
        foreach ($options as $option) {
            $control .= '<option' . ((string)$option === (string)$field['default_value'] ? ' selected' : '') . '>' . e($option) . '</option>';
        }
        $control .= '</select>';
    } elseif (in_array($type, ['radio', 'checkbox_group', 'yes_no'], true)) {
        $choices = $type === 'yes_no' ? ['Yes', 'No'] : $options;
        $control = '<div class="preview-choice-list">';
        foreach ($choices as $option) {
            $choiceType = $type === 'checkbox_group' ? 'checkbox' : 'radio';
            $control .= '<label><input type="' . $choiceType . '"' . ((string)$option === (string)$field['default_value'] ? ' checked' : '') . $disabled . '> ' . e($option) . '</label>';
        }
        $control .= '</div>';
    } elseif (in_array($type, ['textarea', 'richtext', 'richtext_image', 'richtext_elements', 'richtext_media'], true)) {
        $control = '<textarea class="' . $inputClass . '" placeholder="' . $placeholder . '"' . $disabled . '>' . $value . '</textarea>';
    } elseif ($type === 'phone_country') {
        $control = '<div class="preview-phone-country"><select class="form-control" disabled><option>+1</option><option>+44</option><option>+61</option><option>+91</option></select><input class="form-control" type="tel" name="' . $name . '" placeholder="' . $placeholder . '"' . $disabled . '></div>';
    } else {
        $allowedTypes = ['email', 'tel', 'number', 'password', 'url', 'date', 'datetime-local', 'file', 'price'];
        $inputType = in_array($type, $allowedTypes, true) ? ($type === 'price' ? 'number' : $type) : 'text';
        $inputValue = $inputType === 'file' ? '' : ' value="' . $value . '"';
        $control = '<input class="' . $inputClass . '" type="' . e($inputType) . '" name="' . $name . '" placeholder="' . $placeholder . '"' . $inputValue . $disabled . '>';
    }

    if ($field['field_input_view'] === '0') {
        $control = '';
    }
    return '<div class="' . $displayClass . '">' . $fieldLabel . $control . $help . '</div>';
};

$pageTitle = 'Form Preview';
$activeMenu = 'forms';
require __DIR__ . '/../../../includes/header.php';
?>
<div class="module-intro">
    <div><h2>Form Preview</h2><p><?= e($form['form_title']) ?> · <?= e($form['form_name']) ?></p></div>
    <div>
        <a class="btn btn-default" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/index.php">Back</a>
        <?php if ($form['form_action_type'] !== 'default'): ?><a class="btn btn-primary" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/edit.php?id=<?= $id ?>">Edit Form</a><?php endif; ?>
    </div>
</div>
<div class="preview-page-card">
    <h3><?= e($form['form_title']) ?></h3>
    <p><?= e($form['form_desc']) ?></p>
    <?php foreach ($fields as $field): if ($field['field_display_view'] !== '0'): ?><?= $renderField($field) ?><?php endif; endforeach; ?>
    <?php if (!$fields): ?><div class="empty-state">This form does not have any fields yet.</div><?php endif; ?>
    <div class="shortcode-preview">Shortcode: <code><?= e($form['short_code'] ?: '[form=' . $form['form_name'] . ']') ?></code></div>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
