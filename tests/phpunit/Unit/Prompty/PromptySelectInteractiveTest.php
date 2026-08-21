<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the select widget's interactive loop.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptySelectInteractiveTest extends PromptyTestCase {

  protected const FRAMEWORKS = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];

  /**
   * Run the select widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param string $default
   *   Option key to pre-focus.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runSelectWidget(string $keystrokes, array $options = self::FRAMEWORKS, array $hints = [], array $ctx_overrides = [], string $default = ''): array {
    return $this->promptyRun(fn(): mixed => Prompty::select('Framework',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  #[DataProvider('dataProviderNavigation')]
  public function testNavigation(string $keystrokes, string $expected): void {
    $r = $this->runSelectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderNavigation(): \Iterator {
    yield 'select first option' => [self::KEY_ENTER, 'react'];
    yield 'select second option with down' => [self::KEY_DOWN . self::KEY_ENTER, 'vue'];
    yield 'select third option with two downs' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER, 'svelte'];
    yield 'up from first wraps to last' => [self::KEY_UP . self::KEY_ENTER, 'svelte'];
    yield 'down from last wraps to first' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER, 'react'];
    yield 'left acts as up' => [self::KEY_LEFT . self::KEY_ENTER, 'svelte'];
    yield 'right acts as down' => [self::KEY_RIGHT . self::KEY_ENTER, 'vue'];
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runSelectWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testActiveStateShowsOptions(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('React', $r['output']);
    $this->assertStringContainsString('Vue', $r['output']);
    $this->assertStringContainsString('Svelte', $r['output']);
  }

  public function testCompletedStateShowsLabel(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $this->assertStringContainsString('Framework', $r['output']);
    $this->assertStringContainsString('Vue', $r['output']);
  }

  public function testCancelledStateShowsFocusedOption(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_CTRL_C);

    $this->assertStringContainsString('Vue', $r['output']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testActiveStateShowsDescription(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Framework',
      options: ['react' => 'React', 'vue' => 'Vue'],
      description: 'Pick one.',
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertStringContainsString('Pick one.', $r['output']);
  }

  public function testHintShownForFocusedOption(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, ['react' => 'React', 'vue' => 'Vue'], ['react' => 'Meta library', 'vue' => 'Progressive framework']);

    $this->assertStringContainsString('Meta library', $r['output']);
  }

  public function testHintChangesOnNavigation(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER,
      ['react' => 'React', 'vue' => 'Vue'],
      ['react' => 'Meta library', 'vue' => 'Progressive framework'],
    );

    $this->assertStringContainsString('Progressive framework', $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, self::FRAMEWORKS, [], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertSame('vue', $r['result']);
    $this->assertStringContainsString('Framework', $r['output']);
  }

  public function testHintAtDepth(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, self::FRAMEWORKS, ['react' => 'Meta library'], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertSame('react', $r['result']);
    $this->assertStringContainsString('Meta library', $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runSelectWidget(self::KEY_CTRL_C, self::FRAMEWORKS, [], ['depth' => 1, 'is_last' => TRUE, 'open' => []]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  #[DataProvider('dataProviderDefault')]
  public function testDefault(string $keystrokes, string $default, string $expected): void {
    $r = $this->runSelectWidget($keystrokes, self::FRAMEWORKS, [], [], $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDefault(): \Iterator {
    yield 'focuses option' => [self::KEY_ENTER, 'vue', 'vue'];
    yield 'focuses last option' => [self::KEY_ENTER, 'svelte', 'svelte'];
    yield 'navigable after focus' => [self::KEY_DOWN . self::KEY_ENTER, 'vue', 'svelte'];
  }

  public function testDefaultThreadsThroughFlowClosure(): void {
    $r = $this->promptyRun(function (): mixed {
      $closure = Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'], default: 'vue');
      $this->assertInstanceOf(\Closure::class, $closure);

      return $closure($this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame('vue', $r['result']);
  }

  public function testPointerMarksFocusedOption(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('> (*) React', $r['output']);
  }

  public function testPointerMovesWithNavigation(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $this->assertStringContainsString('> (*) Vue', $r['output']);
  }

  public function testPointerRendersAtDepth(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, self::FRAMEWORKS, [], ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertStringContainsString('> (*) React', $r['output']);
  }

  public function testPointerRendersUnicodeGlyph(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Framework',
      options: ['react' => 'React', 'vue' => 'Vue'],
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER, ['unicode' => TRUE]);

    $this->assertStringContainsString('❯', $r['output']);
  }

}
