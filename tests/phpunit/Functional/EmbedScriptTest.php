<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for embed.php.
 *
 * Embeds Prompty into starter.php and verifies the result works correctly.
 */
#[CoversNothing]
#[Group('functional')]
final class EmbedScriptTest extends FunctionalTestCase {

  public function testEmbed(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    $this->assertPhpLintPasses($target);

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

    // Embedded script runs correctly.
    $this->assertStarterFlowWorks($target);
  }

  public function testEmbedCompact(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--compact', $target);

    $this->assertPhpLintPasses($target);

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
    copy($this->root . '/starter.php', $normal_target);
    $this->runEmbed($normal_target);
    $this->assertLessThan(filesize($normal_target), filesize($target));

    // Embedded script runs correctly.
    $this->assertStarterFlowWorks($target);
  }

  public function testEmbedWithOutputArgument(): void {
    $source = $this->prepareTarget();
    $output_path = $this->tmpDir . '/output.php';

    $this->runEmbed($source, $output_path);

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

    $this->assertPhpLintPasses($output_path);
  }

  public function testReEmbed(): void {
    $target = $this->prepareTarget();

    // First embed.
    $this->runEmbed($target);
    $first_content = file_get_contents($target);
    $this->assertIsString($first_content);
    $this->assertStringContainsString('class Prompty', $first_content);
    $this->assertStringNotContainsString('require_once', $first_content);

    // Verify the first embed works.
    $this->assertStarterFlowWorks($target);

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

    $this->assertPhpLintPasses($target);

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
  }

  public function testStdout(): void {
    $output_path = $this->generateMinified();

    $this->assertPhpLintPasses($output_path);

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
      filesize($this->root . '/Prompty.php'),
      filesize($output_path),
    );
  }

  public function testStdoutCompact(): void {
    $min_path = $this->generateMinified();
    $compact_path = $this->generateCompacted();

    $this->assertPhpLintPasses($compact_path);

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

    $embed_output = $this->runEmbedWithOutput($target);
    $this->assertStringContainsString('Rector', $embed_output);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);

    $this->assertRectorClean($target);
  }

  public function testEmbedStdoutRunsRector(): void {
    $output_path = $this->generateMinified();

    $this->assertRectorClean($output_path);
  }

  public function testEmbedKillswitchAlreadyPresent(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

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

    $keystrokes = $this->keys(
      'my-project', self::KEY_ENTER,
      self::KEY_DOWN, self::KEY_ENTER,
      self::KEY_SPACE, self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $r = $this->runWithKeystrokes('php ' . escapeshellarg($this->root . '/embed.php') . ' ' . escapeshellarg($target), $keystrokes);
    $this->assertSame(0, $r['exit_code'], 'Embed with verification failed: ' . $r['stderr']);
    $this->assertStringContainsString('Verifying', $r['stdout']);
  }

  public function testEmbedNoKillswitchFlag(): void {
    $target = $this->prepareTargetWithoutKillswitch();

    $embed_output = $this->runEmbedWithOutput('--no-killswitch', $target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertStringNotContainsString("if (!getenv('SHOULD_PROCEED'))", $content);
    $this->assertStringContainsString('no kill switch', strtolower($embed_output));
    $this->assertStringNotContainsString('Verifying', $embed_output);
  }

  public function testSourceFlag(): void {
    $target = $this->prepareTarget();
    $alt_source = $this->copyPromptySource();

    $this->runEmbed('--source', $alt_source, $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringNotContainsString('require_once', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testSourceFlagStdout(): void {
    $output_path = $this->tmpDir . '/Prompty.source.php';
    $alt_source = $this->copyPromptySource();

    $this->runEmbed('--source', $alt_source, '--stdout', $output_path);

    $this->assertPhpLintPasses($output_path);

    $content = file_get_contents($output_path);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('namespace AlexSkrypnyk\Prompty', $content);
  }

  public function testReEmbedWithSourceFlag(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    $new_version = $this->copyPromptySource();

    $this->runEmbed('--source', $new_version, $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testSourceFlagWithMinifiedInput(): void {
    $target = $this->prepareTarget();
    $min_source = $this->generateMinified();

    $this->runEmbed('--source', $min_source, $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('// @embed-start', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testSourceFlagWithCompactedInput(): void {
    $target = $this->prepareTarget();
    $compact_source = $this->generateCompacted();

    $this->runEmbed('--source', $compact_source, $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('// @embed-start', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testSourceFlagWithCompactedInputAndCompactFlag(): void {
    $target = $this->prepareTarget();
    $compact_source = $this->generateCompacted();

    $this->runEmbed('--source', $compact_source, '--compact', $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testUsageHelp(): void {
    $r = $this->runEmbedRaw();
    $this->assertSame(1, $r['exit_code']);

    $this->assertStringContainsString('Usage:', $r['output']);
    $this->assertStringContainsString('--source', $r['output']);
    $this->assertStringContainsString('--compact', $r['output']);
    $this->assertStringContainsString('--stdout', $r['output']);
    $this->assertStringContainsString('--no-killswitch', $r['output']);
    $this->assertStringContainsString('Re-embedding', $r['output']);
    $this->assertStringContainsString('Arguments:', $r['output']);
    $this->assertStringContainsString('Options:', $r['output']);
  }

  public function testEmbedErrors(): void {
    // Missing argument.
    $r = $this->runEmbedRaw();
    $this->assertSame(1, $r['exit_code']);
    $this->assertStringContainsString('Usage', $r['output']);

    // Missing markers.
    $target = $this->tmpDir . '/no-markers.php';
    file_put_contents($target, "<?php\necho 'hello';\n");

    $r = $this->runEmbedRaw($target);
    $this->assertSame(1, $r['exit_code']);
    $this->assertStringContainsString('marker', $r['output']);

    // --source without path.
    $r = $this->runEmbedRaw('--source');
    $this->assertSame(1, $r['exit_code']);
    $this->assertStringContainsString('--source requires a path', $r['output']);

    // --source with non-existent file.
    $target = $this->prepareTarget();
    $r = $this->runEmbedRaw('--source', '/nonexistent/Prompty.php', $target);
    $this->assertSame(1, $r['exit_code']);
    $this->assertStringContainsString('Source class not found', $r['output']);
  }

  public function testVersionAfterImprinting(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--no-killswitch', $target);

    // Simulate release version imprinting.
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

    $keystrokes = $this->keys(
      'test', self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
      self::KEY_ENTER,
    );

    $r = $this->runWithKeystrokes('php ' . escapeshellarg($target), $keystrokes);
    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertStringContainsString('VERSION:1.2.3', $r['stdout']);
  }

  /**
   * Copy starter.php to temp dir and return the path.
   */
  protected function prepareTarget(): string {
    $target = $this->tmpDir . '/starter.php';
    copy($this->root . '/starter.php', $target);

    return $target;
  }

  /**
   * Copy starter.php to temp dir without the kill switch block.
   */
  protected function prepareTargetWithoutKillswitch(): string {
    $content = file_get_contents($this->root . '/starter.php');
    $this->assertIsString($content);

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
   * Run embed.php and assert success (discard output).
   */
  protected function runEmbed(string ...$args): void {
    $this->runEmbedWithOutput(...$args);
  }

  /**
   * Run embed.php and assert success.
   *
   * @return string
   *   Combined stdout+stderr output.
   */
  protected function runEmbedWithOutput(string ...$args): string {
    $r = $this->runEmbedRaw(...$args);
    $this->assertSame(0, $r['exit_code'], 'Embed script failed: ' . $r['output']);

    return $r['output'];
  }

  /**
   * Run embed.php without asserting exit code.
   *
   * @return array{output: string, exit_code: int}
   *   Output and exit code.
   */
  protected function runEmbedRaw(string ...$args): array {
    $cmd = 'php ' . escapeshellarg($this->root . '/embed.php');
    foreach ($args as $arg) {
      $cmd .= ' ' . escapeshellarg($arg);
    }
    $output = [];
    $exit_code = 0;
    exec($cmd . ' 2>&1', $output, $exit_code);

    return [
      'output' => implode("\n", $output),
      'exit_code' => $exit_code,
    ];
  }

  /**
   * Copy Prompty.php to the temp directory.
   */
  protected function copyPromptySource(): string {
    $path = $this->tmpDir . '/Prompty.php';
    copy($this->root . '/Prompty.php', $path);

    return $path;
  }

  /**
   * Generate a minified Prompty.php via --stdout.
   */
  protected function generateMinified(): string {
    $path = $this->tmpDir . '/Prompty.min.php';
    $this->runEmbed('--stdout', $path);

    return $path;
  }

  /**
   * Generate a compacted Prompty.php via --compact --stdout.
   */
  protected function generateCompacted(): string {
    $path = $this->tmpDir . '/Prompty.compact.php';
    $this->runEmbed('--compact', '--stdout', $path);

    return $path;
  }

  /**
   * Create a rector config file suitable for testing embedded output.
   */
  protected function createRectorConfig(): string {
    $config_path = $this->tmpDir . '/rector.php';
    copy($this->root . '/rector.php', $config_path);

    return $config_path;
  }

  /**
   * Assert rector --dry-run reports no changes needed.
   */
  protected function assertRectorClean(string $target): void {
    $rector_config = $this->createRectorConfig();
    $output = [];
    $exit_code = 0;
    exec(
      'php ' . escapeshellarg($this->root . '/vendor/bin/rector') . ' process --dry-run --config=' . escapeshellarg($rector_config) . ' ' . escapeshellarg($target) . ' 2>&1',
      $output,
      $exit_code,
    );
    $this->assertSame(0, $exit_code, 'Rector would make further changes: ' . implode("\n", $output));
  }

}
