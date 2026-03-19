<?php

/**
 * @file
 * Playground — confirm widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Confirm: default yes ---\n";
$r = Prompty::confirm('Install dependencies?');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";

echo "\n--- Confirm: default no ---\n";
$r = Prompty::confirm('Enable telemetry?', default: FALSE);
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";

echo "\n--- Confirm: with description ---\n";
$r = Prompty::confirm('Run migrations?', description: "This will alter database tables.\nMake sure you have a backup.");
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";
