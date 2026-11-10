<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for a script that switches input off.
 */
#[CoversNothing]
#[Group('functional')]
final class NonInteractiveTest extends FunctionalTestCase {

  protected const string ANSWERS = 'Answers: [{"dish":"","course":"main","extras":["bread"],"send":true},"no nuts"]';

  public function testAnswersWithoutInput(): void {
    $output = $this->runScriptWithoutInput(self::$root . '/tests/phpunit/Fixtures/non-interactive.php');

    $this->assertStringContainsString(self::ANSWERS, $output);
  }

  public function testAnswersInTerminalWithoutKeystrokes(): void {
    $r = $this->runInTerminal(self::$root . '/tests/phpunit/Fixtures/non-interactive.php', 'Answers:', '');

    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertSame('', $r['stderr']);
    $this->assertStringContainsString(self::ANSWERS, $this->stripAnsi($r['stdout']));
    $this->assertStringNotContainsString("\033[?25l", $r['stdout'], 'The script hid the cursor, so it set up the terminal for input.');
  }

}
