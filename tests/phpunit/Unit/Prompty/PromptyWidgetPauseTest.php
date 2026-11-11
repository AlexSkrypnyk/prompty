<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::pause(), which waits for a key.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetPauseTest extends PromptyTestCase {

  #[DataProvider('dataProviderPauseContinues')]
  public function testPauseContinues(string $keystrokes, bool $enter_only): void {
    $stream = $this->installKeystrokes($keystrokes);

    $result = NULL;
    $this->captureOutput(function () use (&$result, $enter_only): void {
      $result = Prompty::pause('Press any key to start the order...', enter_only: $enter_only, ctx: $this->defaultCtx());
    });

    $this->assertTrue($result);
    $this->assertSame(strlen($keystrokes), ftell($stream), 'Every byte of the key is read, and nothing after it.');

    fclose($stream);
  }

  public static function dataProviderPauseContinues(): \Iterator {
    yield 'letter' => ['x', FALSE];
    yield 'enter' => [self::KEY_ENTER, FALSE];
    yield 'space' => [self::KEY_SPACE, FALSE];
    yield 'cursor key' => [self::KEY_DOWN, FALSE];
    yield '2-byte character' => ['é', FALSE];
    yield '3-byte character' => ['€', FALSE];
    yield '4-byte character' => ['🍐', FALSE];
    yield 'enter only, enter' => [self::KEY_ENTER, TRUE];
    yield 'enter only, other keys first' => ['xy' . self::KEY_DOWN . self::KEY_SPACE . self::KEY_ENTER, TRUE];
  }

  public function testPauseLeavesLaterKeys(): void {
    $stream = $this->installKeystrokes('éx');

    $this->captureOutput(fn(): mixed => Prompty::pause('Press any key to start the order...', ctx: $this->defaultCtx()));

    $this->assertSame('x', fread($stream, 1));

    fclose($stream);
  }

  #[DataProvider('dataProviderPauseCancels')]
  public function testPauseCancels(string $keystrokes, bool $enter_only): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::pause('Press any key to start the order...', enter_only: $enter_only, ctx: $this->defaultCtx()), $keystrokes);

    $this->assertNull($r['result']);
    $this->assertStringEndsWith("o  Press any key to start the order...\n|  (cancelled)\n|\n", $r['output']);
  }

  public static function dataProviderPauseCancels(): \Iterator {
    yield 'escape' => [self::KEY_ESCAPE, FALSE];
    yield 'ctrl-c' => [self::KEY_CTRL_C, FALSE];
    yield 'end of input' => ['', FALSE];
    yield 'enter only, escape after a letter' => ['x' . self::KEY_ESCAPE, TRUE];
    yield 'enter only, end of input' => ['xy', TRUE];
  }

  #[DataProvider('dataProviderPauseRenders')]
  public function testPauseRenders(array $arguments, string $expected): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::pause('Press any key to start the order...', ...$arguments, ctx: $this->defaultCtx()), 'x');

    $this->assertSame($expected, $r['output']);
  }

  public static function dataProviderPauseRenders(): \Iterator {
    yield 'message' => [
      [],
      "o  Press any key to start the order...\n|\n\r+  Press any key to start the order...\n|\n",
    ];

    yield 'description' => [
      ['description' => "The kitchen opens at noon.\nTables are set by then."],
      "o  Press any key to start the order...\n|  The kitchen opens at noon.\n|  Tables are set by then.\n|\n\r+  Press any key to start the order...\n|\n",
    ];

    yield 'help' => [
      ['help' => 'Escape to leave.'],
      "o  Press any key to start the order...\n|\n|  Escape to leave.\n|\n\r+  Press any key to start the order...\n|\n",
    ];

    yield 'description and help' => [
      ['description' => 'The kitchen opens at noon.', 'help' => 'Escape to leave.'],
      "o  Press any key to start the order...\n|  The kitchen opens at noon.\n|\n|  Escape to leave.\n|\n\r+  Press any key to start the order...\n|\n",
    ];

    yield 'erased' => [
      ['erase' => TRUE],
      "o  Press any key to start the order...\n|\n\r",
    ];
  }

  public function testPauseErasesRows(): void {
    $this->installKeystrokes('x');

    ob_start();
    Prompty::pause('Press any key to start the order...', description: 'The kitchen opens at noon.', erase: TRUE, ctx: $this->defaultCtx());
    $output = (string) ob_get_clean();

    $this->assertStringEndsWith("\033[3A\r\033[J", $output);
  }

  public function testPauseColors(): void {
    $p = $this->createInstance(['ansi' => TRUE]);
    $this->setStaticProperty('instance', $p);
    $stream = fopen('php://memory', 'r+');
    $this->assertIsResource($stream);
    fwrite($stream, self::KEY_ESCAPE);
    rewind($stream);
    $this->setProperty($p, 'input', $stream);

    ob_start();
    Prompty::pause('Press any key to start the order...');
    $output = (string) ob_get_clean();

    $this->assertStringContainsString("\033[36mo\033[0m  Press any key to start the order...", $output);
    $this->assertStringContainsString("\033[31mo\033[0m  Press any key to start the order...", $output);
    $this->assertStringContainsString("  \033[31m(cancelled)\033[0m", $output);

    fclose($stream);
  }

  #[DataProvider('dataProviderPauseNonInteractive')]
  public function testPauseNonInteractive(bool $erase, string $expected): void {
    $stream = $this->installKeystrokes('x', FALSE);
    $p = $this->getStaticProperty('instance');
    $this->assertInstanceOf(Prompty::class, $p);
    $this->setProperty($p, 'cfgIsInteractive', FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result, $erase): void {
      $result = Prompty::pause('Press any key to start the order...', erase: $erase);
    });

    $this->assertTrue($result);
    $this->assertSame($expected, $output);
    $this->assertSame(0, ftell($stream), 'No input is read.');

    fclose($stream);
  }

  public static function dataProviderPauseNonInteractive(): \Iterator {
    yield 'completed line' => [FALSE, "+  Press any key to start the order...\n|\n"];
    yield 'erased' => [TRUE, ''];
  }

  public function testPauseStandalone(): void {
    $this->installKeystrokes(self::KEY_ENTER, FALSE);

    $result = NULL;
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::pause('Press Enter to send the order', enter_only: TRUE);
    });

    $this->assertTrue($result);
    $this->assertSame("o  Press Enter to send the order\n|\n\r+  Press Enter to send the order\n|\n", $output);
  }

  public function testPauseInFlow(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->pause('welcome', 'Press any key to start the order...')
      ->text('dish', 'Dish name', placeholder: 'pear tart')
      ->pause('ready', 'Press Enter to send the order', enter_only: TRUE, erase: TRUE)
      ->run(intro: 'Compose an order'), 'x' . self::KEY_ENTER . 'y' . self::KEY_ENTER);

    $this->assertSame(['welcome' => TRUE, 'dish' => 'pear tart', 'ready' => TRUE], $r['result']);
    $this->assertStringContainsString("+  Press any key to start the order...\n|\n", $r['output']);
  }

  public function testPauseInFlowCancels(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->pause('welcome', 'Press any key to start the order...')
      ->text('dish', 'Dish name', placeholder: 'pear tart')
      ->run(cancelled: 'Order cancelled.'), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringEndsWith("#  Order cancelled.\n\n", $r['output']);
  }

  public function testPauseInFlowNestedAndNumbered(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow()
      ->select('course', 'Course', options: ['main' => 'Main', 'dessert' => 'Dessert'])
        ->children()
          ->pause('welcome', 'Press any key to fire the mains...', description: 'The kitchen starts at once.')
          ->confirm('warm', 'Serve warm?')
        ->end()
      ->run(numbering: TRUE), self::KEY_ENTER . 'x' . self::KEY_ENTER);

    $this->assertSame(['course' => 'main', 'welcome' => TRUE, 'warm' => TRUE], $r['result']);
    $this->assertStringContainsString("|  +  Press any key to fire the mains... (1.1)\n|  |  \n", $r['output']);
  }

  #[DataProvider('dataProviderReadCharacterRest')]
  public function testReadCharacterRest(string $key, int $expected): void {
    $stream = $this->installKeystrokes('abcd');
    $p = $this->getStaticProperty('instance');
    $this->assertInstanceOf(Prompty::class, $p);

    $this->callProtected($p, 'readCharacterRest', $key);

    $this->assertSame($expected, ftell($stream));

    fclose($stream);
  }

  public static function dataProviderReadCharacterRest(): \Iterator {
    yield 'ASCII' => ['x', 0];
    yield 'key name' => ['enter', 0];
    yield 'continuation byte' => ["\x80", 0];
    yield '2-byte lead' => ["\xC3", 1];
    yield '3-byte lead' => ["\xE2", 2];
    yield '4-byte lead' => ["\xF0", 3];
    yield 'invalid lead' => ["\xF8", 0];
  }

}
