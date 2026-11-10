<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::note(), an info, success, warning or error message.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyNoteTest extends PromptyTestCase {

  #[DataProvider('dataProviderNotePrinted')]
  public function testNotePrinted(string $message, string $type, bool $unicode, string $expected): void {
    $this->createAndSetInstance(['unicode' => $unicode, 'ansi' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::note($message, $type));

    $this->assertSame($expected, $output);
  }

  public static function dataProviderNotePrinted(): \Iterator {
    yield 'info' => ['The kitchen opens at 5pm.', 'info', TRUE, "●  The kitchen opens at 5pm.\n│\n"];
    yield 'success' => ['Order sent to the kitchen.', 'success', TRUE, "✓  Order sent to the kitchen.\n│\n"];
    yield 'warning' => ['The pear tart is running low.', 'warning', TRUE, "▲  The pear tart is running low.\n│\n"];
    yield 'error' => ['The oven did not reach temperature.', 'error', TRUE, "✕  The oven did not reach temperature.\n│\n"];
    yield 'info ASCII' => ['The kitchen opens at 5pm.', 'info', FALSE, "*  The kitchen opens at 5pm.\n|\n"];
    yield 'success ASCII' => ['Order sent to the kitchen.', 'success', FALSE, "+  Order sent to the kitchen.\n|\n"];
    yield 'warning ASCII' => ['The pear tart is running low.', 'warning', FALSE, "!  The pear tart is running low.\n|\n"];
    yield 'error ASCII' => ['The oven did not reach temperature.', 'error', FALSE, "x  The oven did not reach temperature.\n|\n"];
    yield 'several lines' => ["The oven did not reach temperature.\nCheck the fan.", 'error', FALSE, "x  The oven did not reach temperature.\n|  Check the fan.\n|\n"];
    yield 'blank line' => ["Tables are set.\n\nThe pass is ready.", 'info', FALSE, "*  Tables are set.\n|  \n|  The pass is ready.\n|\n"];
    yield 'long line wraps with the indent kept' => [
      str_repeat('Check the fan ', 6) . 'and try again before the next order is sent.',
      'error',
      FALSE,
      "x  Check the fan Check the fan Check the fan Check the fan Check the fan Check\n|  the fan and try again before the next order is sent.\n|\n",
    ];
    yield 'control characters are removed' => ["The kitchen\topens\x07 at 5pm.\033[2J", 'info', FALSE, "*  The kitchen opens at 5pm.\n|\n"];
  }

  #[DataProvider('dataProviderNoteColors')]
  public function testNoteColors(string $type, string $color): void {
    $this->createAndSetInstance(['ansi' => TRUE]);

    $output = $this->captureRaw(fn(): mixed => Prompty::note("The kitchen opens at 5pm.\nTables are set.", $type));

    $this->assertSame($color, substr($output, 0, strlen($color)));
    $this->assertStringContainsString('  ' . $color . 'The kitchen opens at 5pm.' . "\033[0m", $output);
    $this->assertStringContainsString("\033[90m|\033[0m  " . $color . 'Tables are set.' . "\033[0m", $output);
  }

  public static function dataProviderNoteColors(): \Iterator {
    yield 'info' => ['info', "\033[34m"];
    yield 'success' => ['success', "\033[32m"];
    yield 'warning' => ['warning', "\033[33m"];
    yield 'error' => ['error', "\033[31m"];
  }

  public function testNoteKeepsSgr(): void {
    $this->createAndSetInstance(['ansi' => TRUE]);

    $output = $this->captureRaw(fn(): mixed => Prompty::note("Send the \033[1mpear tart\033[22m now.", 'warning'));

    $this->assertStringContainsString("\033[33mSend the \033[1mpear tart\033[22m\033[33m now.\033[0m", $output);
  }

  public function testNoteAnsiOff(): void {
    $this->createAndSetInstance(['ansi' => FALSE]);

    $output = $this->captureRaw(fn(): mixed => Prompty::note("Send the \033[1mpear tart\033[22m now.", 'warning'));

    $this->assertSame('!  Send the pear tart now.' . PHP_EOL . '|' . PHP_EOL, $output);
  }

  public function testNoteCustomSymbolAndColor(): void {
    $output = $this->captureRaw(function (): void {
      Prompty::flow(fn(): array => [
        Prompty::note('The kitchen opens at 5pm.'),
      ], symbols_ascii: ['note_info' => 'i'], colors: ['note_info' => "\033[35m"], unicode: FALSE, ansi: TRUE, interactive: FALSE);
    });

    $this->assertStringStartsWith("\033[35mi\033[0m  \033[35mThe kitchen opens at 5pm.\033[0m", $output);
  }

  public function testNoteToOutputTarget(): void {
    $p = $this->createAndSetInstance(['ansi' => FALSE]);
    $written = '';
    $this->setProperty($p, 'cfgOutput', function (string $chunk) use (&$written): void {
      $written .= $chunk;
    });

    $echoed = $this->captureOutput(fn(): mixed => Prompty::note('The kitchen opens at 5pm.'));

    $this->assertSame('', $echoed);
    $this->assertSame("*  The kitchen opens at 5pm.\n|\n", $this->promptyNormalizeOutput($written));
  }

  public function testNotePrintsInNonInteractiveMode(): void {
    $this->createAndSetInstance(['ansi' => FALSE, 'interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::note('The kitchen opens at 5pm.'));

    $this->assertSame("*  The kitchen opens at 5pm.\n|\n", $output);
  }

  public function testNoteReturnsNullOutsideFlow(): void {
    $this->createAndSetInstance();

    $result = 'unset';
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::note('The kitchen opens at 5pm.');
    });

    $this->assertNull($result);
  }

  public function testNoteInvalidTypeThrows(): void {
    $this->createAndSetInstance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Note type "notice" is not valid. Use 1 of: info, success, warning, error.');

    Prompty::note('The kitchen opens at 5pm.', 'notice');
  }

  public function testNoteConditionOutsideFlowThrows(): void {
    $this->createAndSetInstance();

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('A note condition tests the answers collected so far, so it can only be given to a note declared inside the callable passed to Prompty::flow().');

    Prompty::note('The kitchen opens at 5pm.', condition: fn(array $r): bool => TRUE);
  }

  public function testNoteReturnsStepArrayWhileDefining(): void {
    $this->createAndSetInstance();
    $this->setStaticProperty('defining', TRUE);

    $condition = fn(array $r): bool => TRUE;
    $step = Prompty::note('The kitchen opens at 5pm.', 'warning', $condition);

    $this->setStaticProperty('defining', FALSE);

    $this->assertSame(['__note' => 'The kitchen opens at 5pm.', '__type' => 'warning', '__condition' => $condition], $step);
  }

  #[DataProvider('dataProviderNoteInFlow')]
  public function testNoteInFlow(\Closure $steps, string $expected, array $expected_results): void {
    $result = NULL;
    $output = $this->captureOutput(function () use ($steps, &$result): void {
      $result = Prompty::flow($steps, numbering: TRUE, unicode: FALSE, ansi: FALSE, interactive: FALSE);
    });

    $this->assertSame($expected_results, $result);
    $this->assertSame($expected, $output);
  }

  public static function dataProviderNoteInFlow(): \Iterator {
    yield 'between steps' => [
      fn(): array => [
        Prompty::note('The kitchen opens at 5pm.'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::note('Order sent to the kitchen.', type: 'success'),
      ],
      "*  The kitchen opens at 5pm.\n|\n+  Dish name (1)\n|  pear tart\n|\n+  Order sent to the kitchen.\n|\n",
      ['dish' => 'pear tart'],
    ];

    yield 'condition hides the note' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::note('Plums are out of season.', type: 'warning', condition: fn(array $r): bool => $r['dish'] === 'plum compote'),
        Prompty::note('Pears are in season.', condition: fn(array $r): bool => $r['dish'] === 'pear tart'),
      ],
      "+  Dish name (1)\n|  pear tart\n|\n*  Pears are in season.\n|\n",
      ['dish' => 'pear tart'],
    ];

    yield 'notes take no step number' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::note('The pear tart is running low.', type: 'warning'),
        'send' => Prompty::confirm('Send order?'),
      ],
      "+  Dish name (1)\n|  pear tart\n|\n!  The pear tart is running low.\n|\n+  Send order? (2)\n|  Yes\n|\n",
      ['dish' => 'pear tart', 'send' => TRUE],
    ];

    yield 'among children' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert', children: [
          Prompty::note('Desserts are plated by the pastry section.'),
          'warm' => Prompty::confirm('Serve warm?'),
        ]),
      ],
      "+  Course (1)\n|  Dessert\n|\n|  |\n|  *  Desserts are plated by the pastry section.\n|  |  \n|  +  Serve warm? (1.1)\n|     Yes\n|     \n",
      ['course' => 'dessert', 'warm' => TRUE],
    ];

    yield 'last among children' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert', children: [
          'warm' => Prompty::confirm('Serve warm?'),
          Prompty::note('The pear tart is running low.', type: 'warning'),
        ]),
      ],
      "+  Course (1)\n|  Dessert\n|\n|  |\n|  +  Serve warm? (1.1)\n|  |  Yes\n|  |  \n|  !  The pear tart is running low.\n|     \n",
      ['course' => 'dessert', 'warm' => TRUE],
    ];

    yield 'only a note in a section group' => [
      fn(): array => [
        Prompty::section('Service'),
        Prompty::note('The pass is ready.'),
      ],
      "\n#  Service\n|\n*  The pass is ready.\n|\n",
      [],
    ];
  }

  public function testNoteInFlowWithKeyThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Note "The kitchen opens at 5pm." has the key "opening". A note adds no answer to the results, so it takes no key.');

    $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'opening' => Prompty::note('The kitchen opens at 5pm.'),
      ], interactive: FALSE);
    });
  }

  public function testNotePrintsWhileFlowRuns(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'oven' => Prompty::task('Preheating the oven', action: function (): bool {
          Prompty::note('Fan oven, 180°C.');

          return TRUE;
        }),
      ], outro: function (): void {
        Prompty::note('Order sent to the kitchen.', type: 'success');
      }, unicode: FALSE, ansi: FALSE, interactive: FALSE);
    });

    $this->assertSame("o  Preheating the oven\n*  Fan oven, 180°C.\n|\n|  + Done\n|\n+  Order sent to the kitchen.\n|\n", $output);
  }

  public function testDefiningResetsWhenStepsThrow(): void {
    try {
      $this->captureOutput(function (): void {
        Prompty::flow(function (): array {
          throw new \RuntimeException('The menu is missing.');
        }, interactive: FALSE);
      });
    }
    catch (\RuntimeException) {
      // Asserted below.
    }

    $this->assertFalse($this->getStaticProperty('defining'));
  }

  /**
   * Run a callable and return its raw output.
   */
  protected function captureRaw(callable $fn): string {
    ob_start();
    $fn();

    return (string) ob_get_clean();
  }

}
