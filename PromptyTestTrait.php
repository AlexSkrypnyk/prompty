<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty;

/**
 * PromptyTestTrait - testing helper for Prompty interactive prompts.
 *
 * Ships alongside Prompty.php. Include in a PHPUnit test case to simulate
 * keystrokes, capture output, and assert on results without a real TTY.
 *
 * Use promptyRun() when the callback returns the value under test:
 * @code
 *   use AlexSkrypnyk\Prompty\Prompty;
 *   use AlexSkrypnyk\Prompty\PromptyTestTrait;
 *   use PHPUnit\Framework\TestCase;
 *
 *   class MyTest extends TestCase {
 *     use PromptyTestTrait;
 *
 *     public function testMyFlow(): void {
 *       $r = $this->promptyRun(fn(): mixed => Prompty::flow()
 *         ->select('course', 'Course', options: [
 *           'starter' => 'Starter',
 *           'main' => 'Main',
 *         ])
 *         ->run(), self::KEY_DOWN . self::KEY_ENTER);
 *
 *       $this->assertSame('main', $r['result']['course']);
 *     }
 *   }
 * @endcode
 *
 * Use promptyRunScript() to drive a consumer script. Leave the script's kill
 * switch variable unset so it stops before doing real work, then read the
 * answers from Prompty::results():
 * @code
 *   $this->promptyRunScript(function (): void {
 *     require 'my-script.php';
 *   }, self::KEY_DOWN . self::KEY_ENTER);
 *
 *   $this->assertSame('main', Prompty::results()['course']);
 * @endcode
 *
 * Use promptyInput() when the code under test configures Prompty itself and
 * that configuration must survive:
 * @code
 *   Kitchen::bootstrap();
 *   $this->promptyInput($this->promptyKeys('pear tart', self::KEY_ENTER));
 *
 *   $this->assertSame('pear tart', Kitchen::takeOrder());
 * @endcode
 */
trait PromptyTestTrait {

  public const KEY_ENTER = "\n";
  public const KEY_SPACE = ' ';
  public const KEY_BACKSPACE = "\x7f";
  public const KEY_TAB = "\t";
  public const KEY_ESCAPE = "\x1b";
  public const KEY_CTRL_C = "\x03";
  public const KEY_CTRL_U = "\x15";
  public const KEY_UP = "\x1b[A";
  public const KEY_DOWN = "\x1b[B";
  public const KEY_RIGHT = "\x1b[C";
  public const KEY_LEFT = "\x1b[D";
  public const KEY_HOME = "\x1b[H";
  public const KEY_END = "\x1b[F";
  public const KEY_DELETE = "\x1b[3~";

  /**
   * The keystroke stream promptyInput() opened, closed by promptyTearDown().
   *
   * @var resource|null
   */
  protected $promptyInputStream;

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
   *   Raw keystroke bytes to feed (use KEY_* constants). A widget that reads
   *   past the last byte reaches the end of the stream and cancels.
   * @param array<string, mixed> $config
   *   Prompty configuration overrides. Defaults to ASCII mode, wrapping at 80
   *   columns.
   *
   * @return array{result: mixed, output: string}
   *   - result: whatever the callback returns.
   *   - output: terminal output, normalized by promptyNormalizeOutput().
   */
  protected function promptyRun(callable $callback, string $keystrokes = '', array $config = []): array {
    $stream = fopen('php://memory', 'r+') ?: NULL;

    if ($stream !== NULL) {
      fwrite($stream, $keystrokes);
      rewind($stream);
    }

    $instance = $this->promptyCreateInstance($config);

    if ($stream !== NULL) {
      $this->promptySetProperty($instance, 'input', $stream);
    }

    $this->promptySetStatic('instance', $instance);
    $this->promptySetStatic('isInFlow', TRUE);

    // The buffer and the singleton are released when the callback throws, so
    // a test that expects an exception leaves no state behind.
    ob_start();

    try {
      $result = $callback();
    }
    finally {
      $raw_output = ob_get_clean();

      if ($stream !== NULL) {
        fclose($stream);
      }

      $this->promptySetStatic('instance', NULL);
      $this->promptySetStatic('isInFlow', FALSE);
    }

    $output = $this->promptyNormalizeOutput($raw_output ?: '');

    return ['result' => $result, 'output' => $output];
  }

  /**
   * Run a consumer script with simulated keystrokes.
   *
   * Installs a singleton carrying the keystroke stream, which the script's
   * own Prompty::flow() call then reuses. Unlike promptyRun(), $isInFlow is
   * left FALSE and the singleton survives the call, so Prompty::results()
   * still reads the collected answers afterwards.
   *
   * @param callable $callback
   *   Typically: function () { require 'my-script.php'; }.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   *
   * @return array{output: string}
   *   Terminal output, normalized by promptyNormalizeOutput().
   */
  protected function promptyRunScript(callable $callback, string $keystrokes = ''): array {
    $stream = fopen('php://memory', 'r+') ?: NULL;

    if ($stream !== NULL) {
      fwrite($stream, $keystrokes);
      rewind($stream);
    }

    $instance = $this->promptyCreateInstance();

    if ($stream !== NULL) {
      $this->promptySetProperty($instance, 'input', $stream);
    }

    $this->promptySetStatic('instance', $instance);

    ob_start();

    try {
      $callback();
    }
    finally {
      $raw_output = ob_get_clean();

      if ($stream !== NULL) {
        fclose($stream);
      }
    }

    $output = $this->promptyNormalizeOutput($raw_output ?: '');

    return ['output' => $output];
  }

  /**
   * Feed keystrokes to the current Prompty instance.
   *
   * Only the input changes: the instance keeps every setting the code under
   * test configured, including its output target, and its collected answers.
   * When there is no instance, one is created with the same defaults as
   * promptyRun(). A later call replaces the keystrokes of an earlier one.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed (use KEY_* constants). A widget that reads
   *   past the last byte reaches the end of the stream and cancels.
   */
  protected function promptyInput(string $keystrokes): void {
    $instance = $this->promptyGetStatic('instance');

    if (!$instance instanceof Prompty) {
      $instance = $this->promptyCreateInstance();
      $this->promptySetStatic('instance', $instance);
    }

    $this->promptyCloseInput();

    $stream = fopen('php://memory', 'r+') ?: NULL;

    if ($stream !== NULL) {
      fwrite($stream, $keystrokes);
      rewind($stream);
    }

    $this->promptySetProperty($instance, 'input', $stream);
    $this->promptyInputStream = $stream;
  }

  /**
   * Close the keystroke stream promptyInput() opened, if it is still open.
   */
  protected function promptyCloseInput(): void {
    if (is_resource($this->promptyInputStream)) {
      fclose($this->promptyInputStream);
    }

    $this->promptyInputStream = NULL;
  }

  /**
   * Strip ANSI escape codes and fold line endings to "\n".
   *
   * Prompty ends each line with PHP_EOL, which is "\r\n" on Windows, so an
   * assertion written with "\n" holds on every platform.
   */
  protected function promptyNormalizeOutput(string $text): string {
    return str_replace(PHP_EOL, "\n", $this->promptyStripAnsi($text));
  }

  /**
   * Strip ANSI escape codes from a string.
   *
   * The optional '?' covers private-mode sequences such as the cursor
   * show/hide pair, which Prompty emits around every interactive widget.
   */
  protected function promptyStripAnsi(string $text): string {
    return preg_replace('/\033\[\??[0-9;:]*[A-Za-z]/', '', $text) ?? $text;
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
    $config = array_merge(['unicode' => FALSE, 'ansi' => TRUE, 'width' => 80], $config);
    $ref = new \ReflectionClass(Prompty::class);
    $instance = $ref->newInstanceWithoutConstructor();

    $property_map = [
      'unicode' => 'cfgUseUnicode',
      'ansi' => 'cfgUseAnsi',
      'env_prefix' => 'cfgEnvPrefix',
      'truthy' => 'cfgTruthy',
      'falsy' => 'cfgFalsy',
      'interactive' => 'cfgIsInteractive',
      'labels' => 'cfgLabels',
      'symbols_unicode' => 'cfgSymbolsUnicode',
      'symbols_ascii' => 'cfgSymbolsAscii',
      'colors' => 'cfgColors',
      'spacing' => 'cfgSpacing',
      'scroll' => 'cfgScroll',
      'placeholder_fallback' => 'cfgUsePlaceholderFallback',
      'width' => 'cfgWidth',
      'legend' => 'cfgShowLegend',
    ];

    foreach ($config as $key => $value) {
      if (isset($property_map[$key])) {
        $this->promptySetProperty($instance, $property_map[$key], $value);
      }
    }

    $constructor = $ref->getConstructor();

    if ($constructor instanceof \ReflectionMethod) {
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
   * Make a rejected answer throw instead of re-prompting.
   *
   * The first answer a validator rejects throws PromptyValidationException,
   * including a discovered or non-interactive answer. The switch stays on
   * across promptyRun() calls until promptyTearDown() or a call with FALSE.
   *
   * @param bool $throw
   *   TRUE to throw on a rejected answer, FALSE to re-prompt again.
   */
  protected function promptyThrowOnInvalid(bool $throw = TRUE): void {
    $handler = static function (string $label, string|bool|array $value, string $reason): never {
      /** @var string|bool|list<string> $value */
      throw new PromptyValidationException($label, $value, $reason);
    };

    $this->promptySetStatic('onInvalid', $throw ? $handler : NULL);
  }

  /**
   * Clean up Prompty singleton state.
   *
   * Call from the test case's tearDown() method.
   */
  protected function promptyTearDown(): void {
    $this->promptyCloseInput();
    $this->promptySetStatic('instance', NULL);
    $this->promptySetStatic('isInFlow', FALSE);
    $this->promptySetStatic('onInvalid', NULL);
  }

}

/**
 * Thrown for a rejected answer while promptyThrowOnInvalid() is on.
 *
 * Extends \InvalidArgumentException, the class a rejected discovered value
 * throws without the switch, so a catch for that class still matches.
 */
// It ships in this file so the test helper stays 1 release asset.
// phpcs:ignore Squiz.Classes.ClassFileName.NoMatch
class PromptyValidationException extends \InvalidArgumentException {

  /**
   * Constructs the exception.
   *
   * @param string $label
   *   The label of the widget that rejected the answer.
   * @param string|bool|list<string> $value
   *   The rejected answer, as the widget would return it.
   * @param string $reason
   *   The message the validator returned.
   */
  public function __construct(
    public readonly string $label,
    public readonly string|bool|array $value,
    public readonly string $reason,
  ) {
    $display = match (TRUE) {
      is_bool($value) => $value ? 'true' : 'false',
      is_array($value) => implode(',', $value),
      default => $value,
    };

    parent::__construct(sprintf('Value "%s" for "%s" is not valid. %s', $display, $label, $reason));
  }

}
