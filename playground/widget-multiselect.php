<?php

/**
 * @file
 * Playground - multiselect widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Multiselect: basic ---\n";
$r = Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs']);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";

echo "\n--- Multiselect: with description ---\n";
$r = Prompty::multiselect('Drinks',
  options: ['tea' => 'Tea', 'coffee' => 'Coffee', 'juice' => 'Juice'],
  description: "Poured at the table.\nSpace to toggle, enter to confirm.",
);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";

echo "\n--- Multiselect: with hints ---\n";
$r = Prompty::multiselect('Finishes',
  options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
  hints: [
    'glazed' => 'Brushed with syrup for shine.',
    'dusted' => "Icing sugar through a fine sieve.\nDone at the last moment.",
    'piped' => 'Cream rosettes around the edge.',
  ],
);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";

echo "\n--- Multiselect: opt-out (pre-checked default) ---\n";
$r = Prompty::multiselect('Extras',
  options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'],
  default: ['bread', 'olives', 'herbs'],
  description: 'All selected; uncheck to remove.',
);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";

echo "\n--- Multiselect: numeric option keys ---\n";
// The default is written with ints to show that they pre-check the same
// options that string keys would.
$r = Prompty::multiselect('Seats',
  options: ['1' => 'Seat 1', '2' => 'Seat 2', '3' => 'Seat 3', '4' => 'Seat 4'],
  default: [2, 4],
  description: 'Who at the table is having this dish.',
);
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : var_export($selection, TRUE))) . "\n";
