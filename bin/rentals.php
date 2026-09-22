<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$container = require dirname(__DIR__) . '/bootstrap.php';
$db = $container->get(App\Core\Database::class);
$command = $argv[1] ?? '--check';
if (!in_array($command, ['--check', '--migrate', '--seed'], true)) {
    fwrite(STDERR, "Usage: php bin/rentals.php --check|--migrate|--seed\n"); exit(1);
}
try {
    $db->selectValue('SELECT 1');
    if ($command === '--migrate') {
        foreach (explode(';', file_get_contents(BASE_PATH . '/database/rentals.sql')) as $sql) {
            if (trim($sql) !== '') { $db->statement($sql); }
        }
        echo "Rental schema ready.\n";
    } elseif ($command === '--seed') {
        $seed = require BASE_PATH . '/database/seeds/rentals.php';
        $db->transaction(static function ($db) use ($seed): void {
            foreach ($seed['categories'] as $order => $category) {
                $db->statement('INSERT IGNORE INTO rental_categories (name, slug, display_order) VALUES (?, ?, ?)', [$category['name'], $category['id'], $order]);
            }
            foreach ($seed['items'] as $order => $item) {
                if ($db->selectValue('SELECT id FROM rental_items WHERE slug = ?', [$item['id']])) { continue; }
                $categoryId = $db->selectValue('SELECT id FROM rental_categories WHERE slug = ?', [$item['category']]);
                $id = $db->insert('INSERT INTO rental_items (category_id, name, slug, description, ideal_use, media_reference, is_sample, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)', [$categoryId, $item['name'], $item['id'], $item['description'], $item['ideal_for'], $item['slot'], $order]);
                foreach (explode(' · ', $item['includes']) as $position => $inclusion) {
                    $db->insert('INSERT INTO rental_inclusions (rental_item_id, inclusion_text, display_order) VALUES (?, ?, ?)', [$id, $inclusion, $position]);
                }
            }
        });
        echo "Replaceable sample data seeded. Existing records preserved.\n";
    }
    $count = $db->selectValue('SELECT COUNT(*) FROM rental_items');
    echo "Rental database connected; {$count} items.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Rental database check/setup failed. Verify the existing database connection, schema and account permissions. See docs/rentals.md.\n");
    exit(1);
}
