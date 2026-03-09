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
   */
  protected function runMultiselectWidget(string $keystrokes, array $options = [], array $hints = [], array $ctx_overrides = []): array {
    if ($options === []) {
      $options = ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'];
    }

    return $this->promptyRun(function () use ($options, $hints, $ctx_overrides): mixed {
      $p = $this->createInstance();
      $default_ctx = [
        'depth' => 0,
        'is_last' => FALSE,
        'open' => [],
        'number' => NULL,
        'env_value' => NULL,
      ];

      return Prompty::multiselect('Features', options: $options, hints: $hints, ctx: array_merge($default_ctx, $ctx_overrides));
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

}
