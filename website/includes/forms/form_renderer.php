<?php
/**
 * Form renderer - turns a form definition into the HTML a visitor fills in.
 * Entry point: render_form('form_name') (this is what the [form=form_name] shortcode calls).
 */

/** Country dialling codes offered by the "Phone Number + Country Code" field. */
function form_dial_codes(): array {
    return ['+91' => 'India (+91)', '+1' => 'USA/Canada (+1)', '+44' => 'UK (+44)', '+61' => 'Australia (+61)', '+971' => 'UAE (+971)',
            '+65' => 'Singapore (+65)', '+49' => 'Germany (+49)', '+33' => 'France (+33)', '+81' => 'Japan (+81)'];
}

/** Option list for select / radio / checkbox / list fields (own options first, then the stored lists). */
function form_field_options(PDO $pdo, array $f): array {
    static $lists = null;
    $own = split_lines($f['options']);
    if ($own) { return $own; }
    if ($lists === null) { $lists = loadBuilderLists($pdo); }   // countries, states, categories from the DB
    switch ($f['type']) {
        case 'country':      return $lists['countries'];
        case 'state':        return $lists['states'];
        case 'top_category': return $lists['categories'];
        case 'years':
            $year = (int)date('Y');
            return array_map('strval', range($year, $year - 80));
        case 'yes_no':       return ['Yes', 'No'];
    }
    return [];
}

/** Public entry point: HTML for the form (or a friendly notice when it is unavailable). */
function render_form(string $name, array $opts = []): string {
    global $pdo;
    $form = get_form($pdo, $name);
    if ($form === null) {
        return '<div class="mjd-notice">This form is not available right now.</div>';
    }
    if (!$form['fields']) {
        return '<div class="mjd-notice">This form has no fields yet.</div>';
    }

    // One-time result of a non-JavaScript submit (messages, errors and what the visitor typed).
    $flash = $_SESSION['mjd_form_flash'][$form['name']] ?? null;
    unset($_SESSION['mjd_form_flash'][$form['name']]);
    $old    = $flash['old'] ?? [];
    $errors = $flash['errors'] ?? [];

    $hasSubmit = false;
    $body = '';
    foreach ($form['fields'] as $field) {
        if ($field['type'] === 'submit') { $hasSubmit = true; }
        $body .= render_field($pdo, $form, $field, $old, $errors);
    }
    if (!$hasSubmit) { $body .= '<div class="mjd-field mjd-submit"><button type="submit" class="mjd-submit-btn">Submit</button></div>'; }

    $id = 'mjd-' . $form['name'];
    $html  = '<form class="mjd-form" id="' . e($id) . '" method="post" action="' . e(site_url('form-submit.php')) . '"'
           . ' enctype="multipart/form-data" data-form="' . e($form['name']) . '" novalidate>';
    $html .= '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
    $html .= '<input type="hidden" name="mjd_form" value="' . e($form['name']) . '">';
    $html .= '<input type="hidden" name="mjd_return" value="' . e(current_url()) . '">';
    if (!empty($opts['member_id'])) {
        $html .= '<input type="hidden" name="mjd_ctx_member" value="' . (int)$opts['member_id'] . '">';
    }
    if (!empty($opts['plan']) && plan_by_key((string)$opts['plan']) !== null) {
        $html .= '<input type="hidden" name="mjd_ctx_plan" value="' . e((string)$opts['plan']) . '">';   // plan chosen on membership-plans.php
    }
    // Spam trap: real visitors never see or fill this; bots usually do.
    $html .= '<div class="mjd-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="mjd_hp" tabindex="-1" autocomplete="off"></label></div>';

    $cls = !empty($flash['ok']) ? 'mjd-form-msg is-success' : (!empty($flash['message']) ? 'mjd-form-msg is-error' : 'mjd-form-msg');
    $html .= '<div class="' . $cls . '" role="alert">' . e((string)($flash['message'] ?? '')) . '</div>';
    return $html . $body . '</form>';
}

/** HTML for one field (label + control + help + error placeholder). */
function render_field(PDO $pdo, array $form, array $f, array $old, array $errors): string {
    $type = $f['type'];
    $s = $f['settings'];
    if (empty($s['inputView']) || !empty($s['adminOnly'])) { return ''; }      // hidden from the public form

    $label = form_label_text($f['label']);

    // ----- display-only items -----
    switch ($type) {
        case 'honeypot':
        case 'hidden':
            return $type === 'hidden'
                ? '<input type="hidden" name="' . e($f['name']) . '" value="' . e($f['defaultValue']) . '">'
                : '';                                                               // honeypot is added once, at form level
        case 'section':
            return '<div class="mjd-section"><h3>' . e(strip_tags($label)) . '</h3></div>';
        case 'help_alert':
            return '<div class="mjd-alert">' . e($f['description'] !== '' ? $f['description'] : strip_tags($label)) . '</div>';
        case 'captcha':
            // No reCAPTCHA keys are configured, so this is a static "I'm not a robot" box (not verified).
            return '<div class="mjd-field"><label class="mjd-check"><input type="checkbox" disabled> I\'m not a robot</label></div>';
        case 'html':
            $content = $f['description'] !== '' ? $f['description'] : $label;
            if (preg_match('/^\s*\[widget=/i', $content)) { return ''; }            // theme widgets are not supported
            // The default "Form Title" placeholder (field name "title") becomes the form's heading.
            if ($f['name'] === 'title' && strpos($content, '<') === false) {
                return '<div class="mjd-section"><h3>' . e($form['title'] !== '' ? $form['title'] : strip_tags($label)) . '</h3></div>';
            }
            // Admin-authored markup (e.g. the grid <div>s of the default sign-up form) is trusted and printed as is.
            return strpos($content, '<') !== false ? $content : '<p class="mjd-text">' . e($content) . '</p>';
        case 'submit':
            return '<div class="mjd-field mjd-submit"><button type="submit" class="mjd-submit-btn">' . e($label !== '' ? strip_tags($label) : 'Submit') . '</button></div>';
    }

    $name  = $f['name'];
    if ($name === '') { return ''; }
    $id    = 'mjd-' . $form['name'] . '-' . $name;
    $value = $old[$name] ?? $f['defaultValue'];
    $req   = !empty($f['required']);
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '"' . ($f['placeholder'] !== '' ? ' placeholder="' . e($f['placeholder']) . '"' : '');
    $class = 'mjd-control ' . trim($f['inputClass']);

    $control = '';
    switch ($type) {
        case 'textarea':
        case 'richtext': case 'richtext_image': case 'richtext_elements': case 'richtext_media':
            $control = '<textarea class="' . e($class) . '" rows="4"' . $attrs . '>' . e((string)$value) . '</textarea>';
            break;
        case 'select': case 'country': case 'state': case 'top_category': case 'years':
            $control = '<select class="' . e($class) . '"' . $attrs . '><option value="">' . e($f['placeholder'] !== '' ? $f['placeholder'] : 'Select') . '</option>';
            foreach (form_field_options($pdo, $f) as $opt) {
                $control .= '<option value="' . e($opt) . '"' . ((string)$value === $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
            }
            $control .= '</select>';
            break;
        case 'radio': case 'yes_no':
            $control = '<div class="mjd-choices">';
            foreach (form_field_options($pdo, $f) as $i => $opt) {
                $control .= '<label class="mjd-check"><input type="radio" name="' . e($name) . '" value="' . e($opt) . '"' . ((string)$value === $opt ? ' checked' : '') . '> ' . e($opt) . '</label>';
            }
            $control .= '</div>';
            break;
        case 'checkbox_group':
            $picked = is_array($value) ? $value : array_map('trim', explode(',', (string)$value));
            $control = '<div class="mjd-choices">';
            foreach (form_field_options($pdo, $f) as $opt) {
                $control .= '<label class="mjd-check"><input type="checkbox" name="' . e($name) . '[]" value="' . e($opt) . '"' . (in_array($opt, $picked, true) ? ' checked' : '') . '> ' . e($opt) . '</label>';
            }
            $control .= '</div>';
            break;
        case 'phone_country':
            $control = '<div class="mjd-phone"><select class="mjd-control" name="' . e($name) . '__cc" aria-label="Country code">';
            foreach (form_dial_codes() as $code => $text) { $control .= '<option value="' . e($code) . '">' . e($text) . '</option>'; }
            $control .= '</select><input class="' . e($class) . '" type="tel"' . $attrs . ' value="' . e((string)$value) . '"></div>';
            break;
        case 'price':
            global $site;
            $control = '<div class="mjd-price"><span>' . e($site['currency'] ?? '₹') . '</span><input class="' . e($class) . '" type="number" step="0.01" min="0"' . $attrs . ' value="' . e((string)$value) . '"></div>';
            break;
        case 'file':
            $control = '<input class="' . e($class) . '" type="file"' . $attrs . '>';
            break;
        default:
            // text, email, tel, url, number, password, date, datetime-local ...
            $inputTypes = ['email' => 'email', 'tel' => 'tel', 'url' => 'url', 'number' => 'number', 'password' => 'password', 'date' => 'date', 'datetime-local' => 'datetime-local'];
            $inputType = $inputTypes[$type] ?? 'text';
            $control = '<input class="' . e($class) . '" type="' . $inputType . '"' . $attrs . ($inputType === 'password' ? '' : ' value="' . e((string)$value) . '"') . ($inputType === 'password' ? ' autocomplete="new-password"' : '') . '>';
    }

    $html  = '<div class="mjd-field mjd-type-' . e($type) . ' ' . e($f['displayClass']) . '"' . ($f['outerId'] !== '' ? ' id="' . e($f['outerId']) . '"' : '') . ' data-field="' . e($name) . '">';
    $html .= '<label class="mjd-label" for="' . e($id) . '">' . ($req ? '<span class="mjd-star">*</span> ' : '') . e(strip_tags($label)) . '</label>';
    $html .= $control;
    if ($f['help'] !== '') { $html .= '<small class="mjd-help">' . e($f['help']) . '</small>'; }
    $html .= '<div class="mjd-error" data-for="' . e($name) . '">' . e((string)($errors[$name] ?? '')) . '</div>';
    return $html . '</div>';
}
