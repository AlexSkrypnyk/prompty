<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty::flow() and the flow-mode widget contract.
 *
 * Inside a flow a widget returns a deferred closure, or a step array when it
 * carries a condition or children. The whole-flow tests resolve answers from
 * the environment. Rendered output is captured, ANSI-stripped, and compared
 * whole against a heredoc, or against an escaped string where trailing
 * whitespace is significant. The cancellation tests that assert output use
 * substrings, because their output carries redraw sequences that depend on
 * keystroke timing.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowTest extends PromptyTestCase {

  #[DataProvider('dataProviderWidgetFlowModeReturnsClosure')]
  public function testWidgetFlowModeReturnsClosure(\Closure $call): void {
    $this->createAndSetInstance([], TRUE);

    $this->assertInstanceOf(\Closure::class, $call());
  }

  public static function dataProviderWidgetFlowModeReturnsClosure(): \Iterator {
    yield 'text' => [fn(): mixed => Prompty::text('Project name', placeholder: 'my-app')];
    yield 'select' => [fn(): mixed => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'])];
    yield 'multiselect' => [fn(): mixed => Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'])];
    yield 'confirm' => [fn(): mixed => Prompty::confirm('Install dependencies?')];
  }

  #[DataProvider('dataProviderWidgetFlowModeReturnsStepArray')]
  public function testWidgetFlowModeReturnsStepArray(\Closure $call, ?\Closure $condition, int $expected_children): void {
    $this->createAndSetInstance([], TRUE);

    $result = $call();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('__call', $result);
    $this->assertArrayHasKey('__children', $result);
    $this->assertArrayHasKey('__condition', $result);
    $this->assertInstanceOf(\Closure::class, $result['__call']);

    if (!$condition instanceof \Closure) {
      $this->assertNull($result['__condition']);
    }
    else {
      $this->assertSame($condition, $result['__condition']);
    }

    $this->assertIsArray($result['__children']);
    $this->assertCount($expected_children, $result['__children']);
  }

  public static function dataProviderWidgetFlowModeReturnsStepArray(): \Iterator {
    $condition_app = fn($r): bool => ($r['type'] ?? '') === 'app';
    $condition_lib = fn($r): bool => ($r['type'] ?? '') === 'lib';

    yield 'text with children' => [fn(): mixed => Prompty::text('Project name', children: ['child_key' => Prompty::text('Child question')]), NULL, 1];

    yield 'text with condition' => [fn(): mixed => Prompty::text('App name', condition: $condition_app), $condition_app, 0];

    yield 'select with condition' => [fn(): mixed => Prompty::select('Framework', options: ['react' => 'React'], condition: $condition_app), $condition_app, 0];

    yield 'select with children' => [
      fn(): mixed => Prompty::select('Framework', options: ['react' => 'React'], children: ['ssr' => Prompty::confirm('Use SSR?')]),
      NULL,
      1,
    ];

    yield 'multiselect with condition and children' => [
      fn(): mixed => Prompty::multiselect('Formats',
        options: ['esm' => 'ESM', 'cjs' => 'CJS'],
        condition: $condition_lib,
        children: ['bundler' => Prompty::select('Bundler', options: ['tsup' => 'tsup'])],
      ),
      $condition_lib,
      1,
    ];

    yield 'confirm with children' => [
      fn(): mixed => Prompty::confirm('Enable testing?', children: ['runner' => Prompty::select('Runner', options: ['jest' => 'Jest'])]),
      NULL,
      1,
    ];
  }

  public function testFlowModeClosureAcceptsCtx(): void {
    $this->createAndSetInstance([], TRUE);

    $result = Prompty::text('Name', placeholder: 'default', discovered: 'pre-filled');

    $this->assertInstanceOf(\Closure::class, $result);

    $ctx = $this->defaultCtx();

    $value = NULL;
    $this->captureOutput(function () use ($result, $ctx, &$value): void {
      $value = $result($ctx);
    });

    $this->assertSame('pre-filled', $value);
  }

  #[DataProvider('dataProviderFlowResolvesFromEnv')]
  public function testFlowResolvesFromEnv(array $env, string $prefix, \Closure $run, array $expected): void {
    $this->setEnvVars($env, $prefix);

    $result = NULL;
    $this->captureOutput(function () use (&$result, $run): void {
      $result = $run();
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderFlowResolvesFromEnv(): \Iterator {
    yield 'linear steps' => [
      ['name' => 'my-app', 'framework' => 'vue', 'install' => 'yes'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => [
        'name' => Prompty::text('Project name', placeholder: 'my-app'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
        'install' => Prompty::confirm('Install?'),
      ], unicode: FALSE),
      ['name' => 'my-app', 'framework' => 'vue', 'install' => TRUE],
    ];

    yield 'nested children with conditions' => [
      ['type' => 'app', 'app_framework' => 'next', 'api_routes' => 'yes'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => [
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
      ], unicode: FALSE),
      ['type' => 'app', 'app_framework' => 'next', 'api_routes' => TRUE],
    ];

    yield 'custom env prefix' => [
      ['name' => 'configured'],
      'MYAPP_',
      fn(): mixed => Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE, env_prefix: 'MYAPP_'),
      ['name' => 'configured'],
    ];

    yield 'multiselect from csv' => [
      ['features' => 'ts,eslint'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => [
        'features' => Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier']),
      ], unicode: FALSE),
      ['features' => ['ts', 'eslint']],
    ];

    yield 'multiselect normalizes order and duplicates' => [
      ['features' => 'prettier, ts ,prettier'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => [
        'features' => Prompty::multiselect('Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier']),
      ], unicode: FALSE),
      ['features' => ['ts', 'prettier']],
    ];

    yield 'empty steps' => [[], 'PROMPTY_', fn(): mixed => Prompty::flow(fn(): array => [], unicode: FALSE), []];

    yield 'truthy override' => [
      ['install' => 'yep'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => ['install' => Prompty::confirm('Install?')], unicode: FALSE, truthy: ['yep']),
      ['install' => TRUE],
    ];

    yield 'falsy override' => [
      ['install' => 'nah'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => ['install' => Prompty::confirm('Install?')], unicode: FALSE, falsy: ['nah']),
      ['install' => FALSE],
    ];

    yield 'no config overrides' => [
      ['name' => 'test'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE),
      ['name' => 'test'],
    ];
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

  #[DataProvider('dataProviderFlowRejectsOutOfDomainEnv')]
  public function testFlowRejectsOutOfDomainEnv(array $env, \Closure $run, string $message): void {
    $this->setEnvVars($env);

    $this->captureOutputThrows(\InvalidArgumentException::class, $message, $run);

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

  #[DataProvider('dataProviderFlowCancelled')]
  public function testFlowCancelled(\Closure $run, ?string $expected_output): void {
    $r = $this->promptyRun($run, self::KEY_ESCAPE);

    $this->assertNull($r['result']);

    if ($expected_output !== NULL) {
      $this->assertStringContainsString($expected_output, $r['output']);
    }
  }

  public static function dataProviderFlowCancelled(): \Iterator {
    yield 'string message' => [
      fn(): mixed => Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], cancelled: 'Cancelled!', unicode: FALSE),
      'Cancelled!',
    ];

    yield 'null default' => [fn(): mixed => Prompty::flow(fn(): array => ['name' => Prompty::text('Name')], unicode: FALSE), NULL];
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

  #[DataProvider('dataProviderFlowResetsStateWhenCallbackThrows')]
  public function testFlowResetsStateWhenCallbackThrows(\Closure $run): void {
    $this->setEnvVars(['dish' => 'pear tart']);

    $this->captureOutputThrows(\RuntimeException::class, 'boom', $run);

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
        // The exception is swallowed, as a caller that catches it and
        // continues would.
      }

      $value = Prompty::text('Dish name');
    });

    fclose($stream);

    $this->assertIsString($value);
    $this->assertSame('pear tart', $value);
  }

}
