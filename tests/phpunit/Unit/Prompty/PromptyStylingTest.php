<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty color() and bar() methods.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyStylingTest extends PromptyTestCase {

  #[DataProvider('dataProviderColor')]
  public function testColor(string $color_name, string $expected_code): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'color', 'hello', $color_name);

    $this->assertSame($expected_code . 'hello' . "\033[0m", $result);
  }

  public static function dataProviderColor(): \Iterator {
    yield 'dim' => ['dim', "\033[2m"];
    yield 'dim_italic' => ['dim_italic', "\033[2;3m"];
    yield 'cyan' => ['cyan', "\033[36m"];
    yield 'green' => ['green', "\033[32m"];
    yield 'red' => ['red', "\033[31m"];
    yield 'gray' => ['gray', "\033[90m"];
    yield 'bold' => ['bold', "\033[1m"];
    yield 'white' => ['white', "\033[37m"];
  }

  public function testColorUnknown(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'color', 'hello', 'nonexistent');

    $this->assertSame('hello', $result);
  }

  public function testColorEmptyText(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'color', '', 'cyan');

    $this->assertIsString($result);
    $this->assertSame("\033[36m\033[0m", $result);
  }

  #[DataProvider('dataProviderBar')]
  public function testBar(bool $unicode, string $expected_stripped): void {
    $p = $this->createInstance(['unicode' => $unicode]);

    $result = $this->callProtected($p, 'bar');

    $this->assertIsString($result);
    $this->assertSame($expected_stripped, $this->stripAnsi($result));
  }

  public static function dataProviderBar(): \Iterator {
    yield 'ascii bar' => [FALSE, '|'];
    yield 'unicode bar' => [TRUE, '│'];
  }

  public function testBarHasGrayColor(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'bar');

    $this->assertIsString($result);
    $this->assertStringStartsWith("\033[90m", $result);
    $this->assertStringEndsWith("\033[0m", $result);
  }

}
