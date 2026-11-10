<?php
/**
 * Search Directory page.
 *   search.php                       -> member directory (filters: keyword, location, category, sub, sort, page)
 *   search.php?listing=ID            -> one member's full listing
 * Members come from users_data; the query lives in includes/data.php (search_members / get_member).
 */
require __DIR__ . '/includes/bootstrap.php';

$listingId = get_int('listing');
$filters = [
    'keyword'  => get_str('keyword'),
    'location' => get_str('location'),
    'category' => get_int('category'),
    'sub'      => get_int('sub'),
    'sort'     => get_str('sort'),
];
$page    = max(1, get_int('page'));
$perPage = (int)$site['members_per_page'];

$listing = $listingId ? get_member($pdo, $listingId) : null;
$found   = $listingId ? ['rows' => [], 'total' => 0] : search_members($pdo, $filters, $page, $perPage);
$from    = $found['total'] ? ($page - 1) * $perPage + 1 : 0;
$to      = $found['total'] ? ($page - 1) * $perPage + count($found['rows']) : 0;

render_header(['title' => $listing ? member_name($listing) : 'Search Directory', 'active' => 'search']);
?>
<div class="container two-col">

    <!-- LEFT: search form + side cards -->
    <aside>
        <form class="side-card" action="<?= e(site_url('search.php')) ?>" method="get">
            <h3>Find Members</h3>
            <label for="kw">What do you need:</label>
            <input class="control" id="kw" type="text" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="Name or Keyword">
            <label for="loc">Search by location:</label>
            <input class="control" id="loc" type="text" name="location" value="<?= e($filters['location']) ?>" placeholder="City or Post Code">
            <?php if ($filters['category']): ?><input type="hidden" name="category" value="<?= (int)$filters['category'] ?>"><?php endif; ?>
            <?php if ($filters['sub']): ?><input type="hidden" name="sub" value="<?= (int)$filters['sub'] ?>"><?php endif; ?>
            <button class="btn btn-primary" type="submit">Search Now</button>
        </form>
        <?= render_side_cards() ?>
    </aside>

    <!-- RIGHT: results or one listing -->
    <section>
    <?php if ($listingId): ?>
        <?php if (!$listing): ?>
            <a class="back-link" href="<?= e(site_url('search.php')) ?>">&laquo; Back to results</a>
            <div class="no-results"><i class="fa fa-exclamation-circle"></i>Sorry, that listing could not be found.</div>
        <?php else:
            $m = $listing;
            $links = [];
            foreach (['website' => 'Website', 'facebook' => 'Facebook', 'twitter' => 'Twitter', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'] as $col => $label) {
                $url = clean_url($m[$col] ?? '');
                if ($url !== '') { $links[$label] = $url; }
            }
            $payments = split_lines($m['affiliation'] ?? '');
            $awards   = split_lines($m['awards'] ?? '');
            $phone    = trim((string)$m['phone_number']);
            ?>
            <a class="back-link" href="<?= e(site_url('search.php')) ?>">&laquo; Back to results</a>
            <article class="card listing">
                <div class="listing-head">
                    <img src="<?= e(asset_url('images/avatar.svg')) ?>" alt="" width="150" height="150">
                    <div>
                        <h1><?= e(member_name($m)) ?></h1>
                        <?php if (trim((string)$m['company']) !== ''): ?><div class="result-company" style="margin-bottom:8px"><?= e($m['company']) ?></div><?php endif; ?>
                        <div class="result-loc"><i class="fa fa-map-marker"></i><?= e(member_location_long($m)) ?></div>
                    </div>
                </div>
                <div class="listing-actions">
                    <a class="btn btn-dark" <?= modal_attrs($site['forms']['contact'], 'Send Message to ' . member_name($m), (int)$m['user_id']) ?>>Send Message</a>
                    <?php if ($phone !== ''): ?><button class="btn btn-outline" type="button" data-reveal-phone="<?= e($phone) ?>"><i class="fa fa-phone"></i> See Phone Number</button><?php endif; ?>
                    <button class="btn btn-outline save-btn" type="button" data-save-member="<?= (int)$m['user_id'] ?>"><i class="fa fa-bookmark"></i> SAVE</button>
                </div>
                <?php if (trim((string)$m['quote']) !== ''): ?><p style="font-size:20px;font-weight:600"><?= e($m['quote']) ?></p><?php endif; ?>
                <?php if (trim((string)$m['about_me']) !== ''): ?><div class="post-body"><?= safe_html($m['about_me']) ?></div><?php endif; ?>
                <div class="listing-meta">
                    <?php if ((int)$m['experience'] > 1900): ?><div><h4>In business since</h4><?= (int)$m['experience'] ?></div><?php endif; ?>
                    <?php if ($payments): ?><div><h4>Payment methods</h4><ul class="chips"><?php foreach ($payments as $p) { echo '<li>' . e($p) . '</li>'; } ?></ul></div><?php endif; ?>
                    <?php if ($awards): ?><div><h4>Awards</h4><ul class="chips"><?php foreach ($awards as $a) { echo '<li>' . e($a) . '</li>'; } ?></ul></div><?php endif; ?>
                    <?php if ($links): ?><div><h4>Links</h4><ul class="chips"><?php foreach ($links as $label => $url) { echo '<li><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($label) . '</a></li>'; } ?></ul></div><?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

    <?php else: ?>
        <div class="results-head">
            <div class="results-count">Showing <?= $from ?> - <?= $to ?> of <?= (int)$found['total'] ?> Results</div>
            <div class="results-tools">
                <!-- Changing the sort order reloads the page and keeps the current search -->
                <form action="<?= e(site_url('search.php')) ?>" method="get">
                    <?php foreach (['keyword', 'location', 'category', 'sub'] as $keep): if ($filters[$keep]): ?>
                        <input type="hidden" name="<?= $keep ?>" value="<?= e((string)$filters[$keep]) ?>">
                    <?php endif; endforeach; ?>
                    <select class="control" name="sort" data-autosubmit aria-label="Sort results">
                        <option value="">Sort Results</option>
                        <?php foreach (['name' => 'Name A - Z', 'name_desc' => 'Name Z - A', 'newest' => 'Newest First', 'oldest' => 'Oldest First'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $filters['sort'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="view-btn" type="button" data-view="list" aria-label="List view"><i class="fa fa-list-ul"></i></button>
                <button class="view-btn" type="button" data-view="grid" aria-label="Grid view"><i class="fa fa-th-large"></i></button>
            </div>
        </div>
        <h1 class="directory-title">Member Directory</h1>

        <?php if ($found['rows']): ?>
            <div class="results">
                <?php foreach ($found['rows'] as $m) { echo render_member_result($m); } ?>
            </div>
            <?= render_pagination((int)$found['total'], $perPage, $page) ?>
        <?php else: ?>
            <div class="no-results"><i class="fa fa-exclamation-circle"></i>Sorry, we did not find any members for your search.</div>
        <?php endif; ?>
        <?= render_promo() ?>
    <?php endif; ?>
    </section>
</div>
<?php render_footer();
