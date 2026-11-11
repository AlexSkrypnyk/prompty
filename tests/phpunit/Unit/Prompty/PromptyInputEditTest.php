<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for editing the text and suggest input at a movable cursor.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyInputEditTest extends PromptyTestCase {

  #[DataProvider('dataProviderEditInput')]
  public function testEditInput(string $key, string $before, string $after, array $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'editInput', $key, $before, $after));
  }

  public static function dataProviderEditInput(): \Iterator {
    yield 'left moves 1 character back' => ['left', 'abc', '', ['ab', 'c']];
    yield 'left at the start does nothing' => ['left', '', 'abc', ['', 'abc']];
    yield 'right moves 1 character on' => ['right', 'a', 'bc', ['ab', 'c']];
    yield 'right at the end does nothing' => ['right', 'abc', '', ['abc', '']];
    yield 'home jumps to the start' => ['home', 'ab', 'c', ['', 'abc']];
    yield 'end jumps to the end' => ['end', 'a', 'bc', ['abc', '']];
    yield 'backspace deletes before the cursor' => ['backspace', 'ab', 'c', ['a', 'c']];
    yield 'backspace at the start does nothing' => ['backspace', '', 'abc', ['', 'abc']];
    yield 'delete deletes under the cursor' => ['delete', 'a', 'bc', ['a', 'c']];
    yield 'delete at the end does nothing' => ['delete', 'abc', '', ['abc', '']];
    yield 'space inserts at the cursor' => ['space', 'a', 'b', ['a ', 'b']];
    yield 'character inserts at the cursor' => ['x', 'a', 'b', ['ax', 'b']];
    yield 'utf8 byte inserts at the cursor' => ["\xC4", 'a', 'b', ["a\xC4", 'b']];
    yield 'control byte is rejected' => ["\x1c", 'a', 'b', ['a', 'b']];
    yield 'unhandled key is ignored' => ['up', 'a', 'b', ['a', 'b']];
    yield 'left steps over a two-byte character' => ['left', "pi\u{0105}", '', ['pi', "\u{0105}"]];
    yield 'right steps over a four-byte character' => ['right', '', "\u{1F350}a", ["\u{1F350}", 'a']];
    yield 'left steps over a combining mark with its base' => ['left', "cafe\u{0301}", '', ['caf', "e\u{0301}"]];
    yield 'delete removes a joined emoji sequence' => ['delete', '', "\u{1F469}\u{200D}\u{1F373}a", ['', 'a']];
    yield 'backspace removes a flag' => ['backspace', "a\u{1F1EB}\u{1F1F7}", '', ['a', '']];
    yield 'left steps over 1 byte of text that is not utf8' => ['left', "ab\xFF", '', ['ab', "\xFF"]];
    yield 'delete removes 1 byte of text that is not utf8' => ['delete', '', "\xFFab", ['', 'ab']];
  }

  #[DataProvider('dataProviderRenderInput')]
  public function testRenderInput(bool $is_ansi, string $before, string $after, string $default, string $placeholder, string $expected): void {
    $p = $this->createInstance(['ansi' => $is_ansi]);

    $this->assertSame($expected, $this->callProtected($p, 'renderInput', $before, $after, $default, $placeholder));
  }

  public static function dataProviderRenderInput(): \Iterator {
    $block = "\033[36m█\033[0m";
    yield 'empty shows the placeholder' => [TRUE, '', '', 'tart', 'e.g. tart', "\033[90me.g. tart\033[0m" . $block];
    yield 'default is dim' => [TRUE, 'tart', '', 'tart', '', "\033[2mtart\033[0m" . $block];
    yield 'edited value is white' => [TRUE, 'tarts', '', 'tart', '', "\033[37mtarts\033[0m" . $block];
    yield 'cursor inside the default' => [TRUE, 'ta', 'rt', 'tart', '', "\033[2mta\033[0m\033[36;7mr\033[0m\033[2mt\033[0m"];
    yield 'cursor on a wide character' => [TRUE, 'a', "\u{1F350}b", '', '', "\033[37ma\033[0m\033[36;7m\u{1F350}\033[0m\033[37mb\033[0m"];
    yield 'cursor at the start' => [TRUE, '', 'ab', '', '', "\033[37m\033[0m\033[36;7ma\033[0m\033[37mb\033[0m"];
    yield 'control characters are removed' => [TRUE, "a\u{0085}", "\u{0085}b", '', '', "\033[37ma\033[0m\033[36;7mb\033[0m\033[37m\033[0m"];
    yield 'no ansi draws the block at the end' => [FALSE, 'tart', '', 'tart', '', 'tart█'];
    yield 'no ansi draws the block before the character' => [FALSE, 'ta', 'rt', 'tart', '', 'ta█rt'];
    yield 'no ansi empty shows the placeholder' => [FALSE, '', '', '', 'tart', 'tart█'];
  }

  public function testRenderInputDrawsTheBlockWhenTheCursorStyleIsEmpty(): void {
    $p = $this->createInstance(['ansi' => TRUE]);
    /** @var array<string, string> $colors */
    $colors = $this->getProperty($p, 'cfgColors');
    $this->setProperty($p, 'cfgColors', ['input_cursor' => ''] + $colors);

    $this->assertSame("\033[37mta\033[0m\033[36m█\033[0mr\033[37mt\033[0m", $this->callProtected($p, 'renderInput', 'ta', 'rt', '', ''));
  }

  #[DataProvider('dataProviderTextEdit')]
  public function testTextEdit(string $keystrokes, string $default, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', default: $default, ctx: $this->defaultCtx()), $keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextEdit(): \Iterator {
    yield 'insert before the last character' => ['abc' . self::KEY_LEFT . 'x', '', 'abxc'];
    yield 'backspace in the middle' => ['abc' . self::KEY_LEFT . self::KEY_LEFT . self::KEY_BACKSPACE, '', 'bc'];
    yield 'delete at the start' => ['abc' . self::KEY_HOME . self::KEY_DELETE, '', 'bc'];
    yield 'type at the end after home' => ['abc' . self::KEY_HOME . self::KEY_END . 'x', '', 'abcx'];
    yield 'right past the end' => ['ab' . self::KEY_RIGHT . 'c', '', 'abc'];
    yield 'left past the start' => ['ab' . self::KEY_HOME . self::KEY_LEFT . 'x', '', 'xab'];
    yield 'insert before a multibyte character' => ["pi\u{0105}" . self::KEY_LEFT . 'x', '', "pix\u{0105}"];
    yield 'utf8 typed in the middle' => ['ab' . self::KEY_LEFT . "\u{0105}", '', "a\u{0105}b"];
    yield 'edit inside the default' => [self::KEY_HOME . 'ripe ', 'pear tart', 'ripe pear tart'];
    yield 'replace a word in the default' => [str_repeat(self::KEY_LEFT, 5) . str_repeat(self::KEY_BACKSPACE, 4) . 'plum', 'pear tart', 'plum tart'];
  }

  public function testTextMovesTheCursorWithoutClearingTheError(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', required: TRUE, ctx: $this->defaultCtx()), self::KEY_ENTER . self::KEY_LEFT . 'x' . self::KEY_ENTER);
    $output = $this->stripAnsi($r['output']);

    $this->assertSame('x', $r['result']);
    $this->assertSame(2, substr_count($output, 'Required.'));
  }

  #[DataProvider('dataProviderSuggestEdit')]
  public function testSuggestEdit(string $keystrokes, string $default, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::suggest('Dessert', options: ['Pear Tart', 'Plum Compote'], default: $default, ctx: $this->defaultCtx()), $keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderSuggestEdit(): \Iterator {
    yield 'insert in the middle' => ['Par' . self::KEY_LEFT . self::KEY_LEFT . 'e', '', 'Pear'];
    yield 'edit inside the default' => [self::KEY_HOME . self::KEY_DELETE . 'R', 'pear', 'Rear'];
    yield 'moving keeps the focused match' => ['P' . self::KEY_DOWN . self::KEY_LEFT, '', 'Pear Tart'];
    yield 'editing clears the focused match' => ['P' . self::KEY_DOWN . self::KEY_END . 'l', '', 'Pl'];
  }

}
