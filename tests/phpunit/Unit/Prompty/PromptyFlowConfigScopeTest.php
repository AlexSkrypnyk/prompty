<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
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
   *   A callable returning one text step that resolves from the environment.
   */
  protected static function steps(): \Closure {
    return fn(): array => ['dish' => Prompty::text('Dish name')];
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
        Prompty::flow($steps, unicode: FALSE);
      },
      'unicode',
    ];

    yield 'ansi' => [
      static function () use ($steps): void {
        Prompty::flow($steps, ansi: FALSE);
      },
      'ansi',
    ];

    yield 'env prefix' => [
      static function () use ($steps): void {
        Prompty::flow($steps, env_prefix: 'OTHER_');
      },
      'env_prefix',
    ];

    yield 'labels' => [
      static function () use ($steps): void {
        Prompty::flow($steps, labels: ['yes' => 'Aye']);
      },
      'labels',
    ];

    yield 'truthy' => [
      static function () use ($steps): void {
        Prompty::flow($steps, truthy: ['aye']);
      },
      'truthy',
    ];

    yield 'falsy' => [
      static function () use ($steps): void {
        Prompty::flow($steps, falsy: ['nay']);
      },
      'falsy',
    ];

    yield 'spacing' => [
      static function () use ($steps): void {
        Prompty::flow($steps, spacing: ['indent' => '....']);
      },
      'spacing',
    ];

    yield 'unicode symbols' => [
      static function () use ($steps): void {
        Prompty::flow($steps, symbols_unicode: ['bar' => '!']);
      },
      'symbols_unicode',
    ];

    yield 'ascii symbols' => [
      static function () use ($steps): void {
        Prompty::flow($steps, symbols_ascii: ['bar' => '!']);
      },
      'symbols_ascii',
    ];

    yield 'colors' => [
      static function () use ($steps): void {
        Prompty::flow($steps, colors: ['cyan' => '']);
      },
      'colors',
    ];
  }

  public function testSecondFlowSeesTheGlobalConfiguration(): void {
    $this->setEnvVars(['dish' => 'plum compote']);
    $steps = self::steps();

    $first = $this->captureOutput(function () use ($steps): void {
      Prompty::flow($steps, spacing: ['indent' => '....'], unicode: FALSE);
    });

    $second = $this->captureOutput(function () use ($steps): void {
      Prompty::flow($steps);
    });

    $this->assertStringContainsString('....', $first);
    $this->assertStringNotContainsString('....', $second);
    $this->assertStringContainsString('│', $second);
  }

  public function testConfigIsRestoredAfterCancellation(): void {
    $this->clearEnvVars(['dish']);
    $before = Prompty::config();
    $steps = self::steps();

    $r = $this->promptyRun(fn(): mixed => Prompty::flow($steps, unicode: FALSE, env_prefix: 'OTHER_'), self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertSame($before['env_prefix'], Prompty::config()['env_prefix']);
  }

  public function testConfigIsRestoredAfterThrow(): void {
    $before = Prompty::config();

    $thrown = NULL;

    try {
      $this->captureOutput(function (): void {
        Prompty::flow(function (): array {
          throw new \RuntimeException('Kitchen closed.');
        }, unicode: FALSE, env_prefix: 'OTHER_');
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
        Prompty::flow(self::steps(), env_prefix: 'OTHER_', truthy: []);
      });
    }
    catch (\InvalidArgumentException $exception) {
      $thrown = $exception->getMessage();
    }

    $this->assertSame('No values declared for "truthy". Provide at least one value.', $thrown);
    $this->assertSame($before['env_prefix'], Prompty::config()['env_prefix']);
    $this->assertSame($before['truthy'], Prompty::config()['truthy']);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testGlobalConfigureStillPersists(): void {
    Prompty::configure(env_prefix: 'KITCHEN_');
    $this->setEnvVars(['dish' => 'plum compote'], 'KITCHEN_');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(self::steps(), unicode: FALSE);
    });

    $this->assertSame(['dish' => 'plum compote'], $result);
    $this->assertSame('KITCHEN_', Prompty::config()['env_prefix']);
  }

}
