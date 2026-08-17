<?php

/**
 * @file
 * Playground - each widget type standalone, one at a time.
 *
 * Widgets are called directly without a flow - they handle TTY setup/teardown
 * internally in standalone mode.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Text: with description ---\n";
$r = Prompty::text('Dish name', placeholder: 'pear tart', description: "Written on the order ticket and under\n\"Specials\" on the board.");
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select: with hints ---\n";
$r = Prompty::select('Course',
  options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
  description: 'Where the dish sits in the meal.',
  hints: [
    'starter' => 'Served first, in a small portion.',
    'main' => 'The centre of the meal.',
    'dessert' => 'Sweet, served last.',
  ],
);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Multiselect: with hints ---\n";
$r = Prompty::multiselect('Extras',
  options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs', 'lemon' => 'Lemon'],
  description: "Anything served alongside.\nSpace to toggle, enter to confirm.",
  hints: [
    'bread' => 'Warm, cut at the table.',
    'olives' => 'Marinated, served in oil.',
    'herbs' => 'Picked the same morning.',
    'lemon' => 'Sharpens rich dishes.',
  ],
);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Confirm: with description ---\n";
$r = Prompty::confirm('Send order?', description: 'Passes the order to the kitchen.');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";
