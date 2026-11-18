<?php
/**
 * Form endpoint for every [form=...] shortcode form.
 *
 *   GET  ?action=render&form=NAME[&member=ID]  -> HTML of the form (used by the pop-up window)
 *   POST (form fields)                          -> validate, save, answer with JSON (or redirect without JavaScript)
 */
require __DIR__ . '/includes/bootstrap.php';

/** The visitor's page to return to after a non-JavaScript submit (same site only). */
function safe_return_url(string $url): string {
    return (strpos($url, '/') === 0 && strpos($url, '//') !== 0) ? $url : site_url('index.php');
}

/** Send the answer: JSON for fetch(), otherwise remember it in the session and redirect back to the page. */
function answer(bool $ajax, string $formName, array $result, array $old, string $return): void {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($result['redirect'])) {
        header('Location: ' . safe_return_url((string)$result['redirect']));
        exit;
    }
    $_SESSION['mjd_form_flash'][$formName] = [
        'ok' => !empty($result['ok']), 'message' => $result['message'] ?? '', 'errors' => $result['errors'] ?? [],
        'old' => !empty($result['ok']) ? [] : $old,
    ];
    header('Location: ' . safe_return_url($return));
    exit;
}

/* ---------------------------------------------------------------- GET: render a form */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (get_str('action') === 'render') {
        header('Content-Type: text/html; charset=utf-8');
        echo render_form(get_str('form'), ['member_id' => get_int('member'), 'plan' => get_str('plan')]);
    } else {
        http_response_code(404);
    }
    exit;
}

/* --------------------------------------------------------------- POST: submit a form */
$ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$formName = (string)($_POST['mjd_form'] ?? '');
$return = (string)($_POST['mjd_return'] ?? '');

// 1. CSRF: the token printed inside the form must come back unchanged.
if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    answer($ajax, $formName, ['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.', 'errors' => []], $_POST, $return);
}

// 2. Spam trap: a filled hidden field means a bot. Pretend it worked, save nothing.
if (trim((string)($_POST['mjd_hp'] ?? '')) !== '') {
    answer($ajax, $formName, ['ok' => true, 'message' => 'Thank you!', 'errors' => []], [], $return);
}

// 3. The form must exist (and be enabled) - its fields are the only source of truth.
$form = get_form($pdo, $formName);
if ($form === null) {
    answer($ajax, $formName, ['ok' => false, 'message' => 'This form is not available.', 'errors' => []], $_POST, $return);
}

// 4. Validate every field.
$check = validate_submission($pdo, $form, $_POST, $_FILES);
if ($check['errors']) {
    answer($ajax, $formName, ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $check['errors']], $_POST, $return);
}

// 5. Store uploads, then save to the database.
$values = $check['values'];
foreach (store_uploads($check['uploads']) as $field => $path) { $values[$field] = $path; }
$plan = plan_by_key((string)($_POST['mjd_ctx_plan'] ?? ''));   // only known plan keys are accepted
$result = save_submission($pdo, $form, $values, [
    'return' => $return,
    'member_id' => max(0, (int)($_POST['mjd_ctx_member'] ?? 0)),
    'plan_id' => (int)($plan['subscription_id'] ?? 0),
    'plan_name' => (string)($plan['name'] ?? ''),
    'reset_token' => (string)($_POST['mjd_reset_token'] ?? ''),
]);
answer($ajax, $formName, $result, $_POST, $return);
