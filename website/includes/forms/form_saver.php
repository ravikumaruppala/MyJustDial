<?php
/**
 * Form saver - writes a validated submission to the right database table.
 *
 * Where a form saves (see resolve_form_target):
 *   member_login            -> no insert, logs the member in (users_data is only read)
 *   form_table = users_data -> new member account            (columns: first_name, email, password ...)
 *   form_table = leads      -> lead record                   (lead_name, lead_email, lead_message ...)
 *   anything else           -> website_contacts              (NAME, EMAIL, PHONE, COMMENTS ...)
 * Each posted value goes into the column that matches its "Database Variable"; values with no matching
 * column are kept in the notes column so nothing the visitor typed is lost.
 */

/** Tables a public form may ever write to. */
function form_allowed_tables(): array { return ['users_data', 'website_contacts', 'leads']; }

/** Decide where a submission goes. */
function resolve_form_target(array $form): string {
    if (strtolower($form['name']) === 'member_login' || strtolower($form['table']) === 'login') { return 'login'; }
    $table = strtolower($form['table']);
    if (in_array($table, form_allowed_tables(), true)) { return $table; }
    // No table configured: a form with an email + password field is a sign-up form, everything else is a contact.
    $types = array_column($form['fields'], 'type');
    if (in_array('password', $types, true) && in_array('email', $types, true)) { return 'users_data'; }
    return 'website_contacts';
}

/** Column metadata of a table: [lower-case name => ['name','data_type','nullable','default','auto']]. */
function table_columns(PDO $pdo, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) { return $cache[$table]; }
    $stmt = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    $cols = [];
    foreach ($stmt->fetchAll() as $c) {
        $cols[strtolower($c['COLUMN_NAME'])] = [
            'name' => $c['COLUMN_NAME'], 'data_type' => strtolower($c['DATA_TYPE']), 'nullable' => $c['IS_NULLABLE'] === 'YES',
            'default' => $c['COLUMN_DEFAULT'], 'auto' => stripos((string)$c['EXTRA'], 'auto_increment') !== false,
        ];
    }
    return $cache[$table] = $cols;
}

/** Neutral value for a NOT NULL column that has no default (keeps inserts working in strict SQL mode). */
function column_fallback(string $dataType) {
    if (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'], true)) { return 0; }
    if ($dataType === 'date') { return date('Y-m-d'); }
    if (in_array($dataType, ['datetime', 'timestamp'], true)) { return date('Y-m-d H:i:s'); }
    return '';
}

/** INSERT one row; unknown columns are skipped, required columns are filled. Returns the new id. */
function insert_row(PDO $pdo, string $table, array $data): int {
    if (!in_array($table, form_allowed_tables(), true)) { throw new RuntimeException('Table not allowed.'); }
    $cols = table_columns($pdo, $table);
    $row = [];
    foreach ($data as $col => $val) {
        $key = strtolower($col);
        if (isset($cols[$key])) { $row[$cols[$key]['name']] = $val; }
    }
    foreach ($cols as $c) {
        if (array_key_exists($c['name'], $row) || $c['auto'] || $c['nullable'] || $c['default'] !== null) { continue; }
        $row[$c['name']] = column_fallback($c['data_type']);
    }
    if (!$row) { throw new RuntimeException('Nothing to save.'); }
    $names = array_keys($row);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}

/** Alternative field names that map to a column, per target table. */
function field_aliases(string $target): array {
    if ($target === 'website_contacts') {
        return ['yourname' => 'NAME', 'name' => 'NAME', 'full_name' => 'NAME', 'fullname' => 'NAME', 'inquiry_email' => 'EMAIL', 'email' => 'EMAIL',
                'phone' => 'PHONE', 'phone_number' => 'PHONE', 'mobile' => 'PHONE', 'comments' => 'COMMENTS', 'message' => 'COMMENTS',
                'subject' => 'TITLE', 'company' => 'COMPANY', 'country' => 'COUNTRY', 'category' => 'CATEGORY'];
    }
    if ($target === 'leads') {
        return ['yourname' => 'lead_name', 'name' => 'lead_name', 'full_name' => 'lead_name', 'inquiry_email' => 'lead_email', 'email' => 'lead_email',
                'phone' => 'lead_phone', 'phone_number' => 'lead_phone', 'comments' => 'lead_message', 'message' => 'lead_message'];
    }
    return [];   // users_data: the field name must equal the column name (first_name, city, ...)
}

/** Columns a public form must never be able to set on users_data (permissions, status, identity). */
function protected_member_columns(): array {
    return ['user_id', 'active', 'featured', 'verified', 'subscription_id', 'token', 'ref_code', 'cookie', 'last_login', 'signup_date',
            'modtime', 'filename', 'password', 'parent_id', 'profession_id', 'google_id', 'facebook_id'];
}

/**
 * Match posted values to table columns.
 * Returns [columns => [col => value], 'extra' => ["Label: value", ...]] ("extra" = values with no matching column).
 */
function map_values_to_columns(PDO $pdo, string $table, array $form, array $values): array {
    $cols = table_columns($pdo, $table);
    $aliases = field_aliases($table);
    $labels = [];
    foreach ($form['fields'] as $f) { $labels[$f['name']] = trim(strip_tags(form_label_text($f['label']))); }
    $columns = []; $extra = [];
    foreach ($values as $name => $value) {
        $key = strtolower($name);
        $col = null;
        if (isset($cols[$key])) { $col = $cols[$key]['name']; }
        elseif (isset($aliases[$key])) { $col = $aliases[$key]; }
        if ($table === 'users_data' && $col !== null && in_array(strtolower($col), protected_member_columns(), true)) { $col = null; }
        if ($col !== null && !isset($columns[$col])) { $columns[$col] = $value; continue; }
        // sign-up helper fields (email_confirm, password_confirm ...) are not data - skip them
        if ($table === 'users_data' || substr($key, -8) === '_confirm' || $value === '') { continue; }
        $extra[] = ($labels[$name] ?? $name) . ': ' . $value;
    }
    return ['columns' => $columns, 'extra' => $extra];
}

/** First submitted value whose field has the given type (e.g. the form's email or password field). */
function value_by_type(array $form, array $values, string $type): string {
    foreach ($form['fields'] as $f) {
        if ($f['type'] === $type && isset($values[$f['name']]) && $values[$f['name']] !== '') { return (string)$values[$f['name']]; }
    }
    return '';
}

/** Name of the first field with the given type (used to attach an error message to that field). */
function field_name_by_type(array $form, string $type): string {
    foreach ($form['fields'] as $f) { if ($f['type'] === $type && $f['name'] !== '') { return $f['name']; } }
    return '';
}

/**
 * Save a validated submission. Returns:
 *   ['ok' => bool, 'message' => string, 'errors' => [field => message], 'reload' => bool]
 * 'reload' tells the browser to refresh the page (used after login so the header shows the member).
 */
function save_submission(PDO $pdo, array $form, array $values, array $ctx = []): array {
    global $site;
    $target = resolve_form_target($form);
    $now = date('YmdHis');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $referrer = (string)($ctx['return'] ?? '');

    try {
        /* ---------- member login ---------- */
        if ($target === 'login') { return login_member($pdo, $form, $values); }

        /* ---------- new member account ---------- */
        if ($target === 'users_data') {
            $emailField = field_name_by_type($form, 'email');
            $email = value_by_type($form, $values, 'email');
            $password = value_by_type($form, $values, 'password');
            $dup = $pdo->prepare('SELECT user_id FROM users_data WHERE email = ? LIMIT 1');
            $dup->execute([$email]);
            if ($dup->fetchColumn()) {
                return ['ok' => false, 'message' => '', 'errors' => [$emailField ?: 'email' => 'An account with this email address already exists. Please log in.']];
            }
            $mapped = map_values_to_columns($pdo, 'users_data', $form, $values);
            $row = $mapped['columns'];
            $row['email'] = $email;
            if (empty($row['first_name'])) { $row['first_name'] = ucfirst((string)strstr($email, '@', true)) ?: 'Member'; }
            if (!isset($row['last_name'])) { $row['last_name'] = ''; }
            $row['password']        = password_hash($password, PASSWORD_BCRYPT);
            $row['active']          = (int)$site['member_active'];
            // Plan chosen on membership-plans.php (falls back to the free plan). Payment is not connected.
            $row['subscription_id'] = (int)($ctx['plan_id'] ?? 0) > 0 ? (int)$ctx['plan_id'] : (int)$site['free_plan_id'];
            $row['token']           = md5(uniqid('', true));
            $row['signup_date']     = $now;
            $row['last_login']      = '';
            $id = insert_row($pdo, 'users_data', $row);
            // public profile address, e.g. "jane-doe-12"
            $pdo->prepare('UPDATE users_data SET filename = ? WHERE user_id = ?')->execute([slugify($row['first_name'] . ' ' . $row['last_name']) . '-' . $id, $id]);
            $planText = !empty($ctx['plan_name']) ? ' on the ' . $ctx['plan_name'] . ' plan' : '';
            return ['ok' => true, 'message' => 'Your account has been created' . $planText . '. You can now log in with your email and password.', 'errors' => []];
        }

        /* ---------- leads ---------- */
        if ($target === 'leads') {
            $mapped = map_values_to_columns($pdo, 'leads', $form, $values);
            $row = $mapped['columns'];
            if (empty($row['lead_email'])) { $row['lead_email'] = value_by_type($form, $values, 'email'); }
            $row['formname'] = $form['name'];
            $row['date_added'] = $now; $row['DATE'] = $now;
            $row['status'] = 'New'; $row['origin_ip'] = $ip; $row['url_from'] = mb_substr($referrer, 0, 255);
            $row['token'] = md5(uniqid('', true));
            $row['lead_notes'] = implode("\n", $mapped['extra']);
            insert_row($pdo, 'leads', $row);
            return ['ok' => true, 'message' => 'Thank you! Your request has been received.', 'errors' => []];
        }

        /* ---------- website_contacts (default) ---------- */
        $mapped = map_values_to_columns($pdo, 'website_contacts', $form, $values);
        $row = $mapped['columns'];
        if (empty($row['EMAIL'])) { $row['EMAIL'] = value_by_type($form, $values, 'email'); }
        $row['CONTACT_TYPE'] = $form['title'] !== '' ? $form['title'] : $form['name'];
        $row['HTTPR']   = mb_substr($referrer, 0, 1000);
        $row['PAGE']    = mb_substr($referrer, 0, 255);
        $row['DATE']    = $now;
        $row['IP']      = $ip;
        $row['WEBSITE'] = (string)($_SERVER['HTTP_HOST'] ?? '');
        $row['TOKEN']   = md5(uniqid('', true));
        $row['STATUS']  = 'New';
        $row['USER_ID'] = (int)($ctx['member_id'] ?? 0);   // set when the visitor used "Send Message" on a member
        $row['NOTES']   = implode("\n", $mapped['extra']);
        insert_row($pdo, 'website_contacts', $row);
        $isNewsletter = stripos($form['name'], 'newsletter') !== false;
        return ['ok' => true, 'message' => $isNewsletter ? 'Thank you for subscribing!' : 'Thank you! Your message has been sent. We will get back to you soon.', 'errors' => []];
    } catch (Throwable $exception) {
        // The real error stays on the server; the visitor sees a neutral message.
        error_log('Form save failed (' . $form['name'] . '): ' . $exception->getMessage());
        return ['ok' => false, 'message' => 'Sorry, we could not save your submission. Please try again later.', 'errors' => []];
    }
}

/** Verify email + password against users_data and start the member session. */
function login_member(PDO $pdo, array $form, array $values): array {
    global $site;
    $email = value_by_type($form, $values, 'email');
    $password = value_by_type($form, $values, 'password');

    // Simple brute-force guard: 5 wrong attempts lock the form for 10 minutes (per browser session).
    $fails = $_SESSION['login_fails'] ?? ['count' => 0, 'since' => time()];
    if (time() - $fails['since'] > 600) { $fails = ['count' => 0, 'since' => time()]; }
    if ($fails['count'] >= 5) {
        return ['ok' => false, 'message' => 'Too many failed attempts. Please try again in a few minutes.', 'errors' => []];
    }

    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, password, active FROM users_data WHERE email = ?');
    $stmt->execute([$email]);
    foreach ($stmt->fetchAll() as $user) {
        $stored = (string)$user['password'];
        // bcrypt hashes are the norm; a 32-character hex string is an old md5 hash.
        $match = password_verify($password, $stored) || (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(strtolower($stored), md5($password)));
        if (!$match) { continue; }
        if ((int)$user['active'] !== (int)$site['member_active']) {
            return ['ok' => false, 'message' => 'This account is not active yet.', 'errors' => []];
        }
        session_regenerate_id(true);
        unset($_SESSION['login_fails']);
        $_SESSION['member_id'] = (int)$user['user_id'];
        $_SESSION['member_name'] = member_name($user);
        $pdo->prepare('UPDATE users_data SET last_login = ? WHERE user_id = ?')->execute([date('YmdHis'), $user['user_id']]);
        return ['ok' => true, 'message' => 'Welcome back, ' . $_SESSION['member_name'] . '!', 'errors' => [], 'reload' => true];
    }
    $fails['count']++;
    $_SESSION['login_fails'] = $fails;
    return ['ok' => false, 'message' => 'Incorrect email address or password.', 'errors' => []];
}
