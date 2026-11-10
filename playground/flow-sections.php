<?php

/**
 * @file
 * Playground - flow steps grouped under section headings.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);
$interactive = !isset($opts['no-interaction']);

$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$extras_dessert = ['honey' => 'Honey', 'almonds' => 'Almonds'];

$results = Prompty::flow(fn(): array => [
  Prompty::section('The dish', description: 'Name the dish and place it in the meal.'),
  'dish' => Prompty::text('Dish name', default: 'pear tart', description: 'Written on the order ticket and under "Specials" on the board.'),
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    default: 'dessert',
    description: 'Where the dish sits in the meal.',
  ),

  // The heading is drawn only when a step in its group renders.
  Prompty::section('Finishing', description: "Applied just before serving.\nOnly asked for desserts."),
  'finishes' => Prompty::multiselect('Finishes',
    options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
    default: ['dusted'],
    condition: fn(array $r): bool => $r['course'] === 'dessert',
  ),

  Prompty::section("Service\nTable and extras", description: 'Where the order is going and what is served alongside.'),
  'table' => Prompty::select('Table',
    options: ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'],
    default: '7',
    description: 'Where the order is going.',
  ),
  'extras' => Prompty::step(
    fn(array $r): mixed => Prompty::multiselect('Extras',
      options: $r['course'] === 'dessert' ? $extras_dessert : $extras,
      description: sprintf('Served alongside the %s.', $r['dish']),
    ),
  ),

  Prompty::section('Ticket'),
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
  numbering: 'total',
  unicode: $unicode,
  ansi: $ansi,
  interactive: $interactive,
);
