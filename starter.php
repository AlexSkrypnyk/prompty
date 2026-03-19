<?php

/**
 * @file
 * Starter script — example consumer of Prompty.
 *
 * Demonstrates the recommended pattern for scripts that use Prompty::flow()
 * and need to be testable with PromptyTestTrait.
 *
 * The kill switch pattern:
 *   1. Run the flow to collect user answers.
 *   2. Check an environment variable (e.g., SHOULD_PROCEED).
 *   3. If not set, exit early — this is what happens during testing.
 *   4. If set, proceed with real work (file creation, installs, etc.).
 */

declare(strict_types=1);

// phpcs:disable
// @embed-start
// Run `php embed.php starter.php` to embed Prompty here.
// Use `php embed.php --compact starter.php` for a smaller output.
require_once __DIR__ . '/Prompty.php';
// @embed-end
// phpcs:enable

use AlexSkrypnyk\Prompty\Prompty;

$results = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app'),
  'framework' => Prompty::select('Framework', options: [
    'react' => 'React',
    'vue' => 'Vue',
    'svelte' => 'Svelte',
  ]),
  'features' => Prompty::multiselect('Features', options: [
    'ts' => 'TypeScript',
    'eslint' => 'ESLint',
    'prettier' => 'Prettier',
  ]),
  'install' => Prompty::confirm('Install dependencies?'),
], intro: 'Create a new project', outro: 'Project created!');

// Kill switch — stop here when running under tests.
// In production, set SHOULD_PROCEED=1 to continue past this point.
if (!getenv('SHOULD_PROCEED')) {
  return;
}

// === Real work below — only runs when SHOULD_PROCEED=1 ===
/** @var array{name: string, framework: string, features: list<string>, install: bool}|null $results */
echo 'Creating project: ' . ($results['name'] ?? '') . PHP_EOL;
echo 'Framework: ' . ($results['framework'] ?? '') . PHP_EOL;
echo 'Features: ' . implode(', ', $results['features'] ?? []) . PHP_EOL;
echo 'Install: ' . (($results['install'] ?? FALSE) ? 'yes' : 'no') . PHP_EOL;
