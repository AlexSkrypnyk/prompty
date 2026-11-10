<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for wrapping rendered lines to the terminal width.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWrapTest extends PromptyTestCase {

  /**
   * Context of a widget run outside a flow.
   */
  protected const array CTX = ['depth' => 0, 'is_last' => FALSE, 'open' => []];

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const array CTX_NESTED = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  #[DataProvider('dataProviderDisplayWidth')]
  public function testDisplayWidth(string $text, int $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'displayWidth', $text));
  }

  public static function dataProviderDisplayWidth(): \Iterator {
    yield 'empty' => ['', 0];
    yield 'ascii' => ['Plum compote', 12];
    yield 'color sequences' => ["\033[2;3mPlum\033[0m", 4];
    yield 'cursor sequence' => ["\033[?25lPlum", 4];
    yield 'tree symbols' => ['│◆◇❯●○◼◻↳▲↑↓', 12];
    yield 'wide characters' => ['日本', 4];
    yield 'hangul' => ['한', 2];
    yield 'fullwidth' => ['Ａ', 2];
    yield 'emoji' => ['🍐', 2];
    yield 'emoji with variation selector' => ["\u{2764}\u{FE0F}", 2];
    yield 'flag' => ["\u{1F1FA}\u{1F1E6}", 2];
    yield 'adjacent pictographs' => ['◼◻◼', 3];
    yield 'zero-width joiner sequence' => ["\u{1F469}\u{200D}\u{1F373}", 2];
    yield 'skin tone' => ["\u{1F44D}\u{1F3FD}", 2];
    yield 'two flags' => ["\u{1F1FA}\u{1F1E6}\u{1F1FA}\u{1F1E6}", 4];
    yield 'combining mark' => ["e\u{0301}", 1];
    yield 'leading combining mark' => ["\u{0301}e", 1];
    yield 'zero-width space' => ["\u{200B}", 0];
    yield 'invalid utf-8' => ["Plum\xff", 5];
  }

  /**
   * Tests wrapping plain content after a gutter.
   *
   * @param int $width
   *   The terminal width.
   * @param string $gutter
   *   The prefix of the first row.
   * @param string $content
   *   The text to wrap.
   * @param string $continuation
   *   The prefix of every further row.
   * @param list<string> $expected
   *   The expected rows.
   */
  #[DataProvider('dataProviderWrap')]
  public function testWrap(int $width, string $gutter, string $content, string $continuation, array $expected): void {
    $p = $this->createInstance(['ansi' => FALSE, 'width' => $width]);

    $this->assertSame($expected, $this->callProtectedLines($p, 'wrap', $gutter, $content, $continuation));
  }

  public static function dataProviderWrap(): \Iterator {
    yield 'fits' => [20, '|  ', 'Plum compote', '|  ', ['|  Plum compote']];

    yield 'fits exactly' => [15, '|  ', 'Plum compote', '|  ', ['|  Plum compote']];

    yield 'empty content' => [10, '|  ', '', '|  ', ['|  ']];

    yield 'breaks at a space' => [16, '|  ', 'Plum compote with cream', '|  ', ['|  Plum compote', '|  with cream']];

    yield 'breaks across several rows' => [12, '|  ', 'Bring the bread first, then the olives', '|  ', ['|  Bring the', '|  bread', '|  first,', '|  then the', '|  olives']];

    yield 'continuation differs from gutter' => [14, 'o  ', 'Plum compote with cream', '|  ', ['o  Plum', '|  compote', '|  with cream']];

    yield 'keeps spaces inside a row' => [20, '|  ', 'Plum  compote and cream', '|  ', ['|  Plum  compote and', '|  cream']];

    yield 'drops several spaces at a break' => [16, '|  ', 'Plum compote    with cream', '|  ', ['|  Plum compote', '|  with cream']];

    yield 'keeps leading spaces' => [10, '|  ', '  Plum tart', '|  ', ['|    Plum', '|  tart']];

    yield 'keeps trailing spaces that fit' => [20, '|  ', 'Plum compote and ', '|  ', ['|  Plum compote and ']];

    yield 'keeps trailing spaces that fit after a break' => [13, '|  ', 'Plum compote  ', '|  ', ['|  Plum', '|  compote  ']];

    yield 'drops trailing spaces that do not fit' => [10, '|  ', 'Plum tart    ', '|  ', ['|  Plum', '|  tart']];

    yield 'long word breaks at the width' => [8, '|  ', 'Sourdoughbread', '|  ', ['|  Sourd', '|  oughb', '|  read']];

    yield 'long word starts a new row' => [10, '|  ', 'Hot sourdoughbread', '|  ', ['|  Hot', '|  sourdou', '|  ghbread']];

    yield 'wide characters' => [9, '|  ', '日本 日本語', '|  ', ['|  日本', '|  日本語']];

    yield 'wide characters in a long word' => [8, '|  ', '日本語日本', '|  ', ['|  日本', '|  語日', '|  本']];

    yield 'flags break whole' => [6, '|  ', "\u{1F1FA}\u{1F1E6}\u{1F1FA}\u{1F1E6}", '|  ', ['|  ' . "\u{1F1FA}\u{1F1E6}", '|  ' . "\u{1F1FA}\u{1F1E6}"]];

    yield 'combining marks break with their letter' => [6, '|  ', "e\u{0301}e\u{0301}e\u{0301}e\u{0301}", '|  ', ['|  ' . "e\u{0301}e\u{0301}e\u{0301}", '|  ' . "e\u{0301}"]];

    yield 'gutter fills the width' => [3, '|  ', 'Plum compote', '|  ', ['|  Plum compote']];

    yield 'continuation fills the width' => [8, '|', 'Plum compote', '|       ', ['|Plum compote']];
  }

  public function testWrapReopensStyleAfterTheContinuation(): void {
    $p = $this->createInstance(['ansi' => TRUE, 'width' => 10]);

    $lines = $this->callProtectedLines($p, 'wrap', '| ', "\033[2mone two three\033[0m", '| ');

    $this->assertSame(["| \033[2mone two\033[0m", "| \033[2mthree\033[0m"], $lines);
  }

  public function testWrapClosesEveryStyleAtBreak(): void {
    $p = $this->createInstance(['ansi' => TRUE, 'width' => 10]);

    $lines = $this->callProtectedLines($p, 'wrap', '| ', "\033[2mone\033[0m \033[31mtwo three\033[0m", "\033[90m|\033[0m ");

    $this->assertSame(["| \033[2mone\033[0m \033[31mtwo\033[0m", "\033[90m|\033[0m \033[31mthree\033[0m"], $lines);
  }

  public function testWrapSplitsTextThatIsNotUtf8ByByte(): void {
    $p = $this->createInstance(['ansi' => FALSE, 'width' => 8]);

    $lines = $this->callProtectedLines($p, 'wrap', '|  ', "Plum\xff compote", '|  ');

    $this->assertSame(["|  Plum\xff", '|  compo', '|  te'], $lines);
  }

  public function testColumnsUsesConfiguredWidth(): void {
    $this->setRawEnv('COLUMNS', '100');
    $p = $this->createInstance(['width' => 42]);

    $this->assertSame(42, $this->callProtected($p, 'columns'));
  }

  #[DataProvider('dataProviderColumnsReadsEnvironment')]
  public function testColumnsReadsEnvironment(string $value, int $expected): void {
    $this->setRawEnv('COLUMNS', $value);
    $p = $this->createInstance(['width' => 0]);

    $this->assertSame($expected, $this->callProtected($p, 'columns'));
  }

  public static function dataProviderColumnsReadsEnvironment(): \Iterator {
    yield 'number' => ['120', 120];
    yield 'padded number' => [' 64 ', 64];
  }

  #[DataProvider('dataProviderColumnsIgnoresInvalidEnvironment')]
  public function testColumnsIgnoresInvalidEnvironment(string $value): void {
    $this->setRawEnv('COLUMNS', $value);
    $p = $this->createInstance(['width' => 0]);

    $columns = $this->callProtected($p, 'columns');

    $this->assertIsInt($columns);
    $this->assertGreaterThan(0, $columns);
  }

  public static function dataProviderColumnsIgnoresInvalidEnvironment(): \Iterator {
    yield 'empty' => [''];
    yield 'zero' => ['0'];
    yield 'negative' => ['-80'];
    yield 'text' => ['wide'];
    yield 'fraction' => ['80.5'];
  }

  public function testColumnsAreMeasuredAgainAfterEachFrame(): void {
    $this->setRawEnv('COLUMNS', '50');
    $p = $this->createInstance(['width' => 0]);
    $this->setProperty($p, 'cfgOutput', static fn(string $chunk): null => NULL);

    $this->assertSame(50, $this->callProtected($p, 'columns'));

    $this->setRawEnv('COLUMNS', '70');
    $this->assertSame(50, $this->callProtected($p, 'columns'));

    $this->callProtected($p, 'printLines', ['Plum compote']);
    $this->assertSame(70, $this->callProtected($p, 'columns'));
  }

  /**
   * Tests printLines() counts the terminal rows its lines occupy.
   *
   * @param list<string> $lines
   *   The lines to print.
   * @param int $expected
   *   The expected row count.
   */
  #[DataProvider('dataProviderPrintLinesCountsRows')]
  public function testPrintLinesCountsRows(array $lines, int $expected): void {
    $p = $this->createInstance(['width' => 10]);
    $this->setProperty($p, 'cfgOutput', static fn(string $chunk): null => NULL);

    $this->assertSame($expected, $this->callProtected($p, 'printLines', $lines));
  }

  public static function dataProviderPrintLinesCountsRows(): \Iterator {
    yield 'short lines' => [['Plum', 'Pear'], 2];
    yield 'empty line' => [[''], 1];
    yield 'exactly the width' => [[str_repeat('x', 10)], 1];
    yield 'over the width' => [[str_repeat('x', 11)], 2];
    yield 'several rows' => [['Plum', str_repeat('x', 25), ''], 5];
    yield 'color sequences have no width' => [["\033[2m" . str_repeat('x', 10) . "\033[0m"], 1];
    yield 'wide characters' => [[str_repeat('日', 6)], 2];
  }

  public function testOutputReturnsTheLineCount(): void {
    $this->createAndSetInstance(['width' => 10]);
    Prompty::configure(output: static fn(string $chunk): null => NULL);

    $this->assertSame(2, Prompty::output([str_repeat('x', 25), 'Plum']));
  }

  public function testRedrawErasesEveryRowOfWrappedFrame(): void {
    $description = 'Where the dish sits in the meal, which decides the plate and the moment it leaves the kitchen.';
    $raw = '';

    $this->promptyRun(function () use ($description, &$raw): void {
      Prompty::configure(output: function (string $chunk) use (&$raw): void {
        $raw .= $chunk;
      });
      Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main'], description: $description, ctx: self::CTX);
    }, self::KEY_DOWN . self::KEY_ENTER, ['width' => 40]);

    preg_match_all('/\033\[(\d+)A\r\033\[J/', $raw, $matches);
    $frames = preg_split('/\033\[\d+A\r\033\[J/', $raw) ?: [];

    // The first redraw follows the down key, the second the enter key.
    $this->assertCount(2, $matches[1]);

    foreach ($matches[1] as $index => $up) {
      $lines = explode(PHP_EOL, rtrim($this->stripAnsi($frames[$index]), PHP_EOL));

      $this->assertSame(count($lines), (int) $up);

      foreach ($lines as $line) {
        $this->assertLessThanOrEqual(40, strlen($line), $line);
      }
    }
  }

  /**
   * Tests each widget wraps its lines with the tree gutter on every row.
   */
  #[DataProvider('dataProviderWidgetsWrap')]
  public function testWidgetsWrap(\Closure $run, string $keystrokes, string $expected): void {
    $r = $this->promptyRun($run, $keystrokes, ['width' => 30]);

    $this->assertSame($expected, $this->trimLines($r['output']));
  }

  public static function dataProviderWidgetsWrap(): \Iterator {
    yield 'text' => [
      static fn(): mixed => Prompty::text('Note for the kitchen about table 12', default: 'Bring the bread first, then the olives', description: 'Written on the ticket and read out at the pass.', help: 'Type the note, then press enter.', ctx: self::CTX),
      self::KEY_ENTER,
      <<<'EXPECTED'
o  Note for the kitchen about
|  table 12
|  Written on the ticket and
|  read out at the pass.
|
|  Bring the bread first, then
|  the olives█
|
|  Type the note, then press
|  enter.
|
+  Note for the kitchen about
|  table 12
|  Bring the bread first, then
|  the olives
|

EXPECTED,
    ];

    yield 'select' => [
      static fn(): mixed => Prompty::select('Course', options: ['starter' => 'Starter served with warm sourdough bread', 'main' => 'Main'], hints: ['starter' => 'Comes out first, before the mains reach the pass.'], ctx: self::CTX),
      self::KEY_ENTER,
      <<<'EXPECTED'
o  Course
|
|> (*) Starter served with
|      warm sourdough bread
|    --> Comes out first,
|      before the mains reach
|      the pass.
|  ( ) Main
|
+  Course
|  Starter served with warm
|  sourdough bread
|

EXPECTED,
    ];

    yield 'multiselect' => [
      static fn(): mixed => Prompty::multiselect('Extras', options: ['bread' => 'Sourdough bread, warm, cut at the table', 'olives' => 'Marinated olives, served in oil', 'herbs' => 'Herbs picked the same morning'], default: ['bread', 'olives', 'herbs'], ctx: self::CTX),
      self::KEY_ENTER,
      <<<'EXPECTED'
o  Extras
|
|> [x] Sourdough bread, warm,
|      cut at the table
|  [x] Marinated olives,
|      served in oil
|  [x] Herbs picked the same
|      morning
|
+  Extras
|  Sourdough bread, warm, cut
|  at the table, Marinated
|  olives, served in oil,
|  Herbs picked the same
|  morning
|

EXPECTED,
    ];

    yield 'suggest' => [
      static fn(): mixed => Prompty::suggest('Dish', options: ['Plum compote with vanilla cream and toasted almonds', 'Pear tart'], default: 'P', ctx: self::CTX),
      self::KEY_DOWN . self::KEY_ENTER,
      <<<'EXPECTED'
o  Dish
|
|  P█
|  Plum compote with vanilla
|  cream and toasted almonds
|  Pear tart
|
o  Dish
|
|  P█
|> Plum compote with vanilla
|  cream and toasted almonds
|  Pear tart
|
+  Dish
|  Plum compote with vanilla
|  cream and toasted almonds
|

EXPECTED,
    ];

    yield 'confirm' => [
      static fn(): mixed => Prompty::confirm('Send the order to the kitchen now?', validate: fn(bool $value): ?string => $value ? NULL : 'The kitchen needs the order before the first course can start.', ctx: self::CTX),
      self::KEY_RIGHT . self::KEY_ENTER . self::KEY_LEFT . self::KEY_ENTER,
      <<<'EXPECTED'
o  Send the order to the
|  kitchen now?
|
|  (*) Yes / ( ) No
|
o  Send the order to the
|  kitchen now?
|
|  ( ) Yes / (*) No
|
o  Send the order to the
|  kitchen now?
|
|  ( ) Yes / (*) No
|  ! The kitchen needs the
|    order before the first
|    course can start.
|
o  Send the order to the
|  kitchen now?
|
|  (*) Yes / ( ) No
|
+  Send the order to the
|  kitchen now?
|  Yes
|

EXPECTED,
    ];

    yield 'cancelled' => [
      static fn(): mixed => Prompty::text('Dish', default: 'Plum compote with vanilla cream and toasted almonds', ctx: self::CTX),
      self::KEY_ESCAPE,
      <<<'EXPECTED'
o  Dish
|
|  Plum compote with vanilla
|  cream and toasted almonds█
|
o  Dish
|  Plum compote with vanilla
|  cream and toasted almonds
|  (cancelled)
|

EXPECTED,
    ];

    yield 'nested' => [
      static fn(): mixed => Prompty::select('Course for the second table', options: ['starter' => 'Starter served with warm sourdough bread', 'main' => 'Main'], description: 'Where the dish sits in the meal.', hints: ['starter' => 'Comes out first, before the mains.'], ctx: self::CTX_NESTED),
      self::KEY_ENTER,
      <<<'EXPECTED'
|  o  Course for the second
|  |  table
|  |  Where the dish sits in
|  |  the meal.
|  |
|  |  > (*) Starter served
|  |        with warm
|  |        sourdough bread
|  |      --> Comes out first,
|  |        before the mains.
|  |    ( ) Main
|  |
|  +  Course for the second
|  |  table
|  |  Starter served with warm
|  |  sourdough bread
|  |

EXPECTED,
    ];
  }

  public function testNestedFlowWraps(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'extras' => Prompty::confirm('Extras', children: [
        'note' => Prompty::text('Note about the extras for table 12'),
        'bread' => Prompty::confirm('Bread'),
      ]),
    ], intro: 'Kitchen order for table 12 at the window', outro: 'The order is on the pass and the kitchen has started.', interactive: FALSE), '', ['width' => 30]);

    $this->assertSame(<<<'EXPECTED'

#  Kitchen order for table 12
|  at the window
|
+  Extras
|  Yes
|
|  |
|  +  Note about the extras
|  |  for table 12
|  |
|  |
|  +  Bread
|     Yes
|
|
#  The order is on the pass
   and the kitchen has
   started.


EXPECTED, $this->trimLines($r['output']));
  }

  /**
   * Removes redraw carriage returns and the spaces that end each line.
   *
   * Blank lines inside a nested frame end with the body prefix's spaces.
   */
  protected function trimLines(string $output): string {
    return preg_replace('/ +$/m', '', str_replace("\r", '', $output)) ?? $output;
  }

  /**
   * Sets an environment variable restored when the test ends.
   */
  protected function setRawEnv(string $name, string $value): void {
    putenv($name . '=' . $value);
    $this->envKeys[] = $name;
  }

}
