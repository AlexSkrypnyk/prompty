<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for embed.php.
 */
#[CoversNothing]
#[Group('functional')]
final class EmbedScriptTest extends FunctionalTestCase {

  protected function prepareTarget(): string {
    $target = self::$tmp . '/starter.php';
    copy(self::$root . '/starter.php', $target);

    return $target;
  }

  protected function prepareTargetWithoutKillswitch(): string {
    $content = file_get_contents(self::$root . '/starter.php');
    $this->assertIsString($content);

    $content = preg_replace('/\/\/ Kill switch.*?if \(!getenv\(\'SHOULD_PROCEED\'\)\) \{\s*return;\s*\}\n*/s', '', $content);
    $this->assertIsString($content);

    $target = self::$tmp . '/starter.no-killswitch.php';
    file_put_contents($target, $content);

    return $target;
  }

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
    $this->processRun('php', array_merge([self::$root . '/embed.php'], $args));
    $this->assertProcessSuccessful();

    return $this->processGet()->getOutput() . $this->processGet()->getErrorOutput();
  }

  protected function assertPhpLintPasses(string $path): void {
    $this->processRun('php', ['-l', $path]);
    $this->assertProcessSuccessful('PHP lint failed');
  }

  protected function copyPromptySource(): string {
    $path = self::$tmp . '/Prompty.php';
    copy(self::$root . '/Prompty.php', $path);

    return $path;
  }

  protected function generateMinified(): string {
    $path = self::$tmp . '/Prompty.min.php';
    $this->runEmbed('--stdout', $path);

    return $path;
  }

  protected function generateCompacted(): string {
    $path = self::$tmp . '/Prompty.compact.php';
    $this->runEmbed('--compact', '--stdout', $path);

    return $path;
  }

  protected function createRectorConfig(): string {
    $config_path = self::$tmp . '/rector.php';
    copy(self::$root . '/rector.php', $config_path);

    return $config_path;
  }

  protected function assertRectorClean(string $target): void {
    $rector_config = $this->createRectorConfig();
    $this->processRun('php', [self::$root . '/vendor/bin/rector', 'process', '--dry-run', '--config=' . $rector_config, $target]);
    $this->assertProcessSuccessful('Rector would make further changes');
  }

  public function testEmbed(): void {
    $target = $this->prepareTarget();

    $this->runEmbed($target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertStringContainsString('// @embed-start', $content);
    $this->assertStringContainsString('// @embed-end', $content);

    // Class header docblock preserved.
    $this->assertStringContainsString('Zero-dependency interactive CLI prompt library', $content);
    $this->assertStringContainsString('@license MIT', $content);

    // Other Prompty.php docblocks stripped.
    $this->assertStringNotContainsString('Singleton instance', $content);
    $this->assertStringNotContainsString('Stored TTY settings', $content);

    $this->assertStringNotContainsString('require_once', $content);

    $this->assertStringNotContainsString('use AlexSkrypnyk\Prompty\Prompty', $content);

    // Entire class is on a single line.
    $this->assertMatchesRegularExpression('/^(class Prompty \{.+\})$/m', $content);

    $this->assertStringContainsString('\033[', $content);

    $this->assertDoesNotMatchRegularExpression('/^namespace\s+AlexSkrypnyk\\\\Prompty\s*;/m', $content);

    $this->assertMatchesRegularExpression('/\/\/ @phpstan-ignore-next-line\nclass Prompty/', $content);

    $this->assertStarterFlowWorks($target);
  }

  public function testEmbedCompact(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--compact', $target);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertStringContainsString('\033[', $content);

    $this->assertStringContainsString('function flow', $content);
    $this->assertStringContainsString('function text', $content);
    $this->assertStringContainsString('function select', $content);
    $this->assertStringContainsString('function confirm', $content);
    $this->assertStringContainsString('function configure', $content);

    $this->assertStringNotContainsString('cfgSymbolsUnicode', $content);
    $this->assertStringNotContainsString('renderCompleted', $content);
    $this->assertStringNotContainsString('renderCancelled', $content);

    $normal_target = self::$tmp . '/normal.php';
    copy(self::$root . '/starter.php', $normal_target);
    $this->runEmbed($normal_target);
    $this->assertLessThan(filesize($normal_target), filesize($target));

    $this->assertStarterFlowWorks($target);
  }

  public function testEmbedWithOutputArgument(): void {
    $source = $this->prepareTarget();
    $output_path = self::$tmp . '/output.php';

    $this->runEmbed($source, $output_path);

    $source_content = file_get_contents($source);
    $this->assertIsString($source_content);
    $this->assertStringContainsString('require_once', $source_content);

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

    $this->runEmbed($target);
    $first_content = file_get_contents($target);
    $this->assertIsString($first_content);
    $this->assertStringContainsString('class Prompty', $first_content);
    $this->assertStringNotContainsString('require_once', $first_content);

    $this->assertStarterFlowWorks($target);

    // Simulate a user editing the script outside the embedded block.
    $modified = str_replace("intro: 'Compose an order'", "intro: 'Compose an ORDER'", $first_content);
    $this->assertNotSame($first_content, $modified);
    file_put_contents($target, $modified);

    $this->runEmbed($target);
    $re_embedded = file_get_contents($target);
    $this->assertIsString($re_embedded);

    $this->assertPhpLintPasses($target);

    $this->assertStringContainsString("intro: 'Compose an ORDER'", $re_embedded);

    $this->assertStringContainsString('// @embed-start', $re_embedded);
    $this->assertStringContainsString('// @embed-end', $re_embedded);

    $this->assertStringContainsString('class Prompty', $re_embedded);
    $this->assertStringNotContainsString('require_once', $re_embedded);

    $this->assertSame(1, substr_count($re_embedded, "if (!getenv('SHOULD_PROCEED'))"), 'Kill switch should appear exactly once after re-embed.');
  }

  public function testStdout(): void {
    $output_path = $this->generateMinified();

    $this->assertPhpLintPasses($output_path);

    $content = file_get_contents($output_path);
    $this->assertIsString($content);

    $this->assertStringContainsString('<?php', $content);
    $this->assertStringContainsString('declare(strict_types=1)', $content);
    $this->assertStringContainsString('namespace AlexSkrypnyk\Prompty', $content);
    $this->assertStringContainsString('class Prompty', $content);

    $this->assertStringContainsString('Zero-dependency interactive CLI prompt library', $content);
    $this->assertStringContainsString('\033[', $content);

    $this->assertLessThan(filesize(self::$root . '/Prompty.php'), filesize($output_path));
  }

  public function testStdoutCompact(): void {
    $min_path = $this->generateMinified();
    $compact_path = $this->generateCompacted();

    $this->assertPhpLintPasses($compact_path);

    $this->assertLessThan(filesize($min_path), filesize($compact_path));

    $content = file_get_contents($compact_path);
    $this->assertIsString($content);
    $this->assertStringContainsString('function flow', $content);
    $this->assertStringContainsString('function text', $content);

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

  public function testEmbedKillswitchInjected(): void {
    $target = $this->prepareTargetWithoutKillswitch();

    $this->runEmbed($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertStringContainsString("if (!getenv('SHOULD_PROCEED'))", $content);

    $embed_end_pos = strpos($content, '@embed-end');
    $killswitch_pos = strpos($content, "if (!getenv('SHOULD_PROCEED'))");
    $this->assertNotFalse($embed_end_pos);
    $this->assertNotFalse($killswitch_pos);
    $this->assertGreaterThan($embed_end_pos, $killswitch_pos);
  }

  #[DataProvider('dataProviderEmbedKillswitchAndVerification')]
  public function testEmbedKillswitchAndVerification(bool $has_killswitch, string $flags, bool $expected_killswitch, bool $expected_verification): void {
    $target = $has_killswitch ? $this->prepareTarget() : $this->prepareTargetWithoutKillswitch();

    $args = $flags === '' ? [] : explode(' ', $flags);
    $args[] = $target;

    $embed_output = $this->runEmbedWithOutput(...$args);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertSame($expected_killswitch ? 1 : 0, substr_count($content, "if (!getenv('SHOULD_PROCEED'))"), 'Kill switch count in the embedded file.');
    $this->assertSame($expected_verification, str_contains($embed_output, 'Verifying'), 'Verification run in the embedder output.');
    $this->assertSame(!$expected_killswitch, str_contains(strtolower($embed_output), 'no kill switch'), 'Missing kill switch warning in the embedder output.');
  }

  public static function dataProviderEmbedKillswitchAndVerification(): \Iterator {
    yield 'own kill switch' => [TRUE, '', TRUE, TRUE];

    yield 'own kill switch, --no-killswitch' => [TRUE, '--no-killswitch', TRUE, FALSE];

    yield 'own kill switch, --no-verify' => [TRUE, '--no-verify', TRUE, FALSE];

    yield 'own kill switch, both flags' => [TRUE, '--no-killswitch --no-verify', TRUE, FALSE];

    yield 'no kill switch' => [FALSE, '', TRUE, TRUE];

    yield 'no kill switch, --no-killswitch' => [FALSE, '--no-killswitch', FALSE, FALSE];

    yield 'no kill switch, --no-verify' => [FALSE, '--no-verify', TRUE, FALSE];

    yield 'no kill switch, both flags' => [FALSE, '--no-killswitch --no-verify', FALSE, FALSE];
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
    $output_path = self::$tmp . '/Prompty.source.php';
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
    $this->processRun('php', [self::$root . '/embed.php']);
    $this->assertProcessFailed();

    $this->assertProcessAnyOutputContains(['Usage:', '--source', '--compact', '--stdout', '--no-killswitch', '--no-verify', 'Re-embedding', 'Arguments:', 'Options:']);
  }

  public function testEmbedErrors(): void {
    $this->processRun('php', [self::$root . '/embed.php']);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('Usage');

    $target = self::$tmp . '/target.no-markers.php';
    file_put_contents($target, "<?php\necho 'hello';\n");

    $this->processRun('php', [self::$root . '/embed.php', $target]);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('marker');

    $this->processRun('php', [self::$root . '/embed.php', self::$tmp . '/missing.php']);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('Target script not found');

    $target = $this->prepareTarget();
    $this->processRun('php', [self::$root . '/embed.php', $target, self::$tmp . '/no-such-dir/output.php']);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('Could not copy target script to output');

    $this->processRun('php', [self::$root . '/embed.php', '--source']);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('--source requires a path');

    $target = $this->prepareTarget();
    $this->processRun('php', [self::$root . '/embed.php', '--source', '/nonexistent/Prompty.php', $target]);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('Source class not found');
  }

  public function testVersionAfterImprinting(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--no-killswitch', $target);

    // Simulate release version imprinting.
    $content = file_get_contents($target);
    $this->assertIsString($content);
    $content = str_replace('__PROMPTY_VERSION__', '1.2.3', $content);

    $content = str_replace("if (!getenv('SHOULD_PROCEED'))", "echo 'VERSION:' . Prompty::version() . PHP_EOL;\nif (!getenv('SHOULD_PROCEED'))", $content);
    file_put_contents($target, $content);

    $keystrokes = $this->keys('test', self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']);

    $r = $this->runWithKeystrokes('php ' . escapeshellarg($target), $keystrokes);
    $this->assertSame(0, $r['exit_code'], 'Script failed: ' . $r['stderr']);
    $this->assertStringContainsString('VERSION:1.2.3', $r['stdout']);
  }

}
