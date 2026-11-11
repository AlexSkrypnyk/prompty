<?php

/**
 * @file
 * Fixture - a flow and a standalone widget that answer without reading input.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

Prompty::configure(interactive: FALSE);

$results = Prompty::flow()
  ->text('dish', 'Dish name', placeholder: 'pear tart')
  ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'main')
  ->multiselect('extras', 'Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'], default: ['bread'])
  ->confirm('send', 'Send order?')
  ->run();

$note = Prompty::text('Kitchen note', default: 'no nuts');

echo 'Answers: ' . json_encode([$results, $note]) . PHP_EOL;
