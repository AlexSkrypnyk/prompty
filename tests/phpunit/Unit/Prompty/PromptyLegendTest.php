<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the key legend an active widget draws under its input.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyLegendTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected const array DISHES = ['Pear Tart', 'Onion Soup'];

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const array CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  #[DataProvider('dataProviderRenderLegend')]
  public function testRenderLegend(string $widget, bool $unicode, array $configure, int $depth, array $open, array $expected): void {
    $p = $this->createAndSetInstance(['unicode' => $unicode, 'ansi' => FALSE, 'legend' => TRUE]);

    if ($configure !== []) {
      Prompty::configure(...$configure);
    }

    $this->assertSame($expected, $this->callProtectedLines($p, 'renderLegend', $widget, $depth, $open));
  }

  public static function dataProviderRenderLegend(): \Iterator {
    yield 'text ascii' => ['text', FALSE, [], 0, [], ['|', '|  enter confirm']];
    yield 'select ascii' => ['select', FALSE, [], 0, [], ['|', '|  up/down move - enter confirm']];
    yield 'multiselect ascii' => ['multiselect', FALSE, [], 0, [], ['|', '|  up/down move - space toggle - enter confirm']];
    yield 'suggest ascii' => ['suggest', FALSE, [], 0, [], ['|', '|  type to filter - up/down move - enter confirm']];
    yield 'confirm ascii' => ['confirm', FALSE, [], 0, [], ['|', '|  left/right toggle - y/n choose - enter confirm']];
    yield 'pause ascii' => ['pause', FALSE, [], 0, [], ['|', '|  any key continue']];
    yield 'pause enter only ascii' => ['pause_enter', FALSE, [], 0, [], ['|', '|  enter continue']];
    yield 'text unicode' => ['text', TRUE, [], 0, [], ['│', '│  enter confirm']];
    yield 'select unicode' => ['select', TRUE, [], 0, [], ['│', '│  ↑↓ move · enter confirm']];
    yield 'multiselect unicode' => ['multiselect', TRUE, [], 0, [], ['│', '│  ↑↓ move · space toggle · enter confirm']];
    yield 'suggest unicode' => ['suggest', TRUE, [], 0, [], ['│', '│  type to filter · ↑↓ move · enter confirm']];
    yield 'confirm unicode' => ['confirm', TRUE, [], 0, [], ['│', '│  ←→ toggle · y/n choose · enter confirm']];
    yield 'nested' => ['select', FALSE, [], 2, [1 => TRUE], ['|  |     ', '|  |     up/down move - enter confirm']];
    yield 'off' => ['select', FALSE, ['legend' => FALSE], 0, [], []];
    yield 'labels reword' => ['select', FALSE, ['labels' => ['legend_move' => 'go', 'legend_enter' => 'return to pick']], 0, [], ['|', '|  up/down go - return to pick']];
    yield 'blank label drops its segment' => ['suggest', FALSE, ['labels' => ['legend_type' => '']], 0, [], ['|', '|  up/down move - enter confirm']];
    yield 'blank move label drops the arrows' => ['select', FALSE, ['labels' => ['legend_move' => '']], 0, [], ['|', '|  enter confirm']];
    yield 'blank toggle label drops the arrows' => ['confirm', FALSE, ['labels' => ['legend_toggle' => '']], 0, [], ['|', '|  y/n choose - enter confirm']];
    yield 'whitespace label drops its segment' => ['multiselect', FALSE, ['labels' => ['legend_space' => '  ']], 0, [], ['|', '|  up/down move - enter confirm']];
    yield 'every label blank draws nothing' => ['text', FALSE, ['labels' => ['legend_enter' => '']], 0, [], []];
    yield 'symbols override' => ['select', FALSE, ['symbols_ascii' => ['legend_vertical' => 'j/k', 'legend_separator' => '/']], 0, [], ['|', '|  j/k move / enter confirm']];
    yield 'control characters are neutralized' => ['text', FALSE, ['labels' => ['legend_enter' => "enter\033[31m\tconfirm"]], 0, [], ['|', '|  enter confirm']];
    yield 'wraps to the width' => ['multiselect', FALSE, ['width' => 24], 0, [], ['|', '|  up/down move - space', '|  toggle - enter', '|  confirm']];
  }

  public function testRenderLegendIsDim(): void {
    $p = $this->createAndSetInstance(['unicode' => FALSE, 'ansi' => TRUE, 'legend' => TRUE]);

    $lines = $this->callProtectedLines($p, 'renderLegend', 'text', 0, []);

    $this->assertCount(2, $lines);
    $this->assertStringContainsString("\033[2menter confirm\033[0m", $lines[1]);
  }

  #[DataProvider('dataProviderWidgetDrawsLegend')]
  public function testWidgetDrawsLegend(\Closure $widget, array $ctx_overrides, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->ctx($ctx_overrides)), self::KEY_ESCAPE, ['legend' => TRUE]);

    $this->assertNull($r['result']);
    $this->assertSame(1, substr_count($r['output'], $expected));
  }

  public static function dataProviderWidgetDrawsLegend(): \Iterator {
    $text = static fn(array $ctx): mixed => Prompty::text('Dish name', placeholder: 'pear tart', ctx: $ctx);
    $select = static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, ctx: $ctx);
    $multiselect = static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, ctx: $ctx);
    $suggest = static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, ctx: $ctx);
    $confirm = static fn(array $ctx): mixed => Prompty::confirm('Send order?', ctx: $ctx);
    $pause = static fn(array $ctx): mixed => Prompty::pause('Press any key to start the order...', ctx: $ctx);
    $pause_enter = static fn(array $ctx): mixed => Prompty::pause('Press Enter to send the order to the kitchen', enter_only: TRUE, ctx: $ctx);

    yield 'text' => [$text, [], "|  pear tart█\n|\n|  enter confirm\n|\n"];
    yield 'text nested' => [$text, self::CTX_AT_DEPTH, "|  |  pear tart█\n|  |  \n|  |  enter confirm\n|  |  \n"];
    yield 'select' => [$select, [], "|  ( ) Dessert\n|\n|  up/down move - enter confirm\n|\n"];
    yield 'select nested' => [$select, self::CTX_AT_DEPTH, "|  |    ( ) Dessert\n|  |  \n|  |  up/down move - enter confirm\n|  |  \n"];
    yield 'multiselect' => [$multiselect, [], "|  [ ] Herbs\n|\n|  up/down move - space toggle - enter confirm\n|\n"];
    yield 'multiselect nested' => [$multiselect, self::CTX_AT_DEPTH, "|  |    [ ] Herbs\n|  |  \n|  |  up/down move - space toggle - enter confirm\n|  |  \n"];
    yield 'suggest' => [$suggest, [], "|  Onion Soup\n|\n|  type to filter - up/down move - enter confirm\n|\n"];
    yield 'suggest nested' => [$suggest, self::CTX_AT_DEPTH, "|  |    Onion Soup\n|  |  \n|  |  type to filter - up/down move - enter confirm\n|  |  \n"];
    yield 'confirm' => [$confirm, [], "|  (*) Yes / ( ) No\n|\n|  left/right toggle - y/n choose - enter confirm\n|\n"];
    yield 'confirm nested' => [$confirm, self::CTX_AT_DEPTH, "|  |  (*) Yes / ( ) No\n|  |  \n|  |  left/right toggle - y/n choose - enter confirm\n|  |  \n"];
    yield 'pause' => [$pause, [], "o  Press any key to start the order...\n|\n|  any key continue\n|\n"];
    yield 'pause nested' => [$pause, self::CTX_AT_DEPTH, "|  o  Press any key to start the order...\n|  |  \n|  |  any key continue\n|  |  \n"];
    yield 'pause enter only' => [$pause_enter, [], "o  Press Enter to send the order to the kitchen\n|\n|  enter continue\n|\n"];
  }

  #[DataProvider('dataProviderWidgetDrawsNoLegendByDefault')]
  public function testWidgetDrawsNoLegendByDefault(\Closure $widget): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->ctx()), self::KEY_ESCAPE);

    $this->assertStringNotContainsString('enter confirm', $r['output']);
    $this->assertStringNotContainsString('continue', $r['output']);
  }

  public static function dataProviderWidgetDrawsNoLegendByDefault(): \Iterator {
    yield 'text' => [static fn(array $ctx): mixed => Prompty::text('Dish name', ctx: $ctx)];
    yield 'select' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, ctx: $ctx)];
    yield 'multiselect' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, ctx: $ctx)];
    yield 'suggest' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, ctx: $ctx)];
    yield 'confirm' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', ctx: $ctx)];
    yield 'pause' => [static fn(array $ctx): mixed => Prompty::pause('Press any key to start the order...', ctx: $ctx)];
  }

  public function testLegendSitsAfterHelp(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course', options: self::COURSES, help: 'Where the dish sits in the meal.', ctx: $this->ctx()), self::KEY_ESCAPE, ['legend' => TRUE]);

    $this->assertStringContainsString("|  ( ) Dessert\n|\n|  Where the dish sits in the meal.\n|\n|  up/down move - enter confirm\n|\n", $r['output']);
  }

  public function testLegendSitsAfterError(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', required: TRUE, ctx: $this->ctx()), self::KEY_ENTER . self::KEY_ESCAPE, ['legend' => TRUE]);

    $this->assertStringContainsString("|  █\n|  ! Required.\n|\n|  enter confirm\n|\n", $r['output']);
  }

  public function testCompletedLineDrawsNoLegend(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', ctx: $this->ctx()), 'pear tart' . self::KEY_ENTER, ['legend' => TRUE]);

    $this->assertSame('pear tart', $r['result']);
    $completed_at = strrpos($r['output'], "+  Dish name\n");
    $this->assertNotFalse($completed_at);
    $this->assertSame("+  Dish name\n|  pear tart\n|\n", substr($r['output'], $completed_at));
  }

  public function testCancelledLineDrawsNoLegend(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', ctx: $this->ctx()), 'pear' . self::KEY_ESCAPE, ['legend' => TRUE]);

    $this->assertNull($r['result']);
    $cancelled_at = strrpos($r['output'], "o  Dish name\n");
    $this->assertNotFalse($cancelled_at);
    $this->assertStringNotContainsString('enter confirm', substr($r['output'], $cancelled_at));
  }

  public function testDiscoveredAnswerDrawsNoLegend(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', discovered: 'pear tart', ctx: $this->ctx()), '', ['legend' => TRUE]);

    $this->assertSame('pear tart', $r['result']);
    $this->assertStringNotContainsString('enter confirm', $r['output']);
  }

  public function testConfigureTurnsLegendOn(): void {
    Prompty::configure(unicode: FALSE, legend: TRUE);
    $this->promptyInput(self::KEY_ESCAPE);

    $output = $this->captureOutput(function (): void {
      Prompty::confirm('Send order?');
    });

    $this->assertStringContainsString('left/right toggle - y/n choose - enter confirm', $output);
  }

  public function testFlowTurnsLegendOnForItsSteps(): void {
    $this->createAndSetInstance(['unicode' => FALSE]);
    $this->promptyInput('pear tart' . self::KEY_ENTER . self::KEY_ESCAPE);

    $flow = $this->captureOutput(function (): void {
      Prompty::flow()->text('dish', 'Dish name')->run(legend: TRUE);
    });

    $after = $this->captureOutput(function (): void {
      Prompty::text('Guest name');
    });

    $this->assertStringContainsString('enter confirm', $flow);
    $this->assertStringNotContainsString('enter confirm', $after);
    $this->assertFalse(Prompty::config()['legend']);
  }

}
