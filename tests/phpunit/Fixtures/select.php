<?php

/**
 * @file
 * Fixture - a standalone select widget that prints its answer.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$answer = Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']);

echo 'Answer: ' . var_export($answer, TRUE) . PHP_EOL;
