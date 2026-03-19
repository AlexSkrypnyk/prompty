<?php

/**
 * @file
 * Playground — each widget type standalone, one at a time.
 *
 * Widgets are called directly without a flow — they handle TTY setup/teardown
 * internally in standalone mode.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Text ---\n";
$r = Prompty::text('Project name', placeholder: 'my-app', description: "Used as the directory name and the \"name\" field\nin package.json.");
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Select ---\n";
$r = Prompty::select('Framework',
  options: ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'],
  description: 'The UI layer for your project.',
  hints: [
    'react' => 'Component-based library by Meta with the largest ecosystem.',
    'vue' => "Gentle learning curve with excellent documentation.\nStrong ecosystem and single-file components.",
    'svelte' => "Compile-time framework — no virtual DOM.\nSmaller bundles, less runtime overhead.",
  ],
);
echo '  Result: ' . ($r ?? 'cancelled') . "\n";

echo "\n--- Multiselect ---\n";
$r = Prompty::multiselect('Features',
  options: ['typescript' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier', 'vitest' => 'Vitest'],
  description: "Select features to include.\nSpace to toggle, enter to confirm.",
  hints: [
    'typescript' => 'Adds type safety with static type checking.',
    'eslint' => 'Catches common code issues and enforces style rules.',
    'prettier' => "Automatic code formatting on save.\nKeeps your codebase consistent.",
    'vitest' => 'Fast unit testing framework powered by Vite.',
  ],
);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Confirm ---\n";
$r = Prompty::confirm('Install dependencies?', description: 'Runs npm install after scaffolding.');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . "\n";
