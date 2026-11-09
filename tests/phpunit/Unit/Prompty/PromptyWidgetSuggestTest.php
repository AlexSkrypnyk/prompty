<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the suggest widget.
 *
 * Each redraw starts with a carriage return once the cursor sequences are
 * stripped, so the output splits into the frames the widget drew. Most tests
 * assert on the last active frame, the one drawn just before the answer.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetSuggestTest extends PromptyTestCase {

  protected const DISHES = ['Pear Tart', 'Apple Crumble', 'Onion Soup', 'Lentil Stew', 'Herb Omelette', 'Barley Salad', 'Plum Compote', 'Walnut Loaf'];

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  /**
   * Run the suggest widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<int|string, string>|\Closure $options
   *   The suggestions, or a closure returning them.
   * @param array<string, mixed> $args
   *   Further named arguments for suggest().
   * @param array<string, mixed> $config
   *   Prompty configuration overrides.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runSuggest(string $keystrokes, array|\Closure $options = self::DISHES, array $args = [], array $config = []): array {
    $text = fn(string $name): string => is_string($args[$name] ?? NULL) ? $args[$name] : '';
    $scroll = is_int($args['scroll'] ?? NULL) ? $args['scroll'] : NULL;

    return $this->promptyRun(fn(): mixed => Prompty::suggest('Dish',
      options: $options,
      default: $text('default'),
      placeholder: $text('placeholder'),
      description: $text('description'),
      scroll: $scroll,
      help: $text('help'),
      ctx: $this->defaultCtx(),
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
    return array_map(fn(string $frame): array => explode(PHP_EOL, rtrim($frame, PHP_EOL)), explode("\r", $output));
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

  /**
   * Return the frame drawn after the answer or the cancellation.
   *
   * @param string $output
   *   ANSI-stripped widget output.
   *
   * @return list<string>
   *   The lines of the frame.
   */
  protected function finalFrame(string $output): array {
    $frames = $this->frames($output);

    return $frames[count($frames) - 1];
  }

  #[DataProvider('dataProviderAnswer')]
  public function testAnswer(string $keystrokes, array $args, string $expected): void {
    $r = $this->runSuggest($keystrokes . self::KEY_ENTER, args: $args);

    $this->assertSame($expected, $r['result']);
    $this->assertSame(['+  Dish', '|  ' . $expected, '|'], $this->finalFrame($r['output']));
  }

  public static function dataProviderAnswer(): \Iterator {
    yield 'typed text outside the options' => ['quince jam', [], 'quince jam'];
    yield 'typed text matching an option is taken as typed' => ['onion soup', [], 'onion soup'];
    yield 'empty input takes the placeholder' => ['', ['placeholder' => 'Pear Tart'], 'Pear Tart'];
    yield 'default seeds the buffer' => ['', ['default' => 'Lentil Stew', 'placeholder' => 'Pear Tart'], 'Lentil Stew'];
    yield 'default is editable' => [self::KEY_BACKSPACE . 'x', ['default' => 'stew'], 'stex'];
    yield 'down takes the first match' => ['so' . self::KEY_DOWN, [], 'Onion Soup'];
    yield 'down twice takes the second match' => ['a' . self::KEY_DOWN . self::KEY_DOWN, [], 'Apple Crumble'];
    yield 'up takes the last match' => ['a' . self::KEY_UP, [], 'Walnut Loaf'];
    yield 'down past the last match returns to the typed text' => ['tart' . self::KEY_DOWN . self::KEY_DOWN, [], 'tart'];
    yield 'up past the first match returns to the typed text' => ['tart' . self::KEY_DOWN . self::KEY_UP, [], 'tart'];
    yield 'up from none and back down returns to the typed text' => ['tart' . self::KEY_UP . self::KEY_DOWN, [], 'tart'];
    yield 'typing clears the focus' => ['l' . self::KEY_DOWN . 'o', [], 'lo'];
    yield 'backspace clears the focus' => ['lo' . self::KEY_DOWN . self::KEY_BACKSPACE, [], 'l'];
    yield 'navigation without matches does nothing' => ['zz' . self::KEY_DOWN . self::KEY_UP, [], 'zz'];
    yield 'left, right and tab are ignored' => ['stew' . self::KEY_LEFT . self::KEY_RIGHT . self::KEY_TAB, [], 'stew'];
    yield 'down from an empty buffer takes the first option' => [self::KEY_DOWN, [], 'Pear Tart'];
    yield 'space is typed' => ['lentil stew', [], 'lentil stew'];
  }

  #[DataProvider('dataProviderMatches')]
  public function testMatches(array|\Closure $options, string $keystrokes, array $expected_rows): void {
    $r = $this->runSuggest($keystrokes . self::KEY_ENTER, $options);

    $frame = $this->lastActiveFrame($r['output']);

    $this->assertSame($expected_rows, array_slice($frame, 3, -1));
  }

  public static function dataProviderMatches(): \Iterator {
    yield 'empty buffer lists every option' => [['Pear Tart', 'Plum Compote'], '', ['|  Pear Tart', '|  Plum Compote']];
    yield 'substring anywhere in the option' => [self::DISHES, 'ou', ['|  Onion Soup']];
    yield 'case is ignored' => [self::DISHES, 'PLUM', ['|  Plum Compote']];
    yield 'option order is kept' => [self::DISHES, 'e', ['|  Pear Tart', '|  Apple Crumble', '|  Lentil Stew', '|  Herb Omelette', '|  Barley Salad', '|  Plum Compote']];
    yield 'no match draws no rows' => [self::DISHES, 'zz', []];
    yield 'backspace widens the matches' => [self::DISHES, 'ouz' . self::KEY_BACKSPACE . self::KEY_BACKSPACE, ['|  Onion Soup', '|  Herb Omelette', '|  Plum Compote', '|  Walnut Loaf']];
    yield 'keys are ignored' => [['main' => 'Lentil Stew', 'dessert' => 'Pear Tart'], 'pear', ['|  Pear Tart']];
    yield 'non-ascii case is folded' => [['Crème Brûlée', 'Pear Tart'], 'BRÛ', ['|  Crème Brûlée']];
    yield 'typed text that is not utf8 matches byte by byte' => [["Pear \xFF Tart", 'Plum Compote'], "\xFF", ["|  Pear \xFF Tart"]];
    yield 'option that is not utf8 matches byte by byte' => [["Pear \xFF Tart", 'Plum Compote'], 'TART', ["|  Pear \xFF Tart"]];
    yield 'option labels are made printable' => [["Pear\tTart", "Plum\033[31m Compote"], 'p', ['|  Pear Tart', '|  Plum Compote']];
    yield 'focused match carries the pointer' => [self::DISHES, 'ou' . self::KEY_DOWN, ['|> Onion Soup']];
  }

  #[DataProvider('dataProviderClosureOptions')]
  public function testClosureOptions(\Closure $options, string $keystrokes, string $expected, array $expected_rows): void {
    $r = $this->runSuggest($keystrokes . self::KEY_ENTER, $options);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($expected_rows, array_slice($this->lastActiveFrame($r['output']), 3, -1));
  }

  public static function dataProviderClosureOptions(): \Iterator {
    yield 'returns its own matches' => [
      static fn(string $typed): array => $typed === '' ? [] : [ucfirst($typed) . ' Tart', ucfirst($typed) . ' Compote'],
      'plum' . self::KEY_DOWN . self::KEY_DOWN,
      'Plum Compote',
      ['|  Plum Tart', '|> Plum Compote'],
    ];
    yield 'matches are drawn without filtering' => [static fn(string $typed): array => ['Walnut Loaf'], 'pear', 'pear', ['|  Walnut Loaf']];
    yield 'a value that is not an array suggests nothing' => [static fn(string $typed): string => 'Walnut Loaf', 'pear' . self::KEY_DOWN, 'pear', []];
    yield 'scalar entries are cast and others skipped' => [static fn(string $typed): array => [12, ['Pear Tart'], NULL, 'Walnut Loaf'], self::KEY_DOWN, '12', ['|> 12', '|  Walnut Loaf']];
    yield 'entries are made printable' => [static fn(string $typed): array => ["Pear\nTart"], self::KEY_DOWN, 'Pear Tart', ['|> Pear Tart']];
  }

  public function testClosureRunsOnlyWhenTheTextChanges(): void {
    $calls = [];
    $options = function (string $typed) use (&$calls): array {
      $calls[] = $typed;

      return ['Pear Tart', 'Plum Compote'];
    };

    $r = $this->runSuggest('p' . self::KEY_DOWN . self::KEY_DOWN . self::KEY_LEFT . 'l' . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_ENTER, $options);

    $this->assertSame('', $r['result']);
    $this->assertSame(['', 'p', 'pl', 'p', ''], $calls);
  }

  #[DataProvider('dataProviderWindow')]
  public function testWindow(string $keystrokes, array $args, array $config, array $expected_frame): void {
    $r = $this->runSuggest($keystrokes . self::KEY_ENTER, args: $args, config: $config);

    $this->assertSame($expected_frame, $this->lastActiveFrame($r['output']));
  }

  public static function dataProviderWindow(): \Iterator {
    yield 'opens at the top' => ['', ['scroll' => 3], [], ['o  Dish', '|', '|  █', '|', '|  Pear Tart', '|  Apple Crumble', '|  Onion Soup', '|  v 5 more', '|']];
    yield 'shifts by 1 past the bottom edge' => [
      'a' . str_repeat(self::KEY_DOWN, 4),
      ['scroll' => 3],
      [],
      ['o  Dish', '|', '|  a█', '|  ^ 1 more', '|  Apple Crumble', '|  Barley Salad', '|> Walnut Loaf', '|', '|'],
    ];
    yield 'up from none opens on the last match' => [
      self::KEY_UP,
      ['scroll' => 3],
      [],
      ['o  Dish', '|', '|  █', '|  ^ 5 more', '|  Barley Salad', '|  Plum Compote', '|> Walnut Loaf', '|', '|'],
    ];
    yield 'typing resets the window' => [
      self::KEY_UP . 'a',
      ['scroll' => 3],
      [],
      ['o  Dish', '|', '|  a█', '|', '|  Pear Tart', '|  Apple Crumble', '|  Barley Salad', '|  v 1 more', '|'],
    ];
    yield 'returning to none keeps the window' => [
      self::KEY_UP . self::KEY_DOWN,
      ['scroll' => 3],
      [],
      ['o  Dish', '|', '|  █', '|  ^ 5 more', '|  Barley Salad', '|  Plum Compote', '|  Walnut Loaf', '|', '|'],
    ];
    yield 'matches within the size draw no indicators' => ['ou', ['scroll' => 3], [], ['o  Dish', '|', '|  ou█', '|  Onion Soup', '|']];
    yield 'configured scroll applies' => ['', [], ['scroll' => 2], ['o  Dish', '|', '|  █', '|', '|  Pear Tart', '|  Apple Crumble', '|  v 6 more', '|']];
    yield 'help follows the list' => [
      'ou',
      ['help' => 'Up and down to pick.', 'description' => 'From the menu.'],
      [],
      ['o  Dish', '|  From the menu.', '|', '|  ou█', '|  Onion Soup', '|', '|  Up and down to pick.', '|'],
    ];
  }

  public function testPlaceholderShowsInAnEmptyBuffer(): void {
    $r = $this->runSuggest(self::KEY_ENTER, ['Pear Tart'], ['placeholder' => 'pear tart']);

    $this->assertSame(['o  Dish', '|', '|  pear tart█', '|  Pear Tart', '|'], $this->lastActiveFrame($r['output']));
  }

  public function testWindowAtDepth(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::suggest('Dish', options: self::DISHES, scroll: 2, ctx: $this->defaultCtx(self::CTX_AT_DEPTH)), 'o' . self::KEY_DOWN . self::KEY_ENTER);

    $this->assertSame('Onion Soup', $r['result']);
    $this->assertSame(['|  o  Dish', '|  |  ', '|  |  o█', '|  |  ', '|  |  > Onion Soup', '|  |    Herb Omelette', '|  |    v 2 more', '|  |  '], $this->lastActiveFrame($r['output']));
  }

  #[DataProvider('dataProviderCancel')]
  public function testCancel(string $keystrokes, string $expected_line): void {
    $r = $this->runSuggest($keystrokes);

    $this->assertNull($r['result']);
    $this->assertSame(['o  Dish', $expected_line, '|'], $this->finalFrame($r['output']));
  }

  public static function dataProviderCancel(): \Iterator {
    yield 'shows the typed text' => ['pea' . self::KEY_CTRL_C, '|  pea (cancelled)'];
    yield 'shows the focused match' => ['pea' . self::KEY_DOWN . self::KEY_ESCAPE, '|  Pear Tart (cancelled)'];
    yield 'end of input' => ['pea', '|  pea (cancelled)'];
  }

  #[DataProvider('dataProviderDiscovered')]
  public function testDiscovered(mixed $discovered, ?string $ctx_discovered, array $args, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::suggest('Dish', ...array_merge(['options' => self::DISHES, 'discovered' => $discovered, 'ctx' => $this->defaultCtx(['discovered' => $ctx_discovered])], $args)), '');

    $this->assertSame($expected, $r['result']);
    $this->assertSame('+  Dish' . PHP_EOL . '|  ' . $expected . PHP_EOL . '|' . PHP_EOL, $r['output']);
  }

  public static function dataProviderDiscovered(): \Iterator {
    yield 'one of the options' => ['Onion Soup', NULL, [], 'Onion Soup'];
    yield 'outside the options' => ['quince jam', NULL, [], 'quince jam'];
    yield 'trimmed' => ['  Onion Soup  ', NULL, [], 'Onion Soup'];
    yield 'empty takes the default' => ['', NULL, ['default' => 'Lentil Stew', 'placeholder' => 'Pear Tart'], 'Lentil Stew'];
    yield 'blank takes the placeholder' => ['  ', NULL, ['placeholder' => 'Pear Tart'], 'Pear Tart'];
    yield 'environment value' => [NULL, 'walnut loaf', [], 'walnut loaf'];
    yield 'argument wins over the environment' => ['Onion Soup', 'walnut loaf', [], 'Onion Soup'];
    yield 'made printable' => ["Onion\tSoup", NULL, [], 'Onion Soup'];
    yield 'closure options are not called' => [
      'Onion Soup',
      NULL,
      ['options' => static fn(string $typed): array => throw new \RuntimeException('Not called.')],
      'Onion Soup',
    ];
  }

  #[DataProvider('dataProviderRejects')]
  public function testRejects(\Closure $widget, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => $widget($this->defaultCtx()), $message);
  }

  public static function dataProviderRejects(): \Iterator {
    yield 'empty options' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: [], ctx: $ctx), 'No options declared for "Dish". Provide at least one option.'];
    yield 'empty options with a discovered value' => [
      static fn(array $ctx): mixed => Prompty::suggest('Dish', options: [], discovered: 'Pear Tart', ctx: $ctx),
      'No options declared for "Dish". Provide at least one option.',
    ];
    yield 'negative scroll' => [
      static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, scroll: -1, ctx: $ctx),
      'Scroll size -1 for "Dish" is not valid. Use 0 to show every option, or the number of options to show at once.',
    ];
  }

  public function testClosureReturningNothingIsAccepted(): void {
    $r = $this->runSuggest('pear' . self::KEY_ENTER, static fn(string $typed): array => []);

    $this->assertSame('pear', $r['result']);
  }

  #[DataProvider('dataProviderInFlow')]
  public function testInFlow(\Closure $steps, string $keystrokes, array $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow($steps, numbering: TRUE), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString('+  Dish (1)', $r['output']);
  }

  public static function dataProviderInFlow(): \Iterator {
    yield 'closure' => [
      static fn(): array => ['dish' => Prompty::suggest('Dish', options: self::DISHES, scroll: 2)],
      'ou' . self::KEY_DOWN . self::KEY_ENTER,
      ['dish' => 'Onion Soup'],
    ];
    yield 'step with a condition and children' => [
      static fn(): array => [
        'dish' => Prompty::suggest('Dish', options: self::DISHES, placeholder: 'Pear Tart', condition: fn(): bool => TRUE, children: [
          'send' => Prompty::confirm('Send order?'),
        ]),
      ],
      self::KEY_ENTER . self::KEY_ENTER,
      ['dish' => 'Pear Tart', 'send' => TRUE],
    ];
  }

  public function testUnicodeRendering(): void {
    $r = $this->runSuggest('ou' . self::KEY_DOWN . self::KEY_ENTER, config: ['unicode' => TRUE]);

    $this->assertSame('│❯ Onion Soup', $this->lastActiveFrame($r['output'])[3]);
  }

}
