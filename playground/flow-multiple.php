<?php

/**
 * @file
 * Playground — multiple flows in the same script.
 *
 * Demonstrates that the singleton is reused across flows. Each flow()
 * call resets results but preserves the instance and its configuration.
 * Standalone widgets can be called between flows too.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

// --- First flow: project basics ---
$basics = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app'),
  'framework' => Prompty::select('Framework', options: [
    'react' => 'React',
    'vue' => 'Vue',
    'svelte' => 'Svelte',
  ]),
],
  intro: 'Step 1: Project basics',
  outro: 'Basics collected.',
  cancelled: 'Setup cancelled.',
  unicode: FALSE,
);

if ($basics === NULL) {
  exit(0);
}

echo "\nBasics: " . $basics['name'] . ' + ' . $basics['framework'] . "\n\n";

// --- Standalone widget between flows ---
$extra = Prompty::text('Add a tagline?', placeholder: 'A cool project');
echo 'Tagline: ' . ($extra ?? 'skipped') . "\n\n";

// --- Second flow: features and options ---
$options = Prompty::flow(fn(): array => [
  'features' => Prompty::multiselect('Features', options: [
    'ts' => 'TypeScript',
    'eslint' => 'ESLint',
    'prettier' => 'Prettier',
  ]),
  'install' => Prompty::confirm('Install dependencies?'),
],
  intro: 'Step 2: Features',
  outro: 'All done!',
  cancelled: 'Feature selection cancelled.',
);

if ($options === NULL) {
  exit(0);
}

echo "\nFeatures: " . (count($options['features']) > 0 ? implode(', ', $options['features']) : 'none') . "\n";
echo 'Install: ' . ($options['install'] ? 'yes' : 'no') . "\n";
