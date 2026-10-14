<?php

/**
 * @file
 * Playground - the flow the embedder is tested against.
 *
 * Like every other demo here, it loads the class with require, and the
 * embedder builds a copy that carries the class inline and requires nothing.
 *
 * CONTRIBUTING.md, under "The embedded demo", covers how the copy is built
 * and compared with this file.
 *
 * The embedder replaces everything between the markers below with a minified
 * copy of the Prompty class and copies everything outside them unchanged, so
 * both files share this docblock.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

// phpcs:disable
// @embed-start
// The import sits inside the markers together with the require. The embedded
// class is emitted without a namespace, so both lines must be removed.
require_once __DIR__ . '/../Prompty.php';
use AlexSkrypnyk\Prompty\Prompty;
// @embed-end
// phpcs:enable

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
  'send' => Prompty::confirm('Send order?', description: 'Passes the order to the kitchen.'),
],
  intro: 'Compose an order',
  outro: 'Order sent!',
  cancelled: 'Order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'PROMPTY_',
);

if ($results === NULL) {
  exit(0);
}

// Without a kill switch, embed.php injects one directly after the marker
// region, ahead of the flow. Declared here, it gates only the work below.
if (!getenv('SHOULD_PROCEED')) {
  echo PHP_EOL . 'Stopped at the kill switch. Set SHOULD_PROCEED=1 to run the work below it.' . PHP_EOL;

  return;
}

$dish = is_string($results['dish']) ? $results['dish'] : '';
$course = is_string($results['course']) ? $results['course'] : '';
$extras = is_array($results['extras']) ? array_filter($results['extras'], is_string(...)) : [];

echo PHP_EOL . 'Dish: ' . $dish . PHP_EOL;
echo 'Course: ' . $course . PHP_EOL;
echo 'Extras: ' . ($extras === [] ? 'none' : implode(', ', $extras)) . PHP_EOL;
echo 'Send: ' . ($results['send'] ? 'yes' : 'no') . PHP_EOL;
