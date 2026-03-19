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
 * Functional tests for embed.php.
 */
#[CoversClass(Prompty::class)]
#[Group('functional')]
final class EmbedScriptTest extends TestCase {

  use PromptyTestTrait;

  /**
   * Temporary directory for test artifacts.
   */
  protected string $tmpDir;

  protected function setUp(): void {
    parent::setUp();
    $this->tmpDir = __DIR__ . '/../../../.artifacts/tmp/embed_test_' . getmypid();
    mkdir($this->tmpDir, 0755, TRUE);
  }

  protected function tearDown(): void {
    $this->promptyTearDown();

    // Clean up temp directory.
    if (is_dir($this->tmpDir)) {
      $files = glob($this->tmpDir . '/*');
      if ($files !== FALSE) {
        array_map(unlink(...), $files);
      }
      rmdir($this->tmpDir);
    }

    parent::tearDown();
  }

  public function testEmbed(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // Markers preserved.
    $this->assertStringContainsString('// @prompty-start', $content);
    $this->assertStringContainsString('// @prompty-end', $content);

    // Class header docblock preserved.
    $this->assertStringContainsString('Zero-dependency interactive CLI prompt library', $content);
    $this->assertStringContainsString('@license MIT', $content);
    $this->assertStringContainsString('Alex Skrypnyk', $content);

    // Other Prompty.php docblocks stripped.
    $this->assertStringNotContainsString('Singleton instance', $content);
    $this->assertStringNotContainsString('Stored TTY settings', $content);

    // require_once replaced by embedded class.
    $this->assertStringNotContainsString('require_once', $content);

    // 'use' statement for Prompty namespace removed.
    $this->assertStringNotContainsString('use AlexSkrypnyk\Prompty\Prompty', $content);

    // Entire class is on a single line.
    $matched = preg_match('/^(class Prompty \{.+\})$/m', $content, $matches);
    $this->assertSame(1, $matched);

    // ANSI escape sequences preserved.
    $this->assertStringContainsString('\033[', $content);

    // Run the embedded script in a subprocess to verify it actually works.
    $keystrokes = $this->promptyKeys(
      'my-project', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_SPACE, self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $wrapper = $this->tmpDir . '/run_embedded.php';
    $escaped_target = addcslashes($target, "'\\");
    file_put_contents($wrapper, "<?php\n"
      . "declare(strict_types=1);\n"
      . "require_once '{$escaped_target}';\n"
      . "// If we got here, the script ran without errors.\n"
      . ('echo json_encode(' . Prompty::class . '::results());
')
    );

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = proc_open('php ' . escapeshellarg($wrapper), $descriptors, $pipes);
    $this->assertIsResource($process);

    fwrite($pipes[0], $keystrokes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $this->assertSame(0, $exit_code, 'Embedded script failed: ' . $stderr);

    $this->assertIsString($stdout);
    $lines = array_filter(explode("\n", trim($stdout)));
    $json_line = end($lines);
    $this->assertIsString($json_line);
    $results = json_decode($json_line, TRUE);
    $this->assertIsArray($results);
    $this->assertSame('my-project', $results['name']);
    $this->assertSame('vue', $results['framework']);
    $this->assertSame(['ts'], $results['features']);
    $this->assertTrue($results['install']);
  }

  public function testEmbedWithOutputArgument(): void {
    $source = $this->prepareTarget();
    $output_path = $this->tmpDir . '/output.php';

    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd_output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' ' . escapeshellarg($source) . ' ' . escapeshellarg($output_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed script failed: ' . implode("\n", $cmd_output));

    // Source unchanged.
    $source_content = file_get_contents($source);
    $this->assertIsString($source_content);
    $this->assertStringContainsString('require_once', $source_content);

    // Output has embedded class and passes lint.
    $this->assertFileExists($output_path);
    $output_content = file_get_contents($output_path);
    $this->assertIsString($output_content);
    $this->assertStringNotContainsString('require_once', $output_content);
    $this->assertStringContainsString('// @prompty-start', $output_content);
    $this->assertStringContainsString('class Prompty', $output_content);

    exec('php -l ' . escapeshellarg($output_path) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed on output: ' . implode("\n", $lint_output));
  }

  public function testReEmbed(): void {
    $target = $this->prepareTarget();

    // First embed.
    $this->runEmbed($target);
    $first_content = file_get_contents($target);
    $this->assertIsString($first_content);
    $this->assertStringContainsString('class Prompty', $first_content);

    // Simulate a user editing the script outside the embedded block.
    $modified = str_replace(
      "intro: 'Create a new project'",
      "intro: 'Create a NEW project'",
      $first_content,
    );
    $this->assertNotSame($first_content, $modified);
    file_put_contents($target, $modified);

    // Re-embed.
    $this->runEmbed($target);
    $re_embedded = file_get_contents($target);
    $this->assertIsString($re_embedded);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed after re-embed: ' . implode("\n", $lint_output));

    // User's edit preserved.
    $this->assertStringContainsString("intro: 'Create a NEW project'", $re_embedded);

    // Markers still present.
    $this->assertStringContainsString('// @prompty-start', $re_embedded);
    $this->assertStringContainsString('// @prompty-end', $re_embedded);

    // Class still embedded.
    $this->assertStringContainsString('class Prompty', $re_embedded);
    $this->assertStringNotContainsString('require_once', $re_embedded);
  }

  public function testEmbedErrors(): void {
    // Missing argument.
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/../../../embed.php') . ' 2>&1', $output, $exit_code);
    $this->assertSame(1, $exit_code);
    $this->assertStringContainsString('Usage', implode("\n", $output));

    // Missing markers.
    $target = $this->tmpDir . '/no-markers.php';
    file_put_contents($target, "<?php\necho 'hello';\n");

    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/../../../embed.php') . ' ' . escapeshellarg($target) . ' 2>&1', $output, $exit_code);
    $this->assertSame(1, $exit_code);
    $this->assertStringContainsString('marker', implode("\n", $output));
  }

  /**
   * Copy starter.php to temp dir and return the path.
   */
  protected function prepareTarget(): string {
    $source = __DIR__ . '/../../../starter.php';
    $target = $this->tmpDir . '/starter.php';
    copy($source, $target);

    return $target;
  }

  /**
   * Run the embed script on a target file.
   */
  protected function runEmbed(string $target): void {
    $embed_script = __DIR__ . '/../../../embed.php';
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed script failed: ' . implode("\n", $output));
  }

}
