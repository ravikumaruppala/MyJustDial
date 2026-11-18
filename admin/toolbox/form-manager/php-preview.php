<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
header('Content-Type: text/html; charset=utf-8');
$file = (string)($_GET['file'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]+\.php$/i', $file)) { http_response_code(400); echo 'Invalid template filename.'; exit; }
$base = realpath(__DIR__ . '/../../../website/includes/forms/templates');
$path = realpath(__DIR__ . '/../../../website/includes/forms/templates/' . $file);
if (!$base || !$path || dirname($path) !== $base) { http_response_code(404); echo 'Template not found. Add the PHP file under website/includes/forms/templates/.'; exit; }
$site = require __DIR__ . '/../../../website/config/site.php';
function preview_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function preview_site_url(string $path = ''): string { global $config; return rtrim((string)$config['base_url'], '/') . '/website/' . ltrim($path, '/'); }
// Match the helpers used by the public renderer without requiring a member session.
if (!function_exists('e')) { function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('site_url')) { function site_url(string $path = ''): string { return preview_site_url($path); } }
$mjd_member_photos = ['photo' => '', 'logo' => ''];
$mjd_form_errors = [];
include $path;
