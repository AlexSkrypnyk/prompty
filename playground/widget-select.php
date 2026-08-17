<?php

/**
 * @file
 * Playground - select widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Select: basic ---\n";
$r = Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: with description ---\n";
$r = Prompty::select('Dish',
  options: ['tart' => 'Pear Tart', 'soup' => 'Onion Soup', 'stew' => 'Lentil Stew', 'omelette' => 'Herb Omelette'],
  description: 'Chosen from the specials board.',
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: with hints ---\n";
$r = Prompty::select('Method',
  options: ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'],
  hints: [
    'baked' => 'Dry heat, all the way through.',
    'poached' => "Gently, in barely moving liquid.\nKeeps delicate things whole.",
    'grilled' => "Over the flame for colour.\nFast, hot, and unforgiving.",
  ],
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: with default (pre-focused) ---\n";
$r = Prompty::select('Course',
  options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
  default: 'main',
  description: 'Main is focused by default.',
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";
