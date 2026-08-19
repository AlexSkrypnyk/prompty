<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
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

  public function testStarterSelectThirdCourse(): void {
    $keystrokes = $this->keys(
      'walnut loaf', self::KEYS['ENTER'],
      self::KEYS['DOWN'], self::KEYS['DOWN'], self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
    );

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('walnut loaf', $output);
    $this->assertStringContainsString('Dessert', $output);
    $this->assertStringContainsString('None', $output);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testStarterEmptyDishUsesPlaceholder(): void {
    $keystrokes = $this->keys(self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']);

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('pear tart', $output);
    $this->assertStringContainsString('Starter', $output);
  }

  public function testStarterDeclineSend(): void {
    $keystrokes = $this->keys('onion soup', self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['LEFT'], self::KEYS['ENTER']);

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('onion soup', $output);
    $this->assertStringContainsString('No', $output);
  }

  public function testStarterOutputContainsIntroOutro(): void {
    $keystrokes = $this->keys(self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']);

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('Compose an order', $output);
    $this->assertStringContainsString('Order sent!', $output);
  }

}
