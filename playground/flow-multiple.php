<?php

/**
 * @file
 * Playground - multiple flows in the same script.
 *
 * Demonstrates that the singleton is reused across flows. Each flow() call
 * resets the results but keeps the instance, so anything configure() set is
 * still in force. A flow's own configuration arguments are not: they last as
 * long as that flow, which is why the ASCII mode both flows here render in is
 * set with configure() rather than passed to the first of them. See
 * flow-config-scope.php for that difference on its own.
 *
 * Standalone widgets can be called between flows too.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

Prompty::configure(unicode: FALSE);

$basics = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
],
  intro: 'Step 1: The dish',
  outro: 'Dish noted.',
  cancelled: 'Dish selection cancelled.',
);

if ($basics === NULL) {
  exit(0);
}

$dish = is_string($basics['dish']) ? $basics['dish'] : '';
$course = is_string($basics['course']) ? $basics['course'] : '';
echo "\nOrder: " . $dish . ' + ' . $course . "\n\n";

$r = Prompty::text('Kitchen note', placeholder: 'no onions');
echo 'Note: ' . (is_string($r) ? $r : 'cancelled') . "\n\n";

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

$extras = is_array($options['extras']) ? $options['extras'] : [];
echo "\nExtras: " . ($extras === [] ? 'none' : implode(', ', $extras)) . "\n";
echo 'Send: ' . ($options['send'] ? 'yes' : 'no') . "\n";
