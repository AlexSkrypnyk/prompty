<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::task(), which runs an action and reports the result.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTaskTest extends PromptyTestCase {

  #[DataProvider('dataProviderTaskPrinted')]
  public function testTaskPrinted(\Closure $run, string $expected): void {
    $this->createAndSetInstance(['unicode' => TRUE, 'interactive' => FALSE]);

    $output = $this->captureOutput($run);

    $this->assertSame($expected, $output);
  }

  public static function dataProviderTaskPrinted(): \Iterator {
    yield 'success' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, success: 'Oven ready'),
      "◇  Preheating the oven\n│  ✓ Oven ready\n│\n",
    ];

    yield 'failure' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => FALSE, failure: 'Oven did not reach temperature'),
      "◇  Preheating the oven\n│  ▲ Oven did not reach temperature\n│\n",
    ];

    yield 'default success line' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE),
      "◇  Preheating the oven\n│  ✓ Done\n│\n",
    ];

    yield 'default failure line' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => FALSE),
      "◇  Preheating the oven\n│  ▲ Failed\n│\n",
    ];

    yield 'NULL is a success' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): null => NULL, success: 'Oven ready', failure: 'Oven cold'),
      "◇  Preheating the oven\n│  ✓ Oven ready\n│\n",
    ];

    yield 'hint' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, hint: 'Fan oven, 180°C', success: 'Oven ready'),
      "◇  Preheating the oven\n│  Fan oven, 180°C\n│  ✓ Oven ready\n│\n",
    ];

    yield 'multiline hint' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, hint: "Fan oven, 180°C\nShelf 2"),
      "◇  Preheating the oven\n│  Fan oven, 180°C\n│  Shelf 2\n│  ✓ Done\n│\n",
    ];

    yield 'callables receive the return value' => [
      fn(): mixed => Prompty::task('Weighing the flour',
        action: fn(): int => 450,
        hint: fn(int $grams): string => sprintf('Scale shows %dg', $grams),
        success: fn(int $grams): string => sprintf('Weighed %dg', $grams),
      ),
      "◇  Weighing the flour\n│  Scale shows 450g\n│  ✓ Weighed 450g\n│\n",
    ];

    yield 'failure callable receives FALSE' => [
      fn(): mixed => Prompty::task('Weighing the flour', action: fn(): bool => FALSE, failure: fn(bool $value): string => $value ? 'yes' : 'Scale returned no reading'),
      "◇  Weighing the flour\n│  ▲ Scale returned no reading\n│\n",
    ];

    yield 'list of strings as details' => [
      fn(): mixed => Prompty::task('Setting the table', action: fn(): array => ['Table 7', 'Seats 2 and 4'], success: 'Table set'),
      "◇  Setting the table\n│    Table 7\n│    Seats 2 and 4\n│  ✓ Table set\n│\n",
    ];

    yield 'array that is not a list draws no details' => [
      fn(): mixed => Prompty::task('Setting the table', action: fn(): array => ['table' => 'Table 7'], success: 'Table set'),
      "◇  Setting the table\n│  ✓ Table set\n│\n",
    ];

    yield 'list with a non-string draws no details' => [
      fn(): mixed => Prompty::task('Setting the table', action: fn(): array => ['Table 7', 4], success: 'Table set'),
      "◇  Setting the table\n│  ✓ Table set\n│\n",
    ];

    yield 'text is printable' => [
      fn(): mixed => Prompty::task("Preheating\tthe oven\033[2J", action: fn(): array => ["Fan\x07 on"], hint: "Shelf\033[2J 2", success: "Oven\x07 ready"),
      "◇  Preheating the oven\n│  Shelf 2\n│    Fan on\n│  ✓ Oven ready\n│\n",
    ];

    yield 'long result wraps under its text' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => FALSE, failure: str_repeat('Oven cold ', 9) . 'and the fan is off'),
      "◇  Preheating the oven\n│  ▲ Oven cold Oven cold Oven cold Oven cold Oven cold Oven cold Oven cold Oven\n│    cold Oven cold and the fan is off\n│\n",
    ];
  }

  public function testTaskAscii(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, success: 'Oven ready'));

    $this->assertSame("o  Preheating the oven\n|  + Oven ready\n|\n", $output);
  }

  public function testTaskColors(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureRaw(function (): void {
      Prompty::task('Preheating the oven', action: fn(): array => ['Fan on'], hint: 'Shelf 2', success: 'Oven ready');
      Prompty::task('Proving the dough', action: fn(): bool => FALSE, failure: 'Dough did not rise');
    });

    $bar = "\033[90m|\033[0m";
    $this->assertSame(implode(PHP_EOL, [
      "\033[36mo\033[0m  Preheating the oven",
      $bar . "  \033[2mShelf 2\033[0m",
      $bar . "    \033[2mFan on\033[0m",
      $bar . "  \033[32m+ Oven ready\033[0m",
      $bar,
      "\033[36mo\033[0m  Proving the dough",
      $bar . "  \033[31m! Dough did not rise\033[0m",
      $bar,
    ]) . PHP_EOL, $output);
  }

  #[DataProvider('dataProviderTaskReturnsActionValue')]
  public function testTaskReturnsActionValue(mixed $value): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $result = NULL;
    $this->captureOutput(function () use ($value, &$result): void {
      $result = Prompty::task('Preheating the oven', action: fn(): mixed => $value);
    });

    $this->assertSame($value, $result);
  }

  public static function dataProviderTaskReturnsActionValue(): \Iterator {
    yield 'true' => [TRUE];
    yield 'false' => [FALSE];
    yield 'null' => [NULL];
    yield 'string' => ['1.40.0'];
    yield 'list' => [['Fan on']];
  }

  public function testTaskLabelIsPrintedBeforeActionRuns(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $seen = '';
    $output = $this->captureOutput(function () use (&$seen): void {
      Prompty::task('Preheating the oven', action: function () use (&$seen): bool {
        $seen = (string) ob_get_contents();

        return TRUE;
      });
    });

    $this->assertSame("o  Preheating the oven\n", $this->promptyNormalizeOutput($seen));
    $this->assertSame("o  Preheating the oven\n|  + Done\n|\n", $output);
  }

  #[DataProvider('dataProviderTaskLive')]
  public function testTaskLive(\Closure $run, bool $is_in_flow, string $expected): void {
    $p = $this->createAndSetInstance([], $is_in_flow);
    $this->setProperty($p, 'isTerminal', TRUE);

    ob_start();
    $run();
    $output = (string) ob_get_clean();

    $this->assertSame($expected, str_replace(PHP_EOL, "\n", $this->stripColors($output)));
  }

  public static function dataProviderTaskLive(): \Iterator {
    yield 'success' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, hint: 'Fan oven, 180°C', success: 'Oven ready'),
      FALSE,
      "\033[?25l|  Preheating the oven\n\033[1A\r\033[J+  Preheating the oven\n|  Fan oven, 180°C\n|  + Oven ready\n|\n\033[?25h",
    ];

    yield 'failure' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => FALSE, failure: 'Oven cold'),
      FALSE,
      "\033[?25l|  Preheating the oven\n\033[1A\r\033[Jo  Preheating the oven\n|  ! Oven cold\n|\n\033[?25h",
    ];

    yield 'in a flow leaves the cursor to the flow' => [
      fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, ctx: ['depth' => 0, 'open' => []]),
      TRUE,
      "|  Preheating the oven\n\033[1A\r\033[J+  Preheating the oven\n|  + Done\n|\n",
    ];
  }

  public function testTaskLiveColors(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    ob_start();
    Prompty::task('Preheating the oven', action: fn(): bool => FALSE);
    $output = (string) ob_get_clean();

    $this->assertStringContainsString("\033[36m|\033[0m  Preheating the oven", $output);
    $this->assertStringContainsString("\033[31mo\033[0m  Preheating the oven", $output);
  }

  #[DataProvider('dataProviderTaskSpinnerFrames')]
  public function testTaskSpinnerFrames(string $spinner, string $expected): void {
    $p = $this->createAndSetInstance();
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertIsArray($symbols);
    $symbols['spinner'] = $spinner;
    $this->setProperty($p, 'cfgSymbols', $symbols);
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE));

    $this->assertSame($expected, explode("\n", $output)[0]);
  }

  public static function dataProviderTaskSpinnerFrames(): \Iterator {
    yield 'first frame' => ['*+', '*  Preheating the oven'];
    yield 'multibyte frame' => ['◐◓', '◐  Preheating the oven'];
    yield 'empty spinner' => ['', '  Preheating the oven'];
  }

  public function testTaskLiveExceptionClearsLabel(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    ob_start();

    try {
      Prompty::task('Preheating the oven', action: function (): never {
        throw new \RuntimeException('The oven door is open.');
      });
      $this->fail('The exception was not rethrown.');
    }
    catch (\RuntimeException $runtime_exception) {
      $this->assertSame('The oven door is open.', $runtime_exception->getMessage());
    }

    $output = (string) ob_get_clean();

    $this->assertSame("\033[?25l|  Preheating the oven\n\033[1A\r\033[J\033[?25h", str_replace(PHP_EOL, "\n", $this->stripColors($output)));
  }

  public function testTaskLiveExceptionInFlowKeepsCursorHidden(): void {
    $p = $this->createAndSetInstance([], TRUE);
    $this->setProperty($p, 'isTerminal', TRUE);

    ob_start();

    try {
      Prompty::task('Preheating the oven', action: function (): never {
        throw new \RuntimeException('The oven door is open.');
      }, ctx: ['depth' => 0, 'open' => []]);
    }
    catch (\RuntimeException) {
      // Asserted on the output below.
    }

    $output = (string) ob_get_clean();

    $this->assertSame("|  Preheating the oven\n\033[1A\r\033[J", str_replace(PHP_EOL, "\n", $this->stripColors($output)));
  }

  public function testTaskPrintedExceptionKeepsLabel(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutputThrows(\RuntimeException::class, 'The oven door is open.', fn(): mixed => Prompty::task('Preheating the oven', action: function (): never {
      throw new \RuntimeException('The oven door is open.');
    }));

    $this->assertSame("o  Preheating the oven\n", $output);
  }

  #[DataProvider('dataProviderTaskTextCallableInvalid')]
  public function testTaskTextCallableInvalid(array $arguments, string $message): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutputThrows(\UnexpectedValueException::class, $message, fn(): mixed => Prompty::task('Preheating the oven', ...$arguments));

    $this->assertSame("o  Preheating the oven\n", $output);
  }

  public static function dataProviderTaskTextCallableInvalid(): \Iterator {
    yield 'hint' => [
      ['action' => fn(): bool => TRUE, 'hint' => fn(): int => 180],
      'The hint callable for task "Preheating the oven" returned int. It must return the text to draw.',
    ];

    yield 'success' => [
      ['action' => fn(): bool => TRUE, 'success' => fn(): null => NULL],
      'The success callable for task "Preheating the oven" returned null. It must return the text to draw.',
    ];

    yield 'failure' => [
      ['action' => fn(): bool => FALSE, 'failure' => fn(): bool => FALSE],
      'The failure callable for task "Preheating the oven" returned bool. It must return the text to draw.',
    ];
  }

  public function testTaskTextStringIsNeverCalled(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, success: 'strtoupper'));

    $this->assertStringContainsString('|  + strtoupper', $output);
  }

  public function testTaskInFlowReturnsClosure(): void {
    $this->createAndSetInstance([], TRUE);

    $is_run = FALSE;
    $step = Prompty::task('Preheating the oven', action: function () use (&$is_run): bool {
      $is_run = TRUE;

      return TRUE;
    });

    $this->assertInstanceOf(\Closure::class, $step);
    $this->assertFalse($is_run);
  }

  public function testTaskInFlowWithConditionReturnsStepArray(): void {
    $this->createAndSetInstance([], TRUE);

    $condition = fn(array $r): bool => TRUE;
    $children = ['warm' => Prompty::confirm('Serve warm?')];

    $step = Prompty::task('Preheating the oven', action: fn(): bool => TRUE, condition: $condition, children: $children);

    $this->assertIsArray($step);
    $this->assertInstanceOf(\Closure::class, $step['__call']);
    $this->assertSame($condition, $step['__condition']);
    $this->assertSame($children, $step['__children']);
  }

  public function testTaskInFlow(): void {
    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        'oven' => Prompty::task('Preheating the oven', action: fn(): string => '180', success: 'Oven ready'),
        'proof' => Prompty::task('Proving the dough', action: fn(): bool => FALSE, failure: 'Dough did not rise'),
        'unused' => Prompty::task('Lighting the grill', action: fn(): bool => TRUE, condition: fn(array $r): bool => $r['proof'] === TRUE),
        'send' => Prompty::confirm('Send order?'),
      ], intro: 'Compose an order', unicode: TRUE, interactive: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'oven' => TRUE, 'proof' => FALSE, 'send' => TRUE], $result);
    $this->assertSame("\n┌  Compose an order\n│\n◆  Dish name\n│  pear tart\n│\n◇  Preheating the oven\n│  ✓ Oven ready\n│\n◇  Proving the dough\n│  ▲ Dough did not rise\n│\n◆  Send order?\n│  Yes\n│\n", $output);
  }

  public function testTaskInFlowNestedAndNumbered(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'main', children: [
          'oven' => Prompty::task('Preheating the oven', action: fn(): array => ['Fan on'], hint: 'Fan oven, 180°C', success: 'Oven ready'),
          'rest' => Prompty::confirm('Rest before serving?'),
        ]),
      ], numbering: TRUE, unicode: TRUE, interactive: FALSE);
    });

    $this->assertSame("◆  Course (1)\n│  Main\n│\n│  │\n│  ◇  Preheating the oven (1.1)\n│  │  Fan oven, 180°C\n│  │    Fan on\n│  │  ✓ Oven ready\n│  │  \n│  ◆  Rest before serving? (1.2)\n│     Yes\n│     \n", $output);
  }

  public function testTaskInFlowChildrenRun(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'oven' => Prompty::task('Preheating the oven', action: fn(): bool => TRUE, children: [
          'warm' => Prompty::confirm('Serve warm?'),
        ]),
      ], interactive: FALSE);
    });

    $this->assertSame(['oven' => TRUE, 'warm' => TRUE], $result);
  }

  #[DataProvider('dataProviderIsLive')]
  public function testIsLive(array $config, ?bool $is_terminal, \Closure|string|null $output, bool $expected): void {
    $p = $this->createInstance($config);
    $this->setProperty($p, 'isTerminal', $is_terminal);

    if ($output !== NULL) {
      $this->setProperty($p, 'cfgOutput', $output instanceof \Closure ? $output : fopen($output, 'w'));
    }

    $this->assertSame($expected, $this->callProtected($p, 'isLive'));
  }

  public static function dataProviderIsLive(): \Iterator {
    yield 'non-interactive' => [['interactive' => FALSE], TRUE, NULL, FALSE];
    yield 'ANSI off' => [['ansi' => FALSE], TRUE, NULL, FALSE];
    yield 'terminal set' => [[], TRUE, NULL, TRUE];
    yield 'terminal unset' => [[], FALSE, NULL, FALSE];
    yield 'callable target' => [[], NULL, fn(string $chunk): null => NULL, FALSE];
    yield 'stream target' => [[], NULL, 'php://memory', FALSE];
  }

  public function testIsLiveDetectsStdout(): void {
    $p = $this->createInstance();

    $this->assertSame(stream_isatty(STDOUT), $this->callProtected($p, 'isLive'));
  }

  #[DataProvider('dataProviderAnimateDoesNotFork')]
  public function testAnimateDoesNotFork(mixed $output): void {
    $p = $this->createInstance();

    if ($output !== NULL) {
      $this->setProperty($p, 'cfgOutput', $output);
    }

    ob_start();
    $pid = $this->callProtected($p, 'animate', fn(int $index): array => ['frame'], 1);
    ob_end_clean();

    $this->assertNull($pid);
  }

  public static function dataProviderAnimateDoesNotFork(): \Iterator {
    yield 'buffered echo' => [NULL];
    yield 'callable target' => [fn(string $chunk): null => NULL];
  }

  public function testStopAnimationWithoutSpinner(): void {
    $p = $this->createInstance();

    $this->callProtected($p, 'stopAnimation', NULL);

    $this->expectNotToPerformAssertions();
  }

  public function testClearRows(): void {
    $p = $this->createAndSetInstance();

    $output = $this->captureRaw(fn(): mixed => $this->callProtected($p, 'clearRows', 3));
    $this->assertSame("\033[3A\r\033[J", $output);

    $output = $this->captureRaw(fn(): mixed => $this->callProtected($p, 'clearRows', 0));
    $this->assertSame('', $output);
  }

  /**
   * Run a callable and return its raw output.
   */
  protected function captureRaw(callable $fn): string {
    ob_start();
    $fn();

    return (string) ob_get_clean();
  }

  /**
   * Strip SGR sequences and keep cursor movement.
   */
  protected function stripColors(string $text): string {
    return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
  }

}
