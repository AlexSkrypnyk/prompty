<?php

/**
 * @file
 * Fixture - a flow of 2 selects that prints its answers.
 *
 * FIXTURE_BACK_KEY, when set, is the flow's 'back_key'.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7']),
], back_key: getenv('FIXTURE_BACK_KEY') ?: NULL);

echo 'Answer: ' . var_export($results, TRUE) . PHP_EOL;
