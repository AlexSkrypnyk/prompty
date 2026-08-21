<?php

/**
 * @file
 * Playground - where a widget's answer comes from, and what it is held to.
 *
 * An answer can be seeded as well as typed. A default is where the widget
 * starts. A discovered value is an answer the script already has, so the
 * widget records it instead of asking.
 *
 * An empty or whitespace answer falls back to the default, then the
 * placeholder. Each source is held to the widget's options: a default or a
 * discovered key outside them is rejected.
 *
 * The discovered sections take their answers from a flag or a variable, and
 * either can be empty when the command that produced it returned nothing:
 *
 * @code
 * php playground/widget-values.php --extras=bread,olives
 * php playground/widget-values.php --dish='onion soup'
 * php playground/widget-values.php --dish=
 * PROMPTY_EXTRAS=bread,olives PROMPTY_DISH= php playground/widget-values.php
 * @endcode
 *
 * Without either source, those widgets ask, so one script serves an
 * operator at a terminal and a scripted run that already has the answers.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'extras:', 'dish:']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$courses = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];
$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

/**
 * Prints a text result on one line.
 *
 * @param \Closure|array<string, mixed>|string|null $result
 *   What the widget returned.
 */
function show_text(\Closure|array|string|null $result): void {
  echo '  Result: ' . (is_string($result) ? var_export($result, TRUE) : 'cancelled') . "\n";
}

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

echo "\n--- Select: default focuses an option ---\n";
$r = Prompty::select('Course', options: $courses, default: 'dessert', description: 'Dessert is focused before you touch anything.');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: empty default focuses the first option ---\n";
$r = Prompty::select('Course', options: $courses, default: '', description: 'An empty default means no default.');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Multiselect: defaults pre-check options ---\n";
show_selection(Prompty::multiselect('Extras', options: $extras, default: ['bread', 'herbs'], description: 'Uncheck what the table does not want.'));

echo "\n--- Select: default outside the options ---\n";
try {
  Prompty::select('Course', options: $courses, default: 'pudding');
}
catch (\InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}

echo "\n--- Multiselect: one default outside the options ---\n";
try {
  Prompty::multiselect('Extras', options: $extras, default: ['bread', 'pickles']);
}
catch (\InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
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

echo "\n--- Text: answered by a command-line flag ---\n";
$flag = is_string($opts['dish'] ?? NULL) ? $opts['dish'] : NULL;
echo '  Source: ' . ($flag === NULL ? 'no --dish flag, so the widget asks' : '--dish=' . var_export($flag, TRUE)) . "\n";
show_text(Prompty::text('Dish name', placeholder: 'pear tart', discovered: $flag));

echo "\n--- Text: an empty answer takes the placeholder ---\n";
show_text(Prompty::text('Dish name', placeholder: 'pear tart', discovered: ''));

echo "\n--- Text: whitespace is an empty answer too ---\n";
show_text(Prompty::text('Dish name', placeholder: 'pear tart', discovered: '   '));

echo "\n--- Text: an empty answer prefers a seeded default ---\n";
show_text(Prompty::text('Dish name', default: 'plum compote', placeholder: 'pear tart', discovered: ''));

echo "\n--- Text: an answer is trimmed ---\n";
show_text(Prompty::text('Dish name', placeholder: 'pear tart', discovered: '  onion soup  '));

echo "\n--- Text: an empty environment variable is an empty answer ---\n";
$env = getenv('PROMPTY_DISH');
echo '  Source: ' . ($env === FALSE ? 'PROMPTY_DISH unset, so the widget asks' : 'PROMPTY_DISH=' . var_export($env, TRUE)) . "\n";
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
]);
show_text(is_array($results) && is_string($results['dish'] ?? NULL) ? $results['dish'] : NULL);
