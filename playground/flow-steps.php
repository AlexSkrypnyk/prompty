<?php

/**
 * @file
 * Playground - flow steps built from the answers collected so far.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);
$interactive = !isset($opts['no-interaction']);

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', default: 'pear tart', description: 'Written on the order ticket and under "Specials" on the board.'),
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
  ),

  'method' => Prompty::step(
    fn(array $r): \Closure|array|string|null => Prompty::select('Method',
      options: $r['course'] === 'dessert' ? ['baked' => 'Baked', 'poached' => 'Poached'] : ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'],
      default: $r['course'] === 'starter' ? 'poached' : 'baked',
      description: sprintf('How the %s is cooked.', $r['dish']),
    ),
    children: [
      'temperature' => Prompty::step(
        fn(array $r): \Closure|array|string|null => Prompty::select('Temperature',
          options: ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'],
          default: $r['method'] === 'grilled' ? 'high' : 'moderate',
          description: 'Oven setting for the bake.',
        ),
        condition: fn(array $r): bool => in_array($r['method'], ['baked', 'grilled'], TRUE),
      ),
    ],
  ),

  'finishes' => Prompty::step(
    fn(array $r): \Closure|array|string|null => Prompty::multiselect('Finishes',
      options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
      default: $r['method'] === 'baked' ? ['glazed', 'dusted'] : ['piped'],
      description: 'Applied just before serving.',
    ),
    condition: fn(array $r): bool => $r['course'] === 'dessert',
  ),

  'note' => Prompty::step(
    fn(array $r): \Closure|array|string|null => Prompty::text('Kitchen note', default: sprintf('%s, %s', $r['dish'], $r['method'])),
  ),
  'send' => Prompty::step(
    fn(array $r): \Closure|array|string|null => Prompty::confirm('Send order?', description: sprintf('Passes the %s to the kitchen.', $r['dish'])),
  ),
],
  intro: 'Kitchen order',
  outro: function (array $results): void {
    Prompty::outro('Order sent to the kitchen!');
    echo PHP_EOL . 'Collected answers:' . PHP_EOL;

    foreach ($results as $key => $value) {
      $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
      echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
    }
  },
  cancelled: 'Kitchen order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'KITCHEN_',
  interactive: $interactive,
);
