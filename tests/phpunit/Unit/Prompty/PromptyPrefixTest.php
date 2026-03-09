<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty labelPrefix() and bodyPrefix() methods.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyPrefixTest extends PromptyTestCase {

  #[DataProvider('dataProviderLabelPrefix')]
  public function testLabelPrefix(int $depth, array $open, string $expected_stripped): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'labelPrefix', $depth, $open);

    $this->assertIsString($result);
    $this->assertSame($expected_stripped, $this->stripAnsi($result));
  }

  public static function dataProviderLabelPrefix(): \Iterator {
    // labelPrefix loops from level=1 to level<depth.
    // At depth=1, the loop doesn't execute → just the initial '  '.
    yield 'depth 1, no open' => [1, [], '  '];
    yield 'depth 1, open at 1' => [1, [1 => TRUE], '  '];

    // At depth=2, loops level=1.
    yield 'depth 2, open at 1' => [2, [1 => TRUE], '  |  '];
    yield 'depth 2, nothing open' => [2, [], '     '];

    // At depth=3, loops level=1 and level=2.
    yield 'depth 3, open at 1 and 2' => [3, [1 => TRUE, 2 => TRUE], '  |  |  '];
    yield 'depth 3, open at 1 only' => [3, [1 => TRUE], '  |     '];
    yield 'depth 3, open at 2 only' => [3, [2 => TRUE], '     |  '];
    yield 'depth 3, nothing open' => [3, [], '        '];
  }

  #[DataProvider('dataProviderBodyPrefix')]
  public function testBodyPrefix(int $depth, array $open, string $expected_stripped): void {
    $p = $this->createInstance();

    $result = $this->callProtected($p, 'bodyPrefix', $depth, $open);

    $this->assertIsString($result);
    $this->assertSame($expected_stripped, $this->stripAnsi($result));
  }

  public static function dataProviderBodyPrefix(): \Iterator {
    // bodyPrefix loops from level=1 to level<=depth.
    // At depth=1, loops level=1.
    yield 'depth 1, open at 1' => [1, [1 => TRUE], '  |  '];
    yield 'depth 1, nothing open' => [1, [], '     '];

    // At depth=2, loops level=1 and level=2.
    yield 'depth 2, open at 1 and 2' => [2, [1 => TRUE, 2 => TRUE], '  |  |  '];
    yield 'depth 2, open at 1 only' => [2, [1 => TRUE], '  |     '];
    yield 'depth 2, nothing open' => [2, [], '        '];

    // At depth=3, loops level=1, 2, and 3.
    yield 'depth 3, all open' => [3, [1 => TRUE, 2 => TRUE, 3 => TRUE], '  |  |  |  '];
    yield 'depth 3, open at 1 and 3' => [3, [1 => TRUE, 3 => TRUE], '  |     |  '];
    yield 'depth 3, nothing open' => [3, [], '           '];
  }

}
