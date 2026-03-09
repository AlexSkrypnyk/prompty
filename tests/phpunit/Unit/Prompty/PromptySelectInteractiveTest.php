<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the select widget's interactive loop.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptySelectInteractiveTest extends PromptyTestCase {

  /**
   * Run the select widget with injected keystrokes.
   */
  protected function runSelectWidget(string $keystrokes, array $options = [], array $hints = [], array $ctx_overrides = []): array {
    if ($options === []) {
      $options = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];
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

      return Prompty::select('Framework', options: $options, hints: $hints, ctx: array_merge($default_ctx, $ctx_overrides));
    }, $keystrokes);
  }

  public function testSelectFirstOption(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER);

    $this->assertSame('react', $r['result']);
  }

  public function testSelectSecondOptionWithDown(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $this->assertSame('vue', $r['result']);
  }

  public function testSelectThirdOptionWithTwoDowns(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER);

    $this->assertSame('svelte', $r['result']);
  }

  public function testUpFromFirstWrapsToLast(): void {
    $r = $this->runSelectWidget(self::KEY_UP . self::KEY_ENTER);

    $this->assertSame('svelte', $r['result']);
  }

  public function testDownFromLastWrapsToFirst(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER);

    $this->assertSame('react', $r['result']);
  }

  public function testLeftActsAsUp(): void {
    $r = $this->runSelectWidget(self::KEY_LEFT . self::KEY_ENTER);

    $this->assertSame('svelte', $r['result']);
  }

  public function testRightActsAsDown(): void {
    $r = $this->runSelectWidget(self::KEY_RIGHT . self::KEY_ENTER);

    $this->assertSame('vue', $r['result']);
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runSelectWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testActiveStateShowsOptions(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER);

    $this->assertStringContainsString('React', (string) $r['output']);
    $this->assertStringContainsString('Vue', (string) $r['output']);
    $this->assertStringContainsString('Svelte', (string) $r['output']);
  }

  public function testCompletedStateShowsLabel(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER);

    $this->assertStringContainsString('Framework', (string) $r['output']);
    $this->assertStringContainsString('Vue', (string) $r['output']);
  }

  public function testCancelledStateShowsFocusedOption(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_CTRL_C);

    $this->assertStringContainsString('Vue', (string) $r['output']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testActiveStateShowsDescription(): void {
    $r = $this->promptyRun(function (): mixed {
      $p = $this->createInstance();
      $ctx = [
        'depth' => 0,
        'is_last' => FALSE,
        'open' => [],
        'number' => NULL,
        'env_value' => NULL,
      ];

      return Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'], description: 'Pick one.', ctx: $ctx);
    }, self::KEY_ENTER);

    $this->assertStringContainsString('Pick one.', $r['output']);
  }

  public function testHintShownForFocusedOption(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, ['react' => 'React', 'vue' => 'Vue'], ['react' => 'Meta library', 'vue' => 'Progressive framework']);

    $this->assertStringContainsString('Meta library', (string) $r['output']);
  }

  public function testHintChangesOnNavigation(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, ['react' => 'React', 'vue' => 'Vue'], ['react' => 'Meta library', 'vue' => 'Progressive framework']);

    $this->assertStringContainsString('Progressive framework', (string) $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, [], [], [
      'depth' => 1,
      'is_last' => FALSE,
      'open' => [1 => TRUE],
    ]);

    $this->assertSame('vue', $r['result']);
    $this->assertStringContainsString('Framework', (string) $r['output']);
  }

  public function testHintAtDepth(): void {
    $r = $this->runSelectWidget(self::KEY_ENTER, [], ['react' => 'Meta library'], [
      'depth' => 1,
      'is_last' => FALSE,
      'open' => [1 => TRUE],
    ]);

    $this->assertSame('react', $r['result']);
    $this->assertStringContainsString('Meta library', (string) $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runSelectWidget(self::KEY_CTRL_C, [], [], [
      'depth' => 1,
      'is_last' => TRUE,
      'open' => [],
    ]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

}
