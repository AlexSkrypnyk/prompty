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
    yield 'color sequence' => ["\033[31mred\033[0m", 'red'];
    yield 'lone escape' => ["a\033b", 'ab'];
    yield 'bell' => ["a\x07b", 'ab'];
    yield 'delete' => ["a\x7fb", 'ab'];
    yield 'c1 sequence introducer' => ["pear\u{009B}2Ktart", 'pear2Ktart'];
    yield 'c1 next line' => ["pear\u{0085}tart", 'peartart'];
    yield 'multibyte character holding a c1 byte' => ["pi\u{0105}tek", "pi\u{0105}tek"];
    yield 'multibyte character holding a csi byte' => ["\u{069B}tart", "\u{069B}tart"];
    yield 'text that is not valid utf8' => ["pear\xC3\x28tart\x07", "pear\xC3\x28tart"];
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

  public function testTypedTextIsPrintable(): void {
    // A paste delivers a character as its bytes, the same way typing does, so
    // a control character can arrive through the input buffer.
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', ctx: $this->ctx()), "pear\u{0085}tart" . self::KEY_ENTER);

    $this->assertSame('peartart', $r['result']);
    $this->assertStringNotContainsString("\u{0085}", $r['output']);
  }

  public function testConfiguredCancelLabelIsPrintable(): void {
    $p = $this->createAndSetInstance(['labels' => ['yes' => 'Yes', 'no' => 'No', 'cancelled' => "cancel\nled", 'none' => 'None', 'separator' => '/']]);

    $lines = $this->callProtectedLines($p, 'renderCancelled', 'Dish name', 'plum compote', 0, []);

    $this->assertSame('|  plum compote cancel led', $this->stripAnsi($lines[1]));
  }

  public function testConfiguredConfirmLabelsArePrintable(): void {
    $this->createAndSetInstance(['labels' => ['yes' => "A\nye", 'no' => "N\033[2Kay", 'cancelled' => '(cancelled)', 'none' => 'None', 'separator' => '/']]);

    $r = $this->promptyRun(fn(): mixed => Prompty::confirm('Send order?', ctx: $this->ctx()), self::KEY_ENTER, ['labels' => ['yes' => "A\nye", 'no' => "N\033[2Kay", 'cancelled' => '(cancelled)', 'none' => 'None', 'separator' => '/']]);

    $this->assertStringContainsString('A ye', $r['output']);
    $this->assertStringContainsString('Nay', $r['output']);
    $this->assertStringNotContainsString("A\nye", $r['output']);
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
