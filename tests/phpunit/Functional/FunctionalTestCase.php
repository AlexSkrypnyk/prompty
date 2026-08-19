<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use AlexSkrypnyk\PhpunitHelpers\Traits\LocationsTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\TuiTrait;
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

  use LocationsTrait;
  use ProcessTrait;
  use TuiTrait;

  protected function setUp(): void {
    parent::setUp();
    $this->locationsInit();
  }

  protected function tearDown(): void {
    $this->locationsTearDown();
    parent::tearDown();
  }

  /**
   * Assertion suffix for ProcessTrait error messages.
   */
  protected function assertionSuffix(): string {
    return '';
  }

  protected function keys(string ...$keys): string {
    return implode('', $keys);
  }

  /**
   * Run a command in a subprocess with raw keystroke input.
   *
   * TUI escape sequences must reach stdin as raw bytes, so this method
   * bypasses processRun(), which joins inputs with newlines.
   *
   * @param string $command
   *   The command to execute.
   * @param string $keystrokes
   *   Raw keystroke bytes.
   *
   * @return array{stdout: string, stderr: string, exit_code: int}
   *   Process output.
   */
  protected function runWithKeystrokes(string $command, string $keystrokes): array {
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open($command, $descriptors, $pipes);
    $this->assertIsResource($process);

    fwrite($pipes[0], $keystrokes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);

    return ['stdout' => $stdout ?: '', 'stderr' => $stderr ?: '', 'exit_code' => $exit_code];
  }

  protected function stripAnsi(string $text): string {
    return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\x1b\[\?[0-9;]*[A-Za-z]/', '', $text);
  }

  protected function runScript(string $script_path, string $keystrokes): string {
    $r = $this->runWithKeystrokes('php ' . escapeshellarg($script_path), $keystrokes);
    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);

    return $this->stripAnsi($r['stdout']);
  }

  /**
   * Assert that a starter script produces expected output for a standard flow.
   *
   * Runs the script with keystrokes that select: dish=plum compote,
   * course=Main, extras=[Bread], send=Yes.
   */
  protected function assertStarterFlowWorks(string $script_path): void {
    $keystrokes = $this->keys(
      'plum compote', self::KEYS['ENTER'],
      self::KEYS['DOWN'], self::KEYS['ENTER'],
      self::KEYS['SPACE'], self::KEYS['ENTER'],
      self::KEYS['ENTER'],
    );

    $output = $this->runScript($script_path, $keystrokes);

    $this->assertStringContainsString('Compose an order', $output);
    $this->assertStringContainsString('Order sent!', $output);
    $this->assertStringContainsString('plum compote', $output);
    $this->assertStringContainsString('Main', $output);
    $this->assertStringContainsString('Bread', $output);
    $this->assertStringContainsString('Yes', $output);
  }

}
