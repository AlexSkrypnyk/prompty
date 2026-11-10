<?php

/**
 * @file
 * Playground - task widget standalone, behind a spinner and streamed.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
Prompty::configure(unicode: !isset($opts['no-unicode']), ansi: !isset($opts['no-ansi']), interactive: !isset($opts['no-interaction']));

// The oven is a child process, so its output reaches the task through
// passthru(). Its stderr is joined to stdout to be streamed as well.
$code = <<<'PHP'
  $say = function (string $text): void { echo $text; flush(); usleep(250000); };
  $say("Tray 1 of 2 in the top rack\n");
  foreach ([20, 45, 70, 100] as $percent) { $say("Rising {$percent}%\r"); }
  $say("\n");
  $say("Crust \033[33mgolden\033[0m at the edges\n");
  $say("Tray 2 of 2 in the top rack\n");
  fwrite(STDERR, "Fan cycling, temperature held\n");
  $say("Both trays out, resting on the rack\n");
  PHP;
$oven = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1';

echo "\n--- Task: hint and result ---\n";
$r = Prompty::task('Preheating the oven', action: function (): bool {
  sleep(1);

  return TRUE;
}, hint: 'Fan oven, 180°C', success: 'Oven ready', failure: 'Oven did not reach temperature');
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: detail lines ---\n";
$r = Prompty::task('Setting the table', action: function (): array {
  sleep(1);

  return ['Table 7', 'Seats 2 and 4'];
}, success: fn(array $details): string => sprintf('Table set (%d details)', count($details)));
echo '  Result: ' . (is_array($r) ? implode(', ', array_filter($r, is_string(...))) : 'none') . "\n";

echo "\n--- Task: failure ---\n";
$r = Prompty::task('Proving the dough', action: function (): bool {
  sleep(1);

  return FALSE;
}, failure: 'Dough did not rise');
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: streamed ---\n";
$r = Prompty::task('Baking the tart', action: function () use ($oven): bool {
  echo "Oven at 180°C\n";
  usleep(250000);
  passthru($oven);

  return TRUE;
}, success: 'Tart baked', stream: TRUE);
echo '  Result: ' . var_export($r, TRUE) . "\n";

echo "\n--- Task: streamed failure ---\n";
$r = Prompty::task('Poaching the pears', action: function (): bool {
  foreach (['Syrup at a low simmer', 'Pears in, lid half on', 'Checked at 20 minutes: still firm'] as $line) {
    echo $line . "\n";
    usleep(400000);
  }

  return FALSE;
}, failure: 'Pears are not soft yet', stream: TRUE);
echo '  Result: ' . var_export($r, TRUE) . "\n";
