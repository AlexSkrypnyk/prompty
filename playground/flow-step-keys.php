<?php

/**
 * @file
 * Playground - what a step key becomes, and which ones are turned down.
 *
 * A step's key is uppercased behind the prefix to make the name discovery
 * reads, so a step keyed 'dish_name' is pre-filled by PROMPTY_DISH_NAME.
 *
 * A name outside letters, digits and underscores can still be set by a parent
 * process - env 'PROMPTY_CI-PROVIDER=x' php script.php works - but 'export'
 * rejects it as a syntax error and most .env parsers will not accept it. A
 * step named that way could never be filled by the means callers reach for:
 * the lookup found nothing and the flow prompted instead, saying nothing
 * about why. It is turned down now, and the name is what is checked, so a
 * prefix that cannot be exported is caught the same way.
 *
 * The first flow is turned down and prints the message. The second is
 * accepted, and its answers come from the environment.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

echo '--- A key a shell cannot export ---' . PHP_EOL;
echo 'Trying a flow with a step keyed "ci-provider".' . PHP_EOL;

try {
  Prompty::flow(fn(): array => [
    'ci-provider' => Prompty::text('Provider'),
  ]);
  echo 'Accepted.' . PHP_EOL;
}
catch (\InvalidArgumentException $exception) {
  echo 'Turned down: ' . $exception->getMessage() . PHP_EOL;
}

echo PHP_EOL . '--- The same key inside children ---' . PHP_EOL;

try {
  Prompty::flow(fn(): array => [
    'course' => Prompty::confirm('Send order?', discovered: TRUE, children: [
      'side.dish' => Prompty::text('Side dish'),
    ]),
  ]);
  echo 'Accepted.' . PHP_EOL;
}
catch (\InvalidArgumentException $exception) {
  echo 'Turned down: ' . $exception->getMessage() . PHP_EOL;
}

echo PHP_EOL . '--- Keys that are fine ---' . PHP_EOL;
echo 'dish_name reads PROMPTY_DISH_NAME, dishName reads PROMPTY_DISHNAME,' . PHP_EOL;
echo 'and course2 reads PROMPTY_COURSE2. Set those three to see them' . PHP_EOL;
echo 'pre-filled; leave them unset and the flow asks instead.' . PHP_EOL . PHP_EOL;

$results = Prompty::flow(fn(): array => [
  'dish_name' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'dishName' => Prompty::text('Written as', placeholder: 'on the ticket'),
  'course2' => Prompty::select('Second course', options: ['starter' => 'Starter', 'main' => 'Main']),
],
  intro: 'Keys that can be exported',
  outro: 'Order noted.',
  cancelled: 'Cancelled.',
);

if ($results === NULL) {
  exit(0);
}

echo PHP_EOL . 'Collected:' . PHP_EOL;

foreach ($results as $key => $value) {
  echo '  ' . $key . ': ' . (is_string($value) ? $value : json_encode($value)) . PHP_EOL;
}
