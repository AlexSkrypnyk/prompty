<?php

/**
 * @file
 * Playground - streamed tasks nested 1, 2 and 3 levels deep.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']), interactive: !isset($opts['no-interaction']));

$say = function (string $line): void {
  echo $line . "\n";
  usleep(300000);
};

$rest = fn(): bool => Prompty::task('Resting the dough', action: function () use ($say): bool {
  $say('Chilled for 20 minutes');
  $say('Rolled thin');

  return TRUE;
}, success: 'Dough rested', stream: TRUE) !== FALSE;

$pastry = fn(bool $is_resting): bool => Prompty::task('Making the pastry', action: function () use ($say, $rest, $is_resting): bool {
  $say('Flour and butter rubbed together');

  if ($is_resting) {
    $rest();
  }
  else {
    $say('Dough wrapped and chilled');
  }

  $say('Lined into the tin');

  return TRUE;
}, success: 'Pastry made', stream: TRUE) !== FALSE;

$bake = fn(bool $is_resting, bool $is_preheating): bool => Prompty::task('Baking the tart', action: function () use ($say, $pastry, $is_resting, $is_preheating): bool {
  if ($is_preheating) {
    Prompty::task('Preheating the oven', action: function (): bool {
      sleep(1);

      return TRUE;
    }, hint: 'Fan oven, 180°C', success: 'Oven ready');
  }

  $say('Oven at 180°C');
  $pastry($is_resting);
  $say('Tart in the top rack');

  return TRUE;
}, success: 'Tart baked', stream: TRUE) !== FALSE;

echo "\n--- Task: 1 level of nested tasks ---\n";
$r = $bake(FALSE, FALSE);
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: 2 levels of nested tasks ---\n";
$r = $bake(TRUE, FALSE);
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: 3 levels of nested tasks ---\n";
$r = Prompty::task('Preparing the order', action: function () use ($say, $bake): bool {
  $say('Order for table 7');
  $bake(TRUE, TRUE);
  $say('Tart plated with cream on the side');

  return TRUE;
}, success: 'Order ready', stream: TRUE);
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: nested failure ---\n";
$r = Prompty::task('Poaching the pears', action: function () use ($say): bool {
  $say('Syrup at a low simmer');

  return Prompty::task('Checking the pears', action: function () use ($say): bool {
    $say('Checked at 20 minutes: still firm');

    return FALSE;
  }, failure: 'Pears are not soft yet', stream: TRUE) !== FALSE;
}, failure: 'Pears not ready to serve', stream: TRUE);
echo '  Result: ' . var_export($r, TRUE) . "\n";
