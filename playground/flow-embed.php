<?php

/**
 * @file
 * Playground - the flow from flow.php, before and after embedding.
 *
 * Two files hold this demo, and both run:
 *
 * - flow-embed.php loads the class with require, like every other demo here.
 * - flow-embed.dist.php carries the class inline and requires nothing. Copy
 *   it anywhere and it still runs.
 *
 * flow-embed.dist.php is generated. Edit flow-embed.php, then regenerate:
 *
 *   composer embed-playground
 *
 * which runs embed.php with an output path, so the source is copied there
 * and the copy is rewritten:
 *
 *   php embed.php playground/flow-embed.php playground/flow-embed.dist.php
 *
 * embed.php replaces everything between the markers below with a minified
 * copy of the Prompty class, and carries everything outside them across
 * unchanged - which is why both files share this comment. composer lint
 * fails when the two drift apart.
 *
 * Add --compact to shorten internal property and method names and strip
 * whitespace, for a smaller result.
 *
 * Running embed.php again on an embedded script replaces the marker region
 * with the current class and preserves everything outside it. That is how an
 * embedded script picks up a newer version of Prompty.
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

// Kill switch. Without one, embed.php injects it directly after the marker
// region, ahead of the flow; declared here, it gates only the work below.
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
