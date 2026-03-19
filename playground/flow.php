<?php

/**
 * @file
 * Playground — simple linear flow, no nesting.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);

$results = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app', description: "Used as the directory name and the \"name\" field\nin package.json."),
  'framework' => Prompty::select('Framework',
    options: ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte', 'astro' => 'Astro'],
    description: 'The UI layer for your project.',
    hints: [
      'react' => 'Component-based library by Meta with the largest ecosystem.',
      'vue' => "Gentle learning curve with excellent documentation.\nStrong ecosystem and single-file components.",
      'svelte' => "Compile-time framework — no virtual DOM.\nSmaller bundles, less runtime overhead.",
      'astro' => "Content-first framework for static sites.\nShips zero JavaScript by default.",
    ],
  ),
  'features' => Prompty::multiselect('Features',
    options: ['typescript' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier', 'vitest' => 'Vitest', 'tailwind' => 'Tailwind CSS'],
    description: "Select features to include.\nSpace to toggle, enter to confirm.",
    hints: [
      'typescript' => 'Adds type safety with static type checking.',
      'eslint' => 'Catches common code issues and enforces style rules.',
      'prettier' => "Automatic code formatting on save.\nKeeps your codebase consistent.",
      'vitest' => 'Fast unit testing framework powered by Vite.',
      'tailwind' => "Utility-first CSS framework.\nBuild designs directly in your markup.",
    ],
  ),
  'install' => Prompty::confirm('Install dependencies?', description: 'Runs npm install after scaffolding.'),
],
  intro: 'Create your project',
  outro: function (array $results): void {
    Prompty::outro("You're all set!");
    echo "\nCollected answers:\n";
    foreach ($results as $key => $value) {
      $display = is_array($value) ? (count($value) > 0 ? implode(', ', $value) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
      echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
    }
  },
  cancelled: 'Cancelled.',
  numbering: TRUE,
  env_prefix: 'PROMPTY_',
  unicode: $unicode,
  ansi: $ansi,
);
