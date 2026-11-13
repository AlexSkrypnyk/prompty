<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for a task whose action configures another output target.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTaskOutputTest extends PromptyTestCase {

  /**
   * Output written to the target the task started on.
   */
  protected string $started = '';

  /**
   * Output written to the target the action configured.
   */
  protected string $configured = '';

  #[DataProvider('dataProviderStreamConfigureOutput')]
  public function testStreamConfigureOutput(string $target): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $this->setProperty($p, 'cfgOutput', $this->collectStarted(...));
    $stream = fopen('php://memory', 'w+');
    $this->assertIsResource($stream);

    $new_target = match ($target) {
      'echo' => function (string $chunk): void {
        echo $chunk;
      },
      'stream' => $stream,
      default => $this->collectConfigured(...),
    };

    $echoed = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function () use ($new_target): bool {
      echo "Oven at 180°C\n";
      Prompty::configure(output: $new_target);
      Prompty::task('Preheating the oven', action: fn(): bool => TRUE, success: 'Oven ready');
      Prompty::output(['Tray 1 in']);
      echo "Tray 2 in\n";

      return TRUE;
    }, success: 'Tart baked', stream: TRUE));

    rewind($stream);

    $this->assertSame('', $echoed);
    $this->assertSame('', (string) stream_get_contents($stream));
    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|    o  Preheating the oven\n|    |  + Oven ready\n|    |\n|    Tray 1 in\n|    Tray 2 in\n|\n|  + Tart baked\n|\n", $this->promptyNormalizeOutput($this->started));
    $this->assertSame('', $this->configured);

    // The configured target stays in force after the task.
    $echoed = $this->captureOutput(fn(): int => Prompty::output(['Oven off']));
    rewind($stream);

    $this->assertSame("Oven off\n", $this->promptyNormalizeOutput($echoed . stream_get_contents($stream) . $this->configured));
  }

  public static function dataProviderStreamConfigureOutput(): \Iterator {
    yield 'callable that echoes' => ['echo'];
    yield 'stream' => ['stream'];
    yield 'callable' => ['callable'];
  }

  public function testStreamFromEchoConfigureOutput(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $output = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      echo "Oven at 180°C\n";
      Prompty::configure(output: $this->collectConfigured(...));
      Prompty::output(['Tray 1 in']);

      return TRUE;
    }, success: 'Tart baked', stream: TRUE));

    $this->assertSame("o  Baking the tart\n|    Oven at 180°C\n|    Tray 1 in\n|\n|  + Tart baked\n|\n", $output);
    $this->assertSame('', $this->configured);
  }

  public function testStreamNestedConfigureOutput(): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $this->setProperty($p, 'cfgOutput', $this->collectStarted(...));

    $echoed = $this->captureOutput(fn(): mixed => Prompty::task('Baking the tart', action: function (): bool {
      Prompty::task('Making the pastry', action: function (): bool {
        Prompty::configure(output: function (string $chunk): void {
          echo $chunk;
        });
        echo "Butter rubbed in\n";

        return TRUE;
      }, success: 'Pastry made', stream: TRUE);
      echo "Tart in the oven\n";

      return TRUE;
    }, success: 'Tart baked', stream: TRUE));

    $this->assertSame('', $echoed);
    $this->assertSame("o  Baking the tart\n|    o  Making the pastry\n|    |    Butter rubbed in\n|    |\n|    |  + Pastry made\n|    |\n|    Tart in the oven\n|\n|  + Tart baked\n|\n", $this->promptyNormalizeOutput($this->started));
  }

  #[DataProvider('dataProviderSpinnerConfigureOutput')]
  public function testSpinnerConfigureOutput(bool $is_live, string $expected): void {
    $p = $this->createAndSetInstance($is_live ? [] : ['interactive' => FALSE]);
    $this->setProperty($p, 'isTerminal', $is_live);
    $this->setProperty($p, 'cfgOutput', $this->collectStarted(...));

    Prompty::task('Preheating the oven', action: function (): bool {
      Prompty::configure(output: $this->collectConfigured(...));

      return TRUE;
    }, success: 'Oven ready');

    $this->assertSame($expected, $this->stripColors($this->started));
    $this->assertSame('', $this->configured);
  }

  public static function dataProviderSpinnerConfigureOutput(): \Iterator {
    yield 'printed' => [FALSE, "o  Preheating the oven\n|  + Oven ready\n|\n"];
    yield 'live' => [TRUE, "\033[?25l|  Preheating the oven\n\033[1A\r\033[J+  Preheating the oven\n|  + Oven ready\n|\n\033[?25h"];
  }

  #[DataProvider('dataProviderConfigureOutputWhileDrawing')]
  public function testConfigureOutputWhileDrawing(bool $is_streamed): void {
    $p = $this->createAndSetInstance(['interactive' => FALSE]);
    $this->setProperty($p, 'cfgOutput', function (string $chunk): void {
      $this->started .= $chunk;

      if (str_contains($chunk, 'Oven ready')) {
        Prompty::configure(output: $this->collectConfigured(...));
      }
    });

    Prompty::task('Preheating the oven', action: fn(): bool => TRUE, success: 'Oven ready', stream: $is_streamed);
    Prompty::output(['Oven off']);

    $this->assertSame("o  Preheating the oven\n|  + Oven ready\n|\n", $this->promptyNormalizeOutput($this->started));
    $this->assertSame("Oven off\n", $this->promptyNormalizeOutput($this->configured));
    $this->assertSame([], $this->getProperty($p, 'drawTargets'));
  }

  public static function dataProviderConfigureOutputWhileDrawing(): \Iterator {
    yield 'spinner' => [FALSE];
    yield 'streamed' => [TRUE];
  }

  public function testStreamLiveConfigureOutput(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);
    $this->setProperty($p, 'cfgOutput', $this->collectStarted(...));

    Prompty::task('Baking the tart', action: function (): bool {
      Prompty::configure(output: $this->collectConfigured(...));
      echo "Oven at 180°C\n";

      return TRUE;
    }, success: 'Tart baked', stream: TRUE);

    $this->assertSame("\033[?25lo  Baking the tart\n\033[1A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n\033[2A\r\033[J+  Baking the tart\n|  + Tart baked (1 line)\n|\n\033[?25h", $this->stripColors($this->started));
    $this->assertSame('', $this->configured);
  }

  public function testStreamLiveExceptionConfigureOutput(): void {
    $p = $this->createAndSetInstance();
    $this->setProperty($p, 'isTerminal', TRUE);
    $this->setProperty($p, 'cfgOutput', $this->collectStarted(...));

    try {
      Prompty::task('Baking the tart', action: function (): never {
        Prompty::configure(output: $this->collectConfigured(...));
        echo "Oven at 180°C\n";

        throw new \RuntimeException('The oven door is open.');
      }, stream: TRUE);
      $this->fail('The exception was not rethrown.');
    }
    catch (\RuntimeException $runtime_exception) {
      $this->assertSame('The oven door is open.', $runtime_exception->getMessage());
    }

    $this->assertSame("\033[?25lo  Baking the tart\n\033[1A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n\033[2A\r\033[Jo  Baking the tart\n|    Oven at 180°C\n|\n|  ! The oven door is open.\n|\n\033[?25h", $this->stripColors($this->started));
    $this->assertSame('', $this->configured);
  }

  /**
   * Strip SGR sequences, keep cursor movement and normalize line endings.
   */
  protected function stripColors(string $text): string {
    return str_replace(PHP_EOL, "\n", preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text);
  }

  /**
   * Collects output written to the target the task started on.
   */
  protected function collectStarted(string $chunk): void {
    $this->started .= $chunk;
  }

  /**
   * Collects output written to the target the action configured.
   */
  protected function collectConfigured(string $chunk): void {
    $this->configured .= $chunk;
  }

}
