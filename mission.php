<?php
require_once __DIR__ . '/auth.php';

$partnerEmail = 'scifables2026@gmail.com';
$formMessage = '';
$formError = '';
$formSuccess = false;

$partnershipOptions = [
    'share_families' => 'Share Science Fables with the children and families we serve',
    'classrooms' => 'Use Science Fables in our classrooms',
    'after_school' => 'Use Science Fables in our after-school program',
    'reading_program' => 'Include Science Fables in our reading program',
    'newsletter' => 'Feature Science Fables in our newsletter or website',
    'stem_initiative' => 'Collaborate on a STEM reading initiative',
    'storytelling_event' => 'Organize a science storytelling event',
    'other' => "I'd like to discuss another partnership",
];

$postedName = '';
$postedOrg = '';
$postedEmail = '';
$postedRole = '';
$postedMessage = '';
$postedInterests = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedName = trim((string) ($_POST['full_name'] ?? ''));
    $postedOrg = trim((string) ($_POST['organization'] ?? ''));
    $postedEmail = trim((string) ($_POST['email'] ?? ''));
    $postedRole = trim((string) ($_POST['role'] ?? ''));
    $postedMessage = trim((string) ($_POST['message'] ?? ''));
    $rawInterests = $_POST['interests'] ?? [];
    if (!is_array($rawInterests)) {
        $rawInterests = [];
    }
    foreach ($rawInterests as $key) {
        $key = (string) $key;
        if (isset($partnershipOptions[$key])) {
            $postedInterests[] = $key;
        }
    }

    if ($postedName === '' || $postedOrg === '' || $postedEmail === '') {
        $formError = 'Please enter your name, organization, and email.';
    } elseif (!filter_var($postedEmail, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } elseif ($postedInterests === []) {
        $formError = 'Please select at least one way you would like to partner.';
    } else {
        $interestLines = [];
        foreach ($postedInterests as $key) {
            $interestLines[] = '- ' . $partnershipOptions[$key];
        }

        $inquiry = [
            'submitted_at' => date('c'),
            'full_name' => $postedName,
            'organization' => $postedOrg,
            'email' => $postedEmail,
            'role' => $postedRole,
            'interests' => array_map(static fn(string $key): string => $partnershipOptions[$key], $postedInterests),
            'message' => $postedMessage,
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/partnership-inquiries.jsonl';
        $saved = @file_put_contents($logFile, json_encode($inquiry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) !== false;

        $body = "New Science Fables partnership inquiry\n\n"
            . "Name: {$postedName}\n"
            . "Organization: {$postedOrg}\n"
            . "Email: {$postedEmail}\n"
            . "Role / Title: " . ($postedRole !== '' ? $postedRole : '(not provided)') . "\n\n"
            . "How they would like to partner:\n"
            . implode("\n", $interestLines) . "\n\n"
            . "Message:\n"
            . ($postedMessage !== '' ? $postedMessage : '(none)') . "\n\n"
            . "Submitted: " . date('Y-m-d H:i:s T') . "\n"
            . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

        $subject = 'Science Fables partnership inquiry from ' . $postedOrg;
        $headers = [
            'From: Science Fables <noreply@scifables.com>',
            'Reply-To: ' . $postedEmail,
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $sent = @mail($partnerEmail, $subject, $body, implode("\r\n", $headers));
        if ($sent || $saved) {
            $formSuccess = true;
            $formMessage = 'Thank you! Your partnership note is on its way. We will reply soon.';
            $postedName = '';
            $postedOrg = '';
            $postedEmail = '';
            $postedRole = '';
            $postedMessage = '';
            $postedInterests = [];
        } else {
            $formError = 'We could not send your message right now. Please email ' . $partnerEmail . ' directly.';
        }
    }
}
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
            <a href="#join-mission" class="btn btn-outline">🤝 Join Our Mission</a>
        </div>
    </header>

    <section class="mission-section mission-founder" aria-labelledby="founder-heading">
        <h2 id="founder-heading">Meet the Founder</h2>
        <div class="mission-founder-layout">
            <img
                src="<?= e(asset_url('assets/vaishnavi-renduchintala.jpg')) ?>"
                alt="Vaishnavi Renduchintala, founder of Science Fables"
                class="mission-founder-photo"
                width="240"
                height="300"
            >
            <div class="mission-founder-copy">
                <p class="mission-founder-name">Vaishnavi Renduchintala</p>
                <p>
                    My name is Vaishnavi Renduchintala and I am the 14-year-old founder of Science Fables.
                    I have always loved science and storytelling, so I decided to combine them into something fun for kids.
                    That's how SciFables was born.
                </p>
                <p>
                    My goal is to help children discover amazing science concepts through exciting stories that spark curiosity and make learning feel like an enjoyable experience.
                </p>
            </div>
        </div>
    </section>

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

    <section class="mission-section" aria-labelledby="receive-heading">
        <h2 id="receive-heading">What Organizations Receive</h2>
        <p class="mission-list-intro">As a Science Fables Partner, you'll receive:</p>
        <ul class="mission-receive-list">
            <li><span aria-hidden="true">📚</span> Free access to our complete library of science stories</li>
            <li><span aria-hidden="true">🔬</span> New science stories as they are published</li>
            <li><span aria-hidden="true">🖨️</span> Printable PDFs for classrooms and reading programs</li>
            <li><span aria-hidden="true">📩</span> Early access to new collections</li>
            <li><span aria-hidden="true">🌍</span> Opportunities to collaborate on STEM literacy initiatives</li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="ask-heading">
        <h2 id="ask-heading">What We Ask in Return</h2>
        <p>We don't charge organizations to use Science Fables.</p>
        <p class="mission-list-intro">Instead, we ask our partners to help us reach more children by:</p>
        <ul class="mission-checklist">
            <li>Sharing Science Fables with students and families</li>
            <li>Including our website in newsletters or resource pages</li>
            <li>Recommending our stories to educators</li>
            <li>Providing feedback so we can continue improving</li>
        </ul>
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

    <section class="mission-section" aria-labelledby="promise-heading">
        <h2 id="promise-heading">Our Promise</h2>
        <p>Science Fables is committed to keeping a growing library of science stories freely available for children, educators, libraries, and nonprofit organizations.</p>
        <p class="mission-highlight">Our goal is simple:</p>
        <p class="mission-promise-line">Inspire curiosity. Encourage reading. Make science accessible to every child.</p>
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

    <section class="mission-section mission-join" id="join-mission" aria-labelledby="join-heading">
        <h2 id="join-heading">🤝 Join Our Mission</h2>
        <p>Tell us a little about your organization and how you'd like to partner. Your note will come straight to us.</p>

        <?php if ($formSuccess): ?>
            <div class="alert alert-success"><?= e($formMessage) ?></div>
        <?php endif; ?>
        <?php if ($formError !== ''): ?>
            <div class="alert alert-error"><?= e($formError) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('mission.php')) ?>#join-mission" class="mission-partner-form">
            <div class="mission-form-grid">
                <div class="form-group">
                    <label for="full_name">Your name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required
                        value="<?= e($postedName) ?>" autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="organization">Organization *</label>
                    <input type="text" id="organization" name="organization" class="form-control" required
                        value="<?= e($postedOrg) ?>" autocomplete="organization">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" class="form-control" required
                        value="<?= e($postedEmail) ?>" autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="role">Role / title</label>
                    <input type="text" id="role" name="role" class="form-control"
                        value="<?= e($postedRole) ?>" autocomplete="organization-title">
                </div>
            </div>

            <fieldset class="mission-interest-fieldset">
                <legend>How would you like to partner with Science Fables? *</legend>
                <div class="mission-interest-list">
                    <?php foreach ($partnershipOptions as $key => $label): ?>
                        <label class="mission-interest-option">
                            <input type="checkbox" name="interests[]" value="<?= e($key) ?>"
                                <?= in_array($key, $postedInterests, true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="message">Anything else you'd like to share?</label>
                <textarea id="message" name="message" class="form-control" rows="4"><?= e($postedMessage) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">Send Partnership Note</button>
            <p class="mission-form-note">
                Or email us directly at
                <a href="mailto:<?= e($partnerEmail) ?>?subject=<?= e(rawurlencode('Science Fables Partnership')) ?>"><?= e($partnerEmail) ?></a>
            </p>
        </form>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
