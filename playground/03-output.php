<?php

/**
 * @file
 * Playground - notes of each type, tables and boxes.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']), interactive: !isset($opts['no-interaction']));

echo "\n--- Note: each type ---\n";
Prompty::note('The kitchen opens at 5pm.');
Prompty::note('Order sent to the kitchen.', type: 'success');
Prompty::note('The pear tart is running low.', type: 'warning');
Prompty::note("The oven did not reach temperature.\nCheck the fan and try again.", type: 'error');

echo "\n--- Note: in a flow ---\n";
Prompty::flow()
  ->note('The kitchen opens at 5pm.')
  ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert')
    ->children()
      // A note among children sits in the tree at their depth.
      ->note('The pear tart is running low.', type: 'warning')
        ->when(fn(array $r): bool => $r['course'] === 'dessert')
      ->confirm('warm', 'Serve warm?', description: 'Straight from the oven.')
    ->end()
  ->confirm('send', 'Send order?', description: 'Passes the order to the kitchen.')
  ->note('Order sent to the kitchen.', type: 'success')
    ->when(fn(array $r): bool => $r['send'] === TRUE)
  ->run(intro: 'Compose an order', outro: 'Order sent!', cancelled: 'Order cancelled.');

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
Prompty::intro('Kitchen order');
Prompty::box("Plate the tart, then:\n  ring the bell\n  call the table", title: 'Order ready');

echo "\n--- Box: without a title ---\n";
Prompty::intro('Kitchen order');
Prompty::box('Passes the order to the kitchen.');
