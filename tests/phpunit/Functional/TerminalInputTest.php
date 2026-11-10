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

  public function testStepBackInTerminal(): void {
    // Ctrl+U is the terminal's line-kill key, which reaches the flow only
    // once canonical mode is off.
    $keystrokes = self::KEYS['DOWN'] . self::KEYS['ENTER'] . "\x15" . self::KEYS['DOWN'] . self::KEYS['ENTER'] . self::KEYS['ENTER'];

    $r = $this->runInTerminal(self::$root . '/tests/phpunit/Fixtures/flow-back.php', 'Course', $keystrokes);

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);
    $this->assertStringContainsString("'course' => 'dessert'", $this->stripAnsi($r['stdout']));
    $this->assertStringContainsString("'table' => '4'", $this->stripAnsi($r['stdout']));
  }

}
