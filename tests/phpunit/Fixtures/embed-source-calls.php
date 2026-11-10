<?php

/**
 * @file
 * Fixture - calls each public method of an EmbedSource file and prints them.
 *
 * Pass the path of the class file to load: the source or a minified copy.
 */

declare(strict_types=1);

require $argv[1];

use AlexSkrypnyk\Prompty\Tests\Fixtures\EmbedSource;

$source = EmbedSource::instance();

$counts = [];
$source->tally($counts, 'soup');
$source->tally(counts: $counts, dish: 'bread');
$source->tally($counts, 'soup');

echo json_encode([
  $source->describe('soup', 'starter', [' bread ', '']),
  $source->describe('', course: 'late_snack'),
  $source->describe(dish: 'stew', sides: ['olives']),
  $source->pause(2),
  $source->pause(),
  $source->tickets(['main' => 'stew', 'side_dish' => 'toast'], copies: 3),
  $source->tickets(dishes: ['dessert' => 'tart']),
  $source->plates(6, 2),
  $counts,
], JSON_THROW_ON_ERROR) . PHP_EOL;
