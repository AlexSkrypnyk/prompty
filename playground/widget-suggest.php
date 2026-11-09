<?php

/**
 * @file
 * Playground - suggest widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$dishes = ['Pear Tart', 'Apple Crumble', 'Onion Soup', 'Lentil Stew', 'Herb Omelette', 'Barley Salad', 'Plum Compote', 'Walnut Loaf'];

echo "\n--- Suggest: from a list ---\n";
$r = Prompty::suggest('Dish',
  options: $dishes,
  placeholder: 'Pear Tart',
  description: 'Pick from the menu, or type a dish that is not on it.',
  scroll: 4,
  help: 'Up and down to pick, enter to confirm.',
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Suggest: from a closure ---\n";
$r = Prompty::suggest('Dish',
  options: fn(string $typed): array => array_values(array_filter($dishes, fn(string $dish): bool => stripos($dish, $typed) === 0)),
  description: 'Only the dishes that start with the typed text.',
);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";
