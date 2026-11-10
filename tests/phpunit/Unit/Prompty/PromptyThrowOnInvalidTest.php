<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the test harness switch that turns a rejected answer into an exception.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyThrowOnInvalidTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected const MESSAGE = 'Name the whole dish, such as "pear tart".';

  /**
   * Builds a validator that rejects every answer.
   */
  protected static function reject(): \Closure {
    return static fn(): string => self::MESSAGE;
  }

  #[DataProvider('dataProviderInteractiveRejectionThrows')]
  public function testInteractiveRejectionThrows(\Closure $widget, string $keystrokes, int $read, string $label, mixed $value, string $message): void {
    $this->promptyThrowOnInvalid();
    $stream = $this->installKeystrokes($keystrokes);

    try {
      $this->captureOutput(fn(): mixed => $widget($this->ctx()));
      $this->fail('A rejected answer did not throw.');
    }
    catch (PromptyValidationException $exception) {
      $this->assertSame($message, $exception->getMessage());
      $this->assertSame($label, $exception->label);
      $this->assertSame($value, $exception->value);
      $this->assertSame(self::MESSAGE, $exception->reason);
    }

    $this->assertSame($read, ftell($stream), 'The widget read past the rejected answer.');
    fclose($stream);
  }

  public static function dataProviderInteractiveRejectionThrows(): \Iterator {
    $keystrokes = static fn(string $answer): array => [$answer . self::KEY_ENTER . 'x' . self::KEY_ENTER, strlen($answer . self::KEY_ENTER)];

    [$text_keys, $text_read] = $keystrokes('pear');
    yield 'text' => [
      static fn(array $ctx): mixed => Prompty::text('Dish name', validate: self::reject(), ctx: $ctx),
      $text_keys,
      $text_read,
      'Dish name',
      'pear',
      'Value "pear" for "Dish name" is not valid. ' . self::MESSAGE,
    ];

    [$select_keys, $select_read] = $keystrokes(self::KEY_DOWN);
    yield 'select' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, validate: self::reject(), ctx: $ctx),
      $select_keys,
      $select_read,
      'Course',
      'main',
      'Value "main" for "Course" is not valid. ' . self::MESSAGE,
    ];

    [$multiselect_keys, $multiselect_read] = $keystrokes(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE);
    yield 'multiselect' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, validate: self::reject(), ctx: $ctx),
      $multiselect_keys,
      $multiselect_read,
      'Extras',
      ['bread', 'olives'],
      'Value "bread,olives" for "Extras" is not valid. ' . self::MESSAGE,
    ];

    [$confirm_keys, $confirm_read] = $keystrokes(self::KEY_LEFT);
    yield 'confirm' => [
      static fn(array $ctx): mixed => Prompty::confirm('Send order?', validate: self::reject(), ctx: $ctx),
      $confirm_keys,
      $confirm_read,
      'Send order?',
      FALSE,
      'Value "false" for "Send order?" is not valid. ' . self::MESSAGE,
    ];
  }

  public function testRejectionDrawsNoErrorFrame(): void {
    $this->promptyThrowOnInvalid();
    $stream = $this->installKeystrokes('pear' . self::KEY_ENTER);

    $output = $this->captureOutputThrows(PromptyValidationException::class, 'Value "pear" for "Dish name" is not valid. ' . self::MESSAGE, fn(): mixed => Prompty::text('Dish name', validate: self::reject(), ctx: $this->ctx()));

    $this->assertStringNotContainsString('! ' . self::MESSAGE, $output);
    fclose($stream);
  }

  #[DataProvider('dataProviderResolvedRejectionThrows')]
  public function testResolvedRejectionThrows(bool $interactive, mixed $discovered, string $message): void {
    $this->promptyThrowOnInvalid();
    $stream = $this->installKeystrokes(self::KEY_ENTER);
    Prompty::configure(interactive: $interactive);

    $output = $this->captureOutputThrows(PromptyValidationException::class, $message, fn(): mixed => Prompty::text('Dish name', default: 'pear', discovered: $discovered, validate: self::reject(), ctx: $this->ctx()));

    $this->assertSame('', $output);
    $this->assertSame(0, ftell($stream));
    fclose($stream);
  }

  public static function dataProviderResolvedRejectionThrows(): \Iterator {
    yield 'discovered value' => [TRUE, 'onion', 'Value "onion" for "Dish name" is not valid. ' . self::MESSAGE];
    yield 'non-interactive default' => [FALSE, NULL, 'Value "pear" for "Dish name" is not valid. ' . self::MESSAGE];
  }

  public function testExceptionIsAnInvalidArgumentException(): void {
    $this->promptyThrowOnInvalid();
    $stream = $this->installKeystrokes(self::KEY_ENTER);

    $this->captureOutputThrows(\InvalidArgumentException::class, 'Value "onion" for "Dish name" is not valid. ' . self::MESSAGE, fn(): mixed => Prompty::text('Dish name', discovered: 'onion', validate: self::reject(), ctx: $this->ctx()));

    fclose($stream);
  }

  public function testValidAnswerPasses(): void {
    $this->promptyThrowOnInvalid();

    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', validate: fn(): ?string => NULL, ctx: $this->ctx()), 'pear tart' . self::KEY_ENTER);

    $this->assertSame('pear tart', $r['result']);
  }

  #[DataProvider('dataProviderSwitchOffRePrompts')]
  public function testSwitchOffRePrompts(\Closure $configure): void {
    $configure($this);

    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', validate: self::reject(), ctx: $this->ctx()), 'pear' . self::KEY_ENTER);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('! ' . self::MESSAGE, $r['output']);
  }

  public static function dataProviderSwitchOffRePrompts(): \Iterator {
    $switched_off = static function (self $test): void {
      $test->promptyThrowOnInvalid();
      $test->promptyThrowOnInvalid(FALSE);
    };

    $torn_down = static function (self $test): void {
      $test->promptyThrowOnInvalid();
      $test->promptyTearDown();
    };

    yield 'never switched on' => [static function (): void {}];
    yield 'switched off' => [$switched_off];
    yield 'reset by tear down' => [$torn_down];
  }

  public function testSwitchLastsAcrossRuns(): void {
    $this->promptyThrowOnInvalid();

    $first = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', ctx: $this->ctx()), 'pear' . self::KEY_ENTER);
    $this->assertSame('pear', $first['result']);

    $this->expectException(PromptyValidationException::class);
    $this->expectExceptionMessage(self::MESSAGE);

    $this->promptyRun(fn(): mixed => Prompty::text('Dish name', validate: self::reject(), ctx: $this->ctx()), 'pear' . self::KEY_ENTER);
  }

  public function testRunReleasesStateWhenCallbackThrows(): void {
    $this->promptyThrowOnInvalid();
    $level = ob_get_level();

    try {
      $this->promptyRun(fn(): mixed => Prompty::text('Dish name', validate: self::reject(), ctx: $this->ctx()), 'pear' . self::KEY_ENTER);
      $this->fail('A rejected answer did not throw.');
    }
    catch (PromptyValidationException) {
      // The state after the throw is what is under test.
    }

    $this->assertSame($level, ob_get_level());
    $this->assertNull($this->getStaticProperty('instance'));
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testRunScriptReleasesBufferWhenCallbackThrows(): void {
    $this->promptyThrowOnInvalid();
    $level = ob_get_level();

    try {
      $this->promptyRunScript(function (): void {
        Prompty::flow(fn(): array => [
          'dish' => Prompty::text('Dish name', validate: self::reject()),
        ]);
      }, 'pear' . self::KEY_ENTER);
      $this->fail('A rejected answer did not throw.');
    }
    catch (PromptyValidationException $exception) {
      $this->assertSame('pear', $exception->value);
    }

    $this->assertSame($level, ob_get_level());
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  #[DataProvider('dataProviderExceptionMessage')]
  public function testExceptionMessage(string|bool|array $value, string $expected): void {
    /** @var string|bool|list<string> $value */
    $exception = new PromptyValidationException('Extras', $value, 'Pick at least one extra.');

    $this->assertSame($expected, $exception->getMessage());
    $this->assertSame('Extras', $exception->label);
    $this->assertSame($value, $exception->value);
    $this->assertSame('Pick at least one extra.', $exception->reason);
  }

  public static function dataProviderExceptionMessage(): \Iterator {
    yield 'string' => ['olives', 'Value "olives" for "Extras" is not valid. Pick at least one extra.'];
    yield 'empty string' => ['', 'Value "" for "Extras" is not valid. Pick at least one extra.'];
    yield 'list' => [['bread', 'herbs'], 'Value "bread,herbs" for "Extras" is not valid. Pick at least one extra.'];
    yield 'empty list' => [[], 'Value "" for "Extras" is not valid. Pick at least one extra.'];
    yield 'true' => [TRUE, 'Value "true" for "Extras" is not valid. Pick at least one extra.'];
    yield 'false' => [FALSE, 'Value "false" for "Extras" is not valid. Pick at least one extra.'];
  }

}
