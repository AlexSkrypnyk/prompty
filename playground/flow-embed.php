<?php

/**
 * @file
 * Playground - this is how embedding is tested.
 *
 * Prompty is meant to be copied into a consumer script, so the thing that has
 * to keep working is the copy. This file is the script that proves it: it
 * loads the class with require, like every other demo here, and the embedder
 * rewrites it into a copy that carries the class inline and requires nothing.
 *
 * Build that copy by running the embedder with an output path, so the source
 * is copied there and the copy is rewritten:
 *
 *   php embed.php playground/flow-embed.php playground/flow-embed.dist.php
 *
 * Then run both and compare what they draw. They render the same flow, so
 * anything that differs between them is a fault in the embedder rather than
 * in the flow:
 *
 *   php playground/flow-embed.php
 *   php playground/flow-embed.dist.php
 *
 * Two things do that comparison without being asked. `composer lint` runs
 * .util/check-embed.php, which builds the copy and fails if the two print
 * anything different. And .util/assets/flow-embed.svg records the embedded
 * copy being driven through its prompts by the expect script the recorder
 * generates for it, kept out of the README on purpose: it is there so that a
 * change in what the embedded script draws shows up as a diff, in the frames
 * rather than only in the text.
 *
 * flow-embed.dist.php is generated and is not committed. Build it whenever
 * it is wanted; nothing depends on a copy of it being in the repository.
 *
 * The embedder replaces everything between the markers below with a minified
 * copy of the Prompty class and copies everything outside them unchanged, so
 * both files share this docblock.
 *
 * Running the embedder again on an embedded script replaces the marker region
 * with the current class and preserves everything outside it. That second run
 * updates an embedded script to a newer version of Prompty.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

// phpcs:disable
// @embed-start
// The import sits inside the markers together with the require. The embedded
// class is emitted without a namespace, so both lines have to go.
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
