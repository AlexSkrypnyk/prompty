<?php

/**
 * @file
 * Playground - multiple flows in the same script.
 *
 * Demonstrates that the singleton is reused across flows. Each flow()
 * call resets results but preserves the instance and its configuration.
 *
 * Standalone widgets can be called between flows too.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$basics = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
],
  intro: 'Step 1: The dish',
  outro: 'Dish noted.',
  cancelled: 'Dish selection cancelled.',
  unicode: FALSE,
);

if ($basics === NULL) {
  exit(0);
}

echo "\nOrder: " . $basics['dish'] . ' + ' . $basics['course'] . "\n\n";

$extra = Prompty::text('Kitchen note', placeholder: 'no onions');
echo 'Note: ' . ($extra ?? 'cancelled') . "\n\n";

$options = Prompty::flow(fn(): array => [
  'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs']),
  'send' => Prompty::confirm('Send order?'),
],
  intro: 'Step 2: Extras',
  outro: 'Order sent!',
  cancelled: 'Extras selection cancelled.',
);

if ($options === NULL) {
  exit(0);
}

echo "\nExtras: " . (count($options['extras']) > 0 ? implode(', ', $options['extras']) : 'none') . "\n";
echo 'Send: ' . ($options['send'] ? 'yes' : 'no') . "\n";
