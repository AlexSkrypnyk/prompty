<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ANSI stripper in PromptyTestTrait.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyStripAnsiTest extends PromptyTestCase {

  #[DataProvider('dataProviderStripAnsi')]
  public function testStripAnsi(string $text, string $expected): void {
    $this->assertSame($expected, $this->stripAnsi($text));
  }

  public static function dataProviderStripAnsi(): \Iterator {
    yield 'plain text' => ['hello', 'hello'];
    yield 'reset' => ["\033[0mhello", 'hello'];
    yield 'color' => ["\033[36mhello\033[0m", 'hello'];
    yield 'multi parameter' => ["\033[2;3mhello\033[0m", 'hello'];
    yield 'cursor up' => ["\033[2Ahello", 'hello'];
    yield 'hide cursor' => ["\033[?25lhello", 'hello'];
    yield 'show cursor' => ["\033[?25hhello", 'hello'];
    yield 'cursor pair around text' => ["\033[?25lhello\033[?25h", 'hello'];
    yield 'mixed private and standard' => ["\033[?25l\033[36mhello\033[0m\033[?25h", 'hello'];
    yield 'no escapes to strip' => ['[?25l', '[?25l'];
  }

  public function testStripAnsiRemovesCursorSequencesPromptyEmits(): void {
    $p = $this->createInstance();

    ob_start();
    $this->callProtected($p, 'hideCursor');
    $this->callProtected($p, 'showCursor');
    $raw = ob_get_clean();

    $this->assertIsString($raw);
    $this->assertNotSame('', $raw);
    $this->assertSame('', $this->stripAnsi($raw));
  }

}
