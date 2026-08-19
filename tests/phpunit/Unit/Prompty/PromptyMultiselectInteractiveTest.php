<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the multiselect widget's interactive loop.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyMultiselectInteractiveTest extends PromptyTestCase {

  /**
   * Option set for the multiselect cases.
   */
  protected const FEATURES = ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'];

  /**
   * Run the multiselect widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param list<string> $default
   *   Option keys to pre-check.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselectWidget(string $keystrokes, array $options = self::FEATURES, array $hints = [], array $ctx_overrides = [], array $default = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Features',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  public function testSubmitNoneSelected(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertSame([], $r['result']);
    $this->assertStringContainsString('None', $r['output']);
  }

  #[DataProvider('dataProviderToggleAndNavigation')]
  public function testToggleAndNavigation(string $keystrokes, array $expected): void {
    $r = $this->runMultiselectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderToggleAndNavigation(): \Iterator {
    yield 'select single option' => [self::KEY_SPACE . self::KEY_ENTER, ['ts']];
    yield 'select multiple options' => [self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['ts', 'eslint']];
    yield 'select all options' => [
      self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER,
      ['ts', 'eslint', 'prettier'],
    ];
    yield 'toggle off option' => [self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER, []];
    yield 'navigate down and select' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'up from first wraps to last' => [self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'down from last wraps to first' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['ts']];
    yield 'left acts as up' => [self::KEY_LEFT . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'right acts as down' => [self::KEY_RIGHT . self::KEY_SPACE . self::KEY_ENTER, ['eslint']];
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runMultiselectWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testActiveStateShowsOptions(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('TypeScript', $r['output']);
    $this->assertStringContainsString('ESLint', $r['output']);
    $this->assertStringContainsString('Prettier', $r['output']);
  }

  public function testCompletedStateShowsSelectedLabels(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertStringContainsString('TypeScript, ESLint', $r['output']);
  }

  public function testHintShownForFocusedOption(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, ['ts' => 'TypeScript', 'eslint' => 'ESLint'], ['ts' => 'Strict typing', 'eslint' => 'Code linter']);

    $this->assertStringContainsString('Strict typing', $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, self::FEATURES, [], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertSame(['ts'], $r['result']);
    $this->assertStringContainsString('Features', $r['output']);
  }

  public function testHintAtDepth(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, self::FEATURES, ['ts' => 'Typed JS'], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertSame(['ts'], $r['result']);
    $this->assertStringContainsString('Typed JS', $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runMultiselectWidget(self::KEY_CTRL_C, self::FEATURES, [], ['depth' => 1, 'is_last' => TRUE, 'open' => []]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  #[DataProvider('dataProviderDefault')]
  public function testDefault(string $keystrokes, array $default, array $expected): void {
    /** @var list<string> $default */
    $r = $this->runMultiselectWidget($keystrokes, self::FEATURES, [], [], $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDefault(): \Iterator {
    yield 'pre-checks options' => [self::KEY_ENTER, ['ts', 'prettier'], ['ts', 'prettier']];
    yield 'all pre-checked' => [self::KEY_ENTER, ['ts', 'eslint', 'prettier'], ['ts', 'eslint', 'prettier']];
    yield 'toggle off yields empty' => [self::KEY_SPACE . self::KEY_ENTER, ['ts'], []];
    yield 'check another after default' => [self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['ts'], ['ts', 'eslint']];
  }

  public function testDefaultRendersPreCheckedSelection(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, self::FEATURES, [], [], ['ts', 'eslint']);

    $this->assertSame(['ts', 'eslint'], $r['result']);
    $this->assertStringContainsString('TypeScript, ESLint', $r['output']);
  }

  public function testDefaultThreadsThroughFlowClosure(): void {
    $r = $this->promptyRun(function (): mixed {
      $closure = Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'], default: ['ts']);
      $this->assertInstanceOf(\Closure::class, $closure);

      return $closure($this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame(['ts'], $r['result']);
  }

  public function testPointerMarksFocusedOption(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('> [ ] TypeScript', $r['output']);
  }

  public function testPointerStaysVisibleOnCheckedFocusedOption(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, self::FEATURES, [], [], ['ts', 'eslint']);
    $output = $r['output'];

    $this->assertStringContainsString('> [x] TypeScript', $output);
    $this->assertStringNotContainsString('> [x] ESLint', $output);
  }

  public function testPointerMovesWithNavigation(): void {
    $r = $this->runMultiselectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $this->assertStringContainsString('> [ ] ESLint', $r['output']);
  }

  public function testPointerRendersAtDepth(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, self::FEATURES, [], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertStringContainsString('> [ ] TypeScript', $r['output']);
  }

  public function testPointerRendersUnicodeGlyph(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Features',
      options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER, ['unicode' => TRUE]);

    $this->assertStringContainsString('❯', $r['output']);
  }

}
