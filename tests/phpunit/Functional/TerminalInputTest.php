<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for a widget reading keys from a real terminal.
 *
 * A terminal delivers each keypress when it happens, while a memory stream or
 * a pipe holds every byte from the start, so only a terminal shows how long a
 * widget waits for input.
 */
#[CoversNothing]
#[Group('functional')]
final class TerminalInputTest extends FunctionalTestCase {

  #[DataProvider('dataProviderSelectInTerminal')]
  public function testSelectInTerminal(string $keystrokes, string $expected): void {
    $r = $this->runInTerminal(self::$root . '/tests/phpunit/Fixtures/select.php', 'Course', $keystrokes);

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);
    $this->assertStringContainsString('Answer: ' . $expected, $this->stripAnsi($r['stdout']));
  }

  public static function dataProviderSelectInTerminal(): \Iterator {
    yield 'escape' => [self::KEYS['ESCAPE'], 'NULL'];
    yield 'interrupt' => [self::KEYS['CTRL_C'], 'NULL'];
    yield 'cursor key' => [self::KEYS['DOWN'] . self::KEYS['ENTER'], "'main'"];
    yield 'cursor key in application mode' => [self::KEYS['DOWN_ARROW'] . self::KEYS['ENTER'], "'main'"];
    yield 'unrecognised key' => [self::KEYS['DELETE'] . self::KEYS['ENTER'], "'starter'"];
  }

  #[DataProvider('dataProviderPauseInTerminal')]
  public function testPauseInTerminal(string $keystrokes, bool $enter_only, string $expected): void {
    $r = $this->runInTerminal(self::$root . '/tests/phpunit/Fixtures/pause.php', 'Press any key', $keystrokes, $enter_only ? ['--enter-only'] : []);

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);
    $this->assertStringContainsString('Answer: ' . $expected, $this->stripAnsi($r['stdout']));
  }

  public static function dataProviderPauseInTerminal(): \Iterator {
    yield 'letter without enter' => ['x', FALSE, 'true'];
    yield 'cursor key' => [self::KEYS['DOWN'], FALSE, 'true'];
    yield 'escape' => [self::KEYS['ESCAPE'], FALSE, 'NULL'];
    yield 'interrupt' => [self::KEYS['CTRL_C'], FALSE, 'NULL'];
    yield 'enter only, after a letter' => ['x' . self::KEYS['ENTER'], TRUE, 'true'];
  }

  #[DataProvider('dataProviderStepBackInTerminal')]
  public function testStepBackInTerminal(string $back_key, string $byte): void {
    $keystrokes = self::KEYS['DOWN'] . self::KEYS['ENTER'] . $byte . self::KEYS['DOWN'] . self::KEYS['ENTER'] . self::KEYS['ENTER'];

    $r = $this->runInTerminal(self::$root . '/tests/phpunit/Fixtures/flow-back.php', 'Course', $keystrokes, ['--back-key=' . $back_key]);

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);
    $this->assertStringContainsString("'course' => 'dessert'", $this->stripAnsi($r['stdout']));
    $this->assertStringContainsString("'table' => '4'", $this->stripAnsi($r['stdout']));
  }

  public static function dataProviderStepBackInTerminal(): \Iterator {
    // The terminal driver claims each of these bytes in its default mode:
    // Ctrl+U kills the line, Ctrl+S and Ctrl+Q control the flow of output,
    // and Ctrl+V quotes the next byte.
    yield 'ctrl-u' => ['ctrl-u', "\x15"];
    yield 'ctrl-s' => ['ctrl-s', "\x13"];
    yield 'ctrl-q' => ['ctrl-q', "\x11"];
    yield 'ctrl-v' => ['ctrl-v', "\x16"];
  }

}
