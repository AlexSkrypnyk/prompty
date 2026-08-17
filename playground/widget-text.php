<?php

/**
 * @file
 * Playground - text widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Text: basic ---\n";
$r = Prompty::text('Dish name', placeholder: 'pear tart');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Text: with description ---\n";
$r = Prompty::text('Guest name', placeholder: 'Jane Doe', description: "Printed on the order ticket.\nLeave blank for takeaway.");
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Text: no placeholder ---\n";
$r = Prompty::text('Allergy note');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Text: with editable default ---\n";
$r = Prompty::text('Dish name', default: 'pear tart', description: 'Pre-filled and editable.');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";
