<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for tasks drawn to a real terminal.
 *
 * Only a terminal makes a task redraw in place, fork its spinner, and pass
 * a child process's output through the stream's buffer.
 */
#[CoversNothing]
#[Group('functional')]
final class TaskTerminalTest extends FunctionalTestCase {

  public function testTasksOnTerminal(): void {
    $r = $this->runOnTerminal(self::$root . '/tests/phpunit/Fixtures/task.php');

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);

    $stdout = str_replace("\r\n", "\n", $r['stdout']);
    $plain = $this->stripAnsi($stdout);

    $this->assertStringContainsString("+  Preheating the oven\n|  + Oven at 180\n|\n", $plain);
    $this->assertStringContainsString("o  Baking the tart\n|  ^ 3 more\n|    Tray 3 in\n|    Tray 4 in\n|    Tray 5 in\n|    Tray 6 in\n|    Fan cycling\n", $plain);
    $this->assertStringContainsString("+  Baking the tart\n|  + Tart baked (8 lines)\n|\n", $plain);
    $this->assertStringContainsString("Answers: array (\n  0 => '180',\n  1 => true,\n)", $plain);

    // Every line of the child process is drawn under the label, never raw.
    $this->assertDoesNotMatchRegularExpression('/^Tray \d in$/m', $plain);

    if (function_exists('pcntl_fork') && function_exists('posix_kill')) {
      $this->assertStringContainsString("/  Preheating the oven\n", $plain, 'The spinner drew a second frame.');
    }
  }

}
