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
    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);

    // Class header docblock preserved.
    $this->assertStringContainsString('Zero-dependency interactive CLI prompt library', $content);
    $this->assertStringContainsString('@license MIT', $content);

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

    // Namespace removed from embedded output.
    $this->assertDoesNotMatchRegularExpression(
      '/^namespace\s+AlexSkrypnyk\\\\Prompty\s*;/m',
      $content,
    );

    // PHPStan ignore comment added before class declaration.
    $this->assertMatchesRegularExpression(
      '/\/\/ @phpstan-ignore-next-line\nclass Prompty/',
      $content,
    );

    // Embedded script runs correctly in a subprocess.
    $this->assertEmbeddedScriptWorks($target);
  }

  public function testEmbedCompact(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target, ['--compact']);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // ANSI escape sequences preserved.
    $this->assertStringContainsString('\033[', $content);

    // Public API preserved.
    $this->assertStringContainsString('function flow', $content);
    $this->assertStringContainsString('function text', $content);
    $this->assertStringContainsString('function select', $content);
    $this->assertStringContainsString('function confirm', $content);
    $this->assertStringContainsString('function configure', $content);

    // Internal names shortened.
    $this->assertStringNotContainsString('cfgSymbolsUnicode', $content);
    $this->assertStringNotContainsString('renderCompleted', $content);
    $this->assertStringNotContainsString('renderCancelled', $content);

    // Compact output is smaller than normal.
    $normal_target = $this->tmpDir . '/normal.php';
    copy(__DIR__ . '/../../../starter.php', $normal_target);
    $this->runEmbed($normal_target);
    $this->assertLessThan(filesize($normal_target), filesize($target));

    // Embedded script runs correctly in a subprocess.
    $this->assertEmbeddedScriptWorks($target);
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
    $this->assertStringContainsString('// @embed-start', $output_content);
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
    $this->assertStringContainsString('// @embed-start', $re_embedded);
    $this->assertStringContainsString('// @embed-end', $re_embedded);

    // Class still embedded.
    $this->assertStringContainsString('class Prompty', $re_embedded);
    $this->assertStringNotContainsString('require_once', $re_embedded);
  }

  public function testStdout(): void {
    $output_path = $this->tmpDir . '/Prompty.min.php';

    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd_output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' --stdout ' . escapeshellarg($output_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed --stdout failed: ' . implode("\n", $cmd_output));

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($output_path) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    $content = file_get_contents($output_path);
    $this->assertIsString($content);

    // Standalone PHP file with proper preamble.
    $this->assertStringContainsString('<?php', $content);
    $this->assertStringContainsString('declare(strict_types=1)', $content);
    $this->assertStringContainsString('namespace AlexSkrypnyk\Prompty', $content);
    $this->assertStringContainsString('class Prompty', $content);

    // Header docblock preserved, ANSI preserved.
    $this->assertStringContainsString('Zero-dependency interactive CLI prompt library', $content);
    $this->assertStringContainsString('\033[', $content);

    // Smaller than original.
    $this->assertLessThan(
      filesize(__DIR__ . '/../../../Prompty.php'),
      filesize($output_path),
    );
  }

  public function testStdoutCompact(): void {
    $min_path = $this->tmpDir . '/Prompty.min.php';
    $compact_path = $this->tmpDir . '/Prompty.compact.php';

    $embed_script = __DIR__ . '/../../../embed.php';

    // Generate both variants.
    $cmd_output = [];
    exec('php ' . escapeshellarg($embed_script) . ' --stdout ' . escapeshellarg($min_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code);

    $cmd_output = [];
    exec('php ' . escapeshellarg($embed_script) . ' --compact --stdout ' . escapeshellarg($compact_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code);

    // PHP lint passes on compact.
    exec('php -l ' . escapeshellarg($compact_path) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    // Compact is smaller than minimized.
    $this->assertLessThan(filesize($min_path), filesize($compact_path));

    // Public API preserved in compact.
    $content = file_get_contents($compact_path);
    $this->assertIsString($content);
    $this->assertStringContainsString('function flow', $content);
    $this->assertStringContainsString('function text', $content);

    // Internal names shortened.
    $this->assertStringNotContainsString('cfgSymbolsUnicode', $content);
    $this->assertStringNotContainsString('renderCompleted', $content);
  }

  public function testEmbedRunsRector(): void {
    $target = $this->prepareTarget();

    // Capture embed output to verify rector message.
    $embed_output = $this->runEmbedWithOutput($target);
    $this->assertStringContainsString('Rector', $embed_output);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);

    // Run rector --dry-run to confirm no further changes needed.
    $rector_config = $this->createRectorConfig();
    $output = [];
    $exit_code = 0;
    exec(
      'php ' . escapeshellarg(__DIR__ . '/../../../vendor/bin/rector') . ' process --dry-run --config=' . escapeshellarg($rector_config) . ' ' . escapeshellarg($target) . ' 2>&1',
      $output,
      $exit_code,
    );
    $this->assertSame(0, $exit_code, 'Rector would make further changes: ' . implode("\n", $output));
  }

  public function testEmbedStdoutRunsRector(): void {
    $output_path = $this->tmpDir . '/Prompty.rector.php';

    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd_output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' --stdout ' . escapeshellarg($output_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed --stdout failed: ' . implode("\n", $cmd_output));
    $this->assertStringContainsString('Rector', implode("\n", $cmd_output));

    // Run rector --dry-run to confirm no further changes needed.
    $rector_config = $this->createRectorConfig();
    $output = [];
    $exit_code = 0;
    exec(
      'php ' . escapeshellarg(__DIR__ . '/../../../vendor/bin/rector') . ' process --dry-run --config=' . escapeshellarg($rector_config) . ' ' . escapeshellarg($output_path) . ' 2>&1',
      $output,
      $exit_code,
    );
    $this->assertSame(0, $exit_code, 'Rector would make further changes on stdout output: ' . implode("\n", $output));
  }

  public function testEmbedKillswitchAlreadyPresent(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // Kill switch already existed in starter.php — should not be duplicated.
    $this->assertSame(
      1,
      substr_count($content, "if (!getenv('SHOULD_PROCEED'))"),
      'Kill switch should appear exactly once.',
    );
  }

  public function testEmbedKillswitchInjected(): void {
    $target = $this->prepareTargetWithoutKillswitch();

    $this->runEmbed($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // Kill switch should have been injected.
    $this->assertStringContainsString("if (!getenv('SHOULD_PROCEED'))", $content);

    // Should appear after the @embed-end marker.
    $embed_end_pos = strpos($content, '@embed-end');
    $killswitch_pos = strpos($content, "if (!getenv('SHOULD_PROCEED'))");
    $this->assertNotFalse($embed_end_pos);
    $this->assertNotFalse($killswitch_pos);
    $this->assertGreaterThan($embed_end_pos, $killswitch_pos);
  }

  public function testEmbedRunsVerification(): void {
    $target = $this->prepareTarget();

    // Run embed with simulated keystrokes for the interactive verification.
    $keystrokes = $this->promptyKeys(
      'my-project', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_SPACE, self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $embed_script = __DIR__ . '/../../../embed.php';
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = proc_open(
      'php ' . escapeshellarg($embed_script) . ' ' . escapeshellarg($target),
      $descriptors,
      $pipes,
    );
    $this->assertIsResource($process);

    fwrite($pipes[0], $keystrokes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $this->assertSame(0, $exit_code, 'Embed with verification failed: ' . $stderr);

    // Verification message should appear in output.
    $this->assertIsString($stdout);
    $this->assertStringContainsString('Verifying', $stdout);
  }

  public function testEmbedSkipsVerificationWithoutKillswitch(): void {
    $target = $this->prepareTargetWithoutKillswitch();

    $embed_output = $this->runEmbedWithOutput($target, ['--no-killswitch']);

    // Should show yellow warning about skipping verification.
    $this->assertStringContainsString('no kill switch', strtolower($embed_output));
    $this->assertStringNotContainsString('Verifying', $embed_output);
  }

  public function testEmbedNoKillswitchFlag(): void {
    $target = $this->prepareTargetWithoutKillswitch();

    $embed_output = $this->runEmbedWithOutput($target, ['--no-killswitch']);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // Kill switch should NOT have been injected.
    $this->assertStringNotContainsString("if (!getenv('SHOULD_PROCEED'))", $content);

    // Warning should have been shown.
    $this->assertStringContainsString('no kill switch', strtolower($embed_output));
  }

  public function testSourceFlag(): void {
    $target = $this->prepareTarget();

    // Copy Prompty.php to a different location.
    $alt_source = $this->tmpDir . '/AltPrompty.php';
    copy(__DIR__ . '/../../../Prompty.php', $alt_source);

    $this->runEmbed($target, ['--source', $alt_source]);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // Class embedded from the alternate source.
    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringNotContainsString('require_once', $content);

    // Embedded script runs correctly.
    $this->assertEmbeddedScriptWorks($target);
  }

  public function testSourceFlagStdout(): void {
    $output_path = $this->tmpDir . '/Prompty.source.php';

    // Copy Prompty.php to a different location.
    $alt_source = $this->tmpDir . '/AltPrompty.php';
    copy(__DIR__ . '/../../../Prompty.php', $alt_source);

    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd_output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($embed_script) . ' --source ' . escapeshellarg($alt_source) . ' --stdout ' . escapeshellarg($output_path) . ' 2>&1', $cmd_output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed --source --stdout failed: ' . implode("\n", $cmd_output));

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($output_path) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    $content = file_get_contents($output_path);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('namespace AlexSkrypnyk\Prompty', $content);
  }

  public function testReEmbedPreservesContentAndWorks(): void {
    $target = $this->prepareTarget();

    // First embed.
    $this->runEmbed($target);
    $first_content = file_get_contents($target);
    $this->assertIsString($first_content);
    $this->assertStringContainsString('class Prompty', $first_content);
    $this->assertStringNotContainsString('require_once', $first_content);

    // Verify the first embed works.
    $this->assertEmbeddedScriptWorks($target);

    // Simulate a user editing the script outside the embedded block.
    $modified = str_replace(
      "intro: 'Create a new project'",
      "intro: 'Create a NEW project'",
      $first_content,
    );
    $this->assertNotSame($first_content, $modified);
    file_put_contents($target, $modified);

    // Re-embed (simulating a version update).
    $this->runEmbed($target);
    $re_embedded = file_get_contents($target);
    $this->assertIsString($re_embedded);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed after re-embed: ' . implode("\n", $lint_output));

    // User's edit preserved.
    $this->assertStringContainsString("intro: 'Create a NEW project'", $re_embedded);

    // Markers still present.
    $this->assertStringContainsString('// @embed-start', $re_embedded);
    $this->assertStringContainsString('// @embed-end', $re_embedded);

    // Class still embedded.
    $this->assertStringContainsString('class Prompty', $re_embedded);
    $this->assertStringNotContainsString('require_once', $re_embedded);

    // Kill switch not duplicated.
    $this->assertSame(
      1,
      substr_count($re_embedded, "if (!getenv('SHOULD_PROCEED'))"),
      'Kill switch should appear exactly once after re-embed.',
    );

    // Embedded script still runs correctly after re-embed.
    $this->assertEmbeddedScriptWorks($target);
  }

  public function testReEmbedWithSourceFlag(): void {
    $target = $this->prepareTarget();

    // First embed with default source.
    $this->runEmbed($target);

    // Copy Prompty.php to simulate downloading a new version.
    $new_version = $this->tmpDir . '/PromptyNew.php';
    copy(__DIR__ . '/../../../Prompty.php', $new_version);

    // Re-embed with --source pointing at the "new version".
    $this->runEmbed($target, ['--source', $new_version]);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    // PHP lint passes.
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint_output, $lint_exit);
    $this->assertSame(0, $lint_exit, 'PHP lint failed: ' . implode("\n", $lint_output));

    // Class embedded, markers preserved.
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);

    // Embedded script works.
    $this->assertEmbeddedScriptWorks($target);
  }

  public function testUsageHelp(): void {
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/../../../embed.php') . ' 2>&1', $output, $exit_code);
    $this->assertSame(1, $exit_code);

    $help = implode("\n", $output);

    // Usage header present.
    $this->assertStringContainsString('Usage:', $help);

    // All flags documented.
    $this->assertStringContainsString('--source', $help);
    $this->assertStringContainsString('--compact', $help);
    $this->assertStringContainsString('--stdout', $help);
    $this->assertStringContainsString('--no-killswitch', $help);

    // Re-embedding documented.
    $this->assertStringContainsString('Re-embedding', $help);

    // Arguments section present.
    $this->assertStringContainsString('Arguments:', $help);
    $this->assertStringContainsString('Options:', $help);
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

    // --source without path.
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/../../../embed.php') . ' --source 2>&1', $output, $exit_code);
    $this->assertSame(1, $exit_code);
    $this->assertStringContainsString('--source requires a path', implode("\n", $output));

    // --source with non-existent file.
    $target = $this->prepareTarget();
    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/../../../embed.php') . ' --source /nonexistent/Prompty.php ' . escapeshellarg($target) . ' 2>&1', $output, $exit_code);
    $this->assertSame(1, $exit_code);
    $this->assertStringContainsString('Source class not found', implode("\n", $output));
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
   *
   * @param string $target
   *   Path to the target file.
   * @param list<string> $extra_args
   *   Additional CLI arguments.
   */

  /**
   * Run the embed script and return its combined output.
   *
   * @param string $target
   *   Path to the target file.
   * @param list<string> $extra_args
   *   Additional CLI arguments.
   */
  protected function runEmbedWithOutput(string $target, array $extra_args = []): string {
    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd = 'php ' . escapeshellarg($embed_script);
    foreach ($extra_args as $extra_arg) {
      $cmd .= ' ' . escapeshellarg($extra_arg);
    }
    $cmd .= ' ' . escapeshellarg($target);
    $output = [];
    $exit_code = 0;
    exec($cmd . ' 2>&1', $output, $exit_code);
    $combined = implode("\n", $output);
    $this->assertSame(0, $exit_code, 'Embed script failed: ' . $combined);

    return $combined;
  }

  protected function runEmbed(string $target, array $extra_args = []): void {
    $embed_script = __DIR__ . '/../../../embed.php';
    $cmd = 'php ' . escapeshellarg($embed_script);
    foreach ($extra_args as $extra_arg) {
      $cmd .= ' ' . escapeshellarg((string) $extra_arg);
    }
    $cmd .= ' ' . escapeshellarg($target);
    $output = [];
    $exit_code = 0;
    exec($cmd . ' 2>&1', $output, $exit_code);
    $this->assertSame(0, $exit_code, 'Embed script failed: ' . implode("\n", $output));
  }

  /**
   * Copy starter.php to temp dir without the kill switch block.
   */
  protected function prepareTargetWithoutKillswitch(): string {
    $source = __DIR__ . '/../../../starter.php';
    $content = file_get_contents($source);
    $this->assertIsString($content);

    // Remove the kill switch block.
    $content = preg_replace(
      '/\/\/ Kill switch.*?if \(!getenv\(\'SHOULD_PROCEED\'\)\) \{\s*return;\s*\}\n*/s',
      '',
      $content,
    );
    $this->assertIsString($content);

    $target = $this->tmpDir . '/starter_no_ks.php';
    file_put_contents($target, $content);

    return $target;
  }

  /**
   * Create a rector config file suitable for testing embedded output.
   */
  protected function createRectorConfig(): string {
    $config_path = $this->tmpDir . '/rector.php';
    copy(__DIR__ . '/../../../rector.php', $config_path);

    return $config_path;
  }

  /**
   * Run the embedded script in a subprocess and verify results.
   */
  protected function assertEmbeddedScriptWorks(string $target): void {
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
      . "echo json_encode(Prompty::results());\n"
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

}
