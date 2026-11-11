<?php

/**
 * @file
 * Playground - long option lists, and the key legend under each widget.
 *
 * @code
 * php playground/07-display.php
 * php playground/07-display.php --no-unicode
 * @endcode
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

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

echo "\n--- Select: long list ---\n";
show(Prompty::select('Menu',
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
));

echo "\n--- Multiselect: long list ---\n";
show(Prompty::multiselect('Pantry',
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
));

Prompty::configure(legend: TRUE);

echo "\n--- Legend: text ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart'));

echo "\n--- Legend: select ---\n";
show(Prompty::select('Course', options: $courses));

echo "\n--- Legend: multiselect ---\n";
show(Prompty::multiselect('Extras', options: $extras));

echo "\n--- Legend: suggest ---\n";
show(Prompty::suggest('Dish', options: $dishes));

echo "\n--- Legend: confirm, following the help ---\n";
show(Prompty::confirm('Fire the mains?', description: 'The kitchen starts cooking at once.', help: 'Make sure the table is ready.'));

echo "\n--- Legend: pause ---\n";
show(Prompty::pause('Press any key to start the order...'));

echo "\n--- Legend: a flow rewording it, with 1 key removed ---\n";
show(Prompty::flow()
  ->suggest('dish', 'Dish', options: $dishes)
  ->confirm('send', 'Send order?')
  ->run(labels: ['legend_enter' => 'enter to send', 'legend_type' => '']));
