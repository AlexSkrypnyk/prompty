<?php

/**
 * @file
 * Fixture - a standalone pause widget that prints its answer.
 *
 * Pass --enter-only to continue only on Enter.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$answer = Prompty::pause('Press any key to start the order...', enter_only: in_array('--enter-only', $argv, TRUE));

echo 'Answer: ' . var_export($answer, TRUE) . PHP_EOL;
