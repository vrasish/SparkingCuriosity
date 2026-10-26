<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../book-i18n-lib.php';
require_once __DIR__ . '/../cache-lib.php';

$pdo = stories_connect();
ensure_book_i18n_schema($pdo);

$jsonPath = $argv[1] ?? '/tmp/science-es.json';
$rows = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($rows)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$upd = $pdo->prepare('UPDATE books SET science_element_es = ? WHERE book_id = ?');
$n = 0;
foreach ($rows as $row) {
    $upd->execute([(string) $row['science_element_es'], (int) $row['book_id']]);
    $n += $upd->rowCount() ? 1 : 0;
}
stories_page_cache_clear();
echo "Updated $n science_element_es rows\n";
