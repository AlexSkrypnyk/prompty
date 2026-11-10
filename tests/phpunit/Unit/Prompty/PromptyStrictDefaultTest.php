<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the strict default option on the select and multiselect widgets.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyStrictDefaultTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  /**
   * Tests the answer a widget gives when its default falls back.
   *
   * @param \Closure $widget
   *   Builds the widget from a flow context.
   * @param string $keystrokes
   *   Raw keystroke bytes fed to the widget.
   * @param array<string, mixed> $config
   *   Configuration for the instance.
   * @param mixed $expected
   *   The expected answer.
   */
  #[DataProvider('dataProviderFallback')]
  public function testFallback(\Closure $widget, string $keystrokes, array $config, mixed $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes, $config);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderFallback(): \Iterator {
    $select = static fn(string $default, ?bool $strict = NULL): \Closure => static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: $default, strict_default: $strict, ctx: $ctx);
    $multiselect = self::multiselectWidget(...);
    $lenient = ['strict_default' => FALSE];
    $offline = ['interactive' => FALSE];

    yield 'select focuses the first option' => [$select('brunch', FALSE), self::KEY_ENTER, [], 'starter'];
    yield 'select focus moves from the first option' => [$select('brunch', FALSE), self::KEY_DOWN . self::KEY_ENTER, [], 'main'];
    yield 'select keeps a valid default' => [$select('dessert', FALSE), self::KEY_ENTER, [], 'dessert'];
    yield 'select trims a valid default' => [$select(' dessert ', FALSE), self::KEY_ENTER, [], 'dessert'];
    yield 'select non-interactive answers the first option' => [$select('brunch', FALSE), '', $offline, 'starter'];
    yield 'select non-interactive keeps a valid default' => [$select('main', FALSE), '', $offline, 'main'];
    yield 'select follows the configured value' => [$select('brunch'), self::KEY_ENTER, $lenient, 'starter'];
    yield 'multiselect drops keys outside the options' => [$multiselect(['bread', 'garlic', 'herbs'], FALSE), self::KEY_ENTER, [], ['bread', 'herbs']];
    yield 'multiselect with no valid key checks nothing' => [$multiselect(['garlic'], FALSE), self::KEY_ENTER, [], []];
    yield 'multiselect drops a numeric key outside the options' => [$multiselect([4, 'olives'], FALSE), self::KEY_ENTER, [], ['olives']];
    yield 'multiselect non-interactive answers the remaining keys' => [$multiselect(['garlic', 'olives'], FALSE), '', $offline, ['olives']];
    yield 'multiselect follows the configured value' => [$multiselect(['garlic', 'olives']), self::KEY_ENTER, $lenient, ['olives']];
  }

  /**
   * Tests a default outside the options still throws when strict.
   *
   * @param \Closure $widget
   *   Builds the widget from a flow context.
   * @param array<string, mixed> $config
   *   Configuration for the instance.
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderStrict')]
  public function testStrict(\Closure $widget, array $config, string $message): void {
    $this->createAndSetInstance($config);

    $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => $widget($this->defaultCtx()));
  }

  public static function dataProviderStrict(): \Iterator {
    $select = 'Default value "brunch" for "Course" is not a valid option. Available options: starter, main, dessert.';
    $multiselect = 'Default value "garlic" for "Extras" is not a valid option. Available options: bread, olives, herbs.';

    yield 'select by default' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'brunch', ctx: $ctx), [], $select];
    yield 'select widget overrides the configured value' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'brunch', strict_default: TRUE, ctx: $ctx), ['strict_default' => FALSE], $select];
    yield 'multiselect by default' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['bread', 'garlic'], ctx: $ctx), [], $multiselect];
    yield 'multiselect widget overrides the configured value' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['garlic'], strict_default: TRUE, ctx: $ctx), ['strict_default' => FALSE], $multiselect];
  }

  /**
   * Tests a discovered value stays strict when the default falls back.
   *
   * @param \Closure $widget
   *   Builds the widget from a flow context.
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderDiscoveredStaysStrict')]
  public function testDiscoveredStaysStrict(\Closure $widget, string $message): void {
    $this->createAndSetInstance(['strict_default' => FALSE]);

    $this->captureOutputThrows(\InvalidArgumentException::class, $message, fn(): mixed => $widget($this->defaultCtx()));
  }

  public static function dataProviderDiscoveredStaysStrict(): \Iterator {
    yield 'select' => [static fn(array $ctx): mixed => Prompty::select('Course', options: self::COURSES, default: 'brunch', discovered: 'supper', ctx: $ctx), 'Discovered value "supper" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'multiselect' => [static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['garlic'], discovered: 'bread,chilli', ctx: $ctx), 'Discovered value "chilli" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
  }

  public function testEnvironmentValueStaysStrict(): void {
    $this->setEnvVars(['course' => 'supper']);

    $this->captureOutputThrows(\InvalidArgumentException::class, 'Discovered value "supper" for "Course" is not a valid option. Available options: starter, main, dessert.', fn(): ?array => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES, default: 'brunch'),
    ], interactive: FALSE, strict_default: FALSE));

    $this->clearEnvVars(['course']);
  }

  public function testFlowScopesTheValue(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, default: 'brunch'),
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['garlic', 'herbs']),
      ], unicode: FALSE, interactive: FALSE, strict_default: FALSE);
    });

    $this->assertSame(['course' => 'starter', 'extras' => ['herbs']], $result);
    $this->assertStringContainsString('+  Course', $output);
    $this->assertTrue(Prompty::config()['strict_default']);
  }

  public function testConfigureSetsTheValue(): void {
    $this->createAndSetInstance();

    $this->assertTrue(Prompty::config()['strict_default']);

    Prompty::configure(strict_default: FALSE);

    $this->assertFalse(Prompty::config()['strict_default']);
  }

  public function testFlowKeepsStepOptionsWhenDefined(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'course' => Prompty::select('Course', options: self::COURSES, default: 'brunch', strict_default: FALSE, condition: fn(array $r): bool => TRUE),
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['garlic', 'bread'], strict_default: FALSE),
      ]);
    });

    $this->assertSame(['course' => 'starter', 'extras' => ['bread']], $result);
  }

  /**
   * Builds a multiselect widget from a flow context.
   *
   * @param list<int|string> $default
   *   The default keys.
   * @param bool|null $strict
   *   The strict_default argument.
   *
   * @return \Closure
   *   A closure that runs the widget with the context it receives.
   */
  protected static function multiselectWidget(array $default, ?bool $strict = NULL): \Closure {
    return static fn(array $ctx): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: $default, strict_default: $strict, ctx: $ctx);
  }

}
