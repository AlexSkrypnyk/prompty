<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for reading and sanitising input.
 *
 * Control characters in values, labels and messages are filtered to
 * printable text before rendering, and an exhausted input stream yields the
 * 'eof' sentinel that cancels a widget instead of blocking.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyInputTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives'];

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

  #[DataProvider('dataProviderNewlineKeepsTheTreeIntact')]
  public function testNewlineKeepsTheTreeIntact(\Closure $widget, string $expected_result, string $expected_output): void {
    $result = NULL;
    $output = $this->captureOutput(function () use ($widget, &$result): void {
      $result = $widget();
    });

    $this->assertSame($expected_result, $result);
    $this->assertSame($expected_output, $output);
  }

  public static function dataProviderNewlineKeepsTheTreeIntact(): \Iterator {
    $ctx = ['depth' => 0, 'is_last' => FALSE, 'open' => [], 'number' => NULL, 'discovered' => NULL];
    $ctx['truthy'] = ['1', 'true', 'yes'];
    $ctx['falsy'] = ['0', 'false', 'no'];

    $value = fn(): mixed => Prompty::text('Dish name', discovered: "first\nsecond", ctx: $ctx);
    $label = fn(): mixed => Prompty::text("Dish\nname", discovered: 'plum compote', ctx: $ctx);
    $option_label = fn(): mixed => Prompty::select('Course', options: ['alpha' => "Al\npha", 'main' => 'Main'], discovered: 'alpha', ctx: $ctx);

    yield 'newline in the value' => [$value, 'first second', "+  Dish name\n|  first second\n|\n"];
    yield 'newline in the label' => [$label, 'plum compote', "+  Dish name\n|  plum compote\n|\n"];
    yield 'newline in an option label' => [$option_label, 'alpha', "+  Course\n|  Al pha\n|\n"];
  }

  #[DataProvider('dataProviderInteractiveOutputIsPrintable')]
  public function testInteractiveOutputIsPrintable(\Closure $widget, string $keystrokes, mixed $expected, ?string $contains, ?string $not_contains): void {
    $r = $this->promptyRun($widget, $keystrokes);

    $this->assertSame($expected, $r['result']);

    if ($contains !== NULL) {
      $this->assertStringContainsString($contains, $r['output']);
    }

    if ($not_contains !== NULL) {
      $this->assertStringNotContainsString($not_contains, $r['output']);
    }
  }

  public static function dataProviderInteractiveOutputIsPrintable(): \Iterator {
    $ctx = ['depth' => 0, 'is_last' => FALSE, 'open' => [], 'number' => NULL, 'discovered' => NULL];
    $ctx['truthy'] = ['1', 'true', 'yes'];
    $ctx['falsy'] = ['0', 'false', 'no'];

    $select = fn(): mixed => Prompty::select('Course', options: ['alpha' => "Al\npha", 'main' => 'Main'], ctx: $ctx);
    $text_with_default = fn(): mixed => Prompty::text('Dish name', default: "first\nsecond", ctx: $ctx);
    $text = fn(): mixed => Prompty::text('Dish name', ctx: $ctx);

    yield 'focused option label' => [$select, self::KEY_ENTER, 'alpha', 'Al pha', "Al\npha"];
    yield 'cancelled value' => [$text_with_default, self::KEY_CTRL_C, NULL, 'first second', NULL];
    // A paste delivers a character as its bytes, the same way typing does, so
    // a control character can arrive through the input buffer.
    yield 'typed control character' => [$text, "pear\u{0085}tart" . self::KEY_ENTER, 'peartart', NULL, "\u{0085}"];
  }

  public function testDescriptionKeepsItsOwnLineBreaks(): void {
    // A description renders while the widget is active, so this drives it with
    // keystrokes rather than resolving it from a discovered value.
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', description: "Written on the ticket.\nAnd on the board.", ctx: $this->ctx()), 'plum compote' . self::KEY_ENTER);

    $this->assertStringContainsString('|  Written on the ticket.', $r['output']);
    $this->assertStringContainsString('|  And on the board.', $r['output']);
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

  #[DataProvider('dataProviderWidgetCancelsAtEndOfInput')]
  public function testWidgetCancelsAtEndOfInput(\Closure $widget, string $keystrokes): void {
    $r = $this->promptyRun($widget, $keystrokes);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
  }

  public static function dataProviderWidgetCancelsAtEndOfInput(): \Iterator {
    $ctx = ['depth' => 0, 'is_last' => FALSE, 'open' => [], 'number' => NULL, 'discovered' => NULL];

    $text = fn(): mixed => Prompty::text('Dish name', placeholder: 'pear tart', ctx: $ctx);
    $select = fn(): mixed => Prompty::select('Course', options: self::COURSES, ctx: $ctx);
    $multiselect = fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, ctx: $ctx);
    $confirm = fn(): mixed => Prompty::confirm('Send order?', ctx: $ctx);

    yield 'text, nothing to read' => [$text, ''];
    yield 'text, input runs out' => [$text, 'plum compote'];
    yield 'select, nothing to read' => [$select, ''];
    yield 'select, input runs out' => [$select, self::KEY_DOWN];
    yield 'multiselect, nothing to read' => [$multiselect, ''];
    yield 'multiselect, input runs out' => [$multiselect, self::KEY_SPACE];
    yield 'confirm, nothing to read' => [$confirm, ''];
    yield 'confirm, input runs out' => [$confirm, self::KEY_LEFT];
  }

  public function testStandaloneWidgetCancelsAtEndOfInput(): void {
    $stream = $this->installKeystrokes('', FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Dish name', placeholder: 'pear tart');
    });

    $this->assertNull($result);
    $this->assertStringContainsString('(cancelled)', $output);

    fclose($stream);
  }

  public function testFlowCancelsWhenInputRunsOut(): void {
    $this->clearEnvVars(['dish', 'course']);

    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
      'course' => Prompty::select('Course', options: self::COURSES),
    ], cancelled: 'Order cancelled.', unicode: FALSE), 'plum compote' . self::KEY_ENTER);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('plum compote', $r['output']);
    $this->assertStringContainsString('Order cancelled.', $r['output']);
  }

  public function testReadKeyReturnsEofWhenStreamIsExhausted(): void {
    $p = $this->createInstance();
    $stream = fopen('php://memory', 'r+');
    $this->assertIsResource($stream);
    $this->setProperty($p, 'input', $stream);

    $this->assertSame('eof', $this->callProtected($p, 'readKey'));

    fclose($stream);
  }

  #[DataProvider('dataProviderIsCancelKey')]
  public function testIsCancelKey(string $key, bool $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'isCancelKey', $key));
  }

  public static function dataProviderIsCancelKey(): \Iterator {
    yield 'interrupt' => ['ctrl-c', TRUE];
    yield 'escape' => ['escape', TRUE];
    yield 'end of input' => ['eof', TRUE];
    yield 'enter' => ['enter', FALSE];
    yield 'space' => ['space', FALSE];
    yield 'printable character' => ['x', FALSE];
  }

}
