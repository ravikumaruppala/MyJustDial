<?php
/**
 * Form saver - writes a validated submission to the right database table.
 *
 * Where a form saves (see resolve_form_target):
 *   member_login            -> no insert, logs the member in (users_data is only read)
 *   form_table = users_data -> new member account            (columns: first_name, email, password ...)
 *   form_table = leads      -> lead record                   (lead_name, lead_email, lead_message ...)
 *   contact_form            -> form_inquiries                (default inquiry columns + custom users_meta rows)
 *   anything else           -> website_contacts              (NAME, EMAIL, PHONE, COMMENTS ...)
 * Each posted value goes into the column that matches its "Database Variable"; values with no matching
 * column are kept in the notes column so nothing the visitor typed is lost.
 */

/** Tables a public form may ever write to. */
function form_allowed_tables(): array { return ['users_data', 'website_contacts', 'form_inquiries', 'leads', 'users_photo', 'users_reviews']; }

/** Decide where a submission goes. */
function resolve_form_target(array $form): string {
    if (strtolower($form['name']) === 'member_login' || strtolower($form['table']) === 'login') { return 'login'; }
    // The public Contact Us form always writes to form_inquiries, even if an older
    // Form Manager record still has website_contacts selected as its destination.
    if (in_array(strtolower($form['name']), ['contact_form', 'contact_us', 'contact_us_form'], true)) { return 'form_inquiries'; }
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
        return ['yourname' => 'NAME', 'name' => 'NAME', 'full_name' => 'NAME', 'first_name' => 'NAME', 'last_name' => 'NAME', 'fullname' => 'NAME', 'inquiry_email' => 'EMAIL', 'email' => 'EMAIL',
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
    if ($table === 'website_contacts' && (isset($values['first_name']) || isset($values['last_name']))) {
        $fullName = trim((string)($values['first_name'] ?? '') . ' ' . (string)($values['last_name'] ?? ''));
        if ($fullName !== '') $columns['NAME'] = $fullName;
    }
    foreach ($values as $name => $value) {
        if ($table === 'website_contacts' && in_array($name, ['first_name','last_name'], true)) continue;
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

/** Save/update a key-value field in the existing users_meta table. */
function save_member_meta(PDO $pdo, int $userId, string $key, string $value): void {
    if ($userId <= 0 || !tableExists($pdo, 'users_meta')) return;
    $del = $pdo->prepare("DELETE FROM users_meta WHERE `database`='users_data' AND database_id=? AND `key`=?");
    $del->execute([$userId, $key]);
    $ins = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('users_data',?,?,?,?)");
    $ins->execute([$userId, $key, $value, date('YmdHis')]);
}

/** Store non-column profile fields in users_meta while keeping native users_data columns normalized. */
function save_member_extra_meta(PDO $pdo, int $userId, array $form, array $values): void {
    $known = array_column(table_columns($pdo, 'users_data'), 'name');
    $known = array_map('strtolower', $known);
    $protected = array_map('strtolower', protected_member_columns());
    $labels = [];
    foreach ($form['fields'] as $field) $labels[$field['name']] = trim(strip_tags(form_label_text($field['label'])));
    $delete = $pdo->prepare("DELETE FROM users_meta WHERE `database`='users_data' AND database_id=? AND `key`=?");
    foreach ($values as $key => $value) {
        if (substr($key, -8) === '_confirm') continue;
        if (in_array(strtolower($key), $known, true) || in_array(strtolower($key), $protected, true)) continue;
        $delete->execute([$userId, (string)$key]);
        if ($value === '' || is_array($value)) continue;
        save_member_meta($pdo, $userId, $key, (string)$value);
    }
}

/**
 * Contact form fields that belong in the native form_inquiries columns.
 * Any other user-created input field is stored as a users_meta key/value row.
 */
function form_inquiry_default_fields(): array {
    return [
        'yourname' => 'yourname', 'name' => 'yourname', 'full_name' => 'yourname',
        'first_name' => 'yourname', 'last_name' => 'yourname',
        'inquiry_email' => 'inquiry_email', 'email' => 'inquiry_email',
        'phone' => 'phone', 'phone_number' => 'phone', 'mobile' => 'phone',
        'comments' => 'comments', 'message' => 'comments',
    ];
}

/** Save all extra Contact Us fields against the newly-created inquiry ID. */
function save_inquiry_extra_meta(PDO $pdo, int $inquiryId, array $form, array $values): void {
    if ($inquiryId <= 0 || !tableExists($pdo, 'users_meta')) return;
    $defaults = form_inquiry_default_fields();
    $labels = [];
    foreach ($form['fields'] as $field) {
        $labels[$field['name']] = trim(strip_tags(form_label_text($field['label'])));
    }
    $insert = $pdo->prepare("INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added) VALUES ('form_inquiries', ?, ?, ?, ?)");
    foreach ($values as $fieldName => $value) {
        $key = strtolower((string)$fieldName);
        if ($value === '' || is_array($value) || is_object($value) || isset($defaults[$key]) || substr($key, -8) === '_confirm') continue;
        if (!isset($labels[$fieldName])) continue;
        $insert->execute([$inquiryId, (string)$fieldName, (string)$value, date('YmdHis')]);
    }
}

/**
 * Insert the inquiry first, then attach custom field metadata using the generated inquiry ID.
 * Both writes are atomic so a failed metadata insert cannot leave a partial inquiry.
 */
function save_contact_inquiry(PDO $pdo, array $form, array $values, string $now, string $ip, string $referrer, array $ctx): int {
    $name = '';
    foreach (['yourname', 'name', 'full_name'] as $field) {
        if (!empty($values[$field])) { $name = trim((string)$values[$field]); break; }
    }
    if ($name === '' && (!empty($values['first_name']) || !empty($values['last_name']))) {
        $name = trim((string)($values['first_name'] ?? '') . ' ' . (string)($values['last_name'] ?? ''));
    }
    $email = '';
    foreach (['inquiry_email', 'email'] as $field) {
        if (!empty($values[$field])) { $email = trim((string)$values[$field]); break; }
    }
    $phone = '';
    foreach (['phone', 'phone_number', 'mobile'] as $field) {
        if (!empty($values[$field])) { $phone = trim((string)$values[$field]); break; }
    }
    $message = '';
    foreach (['comments', 'message'] as $field) {
        if (!empty($values[$field])) { $message = (string)$values[$field]; break; }
    }
    $row = [
        'inquiry_email' => $email, 'inquiry_ip' => $ip, 'yourname' => $name,
        'phone' => $phone, 'comments' => $message,
        'inquiry_user_id' => (int)($ctx['member_id'] ?? 0),
        'inquiry_form' => (string)$form['name'], 'inquiry_content' => '',
        'inquiry_status' => 'New', 'inquiry_archived' => 0, 'inquiry_notes' => '',
        'url_origin' => mb_substr($referrer, 0, 255), 'date_submitted' => $now, 'user_consent' => '',
    ];
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $inquiryId = insert_row($pdo, 'form_inquiries', $row);
        save_inquiry_extra_meta($pdo, $inquiryId, $form, $values);
        if ($ownsTransaction) $pdo->commit();
        return $inquiryId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
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
        if ($target === 'login') { return login_member($pdo, $form, $values, $ctx); }

        /* ---------- member password reset request ---------- */
        if ($form['name'] === 'member_password_reset_request_form') {
            $email = value_by_type($form,$values,'email');
            $q=$pdo->prepare('SELECT user_id FROM users_data WHERE email=? LIMIT 1');
            $q->execute([$email]); $uid=(int)$q->fetchColumn();
            if ($uid>0) {
                $raw=bin2hex(random_bytes(32));
                save_member_meta($pdo,$uid,'member_reset_token',hash('sha256',$raw));
                save_member_meta($pdo,$uid,'member_reset_expires',(string)(time()+3600));
                $url=site_url('member-forgot-password.php?token='.$raw);
                $host=preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['HTTP_HOST']??'localhost'));
                @mail($email,'Reset your member password',"Use this link within one hour:\n".$url."\n\nIf you did not request this, ignore this email.","From: noreply@".($host?:'localhost'));
            }
            return ['ok'=>true,'message'=>'If the account exists and email delivery is configured, a password reset link has been sent.','errors'=>[]];
        }

        /* ---------- member password reset ---------- */
        if ($form['name'] === 'member_password_reset_form') {
            $raw=(string)($ctx['reset_token']??'');
            if ($raw==='') return ['ok'=>false,'message'=>'This reset link is invalid. Request a new link.','errors'=>[]];
            $q=$pdo->prepare("SELECT database_id FROM users_meta WHERE `database`='users_data' AND `key`='member_reset_token' AND `value`=? LIMIT 1");
            $q->execute([hash('sha256',$raw)]); $uid=(int)$q->fetchColumn();
            $exp=$pdo->prepare("SELECT `value` FROM users_meta WHERE `database`='users_data' AND database_id=? AND `key`='member_reset_expires' LIMIT 1");
            $exp->execute([$uid]); $expires=(int)$exp->fetchColumn();
            if ($uid<=0 || $expires<time()) return ['ok'=>false,'message'=>'This reset link is invalid or expired. Request a new link.','errors'=>[]];
            $password=value_by_type($form,$values,'password');
            $pdo->prepare('UPDATE users_data SET password=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
            $pdo->prepare("DELETE FROM users_meta WHERE `database`='users_data' AND database_id=? AND `key` IN ('member_reset_token','member_reset_expires')")->execute([$uid]);
            return ['ok'=>true,'message'=>'Your password has been updated. You can now log in.','errors'=>[]];
        }

        /* ---------- member review ---------- */
        if ($form['name'] === 'member_review_form') {
            $memberId = (int)($ctx['member_id'] ?? 0);
            if ($memberId <= 0 || !tableExists($pdo, 'users_data')) {
                return ['ok'=>false,'message'=>'The member listing could not be identified. Please reopen the review page.','errors'=>[]];
            }
            $exists = $pdo->prepare('SELECT user_id FROM users_data WHERE user_id=?');
            $exists->execute([$memberId]);
            if (!$exists->fetchColumn()) return ['ok'=>false,'message'=>'This member listing no longer exists.','errors'=>[]];
            foreach (['rating_overall','rating_service','rating_response','rating_expertise','rating_results'] as $rating) {
                $v=(int)($values[$rating] ?? 0);
                if ($v<1 || $v>5) return ['ok'=>false,'message'=>'Please enter ratings between 1 and 5.','errors'=>[$rating=>'Choose a rating from 1 to 5.']];
            }
            $stmt=$pdo->prepare("INSERT INTO users_reviews
              (user_id,review_title,review_description,review_worked,service_id,review_name,review_email,review_zip,
               review_status,review_added,review_approved,ip,cookie,httpr,member_id,review_token,review_updated,
               rating_overall,rating_service,rating_response,rating_expertise,rating_results,spoken_language,gender,
               rating_language,recommend,review_company,formname,user_consent)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $memberId,(string)$values['review_title'],(string)$values['review_description'],'',0,
                (string)$values['review_name'],(string)$values['review_email'],(string)($values['review_zip']??''),
                0,$now,'',$ip,'',mb_substr($referrer,0,255),$memberId,bin2hex(random_bytes(20)),'',
                (int)$values['rating_overall'],(int)$values['rating_service'],(int)$values['rating_response'],
                (int)$values['rating_expertise'],(int)$values['rating_results'],'',(string)$values['gender'],0,0,'',
                $form['name'],''
            ]);
            return ['ok'=>true,'message'=>'Thank you. Your review has been submitted and is awaiting moderation.','errors'=>[]];
        }

        /* ---------- authenticated member profile forms ---------- */
        if (in_array($form['name'], ['member_contact_details_form','profile_photo_handler_form','listing_additional_details_form','about_member_form'], true)) {
            $userId = (int)($_SESSION['member_id'] ?? 0);
            if ($userId <= 0) return ['ok'=>false,'message'=>'Please log in to update your member profile.','errors'=>[]];
            if ($form['name'] === 'profile_photo_handler_form') {
                $saved = 0;
                foreach (['profile_photo'=>'photo','company_logo'=>'logo'] as $fieldName=>$photoType) {
                    $deleteFlag = $fieldName === 'profile_photo' ? 'mjd_delete_profile_photo' : 'mjd_delete_company_logo';
                    if ((string)($_POST[$deleteFlag] ?? '0') === '1') {
                        $oldStmt = $pdo->prepare('SELECT file FROM users_photo WHERE user_id=? AND type=?');
                        $oldStmt->execute([$userId, $photoType]);
                        foreach ($oldStmt->fetchAll(PDO::FETCH_COLUMN) as $oldFile) {
                            $oldPath = realpath(__DIR__ . '/../../' . ltrim((string)$oldFile, '/'));
                            $uploadRoot = realpath(__DIR__ . '/../../uploads/forms');
                            if ($oldPath && $uploadRoot && strpos($oldPath, $uploadRoot . DIRECTORY_SEPARATOR) === 0 && is_file($oldPath)) @unlink($oldPath);
                        }
                        $pdo->prepare('DELETE FROM users_photo WHERE user_id=? AND type=?')->execute([$userId, $photoType]);
                        $pdo->prepare("DELETE FROM users_meta WHERE `database`='users_data' AND database_id=? AND `key`=?")->execute([$userId, $fieldName]);
                    }
                    $path = trim((string)($values[$fieldName] ?? ''));
                    if ($path === '') continue;
                    $absolute = __DIR__ . '/../../' . ltrim($path, '/');
                    $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
                    $mime = function_exists('mime_content_type') ? (string)mime_content_type($absolute) : '';
                    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true) || ($mime !== '' && !in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true))) {
                        return ['ok'=>false,'message'=>'Please upload a valid JPG, GIF, PNG or WEBP image.','errors'=>[]];
                    }
                    // A newly uploaded image replaces the previous image of the same type.
                    $oldStmt = $pdo->prepare('SELECT file FROM users_photo WHERE user_id=? AND type=?');
                    $oldStmt->execute([$userId, $photoType]);
                    foreach ($oldStmt->fetchAll(PDO::FETCH_COLUMN) as $oldFile) {
                        $oldPath = realpath(__DIR__ . '/../../' . ltrim((string)$oldFile, '/'));
                        $uploadRoot = realpath(__DIR__ . '/../../uploads/forms');
                        if ($oldPath && $uploadRoot && strpos($oldPath, $uploadRoot . DIRECTORY_SEPARATOR) === 0 && is_file($oldPath)) @unlink($oldPath);
                    }
                    $pdo->prepare('DELETE FROM users_photo WHERE user_id=? AND type=?')->execute([$userId, $photoType]);
                    $pdo->prepare("INSERT INTO users_photo (user_id,file,original,type,date_added,resized,error,compliant) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$userId,$path,basename($absolute),$photoType,date('YmdHis'),'','','1']);
                    save_member_meta($pdo,$userId,$fieldName,$path);
                    $saved++;
                }
                return ['ok'=>true,'message'=>$saved > 0 ? 'Your profile images have been saved.' : 'Your profile photo settings have been updated.','errors'=>[],'redirect'=>site_url('listing-details.php')];
            }
            if ($form['name'] === 'member_contact_details_form') {
                $email = trim((string)($values['email'] ?? ''));
                if ($email !== '') {
                    $dupe = $pdo->prepare('SELECT user_id FROM users_data WHERE email=? AND user_id<>? LIMIT 1');
                    $dupe->execute([$email,$userId]);
                    if ($dupe->fetchColumn()) return ['ok'=>false,'message'=>'That email address is already used by another account.','errors'=>['email'=>'Please use a different email address.']];
                }
                // Save every form-manager field that maps to a safe users_data column.
                // Fields without a matching column are stored in users_meta below.
                $mapped = map_values_to_columns($pdo, 'users_data', $form, $values);
                $updates = $mapped['columns'];
                if (isset($values['profession_id']) && tableExists($pdo,'list_professions')) {
                    $category = trim((string)$values['profession_id']);
                    $catQuery = $pdo->prepare('SELECT profession_id FROM list_professions WHERE name=? OR profession_id=? LIMIT 1');
                    $catQuery->execute([$category, ctype_digit($category) ? (int)$category : 0]);
                    $categoryId = $catQuery->fetchColumn();
                    if ($categoryId) $updates['profession_id'] = (int)$categoryId;
                    else save_member_meta($pdo,$userId,'best_describes',$category);
                }
                if ($updates) {
                    $sets=[]; foreach(array_keys($updates) as $column) $sets[]='`'.$column.'`=?';
                    $pdo->prepare('UPDATE users_data SET '.implode(',',$sets).' WHERE user_id=?')->execute(array_merge(array_values($updates),[$userId]));
                }
                save_member_extra_meta($pdo,$userId,$form,$values);
                return ['ok'=>true,'message'=>'Your contact details have been updated.','errors'=>[]];
            }
            $mapped = map_values_to_columns($pdo, 'users_data', $form, $values);
            $updates = $mapped['columns'];
            if ($form['name'] === 'listing_additional_details_form' && array_key_exists('hours_of_operation',$values)) {
                $updates['rep_matters'] = (string)$values['hours_of_operation'];
            }
            if ($updates) {
                $sets = [];
                foreach (array_keys($updates) as $column) $sets[] = '`'.$column.'`=?';
                $pdo->prepare('UPDATE users_data SET '.implode(',', $sets).' WHERE user_id=?')->execute(array_merge(array_values($updates),[$userId]));
            }
            save_member_extra_meta($pdo,$userId,$form,$values);
            return ['ok'=>true,'message'=>'Your member profile has been updated.','errors'=>[]];
        }

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
            save_member_extra_meta($pdo, $id, $form, $values);
            // public profile address, e.g. "jane-doe-12"
            $pdo->prepare('UPDATE users_data SET filename = ? WHERE user_id = ?')->execute([slugify($row['first_name'] . ' ' . $row['last_name']) . '-' . $id, $id]);
            session_regenerate_id(true);
            $_SESSION['member_id'] = $id;
            $_SESSION['member_name'] = member_name($row);
            $planText = !empty($ctx['plan_name']) ? ' on the ' . $ctx['plan_name'] . ' plan' : '';
            return ['ok' => true, 'message' => 'Your account has been created' . $planText . '.', 'errors' => [], 'redirect' => site_url('member-dashboard.php')];
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

        /* ---------- Contact Us inquiry + custom metadata ---------- */
        if ($target === 'form_inquiries') {
            save_contact_inquiry($pdo, $form, $values, $now, $ip, $referrer, $ctx);
            return ['ok' => true, 'message' => 'Thank you! Your message has been sent. We will get back to you soon.', 'errors' => []];
        }

        /* ---------- website_contacts (other general forms) ---------- */
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
function login_member(PDO $pdo, array $form, array $values, array $ctx = []): array {
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
        return ['ok' => true, 'message' => 'Welcome back, ' . $_SESSION['member_name'] . '!', 'errors' => [], 'redirect' => site_url('member-dashboard.php')];
    }
    $fails['count']++;
    $_SESSION['login_fails'] = $fails;
    return ['ok' => false, 'message' => 'Incorrect email address or password.', 'errors' => []];
}
