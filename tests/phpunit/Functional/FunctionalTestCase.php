<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for functional tests.
 *
 * Runs scripts in subprocesses with simulated keystrokes and asserts on
 * stdout. Does not depend on Prompty internals.
 */
#[CoversNothing]
abstract class FunctionalTestCase extends TestCase {

  protected const KEY_ENTER = "\n";
  protected const KEY_SPACE = ' ';
  protected const KEY_BACKSPACE = "\x7f";
  protected const KEY_TAB = "\t";
  protected const KEY_ESCAPE = "\x1b";
  protected const KEY_CTRL_C = "\x03";
  protected const KEY_UP = "\x1b[A";
  protected const KEY_DOWN = "\x1b[B";
  protected const KEY_RIGHT = "\x1b[C";
  protected const KEY_LEFT = "\x1b[D";

  /**
   * Project root directory.
   */
  protected string $root;

  /**
   * Temporary directory for test artifacts.
   */
  protected string $tmpDir;

  protected function setUp(): void {
    parent::setUp();

    $this->root = dirname(__DIR__, 3);

    $short_class = (new \ReflectionClass(static::class))->getShortName();
    $this->tmpDir = $this->root . '/.artifacts/tmp/' . $short_class . '_' . getmypid();
    mkdir($this->tmpDir, 0755, TRUE);
  }

  protected function tearDown(): void {
    if (is_dir($this->tmpDir)) {
      $files = glob($this->tmpDir . '/*');
      if ($files !== FALSE) {
        array_map(unlink(...), $files);
      }
      rmdir($this->tmpDir);
    }

    parent::tearDown();
  }

  /**
   * Concatenate key constants into a keystroke string.
   */
  protected function keys(string ...$keys): string {
    return implode('', $keys);
  }

  /**
   * Assert that a PHP file passes lint checking.
   */
  protected function assertPhpLintPasses(string $path): void {
    $output = [];
    $exit_code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exit_code);
    $this->assertSame(0, $exit_code, 'PHP lint failed: ' . implode("\n", $output));
  }

  /**
   * Run a command in a subprocess with simulated keystrokes.
   *
   * @param string $command
   *   The command to execute.
   * @param string $keystrokes
   *   Simulated keyboard input.
   *
   * @return array{stdout: string, stderr: string, exit_code: int}
   *   Process output.
   */
  protected function runWithKeystrokes(string $command, string $keystrokes): array {
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);
    $this->assertIsResource($process);

    fwrite($pipes[0], $keystrokes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);

    return [
      'stdout' => $stdout ?: '',
      'stderr' => $stderr ?: '',
      'exit_code' => $exit_code,
    ];
  }

  /**
   * Strip ANSI escape codes from a string.
   */
  protected function stripAnsi(string $text): string {
    return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\x1b\[\?[0-9;]*[A-Za-z]/', '', $text);
  }

  /**
   * Run a PHP script with keystrokes and return stripped stdout.
   */
  protected function runScript(string $script_path, string $keystrokes): string {
    $r = $this->runWithKeystrokes('php ' . escapeshellarg($script_path), $keystrokes);
    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);

    return $this->stripAnsi($r['stdout']);
  }

  /**
   * Assert that a starter script produces expected output for a standard flow.
   *
   * Runs the script with keystrokes that select: name=my-project,
   * framework=Vue, features=[TypeScript], install=Yes.
   */
  protected function assertStarterFlowWorks(string $script_path): void {
    $keystrokes = $this->keys(
      'my-project', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_SPACE, self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $output = $this->runScript($script_path, $keystrokes);

    // Intro and outro.
    $this->assertStringContainsString('Create a new project', $output);
    $this->assertStringContainsString('Project created!', $output);

    // Completed values.
    $this->assertStringContainsString('my-project', $output);
    $this->assertStringContainsString('Vue', $output);
    $this->assertStringContainsString('TypeScript', $output);
    $this->assertStringContainsString('Yes', $output);
  }

}
