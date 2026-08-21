<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for walking a flow's steps.
 *
 * Covers the keys a step may use, the results a step condition receives and
 * when it runs, and how walkFlow() traverses the step tree and settles its
 * connectors.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWalkFlowTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  // Step keys.

  /**
   * The message a rejected step produces.
   *
   * @param string $key
   *   The offending key.
   * @param string $prefix
   *   The environment prefix in force.
   *
   * @return string
   *   The expected exception message.
   */
  protected function rejection(string $key, string $prefix = 'PROMPTY_'): string {
    return sprintf('Step "%s" is looked up as the environment variable "%s", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.', $key, $prefix . strtoupper($key));
  }

  #[DataProvider('dataProviderRejectsKey')]
  public function testRejectsKey(string $key): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection($key), function () use ($key): void {
      Prompty::flow(fn(): array => [$key => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderRejectsKey(): \Iterator {
    yield 'hyphen' => ['dish-name'];
    yield 'dot' => ['dish.name'];
    yield 'space' => ['dish name'];
    yield 'bracket' => ['dish[name]'];
  }

  public function testRejectsEmptyKey(): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, 'A step must have a key: it names the answer in the results and the variable the answer can come from.', function (): void {
      Prompty::flow(fn(): array => ['' => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE);
    });
  }

  public function testRejectsChildKey(): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection('with-dash'), function (): void {
      Prompty::flow(fn(): array => [
        'course' => Prompty::confirm('Send order?', discovered: TRUE, children: [
          'with-dash' => Prompty::text('Dish name', discovered: 'plum compote'),
        ]),
      ], unicode: FALSE);
    });
  }

  #[DataProvider('dataProviderAcceptsKey')]
  public function testAcceptsKey(string $key): void {
    $this->setEnvVars([$key => 'plum compote']);

    $result = NULL;
    $this->captureOutput(function () use ($key, &$result): void {
      $result = Prompty::flow(fn(): array => [$key => Prompty::text('Dish name')], unicode: FALSE);
    });

    $this->assertSame([$key => 'plum compote'], $result);
  }

  public static function dataProviderAcceptsKey(): \Iterator {
    yield 'lowercase' => ['dish'];
    yield 'underscored' => ['dish_name'];
    yield 'mixed case' => ['dishName'];
    yield 'trailing digit' => ['dish2'];
    yield 'leading digit behind prefix' => ['2nd'];
  }

  #[DataProvider('dataProviderRejectsKeyUnderPrefix')]
  public function testRejectsKeyUnderPrefix(string $key, string $prefix): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection($key, $prefix), function () use ($key, $prefix): void {
      Prompty::flow(fn(): array => [$key => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE, env_prefix: $prefix);
    });
  }

  public static function dataProviderRejectsKeyUnderPrefix(): \Iterator {
    // An empty prefix leaves the key to start the name on its own, and a name
    // cannot start with a digit.
    yield 'digit-led key behind empty prefix' => ['2nd', ''];
    yield 'hyphenated prefix' => ['dish', 'MY-APP_'];
  }

  // Step conditions.

  /**
   * A flow whose second child is shown only when the first answer is 'x'.
   *
   * The condition reads the earlier answer directly, as a caller would when
   * the step it depends on always runs first.
   *
   * @param callable|null $spy
   *   Optional callback receiving the results the condition was given.
   *
   * @return array<string, mixed>|null
   *   The collected answers.
   */
  protected function runSiblingFlow(?callable $spy = NULL): ?array {
    return Prompty::flow(fn(): array => [
      'course' => Prompty::confirm('Send order?', children: [
        'childa' => Prompty::text('Dish name'),
        'childb' => Prompty::text('Garnish', condition: function (array $results) use ($spy): bool {
          if ($spy !== NULL) {
            $spy($results);
          }

          return $results['childa'] === 'x';
        }),
      ]),
    ], unicode: FALSE);
  }

  public function testConditionNeverSeesMissingEarlierAnswer(): void {
    $this->setEnvVars(['course' => 'yes', 'childa' => 'x', 'childb' => 'y']);

    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      $result = $this->runSiblingFlow(function (array $results) use (&$seen): void {
        $seen[] = $results;
      });
    });

    $this->assertSame(['course' => TRUE, 'childa' => 'x', 'childb' => 'y'], $result);
    $this->assertNotSame([], $seen);

    foreach ($seen as $results) {
      $this->assertArrayHasKey('childa', $results);
    }
  }

  public function testFlowCompletesWhenWarningsBecomeExceptions(): void {
    $this->setEnvVars(['course' => 'yes', 'childa' => 'x', 'childb' => 'y']);

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
      throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    $result = NULL;

    try {
      $this->captureOutput(function () use (&$result): void {
        $result = $this->runSiblingFlow();
      });
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame(['course' => TRUE, 'childa' => 'x', 'childb' => 'y'], $result);
  }

  #[DataProvider('dataProviderTreeReflectsConditionalSiblingVisibility')]
  public function testTreeReflectsConditionalSiblingVisibility(string $childa, ?string $childb, array $contains, array $not_contains): void {
    $env = ['course' => 'yes', 'childa' => $childa];
    $expected = ['course' => TRUE, 'childa' => $childa];

    if ($childb !== NULL) {
      $env['childb'] = $childb;
      $expected['childb'] = $childb;
    }

    $this->setEnvVars($env);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = $this->runSiblingFlow();
    });

    $this->assertSame($expected, $result);

    foreach ($contains as $contain) {
      $this->assertIsString($contain);
      $this->assertStringContainsString($contain, $output);
    }

    foreach ($not_contains as $not_contain) {
      $this->assertIsString($not_contain);
      $this->assertStringNotContainsString($not_contain, $output);
    }
  }

  public static function dataProviderTreeReflectsConditionalSiblingVisibility(): \Iterator {
    yield 'sibling renders and the tree stays open' => ['x', 'y', ['|  |  x', '|     y'], []];
    yield 'sibling hidden and the tree closes' => ['z', NULL, ['|     z'], ['|  |  z']];
  }

  public function testStoredResultSettlesTheConnector(): void {
    $p = $this->createAndSetInstance(['unicode' => FALSE], TRUE);
    $this->setEnvVars(['childa' => 'x', 'grandchild' => 'g']);

    // A step may be any callable, so the value the flow stores is not always
    // the one the widget inside it returned.
    $steps = [
      'childa' => [
        '__call' => function (array $ctx): string {
          $value = Prompty::text('Dish name', ctx: $ctx);

          return strtoupper(is_string($value) ? $value : '');
        },
        '__children' => [
          'grandchild' => function (array $ctx): string {
            $value = Prompty::text('Garnish', ctx: $ctx);

            return is_string($value) ? $value : '';
          },
        ],
        '__condition' => NULL,
      ],
      'childb' => [
        '__call' => fn(array $ctx): string => 'y',
        '__children' => [],
        '__condition' => fn(array $results): bool => $results['childa'] === 'X',
      ],
    ];

    $options = ['numbering' => FALSE];

    $output = $this->captureOutput(function () use ($p, $steps, $options): void {
      $this->callProtected($p, 'walkFlow', $steps, 1, $options, '');
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('X', $results['childa']);
    $this->assertSame('y', $results['childb']);

    // The stored 'X' makes the later sibling visible, so the child group is
    // drawn with the bar that continues to it.
    $this->assertStringContainsString('|  |  +  Garnish', $output);
  }

  public function testChildConditionReadingHiddenStepIsTheCallersToGuard(): void {
    $this->setEnvVars(['course' => 'yes', 'childa' => 'z', 'childc' => 'c']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'course' => Prompty::confirm('Send order?', children: [
          'childa' => Prompty::text('Dish name'),
          'childb' => Prompty::text('Garnish', condition: fn(array $results): bool => $results['childa'] === 'x'),
          'childc' => Prompty::text('Note', condition: fn(array $results): bool => ($results['childb'] ?? '') === ''),
        ]),
      ], unicode: FALSE);
    });

    $this->assertSame(['course' => TRUE, 'childa' => 'z', 'childc' => 'c'], $result);
  }

  // Tree walking and connectors.

  /**
   * Default flow options.
   *
   * @return array<string, mixed>
   *   Options array.
   */
  protected function defaultOptions(array $overrides = []): array {
    return array_merge(['numbering' => FALSE, 'env_prefix' => 'TEST_WALK_', 'truthy' => ['1', 'true', 'yes'], 'falsy' => ['0', 'false', 'no']], $overrides);
  }

  /**
   * Create a step closure that returns a fixed value via "discovered".
   *
   * Simulates what a widget closure does when the flow walker calls it.
   */
  protected function resolvedStep(string $value): \Closure {
    return function (array $ctx) use ($value): string {
      $p = $this->createInstance();
      $this->captureOutput(function () use ($p, $value, $ctx): void {
        $this->callProtected($p, 'printLines', $this->callProtected($p, 'renderCompleted', 'label', $value, $ctx['depth'] ?? 0, $ctx['open'] ?? []));
      });

      return $value;
    };
  }

  protected function cancelledStep(): \Closure {
    return fn(array $ctx): null => NULL;
  }

  /**
   * Expand a declarative spec into the steps walkFlow() consumes.
   *
   * A string leaf becomes a bare resolved step and a NULL leaf a bare
   * cancelled step. An array node maps its 'value', 'children' and
   * 'condition' onto the '__call', '__children' and '__condition' step form.
   */
  protected function buildSteps(array $spec): array {
    $steps = [];

    foreach ($spec as $key => $node) {
      if (!is_array($node)) {
        $steps[$key] = is_string($node) ? $this->resolvedStep($node) : $this->cancelledStep();

        continue;
      }

      $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
      $value = $node['value'] ?? NULL;

      $steps[$key] = [
        '__call' => is_string($value) ? $this->resolvedStep($value) : $this->cancelledStep(),
        '__children' => $this->buildSteps($children),
        '__condition' => $node['condition'] ?? NULL,
      ];
    }

    return $steps;
  }

  /**
   * Every key in a spec, children included.
   *
   * @return list<string>
   *   The keys in walk order.
   */
  protected function specKeys(array $spec): array {
    $keys = [];

    foreach ($spec as $key => $node) {
      $keys[] = (string) $key;

      if (is_array($node) && isset($node['children']) && is_array($node['children'])) {
        $keys = array_merge($keys, $this->specKeys($node['children']));
      }
    }

    return $keys;
  }

  #[DataProvider('dataProviderWalkFlow')]
  public function testWalkFlow(
    array $spec,
    array $initial_results,
    int $depth,
    bool $expected_result,
    array $expected_values,
    array $absent_keys,
    ?string $expected_output,
  ): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'results', $initial_results);

    $steps = $this->buildSteps($spec);
    $this->clearEnvVars($this->specKeys($spec), 'TEST_WALK_');

    $result = NULL;
    $output = $this->captureOutput(function () use ($p, $steps, $depth, &$result): void {
      $result = $this->callProtected($p, 'walkFlow', $steps, $depth, $this->defaultOptions(), '');
    });

    $this->assertSame($expected_result, $result);

    if ($expected_output !== NULL) {
      $this->assertSame($expected_output, $output);
    }

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');

    foreach ($expected_values as $key => $value) {
      $this->assertSame($value, $results[$key] ?? NULL);
    }

    foreach ($absent_keys as $absent_key) {
      $this->assertIsString($absent_key);
      $this->assertArrayNotHasKey($absent_key, $results);
    }
  }

  public static function dataProviderWalkFlow(): \Iterator {
    yield 'flat steps resolve in order' => [['name' => 'my-app', 'framework' => 'vue'], [], 0, TRUE, ['name' => 'my-app', 'framework' => 'vue'], [], NULL];

    yield 'failing condition skips the step' => [
      ['name' => 'my-app', 'conditional' => ['value' => 'skipped', 'condition' => fn($r): false => FALSE]],
      [],
      0,
      TRUE,
      ['name' => 'my-app'],
      ['conditional'],
      NULL,
    ];

    yield 'condition reads earlier results' => [
      ['conditional' => ['value' => 'passed', 'condition' => fn($r): bool => ($r['name'] ?? '') === 'my-app']],
      ['name' => 'my-app'],
      0,
      TRUE,
      ['conditional' => 'passed'],
      [],
      NULL,
    ];

    yield 'children resolve after the parent' => [
      ['parent' => ['value' => 'parent-val', 'children' => ['child1' => 'child1-val', 'child2' => 'child2-val']]],
      [],
      0,
      TRUE,
      ['parent' => 'parent-val', 'child1' => 'child1-val', 'child2' => 'child2-val'],
      [],
      NULL,
    ];

    // A separator line is drawn between the parent and a visible child.
    yield 'separator drawn before a visible child' => [
      [
        'parent' => [
          'value' => 'parent-val',
          'children' => [
            'visible_child' => ['value' => 'visible', 'condition' => fn($r): bool => ($r['parent'] ?? '') === 'parent-val'],
            'hidden_child' => ['value' => 'hidden', 'condition' => fn($r): false => FALSE],
          ],
        ],
      ],
      [],
      0,
      TRUE,
      ['visible_child' => 'visible'],
      ['hidden_child'],
      "|  |\n",
    ];

    // With every child hidden, no separator line is drawn at all.
    yield 'no separator when every child is hidden' => [
      ['parent' => ['value' => 'parent-val', 'children' => ['child' => ['value' => 'nope', 'condition' => fn($r): false => FALSE]]]],
      [],
      0,
      TRUE,
      ['parent' => 'parent-val'],
      ['child'],
      '',
    ];

    yield 'cancelled step stops the walk' => [
      ['name' => 'my-app', 'cancelled' => NULL, 'unreached' => 'should-not-run'],
      [],
      0,
      FALSE,
      ['name' => 'my-app'],
      ['unreached'],
      NULL,
    ];

    yield 'cancelled child stops the walk' => [
      ['parent' => ['value' => 'parent-val', 'children' => ['child' => NULL]]],
      [],
      0,
      FALSE,
      ['parent' => 'parent-val'],
      ['child'],
      NULL,
    ];

    yield 'hidden last sibling leaves the previous one as last visible' => [
      ['child1' => 'val1', 'child2' => 'val2', 'child3' => ['value' => 'val3', 'condition' => fn($r): false => FALSE]],
      [],
      1,
      TRUE,
      ['child1' => 'val1', 'child2' => 'val2'],
      ['child3'],
      NULL,
    ];

    yield 'passing condition keeps the last sibling visible' => [
      ['first' => 'val1', 'second' => ['value' => 'val2', 'condition' => fn($r): true => TRUE]],
      [],
      1,
      TRUE,
      ['first' => 'val1', 'second' => 'val2'],
      [],
      NULL,
    ];
  }

  #[DataProvider('dataProviderWalkFlowNumbering')]
  public function testWalkFlowNumbering(string $number_prefix, int $step_index, string $expected_number): void {
    $p = $this->createAndSetInstance();

    $steps = [];
    $numbers = [];
    for ($i = 1; $i <= max($step_index, 3); $i++) {
      $resolved = $this->resolvedStep('val' . $i);
      $steps['step' . $i] = function (array $ctx) use (&$numbers, $resolved): string {
        $numbers[] = $ctx['number'];

        return $resolved($ctx);
      };
      $this->clearEnvVars(['step' . $i], 'TEST_WALK_');
    }

    $this->captureOutput(function () use ($p, $steps, $number_prefix): void {
      $this->callProtected($p, 'walkFlow', $steps, 0, $this->defaultOptions(['numbering' => TRUE]), $number_prefix);
    });

    $this->assertSame($expected_number, $numbers[$step_index - 1]);

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertCount(count($steps), $results);
  }

  public static function dataProviderWalkFlowNumbering(): \Iterator {
    yield 'top level' => ['', 1, '1'];
    yield 'nested level' => ['1', 2, '1.2'];
    yield 'deeply nested' => ['1.1', 3, '1.1.3'];
  }

}
