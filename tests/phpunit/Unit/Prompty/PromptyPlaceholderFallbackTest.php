<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the placeholder fallback option on the text and suggest widgets.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyPlaceholderFallbackTest extends PromptyTestCase {

  protected const array DISHES = ['Pear Tart', 'Apple Crumble', 'Onion Soup', 'Lentil Stew'];

  #[DataProvider('dataProviderEmptyAnswer')]
  public function testEmptyAnswer(\Closure $widget, string $keystrokes, bool $is_fallback, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes, ['placeholder_fallback' => $is_fallback]);

    $this->assertSame($expected, $r['result']);
  }

  public static function dataProviderEmptyAnswer(): \Iterator {
    $text = static fn(string $default = '', mixed $discovered = NULL): \Closure => static fn(array $ctx): mixed => Prompty::text('Dish name', default: $default, placeholder: 'pear tart', discovered: $discovered, ctx: $ctx);
    $suggest = static fn(string $default = '', mixed $discovered = NULL): \Closure => static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, default: $default, placeholder: 'pear tart', discovered: $discovered, ctx: $ctx);
    $clear = str_repeat(self::KEY_BACKSPACE, 12);

    yield 'text empty submit takes the placeholder' => [$text(), self::KEY_ENTER, TRUE, 'pear tart'];
    yield 'text empty submit stays empty' => [$text(), self::KEY_ENTER, FALSE, ''];
    yield 'text cleared default takes the placeholder' => [$text('plum compote'), $clear . self::KEY_ENTER, TRUE, 'pear tart'];
    yield 'text cleared default stays empty' => [$text('plum compote'), $clear . self::KEY_ENTER, FALSE, ''];
    yield 'text default submitted as is' => [$text('plum compote'), self::KEY_ENTER, FALSE, 'plum compote'];
    yield 'text typed answer' => [$text(), 'onion soup' . self::KEY_ENTER, FALSE, 'onion soup'];
    yield 'text blank discovered takes the placeholder' => [$text('', '  '), '', TRUE, 'pear tart'];
    yield 'text blank discovered stays empty' => [$text('', '  '), '', FALSE, ''];
    yield 'text blank discovered takes the default' => [$text('plum compote', ''), '', FALSE, 'plum compote'];
    yield 'suggest empty submit takes the placeholder' => [$suggest(), self::KEY_ENTER, TRUE, 'pear tart'];
    yield 'suggest empty submit stays empty' => [$suggest(), self::KEY_ENTER, FALSE, ''];
    yield 'suggest cleared default stays empty' => [$suggest('Lentil Stew'), str_repeat(self::KEY_BACKSPACE, 11) . self::KEY_ENTER, FALSE, ''];
    yield 'suggest focused suggestion' => [$suggest(), self::KEY_DOWN . self::KEY_ENTER, FALSE, 'Pear Tart'];
    yield 'suggest blank discovered takes the placeholder' => [$suggest('', ''), '', TRUE, 'pear tart'];
    yield 'suggest blank discovered stays empty' => [$suggest('', ''), '', FALSE, ''];
    yield 'suggest blank discovered takes the default' => [$suggest('Lentil Stew', ' '), '', FALSE, 'Lentil Stew'];
  }

  #[DataProvider('dataProviderPlaceholderIsDrawn')]
  public function testPlaceholderIsDrawn(\Closure $widget): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), self::KEY_ENTER, ['placeholder_fallback' => FALSE]);

    $this->assertSame('', $r['result']);
    $this->assertStringContainsString('|  pear tart█', $r['output']);
  }

  public static function dataProviderPlaceholderIsDrawn(): \Iterator {
    yield 'text' => [static fn(array $ctx): mixed => Prompty::text('Dish name', placeholder: 'pear tart', ctx: $ctx)];
    yield 'suggest' => [static fn(array $ctx): mixed => Prompty::suggest('Dish', options: self::DISHES, placeholder: 'pear tart', ctx: $ctx)];
  }

  public function testValidateRejectsEmptyAnswer(): void {
    $seen = [];
    $validate = static function (string $answer) use (&$seen): ?string {
      $seen[] = $answer;

      return $answer === '' ? 'Name the dish.' : NULL;
    };

    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', placeholder: 'pear tart', validate: $validate, ctx: $this->defaultCtx()), self::KEY_ENTER . 'onion soup' . self::KEY_ENTER, ['placeholder_fallback' => FALSE]);

    $this->assertSame('onion soup', $r['result']);
    $this->assertSame(['', 'onion soup'], $seen);
    $this->assertStringContainsString('Name the dish.', $r['output']);
  }

  public function testTransformReceivesEmptyAnswer(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::text('Dish name', placeholder: 'pear tart', transform: static fn(string $answer): string => $answer === '' ? 'chef special' : $answer, ctx: $this->defaultCtx()), self::KEY_ENTER, ['placeholder_fallback' => FALSE]);

    $this->assertSame('chef special', $r['result']);
  }

  #[DataProvider('dataProviderFlowOption')]
  public function testFlowOption(?bool $is_fallback, string $expected): void {
    $this->createAndSetInstance();
    $this->setEnvVars(['dish' => '']);

    $result = NULL;
    $this->captureOutput(function () use (&$result, $is_fallback): void {
      $result = Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name', placeholder: 'pear tart')], placeholder_fallback: $is_fallback);
    });

    $this->assertSame(['dish' => $expected], $result);
    $this->assertTrue(Prompty::config()['placeholder_fallback']);
  }

  public static function dataProviderFlowOption(): \Iterator {
    yield 'global setting' => [NULL, 'pear tart'];
    yield 'turned off for the flow' => [FALSE, ''];
  }

}
