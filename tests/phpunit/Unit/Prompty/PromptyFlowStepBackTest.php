<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests stepping back to the previous step in a flow.
 *
 * Covers which step reopens, the answers it and later steps start from,
 * what the screen shows afterwards, and the 'back_key' setting.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowStepBackTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Run a flow over keystrokes and capture what it writes.
   *
   * @param \Closure $steps
   *   The flow's steps callable.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param string|null $back_key
   *   The flow's 'back_key', or NULL to keep the configured one.
   *
   * @return array{result: mixed, screen: string, raw: string, unread: string}
   *   The flow's return value, the screen left after every erase, the raw
   *   bytes written and the keystrokes left unread.
   */
  protected function runFlow(\Closure $steps, string $keystrokes, ?string $back_key = NULL): array {
    $stream = $this->installKeystrokes($keystrokes, FALSE);
    $raw = '';
    Prompty::configure(output: function (string $chunk) use (&$raw): void {
      $raw .= $chunk;
    });

    $result = Prompty::flow($steps, intro: 'Kitchen order', unicode: FALSE, back_key: $back_key);
    $unread = (string) stream_get_contents($stream);
    fclose($stream);

    return ['result' => $result, 'screen' => $this->screen($raw), 'raw' => $raw, 'unread' => $unread];
  }

  /**
   * Replay raw output the way a terminal draws it.
   *
   * Applies every "cursor up N rows, clear below" sequence by dropping the
   * last N rows, then strips the remaining escape sequences.
   *
   * @param string $raw
   *   The bytes a flow wrote.
   *
   * @return string
   *   The rows left on screen.
   */
  protected function screen(string $raw): string {
    $rows = [];
    $parts = preg_split('/\033\[(\d+)A\r\033\[J/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

    foreach ($parts as $index => $part) {
      if ($index % 2 === 1) {
        $rows = array_slice($rows, 0, max(0, count($rows) - (int) $part));

        continue;
      }

      $lines = explode("\n", $this->stripAnsi(str_replace(PHP_EOL, "\n", $part)));
      // Every write ends with a newline, which leaves an empty last element.
      array_pop($lines);
      $rows = array_merge($rows, $lines);
    }

    return implode("\n", $rows);
  }

  /**
   * A flow of 3 text steps.
   */
  protected static function textSteps(): \Closure {
    return fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'table' => Prompty::text('Table'),
      'note' => Prompty::text('Note for the kitchen'),
    ];
  }

  #[DataProvider('dataProviderStepBack')]
  public function testStepBack(\Closure $steps, string $keystrokes, string $straight_through, ?array $expected): void {
    $r = $this->runFlow($steps, $keystrokes);
    $direct = $this->runFlow($steps, $straight_through);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($direct['result'], $r['result']);
    $this->assertSame($direct['screen'], $r['screen']);
    $this->assertSame('', $r['unread']);
  }

  public static function dataProviderStepBack(): \Iterator {
    $u = self::KEY_CTRL_U;
    $enter = self::KEY_ENTER;

    yield 'back from the second step, then change the first' => [
      self::textSteps(),
      'Ann' . $enter . '4' . $u . 'e' . $enter . '7' . $enter . 'Hot' . $enter,
      'Anne' . $enter . '7' . $enter . 'Hot' . $enter,
      ['guest' => 'Anne', 'table' => '7', 'note' => 'Hot'],
    ];

    yield 'later answers are kept as defaults' => [
      self::textSteps(),
      'Ann' . $enter . '4' . $enter . $u . $u . $enter . $enter . 'Hot' . $enter,
      'Ann' . $enter . '4' . $enter . 'Hot' . $enter,
      ['guest' => 'Ann', 'table' => '4', 'note' => 'Hot'],
    ];

    yield 'changed answer, later default kept' => [
      self::textSteps(),
      'Ann' . $enter . '4' . $enter . $u . $u . 'e' . $enter . $enter . 'Hot' . $enter,
      'Anne' . $enter . '4' . $enter . 'Hot' . $enter,
      ['guest' => 'Anne', 'table' => '4', 'note' => 'Hot'],
    ];

    yield 'first step ignores the key' => [
      self::textSteps(),
      $u . 'Ann' . $enter . '4' . $enter . 'Hot' . $enter,
      'Ann' . $enter . '4' . $enter . 'Hot' . $enter,
      ['guest' => 'Ann', 'table' => '4', 'note' => 'Hot'],
    ];

    yield 'back twice in a row from the last step' => [
      self::textSteps(),
      'Ann' . $enter . '4' . $enter . 'Ho' . $u . $u . $u . $enter . $enter . 'Hot' . $enter,
      'Ann' . $enter . '4' . $enter . 'Hot' . $enter,
      ['guest' => 'Ann', 'table' => '4', 'note' => 'Hot'],
    ];

    yield 'discovered step is skipped' => [
      fn(): array => [
        'guest' => Prompty::text('Guest name'),
        'table' => Prompty::text('Table', discovered: '12'),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . $u . 'e' . $enter . 'Hot' . $enter,
      'Anne' . $enter . 'Hot' . $enter,
      ['guest' => 'Anne', 'table' => '12', 'note' => 'Hot'],
    ];

    yield 'nothing asked before a discovered first step' => [
      fn(): array => [
        'table' => Prompty::text('Table', discovered: '12'),
        'guest' => Prompty::text('Guest name'),
      ],
      $u . 'Ann' . $enter,
      'Ann' . $enter,
      ['table' => '12', 'guest' => 'Ann'],
    ];

    yield 'first child reopens the parent' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, children: [
          'dish' => Prompty::text('Dish name'),
        ]),
      ],
      self::KEY_DOWN . $enter . 'Stew' . $u . self::KEY_DOWN . $enter . 'Tart' . $enter,
      self::KEY_DOWN . self::KEY_DOWN . $enter . 'Tart' . $enter,
      ['course' => 'dessert', 'dish' => 'Tart'],
    ];

    yield 'sibling after children reopens the parent, not a child' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, children: [
          'dish' => Prompty::text('Dish name'),
        ]),
        'guest' => Prompty::text('Guest name'),
      ],
      $enter . 'Soup' . $enter . $u . self::KEY_DOWN . $enter . $enter . 'Ann' . $enter,
      self::KEY_DOWN . $enter . 'Soup' . $enter . 'Ann' . $enter,
      ['course' => 'main', 'dish' => 'Soup', 'guest' => 'Ann'],
    ];

    yield 'second child reopens the first child' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, children: [
          'dish' => Prompty::text('Dish name'),
          'note' => Prompty::text('Note for the kitchen'),
        ]),
      ],
      $enter . 'Soup' . $enter . $u . 's' . $enter . 'Hot' . $enter,
      $enter . 'Soups' . $enter . 'Hot' . $enter,
      ['course' => 'starter', 'dish' => 'Soups', 'note' => 'Hot'],
    ];

    yield 'changed answer hides a later step' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES),
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, condition: fn(array $r): bool => $r['course'] === 'main'),
        'send' => Prompty::confirm('Send to the kitchen?'),
      ],
      self::KEY_DOWN . $enter . self::KEY_SPACE . $enter . $u . $u . self::KEY_UP . $enter . $enter,
      $enter . $enter,
      ['course' => 'starter', 'send' => TRUE],
    ];

    yield 'builder with new options ignores a previous answer it lacks' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES),
        'dish' => Prompty::step(fn(array $r): mixed => Prompty::select('Dish', options: $r['course'] === 'main' ? ['stew' => 'Stew', 'pie' => 'Pie'] : ['soup' => 'Soup', 'salad' => 'Salad'])),
        'guest' => Prompty::text('Guest name'),
      ],
      $enter . self::KEY_DOWN . $enter . $u . $u . self::KEY_DOWN . $enter . $enter . 'Ann' . $enter,
      self::KEY_DOWN . $enter . $enter . 'Ann' . $enter,
      ['course' => 'main', 'dish' => 'stew', 'guest' => 'Ann'],
    ];

    yield 'section heading stays drawn once' => [
      fn(): array => [
        'guest' => Prompty::text('Guest name'),
        Prompty::section('Kitchen'),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . 'Ho' . $u . 'e' . $enter . 'Hot' . $enter,
      'Anne' . $enter . 'Hot' . $enter,
      ['guest' => 'Anne', 'note' => 'Hot'],
    ];

    yield 'section heading over the reopened step' => [
      fn(): array => [
        Prompty::section('Guest'),
        'guest' => Prompty::text('Guest name'),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . $u . $enter . 'Hot' . $enter,
      'Ann' . $enter . 'Hot' . $enter,
      ['guest' => 'Ann', 'note' => 'Hot'],
    ];

    yield 'children of a parent that asked nothing' => [
      fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, discovered: 'main', children: [
          'dish' => Prompty::text('Dish name'),
        ]),
        'guest' => Prompty::text('Guest name'),
      ],
      'Stew' . $enter . 'An' . $u . 's' . $enter . 'Ann' . $enter,
      'Stews' . $enter . 'Ann' . $enter,
      ['course' => 'main', 'dish' => 'Stews', 'guest' => 'Ann'],
    ];

    yield 'streamed task output is counted once' => [
      fn(): array => [
        'guest' => Prompty::text('Guest name'),
        'ticket' => Prompty::task('Print the ticket', function (): bool {
          Prompty::output(['Printing']);
          echo "Done\n";

          return TRUE;
        }, stream: TRUE),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . 'Ho' . $u . 'e' . $enter . 'Hot' . $enter,
      'Anne' . $enter . 'Hot' . $enter,
      ['guest' => 'Anne', 'ticket' => TRUE, 'note' => 'Hot'],
    ];

    yield 'pause is passed over' => [
      fn(): array => [
        'guest' => Prompty::text('Guest name'),
        'ready' => Prompty::pause('Press any key to continue'),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . 'xHo' . $u . 'e' . $enter . 'xHot' . $enter,
      'Anne' . $enter . 'xHot' . $enter,
      ['guest' => 'Anne', 'ready' => TRUE, 'note' => 'Hot'],
    ];

    yield 'back key at a pause steps back' => [
      fn(): array => [
        'guest' => Prompty::text('Guest name'),
        'ready' => Prompty::pause('Press any key to continue'),
        'note' => Prompty::text('Note for the kitchen'),
      ],
      'Ann' . $enter . $u . 'e' . $enter . 'xHot' . $enter,
      'Anne' . $enter . 'xHot' . $enter,
      ['guest' => 'Anne', 'ready' => TRUE, 'note' => 'Hot'],
    ];

    yield 'back key at a first-step pause continues' => [
      fn(): array => [
        'ready' => Prompty::pause('Press any key to continue'),
        'guest' => Prompty::text('Guest name'),
      ],
      $u . 'Ann' . $enter,
      'xAnn' . $enter,
      ['ready' => TRUE, 'guest' => 'Ann'],
    ];

    yield 'cancel after stepping back cancels the flow' => [
      self::textSteps(),
      'Ann' . $enter . $u . self::KEY_ESCAPE,
      'Ann' . self::KEY_ESCAPE,
      NULL,
    ];
  }

  #[DataProvider('dataProviderWidgetReopensWithItsAnswer')]
  public function testWidgetReopensWithItsAnswer(\Closure $widget, string $answer, mixed $expected): void {
    $steps = fn(): array => [
      'first' => $widget(),
      'note' => Prompty::text('Note for the kitchen'),
    ];

    $r = $this->runFlow($steps, $answer . self::KEY_ENTER . self::KEY_CTRL_U . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    $this->assertSame(['first' => $expected, 'note' => 'Hot'], $r['result']);
  }

  public static function dataProviderWidgetReopensWithItsAnswer(): \Iterator {
    yield 'text' => [static fn(): mixed => Prompty::text('Guest name', default: 'Bob'), self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . 'Ann', 'Ann'];
    yield 'select' => [static fn(): mixed => Prompty::select('Course', options: self::COURSES), self::KEY_DOWN . self::KEY_DOWN, 'dessert'];
    yield 'select with numeric keys' => [static fn(): mixed => Prompty::select('Table', options: [4 => 'Table 4', 7 => 'Table 7', 12 => 'Table 12']), self::KEY_DOWN, '7'];
    yield 'multiselect' => [static fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread']), self::KEY_SPACE . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE, ['herbs']];
    yield 'suggest' => [static fn(): mixed => Prompty::suggest('Dish', options: ['Pear tart', 'Plum compote']), 'Onion soup', 'Onion soup'];
    yield 'confirm' => [static fn(): mixed => Prompty::confirm('Send to the kitchen?'), self::KEY_RIGHT, FALSE];

    // A reopened step starts from the input it was accepted with, so its
    // transform runs once on the way forward.
    yield 'text with a transform' => [static fn(): mixed => Prompty::text('Table', transform: fn(string $value): string => 'Table ' . $value), '4', 'Table 4'];
    yield 'suggest with a transform' => [static fn(): mixed => Prompty::suggest('Dish', options: ['Pear tart'], transform: fn(string $value): string => 'Fresh ' . $value), 'Onion soup', 'Fresh Onion soup'];
    yield 'select with a transform' => [static fn(): mixed => Prompty::select('Course', options: self::COURSES, transform: strtoupper(...)), self::KEY_DOWN, 'MAIN'];
    yield 'multiselect with a transform' => [static fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, transform: fn(array $keys): array => array_map(ucfirst(...), $keys)), self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE, ['Herbs']];
    yield 'confirm with a transform' => [static fn(): mixed => Prompty::confirm('Send to the kitchen?', transform: fn(bool $answer): bool => !$answer), self::KEY_RIGHT, TRUE];
  }

  #[DataProvider('dataProviderWidgetStepsBack')]
  public function testWidgetStepsBack(\Closure $widget, string $answer, mixed $expected): void {
    $steps = fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'second' => $widget(),
    ];

    $r = $this->runFlow($steps, 'Ann' . self::KEY_ENTER . self::KEY_CTRL_U . 'e' . self::KEY_ENTER . $answer . self::KEY_ENTER);

    $this->assertSame(['guest' => 'Anne', 'second' => $expected], $r['result']);
  }

  public static function dataProviderWidgetStepsBack(): \Iterator {
    yield 'text' => [static fn(): mixed => Prompty::text('Table'), '4', '4'];
    yield 'select' => [static fn(): mixed => Prompty::select('Course', options: self::COURSES), self::KEY_DOWN, 'main'];
    yield 'multiselect' => [static fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS), self::KEY_SPACE, ['bread']];
    yield 'suggest' => [static fn(): mixed => Prompty::suggest('Dish', options: ['Pear tart', 'Plum compote']), 'Onion soup', 'Onion soup'];
    yield 'confirm' => [static fn(): mixed => Prompty::confirm('Send to the kitchen?'), self::KEY_RIGHT, FALSE];
  }

  #[DataProvider('dataProviderPreviousAnswerThatNoLongerFits')]
  public function testPreviousAnswerThatNoLongerFits(\Closure $widget, mixed $expected): void {
    $answers = 0;
    $steps = function () use ($widget, &$answers): array {
      return [
        'first' => Prompty::step(function () use ($widget, &$answers): mixed {
          return $widget($answers++);
        }),
        'note' => Prompty::text('Note for the kitchen'),
      ];
    };

    $r = $this->runFlow($steps, self::KEY_ENTER . self::KEY_CTRL_U . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    $this->assertSame(['first' => $expected, 'note' => 'Hot'], $r['result']);
  }

  public static function dataProviderPreviousAnswerThatNoLongerFits(): \Iterator {
    yield 'select whose option is gone' => [
      static fn(int $build): mixed => Prompty::select('Course', options: $build === 0 ? ['starter' => 'Starter'] : ['main' => 'Main']),
      'main',
    ];

    yield 'multiselect whose option is gone' => [
      static fn(int $build): mixed => Prompty::multiselect('Extras', options: $build === 0 ? self::EXTRAS : ['olives' => 'Olives'], default: $build === 0 ? ['bread'] : ['olives']),
      ['olives'],
    ];

    yield 'confirm now built as a text' => [
      static fn(int $build): mixed => $build === 0 ? Prompty::confirm('Send to the kitchen?') : Prompty::text('Note', default: 'Hot'),
      'Hot',
    ];
  }

  public function testLaterStepWithTransformKeepsItsInput(): void {
    $steps = fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'table' => Prompty::text('Table', transform: fn(string $value): string => 'Table ' . $value),
      'note' => Prompty::text('Note for the kitchen'),
    ];

    $r = $this->runFlow($steps, 'Ann' . self::KEY_ENTER . '4' . self::KEY_ENTER . self::KEY_CTRL_U . self::KEY_CTRL_U . 'e' . self::KEY_ENTER . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    $this->assertSame(['guest' => 'Anne', 'table' => 'Table 4', 'note' => 'Hot'], $r['result']);
  }

  public function testReplayEndsAtStepWithoutPreviousAnswer(): void {
    $show = FALSE;
    $steps = fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'table' => Prompty::text('Table', condition: function () use (&$show): bool {
        return $show;
      }),
      'note' => Prompty::text('Note for the kitchen', validate: function () use (&$show): ?string {
        $show = TRUE;

        return NULL;
      }),
      'extra' => Prompty::text('Extra'),
    ];

    $r = $this->runFlow($steps, 'Ann' . self::KEY_ENTER . 'Hot' . self::KEY_ENTER . self::KEY_CTRL_U . '4' . self::KEY_ENTER . self::KEY_ENTER . 'Bread' . self::KEY_ENTER);

    // Answering the note shows the table step, which the first walk skipped.
    // It has no previous answer, so the replay ends there, before the note.
    $this->assertSame(['guest' => 'Ann', 'table' => '4', 'note' => 'Hot', 'extra' => 'Bread'], $r['result']);
    $this->assertSame('', $r['unread']);
  }

  public function testTaskAfterReopenedStepRunsAgain(): void {
    $runs = [];
    $steps = function () use (&$runs): array {
      return [
        'guest' => Prompty::text('Guest name'),
        'ticket' => Prompty::task('Print the ticket', function () use (&$runs): bool {
          $runs[] = Prompty::results()['guest'] ?? NULL;

          return TRUE;
        }),
        'note' => Prompty::text('Note for the kitchen'),
      ];
    };

    $r = $this->runFlow($steps, 'Ann' . self::KEY_ENTER . self::KEY_CTRL_U . 'e' . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    // The task asks nothing, so the key skips it and reopens the guest step.
    $this->assertSame(['guest' => 'Anne', 'ticket' => TRUE, 'note' => 'Hot'], $r['result']);
    $this->assertSame(['Ann', 'Anne'], $runs);
  }

  public function testTaskBeforeReopenedStepIsNotRunAgain(): void {
    $runs = 0;
    $steps = function () use (&$runs): array {
      return [
        'ticket' => Prompty::task('Print the ticket', function () use (&$runs): bool {
          $runs++;

          return TRUE;
        }),
        'guest' => Prompty::text('Guest name'),
        'note' => Prompty::text('Note for the kitchen'),
      ];
    };

    $r = $this->runFlow($steps, 'Ann' . self::KEY_ENTER . self::KEY_CTRL_U . 'e' . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    $this->assertSame(['ticket' => TRUE, 'guest' => 'Anne', 'note' => 'Hot'], $r['result']);
    $this->assertSame(1, $runs);
  }

  public function testEraseMovesUpTheRowsDrawnSinceTheReopenedStep(): void {
    $r = $this->runFlow(self::textSteps(), 'Ann' . self::KEY_ENTER . self::KEY_CTRL_U . self::KEY_ENTER . '4' . self::KEY_ENTER . 'Hot' . self::KEY_ENTER);

    // The completed guest step is 3 rows and the active table step 4.
    $this->assertStringContainsString("\033[7A\r\033[J", $r['raw']);
  }

  #[DataProvider('dataProviderLegendListsTheBackKey')]
  public function testLegendListsTheBackKey(array $config, ?string $expected): void {
    $stream = $this->installKeystrokes('Ann' . self::KEY_ENTER . 'Hot' . self::KEY_ENTER, FALSE);
    $raw = '';
    Prompty::configure(...$config, output: function (string $chunk) use (&$raw): void {
      $raw .= $chunk;
    }, legend: TRUE);

    Prompty::flow(fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'note' => Prompty::text('Note for the kitchen'),
    ], unicode: FALSE);
    fclose($stream);

    $output = $this->stripAnsi($raw);
    [$guest, $note] = explode('Note for the kitchen', $output, 2);

    // The first step has nothing to step back to.
    $this->assertStringNotContainsString(' back', $guest);

    if ($expected === NULL) {
      $this->assertStringNotContainsString(' back', $note);

      return;
    }

    $this->assertStringContainsString('enter confirm - ' . $expected, $note);
  }

  public static function dataProviderLegendListsTheBackKey(): \Iterator {
    yield 'default key' => [[], 'ctrl+u back'];
    yield 'custom key' => [['back_key' => 'ctrl-b'], 'ctrl+b back'];
    yield 'custom label' => [['labels' => ['legend_back' => 'previous step']], 'ctrl+u previous step'];
    yield 'key turned off' => [['back_key' => ''], NULL];
    yield 'label blank' => [['labels' => ['legend_back' => '']], NULL];
  }

  public function testStandaloneWidgetIgnoresTheKey(): void {
    $stream = $this->installKeystrokes(self::KEY_CTRL_U . 'Ann' . self::KEY_ENTER, FALSE);

    $value = NULL;
    $this->captureOutput(function () use (&$value): void {
      $value = Prompty::text('Guest name');
    });

    fclose($stream);

    $this->assertSame('Ann', $value);
  }

  #[DataProvider('dataProviderBackKeySetting')]
  public function testBackKeySetting(?string $back_key, string $keystrokes, array $expected): void {
    $r = $this->runFlow(fn(): array => [
      'guest' => Prompty::text('Guest name'),
      'table' => Prompty::text('Table'),
    ], $keystrokes, $back_key);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderBackKeySetting(): \Iterator {
    $enter = self::KEY_ENTER;

    yield 'default ctrl-u' => [NULL, 'Ann' . $enter . self::KEY_CTRL_U . 'e' . $enter . '4' . $enter, ['guest' => 'Anne', 'table' => '4']];
    yield 'custom key' => ['ctrl-b', 'Ann' . $enter . "\x02" . 'e' . $enter . '4' . $enter, ['guest' => 'Anne', 'table' => '4']];
    yield 'ctrl-u does nothing under a custom key' => ['ctrl-b', 'Ann' . $enter . self::KEY_CTRL_U . '4' . $enter, ['guest' => 'Ann', 'table' => '4']];
    yield 'turned off' => ['', 'Ann' . $enter . self::KEY_CTRL_U . '4' . $enter, ['guest' => 'Ann', 'table' => '4']];
  }

  public function testBackKeyInFlowIsScopedToThatFlow(): void {
    $during = NULL;

    $this->runFlow(function () use (&$during): array {
      $during = Prompty::config()['back_key'];

      return ['guest' => Prompty::text('Guest name', discovered: 'Ann')];
    }, '', 'ctrl-b');

    $this->assertSame('ctrl-b', $during);
    $this->assertSame('ctrl-u', Prompty::config()['back_key']);
  }

  public function testBackKeyConfigured(): void {
    $this->createAndSetInstance();

    Prompty::configure(back_key: 'ctrl-z');
    $this->assertSame('ctrl-z', Prompty::config()['back_key']);

    Prompty::configure(back_key: '');
    $this->assertSame('', Prompty::config()['back_key']);
  }

  #[DataProvider('dataProviderBackKeyRejected')]
  public function testBackKeyRejected(string $back_key): void {
    $this->createAndSetInstance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('Back key "%s" for "back_key" is not valid. Use a Ctrl key from "ctrl-a" to "ctrl-z" other than "ctrl-c", "ctrl-h", "ctrl-i", "ctrl-j" and "ctrl-m", or "" to turn stepping back off.', $back_key));

    Prompty::configure(back_key: $back_key);
  }

  public static function dataProviderBackKeyRejected(): \Iterator {
    yield 'cancel key' => ['ctrl-c'];
    yield 'backspace' => ['ctrl-h'];
    yield 'tab' => ['ctrl-i'];
    yield 'enter as ctrl-j' => ['ctrl-j'];
    yield 'enter as ctrl-m' => ['ctrl-m'];
    yield 'plain letter' => ['u'];
    yield 'uppercase' => ['CTRL-U'];
    yield 'digit' => ['ctrl-1'];
    yield 'named key' => ['escape'];
  }

  public function testRejectedBackKeyLeavesConfigurationUnchanged(): void {
    $this->createAndSetInstance();

    try {
      Prompty::configure(width: 50, back_key: 'ctrl-c');
    }
    catch (\InvalidArgumentException) {
      // The rejection is the point; the configuration is checked below.
    }

    $this->assertSame('ctrl-u', Prompty::config()['back_key']);
    $this->assertSame(80, Prompty::config()['width']);
  }

  #[DataProvider('dataProviderReadKeyNamesControlBytes')]
  public function testReadKeyNamesControlBytes(string $byte, string $expected): void {
    $stream = $this->installKeystrokes($byte);
    $p = $this->getStaticProperty('instance');
    $this->assertInstanceOf(Prompty::class, $p);

    $this->assertSame($expected, $this->callProtected($p, 'readKey'));

    fclose($stream);
  }

  public static function dataProviderReadKeyNamesControlBytes(): \Iterator {
    yield 'ctrl-a' => ["\x01", 'ctrl-a'];
    yield 'ctrl-b' => ["\x02", 'ctrl-b'];
    yield 'ctrl-u' => ["\x15", 'ctrl-u'];
    yield 'ctrl-z' => ["\x1a", 'ctrl-z'];
    yield 'ctrl-c stays cancel' => ["\x03", 'ctrl-c'];
    yield 'ctrl-h stays backspace' => ["\x08", 'backspace'];
    yield 'tab' => ["\t", 'tab'];
    yield 'line feed' => ["\n", 'enter'];
    yield 'carriage return' => ["\r", 'enter'];
    yield 'nul stays a byte' => ["\x00", "\x00"];
    yield 'file separator stays a byte' => ["\x1c", "\x1c"];
    yield 'printable letter' => ['u', 'u'];
  }

}
