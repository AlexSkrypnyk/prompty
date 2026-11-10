<?php

/**
 * @file
 * Fixture - a spinner task and a streamed task drawn to a terminal.
 *
 * Prints EXITED once both tasks have drawn their result.
 */

declare(strict_types=1);

require __DIR__ . '/../../../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

Prompty::configure(unicode: FALSE, width: 80);

$oven = Prompty::task('Preheating the oven', action: function (): string {
  usleep(400000);

  return '180';
}, success: fn(string $degrees): string => sprintf('Oven at %s', $degrees));

$command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('foreach (range(1, 6) as $i) { echo "Tray $i in\n"; usleep(20000); } fwrite(STDERR, "Fan cycling\n");') . ' 2>&1';

$tart = Prompty::task('Baking the tart', action: function () use ($command): bool {
  echo "Oven at 180\n";
  passthru($command);

  return TRUE;
}, success: 'Tart baked', stream: TRUE);

echo 'Answers: ' . var_export([$oven, $tart], TRUE) . PHP_EOL;
echo 'EXITED' . PHP_EOL;
