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

  public function testVersionAfterImprinting(): void {
    $tmp_dir = __DIR__ . '/../../../.artifacts/tmp/version_test_' . getmypid();
    mkdir($tmp_dir, 0755, TRUE);

    // Embed Prompty into a temp copy of starter.php.
    $target = $tmp_dir . '/starter.php';
    copy(__DIR__ . '/../../../starter.php', $target);

    $embed_script = __DIR__ . '/../../../embed.php';
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' --no-killswitch ' . escapeshellarg($target) . ' 2>&1', $output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed failed: ' . implode("\n", $output));

    // Simulate release version imprinting via sed.
    $content = file_get_contents($target);
    $this->assertIsString($content);
    $content = str_replace('__PROMPTY_VERSION__', '1.2.3', $content);

    // Inject version output before the kill switch.
    $content = str_replace(
      "if (!getenv('SHOULD_PROCEED'))",
      "echo 'VERSION:' . Prompty::version() . PHP_EOL;\nif (!getenv('SHOULD_PROCEED'))",
      $content,
    );
    file_put_contents($target, $content);

    // Run the script with piped keystrokes to get through the flow.
    $keystrokes = $this->promptyKeys(
      'test', self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open('php ' . escapeshellarg($target), $descriptors, $pipes);
    $this->assertIsResource($process);

    fwrite($pipes[0], $keystrokes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $this->assertSame(0, $exit_code, 'Script failed: ' . $stderr);
    $this->assertIsString($stdout);
    $this->assertStringContainsString('VERSION:1.2.3', $stdout);

    // Clean up.
    array_map(unlink(...), glob($tmp_dir . '/*') ?: []);
    rmdir($tmp_dir);
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
