<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what select and multiselect accept as options and defaults.
 *
 * Covers three concerns: a widget declared with no options is rejected, a
 * default must name a declared option key, and numeric-string option keys
 * survive PHP's array-key cast.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetValidationTest extends PromptyTestCase {

  protected const MESSAGE_SELECT = 'No options declared for "Course". Provide at least one option.';

  protected const MESSAGE_MULTISELECT = 'No options declared for "Extras". Provide at least one option.';

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Option set whose keys PHP stores as ints.
   */
  protected const TABLES = ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'];

  /**
   * Keystrokes that would reach a widget's interactive loop.
   *
   * The guard fires before the loop, so every navigation or answer key must
   * fail the same way for both widgets.
   *
   * @return \Iterator<string, array{string}>
   *   One keystroke sequence per case.
   */
  protected static function keystrokeCases(): \Iterator {
    yield 'enter' => [self::KEY_ENTER];
    yield 'space' => [self::KEY_SPACE];
    yield 'down' => [self::KEY_DOWN];
    yield 'up' => [self::KEY_UP];
    yield 'left' => [self::KEY_LEFT];
    yield 'right' => [self::KEY_RIGHT];
  }

  #[DataProvider('dataProviderInteractiveRejectsEmptyOptions')]
  public function testInteractiveRejectsEmptyOptions(\Closure $widget, string $keystrokes, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => $widget($this->defaultCtx()), $message, $keystrokes);
  }

  public static function dataProviderInteractiveRejectsEmptyOptions(): \Iterator {
    foreach (self::keystrokeCases() as $key_name => $case) {
      yield 'select ' . $key_name => [
        static fn(array $ctx): mixed => Prompty::select('Course', options: [], ctx: $ctx),
        $case[0],
        self::MESSAGE_SELECT,
      ];

      yield 'multiselect ' . $key_name => [
        static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: [], ctx: $ctx),
        $case[0],
        self::MESSAGE_MULTISELECT,
      ];
    }
  }

  #[DataProvider('dataProviderDiscoveredRejectsEmptyOptions')]
  public function testDiscoveredRejectsEmptyOptions(\Closure $widget, ?string $ctx_discovered, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => $widget($this->defaultCtx(['discovered' => $ctx_discovered])), $message);
  }

  public static function dataProviderDiscoveredRejectsEmptyOptions(): \Iterator {
    yield 'select discovered argument' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: [], discovered: 'starter', ctx: $ctx),
      NULL,
      self::MESSAGE_SELECT,
    ];
    yield 'select env value' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: [], ctx: $ctx),
      'starter',
      self::MESSAGE_SELECT,
    ];
    yield 'multiselect discovered argument' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: [], discovered: ['bread'], ctx: $ctx),
      NULL,
      self::MESSAGE_MULTISELECT,
    ];
    yield 'multiselect empty discovered list' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: [], discovered: [], ctx: $ctx),
      NULL,
      self::MESSAGE_MULTISELECT,
    ];
    yield 'multiselect env value' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: [], ctx: $ctx),
      'bread',
      self::MESSAGE_MULTISELECT,
    ];
    yield 'multiselect empty env value' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: [], ctx: $ctx),
      '',
      self::MESSAGE_MULTISELECT,
    ];
  }

  #[DataProvider('dataProviderStandaloneRejectsEmptyOptions')]
  public function testStandaloneRejectsEmptyOptions(\Closure $widget, string $message): void {
    $this->createAndSetInstance(['unicode' => FALSE]);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, $widget);

    $this->assertSame('', $output);
  }

  public static function dataProviderStandaloneRejectsEmptyOptions(): \Iterator {
    yield 'select' => [static fn(): mixed => Prompty::select('Course', options: []), self::MESSAGE_SELECT];
    yield 'multiselect' => [static fn(): mixed => Prompty::multiselect('Extras', options: []), self::MESSAGE_MULTISELECT];
  }

  #[DataProvider('dataProviderFlowRejectsEmptyOptions')]
  public function testFlowRejectsEmptyOptions(\Closure $steps, string $message): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => Prompty::flow($steps, unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public static function dataProviderFlowRejectsEmptyOptions(): \Iterator {
    yield 'select' => [static fn(): array => ['course' => Prompty::select('Course', options: [])], self::MESSAGE_SELECT];
    yield 'multiselect' => [static fn(): array => ['extras' => Prompty::multiselect('Extras', options: [])], self::MESSAGE_MULTISELECT];
  }

  public function testFlowStepSkippedByConditionSkipsOptionsValidation(): void {
    $result = NULL;

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: [], condition: fn(): bool => FALSE),
      ], unicode: FALSE);
    });

    $this->assertSame([], $result);
    $this->assertSame('', $output);
  }

  #[DataProvider('dataProviderAcceptsSingleOption')]
  public function testAcceptsSingleOption(\Closure $widget, string $keystrokes, mixed $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderAcceptsSingleOption(): \Iterator {
    yield 'select' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: ['starter' => 'Starter'], ctx: $ctx),
      self::KEY_ENTER,
      'starter',
    ];
    yield 'multiselect' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: ['bread' => 'Bread'], ctx: $ctx),
      self::KEY_SPACE . self::KEY_ENTER,
      ['bread'],
    ];
  }

  #[DataProvider('dataProviderRejectsDefaultOutsideOptions')]
  public function testRejectsDefaultOutsideOptions(\Closure $widget, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => $widget($this->defaultCtx()), $message);
  }

  public static function dataProviderRejectsDefaultOutsideOptions(): \Iterator {
    yield 'select unknown key' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'pudding', ctx: $ctx),
      'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.',
    ];
    yield 'select option label is not a key' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'Dessert', ctx: $ctx),
      'Default value "Dessert" for "Course" is not a valid option. Available options: starter, main, dessert.',
    ];
    yield 'select whitespace around a miss' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: '  pudding  ', ctx: $ctx),
      'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.',
    ];
    yield 'select numeric options' => [
      static fn(array $ctx): mixed => Prompty::select('Table', options: self::TABLES, default: '99', ctx: $ctx),
      'Default value "99" for "Table" is not a valid option. Available options: 4, 7, 12.',
    ];
    yield 'select discovered does not excuse the default' => [
      static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'pudding', discovered: 'main', ctx: $ctx),
      'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.',
    ];
    yield 'multiselect sole entry unknown' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['pickles'], ctx: $ctx),
      'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.',
    ];
    yield 'multiselect one entry of several unknown' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread', 'pickles'], ctx: $ctx),
      'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.',
    ];
    yield 'multiselect option label is not a key' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['Bread'], ctx: $ctx),
      'Default value "Bread" for "Extras" is not a valid option. Available options: bread, olives, herbs.',
    ];
    yield 'multiselect numeric options' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Seats', options: ['1' => 'Seat 1', '2' => 'Seat 2'], default: [9], ctx: $ctx),
      'Default value "9" for "Seats" is not a valid option. Available options: 1, 2.',
    ];
    yield 'multiselect discovered does not excuse the default' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['pickles'], discovered: ['bread'], ctx: $ctx),
      'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.',
    ];
  }

  public function testFlowRejectsDefaultOutsideOptions(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.', fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES, default: 'pudding'),
    ], unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testFlowStepSkippedByConditionSkipsDefaultValidation(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);
    $result = NULL;

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['pickles'], condition: fn(): bool => FALSE),
      ], unicode: FALSE);
    });

    $this->assertSame([], $result);
    $this->assertSame('', $output);
  }

  #[DataProvider('dataProviderSelectAcceptsValidDefault')]
  public function testSelectAcceptsValidDefault(string $default, string $expected, ?string $expected_output): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course',
      options: self::COURSES,
      default: $default,
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);

    if ($expected_output !== NULL) {
      $this->assertStringContainsString($expected_output, $r['output']);
    }
  }

  public static function dataProviderSelectAcceptsValidDefault(): \Iterator {
    yield 'empty default focuses first option' => ['', 'starter', NULL];
    yield 'declared key' => ['dessert', 'dessert', '> (*) Dessert'];
  }

  #[DataProvider('dataProviderMultiselectAcceptsValidDefault')]
  public function testMultiselectAcceptsValidDefault(string $label, array $options, array $default, array $expected): void {
    /** @var array<int|string, string> $options */
    /** @var list<int|string> $default */
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect($label,
      options: $options,
      default: $default,
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectAcceptsValidDefault(): \Iterator {
    yield 'empty default' => ['Extras', self::EXTRAS, [], []];
    yield 'string keys' => ['Seats', self::EXTRAS, ['bread', 'herbs'], ['bread', 'herbs']];
    yield 'every option' => ['Seats', self::EXTRAS, ['bread', 'olives', 'herbs'], ['bread', 'olives', 'herbs']];
    yield 'int keys against numeric options' => ['Seats', ['1' => 'Seat 1', '2' => 'Seat 2', '4' => 'Seat 4'], [2, 4], ['2', '4']];
    yield 'declaration order restored' => ['Seats', self::EXTRAS, ['herbs', 'bread'], ['bread', 'herbs']];
  }

  /**
   * Run the select widget over the table option set.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param string $default
   *   Option key to pre-focus.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runSelectWidget(string $keystrokes, string $default = '', array $options = self::TABLES, array $hints = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::select('Table',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx(),
    ), $keystrokes);
  }

  /**
   * Run the multiselect widget over the table option set.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param list<int|string> $default
   *   Option keys to pre-check.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselectWidget(string $keystrokes, array $default = [], array $options = self::TABLES, array $hints = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Tables',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx(),
    ), $keystrokes);
  }

  #[DataProvider('dataProviderSelectInteractiveReturnsString')]
  public function testSelectInteractiveReturnsString(string $keystrokes, string $expected): void {
    $r = $this->runSelectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderSelectInteractiveReturnsString(): \Iterator {
    yield 'first option' => [self::KEY_ENTER, '4'];
    yield 'second option' => [self::KEY_DOWN . self::KEY_ENTER, '7'];
    yield 'third option' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER, '12'];
    yield 'wraps to last' => [self::KEY_UP . self::KEY_ENTER, '12'];
  }

  #[DataProvider('dataProviderSelectDefaultFocusesOption')]
  public function testSelectDefaultFocusesOption(string $default, string $expected_label, string $expected): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, $default);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString('> (*) ' . $expected_label, $r['output']);
  }

  public static function dataProviderSelectDefaultFocusesOption(): \Iterator {
    yield 'second option' => ['7', 'Table 7', '7'];
    yield 'third option' => ['12', 'Table 12', '12'];
  }

  public function testSelectDiscoveredAndInteractiveAgree(): void {
    $interactive = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $discovered = $this->promptyRun(fn(): mixed => Prompty::select('Table',
      options: self::TABLES,
      discovered: '7',
      ctx: $this->defaultCtx(),
    ));

    $this->assertSame('7', $interactive['result']);
    $this->assertSame($interactive['result'], $discovered['result']);
  }

  public function testSelectHintsResolveForNumericKeys(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, '', self::TABLES, ['4' => 'By the window.', '7' => 'Near the pass.']);

    $this->assertStringContainsString('Near the pass.', $r['output']);
  }

  #[DataProvider('dataProviderMultiselectInteractiveReturnsStrings')]
  public function testMultiselectInteractiveReturnsStrings(string $keystrokes, array $expected): void {
    $r = $this->runMultiselectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectInteractiveReturnsStrings(): \Iterator {
    yield 'single option' => [self::KEY_SPACE . self::KEY_ENTER, ['4']];
    yield 'two options' => [self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['4', '7']];
    yield 'last option' => [self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER, ['12']];
    yield 'none selected' => [self::KEY_ENTER, []];
  }

  #[DataProvider('dataProviderMultiselectDiscoveredReturnsStrings')]
  public function testMultiselectDiscoveredReturnsStrings(?array $discovered, ?string $ctx_discovered, array $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Tables',
      options: self::TABLES,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectDiscoveredReturnsStrings(): \Iterator {
    yield 'discovered argument' => [['4', '12'], NULL, ['4', '12']];
    yield 'env value' => [NULL, '7,12', ['7', '12']];
  }

  #[DataProvider('dataProviderMultiselectDefaultPreChecks')]
  public function testMultiselectDefaultPreChecks(array $default, array $expected): void {
    /** @var list<int|string> $default */
    $r = $this->runMultiselectWidget(self::KEY_ENTER, $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectDefaultPreChecks(): \Iterator {
    yield 'string keys' => [['7', '12'], ['7', '12']];
    yield 'int keys' => [[7, 12], ['7', '12']];
    yield 'mixed keys' => [[7, '12'], ['7', '12']];
    yield 'all pre-checked as ints' => [[4, 7, 12], ['4', '7', '12']];
  }

  public function testMultiselectIntDefaultRendersChecked(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, [7]);

    $this->assertStringContainsString('[x] Table 7', $r['output']);
    $this->assertStringContainsString('Tables', $r['output']);
  }

  #[DataProvider('dataProviderNumericLikeKeysReturnAsDeclaredStrings')]
  public function testNumericLikeKeysReturnAsDeclaredStrings(string $keystrokes, array $options, string $expected): void {
    /** @var array<int|string, string> $options */
    $r = $this->runSelectWidget($keystrokes, '', $options);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderNumericLikeKeysReturnAsDeclaredStrings(): \Iterator {
    // PHP stores a canonical decimal integer as an int key and leaves every
    // other numeric-looking key a string, so both paths reach the widget.
    yield 'leading zero, kept a string key' => [self::KEY_ENTER, ['007' => 'Table 007', '8' => 'Table 8'], '007'];
    yield 'decimal point, kept a string key' => [self::KEY_ENTER, ['1.5' => 'Half portion', '2' => 'Double portion'], '1.5'];
    yield 'negative, stored as an int key' => [self::KEY_ENTER, ['-1' => 'Void', '1' => 'Table 1'], '-1'];
  }

  public function testMixedNumericAndTextualKeys(): void {
    $options = ['4' => 'Table 4', 'bar' => 'Bar', '12' => 'Table 12'];

    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, [], $options);

    $this->assertSame(['4', 'bar', '12'], $r['result']);
  }

  #[DataProvider('dataProviderRejectsDiscoveredOutsideNumericOptions')]
  public function testRejectsDiscoveredOutsideNumericOptions(\Closure $widget, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => $widget($this->defaultCtx()), $message);
  }

  public static function dataProviderRejectsDiscoveredOutsideNumericOptions(): \Iterator {
    yield 'select' => [
      static fn(array $ctx): mixed => Prompty::select('Table', options: self::TABLES, discovered: '99', ctx: $ctx),
      'Discovered value "99" for "Table" is not a valid option. Available options: 4, 7, 12.',
    ];
    yield 'multiselect' => [
      static fn(array $ctx): mixed => Prompty::multiselect('Tables', options: self::TABLES, discovered: ['4', '99'], ctx: $ctx),
      'Discovered value "99" for "Tables" is not a valid option. Available options: 4, 7, 12.',
    ];
  }

}
