<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the default parameter against a widget's declared options.
 *
 * A default names an option key, so it is held to the same domain as a
 * discovered value: an entry that matches nothing is a caller mistake, not a
 * widget that quietly opens on the first option with nothing checked.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyDefaultDomainTest extends PromptyTestCase {

  /**
   * Option set for the select cases.
   */
  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  /**
   * Option set for the multiselect cases.
   */
  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];

  #[DataProvider('dataProviderSelectRejectsDefaultOutsideOptions')]
  public function testSelectRejectsDefaultOutsideOptions(string $default, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Course',
      options: self::COURSES,
      default: $default,
      ctx: $this->defaultCtx(),
    ), $message);
  }

  public static function dataProviderSelectRejectsDefaultOutsideOptions(): \Iterator {
    yield 'unknown key' => ['pudding', 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'option label is not a key' => ['Dessert', 'Default value "Dessert" for "Course" is not a valid option. Available options: starter, main, dessert.'];
    yield 'whitespace around a miss' => ['  pudding  ', 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.'];
  }

  #[DataProvider('dataProviderMultiselectRejectsDefaultOutsideOptions')]
  public function testMultiselectRejectsDefaultOutsideOptions(array $default, string $message): void {
    /** @var list<int|string> $default */
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: $default,
      ctx: $this->defaultCtx(),
    ), $message);
  }

  public static function dataProviderMultiselectRejectsDefaultOutsideOptions(): \Iterator {
    yield 'sole entry unknown' => [['pickles'], 'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
    yield 'one entry of several unknown' => [['bread', 'pickles'], 'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
    yield 'option label is not a key' => [['Bread'], 'Default value "Bread" for "Extras" is not a valid option. Available options: bread, olives, herbs.'];
  }

  public function testSelectRejectsDefaultOutsideNumericOptions(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Table',
      options: ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'],
      default: '99',
      ctx: $this->defaultCtx(),
    ), 'Default value "99" for "Table" is not a valid option. Available options: 4, 7, 12.');
  }

  public function testMultiselectRejectsDefaultOutsideNumericOptions(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Seats',
      options: ['1' => 'Seat 1', '2' => 'Seat 2'],
      default: [9],
      ctx: $this->defaultCtx(),
    ), 'Default value "9" for "Seats" is not a valid option. Available options: 1, 2.');
  }

  public function testSelectRejectsDefaultEvenWhenDiscovered(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Course',
      options: self::COURSES,
      default: 'pudding',
      discovered: 'main',
      ctx: $this->defaultCtx(),
    ), 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.');
  }

  public function testMultiselectRejectsDefaultEvenWhenDiscovered(): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: ['pickles'],
      discovered: ['bread'],
      ctx: $this->defaultCtx(),
    ), 'Default value "pickles" for "Extras" is not a valid option. Available options: bread, olives, herbs.');
  }

  public function testFlowRejectsDefaultOutsideOptions(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, 'Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.', fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: self::COURSES, default: 'pudding'),
    ], unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testFlowStepSkippedByConditionKeepsDefaultUnvalidated(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);
    $result = NULL;

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: self::EXTRAS, default: ['pickles'], condition: fn(): bool => FALSE),
      ], unicode: FALSE);
    });

    $this->assertSame([], $result);
    $this->assertSame('', $output);
  }

  public function testSelectAcceptsEmptyDefault(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course',
      options: self::COURSES,
      default: '',
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame('starter', $r['result']);
  }

  public function testMultiselectAcceptsEmptyDefault(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: self::EXTRAS,
      default: [],
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame([], $r['result']);
  }

  public function testSelectAcceptsDeclaredDefault(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course',
      options: self::COURSES,
      default: 'dessert',
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame('dessert', $r['result']);
    $this->assertStringContainsString('> (*) Dessert', $r['output']);
  }

  #[DataProvider('dataProviderMultiselectAcceptsDeclaredDefaults')]
  public function testMultiselectAcceptsDeclaredDefaults(array $options, array $default, array $expected): void {
    /** @var array<int|string, string> $options */
    /** @var list<int|string> $default */
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Seats',
      options: $options,
      default: $default,
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderMultiselectAcceptsDeclaredDefaults(): \Iterator {
    yield 'string keys' => [self::EXTRAS, ['bread', 'herbs'], ['bread', 'herbs']];
    yield 'every option' => [self::EXTRAS, ['bread', 'olives', 'herbs'], ['bread', 'olives', 'herbs']];
    yield 'int keys against numeric options' => [['1' => 'Seat 1', '2' => 'Seat 2', '4' => 'Seat 4'], [2, 4], ['2', '4']];
    yield 'declaration order restored' => [self::EXTRAS, ['herbs', 'bread'], ['bread', 'herbs']];
  }

}
