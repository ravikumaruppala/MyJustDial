<?php
/**
 * Form definitions.
 *
 * A "form" is the array returned by get_form():
 *   ['name' => 'contact_form', 'title' => '...', 'desc' => '...', 'table' => 'website_contacts',
 *    'virtual' => false, 'fields' => [ ...same field arrays the admin Form Builder uses... ]]
 *
 * Forms come from the admin tables `forms` + `form_fields`. When a shortcode names a form that has not
 * been created in the admin yet (contact_form, member_login, newsletter_modal_signup) a built-in
 * fallback is used, so the website works out of the box. A form created in the admin always wins.
 */

/** Build one field array in the same shape loadBuilderFields() returns for DB rows. */
function virtual_field(string $type, string $label, string $name, bool $required = false, array $extra = []): array {
    return array_merge([
        'id' => 'v_' . $name, 'type' => $type, 'origType' => '', 'label' => $label, 'name' => $name,
        'required' => $required, 'placeholder' => '', 'help' => '', 'description' => '', 'options' => '',
        'defaultValue' => '', 'inputClass' => 'form-control', 'displayClass' => '', 'outerId' => '',
        'settings' => ['inputView' => true, 'displayView' => true, 'leadPreviews' => false, 'includeEmails' => true,
                       'tableView' => true, 'adminOnly' => false, 'altInput' => '', 'altDisplay' => '', 'altEmail' => '', 'altTable' => ''],
    ], $extra);
}

/** Built-in fallback forms, keyed by shortcode name. */
function virtual_forms(): array {
    return [
        'contact_form' => [
            'title' => 'Contact Us', 'table' => 'website_contacts',
            'fields' => [
                virtual_field('text', 'Your Name', 'yourname', true, ['placeholder' => 'Enter your full name']),
                virtual_field('email', 'Email Address', 'inquiry_email', true, ['placeholder' => 'you@example.com']),
                virtual_field('tel', 'Phone Number', 'phone', false, ['placeholder' => 'Optional']),
                virtual_field('textarea', 'Message', 'comments', true, ['placeholder' => 'How can we help?']),
                virtual_field('submit', 'Send Message', 'savebutton'),
            ],
        ],
        'member_login' => [
            'title' => 'Member Login', 'table' => 'login',
            'fields' => [
                virtual_field('email', 'Email Address', 'email', true, ['placeholder' => 'Enter Email Address']),
                virtual_field('password', 'Password', 'password', true, ['placeholder' => 'Enter Password']),
                virtual_field('submit', 'Log In Now', 'savebutton'),
            ],
        ],
        'newsletter_modal_signup' => [
            'title' => 'Join Our Newsletter', 'table' => 'website_contacts',
            'fields' => [
                virtual_field('email', 'Email Address', 'email', true, ['placeholder' => 'Enter your email address']),
                virtual_field('submit', 'Subscribe', 'savebutton'),
            ],
        ],
    ];
}

/**
 * Theme widgets cannot run on this website, but one of them matters: the sign-up form's
 * "Member Signup Default Fields" widget stands for the visitor's name. It is replaced by
 * First Name + Last Name inputs so new accounts get a real name. Other widgets are skipped.
 */
function expand_widget_fields(array $fields): array {
    $out = [];
    foreach ($fields as $f) {
        if ($f['type'] === 'html' && preg_match('/\[widget=[^\]]*Member Signup Default Fields[^\]]*\]/i', $f['label'])) {
            $out[] = virtual_field('text', 'First Name', 'first_name', true, ['placeholder' => 'First Name']);
            $out[] = virtual_field('text', 'Last Name', 'last_name', true, ['placeholder' => 'Last Name']);
            continue;
        }
        $out[] = $f;
    }
    return $out;
}

/** True when an administrator disabled the form (Form Manager > Actions > Disable). */
function form_is_disabled(PDO $pdo, int $formId): bool {
    if ($formId <= 0 || !tableExists($pdo, 'users_meta')) { return false; }
    $stmt = $pdo->prepare("SELECT `value` FROM users_meta WHERE `database` = 'forms' AND database_id = ? AND `key` = 'form_status' LIMIT 1");
    $stmt->execute([$formId]);
    return $stmt->fetchColumn() === 'disabled';
}

/**
 * Load a form by shortcode name. Returns null when it does not exist or is disabled.
 * Only [A-Za-z0-9_-] names are accepted, so the value is safe to use anywhere.
 */
function get_form(PDO $pdo, string $name): ?array {
    if (!preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $name)) { return null; }

    if (tableExists($pdo, 'forms')) {
        $stmt = $pdo->prepare('SELECT form_id, form_name, form_title, form_desc, form_table FROM forms WHERE form_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            if (form_is_disabled($pdo, (int)$row['form_id'])) { return null; }
            return [
                'name' => $row['form_name'], 'title' => (string)$row['form_title'], 'desc' => (string)$row['form_desc'],
                'table' => (string)$row['form_table'], 'virtual' => false,
                'fields' => expand_widget_fields(loadBuilderFields($pdo, $row['form_name'])),   // shared with the admin builder
            ];
        }
    }

    $virtual = virtual_forms();
    if (isset($virtual[$name])) {
        return ['name' => $name, 'title' => $virtual[$name]['title'], 'desc' => '', 'table' => $virtual[$name]['table'],
                'virtual' => true, 'fields' => $virtual[$name]['fields']];
    }
    return null;
}

/** Field types that show something but never collect a value. */
function form_display_only_types(): array {
    return ['section', 'html', 'help_alert', 'honeypot', 'captcha', 'submit'];
}

/** True when the field is shown to visitors and its posted value should be read. */
function form_field_accepts_input(array $field): bool {
    if (in_array($field['type'], form_display_only_types(), true)) { return false; }
    if (empty($field['settings']['inputView']) || !empty($field['settings']['adminOnly'])) { return false; }
    return $field['name'] !== '';
}

/** Replace %%%token%%% placeholders (used by the default sign-up form) with readable text. */
function form_label_text(string $label): string {
    $known = [
        'enter_email_placeholder' => 'Email Address', 'confirm_email' => 'Confirm Email Address',
        'create_pw_label' => 'Create Password', 'confirm_pw_label' => 'Confirm Password',
        'create_profile_label' => 'Create My Profile',
    ];
    return preg_replace_callback('/%%%([a-z0-9_]+)%%%/i', static function (array $m) use ($known): string {
        $key = strtolower($m[1]);
        return $known[$key] ?? ucwords(str_replace('_', ' ', $key));
    }, $label);
}
