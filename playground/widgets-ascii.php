<?php

/**
 * @file
 * Playground — standalone widgets with forced ASCII (no unicode).
 *
 * Same as widgets-config.php but specifically demonstrates the difference
 * in rendering when unicode is disabled.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

// Configure before any widget calls.
Prompty::configure(unicode: FALSE);

echo "\n--- Text (ASCII mode, MYAPP_ prefix) ---\n";
$r = Prompty::text('Project name', placeholder: 'my-app');
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select (custom labels) ---\n";
$r = Prompty::select('Framework',
  options: ['next' => 'Next.js', 'nuxt' => 'Nuxt', 'sveltekit' => 'SvelteKit'],
);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Confirm (Yep/Nope labels) ---\n";
$r = Prompty::confirm('Continue?');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";
