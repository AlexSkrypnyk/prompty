<?php

/**
 * @file
 * Playground - a validator that rejects an answer and asks again.
 *
 * A rejected answer keeps the widget open with the message under the input.
 * The message clears when the answer changes, and the widget completes only
 * on an answer the validator accepts. In a flow, the validator also receives
 * the answers collected so far.
 *
 * A discovered answer has nobody to ask again, so a rejected one throws:
 *
 * @code
 * php playground/widget-validate.php --dish=pear
 * @endcode
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'dish:']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']));

$courses = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];
$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$seats = ['1' => 'Seat 1', '2' => 'Seat 2', '3' => 'Seat 3', '4' => 'Seat 4'];
$whole_dish = fn(string $dish): ?string => str_contains($dish, ' ') ? NULL : 'Name the whole dish, such as "pear tart".';

echo "\n--- Text: a single word is rejected ---\n";
$r = Prompty::text('Dish name', placeholder: 'pear tart', description: 'Type a single word, such as "pear", to see the message.', validate: $whole_dish);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Text: an empty answer is rejected ---\n";
$r = Prompty::text('Guest name', validate: fn(string $guest): ?string => $guest === '' ? 'Enter a name for the ticket.' : NULL);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Select: 1 option is rejected ---\n";
$r = Prompty::select('Course', options: $courses, validate: fn(string $course): ?string => $course === 'starter' ? 'The kitchen has stopped serving starters.' : NULL);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";

echo "\n--- Multiselect: an empty selection is rejected ---\n";
$r = Prompty::multiselect('Seats', options: $seats, validate: fn(array $picked): ?string => $picked === [] ? 'Pick at least one seat.' : NULL);
echo '  Result: ' . (is_array($r) ? implode(', ', array_filter($r, is_string(...))) : 'cancelled') . "\n";

echo "\n--- Flow: a rule that depends on earlier answers ---\n";
$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course', options: $courses, default: 'dessert'),
  'extras' => Prompty::multiselect('Extras', options: $extras, default: ['olives'], validate: fn(array $picked, array $r): ?string => $r['course'] === 'dessert' && in_array('olives', $picked, TRUE) ? 'Olives are not served with dessert.' : NULL),
  'seats' => Prompty::multiselect('Seats', options: $seats),
  'send' => Prompty::confirm('Send order?', validate: fn(bool $send, array $r): ?string => $send && $r['seats'] === [] ? 'Pick a seat before sending the order.' : NULL),
], cancelled: 'Order cancelled.');

if (is_array($results)) {
  echo PHP_EOL . 'Collected answers:' . PHP_EOL;

  foreach ($results as $key => $value) {
    $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}

echo "\n--- Text: a discovered answer is validated too ---\n";
$flag = is_string($opts['dish'] ?? NULL) ? $opts['dish'] : NULL;
echo '  Source: ' . ($flag === NULL ? 'no --dish flag, so the widget asks' : '--dish=' . var_export($flag, TRUE)) . "\n";

try {
  $r = Prompty::text('Dish name', placeholder: 'pear tart', discovered: $flag, validate: $whole_dish);
  echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . "\n";
}
catch (\InvalidArgumentException $exception) {
  echo '  Rejected: ' . $exception->getMessage() . "\n";
}
