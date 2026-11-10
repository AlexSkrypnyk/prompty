<?php

/**
 * @file
 * Playground - a transform rewrites each widget's answer.
 *
 * The transform receives the answer and returns the value the widget answers
 * with. The completed line draws the returned value, and a discovered value
 * goes through the same transform.
 *
 * @code
 * php playground/widget-transform.php
 * php playground/widget-transform.php --dish='  PEAR tart '
 * @endcode
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'dish:']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$courses = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];
$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$dish_name = fn(string $value): string => ucwords(strtolower(trim($value)));
$with_bread = fn(array $keys): array => array_values(array_unique(['bread', ...$keys]));

/**
 * Prints a widget result on 1 line.
 *
 * @param mixed $result
 *   What the widget returned.
 */
function show(mixed $result): void {
  echo '  Result: ' . ($result === NULL ? 'cancelled' : var_export($result, TRUE)) . "\n";
}

echo "\n--- Text: the dish name is capitalized however it is typed ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart', description: 'Try typing "  PEAR tart ".', transform: $dish_name));

echo "\n--- Text: a discovered value goes through the transform ---\n";
$flag = is_string($opts['dish'] ?? NULL) ? $opts['dish'] : '  onion SOUP ';
echo '  Source: --dish=' . var_export($flag, TRUE) . "\n";
show(Prompty::text('Dish name', discovered: $flag, transform: $dish_name));

echo "\n--- Text: a built-in function is a transform ---\n";
show(Prompty::text('Kitchen note', placeholder: 'no onions', description: 'Leading and trailing spaces are dropped.', transform: trim(...)));

echo "\n--- Suggest: a typed dish is capitalized like the menu ---\n";
show(Prompty::suggest('Dish', options: ['Pear Tart', 'Apple Crumble', 'Onion Soup', 'Lentil Stew'], description: 'Try typing "  lentil STEW ".', transform: $dish_name));

echo "\n--- Select: a starter is served as a main ---\n";
show(Prompty::select('Course', options: $courses, transform: fn(string $key): string => $key === 'starter' ? 'main' : $key));

echo "\n--- Multiselect: bread is always added ---\n";
show(Prompty::multiselect('Extras', options: $extras, transform: $with_bread));

echo "\n--- Confirm: a transform that returns the wrong type ---\n";

try {
  Prompty::confirm('Send order?', discovered: TRUE, transform: fn(bool $yes): string => $yes ? 'yes' : 'no');
}
catch (\UnexpectedValueException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}
