<?php

/**
 * @file
 * Playground - flow() configuration scope.
 *
 * The configuration arguments on flow() are scoped to that call, the way
 * intro, outro and numbering are. They are not a shorthand for configure().
 *
 * Three flows run here:
 *
 * 1. One that passes its own spacing, labels and environment prefix.
 * 2. One that passes none, and renders with the configuration in force
 *    before the first flow rather than the one that flow set.
 * 3. One that runs after configure(), which is how configuration is changed
 *    for the rest of the process.
 *
 * The configuration is printed between the flows, so the difference is
 * readable without comparing glyphs.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

/**
 * Print the configuration values these flows change.
 *
 * @param string $when
 *   What has happened by this point.
 */
function report(string $when): void {
  $config = Prompty::config();
  $spacing = is_array($config['spacing'] ?? NULL) ? $config['spacing'] : [];
  $labels = is_array($config['labels'] ?? NULL) ? $config['labels'] : [];

  $indent = is_string($spacing['indent'] ?? NULL) ? $spacing['indent'] : '';
  $yes = is_string($labels['yes'] ?? NULL) ? $labels['yes'] : '';
  $prefix = is_string($config['env_prefix'] ?? NULL) ? $config['env_prefix'] : '';

  echo PHP_EOL . $when . PHP_EOL;
  echo '  indent:     "' . $indent . '"' . PHP_EOL;
  echo '  yes label:  "' . $yes . '"' . PHP_EOL;
  echo '  env prefix: "' . $prefix . '"' . PHP_EOL . PHP_EOL;
}

report('Before any flow ran:');

// While this flow runs, answers come from KITCHEN_DISH, not PROMPTY_DISH.
$first = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
],
  intro: 'A flow with its own configuration',
  outro: 'Dish noted.',
  cancelled: 'Cancelled.',
  spacing: ['indent' => '....'],
  labels: ['yes' => 'Aye', 'no' => 'Nay'],
  env_prefix: 'KITCHEN_',
);

if ($first === NULL) {
  exit(0);
}

report('After that flow returned, its configuration is gone:');

// This flow passes no configuration, so it renders with the configuration
// in force before the first flow, not the one that flow set.
$second = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
],
  intro: 'A flow that asks for nothing',
  outro: 'Course noted.',
  cancelled: 'Cancelled.',
);

if ($second === NULL) {
  exit(0);
}

// configure() writes to the singleton, so the change persists.
Prompty::configure(labels: ['yes' => 'Aye', 'no' => 'Nay']);

report('After configure(), the change stays:');

$third = Prompty::flow(fn(): array => [
  'send' => Prompty::confirm('Send order?'),
],
  intro: 'A flow after configure()',
  outro: 'Order sent!',
  cancelled: 'Cancelled.',
);

if ($third === NULL) {
  exit(0);
}

report('And it is still in force after that flow too:');
