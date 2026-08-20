<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for a blank discovered value, which stands for no answer supplied.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyBlankDiscoveredTest extends PromptyTestCase {

  protected const COURSES = ['starter' => 'Starter', 'main' => 'Main'];

  protected const EXTRAS = ['bread' => 'Bread', 'olives' => 'Olives'];

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * Build a context array carrying the confirm lists.
   *
   * @param array<string, mixed> $overrides
   *   Context overrides.
   *
   * @return array<string, mixed>
   *   Context array.
   */
  protected function ctx(array $overrides = []): array {
    return $this->defaultCtx(array_merge(['truthy' => ['1', 'true', 'yes'], 'falsy' => ['0', 'false', 'no']], $overrides));
  }

  #[DataProvider('dataProviderTextBlankTakesFallback')]
  public function testTextBlankTakesFallback(string $discovered, string $default, string $placeholder, string $expected): void {
    $result = NULL;
    $this->captureOutput(function () use ($discovered, $default, $placeholder, &$result): void {
      $result = Prompty::text('Dish name', default: $default, placeholder: $placeholder, discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderTextBlankTakesFallback(): \Iterator {
    yield 'empty takes placeholder' => ['', '', 'pear tart', 'pear tart'];
    yield 'blank takes placeholder' => ['   ', '', 'pear tart', 'pear tart'];
    yield 'empty takes default over placeholder' => ['', 'plum compote', 'pear tart', 'plum compote'];
    yield 'blank takes default over placeholder' => [' ', 'plum compote', 'pear tart', 'plum compote'];
    yield 'nothing to fall back to' => ['  ', '', '', ''];
  }

  #[DataProvider('dataProviderMultiselectBlankSelectsNothing')]
  public function testMultiselectBlankSelectsNothing(mixed $discovered): void {
    $result = NULL;
    $this->captureOutput(function () use ($discovered, &$result): void {
      $result = Prompty::multiselect('Extras', options: self::EXTRAS, discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame([], $result);
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

  public function testBlankEnvValueFollowsTheSameRule(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Dish name', placeholder: 'pear tart', ctx: $this->ctx(['discovered' => '']));
    });

    $this->assertSame('pear tart', $result);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Extras', options: self::EXTRAS, ctx: $this->ctx(['discovered' => '']));
    });

    $this->assertSame([], $result);
  }

}
