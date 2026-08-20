#!/usr/bin/env php
<?php

/**
 * @file
 * Check that embedding does not change what a script does.
 *
 * playground/flow-embed.php loads the class with require; embedding rewrites
 * it into a copy that carries the class inline and requires nothing. The two
 * run the same flow, so anything that differs between what they print is a
 * fault in the embedder.
 *
 * This builds that copy into a temporary path, runs both with the same
 * answers supplied through the environment, and compares the output.
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

// Answers for every step of the flow, so neither run waits for a keypress.
define('ANSWERS', [
  'PROMPTY_DISH' => 'plum compote',
  'PROMPTY_COURSE' => 'main',
  'PROMPTY_EXTRAS' => 'bread,olives',
  'PROMPTY_SEND' => 'yes',
]);

/**
 * Compare the source script against a freshly embedded copy of it.
 *
 * @throws \RuntimeException
 *   When the copy cannot be built, either run prints nothing, or the two
 *   runs print something different.
 */
function main(): void {
  $project_dir = dirname(__DIR__);
  $tmp_dir = $project_dir . '/.artifacts/tmp';

  if (!is_dir($tmp_dir) && !mkdir($tmp_dir, 0755, TRUE) && !is_dir($tmp_dir)) {
    throw new \RuntimeException(sprintf('Could not create %s.', $tmp_dir));
  }

  $embedded_path = $tmp_dir . '/flow-embed.check.php';
  build($project_dir, $embedded_path);

  $source = run($project_dir . '/' . SOURCE_SCRIPT);
  $embedded = run($embedded_path);
  unlink($embedded_path);

  // A script that prints nothing would otherwise match one that prints
  // nothing, so the runs have to reach the end of the flow to count.
  if (!str_contains($source, 'Order sent!')) {
    throw new \RuntimeException(sprintf("%s did not complete its flow. It printed:%s%s", SOURCE_SCRIPT, PHP_EOL, $source));
  }

  if ($source !== $embedded) {
    throw new \RuntimeException(sprintf("Embedding changed what the script prints.%s%s--- %s%s%s%s--- embedded copy%s%s", PHP_EOL, PHP_EOL, SOURCE_SCRIPT, PHP_EOL, $source, PHP_EOL, PHP_EOL, $embedded));
  }

  info(sprintf('An embedded %s prints what it printed before embedding.', SOURCE_SCRIPT));
}

/**
 * Build an embedded copy of the source script.
 *
 * @param string $project_dir
 *   Path to the project root.
 * @param string $target_path
 *   Path to build into.
 *
 * @throws \RuntimeException
 *   When the embedder fails or writes nothing.
 */
function build(string $project_dir, string $target_path): void {
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

  if (!is_file($target_path)) {
    throw new \RuntimeException(sprintf('The embedder wrote nothing to %s.', $target_path));
  }
}

/**
 * Run a playground script with every answer supplied.
 *
 * @param string $script_path
 *   Path to the script to run.
 *
 * @return string
 *   Everything the script printed.
 */
function run(string $script_path): string {
  $prefix = '';

  foreach (ANSWERS as $name => $value) {
    $prefix .= $name . '=' . escapeshellarg($value) . ' ';
  }

  $output = [];
  exec($prefix . 'php ' . escapeshellarg($script_path) . ' --no-ansi 2>&1', $output);

  return implode(PHP_EOL, $output);
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
