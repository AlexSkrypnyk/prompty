<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the full Prompty::flow() method with env-resolved values.
 *
 * Rendered output is captured, ANSI-stripped, and compared whole against a
 * heredoc, or against an escaped string where trailing whitespace is
 * significant. The cancellation tests that assert output use substrings,
 * because their output carries redraw sequences that depend on keystroke
 * timing.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowIntegrationTest extends PromptyTestCase {

  public function testFlowLinear(): void {
    $this->setEnvVars(['name' => 'my-app', 'framework' => 'vue', 'install' => 'yes']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'name' => Prompty::text('Project name', placeholder: 'my-app'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
        'install' => Prompty::confirm('Install?'),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame('my-app', $result['name']);
    $this->assertSame('vue', $result['framework']);
    $this->assertTrue($result['install']);
  }

  public function testFlowLinearRenderedOutput(): void {
    $this->setEnvVars(['name' => 'my-app', 'framework' => 'react']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Project name'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
      ], unicode: FALSE);
    });

    $expected = <<<'EXPECTED'
    +  Project name
    |  my-app
    |
    +  Framework
    |  React
    |
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowNested(): void {
    $this->setEnvVars(['type' => 'app', 'app_framework' => 'next', 'api_routes' => 'yes']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'type' => Prompty::select('Type',
          options: ['app' => 'App', 'lib' => 'Library'],
          children: [
            'app_framework' => Prompty::select('App framework',
              options: ['next' => 'Next.js', 'nuxt' => 'Nuxt'],
              condition: fn($r): bool => ($r['type'] ?? '') === 'app',
            ),
            'lib_format' => Prompty::select('Format',
              options: ['esm' => 'ESM', 'cjs' => 'CJS'],
              condition: fn($r): bool => ($r['type'] ?? '') === 'lib',
            ),
          ],
        ),
        'api_routes' => Prompty::confirm('API routes?'),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame('app', $result['type']);
    $this->assertSame('next', $result['app_framework']);
    $this->assertArrayNotHasKey('lib_format', $result);
    $this->assertTrue($result['api_routes']);
  }

  public function testFlowIntroOutroStrings(): void {
    $this->setEnvVars(['name' => 'test']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], intro: 'Welcome', outro: 'Done!', unicode: FALSE);
    });

    $expected = <<<'EXPECTED'

    #  Welcome
    |
    +  Name
    |  test
    |
    |
    #  Done!

    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowIntroOutroCallable(): void {
    $this->setEnvVars(['name' => 'test']);

    $intro_called = FALSE;
    $outro_called = FALSE;

    $output = $this->captureOutput(function () use (&$intro_called, &$outro_called): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ],
        intro: function () use (&$intro_called): void {
          $intro_called = TRUE;
          echo "Custom intro\n";
        },
        outro: function (array $results) use (&$outro_called): void {
          $outro_called = TRUE;
          echo 'Custom outro: ' . $results['name'] . "\n";
        },
        unicode: FALSE,
      );
    });

    $this->assertTrue($intro_called);
    $this->assertTrue($outro_called);
    $expected = <<<'EXPECTED'
    Custom intro
    +  Name
    |  test
    |
    Custom outro: test
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowConfig(): void {
    $this->setEnvVars(['name' => 'configured'], 'MYAPP_');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE, env_prefix: 'MYAPP_');
    });

    $this->assertNotNull($result);
    $this->assertSame('configured', $result['name']);
  }

  public function testFlowNumbering(): void {
    $this->setEnvVars(['name' => 'test', 'framework' => 'react']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React']),
      ], numbering: TRUE, unicode: FALSE);
    });

    $expected = <<<'EXPECTED'
    +  Name (1)
    |  test
    |
    +  Framework (2)
    |  React
    |
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowNumberingNested(): void {
    $this->setEnvVars(['type' => 'app', 'child' => 'val']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'type' => Prompty::select('Type',
          options: ['app' => 'App'],
          children: [
            'child' => Prompty::text('Child'),
          ],
        ),
      ], numbering: TRUE, unicode: FALSE);
    });

    // Double-quoted because the final line carries significant trailing
    // whitespace, which .editorconfig would strip from a nowdoc.
    $expected = "+  Type (1)\n|  App\n|\n|  |\n|  +  Child (1.1)\n|     val\n|     \n";

    $this->assertSame($expected, $output);
  }

  public function testFlowMultiselectWithEnv(): void {
    $this->setEnvVars(['features' => 'ts,eslint']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'features' => Prompty::multiselect('Features',
          options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
        ),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame(['ts', 'eslint'], $result['features']);
  }

  public function testFlowMultiselectEnvNormalisesOrderAndDuplicates(): void {
    $this->setEnvVars(['features' => 'prettier, ts ,prettier']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'features' => Prompty::multiselect('Features',
          options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
        ),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame(['ts', 'prettier'], $result['features']);
  }

  #[DataProvider('dataProviderFlowRejectsOutOfDomainEnv')]
  public function testFlowRejectsOutOfDomainEnv(array $env, \Closure $run, string $message): void {
    $this->setEnvVars($env);

    $caught = NULL;

    $this->captureOutput(function () use ($run, &$caught): void {
      try {
        $run();
      }
      catch (\InvalidArgumentException $exception) {
        $caught = $exception;
      }
    });

    $this->assertInstanceOf(\InvalidArgumentException::class, $caught);
    $this->assertSame($message, $caught->getMessage());
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public static function dataProviderFlowRejectsOutOfDomainEnv(): \Iterator {
    yield 'select' => [
      ['framework' => 'angular'],
      fn(): mixed => Prompty::flow(fn(): array => [
        'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
      ], unicode: FALSE),
      'Discovered value "angular" for "Framework" is not a valid option. Available options: react, vue.',
    ];

    yield 'multiselect' => [
      ['features' => 'ts,webpack'],
      fn(): mixed => Prompty::flow(fn(): array => [
        'features' => Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint']),
      ], unicode: FALSE),
      'Discovered value "webpack" for "Features" is not a valid option. Available options: ts, eslint.',
    ];

    yield 'confirm' => [
      ['install' => 'maybe'],
      fn(): mixed => Prompty::flow(fn(): array => [
        'install' => Prompty::confirm('Install?'),
      ], unicode: FALSE),
      'Discovered value "maybe" for "Install?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.',
    ];

    yield 'nested child step' => [
      ['type' => 'app', 'app_framework' => 'svelte'],
      fn(): mixed => Prompty::flow(fn(): array => [
        'type' => Prompty::select('Type', options: ['app' => 'App'], children: [
          'app_framework' => Prompty::select('App framework', options: ['next' => 'Next.js', 'nuxt' => 'Nuxt']),
        ]),
      ], unicode: FALSE),
      'Discovered value "svelte" for "App framework" is not a valid option. Available options: next, nuxt.',
    ];
  }

  public function testFlowEmptyReturnsEmptyArray(): void {
    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [], unicode: FALSE);
    });

    $this->assertSame([], $result);
  }

  public function testFlowConfigLabels(): void {
    $this->setEnvVars(['install' => 'yes']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => ['install' => Prompty::confirm('Install?')], labels: ['yes' => 'Yep', 'no' => 'Nope'], unicode: FALSE);
    });

    $expected = <<<'EXPECTED'
    +  Install?
    |  Yep
    |
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowConfigTruthy(): void {
    $this->setEnvVars(['install' => 'yep']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => ['install' => Prompty::confirm('Install?')], unicode: FALSE, truthy: ['yep']);
    });

    $this->assertNotNull($result);
    $this->assertTrue($result['install']);
  }

  public function testFlowConfigFalsy(): void {
    $this->setEnvVars(['install' => 'nah']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => ['install' => Prompty::confirm('Install?')], unicode: FALSE, falsy: ['nah']);
    });

    $this->assertNotNull($result);
    $this->assertFalse($result['install']);
  }

  public function testFlowConfigSymbolsAscii(): void {
    $this->setEnvVars(['name' => 'test']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], symbols_ascii: ['completed' => '*'], unicode: FALSE);
    });

    $expected = <<<'EXPECTED'
    *  Name
    |  test
    |
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowConfigNoOverrides(): void {
    $this->setEnvVars(['name' => 'test']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame('test', $result['name']);
  }

  public function testFlowCancelledWithString(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'name' => Prompty::text('Name'),
    ], cancelled: 'Cancelled!', unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString('Cancelled!', $r['output']);
  }

  public function testFlowCancelledWithCallable(): void {
    $called = FALSE;

    $r = $this->promptyRun(function () use (&$called): mixed {
      return Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ], cancelled: function () use (&$called): void {
        $called = TRUE;
        echo "Custom cancel\n";
      }, unicode: FALSE);
    }, self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertTrue($called);
    $this->assertStringContainsString('Custom cancel', $r['output']);
  }

  public function testFlowCancelledWithNull(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
  }

  #[DataProvider('dataProviderFlowResetsStateWhenCallbackThrows')]
  public function testFlowResetsStateWhenCallbackThrows(\Closure $run): void {
    $this->setEnvVars(['dish' => 'pear tart']);

    $caught = NULL;

    $this->captureOutput(function () use ($run, &$caught): void {
      try {
        $run();
      }
      catch (\RuntimeException $exception) {
        $caught = $exception;
      }
    });

    $this->assertInstanceOf(\RuntimeException::class, $caught);
    $this->assertSame('boom', $caught->getMessage());
    $this->assertFalse($this->getStaticProperty('inFlow'));
  }

  public static function dataProviderFlowResetsStateWhenCallbackThrows(): \Iterator {
    yield 'steps callable' => [
      fn(): mixed => Prompty::flow(fn(): array => throw new \RuntimeException('boom'), unicode: FALSE),
    ];

    yield 'intro callback' => [
      fn(): mixed => Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], intro: fn(): never => throw new \RuntimeException('boom'), unicode: FALSE),
    ];

    yield 'widget condition' => [
      fn(): mixed => Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name', condition: fn(): never => throw new \RuntimeException('boom'))], unicode: FALSE),
    ];

    yield 'outro callback' => [
      fn(): mixed => Prompty::flow(fn(): array => ['dish' => Prompty::text('Dish name')], outro: fn(): never => throw new \RuntimeException('boom'), unicode: FALSE),
    ];

    // Throws from the recursive walkFlow() call, one level below the parent.
    yield 'nested child callable' => [
      fn(): mixed => Prompty::flow(fn(): array => [
        'dish' => Prompty::text('Dish name', children: [
          'method' => fn(array $ctx): never => throw new \RuntimeException('boom'),
        ]),
      ], unicode: FALSE),
    ];
  }

  public function testFlowThrowLeavesWidgetsInStandaloneMode(): void {
    $stream = $this->installKeystrokes('pear tart' . self::KEY_ENTER, FALSE);

    $value = NULL;

    $this->captureOutput(function () use (&$value): void {
      try {
        Prompty::flow(fn(): array => throw new \RuntimeException('boom'));
      }
      catch (\RuntimeException) {
        // Swallowed, as a caller that catches and carries on would.
      }

      $value = Prompty::text('Dish name');
    });

    fclose($stream);

    $this->assertIsString($value);
    $this->assertSame('pear tart', $value);
  }

}
