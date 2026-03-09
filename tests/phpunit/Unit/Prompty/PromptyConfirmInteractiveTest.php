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
   */
  protected function runConfirmWidget(string $keystrokes, bool $default = TRUE, array $ctx_overrides = []): array {
    return $this->promptyRun(function () use ($default, $ctx_overrides): mixed {
      $p = $this->createInstance();
      $default_ctx = [
        'depth' => 0,
        'is_last' => FALSE,
        'open' => [],
        'number' => NULL,
        'env_value' => NULL,
        'truthy' => ['1', 'true', 'yes'],
        'falsy' => ['0', 'false', 'no'],
      ];

      return Prompty::confirm('Install?', default: $default, description: 'Install dependencies.', ctx: array_merge($default_ctx, $ctx_overrides));
    }, $keystrokes);
  }

  public function testSubmitDefaultYes(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER, TRUE);

    $this->assertTrue($r['result']);
    $this->assertStringContainsString('Yes', (string) $r['output']);
  }

  public function testSubmitDefaultNo(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER, FALSE);

    $this->assertFalse($r['result']);
    $this->assertStringContainsString('No', (string) $r['output']);
  }

  #[DataProvider('dataProviderToggleFromYesToNo')]
  public function testToggleFromYesToNo(string $key): void {
    $r = $this->runConfirmWidget($key . self::KEY_ENTER, TRUE);

    $this->assertFalse($r['result']);
  }

  #[DataProvider('dataProviderToggleFromNoToYes')]
  public function testToggleFromNoToYes(string $key): void {
    $r = $this->runConfirmWidget($key . self::KEY_ENTER, FALSE);

    $this->assertTrue($r['result']);
  }

  public static function dataProviderToggleFromYesToNo(): \Iterator {
    yield 'left' => ["\x1b[D"];
    yield 'right' => ["\x1b[C"];
    yield 'up' => ["\x1b[A"];
    yield 'down' => ["\x1b[B"];
    yield 'tab' => ["\t"];
  }

  public static function dataProviderToggleFromNoToYes(): \Iterator {
    yield 'left' => ["\x1b[D"];
    yield 'right' => ["\x1b[C"];
    yield 'up' => ["\x1b[A"];
    yield 'down' => ["\x1b[B"];
    yield 'tab' => ["\t"];
  }

  public function testDoubleToggleReturnsToOriginal(): void {
    $r = $this->runConfirmWidget(self::KEY_LEFT . self::KEY_LEFT . self::KEY_ENTER, TRUE);

    $this->assertTrue($r['result']);
  }

  public function testYkeySelectsYes(): void {
    $r = $this->runConfirmWidget('y' . self::KEY_ENTER, FALSE);

    $this->assertTrue($r['result']);
  }

  public function testUpperYkeySelectsYes(): void {
    $r = $this->runConfirmWidget('Y' . self::KEY_ENTER, FALSE);

    $this->assertTrue($r['result']);
  }

  public function testNkeySelectsNo(): void {
    $r = $this->runConfirmWidget('n' . self::KEY_ENTER, TRUE);

    $this->assertFalse($r['result']);
  }

  public function testUpperNkeySelectsNo(): void {
    $r = $this->runConfirmWidget('N' . self::KEY_ENTER, TRUE);

    $this->assertFalse($r['result']);
  }

  public function testCancelWithCtrlC(): void {
    $r = $this->runConfirmWidget(self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testCancelWithEscape(): void {
    $r = $this->runConfirmWidget(self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testActiveStateShowsYesNo(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Yes', (string) $r['output']);
    $this->assertStringContainsString('No', (string) $r['output']);
  }

  public function testActiveStateShowsDescription(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Install dependencies.', (string) $r['output']);
  }

  public function testCompletedStateShowsLabel(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER);

    $this->assertStringContainsString('Install?', (string) $r['output']);
  }

  public function testCancelledShowsFocusedValue(): void {
    $r = $this->runConfirmWidget(self::KEY_LEFT . self::KEY_CTRL_C, TRUE);

    $this->assertStringContainsString('No', (string) $r['output']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

  public function testInteractiveAtDepth(): void {
    $r = $this->runConfirmWidget(self::KEY_ENTER, TRUE, [
      'depth' => 1,
      'is_last' => FALSE,
      'open' => [1 => TRUE],
    ]);

    $this->assertTrue($r['result']);
    $this->assertStringContainsString('Install?', (string) $r['output']);
  }

  public function testInteractiveAtDepthCancelled(): void {
    $r = $this->runConfirmWidget(self::KEY_CTRL_C, TRUE, [
      'depth' => 1,
      'is_last' => TRUE,
      'open' => [],
    ]);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', (string) $r['output']);
  }

}
