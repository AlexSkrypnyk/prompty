#!/usr/bin/env php
<?php

/**
 * @file
 * Check that a set of release assets embeds into a script that verifies.
 *
 * The directory must hold embed.php, Prompty.php, starter.php and a
 * SHA256SUMS that lists the payloads and embed.php. For each mode, this
 * embeds Prompty.php into a copy of starter.php with that embed.php, runs
 * 'embed.php --check --sums' on it, and confirms that a copy with 1 changed
 * byte fails the same check.
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress informational messages.
 *
 * Usage:
 * @code
 * php .util/check-embed-release.php <directory>
 * @endcode
 */

declare(strict_types=1);

define('MODES', ['min' => [], 'compact' => ['--compact']]);

/**
 * Embed and check the assets in a directory in every mode.
 *
 * @param array<int, string> $argv
 *   The command line arguments.
 *
 * @throws \RuntimeException
 *   When an asset is missing, embedding fails, the check fails, or a changed
 *   copy passes the check.
 */
function main(array $argv): void {
  $dir = $argv[1] ?? '';

  foreach (['embed.php', 'Prompty.php', 'starter.php', 'SHA256SUMS'] as $asset) {
    if (!is_file($dir . '/' . $asset)) {
      throw new \RuntimeException(sprintf('%s is missing from "%s".', $asset, $dir));
    }
  }

  foreach (MODES as $mode => $flags) {
    $embedded = $dir . '/embedded-' . $mode . '.php';
    $changed = $dir . '/changed-' . $mode . '.php';

    copy($dir . '/starter.php', $embedded);
    run($dir, array_merge($flags, ['--no-verify', $embedded]), TRUE);
    run($dir, ['--check', $embedded, '--sums', $dir . '/SHA256SUMS'], TRUE);

    $content = (string) file_get_contents($embedded);
    file_put_contents($changed, (string) preg_replace('/^(\/\/ @embed-start[^\n]*\n)/m', '$1 ', $content, 1));

    if (run($dir, ['--check', $changed, '--sums', $dir . '/SHA256SUMS'], FALSE) === 0) {
      throw new \RuntimeException(sprintf('A %s embed with 1 changed byte passed the check.', $mode));
    }

    unlink($embedded);
    unlink($changed);
    info(sprintf('A %s embed verifies against SHA256SUMS, and a changed copy does not.', $mode));
  }
}

/**
 * Run the embed.php in a directory.
 *
 * @param string $dir
 *   The directory holding embed.php.
 * @param array<int, string> $args
 *   The arguments.
 * @param bool $must_pass
 *   Whether a non-zero exit is an error.
 *
 * @return int
 *   The exit code.
 *
 * @throws \RuntimeException
 *   When $must_pass is set and embed.php fails.
 */
function run(string $dir, array $args, bool $must_pass): int {
  $cmd = 'php ' . escapeshellarg($dir . '/embed.php') . ' ' . implode(' ', array_map(escapeshellarg(...), $args)) . ' 2>&1';

  $output = [];
  $exit = 0;
  exec($cmd, $output, $exit);

  if ($must_pass && $exit !== 0) {
    throw new \RuntimeException(sprintf('embed.php %s exited with code %d:%s%s', implode(' ', $args), $exit, PHP_EOL, implode(PHP_EOL, $output)));
  }

  return $exit;
}

/**
 * Print an informational message.
 *
 * @param string $message
 *   The message to print.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') === '1') {
    return;
  }

  print $message . PHP_EOL;
}

/**
 * Print an error message to STDERR.
 *
 * Not silenced by SCRIPT_QUIET, so failures surface in quiet runs.
 *
 * @param string $message
 *   The message to print.
 */
function error(string $message): void {
  fwrite(STDERR, $message . PHP_EOL);
}

ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
  if ((error_reporting() & $severity) === 0) {
    return FALSE;
  }
  throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
  main($argv);
}
catch (\Exception $exception) {
  error('');
  error('ERROR: ' . $exception->getMessage());
  exit(1);
}
