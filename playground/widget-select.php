<?php

/**
 * @file
 * Playground — select widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

echo "\n--- Select: basic ---\n";
$r = Prompty::select('Framework', options: [
  'react' => 'React',
  'vue' => 'Vue',
  'svelte' => 'Svelte',
]);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select: with description ---\n";
$r = Prompty::select('Package manager', options: [
  'npm' => 'npm',
  'yarn' => 'Yarn',
  'pnpm' => 'pnpm',
  'bun' => 'Bun',
], description: 'Used for installing dependencies.');
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select: with hints ---\n";
$r = Prompty::select('License', options: [
  'mit' => 'MIT',
  'apache2' => 'Apache 2.0',
  'gpl3' => 'GPL 3.0',
], hints: [
  'mit' => 'Permissive. Do whatever you want.',
  'apache2' => "Permissive with patent protection.\nGood for enterprise use.",
  'gpl3' => "Copyleft. Derivative works must also be GPL.\nStrong open-source guarantee.",
]);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";
