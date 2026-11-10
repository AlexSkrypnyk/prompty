<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the scroll window of select and multiselect.
 *
 * Each redraw starts with a carriage return once the cursor sequences are
 * stripped, so the output splits into the frames the widget drew. Most tests
 * assert on the last active frame, the one drawn just before the answer.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetScrollTest extends PromptyTestCase {

  protected const array MENU = [
    'tart' => 'Pear Tart',
    'crumble' => 'Apple Crumble',
    'soup' => 'Onion Soup',
    'stew' => 'Lentil Stew',
    'omelette' => 'Herb Omelette',
    'salad' => 'Barley Salad',
    'compote' => 'Plum Compote',
    'loaf' => 'Walnut Loaf',
  ];

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const array CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  /**
   * Run the select widget over the menu with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param int|null $scroll
   *   The widget's scroll size.
   * @param string $default
   *   Option key to pre-focus.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param array<string, mixed> $config
   *   Prompty configuration overrides.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runSelect(string $keystrokes, ?int $scroll = 3, string $default = '', array $hints = [], array $config = [], array $ctx_overrides = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::select('Menu',
      options: self::MENU,
      default: $default,
      hints: $hints,
      scroll: $scroll,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes, $config);
  }

  /**
   * Run the multiselect widget over the menu with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param int|null $scroll
   *   The widget's scroll size.
   * @param list<string> $default
   *   Option keys to pre-check.
   * @param array<string, mixed> $config
   *   Prompty configuration overrides.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselect(string $keystrokes, ?int $scroll = 3, array $default = [], array $config = [], array $ctx_overrides = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Pantry',
      options: self::MENU,
      default: $default,
      scroll: $scroll,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes, $config);
  }

  /**
   * Split captured output into the frames the widget drew.
   *
   * @param string $output
   *   ANSI-stripped widget output.
   *
   * @return list<list<string>>
   *   The lines of each frame, in the order drawn.
   */
  protected function frames(string $output): array {
    return array_map(fn(string $frame): array => explode("\n", rtrim($frame, "\n")), explode("\r", $output));
  }

  /**
   * Return the last frame drawn before the answer.
   *
   * @param string $output
   *   ANSI-stripped widget output.
   *
   * @return list<string>
   *   The lines of the frame.
   */
  protected function lastActiveFrame(string $output): array {
    $frames = $this->frames($output);
    $this->assertGreaterThan(1, count($frames));

    return $frames[count($frames) - 2];
  }

  #[DataProvider('dataProviderSelectWindow')]
  public function testSelectWindow(string $keystrokes, string $default, array $hints, string $expected, array $expected_frame): void {
    $r = $this->runSelect($keystrokes . self::KEY_ENTER, default: $default, hints: $hints);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($expected_frame, $this->lastActiveFrame($r['output']));
  }

  public static function dataProviderSelectWindow(): \Iterator {
    yield 'opens at the top' => ['', '', [], 'tart', ['o  Menu', '|', '|', '|> (*) Pear Tart', '|  ( ) Apple Crumble', '|  ( ) Onion Soup', '|  v 5 more', '|']];
    yield 'focus moves inside the window' => [
      self::KEY_DOWN . self::KEY_DOWN,
      '',
      [],
      'soup',
      ['o  Menu', '|', '|', '|  ( ) Pear Tart', '|  ( ) Apple Crumble', '|> (*) Onion Soup', '|  v 5 more', '|'],
    ];
    yield 'shifts by 1 past the bottom edge' => [
      self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN,
      '',
      [],
      'stew',
      ['o  Menu', '|', '|  ^ 1 more', '|  ( ) Apple Crumble', '|  ( ) Onion Soup', '|> (*) Lentil Stew', '|  v 4 more', '|'],
    ];
    yield 'shifts by 1 past the top edge' => [
      self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_UP . self::KEY_UP . self::KEY_UP,
      '',
      [],
      'crumble',
      ['o  Menu', '|', '|  ^ 1 more', '|> (*) Apple Crumble', '|  ( ) Onion Soup', '|  ( ) Lentil Stew', '|  v 4 more', '|'],
    ];
    yield 'up from the first wraps to the last' => [
      self::KEY_UP,
      '',
      [],
      'loaf',
      ['o  Menu', '|', '|  ^ 5 more', '|  ( ) Barley Salad', '|  ( ) Plum Compote', '|> (*) Walnut Loaf', '|', '|'],
    ];
    yield 'down from the last wraps to the first' => [
      self::KEY_UP . self::KEY_DOWN,
      '',
      [],
      'tart',
      ['o  Menu', '|', '|', '|> (*) Pear Tart', '|  ( ) Apple Crumble', '|  ( ) Onion Soup', '|  v 5 more', '|'],
    ];
    yield 'opens with the default in view' => [
      '',
      'compote',
      [],
      'compote',
      ['o  Menu', '|', '|  ^ 4 more', '|  ( ) Herb Omelette', '|  ( ) Barley Salad', '|> (*) Plum Compote', '|  v 1 more', '|'],
    ];
    yield 'default inside the first window opens at the top' => [
      '',
      'crumble',
      [],
      'crumble',
      ['o  Menu', '|', '|', '|  ( ) Pear Tart', '|> (*) Apple Crumble', '|  ( ) Onion Soup', '|  v 5 more', '|'],
    ];
    yield 'hint of the focused option stays inside the window' => [
      '',
      'stew',
      ['stew' => 'Simmered slowly.'],
      'stew',
      ['o  Menu', '|', '|  ^ 1 more', '|  ( ) Apple Crumble', '|  ( ) Onion Soup', '|> (*) Lentil Stew', '|    --> Simmered slowly.', '|  v 4 more', '|'],
    ];
  }

  /**
   * Tests the multiselect window.
   *
   * @param string $keystrokes
   *   Keystrokes fed before the final Enter.
   * @param int $scroll
   *   The widget's scroll size.
   * @param list<string> $default
   *   Option keys to pre-check.
   * @param list<string> $expected
   *   The selected option keys.
   * @param list<string> $expected_frame
   *   The lines of the last active frame.
   */
  #[DataProvider('dataProviderMultiselectWindow')]
  public function testMultiselectWindow(string $keystrokes, int $scroll, array $default, array $expected, array $expected_frame): void {
    $r = $this->runMultiselect($keystrokes . self::KEY_ENTER, $scroll, $default);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($expected_frame, $this->lastActiveFrame($r['output']));
  }

  public static function dataProviderMultiselectWindow(): \Iterator {
    yield 'opens at the top without a default' => [
      '',
      3,
      [],
      [],
      ['o  Pantry', '|', '|', '|> [ ] Pear Tart', '|  [ ] Apple Crumble', '|  [ ] Onion Soup', '|  v 5 more', '|'],
    ];
    yield 'opens on the first pre-checked option' => [
      '',
      3,
      ['loaf', 'salad'],
      ['salad', 'loaf'],
      ['o  Pantry', '|', '|  ^ 3 more', '|  [ ] Lentil Stew', '|  [ ] Herb Omelette', '|> [x] Barley Salad', '|  v 2 more', '|'],
    ];
    yield 'focus starts on a pre-checked option inside the first window' => [
      self::KEY_SPACE,
      3,
      ['soup'],
      [],
      ['o  Pantry', '|', '|', '|  [ ] Pear Tart', '|  [ ] Apple Crumble', '|> [ ] Onion Soup', '|  v 5 more', '|'],
    ];
    yield 'toggles an option after shifting' => [
      self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE,
      3,
      [],
      ['stew'],
      ['o  Pantry', '|', '|  ^ 1 more', '|  [ ] Apple Crumble', '|  [ ] Onion Soup', '|> [x] Lentil Stew', '|  v 4 more', '|'],
    ];
    yield 'up from the first wraps to the last' => [
      self::KEY_UP . self::KEY_SPACE,
      3,
      [],
      ['loaf'],
      ['o  Pantry', '|', '|  ^ 5 more', '|  [ ] Barley Salad', '|  [ ] Plum Compote', '|> [x] Walnut Loaf', '|', '|'],
    ];
    yield 'a list drawn in full keeps focus at the top' => [
      self::KEY_SPACE,
      0,
      ['soup'],
      ['tart', 'soup'],
      [
        'o  Pantry',
        '|',
        '|> [x] Pear Tart',
        '|  [ ] Apple Crumble',
        '|  [x] Onion Soup',
        '|  [ ] Lentil Stew',
        '|  [ ] Herb Omelette',
        '|  [ ] Barley Salad',
        '|  [ ] Plum Compote',
        '|  [ ] Walnut Loaf',
        '|',
      ],
    ];
  }

  #[DataProvider('dataProviderFullListHasNoIndicators')]
  public function testFullListHasNoIndicators(\Closure $run): void {
    $r = $run($this);
    $this->assertIsArray($r);
    $this->assertIsString($r['output']);

    $frame = $this->lastActiveFrame($r['output']);

    $this->assertCount(count(self::MENU) + 3, $frame);
    $this->assertSame('|> (*) Pear Tart', $frame[2]);
    $this->assertSame('|  ( ) Walnut Loaf', $frame[count(self::MENU) + 1]);
  }

  public static function dataProviderFullListHasNoIndicators(): \Iterator {
    yield 'scroll 0' => [static fn(self $test): array => $test->runSelect(self::KEY_ENTER, 0)];
    yield 'scroll equal to the option count' => [static fn(self $test): array => $test->runSelect(self::KEY_ENTER, count(self::MENU))];
    yield 'scroll above the option count' => [static fn(self $test): array => $test->runSelect(self::KEY_ENTER, 20)];
    yield 'no scroll and none configured' => [static fn(self $test): array => $test->runSelect(self::KEY_ENTER, NULL)];
    yield 'widget scroll 0 overrides the configured size' => [static fn(self $test): array => $test->runSelect(self::KEY_ENTER, 0, config: ['scroll' => 3])];
  }

  public function testConfiguredScrollApplies(): void {
    $r = $this->runSelect(self::KEY_ENTER, NULL, config: ['scroll' => 2]);

    $this->assertSame(['o  Menu', '|', '|', '|> (*) Pear Tart', '|  ( ) Apple Crumble', '|  v 6 more', '|'], $this->lastActiveFrame($r['output']));
  }

  public function testWidgetScrollOverridesConfiguredScroll(): void {
    $r = $this->runSelect(self::KEY_ENTER, 4, config: ['scroll' => 2]);

    $this->assertSame('|  v 4 more', $this->lastActiveFrame($r['output'])[7]);
  }

  #[DataProvider('dataProviderWindowKeepsItsHeight')]
  public function testWindowKeepsItsHeight(\Closure $run): void {
    $r = $run($this);
    $this->assertIsArray($r);
    $this->assertIsString($r['output']);

    $frames = $this->frames($r['output']);
    array_pop($frames);

    $this->assertSame([8], array_values(array_unique(array_map(count(...), $frames))));
  }

  public static function dataProviderWindowKeepsItsHeight(): \Iterator {
    $keys = str_repeat(self::KEY_DOWN, count(self::MENU)) . self::KEY_UP . self::KEY_ENTER;

    yield 'select' => [static fn(self $test): array => $test->runSelect($keys)];
    yield 'multiselect' => [static fn(self $test): array => $test->runMultiselect($keys)];
  }

  #[DataProvider('dataProviderCompletedAndCancelled')]
  public function testCompletedAndCancelled(\Closure $run, mixed $expected, array $expected_frame): void {
    $r = $run($this);
    $this->assertIsArray($r);
    $this->assertIsString($r['output']);

    $frames = $this->frames($r['output']);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($expected_frame, $frames[count($frames) - 1]);
  }

  public static function dataProviderCompletedAndCancelled(): \Iterator {
    $down_4 = str_repeat(self::KEY_DOWN, 4);

    yield 'select completed' => [static fn(self $test): array => $test->runSelect($down_4 . self::KEY_ENTER), 'omelette', ['+  Menu', '|  Herb Omelette', '|']];
    yield 'select cancelled' => [static fn(self $test): array => $test->runSelect($down_4 . self::KEY_CTRL_C), NULL, ['o  Menu', '|  Herb Omelette (cancelled)', '|']];
    yield 'multiselect completed' => [
      static fn(self $test): array => $test->runMultiselect(self::KEY_SPACE . $down_4 . self::KEY_SPACE . self::KEY_ENTER),
      ['tart', 'omelette'],
      ['+  Pantry', '|  Pear Tart, Herb Omelette', '|'],
    ];
    yield 'multiselect cancelled' => [static fn(self $test): array => $test->runMultiselect($down_4 . self::KEY_ESCAPE), NULL, ['o  Pantry', '|   (cancelled)', '|']];
  }

  #[DataProvider('dataProviderWindowAtDepth')]
  public function testWindowAtDepth(\Closure $run, array $expected_frame): void {
    $r = $run($this);
    $this->assertIsArray($r);
    $this->assertIsString($r['output']);

    $this->assertSame($expected_frame, $this->lastActiveFrame($r['output']));
  }

  public static function dataProviderWindowAtDepth(): \Iterator {
    $keys = str_repeat(self::KEY_DOWN, 3) . self::KEY_ENTER;

    yield 'select' => [
      static fn(self $test): array => $test->runSelect($keys, ctx_overrides: self::CTX_AT_DEPTH),
      ['|  o  Menu', '|  |  ', '|  |    ^ 1 more', '|  |    ( ) Apple Crumble', '|  |    ( ) Onion Soup', '|  |  > (*) Lentil Stew', '|  |    v 4 more', '|  |  '],
    ];
    yield 'select with nothing hidden above' => [
      static fn(self $test): array => $test->runSelect(self::KEY_ENTER, ctx_overrides: self::CTX_AT_DEPTH),
      ['|  o  Menu', '|  |  ', '|  |  ', '|  |  > (*) Pear Tart', '|  |    ( ) Apple Crumble', '|  |    ( ) Onion Soup', '|  |    v 5 more', '|  |  '],
    ];
    yield 'multiselect' => [
      static fn(self $test): array => $test->runMultiselect($keys, ctx_overrides: self::CTX_AT_DEPTH),
      ['|  o  Pantry', '|  |  ', '|  |    ^ 1 more', '|  |    [ ] Apple Crumble', '|  |    [ ] Onion Soup', '|  |  > [ ] Lentil Stew', '|  |    v 4 more', '|  |  '],
    ];
  }

  #[DataProvider('dataProviderIndicatorDisplay')]
  public function testIndicatorDisplay(array $config, string $expected_up, string $expected_down): void {
    $r = $this->runSelect(str_repeat(self::KEY_DOWN, 3) . self::KEY_ENTER, config: $config);

    $frame = $this->lastActiveFrame($r['output']);

    $this->assertSame($expected_up, $frame[2]);
    $this->assertSame($expected_down, $frame[6]);
  }

  public static function dataProviderIndicatorDisplay(): \Iterator {
    yield 'unicode symbols' => [['unicode' => TRUE], '│  ↑ 1 more', '│  ↓ 4 more'];
    yield 'custom ascii symbols' => [['symbols_ascii' => ['scroll_up' => '<', 'scroll_down' => '>'] + self::asciiSymbols()], '|  < 1 more', '|  > 4 more'];
    yield 'custom label' => [['labels' => ['more' => 'hidden'] + self::labels()], '|  ^ 1 hidden', '|  v 4 hidden'];
  }

  public function testFlowScrollAppliesToItsWidgets(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'menu' => Prompty::select('Menu', options: self::MENU),
    ], scroll: 2), self::KEY_ENTER);

    $this->assertSame(['menu' => 'tart'], $r['result']);
    $this->assertStringContainsString('|  v 6 more', $r['output']);
  }

  #[DataProvider('dataProviderScrollThreadsThroughFlowStep')]
  public function testScrollThreadsThroughFlowStep(\Closure $steps, string $keystrokes, array $expected, string $expected_output): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow($steps), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString($expected_output, $r['output']);
  }

  public static function dataProviderScrollThreadsThroughFlowStep(): \Iterator {
    yield 'select closure' => [
      static fn(): array => ['menu' => Prompty::select('Menu', options: self::MENU, scroll: 2)],
      self::KEY_ENTER,
      ['menu' => 'tart'],
      '|  v 6 more',
    ];
    yield 'select step with a condition' => [
      static fn(): array => ['menu' => Prompty::select('Menu', options: self::MENU, scroll: 2, condition: fn(): bool => TRUE)],
      self::KEY_ENTER,
      ['menu' => 'tart'],
      '|  v 6 more',
    ];
    yield 'multiselect closure' => [
      static fn(): array => ['pantry' => Prompty::multiselect('Pantry', options: self::MENU, scroll: 2)],
      self::KEY_SPACE . self::KEY_ENTER,
      ['pantry' => ['tart']],
      '|  v 6 more',
    ];
  }

  #[DataProvider('dataProviderScrollWindow')]
  public function testScrollWindow(int $first, int $focused, int $visible, int $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'scrollWindow', $first, $focused, $visible));
  }

  public static function dataProviderScrollWindow(): \Iterator {
    yield 'focus inside the window' => [2, 3, 3, 2];
    yield 'focus on the top edge' => [2, 2, 3, 2];
    yield 'focus on the bottom edge' => [2, 4, 3, 2];
    yield 'focus 1 above the window' => [2, 1, 3, 1];
    yield 'focus 1 below the window' => [2, 5, 3, 3];
    yield 'focus far above the window' => [5, 0, 3, 0];
    yield 'focus far below the window' => [0, 7, 3, 5];
    yield 'window holding every option' => [0, 7, 8, 0];
  }

  /**
   * The default ASCII symbol set.
   *
   * @return array<string, string>
   *   Symbol name to symbol.
   */
  protected static function asciiSymbols(): array {
    return ['bar' => '|', 'completed' => '+', 'active' => 'o', 'intro' => '#', 'outro' => '#', 'pointer' => '>', 'radio_on' => '(*)', 'radio_off' => '( )', 'check_on' => '[x]', 'check_off' => '[ ]', 'hint_arrow' => '-->', 'scroll_up' => '^', 'scroll_down' => 'v'];
  }

  /**
   * The default labels.
   *
   * @return array<string, string>
   *   Label name to text.
   */
  protected static function labels(): array {
    return ['yes' => 'Yes', 'no' => 'No', 'cancelled' => '(cancelled)', 'none' => 'None', 'separator' => '/', 'more' => 'more'];
  }

}
