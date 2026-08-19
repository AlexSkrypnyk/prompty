<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the text widget's interactive loop.
 *
 * Uses PromptyTestTrait::promptyRun() to inject keystrokes and capture output.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTextInteractiveTest extends PromptyTestCase {

  /**
   * Run the text widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param string $default
   *   Pre-filled, editable initial value.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runTextWidget(string $keystrokes, array $ctx_overrides = [], string $default = ''): array {
    return $this->promptyRun(fn(): mixed => Prompty::text('Project name',
      default: $default,
      placeholder: 'my-app',
      description: 'Enter a name.',
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  #[DataProvider('dataProviderTypeAndSubmit')]
  public function testTypeAndSubmit(string $keystrokes, string $expected): void {
    $r = $this->runTextWidget($keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTypeAndSubmit(): \Iterator {
    yield 'simple word' => ['hello', 'hello'];
    yield 'single char' => ['x', 'x'];
    yield 'numbers' => ['123', '123'];
    yield 'mixed' => ['my-app-v2', 'my-app-v2'];
    yield 'with spaces' => ['my app', 'my app'];
  }

  public function testEmptySubmitReturnsPlaceholder(): void {
    $r = $this->runTextWidget(self::KEY_ENTER);

    $this->assertSame('my-app', $r['result']);
  }

  #[DataProvider('dataProviderBackspace')]
  public function testBackspace(string $keystrokes, string $expected): void {
    $r = $this->runTextWidget($keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderBackspace(): \Iterator {
    yield 'delete last char' => ['hello', 'hell'];
    yield 'delete and retype' => ['helolo', 'hello'];
    yield 'multiple deletes' => ['abcdef', 'abc'];
    yield 'backspace on empty' => ['ok', 'ok'];
    yield 'delete all returns placeholder' => ['hi', 'my-app'];
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runTextWidget('hel' . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runTextWidget('hel' . self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testCancelEmpty(): void {
    $r = $this->runTextWidget(self::KEY_CTRL_C);

    $this->assertNull($r['result']);
  }

  public function testActiveStateShowsPlaceholder(): void {
    $r = $this->runTextWidget(self::KEY_ENTER);

    $this->assertStringContainsString('my-app', $r['output']);
  }

  public function testActiveStateShowsDescription(): void {
    $r = $this->runTextWidget('x' . self::KEY_ENTER);

    $this->assertStringContainsString('Enter a name.', $r['output']);
  }

  public function testCompletedStateShowsLabel(): void {
    $r = $this->runTextWidget('hello' . self::KEY_ENTER);

    $this->assertStringContainsString('Project name', $r['output']);
    $this->assertStringContainsString('hello', $r['output']);
  }

  public function testCancelledStateShowsTypedValue(): void {
    $r = $this->runTextWidget('partial' . self::KEY_CTRL_C);

    $this->assertStringContainsString('partial', $r['output']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runTextWidget('val' . self::KEY_ENTER, ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]);

    $this->assertSame('val', $r['result']);
    $this->assertStringContainsString('Project name', $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runTextWidget('x' . self::KEY_CTRL_C, ['depth' => 1, 'is_last' => TRUE, 'open' => []]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public function testIgnoresUnknownKeys(): void {
    $r = $this->runTextWidget('a' . self::KEY_TAB . self::KEY_UP . 'b' . self::KEY_ENTER);

    $this->assertSame('ab', $r['result']);
  }

  public function testSpaceInText(): void {
    $r = $this->runTextWidget('a b' . self::KEY_ENTER);

    $this->assertSame('a b', $r['result']);
  }

  #[DataProvider('dataProviderDefault')]
  public function testDefault(string $keystrokes, string $default, string $expected): void {
    $r = $this->runTextWidget($keystrokes, [], $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDefault(): \Iterator {
    yield 'pre-fills value' => [self::KEY_ENTER, 'seed-name', 'seed-name'];
    yield 'editable' => [self::KEY_BACKSPACE . 'x' . self::KEY_ENTER, 'abc', 'abx'];
    yield 'cleared falls back to placeholder' => [self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_ENTER, 'ab', 'my-app'];
  }

  public function testDefaultShownInActiveState(): void {
    $r = $this->runTextWidget('x' . self::KEY_ENTER, [], 'preset');

    $this->assertStringContainsString('preset', $r['output']);
  }

  public function testDefaultThreadsThroughFlowClosure(): void {
    $r = $this->promptyRun(function (): mixed {
      $closure = Prompty::text('Name', default: 'from-closure', placeholder: 'ph');
      $this->assertInstanceOf(\Closure::class, $closure);

      return $closure($this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame('from-closure', $r['result']);
  }

}
