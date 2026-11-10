<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for the embed marker, payloads, --check and the snippets.
 */
#[CoversNothing]
#[Group('functional')]
final class EmbedIntegrityTest extends EmbedTestCase {

  protected const string FIXTURE = 'tests/phpunit/Fixtures/EmbedSource';

  #[DataProvider('dataProviderFixturePayload')]
  public function testFixturePayload(bool $compact): void {
    $path = self::$tmp . '/fixture.payload';

    $this->runEmbed(...$this->withMode($compact, ['--source', self::$root . '/' . self::FIXTURE . '.php', '--payload', $path]));

    $expected = $this->readFile(self::$root . '/' . self::FIXTURE . ($compact ? '.compact' : '.min') . '.payload');
    $this->assertSame($expected, $this->readFile($path), 'The payload is byte-identical to the committed one on this PHP version.');
  }

  public static function dataProviderFixturePayload(): \Iterator {
    yield from self::modes();
  }

  #[DataProvider('dataProviderEmbedIsDeterministic')]
  public function testEmbedIsDeterministic(bool $compact): void {
    $first = $this->prepareTarget('first.php');
    $second = $this->prepareTarget('second.php');

    $this->runEmbed(...$this->withMode($compact, ['--no-verify', $first]));
    $this->runEmbed(...$this->withMode($compact, ['--no-verify', $second]));

    $this->assertSame($this->readFile($first), $this->readFile($second));
  }

  public static function dataProviderEmbedIsDeterministic(): \Iterator {
    yield from self::modes();
  }

  #[DataProvider('dataProviderRegionMatchesPayload')]
  public function testRegionMatchesPayload(bool $compact): void {
    $target = $this->prepareTarget();
    $payload = self::$tmp . '/Prompty.payload';

    $this->runEmbed(...$this->withMode($compact, ['--no-verify', $target]));
    $this->runEmbed(...$this->withMode($compact, ['--payload', $payload]));

    $this->assertSame($this->readFile($payload), $this->readRegion($target));
    $this->assertSame(hash('sha256', $this->readFile($payload)), $this->readMarker($target)['sha256']);
    $this->assertSame($compact ? 'compact' : 'min', $this->readMarker($target)['mode']);
  }

  public static function dataProviderRegionMatchesPayload(): \Iterator {
    yield from self::modes();
  }

  /**
   * The embed modes, as data provider rows.
   */
  protected static function modes(): \Iterator {
    yield 'minified' => [FALSE];

    yield 'compacted' => [TRUE];
  }

  #[DataProvider('dataProviderMarkerAttributes')]
  public function testMarkerAttributes(string $source, string $expected_version): void {
    $target = $this->prepareTarget();
    $source_path = match ($source) {
      'repository' => self::$root . '/Prompty.php',
      'released' => $this->copyReleasedSource(),
      'no constant' => $this->copySourceWithoutVersion(),
      default => self::$root . '/' . self::FIXTURE . '.php',
    };

    $this->runEmbed('--no-verify', '--source', $source_path, $target);

    $marker = $this->readMarker($target);
    $this->assertSame(['class', 'version', 'mode', 'sha256', 'embedder'], array_keys($marker));
    $this->assertSame($source === 'fixture' ? 'EmbedSource' : 'Prompty', $marker['class']);
    $this->assertSame($expected_version, $marker['version']);
    $this->assertSame('min', $marker['mode']);
    $this->assertSame(hash('sha256', $this->readRegion($target)), $marker['sha256']);
    $this->assertSame(hash_file('sha256', self::$root . '/embed.php'), $marker['embedder']);
  }

  public static function dataProviderMarkerAttributes(): \Iterator {
    yield 'repository source' => ['repository', 'development'];

    yield 'released source' => ['released', '1.2.3'];

    yield 'source without a version constant' => ['no constant', 'unknown'];

    yield 'another class' => ['fixture', '9.8.7'];
  }

  public function testReEmbedRewritesMarker(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--no-verify', $target);
    $expected = $this->readMarker($target);

    file_put_contents($target, (string) preg_replace('/^\/\/ @embed-start.*$/m', '// @embed-start custom=kept version=0.0.1 note', $this->readFile($target)));
    $this->runEmbed('--no-verify', $target);

    $this->assertSame($expected, $this->readMarker($target));
  }

  public function testReleaseVersionAfterImprinting(): void {
    $target = $this->prepareTarget();

    $this->runEmbed('--no-verify', '--source', $this->copyReleasedSource('4.5.6'), $target);

    $content = str_replace("if (!getenv('SHOULD_PROCEED'))", "echo 'VERSION:' . Prompty::version() . PHP_EOL;\nif (!getenv('SHOULD_PROCEED'))", $this->readFile($target));
    file_put_contents($target, $content);

    $output = $this->runScript($target, $this->keys('plum compote', self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER'], self::KEYS['ENTER']));

    $this->assertStringContainsString('VERSION:4.5.6', $output);
    $this->assertSame('4.5.6', $this->readMarker($target)['version']);
  }

  /**
   * Run --check against a script and SHA256SUMS built as a release does.
   *
   * @param string $mutation
   *   The change made to the script or SHA256SUMS before the check.
   * @param bool $with_sums
   *   Whether to pass --sums.
   * @param int $expected_exit
   *   The expected exit code.
   * @param array<int, string> $expected_output
   *   Strings the combined output must contain.
   */
  #[DataProvider('dataProviderCheck')]
  public function testCheck(string $mutation, bool $with_sums, int $expected_exit, array $expected_output): void {
    $source = $this->copyReleasedSource();
    $target = $this->prepareTarget('install.php');
    $payload = self::$tmp . '/Prompty.min.payload';
    $sums = self::$tmp . '/SHA256SUMS';

    $this->runEmbed('--no-verify', '--source', $source, $target);
    $this->runEmbed('--source', $source, '--payload', $payload);

    $payload_sum = hash_file('sha256', $payload);
    $embedder_sum = hash_file('sha256', self::$root . '/embed.php');
    $this->assertIsString($payload_sum);
    $this->assertIsString($embedder_sum);

    $script_content = $this->readFile($target);
    $sums_content = sprintf("%s  Prompty.min.payload\n%s  embed.php\n", $payload_sum, $embedder_sum);
    $zeros = str_repeat('0', 64);

    $script_content = match ($mutation) {
      'changed code' => str_replace('class Prompty {', 'class Prompty  {', $script_content),
      'changed marker hash' => (string) preg_replace('/ sha256=[0-9a-f]{64}/', ' sha256=' . $zeros, $script_content),
      'changed marker embedder' => (string) preg_replace('/ embedder=[0-9a-f]{64}/', ' embedder=' . $zeros, $script_content),
      'changed marker mode' => str_replace(' mode=min ', ' mode=compact ', $script_content),
      'development version' => str_replace(' version=1.2.3 ', ' version=development ', $script_content),
      'unknown version' => str_replace(' version=1.2.3 ', ' version=unknown ', $script_content),
      'malformed marker hash' => (string) preg_replace('/ sha256=[0-9a-f]{64}/', ' sha256=abc', $script_content),
      'bare marker' => (string) preg_replace('/^\/\/ @embed-start.*$/m', '// @embed-start', $script_content),
      'no markers' => (string) preg_replace('/^\/\/ @embed-(start|end).*\n/m', '', $script_content),
      'second region' => $script_content . "// @embed-start\n// @embed-end\n",
      'crlf line endings' => str_replace("\n", "\r\n", $script_content),
      'indented markers' => (string) preg_replace('/^(\/\/ @embed-(start|end))/m', '  $1', $script_content),
      default => $script_content,
    };

    $sums_content = match ($mutation) {
      'sums without payload' => sprintf("%s  embed.php\n", $embedder_sum),
      'sums without embedder' => sprintf("%s  Prompty.min.payload\n", $payload_sum),
      'sums with another payload' => sprintf("%s  Prompty.min.payload\n%s  embed.php\n", $zeros, $embedder_sum),
      'sums listing a file twice' => $sums_content . sprintf("%s  embed.php\n", $embedder_sum),
      'sums with a short hash' => sprintf("%s  Prompty.min.payload\n", substr($payload_sum, 1)),
      'sums with an uppercase hash' => sprintf("%s  Prompty.min.payload\n", strtoupper($payload_sum)),
      'sums with 1 space' => sprintf("%s Prompty.min.payload\n", $payload_sum),
      'sums in binary mode' => sprintf("%s *Prompty.min.payload\n", $payload_sum),
      'sums with crlf line endings' => str_replace("\n", "\r\n", $sums_content),
      'empty sums' => '',
      default => $sums_content,
    };

    file_put_contents($target, $script_content);

    if ($mutation !== 'missing sums') {
      file_put_contents($sums, $sums_content);
    }

    $script_arg = $mutation === 'missing script' ? self::$tmp . '/missing.php' : $target;
    $this->processRun('php', array_merge([self::$root . '/embed.php', '--check', $script_arg], $with_sums ? ['--sums', $sums] : []));

    $output = $this->processGet()->getOutput() . $this->processGet()->getErrorOutput();
    $this->assertSame($expected_exit, $this->processGet()->getExitCode(), $output);

    foreach ($expected_output as $expected) {
      $this->assertStringContainsString($expected, $output);
    }
  }

  public static function dataProviderCheck(): \Iterator {
    $all_ok = [
      'Embedded Prompty 1.2.3 (min)',
      'OK    The embedded code matches the marker hash.',
      'OK    Version 1.2.3 is a release version.',
      'OK    The embedded code matches Prompty.min.payload in SHA256SUMS.',
      'OK    The script was embedded by the embed.php in SHA256SUMS.',
      'SHA256SUMS is trusted as given.',
    ];

    yield 'release build' => ['none', TRUE, 0, $all_ok];

    yield 'release build, no sums' => ['none', FALSE, 0, ['OK    The embedded code matches the marker hash.', 'NOT VERIFIED AGAINST A RELEASE']];

    yield 'changed code' => ['changed code', TRUE, 1, ['FAIL  The embedded code does not match the marker hash', 'FAIL  The embedded code does not match Prompty.min.payload in SHA256SUMS.']];

    yield 'changed code, no sums' => ['changed code', FALSE, 1, ['FAIL  The embedded code does not match the marker hash', 'NOT VERIFIED AGAINST A RELEASE']];

    yield 'changed marker hash' => ['changed marker hash', TRUE, 1, ['FAIL  The embedded code does not match the marker hash', 'OK    The embedded code matches Prompty.min.payload in SHA256SUMS.']];

    yield 'changed marker embedder' => ['changed marker embedder', TRUE, 1, ['FAIL  The script was embedded by a different embed.php than the one in SHA256SUMS.']];

    yield 'changed marker mode' => ['changed marker mode', TRUE, 1, ['FAIL  SHA256SUMS does not list Prompty.compact.payload.']];

    yield 'development version' => ['development version', TRUE, 1, ['FAIL  Version "development" is not a release']];

    yield 'development version, no sums' => ['development version', FALSE, 0, ['NOT VERIFIED AGAINST A RELEASE']];

    yield 'unknown version' => ['unknown version', TRUE, 1, ['FAIL  Version "unknown" is not a release']];

    yield 'malformed marker hash' => ['malformed marker hash', TRUE, 1, ['The start marker has no valid "sha256" attribute.']];

    yield 'bare marker' => ['bare marker', FALSE, 1, ['The start marker has no valid "class" attribute.']];

    yield 'no markers' => ['no markers', FALSE, 1, ['Found 0 embed regions']];

    yield 'second region' => ['second region', TRUE, 1, ['Found 2 embed regions']];

    yield 'crlf line endings' => ['crlf line endings', TRUE, 0, $all_ok];

    yield 'indented markers' => ['indented markers', TRUE, 0, $all_ok];

    yield 'sums without payload' => ['sums without payload', TRUE, 1, ['FAIL  SHA256SUMS does not list Prompty.min.payload.']];

    yield 'sums without embedder' => ['sums without embedder', TRUE, 1, ['FAIL  SHA256SUMS does not list embed.php.']];

    yield 'sums with another payload' => ['sums with another payload', TRUE, 1, ['FAIL  The embedded code does not match Prompty.min.payload in SHA256SUMS.']];

    yield 'sums listing a file twice' => ['sums listing a file twice', TRUE, 1, ['SHA256SUMS lists embed.php more than once.']];

    yield 'sums with a short hash' => ['sums with a short hash', TRUE, 1, ['Line 1 of SHA256SUMS is not']];

    yield 'sums with an uppercase hash' => ['sums with an uppercase hash', TRUE, 1, ['Line 1 of SHA256SUMS is not']];

    yield 'sums with 1 space' => ['sums with 1 space', TRUE, 1, ['Line 1 of SHA256SUMS is not']];

    yield 'sums in binary mode' => ['sums in binary mode', TRUE, 1, ['Line 1 of SHA256SUMS is not']];

    yield 'sums with crlf line endings' => ['sums with crlf line endings', TRUE, 1, ['Line 1 of SHA256SUMS is not']];

    yield 'empty sums' => ['empty sums', TRUE, 1, ['SHA256SUMS lists no files.']];

    yield 'missing sums' => ['missing sums', TRUE, 1, ['SHA256SUMS not found']];

    yield 'missing script' => ['missing script', TRUE, 1, ['Script not found']];
  }

  public function testGuidanceOutsideRepository(): void {
    $target = $this->prepareTarget();

    $output = $this->runEmbedOutsideRepository('--no-verify', $target);

    $this->assertStringContainsString('Embedded Prompty development (min). To let your repository and its users verify starter.php:', $output);
    $this->assertStringContainsString('-- Snippet 1 of 3: a README section', $output);
    $this->assertStringContainsString('## Verifying starter.php', $output);
    $this->assertStringContainsString('curl -fsSL https://github.com/<owner>/<repo>/releases/latest/download/starter.php -o starter.php', $output);
    $this->assertStringContainsString('gh attestation verify starter.php --repo <owner>/<repo>', $output);
    $this->assertStringContainsString('-- Snippet 2 of 3: .github/workflows/publish-starter.yml', $output);
    $this->assertStringContainsString('cp starter.php .publish/starter.php', $output);
    $this->assertStringContainsString('-- Snippet 3 of 3: a job for the workflow that tests pull requests', $output);
    $this->assertStringContainsString('gh attestation verify .prompty/embed.php --repo AlexSkrypnyk/prompty', $output);
    $this->assertStringContainsString("VALIDSIG 755E824E80F849135F5F4043E71EDB25C4F6D89D '", $output);
    $this->assertStringContainsString('php .prompty/embed.php --check starter.php --sums .prompty/SHA256SUMS', $output);
    $this->assertStringContainsString('-- End of snippets', $output);
    $this->assertStringContainsString('Prompty is version "development", which no release lists, so snippet 3 fails until the script is embedded from a release.', $output);
    $this->assertStringContainsString('Replace <owner>/<repo> in the snippets: no GitHub remote named "origin" was found for starter.php.', $output);
  }

  #[DataProvider('dataProviderGuidanceInRepository')]
  public function testGuidanceInRepository(string $remote, string $expected_repo): void {
    $repository = self::$tmp . '/repository';
    mkdir($repository . '/bin', 0755, TRUE);

    $this->processRun('git', ['-C', $repository, 'init', '-q']);
    $this->assertProcessSuccessful();
    $this->processRun('git', ['-C', $repository, 'remote', 'add', 'origin', $remote]);
    $this->assertProcessSuccessful();

    $target = $repository . '/bin/install.php';
    copy(self::$root . '/starter.php', $target);

    $output = $this->runEmbedOutsideRepository('--no-verify', '--source', $this->copyReleasedSource(), $target);

    $this->assertStringContainsString('gh attestation verify install.php --repo ' . $expected_repo, $output);
    $this->assertStringContainsString('cp bin/install.php .publish/install.php', $output);
    $this->assertStringContainsString('php .prompty/embed.php --check bin/install.php --sums .prompty/SHA256SUMS', $output);
    $this->assertStringNotContainsString('which no release lists', $output);
    $this->assertSame($expected_repo === '<owner>/<repo>', str_contains($output, 'Replace <owner>/<repo>'));
  }

  public static function dataProviderGuidanceInRepository(): \Iterator {
    yield 'https remote' => ['https://github.com/kitchen/orders.git', 'kitchen/orders'];

    yield 'https remote without .git' => ['https://github.com/kitchen/orders', 'kitchen/orders'];

    yield 'ssh remote' => ['git@github.com:kitchen/orders.git', 'kitchen/orders'];

    yield 'ssh url remote' => ['ssh://git@github.com/kitchen/orders.git', 'kitchen/orders'];

    yield 'remote on another host' => ['https://example.com/kitchen/orders.git', '<owner>/<repo>'];
  }

  #[DataProvider('dataProviderGuidanceSkipped')]
  public function testGuidanceSkipped(string $mode): void {
    $target = $this->prepareTarget();
    $this->runEmbed('--no-verify', $target);

    $args = match ($mode) {
      '--stdout' => ['--stdout', self::$tmp . '/Prompty.min.php'],
      '--payload' => ['--payload', self::$tmp . '/Prompty.min.payload'],
      default => ['--check', $target],
    };

    $this->assertStringNotContainsString('-- Snippet', $this->runEmbedWithOutput(...$args));
  }

  public static function dataProviderGuidanceSkipped(): \Iterator {
    yield '--stdout' => ['--stdout'];

    yield '--payload' => ['--payload'];

    yield '--check' => ['--check'];
  }

  public function testReadmeShowsGuidance(): void {
    $target = self::$tmp . '/my-script.php';
    copy(self::$root . '/starter.php', $target);

    $output = $this->runEmbedOutsideRepository('--no-verify', $target);

    $this->assertSame(3, preg_match_all('/^-- Snippet \d of 3: [^\n]*\n(.*?)(?=^-- (?:Snippet|End of snippets))/ms', $output, $matches));

    $readme = $this->readFile(self::$root . '/README.md');

    foreach ($matches[1] as $index => $snippet) {
      $this->assertStringContainsString(rtrim($snippet), $readme, sprintf("README.md does not show snippet %d as embed.php prints it for my-script.php:\n%s", $index + 1, $snippet));
    }
  }

  /**
   * Run embed.php where git finds no repository above the temporary directory.
   *
   * @return string
   *   Combined stdout+stderr output.
   */
  protected function runEmbedOutsideRepository(string ...$args): string {
    $this->processRun('php', array_merge([self::$root . '/embed.php'], $args), [], ['GIT_CEILING_DIRECTORIES' => dirname(self::$tmp)]);
    $this->assertProcessSuccessful();

    return $this->processGet()->getOutput() . $this->processGet()->getErrorOutput();
  }

  /**
   * Prefix embed.php arguments with --compact when compacting.
   *
   * @param bool $compact
   *   Whether to compact.
   * @param array<int, string> $args
   *   The other arguments.
   *
   * @return array<int, string>
   *   The arguments.
   */
  protected function withMode(bool $compact, array $args): array {
    return $compact ? array_merge(['--compact'], $args) : $args;
  }

  protected function copySourceWithoutVersion(): string {
    $path = self::$tmp . '/Prompty.no-version.php';
    $content = str_replace("  public const VERSION = '__PROMPTY_VERSION__';\n", '', $this->readFile(self::$root . '/Prompty.php'));
    $this->assertStringNotContainsString('const VERSION', $content);
    file_put_contents($path, $content);

    return $path;
  }

}
