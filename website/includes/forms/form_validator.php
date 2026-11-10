<?php
/**
 * Form validator - checks a submission against the form's own definition.
 * The browser is never trusted: the field list, required flags and option lists
 * all come from the database, not from what was posted.
 */

/**
 * Validate a submission.
 * Returns ['values' => [field_name => cleaned value], 'errors' => [field_name => message], 'uploads' => [field_name => pending upload]].
 */
function validate_submission(PDO $pdo, array $form, array $post, array $files): array {
    global $site;
    $values = []; $errors = []; $uploads = [];

    foreach ($form['fields'] as $f) {
        if (!form_field_accepts_input($f)) { continue; }
        $name  = $f['name'];
        $type  = $f['type'];
        $label = trim(strip_tags(form_label_text($f['label']))) ?: $name;
        $req   = !empty($f['required']);

        // Hidden fields always keep the value configured by the administrator.
        if ($type === 'hidden') { $values[$name] = $f['defaultValue']; continue; }

        // ----- file upload -----
        if ($type === 'file') {
            $file = $files[$name] ?? null;
            if (!$file || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                if ($req) { $errors[$name] = "$label is required."; }
                continue;
            }
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if ((int)$file['error'] !== UPLOAD_ERR_OK) { $errors[$name] = 'The file could not be uploaded.'; }
            elseif ($file['size'] > $site['upload_max_bytes']) { $errors[$name] = 'The file is larger than ' . round($site['upload_max_bytes'] / 1048576) . ' MB.'; }
            elseif (!in_array($ext, $site['upload_allowed'], true)) { $errors[$name] = 'Allowed file types: ' . implode(', ', $site['upload_allowed']) . '.'; }
            else { $uploads[$name] = ['tmp' => $file['tmp_name'], 'ext' => $ext, 'original' => $file['name']]; }
            continue;
        }

        $raw = $post[$name] ?? '';

        // ----- several values: checkbox group -----
        if ($type === 'checkbox_group') {
            $allowed = form_field_options($pdo, $f);
            $picked = array_values(array_intersect(is_array($raw) ? $raw : [], $allowed));
            if ($req && !$picked) { $errors[$name] = "Please choose at least one option for $label."; }
            $values[$name] = implode(', ', $picked);
            continue;
        }

        // ----- single value -----
        $value = is_array($raw) ? '' : (string)$raw;
        $value = $type === 'password' ? $value : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value));
        $multiline = ($type === 'textarea' || strpos($type, 'richtext') === 0);

        if ($value === '') {
            if ($req) { $errors[$name] = "$label is required."; }
            $values[$name] = '';
            continue;
        }
        if (mb_strlen($value) > ($multiline ? 10000 : 255)) { $errors[$name] = "$label is too long."; continue; }

        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[$name] = 'Enter a valid email address.'; }
                break;
            case 'url':
                if (!preg_match('#^https?://#i', $value)) { $value = 'http://' . $value; }
                if (!filter_var($value, FILTER_VALIDATE_URL)) { $errors[$name] = 'Enter a valid website address.'; }
                break;
            case 'tel':
                if (!preg_match('/^[0-9+()\-.\s]{6,20}$/', $value)) { $errors[$name] = 'Enter a valid phone number.'; }
                break;
            case 'phone_country':
                $code = (string)($post[$name . '__cc'] ?? '');
                if (!isset(form_dial_codes()[$code])) { $errors[$name] = 'Choose a country code.'; }
                elseif (!preg_match('/^[0-9()\-.\s]{5,18}$/', $value)) { $errors[$name] = 'Enter a valid phone number.'; }
                else { $value = $code . ' ' . $value; }
                break;
            case 'number':
                if (!preg_match('/^-?\d+$/', $value)) { $errors[$name] = 'Enter a whole number.'; }
                break;
            case 'price':
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) { $errors[$name] = 'Enter a valid amount.'; }
                break;
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $value);
                if (!$d || $d->format('Y-m-d') !== $value) { $errors[$name] = 'Enter a valid date.'; }
                break;
            case 'datetime-local':
                if (!DateTime::createFromFormat('Y-m-d\TH:i', $value)) { $errors[$name] = 'Enter a valid date and time.'; }
                break;
            case 'password':
                if (mb_strlen($value) < 6) { $errors[$name] = 'Use at least 6 characters.'; }
                break;
            case 'select': case 'radio': case 'yes_no': case 'country': case 'state': case 'top_category': case 'years':
                $allowed = form_field_options($pdo, $f);
                if ($allowed && !in_array($value, $allowed, true)) { $errors[$name] = 'Choose one of the listed options.'; }
                break;
        }
        $values[$name] = $value;
    }

    // "email_confirm" must equal "email", "password_confirm" must equal "password", ...
    foreach ($values as $name => $value) {
        if (substr($name, -8) === '_confirm') {
            $base = substr($name, 0, -8);
            if (isset($values[$base]) && $values[$base] !== $value && !isset($errors[$name])) {
                $errors[$name] = 'This does not match the field above.';
            }
        }
    }
    return ['values' => $values, 'errors' => $errors, 'uploads' => $uploads];
}

/**
 * Move validated uploads into website/uploads/forms/ and return [field_name => relative path].
 * Stored names are random, so an uploaded file can never overwrite another one or run as a script.
 */
function store_uploads(array $uploads): array {
    $dir = __DIR__ . '/../../uploads/forms';
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $saved = [];
    foreach ($uploads as $name => $u) {
        $file = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $u['ext'];
        if (is_uploaded_file($u['tmp']) && move_uploaded_file($u['tmp'], $dir . '/' . $file)) {
            $saved[$name] = 'uploads/forms/' . $file;
        }
    }
    return $saved;
}
