<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty rendering methods.
 *
 * All expectations use heredocs with ANSI-stripped output for readability.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyRenderTest extends PromptyTestCase {

  #[DataProvider('dataProviderRenderIntro')]
  public function testRenderIntro(string $message, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtected($p, 'renderIntro', $message);

    $this->assertIsArray($lines);
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

    $lines = $this->callProtected($p, 'renderOutro', $message);

    $this->assertIsArray($lines);
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

    $lines = $this->callProtected($p, 'renderDescription', $description, $depth, $open);

    $this->assertIsArray($lines);
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
      // bodyPrefix(1, [1=>TRUE]) = '  |  ' → trailing line has trailing spaces.
      "|  |  Server-side rendering framework.\n|  |  ",
    ];

    yield 'single line, depth 1, last' => [
      'Static site generator.',
      1,
      [],
      // bodyPrefix(1, []) = '     ' → trailing line has trailing spaces.
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

    $lines = $this->callProtected($p, 'renderHint', $hint, $depth, $open);

    $this->assertIsArray($lines);
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
  public function testRenderCompleted(string $label, string $value, int $depth, bool $is_last, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtected($p, 'renderCompleted', $label, $value, $depth, $is_last, $open);

    $this->assertIsArray($lines);
    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderCompleted(): \Iterator {
    yield 'depth 0, simple' => [
      'Project name',
      'my-app',
      0,
      FALSE,
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
      FALSE,
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
      FALSE,
      [1 => TRUE],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, [1=>TRUE]) = '  |  '.
      "|  +  App framework\n|  |  Next.js\n|  |  ",
    ];

    yield 'depth 1, is last' => [
      'API routes',
      'Yes',
      1,
      TRUE,
      [],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, []) = '     '.
      "|  +  API routes\n|     Yes\n|     ",
    ];

    yield 'depth 2, open at 1 and 2' => [
      'SSR mode',
      'Hybrid',
      2,
      FALSE,
      [1 => TRUE, 2 => TRUE],
      // labelPrefix(2) = '  |  ', bodyPrefix(2, [1,2=>TRUE]) = '  |  |  '.
      "|  |  +  SSR mode\n|  |  |  Hybrid\n|  |  |  ",
    ];
  }

  #[DataProvider('dataProviderRenderCancelled')]
  public function testRenderCancelled(string $label, string $value, int $depth, bool $is_last, array $open, string $expected): void {
    $p = $this->createInstance();

    $lines = $this->callProtected($p, 'renderCancelled', $label, $value, $depth, $is_last, $open);

    $this->assertIsArray($lines);
    $actual = $this->stripAnsi(implode("\n", $lines));

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderRenderCancelled(): \Iterator {
    yield 'depth 0, empty value' => [
      'Project name',
      '',
      0,
      FALSE,
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
      FALSE,
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
      FALSE,
      [1 => TRUE],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, [1=>TRUE]) = '  |  '.
      "|  o  Framework\n|  |  React (cancelled)\n|  |  ",
    ];

    yield 'depth 1, is last' => [
      'Framework',
      '',
      1,
      TRUE,
      [],
      // labelPrefix(1, ...) = '  ', bodyPrefix(1, []) = '     '.
      "|  o  Framework\n|      (cancelled)\n|     ",
    ];
  }

}
