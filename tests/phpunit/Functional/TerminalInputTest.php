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

}
