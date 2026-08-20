<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for control characters in values, labels and messages.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyControlCharactersTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * Build a context array for widget execution.
   *
   * @param array<string, mixed> $overrides
   *   Context overrides.
   *
   * @return array<string, mixed>
   *   Context array.
   */
  protected function ctx(array $overrides = []): array {
    return $this->defaultCtx(array_merge(['truthy' => ['1', 'true', 'yes'], 'falsy' => ['0', 'false', 'no']], $overrides));
  }

  #[DataProvider('dataProviderPrintable')]
  public function testPrintable(string $text, string $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'printable', $text));
  }

  public static function dataProviderPrintable(): \Iterator {
    yield 'plain text' => ['plum compote', 'plum compote'];
    yield 'newline' => ["first\nsecond", 'first second'];
    yield 'carriage return' => ["over\rwrite", 'over write'];
    yield 'windows newline' => ["first\r\nsecond", 'first second'];
    yield 'tab' => ["first\tsecond", 'first second'];
    yield 'run of newlines' => ["first\n\n\nsecond", 'first second'];
    yield 'erase line sequence' => ["a\033[2Kb", 'ab'];
    yield 'colour sequence' => ["\033[31mred\033[0m", 'red'];
    yield 'lone escape' => ["a\033b", 'ab'];
    yield 'bell' => ["a\x07b", 'ab'];
    yield 'delete' => ["a\x7fb", 'ab'];
    yield 'nothing to strip' => ['', ''];
  }

  public function testValueWithNewlineKeepsTheTreeIntact(): void {
    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Dish name', discovered: "first\nsecond", ctx: $this->ctx());
    });

    $this->assertSame('first second', $result);
    $this->assertSame("+  Dish name\n|  first second\n|\n", $output);
  }

  #[DataProvider('dataProviderTextReturnsPrintableText')]
  public function testTextReturnsPrintableText(string $discovered, string $expected): void {
    $result = NULL;
    $this->captureOutput(function () use ($discovered, &$result): void {
      $result = Prompty::text('Dish name', discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderTextReturnsPrintableText(): \Iterator {
    yield 'newline' => ["first\nsecond", 'first second'];
    yield 'carriage return' => ["over\rwrite", 'over write'];
    yield 'erase line sequence' => ["a\033[2Kb", 'ab'];
  }

  public function testOptionLabelWithNewlineKeepsTheTreeIntact(): void {
    $options = ['alpha' => "Al\npha", 'main' => 'Main'];

    $result = NULL;
    $output = $this->captureOutput(function () use ($options, &$result): void {
      $result = Prompty::select('Course', options: $options, discovered: 'alpha', ctx: $this->ctx());
    });

    $this->assertSame('alpha', $result);
    $this->assertSame("+  Course\n|  Al pha\n|\n", $output);
  }

  public function testFocusedOptionLabelIsPrintable(): void {
    $options = ['alpha' => "Al\npha", 'main' => 'Main'];

    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course', options: $options, ctx: $this->ctx()), self::KEY_ENTER);

    $this->assertSame('alpha', $r['result']);
    $this->assertStringContainsString('Al pha', $r['output']);
    $this->assertStringNotContainsString("Al\npha", $r['output']);
  }

  public function testLabelWithNewlineKeepsTheTreeIntact(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::text("Dish\nname", discovered: 'plum compote', ctx: $this->ctx());
    });

    $this->assertSame("+  Dish name\n|  plum compote\n|\n", $output);
  }

  public function testCancelledValueIsPrintable(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', default: "first\nsecond", ctx: $this->ctx()), self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('first second', $r['output']);
  }

  public function testDescriptionKeepsItsOwnLineBreaks(): void {
    // A description renders while the widget is active, so this drives it with
    // keystrokes rather than resolving it from a discovered value.
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', description: "Written on the ticket.\nAnd on the board.", ctx: $this->ctx()), 'plum compote' . self::KEY_ENTER);

    $this->assertStringContainsString('|  Written on the ticket.', $r['output']);
    $this->assertStringContainsString('|  And on the board.', $r['output']);
  }

  public function testIntroAndOutroArePrintable(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::intro("Kitchen\norder");
      Prompty::outro("Order\nsent");
    });

    $this->assertStringContainsString('Kitchen order', $output);
    $this->assertStringContainsString('Order sent', $output);
  }

}
