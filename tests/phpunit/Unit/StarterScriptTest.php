<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../Prompty.php';
require_once __DIR__ . '/../../../PromptyTestTrait.php';

/**
 * Tests for starter.php — demonstrates the consumer testing pattern.
 *
 * Uses PromptyTestTrait directly (not PromptyTestCase) to prove the trait
 * works standalone, exactly as a consumer would use it.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class StarterScriptTest extends TestCase {

  use PromptyTestTrait;

  protected function tearDown(): void {
    $this->promptyTearDown();
    parent::tearDown();
  }

  public function testStarterFlowCompletes(): void {
    // Keystrokes: type "my-project" + Enter, select Vue (down+enter),
    // toggle TypeScript (space) + submit (enter), confirm Yes (enter).
    $keystrokes = $this->promptyKeys(
      'my-project', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_SPACE, self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $this->promptyRunScript(function (): void {
      require __DIR__ . '/../../../starter.php';
    }, $keystrokes);

    $results = Prompty::results();
    $this->assertSame('my-project', $results['name']);
    $this->assertSame('vue', $results['framework']);
    $this->assertSame(['ts'], $results['features']);
    $this->assertTrue($results['install']);
  }

  public function testStarterSelectThirdFramework(): void {
    $keystrokes = $this->promptyKeys(
      'app', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $this->promptyRunScript(function (): void {
      require __DIR__ . '/../../../starter.php';
    }, $keystrokes);

    $results = Prompty::results();
    $this->assertSame('app', $results['name']);
    $this->assertSame('svelte', $results['framework']);
    $this->assertSame([], $results['features']);
    $this->assertTrue($results['install']);
  }

  public function testStarterEmptyNameUsesPlaceholder(): void {
    $keystrokes = $this->promptyKeys(
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $this->promptyRunScript(function (): void {
      require __DIR__ . '/../../../starter.php';
    }, $keystrokes);

    $results = Prompty::results();
    $this->assertSame('my-app', $results['name']);
    $this->assertSame('react', $results['framework']);
  }

  public function testStarterDeclineInstall(): void {
    $keystrokes = $this->promptyKeys(
      'test', self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_LEFT, self::KEY_ENTER,
    );

    $this->promptyRunScript(function (): void {
      require __DIR__ . '/../../../starter.php';
    }, $keystrokes);

    $results = Prompty::results();
    $this->assertSame('test', $results['name']);
    $this->assertFalse($results['install']);
  }

  public function testStarterOutputContainsIntroOutro(): void {
    $keystrokes = $this->promptyKeys(
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $r = $this->promptyRunScript(function (): void {
      require __DIR__ . '/../../../starter.php';
    }, $keystrokes);

    $this->assertStringContainsString('Create a new project', $r['output']);
    $this->assertStringContainsString('Project created!', $r['output']);
  }

}
