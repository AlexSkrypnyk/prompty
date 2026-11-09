<?php

/**
 * @file
 * Playground - select and multiselect with long, scrolled option lists.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Select: long list ---\n";
$r = Prompty::select('Menu',
  options: [
    'tart' => 'Pear Tart',
    'crumble' => 'Apple Crumble',
    'soup' => 'Onion Soup',
    'stew' => 'Lentil Stew',
    'omelette' => 'Herb Omelette',
    'salad' => 'Barley Salad',
    'compote' => 'Plum Compote',
    'loaf' => 'Walnut Loaf',
  ],
  default: 'compote',
  description: 'The whole menu, 3 dishes at a time.',
  scroll: 3,
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Multiselect: long list ---\n";
$r = Prompty::multiselect('Pantry',
  options: [
    'bread' => 'Bread',
    'olives' => 'Olives',
    'herbs' => 'Herbs',
    'lemon' => 'Lemon',
    'honey' => 'Honey',
    'almonds' => 'Almonds',
    'apple' => 'Apple',
    'pear' => 'Pear',
    'plum' => 'Plum',
    'quince' => 'Quince',
  ],
  default: ['honey', 'pear'],
  description: 'Set out for the order.',
  scroll: 4,
);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";
