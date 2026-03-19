<?php

/**
 * @file
 * Playground — standalone widgets with custom configuration.
 *
 * Demonstrates Prompty::configure() to set options before using widgets
 * outside of a flow. This is useful when you want custom symbols, colors,
 * env prefix, or truthy/falsy values without wrapping everything in a flow.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

// Configure before any widget calls.
$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(
  labels: ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(aborted)', 'none' => 'Nothing selected'],
  unicode: !isset($opts['no-unicode']),
  ansi: !isset($opts['no-ansi']),
  env_prefix: 'MYAPP_',
);

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
