<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for flow steps whose arguments are built from the answers so far.
 *
 * The rendering tests compare a flow of built steps with the same flow written
 * as static steps, so a built step is held to the static step's output.
 */
#[CoversClass(Prompty::class)]
#[CoversClass(PromptyFlow::class)]
#[Group('unit')]
final class PromptyFlowArgumentsTest extends PromptyTestCase {

  public function testArgumentReceivesEarlierAnswers(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'course' => 'dessert', 'note' => 'no nuts']);

    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      // A closure rather than an arrow function, which would capture $seen by
      // value.
      $result = Prompty::flow()
        ->text('dish', 'Dish name')
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'])
        ->text('note', function (array $results) use (&$seen): string {
          $seen[] = $results;

          return 'Kitchen note';
        })
        ->run(unicode: FALSE);
    });

    $this->assertSame([['dish' => 'pear tart', 'course' => 'dessert']], $seen);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'note' => 'no nuts'], $result);
  }

  #[DataProvider('dataProviderArgumentsBuildWidgetFromEarlierAnswers')]
  public function testArgumentsBuildWidgetFromEarlierAnswers(array $env, \Closure $build, mixed $expected, string $expected_output): void {
    $this->setEnvVars($env);

    $flow = Prompty::flow()
      ->text('dish', 'Dish name')
      ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert']);

    $result = NULL;
    $output = $this->captureOutput(function () use ($build, $flow, &$result): void {
      $result = $build($flow)->run(unicode: FALSE, interactive: FALSE);
    });

    $this->assertIsArray($result);
    $this->assertSame($expected, $result['answer']);
    $this->assertStringContainsString($expected_output, $output);
  }

  public static function dataProviderArgumentsBuildWidgetFromEarlierAnswers(): \Iterator {
    yield 'text default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('answer', 'Kitchen note', default: fn(array $r): string => sprintf('%s, sliced', $r['dish'])),
      'pear tart, sliced',
      "+  Kitchen note\n|  pear tart, sliced\n",
    ];

    yield 'text label' => [
      ['dish' => 'pear tart', 'course' => 'dessert', 'answer' => 'no nuts'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->text('answer', fn(array $r): string => sprintf('Allergy note for %s', $r['dish'])),
      'no nuts',
      "+  Allergy note for pear tart\n|  no nuts\n",
    ];

    yield 'select options' => [
      ['dish' => 'pear tart', 'course' => 'dessert', 'answer' => 'glazed'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('answer', 'Finish', options: fn(array $r): array => $r['course'] === 'dessert' ? ['glazed' => 'Glazed', 'dusted' => 'Dusted'] : ['baked' => 'Baked']),
      'glazed',
      "+  Finish\n|  Glazed\n",
    ];

    yield 'select default' => [
      ['dish' => 'pear tart', 'course' => 'main'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->select('answer', 'Method', options: ['baked' => 'Baked', 'grilled' => 'Grilled'], default: fn(array $r): string => $r['course'] === 'main' ? 'grilled' : 'baked'),
      'grilled',
      "+  Method\n|  Grilled\n",
    ];

    yield 'multiselect default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->multiselect('answer', 'Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'], default: fn(array $r): array => $r['course'] === 'dessert' ? ['dusted', 'piped'] : []),
      ['dusted', 'piped'],
      "+  Finishes\n|  Dusted, Piped\n",
    ];

    yield 'suggest default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->suggest('answer', 'Special', options: ['pear tart', 'Onion Soup'], default: fn(array $r): string => sprintf('%s', $r['dish'])),
      'pear tart',
      "+  Special\n|  pear tart\n",
    ];

    yield 'confirm default' => [
      ['dish' => 'pear tart', 'course' => 'dessert'],
      static fn(PromptyFlow $flow): PromptyFlow => $flow->confirm('answer', 'Serve warm?', default: fn(array $r): bool => $r['course'] === 'dessert'),
      TRUE,
      "+  Serve warm?\n|  Yes\n",
    ];
  }

  public function testArgumentsBuildFromTypedAnswer(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow()
      ->text('dish', 'Dish name')
      ->text('note', 'Kitchen note', default: fn(array $r): string => sprintf('%s, sliced', $r['dish']), description: fn(array $r): string => sprintf('Sent with the %s.', $r['dish']))
      ->run(unicode: FALSE), 'plum compote' . self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'plum compote', 'note' => 'plum compote, sliced'], $r['result']);
    $this->assertStringContainsString('Sent with the plum compote.', $r['output']);
  }

  public function testBuiltStepResolvesFromEnv(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'note' => 'no onions']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow()
        ->text('dish', 'Dish name')
        ->text('note', 'Kitchen note', default: fn(array $r): string => sprintf('%s, sliced', $r['dish']))
        ->run(unicode: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'note' => 'no onions'], $result);
  }

  public function testBuiltStepResolvesFromCustomEnvPrefix(): void {
    $this->setEnvVars(['dish' => 'pear tart', 'note' => 'no onions'], 'KITCHEN_');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow()
        ->text('dish', 'Dish name')
        ->text('note', fn(array $r): string => 'Kitchen note')
        ->run(unicode: FALSE, env_prefix: 'KITCHEN_');
    });

    $this->assertSame(['dish' => 'pear tart', 'note' => 'no onions'], $result);
  }

  public function testConditionSkipsWithoutBuilding(): void {
    $this->setEnvVars(['course' => 'main', 'send' => 'yes']);

    $was_built = FALSE;
    $result = NULL;
    $output = $this->captureOutput(function () use (&$was_built, &$result): void {
      $result = Prompty::flow()
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'])
        ->multiselect('finishes', function () use (&$was_built): string {
          $was_built = TRUE;

          return 'Finishes';
        }, options: ['glazed' => 'Glazed'])
          ->when(fn(array $r): bool => $r['course'] === 'dessert')
        ->confirm('send', 'Send order?')
        ->run(numbering: TRUE, unicode: FALSE);
    });

    $this->assertFalse($was_built);
    $this->assertSame(['course' => 'main', 'finishes' => NULL, 'send' => TRUE], $result);
    $this->assertStringContainsString('Send order? (2)', $output);
    $this->assertStringNotContainsString('Finishes', $output);
  }

  public function testConditionReceivesEarlierAnswers(): void {
    $this->setEnvVars(['course' => 'dessert', 'finishes' => 'glazed']);

    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      $result = Prompty::flow()
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'])
        ->multiselect('finishes', fn(array $r): string => 'Finishes', options: ['glazed' => 'Glazed'])
          ->when(function (array $r) use (&$seen): bool {
            $seen[] = $r;

            return $r['course'] === 'dessert';
          })
        ->run(unicode: FALSE);
    });

    $this->assertContains(['course' => 'dessert'], $seen);
    $this->assertSame(['course' => 'dessert', 'finishes' => ['glazed']], $result);
  }

  #[DataProvider('dataProviderBuiltStepsRenderAsStaticSteps')]
  public function testBuiltStepsRenderAsStaticSteps(array $env, bool $numbering, \Closure $static_build, \Closure $built_build, array $expected): void {
    $this->setEnvVars($env);

    $static_result = NULL;
    $static_output = $this->captureOutput(function () use ($static_build, $numbering, &$static_result): void {
      $static_result = $static_build(Prompty::flow())->run(numbering: $numbering, unicode: FALSE);
    });

    $built_result = NULL;
    $built_output = $this->captureOutput(function () use ($built_build, $numbering, &$built_result): void {
      $built_result = $built_build(Prompty::flow())->run(numbering: $numbering, unicode: FALSE);
    });

    $this->assertSame($expected, $static_result);
    $this->assertSame($expected, $built_result);
    $this->assertSame($static_output, $built_output);
  }

  public static function dataProviderBuiltStepsRenderAsStaticSteps(): \Iterator {
    $is_main = fn(array $r): bool => $r['course'] === 'main';
    $is_dessert = fn(array $r): bool => $r['course'] === 'dessert';
    $courses = ['main' => 'Main', 'dessert' => 'Dessert'];
    $methods = ['baked' => 'Baked', 'grilled' => 'Grilled'];
    $finishes = ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'];

    $static_build = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', 'Course', options: $courses)
        ->children()
          ->select('method', 'Method', options: $methods)
            ->when($is_main)
            ->children()
              ->confirm('rest', 'Rest before serving?')
            ->end()
          ->multiselect('finishes', 'Finishes', options: $finishes)
            ->when($is_dessert)
        ->end()
      ->confirm('send', 'Send order?');

    $built_build = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->select('course', fn(array $r): string => 'Course', options: fn(array $r): array => $courses)
        ->children()
          ->select('method', fn(array $r): string => 'Method', options: fn(array $r): array => $methods)
            ->when($is_main)
            ->children()
              ->confirm('rest', fn(array $r): string => 'Rest before serving?')
            ->end()
          ->multiselect('finishes', fn(array $r): string => 'Finishes', options: fn(array $r): array => $finishes)
            ->when($is_dessert)
        ->end()
      ->confirm('send', fn(array $r): string => 'Send order?');

    yield 'main course, numbered' => [
      ['course' => 'main', 'method' => 'baked', 'rest' => 'yes', 'send' => 'yes'],
      TRUE,
      $static_build,
      $built_build,
      ['course' => 'main', 'method' => 'baked', 'rest' => TRUE, 'finishes' => NULL, 'send' => TRUE],
    ];

    yield 'main course, unnumbered' => [
      ['course' => 'main', 'method' => 'grilled', 'rest' => 'no', 'send' => 'no'],
      FALSE,
      $static_build,
      $built_build,
      ['course' => 'main', 'method' => 'grilled', 'rest' => FALSE, 'finishes' => NULL, 'send' => FALSE],
    ];

    yield 'dessert course, last child hidden' => [
      ['course' => 'dessert', 'finishes' => 'glazed,piped', 'send' => 'yes'],
      TRUE,
      $static_build,
      $built_build,
      ['course' => 'dessert', 'method' => NULL, 'rest' => NULL, 'finishes' => ['glazed', 'piped'], 'send' => TRUE],
    ];
  }

  public function testBuiltStepCancelledReturnsNull(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow()
      ->text('dish', fn(array $r): string => 'Dish name')
      ->run(cancelled: 'Order cancelled.', unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('Order cancelled.', $r['output']);
  }

  #[DataProvider('dataProviderBuiltStepKeysAreChecked')]
  public function testBuiltStepKeysAreChecked(\Closure $build, string $message): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, function () use ($build): void {
      $build(Prompty::flow())->run(unicode: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderBuiltStepKeysAreChecked(): \Iterator {
    yield 'duplicate key in children' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->confirm('course', fn(array $r): string => 'Add dessert?', discovered: TRUE)
          ->children()
            ->text('dish', 'Dessert', discovered: 'plum compote')
          ->end(),
      'Step "dish" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.',
    ];

    yield 'child key that cannot be exported' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->confirm('course', fn(array $r): string => 'Add dessert?', discovered: TRUE)
          ->children()
            ->text('with-dash', 'Dessert', discovered: 'plum compote')
          ->end(),
      'Step "with-dash" is looked up as the environment variable "PROMPTY_WITH-DASH", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.',
    ];
  }

}
