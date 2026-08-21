<?php

/**
 * @file
 * Playground - flow with pre-configured instance.
 *
 * Demonstrates calling Prompty::configure() before a flow. The flow's own
 * configuration arguments merge on top, so both configure() and flow()
 * configuration are combined.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

Prompty::configure(unicode: FALSE, env_prefix: 'ORDER_');

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'send' => Prompty::confirm('Send order?'),
],
  intro: 'Order setup',
  outro: 'All done!',
  cancelled: 'Order setup cancelled.',
  labels: ['yes' => 'Yep', 'no' => 'Nope'],
);

if ($results === NULL) {
  exit(0);
}

echo PHP_EOL . 'Collected answers:' . PHP_EOL;
foreach ($results as $key => $value) {
  $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
  echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
}
