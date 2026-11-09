<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests widgets and flows that answer without reading input.
 *
 * Every test makes keystrokes available and asserts that none is read, so a
 * widget that fell through to its interactive loop would be caught.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyNonInteractiveTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Keystrokes that would change every widget's answer if they were read.
   */
  protected const KEYSTROKES = "\x1b[B x\n";

  /**
   * Run a call with input switched off and keystrokes available.
   *
   * @param callable $call
   *   The widget or flow call under test.
   * @param bool $in_flow
   *   Whether to mark a flow as already running.
   *
   * @return array{result: mixed, output: string}
   *   The call's return value and its ANSI-stripped output.
   */
  protected function runNonInteractive(callable $call, bool $in_flow = FALSE): array {
    $stream = $this->installKeystrokes(self::KEYSTROKES, $in_flow);
    Prompty::configure(interactive: FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use ($call, &$result): void {
      $result = $call();
    });

    $this->assertSame(0, ftell($stream), 'A non-interactive call read input.');
    fclose($stream);

    return ['result' => $result, 'output' => $output];
  }

  #[DataProvider('dataProviderWidgetAnswersWithDefault')]
  public function testWidgetAnswersWithDefault(\Closure $widget, mixed $expected, string $expected_display): void {
    $r = $this->runNonInteractive($widget);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString('|  ' . $expected_display, $r['output']);
  }

  public static function dataProviderWidgetAnswersWithDefault(): \Iterator {
    yield 'text default' => [fn(): mixed => Prompty::text('Dish name', default: 'pear tart', placeholder: 'onion soup'), 'pear tart', 'pear tart'];
    yield 'text default is not trimmed' => [fn(): mixed => Prompty::text('Dish name', default: ' pear tart '), ' pear tart ', ' pear tart '];
    yield 'text empty default ignores the placeholder' => [fn(): mixed => Prompty::text('Dish name', placeholder: 'pear tart'), '', ''];
    yield 'text default is made printable' => [fn(): mixed => Prompty::text('Dish name', default: "pear\ttart"), 'pear tart', 'pear tart'];
    yield 'select default' => [fn(): mixed => Prompty::select('Course', options: self::COURSES, default: 'dessert'), 'dessert', 'Dessert'];
    yield 'select empty default takes the first option' => [fn(): mixed => Prompty::select('Course', options: self::COURSES), 'starter', 'Starter'];
    yield 'select numeric keys are returned as strings' => [fn(): mixed => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7']), '4', 'Table 4'];
    yield 'multiselect default' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['herbs', 'bread']), ['bread', 'herbs'], 'Bread, Herbs'];
    yield 'multiselect default deduplicated' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['olives', 'olives']), ['olives'], 'Olives'];
    yield 'multiselect numeric default' => [fn(): mixed => Prompty::multiselect('Seats', options: ['1' => 'Seat 1', '2' => 'Seat 2'], default: [2]), ['2'], 'Seat 2'];
    yield 'multiselect empty default selects nothing' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS), [], 'None'];
    yield 'confirm default yes' => [fn(): mixed => Prompty::confirm('Send order?'), TRUE, 'Yes'];
    yield 'confirm default no' => [fn(): mixed => Prompty::confirm('Send order?', default: FALSE), FALSE, 'No'];
  }

  #[DataProvider('dataProviderDiscoveredWinsOverDefault')]
  public function testDiscoveredWinsOverDefault(\Closure $widget, mixed $expected): void {
    $r = $this->runNonInteractive($widget);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDiscoveredWinsOverDefault(): \Iterator {
    yield 'text' => [fn(): mixed => Prompty::text('Dish name', default: 'pear tart', discovered: 'onion soup'), 'onion soup'];
    yield 'text blank discovered takes the default' => [fn(): mixed => Prompty::text('Dish name', default: 'pear tart', placeholder: 'onion soup', discovered: '  '), 'pear tart'];
    yield 'text blank discovered ignores the placeholder' => [fn(): mixed => Prompty::text('Dish name', placeholder: 'onion soup', discovered: ''), ''];
    yield 'select' => [fn(): mixed => Prompty::select('Course', options: self::COURSES, default: 'main', discovered: 'dessert'), 'dessert'];
    yield 'multiselect' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread'], discovered: 'olives,herbs'), ['olives', 'herbs']];
    yield 'multiselect blank discovered selects nothing' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread'], discovered: ''), []];
    yield 'confirm' => [fn(): mixed => Prompty::confirm('Send order?', default: TRUE, discovered: 'no'), FALSE];
  }

  #[DataProvider('dataProviderInvalidValueThrows')]
  public function testInvalidValueThrows(\Closure $widget, string $message): void {
    $stream = $this->installKeystrokes(self::KEYSTROKES, FALSE);
    Prompty::configure(interactive: FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, $widget);

    $this->assertSame('', $output);
    $this->assertSame(0, ftell($stream));
    fclose($stream);
  }

  public static function dataProviderInvalidValueThrows(): \Iterator {
    yield 'select default' => [fn(): mixed => Prompty::select('Course', options: self::COURSES, default: 'pudding'), 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'select discovered' => [fn(): mixed => Prompty::select('Course', options: self::COURSES, discovered: 'pudding'), 'Discovered value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'select without options' => [fn(): mixed => Prompty::select('Course'), 'No options declared for "Course". Provide at least one option.'];
    yield 'multiselect default' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['honey']), 'Default value "honey" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
    yield 'confirm discovered' => [fn(): mixed => Prompty::confirm('Send order?', discovered: 'maybe'), 'Discovered value "maybe" for "Send order?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.'];
  }

  #[DataProvider('dataProviderHelpIsNotDrawn')]
  public function testHelpIsNotDrawn(\Closure $widget): void {
    $r = $this->runNonInteractive($widget);

    $this->assertStringNotContainsString('Leave blank for takeaway.', $r['output']);
  }

  public static function dataProviderHelpIsNotDrawn(): \Iterator {
    yield 'text' => [fn(): mixed => Prompty::text('Guest name', help: 'Leave blank for takeaway.')];
    yield 'select' => [fn(): mixed => Prompty::select('Course', options: self::COURSES, help: 'Leave blank for takeaway.')];
    yield 'multiselect' => [fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, help: 'Leave blank for takeaway.')];
    yield 'confirm' => [fn(): mixed => Prompty::confirm('Send order?', help: 'Leave blank for takeaway.')];
  }

  public function testFlowAnswersEveryStep(): void {
    $r = $this->runNonInteractive(fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
      'course' => Prompty::select('Course', options: self::COURSES, default: 'main'),
      'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread']),
      'send' => Prompty::confirm('Send order?'),
    ], intro: 'Compose an order', outro: 'Order sent!', cancelled: 'Order cancelled.'));

    $this->assertSame(['dish' => '', 'course' => 'main', 'extras' => ['bread'], 'send' => TRUE], $r['result']);
    $this->assertStringContainsString('Compose an order', $r['output']);
    $this->assertStringContainsString('Order sent!', $r['output']);
    $this->assertStringNotContainsString('Order cancelled.', $r['output']);
  }

  public function testFlowEnvironmentWinsOverDefault(): void {
    $this->setEnvVars(['dish' => 'onion soup', 'course' => 'dessert', 'send' => 'no']);

    $r = $this->runNonInteractive(fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
      'course' => Prompty::select('Course', options: self::COURSES, default: 'main'),
      'send' => Prompty::confirm('Send order?', discovered: 'yes'),
    ]));

    $this->assertSame(['dish' => 'onion soup', 'course' => 'dessert', 'send' => TRUE], $r['result']);
  }

  public function testNestedFlowFollowsConditions(): void {
    $r = $this->runNonInteractive(fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], children: [
        'method' => Prompty::select('Method', options: ['baked' => 'Baked', 'poached' => 'Poached'], default: 'poached', condition: fn(array $r): bool => $r['course'] === 'main'),
        'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted'], condition: fn(array $r): bool => $r['course'] === 'dessert'),
      ]),
    ]));

    $this->assertSame(['course' => 'main', 'method' => 'poached'], $r['result']);
    $this->assertStringContainsString('Poached', $r['output']);
    $this->assertStringNotContainsString('Finishes', $r['output']);
  }

  #[DataProvider('dataProviderFlowArgument')]
  public function testFlowArgument(bool $global, ?bool $flow, string $keystrokes, ?array $expected, bool $global_after): void {
    $stream = $this->installKeystrokes($keystrokes, FALSE);
    Prompty::configure(interactive: $global);

    $result = NULL;
    $this->captureOutput(function () use ($flow, &$result): void {
      $result = Prompty::flow(fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES),
      ], interactive: $flow);
    });

    $this->assertSame($expected, $result);
    $this->assertSame($global_after, Prompty::config()['interactive']);
    fclose($stream);
  }

  public static function dataProviderFlowArgument(): \Iterator {
    yield 'flow switches input off' => [TRUE, FALSE, "\x1b[B\n", ['course' => 'starter'], TRUE];
    yield 'flow switches input back on' => [FALSE, TRUE, "\x1b[B\n", ['course' => 'main'], FALSE];
    yield 'flow keeps the global setting' => [FALSE, NULL, "\x1b[B\n", ['course' => 'starter'], FALSE];
    yield 'interactive flow cancels at the end of input' => [TRUE, NULL, '', NULL, TRUE];
  }

  public function testStandaloneWidgetsBetweenFlowsFollowTheGlobalSetting(): void {
    $r = $this->runNonInteractive(function (): array {
      $first = Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name', default: 'pear tart')]);
      $note = Prompty::text('Kitchen note', default: 'no nuts');
      $second = Prompty::flow(fn(): array => ['send' => Prompty::confirm('Send order?', default: FALSE)]);

      return [$first, $note, $second];
    });

    $this->assertSame([['dish' => 'pear tart'], 'no nuts', ['send' => FALSE]], $r['result']);
  }

}
