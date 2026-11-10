<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Base test case for tests that run embed.php.
 */
#[CoversNothing]
abstract class EmbedTestCase extends FunctionalTestCase {

  protected function prepareTarget(string $name = 'starter.php'): string {
    $target = self::$tmp . '/' . $name;

    if (!is_dir(dirname($target))) {
      mkdir(dirname($target), 0755, TRUE);
    }

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

  protected function prepareTargetWithoutMarkers(): string {
    $target = self::$tmp . '/target.no-markers.php';
    file_put_contents($target, "<?php\necho 'hello';\n");

    return $target;
  }

  protected function prepareTargetWithTwoRegions(): string {
    $target = self::$tmp . '/target.two-regions.php';
    file_put_contents($target, "<?php\n// @embed-start\n// @embed-end\necho 'hello';\n// @embed-start\n// @embed-end\n");

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

  /**
   * Copy Prompty.php with the version token replaced, as a release does.
   */
  protected function copyReleasedSource(string $version = '1.2.3'): string {
    $content = file_get_contents(self::$root . '/Prompty.php');
    $this->assertIsString($content);

    $path = self::$tmp . '/released/Prompty.php';

    if (!is_dir(dirname($path))) {
      mkdir(dirname($path), 0755, TRUE);
    }

    file_put_contents($path, str_replace('__PROMPTY_VERSION__', $version, $content));

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

  protected function readFile(string $path): string {
    $content = file_get_contents($path);
    $this->assertIsString($content);

    return $content;
  }

  /**
   * Read the start marker attributes of an embedded script.
   *
   * @return array<string, string>
   *   Attribute values keyed by name.
   */
  protected function readMarker(string $path): array {
    if (preg_match('/^\/\/ @embed-start(.*)$/m', $this->readFile($path), $match) !== 1) {
      $this->fail('The script has no start marker.');
    }

    preg_match_all('/\s([a-z0-9]+)=(\S+)/', $match[1], $pairs, PREG_SET_ORDER);

    $attributes = [];

    foreach ($pairs as $pair) {
      $attributes[$pair[1]] = $pair[2];
    }

    return $attributes;
  }

  /**
   * Read the code between the markers of an embedded script.
   */
  protected function readRegion(string $path): string {
    if (preg_match('/^\/\/ @embed-start[^\n]*\n(.*?)^\/\/ @embed-end/ms', $this->readFile($path), $match) !== 1) {
      $this->fail('The script has no embed region.');
    }

    return $match[1];
  }

  /**
   * Assert that embed.php printed the size row of a file.
   *
   * @param string $output
   *   The embed.php output.
   * @param string $label
   *   The row label.
   * @param string $content
   *   The file content the row measures.
   * @param string $rest
   *   The path and note that end the row.
   */
  protected function assertStatsRow(string $output, string $label, string $content, string $rest): void {
    $lines = substr_count($content, "\n") + ($content === '' || str_ends_with($content, "\n") ? 0 : 1);
    // embed.php ends each row with PHP_EOL, which is "\r\n" on Windows.
    $pattern = sprintf('/^  %s +%s bytes +%s lines  %s\r?$/m', preg_quote($label, '/'), preg_quote(number_format(strlen($content)), '/'), preg_quote(number_format($lines), '/'), preg_quote($rest, '/'));

    $this->assertMatchesRegularExpression($pattern, $output);
  }

  /**
   * The share of the source that a payload takes, as embed.php prints it.
   */
  protected function payloadNote(string $payload, string $source): string {
    return sprintf('(%s%% of the source)', number_format(strlen($payload) / strlen($source) * 100, 1));
  }

  /**
   * The embed modes, as data provider rows.
   */
  protected static function modes(): \Iterator {
    yield 'minified' => [FALSE];

    yield 'compacted' => [TRUE];
  }

  /**
   * Prefix embed.php arguments with --compact when compacting.
   *
   * @param bool $is_compact
   *   Whether to compact.
   * @param array<int, string> $args
   *   The other arguments.
   *
   * @return array<int, string>
   *   The arguments.
   */
  protected function withMode(bool $is_compact, array $args): array {
    return $is_compact ? array_merge(['--compact'], $args) : $args;
  }

}
