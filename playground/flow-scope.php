<?php

/**
 * @file
 * Playground - several flows, and how long a flow's own configuration lasts.
 *
 * The shared instance is reused across flows. Each flow() call resets the
 * collected results but keeps the instance, so anything configure() set is
 * still in force for the next flow, and a standalone widget can run between
 * flows. A flow's own configuration arguments are scoped to that call, the
 * way intro, outro and numbering are: they are not a shorthand for
 * configure().
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
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

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

// The instance serves standalone widgets between flows too.
$r = Prompty::text('Kitchen note', placeholder: 'no onions');
echo 'Note: ' . (is_string($r) ? $r : 'cancelled') . PHP_EOL;

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

// Each flow returns only its own steps, so the order is assembled from the
// two result arrays.
$dish = is_string($first['dish']) ? $first['dish'] : '';
$course = is_string($second['course']) ? $second['course'] : '';
echo PHP_EOL . 'Order: ' . $dish . ' + ' . $course . PHP_EOL;

// configure() writes to the singleton, so the change persists.
Prompty::configure(labels: ['yes' => 'Aye', 'no' => 'Nay']);

report('After configure(), the change stays:');

$third = Prompty::flow(fn(): array => [
  'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs']),
  'send' => Prompty::confirm('Send order?'),
],
  intro: 'A flow after configure()',
  outro: 'Order sent!',
  cancelled: 'Cancelled.',
);

if ($third === NULL) {
  exit(0);
}

$extras = is_array($third['extras']) ? array_filter($third['extras'], is_string(...)) : [];
echo PHP_EOL . 'Extras: ' . ($extras === [] ? 'none' : implode(', ', $extras)) . PHP_EOL;
echo 'Send: ' . ($third['send'] ? 'yes' : 'no') . PHP_EOL;

report('And it is still in force after that flow too:');
