<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for rendering tables.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTableTest extends PromptyTestCase {

  /**
   * Tests the rendered lines of a table.
   *
   * @param array<string, mixed> $config
   *   Configuration for the instance.
   * @param array<mixed> $rows
   *   The rows passed to table().
   * @param array<mixed> $headers
   *   The headers passed to table().
   * @param list<string> $expected
   *   The expected lines.
   */
  #[DataProvider('dataProviderRenderTable')]
  public function testRenderTable(array $config, array $rows, array $headers, array $expected): void {
    $p = $this->createInstance($config + ['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);

    $this->assertSame($expected, $this->callProtectedLines($p, 'renderTable', $rows, $headers));
  }

  public static function dataProviderRenderTable(): \Iterator {
    yield 'rows only' => [[], [['Dish', 'Pear tart'], ['Course', 'Dessert'], ['Extras', 'Bread, olives']], [], [
      '│  ┌────────┬───────────────┐',
      '│  │ Dish   │ Pear tart     │',
      '│  │ Course │ Dessert       │',
      '│  │ Extras │ Bread, olives │',
      '│  └────────┴───────────────┘',
      '│',
    ]];

    yield 'headers' => [[], [['Dish', 'Pear tart'], ['Course', 'Dessert']], ['Field', 'Answer'], [
      '│  ┌────────┬───────────┐',
      '│  │ Field  │ Answer    │',
      '│  ├────────┼───────────┤',
      '│  │ Dish   │ Pear tart │',
      '│  │ Course │ Dessert   │',
      '│  └────────┴───────────┘',
      '│',
    ]];

    yield 'headers only' => [[], [], ['Field', 'Answer'], [
      '│  ┌───────┬────────┐',
      '│  │ Field │ Answer │',
      '│  └───────┴────────┘',
      '│',
    ]];

    yield 'section rows' => [[], ['The dish', ['Dish', 'Pear tart'], 'Service', ['Table', '12']], [], [
      '│  ┌───────────────────┐',
      '│  │ The dish          │',
      '│  ├───────┬───────────┤',
      '│  │ Dish  │ Pear tart │',
      '│  ├───────┴───────────┤',
      '│  │ Service           │',
      '│  ├───────┬───────────┤',
      '│  │ Table │ 12        │',
      '│  └───────┴───────────┘',
      '│',
    ]];

    yield 'section row after headers' => [[], ['The dish', ['Dish', 'Pear tart']], ['Field', 'Answer'], [
      '│  ┌───────┬───────────┐',
      '│  │ Field │ Answer    │',
      '│  ├───────┴───────────┤',
      '│  │ The dish          │',
      '│  ├───────┬───────────┤',
      '│  │ Dish  │ Pear tart │',
      '│  └───────┴───────────┘',
      '│',
    ]];

    yield 'consecutive and last section rows' => [[], [['Dish', 'Pear tart'], 'The dish', 'Service'], [], [
      '│  ┌──────┬───────────┐',
      '│  │ Dish │ Pear tart │',
      '│  ├──────┴───────────┤',
      '│  │ The dish         │',
      '│  ├──────────────────┤',
      '│  │ Service          │',
      '│  └──────────────────┘',
      '│',
    ]];

    yield 'section rows only' => [[], ['The dish'], [], [
      '│  ┌──────────┐',
      '│  │ The dish │',
      '│  └──────────┘',
      '│',
    ]];

    yield 'section row widens the last column' => [[], ['Where the order is going', ['Dish', 'Tart']], [], [
      '│  ┌──────────────────────────┐',
      '│  │ Where the order is going │',
      '│  ├──────┬───────────────────┤',
      '│  │ Dish │ Tart              │',
      '│  └──────┴───────────────────┘',
      '│',
    ]];

    yield 'ragged rows' => [[], [['Dish', 'Pear tart', 'Dessert'], ['Extras']], [], [
      '│  ┌────────┬───────────┬─────────┐',
      '│  │ Dish   │ Pear tart │ Dessert │',
      '│  │ Extras │           │         │',
      '│  └────────┴───────────┴─────────┘',
      '│',
    ]];

    yield 'empty row' => [[], [[]], [], [
      '│  ┌──┐',
      '│  │  │',
      '│  └──┘',
      '│',
    ]];

    yield 'associative rows and numeric cells' => [[], ['first' => ['label' => 'Table', 'value' => 12], 'second' => ['Price', 4.5]], [], [
      '│  ┌───────┬─────┐',
      '│  │ Table │ 12  │',
      '│  │ Price │ 4.5 │',
      '│  └───────┴─────┘',
      '│',
    ]];

    yield 'newline in a cell' => [[], [['Extras', "Bread\nOlives"]], [], [
      '│  ┌────────┬────────┐',
      '│  │ Extras │ Bread  │',
      '│  │        │ Olives │',
      '│  └────────┴────────┘',
      '│',
    ]];

    yield 'wide characters' => [[], [['日本', 'Tart'], ['Dish', '🍐']], [], [
      '│  ┌──────┬──────┐',
      '│  │ 日本 │ Tart │',
      '│  │ Dish │ 🍐   │',
      '│  └──────┴──────┘',
      '│',
    ]];

    yield 'control characters are removed' => [[], [["Dish\033[2J", "Pear\ttart"]], [], [
      '│  ┌──────┬───────────┐',
      '│  │ Dish │ Pear tart │',
      '│  └──────┴───────────┘',
      '│',
    ]];

    yield 'widest column shrinks and wraps' => [['width' => 40], [['Kitchen note', 'No nuts and the cream on the side']], [], [
      '│  ┌──────────────┬────────────────────┐',
      '│  │ Kitchen note │ No nuts and the    │',
      '│  │              │ cream on the side  │',
      '│  └──────────────┴────────────────────┘',
      '│',
    ]];

    yield 'shrinking columns even out' => [['width' => 30], [['Kitchen note', 'No nuts and the cream on the side']], [], [
      '│  ┌────────────┬────────────┐',
      '│  │ Kitchen    │ No nuts    │',
      '│  │ note       │ and the    │',
      '│  │            │ cream on   │',
      '│  │            │ the side   │',
      '│  └────────────┴────────────┘',
      '│',
    ]];

    yield 'equally wide columns shrink from the right' => [['width' => 19], [['Bread', 'Honey']], [], [
      '│  ┌───────┬──────┐',
      '│  │ Bread │ Hone │',
      '│  │       │ y    │',
      '│  └───────┴──────┘',
      '│',
    ]];

    yield 'wrapped line keeps its indent' => [['width' => 22], [['Note', '  ring the bell']], [], [
      '│  ┌──────┬──────────┐',
      '│  │ Note │   ring   │',
      '│  │      │   the    │',
      '│  │      │   bell   │',
      '│  └──────┴──────────┘',
      '│',
    ]];

    yield 'columns shrink no further than 1' => [['width' => 5], [['Dish', 'Tart']], [], [
      '│  ┌───┬───┐',
      '│  │ D │ T │',
      '│  │ i │ a │',
      '│  │ s │ r │',
      '│  │ h │ t │',
      '│  └───┴───┘',
      '│',
    ]];

    yield 'ascii symbols' => [['unicode' => FALSE], ['The dish', ['Dish', 'Pear tart']], ['Field', 'Answer'], [
      '|  +-------+-----------+',
      '|  | Field | Answer    |',
      '|  +-------+-----------+',
      '|  | The dish          |',
      '|  +-------+-----------+',
      '|  | Dish  | Pear tart |',
      '|  +-------+-----------+',
      '|',
    ]];
  }

  public function testTableCustomSymbols(): void {
    $this->createAndSetInstance(['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);
    Prompty::configure(symbols_unicode: ['box_horizontal' => '═', 'box_vertical' => '║']);

    $output = $this->captureOutput(fn() => Prompty::table([['Dish', 'Tart']]));

    $this->assertSame(implode("\n", [
      '│  ┌══════┬══════┐',
      '│  ║ Dish ║ Tart ║',
      '│  └══════┴══════┘',
      '│',
    ]) . "\n", $output);
  }

  public function testRenderTableColors(): void {
    $p = $this->createInstance(['unicode' => TRUE, 'ansi' => TRUE, 'width' => 80]);

    $gray = "\033[90m";
    $bold = "\033[1m";
    $reset = "\033[0m";
    $gutter = $gray . '│' . $reset . '  ';
    $border = $gray . '│' . $reset;

    $this->assertSame([
      $gutter . $gray . '┌──────┬──────┐' . $reset,
      $gutter . $border . ' ' . $bold . 'A' . $reset . '    ' . $border . ' ' . $bold . 'B' . $reset . '    ' . $border,
      $gutter . $gray . '├──────┴──────┤' . $reset,
      $gutter . $border . ' ' . $bold . 'Order' . $reset . '       ' . $border,
      $gutter . $gray . '├──────┬──────┤' . $reset,
      $gutter . $border . ' Dish ' . $border . ' ' . $bold . 'Pear' . $reset . ' ' . $border,
      $gutter . $gray . '└──────┴──────┘' . $reset,
      $gray . '│' . $reset,
    ], $this->callProtectedLines($p, 'renderTable', ['Order', ['Dish', $bold . 'Pear']], ['A', 'B']));
  }

  public function testRenderTableKeepsClosedStyle(): void {
    $p = $this->createInstance(['unicode' => TRUE, 'ansi' => TRUE, 'width' => 80]);

    $lines = $this->callProtectedLines($p, 'renderTable', [["\033[32mPear\033[0m tart"]], []);

    $this->assertSame("\033[90m│\033[0m  \033[90m│\033[0m \033[32mPear\033[0m tart \033[90m│\033[0m", $lines[1]);
  }

  public function testRenderTableDropsStylesWithoutAnsi(): void {
    $p = $this->createInstance(['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);

    $lines = $this->callProtectedLines($p, 'renderTable', [["\033[1mPear\033[0m"]], []);

    $this->assertSame('│  │ Pear │', $lines[1]);
  }

  public function testRenderTableEmpty(): void {
    $p = $this->createInstance(['ansi' => FALSE]);

    $this->assertSame([], $this->callProtectedLines($p, 'renderTable', [], []));
  }

  public function testTablePrints(): void {
    $this->createAndSetInstance(['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);

    $output = $this->captureOutput(fn() => Prompty::table([['Dish', 'Pear tart']], headers: ['Field', 'Answer']));

    $this->assertSame(implode("\n", [
      '│  ┌───────┬───────────┐',
      '│  │ Field │ Answer    │',
      '│  ├───────┼───────────┤',
      '│  │ Dish  │ Pear tart │',
      '│  └───────┴───────────┘',
      '│',
    ]) . "\n", $output);
  }

  public function testTableEmptyPrintsNothing(): void {
    $this->createAndSetInstance(['ansi' => FALSE]);

    $this->assertSame('', $this->captureOutput(fn() => Prompty::table([])));
  }

  public function testTableWritesToOutputTarget(): void {
    $chunks = [];
    $this->createAndSetInstance(['unicode' => TRUE, 'ansi' => FALSE]);
    Prompty::configure(output: function (string $chunk) use (&$chunks): void {
      $chunks[] = $chunk;
    });

    $buffer = $this->captureOutput(fn() => Prompty::table([['Dish', 'Pear tart']]));

    $this->assertSame('', $buffer);
    $this->assertStringContainsString('│ Dish │ Pear tart │', implode('', $chunks));
  }

  /**
   * Tests a row or a cell that is not valid throws.
   *
   * @param array<mixed> $rows
   *   The rows passed to table().
   * @param array<mixed> $headers
   *   The headers passed to table().
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderTableInvalid')]
  public function testTableInvalid(array $rows, array $headers, string $message): void {
    $this->createAndSetInstance(['ansi' => FALSE]);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn() => Prompty::table($rows, $headers));

    $this->assertSame('', $output);
  }

  public static function dataProviderTableInvalid(): \Iterator {
    yield 'row is an integer' => [[['Dish', 'Tart'], 12], [], 'Table row 1 of type "int" is not valid. Provide a list of cells, or a string for a section row.'];

    yield 'row is null' => [[NULL], [], 'Table row 0 of type "null" is not valid. Provide a list of cells, or a string for a section row.'];

    yield 'cell is a boolean' => [[['Send', TRUE]], [], 'Table row 0 holds a cell of type "bool". A cell must be a string, an integer or a float.'];

    yield 'cell is an array' => [[['Extras', ['bread']]], [], 'Table row 0 holds a cell of type "array". A cell must be a string, an integer or a float.'];

    yield 'header cell is null' => [[], ['Field', NULL], 'Table headers holds a cell of type "null". A cell must be a string, an integer or a float.'];
  }

}
