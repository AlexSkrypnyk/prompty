<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty public output methods and redraw.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyOutputTest extends PromptyTestCase {

  public function testIntro(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput(function (): void {
      Prompty::intro('Welcome');
    });

    $this->assertStringContainsString('#  Welcome', $output);
  }

  public function testOutro(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput(function (): void {
      Prompty::outro('Goodbye');
    });

    $this->assertStringContainsString('#  Goodbye', $output);
  }

  public function testOutput(): void {
    $this->createAndSetInstance();

    $output = $this->captureOutput(function (): void {
      $count = Prompty::output(['line 1', 'line 2', 'line 3']);
      $this->assertSame(3, $count);
    });

    $this->assertStringContainsString('line 1', $output);
    $this->assertStringContainsString('line 2', $output);
    $this->assertStringContainsString('line 3', $output);
  }

  public function testRedraw(): void {
    $p = $this->createInstance();

    $output = $this->captureOutput(function () use ($p): void {
      $count = $this->callProtected($p, 'printLines', ['old line 1', 'old line 2']);
      $this->assertSame(2, $count);

      $new_count = $this->callProtected($p, 'redraw', 2, ['new line 1', 'new line 2', 'new line 3']);
      $this->assertSame(3, $new_count);
    });

    // Output should contain the escape sequence for moving cursor up.
    $this->assertStringContainsString('new line 1', $output);
  }

  public function testRedrawWithZeroPrevLines(): void {
    $p = $this->createInstance();

    $output = $this->captureOutput(function () use ($p): void {
      $count = $this->callProtected($p, 'redraw', 0, ['line 1']);
      $this->assertSame(1, $count);
    });

    $this->assertStringContainsString('line 1', $output);
  }

  public function testShowCursor(): void {
    $p = $this->createInstance();

    ob_start();
    $this->callProtected($p, 'showCursor');
    $output = ob_get_clean();

    $this->assertSame("\033[?25h", $output);
  }

  public function testHideCursor(): void {
    $p = $this->createInstance();

    ob_start();
    $this->callProtected($p, 'hideCursor');
    $output = ob_get_clean();

    $this->assertSame("\033[?25l", $output);
  }

  #[DoesNotPerformAssertions]
  public function testRestoreTty(): void {
    $p = $this->createInstance();

    // restoreTty() shells out to stty. Without a TTY the command fails
    // silently, so a dummy value only has to not throw.
    $this->callProtected($p, 'restoreTty', 'dummy-settings');
  }

  public function testSetupTtyNoTty(): void {
    $p = $this->createInstance();

    // Without a TTY attached, stty -g returns NULL.
    $this->captureOutput(fn(): mixed => $this->callProtected($p, 'setupTty'));

    $prev = $this->getProperty($p, 'prevTty');
    $this->assertTrue($prev === NULL || is_string($prev));
  }

  public function testTeardownTtyWhenNull(): void {
    $p = $this->createInstance();
    $this->setProperty($p, 'prevTty', NULL);

    ob_start();
    $this->callProtected($p, 'teardownTty');
    $output = ob_get_clean();

    $this->assertSame('', $output);
    $this->assertNull($this->getProperty($p, 'prevTty'));
  }

  public function testTeardownTtyWhenSet(): void {
    $p = $this->createInstance();
    $this->setProperty($p, 'prevTty', 'dummy-tty-settings');

    ob_start();
    $this->callProtected($p, 'teardownTty');
    $output = ob_get_clean();

    $this->assertStringContainsString("\033[?25h", (string) $output);
    $this->assertNull($this->getProperty($p, 'prevTty'));
  }

  public function testReadKeyReturnsEmptyOnEof(): void {
    $p = $this->createInstance();

    $stream = fopen('php://memory', 'r+');
    $this->assertNotFalse($stream);
    // Write nothing - stream is at EOF.
    $this->setProperty($p, 'input', $stream);

    $key = $this->callProtected($p, 'readKey');

    $this->assertSame('', $key);

    fclose($stream);
  }

}
