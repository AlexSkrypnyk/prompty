<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'required' argument shared by all 5 widgets.
 *
 * Covers the interactive path, where an empty answer keeps the widget active
 * with the message drawn under it, and the resolved path, where an empty
 * answer throws before the widget draws.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetRequiredTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected const array DISHES = ['Pear Tart', 'Onion Soup', 'Lentil Stew'];

  protected const string MESSAGE = 'Enter a name for the ticket.';

  #[DataProvider('dataProviderEmptyAnswerKeepsWidgetActive')]
  public function testEmptyAnswerKeepsWidgetActive(\Closure $widget, bool|string $required, string $keystrokes, mixed $expected, string $error, array $ctx_overrides = []): void {
    $r = $this->promptyRun(fn(): mixed => $widget($required, $this->defaultCtx($ctx_overrides)), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertSame(1, substr_count($r['output'], $error));
  }

  public static function dataProviderEmptyAnswerKeepsWidgetActive(): \Iterator {
    $text = static fn(bool|string $required, array $ctx): mixed => Prompty::text('Guest name', required: $required, ctx: $ctx);
    $multiselect = static fn(bool|string $required, array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, required: $required, ctx: $ctx);
    $suggest = static fn(bool|string $required, array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, required: $required, ctx: $ctx);

    yield 'text, default message' => [$text, TRUE, self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER, 'Jane Doe', '! Required.'];
    yield 'text, own message' => [$text, self::MESSAGE, self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER, 'Jane Doe', '! ' . self::MESSAGE];
    yield 'text, whitespace only' => [$text, TRUE, '   ' . self::KEY_ENTER . 'Jane' . self::KEY_ENTER, '   Jane', '! Required.'];
    yield 'text, blank message takes the label' => [$text, '   ', self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER, 'Jane Doe', '! Required.'];
    yield 'text, nested' => [$text, TRUE, self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER, 'Jane Doe', '|  |  ! Required.', ['depth' => 1, 'open' => [1 => TRUE]]];
    yield 'multiselect, default message' => [$multiselect, TRUE, self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER, ['bread'], '! Required.'];
    yield 'multiselect, own message' => [$multiselect, 'Pick at least one extra.', self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER, ['bread'], '! Pick at least one extra.'];
    yield 'suggest, default message' => [$suggest, TRUE, self::KEY_ENTER . 'onion' . self::KEY_ENTER, 'onion', '! Required.'];
    yield 'suggest, own message' => [$suggest, 'Pick a dish.', self::KEY_ENTER . 'tart' . self::KEY_DOWN . self::KEY_ENTER, 'Pear Tart', '! Pick a dish.'];
    yield 'suggest, nested' => [$suggest, TRUE, self::KEY_ENTER . 'onion' . self::KEY_ENTER, 'onion', '|  |  ! Required.', ['depth' => 1, 'open' => [1 => TRUE]]];
  }

  #[DataProvider('dataProviderNonEmptyAnswerPasses')]
  public function testNonEmptyAnswerPasses(\Closure $widget, string $keystrokes, mixed $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertStringNotContainsString('Required.', $r['output']);
  }

  public static function dataProviderNonEmptyAnswerPasses(): \Iterator {
    yield 'text' => [static fn(array $ctx): mixed => Prompty::text('Guest name', required: TRUE, ctx: $ctx), 'Jane Doe' . self::KEY_ENTER, 'Jane Doe'];
    yield 'text, placeholder answer' => [static fn(array $ctx): mixed => Prompty::text('Guest name', placeholder: 'Jane Doe', required: TRUE, ctx: $ctx), self::KEY_ENTER, 'Jane Doe'];
    yield 'text, default answer' => [static fn(array $ctx): mixed => Prompty::text('Guest name', default: 'Jane Doe', required: TRUE, ctx: $ctx), self::KEY_ENTER, 'Jane Doe'];
    yield 'select' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, required: TRUE, ctx: $ctx), self::KEY_ENTER, 'starter'];
    yield 'multiselect' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['olives'], required: TRUE, ctx: $ctx), self::KEY_ENTER, ['olives']];
    yield 'suggest' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, required: TRUE, ctx: $ctx), 'stew' . self::KEY_ENTER, 'stew'];
    yield 'confirm, no' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', default: FALSE, required: TRUE, ctx: $ctx), self::KEY_ENTER, FALSE];
  }

  #[DataProvider('dataProviderNotRequiredAcceptsEmptyAnswer')]
  public function testNotRequiredAcceptsEmptyAnswer(\Closure $widget, mixed $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderNotRequiredAcceptsEmptyAnswer(): \Iterator {
    yield 'text' => [static fn(array $ctx): mixed => Prompty::text('Guest name', ctx: $ctx), ''];
    yield 'multiselect' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, ctx: $ctx), []];
    yield 'suggest' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, ctx: $ctx), ''];
    yield 'text, explicit FALSE' => [static fn(array $ctx): mixed => Prompty::text('Guest name', required: FALSE, ctx: $ctx), ''];
  }

  #[DataProvider('dataProviderTransformedToEmptyIsRejected')]
  public function testTransformedToEmptyIsRejected(\Closure $widget, string $keystrokes, mixed $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertSame(1, substr_count($r['output'], '! Required.'));
  }

  public static function dataProviderTransformedToEmptyIsRejected(): \Iterator {
    yield 'text' => [
      static fn(array $ctx): mixed => Prompty::text('Guest name', transform: fn(string $v): string => $v === 'none' ? '' : $v, required: TRUE, ctx: $ctx),
      'none' . self::KEY_ENTER . str_repeat(self::KEY_BACKSPACE, 4) . 'Jane' . self::KEY_ENTER,
      'Jane',
    ];
    yield 'select' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, transform: fn(string $v): string => $v === 'starter' ? '' : $v, required: TRUE, ctx: $ctx),
      self::KEY_ENTER . self::KEY_DOWN . self::KEY_ENTER,
      'main',
    ];
    yield 'multiselect' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, transform: fn(array $v): array => array_values(array_diff($v, ['bread'])), required: TRUE, ctx: $ctx),
      self::KEY_SPACE . self::KEY_ENTER . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER,
      ['olives'],
    ];
  }

  public function testValidatorSkipsEmptyAnswer(): void {
    $seen = [];
    $validate = function (string $value) use (&$seen): ?string {
      $seen[] = $value;

      return NULL;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::text('Guest name', required: TRUE, validate: $validate, ctx: $this->defaultCtx()), self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER);

    $this->assertSame('Jane Doe', $r['result']);
    $this->assertSame(['Jane Doe'], $seen);
  }

  public function testValidatorRunsAfterRequired(): void {
    $validate = fn(string $value): ?string => $value === 'Jane' ? 'Enter the whole name.' : NULL;

    $r = $this->promptyRun(fn(): mixed => Prompty::text('Guest name', required: TRUE, validate: $validate, ctx: $this->defaultCtx()), self::KEY_ENTER . 'Jane' . self::KEY_ENTER . ' Doe' . self::KEY_ENTER);

    $this->assertSame('Jane Doe', $r['result']);
    $this->assertSame(1, substr_count($r['output'], '! Required.'));
    $this->assertSame(1, substr_count($r['output'], '! Enter the whole name.'));
  }

  public function testLabelOverride(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Guest name', required: TRUE, ctx: $this->defaultCtx()), self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER, ['labels' => ['required' => 'Fill this in.'] + self::labels()]);

    $this->assertSame('Jane Doe', $r['result']);
    $this->assertStringContainsString('! Fill this in.', $r['output']);
  }

  public function testLabelOverrideThroughConfigure(): void {
    Prompty::configure(labels: ['required' => 'Fill this in.']);

    $labels = Prompty::config()['labels'];

    $this->assertIsArray($labels);
    $this->assertSame('Fill this in.', $labels['required']);
  }

  #[DataProvider('dataProviderResolvedEmptyAnswerThrows')]
  public function testResolvedEmptyAnswerThrows(\Closure $widget, string $message, bool $interactive = TRUE): void {
    $this->createAndSetInstance(['interactive' => $interactive], TRUE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => $widget($this->defaultCtx()));

    $this->assertSame('', $output);
  }

  public static function dataProviderResolvedEmptyAnswerThrows(): \Iterator {
    yield 'text, discovered blank' => [
      static fn(array $ctx): mixed => Prompty::text('Guest name', discovered: '  ', required: TRUE, ctx: $ctx),
      'Discovered value "" for "Guest name" is not valid. Required.',
    ];
    yield 'text, non-interactive default' => [
      static fn(array $ctx): mixed => Prompty::text('Guest name', placeholder: 'Jane Doe', required: self::MESSAGE, ctx: $ctx),
      'Default value "" for "Guest name" is not valid. ' . self::MESSAGE,
      FALSE,
    ];
    yield 'text, discovered transformed to empty' => [
      static fn(array $ctx): mixed => Prompty::text('Guest name', discovered: 'none', transform: fn(string $v): string => $v === 'none' ? '' : $v, required: TRUE, ctx: $ctx),
      'Discovered value "" for "Guest name" is not valid. Required.',
    ];
    yield 'select, discovered transformed to empty' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, discovered: 'main', transform: fn(string $v): string => '', required: TRUE, ctx: $ctx),
      'Discovered value "" for "Course" is not valid. Required.',
    ];
    yield 'multiselect, discovered blank' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, discovered: '', required: TRUE, ctx: $ctx),
      'Discovered value "" for "Extras" is not valid. Required.',
    ];
    yield 'multiselect, non-interactive default' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, required: 'Pick at least one extra.', ctx: $ctx),
      'Default value "" for "Extras" is not valid. Pick at least one extra.',
      FALSE,
    ];
    yield 'suggest, discovered blank' => [
      static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, discovered: '', required: TRUE, ctx: $ctx),
      'Discovered value "" for "Dish" is not valid. Required.',
    ];
    yield 'suggest, non-interactive default' => [
      static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, placeholder: 'Pear Tart', required: 'Pick a dish.', ctx: $ctx),
      'Default value "" for "Dish" is not valid. Pick a dish.',
      FALSE,
    ];
  }

  #[DataProvider('dataProviderResolvedNonEmptyAnswerPasses')]
  public function testResolvedNonEmptyAnswerPasses(\Closure $widget, mixed $expected, bool $interactive = TRUE): void {
    $this->createAndSetInstance(['interactive' => $interactive], TRUE);

    $result = NULL;
    $this->captureOutput(function () use ($widget, &$result): void {
      $result = $widget($this->defaultCtx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderResolvedNonEmptyAnswerPasses(): \Iterator {
    yield 'text, discovered' => [static fn(array $ctx): mixed => Prompty::text('Guest name', discovered: 'Jane Doe', required: TRUE, ctx: $ctx), 'Jane Doe'];
    yield 'text, non-interactive default' => [static fn(array $ctx): mixed => Prompty::text('Guest name', default: 'Jane Doe', required: TRUE, ctx: $ctx), 'Jane Doe', FALSE];
    yield 'select, non-interactive' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, required: TRUE, ctx: $ctx), 'starter', FALSE];
    yield 'multiselect, discovered' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, discovered: 'bread,herbs', required: TRUE, ctx: $ctx), ['bread', 'herbs']];
    yield 'suggest, discovered' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, discovered: 'Walnut Loaf', required: TRUE, ctx: $ctx), 'Walnut Loaf'];
    yield 'suggest, non-interactive default' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, default: 'Pear Tart', required: TRUE, ctx: $ctx), 'Pear Tart', FALSE];
    yield 'confirm, non-interactive' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', default: FALSE, required: TRUE, ctx: $ctx), FALSE, FALSE];
  }

  public function testStandaloneSuggestRequired(): void {
    $stream = $this->installKeystrokes(self::KEY_ENTER . 'soup' . self::KEY_ENTER, FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::suggest('Dish', options: self::DISHES, required: TRUE);
    });

    $this->assertSame('soup', $result);
    $this->assertSame(1, substr_count($output, '! Required.'));

    fclose($stream);
  }

  public function testRequiredInFlow(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow()
      ->text('guest', 'Guest name', required: TRUE)
      ->multiselect('extras', 'Extras', options: self::EXTRAS, required: TRUE)
      ->suggest('special', 'Dish', options: self::DISHES, required: TRUE)
        ->when(fn(array $r): bool => TRUE)
      ->run(unicode: FALSE), self::KEY_ENTER . 'Jane Doe' . self::KEY_ENTER . self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER . self::KEY_ENTER . 'soup' . self::KEY_ENTER);

    $this->assertSame(['guest' => 'Jane Doe', 'extras' => ['bread'], 'special' => 'soup'], $r['result']);
    $this->assertSame(3, substr_count($r['output'], '! Required.'));
  }

  public function testRequiredInFlowThrowsForBlankEnv(): void {
    $this->setEnvVars(['guest' => ' ']);

    $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "" for "Guest name" is not valid. ' . self::MESSAGE, function (): void {
      Prompty::flow()
        ->text('guest', 'Guest name', required: self::MESSAGE)
        ->run(unicode: FALSE);
    });
  }

  #[DataProvider('dataProviderThrowOnInvalid')]
  public function testThrowOnInvalid(\Closure $widget, string $keystrokes, string $label, string|array $value): void {
    $this->promptyThrowOnInvalid();

    try {
      $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes);
      $this->fail('Expected PromptyValidationException was not thrown.');
    }
    catch (PromptyValidationException $exception) {
      $this->assertSame($label, $exception->label);
      $this->assertSame($value, $exception->value);
      $this->assertSame('Required.', $exception->reason);
    }
  }

  public static function dataProviderThrowOnInvalid(): \Iterator {
    yield 'text' => [static fn(array $ctx): mixed => Prompty::text('Guest name', required: TRUE, ctx: $ctx), self::KEY_ENTER, 'Guest name', ''];
    yield 'multiselect' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, required: TRUE, ctx: $ctx), self::KEY_ENTER, 'Extras', []];
    yield 'suggest' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, required: TRUE, ctx: $ctx), self::KEY_ENTER, 'Dish', ''];
  }

  /**
   * Returns the default labels, for a partial override through promptyRun().
   *
   * @return array<string, string>
   *   The label map.
   */
  protected static function labels(): array {
    return ['yes' => 'Yes', 'no' => 'No', 'cancelled' => '(cancelled)', 'none' => 'None', 'separator' => '/', 'more' => 'more', 'required' => 'Required.'];
  }

}
