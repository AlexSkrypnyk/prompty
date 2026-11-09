<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the 'transform' callback on every widget.
 *
 * A transform receives the answer and the flow results so far, and returns
 * the value the widget answers with and draws on its completed line.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetTransformTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Tests each widget returns and draws the transformed answer.
   *
   * @param \Closure $widget
   *   The widget call, given the discovered value to pass.
   * @param string $keystrokes
   *   Raw keystroke bytes for the interactive run.
   * @param mixed $discovered
   *   The discovered value for the non-interactive run.
   * @param mixed $expected
   *   The transformed answer.
   * @param string $completed
   *   Text the completed line shows.
   */
  #[DataProvider('dataProviderTransformedAnswer')]
  public function testTransformedAnswer(\Closure $widget, string $keystrokes, mixed $discovered, mixed $expected, string $completed): void {
    $interactive = $this->promptyRun(fn(): mixed => $widget(NULL), $keystrokes);
    $this->assertSame($expected, $interactive['result']);
    $this->assertStringContainsString('+  Order' . PHP_EOL . '|  ' . $completed . PHP_EOL, $interactive['output']);

    $resolved = $this->promptyRun(fn(): mixed => $widget($discovered));
    $this->assertSame($expected, $resolved['result']);
    $this->assertSame('+  Order' . PHP_EOL . '|  ' . $completed . PHP_EOL . '|' . PHP_EOL, $resolved['output']);
  }

  public static function dataProviderTransformedAnswer(): \Iterator {
    $domain = fn(string $value): string => strtolower(trim((string) preg_replace('#^https?://(www\.)?#', '', $value), '/'));

    yield 'text normalised' => [
      fn(mixed $discovered): mixed => Prompty::text('Order', discovered: $discovered, transform: $domain, ctx: self::context()),
      'https://www.Kitchen.Example/' . self::KEY_ENTER,
      'https://www.Kitchen.Example/',
      'kitchen.example',
      'kitchen.example',
    ];

    yield 'text placeholder transformed' => [
      fn(mixed $discovered): mixed => Prompty::text('Order', placeholder: 'pear tart', discovered: $discovered, transform: strtoupper(...), ctx: self::context()),
      self::KEY_ENTER,
      '',
      'PEAR TART',
      'PEAR TART',
    ];

    yield 'select mapped to another option' => [
      fn(mixed $discovered): mixed => Prompty::select('Order', options: self::COURSES, discovered: $discovered, transform: fn(string $key): string => $key === 'starter' ? 'main' : $key, ctx: self::context()),
      self::KEY_ENTER,
      'starter',
      'main',
      'Main',
    ];

    yield 'select mapped to a value outside the options' => [
      fn(mixed $discovered): mixed => Prompty::select('Order', options: self::COURSES, discovered: $discovered, transform: fn(string $key): string => 'course-' . $key, ctx: self::context()),
      self::KEY_DOWN . self::KEY_ENTER,
      'main',
      'course-main',
      'course-main',
    ];

    yield 'multiselect adds an option' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): array => array_values(array_unique(['bread', ...$keys])), ctx: self::context()),
      self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER,
      'olives',
      ['bread', 'olives'],
      'Bread, Olives',
    ];

    yield 'multiselect mixes option and non-option entries' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): array => [...$keys, 'lemon'], ctx: self::context()),
      self::KEY_SPACE . self::KEY_ENTER,
      'bread',
      ['bread', 'lemon'],
      'Bread, lemon',
    ];

    yield 'multiselect emptied' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): array => [], ctx: self::context()),
      self::KEY_SPACE . self::KEY_ENTER,
      'bread',
      [],
      'None',
    ];

    yield 'confirm inverted' => [
      fn(mixed $discovered): mixed => Prompty::confirm('Order', discovered: $discovered, transform: fn(bool $yes): bool => !$yes, ctx: self::context()),
      self::KEY_ENTER,
      'yes',
      FALSE,
      'No',
    ];
  }

  public function testTextTransformOutputIsMadePrintable(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Order', transform: fn(string $value): string => $value . "\033[31m\ttart", ctx: self::context()), 'pear' . self::KEY_ENTER);

    $this->assertSame('pear tart', $r['result']);
  }

  /**
   * Tests each widget calls its transform once, with the answer alone.
   *
   * A built-in function rejects an extra argument, so passing anything beside
   * the answer would break a transform such as trim(...).
   *
   * @param \Closure $widget
   *   The widget call, given the transform to attach.
   * @param string $keystrokes
   *   Raw keystroke bytes for the widget.
   * @param mixed $answer
   *   The answer passed to the transform.
   */
  #[DataProvider('dataProviderTransformReceivesAnswerOnly')]
  public function testTransformReceivesAnswerOnly(\Closure $widget, string $keystrokes, mixed $answer): void {
    $calls = [];
    $transform = function (mixed ...$arguments) use (&$calls): mixed {
      $calls[] = $arguments;

      return $arguments[0];
    };

    $r = $this->promptyRun(fn(): mixed => $widget($transform), $keystrokes);

    $this->assertSame($answer, $r['result']);
    $this->assertSame([[$answer]], $calls);
  }

  public static function dataProviderTransformReceivesAnswerOnly(): \Iterator {
    yield 'text' => [fn(\Closure $transform): mixed => Prompty::text('Order', transform: $transform, ctx: self::context()), 'window' . self::KEY_ENTER, 'window'];

    yield 'select' => [fn(\Closure $transform): mixed => Prompty::select('Order', options: self::COURSES, transform: $transform, ctx: self::context()), self::KEY_DOWN . self::KEY_ENTER, 'main'];

    yield 'multiselect' => [fn(\Closure $transform): mixed => Prompty::multiselect('Order', options: self::EXTRAS, transform: $transform, ctx: self::context()), self::KEY_SPACE . self::KEY_ENTER, ['bread']];

    yield 'confirm' => [fn(\Closure $transform): mixed => Prompty::confirm('Order', transform: $transform, ctx: self::context()), self::KEY_ENTER, TRUE];
  }

  public function testBuiltInFunctionAsTransform(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Order', transform: trim(...), ctx: self::context()), '  pear  ' . self::KEY_ENTER);

    $this->assertSame('pear', $r['result']);
  }

  public function testEnvironmentValueIsTransformed(): void {
    $this->createAndSetInstance(['unicode' => FALSE]);
    $this->setEnvVars(['dish' => '  ONION SOUP  ']);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name', transform: fn(string $value): string => ucwords(strtolower($value))),
      ]);
    });

    $this->assertSame(['dish' => 'Onion Soup'], $result);
    $this->assertStringContainsString('Onion Soup', $output);
    $this->assertStringNotContainsString('ONION SOUP', $output);
  }

  public function testSiblingConditionSeesTransformedAnswer(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES, transform: fn(string $key): string => $key === 'starter' ? 'dessert' : $key),
      'accompaniment' => Prompty::select('Accompaniment', options: ['cream' => 'Cream', 'custard' => 'Custard'], condition: fn(array $results): bool => $results['course'] === 'dessert'),
    ]), self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['course' => 'dessert', 'accompaniment' => 'cream'], $r['result']);
  }

  /**
   * Tests cancelling a widget leaves its transform uncalled.
   *
   * @param \Closure $widget
   *   The widget call, given the transform to attach.
   */
  #[DataProvider('dataProviderCancelSkipsTransform')]
  public function testCancelSkipsTransform(\Closure $widget): void {
    $called = FALSE;
    $transform = function (mixed $value) use (&$called): mixed {
      $called = TRUE;

      return $value;
    };

    $r = $this->promptyRun(fn(): mixed => $widget($transform), 'x' . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertFalse($called);
  }

  public static function dataProviderCancelSkipsTransform(): \Iterator {
    yield 'text' => [fn(\Closure $transform): mixed => Prompty::text('Order', transform: $transform, ctx: self::context())];

    yield 'select' => [fn(\Closure $transform): mixed => Prompty::select('Order', options: self::COURSES, transform: $transform, ctx: self::context())];

    yield 'multiselect' => [fn(\Closure $transform): mixed => Prompty::multiselect('Order', options: self::EXTRAS, transform: $transform, ctx: self::context())];

    yield 'confirm' => [fn(\Closure $transform): mixed => Prompty::confirm('Order', transform: $transform, ctx: self::context())];
  }

  /**
   * Tests a transform returning the wrong type is rejected.
   *
   * @param \Closure $widget
   *   The widget call, given the discovered value to pass.
   * @param string $keystrokes
   *   Raw keystroke bytes for the interactive run.
   * @param mixed $discovered
   *   The discovered value for the non-interactive run.
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderRejectsWrongType')]
  public function testRejectsWrongType(\Closure $widget, string $keystrokes, mixed $discovered, string $message): void {
    $stream = $this->installKeystrokes($keystrokes);
    $this->captureOutputThrows(\UnexpectedValueException::class, $message, fn(): mixed => $widget(NULL));
    fclose($stream);

    $stream = $this->installKeystrokes('');
    $output = $this->captureOutputThrows(\UnexpectedValueException::class, $message, fn(): mixed => $widget($discovered));
    fclose($stream);

    $this->assertSame('', $output);
  }

  public static function dataProviderRejectsWrongType(): \Iterator {
    $message = 'Transform for "Order" returned %s. A transform returns %s, the type the widget answers with.';

    yield 'text returning null' => [
      fn(mixed $discovered): mixed => Prompty::text('Order', discovered: $discovered, transform: fn(string $value): mixed => NULL, ctx: self::context()),
      'pear' . self::KEY_ENTER,
      'pear',
      sprintf($message, 'null', 'a string'),
    ];

    yield 'text returning an int' => [
      fn(mixed $discovered): mixed => Prompty::text('Order', discovered: $discovered, transform: fn(string $value): mixed => 4, ctx: self::context()),
      'pear' . self::KEY_ENTER,
      'pear',
      sprintf($message, 'int', 'a string'),
    ];

    yield 'select returning an array' => [
      fn(mixed $discovered): mixed => Prompty::select('Order', options: self::COURSES, discovered: $discovered, transform: fn(string $key): mixed => [$key], ctx: self::context()),
      self::KEY_ENTER,
      'main',
      sprintf($message, 'array', 'a string'),
    ];

    yield 'multiselect returning a string' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): mixed => implode(',', $keys), ctx: self::context()),
      self::KEY_ENTER,
      'bread',
      sprintf($message, 'string', 'a list of strings'),
    ];

    yield 'multiselect returning a keyed array' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): mixed => ['first' => 'bread'], ctx: self::context()),
      self::KEY_ENTER,
      'bread',
      sprintf($message, 'array', 'a list of strings'),
    ];

    yield 'multiselect returning a list holding an int' => [
      fn(mixed $discovered): mixed => Prompty::multiselect('Order', options: self::EXTRAS, discovered: $discovered, transform: fn(array $keys): mixed => ['bread', 4], ctx: self::context()),
      self::KEY_ENTER,
      'bread',
      sprintf($message, 'a list holding int', 'a list of strings'),
    ];

    yield 'confirm returning a string' => [
      fn(mixed $discovered): mixed => Prompty::confirm('Order', discovered: $discovered, transform: fn(bool $yes): mixed => $yes ? 'yes' : 'no', ctx: self::context()),
      self::KEY_ENTER,
      'yes',
      sprintf($message, 'string', 'a boolean'),
    ];
  }

  public function testTransformExceptionPropagates(): void {
    $stream = $this->installKeystrokes('pear' . self::KEY_ENTER);

    $this->captureOutputThrows(\RuntimeException::class, 'Kitchen closed.', fn(): mixed => Prompty::text('Order', transform: function (): never {
      throw new \RuntimeException('Kitchen closed.');
    }, ctx: self::context()));

    fclose($stream);
  }

  public function testNumberedLabelIsNotUsedInMessage(): void {
    $this->createAndSetInstance(['unicode' => FALSE]);
    $this->setEnvVars(['dish' => 'pear']);

    $this->captureOutputThrows(\UnexpectedValueException::class, 'Transform for "Dish name" returned null. A transform returns a string, the type the widget answers with.', function (): void {
      Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name', transform: fn(): mixed => NULL)], numbering: TRUE);
    });
  }

  /**
   * Builds the context of a top-level widget.
   *
   * @return array<string, mixed>
   *   The widget context.
   */
  protected static function context(): array {
    return ['depth' => 0, 'is_last' => FALSE, 'open' => [], 'number' => NULL, 'discovered' => NULL, 'truthy' => ['1', 'true', 'yes'], 'falsy' => ['0', 'false', 'no']];
  }

}
