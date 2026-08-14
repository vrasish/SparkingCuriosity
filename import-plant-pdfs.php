<?php
/**
 * One-time bulk import for Plants category PDFs from ~/Downloads.
 * Safe to re-run — skips titles already in the library.
 *
 * Usage: /Applications/XAMPP/xamppfiles/bin/php import-plant-pdfs.php
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cart-lib.php';

ensure_book_pdf_schema($pdo);
ensure_book_pricing_schema($pdo);

$author = 'Vaishnavi Renduchintala';
$downloads = '/Users/vaishnavi/Downloads';
$uploadDir = books_upload_dir();
$coversDir = __DIR__ . '/uploads/covers';

foreach ([$uploadDir, $coversDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$defaultCoverSource = __DIR__ . '/cover_plants.png';
$defaultCoverRel = 'uploads/covers/plants-section-cover.png';
$defaultCoverDisk = __DIR__ . '/' . $defaultCoverRel;
if (is_file($defaultCoverSource) && !is_file($defaultCoverDisk)) {
    copy($defaultCoverSource, $defaultCoverDisk);
}
$coverUrl = app_base_path() . '/' . $defaultCoverRel;

/** @var list<array{file: string, title: string, description: string, science_element: string}> */
$stories = [
    [
        'file' => 'The_Secret_Inside_the_Seed_Beach_Style.pdf',
        'title' => 'The Secret Inside the Seed',
        'description' => 'A curious kid opens up a mystery seed and discovers the tiny plant waiting inside — plus everything a seed needs before it can sprout.',
        'science_element' => 'Seeds hold a baby plant, stored food, and a protective coat. With water, warmth, and air, the seed can germinate and grow.',
    ],
    [
        'file' => 'The_Seed_That_Finally_Woke_Up_Beach_Style.pdf',
        'title' => 'The Seed That Finally Woke Up',
        'description' => 'One stubborn seed sleeps while others sprout around it. A young gardener learns what finally gives that seed the signal to wake up and grow.',
        'science_element' => 'Germination happens when a seed gets the right mix of water, oxygen, and temperature so the embryo can start growing.',
    ],
    [
        'file' => 'From_Seed_to_Seed_Again_Beach_Style.pdf',
        'title' => 'From Seed to Seed Again',
        'description' => 'Follow one plant through the full adventure — sprouting, growing, flowering, making new seeds, and starting the cycle all over again.',
        'science_element' => 'Plants have a life cycle: seed, sprout, grow, flower, make seeds, and die or rest — then the cycle can begin again.',
    ],
    [
        'file' => 'The_Traveling_Seed_Premium.pdf',
        'title' => 'The Traveling Seed',
        'description' => 'A seed sets off on an unexpected journey by wind, water, and animal helpers until it finds the perfect place to put down roots.',
        'science_element' => 'Seed dispersal spreads plants to new places. Seeds can travel by wind, water, animals, or by hitching a ride on people and clothes.',
    ],
    [
        'file' => 'The_Plants_Water_Elevator_Beach_Style_Fixed_v2.pdf',
        'title' => "The Plant's Water Elevator",
        'description' => 'When a potted plant looks thirsty on top but wet on the bottom, two friends investigate how water climbs from roots to leaves.',
        'science_element' => 'Plants pull water up through tubes called xylem. Transpiration from leaves helps draw water from roots to the rest of the plant.',
    ],
    [
        'file' => 'The_Leaf_Detective_Beach_Style.pdf',
        'title' => 'The Leaf Detective',
        'description' => 'A sharp-eyed sleuth uses leaf clues — shape, veins, and color — to figure out which plants belong to the same family.',
        'science_element' => 'Leaves come in many shapes and patterns. Leaf structure helps identify plants and shows how they capture sunlight for photosynthesis.',
    ],
    [
        'file' => 'The_Plant_That_Ate_Lunch_Beach_Style.pdf',
        'title' => 'The Plant That Ate Lunch',
        'description' => 'A classroom terrarium gets weird when a plant seems to snack on bugs. The kids uncover how some plants catch their own food.',
        'science_element' => 'Most plants make food through photosynthesis, but some carnivorous plants trap insects to get extra nutrients in poor soil.',
    ],
    [
        'file' => 'The_Plant_With_a_Trick_Beach_Style.pdf',
        'title' => 'The Plant With a Trick',
        'description' => 'A strange desert plant surprises hikers with a clever survival trick that helps it save water in harsh conditions.',
        'science_element' => 'Plants adapt to their environment with tricks like thick stems, waxy coatings, and deep roots to store water and survive drought.',
    ],
    [
        'file' => 'The_Flower_That_Sent_an_Invitation_Beach_Style.pdf',
        'title' => 'The Flower That Sent an Invitation',
        'description' => 'Bright petals and sweet scents aren’t just for show — they’re invitations for pollinators to visit and help the garden thrive.',
        'science_element' => 'Flowers attract pollinators with color, scent, and nectar. When pollen moves between flowers, plants can make seeds and fruit.',
    ],
    [
        'file' => 'The_Sweet_Secret_of_the_Mango_Beach_Style_Corrected.pdf',
        'title' => 'The Sweet Secret of the Mango',
        'description' => 'A mango tree in the schoolyard holds a sweet secret about how flowers slowly transform into juicy fruit.',
        'science_element' => 'After pollination, a flower’s ovary can develop into fruit that protects seeds and helps spread them when animals eat it.',
    ],
    [
        'file' => 'The_Hidden_Helpers_Underground_Beach_Style.pdf',
        'title' => 'The Hidden Helpers Underground',
        'description' => 'Digging in the garden reveals a busy world beneath the soil where roots and tiny helpers work together to feed plants.',
        'science_element' => 'Roots anchor plants and absorb water and minerals. Healthy soil life helps roots access nutrients plants need to grow.',
    ],
];

$stmtExisting = $pdo->prepare('SELECT book_id FROM books WHERE title = ? LIMIT 1');
$stmtCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
$stmtCat->execute(['Plants']);
$catRow = $stmtCat->fetch();
if ($catRow) {
    $categoryId = (int) $catRow['category_id'];
} else {
    $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute(['Plants']);
    $categoryId = (int) $pdo->lastInsertId();
}

$insertBook = $pdo->prepare("
    INSERT INTO books (
        title, author_name, description, cover_image_url,
        age_group, science_element, status, book_format, pdf_file_path,
        created_by, price_cents
    ) VALUES (
        :title, :author_name, :description, :cover_image_url,
        '8-15', :science_element, 'approved', 'pdf', :pdf_file_path,
        NULL, 0
    )
");
$insertBookCat = $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)');

$imported = 0;
$skipped = 0;
$errors = [];

foreach ($stories as $story) {
    $stmtExisting->execute([$story['title']]);
    if ($stmtExisting->fetch()) {
        echo "SKIP (exists): {$story['title']}\n";
        $skipped++;
        continue;
    }

    $source = $downloads . '/' . $story['file'];
    if (!is_file($source)) {
        $errors[] = "Missing file: {$story['file']}";
        echo "ERROR missing: {$story['file']}\n";
        continue;
    }

    $basename = 'book_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    usleep(1000);
    $dest = $uploadDir . '/' . $basename;
    if (!copy($source, $dest)) {
        $errors[] = "Could not copy: {$story['file']}";
        echo "ERROR copy: {$story['file']}\n";
        continue;
    }

    $relativePath = 'uploads/books/' . $basename;

    try {
        $pdo->beginTransaction();
        $insertBook->execute([
            'title' => $story['title'],
            'author_name' => $author,
            'description' => $story['description'],
            'cover_image_url' => $coverUrl,
            'science_element' => $story['science_element'],
            'pdf_file_path' => $relativePath,
        ]);
        $bookId = (int) $pdo->lastInsertId();
        $insertBookCat->execute([$bookId, $categoryId]);
        $pdo->commit();
        echo "IMPORTED: {$story['title']} (#{$bookId})\n";
        $imported++;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($dest);
        $errors[] = $story['title'] . ': ' . $ex->getMessage();
        echo "ERROR db: {$story['title']}\n";
    }
}

echo "\nDone. Imported: {$imported}, skipped: {$skipped}, errors: " . count($errors) . "\n";
if ($errors !== []) {
    foreach ($errors as $err) {
        echo " - {$err}\n";
    }
    exit(1);
}
