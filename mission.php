<?php
require_once __DIR__ . '/auth.php';

$partnerEmail = 'partnerships@scifables.com';
$mailtoPartner = 'mailto:' . $partnerEmail . '?subject=' . rawurlencode('Science Fables Partnership');
$mailtoSchedule = 'mailto:' . $partnerEmail . '?subject=' . rawurlencode('Schedule a Conversation — Science Fables');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Our Mission')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main">
    <div class="mission-impact-badge" role="note">
        <span class="mission-impact-icon" aria-hidden="true">🌍</span>
        <div>
            <strong>Our Social Impact Commitment</strong>
            <p>For every nonprofit, school, or library that partners with Science Fables, we provide free access to our educational story library because we believe every child deserves the opportunity to discover science through reading.</p>
        </div>
    </div>

    <header class="mission-hero">
        <p class="mission-kicker">Partner with Science Fables</p>
        <h1 class="mission-hero-title">Making Science Accessible Through Stories</h1>
        <p class="mission-hero-lead">
            Science Fables is a free digital library of engaging science stories that helps children ages 8–12 discover STEM through imagination, curiosity, and reading.
        </p>
        <p class="mission-hero-support">
            Whether you're a nonprofit, school, library, museum, or community organization, we'd love to work together to inspire the next generation of scientists, engineers, and innovators.
        </p>
        <div class="mission-cta-row">
            <a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary">Explore Stories</a>
            <a href="#partnerships" class="btn btn-outline">Become a Partner</a>
        </div>
    </header>

    <section class="mission-section" aria-labelledby="mission-heading">
        <h2 id="mission-heading">Our Mission</h2>
        <p>We believe every child deserves the opportunity to fall in love with science.</p>
        <p>Science Fables transforms complex scientific concepts into exciting adventures that make learning natural, memorable, and fun.</p>
        <p class="mission-list-intro">Our mission is to:</p>
        <ul class="mission-checklist">
            <li>Spark curiosity about STEM</li>
            <li>Encourage reading through storytelling</li>
            <li>Make science accessible to every child</li>
            <li>Support educators, nonprofits, and families with free educational resources</li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="serve-heading">
        <h2 id="serve-heading">Who We Serve</h2>
        <div class="mission-audience-grid">
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">👧</span>
                <h3>Children (Ages 8–12)</h3>
                <p>Stories that make science exciting and easy to understand.</p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">👨‍👩‍👧</span>
                <h3>Parents</h3>
                <p>A trusted resource for educational reading at home.</p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">🍎</span>
                <h3>Teachers</h3>
                <p>Supplement classroom learning with engaging science stories.</p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">🤝</span>
                <h3>Nonprofits</h3>
                <p>Provide free STEM literacy resources to children and families.</p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">📚</span>
                <h3>Libraries &amp; Museums</h3>
                <p>Enhance reading programs and STEM outreach initiatives.</p>
            </article>
        </div>
    </section>

    <section class="mission-section" aria-labelledby="free-heading">
        <h2 id="free-heading">Why Science Fables Is Free</h2>
        <p class="mission-highlight">Our goal is simple:</p>
        <p>We want every child—regardless of background—to have access to engaging science education.</p>
        <p class="mission-list-intro">By offering free stories, we hope to:</p>
        <ul class="mission-checklist">
            <li>Inspire curiosity</li>
            <li>Improve STEM literacy</li>
            <li>Encourage lifelong reading</li>
            <li>Reach communities with limited educational resources</li>
        </ul>
        <p>As Science Fables grows, premium features may be introduced, but we are committed to keeping a rich library of stories freely available.</p>
    </section>

    <section class="mission-section" aria-labelledby="use-heading">
        <h2 id="use-heading">How Organizations Can Use Science Fables</h2>
        <p>Organizations are welcome to use Science Fables to support their educational programs.</p>
        <p class="mission-list-intro">Ideas include:</p>
        <ul class="mission-checklist mission-checklist-columns">
            <li>STEM enrichment programs</li>
            <li>After-school activities</li>
            <li>Summer reading initiatives</li>
            <li>Library reading clubs</li>
            <li>Homeschool resources</li>
            <li>Family literacy nights</li>
            <li>Classroom reading assignments</li>
            <li>Community science events</li>
        </ul>
    </section>

    <section class="mission-section" id="partnerships" aria-labelledby="partner-heading">
        <h2 id="partner-heading">Partnership Opportunities</h2>
        <p>We'd love to collaborate with organizations in ways such as:</p>
        <div class="mission-partner-grid">
            <article class="mission-partner-card">
                <span class="mission-partner-icon" aria-hidden="true">📖</span>
                <h3>Resource Partner</h3>
                <p>Share Science Fables with your students, families, or members.</p>
            </article>
            <article class="mission-partner-card">
                <span class="mission-partner-icon" aria-hidden="true">🏫</span>
                <h3>Education Partner</h3>
                <p>Integrate stories into your educational programs or curriculum.</p>
            </article>
            <article class="mission-partner-card">
                <span class="mission-partner-icon" aria-hidden="true">🌍</span>
                <h3>Community Partner</h3>
                <p>Co-host STEM reading events or science literacy initiatives.</p>
            </article>
            <article class="mission-partner-card">
                <span class="mission-partner-icon" aria-hidden="true">🤝</span>
                <h3>Content Partner</h3>
                <p>Collaborate on new stories or educational resources aligned with your mission.</p>
            </article>
        </div>
    </section>

    <section class="mission-section" aria-labelledby="why-partner-heading">
        <h2 id="why-partner-heading">Why Partner With Science Fables?</h2>
        <ul class="mission-benefits">
            <li>Free educational resource</li>
            <li>Story-based STEM learning</li>
            <li>Encourages reading and curiosity</li>
            <li>Supports educators and families</li>
            <li>Suitable for classrooms and community programs</li>
            <li>Easy to access online</li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="impact-heading">
        <h2 id="impact-heading">Impact We're Working Toward</h2>
        <p>Our vision is to help millions of children discover that science is not something to memorize—it's something to explore.</p>
        <p class="mission-list-intro">We hope to:</p>
        <ul class="mission-checklist">
            <li>Increase children's interest in STEM</li>
            <li>Strengthen reading comprehension</li>
            <li>Support teachers and nonprofit educators</li>
            <li>Make high-quality science learning accessible worldwide</li>
        </ul>
    </section>

    <section class="mission-section mission-contact" aria-labelledby="together-heading">
        <h2 id="together-heading">Let's Work Together</h2>
        <p>If your organization shares our passion for inspiring young learners, we'd love to hear from you.</p>
        <div class="mission-contact-details">
            <p>
                <span aria-hidden="true">📧</span>
                <a href="<?= e($mailtoPartner) ?>"><?= e($partnerEmail) ?></a>
            </p>
            <p>
                <span aria-hidden="true">🌐</span>
                <a href="https://www.scifables.com" rel="noopener">www.scifables.com</a>
            </p>
        </div>
        <div class="mission-cta-row">
            <a href="<?= e($mailtoPartner) ?>" class="btn btn-primary">Contact Us</a>
            <a href="<?= e($mailtoSchedule) ?>" class="btn btn-outline">Schedule a Conversation</a>
        </div>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
