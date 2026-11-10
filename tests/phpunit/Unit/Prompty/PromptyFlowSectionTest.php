<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::section(), a heading over a group of flow steps.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowSectionTest extends PromptyTestCase {

  public function testSectionReturnsStepArray(): void {
    $this->createAndSetInstance([], TRUE);

    $condition = fn(array $r): bool => TRUE;

    $section = Prompty::section('The dish', description: 'Name the dish.', condition: $condition);

    $this->assertSame(['__section' => 'The dish', '__description' => 'Name the dish.', '__condition' => $condition], $section);
  }

  public function testSectionDefaultsToNoDescriptionAndNoCondition(): void {
    $this->createAndSetInstance([], TRUE);

    $section = Prompty::section('The dish');

    $this->assertSame(['__section' => 'The dish', '__description' => '', '__condition' => NULL], $section);
  }

  public function testSectionOutsideFlowThrows(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Prompty::section() declares a flow heading, so it can only be called inside the callable passed to Prompty::flow().');

    Prompty::section('The dish');
  }

  #[DataProvider('dataProviderSectionBlankTitleThrows')]
  public function testSectionBlankTitleThrows(string $title): void {
    $this->createAndSetInstance([], TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A section must have a title: it is the heading drawn above its steps.');

    Prompty::section($title);
  }

  public static function dataProviderSectionBlankTitleThrows(): \Iterator {
    yield 'empty' => [''];
    yield 'spaces' => ['   '];
    yield 'newline' => ["\n"];
  }

  #[DataProvider('dataProviderSectionRenders')]
  public function testSectionRenders(\Closure $steps, ?string $intro, string $expected): void {
    $output = $this->captureOutput(function () use ($steps, $intro): void {
      Prompty::flow($steps, intro: $intro, unicode: TRUE, interactive: FALSE);
    });

    $this->assertSame($expected, $output);
  }

  public static function dataProviderSectionRenders(): \Iterator {
    yield 'after the intro' => [
      fn(): array => [
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      'Compose an order',
      "\n┌  Compose an order\n│\n├  The dish\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'opens the tree without an intro' => [
      fn(): array => [
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ],
      NULL,
      "\n┌  The dish\n│\n◆  Dish name\n│  pear tart\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'after a step without an intro' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ],
      NULL,
      "◆  Dish name\n│  pear tart\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'after a skipped step without an intro' => [
      fn(): array => [
        'note' => Prompty::text('Kitchen note', condition: fn(array $r): bool => FALSE),
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      NULL,
      "\n┌  The dish\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'description' => [
      fn(): array => [
        Prompty::section('The dish', description: 'Name the dish and place it in the meal.'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      'Compose an order',
      "\n┌  Compose an order\n│\n├  The dish\n│  Name the dish and place it in the meal.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'multiline title and description' => [
      fn(): array => [
        Prompty::section("Service\nTable and extras", description: "Where the order is going.\nThe pass reads this from the ticket."),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      'Compose an order',
      "\n┌  Compose an order\n│\n├  Service\n│  Table and extras\n│  Where the order is going.\n│  The pass reads this from the ticket.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'multiline title opening the tree' => [
      fn(): array => [
        Prompty::section("Service\nTable and extras", description: 'Where the order is going.'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      NULL,
      "\n┌  Service\n│  Table and extras\n│  Where the order is going.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'condition hides the heading only' => [
      fn(): array => [
        Prompty::section('The dish', condition: fn(array $r): bool => FALSE),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ],
      'Compose an order',
      "\n┌  Compose an order\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'group with every step skipped' => [
      fn(): array => [
        Prompty::section('Finishing'),
        'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed'], condition: fn(array $r): bool => FALSE),
        'warm' => Prompty::confirm('Serve warm?', condition: fn(array $r): bool => FALSE),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ],
      NULL,
      "\n┌  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'consecutive sections' => [
      fn(): array => [
        Prompty::section('The dish'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ],
      'Compose an order',
      "\n┌  Compose an order\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'trailing section' => [
      fn(): array => [
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
      ],
      NULL,
      "◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'only sections' => [
      fn(): array => [
        Prompty::section('The dish'),
        Prompty::section('Service'),
      ],
      NULL,
      '',
    ];

    yield 'step with children in the group' => [
      fn(): array => [
        Prompty::section('The dish'),
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'main', children: [
          'method' => Prompty::select('Method', options: ['baked' => 'Baked', 'grilled' => 'Grilled']),
        ]),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ],
      NULL,
      "\n┌  The dish\n│\n◆  Course\n│  Main\n│\n│  │\n│  ◆  Method\n│     Baked\n│     \n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];
  }

  public function testSectionAsciiSymbols(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        Prompty::section('The dish', description: 'Name the dish.'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ], unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame("\n#  The dish\n|  Name the dish.\n|\n+  Dish name\n|  pear tart\n|\n#  Service\n|\n+  Table\n|  Table 7\n|\n", $output);
  }

  public function testSectionCustomSymbol(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ], intro: 'Compose an order', symbols_ascii: ['section' => '>'], unicode: FALSE, interactive: FALSE);
    });

    $this->assertStringContainsString("|\n>  Service\n|\n", $output);
  }

  public function testSectionColors(): void {
    $p = $this->createInstance(['unicode' => FALSE, 'ansi' => TRUE]);

    $lines = $this->callProtectedLines($p, 'renderSection', "Service\nTable and extras", 'Where the order is going.', FALSE);

    $this->assertSame([
      "\033[90m#\033[0m  \033[1mService\033[0m",
      "\033[90m|\033[0m  \033[1mTable and extras\033[0m",
      "\033[90m|\033[0m  \033[2mWhere the order is going.\033[0m",
      "\033[90m|\033[0m",
    ], $lines);
  }

  public function testSectionTextIsPrintable(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        Prompty::section("The\tdish\033[2J", description: "Name\x07 the dish."),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
      ], intro: 'Compose an order', unicode: FALSE, interactive: FALSE);
    });

    $this->assertStringContainsString("#  The dish\n|  Name the dish.\n|\n", $output);
  }

  public function testSectionAddsNothingToResults(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ], unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'table' => '7'], $result);
  }

  public function testSectionStepsSeeEarlierGroups(): void {
    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      $result = Prompty::flow(function () use (&$seen): array {
        return [
          Prompty::section('The dish'),
          'dish' => Prompty::text('Dish name', default: 'pear tart'),
          'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert'),
          Prompty::section('Finishing', condition: function (array $r) use (&$seen): bool {
            $seen['section'] = $r;

            return TRUE;
          }),
          'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted'], default: ['dusted'], condition: function (array $r) use (&$seen): bool {
            $seen['condition'] = $r;

            return $r['course'] === 'dessert';
          }),
          Prompty::section('Service'),
          'extras' => Prompty::step(function (array $r) use (&$seen): mixed {
            $seen['builder'] = $r;

            return Prompty::multiselect('Extras', options: ['honey' => 'Honey', 'almonds' => 'Almonds'], default: ['honey']);
          }),
        ];
      }, unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert'], $seen['section']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert'], $seen['condition']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'finishes' => ['dusted']], $seen['builder']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'finishes' => ['dusted'], 'extras' => ['honey']], $result);
  }

  public function testSectionNumberingRunsAcrossSections(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name', default: 'pear tart'),
        'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'main', children: [
          'method' => Prompty::select('Method', options: ['baked' => 'Baked']),
        ]),
        Prompty::section('Service'),
        'table' => Prompty::select('Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      ], numbering: TRUE, unicode: FALSE, interactive: FALSE);
    });

    $this->assertStringContainsString('Dish name (1)', $output);
    $this->assertStringContainsString('Course (2)', $output);
    $this->assertStringContainsString('Method (2.1)', $output);
    $this->assertStringContainsString('Table (3)', $output);
  }

  public function testSectionGroupReadsTypedAnswers(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow(fn(): array => [
      Prompty::section('The dish'),
      'dish' => Prompty::text('Dish name'),
      'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
      Prompty::section('Finishing', description: 'Applied just before serving.'),
      'finishes' => Prompty::multiselect('Finishes', options: ['glazed' => 'Glazed'], condition: fn(array $r): bool => $r['course'] === 'dessert'),
      Prompty::section('Service'),
      'note' => Prompty::step(fn(array $r): mixed => Prompty::text('Kitchen note', default: sprintf('%s, sliced', $r['dish']))),
    ], unicode: FALSE), 'plum compote' . self::KEY_ENTER . self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'plum compote', 'course' => 'main', 'note' => 'plum compote, sliced'], $r['result']);
    $this->assertStringContainsString('#  Service', $r['output']);
    $this->assertStringNotContainsString('Finishing', $r['output']);
  }

  public function testSectionFirstHeadingResetsBetweenFlows(): void {
    $steps = fn(): array => [
      Prompty::section('The dish'),
      'dish' => Prompty::text('Dish name', default: 'pear tart'),
    ];

    $first = $this->captureOutput(function () use ($steps): void {
      Prompty::flow($steps, intro: 'Compose an order', unicode: TRUE, interactive: FALSE);
    });
    $second = $this->captureOutput(function () use ($steps): void {
      Prompty::flow($steps, unicode: TRUE, interactive: FALSE);
    });

    $this->assertStringContainsString('├  The dish', $first);
    $this->assertStringStartsWith("\n┌  The dish\n", $second);
  }

  public function testSectionCancelledFlowReturnsNull(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow(fn(): array => [
      Prompty::section('The dish'),
      'dish' => Prompty::text('Dish name'),
    ], cancelled: 'Order cancelled.', unicode: FALSE), self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('#  The dish', $r['output']);
    $this->assertStringContainsString('Order cancelled.', $r['output']);
  }

  #[DataProvider('dataProviderSectionInvalidPlacementThrows')]
  public function testSectionInvalidPlacementThrows(\Closure $steps, string $message): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, function () use ($steps): void {
      Prompty::flow($steps, unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderSectionInvalidPlacementThrows(): \Iterator {
    yield 'string key' => [
      fn(): array => [
        'heading' => Prompty::section('The dish'),
        'dish' => Prompty::text('Dish name'),
      ],
      'Section "The dish" has the key "heading". A section adds no answer to the results, so it takes no key.',
    ];

    // The children parameters are typed with string keys, so these steps are
    // built as the step arrays the widgets return.
    yield 'children' => [
      fn(): array => [
        'course' => ['__call' => Prompty::select('Course', options: ['main' => 'Main']), '__condition' => NULL, '__children' => [
          Prompty::section('Method'),
          'method' => Prompty::select('Method', options: ['baked' => 'Baked']),
        ]],
      ],
      'Section "Method" is among the children of a step. A section heads top-level steps, so it can only appear in the steps array of the flow itself.',
    ];

    yield 'grandchildren' => [
      fn(): array => [
        'course' => Prompty::step(fn(array $r): mixed => Prompty::select('Course', options: ['main' => 'Main']), children: [
          'method' => ['__call' => Prompty::select('Method', options: ['baked' => 'Baked']), '__condition' => NULL, '__children' => [
            Prompty::section('Temperature'),
          ]],
        ]),
      ],
      'Section "Temperature" is among the children of a step. A section heads top-level steps, so it can only appear in the steps array of the flow itself.',
    ];
  }

  public function testSectionDoesNotClaimStepKeys(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        Prompty::section('The dish'),
        'course' => ['__call' => Prompty::select('Course', options: ['main' => 'Main']), '__condition' => NULL, '__children' => [
          0 => Prompty::select('Method', options: ['baked' => 'Baked']),
        ]],
      ], unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame(['course' => 'main', 0 => 'baked'], $result);
  }

}
