<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Prompty unit tests.
 *
 * Delegates to PromptyTestTrait for reflection and output helpers.
 */
abstract class PromptyTestCase extends TestCase {

  use PromptyTestTrait;

  /**
   * Environment variables set during the test.
   *
   * @var list<string>
   */
  protected array $envKeys = [];

  /**
   * Default widget context array.
   *
   * @param array<string, mixed> $overrides
   *   Overrides.
   *
   * @return array<string, mixed>
   *   Context array.
   */
  protected function defaultCtx(array $overrides = []): array {
    return array_merge(['depth' => 0, 'is_last' => FALSE, 'open' => [], 'number' => NULL, 'discovered' => NULL], $overrides);
  }

  /**
   * Create a Prompty instance and set it as the singleton.
   */
  protected function createAndSetInstance(array $config = [], bool $in_flow = FALSE): Prompty {
    $p = $this->promptyCreateInstance($config);
    $this->promptySetStatic('instance', $p);

    if ($in_flow) {
      $this->promptySetStatic('inFlow', TRUE);
    }

    return $p;
  }

  /**
   * Set environment variables for a test.
   *
   * @param array<string, string> $vars
   *   Key-value pairs to set.
   * @param string $prefix
   *   Env prefix.
   */
  protected function setEnvVars(array $vars, string $prefix = 'PROMPTY_'): void {
    foreach ($vars as $key => $value) {
      $env_key = $prefix . strtoupper($key);
      putenv($env_key . '=' . $value);
      $this->envKeys[] = $env_key;
    }
  }

  /**
   * Clear environment variables after a test.
   *
   * @param array<string> $keys
   *   Keys to clear.
   * @param string $prefix
   *   Env prefix.
   */
  protected function clearEnvVars(array $keys, string $prefix = 'PROMPTY_'): void {
    foreach ($keys as $key) {
      putenv($prefix . strtoupper($key));
    }
  }

  protected function createInstance(array $config = []): Prompty {
    return $this->promptyCreateInstance($config);
  }

  protected function callProtected(object $instance, string $method, mixed ...$args): mixed {
    return $this->promptyCallProtected($instance, $method, ...$args);
  }

  /**
   * Call a protected method and assert it returns an array of strings.
   *
   * @return string[]
   *   The array of strings returned by the method.
   */
  protected function callProtectedLines(object $instance, string $method, mixed ...$args): array {
    $result = $this->promptyCallProtected($instance, $method, ...$args);
    $this->assertIsArray($result);
    $lines = [];
    foreach ($result as $item) {
      $this->assertIsString($item);
      $lines[] = $item;
    }

    return $lines;
  }

  protected function setStaticProperty(string $name, mixed $value): void {
    $this->promptySetStatic($name, $value);
  }

  protected function getStaticProperty(string $name): mixed {
    return $this->promptyGetStatic($name);
  }

  protected function getProperty(object $instance, string $name): mixed {
    return $this->promptyGetProperty($instance, $name);
  }

  protected function setProperty(object $instance, string $name, mixed $value): void {
    $this->promptySetProperty($instance, $name, $value);
  }

  protected function stripAnsi(string $text): string {
    return $this->promptyStripAnsi($text);
  }

  /**
   * Run a callable and return its ANSI-stripped output.
   *
   * The buffer is closed even when the callable throws, so a test asserting on
   * an exception does not leak its buffer into the next test.
   */
  protected function captureOutput(callable $fn): string {
    ob_start();

    try {
      $fn();
    }
    finally {
      $output = ob_get_clean();
    }

    return $this->promptyStripAnsi($output ?: '');
  }

  /**
   * Assert that a callable throws, and return the output produced before it.
   *
   * @param class-string<\Throwable> $exception
   *   Expected exception class.
   * @param string $message
   *   Expected exception message, matched exactly.
   * @param callable $fn
   *   The callable expected to throw.
   *
   * @return string
   *   ANSI-stripped output emitted before the throw.
   */
  protected function captureOutputThrows(string $exception, string $message, callable $fn): string {
    ob_start();

    try {
      $fn();
    }
    catch (\Throwable $throwable) {
      $output = ob_get_clean();

      $this->assertInstanceOf($exception, $throwable);
      $this->assertSame($message, $throwable->getMessage());

      return $this->promptyStripAnsi($output ?: '');
    }

    $this->fail(sprintf('Expected %s was not thrown. Output: %s', $exception, ob_get_clean() ?: ''));
  }

  /**
   * Install a singleton whose input stream carries the given keystrokes.
   *
   * A widget reading past the end of the stream would otherwise fall through
   * to STDIN and block, so any test that can reach an interactive loop feeds
   * it from memory.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param bool $in_flow
   *   Whether to mark a flow as already running.
   *
   * @return resource
   *   The stream, positioned at the first keystroke.
   */
  protected function installKeystrokes(string $keystrokes, bool $in_flow = TRUE) {
    $p = $this->createAndSetInstance(['unicode' => FALSE], $in_flow);

    $stream = fopen('php://memory', 'r+') ?: NULL;
    $this->assertNotNull($stream);
    fwrite($stream, $keystrokes);
    rewind($stream);
    $this->setProperty($p, 'input', $stream);

    return $stream;
  }

  /**
   * Assert a widget call throws before it draws anything or reads a key.
   *
   * @param callable $run
   *   The widget call under test.
   * @param string $message
   *   The expected exception message.
   * @param string $keystrokes
   *   Raw keystroke bytes made available to the widget.
   */
  protected function assertWidgetRejects(callable $run, string $message, string $keystrokes = self::KEY_ENTER): void {
    $stream = $this->installKeystrokes($keystrokes);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, $run);

    $this->assertSame('', $output);
    $this->assertSame(0, ftell($stream));

    fclose($stream);
  }

  protected function tearDown(): void {
    array_map(putenv(...), $this->envKeys);
    $this->envKeys = [];

    $this->promptyTearDown();
    parent::tearDown();
  }

}
