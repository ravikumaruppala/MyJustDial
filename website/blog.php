<?php
/**
 * Website Blog page.
 *   blog.php                 -> article list (filters: keyword, category, page)
 *   blog.php?post=ID         -> one article
 * Articles come from data_posts (post_status = 1). An article's text may contain [form=name] shortcodes:
 * do_shortcodes() turns each one into the form built in the admin Form Manager.
 */
require __DIR__ . '/includes/bootstrap.php';

$postId  = get_int('post');
$filters = ['keyword' => get_str('keyword'), 'category' => get_str('category')];
$page    = max(1, get_int('page'));
$perPage = (int)$site['posts_per_page'];

$post  = $postId ? get_post($pdo, $postId) : null;
$found = $postId ? ['rows' => [], 'total' => 0] : search_posts($pdo, $filters, $page, $perPage);
$cats  = blog_categories($pdo);

render_header(['title' => $post ? $post['post_title'] : 'Website Blog', 'active' => 'blog']);
?>
<div class="container two-col">

    <!-- LEFT: article search + side cards -->
    <aside>
        <form class="side-card" action="<?= e(site_url('blog.php')) ?>" method="get">
            <h3>Website Blog Article Search</h3>
            <div class="input-icon" style="margin-bottom:14px"><span><i class="fa fa-search"></i></span>
                <input class="control" type="text" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="Keyword (Optional)" aria-label="Keyword" style="margin:0"></div>
            <select class="control" name="category" aria-label="Category">
                <option value="">Select Category</option>
                <?php foreach ($cats as $c): ?><option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Search Now</button>
        </form>
        <?= render_side_cards() ?>
    </aside>

    <!-- RIGHT: one article, or the list -->
    <section>
    <?php if ($postId): ?>
        <a class="back-link" href="<?= e(site_url('blog.php')) ?>">&laquo; Back to the blog</a>
        <?php if ($post): ?>
            <article class="card post-card">
                <h1 class="page-title" style="font-size:calc(clamp(28px,4vw,42px) - 5px)"><?= e($post['post_title']) ?></h1>
                <div class="post-meta"><?= e(post_date_label($post)) ?><?= $post['post_category'] !== '' ? ' &middot; ' . e($post['post_category']) : '' ?></div>
                <div class="post-body"><?= do_shortcodes((string)$post['post_content']) /* [form=name] becomes a working form */ ?></div>
            </article>
        <?php else: ?>
            <div class="alert-info"><i class="fa fa-exclamation-circle"></i> Sorry, that article could not be found.</div>
        <?php endif; ?>

    <?php else: ?>
        <div class="blog-head">
            <h1>Website Blog</h1>
            <div class="results-tools">
                <button class="view-btn" type="button" data-view="list" aria-label="List view"><i class="fa fa-list-ul"></i></button>
                <button class="view-btn" type="button" data-view="grid" aria-label="Grid view"><i class="fa fa-th-large"></i></button>
            </div>
        </div>
        <?php if ($found['rows']): ?>
            <div class="post-list">
                <?php foreach ($found['rows'] as $p): ?>
                    <article class="post-card">
                        <h2><a href="<?= e(site_url('blog.php?post=' . (int)$p['post_id'])) ?>"><?= e($p['post_title']) ?></a></h2>
                        <div class="post-meta"><?= e(post_date_label($p)) ?><?= $p['post_category'] !== '' ? ' &middot; ' . e($p['post_category']) : '' ?></div>
                        <p><?= e($p['post_caption'] !== '' ? excerpt($p['post_caption'], 220) : excerpt($p['post_content'], 220)) ?></p>
                        <a class="btn btn-primary" href="<?= e(site_url('blog.php?post=' . (int)$p['post_id'])) ?>">Read More</a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= render_pagination((int)$found['total'], $perPage, $page) ?>
        <?php else: ?>
            <div class="alert-info"><i class="fa fa-exclamation-circle"></i> Sorry, we did not find any results for your search.</div>
        <?php endif; ?>
    <?php endif; ?>
    </section>
</div>
<?php render_footer();
