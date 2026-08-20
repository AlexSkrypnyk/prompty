<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for widgets reading past the end of the input stream.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyEndOfInputTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives'];

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
