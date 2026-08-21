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
  'table' => Prompty::select('Table',
    options: ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'],
    default: '7',
    description: 'Where the order is going.',
    hints: [
      '4' => 'By the window, seats two.',
      '7' => 'The long one near the pass.',
      '12' => 'Corner booth, seats six.',
    ],
  ),
  // The default is written with ints to show that they pre-check the same
  // options string keys would.
  'seats' => Prompty::multiselect('Seats',
    options: ['1' => 'Seat 1', '2' => 'Seat 2', '3' => 'Seat 3', '4' => 'Seat 4'],
    default: [2, 4],
    description: 'Who at the table is having this dish.',
  ),
  'send' => Prompty::confirm('Send order?', description: 'Passes the order to the kitchen.'),
],
  intro: 'Compose an order',
  outro: function (array $results): void {
    Prompty::outro('Order sent!');
    echo PHP_EOL . 'Collected answers:' . PHP_EOL;
    foreach ($results as $key => $value) {
      $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
      echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
    }
  },
  cancelled: 'Order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'PROMPTY_',
);
