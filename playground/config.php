<?php

/**
 * @file
 * Playground - configure() for widgets and flows, and what it rejects.
 *
 * Prompty::configure() writes to the shared instance, so what it sets -
 * labels, a display mode, an environment prefix - serves every widget and
 * flow that follows, with no flow wrapper needed. Repeat calls update the
 * instance, and a flow's own configuration arguments merge on top for as
 * long as that flow runs.
 *
 * The symbol, color, label and spacing arguments each take a fixed set of
 * keys. A key outside that set is rejected, and the message names the key
 * and lists the ones that would have worked. Merged in and never read, a
 * misspelled key would change nothing and report nothing.
 *
 * Each rejected call below prints the message it was rejected with, and the
 * accepted one then draws a confirm with the labels it set.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
Prompty::configure(
  labels: ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(aborted)', 'none' => 'Nothing selected'],
  unicode: !isset($opts['no-unicode']),
  ansi: !isset($opts['no-ansi']),
  env_prefix: 'KITCHEN_',
);

/**
 * Make a configure() call and report what it did.
 *
 * @param string $title
 *   What the call is trying to do.
 * @param callable $call
 *   The configure() call.
 */
function attempt(string $title, callable $call): void {
  echo PHP_EOL . '--- ' . $title . ' ---' . PHP_EOL;

  try {
    $call();
    echo 'Accepted.' . PHP_EOL;
  }
  catch (\InvalidArgumentException $exception) {
    echo 'Rejected: ' . $exception->getMessage() . PHP_EOL;
  }
}

echo PHP_EOL . '--- Text: with env prefix ---' . PHP_EOL;
$r = Prompty::text('Dish name', placeholder: 'pear tart');
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . PHP_EOL;

echo PHP_EOL . '--- Select: with custom labels ---' . PHP_EOL;
$r = Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']);
echo '  Result: ' . (is_string($r) ? $r : 'cancelled') . PHP_EOL;

echo PHP_EOL . '--- Confirm: with custom labels ---' . PHP_EOL;
$r = Prompty::confirm('Send order?');
echo '  Result: ' . ($r !== NULL ? ($r ? 'yes' : 'no') : 'cancelled') . PHP_EOL;

attempt('A label key that does not exist', function (): void {
  Prompty::configure(labels: ['bogus' => 'X']);
});

attempt('A real label key, spelled with a stray space', function (): void {
  Prompty::configure(labels: ['yes ' => 'Aye']);
});

attempt('A symbol key that does not exist', function (): void {
  Prompty::configure(symbols_unicode: ['bogus' => 'X']);
});

attempt('A spacing key that does not exist', function (): void {
  Prompty::configure(spacing: ['padding' => '  ']);
});

attempt('A colour key that does not exist', function (): void {
  Prompty::configure(colors: ['turquoise' => "\033[36m"]);
});

attempt('An empty truthy list, which confirm() could never match', function (): void {
  Prompty::configure(truthy: []);
});

attempt('A label key that does exist', function (): void {
  Prompty::configure(labels: ['yes' => 'Aye', 'no' => 'Nay']);
});

echo PHP_EOL . 'The accepted call changed what a confirm widget draws:' . PHP_EOL;
Prompty::confirm('Send order?', discovered: TRUE);

echo PHP_EOL . '--- An empty env_prefix is accepted ---' . PHP_EOL;
echo 'It is a coherent thing to ask for, so it is allowed, but a step named' . PHP_EOL;
echo 'path, home or user then reads PATH, HOME or USER from the environment' . PHP_EOL;
echo 'the script was started with. Keep a prefix unless that is the intent.' . PHP_EOL;

echo PHP_EOL . '--- A flow merges its own arguments on top ---' . PHP_EOL;

// While the flow runs, both apply: the ORDER_ prefix from configure() and
// the labels the flow passes.
Prompty::configure(env_prefix: 'ORDER_');

$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'send' => Prompty::confirm('Send order?'),
],
  intro: 'Order setup',
  outro: 'All done!',
  cancelled: 'Order setup cancelled.',
  labels: ['yes' => 'Yep', 'no' => 'Nope'],
);

if ($results === NULL) {
  exit(0);
}

echo PHP_EOL . 'Collected answers:' . PHP_EOL;

foreach ($results as $key => $value) {
  $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
  echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
}
