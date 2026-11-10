<?php

/**
 * @file
 * Playground - pause widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']), interactive: !isset($opts['no-interaction']));

echo "\n--- Pause: any key ---\n";
$r = Prompty::pause('Press any key to start the order...');
echo '  Result: ' . ($r !== NULL ? 'yes' : 'cancelled') . "\n";

echo "\n--- Pause: with description and help ---\n";
$r = Prompty::pause('Press any key to start the order...', description: 'The kitchen opens at noon.', help: 'Escape to leave.');
echo '  Result: ' . ($r !== NULL ? 'yes' : 'cancelled') . "\n";

echo "\n--- Pause: Enter only ---\n";
$r = Prompty::pause('Press Enter to send the order to the kitchen', enter_only: TRUE);
echo '  Result: ' . ($r !== NULL ? 'yes' : 'cancelled') . "\n";

echo "\n--- Pause: erased after the key ---\n";
$r = Prompty::pause('Press any key to start the order...', erase: TRUE);
echo '  Result: ' . ($r !== NULL ? 'yes' : 'cancelled') . "\n";
