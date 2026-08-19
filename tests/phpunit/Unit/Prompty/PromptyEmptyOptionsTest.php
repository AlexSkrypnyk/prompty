<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests select and multiselect against an empty options map.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyEmptyOptionsTest extends PromptyTestCase {

  /**
   * Message the select widget throws for an empty options map.
   */
  protected const MESSAGE_SELECT = 'No options declared for "Course". Provide at least one option.';

  /**
   * Message the multiselect widget throws for an empty options map.
   */
  protected const MESSAGE_MULTISELECT = 'No options declared for "Extras". Provide at least one option.';

  /**
   * Keystrokes that would reach a widget's interactive loop.
   *
   * The guard fires before the loop, so every navigation or answer key must
   * fail the same way for both widgets.
   *
   * @return \Iterator<string, array{string}>
   *   One keystroke sequence per case.
   */
  protected static function keystrokeCases(): \Iterator {
    yield 'enter' => [self::KEY_ENTER];
    yield 'space' => [self::KEY_SPACE];
    yield 'down' => [self::KEY_DOWN];
    yield 'up' => [self::KEY_UP];
    yield 'left' => [self::KEY_LEFT];
    yield 'right' => [self::KEY_RIGHT];
  }

  #[DataProvider('dataProviderSelectInteractiveRejectsEmptyOptions')]
  public function testSelectInteractiveRejectsEmptyOptions(string $keystrokes): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Course',
      options: [],
      ctx: $this->defaultCtx(),
    ), self::MESSAGE_SELECT, $keystrokes);
  }

  public static function dataProviderSelectInteractiveRejectsEmptyOptions(): \Iterator {
    yield from self::keystrokeCases();
  }

  #[DataProvider('dataProviderMultiselectInteractiveRejectsEmptyOptions')]
  public function testMultiselectInteractiveRejectsEmptyOptions(string $keystrokes): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: [],
      ctx: $this->defaultCtx(),
    ), self::MESSAGE_MULTISELECT, $keystrokes);
  }

  public static function dataProviderMultiselectInteractiveRejectsEmptyOptions(): \Iterator {
    yield from self::keystrokeCases();
  }

  #[DataProvider('dataProviderSelectDiscoveredRejectsEmptyOptions')]
  public function testSelectDiscoveredRejectsEmptyOptions(?string $discovered, ?string $ctx_discovered): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Course',
      options: [],
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ), self::MESSAGE_SELECT);
  }

  public static function dataProviderSelectDiscoveredRejectsEmptyOptions(): \Iterator {
    yield 'discovered argument' => ['starter', NULL];
    yield 'env value' => [NULL, 'starter'];
  }

  #[DataProvider('dataProviderMultiselectDiscoveredRejectsEmptyOptions')]
  public function testMultiselectDiscoveredRejectsEmptyOptions(?array $discovered, ?string $ctx_discovered): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Extras',
      options: [],
      discovered: $discovered,
      ctx: $this->defaultCtx(['discovered' => $ctx_discovered]),
    ), self::MESSAGE_MULTISELECT);
  }

  public static function dataProviderMultiselectDiscoveredRejectsEmptyOptions(): \Iterator {
    yield 'discovered argument' => [['bread'], NULL];
    yield 'empty discovered list' => [[], NULL];
    yield 'env value' => [NULL, 'bread'];
    yield 'empty env value' => [NULL, ''];
  }

  public function testSelectStandaloneRejectsEmptyOptions(): void {
    $this->createAndSetInstance(['unicode' => FALSE]);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, self::MESSAGE_SELECT, fn(): mixed => Prompty::select('Course', options: []));

    $this->assertSame('', $output);
  }

  public function testMultiselectStandaloneRejectsEmptyOptions(): void {
    $this->createAndSetInstance(['unicode' => FALSE]);

    $output = $this->captureOutputThrows(\InvalidArgumentException::class, self::MESSAGE_MULTISELECT, fn(): mixed => Prompty::multiselect('Extras', options: []));

    $this->assertSame('', $output);
  }

  public function testFlowSelectRejectsEmptyOptions(): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, self::MESSAGE_SELECT, fn(): mixed => Prompty::flow(fn(): array => [
      'course' => Prompty::select('Course', options: []),
    ], unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testFlowMultiselectRejectsEmptyOptions(): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, self::MESSAGE_MULTISELECT, fn(): mixed => Prompty::flow(fn(): array => [
      'extras' => Prompty::multiselect('Extras', options: []),
    ], unicode: FALSE));

    $this->assertSame('', $output);
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public function testFlowStepSkippedByConditionIsNotValidated(): void {
    $result = NULL;

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'extras' => Prompty::multiselect('Extras', options: [], condition: fn(): bool => FALSE),
      ], unicode: FALSE);
    });

    $this->assertSame([], $result);
    $this->assertSame('', $output);
  }

  public function testSelectAcceptsSingleOption(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::select('Course',
      options: ['starter' => 'Starter'],
      ctx: $this->defaultCtx(),
    ), self::KEY_ENTER);

    $this->assertSame('starter', $r['result']);
  }

  public function testMultiselectAcceptsSingleOption(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::multiselect('Extras',
      options: ['bread' => 'Bread'],
      ctx: $this->defaultCtx(),
    ), self::KEY_SPACE . self::KEY_ENTER);

    $this->assertSame(['bread'], $r['result']);
  }

}
