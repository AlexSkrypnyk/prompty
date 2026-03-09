<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that widgets return correct structures when in flow mode.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetFlowModeTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Put Prompty in flow mode so widgets return deferred closures.
    $this->setStaticProperty('inFlow', TRUE);
    // Ensure a singleton instance exists.
    $this->setStaticProperty('instance', $this->createInstance());
  }

  public function testTextFlowMode(): void {
    $result = Prompty::text('Project name', placeholder: 'my-app');

    $this->assertInstanceOf(\Closure::class, $result);
  }

  public function testTextFlowModeWithChildren(): void {
    $child = Prompty::text('Child question');

    $result = Prompty::text('Project name',
      children: ['child_key' => $child],
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('__call', $result);
    $this->assertArrayHasKey('__children', $result);
    $this->assertArrayHasKey('__condition', $result);
    $this->assertInstanceOf(\Closure::class, $result['__call']);
    $this->assertNull($result['__condition']);
    $this->assertIsArray($result['__children']);
    $this->assertCount(1, $result['__children']);
  }

  public function testTextFlowModeWithCondition(): void {
    $condition = fn($r): bool => ($r['type'] ?? '') === 'app';

    $result = Prompty::text('App name', condition: $condition);

    $this->assertIsArray($result);
    $this->assertSame($condition, $result['__condition']);
    $this->assertEmpty($result['__children']);
  }

  public function testSelectFlowMode(): void {
    $result = Prompty::select('Framework',
      options: ['react' => 'React', 'vue' => 'Vue'],
    );

    $this->assertInstanceOf(\Closure::class, $result);
  }

  public function testSelectFlowModeWithCondition(): void {
    $condition = fn($r): bool => ($r['type'] ?? '') === 'app';

    $result = Prompty::select('Framework',
      options: ['react' => 'React'],
      condition: $condition,
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('__call', $result);
    $this->assertSame($condition, $result['__condition']);
  }

  public function testSelectFlowModeWithChildren(): void {
    $child = Prompty::confirm('Use SSR?');

    $result = Prompty::select('Framework',
      options: ['react' => 'React'],
      children: ['ssr' => $child],
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('__call', $result);
    $this->assertIsArray($result['__children']);
    $this->assertCount(1, $result['__children']);
  }

  public function testMultiselectFlowMode(): void {
    $result = Prompty::multiselect('Features',
      options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
    );

    $this->assertInstanceOf(\Closure::class, $result);
  }

  public function testMultiselectFlowModeWithConditionAndChildren(): void {
    $condition = fn($r): bool => ($r['type'] ?? '') === 'lib';

    $result = Prompty::multiselect('Formats',
      options: ['esm' => 'ESM', 'cjs' => 'CJS'],
      condition: $condition,
      children: ['bundler' => Prompty::select('Bundler', options: ['tsup' => 'tsup'])],
    );

    $this->assertIsArray($result);
    $this->assertSame($condition, $result['__condition']);
    $this->assertIsArray($result['__children']);
    $this->assertCount(1, $result['__children']);
  }

  public function testConfirmFlowMode(): void {
    $result = Prompty::confirm('Install dependencies?');

    $this->assertInstanceOf(\Closure::class, $result);
  }

  public function testConfirmFlowModeWithChildren(): void {
    $result = Prompty::confirm('Enable testing?',
      children: ['runner' => Prompty::select('Runner', options: ['jest' => 'Jest'])],
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('__call', $result);
    $this->assertIsArray($result['__children']);
    $this->assertCount(1, $result['__children']);
  }

  public function testFlowModeClosureAcceptsCtx(): void {
    $result = Prompty::text('Name', placeholder: 'default', discovered: 'pre-filled');

    $this->assertInstanceOf(\Closure::class, $result);

    // When the flow walker calls the closure with ctx, it should execute
    // and return the discovered value.
    $ctx = [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
      'number' => NULL,
      'env_value' => NULL,
    ];

    ob_start();
    $value = $result($ctx);
    ob_end_clean();

    $this->assertSame('pre-filled', $value);
  }

}
