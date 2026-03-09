<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests widget execution with pre-resolved values (no TTY interaction).
 *
 * Widgets are called with `discovered` or `env_value` in ctx so they skip
 * the interactive loop and return immediately.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetResolvedTest extends PromptyTestCase {

  /**
   * Build a default context array for widget execution.
   *
   * @param array<string, mixed> $overrides
   *   Context overrides.
   *
   * @return array<string, mixed>
   *   Context array.
   */
  protected function ctx(array $overrides = []): array {
    $this->createInstance();

    return array_merge([
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
      'number' => NULL,
      'env_value' => NULL,
      'truthy' => ['1', 'true', 'yes'],
      'falsy' => ['0', 'false', 'no'],
    ], $overrides);
  }

  #[DataProvider('dataProviderTextResolved')]
  public function testTextResolved(string $discovered, string $expected): void {
    $this->captureOutput(function () use ($discovered, &$result): void {
      $result = Prompty::text('Project name', discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderTextResolved(): \Iterator {
    yield 'simple value' => ['my-app', 'my-app'];
    yield 'value with spaces' => ['my cool app', 'my cool app'];
    yield 'empty string' => ['', ''];
  }

  public function testTextEnvResolved(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Project name', ctx: $this->ctx(['env_value' => 'env-app']));
    });

    $this->assertSame('env-app', $result);
  }

  public function testTextDiscoveredTakesPrecedenceOverEnv(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Project name', discovered: 'direct', ctx: $this->ctx(['env_value' => 'from-env']));
    });

    $this->assertSame('direct', $result);
  }

  #[DataProvider('dataProviderSelectResolved')]
  public function testSelectResolved(string $discovered, array $options, string $expected_return): void {
    $this->captureOutput(function () use ($discovered, $options, &$result): void {
      $result = Prompty::select('Framework', options: $options, discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected_return, $result);
  }

  public static function dataProviderSelectResolved(): \Iterator {
    $options = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];

    yield 'first option' => ['react', $options, 'react'];
    yield 'middle option' => ['vue', $options, 'vue'];
    yield 'last option' => ['svelte', $options, 'svelte'];
  }

  public function testSelectEnvResolved(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        ctx: $this->ctx(['env_value' => 'vue']),
      );
    });

    $this->assertSame('vue', $result);
  }

  public function testSelectRendersCompletedLabel(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        discovered: 'vue',
        ctx: $this->ctx(),
      );
    });

    $this->assertStringContainsString('Framework', $output);
    $this->assertStringContainsString('Vue', $output);
  }

  #[DataProvider('dataProviderMultiselectResolved')]
  public function testMultiselectResolved(string $env_value, array $expected): void {
    $this->captureOutput(function () use ($env_value, &$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
        ctx: $this->ctx(['env_value' => $env_value]),
      );
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderMultiselectResolved(): \Iterator {
    yield 'single value' => ['ts', ['ts']];
    yield 'multiple values' => ['ts,eslint', ['ts', 'eslint']];
    yield 'all values' => ['ts,eslint,prettier', ['ts', 'eslint', 'prettier']];
    yield 'with spaces' => ['ts, eslint, prettier', ['ts', 'eslint', 'prettier']];
  }

  public function testMultiselectEmpty(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript'],
        discovered: [],
        ctx: $this->ctx(),
      );
    });

    $this->assertSame([], $result);
  }

  public function testMultiselectRendersNoneForEmpty(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript'],
        discovered: [],
        ctx: $this->ctx(),
      );
    });

    $this->assertStringContainsString('None', $output);
  }

  #[DataProvider('dataProviderConfirmResolved')]
  public function testConfirmResolved(string $env_value, bool $expected): void {
    $this->captureOutput(function () use ($env_value, &$result): void {
      $result = Prompty::confirm('Install?', ctx: $this->ctx(['env_value' => $env_value]));
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderConfirmResolved(): \Iterator {
    yield 'truthy 1' => ['1', TRUE];
    yield 'truthy true' => ['true', TRUE];
    yield 'truthy yes' => ['yes', TRUE];
    yield 'truthy YES (case)' => ['YES', TRUE];
    yield 'falsy 0' => ['0', FALSE];
    yield 'falsy false' => ['false', FALSE];
    yield 'falsy no' => ['no', FALSE];
    yield 'falsy NO (case)' => ['NO', FALSE];
  }

  public function testConfirmDiscoveredBool(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('Install?', discovered: TRUE, ctx: $this->ctx());
    });

    $this->assertTrue($result);
  }

  public function testConfirmRendersYesNo(): void {
    $output_yes = $this->captureOutput(function (): void {
      Prompty::confirm('Install?', discovered: TRUE, ctx: $this->ctx());
    });

    $output_no = $this->captureOutput(function (): void {
      Prompty::confirm('Install?', discovered: FALSE, ctx: $this->ctx());
    });

    $this->assertStringContainsString('Yes', $output_yes);
    $this->assertStringContainsString('No', $output_no);
  }

  protected function setUp(): void {
    parent::setUp();
    // Ensure the singleton uses ASCII mode for all tests.
    $this->setStaticProperty('instance', $this->createInstance());
  }

  public function testTextResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Child name', discovered: 'child-val', ctx: $this->ctx([
        'depth' => 1,
        'is_last' => FALSE,
        'open' => [1 => TRUE],
      ]));
    });

    $this->assertSame('child-val', $result);
    $this->assertStringContainsString('+  Child name', $output);
    $this->assertStringContainsString('child-val', $output);
  }

  public function testSelectResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        discovered: 'react',
        ctx: $this->ctx([
          'depth' => 1,
          'is_last' => TRUE,
          'open' => [],
        ]),
      );
    });

    $this->assertSame('react', $result);
    $this->assertStringContainsString('React', $output);
  }

  public function testMultiselectResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        ctx: $this->ctx([
          'depth' => 2,
          'is_last' => FALSE,
          'open' => [1 => TRUE, 2 => TRUE],
          'env_value' => 'ts,eslint',
        ]),
      );
    });

    $this->assertSame(['ts', 'eslint'], $result);
    $this->assertStringContainsString('TypeScript, ESLint', $output);
  }

  public function testConfirmResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('Enable?',
        discovered: TRUE,
        ctx: $this->ctx([
          'depth' => 1,
          'is_last' => TRUE,
          'open' => [],
        ]),
      );
    });

    $this->assertTrue($result);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testTextStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->setStaticProperty('instance', $this->createInstance());

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Name', discovered: 'standalone-val');
    });

    $this->assertSame('standalone-val', $result);
    $this->assertStringContainsString('standalone-val', $output);
  }

  public function testSelectStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->setStaticProperty('instance', $this->createInstance());

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick',
        options: ['a' => 'Alpha', 'b' => 'Beta'],
        discovered: 'b',
      );
    });

    $this->assertSame('b', $result);
    $this->assertStringContainsString('Beta', $output);
  }

  public function testMultiselectStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->setStaticProperty('instance', $this->createInstance());

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Pick',
        options: ['a' => 'Alpha', 'b' => 'Beta'],
        discovered: ['a', 'b'],
      );
    });

    $this->assertSame(['a', 'b'], $result);
    $this->assertStringContainsString('Alpha, Beta', $output);
  }

  public function testConfirmStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->setStaticProperty('instance', $this->createInstance());

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('OK?', discovered: FALSE);
    });

    $this->assertFalse($result);
    $this->assertStringContainsString('No', $output);
  }

  public function testSelectWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Framework',
        options: ['react' => 'React'],
        description: 'Pick your framework.',
        discovered: 'react',
        ctx: $this->ctx(),
      );
    });

    $this->assertSame('react', $result);
    $this->assertStringContainsString('React', $output);
  }

  public function testMultiselectWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript'],
        description: "Select features.\nSpace to toggle.",
        discovered: ['ts'],
        ctx: $this->ctx(),
      );
    });

    $this->assertSame(['ts'], $result);
    $this->assertStringContainsString('TypeScript', $output);
  }

  public function testConfirmWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('Install?',
        description: 'Runs npm install.',
        discovered: TRUE,
        ctx: $this->ctx(),
      );
    });

    $this->assertTrue($result);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testTextWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Project name',
        description: 'Used as the directory name.',
        discovered: 'my-app',
        ctx: $this->ctx(),
      );
    });

    $this->assertSame('my-app', $result);
    $this->assertStringContainsString('my-app', $output);
  }

  public function testSelectUnknownKeyFallback(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick',
        options: ['a' => 'Alpha'],
        discovered: 'unknown',
        ctx: $this->ctx(),
      );
    });

    $this->assertSame('unknown', $result);
    $this->assertStringContainsString('unknown', $output);
  }

  public function testWidgetsWithNumbering(): void {
    $ctx_with_number = $this->ctx(['number' => '2.1']);

    $output = $this->captureOutput(function () use ($ctx_with_number): void {
      Prompty::text('Name', discovered: 'val', ctx: $ctx_with_number);
    });

    $this->assertStringContainsString('(2.1)', $output);
  }

}
