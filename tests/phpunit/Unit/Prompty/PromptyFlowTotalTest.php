<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
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
   * @param \Closure $steps
   *   The steps callable passed to flow().
   * @param array<string, string> $env
   *   Answers supplied through environment variables.
   * @param array<string, string>|null $labels
   *   Label overrides passed to flow().
   * @param list<string> $expected
   *   The completed labels, in the order they are drawn.
   */
  #[DataProvider('dataProviderTotal')]
  public function testTotal(\Closure $steps, array $env, ?array $labels, array $expected): void {
    $this->setEnvVars($env);

    $output = $this->captureOutput(fn(): ?array => Prompty::flow($steps, numbering: 'total', labels: $labels, unicode: FALSE, interactive: FALSE));

    preg_match_all('/\+  (.+)$/m', $output, $matches);
    $this->assertSame($expected, $matches[1]);

    $this->clearEnvVars(array_keys($env));
  }

  public static function dataProviderTotal(): \Iterator {
    $order = fn(): array => [
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
      'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'main'),
      'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed'], default: ['glazed'], condition: fn(array $r): bool => $r['course'] === 'dessert'),
      'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread'], default: ['bread']),
    ];

    yield 'no conditions' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        'course' => Prompty::select('Course', options: ['main' => 'Main'], default: 'main'),
        'send' => Prompty::confirm('Send order?'),
      ],
      [],
      NULL,
      ['Dish name (1/3)', 'Course (2/3)', 'Send order? (3/3)'],
    ];

    yield 'conditional step stays out' => [$order, [], NULL, ['Dish name (1/3)', 'Course (2/3)', 'Extras (3/3)']];

    yield 'answer adds a step and earlier steps keep their total' => [$order, ['course' => 'dessert'], NULL, ['Dish name (1/3)', 'Course (2/3)', 'Finishes (3/4)', 'Extras (4/4)']];

    yield 'answer removes a step' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main'], default: 'starter'),
        'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed'], default: ['glazed'], condition: fn(array $r): bool => ($r['course'] ?? '') !== 'starter'),
        'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread'], default: ['bread']),
      ],
      [],
      NULL,
      ['Dish name (1/4)', 'Course (2/4)', 'Extras (3/3)'],
    ];

    yield 'condition that throws while counting' => [
      fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives'], default: ['bread']),
        'toast' => Prompty::confirm('Toast the bread?', condition: fn(array $r): bool => in_array('bread', $r['extras'], TRUE)),
      ],
      [],
      NULL,
      ['Extras (1/1)', 'Toast the bread? (2/2)'],
    ];

    yield 'children count under their parent' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: ['main' => 'Main'], default: 'main', children: [
          'method' => Prompty::select('Method', options: ['baked' => 'Baked'], children: [
            'temperature' => Prompty::select('Temperature', options: ['low' => 'Low']),
          ]),
          'rest' => Prompty::confirm('Rest before serving?'),
        ]),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4'], default: '4'),
      ],
      [],
      NULL,
      ['Course (1/2)', 'Method (1.1)', 'Temperature (1.1.1)', 'Rest before serving? (1.2)', 'Table (2/2)'],
    ];

    yield 'sections are not counted' => [
      fn(): array => [
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4'], default: '4'),
        'send' => Prompty::confirm('Send order?'),
      ],
      [],
      NULL,
      ['Dish name (1/3)', 'Table (2/3)', 'Send order? (3/3)'],
    ];

    yield 'custom format' => [$order, [], ['step_total' => '[%d of %d]'], ['Dish name [1 of 3]', 'Course [2 of 3]', 'Extras [3 of 3]']];

    yield 'format with the total first' => [$order, [], ['step_total' => '(%2$d steps, now %1$d)'], ['Dish name (3 steps, now 1)', 'Course (3 steps, now 2)', 'Extras (3 steps, now 3)']];
  }

  public function testTotalKeepsTheErrorHandler(): void {
    $notices = [];
    set_error_handler(function (int $severity, string $message) use (&$notices): bool {
      $notices[] = $message;

      return TRUE;
    });

    try {
      $this->captureOutput(fn(): ?array => Prompty::flow(fn(): array => [
        'course' => Prompty::select('Course', options: ['main' => 'Main'], default: 'main'),
        'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed'], condition: fn(array $r): bool => $r['course'] === 'dessert'),
      ], numbering: 'total', unicode: FALSE, interactive: FALSE));

      trigger_error('Oven ready', E_USER_NOTICE);
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame(['Oven ready'], $notices);
  }

  public function testNumberingTrueHasNoTotal(): void {
    $output = $this->captureOutput(fn(): ?array => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
    ], numbering: TRUE, unicode: FALSE, interactive: FALSE));

    $this->assertStringContainsString('+  Dish name (1)', $output);
  }

  public function testNumberingInvalid(): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Numbering "totals" for "numbering" is not valid. Use TRUE to number steps, "total" to number them with the total, or FALSE for no numbers.', fn(): ?array => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
    ], numbering: 'totals', labels: ['step_total' => '[%d of %d]'], interactive: FALSE));

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
    $output = $this->captureOutput(fn(): ?array => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
    ], numbering: 'total', labels: ['step_total' => '(next)'], unicode: FALSE, interactive: FALSE));

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
