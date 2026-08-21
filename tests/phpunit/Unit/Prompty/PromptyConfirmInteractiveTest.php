<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the confirm widget's interactive loop.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyConfirmInteractiveTest extends PromptyTestCase {

  /**
   * Run the confirm widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param bool $default
   *   The default selection (TRUE for yes, FALSE for no).
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runConfirmWidget(string $keystrokes, array $ctx_overrides = [], bool $default = TRUE): array {
    return $this->promptyRun(fn(): mixed => Prompty::confirm('Install?',
      default: $default,
      description: 'Install dependencies.',
      ctx: $this->ctx($ctx_overrides),
    ), $keystrokes);
  }

  /**
   * Keystrokes that toggle the focused answer.
   *
   * @return \Iterator<string, array{string}>
   *   One keystroke per case.
   */
  protected static function keystrokeCases(): \Iterator {
    yield 'left' => [self::KEY_LEFT];
    yield 'right' => [self::KEY_RIGHT];
    yield 'up' => [self::KEY_UP];
    yield 'down' => [self::KEY_DOWN];
    yield 'tab' => [self::KEY_TAB];
  }

  public function testSubmitDefaultYes(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertTrue($r['result']);
    $this->assertStringContainsString('Yes', $r['output']);
  }

  public function testSubmitDefaultNo(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER, [], FALSE);

    $this->assertFalse($r['result']);
    $this->assertStringContainsString('No', $r['output']);
  }

  #[DataProvider('dataProviderToggleFromYesToNo')]
  public function testToggleFromYesToNo(string $keystrokes): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER);

    $this->assertFalse($r['result']);
  }

  public static function dataProviderToggleFromYesToNo(): \Iterator {
    yield from self::keystrokeCases();
  }

  #[DataProvider('dataProviderToggleFromNoToYes')]
  public function testToggleFromNoToYes(string $keystrokes): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER, [], FALSE);

    $this->assertTrue($r['result']);
  }

  public static function dataProviderToggleFromNoToYes(): \Iterator {
    yield from self::keystrokeCases();
  }

  public function testDoubleToggleReturnsToOriginal(): void {
    $r = $this->runConfirmWidget(self::KEY_LEFT . self::KEY_LEFT . self::KEY_ENTER);

    $this->assertTrue($r['result']);
  }

  #[DataProvider('dataProviderYesNoKeys')]
  public function testYesNoKeys(string $keystrokes, bool $default, bool $expected): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER, [], $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderYesNoKeys(): \Iterator {
    yield 'lowercase y' => ['y', FALSE, TRUE];
    yield 'uppercase Y' => ['Y', FALSE, TRUE];
    yield 'lowercase n' => ['n', TRUE, FALSE];
    yield 'uppercase N' => ['N', TRUE, FALSE];
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runConfirmWidget(self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runConfirmWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testActiveStateShowsYesNo(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Yes', $r['output']);
    $this->assertStringContainsString('No', $r['output']);
  }

  public function testActiveStateShowsDescription(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Install dependencies.', $r['output']);
  }

  public function testCompletedStateShowsLabel(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Install?', $r['output']);
  }

  public function testCancelledStateShowsFocusedValue(): void {
    $r = $this->runConfirmWidget(self::KEY_LEFT . self::KEY_CTRL_C);

    $this->assertStringContainsString('No', $r['output']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER, ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertTrue($r['result']);
    $this->assertStringContainsString('Install?', $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runConfirmWidget(self::KEY_CTRL_C, ['depth' => 1, 'is_last' => TRUE, 'open' => []]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

}
