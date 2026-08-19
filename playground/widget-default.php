<?php

/**
 * @file
 * Playground - defaults and the option keys they are held to.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$courses = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];
$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

echo "\n--- Select: default focuses an option ---\n";
$r = Prompty::select('Course', options: $courses, default: 'dessert', description: 'Dessert is focused before you touch anything.');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: empty default focuses the first option ---\n";
$r = Prompty::select('Course', options: $courses, default: '', description: 'An empty default means no default.');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Multiselect: defaults pre-check options ---\n";
$r = Prompty::multiselect('Extras', options: $extras, default: ['bread', 'herbs'], description: 'Uncheck what the table does not want.');
$selection = is_array($r) ? array_filter($r, is_string(...)) : NULL;
echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";

echo "\n--- Select: default outside the options ---\n";
try {
  Prompty::select('Course', options: $courses, default: 'pudding');
}
catch (InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}

echo "\n--- Multiselect: one default outside the options ---\n";
try {
  Prompty::multiselect('Extras', options: $extras, default: ['bread', 'pickles']);
}
catch (InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}
