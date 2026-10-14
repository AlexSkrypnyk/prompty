<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for starter.php.
 */
#[CoversNothing]
#[Group('functional')]
final class StarterScriptTest extends FunctionalTestCase {

  /**
   * Path to starter.php.
   */
  protected string $starterScript;

  protected function setUp(): void {
    parent::setUp();
    $this->starterScript = self::$root . '/starter.php';
  }

  public function testStarterFlowCompletes(): void {
    $this->assertStarterFlowWorks($this->starterScript);
  }

  /**
   * Run starter.php with keystrokes and assert the output contains strings.
   *
   * @param array<int, string> $keystrokes
   *   Keys fed to the script, joined into 1 raw input string.
   * @param array<int, string> $expected_strings
   *   Strings the ANSI-stripped output must contain.
   */
  #[DataProvider('dataProviderStarterOutput')]
  public function testStarterOutput(array $keystrokes, array $expected_strings): void {
    $output = $this->runScript($this->starterScript, $this->keys(...$keystrokes));

    foreach ($expected_strings as $expected_string) {
      $this->assertStringContainsString($expected_string, $output);
    }
  }

  public static function dataProviderStarterOutput(): \Iterator {
    yield 'third course selected' => [
      ['walnut loaf', self::KEYS['ENTER'], self::KEYS['DOWN'], self::KEYS['DOWN'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']],
      ['walnut loaf', 'Dessert', 'None', 'Yes'],
    ];

    yield 'empty dish uses placeholder' => [
      [self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']],
      ['pear tart', 'Starter'],
    ];

    yield 'all defaults reach intro and outro' => [
      [self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']],
      ['Compose an order', 'Order sent!'],
    ];

    yield 'declined send' => [
      ['onion soup', self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['LEFT'], self::KEYS['ENTER']],
      ['onion soup', 'No'],
    ];
  }

  public function testStarterCancelsWithoutInput(): void {
    $output = $this->runScriptWithoutInput($this->starterScript);

    $this->assertStringContainsString('(cancelled)', $output);
    $this->assertStringContainsString('Order cancelled.', $output);
    $this->assertStringNotContainsString('Order sent!', $output);
  }

  /**
   * Run starter.php with answers on stdin and assert what reaches the output.
   *
   * @param array<int, string> $inputs
   *   Lines fed to the script. An empty list cancels at the first prompt.
   * @param bool $should_proceed
   *   Whether SHOULD_PROCEED is set to '1' or removed from the environment.
   * @param array<int, string> $expected
   *   Strings the ANSI-stripped output must contain.
   * @param array<int, string> $unexpected
   *   Strings the ANSI-stripped output must not contain.
   */
  #[DataProvider('dataProviderStarterKillSwitch')]
  public function testStarterKillSwitch(array $inputs, bool $should_proceed, array $expected, array $unexpected): void {
    $this->processRun('php', [$this->starterScript], $inputs, ['SHOULD_PROCEED' => $should_proceed ? '1' : FALSE], 15, 15);
    $this->assertProcessSuccessful();

    $output = $this->stripAnsi($this->processGet()->getOutput());

    foreach ($expected as $expected_string) {
      $this->assertStringContainsString($expected_string, $output);
    }

    foreach ($unexpected as $unexpected_string) {
      $this->assertStringNotContainsString($unexpected_string, $output);
    }
  }

  public static function dataProviderStarterKillSwitch(): \Iterator {
    yield 'answered, kill switch unset' => [['plum compote', '', '', ''], FALSE, ['Order sent!'], ['Dish: plum compote']];

    yield 'answered, kill switch set' => [['plum compote', '', '', ''], TRUE, ['Order sent!', 'Dish: plum compote', 'Send: yes'], []];

    yield 'cancelled, kill switch set' => [[], TRUE, ['Order cancelled.'], ['Dish:']];
  }

}
