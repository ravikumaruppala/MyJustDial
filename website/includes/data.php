<?php
/**
 * Data layer - every database read the public pages need lives here.
 * Pages never contain SQL; they call these functions and print the result.
 * All queries use prepared statements. LIMIT/OFFSET values are cast to int first.
 */

/* ------------------------------------------------------------------ members */

/** WHERE fragment that limits users_data to public directory listings. */
function member_listing_condition(): string {
    // Active accounts that have a category or a business name (plain "general user" accounts are not listed).
    return "u.active = :active AND (u.profession_id > 0 OR u.company <> '')";
}

/** Full display name of a member row. */
function member_name(array $m): string {
    $name = trim($m['first_name'] . ' ' . $m['last_name']);
    return $name !== '' ? $name : 'Member';
}

/** "Los Angeles, CA" - short location used on cards. */
function member_location_short(array $m): string {
    return implode(', ', array_filter([trim((string)$m['city']), trim((string)$m['state_code'])], 'strlen'));
}

/** "Los Angeles, California, 90012, United States Of America" - long location used in results. */
function member_location_long(array $m): string {
    return implode(', ', array_filter([
        trim((string)$m['city']), trim((string)$m['state_ln']), trim((string)$m['zip_code']), trim((string)$m['country_ln']),
    ], 'strlen'));
}

/**
 * Search the member directory.
 * $f keys: keyword, location, category (profession_id), sub (service_id), sort.
 * Returns ['rows' => [...], 'total' => int].
 */
function search_members(PDO $pdo, array $f, int $page, int $perPage): array {
    global $site;
    $where  = [member_listing_condition()];
    $params = [':active' => (int)$site['member_active']];

    if (($f['keyword'] ?? '') !== '') {
        $where[] = "(u.first_name LIKE :k1 OR u.last_name LIKE :k2 OR u.company LIKE :k3 OR u.about_me LIKE :k4 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :k5)";
        foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $key) { $params[':' . $key] = '%' . $f['keyword'] . '%'; }
    }
    if (($f['location'] ?? '') !== '') {
        $where[] = '(u.city LIKE :l1 OR u.zip_code LIKE :l2 OR u.state_ln LIKE :l3 OR u.state_code LIKE :l4 OR u.country_ln LIKE :l5)';
        foreach (['l1', 'l2', 'l3', 'l4', 'l5'] as $key) { $params[':' . $key] = '%' . $f['location'] . '%'; }
    }
    if ((int)($f['category'] ?? 0) > 0) {
        $where[] = 'u.profession_id = :cat';
        $params[':cat'] = (int)$f['category'];
    }
    if ((int)($f['sub'] ?? 0) > 0 && tableExists($pdo, 'rel_services')) {
        $where[] = 'EXISTS (SELECT 1 FROM rel_services r WHERE r.user_id = u.user_id AND r.service_id = :sub)';
        $params[':sub'] = (int)$f['sub'];
    }

    // Sort options are whitelisted - the value from the URL is never placed in SQL directly.
    $orders = [
        'name'    => 'u.first_name ASC, u.last_name ASC',
        'name_desc' => 'u.first_name DESC, u.last_name DESC',
        'newest'  => 'u.signup_date DESC, u.user_id DESC',
        'oldest'  => 'u.signup_date ASC, u.user_id ASC',
    ];
    $orderBy = $orders[$f['sort'] ?? ''] ?? 'u.featured DESC, u.user_id ASC';
    $whereSql = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) FROM users_data u WHERE $whereSql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $limit  = max(1, $perPage);
    $offset = max(0, ($page - 1) * $limit);
    $stmt = $pdo->prepare("SELECT u.* FROM users_data u WHERE $whereSql ORDER BY $orderBy LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Newest listed members for the home page cards. */
function newest_members(PDO $pdo, int $limit): array {
    global $site;
    $limit = max(1, $limit);
    $stmt = $pdo->prepare('SELECT u.* FROM users_data u WHERE ' . member_listing_condition() . " ORDER BY u.signup_date DESC, u.user_id ASC LIMIT $limit");
    $stmt->execute([':active' => (int)$site['member_active']]);
    return $stmt->fetchAll();
}

/** Featured members (falls back to the newest ones when none is marked featured). */
function featured_members(PDO $pdo, int $limit): array {
    global $site;
    $limit = max(1, $limit);
    $stmt = $pdo->prepare('SELECT u.* FROM users_data u WHERE ' . member_listing_condition() . " ORDER BY u.featured DESC, u.user_id ASC LIMIT $limit");
    $stmt->execute([':active' => (int)$site['member_active']]);
    return $stmt->fetchAll();
}

/** One public listing by id, or null. */
function get_member(PDO $pdo, int $id): ?array {
    global $site;
    $stmt = $pdo->prepare('SELECT u.* FROM users_data u WHERE ' . member_listing_condition() . ' AND u.user_id = :id LIMIT 1');
    $stmt->execute([':active' => (int)$site['member_active'], ':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Top categories with their sub categories: ['professions' => [...], 'services' => [...]]. */
function get_categories(PDO $pdo): array {
    $out = ['professions' => [], 'services' => []];
    if (tableExists($pdo, 'list_professions')) {
        $out['professions'] = $pdo->query('SELECT profession_id, name FROM list_professions ORDER BY sort_order, name')->fetchAll();
    }
    if (tableExists($pdo, 'list_services')) {
        $out['services'] = $pdo->query('SELECT service_id, name, profession_id FROM list_services ORDER BY sort_order, name')->fetchAll();
    }
    return $out;
}

/* --------------------------------------------------------------------- blog */

/** Published blog articles. $f keys: keyword, category. Returns ['rows' => [...], 'total' => int]. */
function search_posts(PDO $pdo, array $f, int $page, int $perPage): array {
    if (!tableExists($pdo, 'data_posts')) { return ['rows' => [], 'total' => 0]; }
    $where  = ["p.post_status = 1", "p.post_title <> ''"];
    $params = [];
    if (($f['keyword'] ?? '') !== '') {
        $where[] = '(p.post_title LIKE :k1 OR p.post_caption LIKE :k2 OR p.post_content LIKE :k3 OR p.post_tags LIKE :k4)';
        foreach (['k1', 'k2', 'k3', 'k4'] as $key) { $params[':' . $key] = '%' . $f['keyword'] . '%'; }
    }
    if (($f['category'] ?? '') !== '') {
        $where[] = 'p.post_category = :cat';
        $params[':cat'] = $f['category'];
    }
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM data_posts p WHERE $whereSql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $limit  = max(1, $perPage);
    $offset = max(0, ($page - 1) * $limit);
    $stmt = $pdo->prepare("SELECT p.* FROM data_posts p WHERE $whereSql ORDER BY p.post_id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** One published article by id, or null. */
function get_post(PDO $pdo, int $id): ?array {
    if (!tableExists($pdo, 'data_posts')) { return null; }
    $stmt = $pdo->prepare("SELECT p.* FROM data_posts p WHERE p.post_status = 1 AND p.post_id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Distinct categories used by published articles (for the blog category drop-down). */
function blog_categories(PDO $pdo): array {
    if (!tableExists($pdo, 'data_posts')) { return []; }
    return $pdo->query("SELECT DISTINCT post_category FROM data_posts WHERE post_status = 1 AND post_title <> '' AND post_category <> '' ORDER BY post_category")->fetchAll(PDO::FETCH_COLUMN);
}

/** Human date for an article: uses post_date, falls back to post_live_date (YYYYMMDDHHMMSS). */
function post_date_label(array $p): string {
    $time = strtotime((string)$p['post_date']);
    if (!$time && preg_match('/^\d{8}/', (string)$p['post_live_date'])) {
        $time = strtotime(substr($p['post_live_date'], 0, 8));
    }
    return $time ? date('M j, Y', $time) : '';
}
