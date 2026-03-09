<?php

/**
 * @file
 * Playground — text widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

echo "\n--- Text: basic ---\n";
$r = Prompty::text('Project name', placeholder: 'my-app');
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Text: with description ---\n";
$r = Prompty::text('Author name', placeholder: 'Jane Doe', description: "Used in package.json and LICENSE.\nLeave blank for default.");
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Text: no placeholder ---\n";
$r = Prompty::text('Git remote URL');
echo '  Result: ' . ($r ?? 'cancelled') . "\n";
