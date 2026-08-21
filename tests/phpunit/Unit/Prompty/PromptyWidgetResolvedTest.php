<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests widgets that resolve without touching the TTY.
 *
 * A value arrives through the `discovered` argument, the `discovered` ctx
 * key, or an environment variable read by a flow. The widget then skips its
 * interactive loop and returns immediately. Covers where the value comes
 * from, how it is normalised, what a blank one means, and what is rejected.
 *
 * Grouped as resolved values per widget (text, select, multiselect,
 * confirm), resolved values across widgets, blank and empty answers, and
 * the multiselect list forms.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetResolvedTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main'];

  protected const EXTRAS_PAIR = ['bread' => 'Bread', 'olives' => 'Olives'];

  protected const EXTRAS_TRIO = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * Run a widget call against the singleton and capture its output.
   *
   * @param callable $call
   *   The widget call under test.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runWidget(callable $call): array {
    $result = NULL;
    $output = $this->captureOutput(function () use ($call, &$result): void {
      $result = $call();
    });

    return ['result' => $result, 'output' => $output];
  }

  /**
   * Run the text widget over a discovered value.
   *
   * @param mixed $discovered
   *   The discovered argument, or NULL to leave it unset.
   * @param string $default
   *   Value seeded into the input buffer.
   * @param string $placeholder
   *   Placeholder shown while the buffer is empty.
   * @param string|null $ctx_discovered
   *   The environment value carried by the context, or NULL for none.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runTextWidget(mixed $discovered = NULL, string $default = '', string $placeholder = '', ?string $ctx_discovered = NULL): array {
    return $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
      default: $default,
      placeholder: $placeholder,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));
  }

  /**
   * Run the multiselect widget over a discovered value.
   *
   * @param mixed $discovered
   *   The discovered argument, or NULL to leave it unset.
   * @param string|null $ctx_discovered
   *   The environment value carried by the context, or NULL for none.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return array{result: mixed, output: string}
   *   The widget return value and captured output.
   */
  protected function runMultiselectWidget(mixed $discovered = NULL, ?string $ctx_discovered = NULL, array $options = self::EXTRAS_TRIO): array {
    return $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: $options,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));
  }

  #[DataProvider('dataProviderTextResolved')]
  public function testTextResolved(?string $discovered, ?string $ctx_discovered, string $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::text('Project name', discovered: $discovered, ctx: $this->ctx(['discovered' => $ctx_discovered])));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextResolved(): \Iterator {
    yield 'simple value' => ['my-app', NULL, 'my-app'];
    yield 'value with spaces' => ['my cool app', NULL, 'my cool app'];
    yield 'empty string' => ['', NULL, ''];
    yield 'env value' => [NULL, 'env-app', 'env-app'];
    yield 'argument wins over env' => ['direct', 'from-env', 'direct'];
  }

  #[DataProvider('dataProviderTextDefaultPrecedence')]
  public function testTextDefaultPrecedence(?string $discovered, ?string $ctx_discovered, string $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::text('Name', default: 'seed', discovered: $discovered, ctx: $this->ctx(['discovered' => $ctx_discovered])));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => ['disc', NULL, 'disc'];
    yield 'env wins over default' => [NULL, 'env-val', 'env-val'];
  }

  #[DataProvider('dataProviderSelectResolved')]
  public function testSelectResolved(?string $discovered, ?string $ctx_discovered, array $options, string $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::select('Framework',
      options: $options,
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderSelectResolved(): \Iterator {
    $options = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];

    yield 'first option' => ['react', NULL, $options, 'react'];
    yield 'middle option' => ['vue', NULL, $options, 'vue'];
    yield 'last option' => ['svelte', NULL, $options, 'svelte'];
    yield 'env value' => [NULL, 'vue', ['react' => 'React', 'vue' => 'Vue'], 'vue'];
  }

  #[DataProvider('dataProviderSelectRendersResolvedValue')]
  public function testSelectRendersResolvedValue(string $label, array $options, string $discovered, string $expected, array $expected_output): void {
    $r = $this->runWidget(fn(): mixed => Prompty::select($label, options: $options, discovered: $discovered, ctx: $this->ctx()));

    $this->assertSame($expected, $r['result']);

    foreach ($expected_output as $needle) {
      $this->assertStringContainsString($needle, $r['output']);
    }
  }

  public static function dataProviderSelectRendersResolvedValue(): \Iterator {
    yield 'label and completed option label' => ['Framework', ['react' => 'React', 'vue' => 'Vue'], 'vue', 'vue', ['Framework', 'Vue']];
    yield 'option label rather than key' => ['Pick', ['a' => 'Alpha'], 'a', 'a', ['Alpha']];
    yield 'empty option label keeps the prompt label' => ['Pick', ['a' => ''], 'a', 'a', ['Pick']];
  }

  #[DataProvider('dataProviderSelectDefaultPrecedence')]
  public function testSelectDefaultPrecedence(?string $discovered, ?string $ctx_discovered, string $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::select('Framework',
      options: ['react' => 'React', 'vue' => 'Vue'],
      default: 'vue',
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderSelectDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => ['react', NULL, 'react'];
    yield 'env wins over default' => [NULL, 'react', 'react'];
  }

  public function testSelectTrimsDiscoveredValue(): void {
    $r = $this->runWidget(fn(): mixed => Prompty::select('Pick', options: ['a' => 'Alpha'], ctx: $this->ctx(['discovered' => '  a  '])));

    $this->assertSame('a', $r['result']);
  }

  #[DataProvider('dataProviderSelectOutOfDomainThrows')]
  public function testSelectOutOfDomainThrows(?string $discovered, ?string $ctx_discovered, array $options, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Pick',
      options: $options,
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ), $message);
  }

  public static function dataProviderSelectOutOfDomainThrows(): \Iterator {
    $options = ['a' => 'Alpha', 'b' => 'Beta'];

    yield 'discovered argument' => ['unknown', NULL, $options, 'Discovered value "unknown" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'env value' => [NULL, 'nope', $options, 'Discovered value "nope" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'empty env value' => [NULL, '', $options, 'Discovered value "" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'option label is not a key' => ['Alpha', NULL, $options, 'Discovered value "Alpha" for "Pick" is not a valid option. Available options: a, b.'];
  }

  #[DataProvider('dataProviderMultiselectResolved')]
  public function testMultiselectResolved(string $ctx_discovered, array $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::multiselect('Features',
      options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectResolved(): \Iterator {
    yield 'single value' => ['ts', ['ts']];
    yield 'multiple values' => ['ts,eslint', ['ts', 'eslint']];
    yield 'all values' => ['ts,eslint,prettier', ['ts', 'eslint', 'prettier']];
    yield 'with spaces' => ['ts, eslint, prettier', ['ts', 'eslint', 'prettier']];
  }

  #[DataProvider('dataProviderMultiselectDefaultPrecedence')]
  public function testMultiselectDefaultPrecedence(?array $discovered, ?string $ctx_discovered, array $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::multiselect('Features',
      options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
      default: ['ts'],
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => [['eslint'], NULL, ['eslint']];
    yield 'env wins over default' => [NULL, 'eslint', ['eslint']];
  }

  #[DataProvider('dataProviderMultiselectNormalizesDiscovered')]
  public function testMultiselectNormalizesDiscovered(mixed $discovered, ?string $ctx_discovered, array $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::multiselect('Pick',
      options: ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'],
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectNormalizesDiscovered(): \Iterator {
    yield 'argument reordered to option order' => [['c', 'a'], NULL, ['a', 'c']];
    yield 'argument duplicates removed' => [['b', 'a', 'b'], NULL, ['a', 'b']];
    yield 'env reordered to option order' => [NULL, 'c,a', ['a', 'c']];
    yield 'env duplicates removed' => [NULL, 'b,a,b', ['a', 'b']];
    yield 'env entries trimmed' => [NULL, ' a , b ', ['a', 'b']];
    yield 'empty env means none' => [NULL, '', []];
    yield 'trailing comma ignored' => [NULL, 'a,', ['a']];
    yield 'doubled comma ignored' => [NULL, 'a,,b', ['a', 'b']];
    yield 'whitespace-only entry ignored' => [NULL, 'a, ,b', ['a', 'b']];
    yield 'empty argument list' => [[], NULL, []];
  }

  public function testMultiselectEmptyRendersNone(): void {
    $r = $this->runWidget(fn(): mixed => Prompty::multiselect('Features', options: ['ts' => 'TypeScript'], discovered: [], ctx: $this->ctx()));

    $this->assertSame([], $r['result']);
    $this->assertStringContainsString('None', $r['output']);
  }

  #[DataProvider('dataProviderMultiselectOutOfDomainThrows')]
  public function testMultiselectOutOfDomainThrows(?array $discovered, ?string $ctx_discovered, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Pick',
      options: ['a' => 'Alpha', 'b' => 'Beta'],
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ), $message);
  }

  public static function dataProviderMultiselectOutOfDomainThrows(): \Iterator {
    yield 'sole entry unknown' => [['nope'], NULL, 'Discovered value "nope" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'one entry of several unknown' => [['a', 'zz'], NULL, 'Discovered value "zz" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'env entry unknown' => [NULL, 'a,zz', 'Discovered value "zz" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'option label is not a key' => [['Alpha'], NULL, 'Discovered value "Alpha" for "Pick" is not a valid option. Available options: a, b.'];
  }

  #[DataProvider('dataProviderConfirmResolved')]
  public function testConfirmResolved(string $ctx_discovered, bool $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', ctx: $this->ctx(['discovered' => $ctx_discovered])));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderConfirmResolved(): \Iterator {
    yield 'truthy 1' => ['1', TRUE];
    yield 'truthy true' => ['true', TRUE];
    yield 'truthy yes' => ['yes', TRUE];
    yield 'truthy YES (case)' => ['YES', TRUE];
    yield 'falsy 0' => ['0', FALSE];
    yield 'falsy false' => ['false', FALSE];
    yield 'falsy no' => ['no', FALSE];
    yield 'falsy NO (case)' => ['NO', FALSE];
  }

  #[DataProvider('dataProviderConfirmCoercesDiscoveredArgument')]
  public function testConfirmCoercesDiscoveredArgument(mixed $discovered, bool $expected): void {
    $r = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', discovered: $discovered, ctx: $this->ctx()));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderConfirmCoercesDiscoveredArgument(): \Iterator {
    yield 'bool TRUE' => [TRUE, TRUE];
    yield 'bool FALSE' => [FALSE, FALSE];
    yield 'string yes' => ['yes', TRUE];
    yield 'string no' => ['no', FALSE];
    yield 'string NO (case)' => ['NO', FALSE];
    yield 'string true' => ['true', TRUE];
    yield 'string false' => ['false', FALSE];
    yield 'string padded' => [' no ', FALSE];
    yield 'int 1' => [1, TRUE];
    yield 'int 0' => [0, FALSE];
  }

  public function testConfirmRendersYesNo(): void {
    $yes = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', discovered: TRUE, ctx: $this->ctx()));
    $no = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', discovered: FALSE, ctx: $this->ctx()));

    $this->assertStringContainsString('Yes', $yes['output']);
    $this->assertStringContainsString('No', $no['output']);
  }

  #[DataProvider('dataProviderConfirmOutOfDomainThrows')]
  public function testConfirmOutOfDomainThrows(mixed $discovered, ?string $ctx_discovered, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::confirm('Install?', discovered: $discovered, ctx: $this->ctx(['discovered' => $ctx_discovered])), $message);
  }

  public static function dataProviderConfirmOutOfDomainThrows(): \Iterator {
    $accepted = 'Accepted values: 1, true, yes, 0, false, no.';

    yield 'discovered argument' => ['maybe', NULL, 'Discovered value "maybe" for "Install?" is not a valid answer. ' . $accepted];
    yield 'env value' => [NULL, 'maybe', 'Discovered value "maybe" for "Install?" is not a valid answer. ' . $accepted];
    yield 'empty env value' => [NULL, '', 'Discovered value "" for "Install?" is not a valid answer. ' . $accepted];
    yield 'out of range int' => [2, NULL, 'Discovered value "2" for "Install?" is not a valid answer. ' . $accepted];
  }

  #[DataProvider('dataProviderConfirmHonorsCustomTruthyFalsy')]
  public function testConfirmHonorsCustomTruthyFalsy(?string $discovered, ?string $ctx_discovered, bool $expected): void {
    $ctx = $this->ctx(['truthy' => ['on'], 'falsy' => ['off'], 'discovered' => $ctx_discovered]);

    $r = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', discovered: $discovered, ctx: $ctx));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderConfirmHonorsCustomTruthyFalsy(): \Iterator {
    yield 'custom truthy via argument' => ['on', NULL, TRUE];
    yield 'custom falsy via argument' => ['off', NULL, FALSE];
    yield 'custom truthy via env' => [NULL, 'on', TRUE];
    yield 'custom falsy via env' => [NULL, 'off', FALSE];
  }

  #[DataProvider('dataProviderConfirmMatchesListsCaseInsensitively')]
  public function testConfirmMatchesListsCaseInsensitively(string $discovered, bool $expected): void {
    $ctx = $this->ctx(['truthy' => ['YES', ' On '], 'falsy' => ['NO', ' Off ']]);

    $r = $this->runWidget(fn(): mixed => Prompty::confirm('Install?', discovered: $discovered, ctx: $ctx));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderConfirmMatchesListsCaseInsensitively(): \Iterator {
    yield 'lowercase value against uppercase token' => ['yes', TRUE];
    yield 'uppercase value against uppercase token' => ['YES', TRUE];
    yield 'lowercase falsy against uppercase token' => ['no', FALSE];
    yield 'value against padded truthy token' => ['on', TRUE];
    yield 'value against padded falsy token' => ['off', FALSE];
  }

  public function testConfirmRejectsDefaultListValueWhenListsAreCustom(): void {
    $ctx = $this->ctx(['truthy' => ['on'], 'falsy' => ['off']]);

    $this->assertWidgetRejects(fn(): mixed => Prompty::confirm('Install?', discovered: 'yes', ctx: $ctx), 'Discovered value "yes" for "Install?" is not a valid answer. Accepted values: on, off.');
  }

  public function testConfirmStandaloneUsesConfiguredTruthyFalsy(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance(['truthy' => ['on'], 'falsy' => ['off']]);

    $r = $this->runWidget(fn(): mixed => Prompty::confirm('OK?', discovered: 'off'));

    $this->assertFalse($r['result']);
  }

  public function testConfirmStandaloneRejectsValueOutsideConfiguredLists(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance(['truthy' => ['on'], 'falsy' => ['off']]);

    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "yes" for "OK?" is not a valid answer. Accepted values: on, off.',
      function (): void {
        Prompty::confirm('OK?', discovered: 'yes');
      },
    );
  }

  #[DataProvider('dataProviderWidgetResolvedAtDepth')]
  public function testWidgetResolvedAtDepth(\Closure $call, mixed $expected, array $expected_output): void {
    $r = $this->runWidget(fn(): mixed => $call($this));

    $this->assertSame($expected, $r['result']);

    foreach ($expected_output as $needle) {
      $this->assertStringContainsString($needle, $r['output']);
    }
  }

  public static function dataProviderWidgetResolvedAtDepth(): \Iterator {
    yield 'text' => [
      static fn(self $test): mixed => Prompty::text('Child name',
        discovered: 'child-val',
        ctx: $test->ctx(['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]),
      ),
      'child-val',
      ['+  Child name', 'child-val'],
    ];
    yield 'select' => [
      static fn(self $test): mixed => Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        discovered: 'react',
        ctx: $test->ctx(['depth' => 1, 'is_last' => TRUE, 'open' => []]),
      ),
      'react',
      ['React'],
    ];
    yield 'multiselect' => [
      static fn(self $test): mixed => Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        ctx: $test->ctx(['depth' => 2, 'is_last' => FALSE, 'open' => [1 => TRUE, 2 => TRUE], 'discovered' => 'ts,eslint']),
      ),
      ['ts', 'eslint'],
      ['TypeScript, ESLint'],
    ];
    yield 'confirm' => [
      static fn(self $test): mixed => Prompty::confirm('Enable?', discovered: TRUE, ctx: $test->ctx(['depth' => 1, 'is_last' => TRUE, 'open' => []])),
      TRUE,
      ['Yes'],
    ];
  }

  #[DataProvider('dataProviderWidgetStandaloneWithDiscovered')]
  public function testWidgetStandaloneWithDiscovered(\Closure $call, mixed $expected, string $expected_output): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance();

    $r = $this->runWidget(fn(): mixed => $call());

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString($expected_output, $r['output']);
  }

  public static function dataProviderWidgetStandaloneWithDiscovered(): \Iterator {
    yield 'text' => [static fn(): mixed => Prompty::text('Name', discovered: 'standalone-val'), 'standalone-val', 'standalone-val'];
    yield 'select' => [static fn(): mixed => Prompty::select('Pick', options: ['a' => 'Alpha', 'b' => 'Beta'], discovered: 'b'), 'b', 'Beta'];
    yield 'multiselect' => [
      static fn(): mixed => Prompty::multiselect('Pick', options: ['a' => 'Alpha', 'b' => 'Beta'], discovered: ['a', 'b']),
      ['a', 'b'],
      'Alpha, Beta',
    ];
    yield 'confirm' => [static fn(): mixed => Prompty::confirm('OK?', discovered: FALSE), FALSE, 'No'];
  }

  #[DataProvider('dataProviderWidgetWithDescription')]
  public function testWidgetWithDescription(\Closure $call, mixed $expected, string $expected_output): void {
    $r = $this->runWidget(fn(): mixed => $call($this));

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString($expected_output, $r['output']);
  }

  public static function dataProviderWidgetWithDescription(): \Iterator {
    yield 'text' => [
      static fn(self $test): mixed => Prompty::text('Project name', description: 'Used as the directory name.', discovered: 'my-app', ctx: $test->ctx()),
      'my-app',
      'my-app',
    ];
    yield 'select' => [
      static fn(self $test): mixed => Prompty::select('Framework',
        options: ['react' => 'React'],
        description: 'Pick your framework.',
        discovered: 'react',
        ctx: $test->ctx(),
      ),
      'react',
      'React',
    ];
    yield 'multiselect' => [
      static fn(self $test): mixed => Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript'],
        description: "Select features.\nSpace to toggle.",
        discovered: ['ts'],
        ctx: $test->ctx(),
      ),
      ['ts'],
      'TypeScript',
    ];
    yield 'confirm' => [
      static fn(self $test): mixed => Prompty::confirm('Install?', description: 'Runs npm install.', discovered: TRUE, ctx: $test->ctx()),
      TRUE,
      'Yes',
    ];
  }

  public function testWidgetsWithNumbering(): void {
    $r = $this->runWidget(fn(): mixed => Prompty::text('Name', discovered: 'val', ctx: $this->ctx(['number' => '2.1'])));

    $this->assertStringContainsString('(2.1)', $r['output']);
  }

  #[DataProvider('dataProviderTextBlankTakesFallback')]
  public function testTextBlankTakesFallback(string $discovered, string $default, string $placeholder, string $expected): void {
    $r = $this->runTextWidget($discovered, $default, $placeholder);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextBlankTakesFallback(): \Iterator {
    yield 'placeholder only' => ['', '', 'pear tart', 'pear tart'];
    yield 'default only' => ['', 'plum compote', '', 'plum compote'];
    yield 'default wins over placeholder' => ['', 'plum compote', 'pear tart', 'plum compote'];
    yield 'neither stays empty' => ['', '', '', ''];
    yield 'whitespace only' => ['   ', '', 'pear tart', 'pear tart'];
    yield 'tab and newline only' => ["\t\n", '', 'pear tart', 'pear tart'];
    yield 'blank takes default over placeholder' => [' ', 'plum compote', 'pear tart', 'plum compote'];
    yield 'blank with nothing to fall back to' => ['  ', '', '', ''];
  }

  #[DataProvider('dataProviderTextDiscoveredValueIsTrimmed')]
  public function testTextDiscoveredValueIsTrimmed(string $discovered, string $expected): void {
    $r = $this->runTextWidget($discovered, '', 'pear tart');

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderTextDiscoveredValueIsTrimmed(): \Iterator {
    yield 'leading space' => ['  onion soup', 'onion soup'];
    yield 'trailing space' => ['onion soup  ', 'onion soup'];
    yield 'both ends' => ['  onion soup  ', 'onion soup'];
    yield 'inner spacing kept' => ['  onion  soup  ', 'onion  soup'];
    yield 'trailing newline' => ["onion soup\n", 'onion soup'];
  }

  public function testTextTrimmedValueIsRendered(): void {
    $r = $this->runTextWidget('  onion soup  ', '', 'pear tart');

    $this->assertStringContainsString('onion soup', $r['output']);
    $this->assertStringNotContainsString('  onion soup  ', $r['output']);
  }

  public function testTextEmptyDiscoveredRendersFallback(): void {
    $r = $this->runTextWidget('', '', 'pear tart');

    $this->assertStringContainsString('pear tart', $r['output']);
  }

  #[DataProvider('dataProviderTextBothPathsAgreeOnEmptyAnswer')]
  public function testTextBothPathsAgreeOnEmptyAnswer(string $default, string $placeholder): void {
    $interactive = $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
      default: $default,
      placeholder: $placeholder,
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $discovered = $this->runTextWidget('', $default, $placeholder);

    $this->assertSame($interactive['result'], $discovered['result']);
  }

  public static function dataProviderTextBothPathsAgreeOnEmptyAnswer(): \Iterator {
    yield 'placeholder only' => ['', 'pear tart'];
    yield 'default only' => ['plum compote', ''];
    yield 'default and placeholder' => ['plum compote', 'pear tart'];
    yield 'neither' => ['', ''];
  }

  public function testTextEmptyEnvValueTakesFallback(): void {
    $r = $this->runTextWidget(NULL, '', 'pear tart', '');

    $this->assertSame('pear tart', $r['result']);
  }

  public function testTextEnvValueIsTrimmed(): void {
    $r = $this->runTextWidget(NULL, '', 'pear tart', '  walnut loaf  ');

    $this->assertSame('walnut loaf', $r['result']);
  }

  public function testTextNonStringDiscoveredValueSurvives(): void {
    $r = $this->runTextWidget(42, '', 'pear tart');

    $this->assertSame('42', $r['result']);
  }

  #[DataProvider('dataProviderMultiselectBlankSelectsNothing')]
  public function testMultiselectBlankSelectsNothing(mixed $discovered): void {
    $r = $this->runMultiselectWidget($discovered, NULL, self::EXTRAS_PAIR);

    $this->assertSame([], $r['result']);
  }

  public static function dataProviderMultiselectBlankSelectsNothing(): \Iterator {
    yield 'empty string' => [''];
    yield 'blank string' => ['   '];
    yield 'empty list' => [[]];
    yield 'list of blanks' => [['', ' ']];
  }

  #[DataProvider('dataProviderSelectRejectsBlank')]
  public function testSelectRejectsBlank(string $discovered): void {
    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "" for "Course" is not a valid option. Available options: starter, main.',
      function () use ($discovered): void {
        Prompty::select('Course', options: self::COURSES, discovered: $discovered, ctx: $this->ctx());
      },
    );
  }

  public static function dataProviderSelectRejectsBlank(): \Iterator {
    yield 'empty string' => [''];
    yield 'blank string' => ['   '];
  }

  #[DataProvider('dataProviderConfirmRejectsBlank')]
  public function testConfirmRejectsBlank(string $discovered): void {
    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "" for "Send order?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.',
      function () use ($discovered): void {
        Prompty::confirm('Send order?', discovered: $discovered, ctx: $this->ctx());
      },
    );
  }

  public static function dataProviderConfirmRejectsBlank(): \Iterator {
    yield 'empty string' => [''];
    yield 'blank string' => ['   '];
    yield 'tab and newline' => ["\t\n"];
  }

  public function testConfirmReportsTheTrimmedValue(): void {
    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "maybe" for "Send order?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.',
      function (): void {
        Prompty::confirm('Send order?', discovered: '  maybe  ', ctx: $this->ctx());
      },
    );
  }

  public function testBlankEnvValueTakesTheSameFallback(): void {
    $this->setEnvVars(['dish' => '', 'extras' => '']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS_PAIR),
      ], unicode: FALSE);
    });

    $this->assertSame(['dish' => 'pear tart', 'extras' => []], $result);
  }

  public function testBlankEnvValueIsRejectedWhereNothingMeansNoAnswer(): void {
    $this->setEnvVars(['course' => '']);

    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "" for "Course" is not a valid option. Available options: starter, main.',
      function (): void {
        Prompty::flow(fn(): array => ['course' => Prompty::select('Course', options: self::COURSES)], unicode: FALSE);
      },
    );
  }

  public function testUnsetEnvValueIsNotAnAnswerAtAll(): void {
    $this->clearEnvVars(['course']);

    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES),
    ], unicode: FALSE), self::KEY_ENTER);

    $this->assertSame(['course' => 'starter'], $r['result']);
  }

  #[DataProvider('dataProviderMultiselectDiscoveredArgumentAcceptsCommaSeparatedKeys')]
  public function testMultiselectDiscoveredArgumentAcceptsCommaSeparatedKeys(string $discovered, array $expected): void {
    $r = $this->runMultiselectWidget($discovered);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectDiscoveredArgumentAcceptsCommaSeparatedKeys(): \Iterator {
    yield 'single key' => ['bread', ['bread']];
    yield 'two keys' => ['bread,olives', ['bread', 'olives']];
    yield 'every key' => ['bread,olives,herbs', ['bread', 'olives', 'herbs']];
    yield 'spaces around keys' => ['bread, olives', ['bread', 'olives']];
    yield 'reordered to option order' => ['herbs,bread', ['bread', 'herbs']];
    yield 'duplicates removed' => ['olives,bread,olives', ['bread', 'olives']];
    yield 'trailing comma ignored' => ['bread,', ['bread']];
    yield 'doubled comma ignored' => ['bread,,olives', ['bread', 'olives']];
    yield 'empty string means none' => ['', []];
  }

  #[DataProvider('dataProviderMultiselectBothSourcesAgree')]
  public function testMultiselectBothSourcesAgree(string $value): void {
    $argument = $this->runMultiselectWidget($value);
    $env = $this->runMultiselectWidget(NULL, $value);

    $this->assertSame($env['result'], $argument['result']);
  }

  public static function dataProviderMultiselectBothSourcesAgree(): \Iterator {
    yield 'single key' => ['bread'];
    yield 'two keys' => ['bread,olives'];
    yield 'spaces around keys' => ['bread, olives'];
    yield 'trailing comma' => ['bread,'];
    yield 'empty value' => [''];
  }

  public function testMultiselectDiscoveredArgumentRejectsUnknownKeyInList(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS_TRIO,
      discovered: 'bread,pickles',
      ctx: $this->defaultCtx(),
    ), 'Discovered value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.');
  }

  public function testMultiselectListArgumentStillTakesEntriesLiterally(): void {
    $r = $this->runMultiselectWidget(['bread', 'herbs']);

    $this->assertSame(['bread', 'herbs'], $r['result']);
  }

  public function testMultiselectListArgumentReachesOptionKeyHoldingComma(): void {
    $r = $this->runMultiselectWidget(['salt,pepper'], NULL, ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs']);

    $this->assertSame(['salt,pepper'], $r['result']);
  }

  public function testMultiselectCommaFormCannotReachOptionKeyHoldingComma(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: ['salt,pepper' => 'Salt and pepper', 'herbs' => 'Herbs'],
      discovered: 'salt,pepper',
      ctx: $this->defaultCtx(),
    ), 'Discovered value "salt" for "Extras" is not a valid option. Available options: salt,pepper, herbs.');
  }

  public function testMultiselectFlowEnvValueStillSplits(): void {
    $this->setEnvVars(['extras' => 'bread,herbs']);
    $result = NULL;

    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS_TRIO),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame(['bread', 'herbs'], $result['extras']);
  }

}
