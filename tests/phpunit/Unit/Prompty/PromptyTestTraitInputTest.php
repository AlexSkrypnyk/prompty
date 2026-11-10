<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests 'promptyInput()', which feeds keystrokes to the configured instance.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTestTraitInputTest extends PromptyTestCase {

  protected const array COURSES = ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'];

  /**
   * Tests the configured instance and its settings survive the call.
   *
   * @param \Closure $configure
   *   Configures Prompty as the code under test would.
   */
  #[DataProvider('dataProviderKeepsConfiguredInstance')]
  public function testKeepsConfiguredInstance(\Closure $configure): void {
    $configure();
    $instance = $this->getStaticProperty('instance');
    $config = Prompty::config();

    $this->promptyInput('pear tart' . self::KEY_ENTER);

    $this->assertSame($instance, $this->getStaticProperty('instance'));
    $this->assertSame($config, Prompty::config());
  }

  public static function dataProviderKeepsConfiguredInstance(): \Iterator {
    $buffer = '';

    yield 'output callable' => [static fn(): mixed => Prompty::configure(output: function (string $chunk) use (&$buffer): void {
      $buffer .= $chunk;
    })];
    yield 'non-interactive' => [static fn(): mixed => Prompty::configure(interactive: FALSE)];
    yield 'placeholder fallback off' => [static fn(): mixed => Prompty::configure(placeholder_fallback: FALSE)];
    yield 'scroll' => [static fn(): mixed => Prompty::configure(scroll: 2)];
    yield 'display' => [static fn(): mixed => Prompty::configure(unicode: TRUE, ansi: FALSE, width: 60)];
  }

  public function testKeepsCollectedResults(): void {
    Prompty::configure(unicode: FALSE);
    $this->setProperty($this->getStaticProperty('instance'), 'results', ['course' => 'main']);

    $this->promptyInput(self::KEY_ENTER);

    $this->assertSame(['course' => 'main'], Prompty::results());
  }

  public function testWidgetReadsKeystrokes(): void {
    Prompty::configure(unicode: FALSE, ansi: FALSE, width: 80);
    $this->promptyInput($this->promptyKeys('pear tart', self::KEY_ENTER, self::KEY_DOWN, self::KEY_ENTER));

    $dish = NULL;
    $course = NULL;
    $this->captureOutput(function () use (&$dish, &$course): void {
      $dish = Prompty::text('Dish name');
      $course = Prompty::select('Course', options: self::COURSES);
    });

    $this->assertSame('pear tart', $dish);
    $this->assertSame('main', $course);
  }

  public function testConfiguredSettingAppliesToWidget(): void {
    Prompty::configure(unicode: FALSE, placeholder_fallback: FALSE);
    $this->promptyInput(self::KEY_ENTER);

    $dish = NULL;
    $this->captureOutput(function () use (&$dish): void {
      $dish = Prompty::text('Dish name', placeholder: 'pear tart');
    });

    $this->assertSame('', $dish);
  }

  public function testNonInteractiveInstanceReadsNoKeystrokes(): void {
    Prompty::configure(unicode: FALSE, interactive: FALSE);
    $this->promptyInput('onion soup' . self::KEY_ENTER);

    $dish = NULL;
    $this->captureOutput(function () use (&$dish): void {
      $dish = Prompty::text('Dish name', default: 'pear tart');
    });

    $this->assertSame('pear tart', $dish);
    $this->assertSame(0, ftell($this->inputStream()));
  }

  public function testOutputReachesConfiguredTarget(): void {
    $buffer = '';
    Prompty::configure(unicode: FALSE, ansi: FALSE, width: 80, output: function (string $chunk) use (&$buffer): void {
      $buffer .= $chunk;
    });
    $this->promptyInput('pear tart' . self::KEY_ENTER);

    $dish = NULL;
    $echoed = $this->captureOutput(function () use (&$dish): void {
      $dish = Prompty::text('Dish name');
    });

    $this->assertSame('pear tart', $dish);
    $this->assertSame('', $echoed);
    $this->assertStringContainsString("+  Dish name\n|  pear tart\n", $this->promptyNormalizeOutput($buffer));
  }

  public function testReadingPastLastKeystrokeCancels(): void {
    Prompty::configure(unicode: FALSE);
    $this->promptyInput('pear');

    $dish = 'unset';
    $output = $this->captureOutput(function () use (&$dish): void {
      $dish = Prompty::text('Dish name');
    });

    $this->assertNull($dish);
    $this->assertStringContainsString('(cancelled)', $output);
  }

  public function testCreatesInstanceWhenThereIsNone(): void {
    $this->assertNull($this->getStaticProperty('instance'));

    $this->promptyInput('pear tart' . self::KEY_ENTER);
    $instance = $this->getStaticProperty('instance');

    $this->assertInstanceOf(Prompty::class, $instance);
    $this->assertFalse(Prompty::config()['unicode']);
    $this->assertSame(80, Prompty::config()['width']);

    Prompty::configure(placeholder_fallback: FALSE);

    $this->assertSame($instance, $this->getStaticProperty('instance'));
    $this->assertFalse(Prompty::config()['placeholder_fallback']);
    $this->assertIsResource($this->getProperty($instance, 'input'));
  }

  public function testLaterCallReplacesKeystrokes(): void {
    Prompty::configure(unicode: FALSE);
    $this->promptyInput('pear tart' . self::KEY_ENTER);
    $first = $this->inputStream();

    $this->promptyInput('onion soup' . self::KEY_ENTER);

    $this->assertFalse(is_resource($first));

    $dish = NULL;
    $this->captureOutput(function () use (&$dish): void {
      $dish = Prompty::text('Dish name');
    });

    $this->assertSame('onion soup', $dish);
  }

  public function testTearDownClosesStreamAndResetsInstance(): void {
    Prompty::configure(unicode: FALSE);
    $this->promptyInput('pear tart' . self::KEY_ENTER);
    $stream = $this->inputStream();

    $this->promptyTearDown();

    $this->assertFalse(is_resource($stream));
    $this->assertNull($this->getStaticProperty('instance'));
  }

  public function testTearDownAfterStreamWasClosed(): void {
    Prompty::configure(unicode: FALSE);
    $this->promptyInput('pear tart' . self::KEY_ENTER);
    fclose($this->inputStream());

    $this->promptyTearDown();

    $this->assertNull($this->getStaticProperty('instance'));
  }

  public function testRunStillBuildsFreshInstance(): void {
    Prompty::configure(placeholder_fallback: FALSE);
    $this->promptyInput(self::KEY_ENTER);
    $configured = $this->getStaticProperty('instance');

    $running = NULL;
    $r = $this->promptyRun(function () use (&$running): mixed {
      $running = $this->getStaticProperty('instance');

      return Prompty::text('Dish name', placeholder: 'pear tart', ctx: $this->defaultCtx());
    }, self::KEY_ENTER);

    $this->assertSame('pear tart', $r['result']);
    $this->assertInstanceOf(Prompty::class, $running);
    $this->assertNotSame($configured, $running);
  }

  /**
   * Returns the input stream of the current instance.
   *
   * @return resource
   *   The stream promptyInput() installed.
   */
  protected function inputStream() {
    $stream = $this->getProperty($this->getStaticProperty('instance'), 'input');
    $this->assertIsResource($stream);

    return $stream;
  }

}
