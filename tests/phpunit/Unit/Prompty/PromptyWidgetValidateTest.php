<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'validate' callback shared by all 5 widgets.
 *
 * Covers the interactive path, where a rejected answer keeps the widget
 * active with the message drawn under it, and the discovered path, where a
 * rejected answer throws before the widget draws.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetValidateTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected const array DISHES = ['Pear Tart', 'Apple Crumble', 'Onion Soup'];

  protected const string MESSAGE = 'Check the order before it goes to the kitchen.';

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const array CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

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
   * Builds a suggest widget call that takes a validator and a context.
   *
   * @param array<int|string, string>|\Closure $options
   *   The suggestions, or a closure that returns them.
   * @param string $default
   *   Text seeded into the input buffer.
   */
  protected static function suggestWidget(array|\Closure $options = self::DISHES, string $default = ''): \Closure {
    return static fn(?\Closure $validate, array $ctx, mixed $discovered = NULL): mixed => Prompty::suggest('Dish', options: $options, default: $default, placeholder: 'pear tart', discovered: $discovered, validate: $validate, ctx: $ctx);
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
    yield 'suggest typed answer' => [
      self::suggestWidget(),
      'pear',
      'pear' . self::KEY_ENTER . ' tart' . self::KEY_ENTER,
      'pear tart',
      ['pear', 'pear tart'],
      "+  Dish\n|  pear tart\n",
    ];
    yield 'suggest focused suggestion' => [
      self::suggestWidget(),
      'Pear Tart',
      self::KEY_DOWN . self::KEY_ENTER . self::KEY_DOWN . self::KEY_ENTER,
      'Apple Crumble',
      ['Pear Tart', 'Apple Crumble'],
      "+  Dish\n|  Apple Crumble\n",
    ];
    yield 'suggest placeholder taken on an empty submit' => [
      self::suggestWidget(),
      'pear tart',
      self::KEY_ENTER . 'onion soup' . self::KEY_ENTER,
      'onion soup',
      ['pear tart', 'onion soup'],
      "+  Dish\n|  onion soup\n",
    ];
    yield 'suggest default' => [
      self::suggestWidget(default: 'Pear Tart'),
      'Pear Tart',
      self::KEY_ENTER . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE . 'Soup' . self::KEY_ENTER,
      'Pear Soup',
      ['Pear Tart', 'Pear Soup'],
      "+  Dish\n|  Pear Soup\n",
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
    yield 'suggest typing clears' => [self::suggestWidget(), 'x', 1];
    yield 'suggest backspace clears' => [self::suggestWidget(default: 'pear'), self::KEY_BACKSPACE, 1];
    yield 'suggest moving focus clears' => [self::suggestWidget(), self::KEY_DOWN, 1];
    yield 'suggest moving focus with no suggestions keeps' => [self::suggestWidget(static fn(): array => []), self::KEY_DOWN, 2];
    yield 'suggest ignored key keeps' => [self::suggestWidget(), self::KEY_TAB, 2];
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
    yield 'suggest' => [self::suggestWidget(), 'pear', [], [], "|  pear█\n|  Pear Tart\n|  ! " . self::MESSAGE . "\n|\n"];
    yield 'suggest nested' => [self::suggestWidget(), 'pear', self::CTX_AT_DEPTH, [], "|  |  pear█\n|  |    Pear Tart\n|  |  ! " . self::MESSAGE . "\n|  |  \n"];
    yield 'suggest with no suggestions' => [self::suggestWidget(), 'x', [], [], "|  x█\n|  ! " . self::MESSAGE . "\n|\n"];
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
    yield 'suggest error sits above help' => [
      static fn(?\Closure $validate, array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, help: 'Arrows to pick a suggestion.', validate: $validate, ctx: $ctx),
      'pear',
      [],
      [],
      "|  Pear Tart\n|  ! " . self::MESSAGE . "\n|\n|  Arrows to pick a suggestion.\n|\n",
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

  #[DataProvider('dataProviderRenderFailed')]
  public function testRenderFailed(string $label, string $value, array $config, int $depth, array $open, array $expected): void {
    $p = $this->createInstance($config);

    $this->assertSame($expected, $this->callProtectedLines($p, 'renderFailed', $label, $value, 'Too salty.', $depth, $open));
  }

  public static function dataProviderRenderFailed(): \Iterator {
    yield 'value and message' => ['Dish name', 'pear', ['ansi' => FALSE], 0, [], ['o  Dish name', '|  pear', '|  ! Too salty.', '|']];
    yield 'empty value draws no value row' => ['Dish name', '', ['ansi' => FALSE], 0, [], ['o  Dish name', '|  ! Too salty.', '|']];
    yield 'ansi colors the label symbol and the message red' => ['Dish name', 'pear', ['ansi' => TRUE], 0, [], [
      "\033[31mo\033[0m  Dish name",
      "\033[90m|\033[0m  \033[2mpear\033[0m",
      "\033[90m|\033[0m  \033[31m! Too salty.\033[0m",
      "\033[90m|\033[0m",
    ]];
    yield 'nested under an open level' => ['Dish name', 'pear', ['ansi' => FALSE], 2, [1 => TRUE], [
      '|  |  o  Dish name',
      '|  |     pear',
      '|  |     ! Too salty.',
      '|  |     ',
    ]];
    yield 'control characters are neutralized' => ["Dish\033[2J name", "pe\nar", ['ansi' => FALSE], 0, [], ['o  Dish name', '|  pe ar', '|  ! Too salty.', '|']];
  }

  public function testStandaloneDiscoveredRejectedDrawn(): void {
    $stream = $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "pear" for "Dish name" is not valid. ' . self::MESSAGE, fn(): mixed => Prompty::text('Dish name', discovered: 'pear', validate: fn(): string => self::MESSAGE), TRUE);

    $this->assertSame(self::failedOutput('Dish name', 'pear', self::MESSAGE), $output);
    $this->assertSame(0, ftell($stream));
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

    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->select('course', 'Course', options: self::COURSES)
      ->multiselect('extras', 'Extras', options: self::EXTRAS, validate: $validate)
      ->run(), self::KEY_DOWN . self::KEY_ENTER . self::KEY_ENTER . self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['course' => 'main', 'extras' => ['bread']], $r['result']);
    $this->assertSame([['course' => 'main'], ['course' => 'main']], $received);
    $this->assertStringContainsString('! A main needs at least one extra.', $r['output']);
  }

  public function testFlowSuggestValidatorReceivesEarlierAnswers(): void {
    $received = [];
    $validate = function (string $dish, array $results) use (&$received): ?string {
      $received[] = $results;

      return $results['course'] === 'dessert' && $dish === 'Onion Soup' ? 'Onion Soup is not a dessert.' : NULL;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->select('course', 'Course', options: self::COURSES)
      ->suggest('dish', 'Dish', options: self::DISHES, validate: $validate)
      ->run(), self::KEY_UP . self::KEY_ENTER . 'soup' . self::KEY_DOWN . self::KEY_ENTER . self::KEY_UP . self::KEY_ENTER);

    $this->assertSame(['course' => 'dessert', 'dish' => 'soup'], $r['result']);
    $this->assertSame([['course' => 'dessert'], ['course' => 'dessert']], $received);
    $this->assertStringContainsString('! Onion Soup is not a dessert.', $r['output']);
  }

  public function testFlowSuggestEnvValueRejected(): void {
    $this->setEnvVars(['dish' => 'Lentil Stew']);
    $stream = $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "Lentil Stew" for "Dish" is not valid. Pick a dish from the menu.', fn(): mixed => Prompty::flow()
      ->suggest('dish', 'Dish', options: self::DISHES, validate: fn(string $dish): ?string => in_array($dish, self::DISHES, TRUE) ? NULL : 'Pick a dish from the menu.')
      ->run(unicode: FALSE), TRUE);

    $this->assertSame(self::failedOutput('Dish', 'Lentil Stew', 'Pick a dish from the menu.') . "#  Failed\n\n", $output);
    $this->assertSame(0, ftell($stream));
    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  #[DataProvider('dataProviderValidateThreadsThroughFlowBuilder')]
  public function testValidateThreadsThroughFlowBuilder(\Closure $build): void {
    $r = $this->promptyRun(fn(): mixed => $build(Prompty::flow())->run(), self::KEY_ENTER . self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('! ' . self::MESSAGE, $r['output']);
  }

  public static function dataProviderValidateThreadsThroughFlowBuilder(): \Iterator {
    $reject = static fn(): string => self::MESSAGE;

    yield 'text' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('step', 'Dish name', validate: $reject)];
    yield 'select' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->select('step', 'Course', options: self::COURSES, validate: $reject)];
    yield 'multiselect' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->multiselect('step', 'Extras', options: self::EXTRAS, validate: $reject)];
    yield 'suggest' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->suggest('step', 'Dish', options: self::DISHES, validate: $reject)];
    yield 'confirm' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->confirm('step', 'Send order?', validate: $reject)];
    yield 'step with a condition' => [static fn(PromptyFlow $flow): PromptyFlow => $flow->text('step', 'Dish name', validate: $reject)->when(static fn(): bool => TRUE)];
  }

  public function testConditionSkippedStepDoesNotValidate(): void {
    $calls = 0;
    $validate = function () use (&$calls): string {
      $calls++;

      return self::MESSAGE;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->text('dish', 'Dish name', validate: $validate)
        ->when(fn(): bool => FALSE)
      ->run());

    $this->assertSame(['dish' => NULL], $r['result']);
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
      FALSE,
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

    $this->captureOutputThrows(\UnexpectedValueException::class, 'Validator for "Dish name" returned bool. A validator returns NULL for a valid answer or an error message string.', fn(): mixed => Prompty::flow()
      ->text('dish', 'Dish name', validate: fn(): bool => FALSE)
      ->run(unicode: FALSE));

    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  #[DataProvider('dataProviderDiscoveredRejected')]
  public function testDiscoveredRejected(\Closure $widget, mixed $discovered, ?string $ctx_discovered, string $message, string $output): void {
    $answers = [];
    $validate = function (mixed $value) use (&$answers): string {
      $answers[] = $value;

      return self::MESSAGE;
    };

    $this->assertWidgetRejects(fn(): mixed => $widget($validate, $this->ctx(['discovered' => $ctx_discovered]), $discovered), $message, output: $output);
    $this->assertCount(1, $answers);
  }

  public static function dataProviderDiscoveredRejected(): \Iterator {
    $message = static fn(string $value, string $label): string => sprintf('Discovered value "%s" for "%s" is not valid. %s', $value, $label, self::MESSAGE);
    $drawn = static fn(string $label, string $value): string => self::failedOutput($label, $value, self::MESSAGE);

    yield 'text argument' => [self::textWidget(), 'pear', NULL, $message('pear', 'Dish name'), $drawn('Dish name', 'pear')];
    yield 'text env value' => [self::textWidget(), NULL, 'pear', $message('pear', 'Dish name'), $drawn('Dish name', 'pear')];
    yield 'text trimmed' => [self::textWidget(), '  pear  ', NULL, $message('pear', 'Dish name'), $drawn('Dish name', 'pear')];
    yield 'text blank takes the placeholder' => [self::textWidget(), '', NULL, $message('pear tart', 'Dish name'), $drawn('Dish name', 'pear tart')];
    yield 'text blank takes the default' => [self::textWidget('onion soup'), ' ', NULL, $message('onion soup', 'Dish name'), $drawn('Dish name', 'onion soup')];
    yield 'select argument' => [self::selectWidget(), 'main', NULL, $message('main', 'Course'), $drawn('Course', 'Main')];
    yield 'select env value' => [self::selectWidget(), NULL, ' main ', $message('main', 'Course'), $drawn('Course', 'Main')];
    yield 'multiselect list' => [self::multiselectWidget(), ['olives', 'bread'], NULL, $message('bread,olives', 'Extras'), $drawn('Extras', 'Bread, Olives')];
    yield 'multiselect env value' => [self::multiselectWidget(), NULL, 'herbs,bread', $message('bread,herbs', 'Extras'), $drawn('Extras', 'Bread, Herbs')];
    yield 'multiselect blank selects nothing' => [self::multiselectWidget(), NULL, '', $message('', 'Extras'), $drawn('Extras', 'None')];
    yield 'suggest argument' => [self::suggestWidget(), 'Pear Tart', NULL, $message('Pear Tart', 'Dish'), $drawn('Dish', 'Pear Tart')];
    yield 'suggest argument not among the suggestions' => [self::suggestWidget(), 'lentil stew', NULL, $message('lentil stew', 'Dish'), $drawn('Dish', 'lentil stew')];
    yield 'suggest env value' => [self::suggestWidget(), NULL, ' Onion Soup ', $message('Onion Soup', 'Dish'), $drawn('Dish', 'Onion Soup')];
    yield 'suggest blank takes the placeholder' => [self::suggestWidget(), '', NULL, $message('pear tart', 'Dish'), $drawn('Dish', 'pear tart')];
    yield 'suggest blank takes the default' => [self::suggestWidget(default: 'Apple Crumble'), ' ', NULL, $message('Apple Crumble', 'Dish'), $drawn('Dish', 'Apple Crumble')];
    yield 'confirm truthy' => [self::confirmWidget(), 'yes', NULL, $message('true', 'Send order?'), $drawn('Send order?', 'Yes')];
    yield 'confirm falsy env value' => [self::confirmWidget(), NULL, '0', $message('false', 'Send order?'), $drawn('Send order?', 'No')];
    yield 'confirm boolean' => [self::confirmWidget(), FALSE, NULL, $message('false', 'Send order?'), $drawn('Send order?', 'No')];
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
    yield 'suggest' => [self::suggestWidget(), ' Onion Soup ', 'Onion Soup'];
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

  #[DataProvider('dataProviderNonInteractiveAnswerRejected')]
  public function testNonInteractiveAnswerRejected(\Closure $widget, mixed $discovered, string $message, string $expected): void {
    $stream = $this->installKeystrokes(self::KEY_ENTER);
    Prompty::configure(interactive: FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => $widget(fn(): string => self::MESSAGE, $this->ctx(), $discovered), TRUE);

    $this->assertSame($expected, $output);
    $this->assertSame(0, ftell($stream));
    fclose($stream);
  }

  public static function dataProviderNonInteractiveAnswerRejected(): \Iterator {
    $message = static fn(string $parameter, string $value, string $label): string => sprintf('%s value "%s" for "%s" is not valid. %s', $parameter, $value, $label, self::MESSAGE);
    $drawn = static fn(string $label, string $value): string => self::failedOutput($label, $value, self::MESSAGE);

    yield 'text default' => [self::textWidget('pear'), NULL, $message('Default', 'pear', 'Dish name'), $drawn('Dish name', 'pear')];
    yield 'text empty default ignores the placeholder' => [self::textWidget(), NULL, $message('Default', '', 'Dish name'), $drawn('Dish name', '')];
    yield 'text discovered' => [self::textWidget('pear'), 'onion', $message('Discovered', 'onion', 'Dish name'), $drawn('Dish name', 'onion')];
    yield 'select first option' => [self::selectWidget(), NULL, $message('Default', 'starter', 'Course'), $drawn('Course', 'Starter')];
    yield 'select discovered' => [self::selectWidget(), 'main', $message('Discovered', 'main', 'Course'), $drawn('Course', 'Main')];
    yield 'multiselect empty default' => [self::multiselectWidget(), NULL, $message('Default', '', 'Extras'), $drawn('Extras', 'None')];
    yield 'multiselect discovered' => [self::multiselectWidget(), 'herbs', $message('Discovered', 'herbs', 'Extras'), $drawn('Extras', 'Herbs')];
    yield 'suggest default' => [self::suggestWidget(default: 'Pear Tart'), NULL, $message('Default', 'Pear Tart', 'Dish'), $drawn('Dish', 'Pear Tart')];
    yield 'suggest empty default ignores the placeholder' => [self::suggestWidget(), NULL, $message('Default', '', 'Dish'), $drawn('Dish', '')];
    yield 'suggest discovered' => [self::suggestWidget(default: 'Pear Tart'), 'Onion Soup', $message('Discovered', 'Onion Soup', 'Dish'), $drawn('Dish', 'Onion Soup')];
    yield 'confirm default' => [self::confirmWidget(FALSE), NULL, $message('Default', 'false', 'Send order?'), $drawn('Send order?', 'No')];
    yield 'confirm discovered' => [self::confirmWidget(FALSE), 'yes', $message('Discovered', 'true', 'Send order?'), $drawn('Send order?', 'Yes')];
  }

  #[DataProvider('dataProviderNonInteractiveDefaultAccepted')]
  public function testNonInteractiveDefaultAccepted(\Closure $widget, mixed $expected): void {
    $stream = $this->installKeystrokes(self::KEY_ENTER);
    Prompty::configure(interactive: FALSE);
    $answers = [];
    $validate = function (mixed $value) use (&$answers): ?string {
      $answers[] = $value;

      return NULL;
    };

    $result = NULL;
    $this->captureOutput(function () use ($widget, $validate, &$result): void {
      $result = $widget($validate, $this->ctx());
    });

    $this->assertSame($expected, $result);
    $this->assertSame([$expected], $answers);
    $this->assertSame(0, ftell($stream));
    fclose($stream);
  }

  public static function dataProviderNonInteractiveDefaultAccepted(): \Iterator {
    yield 'text' => [self::textWidget('pear tart'), 'pear tart'];
    yield 'select' => [self::selectWidget(), 'starter'];
    yield 'multiselect' => [self::multiselectWidget(), []];
    yield 'suggest' => [self::suggestWidget(default: 'Pear Tart'), 'Pear Tart'];
    yield 'confirm' => [self::confirmWidget(), TRUE];
  }

  #[DataProvider('dataProviderValidatorReceivesTransformedAnswer')]
  public function testValidatorReceivesTransformedAnswer(\Closure $widget, string $keystrokes, mixed $discovered, mixed $expected): void {
    $answers = [];
    $validate = function (mixed $value) use (&$answers): ?string {
      $answers[] = $value;

      return NULL;
    };

    $r = $this->promptyRun(fn(): mixed => $widget($validate, $this->ctx(), $discovered), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertSame([$expected], $answers);
  }

  public static function dataProviderValidatorReceivesTransformedAnswer(): \Iterator {
    $text = static fn(?\Closure $validate, array $ctx, mixed $discovered): mixed => Prompty::text('Dish name', discovered: $discovered, transform: static fn(string $value): string => ucwords($value), validate: $validate, ctx: $ctx);
    $select = static fn(?\Closure $validate, array $ctx, mixed $discovered): mixed => Prompty::select('Course', options: self::COURSES, discovered: $discovered, transform: static fn(string $key): string => $key . '-course', validate: $validate, ctx: $ctx);
    $multiselect = static fn(?\Closure $validate, array $ctx, mixed $discovered): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, discovered: $discovered, transform: static fn(array $keys): array => array_reverse($keys), validate: $validate, ctx: $ctx);
    $suggest = static fn(?\Closure $validate, array $ctx, mixed $discovered): mixed => Prompty::suggest('Dish', options: self::DISHES, discovered: $discovered, transform: static fn(string $dish): string => ucwords(trim($dish)), validate: $validate, ctx: $ctx);
    $confirm = static fn(?\Closure $validate, array $ctx, mixed $discovered): mixed => Prompty::confirm('Send order?', discovered: $discovered, transform: static fn(bool $should_send): bool => !$should_send, validate: $validate, ctx: $ctx);

    yield 'text interactive' => [$text, 'pear tart' . self::KEY_ENTER, NULL, 'Pear Tart'];
    yield 'text discovered' => [$text, '', 'onion soup', 'Onion Soup'];
    yield 'select interactive' => [$select, self::KEY_DOWN . self::KEY_ENTER, NULL, 'main-course'];
    yield 'select discovered' => [$select, '', 'dessert', 'dessert-course'];
    yield 'multiselect interactive' => [$multiselect, self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, NULL, ['olives', 'bread']];
    yield 'multiselect discovered' => [$multiselect, '', 'bread,herbs', ['herbs', 'bread']];
    yield 'suggest typed' => [$suggest, 'onion soup ' . self::KEY_ENTER, NULL, 'Onion Soup'];
    yield 'suggest focused suggestion' => [$suggest, 'crumble' . self::KEY_DOWN . self::KEY_ENTER, NULL, 'Apple Crumble'];
    yield 'suggest discovered' => [$suggest, '', 'lentil stew', 'Lentil Stew'];
    yield 'confirm interactive' => [$confirm, self::KEY_ENTER, NULL, FALSE];
    yield 'confirm discovered' => [$confirm, '', 'no', TRUE];
  }

  public function testTransformedAnswerRejectedKeepsTypedInput(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
      transform: fn(string $value): string => trim($value),
      validate: fn(string $dish): ?string => $dish === '' ? self::MESSAGE : NULL,
      ctx: $this->ctx(),
    ), self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER . 'pear' . self::KEY_ENTER);

    $this->assertSame('pear', $r['result']);
    $this->assertStringContainsString("|    █\n|  ! " . self::MESSAGE . "\n", $r['output']);
  }

  public function testDiscoveredRejectedAfterTransform(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::text('Dish name',
      discovered: 'pear',
      transform: fn(string $value): string => strtoupper($value),
      validate: fn(string $dish): ?string => $dish === 'PEAR' ? self::MESSAGE : NULL,
      ctx: $this->ctx(),
    ), sprintf('Discovered value "PEAR" for "Dish name" is not valid. %s', self::MESSAGE), output: self::failedOutput('Dish name', 'PEAR', self::MESSAGE));
  }

  public function testFlowEnvValueRejected(): void {
    $this->setEnvVars(['dish' => 'pear']);
    $stream = $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "pear" for "Dish name" is not valid. Name the whole dish.', fn(): mixed => Prompty::flow()
      ->text('dish', 'Dish name', validate: fn(string $dish): ?string => str_contains($dish, ' ') ? NULL : 'Name the whole dish.')
      ->run(unicode: FALSE), TRUE);

    $this->assertSame(self::failedOutput('Dish name', 'pear', 'Name the whole dish.') . "#  Failed\n\n", $output);
    $this->assertSame(0, ftell($stream));
    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  #[DataProvider('dataProviderFlowRejectedStepDrawn')]
  public function testFlowRejectedStepDrawn(\Closure $build, array $env, array $options, string $message, string $expected): void {
    $this->setEnvVars($env);
    $stream = $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => $build(Prompty::flow())->run(...$options), TRUE);

    $this->assertSame($expected, $output);
    $this->assertSame(0, ftell($stream));
  }

  public static function dataProviderFlowRejectedStepDrawn(): \Iterator {
    $dish = static fn(string $value): ?string => preg_match('/^[a-z][a-z ]*$/', $value) ? NULL : 'Use lowercase letters and spaces.';
    $flow = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name', placeholder: 'pear tart', validate: $dish)
      ->multiselect('extras', 'Extras', options: self::EXTRAS, required: 'Pick at least 1 extra.');

    yield 'first step rejected' => [
      $flow,
      ['dish' => 'Pear-Tart'],
      ['intro' => 'Kitchen order', 'failed' => 'Order not sent.'],
      'Discovered value "Pear-Tart" for "Dish name" is not valid. Use lowercase letters and spaces.',
      "\n#  Kitchen order\n|\no  Dish name\n|  Pear-Tart\n|  ! Use lowercase letters and spaces.\n|\n#  Order not sent.\n\n",
    ];
    yield 'later step rejected' => [
      $flow,
      ['dish' => 'pear tart', 'extras' => ''],
      ['intro' => 'Kitchen order', 'failed' => 'Order not sent.'],
      'Discovered value "" for "Extras" is not valid. Pick at least 1 extra.',
      "\n#  Kitchen order\n|\n+  Dish name\n|  pear tart\n|\no  Extras\n|  None\n|  ! Pick at least 1 extra.\n|\n#  Order not sent.\n\n",
    ];
    yield 'numbered step' => [
      $flow,
      ['dish' => 'Pear-Tart'],
      ['numbering' => 'total'],
      'Discovered value "Pear-Tart" for "Dish name" is not valid. Use lowercase letters and spaces.',
      "o  Dish name (1/2)\n|  Pear-Tart\n|  ! Use lowercase letters and spaces.\n|\n#  Failed\n\n",
    ];
    yield 'nested step with a later sibling' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->confirm('send', 'Send order?')
        ->children()
          ->text('dish', 'Dish name', validate: $dish)
          ->text('note', 'Note for the kitchen')
        ->end(),
      ['send' => 'yes', 'dish' => 'Pear-Tart', 'note' => 'no salt'],
      [],
      'Discovered value "Pear-Tart" for "Dish name" is not valid. Use lowercase letters and spaces.',
      "+  Send order?\n|  Yes\n|\n|  |\n|  o  Dish name\n|  |  Pear-Tart\n|  |  ! Use lowercase letters and spaces.\n|  |  \n#  Failed\n\n",
    ];
    yield 'unnumbered step' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name', validate: $dish)->unnumbered()
        ->text('note', 'Note for the kitchen'),
      ['dish' => 'Pear-Tart'],
      ['numbering' => TRUE],
      'Discovered value "Pear-Tart" for "Dish name" is not valid. Use lowercase letters and spaces.',
      "o  Dish name\n|  Pear-Tart\n|  ! Use lowercase letters and spaces.\n|\n#  Failed\n\n",
    ];
  }

}
