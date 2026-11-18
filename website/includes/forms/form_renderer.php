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
        case 'top_category':
            if (tableExists($pdo, 'list_professions')) {
                return $pdo->query('SELECT name FROM list_professions ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
            }
            return $lists['categories'];
        case 'years':
            $year = (int)date('Y');
            return array_map('strval', range($year, $year - 80));
        case 'yes_no':       return ['Yes', 'No'];
    }
    return [];
}

/** Resolve an administrator-selected PHP template safely. Only a basename in the
 * website/includes/forms/templates directory can be executed; arbitrary paths/code are rejected. */
function form_php_template_name(string $content): string {
    $content = trim($content);
    if (preg_match('/^<\?php\s*(?:include|require)(?:_once)?\s*\(?\s*[\'\"]([A-Za-z0-9_-]+\.php)[\'\"]\s*\)?\s*;?\s*\?>$/i', $content, $m)) return $m[1];
    if (preg_match('/^[A-Za-z0-9_-]+\.php$/i', $content)) return $content;
    return '';
}

function render_form_php_template(string $filename, array $context = []): string {
    $base = realpath(__DIR__ . '/templates');
    $path = realpath(__DIR__ . '/templates/' . basename($filename));
    if (!$base || !$path || dirname($path) !== $base || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') return '<div class="mjd-notice">The selected form template was not found.</div>';
    extract($context, EXTR_SKIP);
    ob_start();
    include $path;
    return (string)ob_get_clean();
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
    if (!empty($opts['member_id'])) {
        $memberQuery = $pdo->prepare('SELECT * FROM users_data WHERE user_id=? LIMIT 1');
        $memberQuery->execute([(int)$opts['member_id']]);
        $memberRow = $memberQuery->fetch() ?: [];
        $memberValues = $memberRow;
        if (!empty($memberRow['profession_id']) && tableExists($pdo,'list_professions')) {
            $professionQuery=$pdo->prepare('SELECT name FROM list_professions WHERE profession_id=? LIMIT 1');
            $professionQuery->execute([(int)$memberRow['profession_id']]);
            $memberValues['profession_id']=(string)($professionQuery->fetchColumn() ?: $memberRow['profession_id']);
        }
        if (tableExists($pdo, 'users_meta')) {
            $metaQuery = $pdo->prepare("SELECT `key`,`value` FROM users_meta WHERE `database`='users_data' AND database_id=?");
            $metaQuery->execute([(int)$opts['member_id']]);
            foreach ($metaQuery->fetchAll() as $metaRow) $memberValues[$metaRow['key']] = $metaRow['value'];
        }
        if (tableExists($pdo, 'users_photo')) {
            $photoQuery = $pdo->prepare('SELECT type,file FROM users_photo WHERE user_id=? ORDER BY photo_id DESC');
            $photoQuery->execute([(int)$opts['member_id']]);
            foreach ($photoQuery->fetchAll() as $photoRow) {
                $key = $photoRow['type'] === 'logo' ? 'company_logo' : ($photoRow['type'] === 'photo' ? 'profile_photo' : '');
                if ($key !== '' && !isset($memberValues[$key])) $memberValues[$key] = $photoRow['file'];
            }
        }
        // The form's current values are the defaults; validation errors take precedence.
        $old = array_merge($memberValues, $old);
    }

    $phpTemplate = '';
    foreach ($form['fields'] as $candidate) {
        if ($candidate['type'] === 'html') {
            $phpTemplate = form_php_template_name((string)$candidate['label']);
            if ($phpTemplate === '') {
                $phpTemplate = form_php_template_name((string)$candidate['description']);
            }
            if ($phpTemplate !== '') break;
        }
    }
    $memberPhotos = ['photo' => '', 'logo' => ''];
    if (!empty($opts['member_id']) && tableExists($pdo, 'users_photo')) {
        $photoQuery = $pdo->prepare('SELECT type,file FROM users_photo WHERE user_id=? ORDER BY photo_id DESC');
        $photoQuery->execute([(int)$opts['member_id']]);
        foreach ($photoQuery->fetchAll() as $photoRow) {
            if (($photoRow['type'] === 'photo' && $memberPhotos['photo'] === '') || ($photoRow['type'] === 'logo' && $memberPhotos['logo'] === '')) $memberPhotos[$photoRow['type']] = (string)$photoRow['file'];
        }
    }
    $hasSubmit = false;
    $body = '';
    foreach ($form['fields'] as $field) {
        if ($field['type'] === 'submit') { $hasSubmit = true; }
        if ($phpTemplate !== '' && (($field['type'] === 'file' && in_array($field['name'], ['profile_photo','company_logo'], true)) || $field['name'] === 'photo_upload_section' || $field['name'] === 'photo_format_notice')) continue;
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
    if (!empty($opts['reset_token'])) {
        $html .= '<input type="hidden" name="mjd_reset_token" value="' . e((string)$opts['reset_token']) . '">';
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
            $templateName = form_php_template_name((string)$label);
            $content = $templateName !== '' ? $label : ($f['description'] !== '' ? $f['description'] : $label);
            if ($templateName === '') {
                $templateName = form_php_template_name((string)$content);
            }
            if ($templateName !== '') {
                $memberId = (int)($_POST['mjd_ctx_member'] ?? $_SESSION['member_id'] ?? 0);
                $photos = ['photo' => '', 'logo' => ''];
                if ($memberId > 0 && tableExists($pdo, 'users_photo')) {
                    $q = $pdo->prepare('SELECT type,file FROM users_photo WHERE user_id=? ORDER BY photo_id DESC'); $q->execute([$memberId]);
                    foreach ($q->fetchAll() as $r) if (($r['type'] === 'photo' && $photos['photo'] === '') || ($r['type'] === 'logo' && $photos['logo'] === '')) $photos[$r['type']] = (string)$r['file'];
                }
                return render_form_php_template($templateName, ['mjd_member_photos' => $photos, 'mjd_member_id' => $memberId, 'mjd_form_errors' => $errors]);
            }
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
    $class = 'mjd-control ' . trim($f['inputClass']) . (strpos($type, 'richtext') === 0 ? ' mjd-ckeditor' : '');

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
            $isRating = strpos($name, 'rating_') === 0;
            $control = '<div class="mjd-choices' . ($isRating ? ' mjd-rating-choices' : '') . '">';
            foreach (form_field_options($pdo, $f) as $i => $opt) {
                if ($isRating) {
                    $control .= '<label class="mjd-rating-choice" title="' . e($opt) . ' out of 5"><input type="radio" name="' . e($name) . '" value="' . e($opt) . '"' . ((string)$value === $opt ? ' checked' : '') . '><span aria-hidden="true">★</span><span class="sr-only">' . e($opt) . ' out of 5</span></label>';
                } else {
                    $control .= '<label class="mjd-check"><input type="radio" name="' . e($name) . '" value="' . e($opt) . '"' . ((string)$value === $opt ? ' checked' : '') . '> ' . e($opt) . '</label>';
                }
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
            if ($value !== '' && $form['name'] === 'listing_additional_details_form') {
                $control .= '<small class="mjd-help">Current file: <a href="' . e(site_url((string)$value)) . '" target="_blank" rel="noopener">View uploaded file</a></small>';
            }
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
