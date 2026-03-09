<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty;

/**
 * PromptyTestTrait — testing helper for Prompty interactive prompts.
 *
 * Ships alongside Prompty.php. Include in your PHPUnit test case to simulate
 * keystrokes, capture output, and assert on results without a real TTY.
 *
 * Usage:
 * @code
 *   use PHPUnit\Framework\TestCase;
 *   require_once __DIR__ . '/PromptyTestTrait.php';
 *
 *   class MyTest extends TestCase {
 *     use PromptyTestTrait;
 *
 *     public function testMyFlow(): void {
 *       $r = $this->promptyRun(function (): void {
 *         putenv('PROMPTY_STOP=1');
 *         require 'my-installer.php';
 *       }, self::KEY_DOWN . self::KEY_ENTER);
 *
 *       $this->assertSame('vue', $r['result']['framework']);
 *     }
 *   }
 * @endcode
 */
trait PromptyTestTrait {

  public const KEY_ENTER = "\n";
  public const KEY_SPACE = ' ';
  public const KEY_BACKSPACE = "\x7f";
  public const KEY_TAB = "\t";
  public const KEY_ESCAPE = "\x1b";
  public const KEY_CTRL_C = "\x03";
  public const KEY_UP = "\x1b[A";
  public const KEY_DOWN = "\x1b[B";
  public const KEY_RIGHT = "\x1b[C";
  public const KEY_LEFT = "\x1b[D";

  /**
   * Run a callable with simulated keystrokes and capture Prompty output.
   *
   * Sets up the Prompty singleton in ASCII mode, injects a keystroke stream,
   * runs the callable, then returns the captured output and any return value.
   *
   * @param callable $callback
   *   The code to run. Typically a closure that calls Prompty widgets or
   *   requires a consumer script.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed (use KEY_* constants).
   * @param array<string, mixed> $config
   *   Prompty configuration overrides. Defaults to ASCII mode.
   *
   * @return array{result: mixed, output: string}
   *   - result: whatever the callback returns.
   *   - output: ANSI-stripped terminal output.
   */
  protected function promptyRun(callable $callback, string $keystrokes = '', array $config = []): array {
    $config = array_merge(['unicode' => FALSE], $config);
    $stream = NULL;

    if ($keystrokes !== '') {
      $stream = fopen('php://memory', 'r+') ?: NULL;
      if ($stream !== NULL) {
        fwrite($stream, $keystrokes);
        rewind($stream);
      }
    }

    // Create and configure the singleton instance.
    $instance = $this->promptyCreateInstance($config);

    // Inject keystroke stream into the instance.
    if ($stream !== NULL) {
      $this->promptySetProperty($instance, 'input', $stream);
    }

    $this->promptySetStatic('instance', $instance);
    $this->promptySetStatic('inFlow', TRUE);

    $result = NULL;
    ob_start();
    $result = $callback();
    $raw_output = ob_get_clean();

    $output = $this->promptyStripAnsi($raw_output ?: '');

    if ($stream !== NULL) {
      fclose($stream);
    }

    $this->promptySetStatic('instance', NULL);
    $this->promptySetStatic('inFlow', FALSE);

    return ['result' => $result, 'output' => $output];
  }

  /**
   * Run a consumer script with simulated keystrokes.
   *
   * Unlike promptyRun(), this does NOT pre-set the singleton or $inFlow.
   * The script's own Prompty::flow() call handles all setup. This method
   * only injects the keystroke stream and captures output.
   *
   * After calling this, use Prompty::results() to read collected answers.
   *
   * @param callable $callback
   *   Typically: function () { require 'my-script.php'; }.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   *
   * @return array{output: string}
   *   ANSI-stripped terminal output.
   */
  protected function promptyRunScript(callable $callback, string $keystrokes = ''): array {
    $stream = NULL;

    if ($keystrokes !== '') {
      $stream = fopen('php://memory', 'r+') ?: NULL;
      if ($stream !== NULL) {
        fwrite($stream, $keystrokes);
        rewind($stream);
      }
    }

    // Pre-create a singleton with the stream injected.
    // The script's flow() call will reuse this instance.
    $instance = $this->promptyCreateInstance();
    if ($stream !== NULL) {
      $this->promptySetProperty($instance, 'input', $stream);
    }
    $this->promptySetStatic('instance', $instance);

    ob_start();
    $callback();
    $raw_output = ob_get_clean();

    $output = $this->promptyStripAnsi($raw_output ?: '');

    // Clean up stream but leave singleton intact so results() works.
    if ($stream !== NULL) {
      fclose($stream);
    }

    return ['output' => $output];
  }

  /**
   * Strip ANSI escape codes from a string.
   */
  protected function promptyStripAnsi(string $text): string {
    return preg_replace('/\033\[[0-9;]*[A-Za-z]/', '', $text) ?? $text;
  }

  /**
   * Build a keystroke string from multiple KEY_* constants.
   *
   * @param string ...$keys
   *   Individual keystrokes.
   *
   * @return string
   *   Concatenated keystroke bytes.
   */
  protected function promptyKeys(string ...$keys): string {
    return implode('', $keys);
  }

  /**
   * Create a Prompty instance via reflection.
   *
   * @param array<string, mixed> $config
   *   Configuration overrides.
   *
   * @return Prompty
   *   A new instance.
   */
  protected function promptyCreateInstance(array $config = []): Prompty {
    $config = array_merge(['unicode' => FALSE], $config);
    $ref = new \ReflectionClass(Prompty::class);
    $instance = $ref->newInstanceWithoutConstructor();

    $property_map = [
      'unicode' => 'cfgUnicode',
      'env_prefix' => 'cfgEnvPrefix',
      'truthy' => 'cfgTruthy',
      'falsy' => 'cfgFalsy',
      'labels' => 'cfgLabels',
      'symbols_unicode' => 'cfgSymbolsUnicode',
      'symbols_ascii' => 'cfgSymbolsAscii',
      'colors' => 'cfgColors',
      'spacing' => 'cfgSpacing',
    ];

    foreach ($config as $key => $value) {
      if (isset($property_map[$key])) {
        $this->promptySetProperty($instance, $property_map[$key], $value);
      }
    }

    $constructor = $ref->getConstructor();
    if ($constructor !== NULL) {
      $constructor->invoke($instance);
    }

    return $instance;
  }

  /**
   * Call a protected method on a Prompty instance.
   */
  protected function promptyCallProtected(object $instance, string $method, mixed ...$args): mixed {
    $ref = new \ReflectionMethod($instance, $method);

    return $ref->invoke($instance, ...$args);
  }

  /**
   * Set a static property on Prompty.
   */
  protected function promptySetStatic(string $name, mixed $value): void {
    $ref = new \ReflectionProperty(Prompty::class, $name);
    $ref->setValue(NULL, $value);
  }

  /**
   * Get a static property from Prompty.
   */
  protected function promptyGetStatic(string $name): mixed {
    $ref = new \ReflectionProperty(Prompty::class, $name);

    return $ref->getValue();
  }

  /**
   * Get a protected instance property.
   */
  protected function promptyGetProperty(object $instance, string $name): mixed {
    $ref = new \ReflectionProperty($instance, $name);

    return $ref->getValue($instance);
  }

  /**
   * Set a protected instance property.
   */
  protected function promptySetProperty(object $instance, string $name, mixed $value): void {
    $ref = new \ReflectionProperty($instance, $name);
    $ref->setValue($instance, $value);
  }

  /**
   * Clean up Prompty singleton state.
   *
   * Call this in your tearDown() method.
   */
  protected function promptyTearDown(): void {
    $this->promptySetStatic('instance', NULL);
    $this->promptySetStatic('inFlow', FALSE);
  }

}
