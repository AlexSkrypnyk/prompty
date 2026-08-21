<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * In-process tests for the widgets' interactive loops.
 *
 * Every test drives a widget through a fed keystroke stream and asserts on
 * the returned value and on the frames drawn while the widget waits and once
 * it is answered.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetInteractiveTest extends PromptyTestCase {

  protected const FRAMEWORKS = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];

  protected const FEATURES = ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'];

  /**
   * Context of a nested widget with a following sibling.
   */
  protected const CTX_AT_DEPTH = ['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]];

  /**
   * Context of a nested widget that is the last child.
   */
  protected const CTX_AT_DEPTH_LAST = ['depth' => 1, 'is_last' => TRUE, 'open' => []];

  // Text widget.

  /**
   * Run the text widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param string $default
   *   Pre-filled, editable initial value.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runTextWidget(string $keystrokes, array $ctx_overrides = [], string $default = ''): array {
    return $this->promptyRun(fn(): mixed => Prompty::text('Project name',
      default: $default,
      placeholder: 'my-app',
      description: 'Enter a name.',
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  #[DataProvider('dataProviderTextTypeAndSubmit')]
  public function testTextTypeAndSubmit(string $keystrokes, string $expected): void {
    $r = $this->runTextWidget($keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextTypeAndSubmit(): \Iterator {
    yield 'simple word' => ['hello', 'hello'];
    yield 'single char' => ['x', 'x'];
    yield 'numbers' => ['123', '123'];
    yield 'mixed' => ['my-app-v2', 'my-app-v2'];
    yield 'with spaces' => ['my app', 'my app'];
    yield 'space between words' => ['a b', 'a b'];
    yield 'empty submit returns placeholder' => ['', 'my-app'];
    yield 'unknown keys ignored' => ['a' . self::KEY_TAB . self::KEY_UP . 'b', 'ab'];
  }

  #[DataProvider('dataProviderTextBackspace')]
  public function testTextBackspace(string $keystrokes, string $expected): void {
    $r = $this->runTextWidget($keystrokes . self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextBackspace(): \Iterator {
    yield 'delete last char' => ['hello' . self::KEY_BACKSPACE, 'hell'];
    yield 'delete and retype' => ['helo' . self::KEY_BACKSPACE . 'lo', 'hello'];
    yield 'multiple deletes' => ['abcdef' . self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_BACKSPACE, 'abc'];
    yield 'backspace on empty' => [self::KEY_BACKSPACE . self::KEY_BACKSPACE . 'ok', 'ok'];
    yield 'delete all returns placeholder' => ['hi' . self::KEY_BACKSPACE . self::KEY_BACKSPACE, 'my-app'];
  }

  // Select widget.

  /**
   * Run the select widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param string $default
   *   Option key to pre-focus.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runSelectWidget(string $keystrokes, array $options = self::FRAMEWORKS, array $hints = [], array $ctx_overrides = [], string $default = ''): array {
    return $this->promptyRun(fn(): mixed => Prompty::select('Framework',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  #[DataProvider('dataProviderSelectNavigation')]
  public function testSelectNavigation(string $keystrokes, string $expected): void {
    $r = $this->runSelectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderSelectNavigation(): \Iterator {
    yield 'select first option' => [self::KEY_ENTER, 'react'];
    yield 'select second option with down' => [self::KEY_DOWN . self::KEY_ENTER, 'vue'];
    yield 'select third option with two downs' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER, 'svelte'];
    yield 'up from first wraps to last' => [self::KEY_UP . self::KEY_ENTER, 'svelte'];
    yield 'down from last wraps to first' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_ENTER, 'react'];
    yield 'left acts as up' => [self::KEY_LEFT . self::KEY_ENTER, 'svelte'];
    yield 'right acts as down' => [self::KEY_RIGHT . self::KEY_ENTER, 'vue'];
  }

  // Multiselect widget.

  /**
   * Run the multiselect widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param list<string> $default
   *   Option keys to pre-check.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselectWidget(string $keystrokes, array $options = self::FEATURES, array $hints = [], array $ctx_overrides = [], array $default = []): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Features',
      options: $options,
      default: $default,
      hints: $hints,
      ctx: $this->defaultCtx($ctx_overrides),
    ), $keystrokes);
  }

  public function testMultiselectSubmitNoneSelected(): void {
    $r = $this->runMultiselectWidget(self::KEY_ENTER);

    $this->assertSame([], $r['result']);
    $this->assertStringContainsString('None', $r['output']);
  }

  #[DataProvider('dataProviderMultiselectToggleAndNavigation')]
  public function testMultiselectToggleAndNavigation(string $keystrokes, array $expected): void {
    $r = $this->runMultiselectWidget($keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectToggleAndNavigation(): \Iterator {
    yield 'select single option' => [self::KEY_SPACE . self::KEY_ENTER, ['ts']];
    yield 'select multiple options' => [self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['ts', 'eslint']];
    yield 'select all options' => [
      self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER,
      ['ts', 'eslint', 'prettier'],
    ];
    yield 'toggle off option' => [self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER, []];
    yield 'navigate down and select' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'up from first wraps to last' => [self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'down from last wraps to first' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, ['ts']];
    yield 'left acts as up' => [self::KEY_LEFT . self::KEY_SPACE . self::KEY_ENTER, ['prettier']];
    yield 'right acts as down' => [self::KEY_RIGHT . self::KEY_SPACE . self::KEY_ENTER, ['eslint']];
  }

  // Confirm widget.

  /**
   * Run the confirm widget with injected keystrokes.
   *
   * @param string $keystrokes
   *   Raw keystroke bytes to feed.
   * @param array<string, mixed> $ctx_overrides
   *   Optional context overrides.
   * @param bool $default
   *   The default selection (TRUE for yes, FALSE for no).
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runConfirmWidget(string $keystrokes, array $ctx_overrides = [], bool $default = TRUE): array {
    return $this->promptyRun(fn(): mixed => Prompty::confirm('Install?',
      default: $default,
      description: 'Install dependencies.',
      ctx: $this->ctx($ctx_overrides),
    ), $keystrokes);
  }

  /**
   * Keystrokes that toggle the confirm widget's focused answer.
   *
   * @return \Iterator<string, array{string}>
   *   One keystroke per case.
   */
  protected static function confirmKeystrokeCases(): \Iterator {
    yield 'left' => [self::KEY_LEFT];
    yield 'right' => [self::KEY_RIGHT];
    yield 'up' => [self::KEY_UP];
    yield 'down' => [self::KEY_DOWN];
    yield 'tab' => [self::KEY_TAB];
  }

  #[DataProvider('dataProviderConfirmToggleFromYesToNo')]
  public function testConfirmToggleFromYesToNo(string $keystrokes): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER);

    $this->assertFalse($r['result']);
  }

  public static function dataProviderConfirmToggleFromYesToNo(): \Iterator {
    yield from self::confirmKeystrokeCases();
  }

  #[DataProvider('dataProviderConfirmToggleFromNoToYes')]
  public function testConfirmToggleFromNoToYes(string $keystrokes): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER, [], FALSE);

    $this->assertTrue($r['result']);
  }

  public static function dataProviderConfirmToggleFromNoToYes(): \Iterator {
    yield from self::confirmKeystrokeCases();
  }

  public function testConfirmDoubleToggleReturnsToOriginal(): void {
    $r = $this->runConfirmWidget(self::KEY_LEFT . self::KEY_LEFT . self::KEY_ENTER);

    $this->assertTrue($r['result']);
  }

  #[DataProvider('dataProviderConfirmYesNoKeys')]
  public function testConfirmYesNoKeys(string $keystrokes, bool $default, bool $expected): void {
    $r = $this->runConfirmWidget($keystrokes . self::KEY_ENTER, [], $default);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderConfirmYesNoKeys(): \Iterator {
    yield 'lowercase y' => ['y', FALSE, TRUE];
    yield 'uppercase Y' => ['Y', FALSE, TRUE];
    yield 'lowercase n' => ['n', TRUE, FALSE];
    yield 'uppercase N' => ['N', TRUE, FALSE];
  }

  // Behaviour shared by all four widgets.

  /**
   * Run a provider-supplied widget closure and validate the result shape.
   *
   * @param \Closure $run
   *   Closure receiving this test case and returning a promptyRun() result.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runWidgetClosure(\Closure $run): array {
    $r = $run($this);

    $this->assertIsArray($r);
    $this->assertArrayHasKey('result', $r);
    $this->assertArrayHasKey('output', $r);
    $output = $r['output'];
    $this->assertIsString($output);

    return ['result' => $r['result'], 'output' => $output];
  }

  /**
   * Assert that the output contains every given string.
   *
   * @param string $output
   *   ANSI-stripped widget output.
   * @param array $strings
   *   Strings the output must contain.
   */
  protected function assertOutputContainsAll(string $output, array $strings): void {
    foreach ($strings as $string) {
      $this->assertIsString($string);
      $this->assertStringContainsString($string, $output);
    }
  }

  #[DataProvider('dataProviderCancel')]
  public function testCancel(\Closure $run, array $expected_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('(cancelled)', $r['output']);
    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderCancel(): \Iterator {
    yield 'text ctrl-c' => [static fn(self $test): array => $test->runTextWidget('hel' . self::KEY_CTRL_C), []];
    yield 'text escape' => [static fn(self $test): array => $test->runTextWidget('hel' . self::KEY_ESCAPE), []];
    yield 'text empty' => [static fn(self $test): array => $test->runTextWidget(self::KEY_CTRL_C), []];
    yield 'text shows typed value' => [static fn(self $test): array => $test->runTextWidget('partial' . self::KEY_CTRL_C), ['partial']];
    yield 'text at depth' => [static fn(self $test): array => $test->runTextWidget('x' . self::KEY_CTRL_C, self::CTX_AT_DEPTH_LAST), []];
    yield 'select ctrl-c shows focused option' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_CTRL_C), ['Vue']];
    yield 'select escape' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_ESCAPE), []];
    yield 'select at depth' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_CTRL_C, ctx_overrides: self::CTX_AT_DEPTH_LAST), []];
    yield 'multiselect ctrl-c' => [static fn(self $test): array => $test->runMultiselectWidget(self::KEY_SPACE . self::KEY_CTRL_C), []];
    yield 'multiselect escape' => [static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ESCAPE), []];
    yield 'multiselect at depth' => [static fn(self $test): array => $test->runMultiselectWidget(self::KEY_CTRL_C, ctx_overrides: self::CTX_AT_DEPTH_LAST), []];
    yield 'confirm ctrl-c' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_CTRL_C), []];
    yield 'confirm escape' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ESCAPE), []];
    yield 'confirm shows focused value' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_LEFT . self::KEY_CTRL_C), ['No']];
    yield 'confirm at depth' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_CTRL_C, self::CTX_AT_DEPTH_LAST), []];
  }

  #[DataProvider('dataProviderActiveState')]
  public function testActiveState(\Closure $run, array $expected_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderActiveState(): \Iterator {
    yield 'text shows placeholder' => [static fn(self $test): array => $test->runTextWidget(self::KEY_ENTER), ['my-app']];
    yield 'text shows description' => [static fn(self $test): array => $test->runTextWidget('x' . self::KEY_ENTER), ['Enter a name.']];
    yield 'select shows options' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER), ['React', 'Vue', 'Svelte']];
    yield 'select shows description' => [
      static fn(self $test): array => $test->promptyRun(static fn(): mixed => Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        description: 'Pick one.',
        ctx: $test->defaultCtx(),
      ), self::KEY_ENTER),
      ['Pick one.'],
    ];
    yield 'select hint for focused option' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER,
        ['react' => 'React', 'vue' => 'Vue'],
        ['react' => 'Meta library', 'vue' => 'Progressive framework'],
      ),
      ['Meta library'],
    ];
    yield 'select hint changes on navigation' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER,
        ['react' => 'React', 'vue' => 'Vue'],
        ['react' => 'Meta library', 'vue' => 'Progressive framework'],
      ),
      ['Progressive framework'],
    ];
    yield 'multiselect shows options' => [static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER), ['TypeScript', 'ESLint', 'Prettier']];
    yield 'multiselect hint for focused option' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER,
        ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        ['ts' => 'Strict typing', 'eslint' => 'Code linter'],
      ),
      ['Strict typing'],
    ];
    yield 'confirm shows yes and no' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER), ['Yes', 'No']];
    yield 'confirm shows description' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER), ['Install dependencies.']];
  }

  #[DataProvider('dataProviderCompletedStateShowsLabel')]
  public function testCompletedStateShowsLabel(\Closure $run, array $expected_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderCompletedStateShowsLabel(): \Iterator {
    yield 'text' => [static fn(self $test): array => $test->runTextWidget('hello' . self::KEY_ENTER), ['Project name', 'hello']];
    yield 'select' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER), ['Framework', 'Vue']];
    yield 'multiselect selected labels' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_SPACE . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER),
      ['TypeScript, ESLint'],
    ];
    yield 'confirm' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER), ['Install?']];
  }

  #[DataProvider('dataProviderInteractiveAtDepth')]
  public function testInteractiveAtDepth(\Closure $run, mixed $expected, array $expected_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertSame($expected, $r['result']);
    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderInteractiveAtDepth(): \Iterator {
    yield 'text' => [static fn(self $test): array => $test->runTextWidget('val' . self::KEY_ENTER, self::CTX_AT_DEPTH), 'val', ['Project name']];
    yield 'select' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, ctx_overrides: self::CTX_AT_DEPTH),
      'vue',
      ['Framework'],
    ];
    yield 'select hint' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER, hints: ['react' => 'Meta library'], ctx_overrides: self::CTX_AT_DEPTH),
      'react',
      ['Meta library'],
    ];
    yield 'multiselect' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, ctx_overrides: self::CTX_AT_DEPTH),
      ['ts'],
      ['Features'],
    ];
    yield 'multiselect hint' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER,
        hints: ['ts' => 'Typed JS'],
        ctx_overrides: self::CTX_AT_DEPTH,
      ),
      ['ts'],
      ['Typed JS'],
    ];
    yield 'confirm' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER, self::CTX_AT_DEPTH), TRUE, ['Install?']];
  }

  #[DataProvider('dataProviderDefault')]
  public function testDefault(\Closure $run, mixed $expected, array $expected_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertSame($expected, $r['result']);
    $this->assertOutputContainsAll($r['output'], $expected_output);
  }

  public static function dataProviderDefault(): \Iterator {
    yield 'text pre-fills value' => [static fn(self $test): array => $test->runTextWidget(self::KEY_ENTER, default: 'seed-name'), 'seed-name', []];
    yield 'text editable' => [static fn(self $test): array => $test->runTextWidget(self::KEY_BACKSPACE . 'x' . self::KEY_ENTER, default: 'abc'), 'abx', []];
    yield 'text cleared falls back to placeholder' => [
      static fn(self $test): array => $test->runTextWidget(self::KEY_BACKSPACE . self::KEY_BACKSPACE . self::KEY_ENTER, default: 'ab'),
      'my-app',
      [],
    ];
    yield 'text shown in active state' => [
      static fn(self $test): array => $test->runTextWidget('x' . self::KEY_ENTER, default: 'preset'),
      'presetx',
      ['preset'],
    ];
    yield 'select focuses option' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER, default: 'vue'), 'vue', []];
    yield 'select focuses last option' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER, default: 'svelte'), 'svelte', []];
    yield 'select navigable after focus' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER, default: 'vue'),
      'svelte',
      [],
    ];
    yield 'multiselect pre-checks options' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER, default: ['ts', 'prettier']),
      ['ts', 'prettier'],
      [],
    ];
    yield 'multiselect all pre-checked' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER, default: ['ts', 'eslint', 'prettier']),
      ['ts', 'eslint', 'prettier'],
      [],
    ];
    yield 'multiselect toggle off yields empty' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_SPACE . self::KEY_ENTER, default: ['ts']),
      [],
      [],
    ];
    yield 'multiselect check another after default' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, default: ['ts']),
      ['ts', 'eslint'],
      [],
    ];
    yield 'multiselect renders pre-checked selection' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER, default: ['ts', 'eslint']),
      ['ts', 'eslint'],
      ['TypeScript, ESLint'],
    ];
    yield 'confirm submits yes' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER), TRUE, ['Yes']];
    yield 'confirm submits no' => [static fn(self $test): array => $test->runConfirmWidget(self::KEY_ENTER, default: FALSE), FALSE, ['No']];
  }

  #[DataProvider('dataProviderDefaultThreadsThroughFlowClosure')]
  public function testDefaultThreadsThroughFlowClosure(\Closure $widget, mixed $expected): void {
    $r = $this->promptyRun(function () use ($widget): mixed {
      $closure = $widget();
      $this->assertInstanceOf(\Closure::class, $closure);

      return $closure($this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDefaultThreadsThroughFlowClosure(): \Iterator {
    yield 'text' => [static fn(): mixed => Prompty::text('Name', default: 'from-closure', placeholder: 'ph'), 'from-closure'];
    yield 'select' => [static fn(): mixed => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'], default: 'vue'), 'vue'];
    yield 'multiselect' => [
      static fn(): mixed => Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'], default: ['ts']),
      ['ts'],
    ];
  }

  #[DataProvider('dataProviderPointer')]
  public function testPointer(\Closure $run, array $expected_output, array $absent_output): void {
    $r = $this->runWidgetClosure($run);

    $this->assertOutputContainsAll($r['output'], $expected_output);

    foreach ($absent_output as $absent_string) {
      $this->assertIsString($absent_string);
      $this->assertStringNotContainsString($absent_string, $r['output']);
    }
  }

  public static function dataProviderPointer(): \Iterator {
    yield 'select marks focused option' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER), ['> (*) React'], []];
    yield 'select moves with navigation' => [static fn(self $test): array => $test->runSelectWidget(self::KEY_DOWN . self::KEY_ENTER), ['> (*) Vue'], []];
    yield 'select renders at depth' => [
      static fn(self $test): array => $test->runSelectWidget(self::KEY_ENTER, ctx_overrides: self::CTX_AT_DEPTH),
      ['> (*) React'],
      [],
    ];
    yield 'select renders unicode glyph' => [
      static fn(self $test): array => $test->promptyRun(static fn(): mixed => Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        ctx: $test->defaultCtx(),
      ), self::KEY_ENTER, ['unicode' => TRUE]),
      ['❯'],
      [],
    ];
    yield 'multiselect marks focused option' => [static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER), ['> [ ] TypeScript'], []];
    yield 'multiselect stays visible on checked focused option' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER, default: ['ts', 'eslint']),
      ['> [x] TypeScript'],
      ['> [x] ESLint'],
    ];
    yield 'multiselect moves with navigation' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_DOWN . self::KEY_ENTER),
      ['> [ ] ESLint'],
      [],
    ];
    yield 'multiselect renders at depth' => [
      static fn(self $test): array => $test->runMultiselectWidget(self::KEY_ENTER, ctx_overrides: self::CTX_AT_DEPTH),
      ['> [ ] TypeScript'],
      [],
    ];
    yield 'multiselect renders unicode glyph' => [
      static fn(self $test): array => $test->promptyRun(static fn(): mixed => Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        ctx: $test->defaultCtx(),
      ), self::KEY_ENTER, ['unicode' => TRUE]),
      ['❯'],
      [],
    ];
  }

  // PromptyTestTrait usage example.

  /**
   * Guards the usage example in PromptyTestTrait's class docblock.
   *
   * The example is the first code a consumer copies, so it is mirrored here
   * to keep it executable.
   */
  public function testDocumentedExample(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main']),
    ]), self::KEY_DOWN . self::KEY_ENTER);

    $this->assertIsArray($r['result']);
    $this->assertSame('main', $r['result']['course']);
  }

}
