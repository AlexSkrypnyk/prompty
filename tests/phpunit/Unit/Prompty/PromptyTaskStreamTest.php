<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::task() with 'stream', which draws the action's output.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTaskStreamTest extends PromptyTestCase {

  #[DataProvider('dataProviderStreamPrinted')]
  public function testStreamPrinted(\Closure $action, string $expected): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: $action, success: 'Tart baked', failure: 'Tart burnt', stream: TRUE));

    $this->assertSame($expected, $output);
  }

  public static function dataProviderStreamPrinted(): \Iterator {
    yield 'echo and print' => [
      function (): bool {
        echo "Oven at 180°C\n";
        print "Pears fanned over the frangipane\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|    Pears fanned over the frangipane\n|\n|  + Tart baked\n|\n",
    ];

    yield 'several lines in 1 write' => [
      function (): bool {
        echo "Tray 1 in\nTray 2 in\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Tray 1 in\n|    Tray 2 in\n|\n|  + Tart baked\n|\n",
    ];

    yield '1 line across writes' => [
      function (): bool {
        echo 'Tray ';
        echo "1 in\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Tray 1 in\n|\n|  + Tart baked\n|\n",
    ];

    yield 'last line without a newline' => [
      function (): bool {
        echo "Oven at 180°C\nResting on the rack";

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|    Resting on the rack\n|\n|  + Tart baked\n|\n",
    ];

    yield 'carriage returns keep the last frame' => [
      function (): bool {
        echo "Rising 20%\rRising 70%\rRising 100%\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Rising 100%\n|\n|  + Tart baked\n|\n",
    ];

    yield 'CRLF line endings' => [
      function (): bool {
        echo "Tray 1 in\r\nTray 2 in\r\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Tray 1 in\n|    Tray 2 in\n|\n|  + Tart baked\n|\n",
    ];

    yield 'blank lines are kept' => [
      function (): bool {
        echo "Tray 1 in\n\nTray 2 in\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Tray 1 in\n|    \n|    Tray 2 in\n|\n|  + Tart baked\n|\n",
    ];

    yield 'control characters are removed' => [
      function (): bool {
        echo "Tray\t1\x07 in\033[2J\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Tray 1 in\n|\n|  + Tart baked\n|\n",
    ];

    yield 'long line wraps with the indent kept' => [
      function (): bool {
        echo str_repeat('Pastry rolled thin ', 5) . "and lined into the tin with the overhang left on\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Pastry rolled thin Pastry rolled thin Pastry rolled thin Pastry rolled thin\n|    Pastry rolled thin and lined into the tin with the overhang left on\n|\n|  + Tart baked\n|\n",
    ];

    yield 'no output' => [
      fn(): bool => TRUE,
      "o  Baking the tart\n|  + Tart baked\n|\n",
    ];

    yield 'failure' => [
      function (): bool {
        echo "Crust too dark\n";

        return FALSE;
      },
      "o  Baking the tart\n|    Crust too dark\n|\n|  ! Tart burnt\n|\n",
    ];

    yield 'details after the output' => [
      function (): array {
        echo "Oven at 180°C\n";

        return [
          'Tray 1',
          'Tray 2',
        ];
      },
      "o  Baking the tart\n|    Oven at 180°C\n|\n|    Tray 1\n|    Tray 2\n|  + Tart baked\n|\n",
    ];
  }

  public function testStreamChildProcess(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    // escapeshellarg() blanks double quotes on Windows, so the code uses
    // single quotes.
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg("echo 'Tray 1 in', PHP_EOL; fwrite(STDERR, 'Fan cycling' . PHP_EOL); echo 'Tray 2 in', PHP_EOL;") . ' 2>&1';

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function () use ($command): bool {
      passthru($command);

      return TRUE;
    }, stream: TRUE));

    $this->assertStringContainsString("|    Tray 1 in\n", $output);
    $this->assertStringContainsString("|    Fan cycling\n", $output);
    $this->assertStringContainsString("|    Tray 2 in\n", $output);
  }

  public function testStreamKeepsSgr(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    ob_start();
    Prompty::task('Baking the tart', action: function (): bool {
      echo "Crust \033[33mgolden\033[0m at the edges\n";

      return TRUE;
    }, stream: TRUE);
    $output = (string) ob_get_clean();

    $this->assertStringContainsString("    \033[2mCrust \033[33mgolden\033[0m\033[2m at the edges\033[0m", $output);
  }

  public function testStreamPromptyOutputToEcho(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      Prompty::output(['Oven at 180°C']);

      return TRUE;
    }, stream: TRUE));

    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|\n|  + Done\n|\n", $output);
  }

  #[DataProvider('dataProviderStreamToTarget')]
  public function testStreamToTarget(bool $is_callable): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $stream = fopen('php://memory', 'w+');
    $this->assertIsResource($stream);
    $written = '';
    $this->setProperty($p, 'cfgOutput', $is_callable ? function (string $chunk) use (&$written): void {
      $written .= $chunk;
    } : $stream);

    $echoed = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      Prompty::output(['Oven at 180°C']);
      echo "Tray 1 in\n";

      return TRUE;
    }, stream: TRUE));

    if (!$is_callable) {
      rewind($stream);
      $written = (string) stream_get_contents($stream);
    }

    $this->assertSame('', $echoed);
    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|    Tray 1 in\n|\n|  + Done\n|\n", $this->promptyNormalizeOutput($written));
  }

  public static function dataProviderStreamToTarget(): \Iterator {
    yield 'stream' => [FALSE];
    yield 'callable' => [TRUE];
  }

  public function testStreamReturnsActionValue(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::task('Baking the tart', action: function (): string {
        echo "Oven at 180°C\n";

        return '40 minutes';
      }, success: fn(string $time): string => 'Baked in ' . $time, stream: TRUE);
    });

    $this->assertSame('40 minutes', $result);
  }

  public function testStreamPrintedExceptionKeepsOutput(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutputThrows(\RuntimeException::class, 'The oven door is open.', fn(): mixed => Prompty::task('Baking the tart', action: function (): never {
      echo "Oven at 180°C\nDoor";

      throw new \RuntimeException('The oven door is open.');
    }, stream: TRUE), TRUE);

    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|    Door\n|\n|  ! The oven door is open.\n|\n", $output);
    $this->assertSame([], $this->getProperty($p, 'streams'));
  }

  #[DataProvider('dataProviderStreamBufferLevel')]
  public function testStreamBufferLevel(\Closure $action, string $expected): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $before = 0;
    $after = 0;
    $output = $this->captureOutput(function () use ($action, &$before, &$after): void {
      $before = ob_get_level();
      Prompty::task('Baking the tart', action: $action, stream: TRUE);
      $after = ob_get_level();
    });

    $this->assertSame($before, $after);
    $this->assertSame($expected, $output);
  }

  public static function dataProviderStreamBufferLevel(): \Iterator {
    yield 'buffer left open' => [
      function (): bool {
        ob_start();
        echo "Oven at 180°C\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|\n|  + Done\n|\n",
    ];

    yield 'stream buffer closed' => [
      function (): bool {
        echo "Oven at 180°C\n";
        ob_end_flush();

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|\n|  + Done\n|\n",
    ];
  }

  public function testStreamInFlow(): void {
    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow()
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert')
          ->children()
            ->task('bake', 'Baking the tart', action: function (array $results): bool {
              echo 'Oven at 180°C for ' . $results['course'] . "\n";

              return TRUE;
            }, success: 'Tart baked', stream: TRUE)
            ->confirm('warm', 'Serve warm?')
          ->end()
        ->run(numbering: TRUE, unicode: TRUE, interactive: FALSE);
    });

    $this->assertSame(['course' => 'dessert', 'bake' => TRUE, 'warm' => TRUE], $result);
    $this->assertSame("◆  Course (1)\n│  Dessert\n│\n│  │\n│  ◇  Baking the tart (1.1)\n│  │    Oven at 180°C for dessert\n│  │  \n│  │  ✓ Tart baked\n│  │  \n│  ◆  Serve warm? (1.2)\n│     Yes\n│     \n", $output);
  }

  #[DataProvider('dataProviderStreamLive')]
  public function testStreamLive(\Closure $action, array $frames): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureRaw(fn(): mixed => Prompty::task('Baking the tart', action: $action, success: 'Tart baked', failure: 'Tart burnt', stream: TRUE));

    $this->assertSame("\033[?25l" . implode('', $frames) . "\033[?25h", str_replace(PHP_EOL, "\n", $this->stripColors($output)));
  }

  public static function dataProviderStreamLive(): \Iterator {
    yield 'success collapses' => [
      function (): bool {
        echo "Oven at 180°C\nPears fanned\n";

        return TRUE;
      },
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n|    Pears fanned\n",
        "\033[3A\r\033[J+  Baking the tart\n|  + Tart baked (2 lines)\n|\n",
      ],
    ];

    yield '1 line' => [
      function (): bool {
        echo "Oven at 180°C\n";

        return TRUE;
      },
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n",
        "\033[2A\r\033[J+  Baking the tart\n|  + Tart baked (1 line)\n|\n",
      ],
    ];

    yield 'no output' => [
      fn(): bool => TRUE,
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[J+  Baking the tart\n|  + Tart baked\n|\n",
      ],
    ];

    yield 'failure expands' => [
      function (): bool {
        echo "Oven at 180°C\nCrust too dark\n";

        return FALSE;
      },
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n|    Crust too dark\n",
        "\033[3A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n|    Crust too dark\n|\n|  ! Tart burnt\n|\n",
      ],
    ];

    yield 'failure without output' => [
      fn(): bool => FALSE,
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|  ! Tart burnt\n|\n",
      ],
    ];

    yield 'line being written is shown' => [
      function (): bool {
        echo 'Rising 20%';
        echo "\rRising 100%\n";

        return TRUE;
      },
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|    Rising 20%\n",
        "\033[2A\r\033[Jo  Baking the tart\n|    Rising 100%\n",
        "\033[2A\r\033[J+  Baking the tart\n|  + Tart baked (1 line)\n|\n",
      ],
    ];

    yield 'last line without a newline' => [
      function (): bool {
        echo 'Resting on the rack';

        return TRUE;
      },
      [
        "o  Baking the tart\n",
        "\033[1A\r\033[Jo  Baking the tart\n|    Resting on the rack\n",
        "\033[2A\r\033[Jo  Baking the tart\n|    Resting on the rack\n",
        "\033[2A\r\033[J+  Baking the tart\n|  + Tart baked (1 line)\n|\n",
      ],
    ];
  }

  public function testStreamLiveWindow(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      echo "Tray 1 in\nTray 2 in\nTray 3 in\nTray 4 in\nTray 5 in\nTray 6 in\nTray 7 in\n";

      return TRUE;
    }, stream: TRUE));

    $this->assertStringContainsString("o  Baking the tart\n|  ^ 2 more\n|    Tray 3 in\n|    Tray 4 in\n|    Tray 5 in\n|    Tray 6 in\n|    Tray 7 in\n", $output);
    $this->assertStringEndsWith("+  Baking the tart\n|  + Done (7 lines)\n|\n", $output);
  }

  public function testStreamLiveCutsLongLines(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      echo str_repeat('Pastry rolled thin ', 5) . "and lined into the tin\n";

      return FALSE;
    }, stream: TRUE));

    $this->assertStringContainsString("o  Baking the tart\n|    Pastry rolled thin Pastry rolled thin Pastry rolled thin Pastry rolled...\n", $output);
    // The full output on failure wraps the line instead of cutting it.
    $this->assertStringContainsString("|    Pastry rolled thin Pastry rolled thin Pastry rolled thin Pastry rolled thin\n|    Pastry rolled thin and lined into the tin\n", $output);
  }

  public function testStreamLiveExceptionExpandsOutput(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    ob_start();

    try {
      Prompty::task('Baking the tart', action: function (): never {
        echo "Oven at 180°C\n";

        throw new \RuntimeException('The oven door is open.');
      }, stream: TRUE);
      $this->fail('The exception was not rethrown.');
    }
    catch (\RuntimeException $runtime_exception) {
      $this->assertSame('The oven door is open.', $runtime_exception->getMessage());
      $this->assertTrue(Prompty::isDrawn($runtime_exception));
    }

    $output = (string) ob_get_clean();

    $this->assertStringEndsWith("\033[2A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n|\n|  ! The oven door is open.\n|\n\033[?25h", str_replace(PHP_EOL, "\n", $this->stripColors($output)));
    $this->assertStringContainsString("\033[31mo\033[0m  Baking the tart", $output);
  }

  public function testStreamLiveTextCallableException(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureOutputThrows(\UnexpectedValueException::class, 'The success callable for task "Baking the tart" returned int. It must return the text to draw.', fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      echo "Oven at 180°C\n";

      return TRUE;
    }, success: fn(): int => 1, stream: TRUE), FALSE);

    $this->assertSame("o  Baking the tart\n\ro  Baking the tart\n|    Oven at 180°C\n\ro  Baking the tart\n|    Oven at 180°C\n", $output);
    $this->assertSame([], $this->getProperty($p, 'streams'));
  }

  public function testStreamStateClearedAfterTask(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);

    $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      echo "Oven at 180°C\nResting";

      return TRUE;
    }, stream: TRUE));

    $this->assertSame([], $this->getProperty($p, 'streams'));
    $this->assertNull($this->getProperty($p, 'streamDrawing'));
    $this->assertNull($this->getProperty($p, 'streamBuffer'));
  }

  public function testOutputAfterStreamIsNotStreamed(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $stream = fopen('php://memory', 'w+');
    $this->assertIsResource($stream);
    $this->setProperty($p, 'cfgOutput', $stream);

    Prompty::task('Baking the tart', action: fn(): bool => TRUE, stream: TRUE);
    Prompty::output(['Oven off']);

    rewind($stream);
    $this->assertSame("o  Baking the tart\n|  + Done\n|\nOven off\n", $this->promptyNormalizeOutput((string) stream_get_contents($stream)));
  }

  #[DataProvider('dataProviderStreamNested')]
  public function testStreamNested(\Closure $action, string $expected): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: $action, success: 'Tart baked', stream: TRUE));

    $this->assertSame($expected, $output);
    $this->assertSame([], $this->getProperty($p, 'streams'));
    $this->assertNull($this->getProperty($p, 'streamDrawing'));
    $this->assertSame(substr_count($output, "\n"), $this->getProperty($p, 'rows'));
  }

  public static function dataProviderStreamNested(): \Iterator {
    yield '2 levels' => [
      function (): bool {
        echo "Oven at 180°C\n";
        Prompty::task('Making the pastry', action: function (): bool {
          echo "Butter rubbed in\n";

          return TRUE;
        }, success: 'Pastry made', stream: TRUE);
        echo "Tart in the oven\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |\n|    |  + Pastry made\n|    |\n|    Tart in the oven\n|\n|  + Tart baked\n|\n",
    ];

    yield '3 levels' => [
      function (): bool {
        echo "Oven at 180°C\n";
        Prompty::task('Making the pastry', action: function (): bool {
          echo "Butter rubbed in\n";
          Prompty::task('Resting the dough', action: function (): bool {
            echo "Wrapped and chilled\n";

            return TRUE;
          }, success: 'Dough rested', stream: TRUE);
          echo "Rolled thin\n";

          return TRUE;
        }, success: 'Pastry made', stream: TRUE);
        echo "Tart in the oven\n";

        return TRUE;
      },
      "o  Baking the tart\n|    Oven at 180°C\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |    o  Resting the dough\n|    |    |    Wrapped and chilled\n|    |    |\n|    |    |  + Dough rested\n|    |    |\n|    |    Rolled thin\n|    |\n|    |  + Pastry made\n|    |\n|    Tart in the oven\n|\n|  + Tart baked\n|\n",
    ];

    yield 'siblings' => [
      function (): bool {
        Prompty::task('Making the pastry', action: function (): bool {
          echo "Butter rubbed in\n";

          return TRUE;
        }, success: 'Pastry made', stream: TRUE);
        Prompty::task('Slicing the pears', action: function (): bool {
          echo "Pears fanned\n";

          return TRUE;
        }, success: 'Pears sliced', stream: TRUE);

        return TRUE;
      },
      "o  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |\n|    |  + Pastry made\n|    |\n|    o  Slicing the pears\n|    |    Pears fanned\n|    |\n|    |  + Pears sliced\n|    |\n|\n|  + Tart baked\n|\n",
    ];

    yield 'inner failure' => [
      function (): bool {
        Prompty::task('Making the pastry', action: function (): bool {
          echo "Butter too warm\n";

          return FALSE;
        }, failure: 'Pastry cracked', stream: TRUE);
        echo "Tart in the oven\n";

        return TRUE;
      },
      "o  Baking the tart\n|    o  Making the pastry\n|    |    Butter too warm\n|    |\n|    |  ! Pastry cracked\n|    |\n|    Tart in the oven\n|\n|  + Tart baked\n|\n",
    ];

    yield 'inner last line without a newline' => [
      function (): bool {
        Prompty::task('Making the pastry', action: function (): bool {
          echo 'Butter rubbed in';

          return TRUE;
        }, success: 'Pastry made', stream: TRUE);
        echo "Tart in the oven\n";

        return TRUE;
      },
      "o  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |\n|    |  + Pastry made\n|    |\n|    Tart in the oven\n|\n|  + Tart baked\n|\n",
    ];

    yield 'spinner inside a nested stream' => [
      function (): bool {
        Prompty::task('Making the pastry', action: function (): bool {
          Prompty::task('Weighing the flour', action: fn(): bool => TRUE, success: 'Flour weighed');

          return TRUE;
        }, success: 'Pastry made', stream: TRUE);

        return TRUE;
      },
      "o  Baking the tart\n|    o  Making the pastry\n|    |    o  Weighing the flour\n|    |    |  + Flour weighed\n|    |    |\n|    |\n|    |  + Pastry made\n|    |\n|\n|  + Tart baked\n|\n",
    ];
  }

  #[DataProvider('dataProviderStreamNestedToTarget')]
  public function testStreamNestedToTarget(bool $is_callable): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $stream = fopen('php://memory', 'w+');
    $this->assertIsResource($stream);
    $written = '';
    $this->setProperty($p, 'cfgOutput', $is_callable ? function (string $chunk) use (&$written): void {
      $written .= $chunk;
    } : $stream);

    $echoed = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      Prompty::output(['Oven at 180°C']);
      Prompty::task('Making the pastry', action: function (): bool {
        Prompty::output(['Butter rubbed in']);
        echo "Rolled thin\n";

        return TRUE;
      }, success: 'Pastry made', stream: TRUE);
      echo "Tart in the oven\n";

      return TRUE;
    }, stream: TRUE));

    if (!$is_callable) {
      rewind($stream);
      $written = (string) stream_get_contents($stream);
    }

    $this->assertSame('', $echoed);
    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |    Rolled thin\n|    |\n|    |  + Pastry made\n|    |\n|    Tart in the oven\n|\n|  + Done\n|\n", $this->promptyNormalizeOutput($written));
    $this->assertSame([], $this->getProperty($p, 'streams'));
  }

  public static function dataProviderStreamNestedToTarget(): \Iterator {
    yield 'stream' => [FALSE];
    yield 'callable' => [TRUE];
  }

  public function testStreamNestedException(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      try {
        Prompty::task('Making the pastry', action: function (): never {
          echo "Butter too warm\n";

          throw new \RuntimeException('The butter melted.');
        }, stream: TRUE);
      }
      catch (\RuntimeException) {
        echo "Pastry made again\n";
      }

      return TRUE;
    }, success: 'Tart baked', stream: TRUE));

    $this->assertSame("o  Baking the tart\n|    o  Making the pastry\n|    |    Butter too warm\n|    |\n|    |  ! The butter melted.\n|    |\n|    Pastry made again\n|\n|  + Tart baked\n|\n", $output);
    $this->assertSame([], $this->getProperty($p, 'streams'));
  }

  public function testStreamTargetExceptionRestoresDrawing(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $written = '';
    $this->setProperty($p, 'cfgOutput', function (string $chunk) use (&$written): void {
      if (str_contains($chunk, 'Butter too warm')) {
        throw new \RuntimeException('The ticket printer jammed.');
      }

      $written .= $chunk;
    });

    $drawing = [];
    Prompty::task('Baking the tart', action: function () use ($p, &$drawing): bool {
      Prompty::task('Making the pastry', action: function () use ($p, &$drawing): bool {
        try {
          Prompty::output(['Butter too warm']);
        }
        catch (\RuntimeException) {
          $drawing[] = $this->getProperty($p, 'streamDrawing');
        }

        Prompty::output(['Butter rubbed in']);

        return TRUE;
      }, success: 'Pastry made', stream: TRUE);

      return TRUE;
    }, success: 'Tart baked', stream: TRUE);

    $this->assertSame([NULL], $drawing);
    $this->assertSame("o  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |\n|    |  + Pastry made\n|    |\n|\n|  + Tart baked\n|\n", $this->promptyNormalizeOutput($written));
    $this->assertNull($this->getProperty($p, 'streamDrawing'));
  }

  #[DataProvider('dataProviderStreamEndException')]
  public function testStreamEndException(\Closure $action): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $written = '';
    $this->setProperty($p, 'cfgOutput', function (string $chunk) use (&$written): void {
      if (str_contains($chunk, 'Butter too warm')) {
        throw new \RuntimeException('The ticket printer jammed.');
      }

      $written .= $chunk;
    });

    // PHP passes a chunk through unprocessed when its handler throws.
    ob_start();
    $before = ob_get_level();

    try {
      Prompty::task('Baking the tart', action: $action, stream: TRUE);
      $this->fail('The exception was not rethrown.');
    }
    catch (\RuntimeException $runtime_exception) {
      $this->assertSame('The ticket printer jammed.', $runtime_exception->getMessage());
      $this->assertFalse(Prompty::isDrawn($runtime_exception));
    }

    $after = ob_get_level();
    ob_end_clean();

    Prompty::output(['Oven off']);

    $this->assertSame($before, $after);
    $this->assertSame([], $this->getProperty($p, 'streams'));
    $this->assertSame("o  Baking the tart\nOven off\n", $this->promptyNormalizeOutput($written));
  }

  public static function dataProviderStreamEndException(): \Iterator {
    yield 'last line drawn at the end' => [
      function (): bool {
        echo 'Butter too warm';

        return TRUE;
      },
    ];

    yield 'buffer left open' => [
      function (): bool {
        ob_start();
        echo "Butter too warm\n";

        return TRUE;
      },
    ];
  }

  #[DataProvider('dataProviderStreamNestedLive')]
  public function testStreamNestedLive(bool $has_succeeded, string $last_frame): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);

    $output = $this->captureRaw(fn(): mixed => Prompty::task('Baking the tart', action: function () use ($has_succeeded): bool {
      Prompty::task('Making the pastry', action: function (): bool {
        echo "Butter rubbed in\n";
        Prompty::task('Weighing the flour', action: fn(): bool => TRUE, success: 'Flour weighed');

        return TRUE;
      }, success: 'Pastry made', stream: TRUE);

      return $has_succeeded;
    }, success: 'Tart baked', failure: 'Tart burnt', stream: TRUE));

    // Only the outer window is redrawn: the nested tasks print each line once
    // into it, with no cursor movement of their own.
    $frames = [
      "o  Baking the tart\n",
      "\033[1A\r\033[Jo  Baking the tart\n|    o  Making the pastry\n",
      "\033[2A\r\033[Jo  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n",
      "\033[3A\r\033[Jo  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |    o  Weighing the flour\n",
      "\033[4A\r\033[Jo  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |    o  Weighing the flour\n|    |    |  + Flour weighed\n|    |    |\n",
      "\033[6A\r\033[Jo  Baking the tart\n|  ^ 3 more\n|    |    |  + Flour weighed\n|    |    |\n|    |\n|    |  + Pastry made\n|    |\n",
      $last_frame,
    ];

    $this->assertSame("\033[?25l" . implode('', $frames) . "\033[?25h", str_replace(PHP_EOL, "\n", $this->stripColors($output)));
    $this->assertSame([], $this->getProperty($p, 'streams'));
  }

  public static function dataProviderStreamNestedLive(): \Iterator {
    yield 'success collapses' => [TRUE, "\033[7A\r\033[J+  Baking the tart\n|  + Tart baked (8 lines)\n|\n"];
    yield 'failure expands' => [FALSE, "\033[7A\r\033[Jo  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |    o  Weighing the flour\n|    |    |  + Flour weighed\n|    |    |\n|    |\n|    |  + Pastry made\n|    |\n|\n|  ! Tart burnt\n|\n"];
  }

  public function testFeedStreamWithoutStream(): void {
    $p = $this->createAndSetInstance();

    $this->callProtected($p, 'feedStream', 0, "Oven at 180°C\n");

    $this->assertSame([], $this->getProperty($p, 'streams'));
    $this->assertNull($this->getProperty($p, 'streamDrawing'));
  }

  #[DataProvider('dataProviderFitRow')]
  public function testFitRow(string $gutter, string $content, int $width, string $expected): void {
    $p = $this->createInstance(['ansi' => FALSE, 'width' => $width]);

    $this->assertSame($expected, $this->callProtected($p, 'fitRow', $gutter, $content));
    $this->assertSame($width, $this->getProperty($p, 'cfgWidth'));
  }

  public static function dataProviderFitRow(): \Iterator {
    yield 'fits' => ['|    ', 'Oven at 180', 20, '|    Oven at 180'];
    yield 'cut at a word' => ['|    ', 'Oven at 180 with the fan on', 20, '|    Oven at 180...'];
    yield 'cut inside a long word' => ['|    ', str_repeat('a', 30), 20, '|    ' . str_repeat('a', 12) . '...'];
    yield 'gutter wider than the row' => [str_repeat('|', 25), 'Oven', 20, str_repeat('|', 25) . 'Oven'];
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
