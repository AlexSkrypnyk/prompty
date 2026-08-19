#!/usr/bin/env php
<?php

/**
 * @file
 * Check that the embedded playground demo matches its source.
 *
 * playground/flow-embed.dist.php is generated from playground/flow-embed.php
 * and holds a minified copy of the Prompty class, so it goes stale whenever
 * either file changes. This regenerates it into a temporary path and compares
 * the bytes, and is run by composer lint.
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress informational messages.
 *
 * Usage:
 * @code
 * php .util/check-embed.php
 * @endcode
 */

declare(strict_types=1);

define('SOURCE_SCRIPT', 'playground/flow-embed.php');
define('DIST_SCRIPT', 'playground/flow-embed.dist.php');
define('REGENERATE_COMMAND', 'composer embed-playground');

/**
 * Compare the committed embedded demo against a fresh build.
 *
 * @throws \RuntimeException
 *   When the demo is missing, cannot be rebuilt, or has drifted.
 */
function main(): void {
  $project_dir = dirname(__DIR__);
  $dist_path = $project_dir . '/' . DIST_SCRIPT;

  if (!is_file($dist_path)) {
    throw new \RuntimeException(sprintf('%s does not exist. Run `%s` to create it.', DIST_SCRIPT, REGENERATE_COMMAND));
  }

  $tmp_dir = $project_dir . '/.artifacts/tmp';

  if (!is_dir($tmp_dir) && !mkdir($tmp_dir, 0755, TRUE) && !is_dir($tmp_dir)) {
    throw new \RuntimeException(sprintf('Could not create %s.', $tmp_dir));
  }

  $tmp_path = $tmp_dir . '/flow-embed.check.php';
  $expected = build($project_dir, $tmp_path);
  $actual = file_get_contents($dist_path);

  if ($actual === FALSE) {
    throw new \RuntimeException(sprintf('Could not read %s.', DIST_SCRIPT));
  }

  if ($expected !== $actual) {
    throw new \RuntimeException(sprintf('%s is out of date. Run `%s` and commit the result.', DIST_SCRIPT, REGENERATE_COMMAND));
  }

  info(sprintf('%s matches %s.', DIST_SCRIPT, SOURCE_SCRIPT));
}

/**
 * Build the embedded demo at a temporary path.
 *
 * @param string $project_dir
 *   Path to the project root.
 * @param string $target_path
 *   Path to build into. Removed before returning.
 *
 * @return string
 *   Contents of the built file.
 *
 * @throws \RuntimeException
 *   When the embedder fails or writes nothing.
 */
function build(string $project_dir, string $target_path): string {
  $cmd = sprintf(
    'php %s --no-verify %s %s 2>&1',
    escapeshellarg($project_dir . '/embed.php'),
    escapeshellarg($project_dir . '/' . SOURCE_SCRIPT),
    escapeshellarg($target_path),
  );

  $output = [];
  $exit = 0;
  exec($cmd, $output, $exit);

  if ($exit !== 0) {
    throw new \RuntimeException(sprintf('The embedder exited with code %d:%s%s', $exit, PHP_EOL, implode(PHP_EOL, $output)));
  }

  $contents = file_get_contents($target_path);
  unlink($target_path);

  if ($contents === FALSE) {
    throw new \RuntimeException(sprintf('The embedder wrote nothing to %s.', $target_path));
  }

  return $contents;
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
  main();
}
catch (\Exception $exception) {
  error('');
  error('ERROR: ' . $exception->getMessage());
  exit(1);
}
