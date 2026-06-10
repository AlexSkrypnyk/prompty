<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the multiselect widget's interactive loop.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyMultiselectInteractiveTest extends PromptyTestCase {

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
  protected function runMultiselectWidget(string $keystrokes, array $options = [], array $hints = [], array $ctx_overrides = [], array $default = []): array {
    if ($options === []) {
      $options = ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'];
    }

    return $this->promptyRun(function () use ($options, $hints, $ctx_overrides, $default): mixed {
      $p = $this->createInstance();
      $default_ctx = [
        'depth' => 0,
        'is_last' => FALSE,
        'open' => [],
        'number' => NULL,
        'env_value' => NULL,
      ];

      return Prompty::multiselect('Features', options: $options, default: $default, hints: $hints, ctx: array_merge($default_ctx, $ctx_overrides));
    }, $keystrokes);
  }

  public function testSubmitNoneSelected(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertSame([], $r['result']);
    $this->assertStringContainsString('None', (string) $r['output']);
  }

  public function testSelectSingleOption(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['ts'], $r['result']);
  }

  public function testSelectMultipleOptions(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['ts', 'eslint'], $r['result']);
  }

  public function testSelectAllOptions(): void {
    $keys = self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER;
    $r = $this->runMultiselectWidget($keys);

    $this->assertSame(['ts', 'eslint', 'prettier'], $r['result']);
  }

  public function testToggleOffOption(): void {
    $keys = self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER;
    $r = $this->runMultiselectWidget($keys);

    $this->assertSame([], $r['result']);
  }

  public function testNavigateDownAndSelect(): void {
    $r = $this->runMultiselectWidget(self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['prettier'], $r['result']);
  }

  public function testUpFromFirstWrapsToLast(): void {
    $r = $this->runMultiselectWidget(self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['prettier'], $r['result']);
  }

  public function testDownFromLastWrapsToFirst(): void {
    $keys = self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER;
    $r = $this->runMultiselectWidget($keys);

    $this->assertSame(['ts'], $r['result']);
  }

  public function testLeftActsAsUp(): void {
    $r = $this->runMultiselectWidget(self::KEY_LEFT . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['prettier'], $r['result']);
  }

  public function testRightActsAsDown(): void {
    $r = $this->runMultiselectWidget(self::KEY_RIGHT . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['eslint'], $r['result']);
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runMultiselectWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testActiveStateShowsOptions(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('TypeScript', (string) $r['output']);
    $this->assertStringContainsString('ESLint', (string) $r['output']);
    $this->assertStringContainsString('Prettier', (string) $r['output']);
  }

  public function testCompletedStateShowsSelectedLabels(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertStringContainsString('TypeScript, ESLint', (string) $r['output']);
  }

  public function testHintShownForFocusedOption(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, ['ts' => 'TypeScript', 'eslint' => 'ESLint'], ['ts' => 'Strict typing', 'eslint' => 'Code linter']);

    $this->assertStringContainsString('Strict typing', (string) $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, [], [], [
      'depth' => 1,
      'is_last' => FALSE,
      'open' => [1 => TRUE],
    ]);

    $this->assertSame(['ts'], $r['result']);
    $this->assertStringContainsString('Features', (string) $r['output']);
  }

  public function testHintAtDepth(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, [], ['ts' => 'Typed JS'], [
      'depth' => 1,
      'is_last' => FALSE,
      'open' => [1 => TRUE],
    ]);

    $this->assertSame(['ts'], $r['result']);
    $this->assertStringContainsString('Typed JS', (string) $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runMultiselectWidget(self::KEY_CTRL_C, [], [], [
      'depth' => 1,
      'is_last' => TRUE,
      'open' => [],
    ]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testDefaultPreChecksOptions(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, [], [], [], ['ts', 'prettier']);

    $this->assertSame(['ts', 'prettier'], $r['result']);
  }

  public function testDefaultRendersPreCheckedSelection(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, [], [], [], ['ts', 'eslint']);

    $this->assertSame(['ts', 'eslint'], $r['result']);
    $this->assertStringContainsString('TypeScript, ESLint', (string) $r['output']);
  }

  public function testDefaultThenToggleOffYieldsEmpty(): void {
    $r = $this->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, [], [], [], ['ts']);

    $this->assertSame([], $r['result']);
  }

  public function testDefaultThenCheckAnother(): void {
    $r = $this->runMultiselectWidget(self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, [], [], [], ['ts']);

    $this->assertSame(['ts', 'eslint'], $r['result']);
  }

  public function testDefaultUnknownKeysIgnored(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER, [], [], [], ['nonexistent']);

    $this->assertSame([], $r['result']);
  }

  public function testDefaultThreadsThroughFlowClosure(): void {
    $r = $this->promptyRun(function (): mixed {
      $closure = Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'], default: ['ts']);
      $this->assertInstanceOf(\Closure::class, $closure);

      return $closure($this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame(['ts'], $r['result']);
  }

}
