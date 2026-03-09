<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty numberLabel() method.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyLabelTest extends PromptyTestCase {

  #[DataProvider('dataProviderNumberLabel')]
  public function testNumberLabel(string $label, array $ctx, string $expected_stripped): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'numberLabel', $label, $ctx);

    $this->assertIsString($result);
    $this->assertSame($expected_stripped, $this->stripAnsi($result));
  }

  public static function dataProviderNumberLabel(): \Iterator {
    yield 'simple number' => [
      'Project name',
      ['number' => '1'],
      'Project name (1)',
    ];

    yield 'nested number' => [
      'Framework',
      ['number' => '1.2'],
      'Framework (1.2)',
    ];

    yield 'deeply nested number' => [
      'SSR mode',
      ['number' => '1.1.3'],
      'SSR mode (1.1.3)',
    ];

    yield 'no number in ctx' => [
      'Project name',
      [],
      'Project name',
    ];

    yield 'null number in ctx' => [
      'Project name',
      ['number' => NULL],
      'Project name',
    ];
  }

  public function testNumberLabelHasDimColor(): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'numberLabel', 'Label', ['number' => '1']);

    // The (1) portion should be wrapped in dim color.
    $this->assertStringContainsString("\033[2m(1)\033[0m", is_string($result) ? $result : '');
  }

}
