<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for PromptyFlow::section(), a heading over a group of flow steps.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowSectionTest extends PromptyTestCase {

  #[DataProvider('dataProviderSectionBlankTitleThrows')]
  public function testSectionBlankTitleThrows(string $title): void {
    $this->createAndSetInstance([], TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A section must have a title: it is the heading drawn above its steps.');

    Prompty::flow()->section($title);
  }

  public static function dataProviderSectionBlankTitleThrows(): \Iterator {
    yield 'empty' => [''];
    yield 'spaces' => ['   '];
    yield 'newline' => ["\n"];
  }

  #[DataProvider('dataProviderSectionRenders')]
  public function testSectionRenders(\Closure $build, ?string $intro, string $expected): void {
    $output = $this->captureOutput(function () use ($build, $intro): void {
      $build(Prompty::flow())->run(intro: $intro, unicode: TRUE, interactive: FALSE);
    });

    $this->assertSame($expected, $output);
  }

  public static function dataProviderSectionRenders(): \Iterator {
    yield 'after the intro' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart'),
      'Compose an order',
      "\n┌  Compose an order\n│\n├  The dish\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'opens the tree without an intro' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      NULL,
      "\n┌  The dish\n│\n◆  Dish name\n│  pear tart\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'after a step without an intro' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      NULL,
      "◆  Dish name\n│  pear tart\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'after a skipped step without an intro' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('note', 'Kitchen note')
          ->when(fn(array $r): bool => FALSE)
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart'),
      NULL,
      "\n┌  The dish\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'description' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish', description: 'Name the dish and place it in the meal.')
        ->text('dish', 'Dish name', default: 'pear tart'),
      'Compose an order',
      "\n┌  Compose an order\n│\n├  The dish\n│  Name the dish and place it in the meal.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'multiline title and description' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section("Service\nTable and extras", description: "Where the order is going.\nThe pass reads this from the ticket.")
        ->text('dish', 'Dish name', default: 'pear tart'),
      'Compose an order',
      "\n┌  Compose an order\n│\n├  Service\n│  Table and extras\n│  Where the order is going.\n│  The pass reads this from the ticket.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'multiline title opening the tree' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section("Service\nTable and extras", description: 'Where the order is going.')
        ->text('dish', 'Dish name', default: 'pear tart'),
      NULL,
      "\n┌  Service\n│  Table and extras\n│  Where the order is going.\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'condition hides the heading only' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
          ->when(fn(array $r): bool => FALSE)
        ->text('dish', 'Dish name', default: 'pear tart'),
      'Compose an order',
      "\n┌  Compose an order\n│\n◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'group with every step skipped' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('Finishing')
        ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed'])
          ->when(fn(array $r): bool => FALSE)
        ->confirm('warm', 'Serve warm?')
          ->when(fn(array $r): bool => FALSE)
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      NULL,
      "\n┌  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'consecutive sections' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      'Compose an order',
      "\n┌  Compose an order\n│\n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];

    yield 'trailing section' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service'),
      NULL,
      "◆  Dish name\n│  pear tart\n│\n",
    ];

    yield 'only sections' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->section('Service'),
      NULL,
      '',
    ];

    yield 'step with children in the group' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->section('The dish')
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'main')
          ->children()
            ->select('method', 'Method', options: ['baked' => 'Baked', 'grilled' => 'Grilled'])
          ->end()
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7'),
      NULL,
      "\n┌  The dish\n│\n◆  Course\n│  Main\n│\n│  │\n│  ◆  Method\n│     Baked\n│     \n├  Service\n│\n◆  Table\n│  Table 7\n│\n",
    ];
  }

  public function testSectionAsciiSymbols(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow()
        ->section('The dish', description: 'Name the dish.')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7')
        ->run(unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame("\n#  The dish\n|  Name the dish.\n|\n+  Dish name\n|  pear tart\n|\n#  Service\n|\n+  Table\n|  Table 7\n|\n", $output);
  }

  public function testSectionCustomSymbol(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow()
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7')
        ->run(intro: 'Compose an order', symbols_ascii: ['section' => '>'], unicode: FALSE, interactive: FALSE);
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
      Prompty::flow()
        ->section("The\tdish\033[2J", description: "Name\x07 the dish.")
        ->text('dish', 'Dish name', default: 'pear tart')
        ->run(intro: 'Compose an order', unicode: FALSE, interactive: FALSE);
    });

    $this->assertStringContainsString("#  The dish\n|  Name the dish.\n|\n", $output);
  }

  public function testSectionAddsNothingToResults(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow()
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7')
        ->run(unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'table' => '7'], $result);
  }

  public function testSectionStepsSeeEarlierGroups(): void {
    $seen = [];
    $result = NULL;
    $this->captureOutput(function () use (&$seen, &$result): void {
      $result = Prompty::flow()
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert')
        ->section('Finishing')
          ->when(function (array $r) use (&$seen): bool {
            $seen['section'] = $r;

            return TRUE;
          })
        ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted'], default: ['dusted'])
          ->when(function (array $r) use (&$seen): bool {
            $seen['condition'] = $r;

            return $r['course'] === 'dessert';
          })
        ->section('Service')
        ->multiselect('extras', function (array $r) use (&$seen): string {
          $seen['builder'] = $r;

          return 'Extras';
        }, options: ['honey' => 'Honey', 'almonds' => 'Almonds'], default: ['honey'])
        ->run(unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert'], $seen['section']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert'], $seen['condition']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'finishes' => ['dusted']], $seen['builder']);
    $this->assertSame(['dish' => 'pear tart', 'course' => 'dessert', 'finishes' => ['dusted'], 'extras' => ['honey']], $result);
  }

  public function testSectionNumberingRunsAcrossSections(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::flow()
        ->section('The dish')
        ->text('dish', 'Dish name', default: 'pear tart')
        ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'], default: 'main')
          ->children()
            ->select('method', 'Method', options: ['baked' => 'Baked'])
          ->end()
        ->section('Service')
        ->select('table', 'Table', options: ['4' => 'Table 4', '7' => 'Table 7'], default: '7')
        ->run(numbering: TRUE, unicode: FALSE, interactive: FALSE);
    });

    $this->assertStringContainsString('Dish name (1)', $output);
    $this->assertStringContainsString('Course (2)', $output);
    $this->assertStringContainsString('Method (2.1)', $output);
    $this->assertStringContainsString('Table (3)', $output);
  }

  public function testSectionGroupReadsTypedAnswers(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow()
      ->section('The dish')
      ->text('dish', 'Dish name')
      ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'])
      ->section('Finishing', description: 'Applied just before serving.')
      ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed'])
        ->when(fn(array $r): bool => $r['course'] === 'dessert')
      ->section('Service')
      ->text('note', 'Kitchen note', default: fn(array $r): string => sprintf('%s, sliced', $r['dish']))
      ->run(unicode: FALSE), 'plum compote' . self::KEY_ENTER . self::KEY_ENTER . self::KEY_ENTER);

    $this->assertSame(['dish' => 'plum compote', 'course' => 'main', 'finishes' => NULL, 'note' => 'plum compote, sliced'], $r['result']);
    $this->assertStringContainsString('#  Service', $r['output']);
    $this->assertStringNotContainsString('Finishing', $r['output']);
  }

  public function testSectionFirstHeadingResetsBetweenFlows(): void {
    $build = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->section('The dish')
      ->text('dish', 'Dish name', default: 'pear tart');

    $first = $this->captureOutput(function () use ($build): void {
      $build(Prompty::flow())->run(intro: 'Compose an order', unicode: TRUE, interactive: FALSE);
    });
    $second = $this->captureOutput(function () use ($build): void {
      $build(Prompty::flow())->run(unicode: TRUE, interactive: FALSE);
    });

    $this->assertStringContainsString('├  The dish', $first);
    $this->assertStringStartsWith("\n┌  The dish\n", $second);
  }

  public function testSectionCancelledFlowReturnsNull(): void {
    $r = $this->promptyRun(fn(): ?array => Prompty::flow()
      ->section('The dish')
      ->text('dish', 'Dish name')
      ->run(cancelled: 'Order cancelled.', unicode: FALSE), self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('#  The dish', $r['output']);
    $this->assertStringContainsString('Order cancelled.', $r['output']);
  }

  #[DataProvider('dataProviderSectionInvalidPlacementThrows')]
  public function testSectionInvalidPlacementThrows(\Closure $build, string $message): void {
    $output = $this->captureOutputThrows(\LogicException::class, $message, function () use ($build): void {
      $build(Prompty::flow())->run(unicode: FALSE, interactive: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderSectionInvalidPlacementThrows(): \Iterator {
    yield 'children' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->select('course', 'Course', options: ['main' => 'Main'])
          ->children()
            ->section('Method')
            ->select('method', 'Method', options: ['baked' => 'Baked'])
          ->end(),
      'Section "Method" is among the children of step "course". A section heads top-level steps, so close the children with end() first.',
    ];

    yield 'grandchildren' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->select('course', 'Course', options: ['main' => 'Main'])
          ->children()
            ->select('method', 'Method', options: ['baked' => 'Baked'])
              ->children()
                ->section('Temperature')
              ->end()
          ->end(),
      'Section "Temperature" is among the children of step "method". A section heads top-level steps, so close the children with end() first.',
    ];
  }

}
