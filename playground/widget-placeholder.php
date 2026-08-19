<?php

/**
 * @file
 * Playground - what an empty answer to a text widget means.
 *
 * A text answer can arrive from a flag or from the environment, and either can
 * turn up empty when the command that produced it returned nothing:
 *
 * @code
 * php playground/widget-placeholder.php --dish='onion soup'
 * php playground/widget-placeholder.php --dish=
 * PROMPTY_DISH= php playground/widget-placeholder.php
 * @endcode
 *
 * Run it with no flag and the first widget asks, which is the answer the empty
 * cases fall back to.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'dish:']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

/**
 * Prints a text result on one line.
 *
 * @param \Closure|array<string, mixed>|string|null $result
 *   What the widget returned.
 */
function show(\Closure|array|string|null $result): void {
  echo '  Result: ' . (is_string($result) ? var_export($result, TRUE) : 'cancelled') . "\n";
}

echo "\n--- Text: answered by a command-line flag ---\n";
$flag = is_string($opts['dish'] ?? NULL) ? $opts['dish'] : NULL;
echo '  Source: ' . ($flag === NULL ? 'no --dish flag, so the widget asks' : '--dish=' . var_export($flag, TRUE)) . "\n";
show(Prompty::text('Dish name', placeholder: 'pear tart', discovered: $flag));

echo "\n--- Text: an empty answer takes the placeholder ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart', discovered: ''));

echo "\n--- Text: whitespace is an empty answer too ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart', discovered: '   '));

echo "\n--- Text: an empty answer prefers a seeded default ---\n";
show(Prompty::text('Dish name', default: 'plum compote', placeholder: 'pear tart', discovered: ''));

echo "\n--- Text: an answer is trimmed ---\n";
show(Prompty::text('Dish name', placeholder: 'pear tart', discovered: '  onion soup  '));

echo "\n--- Text: an empty environment variable is an empty answer ---\n";
$env = getenv('PROMPTY_DISH');
echo '  Source: ' . ($env === FALSE ? 'PROMPTY_DISH unset, so the widget asks' : 'PROMPTY_DISH=' . var_export($env, TRUE)) . "\n";
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
]);
show(is_array($results) && is_string($results['dish'] ?? NULL) ? $results['dish'] : NULL);
