<?php

/**
 * @file
 * Playground - the key legend each widget draws while it waits for input.
 *
 * @code
 * php playground/widget-legend.php
 * php playground/widget-legend.php --no-unicode
 * @endcode
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']), legend: TRUE);

$courses = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];
$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$dishes = ['Pear Tart', 'Apple Crumble', 'Onion Soup', 'Lentil Stew'];

/**
 * Prints a widget result on 1 line.
 *
 * @param mixed $result
 *   What the widget returned.
 */
function show(mixed $result): void {
  echo '  Result: ' . ($result === NULL ? 'cancelled' : var_export($result, TRUE)) . "\n";
}

echo "\n--- Text ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart'));

echo "\n--- Select ---\n";
show(Prompty::select('Course', options: $courses));

echo "\n--- Multiselect ---\n";
show(Prompty::multiselect('Extras', options: $extras));

echo "\n--- Suggest ---\n";
show(Prompty::suggest('Dish', options: $dishes));

echo "\n--- Confirm: the legend follows the help ---\n";
show(Prompty::confirm('Fire the mains?', description: 'The kitchen starts cooking at once.', help: 'Make sure the table is ready.'));

echo "\n--- Flow: the legend reworded, with 1 key removed ---\n";
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::suggest('Dish', options: $dishes),
  'send' => Prompty::confirm('Send order?'),
], labels: ['legend_enter' => 'enter to send', 'legend_type' => '']);
show($results);
