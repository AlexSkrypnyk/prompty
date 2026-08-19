<?php

/**
 * @file
 * Playground - where a discovered value comes from, and the forms it takes.
 *
 * A discovered value is an answer the script already has, so the widget
 * records it instead of asking. Both sources hand the script a string:
 *
 * @code
 * php playground/widget-discovered.php --extras=bread,olives
 * PROMPTY_EXTRAS=bread,olives php playground/widget-discovered.php
 * @endcode
 *
 * Run it with neither and every widget asks, which is the same script serving
 * an operator at a terminal and a scripted run with the answers to hand.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'extras:']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

/**
 * Prints a multiselect result on one line.
 *
 * @param \Closure|array<string, mixed>|list<string>|null $result
 *   What the widget returned.
 */
function show_selection(\Closure|array|null $result): void {
  $selection = is_array($result) ? array_filter($result, is_string(...)) : NULL;
  echo '  Result: ' . ($selection === NULL ? 'cancelled' : ($selection === [] ? 'none' : implode(', ', $selection))) . "\n";
}

echo "\n--- Multiselect: answered by a command-line flag ---\n";
$flag = is_string($opts['extras'] ?? NULL) ? $opts['extras'] : NULL;
echo '  Source: ' . ($flag === NULL ? 'no --extras flag, so the widget asks' : '--extras=' . $flag) . "\n";
show_selection(Prompty::multiselect('Extras', options: $extras, discovered: $flag));

echo "\n--- Multiselect: answered by an environment variable ---\n";
$env = getenv('PROMPTY_EXTRAS');
echo '  Source: ' . ($env === FALSE ? 'PROMPTY_EXTRAS unset, so the widget asks' : 'PROMPTY_EXTRAS=' . $env) . "\n";
$results = Prompty::flow(fn(): array => [
  'extras' => Prompty::multiselect('Extras', options: $extras),
]);
show_selection(is_array($results['extras'] ?? NULL) ? $results['extras'] : NULL);

echo "\n--- Multiselect: answered from a config file ---\n";
$config = json_decode('{"extras": ["bread", "herbs"]}', TRUE);
echo '  Source: a decoded list, which needs no splitting' . "\n";
show_selection(Prompty::multiselect('Extras', options: $extras, discovered: $config['extras']));

echo "\n--- Multiselect: spacing and stray commas are tidied ---\n";
show_selection(Prompty::multiselect('Extras', options: $extras, discovered: ' herbs , bread ,,'));

echo "\n--- Multiselect: a key holding a comma needs the list form ---\n";
show_selection(Prompty::multiselect('Extras', options: ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs'], discovered: ['salt,pepper']));

echo "\n--- Multiselect: one key of the answer is not an option ---\n";
try {
  Prompty::multiselect('Extras', options: $extras, discovered: 'bread,pickles');
}
catch (\InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}
