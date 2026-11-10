<?php

/**
 * @file
 * Playground - notes of each type, standalone and in a flow.
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
Prompty::flow(fn(): array => [
  Prompty::note('The kitchen opens at 5pm.'),
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    default: 'dessert',
    children: [
      // A note among children sits in the tree at their depth.
      Prompty::note('The pear tart is running low.', type: 'warning', condition: fn(array $r): bool => $r['course'] === 'dessert'),
      'warm' => Prompty::confirm('Serve warm?', description: 'Straight from the oven.'),
    ],
  ),
  'send' => Prompty::confirm('Send order?', description: 'Passes the order to the kitchen.'),
  Prompty::note('Order sent to the kitchen.', type: 'success', condition: fn(array $r): bool => $r['send'] === TRUE),
], intro: 'Compose an order', outro: 'Order sent!', cancelled: 'Order cancelled.');
