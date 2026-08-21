#!/usr/bin/env php
<?php

/**
 * @file
 * Generate SVG assets from asciinema recordings.
 *
 * Records terminal sessions for seven playground scripts in four flag
 * variants each, plus the embedded copy of the flow. The widgets, flow,
 * flow-nested and flow-embed recordings render as animated SVGs; the
 * widget-* recordings render as static single-frame SVGs.
 *
 * Recordings run in small batches: enough of them at once to finish in
 * reasonable time, few enough that the machine does not stall a session
 * being recorded and change where its frames fall.
 *
 * Dependencies: asciinema, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 * - SCRIPT_KEEP_CASTS: Set to '1' to keep the recordings for inspection.
 *
 * Usage:
 * @code
 * php .util/update-assets.php
 * php .util/update-assets.php --record widgets
 * @endcode
 */

declare(strict_types=1);

define('TERMINAL_COLS', 80);
define('TERMINAL_ROWS', 24);

// Delay before interacting with prompts in expect scripts (seconds).
define('PROMPT_DELAY', 1);

// Delay between typed characters (seconds). Fixed rather than humanised, so
// the recording splits into the same frames on every run.
define('TYPE_DELAY', 0.02);

// Sleep before a keypress that moves within a widget (seconds). Long enough
// to sit clear of MERGE_WINDOW; the rendered pacing does not depend on it.
define('RECORD_STEP_SLEEP', 0.7);

// Recordings running at the same time.
define('MAX_WORKERS', 4);

// Output arriving within this many seconds of the previous event belongs to
// the same step, and is merged into it. It sits well above the gap between
// typed characters and well below the shortest sleep between steps, so the
// same steps survive however the terminal happened to chunk the output.
define('MERGE_WINDOW', 0.35);

// Rendered gap between one step and the next (seconds). Long enough to read
// the prompt that was just answered.
define('STEP_DELAY', 1.0);

// Rendered gap between the redraws within one step (seconds), which is the
// speed typing plays back at.
define('FRAME_DELAY', 0.1);

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
 *   optionally at and cols (for static screenshots).
 */
function getJobs(string $project_dir): array {
  $variants = ['' => '', '-ascii' => ' --no-unicode', '-no-ansi' => ' --no-ansi', '-ascii-no-ansi' => ' --no-unicode --no-ansi'];

  $animated_bases = [
    'widgets' => ['script' => $project_dir . '/playground/widgets.php', 'expect_fn' => 'createWidgetsExpectScript', 'rows' => 18],
    'flow' => ['script' => $project_dir . '/playground/flow.php', 'expect_fn' => 'createFlowExpectScript', 'rows' => 20],
    'flow-nested' => ['script' => $project_dir . '/playground/flow-nested.php', 'expect_fn' => 'createFlowNestedExpectScript', 'rows' => 20],
  ];

  $jobs = [];

  foreach ($animated_bases as $base_name => $base_job) {
    foreach ($variants as $suffix => $flags) {
      $jobs[$base_name . $suffix] = ['script' => $base_job['script'] . $flags, 'expect_fn' => $base_job['expect_fn'], 'rows' => $base_job['rows']];
    }
  }

  $static_bases = [
    'widget-text' => [
      'script' => $project_dir . '/playground/widget-text.php',
      'expect_fn' => 'createWidgetTextExpectScript',
      'at' => 500,
      'rows' => 8,
      'cols' => 40,
    ],
    'widget-select' => [
      'script' => $project_dir . '/playground/widget-select.php',
      'expect_fn' => 'createWidgetSelectExpectScript',
      'at' => 500,
      'rows' => 10,
      'cols' => 40,
    ],
    'widget-multiselect' => [
      'script' => $project_dir . '/playground/widget-multiselect.php',
      'expect_fn' => 'createWidgetMultiselectExpectScript',
      'at' => 500,
      'rows' => 10,
      'cols' => 40,
    ],
    'widget-confirm' => [
      'script' => $project_dir . '/playground/widget-confirm.php',
      'expect_fn' => 'createWidgetConfirmExpectScript',
      'at' => 500,
      'rows' => 7,
      'cols' => 40,
    ],
  ];

  foreach ($static_bases as $base_name => $base_job) {
    foreach ($variants as $suffix => $flags) {
      $jobs[$base_name . $suffix] = array_merge($base_job, ['script' => $base_job['script'] . $flags]);
    }
  }

  // The embedded copy of the flow, recorded so that a change in what an
  // embedded script draws shows up as a diff. Nothing embeds this asset; it
  // is kept for the comparison rather than for the README.
  $jobs['flow-embed'] = [
    'script' => $project_dir . '/playground/flow-embed.dist.php',
    'expect_fn' => 'createFlowEmbedExpectScript',
    'prepare_fn' => 'buildEmbeddedPlayground',
    'rows' => 20,
  ];

  return $jobs;
}

/**
 * Main functionality - orchestrator mode.
 *
 * Launches all recordings as parallel worker processes.
 */
function main(): void {
  $script_dir = __DIR__;
  $project_dir = dirname($script_dir);
  $assets_dir = $script_dir . '/assets';

  info('Prompty - Asset Generator');
  info('========================');
  info('');

  checkDependencies();
  installNodeDependencies($script_dir);

  $jobs = getJobs($project_dir);
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0700, TRUE);
  }

  $script_path = __FILE__;

  $failed = [];

  // A recording is timed against the machine it runs on, and a machine running
  // every recording at once stalls each of them unpredictably. Batches keep
  // the load low enough for the pauses in a session to stay apart from the
  // gaps within one, which is what makes the output reproducible.
  info(sprintf('Recording %d sessions, %d at a time...', count($jobs), MAX_WORKERS));
  info('');

  foreach (array_chunk($jobs, MAX_WORKERS, TRUE) as $batch) {
    $processes = [];
    $pipes_list = [];

    foreach ($batch as $name => $job) {
      $cmd = sprintf('php %s --record %s', escapeshellarg($script_path), escapeshellarg($name));

      $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

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

    foreach ($processes as $name => $process) {
      ['stdout' => $stdout, 'stderr' => $stderr] = drainPipes($pipes_list[$name][1], $pipes_list[$name][2]);
      fclose($pipes_list[$name][1]);
      fclose($pipes_list[$name][2]);

      $exit = proc_close($process);

      if ($exit !== 0) {
        $failed[$name] = trim($stdout . $stderr);
        info('  FAILED: ' . $name);
      }
      else {
        info('  Done: ' . $name);
      }
    }
  }

  // Reset terminal - workers may leave it in raw mode.
  shell_exec('stty sane 2>/dev/null');

  info('');

  if (getenv('SCRIPT_KEEP_CASTS') === '1') {
    info('Keeping recordings: ' . $tmp_dir);
  }
  else {
    info('Cleaning up: ' . $tmp_dir);
    removeDir($tmp_dir);
  }

  if ($failed !== []) {
    error('');
    error('Errors:');
    foreach ($failed as $name => $output) {
      error('  ' . $name . ': ' . $output);
    }
    throw new \RuntimeException('Failed to generate ' . count($failed) . ' asset(s).');
  }

  info('');
  info('Done. ' . count($jobs) . ' SVG assets updated in ' . $assets_dir);
}

/**
 * Worker mode - process a single recording.
 *
 * @param string $name
 *   The job name to process.
 */
function processOne(string $name): void {
  $script_dir = __DIR__;
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
  $cols = $job['cols'] ?? TERMINAL_COLS;
  $at = $job['at'] ?? NULL;

  if (!is_dir($tmp_dir) && !mkdir($tmp_dir, 0700, TRUE) && !is_dir($tmp_dir)) {
    throw new \RuntimeException('Could not create ' . $tmp_dir);
  }

  if (isset($job['prepare_fn'])) {
    $prepare_fn = $job['prepare_fn'];
    $prepare_fn($project_dir);
  }

  $expect_fn = $job['expect_fn'];
  $expect_fn($job['script'], $expect_script);

  recordSession($expect_script, $cast_file, $rows, $cols);
  postProcessCast($cast_file);
  convertToSvg($cast_file, $svg_file, $script_dir, $at);
}

/**
 * Build the embedded copy of the flow playground.
 *
 * The copy is generated and is not committed, so the recording builds it
 * rather than assuming a copy is lying around.
 *
 * @param string $project_dir
 *   Path to the project root.
 *
 * @throws \RuntimeException
 *   When the embedder fails.
 */
function buildEmbeddedPlayground(string $project_dir): void {
  $cmd = sprintf(
    'php %s --no-verify %s %s 2>&1',
    escapeshellarg($project_dir . '/embed.php'),
    escapeshellarg($project_dir . '/playground/flow-embed.php'),
    escapeshellarg($project_dir . '/playground/flow-embed.dist.php'),
  );

  $output = [];
  $exit = 0;
  exec($cmd, $output, $exit);

  if ($exit !== 0) {
    throw new \RuntimeException('Could not build the embedded playground: ' . implode(PHP_EOL, $output));
  }
}
/**
 * Check that all required dependencies are installed.
 */
function checkDependencies(): void {
  $deps = ['asciinema', 'expect', 'node', 'npm'];
  $missing = [];

  foreach ($deps as $dep) {
    $cmd = sprintf('which %s 2>/dev/null', escapeshellarg($dep));
    if (empty(shell_exec($cmd))) {
      $missing[] = $dep;
    }
  }

  if ($missing !== []) {
    throw new \RuntimeException('Missing required dependencies: ' . implode(', ', $missing));
  }

  info('All dependencies found.');
}

/**
 * Install Node.js dependencies for svg-term rendering.
 *
 * @param string $script_dir
 *   Path to the .util directory containing svg-term-render.js.
 */
function installNodeDependencies(string $script_dir): void {
  info('Installing svg-term Node.js dependency...');

  $node_modules_dir = $script_dir . '/node_modules';
  if (is_dir($node_modules_dir . '/svg-term')) {
    info('svg-term already installed.');

    return;
  }

  $cmd = sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg($script_dir));
  $output = shell_exec($cmd);
  if (!is_dir($node_modules_dir . '/svg-term')) {
    throw new \RuntimeException('Failed to install svg-term: ' . ($output ?? 'unknown error'));
  }

  info('svg-term installed.');
}

/**
 * Record a session using asciinema with an expect script.
 *
 * @param string $expect_script
 *   Path to the expect script for automation.
 * @param string $cast_file
 *   Path to write the cast file.
 * @param int $rows
 *   Number of terminal rows.
 * @param int $cols
 *   Number of terminal columns.
 */
function recordSession(string $expect_script, string $cast_file, int $rows = TERMINAL_ROWS, int $cols = TERMINAL_COLS): void {
  if (!is_file($expect_script)) {
    throw new \RuntimeException('Missing expect script: ' . $expect_script);
  }

  $cmd = sprintf(
    'asciinema rec --command=%s --window-size=%dx%d --idle-time-limit=%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    $cols,
    $rows,
    MAX_IDLE_TIME,
    escapeshellarg($cast_file)
  );

  $output = shell_exec($cmd);

  if (!is_file($cast_file)) {
    throw new \RuntimeException('Failed to record session: ' . $cast_file . PHP_EOL . ($output ?? ''));
  }
}

/**
 * Post-process a cast file.
 *
 * Rewrites the events onto a canonical timeline, appends an END_PAUSE event
 * so the animation pauses before looping, and sanitizes paths.
 *
 * @param string $cast_file
 *   Path to the cast file.
 */
function postProcessCast(string $cast_file): void {
  $content = file_get_contents($cast_file);
  if ($content === FALSE) {
    return;
  }

  $filtered = canonicalizeCast(explode("\n", $content));

  // Add a pause at the end of the recording before the animation loops.
  // In asciicast v3, timestamps are relative (delta from previous event),
  // so the pause is added as an empty output event with that duration.
  $filtered[] = json_encode([END_PAUSE, 'o', ' ']);

  $content = implode("\n", $filtered);

  $home = getenv('HOME');
  if ($home !== FALSE && $home !== '') {
    $content = str_replace($home, '/home/user', $content);
  }

  file_put_contents($cast_file, $content);
}

/**
 * Rewrite a recording's events onto a canonical timeline.
 *
 * A recording carries whatever chunks the terminal happened to deliver, at
 * whatever moment the scheduler delivered them, so two runs of one session
 * produce different frames and different durations even when nothing about
 * the session changed. The output is therefore treated as one stream and cut
 * into frames where the session itself redrew, which no longer depends on how
 * the chunks fell. The recorded times survive only as a two-way choice: a
 * frame that follows within MERGE_WINDOW continues the step before it and
 * plays at FRAME_DELAY, and anything slower begins a step and plays at
 * STEP_DELAY.
 *
 * The spawn echo is dropped from that stream rather than from the events it
 * arrived in, so a terminal that splits the echo, or delivers it joined to
 * the output after it, cannot leave part of it in the render or take real
 * output out with it.
 *
 * @param array<int, string> $lines
 *   Cast lines, the header first.
 *
 * @return array<int, string>
 *   Canonical cast lines, the header first.
 */
function canonicalizeCast(array $lines): array {
  $header = array_shift($lines) ?? '';
  $stream = '';
  $arrivals = [];
  $time = 0.0;

  foreach ($lines as $line) {
    $event = json_decode(trim($line), TRUE);

    if (!is_array($event) || count($event) < 3 || !is_numeric($event[0]) || !is_string($event[2])) {
      continue;
    }

    // A non-output event draws nothing, but the time before it still passed.
    $time += (float) $event[0];

    if ($event[1] !== 'o') {
      continue;
    }

    $arrivals[strlen($stream)] = $time;
    $stream .= $event[2];
  }

  [$stream, $arrivals] = stripSpawnEcho($stream, $arrivals);

  $canonical = [$header];
  $offset = 0;
  $previous = 0.0;

  foreach (splitRedraws($stream) as $index => $frame) {
    $arrival = arrivalAt($arrivals, $offset);
    $delay = $arrival - $previous < MERGE_WINDOW ? FRAME_DELAY : STEP_DELAY;
    $canonical[] = json_encode([$index === 0 ? 0 : $delay, 'o', $frame]);
    $previous = $arrival;
    $offset += strlen($frame);
  }

  return $canonical;
}

/**
 * Remove the spawn echo from the head of a session's output.
 *
 * Expect announces the command it spawns, so a recording opens with a line
 * that belongs to the harness rather than to the session. The echo runs to
 * the first line break after it.
 *
 * @param string $stream
 *   The session's output.
 * @param array<int, float> $arrivals
 *   Times the recording captured, keyed by the offset each chunk starts at.
 *
 * @return array{string, array<int, float>}
 *   The output past the echo, and the arrival times rebased onto it.
 */
function stripSpawnEcho(string $stream, array $arrivals): array {
  if (!str_starts_with($stream, 'spawn ')) {
    return [$stream, $arrivals];
  }

  $break = strpos($stream, "\n");

  if ($break === FALSE) {
    return [$stream, $arrivals];
  }

  $cut = $break + 1;
  $rebased = [];

  // A chunk that starts inside the echo holds the first output after it, so
  // that chunk's time becomes the arrival of the rebased stream.
  foreach ($arrivals as $start => $at) {
    $rebased[max(0, $start - $cut)] = $at;
  }

  return [substr($stream, $cut), $rebased];
}

/**
 * Return the time the chunk holding an offset arrived.
 *
 * @param array<int, float> $arrivals
 *   Times the recording captured, keyed by the offset each chunk starts at.
 * @param int $offset
 *   Offset into the stream.
 *
 * @return float
 *   Seconds from the start of the recording.
 */
function arrivalAt(array $arrivals, int $offset): float {
  $time = 0.0;

  foreach ($arrivals as $start => $at) {
    if ($start > $offset) {
      break;
    }

    $time = $at;
  }

  return $time;
}

/**
 * Split a session's output into the redraws it is made of.
 *
 * A renderer only paints the state an event ends in, so the frames have to be
 * cut where the session redrew. A widget starts every redraw by moving the
 * cursor up, so the sequence that does it marks where one frame ends and the
 * next begins - a boundary the output itself carries, rather than one the
 * recording timings happened to fall on.
 *
 * @param string $data
 *   The session's output.
 *
 * @return list<string>
 *   One entry per redraw, in order.
 */
function splitRedraws(string $data): array {
  $frames = preg_split('/(?=\x1b\[[0-9]+A)/', $data, -1, PREG_SPLIT_NO_EMPTY);

  return $frames === FALSE ? [$data] : array_values($frames);
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
 * @param string $script_dir
 *   Path to the .util directory containing svg-term-render.js.
 * @param int|null $at
 *   Optional timestamp in ms to capture a static frame.
 */
function convertToSvg(string $cast_file, string $svg_file, string $script_dir, ?int $at = NULL): void {
  $renderer_script = $script_dir . '/svg-term-render.js';

  $at_flag = $at !== NULL ? sprintf(' --at %d', $at) : '';
  $cmd = sprintf('node %s %s %s --line-height 1.1%s 2>&1', escapeshellarg($renderer_script), escapeshellarg($cast_file), escapeshellarg($svg_file), $at_flag);

  $output = shell_exec($cmd);

  if (!is_file($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . PHP_EOL . ($output ?? ''));
  }
}

/**
 * Create an expect script for the widgets.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createWidgetsExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
  $step_delay = RECORD_STEP_SLEEP;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

proc arrow_down {} {
    sleep {$step_delay}
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep {$step_delay}
    safe_send " "
}

spawn php {$playground_script}

# Text: Dish name - type "apple crumble" and press enter.
expect "Dish name" {
    sleep {$delay}
    type_text "apple crumble"
    wait_and_enter
}

# Select: Course - arrow down to Main, press enter.
expect "Course" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Extras - select Olives and Herbs.
expect "Extras" {
    sleep {$delay}
    arrow_down
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Send order - type "y".
expect "Send order" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the flow.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createFlowExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
  $step_delay = RECORD_STEP_SLEEP;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

proc arrow_down {} {
    sleep {$step_delay}
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep {$step_delay}
    safe_send " "
}

spawn php {$playground_script}

# Text: Dish name - type "plum compote" and press enter.
expect "Dish name" {
    sleep {$delay}
    type_text "plum compote"
    wait_and_enter
}

# Select: Course - arrow down to Main, press enter.
expect "Course" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Extras - select Olives and Lemon.
expect "Extras" {
    sleep {$delay}
    arrow_down
    toggle_space
    arrow_down
    arrow_down
    toggle_space
    wait_and_enter
}

# Select: Table - arrow down to Table 12, press enter.
expect "Table" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Seats - add Seat 1 to the pre-checked Seat 2 and Seat 4.
expect "Seats" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Confirm: Send order - type "y".
expect "Send order" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the embedded flow playground.
 *
 * The embedded copy runs the same four prompts as flow-embed.php, so what it
 * draws is what that flow draws unless the embedder changed something.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createFlowEmbedExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
  $step_delay = RECORD_STEP_SLEEP;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

proc arrow_down {} {
    sleep {$step_delay}
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep {$step_delay}
    safe_send " "
}

spawn php {$playground_script}

# Text: Dish name - type "plum compote" and press enter.
expect "Dish name" {
    sleep {$delay}
    type_text "plum compote"
    wait_and_enter
}

# Select: Course - arrow down to Main, press enter.
expect "Course" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Extras - select Olives and Lemon.
expect "Extras" {
    sleep {$delay}
    arrow_down
    toggle_space
    arrow_down
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Send order - type "y".
expect "Send order" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}
/**
 * Create an expect script for the flow-nested.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createFlowNestedExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
  $step_delay = RECORD_STEP_SLEEP;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

proc arrow_down {} {
    sleep {$step_delay}
    safe_send "\\033\[B"
}

proc toggle_space {} {
    sleep {$step_delay}
    safe_send " "
}

spawn php {$playground_script}

# Select: Course - arrow down to Main, press enter.
expect "Course" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Select: Method - press enter (Baked).
expect "Method" {
    wait_and_enter
}

# Select: Temperature - arrow down to Moderate, press enter.
expect "Temperature" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Confirm: Rest before serving - type "y".
expect "Rest before serving" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Confirm: Sauce on the side - type "y".
expect "Sauce on the side" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Multiselect: Extras - select Bread and Olives.
expect "Extras" {
    sleep {$delay}
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Confirm: Toast the bread - type "y".
expect "Toast the bread" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Select: Marinade - down to Chilli, press enter.
expect "Marinade" {
    sleep {$delay}
    arrow_down
    wait_and_enter
}

# Multiselect: Drinks - select Tea and Coffee.
expect "Drinks" {
    sleep {$delay}
    toggle_space
    arrow_down
    toggle_space
    wait_and_enter
}

# Select: Brew - press enter (Black).
expect "Brew" {
    wait_and_enter
}

# Select: Roast - press enter (Light).
expect "Roast" {
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the widget-text.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createWidgetTextExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

spawn php {$playground_script}

# Text: Dish name - type dish and press enter.
expect "Dish name" {
    sleep {$delay}
    type_text "onion soup"
    wait_and_enter
}

# Text: Guest name - type name and press enter.
expect "Guest name" {
    sleep {$delay}
    type_text "Jane Doe"
    wait_and_enter
}

# Text: Allergy note - type note and press enter.
expect "Allergy note" {
    sleep {$delay}
    type_text "no nuts"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the widget-select.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createWidgetSelectExpectScript(string $playground_script, string $expect_script): void {
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

spawn php {$playground_script}

# Select: Course - press enter (Starter).
expect "Course" {
    wait_and_enter
}

# Select: Dish - press enter (Pear Tart).
expect "Dish" {
    wait_and_enter
}

# Select: Method - press enter (Baked).
expect "Method" {
    wait_and_enter
}

# Select: Course with default - press enter (Main, pre-focused).
expect "Course" {
    wait_and_enter
}

# Select: Table - press enter (Table 7, pre-focused by a numeric default).
expect "Table" {
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the widget-multiselect.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createWidgetMultiselectExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $step_delay = RECORD_STEP_SLEEP;
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
    sleep {$step_delay}
    safe_send " "
}

spawn php {$playground_script}

# Multiselect: Extras - select first, press enter.
expect "Extras" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Multiselect: Drinks - select first, press enter.
expect "Drinks" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Multiselect: Finishes - select first, press enter.
expect "Finishes" {
    sleep {$delay}
    toggle_space
    wait_and_enter
}

# Multiselect: Extras opt-out - press enter (all pre-checked).
expect "Extras" {
    wait_and_enter
}

# Multiselect: Seats - press enter (pre-checked by an integer default).
expect "Seats" {
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Create an expect script for the widget-confirm.php playground.
 *
 * @param string $playground_script
 *   Path to the playground PHP script.
 * @param string $expect_script
 *   Path to write the expect script.
 */
function createWidgetConfirmExpectScript(string $playground_script, string $expect_script): void {
  $delay = PROMPT_DELAY;
  $type_delay = TYPE_DELAY;
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
    foreach ch [split \$text ""] {
        safe_send \$ch
        sleep {$type_delay}
    }
}

spawn php {$playground_script}

# Confirm: Send order - type "y".
expect "Send order" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Confirm: Add garnish - type "n".
expect "garnish" {
    sleep {$delay}
    type_text "n"
    wait_and_enter
}

# Confirm: Fire the mains - type "y".
expect "Fire the mains" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

expect eof
EXPECT;

  file_put_contents($expect_script, $content);
  chmod($expect_script, 0700);
}

/**
 * Read a worker's stdout and stderr until both reach EOF.
 *
 * Both pipes are polled together, so a worker that fills one pipe buffer
 * cannot block while this waits on the other. Returns once the worker has
 * closed both ends, so the captured output is complete enough to diagnose a
 * failure.
 *
 * A stream_select() failure ends the loop early and returns whatever was
 * read up to that point.
 *
 * @param resource $stdout_pipe
 *   The worker's stdout pipe.
 * @param resource $stderr_pipe
 *   The worker's stderr pipe.
 *
 * @return array{stdout: string, stderr: string}
 *   The captured stdout and stderr.
 */
function drainPipes($stdout_pipe, $stderr_pipe): array {
  $stdout = '';
  $stderr = '';
  $open = [$stdout_pipe, $stderr_pipe];

  while ($open !== []) {
    $read = $open;
    $write = NULL;
    $except = NULL;

    if (stream_select($read, $write, $except, NULL) === FALSE) {
      break;
    }

    foreach ($read as $stream) {
      $chunk = fread($stream, 8192);

      if ($chunk === FALSE || $chunk === '') {
        if (feof($stream)) {
          $open = array_values(array_filter($open, fn($candidate): bool => $candidate !== $stream));
        }

        continue;
      }

      if ($stream === $stdout_pipe) {
        $stdout .= $chunk;
      }
      else {
        $stderr .= $chunk;
      }
    }
  }

  return ['stdout' => $stdout, 'stderr' => $stderr];
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
  $record_index = array_search('--record', $argv);
  if ($record_index !== FALSE && isset($argv[$record_index + 1])) {
    processOne($argv[$record_index + 1]);
  }
  else {
    main();
  }
}
catch (\Exception $exception) {
  error('');
  error('ERROR: ' . $exception->getMessage());
  exit(1);
}
