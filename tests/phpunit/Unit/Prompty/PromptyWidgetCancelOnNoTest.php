<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'cancel_on_no' argument of the confirm widget.
 *
 * A No settles the widget on its completed line and answers NULL, so a flow
 * ends the way Escape ends it.
 */
#[CoversClass(Prompty::class)]
#[CoversClass(PromptyFlow::class)]
#[Group('unit')]
final class PromptyWidgetCancelOnNoTest extends PromptyTestCase {

  #[DataProvider('dataProviderWidgetAnswer')]
  public function testWidgetAnswer(\Closure $widget, string $keystrokes, ?bool $expected, string $expected_output, array $config = []): void {
    $r = $this->promptyRun(fn(): mixed => $widget($this->defaultCtx()), $keystrokes, $config);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString($expected_output, $r['output']);
    $this->assertStringNotContainsString('(cancelled)', $r['output']);
  }

  public static function dataProviderWidgetAnswer(): \Iterator {
    $confirm = static fn(array $ctx): mixed => Prompty::confirm('Send order?', cancel_on_no: TRUE, ctx: $ctx);
    $confirm_no = static fn(array $ctx): mixed => Prompty::confirm('Send order?', default: FALSE, cancel_on_no: TRUE, ctx: $ctx);

    yield 'no by arrow key' => [$confirm, self::KEY_RIGHT . self::KEY_ENTER, NULL, "+  Send order?\n|  No\n"];
    yield 'no by n key' => [$confirm, 'n' . self::KEY_ENTER, NULL, "+  Send order?\n|  No\n"];
    yield 'no by default' => [$confirm_no, self::KEY_ENTER, NULL, "+  Send order?\n|  No\n"];
    yield 'yes' => [$confirm, self::KEY_ENTER, TRUE, "+  Send order?\n|  Yes\n"];
    yield 'no without the argument' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', default: FALSE, ctx: $ctx), self::KEY_ENTER, FALSE, "+  Send order?\n|  No\n"];
    yield 'no from discovered' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', cancel_on_no: TRUE, discovered: 'no', ctx: $ctx), '', NULL, "+  Send order?\n|  No\n"];
    yield 'yes from discovered' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', cancel_on_no: TRUE, discovered: 'yes', ctx: $ctx), '', TRUE, "+  Send order?\n|  Yes\n"];
    yield 'no from default, non-interactive' => [$confirm_no, '', NULL, "+  Send order?\n|  No\n", ['interactive' => FALSE]];
    yield 'no after transform' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', cancel_on_no: TRUE, transform: static fn(bool $answer): bool => !$answer, ctx: $ctx), self::KEY_ENTER, NULL, "+  Send order?\n|  No\n"];
    yield 'yes after transform' => [static fn(array $ctx): mixed => Prompty::confirm('Send order?', cancel_on_no: TRUE, transform: static fn(bool $answer): bool => !$answer, ctx: $ctx), self::KEY_RIGHT . self::KEY_ENTER, TRUE, "+  Send order?\n|  Yes\n"];
  }

  #[DataProvider('dataProviderLaterConditionsNotRun')]
  public function testLaterConditionsNotRun(array $env, string $keystrokes): void {
    $this->setEnvVars($env);
    $calls = new \ArrayObject();

    $r = $this->promptyRun(static fn(): mixed => Prompty::flow()
      ->text('dish', 'Dish name')
        ->children()
          ->confirm('send', 'Send order?', cancel_on_no: TRUE)
          ->text('note', 'Kitchen note')
            ->when(static function () use ($calls): bool {
              $calls->append('note');

              return TRUE;
            })
        ->end()
      ->run(unicode: FALSE), $keystrokes);

    $this->assertNull($r['result']);
    $this->assertCount(0, $calls);
    $this->assertStringContainsString("|  +  Send order?\n|  |  No\n", $r['output']);
  }

  public static function dataProviderLaterConditionsNotRun(): \Iterator {
    yield 'no from env' => [['dish' => 'pear tart', 'send' => 'no'], ''];
    yield 'no by key' => [[], 'pear tart' . self::KEY_ENTER . 'n' . self::KEY_ENTER];
  }

  public function testValidatorRejectingNoKeepsWidgetActive(): void {
    $validate = static fn(bool $answer): ?string => $answer ? NULL : 'Send the order to continue.';

    $r = $this->promptyRun(fn(): mixed => Prompty::confirm('Send order?', default: FALSE, cancel_on_no: TRUE, validate: $validate, ctx: $this->defaultCtx()), self::KEY_ENTER . 'y' . self::KEY_ENTER);

    $this->assertTrue($r['result']);
    $this->assertStringContainsString('Send the order to continue.', $r['output']);
  }

  #[DataProvider('dataProviderFlow')]
  public function testFlow(\Closure $build, array $env, string $keystrokes, ?array $expected, string $expected_output): void {
    $this->setEnvVars($env);

    $r = $this->promptyRun(static fn(): mixed => $build(Prompty::flow())->run(cancelled: 'Order cancelled.', unicode: FALSE), $keystrokes);

    $this->assertSame($expected, $r['result']);
    $this->assertStringContainsString($expected_output, $r['output']);
  }

  public static function dataProviderFlow(): \Iterator {
    $flow = static fn(PromptyFlow $flow): PromptyFlow => $flow
      ->text('dish', 'Dish name')
      ->confirm('send', 'Send order?', cancel_on_no: TRUE)
      ->text('note', 'Kitchen note');

    yield 'no from env' => [$flow, ['dish' => 'pear tart', 'send' => 'no'], '', NULL, "+  Send order?\n|  No\n|\n#  Order cancelled.\n"];
    yield 'no by key' => [$flow, [], 'pear tart' . self::KEY_ENTER . 'n' . self::KEY_ENTER, NULL, "+  Send order?\n|  No\n|\n#  Order cancelled.\n"];
    yield 'yes by key' => [$flow, [], 'pear tart' . self::KEY_ENTER . self::KEY_ENTER . 'no onions' . self::KEY_ENTER, ['dish' => 'pear tart', 'send' => TRUE, 'note' => 'no onions'], "+  Kitchen note\n|  no onions\n"];

    yield 'argument from a closure' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name')
        ->confirm('send', 'Send order?', cancel_on_no: static fn(array $results): bool => $results['dish'] === 'pear tart'),
      ['dish' => 'pear tart', 'send' => 'no'],
      '',
      NULL,
      "|  No\n|\n#  Order cancelled.\n",
    ];

    yield 'argument from a closure, not set' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name')
        ->confirm('send', 'Send order?', cancel_on_no: static fn(array $results): bool => $results['dish'] === 'pear tart'),
      ['dish' => 'onion soup', 'send' => 'no'],
      '',
      ['dish' => 'onion soup', 'send' => FALSE],
      "|  No\n|\n",
    ];

    yield 'nested child' => [
      static fn(PromptyFlow $flow): PromptyFlow => $flow
        ->text('dish', 'Dish name')
          ->children()
            ->confirm('send', 'Send order?', cancel_on_no: TRUE)
          ->end()
        ->text('note', 'Kitchen note'),
      ['dish' => 'pear tart', 'send' => 'no'],
      '',
      NULL,
      "|  +  Send order?\n|     No\n|     \n#  Order cancelled.\n",
    ];
  }

}
