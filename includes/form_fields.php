<?php
// Shared helpers for the Form Manager field builder (editor, save endpoint).

/** Field-type dropdown: exactly the groups and labels shown in the Brilliant Directories reference. */
function formFieldGroups(): array {
    return [
        'Select Fields' => [
            'Checkboxes' => 'checkbox_group',
            'Dropdown List' => 'select',
            'Multiple Choice Radios' => 'radio',
            'Yes or No' => 'yes_no',
        ],
        'Text Inputs' => [
            'Custom HTML' => 'html',
            'Email Address' => 'email',
            'Section Title' => 'section',
            'Submit Form Button' => 'submit',
            'Text - Single Line' => 'text',
            'Textarea - Paragraph' => 'textarea',
            'Website URL' => 'url',
        ],
        'Fancy Fields' => [
            'Calendar - Date Picker' => 'date',
            'Calendar - Date & Time Picker' => 'datetime-local',
            'File Upload Field' => 'file',
            'Froala Text Editor - Basic' => 'richtext',
            'Froala Text Editor - Image Upload' => 'richtext_image',
            'Froala Text Editor - Image Upload + Pre-Made Elements' => 'richtext_elements',
            'Froala Text Editor - Media Manager Access' => 'richtext_media',
            'Help Alert Box' => 'help_alert',
            'Hidden Field' => 'hidden',
            'Location - Country List' => 'country',
            'Location - State List' => 'state',
            'Number - Integer Only' => 'number',
            'Password Field' => 'password',
            'Phone Number Field' => 'tel',
            'Phone Number + Country Code' => 'phone_country',
            'Price Field' => 'price',
            'Security - Google reCaptcha' => 'captcha',
            'Security - Honeypot Field' => 'honeypot',
            'Top Category List' => 'top_category',
            'Years' => 'years',
        ],
    ];
}

/** Types the directory platform already stores in form_fields.field_type. */
function formFieldStoredNames(): array {
    return [
        'text' => 'Textbox', 'email' => 'Email', 'tel' => 'Phone', 'textarea' => 'textarea',
        'html' => 'HTML', 'submit' => 'Button', 'honeypot' => 'HoneyPot', 'captcha' => 'ReCaptcha',
        'yes_no' => 'YesNo', 'password' => 'Password',
    ];
}

/** Convert a stored field_type (e.g. "Textbox", "YesNo", "Custom") to the editor's internal key. */
function formFieldInternalType(string $stored): string {
    $key = strtolower(preg_replace('/[\s_-]+/', '', $stored));
    $aliases = [
        'textbox' => 'text', 'text' => 'text', 'email' => 'email', 'emailaddress' => 'email',
        'phone' => 'tel', 'phonenumber' => 'tel', 'tel' => 'tel', 'textarea' => 'textarea',
        'yesno' => 'yes_no', 'button' => 'submit', 'submit' => 'submit', 'submitformbutton' => 'submit',
        'html' => 'html', 'custom' => 'html', 'section' => 'section', 'sectiontitle' => 'section',
        'honeypot' => 'honeypot', 'recaptcha' => 'captcha', 'captcha' => 'captcha',
        'select' => 'select', 'dropdown' => 'select', 'dropdownlist' => 'select',
        'checkbox' => 'checkbox_group', 'checkboxes' => 'checkbox_group', 'checkboxgroup' => 'checkbox_group',
        'radio' => 'radio', 'multiplechoiceradios' => 'radio', 'price' => 'price',
        'country' => 'country', 'countrylist' => 'country', 'state' => 'state', 'statelist' => 'state',
        'years' => 'years', 'topcategory' => 'top_category', 'date' => 'date', 'file' => 'file',
        'number' => 'number', 'integer' => 'number', 'password' => 'password', 'url' => 'url',
        'websiteurl' => 'url', 'datetime' => 'datetime-local', 'datetimelocal' => 'datetime-local',
        'hidden' => 'hidden', 'helpalert' => 'help_alert', 'phonecountry' => 'phone_country',
        'richtext' => 'richtext', 'richtextimage' => 'richtext_image',
        'richtextelements' => 'richtext_elements', 'richtextmedia' => 'richtext_media',
    ];
    return $aliases[$key] ?? $stored;
}

/** Convert an internal key to the value written to form_fields.field_type. */
function formFieldStoredType(string $internal, string $originalStored = ''): string {
    // An untouched field keeps exactly what was in the database (e.g. "Custom").
    if ($originalStored !== '' && formFieldInternalType($originalStored) === $internal) {
        return $originalStored;
    }
    $map = formFieldStoredNames();
    return $map[$internal] ?? $internal;
}

/** Load a form's fields from the database in the shape the builder (and its preview) uses. */
function loadBuilderFields(PDO $pdo, string $formName): array {
    $stmt = $pdo->prepare('SELECT * FROM form_fields WHERE form_name=? ORDER BY field_order, field_id');
    $stmt->execute([$formName]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $meta = json_decode($row['json_meta'] ?: '{}', true);
        if (!is_array($meta)) { $meta = []; }
        $out[] = [
            'id' => 'f' . $row['field_id'],
            'type' => formFieldInternalType((string)$row['field_type']),
            'origType' => (string)$row['field_type'],
            'label' => (string)$row['field_text'],
            'name' => (string)$row['field_name'],
            'required' => (bool)$row['field_required'],
            'placeholder' => (string)$row['field_placeholder'],
            'help' => (string)$row['field_sdesc'],
            'description' => (string)$row['field_ldesc'],
            'options' => (string)$row['field_options'],
            'defaultValue' => (string)$row['default_value'],
            'inputClass' => (string)$row['input_class'],
            'displayClass' => (string)$row['display_class'],
            'outerId' => (string)$row['display_div_id'],
            'settings' => [
                'inputView' => $row['field_input_view'] !== '0',
                'displayView' => $row['field_display_view'] !== '0',
                // Matches the reference data: field_search_view = Lead Previews, field_grid_view = Table View.
                'leadPreviews' => $row['field_search_view'] === '1',
                'includeEmails' => $row['field_email_view'] !== '0',
                'tableView' => $row['field_grid_view'] !== '0',
                'adminOnly' => !empty($meta['adminOnly']),
                'altInput' => (string)($meta['altInput'] ?? ''),
                'altDisplay' => (string)($meta['altDisplay'] ?? ''),
                'altEmail' => (string)($meta['altEmail'] ?? ''),
                'altTable' => (string)($meta['altTable'] ?? ''),
            ],
        ];
    }
    return $out;
}

/** Country / state / category lists used by the preview. */
function loadBuilderLists(PDO $pdo): array {
    $lists = ['countries' => [], 'states' => [], 'categories' => []];
    if (tableExists($pdo, 'list_countries')) {
        $lists['countries'] = $pdo->query("SELECT country_name FROM list_countries WHERE active = 1 ORDER BY country_name")->fetchAll(PDO::FETCH_COLUMN);
    }
    if (tableExists($pdo, 'list_states')) {
        $lists['states'] = $pdo->query("SELECT DISTINCT name FROM list_states WHERE name IS NOT NULL AND name <> '' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }
    if (tableExists($pdo, 'data_categories')) {
        $lists['categories'] = $pdo->query("SELECT data_name FROM data_categories WHERE data_active = 1 AND data_name <> '' ORDER BY display_order, data_name")->fetchAll(PDO::FETCH_COLUMN);
    }
    return $lists;
}

/** A static, non-interactive miniature of a form (used for the thumbnails in Form Manager). */
function renderMiniForm(array $fields): string {
    $html = '';
    foreach ($fields as $f) {
        $s = $f['settings'] ?? [];
        if (isset($s['inputView']) && !$s['inputView']) { continue; }
        $t = $f['type'];
        $label = e($f['label']);
        if ($t === 'honeypot' || $t === 'hidden') { continue; }
        if ($t === 'section') { $html .= '<div class="mf-section">' . $label . '</div>'; continue; }
        if ($t === 'submit') { $html .= '<div class="mf"><div class="mf-submit">' . e($f['label'] ?: 'Submit') . '</div></div>'; continue; }
        if ($t === 'captcha') { $html .= '<div class="mf"><div class="mf-note">I\'m not a robot</div></div>'; continue; }
        if ($t === 'html') {
            $text = (string)($f['description'] !== '' ? $f['description'] : $f['label']);
            if (preg_match('/^\s*\[widget=/i', $text)) {
                $html .= '<div class="mf"><div class="mf-note">Theme widget placeholder</div></div>';
            } else {
                $html .= '<div class="mf mf-custom-html">' . $text . '</div>';
            }
            continue;
        }
        if ($t === 'help_alert') { $html .= '<div class="mf"><div class="mf-note">' . e($f['description'] ?: $f['label']) . '</div></div>'; continue; }
        $star = !empty($f['required']) ? '<i>*</i>' : '';
        $html .= '<div class="mf"><div class="mf-label">' . $star . $label . '</div>';
        if (in_array($t, ['radio', 'checkbox_group', 'yes_no'], true)) {
            $opts = $t === 'yes_no' ? ['Yes', 'No'] : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$f['options']))));
            if (!$opts) { $opts = ['Option 1', 'Option 2']; }
            foreach (array_slice($opts, 0, 3) as $o) {
                $html .= '<div class="mf-choice' . ($t === 'checkbox_group' ? ' is-box' : '') . '">' . e($o) . '</div>';
            }
        } elseif ($t === 'textarea' || strpos($t, 'richtext') === 0) {
            $html .= '<div class="mf-ctl mf-area"></div>';
        } elseif ($t === 'select' || in_array($t, ['country', 'state', 'top_category', 'years', 'phone_country'], true)) {
            $html .= '<div class="mf-ctl mf-select"></div>';
        } else {
            $html .= '<div class="mf-ctl"></div>';
        }
        $html .= '</div>';
    }
    return $html !== '' ? $html : '<div class="mf-empty">No fields yet</div>';
}
