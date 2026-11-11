<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a flow's configuration arguments last only as long as the flow.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowConfigScopeTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance(['unicode' => TRUE, 'ansi' => TRUE]);
  }

  /**
   * The steps every flow in this test runs.
   *
   * @return \Closure
   *   A callable returning a flow of 1 text step that resolves from the
   *   environment.
   */
  protected static function steps(): \Closure {
    return static fn(): PromptyFlow => Prompty::flow()->text('dish', 'Dish name');
  }

  #[DataProvider('dataProviderConfigIsRestored')]
  public function testConfigIsRestored(\Closure $run, string $key): void {
    $this->setEnvVars(['dish' => 'plum compote']);
    $before = Prompty::config();

    $this->captureOutput($run);

    $this->assertSame($before[$key], Prompty::config()[$key]);
  }

  public static function dataProviderConfigIsRestored(): \Iterator {
    $steps = self::steps();

    yield 'unicode' => [
      static function () use ($steps): void {
        $steps()->run(unicode: FALSE);
      },
      'unicode',
    ];

    yield 'ansi' => [
      static function () use ($steps): void {
        $steps()->run(ansi: FALSE);
      },
      'ansi',
    ];

    yield 'env prefix' => [
      static function () use ($steps): void {
        $steps()->run(env_prefix: 'OTHER_');
      },
      'env_prefix',
    ];

    yield 'labels' => [
      static function () use ($steps): void {
        $steps()->run(labels: ['yes' => 'Aye']);
      },
      'labels',
    ];

    yield 'truthy' => [
      static function () use ($steps): void {
        $steps()->run(truthy: ['aye']);
      },
      'truthy',
    ];

    yield 'falsy' => [
      static function () use ($steps): void {
        $steps()->run(falsy: ['nay']);
      },
      'falsy',
    ];

    yield 'spacing' => [
      static function () use ($steps): void {
        $steps()->run(spacing: ['indent' => '....']);
      },
      'spacing',
    ];

    yield 'unicode symbols' => [
      static function () use ($steps): void {
        $steps()->run(symbols_unicode: ['bar' => '!']);
      },
      'symbols_unicode',
    ];

    yield 'ascii symbols' => [
      static function () use ($steps): void {
        $steps()->run(symbols_ascii: ['bar' => '!']);
      },
      'symbols_ascii',
    ];

    yield 'colors' => [
      static function () use ($steps): void {
        $steps()->run(colors: ['cyan' => '']);
      },
      'colors',
    ];

    yield 'interactive' => [
      static function () use ($steps): void {
        $steps()->run(interactive: FALSE);
      },
      'interactive',
    ];

    yield 'scroll' => [
      static function () use ($steps): void {
        $steps()->run(scroll: 5);
      },
      'scroll',
    ];

    yield 'placeholder fallback' => [
      static function () use ($steps): void {
        $steps()->run(placeholder_fallback: FALSE);
      },
      'placeholder_fallback',
    ];

    yield 'width' => [
      static function () use ($steps): void {
        $steps()->run(width: 40);
      },
      'width',
    ];

    yield 'legend' => [
      static function () use ($steps): void {
        $steps()->run(legend: TRUE);
      },
      'legend',
    ];
  }

  public function testSecondFlowSeesTheGlobalConfiguration(): void {
    $this->setEnvVars(['dish' => 'plum compote']);
    $steps = self::steps();

    $first = $this->captureOutput(function () use ($steps): void {
      $steps()->run(spacing: ['indent' => '....'], unicode: FALSE);
    });

    $second = $this->captureOutput(function () use ($steps): void {
      $steps()->run();
    });

    $this->assertStringContainsString('....', $first);
    $this->assertStringNotContainsString('....', $second);
    $this->assertStringContainsString('│', $second);
  }

  public function testConfigIsRestoredAfterCancellation(): void {
    $this->clearEnvVars(['dish']);
    $before = Prompty::config();
    $steps = self::steps();

    $r = $this->promptyRun(fn(): mixed => $steps()->run(unicode: FALSE, env_prefix: 'OTHER_'), self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertSame($before['env_prefix'], Prompty::config()['env_prefix']);
  }

  public function testConfigIsRestoredAfterThrow(): void {
    $before = Prompty::config();

    $thrown = NULL;

    try {
      $this->captureOutput(function (): void {
        Prompty::flow()
          ->text('dish', static function (): string {
            throw new \RuntimeException('Kitchen closed.');
          })
          ->run(unicode: FALSE, env_prefix: 'OTHER_');
      });
    }
    catch (\RuntimeException $exception) {
      $thrown = $exception->getMessage();
    }

    $this->assertSame('Kitchen closed.', $thrown);
    $this->assertSame($before['env_prefix'], Prompty::config()['env_prefix']);
    $this->assertSame($before['unicode'], Prompty::config()['unicode']);
  }

  public function testConfigIsUntouchedWhenFlowArgumentIsRejected(): void {
    $before = Prompty::config();

    $thrown = NULL;

    try {
      $this->captureOutput(function (): void {
        self::steps()()->run(env_prefix: 'OTHER_', truthy: []);
      });
    }
    catch (\InvalidArgumentException $exception) {
      $thrown = $exception->getMessage();
    }

    $this->assertSame('No values declared for "truthy". Provide at least one value.', $thrown);
    $this->assertSame($before['env_prefix'], Prompty::config()['env_prefix']);
    $this->assertSame($before['truthy'], Prompty::config()['truthy']);
    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  public function testGlobalConfigureStillPersists(): void {
    Prompty::configure(env_prefix: 'KITCHEN_');
    $this->setEnvVars(['dish' => 'plum compote'], 'KITCHEN_');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = self::steps()()->run(unicode: FALSE);
    });

    $this->assertSame(['dish' => 'plum compote'], $result);
    $this->assertSame('KITCHEN_', Prompty::config()['env_prefix']);
  }

}
