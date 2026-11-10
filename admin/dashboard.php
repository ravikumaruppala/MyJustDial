<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$pageClass = 'dashboard-page';
$siteHost = $_SERVER['HTTP_HOST'] ?? 'myjustdial.com';
$memberCount = tableExists($pdo, 'users_data') ? countRows($pdo, 'users_data') : 0;
require __DIR__ . '/../includes/header.php';
?>
<section class="bd-start-panel">
    <div class="start-banner"><span class="rocket"><i class="fa fa-rocket"></i></span><strong>Start Here</strong><b>Complete these one-time steps to ensure your site runs smoothly.</b></div>
    <div class="start-grid">
        <article class="website-card">
            <h3>MyJustDial</h3>
            <div class="site-mock" role="img" aria-label="MyJustDial website preview">
                <div class="sm-top"><b>MyJustDial</b><span>The All-in-One Local Business Directory</span><i>Member Login</i></div>
                <div class="sm-nav"><span>Home</span><span>Search Directory</span><span>How It Works</span><span>Blog</span></div>
                <div class="sm-hero"><h4>Find Trusted Local Businesses<br>Near You, Instantly</h4>
                    <div class="sm-search"><span>What do you need</span><span>Specializing in</span><span>City or Pin Code</span><em>Search Now</em></div>
                </div>
            </div>
            <div class="website-info"><a href="#"><i class="fa fa-link"></i><?= e($siteHost) ?></a>
                <p><i class="fa fa-calendar-o"></i>Start Date: <strong>Dec 11, 2025</strong></p>
                <p><i class="fa fa-users"></i>Members: <strong><?= number_format($memberCount ?: 5) ?></strong></p>
                <p><i class="fa fa-star-o"></i>Plan: <strong class="plan-pill">Lifetime Plan</strong></p>
            </div>
        </article>
        <article class="setup-card-bd">
            <div class="progress-summary">
                <div class="progress-track"><span></span></div>
                <div class="progress-line"><strong>0%</strong><b>Basic Website Setup</b><small>Phase 1 of 2</small></div>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>1. Website Basics</b><small>Article <i class="fa fa-external-link"></i> &nbsp; Video <i class="fa fa-play-circle-o"></i></small></div><button>Edit Basics</button>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>2. Add Business Listings</b><small>Article <i class="fa fa-external-link"></i> &nbsp; Video <i class="fa fa-play-circle-o"></i></small></div><button>Add Listings</button>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>3. Connect Domain</b><small>Article <i class="fa fa-external-link"></i> &nbsp; Video <i class="fa fa-play-circle-o"></i></small></div><button>Connect Domain</button>
            </div>
        </article>
        <article class="explore-card-bd">
            <h3>Explore What You Can Do</h3>
            <a href="#"><span><i class="fa fa-home"></i></span>Edit Homepage <i class="fa fa-angle-right"></i></a><a href="#"><span><i class="fa fa-users"></i></span>View Members <i class="fa fa-angle-right"></i></a><a href="#"><span><i class="fa fa-user-plus"></i></span>Test Member Sign Up <i class="fa fa-angle-right"></i></a><a href="#"><span><i class="fa fa-paint-brush"></i></span>Design &amp; Layout <i class="fa fa-angle-right"></i></a><a href="#"><span><i class="fa fa-cog"></i></span>General Settings <i class="fa fa-angle-right"></i></a><a class="ai-logo-button" href="#"><span style="color:#fff"><i class="fa fa-magic"></i></span>Generate Logo with AI</a>
        </article>
    </div>
    <div class="power-up">
        <div class="power-heading"><strong><i class="fa fa-bolt"></i>Power Up Your Site</strong><span>RECOMMENDED</span></div>
        <div class="promo-list">
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-hosting.jpg" alt="Enhanced hosting bundle">
                <div><b>Enhanced Hosting - 50% Off</b>
                    <p>Allow AI bots to crawl your site for better SEO visibility —</p><button>Activate</button>
                </div>
            </article>
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-ai-credits.jpg" alt="AI credits">
                <div><b>AI Credits Boost</b>
                    <p>One-time credit packs from $10 — larger packs include bonus</p><button>Get It Now</button>
                </div>
            </article>
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-business-data.jpg" alt="Instant business data">
                <div><b>Business Data</b>
                    <p>Add real local business data to populate your site</p><button>Get It Now</button>
                </div>
            </article>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>