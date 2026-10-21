<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/content.php';

send_security_headers();

$photos = db()->query('SELECT filename, caption FROM gallery ORDER BY created_at DESC, id DESC')->fetchAll();
$products = db()->query('SELECT * FROM products WHERE visible = 1 ORDER BY in_stock DESC, created_at DESC, id DESC')->fetchAll();
$shopCategories = array_values(array_unique(array_filter(array_column($products, 'category'))));
$number = setting('whatsapp', '');

$base = site_url();
$canonical = $base . '/';
$pageTitle = 'Svayamev | Reiki, Angel & Merlin Healing Workshops';
$pageDesc = 'Certified Reiki, Merlin Trinity & Angel Healing workshops, plus healing sessions, Akashic Record reading and attuned crystals. Svayamev: the path is within.';
$ownerName = OWNER_NAME;
$mentorAlt = ($ownerName !== '' ? $ownerName . ', ' : '') . 'founder and mentor of Svayamev, smiling in a red kurta';

function wa_link(string $number, string $message): string
{
    if ($number === '') {
        return '#contact';
    }
    return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($message);
}

$workshops = array_map(fn ($r) => [
    'name' => $r['name'],
    'tag' => $r['tag'],
    'image' => $r['image'],
    'duration' => $r['duration'],
    'mode' => $r['mode'],
    'age' => $r['age'],
    'meta' => array_values(array_filter([$r['duration'], $r['mode'], $r['levels'], $r['age']])),
    'items' => lines_to_array($r['items']),
    'note' => $r['note'],
], db()->query('SELECT * FROM workshops WHERE visible = 1 ORDER BY sort_order, id')->fetchAll());

$services = lines_to_array(setting('healing_services'));

$mantra = [
    'Only you can help yourself.',
    'Only you can save yourself.',
    'Only you can look deep within and be truly honest with yourself.',
    'Only you can create your own happiness.',
    'Because, at the end of the day, you are the one person who will always have yourself.',
];

// FAQ answers about workshops are built from the workshops saved in the admin, so they never go stale.
$pairs = fn (array $list, callable $text) => implode(' ', array_map($text, $list));
$faqs = [
    ['What is Svayamev?', 'Svayamev is here to help you retake the life evolving journey and know your true-self in a much deeper way, through self work and self realisation.'],
];
if ($workshops) {
    $faqs[] = ['Which workshops can I learn?', 'You can learn ' . implode(', ', array_map(fn ($w) => $w['name'] . ($w['tag'] !== '' ? ' (' . $w['tag'] . ')' : ''), $workshops)) . '.'];
    $withAge = array_filter($workshops, fn ($w) => $w['age'] !== '');
    if ($withAge) {
        $faqs[] = ['What is the age criteria for the workshops?', $pairs($withAge, fn ($w) => $w['name'] . ': ' . rtrim($w['age'], '.') . '.')];
    }
    $withMode = array_filter($workshops, fn ($w) => $w['mode'] !== '');
    if ($withMode) {
        $faqs[] = ['Are the workshops online or offline?', $pairs($withMode, fn ($w) => $w['name'] . ($w['tag'] !== '' ? ' (' . $w['tag'] . ')' : '') . ': ' . rtrim($w['mode'], '.') . '.')];
    }
    $withCert = array_filter($workshops, fn ($w) => stripos($w['note'], 'certificate') !== false);
    if ($withCert) {
        $faqs[] = ['Will I receive a certificate?', 'Course content and a certificate are provided for: ' . implode(', ', array_map(fn ($w) => $w['name'], $withCert)) . '. Message us on WhatsApp if you would like to know about any other workshop.'];
    }
    $faqs[] = ['Can I learn more than one level?', 'Yes. You can learn as many levels as you want, as per your calling. You need to complete the practice duration of each level before learning the next. At every level the healing capacity increases.'];
}
$faqs[] = ['How do I book a healing or cleansing session?', 'Appointments are requirement based and taken via WhatsApp message only. If you are not sure what you need, feel lost or overwhelmed, message us and we will help you decide which service is for your highest good.'];
if ($services) {
    $faqs[] = ['Which healing and consultation services are available?', implode(', ', $services) . '.'];
}
$faqs[] = ['How can I order attuned crystals?', 'Message us on WhatsApp to know more and to order attuned crystals for wearing, carrying, or bringing positivity to your house or office.'];

// Reviews: approved ones are shown, new ones from visitors wait for admin approval.
$reviewState = ['error' => '', 'name' => '', 'body' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'review') {
    $reviewState['name'] = clean_text(str_replace("\n", ' ', (string) ($_POST['name'] ?? '')), 60);
    $reviewState['body'] = clean_text((string) ($_POST['body'] ?? ''), 600);

    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        // Hidden trap field filled in: a bot. Pretend it worked.
        header('Location: ./?review=thanks#reviews');
        exit;
    }
    $tokenError = check_form_token((string) ($_POST['t'] ?? ''));
    if ($tokenError !== null) {
        $reviewState['error'] = $tokenError;
    } elseif (mb_strlen($reviewState['name']) < 2) {
        $reviewState['error'] = 'Please enter your name.';
    } elseif (mb_strlen($reviewState['body']) < 15) {
        $reviewState['error'] = 'Please write a little more (at least 15 characters).';
    } elseif (preg_match('~https?://|www\.|\S+@\S+\.\S+~i', $reviewState['name'] . ' ' . $reviewState['body'])) {
        $reviewState['error'] = 'Please do not include links or email addresses in your review.';
    } else {
        $recent = db()->prepare('SELECT COUNT(*) FROM reviews WHERE ip_hash = ? AND created_at > ?');
        $recent->execute([visitor_hash(), time() - 3600]);
        if ((int) $recent->fetchColumn() >= 3) {
            $reviewState['error'] = 'You have sent a few reviews recently. Please try again later.';
        } else {
            db()->prepare('INSERT INTO reviews (name, body, status, ip_hash, created_at) VALUES (?, ?, "pending", ?, ?)')
                ->execute([$reviewState['name'], $reviewState['body'], visitor_hash(), time()]);
            header('Location: ./?review=thanks#reviews');
            exit;
        }
    }
}
$approvedReviews = db()->query('SELECT name, body FROM reviews WHERE status = "approved" ORDER BY created_at DESC, id DESC LIMIT 30')->fetchAll();
$reviewThanks = ($_GET['review'] ?? '') === 'thanks';
$formToken = make_form_token();

$org = [
    '@type' => 'ProfessionalService',
    '@id' => $base . '/#org',
    'name' => SITE_NAME,
    'slogan' => 'The path is within',
    'url' => $canonical,
    'description' => $pageDesc,
    'logo' => ['@type' => 'ImageObject', 'url' => $base . '/assets/img/logo-800.webp', 'width' => 800, 'height' => 800],
    'image' => [$base . '/assets/img/og-image.jpg', $base . '/assets/img/teacher-800.jpg'],
    'knowsAbout' => ['Reiki', 'Chakra healing', 'Merlin healing', 'Angel healing', 'Akashic Record reading', 'Crystal healing', 'Meditation'],
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name' => 'Healing services',
        'itemListElement' => array_map(fn ($s) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s]], $services),
    ],
];
if ($number !== '') {
    $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => '+' . $number, 'availableLanguage' => ['English', 'Hindi']];
}
if (SOCIAL_PROFILES) {
    $org['sameAs'] = SOCIAL_PROFILES;
}
if ($ownerName !== '') {
    $org['founder'] = ['@type' => 'Person', 'name' => $ownerName, 'jobTitle' => 'Founder and mentor', 'image' => $base . '/assets/img/teacher-800.jpg'];
}

$courses = [];
foreach ($workshops as $w) {
    $courses[] = array_filter([
        '@type' => 'Course',
        'name' => $w['name'] . ($w['tag'] !== '' ? ' (' . $w['tag'] . ')' : ''),
        'description' => $w['name'] . ' workshop' . ($w['items'] ? ' covering: ' . implode('; ', array_slice($w['items'], 0, 5)) : '') . '.',
        'provider' => ['@id' => $base . '/#org'],
        'inLanguage' => 'en',
        'image' => $w['image'] !== '' ? $base . '/uploads/' . $w['image'] : null,
        'hasCourseInstance' => array_filter([
            '@type' => 'CourseInstance',
            'courseMode' => mode_to_schema($w['mode']),
            'courseWorkload' => duration_to_iso($w['duration']),
        ]),
    ]);
}

$productNodes = [];
foreach ($products as $p) {
    $productNodes[] = array_filter([
        '@type' => 'Product',
        'name' => $p['name'],
        'description' => $p['description'] !== '' ? $p['description'] : $p['name'] . ' by ' . SITE_NAME,
        'image' => $base . '/uploads/' . $p['image'],
        'category' => $p['category'] ?: null,
        'brand' => ['@type' => 'Brand', 'name' => SITE_NAME],
    ]);
}

$graph = [
    '@context' => 'https://schema.org',
    '@graph' => array_merge([
        $org,
        ['@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $canonical, 'name' => SITE_NAME, 'inLanguage' => 'en-IN', 'publisher' => ['@id' => $base . '/#org']],
        ['@type' => 'WebPage', '@id' => $canonical . '#page', 'url' => $canonical, 'name' => $pageTitle, 'description' => $pageDesc, 'isPartOf' => ['@id' => $base . '/#website'], 'about' => ['@id' => $base . '/#org'], 'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $base . '/assets/img/og-image.jpg']],
    ], $courses, $productNodes, [[
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
    ]]),
];
$jsonLd = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <meta name="author" content="<?= e($ownerName !== '' ? $ownerName : SITE_NAME) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#0e1738">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:locale" content="en_IN">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($base) ?>/assets/img/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Svayamev logo: the path is within">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <meta name="twitter:image" content="<?= e($base) ?>/assets/img/og-image.jpg">

    <link rel="icon" href="favicon.ico" sizes="32x32">
    <link rel="icon" type="image/png" href="assets/img/icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="sitemap" type="application/xml" href="sitemap.xml">
    <script type="application/ld+json"><?= $jsonLd ?></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/js-flag.js"></script>
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    <header class="site-header" id="top">
        <a class="brand" href="#top" aria-label="Svayamev home">
            <img class="brand-mark" src="assets/img/emblem-96.png" width="44" height="44" alt="" decoding="async">
            <span>Svayamev</span>
        </a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="nav">Menu</button>
        <nav id="nav" class="nav" aria-label="Main">
            <a href="#about">About</a>
            <?php if ($workshops): ?><a href="#workshops">Workshops</a><?php endif; ?>
            <a href="#healing">Healing</a>
            <a href="#crystals">Crystals</a>
            <?php if ($products): ?><a href="#shop">Shop</a><?php endif; ?>
            <a href="#reviews">Reviews</a>
            <a href="#faq">FAQ</a>
            <?php if ($photos): ?><a href="#gallery">Gallery</a><?php endif; ?>
            <a class="nav-cta" href="<?= e(wa_link($number, 'Hi Svayamev, I would like to know more.')) ?>" target="_blank" rel="noopener">WhatsApp</a>
        </nav>
    </header>

    <main id="main">
        <!-- Hero -->
        <section class="hero">
            <svg class="mandala" viewBox="0 0 600 600" aria-hidden="true"><use href="#mandala"/></svg>
            <div class="hero-inner">
                <p class="eyebrow reveal">Welcome</p>
                <h1 class="reveal">Svayamev,<br><em>the path is within.</em></h1>
                <p class="lead reveal">The time has come to meet your true-self. Embark on this path of going within, today.</p>
                <div class="actions reveal">
                    <a class="btn btn-gold" href="#workshops">Explore workshops</a>
                    <a class="btn btn-ghost" href="<?= e(wa_link($number, 'Hi Svayamev, I would like to book a session.')) ?>" target="_blank" rel="noopener">Message on WhatsApp</a>
                </div>
            </div>
            <a class="scroll-cue" href="#about" aria-label="Scroll to About"><span></span></a>
        </section>

        <!-- Objective + About me -->
        <section class="section about" id="about">
            <div class="wrap">
                <header class="section-head center narrow reveal">
                    <p class="eyebrow dark">Our objective</p>
                    <h2>Retake the life evolving journey</h2>
                    <p>Svayamev is here to help you retake the life evolving journey and know your true-self in a much deeper way.</p>
                </header>
            </div>
            <div class="wrap split about-split">
                <figure class="mentor reveal">
                    <picture>
                        <source type="image/webp" srcset="assets/img/teacher-480.webp 480w, assets/img/teacher-800.webp 800w" sizes="(min-width: 900px) 440px, 88vw">
                        <img src="assets/img/teacher-800.jpg" width="800" height="1067" alt="<?= e($mentorAlt) ?>" loading="lazy" decoding="async">
                    </picture>
                    <figcaption>
                        <?php if ($ownerName !== ''): ?><strong><?= e($ownerName) ?></strong><?php else: ?><strong>Your mentor</strong><?php endif; ?>
                        <span>Founder &amp; mentor, Svayamev</span>
                    </figcaption>
                </figure>
                <div class="about-card reveal">
                    <p class="eyebrow dark">About me</p>
                    <p>It has been over a decade now. I have been practising spirituality and I will always be seeking to learn more, go deeper and deeper into my purpose.</p>
                    <p>Svayamev is the story of my life. I always had the guidance from my gurus, but the real journey began when I started putting the guidance into practice with the quest to find my answers to my existence. With every practice I learnt, to all my questions the answers have always been within me. Tapping into my existence has brought me here today with Svayamev as not only a purpose for me but also for the world.</p>
                </div>
            </div>
        </section>

        <!-- Mantra -->
        <section class="section mantra" aria-labelledby="mantra-title">
            <div class="wrap narrow">
                <p class="eyebrow reveal" id="mantra-title">About Svayamev</p>
                <p class="mantra-intro reveal">Svayamev's purpose here is to encourage self work, to start a more meaningful existence.</p>
                <ul class="mantra-list">
                    <?php foreach ($mantra as $line): ?>
                        <li class="reveal"><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mantra-close reveal">Your healing begins within you. Your power lies within you. And your journey towards becoming your truest self begins the moment you choose yourself.</p>
            </div>
        </section>

        <?php if ($workshops): ?>
        <!-- Workshops -->
        <section class="section workshops" id="workshops">
            <div class="wrap">
                <header class="section-head reveal">
                    <p class="eyebrow dark">Certified workshops</p>
                    <h2>Learn, practise, heal</h2>
                </header>
                <div class="cards">
                    <?php foreach ($workshops as $i => $w): ?>
                        <article class="card reveal<?= $w['image'] !== '' ? ' has-img' : '' ?>">
                            <?php if ($w['image'] !== ''):
                                $sz = @getimagesize(UPLOAD_DIR . '/' . $w['image']) ?: [1200, 750]; ?>
                                <div class="card-img"><img src="uploads/<?= e($w['image']) ?>" width="<?= (int) $sz[0] ?>" height="<?= (int) $sz[1] ?>" alt="<?= e($w['name'] . ' workshop') ?>" loading="lazy" decoding="async"></div>
                            <?php endif; ?>
                            <span class="card-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <?php if ($w['tag'] !== ''): ?><p class="chip"><?= e($w['tag']) ?></p><?php endif; ?>
                            <h3><?= e($w['name']) ?></h3>
                            <?php if ($w['meta']): ?>
                                <ul class="meta">
                                    <?php foreach ($w['meta'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($w['items'] || $w['note'] !== ''): ?>
                                <details>
                                    <summary>Course content</summary>
                                    <?php if ($w['items']): ?>
                                        <ul class="items">
                                            <?php foreach ($w['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if ($w['note'] !== ''): ?><p class="note"><?= e($w['note']) ?></p><?php endif; ?>
                                </details>
                            <?php endif; ?>
                            <a class="card-link" href="<?= e(wa_link($number, 'Hi Svayamev, I am interested in the ' . $w['name'] . ' workshop' . ($w['tag'] !== '' ? ' (' . $w['tag'] . ')' : '') . '.')) ?>" target="_blank" rel="noopener">Enquire on WhatsApp &rarr;</a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="levels-note reveal">Individuals have a choice to learn as many levels as they want to, as per their calling. However, one has to complete the duration of practice of each level to learn further levels. At every level the healing capacity increases, making you even more spiritually connected, balanced in mind, body and soul.</p>
            </div>
        </section>
        <?php endif; ?>

        <!-- Healing -->
        <section class="section healing" id="healing">
            <div class="wrap split">
                <div class="reveal">
                    <p class="eyebrow">Healing &amp; cleansing</p>
                    <h2>Consultation &amp; healing services</h2>
                    <p>Requirement based appointments are taken via WhatsApp message only. If you don't understand what your exact requirement is, feel lost, overwhelmed or unable to identify the concern, feel free to talk via WhatsApp message. We can help you decide what service can be taken for your highest good.</p>
                    <a class="btn btn-gold" href="<?= e(wa_link($number, 'Hi Svayamev, I would like to book a healing session.')) ?>" target="_blank" rel="noopener">Book via WhatsApp</a>
                </div>
                <?php if ($services): ?>
                    <ul class="service-list reveal">
                        <?php foreach ($services as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <!-- Crystals -->
        <section class="section crystals" id="crystals">
            <div class="wrap narrow center">
                <svg class="gem" viewBox="0 0 64 64" width="56" height="56" aria-hidden="true"><use href="#gem"/></svg>
                <p class="eyebrow dark reveal">Attuned crystals</p>
                <h2 class="reveal">Mother earth's jewels</h2>
                <p class="reveal">Crystals work for any individual's highest and greatest good. Wearing a crystal bracelet, carrying a crystal or using it for house/office positivity are available in many forms and have uncountable benefits. They work wonders at all levels of life.</p>
                <a class="btn btn-plum reveal" href="<?= e(wa_link($number, 'Hi Svayamev, I would like to know more about attuned crystals.')) ?>" target="_blank" rel="noopener">Know more &amp; order on WhatsApp</a>
            </div>
        </section>

        <?php if ($products): ?>
        <!-- Crystal shop -->
        <section class="section shop" id="shop" aria-labelledby="shop-title">
            <div class="wrap">
                <header class="section-head center narrow reveal">
                    <p class="eyebrow dark">Crystal shop</p>
                    <h2 id="shop-title">Attuned crystals, ready for you</h2>
                    <p>Pick the crystal you love and tap the button. WhatsApp opens with your message ready, and we reply with the price and delivery details.</p>
                </header>

                <?php if (count($shopCategories) > 1): ?>
                    <div class="filters reveal" role="group" aria-label="Filter crystals by category">
                        <button type="button" class="filter is-active" data-filter="all" aria-pressed="true">All</button>
                        <?php foreach ($shopCategories as $c): ?>
                            <button type="button" class="filter" data-filter="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="products">
                    <?php foreach ($products as $p):
                        $size = @getimagesize(UPLOAD_DIR . '/' . $p['image']) ?: [1000, 1000];
                        $msg = "Hi Svayamev, I would like to know the price of:\n\n• " . $p['name'] . " × 1\n\nPlease share the price and delivery details.";
                    ?>
                        <article class="product reveal" data-category="<?= e($p['category']) ?>">
                            <div class="p-img">
                                <img src="uploads/<?= e($p['image']) ?>" width="<?= (int) $size[0] ?>" height="<?= (int) $size[1] ?>" alt="<?= e($p['name'] . ($p['category'] !== '' ? ' (' . $p['category'] . ')' : '') . ', attuned crystal') ?>" loading="lazy" decoding="async">
                                <?php if (!$p['in_stock']): ?><span class="tag tag-sold">Sold out</span><?php endif; ?>
                            </div>
                            <div class="p-body">
                                <?php if ($p['category'] !== ''): ?><p class="p-cat"><?= e($p['category']) ?></p><?php endif; ?>
                                <h3><?= e($p['name']) ?></h3>
                                <?php if ($p['description'] !== ''): ?><p class="p-desc"><?= e($p['description']) ?></p><?php endif; ?>
                                <?php if ($p['in_stock']): ?>
                                    <div class="p-buy">
                                        <div class="qty" role="group" aria-label="Quantity">
                                            <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                                            <output aria-live="polite">1</output>
                                            <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                                        </div>
                                        <a class="btn btn-gold buy" href="<?= e(wa_link($number, $msg)) ?>" target="_blank" rel="noopener"
                                           data-name="<?= e($p['name']) ?>" data-wa="<?= e($number) ?>">Ask price on WhatsApp</a>
                                    </div>
                                <?php else: ?>
                                    <a class="btn btn-ghost-dark" href="<?= e(wa_link($number, 'Hi Svayamev, please let me know when "' . $p['name'] . '" is back in stock.')) ?>" target="_blank" rel="noopener">Ask when available</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="shop-note reveal">Prices are shared on WhatsApp, along with payment and delivery details.</p>
            </div>
        </section>
        <?php endif; ?>

        <!-- FAQ -->
        <section class="section faq" id="faq" aria-labelledby="faq-title">
            <div class="wrap narrow">
                <header class="section-head center reveal">
                    <p class="eyebrow dark">Questions</p>
                    <h2 id="faq-title">Frequently asked questions</h2>
                </header>
                <div class="faq-list">
                    <?php foreach ($faqs as $f): ?>
                        <details class="reveal">
                            <summary><h3><?= e($f[0]) ?></h3></summary>
                            <p><?= e($f[1]) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($photos): ?>
        <!-- Gallery -->
        <section class="section gallery" id="gallery">
            <div class="wrap">
                <header class="section-head reveal">
                    <p class="eyebrow">Moments</p>
                    <h2>Gallery</h2>
                </header>
                <ul class="grid">
                    <?php foreach ($photos as $p): ?>
                        <li class="reveal">
                            <button type="button" class="thumb" data-full="uploads/<?= e($p['filename']) ?>" data-caption="<?= e($p['caption']) ?>" aria-label="Open photo<?= $p['caption'] !== '' ? ': ' . e($p['caption']) : '' ?>">
                                <img src="uploads/<?= e($p['filename']) ?>" alt="<?= e($p['caption'] !== '' ? $p['caption'] : 'Svayamev gallery photo') ?>" loading="lazy">
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <!-- Testimonials -->
        <section class="section reviews" id="reviews" aria-labelledby="reviews-title">
            <div class="wrap">
                <header class="section-head center narrow reveal">
                    <p class="eyebrow dark">Testimonials</p>
                    <h2 id="reviews-title">Words from our community</h2>
                    <p>Experiences shared by people who chose to go within.</p>
                </header>

                <?php if ($approvedReviews): ?>
                    <div class="review-grid">
                        <?php foreach ($approvedReviews as $r): ?>
                            <figure class="review reveal">
                                <blockquote><p><?= nl2br(e($r['body'])) ?></p></blockquote>
                                <figcaption><?= e($r['name']) ?></figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="review-form-card reveal">
                    <h3>Share your experience</h3>
                    <p class="muted"><?= $approvedReviews ? 'Been with Svayamev? We would love to hear from you.' : 'Be the first to share your experience with Svayamev.' ?> Your review appears on the website after approval.</p>

                    <?php if ($reviewThanks): ?>
                        <p class="form-ok" role="status">Thank you! Your review has been received. It will appear here once approved.</p>
                    <?php endif; ?>
                    <?php if ($reviewState['error'] !== ''): ?>
                        <p class="form-error" role="alert"><?= e($reviewState['error']) ?></p>
                    <?php endif; ?>

                    <form method="post" action="./#reviews" class="review-form">
                        <input type="hidden" name="form" value="review">
                        <input type="hidden" name="t" value="<?= e($formToken) ?>">
                        <div class="hp" aria-hidden="true">
                            <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <label for="rv-name">Your name</label>
                        <input id="rv-name" name="name" type="text" required minlength="2" maxlength="60" autocomplete="name" value="<?= e($reviewState['name']) ?>">
                        <label for="rv-body">Your review</label>
                        <textarea id="rv-body" name="body" rows="5" required minlength="15" maxlength="600" placeholder="Tell us about your experience..."><?= e($reviewState['body']) ?></textarea>
                        <button type="submit" class="btn btn-plum">Submit review</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="section contact" id="contact">
            <div class="wrap narrow center">
                <h2 class="reveal">Your journey begins within</h2>
                <p class="reveal">Say hello, ask a question, or book your place. We reply on WhatsApp.</p>
                <a class="btn btn-gold btn-lg reveal" href="<?= e(wa_link($number, 'Hi Svayamev, I would like to know more.')) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <picture>
            <source type="image/webp" srcset="assets/img/logo-400.webp">
            <img class="foot-logo" src="assets/img/logo-400.jpg" width="400" height="400" alt="Svayamev logo: the path is within" loading="lazy" decoding="async">
        </picture>
        <nav class="foot-nav" aria-label="Footer">
            <a href="#about">About</a>
            <?php if ($workshops): ?><a href="#workshops">Workshops</a><?php endif; ?>
            <a href="#healing">Healing</a>
            <a href="#crystals">Crystals</a>
            <a href="#reviews">Reviews</a>
            <a href="#faq">FAQ</a>
            <a href="<?= e(wa_link($number, 'Hi Svayamev, I would like to know more.')) ?>" target="_blank" rel="noopener">WhatsApp</a>
        </nav>
        <p class="copy">&copy; <span id="year"><?= date('Y') ?></span> Svayamev. All rights reserved.</p>
    </footer>

    <div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Photo viewer">
        <button type="button" class="lb-close" aria-label="Close">&times;</button>
        <figure>
            <img src="" alt="">
            <figcaption></figcaption>
        </figure>
    </div>

    <!-- Reusable SVG art -->
    <svg width="0" height="0" class="defs" aria-hidden="true" focusable="false">
        <defs>
            <g id="lotus" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round">
                <path d="M20 6c4 5 5 11 0 18-5-7-4-13 0-18Z"/>
                <path d="M20 24c-1-6-6-10-12-10 0 7 4 11 12 10Z"/>
                <path d="M20 24c1-6 6-10 12-10 0 7-4 11-12 10Z"/>
                <path d="M20 24c-4 2-8 1-13-2M20 24c4 2 8 1 13-2" opacity=".6"/>
                <path d="M12 31c5 3 11 3 16 0" opacity=".6"/>
            </g>
            <g id="gem" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                <path d="M16 10h32l10 14-26 30L6 24l10-14Z"/>
                <path d="M6 24h52M24 10l-6 14 14 30 14-30-6-14M18 24h28"/>
            </g>
            <g id="mandala" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="300" cy="300" r="290"/>
                <circle cx="300" cy="300" r="240" stroke-dasharray="2 8"/>
                <circle cx="300" cy="300" r="150"/>
                <circle cx="300" cy="300" r="60"/>
                <g id="petals">
                    <path d="M300 60c40 70 40 130 0 180-40-50-40-110 0-180Z"/>
                    <path d="M300 540c40-70 40-130 0-180-40 50-40 110 0 180Z"/>
                    <path d="M60 300c70-40 130-40 180 0-50 40-110 40-180 0Z"/>
                    <path d="M540 300c-70-40-130-40-180 0 50 40 110 40 180 0Z"/>
                </g>
                <use href="#petals" transform="rotate(45 300 300)"/>
                <use href="#petals" transform="rotate(22.5 300 300)" opacity=".55"/>
                <use href="#petals" transform="rotate(67.5 300 300)" opacity=".55"/>
            </g>
        </defs>
    </svg>

    <script src="assets/js/main.js"></script>
</body>
</html>
