<?php

/**
 * @file
 * Playground - what configure() accepts, and what it rejects.
 *
 * The symbol, color, label and spacing arguments each take a fixed set of
 * keys. A key outside that set is rejected, and the message names the key
 * and lists the ones that would have worked. Merged in and never read, a
 * misspelled key would change nothing and report nothing.
 *
 * Each call below runs. The rejected ones print the message they were
 * rejected with, and the accepted one then shows the symbol it changed.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

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
