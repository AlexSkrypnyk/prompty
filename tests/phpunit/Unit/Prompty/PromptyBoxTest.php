<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for rendering boxes.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyBoxTest extends PromptyTestCase {

  /**
   * Tests the rendered lines of a box.
   *
   * @param array<string, mixed> $config
   *   Configuration for the instance.
   * @param string $content
   *   The content passed to box().
   * @param string $title
   *   The title passed to box().
   * @param list<string> $expected
   *   The expected lines.
   */
  #[DataProvider('dataProviderRenderBox')]
  public function testRenderBox(array $config, string $content, string $title, array $expected): void {
    $p = $this->createInstance($config + ['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);

    $this->assertSame($expected, $this->callProtectedLines($p, 'renderBox', $content, $title));
  }

  public static function dataProviderRenderBox(): \Iterator {
    yield 'title and content' => [[], "Plate the tart, then:\n  ring the bell\n  call the table", 'Order ready', [
      '│  ┌───────────────────────┐',
      '│  │ Order ready           │',
      '│  │ ───────────           │',
      '│  │ Plate the tart, then: │',
      '│  │   ring the bell       │',
      '│  │   call the table      │',
      '│  └───────────────────────┘',
      '│',
    ]];

    yield 'no title' => [[], 'Passes the order to the kitchen.', '', [
      '│  ┌──────────────────────────────────┐',
      '│  │ Passes the order to the kitchen. │',
      '│  └──────────────────────────────────┘',
      '│',
    ]];

    yield 'title wider than content' => [[], 'Ring the bell', 'Order ready for table 12', [
      '│  ┌──────────────────────────┐',
      '│  │ Order ready for table 12 │',
      '│  │ ──────────────────────── │',
      '│  │ Ring the bell            │',
      '│  └──────────────────────────┘',
      '│',
    ]];

    yield 'empty content' => [[], '', '', [
      '│  ┌───┐',
      '│  │   │',
      '│  └───┘',
      '│',
    ]];

    yield 'title only' => [[], '', 'Order ready', [
      '│  ┌─────────────┐',
      '│  │ Order ready │',
      '│  │ ─────────── │',
      '│  │             │',
      '│  └─────────────┘',
      '│',
    ]];

    yield 'wide characters' => [[], "日本\n🍐 tart", '', [
      '│  ┌─────────┐',
      '│  │ 日本    │',
      '│  │ 🍐 tart │',
      '│  └─────────┘',
      '│',
    ]];

    yield 'content wraps and keeps its indent' => [['width' => 20], "Then:\n  ring the bell twice", '', [
      '│  ┌───────────────┐',
      '│  │ Then:         │',
      '│  │   ring the    │',
      '│  │   bell twice  │',
      '│  └───────────────┘',
      '│',
    ]];

    yield 'title wraps and the underline fits it' => [['width' => 20], 'Ring', 'Order ready for table 12', [
      '│  ┌───────────────┐',
      '│  │ Order ready   │',
      '│  │ for table 12  │',
      '│  │ ────────────  │',
      '│  │ Ring          │',
      '│  └───────────────┘',
      '│',
    ]];

    yield 'indent as wide as the box is dropped' => [['width' => 10], '      ring', '', [
      '│  ┌─────┐',
      '│  │     │',
      '│  │     │',
      '│  │ rin │',
      '│  │ g   │',
      '│  └─────┘',
      '│',
    ]];

    yield 'ascii symbols' => [['unicode' => FALSE], 'Ring the bell', 'Order ready', [
      '|  +---------------+',
      '|  | Order ready   |',
      '|  | -----------   |',
      '|  | Ring the bell |',
      '|  +---------------+',
      '|',
    ]];
  }

  public function testRenderBoxColors(): void {
    $p = $this->createInstance(['unicode' => TRUE, 'ansi' => TRUE, 'width' => 80]);

    $gray = "\033[90m";
    $bold = "\033[1m";
    $green = "\033[32m";
    $reset = "\033[0m";
    $gutter = $gray . '│' . $reset . '  ';
    $border = $gray . '│' . $reset;

    $this->assertSame([
      $gutter . $gray . '┌───────┐' . $reset,
      $gutter . $border . ' ' . $bold . 'Ready' . $reset . ' ' . $border,
      $gutter . $border . ' ' . $gray . '─────' . $reset . ' ' . $border,
      $gutter . $border . ' ' . $green . 'Ring' . $reset . '  ' . $border,
      $gutter . $gray . '└───────┘' . $reset,
      $gray . '│' . $reset,
    ], $this->callProtectedLines($p, 'renderBox', $green . 'Ring', 'Ready'));
  }

  public function testBoxPrints(): void {
    $this->createAndSetInstance(['unicode' => TRUE, 'ansi' => FALSE, 'width' => 80]);

    $output = $this->captureOutput(fn() => Prompty::box('Ring the bell', title: 'Order ready'));

    $this->assertSame(implode("\n", [
      '│  ┌───────────────┐',
      '│  │ Order ready   │',
      '│  │ ───────────   │',
      '│  │ Ring the bell │',
      '│  └───────────────┘',
      '│',
    ]) . "\n", $output);
  }

}
