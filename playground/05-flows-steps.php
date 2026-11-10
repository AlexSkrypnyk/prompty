<?php

/**
 * @file
 * Playground - flow steps built from earlier answers, and section headings.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);
$interactive = !isset($opts['no-interaction']);

/**
 * Prints the answers a flow collected.
 *
 * @param array<string, mixed> $results
 *   The flow results.
 */
function summarize(array $results): void {
  echo PHP_EOL . 'Collected answers:' . PHP_EOL;

  foreach ($results as $key => $value) {
    $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}

echo "\n--- Flow: steps built from earlier answers ---\n";

$methods = ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'];
$methods_dessert = ['baked' => 'Baked', 'poached' => 'Poached'];

Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', default: 'pear tart', description: 'Written on the order ticket and under "Specials" on the board.'),
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
  ),

  'method' => Prompty::step(
    fn(array $r): mixed => Prompty::select('Method',
      options: $r['course'] === 'dessert' ? $methods_dessert : $methods,
      default: $r['course'] === 'starter' ? 'poached' : 'baked',
      description: sprintf('How the %s is cooked.', $r['dish']),
    ),
    children: [
      'temperature' => Prompty::step(
        fn(array $r): mixed => Prompty::select('Temperature',
          options: ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'],
          default: $r['method'] === 'grilled' ? 'high' : 'moderate',
          description: 'Oven setting for the bake.',
        ),
        condition: fn(array $r): bool => in_array($r['method'], ['baked', 'grilled'], TRUE),
      ),
    ],
  ),

  'finishes' => Prompty::step(
    fn(array $r): mixed => Prompty::multiselect('Finishes',
      options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
      default: $r['method'] === 'baked' ? ['glazed', 'dusted'] : ['piped'],
      description: 'Applied just before serving.',
    ),
    condition: fn(array $r): bool => $r['course'] === 'dessert',
  ),

  'note' => Prompty::step(
    fn(array $r): mixed => Prompty::text('Kitchen note', default: sprintf('%s, %s', $r['dish'], $r['method'])),
  ),
  'send' => Prompty::step(
    fn(array $r): mixed => Prompty::confirm('Send order?', description: sprintf('Passes the %s to the kitchen.', $r['dish'])),
  ),
],
  intro: 'Kitchen order',
  outro: function (array $results): void {
    Prompty::outro('Order sent to the kitchen!');
    summarize($results);
  },
  cancelled: 'Kitchen order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'KITCHEN_',
  interactive: $interactive,
);

echo "\n--- Flow: steps grouped under section headings ---\n";

$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$extras_dessert = ['honey' => 'Honey', 'almonds' => 'Almonds'];

Prompty::flow(fn(): array => [
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
    summarize($results);
  },
  cancelled: 'Order cancelled.',
  numbering: 'total',
  unicode: $unicode,
  ansi: $ansi,
  interactive: $interactive,
);
