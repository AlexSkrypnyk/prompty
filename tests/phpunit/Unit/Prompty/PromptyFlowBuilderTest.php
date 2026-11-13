<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use AlexSkrypnyk\Prompty\PromptyStepInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the PromptyFlow builder and the step provider interface.
 */
#[CoversClass(Prompty::class)]
#[CoversClass(PromptyFlow::class)]
#[Group('unit')]
final class PromptyFlowBuilderTest extends PromptyTestCase {

  protected const array COURSES = ['main' => 'Main', 'dessert' => 'Dessert'];

  /**
   * Run a flow and return its answers and output.
   *
   * @param \Closure $build
   *   Receives an empty flow and returns it with its steps.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param \Closure|null $run
   *   Receives the built flow and runs it, or NULL to call run() without
   *   arguments.
   *
   * @return array{result: mixed, output: string}
   *   The answers and the ANSI-stripped output.
   */
  protected function runFlow(\Closure $build, string $keystrokes = '', ?\Closure $run = NULL): array {
    $run ??= static fn(PromptyFlow $flow): ?array => $flow->run();

    return $this->promptyRun(static function () use ($build, $run): mixed {
      $flow = $build(Prompty::flow());
      self::assertInstanceOf(PromptyFlow::class, $flow);

      return $run($flow);
    }, $keystrokes);
  }

  /**
   * Assert that the output contains each of the strings.
   *
   * @param string $output
   *   The ANSI-stripped output.
   * @param array<array-key, mixed> $strings
   *   The strings expected in the output.
   */
  protected function assertOutputContainsAll(string $output, array $strings): void {
    foreach ($strings as $string) {
      $this->assertIsString($string);
      $this->assertStringContainsString($string, $output);
    }
  }

  #[DataProvider('dataProviderWidgetMethods')]
  public function testWidgetMethods(\Closure $build, string $keystrokes, array $expected): void {
    $r = $this->runFlow($build, $keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderWidgetMethods(): \Iterator {
    yield 'text' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish name'), 'tart' . self::KEY_ENTER, ['dish' => 'tart']];
    yield 'select' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES), self::KEY_DOWN . self::KEY_ENTER, ['course' => 'dessert']];
    yield 'multiselect' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->multiselect('extras', 'Extras', options: ['bread' => 'Bread', 'olives' => 'Olives']),
      self::KEY_SPACE . self::KEY_ENTER,
      ['extras' => ['bread']],
    ];
    yield 'suggest' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->suggest('dish', 'Dish', options: ['Pear Tart']), 'pe' . self::KEY_DOWN . self::KEY_ENTER, ['dish' => 'Pear Tart']];
    yield 'confirm' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->confirm('send', 'Send order?'), self::KEY_ENTER, ['send' => TRUE]];
    yield 'pause' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->pause('ready', 'Ready?'), self::KEY_ENTER, ['ready' => TRUE]];
    yield 'task that succeeds stores TRUE' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->task('send', 'Sending', action: static fn(): string => 'sent'), '', ['send' => TRUE]];
    yield 'task that fails stores FALSE' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->task('send', 'Sending', action: static fn(): bool => FALSE), '', ['send' => FALSE]];
    yield 'task that returns NULL stores TRUE' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->task('send', 'Sending', action: static fn(): null => NULL), '', ['send' => TRUE]];
  }

  #[DataProvider('dataProviderClosureArguments')]
  public function testClosureArguments(\Closure $build, string $keystrokes, string $key, mixed $expected, array $expected_output): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $build($flow->text('dish', 'Dish name', default: 'tart')), self::KEY_ENTER . $keystrokes);

    $this->assertIsArray($r['result']);
    $this->assertSame($expected, $r['result'][$key]);
    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderClosureArguments(): \Iterator {
    $dish = static fn(array $r): string => is_string($r['dish']) ? $r['dish'] : '';

    yield 'label' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('table', static fn(array $r): string => 'Table for ' . $dish($r)), 'one' . self::KEY_ENTER, 'table', 'one', ['Table for tart']];
    yield 'default' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('note', 'Note', default: static fn(array $r): string => $dish($r) . ', warm'), self::KEY_ENTER, 'note', 'tart, warm', []];
    yield 'placeholder' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('note', 'Note', placeholder: static fn(array $r): string => 'e.g. ' . $dish($r)), self::KEY_ENTER, 'note', 'e.g. tart', []];
    yield 'description' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->confirm('warm', 'Warm?', description: static fn(array $r): string => 'Applies to ' . $dish($r)),
      self::KEY_ENTER,
      'warm',
      TRUE,
      ['Applies to tart'],
    ];
    yield 'help' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->pause('ready', 'Ready?', help: static fn(array $r): string => 'Plate the ' . $dish($r)), self::KEY_ENTER, 'ready', TRUE, ['Plate the tart']];
    yield 'options' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('method', 'Method', options: static fn(array $r): array => [$dish($r) => ucfirst($dish($r))]),
      self::KEY_ENTER,
      'method',
      'tart',
      ['Tart'],
    ];
    yield 'hints' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('method', 'Method', options: ['baked' => 'Baked'], hints: static fn(array $r): array => ['baked' => 'For the ' . $dish($r)]),
      self::KEY_ENTER,
      'method',
      'baked',
      ['For the tart'],
    ];
    yield 'multiselect default' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->multiselect('extras', 'Extras', options: ['bread' => 'Bread', 'cream' => 'Cream'], default: static fn(array $r): array => $dish($r) === 'tart' ? ['cream'] : []),
      self::KEY_ENTER,
      'extras',
      ['cream'],
      [],
    ];
    yield 'confirm default' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->confirm('warm', 'Warm?', default: static fn(array $r): bool => $dish($r) !== 'tart'), self::KEY_ENTER, 'warm', FALSE, []];
    yield 'required' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('note', 'Note', required: static fn(array $r): string => 'Name the ' . $dish($r) . ' note.'),
      self::KEY_ENTER . 'x' . self::KEY_ENTER,
      'note',
      'x',
      ['Name the tart note.'],
    ];
    yield 'discovered' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('note', 'Note', discovered: static fn(array $r): string => 'with ' . $dish($r)), '', 'note', 'with tart', ['with tart']];
    yield 'scroll' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('method', 'Method', options: ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'], scroll: static fn(array $r): int => strlen($dish($r)) - 3),
      self::KEY_ENTER,
      'method',
      'baked',
      ['2 more'],
    ];
    yield 'task label' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->task('send', static fn(array $r): string => 'Sending ' . $dish($r), action: static fn(): bool => TRUE), '', 'send', TRUE, ['Sending tart']];
    yield 'pause enter_only' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->pause('ready', 'Ready?', enter_only: static fn(array $r): bool => TRUE), 'x' . self::KEY_ENTER, 'ready', TRUE, []];
  }

  #[DataProvider('dataProviderCallableArgumentsKeepTheirMeaning')]
  public function testCallableArgumentsKeepTheirMeaning(\Closure $build, string $keystrokes, mixed $expected): void {
    $r = $this->runFlow($build, $keystrokes);

    $this->assertIsArray($r['result']);
    $this->assertSame($expected, $r['result']['step']);
  }

  public static function dataProviderCallableArgumentsKeepTheirMeaning(): \Iterator {
    yield 'transform receives the answer' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('step', 'Dish', transform: strtoupper(...)), 'tart' . self::KEY_ENTER, 'TART'];
    yield 'validate receives the answer' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('step', 'Dish', validate: static fn(string $value): ?string => $value === 'tart' ? NULL : 'Only tart.'),
      'pie' . self::KEY_ENTER . str_repeat(self::KEY_BACKSPACE, 3) . 'tart' . self::KEY_ENTER,
      'tart',
    ];
    yield 'suggest options receive the typed text' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->suggest('step', 'Dish', options: static fn(string $typed): array => [ucfirst($typed) . ' Tart']),
      'plum' . self::KEY_DOWN . self::KEY_ENTER,
      'Plum Tart',
    ];
    yield 'multiselect order compares keys' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->multiselect('step', 'Extras', options: ['bread' => 'Bread', 'olives' => 'Olives'], default: ['bread', 'olives'], order: static fn(string $a, string $b): int => strcmp($b, $a)),
      self::KEY_ENTER,
      ['olives', 'bread'],
    ];
  }

  public function testTaskHintReceivesTheActionValue(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->task('send', 'Sending', action: static fn(): string => '3 plates', hint: static fn(mixed $value): string => 'Sent ' . (is_string($value) ? $value : '')));

    $this->assertSame(['send' => TRUE], $r['result']);
    $this->assertStringContainsString('Sent 3 plates', $r['output']);
  }

  public function testClosureArgumentsRunWhenTheStepIsReached(): void {
    $calls = [];
    $label = static function (array $r) use (&$calls): string {
      $calls[] = array_keys($r);

      return 'Note';
    };

    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'tart')
      ->text('note', $label)
      ->text('skipped', $label)
        ->when(static fn(): bool => FALSE), self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'tart', 'note' => '', 'skipped' => NULL], $r['result']);
    $this->assertSame([['dish']], $calls);
  }

  public function testNesting(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: self::COURSES)
        ->children()
          ->text('method', 'Method', default: 'baked')
            ->children()
              ->confirm('rest', 'Rest it?')
            ->end()
          ->confirm('cream', 'Cream?')
        ->end()
      ->text('table', 'Table', default: '12'), str_repeat(self::KEY_ENTER, 5), static fn(PromptyFlow $flow): ?array => $flow->run(numbering: TRUE));

    $this->assertSame(['course' => 'main', 'method' => 'baked', 'rest' => TRUE, 'cream' => TRUE, 'table' => '12'], $r['result']);
    $this->assertOutputContainsAll($r['output'], ['Course (1)', 'Method (1.1)', 'Rest it? (1.1.1)', 'Cream? (1.2)', 'Table (2)']);
  }

  public function testChildrenReopenedContinueTheScope(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish', default: 'tart')
        ->children()
          ->text('first', 'First', default: 'a')
        ->end()
        ->children()
          ->text('second', 'Second', default: 'b')
        ->end(), str_repeat(self::KEY_ENTER, 3), static fn(PromptyFlow $flow): ?array => $flow->run(numbering: TRUE));

    $this->assertSame(['dish' => 'tart', 'first' => 'a', 'second' => 'b'], $r['result']);
    $this->assertOutputContainsAll($r['output'], ['First (1.1)', 'Second (1.2)']);
  }

  public function testEmptyChildrenScope(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish', default: 'tart')->children()->end(), self::KEY_ENTER);

    $this->assertSame(['dish' => 'tart'], $r['result']);
  }

  #[DataProvider('dataProviderWhen')]
  public function testWhen(\Closure $build, array $expected, array $expected_output, array $absent_output): void {
    $r = $this->runFlow($build, str_repeat(self::KEY_ENTER, 3));

    $this->assertSame($expected, $r['result']);
    $this->assertOutputContainsAll($r['output'], $expected_output);

    foreach ($absent_output as $absent) {
      $this->assertIsString($absent);
      $this->assertStringNotContainsString($absent, $r['output']);
    }
  }

  public static function dataProviderWhen(): \Iterator {
    $is_main = static fn(array $r): bool => $r['course'] === 'main';
    $is_dessert = static fn(array $r): bool => $r['course'] === 'dessert';

    yield 'step shown' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->text('method', 'Method', default: 'baked')->when($is_main),
      ['course' => 'main', 'method' => 'baked'],
      ['Method'],
      [],
    ];
    yield 'step skipped' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->text('method', 'Method')->when($is_dessert),
      ['course' => 'main', 'method' => NULL],
      [],
      ['Method'],
    ];
    yield 'section hidden' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->section('Pudding')->when($is_dessert)->text('table', 'Table', default: '1'),
      ['course' => 'main', 'table' => '1'],
      ['Table'],
      ['Pudding'],
    ];
    yield 'note hidden' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->note('Served cold.')->when($is_dessert),
      ['course' => 'main'],
      [],
      ['Served cold.'],
    ];
    yield 'note shown' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->note('Served hot.', 'warning')->when($is_main),
      ['course' => 'main'],
      ['Served hot.'],
      [],
    ];
    yield 'later when replaces the earlier one' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('course', 'Course', options: self::COURSES)->text('method', 'Method', default: 'baked')->when($is_dessert)->when($is_main),
      ['course' => 'main', 'method' => 'baked'],
      ['Method'],
      [],
    ];
  }

  public function testSkippedStepsAnswerNull(): void {
    $seen = [];
    $is_table_due = static function (array $r) use (&$seen): bool {
      $seen = $r;

      return array_key_exists('pudding', $r) && $r['pudding'] === NULL && !array_key_exists('later', $r);
    };

    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: self::COURSES)
        ->children()
          ->text('pudding', 'Pudding')
            ->when(static fn(array $r): bool => $r['course'] === 'dessert')
            ->children()
              ->text('sauce', 'Sauce')
            ->end()
        ->end()
      ->confirm('only_hidden', 'Hidden children?')
        ->children()
          ->text('never', 'Never')
            ->when(static fn(): bool => FALSE)
        ->end()
      ->text('table', 'Table', default: '1')
        ->when($is_table_due)
      ->text('later', 'Later', default: '2'), str_repeat(self::KEY_ENTER, 4));

    $expected = ['course' => 'main', 'pudding' => NULL, 'sauce' => NULL, 'only_hidden' => TRUE, 'never' => NULL, 'table' => '1', 'later' => '2'];
    $this->assertSame($expected, $r['result']);
    $this->assertSame(['course' => 'main', 'pudding' => NULL, 'sauce' => NULL, 'only_hidden' => TRUE, 'never' => NULL], $seen);
  }

  public function testIntro(): void {
    $flow = static fn(PromptyFlow $flow): PromptyFlow => $flow->intro('Compose an order')->text('dish', 'Dish', default: 'tart');

    $from_builder = $this->runFlow($flow, self::KEY_ENTER);
    $from_run = $this->runFlow($flow, self::KEY_ENTER, static fn(PromptyFlow $flow): ?array => $flow->run(intro: 'Take an order'));

    $this->assertStringContainsString('Compose an order', $from_builder['output']);
    $this->assertStringContainsString('Take an order', $from_run['output']);
    $this->assertStringNotContainsString('Compose an order', $from_run['output']);
  }

  public function testRunOptionsReachTheFlow(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish', default: 'tart'), '', static fn(PromptyFlow $flow): ?array => $flow->run(outro: 'Order sent!', numbering: 'total', interactive: FALSE));

    $this->assertSame(['dish' => 'tart'], $r['result']);
    $this->assertOutputContainsAll($r['output'], ['Dish (1/1)', 'Order sent!']);
  }

  public function testRunTwice(): void {
    $flow = Prompty::flow()->text('dish', 'Dish', default: 'tart');

    $first = $this->promptyRun(static fn(): ?array => $flow->run(), self::KEY_ENTER);
    $second = $this->promptyRun(static fn(): ?array => $flow->run(), 'lets' . self::KEY_ENTER);

    $this->assertSame(['dish' => 'tart'], $first['result']);
    $this->assertSame(['dish' => 'tartlets'], $second['result']);
  }

  public function testCancelledReturnsNull(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->text('table', 'Table'), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
  }

  /**
   * Tests that a misdeclared chain throws where it is declared.
   *
   * @param \Closure $build
   *   Receives an empty flow and declares the chain.
   * @param class-string<\Throwable> $exception
   *   The exception class the declaration throws.
   * @param string $message
   *   The start of the exception message.
   */
  #[DataProvider('dataProviderDeclarationErrors')]
  public function testDeclarationErrors(\Closure $build, string $exception, string $message): void {
    $this->expectException($exception);
    $this->expectExceptionMessage($message);

    $build(Prompty::flow());
  }

  public static function dataProviderDeclarationErrors(): \Iterator {
    yield 'empty key' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('', 'Dish'),
      \InvalidArgumentException::class,
      'A step must have a key: it names the answer in the results and the variable the answer can come from.',
    ];
    yield 'key reused in the same scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->confirm('dish', 'Dish?'),
      \InvalidArgumentException::class,
      'Step "dish" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.',
    ];
    yield 'key reused in a child scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->text('dish', 'Dish again'),
      \InvalidArgumentException::class,
      'Step "dish" appears more than once in the flow.',
    ];
    yield 'key reused by a provider' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dessert', 'Dessert')->add(new PromptyFlowBuilderTestStep()),
      \InvalidArgumentException::class,
      'Step "dessert" appears more than once in the flow.',
    ];
    yield 'blank section title' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->section('  '),
      \InvalidArgumentException::class,
      'A section must have a title: it is the heading drawn above its steps.',
    ];
    yield 'section in a child scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->section('Service'),
      \LogicException::class,
      'Section "Service" is among the children of step "dish". A section heads top-level steps, so close the children with end() first.',
    ];
    yield 'when with no step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->when(static fn(): bool => TRUE),
      \LogicException::class,
      'when() sets the condition of the step added before it, and the flow has no step yet.',
    ];
    yield 'when in an empty child scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->when(static fn(): bool => TRUE),
      \LogicException::class,
      'when() sets the condition of the step added before it, and the children of step "dish" have no step yet.',
    ];
    yield 'children with no step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->children(),
      \LogicException::class,
      'children() opens a scope under the step added before it, and the flow has no step yet.',
    ];
    yield 'children in an empty child scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->children(),
      \LogicException::class,
      'children() opens a scope under the step added before it, and the children of step "dish" have no step yet.',
    ];
    yield 'children after a section' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->section('Service')->children(),
      \LogicException::class,
      'children() follows the section "Service". Only a step takes children.',
    ];
    yield 'children after a note' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->note('Served cold.')->children(),
      \LogicException::class,
      'children() follows the note "Served cold.". Only a step takes children.',
    ];
    yield 'children after a closure note' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->note(static fn(array $r): string => 'Served cold.')->children(),
      \LogicException::class,
      'children() follows a note. Only a step takes children.',
    ];
    yield 'unnumbered with no step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->unnumbered(),
      \LogicException::class,
      'unnumbered() leaves out the number of the step added before it, and the flow has no step yet.',
    ];
    yield 'unnumbered in an empty child scope' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->unnumbered(),
      \LogicException::class,
      'unnumbered() leaves out the number of the step added before it, and the children of step "dish" have no step yet.',
    ];
    yield 'unnumbered after a section' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->section('Service')->unnumbered(),
      \LogicException::class,
      'unnumbered() follows the section "Service". Only a step has a number.',
    ];
    yield 'unnumbered after a note' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->note('Served cold.')->unnumbered(),
      \LogicException::class,
      'unnumbered() follows the note "Served cold.". Only a step has a number.',
    ];
    yield 'unnumbered after a closure note' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->note(static fn(array $r): string => 'Served cold.')->unnumbered(),
      \LogicException::class,
      'unnumbered() follows a note. Only a step has a number.',
    ];
    yield 'end with no step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->end(),
      \LogicException::class,
      'end() closes a scope opened with children(), and none is open.',
    ];
    yield 'end after a step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->text('sauce', 'Sauce')->end()->end(),
      \LogicException::class,
      'end() after step "sauce" closes a scope opened with children(), and none is open.',
    ];
  }

  /**
   * Tests that run() rejects a chain before it draws.
   *
   * @param \Closure $build
   *   Receives an empty flow and declares the chain.
   * @param class-string<\Throwable> $exception
   *   The exception class run() throws.
   * @param string $message
   *   The start of the exception message.
   */
  #[DataProvider('dataProviderRunErrors')]
  public function testRunErrors(\Closure $build, string $exception, string $message): void {
    $this->expectException($exception);
    $this->expectExceptionMessage($message);

    $this->runFlow($build);
  }

  public static function dataProviderRunErrors(): \Iterator {
    yield 'scope left open' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->text('sauce', 'Sauce')->children(),
      \LogicException::class,
      'The children of step "sauce" are still open. Close them with end() before run().',
    ];
    yield 'note type' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->note('Served cold.', 'shout')->end(),
      \InvalidArgumentException::class,
      'Note type "shout" is not valid. Use 1 of: info, success, warning, error.',
    ];
    yield 'key that cannot be exported' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish')->children()->text('side-dish', 'Side dish')->end(),
      \InvalidArgumentException::class,
      'Step "side-dish" is looked up as the environment variable "PROMPTY_SIDE-DISH", which cannot be exported.',
    ];
  }

  public function testRunErrorDrawsNothing(): void {
    $r = NULL;

    try {
      $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->intro('Compose an order')->text('dish', 'Dish')->note('Served cold.', 'shout'));
    }
    catch (\InvalidArgumentException) {
      $r = 'thrown';
    }

    $this->assertSame('thrown', $r);
    $this->expectOutputString('');
  }

  public function testProvider(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'pear tart')
      ->add(new PromptyFlowBuilderTestStep())
        ->children()
          ->confirm('cream', 'Cream?')
        ->end(), self::KEY_ENTER . self::KEY_DOWN . self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'pear tart', 'dessert' => 'compote', 'cream' => TRUE], $r['result']);
    $this->assertStringContainsString('Dessert for pear tart', $r['output']);
  }

  public function testUnnumberedProvider(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'pear tart')
      ->add(new PromptyFlowBuilderTestStep())
        ->unnumbered()
      ->confirm('cream', 'Cream?'), self::KEY_ENTER . self::KEY_DOWN . self::KEY_ENTER . self::KEY_ENTER, static fn(PromptyFlow $flow): ?array => $flow->run(numbering: 'total'));

    $this->assertSame(['dish' => 'pear tart', 'dessert' => 'compote', 'cream' => TRUE], $r['result']);
    $this->assertOutputContainsAll($r['output'], ['Dish name (1/2)', 'Cream? (2/2)']);
    $this->assertStringNotContainsString('Dessert for pear tart (', $r['output']);
  }

  public function testProviderConditionSkipsItsChildren(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name')
      ->add(new PromptyFlowBuilderTestStep())
        ->children()
          ->confirm('cream', 'Cream?')
        ->end(), self::KEY_ENTER);

    $this->assertSame(['dish' => '', 'dessert' => NULL, 'cream' => NULL], $r['result']);
  }

  public function testWhenReplacesTheProviderCondition(): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name')
      ->add(new PromptyFlowBuilderTestStep())
        ->when(static fn(): bool => TRUE), self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => '', 'dessert' => 'tart'], $r['result']);
  }

  public function testProviderIsAskedWhenReached(): void {
    $step = new PromptyFlowBuilderTestStep();

    $flow = Prompty::flow()->text('dish', 'Dish name', default: 'tart')->add($step);

    $this->assertSame([], $step->calls);

    $this->promptyRun(static fn(): ?array => $flow->run(), self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['condition', 'widget', 'arguments'], array_values(array_unique($step->calls)));
    $this->assertSame([['dish' => 'tart']], $step->results);
  }

  #[DataProvider('dataProviderProviderErrors')]
  public function testProviderErrors(PromptyFlowBuilderTestStep $step, string $message): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage($message);

    $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish', default: 'tart')->add($step), self::KEY_ENTER);
  }

  public static function dataProviderProviderErrors(): \Iterator {
    yield 'unknown widget' => [new PromptyFlowBuilderTestStep(widget: 'task'), 'Step "dessert" names the widget "task". A step provider names 1 of: text, select, multiselect, suggest, confirm.'];
    yield 'context among the arguments' => [new PromptyFlowBuilderTestStep(arguments: ['label' => 'Dessert', 'ctx' => []]), 'The arguments of step "dessert" include "ctx". The flow passes the context to the widget itself.'];
    $no_label = 'The arguments of step "dessert" have no string "label".';
    yield 'no label' => [new PromptyFlowBuilderTestStep(arguments: ['options' => ['tart' => 'Tart']]), $no_label];
    yield 'label that is not a string' => [new PromptyFlowBuilderTestStep(arguments: ['label' => 12, 'options' => ['tart' => 'Tart']]), $no_label];
  }

  #[DataProvider('dataProviderProviderWidgets')]
  public function testProviderWidgets(string $widget, array $arguments, string $keystrokes, mixed $expected): void {
    $r = $this->runFlow(static fn(PromptyFlow $flow): PromptyFlow => $flow->text('dish', 'Dish', default: 'tart')->add(new PromptyFlowBuilderTestStep($widget, $arguments)), self::KEY_ENTER . $keystrokes);

    $this->assertIsArray($r['result']);
    $this->assertSame($expected, $r['result']['dessert']);
  }

  public static function dataProviderProviderWidgets(): \Iterator {
    yield 'text' => ['text', ['label' => 'Dessert'], 'tart' . self::KEY_ENTER, 'tart'];
    yield 'select' => ['select', ['label' => 'Dessert', 'options' => ['tart' => 'Tart', 'compote' => 'Compote']], self::KEY_DOWN . self::KEY_ENTER, 'compote'];
    yield 'multiselect' => ['multiselect', ['label' => 'Dessert', 'options' => ['tart' => 'Tart']], self::KEY_SPACE . self::KEY_ENTER, ['tart']];
    yield 'suggest' => ['suggest', ['label' => 'Dessert', 'options' => ['Pear Tart']], 'pe' . self::KEY_DOWN . self::KEY_ENTER, 'Pear Tart'];
    yield 'confirm' => ['confirm', ['label' => 'Dessert?', 'default' => FALSE], self::KEY_ENTER, FALSE];
  }

}

/**
 * A step provider whose widget and arguments a test can choose.
 */
final class PromptyFlowBuilderTestStep implements PromptyStepInterface {

  /**
   * The methods the flow called, in order.
   *
   * @var list<string>
   */
  public array $calls = [];

  /**
   * The answers arguments() received.
   *
   * @var list<array<string, mixed>>
   */
  public array $results = [];

  /**
   * Constructs the step.
   *
   * @param string $widget
   *   The widget the step names.
   * @param array<array-key, mixed>|null $arguments
   *   The arguments the step returns, or NULL for a select built from the
   *   dish answer.
   */
  public function __construct(
    protected string $widget = 'select',
    protected ?array $arguments = NULL,
  ) {
  }

  public function key(): string {
    return 'dessert';
  }

  public function widget(): string {
    $this->calls[] = 'widget';

    return $this->widget;
  }

  public function arguments(array $results): array {
    $this->calls[] = 'arguments';
    $this->results[] = $results;
    $dish = is_string($results['dish'] ?? NULL) ? $results['dish'] : '';

    /** @var array<string, mixed> $arguments */
    $arguments = $this->arguments ?? ['label' => 'Dessert for ' . $dish, 'options' => ['tart' => 'Tart', 'compote' => 'Compote']];

    return $arguments;
  }

  public function condition(array $results): bool {
    $this->calls[] = 'condition';

    return $results['dish'] !== '';
  }

}
