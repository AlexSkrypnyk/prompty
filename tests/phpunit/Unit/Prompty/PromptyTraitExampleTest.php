<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the usage example in PromptyTestTrait's class docblock.
 *
 * The example ships to consumers as the first thing they copy, so it is
 * mirrored here to keep it executable.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyTraitExampleTest extends PromptyTestCase {

  public function testDocumentedExample(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
    ]), self::KEY_DOWN . self::KEY_ENTER);

    $this->assertIsArray($r['result']);
    $this->assertSame('vue', $r['result']['framework']);
  }

}
