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
final class EmbedScriptTest extends EmbedTestCase {

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

  public function testEmbedIsRectorClean(): void {
    $target = $this->prepareTarget();

    $embed_output = $this->runEmbedWithOutput($target);
    $this->assertStringNotContainsString('Rector', $embed_output);
    $this->assertStringContainsString('class Prompty', $this->readFile($target));

    $this->assertRectorClean($target);
  }

  public function testStdoutIsRectorClean(): void {
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
  public function testEmbedKillswitchAndVerification(bool $is_killswitch_present, string $flags, bool $is_killswitch_expected, bool $is_verification_expected): void {
    $target = $is_killswitch_present ? $this->prepareTarget() : $this->prepareTargetWithoutKillswitch();

    $args = $flags === '' ? [] : explode(' ', $flags);
    $args[] = $target;

    $embed_output = $this->runEmbedWithOutput(...$args);

    $content = file_get_contents($target);
    $this->assertIsString($content);

    $this->assertSame($is_killswitch_expected ? 1 : 0, substr_count($content, "if (!getenv('SHOULD_PROCEED'))"), 'Kill switch count in the embedded file.');
    $this->assertSame($is_verification_expected, str_contains($embed_output, 'Verifying embedded script'), 'Verification run in the embedder output.');
    $this->assertSame(!$is_killswitch_expected, str_contains(strtolower($embed_output), 'no kill switch'), 'Missing kill switch warning in the embedder output.');
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

  #[DataProvider('dataProviderSourceFlagWithGeneratedInput')]
  public function testSourceFlagWithGeneratedInput(bool $is_compact_source, bool $is_compact_flag): void {
    $target = $this->prepareTarget();
    $source = $is_compact_source ? $this->generateCompacted() : $this->generateMinified();

    $args = ['--source', $source];

    if ($is_compact_flag) {
      $args[] = '--compact';
    }

    $args[] = $target;

    $this->runEmbed(...$args);

    $this->assertPhpLintPasses($target);

    $content = file_get_contents($target);
    $this->assertIsString($content);
    $this->assertStringContainsString('class Prompty', $content);
    $this->assertStringContainsString('// @embed-start', $content);

    $this->assertStarterFlowWorks($target);
  }

  public static function dataProviderSourceFlagWithGeneratedInput(): \Iterator {
    yield 'minified source' => [FALSE, FALSE];

    yield 'compacted source' => [TRUE, FALSE];

    yield 'compacted source, --compact' => [TRUE, TRUE];
  }

  /**
   * Run embed.php with invalid arguments and assert the error output.
   *
   * @param array<int, string> $args
   *   Arguments for embed.php. '<target>', '<target-no-markers>' and
   *   '<target-two-regions>' resolve to prepared scripts, '<tmp>' to the
   *   temporary directory.
   * @param array<int, string> $expected_output
   *   Strings the combined output must contain.
   */
  #[DataProvider('dataProviderEmbedErrors')]
  public function testEmbedErrors(array $args, array $expected_output): void {
    $resolved = [];

    foreach ($args as $arg) {
      $resolved[] = match ($arg) {
        '<target>' => $this->prepareTarget(),
        '<target-no-markers>' => $this->prepareTargetWithoutMarkers(),
        '<target-two-regions>' => $this->prepareTargetWithTwoRegions(),
        default => str_replace('<tmp>', self::$tmp, $arg),
      };
    }

    $this->processRun('php', array_merge([self::$root . '/embed.php'], $resolved));
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains($expected_output);
  }

  public static function dataProviderEmbedErrors(): \Iterator {
    yield 'no arguments' => [[], ['Usage:', '--source', '--compact', '--stdout', '--payload', '--check', '--sums', '--no-killswitch', '--no-verify', 'Re-embedding', 'Verifying:', 'Arguments:', 'Options:']];

    yield 'target without markers' => [['<target-no-markers>'], ['marker']];

    yield 'target with 2 embed regions' => [['<target-two-regions>'], ['Found 2 embed regions in target file, expected 1.']];

    yield 'missing target' => [['<tmp>/missing.php'], ['Target script not found']];

    yield 'output path in missing directory' => [['<target>', '<tmp>/no-such-dir/output.php'], ['Could not copy target script to output']];

    yield '--source without a path' => [['--source'], ['--source requires a path']];

    yield '--sums without a path' => [['--check', '<target>', '--sums'], ['--sums requires a path']];

    yield '--sums without --check' => [['--sums', '<tmp>/SHA256SUMS', '<target>'], ['--sums is only used with --check.']];

    yield '--stdout and --payload' => [['--stdout', '--payload', '<tmp>/out.php'], ['Use only 1 of --stdout, --payload and --check.']];

    yield '--payload and --check' => [['--payload', '--check', '<target>'], ['Use only 1 of --stdout, --payload and --check.']];

    yield '--source with missing class' => [['--source', '/nonexistent/Prompty.php', '<target>'], ['Source class not found']];
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

    $keystrokes = $this->keys('plum compote', self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']);

    $output = $this->runScript($target, $keystrokes);

    $this->assertStringContainsString('VERSION:1.2.3', $output);
  }

}
