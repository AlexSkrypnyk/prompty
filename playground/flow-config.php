<?php

/**
 * @file
 * Playground — flow with pre-configured instance.
 *
 * Demonstrates calling Prompty::configure() before a flow. The flow's
 * own config parameter merges on top, so both configure() and flow()
 * config are combined.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

// Pre-configure: force ASCII and set a custom env prefix.
Prompty::configure(unicode: FALSE, env_prefix: 'SETUP_');

// The flow can add further config on top.
$results = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app'),
  'framework' => Prompty::select('Framework', options: [
    'next' => 'Next.js',
    'nuxt' => 'Nuxt',
  ]),
  'install' => Prompty::confirm('Install dependencies?'),
],
  intro: 'Project setup',
  outro: 'All done!',
  labels: ['yes' => 'Yep', 'no' => 'Nope'],
);

if ($results !== NULL) {
  echo "\nCollected:\n";
  foreach ($results as $key => $value) {
    $display = is_bool($value) ? ($value ? 'true' : 'false') : (is_array($value) ? implode(', ', $value) : $value);
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}
