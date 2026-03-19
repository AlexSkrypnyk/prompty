<?php

/**
 * @file
 * Playground — multiselect widget standalone.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

echo "\n--- Multiselect: basic ---\n";
$r = Prompty::multiselect('Features', options: [
  'ts' => 'TypeScript',
  'eslint' => 'ESLint',
  'prettier' => 'Prettier',
]);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Multiselect: with description ---\n";
$r = Prompty::multiselect('CI checks', options: [
  'lint' => 'Linting',
  'test' => 'Unit tests',
  'e2e' => 'E2E tests',
  'build' => 'Build verification',
], description: "Select checks to run in CI.\nSpace to toggle, enter to confirm.");
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";

echo "\n--- Multiselect: with hints ---\n";
$r = Prompty::multiselect('Integrations', options: [
  'sentry' => 'Sentry',
  'analytics' => 'Analytics',
  'cdn' => 'CDN',
], hints: [
  'sentry' => 'Error tracking and performance monitoring.',
  'analytics' => "Page views, events, and user flows.\nGDPR-compliant by default.",
  'cdn' => 'Static asset delivery via edge network.',
]);
echo '  Result: ' . ($r !== NULL ? (count($r) > 0 ? implode(', ', $r) : 'none') : 'cancelled') . "\n";
