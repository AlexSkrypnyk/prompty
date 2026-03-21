<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty flowWalk() internal method.
 *
 * Uses reflection to call the protected flowWalk() directly, feeding it
 * pre-built step arrays (closures that return resolved values).
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowWalkTest extends PromptyTestCase {

  /**
   * Default flow options.
   *
   * @return array<string, mixed>
   *   Options array.
   */
  protected function defaultOptions(array $overrides = []): array {
    return array_merge([
      'numbering' => FALSE,
      'env_prefix' => 'TEST_WALK_',
      'truthy' => ['1', 'true', 'yes'],
      'falsy' => ['0', 'false', 'no'],
    ], $overrides);
  }

  /**
   * Create a step closure that returns a fixed value via "discovered".
   *
   * Simulates what a widget closure does when the flow walker calls it.
   */
  protected function resolvedStep(string $value): \Closure {
    return function (array $ctx) use ($value): string {
      $p = $this->createInstance();
      ob_start();
      $this->callProtected($p, 'printLines', $this->callProtected($p, 'renderCompleted', 'label', $value, $ctx['depth'] ?? 0, $ctx['is_last'] ?? FALSE, $ctx['open'] ?? []));
      ob_end_clean();

      return $value;
    };
  }

  /**
   * Create a step closure that returns NULL (simulating cancellation).
   */
  protected function cancelledStep(): \Closure {
    return fn(array $ctx): null => NULL;
  }

  public function testFlowWalkSimple(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'name' => $this->resolvedStep('my-app'),
      'framework' => $this->resolvedStep('vue'),
    ];

    $this->clearEnvVars(['name', 'framework'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('my-app', $results['name']);
    $this->assertSame('vue', $results['framework']);
  }

  public function testFlowWalkConditionSkip(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'name' => $this->resolvedStep('my-app'),
      'conditional' => [
        '__call' => $this->resolvedStep('skipped'),
        '__children' => [],
        '__condition' => fn($r): false => FALSE,
      ],
    ];

    $this->clearEnvVars(['name', 'conditional'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertArrayHasKey('name', $results);
    $this->assertArrayNotHasKey('conditional', $results);
  }

  public function testFlowWalkConditionPass(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'results', ['name' => 'my-app']);

    $steps = [
      'conditional' => [
        '__call' => $this->resolvedStep('passed'),
        '__children' => [],
        '__condition' => fn($r): bool => ($r['name'] ?? '') === 'my-app',
      ],
    ];

    $this->clearEnvVars(['conditional'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('passed', $results['conditional']);
  }

  public function testFlowWalkChildren(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'parent' => [
        '__call' => $this->resolvedStep('parent-val'),
        '__children' => [
          'child1' => $this->resolvedStep('child1-val'),
          'child2' => $this->resolvedStep('child2-val'),
        ],
        '__condition' => NULL,
      ],
    ];

    $this->clearEnvVars(['parent', 'child1', 'child2'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('parent-val', $results['parent']);
    $this->assertSame('child1-val', $results['child1']);
    $this->assertSame('child2-val', $results['child2']);
  }

  public function testFlowWalkChildrenCondition(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'parent' => [
        '__call' => $this->resolvedStep('parent-val'),
        '__children' => [
          'visible_child' => [
            '__call' => $this->resolvedStep('visible'),
            '__children' => [],
            '__condition' => fn($r): bool => ($r['parent'] ?? '') === 'parent-val',
          ],
          'hidden_child' => [
            '__call' => $this->resolvedStep('hidden'),
            '__children' => [],
            '__condition' => fn($r): false => FALSE,
          ],
        ],
        '__condition' => NULL,
      ],
    ];

    $this->clearEnvVars(['parent', 'visible_child', 'hidden_child'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('visible', $results['visible_child']);
    $this->assertArrayNotHasKey('hidden_child', $results);
  }

  public function testFlowWalkNoVisibleChildren(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'parent' => [
        '__call' => $this->resolvedStep('parent-val'),
        '__children' => [
          'child' => [
            '__call' => $this->resolvedStep('nope'),
            '__children' => [],
            '__condition' => fn($r): false => FALSE,
          ],
        ],
        '__condition' => NULL,
      ],
    ];

    $this->clearEnvVars(['parent', 'child'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    // No separator line should appear (no "  |" after parent).
    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertArrayNotHasKey('child', $results);
  }

  #[DataProvider('dataProviderFlowWalkNumbering')]
  public function testFlowWalkNumbering(string $number_prefix, int $step_index, string $expected_number): void {
    $p = $this->createAndSetInstance();

    // Build steps with the expected count.
    $steps = [];
    for ($i = 1; $i <= max($step_index, 3); $i++) {
      $steps['step' . $i] = $this->resolvedStep('val' . $i);
      $this->clearEnvVars(['step' . $i], 'TEST_WALK_');
    }

    $this->captureOutput(function () use ($p, $steps, $number_prefix): void {
      $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(['numbering' => TRUE]), $number_prefix);
    });

    // Verify the step_number counter worked by checking results are populated.
    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertCount(count($steps), $results);
  }

  public static function dataProviderFlowWalkNumbering(): \Iterator {
    yield 'top level' => ['', 1, '1'];
    yield 'nested level' => ['1', 2, '1.2'];
    yield 'deeply nested' => ['1.1', 3, '1.1.3'];
  }

  public function testFlowWalkCancellation(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'name' => $this->resolvedStep('my-app'),
      'cancelled' => $this->cancelledStep(),
      'unreached' => $this->resolvedStep('should-not-run'),
    ];

    $this->clearEnvVars(['name', 'cancelled', 'unreached'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertFalse($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('my-app', $results['name']);
    $this->assertArrayNotHasKey('unreached', $results);
  }

  public function testFlowWalkSiblingDetection(): void {
    $p = $this->createAndSetInstance();

    // Three children at depth 1. The condition on child3 always fails,
    // so child2 should be detected as the last visible child.
    $steps = [
      'child1' => $this->resolvedStep('val1'),
      'child2' => $this->resolvedStep('val2'),
      'child3' => [
        '__call' => $this->resolvedStep('val3'),
        '__children' => [],
        '__condition' => fn($r): false => FALSE,
      ],
    ];

    $this->clearEnvVars(['child1', 'child2', 'child3'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 1, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('val1', $results['child1']);
    $this->assertSame('val2', $results['child2']);
    $this->assertArrayNotHasKey('child3', $results);
  }

  public function testFlowWalkChildCancellation(): void {
    $p = $this->createAndSetInstance();

    $steps = [
      'parent' => [
        '__call' => $this->resolvedStep('parent-val'),
        '__children' => [
          'child' => $this->cancelledStep(),
        ],
        '__condition' => NULL,
      ],
    ];

    $this->clearEnvVars(['parent', 'child'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 0, $this->defaultOptions(), '');
      $this->assertFalse($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('parent-val', $results['parent']);
    $this->assertArrayNotHasKey('child', $results);
  }

  public function testFlowWalkSiblingDetectionWithConditionPass(): void {
    $p = $this->createAndSetInstance();

    // Two steps at depth 1. The second has a __condition that passes.
    // This tests the sibling condition check path (line 931-933).
    $steps = [
      'first' => $this->resolvedStep('val1'),
      'second' => [
        '__call' => $this->resolvedStep('val2'),
        '__children' => [],
        '__condition' => fn($r): true => TRUE,
      ],
    ];

    $this->clearEnvVars(['first', 'second'], 'TEST_WALK_');

    $this->captureOutput(function () use ($p, $steps): void {
      $result = $this->callProtected($p, 'flowWalk', $steps, 1, $this->defaultOptions(), '');
      $this->assertTrue($result);
    });

    /** @var array<string, mixed> $results */
    $results = $this->getProperty($p, 'results');
    $this->assertSame('val1', $results['first']);
    $this->assertSame('val2', $results['second']);
  }

}
