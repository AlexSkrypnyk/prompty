<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty rendering, styling and output.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyRenderTest extends PromptyTestCase {

  #[DataProvider('dataProviderRenderIntro')]
  public function testRenderIntro(string $message, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderIntro', $message);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderIntro(): \Iterator {
    yield 'simple intro' => [
      'Create your project',
      <<<'EXPECTED'

#  Create your project
|
EXPECTED,
    ];

    yield 'short intro' => [
      'Setup',
      <<<'EXPECTED'

#  Setup
|
EXPECTED,
    ];
  }

  #[DataProvider('dataProviderRenderOutro')]
  public function testRenderOutro(string $message, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderOutro', $message);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderOutro(): \Iterator {
    yield 'simple outro' => [
      "You're all set!",
      <<<'EXPECTED'
|
#  You're all set!

EXPECTED,
    ];

    yield 'cancelled outro' => [
      'Cancelled.',
      <<<'EXPECTED'
|
#  Cancelled.

EXPECTED,
    ];
  }

  #[DataProvider('dataProviderRenderDescription')]
  public function testRenderDescription(string $description, int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderDescription', $description, $depth, $open);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderDescription(): \Iterator {
    yield 'single line, depth 0' => [
      'Used as the directory name.',
      0,
      [],
      <<<'EXPECTED'
|  Used as the directory name.
|
EXPECTED,
    ];

    yield 'multi-line, depth 0' => [
      "Used as the directory name and the \"name\" field\nin package.json.",
      0,
      [],
      <<<'EXPECTED'
|  Used as the directory name and the "name" field
|  in package.json.
|
EXPECTED,
    ];

    yield 'single line, depth 1, open' => [
      'Server-side rendering framework.',
      1,
      [1 => TRUE],
      // bodyPrefix(1, [1=>TRUE]) = '  |  ', so the last line ends in spaces.
      "|  |  Server-side rendering framework.\n|  |  ",
    ];

    yield 'single line, depth 1, last' => [
      'Static site generator.',
      1,
      [],
      // bodyPrefix(1, []) = '     ', so the last line ends in spaces.
      "|     Static site generator.\n|     ",
    ];

    yield 'multi-line, depth 2, open' => [
      "How pages are rendered.\nChoose carefully.",
      2,
      [1 => TRUE, 2 => TRUE],
      // bodyPrefix(2, [1=>TRUE, 2=>TRUE]) = '  |  |  '.
      "|  |  |  How pages are rendered.\n|  |  |  Choose carefully.\n|  |  |  ",
    ];
  }

  #[DataProvider('dataProviderRenderHint')]
  public function testRenderHint(string $hint, int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderHint', $hint, $depth, $open);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderHint(): \Iterator {
    yield 'single line, depth 0' => [
      'Component-based library by Meta.',
      0,
      [],
      <<<'EXPECTED'
|    --> Component-based library by Meta.
EXPECTED,
    ];

    yield 'multi-line, depth 0' => [
      "Compile-time framework.\nSmaller bundles.",
      0,
      [],
      <<<'EXPECTED'
|    --> Compile-time framework.
|      Smaller bundles.
EXPECTED,
    ];

    yield 'single line, depth 1, open' => [
      'Helps debug your library.',
      1,
      [1 => TRUE],
      <<<'EXPECTED'
|  |      --> Helps debug your library.
EXPECTED,
    ];
  }

  #[DataProvider('dataProviderRenderCompleted')]
  public function testRenderCompleted(string $label, string $value, int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderCompleted', $label, $value, $depth, $open);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderCompleted(): \Iterator {
    yield 'depth 0, simple' => [
      'Project name',
      'my-app',
      0,
      [],
      <<<'EXPECTED'
+  Project name
|  my-app
|
EXPECTED,
    ];

    yield 'depth 0, boolean value' => [
      'Install dependencies?',
      'Yes',
      0,
      [],
      <<<'EXPECTED'
+  Install dependencies?
|  Yes
|
EXPECTED,
    ];

    yield 'depth 1, has next sibling' => [
      'App framework',
      'Next.js',
      1,
      [1 => TRUE],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, [1=>TRUE]) = '  |  '.
      "|  +  App framework\n|  |  Next.js\n|  |  ",
    ];

    yield 'depth 1, is last' => [
      'API routes',
      'Yes',
      1,
      [],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, []) = '     '.
      "|  +  API routes\n|     Yes\n|     ",
    ];

    yield 'depth 2, open at 1 and 2' => [
      'SSR mode',
      'Hybrid',
      2,
      [1 => TRUE, 2 => TRUE],
      // labelPrefix(2) = '  |  ', bodyPrefix(2, [1,2=>TRUE]) = '  |  |  '.
      "|  |  +  SSR mode\n|  |  |  Hybrid\n|  |  |  ",
    ];
  }

  #[DataProvider('dataProviderRenderCancelled')]
  public function testRenderCancelled(string $label, string $value, int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtectedLines($p, 'renderCancelled', $label, $value, $depth, $open);

    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderCancelled(): \Iterator {
    yield 'depth 0, empty value' => [
      'Project name',
      '',
      0,
      [],
      <<<'EXPECTED'
o  Project name
|   (cancelled)
|
EXPECTED,
    ];

    yield 'depth 0, with partial value' => [
      'Project name',
      'my-a',
      0,
      [],
      <<<'EXPECTED'
o  Project name
|  my-a (cancelled)
|
EXPECTED,
    ];

    yield 'depth 1, has next sibling' => [
      'Framework',
      'React',
      1,
      [1 => TRUE],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, [1=>TRUE]) = '  |  '.
      "|  o  Framework\n|  |  React (cancelled)\n|  |  ",
    ];

    yield 'depth 1, is last' => [
      'Framework',
      '',
      1,
      [],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, []) = '     '.
      "|  o  Framework\n|      (cancelled)\n|     ",
    ];
  }

  #[DataProvider('dataProviderLabelPrefix')]
  public function testLabelPrefix(int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'labelPrefix', $depth, $open);

    $this->assertIsString($result);
    $this->assertSame($expected, $this->stripAnsi($result));
  }

  public static function dataProviderLabelPrefix(): \Iterator {
    // labelPrefix loops from level=1 to level<depth, so at depth=1 the loop
    // does not run and only the initial '  ' remains.
    yield 'depth 1, no open' => [1, [], '  '];
    yield 'depth 1, open at 1' => [1, [1 => TRUE], '  '];

    yield 'depth 2, open at 1' => [2, [1 => TRUE], '  |  '];
    yield 'depth 2, nothing open' => [2, [], '     '];

    yield 'depth 3, open at 1 and 2' => [3, [1 => TRUE, 2 => TRUE], '  |  |  '];
    yield 'depth 3, open at 1 only' => [3, [1 => TRUE], '  |     '];
    yield 'depth 3, open at 2 only' => [3, [2 => TRUE], '     |  '];
    yield 'depth 3, nothing open' => [3, [], '        '];
  }

  #[DataProvider('dataProviderBodyPrefix')]
  public function testBodyPrefix(int $depth, array $open, string $expected): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'bodyPrefix', $depth, $open);

    $this->assertIsString($result);
    $this->assertSame($expected, $this->stripAnsi($result));
  }

  public static function dataProviderBodyPrefix(): \Iterator {
    // bodyPrefix loops from level=1 to level<=depth.
    yield 'depth 1, open at 1' => [1, [1 => TRUE], '  |  '];
    yield 'depth 1, nothing open' => [1, [], '     '];

    yield 'depth 2, open at 1 and 2' => [2, [1 => TRUE, 2 => TRUE], '  |  |  '];
    yield 'depth 2, open at 1 only' => [2, [1 => TRUE], '  |     '];
    yield 'depth 2, nothing open' => [2, [], '        '];

    yield 'depth 3, all open' => [3, [1 => TRUE, 2 => TRUE, 3 => TRUE], '  |  |  |  '];
    yield 'depth 3, open at 1 and 3' => [3, [1 => TRUE, 3 => TRUE], '  |     |  '];
    yield 'depth 3, nothing open' => [3, [], '           '];
  }

  #[DataProvider('dataProviderColor')]
  public function testColor(array $config, string $text, string $color_name, string $expected): void {
    $p = $this->createInstance($config);

    $result = $this->callProtected($p, 'color', $text, $color_name);

    $this->assertSame($expected, $result);
  }

  public static function dataProviderColor(): \Iterator {
    yield 'dim' => [[], 'hello', 'dim', "\033[2mhello\033[0m"];
    yield 'dim_italic' => [[], 'hello', 'dim_italic', "\033[2;3mhello\033[0m"];
    yield 'cyan' => [[], 'hello', 'cyan', "\033[36mhello\033[0m"];
    yield 'green' => [[], 'hello', 'green', "\033[32mhello\033[0m"];
    yield 'red' => [[], 'hello', 'red', "\033[31mhello\033[0m"];
    yield 'gray' => [[], 'hello', 'gray', "\033[90mhello\033[0m"];
    yield 'bold' => [[], 'hello', 'bold', "\033[1mhello\033[0m"];
    yield 'white' => [[], 'hello', 'white', "\033[37mhello\033[0m"];
    yield 'unknown color' => [[], 'hello', 'nonexistent', 'hello'];
    yield 'empty text' => [[], '', 'cyan', "\033[36m\033[0m"];
    yield 'ansi disabled' => [['ansi' => FALSE], 'hello', 'cyan', 'hello'];
  }

  #[DataProvider('dataProviderBar')]
  public function testBar(bool $unicode, string $expected): void {
    $p = $this->createInstance(['unicode' => $unicode]);

    $result = $this->callProtected($p, 'bar');

    $this->assertIsString($result);
    $this->assertSame($expected, $this->stripAnsi($result));
  }

  public static function dataProviderBar(): \Iterator {
    yield 'ascii bar' => [FALSE, '|'];
    yield 'unicode bar' => [TRUE, '│'];
  }

  public function testBarHasGrayColor(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'bar');

    $this->assertIsString($result);
    $this->assertStringStartsWith("\033[90m", $result);
    $this->assertStringEndsWith("\033[0m", $result);
  }

  #[DataProvider('dataProviderNumberLabel')]
  public function testNumberLabel(string $label, array $ctx, string $expected): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'numberLabel', $label, $ctx);

    $this->assertIsString($result);
    $this->assertSame($expected, $this->stripAnsi($result));
  }

  public static function dataProviderNumberLabel(): \Iterator {
    yield 'simple number' => ['Project name', ['number' => '1'], 'Project name (1)'];

    yield 'nested number' => ['Framework', ['number' => '1.2'], 'Framework (1.2)'];

    yield 'deeply nested number' => ['SSR mode', ['number' => '1.1.3'], 'SSR mode (1.1.3)'];

    yield 'no number in ctx' => ['Project name', [], 'Project name'];

    yield 'null number in ctx' => ['Project name', ['number' => NULL], 'Project name'];
  }

  public function testNumberLabelHasDimColor(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'numberLabel', 'Label', ['number' => '1']);

    $this->assertIsString($result);
    $this->assertStringContainsString("\033[2m(1)\033[0m", $result);
  }

  #[DataProvider('dataProviderStripAnsi')]
  public function testStripAnsi(string $text, string $expected): void {
    $this->assertSame($expected, $this->stripAnsi($text));
  }

  public static function dataProviderStripAnsi(): \Iterator {
    yield 'plain text' => ['hello', 'hello'];
    yield 'reset' => ["\033[0mhello", 'hello'];
    yield 'color' => ["\033[36mhello\033[0m", 'hello'];
    yield 'multi parameter' => ["\033[2;3mhello\033[0m", 'hello'];
    yield 'cursor up' => ["\033[2Ahello", 'hello'];
    yield 'hide cursor' => ["\033[?25lhello", 'hello'];
    yield 'show cursor' => ["\033[?25hhello", 'hello'];
    yield 'cursor pair around text' => ["\033[?25lhello\033[?25h", 'hello'];
    yield 'mixed private and standard' => ["\033[?25l\033[36mhello\033[0m\033[?25h", 'hello'];
    yield 'no escapes to strip' => ['[?25l', '[?25l'];
  }

  public function testStripAnsiRemovesCursorSequencesPromptyEmits(): void {
    $p = $this->createInstance();

    ob_start();
    $this->callProtected($p, 'hideCursor');
    $this->callProtected($p, 'showCursor');
    $raw = ob_get_clean();

    $this->assertIsString($raw);
    $this->assertNotSame('', $raw);
    $this->assertSame('', $this->stripAnsi($raw));
  }

  #[DataProvider('dataProviderIntroAndOutro')]
  public function testIntroAndOutro(\Closure $call, string $expected): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput($call);

    $this->assertStringContainsString($expected, $output);
  }

  public static function dataProviderIntroAndOutro(): \Iterator {
    yield 'intro' => [
      static function (): void {
        Prompty::intro('Welcome');
      },
      '#  Welcome',
    ];

    yield 'outro' => [
      static function (): void {
        Prompty::outro('Goodbye');
      },
      '#  Goodbye',
    ];
  }

  public function testOutput(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput(function (): void {
      $count = Prompty::output(['line 1', 'line 2', 'line 3']);
      $this->assertSame(3, $count);
    });

    $this->assertStringContainsString('line 1', $output);
    $this->assertStringContainsString('line 2', $output);
    $this->assertStringContainsString('line 3', $output);
  }

  #[DataProvider('dataProviderRedraw')]
  public function testRedraw(array $initial_lines, array $new_lines, string $expected): void {
    $p = $this->createInstance();

    $output = $this->captureOutput(function () use ($p, $initial_lines, $new_lines): void {
      if ($initial_lines !== []) {
        $count = $this->callProtected($p, 'printLines', $initial_lines);
        $this->assertSame(count($initial_lines), $count);
      }

      $new_count = $this->callProtected($p, 'redraw', count($initial_lines), $new_lines);
      $this->assertSame(count($new_lines), $new_count);
    });

    $this->assertStringContainsString($expected, $output);
  }

  public static function dataProviderRedraw(): \Iterator {
    yield 'clears previous lines' => [['old line 1', 'old line 2'], ['new line 1', 'new line 2', 'new line 3'], 'new line 1'];
    yield 'zero previous lines' => [[], ['line 1'], 'line 1'];
  }

  #[DataProvider('dataProviderCursorVisibility')]
  public function testCursorVisibility(string $method, string $expected): void {
    $p = $this->createInstance();

    // Asserts on the raw escape sequence, so the base captureOutput(), which
    // strips ANSI, cannot be used here.
    ob_start();
    $this->callProtected($p, $method);
    $output = ob_get_clean();

    $this->assertSame($expected, $output);
  }

  public static function dataProviderCursorVisibility(): \Iterator {
    yield 'show cursor' => ['showCursor', "\033[?25h"];
    yield 'hide cursor' => ['hideCursor', "\033[?25l"];
  }

  #[DoesNotPerformAssertions]
  public function testRestoreTty(): void {
    $p = $this->createInstance();

    // restoreTty() shells out to stty. Without a TTY the command fails
    // silently, so a dummy value only has to not throw.
    $this->callProtected($p, 'restoreTty', 'dummy-settings');
  }

  public function testSetupTtyNoTty(): void {
    $p = $this->createInstance();

    // Without a TTY attached, stty -g returns NULL.
    $this->captureOutput(fn(): mixed => $this->callProtected($p, 'setupTty'));

    $prev = $this->getProperty($p, 'prevTty');
    $this->assertTrue($prev === NULL || is_string($prev));
  }

  #[DataProvider('dataProviderTeardownTty')]
  public function testTeardownTty(?string $prev_tty, string $expected): void {
    $p = $this->createInstance();
    $this->setProperty($p, 'prevTty', $prev_tty);

    ob_start();
    $this->callProtected($p, 'teardownTty');
    $output = ob_get_clean();

    $this->assertSame($expected, $output);
    $this->assertNull($this->getProperty($p, 'prevTty'));
  }

  public static function dataProviderTeardownTty(): \Iterator {
    // A set prevTty restores the TTY through shell_exec(), which prints
    // nothing, so only the show-cursor escape reaches stdout.
    yield 'previous tty unset' => [NULL, ''];
    yield 'previous tty set' => ['dummy-tty-settings', "\033[?25h"];
  }

}
