<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'validate' callback shared by all 4 widgets.
 *
 * Covers the interactive path, where a rejected answer keeps the widget
 * active with the message drawn under it, and the discovered path, where a
 * rejected answer throws before the widget draws.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetValidateTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected const MESSAGE = 'Check the order before it goes to the kitchen.';

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  /**
   * Builds a text widget call that takes a validator and a context.
   */
  protected static function textWidget(string $default = ''): \Closure {
    return static fn(?\Closure $validate, array $ctx, mixed $discovered = NULL): mixed => Prompty::text('Dish name', default: $default, placeholder: 'pear tart', discovered: $discovered, validate: $validate, ctx: $ctx);
  }

  /**
   * Builds a select widget call that takes a validator and a context.
   *
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   */
  protected static function selectWidget(array $options = self::COURSES): \Closure {
    return static fn(?\Closure $validate, array $ctx, mixed $discovered = NULL): mixed => Prompty::select('Course', options: $options, discovered: $discovered, validate: $validate, ctx: $ctx);
  }

  /**
   * Builds a multiselect widget call that takes a validator and a context.
   */
  protected static function multiselectWidget(): \Closure {
    return static fn(?\Closure $validate, array $ctx, mixed $discovered = NULL): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, discovered: $discovered, validate: $validate, ctx: $ctx);
  }

  /**
   * Builds a confirm widget call that takes a validator and a context.
   */
  protected static function confirmWidget(bool $default = TRUE): \Closure {
    return static fn(?\Closure $validate, array $ctx, mixed $discovered = NULL): mixed => Prompty::confirm('Send order?', default: $default, discovered: $discovered, validate: $validate, ctx: $ctx);
  }

  /**
   * Run a widget with a validator that records every call it receives.
   *
   * @param \Closure $widget
   *   Receives the validator and a context, and calls the widget.
   * @param \Closure $verdict
   *   Receives an answer and returns what the validator returns for it.
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, mixed> $ctx_overrides
   *   Context overrides.
   * @param array<string, mixed> $config
   *   Prompty configuration overrides.
   *
   * @return array{result: mixed, output: string, answers: list<mixed>, results: list<mixed>}
   *   The widget return value, the captured output, and the answers and flow
   *   results the validator received, 1 entry per call.
   */
  protected function runValidated(\Closure $widget, \Closure $verdict, string $keystrokes, array $ctx_overrides = [], array $config = []): array {
    $answers = [];
    $results = [];

    $validate = function (mixed $value, array $flow_results) use (&$answers, &$results, $verdict): mixed {
      $answers[] = $value;
      $results[] = $flow_results;

      return $verdict($value);
    };

    $r = $this->promptyRun(fn(): mixed => $widget($validate, $this->defaultCtx($ctx_overrides)), $keystrokes, $config);

    return ['result' => $r['result'], 'output' => $r['output'], 'answers' => $answers, 'results' => $results];
  }

  #[DataProvider('dataProviderRejectedAnswerKeepsWidgetActive')]
  public function testRejectedAnswerKeepsWidgetActive(\Closure $widget, mixed $rejected, string $keystrokes, mixed $expected, array $expected_answers, string $completed): void {
    $r = $this->runValidated($widget, fn(mixed $value): ?string => $value === $rejected ? self::MESSAGE : NULL, $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertSame($expected_answers, $r['answers']);
    $this->assertSame(1, substr_count($r['output'], '! ' . self::MESSAGE));
    $this->assertSame(1, substr_count($r['output'], $completed));

    $error_at = strpos($r['output'], '! ' . self::MESSAGE);
    $completed_at = strpos($r['output'], $completed);
    $this->assertNotFalse($error_at);
    $this->assertNotFalse($completed_at);
    $this->assertGreaterThan($error_at, $completed_at);
  }

  public static function dataProviderRejectedAnswerKeepsWidgetActive(): \Iterator {
    yield 'text typed answer' => [
      self::textWidget(),
      'pear',
      'pear' . self::KEY_ENTER . ' tart' . self::KEY_ENTER,
      'pear tart',
      ['pear', 'pear tart'],
      "+  Dish name\n|  pear tart\n",
    ];
    yield 'text placeholder taken on an empty submit' => [
      self::textWidget(),
      'pear tart',
      self::KEY_ENTER . 'onion soup' . self::KEY_ENTER,
      'onion soup',
      ['pear tart', 'onion soup'],
      "+  Dish name\n|  onion soup\n",
    ];
    yield 'text default' => [
      self::textWidget('pear tart'),
      'pear tart',
      self::KEY_ENTER . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . 'soup' . self::KEY_ENTER,
      'pear soup',
      ['pear tart', 'pear soup'],
      "+  Dish name\n|  pear soup\n",
    ];
    yield 'select' => [
      self::selectWidget(),
      'starter',
      self::KEY_ENTER . self::KEY_DOWN . self::KEY_ENTER,
      'main',
      ['starter', 'main'],
      "+  Course\n|  Main\n",
    ];
    yield 'multiselect' => [
      self::multiselectWidget(),
      [],
      self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER,
      ['bread'],
      [[], ['bread']],
      "+  Extras\n|  Bread\n",
    ];
    yield 'confirm' => [
      self::confirmWidget(),
      TRUE,
      self::KEY_ENTER . self::KEY_LEFT . self::KEY_ENTER,
      FALSE,
      [TRUE, FALSE],
      "+  Send order?\n|  No\n",
    ];
  }

  #[DataProvider('dataProviderErrorClearsOnlyWhenAnswerChanges')]
  public function testErrorClearsOnlyWhenAnswerChanges(\Closure $widget, string $keystrokes, int $expected_frames): void {
    $r = $this->runValidated($widget, fn(): string => self::MESSAGE, $keystrokes . self::KEY_ENTER . $keystrokes . self::KEY_CTRL_C);

    $this->assertNull($r['result']);
    $this->assertSame($expected_frames, substr_count($r['output'], '! ' . self::MESSAGE));
  }

  public static function dataProviderErrorClearsOnlyWhenAnswerChanges(): \Iterator {
    yield 'text typing clears' => [self::textWidget(), 'x', 1];
    yield 'text backspace clears' => [self::textWidget('pear'), self::KEY_BACKSPACE, 1];
    yield 'text space clears' => [self::textWidget(), self::KEY_SPACE, 1];
    yield 'text ignored key keeps' => [self::textWidget(), self::KEY_TAB, 2];
    yield 'text backspace on empty keeps' => [self::textWidget(), self::KEY_BACKSPACE, 2];
    yield 'select moving focus clears' => [self::selectWidget(), self::KEY_DOWN, 1];
    yield 'select single option keeps' => [self::selectWidget(['main' => 'Main']), self::KEY_DOWN, 2];
    yield 'select ignored key keeps' => [self::selectWidget(), 'x', 2];
    yield 'multiselect toggle clears' => [self::multiselectWidget(), self::KEY_SPACE, 1];
    yield 'multiselect moving focus keeps' => [self::multiselectWidget(), self::KEY_DOWN, 2];
    yield 'confirm toggle clears' => [self::confirmWidget(), self::KEY_LEFT, 1];
    yield 'confirm same answer keeps' => [self::confirmWidget(), 'y', 2];
    yield 'confirm ignored key keeps' => [self::confirmWidget(), 'x', 2];
  }

  public function testResubmitRunsValidatorAgain(): void {
    $verdict = fn(mixed $value): string => $value === 'pear' ? 'Name the whole dish.' : 'Pick a dish from the board.';

    $r = $this->runValidated(self::textWidget(), $verdict, 'pear' . self::KEY_ENTER . self::KEY_ENTER . ' tart' . self::KEY_ENTER . self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertSame(['pear', 'pear', 'pear tart'], $r['answers']);
    $this->assertSame(2, substr_count($r['output'], '! Name the whole dish.'));
    $this->assertSame(1, substr_count($r['output'], '! Pick a dish from the board.'));
  }

  public function testCancelAfterRejectedAnswerDrawsCancelledState(): void {
    $r = $this->runValidated(self::textWidget(), fn(): string => self::MESSAGE, 'pear' . self::KEY_ENTER . self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringEndsWith("o  Dish name\n|  pear (cancelled)\n|\n", $r['output']);
  }

  #[DataProvider('dataProviderErrorRendering')]
  public function testErrorRendering(\Closure $widget, string $keystrokes, array $ctx_overrides, array $config, string $expected): void {
    $r = $this->runValidated($widget, fn(): string => self::MESSAGE, $keystrokes . self::KEY_ENTER . self::KEY_ESCAPE, $ctx_overrides, $config);

    $this->assertStringContainsString($expected, $r['output']);
  }

  public static function dataProviderErrorRendering(): \Iterator {
    yield 'text' => [self::textWidget(), 'pear', [], [], "|  pear█\n|  ! " . self::MESSAGE . "\n|\n"];
    yield 'text nested' => [self::textWidget(), 'pear', self::CTX_AT_DEPTH, [], "|  |  pear█\n|  |  ! " . self::MESSAGE . "\n|  |  \n"];
    yield 'text unicode' => [self::textWidget(), 'pear', [], ['unicode' => TRUE], "│  pear█\n│  ▲ " . self::MESSAGE . "\n│\n"];
    yield 'select' => [self::selectWidget(), '', [], [], "|  ( ) Dessert\n|  ! " . self::MESSAGE . "\n|\n"];
    yield 'select nested' => [self::selectWidget(), '', self::CTX_AT_DEPTH, [], "|  |    ( ) Dessert\n|  |  ! " . self::MESSAGE . "\n|  |  \n"];
    yield 'multiselect' => [self::multiselectWidget(), '', [], [], "|  [ ] Herbs\n|  ! " . self::MESSAGE . "\n|\n"];
    yield 'multiselect nested' => [self::multiselectWidget(), '', self::CTX_AT_DEPTH, [], "|  |    [ ] Herbs\n|  |  ! " . self::MESSAGE . "\n|  |  \n"];
    yield 'confirm' => [self::confirmWidget(), '', [], [], "|  (*) Yes / ( ) No\n|  ! " . self::MESSAGE . "\n|\n"];
    yield 'confirm nested' => [self::confirmWidget(), '', self::CTX_AT_DEPTH, [], "|  |  (*) Yes / ( ) No\n|  |  ! " . self::MESSAGE . "\n|  |  \n"];
    yield 'text error sits above help' => [
      static fn(?\Closure $validate, array $ctx): mixed => Prompty::text('Dish name', help: 'Written on the order ticket.', validate: $validate, ctx: $ctx),
      'pear',
      [],
      [],
      "|  pear█\n|  ! " . self::MESSAGE . "\n|\n|  Written on the order ticket.\n|\n",
    ];
    yield 'select error sits above help nested' => [
      static fn(?\Closure $validate, array $ctx): mixed => Prompty::select('Course', options: self::COURSES, help: 'Arrows to move, enter to confirm.', validate: $validate, ctx: $ctx),
      '',
      self::CTX_AT_DEPTH,
      [],
      "|  |    ( ) Dessert\n|  |  ! " . self::MESSAGE . "\n|  |  \n|  |  Arrows to move, enter to confirm.\n|  |  \n",
    ];
  }

  #[DataProvider('dataProviderRenderError')]
  public function testRenderError(?string $error, array $config, int $depth, array $open, array $expected): void {
    $p = $this->createInstance($config);

    $this->assertSame($expected, $this->callProtectedLines($p, 'renderError', $error, $depth, $open));
  }

  public static function dataProviderRenderError(): \Iterator {
    yield 'no error draws nothing' => [NULL, [], 0, [], []];
    yield 'ansi colors the message red' => ['Too salty.', ['ansi' => TRUE], 0, [], ["\033[90m|\033[0m  \033[31m! Too salty.\033[0m"]];
    yield 'no ansi' => ['Too salty.', ['ansi' => FALSE], 0, [], ['|  ! Too salty.']];
    yield 'nested under an open level' => ['Too salty.', ['ansi' => FALSE], 2, [1 => TRUE], ['|  |     ! Too salty.']];
    yield 'control characters are neutralized' => ["Too\nsalty.\033[2J", ['ansi' => FALSE], 0, [], ['|  ! Too salty.']];
    yield 'configured symbol' => ['Too salty.', ['ansi' => FALSE, 'symbols_ascii' => ['error' => 'x', 'bar' => '|']], 0, [], ['|  x Too salty.']];
  }

  public function testStandaloneValidatorReceivesNoResults(): void {
    $r = $this->runValidated(self::textWidget(), fn(): ?string => NULL, 'pear' . self::KEY_ENTER);

    $this->assertSame('pear', $r['result']);
    $this->assertSame([[]], $r['results']);
  }

  public function testFlowValidatorReceivesEarlierAnswers(): void {
    $received = [];
    $validate = function (array $extras, array $results) use (&$received): ?string {
      $received[] = $results;

      return $results['course'] === 'main' && $extras === [] ? 'A main needs at least one extra.' : NULL;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES),
      'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, validate: $validate),
    ]), self::KEY_DOWN . self::KEY_ENTER . self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['course' => 'main', 'extras' => ['bread']], $r['result']);
    $this->assertSame([['course' => 'main'], ['course' => 'main']], $received);
    $this->assertStringContainsString('! A main needs at least one extra.', $r['output']);
  }

  #[DataProvider('dataProviderValidateThreadsThroughFlowDefinition')]
  public function testValidateThreadsThroughFlowDefinition(\Closure $step): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => ['step' => $step()]), self::KEY_ENTER . self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('! ' . self::MESSAGE, $r['output']);
  }

  public static function dataProviderValidateThreadsThroughFlowDefinition(): \Iterator {
    $reject = static fn(): string => self::MESSAGE;

    yield 'text' => [static fn(): mixed => Prompty::text('Dish name', validate: $reject)];
    yield 'select' => [static fn(): mixed => Prompty::select('Course', options: self::COURSES, validate: $reject)];
    yield 'multiselect' => [static fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, validate: $reject)];
    yield 'confirm' => [static fn(): mixed => Prompty::confirm('Send order?', validate: $reject)];
    yield 'step with a condition' => [static fn(): mixed => Prompty::text('Dish name', validate: $reject, condition: static fn(): bool => TRUE)];
  }

  public function testConditionSkippedStepDoesNotValidate(): void {
    $calls = 0;
    $validate = function () use (&$calls): string {
      $calls++;

      return self::MESSAGE;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', validate: $validate, condition: fn(): bool => FALSE),
    ]));

    $this->assertSame([], $r['result']);
    $this->assertSame(0, $calls);
  }

  #[DataProvider('dataProviderValidatorReturnTreatedAsValid')]
  public function testValidatorReturnTreatedAsValid(?string $returned): void {
    $r = $this->runValidated(self::textWidget(), fn(): ?string => $returned, 'pear' . self::KEY_ENTER);

    $this->assertSame('pear', $r['result']);
    $this->assertStringNotContainsString('! ', $r['output']);
  }

  public static function dataProviderValidatorReturnTreatedAsValid(): \Iterator {
    yield 'null' => [NULL];
    yield 'empty string' => [''];
    yield 'whitespace' => [" \t\n"];
    yield 'only an escape sequence' => ["\033[31m"];
  }

  public function testValidatorMessageIsTrimmed(): void {
    $r = $this->runValidated(self::textWidget(), fn(): string => "  Too salty.\n", 'pear' . self::KEY_ENTER . self::KEY_ESCAPE);

    $this->assertStringContainsString("|  ! Too salty.\n", $r['output']);
  }

  #[DataProvider('dataProviderValidatorReturnTypeRejected')]
  public function testValidatorReturnTypeRejected(mixed $returned, string $type, ?string $discovered): void {
    $stream = $this->installKeystrokes('pear' . self::KEY_ENTER);

    $this->captureOutputThrows(
      \UnexpectedValueException::class,
      sprintf('Validator for "Dish name" returned %s. A validator returns NULL for a valid answer or an error message string.', $type),
      fn(): mixed => Prompty::text('Dish name', discovered: $discovered, validate: fn(): mixed => $returned, ctx: $this->defaultCtx()),
    );

    fclose($stream);
  }

  public static function dataProviderValidatorReturnTypeRejected(): \Iterator {
    foreach (['interactive' => NULL, 'discovered' => 'pear'] as $path => $discovered) {
      yield $path . ' bool true' => [TRUE, 'bool', $discovered];
      yield $path . ' bool false' => [FALSE, 'bool', $discovered];
      yield $path . ' int' => [0, 'int', $discovered];
      yield $path . ' array' => [['Too salty.'], 'array', $discovered];
      yield $path . ' object' => [new \stdClass(), 'stdClass', $discovered];
    }
  }

  #[DataProvider('dataProviderValidatorExceptionPropagates')]
  public function testValidatorExceptionPropagates(?string $discovered): void {
    $stream = $this->installKeystrokes('pear' . self::KEY_ENTER);

    $this->captureOutputThrows(\RuntimeException::class, 'The board is offline.', fn(): mixed => Prompty::text('Dish name',
      discovered: $discovered,
      validate: fn(): never => throw new \RuntimeException('The board is offline.'),
      ctx: $this->defaultCtx(),
    ));

    fclose($stream);
  }

  public static function dataProviderValidatorExceptionPropagates(): \Iterator {
    yield 'interactive' => [NULL];
    yield 'discovered' => ['pear'];
  }

  public function testFlowRestoresStateWhenValidatorThrows(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $this->captureOutputThrows(\UnexpectedValueException::class, 'Validator for "Dish name" returned bool. A validator returns NULL for a valid answer or an error message string.', fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', validate: fn(): bool => FALSE),
    ], unicode: FALSE));

    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  #[DataProvider('dataProviderDiscoveredRejected')]
  public function testDiscoveredRejected(\Closure $widget, mixed $discovered, ?string $ctx_discovered, string $message): void {
    $answers = [];
    $validate = function (mixed $value) use (&$answers): string {
      $answers[] = $value;

      return self::MESSAGE;
    };

    $this->assertWidgetRejects(fn(): mixed => $widget($validate, $this->ctx(['discovered' => $ctx_discovered]), $discovered), $message);
    $this->assertCount(1, $answers);
  }

  public static function dataProviderDiscoveredRejected(): \Iterator {
    $message = static fn(string $value, string $label): string => sprintf('Discovered value "%s" for "%s" is not valid. %s', $value, $label, self::MESSAGE);

    yield 'text argument' => [self::textWidget(), 'pear', NULL, $message('pear', 'Dish name')];
    yield 'text env value' => [self::textWidget(), NULL, 'pear', $message('pear', 'Dish name')];
    yield 'text trimmed' => [self::textWidget(), '  pear  ', NULL, $message('pear', 'Dish name')];
    yield 'text blank takes the placeholder' => [self::textWidget(), '', NULL, $message('pear tart', 'Dish name')];
    yield 'text blank takes the default' => [self::textWidget('onion soup'), ' ', NULL, $message('onion soup', 'Dish name')];
    yield 'select argument' => [self::selectWidget(), 'main', NULL, $message('main', 'Course')];
    yield 'select env value' => [self::selectWidget(), NULL, ' main ', $message('main', 'Course')];
    yield 'multiselect list' => [self::multiselectWidget(), ['olives', 'bread'], NULL, $message('bread,olives', 'Extras')];
    yield 'multiselect env value' => [self::multiselectWidget(), NULL, 'herbs,bread', $message('bread,herbs', 'Extras')];
    yield 'multiselect blank selects nothing' => [self::multiselectWidget(), NULL, '', $message('', 'Extras')];
    yield 'confirm truthy' => [self::confirmWidget(), 'yes', NULL, $message('true', 'Send order?')];
    yield 'confirm falsy env value' => [self::confirmWidget(), NULL, '0', $message('false', 'Send order?')];
    yield 'confirm boolean' => [self::confirmWidget(), FALSE, NULL, $message('false', 'Send order?')];
  }

  #[DataProvider('dataProviderDiscoveredAccepted')]
  public function testDiscoveredAccepted(\Closure $widget, mixed $discovered, mixed $expected): void {
    $answers = [];
    $validate = function (mixed $value) use (&$answers): ?string {
      $answers[] = $value;

      return NULL;
    };

    $r = $this->promptyRun(fn(): mixed => $widget($validate, $this->ctx(), $discovered));

    $this->assertSame($expected, $r['result']);
    $this->assertSame([$expected], $answers);
  }

  public static function dataProviderDiscoveredAccepted(): \Iterator {
    yield 'text' => [self::textWidget(), ' onion soup ', 'onion soup'];
    yield 'select' => [self::selectWidget(), 'dessert', 'dessert'];
    yield 'multiselect' => [self::multiselectWidget(), 'olives,bread', ['bread', 'olives']];
    yield 'confirm' => [self::confirmWidget(), 'no', FALSE];
  }

  #[DataProvider('dataProviderDiscoveredOptionCheckRunsFirst')]
  public function testDiscoveredOptionCheckRunsFirst(\Closure $widget, mixed $discovered, string $message): void {
    $calls = 0;
    $validate = function () use (&$calls): string {
      $calls++;

      return self::MESSAGE;
    };

    $this->assertWidgetRejects(fn(): mixed => $widget($validate, $this->ctx(), $discovered), $message);
    $this->assertSame(0, $calls);
  }

  public static function dataProviderDiscoveredOptionCheckRunsFirst(): \Iterator {
    yield 'select' => [self::selectWidget(), 'pudding', 'Discovered value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'multiselect' => [self::multiselectWidget(), 'bread,pickles', 'Discovered value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
    yield 'confirm' => [self::confirmWidget(), 'maybe', 'Discovered value "maybe" for "Send order?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.'];
  }

  public function testFlowEnvValueRejected(): void {
    $this->setEnvVars(['dish' => 'pear']);
    $stream = $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "pear" for "Dish name" is not valid. Name the whole dish.', fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name', validate: fn(string $dish): ?string => str_contains($dish, ' ') ? NULL : 'Name the whole dish.'),
    ], unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertSame(0, ftell($stream));
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

}
