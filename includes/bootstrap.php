<?php
// Shared application bootstrap: configuration, database and helper functions.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);
require __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    global $config;
    header('Location: ' . $config['base_url'] . $path);
    exit;
}

function tableExists(PDO $pdo, string $table): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() > 0;
}

function countRows(PDO $pdo, string $table): int {
    // Table names are selected from this fixed allow-list, never from user input.
    $allowed = ['users_data', 'leads', 'data_posts', 'website_contacts', 'post_transactions', 'forms'];
    if (!in_array($table, $allowed, true) || !tableExists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        exit('Your session token expired. Refresh the page and try again.');
    }
}

// Ensure the Day 2 shortcode forms are available in the existing Form Manager.
require_once __DIR__ . '/form_fields.php';
require_once __DIR__ . '/../website/includes/forms/form_seed.php';
ensure_day2_forms($pdo);
