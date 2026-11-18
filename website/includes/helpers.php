<?php
/** Small, framework-free helpers shared by all website files. */

/** URL of a website page, e.g. site_url('search.php?keyword=abc') -> /MyJustDialNew/website/search.php?keyword=abc */
function site_url(string $path = ''): string {
    global $config;
    return rtrim($config['base_url'], '/') . '/website/' . ltrim($path, '/');
}

/** URL of a file inside website/assets/. */
function asset_url(string $path): string {
    return site_url('assets/' . ltrim($path, '/'));
}

/** Read a GET parameter as a trimmed string (never returns null). */
function get_str(string $key, string $default = ''): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/** Read a GET parameter as a positive integer (0 when missing/invalid). */
function get_int(string $key): int {
    return isset($_GET[$key]) ? max(0, (int)$_GET[$key]) : 0;
}

/** "Hello World!" -> "hello-world" */
function slugify(string $text): string {
    $text = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text), '-'));
    return $text !== '' ? $text : 'member';
}

/** Admin-saved URLs are stored percent-encoded (http%3A%2F%2F...). Decode and keep only http(s) links. */
function clean_url(?string $value): string {
    $url = rawurldecode((string)$value);
    return preg_match('#^https?://#i', $url) ? $url : '';
}

/** Look up a membership plan from config by its key ('premium', 'standard', 'free'); null when unknown. */
function plan_by_key(string $key): ?array {
    global $site;
    return $site['plans'][$key] ?? null;
}

/** Short text for cards: strips tags and cuts at $max characters. */
function excerpt(string $html, int $max = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
}

/** Allow a few harmless formatting tags in admin-written text. */
function safe_html(string $html): string {
    return strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><h4><blockquote>');
}

/** Split a multi-line DB value ("Cash\rVisa\rMastercard") into a clean array. */
function split_lines(?string $value): array {
    $parts = preg_split('/\r\n|\r|\n/', (string)$value);
    return array_values(array_filter(array_map('trim', $parts), 'strlen'));
}

/** Current page URL (used as "referrer" when a form is submitted). */
function current_url(): string {
    return (string)($_SERVER['REQUEST_URI'] ?? '');
}

/** Logged-in website member (set by the member_login form) or null. */
function current_member(): ?array {
    return isset($_SESSION['member_id'])
        ? ['id' => (int)$_SESSION['member_id'], 'name' => (string)($_SESSION['member_name'] ?? 'Member')]
        : null;
}


/** Full member record for authenticated account pages. */
function member_account_data(): ?array {
    global $pdo;
    $member = current_member();
    if (!$member || !tableExists($pdo, 'users_data')) return null;
    $q = $pdo->prepare('SELECT * FROM users_data WHERE user_id=? LIMIT 1');
    $q->execute([(int)$member['id']]);
    $row = $q->fetch();
    if (!$row) return null;
    $row['display_name'] = member_name($row);
    $row['profile_photo'] = '';
    $row['company_logo'] = '';
    if (tableExists($pdo, 'users_photo')) {
        $q = $pdo->prepare('SELECT type,file FROM users_photo WHERE user_id=? ORDER BY photo_id DESC');
        $q->execute([(int)$row['user_id']]);
        foreach ($q->fetchAll() as $photo) {
            if ($photo['type'] === 'photo' && $row['profile_photo'] === '') $row['profile_photo'] = $photo['file'];
            if ($photo['type'] === 'logo' && $row['company_logo'] === '') $row['company_logo'] = $photo['file'];
        }
    }
    $row['meta'] = [];
    if (tableExists($pdo, 'users_meta')) {
        $q = $pdo->prepare("SELECT `key`,`value` FROM users_meta WHERE `database`='users_data' AND database_id=?");
        $q->execute([(int)$row['user_id']]);
        foreach ($q->fetchAll() as $meta) $row['meta'][$meta['key']] = $meta['value'];
    }
    if ($row['profile_photo'] === '' && !empty($row['meta']['profile_photo'])) $row['profile_photo'] = $row['meta']['profile_photo'];
    if ($row['company_logo'] === '' && !empty($row['meta']['company_logo'])) $row['company_logo'] = $row['meta']['company_logo'];
    $q = $pdo->prepare('SELECT subscription_name FROM subscription_types WHERE subscription_id=? LIMIT 1');
    $q->execute([(int)$row['subscription_id']]);
    $row['plan_name'] = (string)($q->fetchColumn() ?: 'Membership Plan');
    return $row;
}

/** Redirect unauthenticated visitors to the existing public member login. */
function require_member_account(): array {
    $member = member_account_data();
    if (!$member) {
        header('Location: ' . site_url('member-login.php'));
        exit;
    }
    return $member;
}

/** Renders pagination links for ?page=N while keeping the other query parameters. */
function render_pagination(int $total, int $perPage, int $page): string {
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) { return ''; }
    $query = $_GET; unset($query['page']);
    $link = static function (int $p, string $label, string $class = '') use ($query): string {
        $href = '?' . http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page-link ' . $class . '" href="' . e($href) . '">' . $label . '</a>';
    };
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) { $html .= $link($page - 1, '&laquo; Prev'); }
    for ($p = 1; $p <= $pages; $p++) { $html .= $link($p, (string)$p, $p === $page ? 'is-current' : ''); }
    if ($page < $pages) { $html .= $link($page + 1, 'Next &raquo;'); }
    return $html . '</nav>';
}
