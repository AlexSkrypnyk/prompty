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
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Multiselect: with description ---\n";
$r = Prompty::multiselect('Drinks',
  options: ['tea' => 'Tea', 'coffee' => 'Coffee', 'juice' => 'Juice'],
  description: "Poured at the table.\nSpace to toggle, enter to confirm.",
);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Multiselect: with hints ---\n";
$r = Prompty::multiselect('Finishes',
  options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
  hints: [
    'glazed' => 'Brushed with syrup for shine.',
    'dusted' => "Icing sugar through a fine sieve.\nDone at the last moment.",
    'piped' => 'Cream rosettes around the edge.',
  ],
);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Multiselect: opt-out (pre-checked default) ---\n";
$r = Prompty::multiselect('Extras',
  options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'],
  default: ['bread', 'olives', 'herbs'],
  description: 'All selected; uncheck to remove.',
);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";
