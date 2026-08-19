<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the forms a multiselect accepts as a discovered value.
 *
 * An environment variable can only carry a string, so a multiple-choice answer
 * arrives comma-separated. The discovered argument accepts the same string, so
 * one source can stand in for the other, and a list for the cases a comma
 * cannot express.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyDiscoveredListTest extends PromptyTestCase {

  /**
   * Option set for the multiselect cases.
   */
  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Run the multiselect widget over a discovered value.
   *
   * @param mixed $discovered
   *   The discovered argument, or NULL to leave it unset.
   * @param string|null $ctx_discovered
   *   The environment value carried by the context, or NULL for none.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselectWidget(mixed $discovered = NULL, ?string $ctx_discovered = NULL, array $options = self::EXTRAS): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: $options,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));
  }

  #[DataProvider('dataProviderDiscoveredArgumentAcceptsCommaSeparatedKeys')]
  public function testDiscoveredArgumentAcceptsCommaSeparatedKeys(string $discovered, array $expected): void {
    $r = $this->runMultiselectWidget($discovered);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDiscoveredArgumentAcceptsCommaSeparatedKeys(): \Iterator {
    yield 'single key' => ['bread', ['bread']];
    yield 'two keys' => ['bread,olives', ['bread', 'olives']];
    yield 'every key' => ['bread,olives,herbs', ['bread', 'olives', 'herbs']];
    yield 'spaces around keys' => ['bread, olives', ['bread', 'olives']];
    yield 'reordered to option order' => ['herbs,bread', ['bread', 'herbs']];
    yield 'duplicates removed' => ['olives,bread,olives', ['bread', 'olives']];
    yield 'trailing comma ignored' => ['bread,', ['bread']];
    yield 'doubled comma ignored' => ['bread,,olives', ['bread', 'olives']];
    yield 'empty string means none' => ['', []];
  }

  #[DataProvider('dataProviderBothSourcesAgree')]
  public function testBothSourcesAgree(string $value): void {
    $argument = $this->runMultiselectWidget($value);
    $env = $this->runMultiselectWidget(NULL, $value);

    $this->assertSame($env['result'], $argument['result']);
  }

  public static function dataProviderBothSourcesAgree(): \Iterator {
    yield 'single key' => ['bread'];
    yield 'two keys' => ['bread,olives'];
    yield 'spaces around keys' => ['bread, olives'];
    yield 'trailing comma' => ['bread,'];
    yield 'empty value' => [''];
  }

  public function testDiscoveredArgumentRejectsUnknownKeyInList(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      discovered: 'bread,pickles',
      ctx: $this->defaultCtx(),
    ), 'Discovered value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.');
  }

  public function testListArgumentStillTakesEntriesLiterally(): void {
    $r = $this->runMultiselectWidget(['bread', 'herbs']);

    $this->assertSame(['bread', 'herbs'], $r['result']);
  }

  public function testListArgumentReachesOptionKeyHoldingComma(): void {
    $r = $this->runMultiselectWidget(['salt,pepper'], NULL, ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs']);

    $this->assertSame(['salt,pepper'], $r['result']);
  }

  public function testCommaFormCannotReachOptionKeyHoldingComma(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs'],
      discovered: 'salt,pepper',
      ctx: $this->defaultCtx(),
    ), 'Discovered value "salt" for "Extras" is not a valid option. Available options: salt,pepper, herbs.');
  }

  public function testFlowEnvValueStillSplits(): void {
    $this->setEnvVars(['extras' => 'bread,herbs']);
    $result = NULL;

    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame(['bread', 'herbs'], $result['extras']);
  }

}
