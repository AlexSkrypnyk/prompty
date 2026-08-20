<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for what a step condition observes, and when.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyStepConditionTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * A flow whose second child is shown only when the first answered 'x'.
   *
   * The condition reads the earlier answer directly, the way a caller writes
   * it when the step it depends on always runs before it.
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

  public function testTreeStaysOpenWhenTheConditionalSiblingRenders(): void {
    $this->setEnvVars(['course' => 'yes', 'childa' => 'x', 'childb' => 'y']);

    $output = $this->captureOutput(function (): void {
      $this->runSiblingFlow();
    });

    $this->assertStringContainsString('|  |  x', $output);
    $this->assertStringContainsString('|     y', $output);
  }

  public function testTreeClosesWhenTheConditionalSiblingIsHidden(): void {
    $this->setEnvVars(['course' => 'yes', 'childa' => 'z']);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = $this->runSiblingFlow();
    });

    $this->assertSame(['course' => TRUE, 'childa' => 'z'], $result);
    $this->assertStringContainsString('|     z', $output);
    $this->assertStringNotContainsString('|  |  z', $output);
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

}
