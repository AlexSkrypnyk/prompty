<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests select and multiselect against numeric-string option keys.
 *
 * PHP casts a canonical decimal-integer string array key to an int, so an
 * option set such as ['4' => 'Table 4'] reaches the widgets with int keys. A
 * textual option set exercises none of that, so these cases pin the string
 * contract for both widgets across the interactive and discovered paths.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyNumericOptionKeysTest extends PromptyTestCase {

  /**
   * Option set whose keys PHP stores as ints.
   */
  protected const TABLES = ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'];

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

  public function testMultiselectDiscoveredReturnsStrings(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Tables',
      options: self::TABLES,
      discovered: ['4', '12'],
      ctx: $this->defaultCtx(),
    ));

    $this->assertSame(['4', '12'], $r['result']);
  }

  public function testMultiselectDiscoveredFromEnvValueReturnsStrings(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Tables',
      options: self::TABLES,
      ctx: $this->defaultCtx(['discovered' => '7,12']),
    ));

    $this->assertSame(['7', '12'], $r['result']);
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

  #[DataProvider('dataProviderKeysPhpDoesNotCastSurviveUnchanged')]
  public function testKeysPhpDoesNotCastSurviveUnchanged(string $keystrokes, array $options, string $expected): void {
    /** @var array<int|string, string> $options */
    $r = $this->runSelectWidget($keystrokes, '', $options);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderKeysPhpDoesNotCastSurviveUnchanged(): \Iterator {
    yield 'leading zero' => [self::KEY_ENTER, ['007' => 'Table 007', '8' => 'Table 8'], '007'];
    yield 'decimal point' => [self::KEY_ENTER, ['1.5' => 'Half portion', '2' => 'Double portion'], '1.5'];
    yield 'negative' => [self::KEY_ENTER, ['-1' => 'Void', '1' => 'Table 1'], '-1'];
  }

  public function testMixedNumericAndTextualKeys(): void {
    $options = ['4' => 'Table 4', 'bar' => 'Bar', '12' => 'Table 12'];

    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, [], $options);

    $this->assertSame(['4', 'bar', '12'], $r['result']);
  }

  public function testSelectRejectsDiscoveredValueOutsideNumericDomain(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Table',
      options: self::TABLES,
      discovered: '99',
      ctx: $this->defaultCtx(),
    ), 'Discovered value "99" for "Table" is not a valid option. Available options: 4, 7, 12.');
  }

  public function testMultiselectRejectsDiscoveredValueOutsideNumericDomain(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Tables',
      options: self::TABLES,
      discovered: ['4', '99'],
      ctx: $this->defaultCtx(),
    ), 'Discovered value "99" for "Tables" is not a valid option. Available options: 4, 7, 12.');
  }

}
