<?php

/**
 * @file
 * Fixture - a flow of 2 selects that prints its answers.
 *
 * Pass --back-key=<key> to set the flow's 'back_key'.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['back-key:']);
$back_key = isset($opts['back-key']) && is_string($opts['back-key']) ? $opts['back-key'] : NULL;

$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7']),
], back_key: $back_key);

echo 'Answer: ' . var_export($results, TRUE) . PHP_EOL;
