<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for step keys, which are uppercased into variable names.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyStepKeyTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * The message a rejected step produces.
   *
   * @param string $key
   *   The offending key.
   * @param string $prefix
   *   The environment prefix in force.
   *
   * @return string
   *   The expected exception message.
   */
  protected function rejection(string $key, string $prefix = 'PROMPTY_'): string {
    return sprintf('Step "%s" is looked up as the environment variable "%s", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.', $key, $prefix . strtoupper($key));
  }

  #[DataProvider('dataProviderRejectsKey')]
  public function testRejectsKey(string $key): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection($key), function () use ($key): void {
      Prompty::flow(fn(): array => [$key => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderRejectsKey(): \Iterator {
    yield 'hyphen' => ['dish-name'];
    yield 'dot' => ['dish.name'];
    yield 'space' => ['dish name'];
    yield 'bracket' => ['dish[name]'];
  }

  public function testRejectsEmptyKey(): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, 'A step must have a key: it names the answer in the results and the variable the answer can come from.', function (): void {
      Prompty::flow(fn(): array => ['' => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE);
    });
  }

  public function testRejectsChildKey(): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection('with-dash'), function (): void {
      Prompty::flow(fn(): array => [
        'course' => Prompty::confirm('Send order?', discovered: TRUE, children: [
          'with-dash' => Prompty::text('Dish name', discovered: 'plum compote'),
        ]),
      ], unicode: FALSE);
    });
  }

  #[DataProvider('dataProviderAcceptsKey')]
  public function testAcceptsKey(string $key): void {
    $this->setEnvVars([$key => 'plum compote']);

    $result = NULL;
    $this->captureOutput(function () use ($key, &$result): void {
      $result = Prompty::flow(fn(): array => [$key => Prompty::text('Dish name')], unicode: FALSE);
    });

    $this->assertSame([$key => 'plum compote'], $result);
  }

  public static function dataProviderAcceptsKey(): \Iterator {
    yield 'lowercase' => ['dish'];
    yield 'underscored' => ['dish_name'];
    yield 'mixed case' => ['dishName'];
    yield 'trailing digit' => ['dish2'];
  }

  public function testRejectsKeyThatWouldStartTheNameWithDigit(): void {
    // An empty prefix leaves the key to start the name on its own, and a name
    // cannot start with a digit.
    $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection('2nd', ''), function (): void {
      Prompty::flow(fn(): array => ['2nd' => Prompty::text('Second course', discovered: 'main')], unicode: FALSE, env_prefix: '');
    });
  }

  public function testRejectsPrefixThatCannotBeExported(): void {
    $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection('dish', 'MY-APP_'), function (): void {
      Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE, env_prefix: 'MY-APP_');
    });
  }

  public function testAcceptsKeyStartingWithDigitBehindPrefix(): void {
    $this->setEnvVars(['2nd' => 'main']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => ['2nd' => Prompty::text('Second course')], unicode: FALSE);
    });

    $this->assertSame(['2nd' => 'main'], $result);
  }

}
