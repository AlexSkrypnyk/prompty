<?php

/**
 * @file
 * Playground - tables and boxes.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Table: rows only ---\n";
Prompty::intro('Order summary');
Prompty::table([
  ['Dish', 'Pear tart'],
  ['Course', 'Dessert'],
  ['Extras', 'Bread, olives'],
]);

echo "\n--- Table: headers and section rows ---\n";
Prompty::intro('Order summary');
Prompty::table([
  'The dish',
  ['Dish name', 'Pear tart'],
  ['Course', 'Dessert'],
  'Service',
  ['Table', 'Table 12'],
  ['Extras', 'Bread, olives'],
  ['Kitchen note', 'No nuts, and the cream on the side rather than poured over the tart'],
], headers: ['Field', 'Answer']);

echo "\n--- Box: with a title ---\n";
Prompty::box("Plate the tart, then:\n  ring the bell\n  call the table", title: 'Order ready');
Prompty::outro('Order sent!');

echo "\n--- Box: without a title ---\n";
Prompty::intro('Kitchen order');
Prompty::box('Passes the order to the kitchen.');
