<?php

/**
 * @file
 * Starter script - example consumer of Prompty.
 *
 * Demonstrates the recommended pattern for scripts that use Prompty::flow()
 * and need to be testable with PromptyTestTrait.
 *
 * The kill switch pattern:
 *   1. Run the flow to collect user answers.
 *   2. Check an environment variable (e.g., SHOULD_PROCEED).
 *   3. If not set, exit early - this is what happens during testing.
 *   4. If set, proceed with real work (file creation, installs, etc.).
 */

declare(strict_types=1);

// phpcs:disable
// @embed-start
// Run `php embed.php starter.php` to embed Prompty here.
// Use `php embed.php --compact starter.php` for a smaller output.
require_once __DIR__ . '/Prompty.php';
use AlexSkrypnyk\Prompty\Prompty;
// @embed-end
// phpcs:enable

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs']),
  'send' => Prompty::confirm('Send order?'),
], intro: 'Compose an order', outro: 'Order sent!', cancelled: 'Order cancelled.');

// Kill switch - stop here when running under tests.
// In production, set SHOULD_PROCEED=1 to continue past this point.
if (!getenv('SHOULD_PROCEED')) {
  return;
}

/** @var array{dish: string, course: string, extras: list<string>, send: bool}|null $results */
echo 'Dish: ' . ($results['dish'] ?? '') . PHP_EOL;
echo 'Course: ' . ($results['course'] ?? '') . PHP_EOL;
echo 'Extras: ' . implode(', ', $results['extras'] ?? []) . PHP_EOL;
echo 'Send: ' . (($results['send'] ?? FALSE) ? 'yes' : 'no') . PHP_EOL;
