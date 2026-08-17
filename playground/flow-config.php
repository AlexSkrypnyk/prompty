<?php

/**
 * @file
 * Playground - flow with pre-configured instance.
 *
 * Demonstrates calling Prompty::configure() before a flow. The flow's
 * own config parameter merges on top, so both configure() and flow()
 * config are combined.
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

if ($results !== NULL) {
  echo "\nCollected answers:\n";
  foreach ($results as $key => $value) {
    $display = is_bool($value) ? ($value ? 'yes' : 'no') : (is_array($value) ? implode(', ', $value) : $value);
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}
