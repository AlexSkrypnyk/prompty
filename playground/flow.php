<?php

/**
 * @file
 * Playground - simple linear flow, no nesting.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);

/**
 * Prints the collected answers once the flow completes.
 *
 * @param array<string, string|bool|int|array<string, string>> $results
 *   Values collected by the flow, keyed by step name.
 */
function print_order_summary(array $results): void {
  Prompty::outro('Order sent!');
  echo "\nCollected answers:\n";
  foreach ($results as $key => $value) {
    $display = is_array($value) ? (count($value) > 0 ? implode(', ', $value) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart', description: "Written on the order ticket and under\n\"Specials\" on the board."),
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
    hints: [
      'starter' => 'Served first, in a small portion.',
      'main' => 'The centre of the meal.',
      'dessert' => 'Sweet, served last.',
    ],
  ),
  'extras' => Prompty::multiselect('Extras',
    options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs', 'lemon' => 'Lemon', 'honey' => 'Honey'],
    description: "Anything served alongside.\nSpace to toggle, enter to confirm.",
    hints: [
      'bread' => 'Warm, cut at the table.',
      'olives' => 'Marinated, served in oil.',
      'herbs' => 'Picked the same morning.',
      'lemon' => 'Sharpens rich dishes.',
      'honey' => 'Runny, spooned over to finish.',
    ],
  ),
  'send' => Prompty::confirm('Send order?', description: 'Passes the order to the kitchen.'),
],
  intro: 'Compose an order',
  outro: print_order_summary(...),
  cancelled: 'Order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'PROMPTY_',
);
