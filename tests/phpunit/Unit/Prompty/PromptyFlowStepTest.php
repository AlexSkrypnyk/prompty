<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::step(), a flow step built from the answers so far.
 *
 * The rendering tests compare a flow of built steps with the same flow written
 * as static steps, so a built step is held to the static step's output.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowStepTest extends PromptyTestCase {

  public function testStepReturnsStepArrayWithoutBuilding(): void {
    $this->createAndSetInstance([], TRUE);

    $built = FALSE;
    $condition = fn(array $r): bool => TRUE;
    $children = ['note' => Prompty::text('Kitchen note')];

    $step = Prompty::step(function () use (&$built): mixed {
      $built = TRUE;

      return Prompty::text('Dish name');
    }, condition: $condition, children: $children);

    $this->assertFalse($built);
    $this->assertInstanceOf(\Closure::class, $step['__call']);
    $this->assertSame($condition, $step['__condition']);
    $this->assertSame($children, $step['__children']);
  }

  public function testStepDefaultsToNoConditionAndNoChildren(): void {
    $this->createAndSetInstance([], TRUE);

    $step = Prompty::step(fn(array $r): mixed => Prompty::text('Dish name'));

    $this->assertNull($step['__condition']);
    $this->assertSame([], $step['__children']);
  }

  public function testStepOutsideFlowThrows(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Prompty::step() declares a flow step, so it can only be called inside the callable passed to Prompty::flow().');

    Prompty::step(fn(array $r): mixed => Prompty::text('Dish name'));
  }

  public function testStepBuilderReceivesEarlierAnswers(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'course' => 'dessert', 'note' => 'no nuts']);

    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      // A closure rather than an arrow function, which would capture $seen by
      // value.
      $result = Prompty::flow(function () use (&$seen): array {
        return [
          'dish' => Prompty::text('Dish name'),
          'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
          'note' => Prompty::step(function (array $results) use (&$seen): mixed {
            $seen[] = $results;

            return Prompty::text('Kitchen note');
          }),
        ];
      }, unicode: FALSE);
    });

    $this->assertSame([['dish' => 'pear tart', 'course' => 'dessert']], $seen);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'note' => 'no nuts'], $result);
  }

  #[DataProvider('dataProviderStepBuildsWidgetFromEarlierAnswers')]
  public function testStepBuildsWidgetFromEarlierAnswers(array $env, \Closure $step, mixed $expected, string $expected_output): void {
    $this->setEnvVars($env);

    $result = NULL;
    $output = $this->captureOutput(function () use ($step, &$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name'),
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
        'answer' => Prompty::step($step),
      ], unicode: FALSE, interactive: FALSE);
    });

    $this->assertIsArray($result);
    $this->assertSame($expected, $result['answer']);
    $this->assertStringContainsString($expected_output, $output);
  }

  public static function dataProviderStepBuildsWidgetFromEarlierAnswers(): \Iterator {
    yield 'text default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      fn(array $r): mixed => Prompty::text('Kitchen note', default: sprintf('%s, sliced', $r['dish'])),
      'pear tart, sliced',
      "+  Kitchen note\n|  pear tart, sliced\n",
    ];

    yield 'text label' => [
      ['dish' => 'pear tart', 'course' => 'dessert', 'answer' => 'no nuts'],
      fn(array $r): mixed => Prompty::text(sprintf('Allergy note for %s', $r['dish'])),
      'no nuts',
      "+  Allergy note for pear tart\n|  no nuts\n",
    ];

    yield 'select options' => [
      ['dish' => 'pear tart', 'course' => 'dessert', 'answer' => 'glazed'],
      fn(array $r): mixed => Prompty::select('Finish', options: $r['course'] === 'dessert' ? ['glazed' => 'Glazed', 'dusted' => 'Dusted'] : ['baked' => 'Baked']),
      'glazed',
      "+  Finish\n|  Glazed\n",
    ];

    yield 'select default' => [
      ['dish' => 'pear tart', 'course' => 'main'],
      fn(array $r): mixed => Prompty::select('Method', options: ['baked' => 'Baked', 'grilled' => 'Grilled'], default: $r['course'] === 'main' ? 'grilled' : 'baked'),
      'grilled',
      "+  Method\n|  Grilled\n",
    ];

    yield 'multiselect default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      fn(array $r): mixed => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'], default: $r['course'] === 'dessert' ? ['dusted', 'piped'] : []),
      ['dusted', 'piped'],
      "+  Finishes\n|  Dusted, Piped\n",
    ];

    yield 'suggest options' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      fn(array $r): mixed => Prompty::suggest('Special', options: [sprintf('%s', $r['dish']), 'Onion Soup'], default: sprintf('%s', $r['dish'])),
      'pear tart',
      "+  Special\n|  pear tart\n",
    ];

    yield 'confirm default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      fn(array $r): mixed => Prompty::confirm('Serve warm?', default: $r['course'] === 'dessert'),
      TRUE,
      "+  Serve warm?\n|  Yes\n",
    ];

    yield 'plain closure taking the context' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      fn(array $r): \Closure => fn(array $ctx): string => sprintf('%s for %s', $r['dish'], $r['course']),
      'pear tart for dessert',
      '',
    ];
  }

  public function testStepBuildsFromTypedAnswer(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name'),
      'note' => Prompty::step(fn(array $r): mixed => Prompty::text('Kitchen note', default: sprintf('%s, sliced', $r['dish']), description: sprintf('Sent with the %s.', $r['dish']))),
    ], unicode: FALSE), 'plum compote' . self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'plum compote', 'note' => 'plum compote, sliced'], $r['result']);
    $this->assertStringContainsString('Sent with the plum compote.', $r['output']);
  }

  public function testStepResolvesFromEnv(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'note' => 'no onions']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name'),
        'note' => Prompty::step(fn(array $r): mixed => Prompty::text('Kitchen note', default: sprintf('%s, sliced', $r['dish']))),
      ], unicode: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'note' => 'no onions'], $result);
  }

  public function testStepResolvesFromCustomEnvPrefix(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'note' => 'no onions'], 'KITCHEN_');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name'),
        'note' => Prompty::step(fn(array $r): mixed => Prompty::text('Kitchen note')),
      ], unicode: FALSE, env_prefix: 'KITCHEN_');
    });

    $this->assertSame(['dish' => 'pear tart', 'note' => 'no onions'], $result);
  }

  public function testStepConditionSkipsWithoutBuilding(): void {
    $this->setEnvVars(['course' => 'main', 'send' => 'yes']);

    $built = FALSE;
    $result = NULL;
    $output = $this->captureOutput(function () use (&$built, &$result): void {
      $result = Prompty::flow(function () use (&$built): array {
        return [
          'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
          'finishes' => Prompty::step(function () use (&$built): mixed {
            $built = TRUE;

            return Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed']);
          }, condition: fn(array $r): bool => $r['course'] === 'dessert'),
          'send' => Prompty::confirm('Send order?'),
        ];
      }, numbering: TRUE, unicode: FALSE);
    });

    $this->assertFalse($built);
    $this->assertSame(['course' => 'main', 'send' => TRUE], $result);
    $this->assertStringContainsString('Send order? (2)', $output);
    $this->assertStringNotContainsString('Finishes', $output);
  }

  public function testStepConditionReceivesEarlierAnswers(): void {
    $this->setEnvVars(['course' => 'dessert', 'finishes' => 'glazed']);

    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      $result = Prompty::flow(function () use (&$seen): array {
        return [
          'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
          'finishes' => Prompty::step(fn(array $r): mixed => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed']), condition: function (array $r) use (&$seen): bool {
            $seen[] = $r;

            return $r['course'] === 'dessert';
          }),
        ];
      }, unicode: FALSE);
    });

    $this->assertContains(['course' => 'dessert'], $seen);
    $this->assertSame(['course' => 'dessert', 'finishes' => ['glazed']], $result);
  }

  #[DataProvider('dataProviderStepRendersAsStaticStep')]
  public function testStepRendersAsStaticStep(array $env, bool $numbering, \Closure $static_steps, \Closure $built_steps, array $expected): void {
    $this->setEnvVars($env);

    $static_result = NULL;
    $static_output = $this->captureOutput(function () use ($static_steps, $numbering, &$static_result): void {
      $static_result = Prompty::flow($static_steps, numbering: $numbering, unicode: FALSE);
    });

    $built_result = NULL;
    $built_output = $this->captureOutput(function () use ($built_steps, $numbering, &$built_result): void {
      $built_result = Prompty::flow($built_steps, numbering: $numbering, unicode: FALSE);
    });

    $this->assertSame($expected, $static_result);
    $this->assertSame($expected, $built_result);
    $this->assertSame($static_output, $built_output);
  }

  public static function dataProviderStepRendersAsStaticStep(): \Iterator {
    $is_main = fn(array $r): bool => $r['course'] === 'main';
    $is_dessert = fn(array $r): bool => $r['course'] === 'dessert';
    $courses = ['main' => 'Main', 'dessert' => 'Dessert'];
    $methods = ['baked' => 'Baked', 'grilled' => 'Grilled'];
    $finishes = ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'];

    $static_steps = fn(): array => [
      'course' => Prompty::select('Course', options: $courses, children: [
        'method' => Prompty::select('Method', options: $methods, condition: $is_main, children: [
          'rest' => Prompty::confirm('Rest before serving?'),
        ]),
        'finishes' => Prompty::multiselect('Finishes', options: $finishes, condition: $is_dessert),
      ]),
      'send' => Prompty::confirm('Send order?'),
    ];

    $built_steps = fn(): array => [
      'course' => Prompty::step(fn(array $r): mixed => Prompty::select('Course', options: $courses), children: [
        'method' => Prompty::step(fn(array $r): mixed => Prompty::select('Method', options: $methods), condition: $is_main, children: [
          'rest' => Prompty::step(fn(array $r): mixed => Prompty::confirm('Rest before serving?')),
        ]),
        'finishes' => Prompty::step(fn(array $r): mixed => Prompty::multiselect('Finishes', options: $finishes), condition: $is_dessert),
      ]),
      'send' => Prompty::step(fn(array $r): mixed => Prompty::confirm('Send order?')),
    ];

    yield 'main course, numbered' => [
      ['course' => 'main', 'method' => 'baked', 'rest' => 'yes', 'send' => 'yes'],
      TRUE,
      $static_steps,
      $built_steps,
      ['course' => 'main', 'method' => 'baked', 'rest' => TRUE, 'send' => TRUE],
    ];

    yield 'main course, unnumbered' => [
      ['course' => 'main', 'method' => 'grilled', 'rest' => 'no', 'send' => 'no'],
      FALSE,
      $static_steps,
      $built_steps,
      ['course' => 'main', 'method' => 'grilled', 'rest' => FALSE, 'send' => FALSE],
    ];

    yield 'dessert course, last child hidden' => [
      ['course' => 'dessert', 'finishes' => 'glazed,piped', 'send' => 'yes'],
      TRUE,
      $static_steps,
      $built_steps,
      ['course' => 'dessert', 'finishes' => ['glazed', 'piped'], 'send' => TRUE],
    ];
  }

  public function testStepCancelledReturnsNull(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow(fn(): array => [
      'dish' => Prompty::step(fn(array $r): mixed => Prompty::text('Dish name')),
    ], cancelled: 'Order cancelled.', unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('Order cancelled.', $r['output']);
  }

  #[DataProvider('dataProviderStepKeysAreChecked')]
  public function testStepKeysAreChecked(\Closure $steps, string $message): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, function () use ($steps): void {
      Prompty::flow($steps, unicode: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderStepKeysAreChecked(): \Iterator {
    yield 'duplicate key in children' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', discovered: 'pear tart'),
        'course' => Prompty::step(fn(array $r): mixed => Prompty::confirm('Add dessert?', discovered: TRUE), children: [
          'dish' => Prompty::text('Dessert', discovered: 'plum compote'),
        ]),
      ],
      'Step "dish" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.',
    ];

    yield 'child key that cannot be exported' => [
      fn(): array => [
        'course' => Prompty::step(fn(array $r): mixed => Prompty::confirm('Add dessert?', discovered: TRUE), children: [
          'with-dash' => Prompty::text('Dessert', discovered: 'plum compote'),
        ]),
      ],
      'Step "with-dash" is looked up as the environment variable "PROMPTY_WITH-DASH", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.',
    ];
  }

  #[DataProvider('dataProviderStepRejectsBuilderResult')]
  public function testStepRejectsBuilderResult(\Closure $builder, string $message): void {
    $this->setEnvVars(['dish' => 'pear tart']);

    $this->captureOutputThrows(\UnexpectedValueException::class, $message, function () use ($builder): void {
      Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name'),
        'note' => Prompty::step($builder),
      ], unicode: FALSE);
    });

    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public static function dataProviderStepRejectsBuilderResult(): \Iterator {
    $step_array = 'The builder for step "note" returned a step array. Pass condition and children to Prompty::step(), so the flow knows them before the step runs.';

    yield 'widget with condition' => [
      fn(array $r): mixed => Prompty::text('Kitchen note', condition: fn(array $r): bool => TRUE),
      $step_array,
    ];

    yield 'widget with children' => [
      fn(array $r): mixed => Prompty::text('Kitchen note', children: ['allergy' => Prompty::text('Allergy note')]),
      $step_array,
    ];

    yield 'nested step' => [
      fn(array $r): array => Prompty::step(fn(array $r): mixed => Prompty::text('Kitchen note')),
      $step_array,
    ];

    yield 'string' => [
      fn(array $r): string => 'no onions',
      'The builder for step "note" returned string. A builder returns a widget call, such as Prompty::text(...).',
    ];

    yield 'null' => [
      fn(array $r): null => NULL,
      'The builder for step "note" returned null. A builder returns a widget call, such as Prompty::text(...).',
    ];

    yield 'array that is not a step' => [
      fn(array $r): array => ['no onions'],
      'The builder for step "note" returned array. A builder returns a widget call, such as Prompty::text(...).',
    ];
  }

}
