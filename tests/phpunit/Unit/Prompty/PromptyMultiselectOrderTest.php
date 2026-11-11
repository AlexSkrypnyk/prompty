<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the order of the keys a multiselect answers with.
 *
 * Covers each order over an interactive answer, a discovered value, an
 * environment variable and the non-interactive default, and the rejection
 * of an unknown order.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyMultiselectOrderTest extends PromptyTestCase {

  protected const array EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs', 'butter' => 'Butter'];

  #[DataProvider('dataProviderOrderKeys')]
  public function testOrderKeys(array $keys, string|\Closure $order, array $options, array $default_keys, array $expected): void {
    $p = $this->createInstance();

    $this->assertSame($expected, $this->callProtected($p, 'orderKeys', 'Extras', $keys, $order, $options, $default_keys));
  }

  public static function dataProviderOrderKeys(): \Iterator {
    $keys = ['butter', 'herbs', 'bread'];
    $default = ['herbs', 'olives', 'bread'];

    yield 'options' => [$keys, 'options', self::EXTRAS, $default, ['bread', 'herbs', 'butter']];
    yield 'default' => [$keys, 'default', self::EXTRAS, $default, ['herbs', 'bread', 'butter']];
    yield 'selection' => [$keys, 'selection', self::EXTRAS, $default, ['butter', 'herbs', 'bread']];
    yield 'default without a default' => [$keys, 'default', self::EXTRAS, [], ['bread', 'herbs', 'butter']];
    yield 'default with no default key selected' => [['butter', 'olives'], 'default', self::EXTRAS, ['herbs'], ['olives', 'butter']];
    yield 'default with new keys in option order' => [['butter', 'olives', 'herbs'], 'default', self::EXTRAS, ['herbs'], ['herbs', 'olives', 'butter']];
    yield 'nothing selected' => [[], 'default', self::EXTRAS, $default, []];
    yield 'numeric keys' => [['12', '4'], 'options', [4 => 'Table 4', 7 => 'Table 7', 12 => 'Table 12'], [], ['4', '12']];
    yield 'numeric default keys' => [['4', '12'], 'default', [4 => 'Table 4', 7 => 'Table 7', 12 => 'Table 12'], ['12', '7', '4'], ['12', '4']];

    yield 'comparator by label' => [$keys, static fn(string $a, string $b): int => strcmp(self::EXTRAS[$a], self::EXTRAS[$b]), self::EXTRAS, $default, ['bread', 'butter', 'herbs']];
    yield 'comparator reversed' => [$keys, static fn(string $a, string $b): int => strcmp($b, $a), self::EXTRAS, $default, ['herbs', 'butter', 'bread']];
    yield 'comparator ties keep the selection order' => [$keys, static fn(string $a, string $b): int => 0, self::EXTRAS, $default, ['butter', 'herbs', 'bread']];
    yield 'comparator with partial ranking' => [$keys, static fn(string $a, string $b): int => ($a === 'bread' ? -1 : 0) - ($b === 'bread' ? -1 : 0), self::EXTRAS, $default, ['bread', 'butter', 'herbs']];
    yield 'first-class callable' => [$keys, strcmp(...), self::EXTRAS, $default, ['bread', 'butter', 'herbs']];
    yield 'comparator over nothing' => [[], static fn(string $a, string $b): int => 1, self::EXTRAS, $default, []];
  }

  #[DataProvider('dataProviderComparator')]
  public function testComparator(string $keystrokes, mixed $discovered, array $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['herbs', 'bread'],
      order: static fn(string $a, string $b): int => strcmp(self::EXTRAS[$b], self::EXTRAS[$a]),
      discovered: $discovered,
      ctx: $this->defaultCtx(),
    ), $keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderComparator(): \Iterator {
    yield 'interactive, default accepted' => [self::KEY_ENTER, NULL, ['herbs', 'bread']];
    yield 'interactive, new key checked' => [self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, NULL, ['olives', 'herbs', 'bread']];
    yield 'discovered list' => ['', ['bread', 'butter', 'olives'], ['olives', 'butter', 'bread']];
    yield 'discovered comma string' => ['', 'bread,herbs,butter', ['herbs', 'butter', 'bread']];
  }

  #[DataProvider('dataProviderComparatorReturnsNonInt')]
  public function testComparatorReturnsNonInt(string $keystrokes, mixed $discovered, mixed $returned, string $type): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage(sprintf('Order for "Extras" returned %s. An order closure compares 2 option keys and returns an int, as for usort().', $type));

    $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['herbs', 'bread'],
      order: static fn(string $a, string $b): mixed => $returned,
      discovered: $discovered,
      ctx: $this->defaultCtx(),
    ), $keystrokes);
  }

  public static function dataProviderComparatorReturnsNonInt(): \Iterator {
    yield 'interactive, bool' => [self::KEY_ENTER, NULL, TRUE, 'bool'];
    yield 'discovered, float' => ['', ['bread', 'olives'], 0.5, 'float'];
    yield 'discovered, numeric string' => ['', ['bread', 'olives'], '1', 'string'];
    yield 'discovered, null' => ['', ['bread', 'olives'], NULL, 'null'];
  }

  public function testComparatorSkipsSingleKey(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      order: static fn(string $a, string $b): mixed => 'never called',
      discovered: ['olives'],
      ctx: $this->defaultCtx(),
    ));

    $this->assertSame(['olives'], $r['result']);
  }

  #[DataProvider('dataProviderInteractive')]
  public function testInteractive(string $keystrokes, string $order, array $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['herbs', 'bread'],
      order: $order,
      ctx: $this->defaultCtx(),
    ), $keystrokes);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderInteractive(): \Iterator {
    // Focus opens on 'bread', the first option.
    $check_butter = self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER;
    $toggle_bread = self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER;
    $check_olives_then_butter = self::KEY_DOWN . self::KEY_SPACE . self::KEY_UP . self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER;
    $check_butter_then_olives = self::KEY_UP . self::KEY_SPACE . self::KEY_UP . self::KEY_UP . self::KEY_SPACE . self::KEY_ENTER;

    yield 'options, default accepted' => [self::KEY_ENTER, 'options', ['bread', 'herbs']];
    yield 'default, default accepted' => [self::KEY_ENTER, 'default', ['herbs', 'bread']];
    yield 'selection, default accepted' => [self::KEY_ENTER, 'selection', ['herbs', 'bread']];

    yield 'options, new key checked' => [$check_butter, 'options', ['bread', 'herbs', 'butter']];
    yield 'default, new key checked' => [$check_butter, 'default', ['herbs', 'bread', 'butter']];
    yield 'selection, new key checked' => [$check_butter, 'selection', ['herbs', 'bread', 'butter']];

    yield 'default, default key unchecked and checked again' => [$toggle_bread, 'default', ['herbs', 'bread']];
    yield 'selection, default key unchecked and checked again' => [$toggle_bread, 'selection', ['herbs', 'bread']];
    yield 'selection, default key moves to the end' => [self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_SPACE . self::KEY_ENTER, 'selection', ['bread', 'herbs']];

    yield 'default, new keys in option order' => [$check_olives_then_butter, 'default', ['herbs', 'bread', 'olives', 'butter']];
    yield 'selection, new keys in checked order' => [$check_olives_then_butter, 'selection', ['herbs', 'bread', 'olives', 'butter']];
    yield 'default, new keys checked in reverse' => [$check_butter_then_olives, 'default', ['herbs', 'bread', 'olives', 'butter']];
    yield 'selection, new keys checked in reverse' => [$check_butter_then_olives, 'selection', ['herbs', 'bread', 'butter', 'olives']];

    yield 'default, default key unchecked' => [self::KEY_SPACE . self::KEY_ENTER, 'default', ['herbs']];
    yield 'selection, every key unchecked' => [self::KEY_SPACE . self::KEY_DOWN . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, 'selection', []];
  }

  public function testInteractiveCompletedLineFollowsTheOrder(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['herbs', 'bread'], order: 'default', ctx: $this->defaultCtx()), self::KEY_ENTER);

    $this->assertStringContainsString('Herbs, Bread', $r['output']);
  }

  #[DataProvider('dataProviderDiscovered')]
  public function testDiscovered(mixed $discovered, ?string $ctx_discovered, string $order, array $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['herbs', 'bread'],
      order: $order,
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ));

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderDiscovered(): \Iterator {
    $list = ['butter', 'bread', 'herbs'];

    yield 'list, options' => [$list, NULL, 'options', ['bread', 'herbs', 'butter']];
    yield 'list, default' => [$list, NULL, 'default', ['herbs', 'bread', 'butter']];
    yield 'list, selection' => [$list, NULL, 'selection', ['butter', 'bread', 'herbs']];

    yield 'comma string, options' => ['butter,olives,herbs', NULL, 'options', ['olives', 'herbs', 'butter']];
    yield 'comma string, default' => ['butter,olives,herbs', NULL, 'default', ['herbs', 'olives', 'butter']];
    yield 'comma string, selection' => ['butter,olives,herbs', NULL, 'selection', ['butter', 'olives', 'herbs']];

    yield 'ctx value, default' => [NULL, 'butter,bread', 'default', ['bread', 'butter']];
    yield 'ctx value, selection' => [NULL, 'butter,bread', 'selection', ['butter', 'bread']];

    yield 'duplicates keep the first position' => [['herbs', 'bread', 'herbs'], NULL, 'selection', ['herbs', 'bread']];
  }

  #[DataProvider('dataProviderEnvValue')]
  public function testEnvValue(string $order, array $expected): void {
    $this->setEnvVars(['extras' => 'butter,bread,herbs']);

    $result = NULL;
    $this->captureOutput(function () use (&$result, $order): void {
      $result = Prompty::flow()
        ->multiselect('extras', 'Extras', options: self::EXTRAS, default: ['herbs', 'bread'], order: $order)
        ->run(unicode: FALSE);
    });

    $this->assertSame(['extras' => $expected], $result);
  }

  public static function dataProviderEnvValue(): \Iterator {
    yield 'options' => ['options', ['bread', 'herbs', 'butter']];
    yield 'default' => ['default', ['herbs', 'bread', 'butter']];
    yield 'selection' => ['selection', ['butter', 'bread', 'herbs']];
  }

  #[DataProvider('dataProviderNonInteractiveDefault')]
  public function testNonInteractiveDefault(string $order, array $expected, string $expected_display): void {
    $this->createAndSetInstance();
    Prompty::configure(interactive: FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result, $order): void {
      $result = Prompty::multiselect('Extras', options: self::EXTRAS, default: ['herbs', 'bread'], order: $order);
    });

    $this->assertSame($expected, $result);
    $this->assertStringContainsString($expected_display, $output);
  }

  public static function dataProviderNonInteractiveDefault(): \Iterator {
    yield 'options' => ['options', ['bread', 'herbs'], 'Bread, Herbs'];
    yield 'default' => ['default', ['herbs', 'bread'], 'Herbs, Bread'];
    yield 'selection' => ['selection', ['herbs', 'bread'], 'Herbs, Bread'];
  }

  public function testTransformAndValidateReceiveTheOrderedKeys(): void {
    $received = [];

    $r = $this->promptyRun(function () use (&$received): mixed {
      return Prompty::multiselect('Extras',
        options: self::EXTRAS,
        default: ['herbs', 'bread'],
        order: 'default',
        transform: function (array $keys) use (&$received): array {
          $received['transform'] = $keys;

          return $keys;
        },
        validate: function (array $keys) use (&$received): ?string {
          $received['validate'] = $keys;

          return NULL;
        },
        ctx: $this->defaultCtx(),
      );
    }, self::KEY_ENTER);

    $this->assertSame(['herbs', 'bread'], $r['result']);
    $this->assertSame(['transform' => ['herbs', 'bread'], 'validate' => ['herbs', 'bread']], $received);
  }

  public function testWindowOpensOnTheFirstPreCheckedOption(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['butter', 'olives'],
      scroll: 2,
      order: 'default',
      ctx: $this->defaultCtx(),
    ), self::KEY_SPACE . self::KEY_ENTER);

    // Focus opens on 'olives', so the space unchecks it.
    $this->assertSame(['butter'], $r['result']);
  }

  #[DataProvider('dataProviderInvalidOrder')]
  public function testInvalidOrder(string $order): void {
    $this->assertWidgetRejects(
      fn(): mixed => Prompty::multiselect('Extras', options: self::EXTRAS, order: $order, ctx: $this->defaultCtx()),
      sprintf('Order "%s" for "Extras" is not valid. Use "options", "default" or "selection".', $order),
    );
  }

  public static function dataProviderInvalidOrder(): \Iterator {
    yield 'unknown' => ['alphabetical'];
    yield 'empty' => [''];
    yield 'wrong case' => ['Default'];
  }

  public function testInvalidOrderInFlow(): void {
    $this->createAndSetInstance();

    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Order "random" for "Extras" is not valid. Use "options", "default" or "selection".',
      function (): void {
        Prompty::flow()
          ->multiselect('extras', 'Extras', options: self::EXTRAS, order: 'random', discovered: ['bread'])
          ->run(unicode: FALSE);
      },
    );
  }

}
