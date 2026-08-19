<?php

/**
 * @file
 * Playground - the forms a discovered value can take.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

/**
 * Prints a multiselect result on one line.
 *
 * @param \Closure|array<string, mixed>|list<string>|null $result
 *   What the widget returned.
 */
function show(\Closure|array|null $result): void {
  $selection = is_array($result) ? array_filter($result, is_string(...)) : NULL;
  echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";
}

echo "\n--- Multiselect: discovered as a comma-separated string ---\n";
show(Prompty::multiselect('Extras', options: $extras, discovered: 'bread,olives'));

echo "\n--- Multiselect: discovered as a list ---\n";
show(Prompty::multiselect('Extras', options: $extras, discovered: ['bread', 'olives']));

echo "\n--- Multiselect: the same string through the environment ---\n";
putenv('PROMPTY_EXTRAS=bread,olives');
$results = Prompty::flow(fn(): array => [
  'extras' => Prompty::multiselect('Extras', options: $extras),
]);
putenv('PROMPTY_EXTRAS');
echo '  Result: ' . (is_array($results['extras'] ?? NULL) ? implode(', ', array_filter($results['extras'], is_string(...))) : 'cancelled') . "\n";

echo "\n--- Multiselect: spacing and stray commas are tidied ---\n";
show(Prompty::multiselect('Extras', options: $extras, discovered: ' herbs , bread ,,'));

echo "\n--- Multiselect: a key holding a comma needs the list form ---\n";
show(Prompty::multiselect('Extras', options: ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs'], discovered: ['salt,pepper']));

echo "\n--- Multiselect: one key of the list is not an option ---\n";
try {
  Prompty::multiselect('Extras', options: $extras, discovered: 'bread,pickles');
}
catch (InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}
