<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for starter.php.
 *
 * Runs starter.php in a subprocess with simulated keystrokes and asserts
 * on stdout output.
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

  public function testStarterSelectThirdFramework(): void {
    $keystrokes = $this->keys(
      'app', self::KEYS['ENTER'],
      self::KEYS['DOWN'], self::KEYS['DOWN'], self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
    );

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('app', $output);
    $this->assertStringContainsString('Svelte', $output);
    $this->assertStringContainsString('None', $output);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testStarterEmptyNameUsesPlaceholder(): void {
    $keystrokes = $this->keys(
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
    );

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('my-app', $output);
    $this->assertStringContainsString('React', $output);
  }

  public function testStarterDeclineInstall(): void {
    $keystrokes = $this->keys(
      'test', self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['LEFT'], self::KEYS['ENTER'],
    );

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('test', $output);
    $this->assertStringContainsString('No', $output);
  }

  public function testStarterOutputContainsIntroOutro(): void {
    $keystrokes = $this->keys(
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
      self::KEYS['ENTER'],
    );

    $output = $this->runScript($this->starterScript, $keystrokes);

    $this->assertStringContainsString('Create a new project', $output);
    $this->assertStringContainsString('Project created!', $output);
  }

}
