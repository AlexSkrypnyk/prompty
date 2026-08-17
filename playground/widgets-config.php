<?php

/**
 * @file
 * Playground - standalone widgets with custom configuration.
 *
 * Demonstrates Prompty::configure() to set options before using widgets
 * outside of a flow. Useful when custom labels, unicode/ANSI output, or an
 * env prefix are needed without wrapping everything in a flow.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(
  labels: ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(aborted)', 'none' => 'Nothing selected'],
  unicode: !isset($opts['no-unicode']),
  ansi: !isset($opts['no-ansi']),
  env_prefix: 'KITCHEN_',
);

echo "\n--- Text: with env prefix ---\n";
$r = Prompty::text('Dish name', placeholder: 'pear tart');
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select: with custom labels ---\n";
$r = Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Confirm: with custom labels ---\n";
$r = Prompty::confirm('Send order?');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";
