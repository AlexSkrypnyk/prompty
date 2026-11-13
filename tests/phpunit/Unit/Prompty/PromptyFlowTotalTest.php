<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for flow numbering that shows the total of steps due.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowTotalTest extends PromptyTestCase {

  /**
   * Tests the numbered labels a flow draws with the step total.
   *
   * @param \Closure $build
   *   Adds the steps to the flow builder it receives.
   * @param array<string, string> $env
   *   Answers supplied through environment variables.
   * @param array<string, string>|null $labels
   *   Label overrides passed to run().
   * @param list<string> $expected
   *   The completed labels, in the order they are drawn.
   */
  #[DataProvider('dataProviderTotal')]
  public function testTotal(\Closure $build, array $env, ?array $labels, array $expected): void {
    $this->setEnvVars($env);

    $output = $this->captureOutput(fn(): ?array => $build(Prompty::flow())->run(numbering: 'total', labels: $labels, unicode: FALSE, interactive: FALSE));

    preg_match_all('/\+  (.+)$/m', $output, $matches);
    $this->assertSame($expected, $matches[1]);

    $this->clearEnvVars(array_keys($env));
  }

  public static function dataProviderTotal(): \Iterator {
    $order = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'pear tart')
      ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'main')
      ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed'], default: ['glazed'])
        ->when(fn(array $r): bool => $r['course'] === 'dessert')
      ->multiselect('extras', 'Extras', options: ['bread' => 'Bread'], default: ['bread']);

    yield 'no conditions' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', default: 'pear tart')
        ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
        ->confirm('send', 'Send order?'),
      [],
      NULL,
      ['Dish name (1/3)', 'Course (2/3)', 'Send order? (3/3)'],
    ];

    yield 'conditional step stays out' => [$order, [], NULL, ['Dish name (1/3)', 'Course (2/3)', 'Extras (3/3)']];

    yield 'answer adds a step and earlier steps keep their total' => [$order, ['course' => 'dessert'], NULL, ['Dish name (1/3)', 'Course (2/3)', 'Finishes (3/4)', 'Extras (4/4)']];

    yield 'answer removes a step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', default: 'pear tart')
        ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main'], default: 'starter')
        ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed'], default: ['glazed'])
          ->when(fn(array $r): bool => ($r['course'] ?? '') !== 'starter')
        ->multiselect('extras', 'Extras', options: ['bread' => 'Bread'], default: ['bread']),
      [],
      NULL,
      ['Dish name (1/4)', 'Course (2/4)', 'Extras (3/3)'],
    ];

    yield 'condition that throws while counting' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->multiselect('extras', 'Extras', options: ['bread' => 'Bread', 'olives' => 'Olives'], default: ['bread'])
        ->confirm('toast', 'Toast the bread?')
          ->when(fn(array $r): bool => in_array('bread', $r['extras'], TRUE)),
      [],
      NULL,
      ['Extras (1/1)', 'Toast the bread? (2/2)'],
    ];

    yield 'children count under their parent' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
          ->children()
            ->select('method', 'Method', options: ['baked' => 'Baked'])
              ->children()
                ->select('temperature', 'Temperature', options: ['low' => 'Low'])
              ->end()
            ->confirm('rest', 'Rest before serving?')
          ->end()
        ->select('table', 'Table', options: ['4' => 'Table 4'], default: '4'),
      [],
      NULL,
      ['Course (1/2)', 'Method (1.1)', 'Temperature (1.1.1)', 'Rest before serving? (1.2)', 'Table (2/2)'],
    ];

    yield 'sections are not counted' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4'], default: '4')
        ->confirm('send', 'Send order?'),
      [],
      NULL,
      ['Dish name (1/3)', 'Table (2/3)', 'Send order? (3/3)'],
    ];

    yield 'custom format' => [$order, [], ['step_total' => '[%d of %d]'], ['Dish name [1 of 3]', 'Course [2 of 3]', 'Extras [3 of 3]']];

    yield 'format with the total first' => [$order, [], ['step_total' => '(%2$d steps, now %1$d)'], ['Dish name (3 steps, now 1)', 'Course (3 steps, now 2)', 'Extras (3 steps, now 3)']];
  }

  /**
   * Tests that an unnumbered step takes no number and is not counted.
   *
   * @param \Closure $build
   *   Adds the steps to the flow builder it receives.
   * @param bool|string $numbering
   *   The numbering passed to run().
   * @param list<string> $expected
   *   The completed step and task labels, in the order they are drawn.
   */
  #[DataProvider('dataProviderUnnumbered')]
  public function testUnnumbered(\Closure $build, bool|string $numbering, array $expected): void {
    $output = $this->captureOutput(fn(): ?array => $build(Prompty::flow())->run(numbering: $numbering, unicode: FALSE, interactive: FALSE));

    preg_match_all('/^[| ]*[+o]  (.+)$/m', $output, $matches);
    $this->assertSame($expected, $matches[1]);
  }

  public static function dataProviderUnnumbered(): \Iterator {
    $order = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'pear tart')
      ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
      ->confirm('send', 'Send order?')
      ->task('oven', 'Heating the oven', static fn(): bool => TRUE)
        ->unnumbered()
      ->task('ticket', 'Printing the ticket', static fn(): bool => TRUE)
        ->unnumbered();

    $between = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', default: 'pear tart')
      ->task('oven', 'Heating the oven', static fn(): bool => TRUE)
        ->unnumbered()
      ->confirm('send', 'Send order?');

    $parent = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
        ->unnumbered()
        ->children()
          ->select('method', 'Method', options: ['baked' => 'Baked'])
            ->children()
              ->select('temperature', 'Temperature', options: ['low' => 'Low'])
            ->end()
        ->end()
      ->select('table', 'Table', options: ['4' => 'Table 4'], default: '4');

    $child = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
        ->children()
          ->task('oven', 'Heating the oven', static fn(): bool => TRUE)
            ->unnumbered()
          ->select('method', 'Method', options: ['baked' => 'Baked'])
        ->end()
      ->select('table', 'Table', options: ['4' => 'Table 4'], default: '4');

    $conditional = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert')
      ->confirm('dust', 'Dust with sugar?')
        ->when(fn(array $r): bool => $r['course'] === 'dessert')
        ->unnumbered()
      ->confirm('send', 'Send order?');

    yield 'total: tasks after the questions' => [$order, 'total', ['Dish name (1/3)', 'Course (2/3)', 'Send order? (3/3)', 'Heating the oven', 'Printing the ticket']];

    yield 'numbered: tasks after the questions' => [$order, TRUE, ['Dish name (1)', 'Course (2)', 'Send order? (3)', 'Heating the oven', 'Printing the ticket']];

    yield 'none: tasks after the questions' => [$order, FALSE, ['Dish name', 'Course', 'Send order?', 'Heating the oven', 'Printing the ticket']];

    yield 'total: next step continues the sequence' => [$between, 'total', ['Dish name (1/2)', 'Heating the oven', 'Send order? (2/2)']];

    yield 'numbered: next step continues the sequence' => [$between, TRUE, ['Dish name (1)', 'Heating the oven', 'Send order? (2)']];

    yield 'total: children of an unnumbered step' => [$parent, 'total', ['Course', 'Method', 'Temperature', 'Table (1/1)']];

    yield 'numbered: children of an unnumbered step' => [$parent, TRUE, ['Course', 'Method', 'Temperature', 'Table (1)']];

    yield 'total: unnumbered child' => [$child, 'total', ['Course (1/2)', 'Heating the oven', 'Method (1.1)', 'Table (2/2)']];

    yield 'numbered: unnumbered child' => [$child, TRUE, ['Course (1)', 'Heating the oven', 'Method (1.1)', 'Table (2)']];

    yield 'total: unnumbered conditional step' => [$conditional, 'total', ['Course (1/2)', 'Dust with sugar?', 'Send order? (2/2)']];
  }

  public function testTotalKeepsTheErrorHandler(): void {
    $notices = [];
    set_error_handler(function (int $severity, string $message) use (&$notices): bool {
      $notices[] = $message;

      return TRUE;
    });

    try {
      $this->captureOutput(fn(): ?array => Prompty::flow()
        ->select('course', 'Course', options: ['main' => 'Main'], default: 'main')
        ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed'])
          ->when(fn(array $r): bool => $r['course'] === 'dessert')
        ->run(numbering: 'total', unicode: FALSE, interactive: FALSE));

      trigger_error('Oven ready', E_USER_NOTICE);
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame(['Oven ready'], $notices);
  }

  public function testNumberingTrueHasNoTotal(): void {
    $output = $this->captureOutput(fn(): ?array => Prompty::flow()->text('dish', 'Dish name', default: 'pear tart')->run(numbering: TRUE, unicode: FALSE, interactive: FALSE));

    $this->assertStringContainsString('+  Dish name (1)', $output);
  }

  public function testNumberingInvalid(): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Numbering "totals" for "numbering" is not valid. Use TRUE to number steps, "total" to number them with the total, or FALSE for no numbers.', fn(): ?array => Prompty::flow()->text('dish', 'Dish name', default: 'pear tart')->run(numbering: 'totals', labels: ['step_total' => '[%d of %d]'], interactive: FALSE));

    $this->assertSame('', $output);
    $this->assertStepTotalLabel('(%d/%d)');
  }

  /**
   * Tests a step total format that sprintf() cannot fill throws.
   *
   * @param string $format
   *   The 'step_total' label.
   * @param string $reason
   *   The sprintf() error quoted in the message.
   */
  #[DataProvider('dataProviderStepTotalFormatInvalid')]
  public function testStepTotalFormatInvalid(string $format, string $reason): void {
    $this->createAndSetInstance();

    $this->captureOutputThrows(\InvalidArgumentException::class, sprintf('Format "%s" for the "step_total" label is not valid (%s). Use a format that takes the step number and the total, such as "(%%d/%%d)".', $format, $reason), fn() => Prompty::configure(labels: ['step_total' => $format]));

    $this->assertStepTotalLabel('(%d/%d)');
  }

  public static function dataProviderStepTotalFormatInvalid(): \Iterator {
    yield 'too many placeholders' => ['(%d/%d/%d)', '4 arguments are required, 3 given'];

    yield 'unknown conversion' => ['(%d of %y)', 'Unknown format specifier "y"'];
  }

  public function testStepTotalFormatWithoutPlaceholders(): void {
    $output = $this->captureOutput(fn(): ?array => Prompty::flow()->text('dish', 'Dish name', default: 'pear tart')->run(numbering: 'total', labels: ['step_total' => '(next)'], unicode: FALSE, interactive: FALSE));

    $this->assertStringContainsString('+  Dish name (next)', $output);
  }

  /**
   * Asserts the configured 'step_total' label.
   *
   * @param string $expected
   *   The expected format.
   */
  protected function assertStepTotalLabel(string $expected): void {
    $labels = Prompty::config()['labels'];

    $this->assertIsArray($labels);
    $this->assertSame($expected, $labels['step_total'] ?? NULL);
  }

}
