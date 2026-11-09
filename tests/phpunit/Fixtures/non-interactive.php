<?php

/**
 * @file
 * Fixture - a flow and a standalone widget that answer without reading input.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

Prompty::configure(interactive: FALSE);

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'main'),
  'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'], default: ['bread']),
  'send' => Prompty::confirm('Send order?'),
]);

$note = Prompty::text('Kitchen note', default: 'no nuts');

echo 'Answers: ' . json_encode([$results, $note]) . PHP_EOL;
