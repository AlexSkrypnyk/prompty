<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what an empty answer to the text widget means.
 *
 * Pressing enter on an empty buffer accepts the seeded default, or the
 * placeholder when no default was seeded. An empty discovered value is
 * treated the same way, so an unset environment variable and an empty one
 * produce the same answer as a user who typed nothing.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTextEmptyAnswerTest extends PromptyTestCase {

  /**
   * Run the text widget over a discovered value.
   *
   * @param mixed $discovered
   *   The discovered argument, or NULL to leave it unset.
   * @param string $default
   *   Value seeded into the input buffer.
   * @param string $placeholder
   *   Placeholder shown while the buffer is empty.
   * @param string|null $ctx_discovered
   *   The environment value carried by the context, or NULL for none.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runTextWidget(mixed $discovered = NULL, string $default = '', string $placeholder = '', ?string $ctx_discovered = NULL): array {
    return $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
      default: $default,
      placeholder: $placeholder,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));
  }

  #[DataProvider('dataProviderEmptyDiscoveredTakesTheUnansweredValue')]
  public function testEmptyDiscoveredTakesTheUnansweredValue(string $discovered, string $default, string $placeholder, string $expected): void {
    $r = $this->runTextWidget($discovered, $default, $placeholder);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderEmptyDiscoveredTakesTheUnansweredValue(): \Iterator {
    yield 'placeholder only' => ['', '', 'pear tart', 'pear tart'];
    yield 'default only' => ['', 'plum compote', '', 'plum compote'];
    yield 'default wins over placeholder' => ['', 'plum compote', 'pear tart', 'plum compote'];
    yield 'neither stays empty' => ['', '', '', ''];
    yield 'whitespace only' => ['   ', '', 'pear tart', 'pear tart'];
    yield 'tab and newline only' => ["\t\n", '', 'pear tart', 'pear tart'];
  }

  #[DataProvider('dataProviderDiscoveredValueIsTrimmed')]
  public function testDiscoveredValueIsTrimmed(string $discovered, string $expected): void {
    $r = $this->runTextWidget($discovered, '', 'pear tart');

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDiscoveredValueIsTrimmed(): \Iterator {
    yield 'leading space' => ['  onion soup', 'onion soup'];
    yield 'trailing space' => ['onion soup  ', 'onion soup'];
    yield 'both ends' => ['  onion soup  ', 'onion soup'];
    yield 'inner spacing kept' => ['  onion  soup  ', 'onion  soup'];
    yield 'trailing newline' => ["onion soup\n", 'onion soup'];
  }

  public function testTrimmedValueIsRendered(): void {
    $r = $this->runTextWidget('  onion soup  ', '', 'pear tart');

    $this->assertStringContainsString('onion soup', $r['output']);
    $this->assertStringNotContainsString('  onion soup  ', $r['output']);
  }

  public function testEmptyDiscoveredRendersTheUnansweredValue(): void {
    $r = $this->runTextWidget('', '', 'pear tart');

    $this->assertStringContainsString('pear tart', $r['output']);
  }

  #[DataProvider('dataProviderBothPathsAgreeOnAnEmptyAnswer')]
  public function testBothPathsAgreeOnAnEmptyAnswer(string $default, string $placeholder): void {
    $interactive = $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
      default: $default,
      placeholder: $placeholder,
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $discovered = $this->runTextWidget('', $default, $placeholder);

    $this->assertSame($interactive['result'], $discovered['result']);
  }

  public static function dataProviderBothPathsAgreeOnAnEmptyAnswer(): \Iterator {
    yield 'placeholder only' => ['', 'pear tart'];
    yield 'default only' => ['plum compote', ''];
    yield 'default and placeholder' => ['plum compote', 'pear tart'];
    yield 'neither' => ['', ''];
  }

  public function testEmptyEnvValueTakesTheUnansweredValue(): void {
    $r = $this->runTextWidget(NULL, '', 'pear tart', '');

    $this->assertSame('pear tart', $r['result']);
  }

  public function testEnvValueIsTrimmed(): void {
    $r = $this->runTextWidget(NULL, '', 'pear tart', '  walnut loaf  ');

    $this->assertSame('walnut loaf', $r['result']);
  }

  public function testFlowEmptyEnvValueTakesThePlaceholder(): void {
    $this->setEnvVars(['dish' => '']);
    $result = NULL;

    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame('pear tart', $result['dish']);
  }

  public function testNonStringDiscoveredValueSurvives(): void {
    $r = $this->runTextWidget(42, '', 'pear tart');

    $this->assertSame('42', $r['result']);
  }

}
