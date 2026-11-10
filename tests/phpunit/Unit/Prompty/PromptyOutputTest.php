<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the output target set through configure() and flow().
 *
 * Every write must reach the configured stream or callable byte for byte as
 * it would have been echoed, and nothing may reach the output buffer.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyOutputTest extends PromptyTestCase {

  public function testDefaultTargetIsEcho(): void {
    $this->createAndSetInstance();

    $this->assertNull(Prompty::config()['output']);
    $this->assertStringContainsString('Kitchen order', $this->captureOutput(fn() => Prompty::intro('Kitchen order')));
  }

  /**
   * Tests a stream target receives exactly what echo would have printed.
   *
   * @param \Closure $call
   *   The Prompty call that writes output.
   * @param string $keystrokes
   *   Raw keystroke bytes fed to an interactive widget.
   * @param string $expected
   *   Text the output must contain once ANSI codes are stripped.
   */
  #[DataProvider('dataProviderStreamTarget')]
  public function testStreamTarget(\Closure $call, string $keystrokes, string $expected): void {
    $echoed = $this->render($call, $keystrokes);

    $stream = fopen('php://memory', 'w+');
    $this->assertIsResource($stream);

    $buffer = $this->render($call, $keystrokes, $stream);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    $this->assertSame('', $buffer);
    $this->assertSame($echoed, $written);
    $this->assertStringContainsString($expected, $this->stripAnsi($echoed));
  }

  /**
   * Tests a callable target receives exactly what echo would have printed.
   *
   * @param \Closure $call
   *   The Prompty call that writes output.
   * @param string $keystrokes
   *   Raw keystroke bytes fed to an interactive widget.
   * @param string $expected
   *   Text the output must contain once ANSI codes are stripped.
   */
  #[DataProvider('dataProviderCallableTarget')]
  public function testCallableTarget(\Closure $call, string $keystrokes, string $expected): void {
    $echoed = $this->render($call, $keystrokes);

    $chunks = [];
    $buffer = $this->render($call, $keystrokes, function (string $chunk) use (&$chunks): void {
      $chunks[] = $chunk;
    });

    $this->assertSame('', $buffer);
    $this->assertNotEmpty($chunks);
    $this->assertSame($echoed, implode('', $chunks));
    $this->assertStringContainsString($expected, $this->stripAnsi($echoed));
  }

  public static function dataProviderCallableTarget(): \Iterator {
    return self::dataProviderStreamTarget();
  }

  public static function dataProviderStreamTarget(): \Iterator {
    yield 'intro' => [
      static function (): void {
        Prompty::intro('Kitchen order');
      },
      '',
      'Kitchen order',
    ];

    yield 'outro' => [
      static function (): void {
        Prompty::outro('Order sent to the kitchen!');
      },
      '',
      'Order sent to the kitchen!',
    ];

    yield 'output' => [fn(): mixed => Prompty::output(['Table 4', 'Seat 2']), '', 'Table 4' . PHP_EOL . 'Seat 2'];

    yield 'text widget redraws' => [fn(): mixed => Prompty::text('Dish name'), 'Pear' . self::KEY_ENTER, 'Pear'];

    yield 'select widget redraws' => [
      fn(): mixed => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
      self::KEY_DOWN . self::KEY_ENTER,
      'Main',
    ];

    yield 'flow banners and steps' => [
      fn(): mixed => Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], intro: 'Kitchen order', outro: 'Order sent to the kitchen!'),
      'Onion Soup' . self::KEY_ENTER,
      'Onion Soup',
    ];

    yield 'flow cancellation' => [
      fn(): mixed => Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], cancelled: 'Order cancelled.'),
      self::KEY_CTRL_C,
      'Order cancelled.',
    ];
  }

  public function testRedrawSequenceReachesTarget(): void {
    $chunks = [];
    $this->render(fn(): mixed => Prompty::text('Dish name'), 'P' . self::KEY_ENTER, function (string $chunk) use (&$chunks): void {
      $chunks[] = $chunk;
    });

    $redraws = array_filter($chunks, fn(string $chunk): bool => preg_match('/^\033\[\d+A\r\033\[J$/', $chunk) === 1);
    $this->assertNotEmpty($redraws);
  }

  /**
   * Tests every callable form is stored as a closure and receives output.
   *
   * @param \Closure $make
   *   Builds the callable and returns it with a reader for what it collected.
   */
  #[DataProvider('dataProviderCallableForms')]
  public function testCallableForms(\Closure $make): void {
    $this->createAndSetInstance();
    [$callable, $read] = $make();

    Prompty::configure(output: $callable);

    $this->assertInstanceOf(\Closure::class, Prompty::config()['output']);
    $this->assertSame('', $this->captureOutput(fn(): int => Prompty::output(['Table 7'])));
    $this->assertSame('Table 7' . PHP_EOL, $read());
  }

  public static function dataProviderCallableForms(): \Iterator {
    yield 'invokable object' => [
      static function (): array {
        $collector = new class() {

          /**
           * Chunks received so far.
           */
          public string $written = '';

          public function __invoke(string $chunk): void {
            $this->written .= $chunk;
          }

        };

        return [
          $collector,
          fn(): string => $collector->written,
        ];
      },
    ];

    yield 'array callable' => [
      static function (): array {
        $collector = new class() {

          /**
           * Chunks received so far.
           */
          public string $written = '';

          public function collect(string $chunk): void {
            $this->written .= $chunk;
          }

        };

        // A literal method name would be rewritten to a first-class callable,
        // which is already a closure.
        $method = 'collect';

        return [
          [$collector, $method],
          fn(): string => $collector->written,
        ];
      },
    ];
  }

  public function testConfigureReplacesTarget(): void {
    $this->createAndSetInstance();

    $first = '';
    $second = '';
    Prompty::configure(output: function (string $chunk) use (&$first): void {
      $first .= $chunk;
    });
    Prompty::output(['Table 4']);

    Prompty::configure(output: function (string $chunk) use (&$second): void {
      $second .= $chunk;
    });
    Prompty::output(['Table 12']);

    $this->assertSame('Table 4' . PHP_EOL, $first);
    $this->assertSame('Table 12' . PHP_EOL, $second);
  }

  public function testConfigureNullKeepsTarget(): void {
    $this->createAndSetInstance();

    $written = '';
    Prompty::configure(output: function (string $chunk) use (&$written): void {
      $written .= $chunk;
    });
    Prompty::configure();
    Prompty::output(['Table 4']);

    $this->assertSame('Table 4' . PHP_EOL, $written);
  }

  /**
   * Tests configure() rejects a target that cannot receive output.
   *
   * @param \Closure $make
   *   Builds the rejected value.
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderRejectsInvalidTarget')]
  public function testRejectsInvalidTarget(\Closure $make, string $message): void {
    $this->createAndSetInstance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    Prompty::configure(output: $make());
  }

  public static function dataProviderRejectsInvalidTarget(): \Iterator {
    $invalid = 'Output target of type "%s" for "output" is not valid. Provide a writable stream or a callable that receives each chunk.';

    yield 'string that is not a function' => [fn(): string => 'php://stderr', sprintf($invalid, 'string')];

    yield 'integer' => [fn(): int => 2, sprintf($invalid, 'int')];

    yield 'array that is not a callable' => [fn(): array => ['Table 4'], sprintf($invalid, 'array')];

    yield 'boolean' => [fn(): bool => FALSE, sprintf($invalid, 'bool')];

    yield 'closed stream' => [
      static function () {
        $stream = fopen('php://memory', 'w+');

        if ($stream !== FALSE) {
          fclose($stream);
        }

        return $stream;
      },
      sprintf($invalid, 'resource (closed)'),
    ];

    yield 'stream context' => [stream_context_create(...), sprintf($invalid, 'resource (stream-context)')];

    yield 'read-only stream' => [fn() => fopen(__FILE__, 'r'), 'Output stream for "output" is opened in read-only mode "r". Provide a writable stream.'];

    yield 'read-only binary stream' => [fn() => fopen(__FILE__, 'rb'), 'Output stream for "output" is opened in read-only mode "rb". Provide a writable stream.'];
  }

  /**
   * Tests configure() accepts streams in every writable mode.
   *
   * @param string $mode
   *   The fopen() mode.
   */
  #[DataProvider('dataProviderAcceptsWritableStream')]
  public function testAcceptsWritableStream(string $mode): void {
    $this->createAndSetInstance();
    $stream = fopen('php://memory', $mode);
    $this->assertIsResource($stream);

    Prompty::configure(output: $stream);

    $this->assertSame($stream, Prompty::config()['output']);
    fclose($stream);
  }

  public static function dataProviderAcceptsWritableStream(): \Iterator {
    yield 'write' => ['w'];
    yield 'append' => ['a'];
    yield 'write and read' => ['w+'];
    yield 'read and write' => ['r+'];
    yield 'binary write' => ['wb'];
  }

  public function testRejectionLeavesConfigAlone(): void {
    $this->createAndSetInstance();
    $before = Prompty::config();

    try {
      Prompty::configure(labels: ['yes' => 'Aye'], env_prefix: 'KITCHEN_', output: 2);
    }
    catch (\InvalidArgumentException) {
      // The message is asserted elsewhere; this checks nothing was applied.
    }

    $after = Prompty::config();
    $this->assertSame($before['labels'], $after['labels']);
    $this->assertSame($before['env_prefix'], $after['env_prefix']);
    $this->assertNull($after['output']);
  }

  public function testFlowTargetIsScoped(): void {
    $this->createAndSetInstance();
    $this->setEnvVars(['dish' => 'Plum Compote']);

    $written = '';
    $buffer = $this->captureOutput(function () use (&$written): void {
      Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], output: function (string $chunk) use (&$written): void {
        $written .= $chunk;
      });
    });

    $this->assertSame('', $buffer);
    $this->assertStringContainsString('Plum Compote', $written);
    $this->assertNull(Prompty::config()['output']);
    $this->assertStringContainsString('Kitchen order', $this->captureOutput(fn() => Prompty::intro('Kitchen order')));
  }

  public function testFlowTargetMergesOverGlobalTarget(): void {
    $this->createAndSetInstance();
    $this->setEnvVars(['dish' => 'Plum Compote']);

    $global = '';
    $scoped = '';
    Prompty::configure(output: function (string $chunk) use (&$global): void {
      $global .= $chunk;
    });

    Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], output: function (string $chunk) use (&$scoped): void {
      $scoped .= $chunk;
    });
    Prompty::outro('Order sent to the kitchen!');

    $this->assertStringContainsString('Plum Compote', $scoped);
    $this->assertStringNotContainsString('Plum Compote', $global);
    $this->assertStringContainsString('Order sent to the kitchen!', $global);
  }

  public function testFlowTargetIsRestoredAfterThrow(): void {
    $this->createAndSetInstance();

    $written = '';
    $thrown = NULL;

    try {
      Prompty::flow(function (): array {
        Prompty::output(['Table 4']);

        throw new \RuntimeException('Kitchen closed.');
      }, output: function (string $chunk) use (&$written): void {
        $written .= $chunk;
      });
    }
    catch (\RuntimeException $exception) {
      $thrown = $exception->getMessage();
    }

    $this->assertSame('Kitchen closed.', $thrown);
    $this->assertSame('Table 4' . PHP_EOL, $written);
    $this->assertNull(Prompty::config()['output']);
  }

  public function testFlowRejectsInvalidTarget(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Output target of type "int" for "output" is not valid. Provide a writable stream or a callable that receives each chunk.', function (): void {
      Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], intro: 'Kitchen order', output: 2);
    });

    $this->assertSame('', $output);
    $this->assertNull(Prompty::config()['output']);
    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  /**
   * Runs a Prompty call on a fresh singleton and returns the output buffer.
   *
   * @param \Closure $call
   *   The Prompty call.
   * @param string $keystrokes
   *   Raw keystroke bytes fed to an interactive widget.
   * @param mixed $target
   *   The output target to configure, or NULL to keep echo.
   *
   * @return string
   *   Raw bytes that reached the output buffer.
   */
  protected function render(\Closure $call, string $keystrokes, mixed $target = NULL): string {
    $stream = $this->installKeystrokes($keystrokes, FALSE);

    if ($target !== NULL) {
      Prompty::configure(output: $target);
    }

    ob_start();

    try {
      $call();
    }
    finally {
      $buffer = ob_get_clean();
      fclose($stream);
    }

    return $buffer ?: '';
  }

}
