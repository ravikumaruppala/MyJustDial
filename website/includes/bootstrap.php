<?php
/**
 * Website bootstrap - loaded first by every public page.
 *
 * It re-uses the project's shared bootstrap (database connection, e(), session, csrfToken())
 * and the form-field helpers written for the admin Form Builder. It deliberately does NOT load
 * includes/auth.php, so the public site never asks for an administrator login.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';   // $config, $pdo, e(), tableExists(), csrfToken() ...
require_once __DIR__ . '/../../includes/form_fields.php'; // loadBuilderFields(), loadBuilderLists() ...

$site = require __DIR__ . '/../config/site.php';          // website settings (see config/site.php)

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/forms/form_definitions.php';
require_once __DIR__ . '/forms/form_renderer.php';
require_once __DIR__ . '/forms/form_validator.php';
require_once __DIR__ . '/forms/form_saver.php';
require_once __DIR__ . '/shortcodes.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/partials.php';
