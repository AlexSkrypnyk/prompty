<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the public text helpers.
 *
 * Covers style(), strip(), width() and terminalWidth().
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTextHelpersTest extends PromptyTestCase {

  protected const string RESET = "\033[0m";

  #[DataProvider('dataProviderStyle')]
  public function testStyle(string $text, array $styles, string $expected): void {
    $this->createAndSetInstance();

    $this->assertSame($expected, Prompty::style($text, ...$styles));
  }

  public static function dataProviderStyle(): \Iterator {
    yield 'bold' => ['Pear tart', ['bold'], "\033[1mPear tart" . self::RESET];
    yield 'dim' => ['Pear tart', ['dim'], "\033[2mPear tart" . self::RESET];
    yield 'italic' => ['Pear tart', ['italic'], "\033[3mPear tart" . self::RESET];
    yield 'underline' => ['Pear tart', ['underline'], "\033[4mPear tart" . self::RESET];
    yield 'green' => ['Pear tart', ['green'], "\033[32mPear tart" . self::RESET];
    yield 'yellow' => ['Pear tart', ['yellow'], "\033[33mPear tart" . self::RESET];
    yield 'blue' => ['Pear tart', ['blue'], "\033[34mPear tart" . self::RESET];
    yield 'cyan' => ['Pear tart', ['cyan'], "\033[36mPear tart" . self::RESET];
    yield 'gray' => ['Pear tart', ['gray'], "\033[90mPear tart" . self::RESET];
    yield 'dim italic' => ['Pear tart', ['dim_italic'], "\033[2;3mPear tart" . self::RESET];
    yield 'combined in the order given' => ['Pear tart', ['bold', 'green'], "\033[1m\033[32mPear tart" . self::RESET];
    yield 'no styles' => ['Pear tart', [], 'Pear tart'];
    yield 'empty text' => ['', ['bold'], ''];

    yield 'each line wrapped' => [
      "Pear tart\nPlum compote",
      ['green'],
      "\033[32mPear tart" . self::RESET . "\n\033[32mPlum compote" . self::RESET,
    ];

    yield 'empty line stays empty' => [
      "Pear tart\n\nPlum compote",
      ['bold'],
      "\033[1mPear tart" . self::RESET . "\n\n\033[1mPlum compote" . self::RESET,
    ];

    yield 'trailing newline kept' => ["Pear tart\n", ['bold'], "\033[1mPear tart" . self::RESET . "\n"];

    yield 'nested run closed by a reset' => [
      "Order \033[1m4\033[0m sent",
      ['green'],
      "\033[32mOrder \033[1m4\033[0m\033[32m sent" . self::RESET,
    ];

    yield 'nested run closed by an off parameter' => [
      "Order \033[1m4\033[22m sent",
      ['green'],
      "\033[32mOrder \033[1m4\033[22m\033[32m sent" . self::RESET,
    ];

    yield 'nested run left on is not restyled' => [
      "Order \033[4m4 sent",
      ['green'],
      "\033[32mOrder \033[4m4 sent" . self::RESET,
    ];
  }

  public function testStyleNests(): void {
    $this->createAndSetInstance();

    $styled = Prompty::style('Order ' . Prompty::style('4', 'bold') . ' sent', 'green');

    $this->assertSame("\033[32mOrder \033[1m4" . self::RESET . "\033[32m sent" . self::RESET, $styled);
  }

  #[DataProvider('dataProviderStyleAnsiOff')]
  public function testStyleAnsiOff(string $text, array $styles): void {
    $this->createAndSetInstance(['ansi' => FALSE]);

    $this->assertSame($text, Prompty::style($text, ...$styles));
  }

  public static function dataProviderStyleAnsiOff(): \Iterator {
    yield 'single style' => ['Pear tart', ['bold']];
    yield 'several styles' => ['Pear tart', ['bold', 'green', 'underline']];
    yield 'several lines' => ["Pear tart\nPlum compote", ['green']];
  }

  public function testStyleFollowsAnsiSetting(): void {
    $this->createAndSetInstance();

    Prompty::configure(ansi: FALSE);
    $this->assertSame('Pear tart', Prompty::style('Pear tart', 'bold'));

    Prompty::configure(ansi: TRUE);
    $this->assertSame("\033[1mPear tart" . self::RESET, Prompty::style('Pear tart', 'bold'));
  }

  public function testStyleUsesConfiguredColors(): void {
    $this->createAndSetInstance();
    Prompty::configure(colors: ['green' => "\033[92m"]);

    $this->assertSame("\033[92mPear tart" . self::RESET, Prompty::style('Pear tart', 'green'));
  }

  #[DataProvider('dataProviderStyleRejectsUnknownName')]
  public function testStyleRejectsUnknownName(bool $ansi, array $styles, string $name): void {
    $this->createAndSetInstance(['ansi' => $ansi]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('Style "%s" is not valid. Available styles: dim, dim_italic, cyan, green, red, gray, bold, white, yellow, blue, italic, underline.', $name));

    Prompty::style('Pear tart', ...$styles);
  }

  public static function dataProviderStyleRejectsUnknownName(): \Iterator {
    yield 'unknown' => [TRUE, ['magenta'], 'magenta'];
    yield 'reset' => [TRUE, ['reset'], 'reset'];
    yield 'wrong case' => [TRUE, ['Bold'], 'Bold'];
    yield 'after a valid name' => [TRUE, ['bold', 'sparkly'], 'sparkly'];
    yield 'ansi off' => [FALSE, ['magenta'], 'magenta'];
  }

  #[DataProvider('dataProviderStrip')]
  public function testStrip(string $text, string $expected): void {
    $this->createAndSetInstance();

    $this->assertSame($expected, Prompty::strip($text));
  }

  public static function dataProviderStrip(): \Iterator {
    yield 'plain text' => ['Pear tart', 'Pear tart'];
    yield 'color' => ["\033[32mPear tart\033[0m", 'Pear tart'];
    yield 'several parameters' => ["\033[1;4mPear tart\033[0m", 'Pear tart'];
    yield 'colon sub-parameters' => ["\033[38:5:22mPear tart\033[0m", 'Pear tart'];
    yield 'cursor sequences' => ["\033[2A\033[2KPear tart", 'Pear tart'];
    yield 'private sequences' => ["\033[?25lPear tart\033[?25h", 'Pear tart'];
    yield 'several lines' => ["\033[1mPear\033[0m\n\033[1mtart\033[0m", "Pear\ntart"];
    yield 'empty text' => ['', ''];
  }

  public function testStripRemovesStyle(): void {
    $this->createAndSetInstance();

    $this->assertSame("Pear tart\nPlum compote", Prompty::strip(Prompty::style("Pear tart\nPlum compote", 'bold', 'green')));
  }

  #[DataProvider('dataProviderWidth')]
  public function testWidth(string $text, int $expected): void {
    $this->createAndSetInstance();

    $this->assertSame($expected, Prompty::width($text));
  }

  public static function dataProviderWidth(): \Iterator {
    yield 'plain text' => ['Pear tart', 9];
    yield 'empty text' => ['', 0];
    yield 'styled text' => ["\033[1;32mPear tart\033[0m", 9];
    yield 'accented letters' => ['Crème brûlée', 12];
    yield 'combining mark' => ["Cre\u{0300}me", 5];
    yield 'wide characters' => ['梨のタルト', 10];
    yield 'emoji' => ['🍐 tart', 7];
    yield 'emoji sequence' => ['👩‍🍳 chef', 7];
    yield 'widest line' => ["Pear tart\nPlum compote\nBread", 12];
    yield 'styled lines' => ["\033[1mPear\033[0m\n\033[1mPlum compote\033[0m", 12];
  }

  public function testWidthMatchesStyledText(): void {
    $this->createAndSetInstance();

    $this->assertSame(Prompty::width('Pear tart'), Prompty::width(Prompty::style('Pear tart', 'bold', 'underline')));
  }

  public function testTerminalWidthUsesConfiguredWidth(): void {
    putenv('COLUMNS=120');
    $this->envKeys[] = 'COLUMNS';
    $this->createAndSetInstance(['width' => 0]);
    Prompty::configure(width: 52);

    $this->assertSame(52, Prompty::terminalWidth());
  }

  #[DataProvider('dataProviderTerminalWidthFromColumns')]
  public function testTerminalWidthFromColumns(string $columns, ?int $expected): void {
    putenv('COLUMNS=' . $columns);
    $this->envKeys[] = 'COLUMNS';
    $this->createAndSetInstance(['width' => 0]);

    $width = Prompty::terminalWidth();

    if ($expected === NULL) {
      $this->assertGreaterThan(0, $width);

      return;
    }

    $this->assertSame($expected, $width);
  }

  public static function dataProviderTerminalWidthFromColumns(): \Iterator {
    yield 'positive' => ['120', 120];
    yield 'surrounding spaces' => [' 64 ', 64];
    yield 'zero falls through' => ['0', NULL];
    yield 'not a number falls through' => ['wide', NULL];
  }

  public function testTerminalWidthMatchesWrapping(): void {
    $p = $this->createAndSetInstance();
    Prompty::configure(width: 40);

    $this->assertSame($this->callProtected($p, 'columns'), Prompty::terminalWidth());
  }

}
