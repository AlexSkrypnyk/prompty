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
   * The message a rejected key produces.
   *
   * @param string $key
   *   The offending key.
   *
   * @return string
   *   The expected exception message.
   */
  protected function rejection(string $key): string {
    return sprintf('Step key "%s" is not valid. A step key is uppercased into an environment variable name, so it may hold only letters, digits and underscores.', $key);
  }

  #[DataProvider('dataProviderRejectsKey')]
  public function testRejectsKey(string $key): void {
    $output = $this->captureOutputThrows(\InvalidArgumentException::class, $this->rejection($key), function () use ($key): void {
      Prompty::flow(fn(): array => [$key => Prompty::text('Dish name', discovered: 'plum compote')], unicode: FALSE);
    });

    $this->assertSame('', $output);
  }

  public static function dataProviderRejectsKey(): \Iterator {
    yield 'hyphen' => ['ci-provider'];
    yield 'dot' => ['ci.provider'];
    yield 'space' => ['ci provider'];
    yield 'bracket' => ['ci[provider]'];
    yield 'empty' => [''];
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

}
