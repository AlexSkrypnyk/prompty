#!/usr/bin/env php
<?php

/**
 * @file
 * Generate animated SVG assets from asciinema recordings.
 *
 * Records terminal sessions for playground scripts (widgets, flow, flow-nested),
 * then converts the recordings to animated SVGs for use in README.md.
 *
 * Supports parallel execution: when run without arguments, launches all
 * recordings as parallel worker processes for faster generation.
 *
 * Dependencies: asciinema, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 *
 * Usage:
 * @code
 * php .util/update-assets.php
 * php .util/update-assets.php --record widgets
 * @endcode
 */

declare(strict_types=1);

// Terminal dimensions for recordings.
define('TERMINAL_COLS', 80);
define('TERMINAL_ROWS', 24);

// Delay before interacting with prompts in expect scripts (seconds).
define('PROMPT_DELAY', 1);

// Maximum idle time in recordings (seconds).
define('MAX_IDLE_TIME', 3);

// Pause at the end of each recording before the animation loops (seconds).
define('END_PAUSE', 10);

/**
 * Get all job definitions.
 *
 * @param string $project_dir
 *   Path to the project root.
 *
 * @return array<string, array<string, mixed>>
 *   Keyed by job name, each containing script, expect_fn, rows, and
 *   optionally at (for static screenshots).
 */
function getJobs(string $project_dir): array {
  return [
    // Animated recordings — full interaction sequences.
    'widgets' => [
      'script' => $project_dir . '/playground/widgets.php',
      'expect_fn' => 'createWidgetsExpectScript',
      'rows' => 18,
    ],
    'flow' => [
      'script' => $project_dir . '/playground/flow.php',
      'expect_fn' => 'createFlowExpectScript',
      'rows' => 20,
    ],
    'flow-nested' => [
      'script' => $project_dir . '/playground/flow-nested.php',
      'expect_fn' => 'createFlowNestedExpectScript',
      'rows' => 20,
    ],
    'flow-ascii' => [
      'script' => $project_dir . '/playground/flow-ascii.php',
      'expect_fn' => 'createFlowExpectScript',
      'rows' => 20,
    ],
    'flow-nested-ascii' => [
      'script' => $project_dir . '/playground/flow-nested-ascii.php',
      'expect_fn' => 'createFlowNestedExpectScript',
      'rows' => 20,
    ],
    // Static screenshots — capture a single frame showing the widget.
    'widget-text' => [
      'script' => $project_dir . '/playground/widget-text.php',
      'expect_fn' => 'createWidgetTextExpectScript',
      'at' => 500,
      'rows' => 8,
    ],
    'widget-select' => [
      'script' => $project_dir . '/playground/widget-select.php',
      'expect_fn' => 'createWidgetSelectExpectScript',
      'at' => 500,
      'rows' => 10,
    ],
    'widget-multiselect' => [
      'script' => $project_dir . '/playground/widget-multiselect.php',
      'expect_fn' => 'createWidgetMultiselectExpectScript',
      'at' => 500,
      'rows' => 10,
    ],
    'widget-confirm' => [
      'script' => $project_dir . '/playground/widget-confirm.php',
      'expect_fn' => 'createWidgetConfirmExpectScript',
      'at' => 500,
      'rows' => 7,
    ],
  ];
}

/**
 * Main functionality — orchestrator mode.
 *
 * Launches all recordings as parallel worker processes.
 */
function main(): void {
  $script_dir = dirname(__FILE__);
  $project_dir = dirname($script_dir);
  $assets_dir = $script_dir . '/assets';

  info('Prompty — Asset Generator');
  info('========================');
  info('');

  checkDependencies();
  installNodeDependencies($script_dir);

  $jobs = getJobs($project_dir);
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  // Create all expect scripts upfront.
  foreach ($jobs as $name => $job) {
    $expect_script = $tmp_dir . '/' . $name . '.exp';
    $create_fn = $job['expect_fn'];
    $create_fn($expect_script, $job['script']);
  }

  // Launch all workers in parallel.
  $script_path = __FILE__;
  $processes = [];
  $pipes_list = [];

  info('Launching ' . count($jobs) . ' workers in parallel...');
  info('');

  foreach ($jobs as $name => $job) {
    $cmd = sprintf(
      'php %s --record %s',
      escapeshellarg($script_path),
      escapeshellarg($name)
    );

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $pipes = [];
    $process = proc_open($cmd, $descriptors, $pipes, $project_dir);

    if (!is_resource($process)) {
      throw new \RuntimeException('Failed to launch worker for: ' . $name);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $processes[$name] = $process;
    $pipes_list[$name] = $pipes;

    info('  Started: ' . $name);
  }

  info('');

  // Wait for all workers to complete and reset terminal state.
  // Workers run asciinema/expect which can leave the terminal in raw mode.
  $failed = [];
  foreach ($processes as $name => $process) {
    $stdout = stream_get_contents($pipes_list[$name][1]);
    $stderr = stream_get_contents($pipes_list[$name][2]);
    fclose($pipes_list[$name][1]);
    fclose($pipes_list[$name][2]);

    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
      $failed[$name] = trim(($stdout ?: '') . ($stderr ?: ''));
      info('  FAILED: ' . $name);
    }
    else {
      info('  Done: ' . $name);
    }
  }

  // Reset terminal — workers may leave it in raw mode.
  shell_exec('stty sane 2>/dev/null');

  // Cleanup.
  info('');
  info('Cleaning up: ' . $tmp_dir);
  removeDir($tmp_dir);

  if (!empty($failed)) {
    info('');
    info('Errors:');
    foreach ($failed as $name => $output) {
      info('  ' . $name . ': ' . $output);
    }
    throw new \RuntimeException('Failed to generate ' . count($failed) . ' asset(s).');
  }

  info('');
  info('Done. ' . count($jobs) . ' SVG assets updated in ' . $assets_dir);
}

/**
 * Worker mode — process a single recording.
 *
 * @param string $name
 *   The job name to process.
 */
function processOne(string $name): void {
  $script_dir = dirname(__FILE__);
  $project_dir = dirname($script_dir);
  $assets_dir = $script_dir . '/assets';
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';

  $jobs = getJobs($project_dir);
  if (!isset($jobs[$name])) {
    throw new \RuntimeException('Unknown job: ' . $name);
  }

  $job = $jobs[$name];
  $cast_file = $tmp_dir . '/' . $name . '.cast';
  $expect_script = $tmp_dir . '/' . $name . '.exp';
  $svg_file = $assets_dir . '/' . $name . '.svg';
  $rows = $job['rows'] ?? TERMINAL_ROWS;
  $at = $job['at'] ?? NULL;

  recordSession($cast_file, $expect_script, $rows);
  postProcessCast($cast_file);
  convertToSvg($cast_file, $svg_file, $script_dir, $at);
}

/**
 * Check that all required dependencies are installed.
 */
function checkDependencies(): void {
  $deps = ['asciinema', 'expect', 'node', 'npm'];
  $missing = [];

  foreach ($deps as $dep) {
    if (empty(shell_exec('which ' . escapeshellarg($dep) . ' 2>/dev/null'))) {
      $missing[] = $dep;
    }
  }

  if (!empty($missing)) {
    throw new \RuntimeException('Missing required dependencies: ' . implode(', ', $missing));
  }

  info('All dependencies found.');
}

/**
 * Install Node.js dependencies for svg-term rendering.
 *
 * @param string $util_dir
 *   Path to the .util directory containing svg-term-render.js.
 */
function installNodeDependencies(string $util_dir): void {
  info('Installing svg-term Node.js dependency...');

  $node_modules = $util_dir . '/node_modules';
  if (is_dir($node_modules . '/svg-term')) {
    info('svg-term already installed.');

    return;
  }

  $cmd = sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg($util_dir));
  $output = shell_exec($cmd);
  if (!is_dir($node_modules . '/svg-term')) {
    throw new \RuntimeException('Failed to install svg-term: ' . ($output ?? 'unknown error'));
  }

  info('svg-term installed.');
}

/**
 * Record a session using asciinema with an expect script.
 *
 * @param string $cast_file
 *   Path to write the cast file.
 * @param string $expect_script
 *   Path to the expect script for automation.
 * @param int $rows
 *   Number of terminal rows.
 */
function recordSession(string $cast_file, string $expect_script, int $rows = TERMINAL_ROWS): void {
  $cmd = sprintf(
    'asciinema rec --command=%s --window-size=%dx%d --idle-time-limit=%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    TERMINAL_COLS,
    $rows,
    MAX_IDLE_TIME,
    escapeshellarg($cast_file)
  );

  $output = shell_exec($cmd);

  if (!file_exists($cast_file)) {
    throw new \RuntimeException('Failed to record session: ' . $cast_file . "\n" . ($output ?? ''));
  }
}

/**
 * Post-process a cast file.
 *
 * Removes the spawn command line and sanitizes paths.
 *
 * @param string $cast_file
 *   Path to the cast file.
 */
function postProcessCast(string $cast_file): void {
  $content = file_get_contents($cast_file);
  if ($content === FALSE) {
    return;
  }

  // Remove the spawn command line from the recording.
  $lines = explode("\n", $content);
  $filtered = [$lines[0]];
  for ($i = 1; $i < count($lines); $i++) {
    if (str_contains($lines[$i], 'spawn ')) {
      continue;
    }
    $filtered[] = $lines[$i];
  }

  // Add a pause at the end of the recording before the animation loops.
  // In asciicast v3, timestamps are relative (delta from previous event),
  // so we add an empty output event with the pause duration.
  $filtered[] = json_encode([END_PAUSE, 'o', ' ']);

  $content = implode("\n", $filtered);

  // Sanitize home directory paths.
  $home = getenv('HOME');
  if ($home !== FALSE && $home !== '') {
    $content = str_replace($home, '/home/user', $content);
  }

  file_put_contents($cast_file, $content);
}

/**
 * Convert a cast file to an SVG.
 *
 * When $at is provided, renders a single static frame at that timestamp.
 * Otherwise, renders an animated SVG of the full recording.
 *
 * @param string $cast_file
 *   Path to the input cast file.
 * @param string $svg_file
 *   Path to the output SVG file.
 * @param string $util_dir
 *   Path to the .util directory containing svg-term-render.js.
 * @param int|null $at
 *   Optional timestamp in ms to capture a static frame.
 */
function convertToSvg(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL): void {
  $renderer = $util_dir . '/svg-term-render.js';

  $at_flag = $at !== NULL ? sprintf(' --at %d', $at) : '';
  $cmd = sprintf(
    'node %s %s %s --line-height 1.1%s 2>&1',
    escapeshellarg($renderer),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $at_flag
  );

  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . "\n" . ($output ?? ''));
  }
}

/**
 * Create an expect script for the widgets.php playground.
 *
 * Interaction sequence:
 * 1. Text "Project name" — type "my-project", press enter.
 * 2. Select "Framework" — arrow down (to Vue), press enter.
 * 3. Multiselect "Features" — down, space (ESLint), down, space (Prettier),
 *    press enter.
 * 4. Confirm "Install dependencies?" — type "y", press enter.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createWidgetsExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep 0.3
    safe_send " "
}

spawn php {$playground_script}

# Text: Project name — type "my-project" and press enter.
expect "Project name" {
    sleep {$delay}
    type_text "my-project"
    wait_and_enter
}

# Select: Framework — arrow down to Vue, press enter.
expect "Framework" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Features — select ESLint and Prettier.
expect "Features" {
    sleep {$delay}
    arrow_down
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Install dependencies — type "y".
expect "Install dependencies" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the flow.php playground.
 *
 * Interaction sequence:
 * 1. Text "Project name" — type "my-project", press enter.
 * 2. Select "Framework" (4 options) — arrow down (to Vue), press enter.
 * 3. Multiselect "Features" (5 options) — down, space (ESLint), down, down,
 *    space (Vitest), press enter.
 * 4. Confirm "Install dependencies?" — type "y", press enter.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createFlowExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep 0.3
    safe_send " "
}

spawn php {$playground_script}

# Text: Project name — type "my-project" and press enter.
expect "Project name" {
    sleep {$delay}
    type_text "my-project"
    wait_and_enter
}

# Select: Framework — arrow down to Vue, press enter.
expect "Framework" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Features — select ESLint and Vitest.
expect "Features" {
    sleep {$delay}
    arrow_down
    toggle_space
    arrow_down
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Install dependencies — type "y".
expect "Install dependencies" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the flow-nested.php playground.
 *
 * Interaction sequence (choosing the "Application" path):
 * 1. Select "Project type" — press enter (Application, first option).
 * 2. Select "App framework" — press enter (Next.js, first option).
 * 3. Select "SSR mode" — press enter (Server-side, first option).
 * 4. Confirm "Use edge runtime?" — type "y", press enter.
 * 5. Confirm "Include API routes?" — type "y", press enter.
 * 6. Multiselect "Code quality" — space (TypeScript), down, space (ESLint),
 *    press enter.
 * 7. Confirm "Strict TypeScript?" — type "y", press enter.
 * 8. Select "ESLint config" — down (Strict), press enter.
 * 9. Multiselect "Testing" — space (Unit tests), down, space (E2E tests),
 *    press enter.
 * 10. Select "Unit test runner" — press enter (Vitest, first option).
 * 11. Select "E2E framework" — press enter (Playwright, first option).
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createFlowNestedExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep 0.3
    safe_send " "
}

spawn php {$playground_script}

# Select: Project type — press enter (Application).
expect "Project type" {
    wait_and_enter
}

# Select: App framework — press enter (Next.js).
expect "App framework" {
    wait_and_enter
}

# Select: SSR mode — press enter (Server-side).
expect "SSR mode" {
    wait_and_enter
}

# Confirm: Use edge runtime — type "y".
expect "edge runtime" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Confirm: Include API routes — type "y".
expect "API routes" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Multiselect: Code quality — select TypeScript and ESLint.
expect "Code quality" {
    sleep {$delay}
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Strict TypeScript — type "y".
expect "Strict TypeScript" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Select: ESLint config — down to Strict, press enter.
expect "ESLint config" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Testing — select Unit tests and E2E tests.
expect "Testing" {
    sleep {$delay}
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Select: Unit test runner — press enter (Vitest).
expect "Unit test runner" {
    wait_and_enter
}

# Select: E2E framework — press enter (Playwright).
expect "E2E framework" {
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the widget-text.php playground.
 *
 * Records all 3 text prompts. Static screenshot is captured at the first.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createWidgetTextExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

spawn php {$playground_script}

# Text: Project name — type name and press enter.
expect "Project name" {
    sleep {$delay}
    type_text "my-project"
    wait_and_enter
}

# Text: Author name — type name and press enter.
expect "Author name" {
    sleep {$delay}
    type_text "Jane Doe"
    wait_and_enter
}

# Text: Git remote URL — type URL and press enter.
expect "Git remote" {
    sleep {$delay}
    type_text "git@github.com:user/repo.git"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the widget-select.php playground.
 *
 * Records all 3 select prompts. Static screenshot is captured at the first.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createWidgetSelectExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

spawn php {$playground_script}

# Select: Framework — press enter (React).
expect "Framework" {
    wait_and_enter
}

# Select: Package manager — press enter (npm).
expect "Package manager" {
    wait_and_enter
}

# Select: License — press enter (MIT).
expect "License" {
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the widget-multiselect.php playground.
 *
 * Records all 3 multiselect prompts. Static screenshot captured at the first.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createWidgetMultiselectExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc toggle_space {} {
    sleep 0.3
    safe_send " "
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

spawn php {$playground_script}

# Multiselect: Features — select first, press enter.
expect "Features" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Multiselect: CI checks — select first, press enter.
expect "CI checks" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Multiselect: Integrations — select first, press enter.
expect "Integrations" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for the widget-confirm.php playground.
 *
 * Records all 3 confirm prompts. Static screenshot is captured at the first.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the playground PHP script.
 */
function createWidgetConfirmExpectScript(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

spawn php {$playground_script}

# Confirm: Install dependencies — type "y".
expect "Install dependencies" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Confirm: Enable telemetry — type "n".
expect "telemetry" {
    sleep {$delay}
    type_text "n"
    wait_and_enter
}

# Confirm: Run migrations — type "y".
expect "migrations" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Remove a directory recursively.
 *
 * @param string $directory
 *   Path to the directory to remove.
 */
function removeDir(string $directory): void {
  if (!is_dir($directory)) {
    return;
  }

  $cmd = sprintf('rm -rf %s 2>&1', escapeshellarg($directory));
  shell_exec($cmd);
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

// Entrypoint.
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
  // Worker mode: process a single recording.
  $record_index = array_search('--record', $argv);
  if ($record_index !== FALSE && isset($argv[$record_index + 1])) {
    processOne($argv[$record_index + 1]);
  }
  else {
    // Orchestrator mode: launch all in parallel.
    main();
  }
}
catch (\Exception $exception) {
  info('');
  info('ERROR: ' . $exception->getMessage());
  exit(1);
}
