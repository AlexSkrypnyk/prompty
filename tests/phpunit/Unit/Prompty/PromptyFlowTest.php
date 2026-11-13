<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for running a flow built with Prompty::flow().
 *
 * The whole-flow tests resolve answers from the environment.
 *
 * Rendered output is captured, ANSI-stripped, and compared whole against a
 * heredoc, or against an escaped string where trailing whitespace is
 * significant. The cancellation tests that assert output use substrings,
 * because their output carries redraw sequences.
 */
#[CoversClass(Prompty::class)]
#[CoversClass(PromptyFlow::class)]
#[Group('unit')]
final class PromptyFlowTest extends PromptyTestCase {

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
      fn(): mixed => Prompty::flow()
        ->text('name', 'Project name', placeholder: 'my-app')
        ->select('framework', 'Framework', options: ['react' => 'React', 'vue' => 'Vue'])
        ->confirm('install', 'Install?')
        ->run(unicode: FALSE),
      ['name' => 'my-app', 'framework' => 'vue', 'install' => TRUE],
    ];

    yield 'nested children with conditions' => [
      ['type' => 'app', 'app_framework' => 'next', 'api_routes' => 'yes'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()
        ->select('type', 'Type', options: ['app' => 'App', 'lib' => 'Library'])
          ->children()
            ->select('app_framework', 'App framework', options: ['next' => 'Next.js', 'nuxt' => 'Nuxt'])
              ->when(fn($r): bool => ($r['type'] ?? '') === 'app')
            ->select('lib_format', 'Format', options: ['esm' => 'ESM', 'cjs' => 'CJS'])
              ->when(fn($r): bool => ($r['type'] ?? '') === 'lib')
          ->end()
        ->confirm('api_routes', 'API routes?')
        ->run(unicode: FALSE),
      ['type' => 'app', 'app_framework' => 'next', 'lib_format' => NULL, 'api_routes' => TRUE],
    ];

    yield 'custom env prefix' => [
      ['name' => 'configured'],
      'MYAPP_',
      fn(): mixed => Prompty::flow()->text('name', 'Name')->run(unicode: FALSE, env_prefix: 'MYAPP_'),
      ['name' => 'configured'],
    ];

    yield 'multiselect from csv' => [
      ['features' => 'ts,eslint'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()
        ->multiselect('features', 'Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'])
        ->run(unicode: FALSE),
      ['features' => ['ts', 'eslint']],
    ];

    yield 'multiselect normalizes order and duplicates' => [
      ['features' => 'prettier, ts ,prettier'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()
        ->multiselect('features', 'Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'])
        ->run(unicode: FALSE),
      ['features' => ['ts', 'prettier']],
    ];

    yield 'empty steps' => [[], 'PROMPTY_', fn(): mixed => Prompty::flow()->run(unicode: FALSE), []];

    yield 'truthy override' => [
      ['install' => 'yep'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()->confirm('install', 'Install?')->run(unicode: FALSE, truthy: ['yep']),
      ['install' => TRUE],
    ];

    yield 'falsy override' => [
      ['install' => 'nah'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()->confirm('install', 'Install?')->run(unicode: FALSE, falsy: ['nah']),
      ['install' => FALSE],
    ];

    yield 'no config overrides' => [
      ['name' => 'test'],
      'PROMPTY_',
      fn(): mixed => Prompty::flow()->text('name', 'Name')->run(unicode: FALSE),
      ['name' => 'test'],
    ];
  }

  public function testFlowLinearRenderedOutput(): void {
    $this->setEnvVars(['name' => 'my-app', 'framework' => 'react']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow()
        ->text('name', 'Project name')
        ->select('framework', 'Framework', options: ['react' => 'React', 'vue' => 'Vue'])
        ->run(unicode: FALSE);
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
      Prompty::flow()->text('name', 'Name')->run(intro: 'Welcome', outro: 'Done!', unicode: FALSE);
    });

    $expected = <<<'EXPECTED'

    #  Welcome
    |
    +  Name
    |  test
    |
    #  Done!

    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  public function testFlowIntroOutroCallable(): void {
    $this->setEnvVars(['name' => 'test']);

    $was_intro_called = FALSE;
    $was_outro_called = FALSE;

    $output = $this->captureOutput(function () use (&$was_intro_called, &$was_outro_called): void {
      Prompty::flow()
        ->text('name', 'Name')
        ->run(
          intro: function () use (&$was_intro_called): void {
            $was_intro_called = TRUE;
            echo "Custom intro\n";
          },
          outro: function (array $results) use (&$was_outro_called): void {
            $was_outro_called = TRUE;
            echo 'Custom outro: ' . $results['name'] . "\n";
          },
          unicode: FALSE,
        );
    });

    $this->assertTrue($was_intro_called);
    $this->assertTrue($was_outro_called);
    $expected = <<<'EXPECTED'
    Custom intro
    +  Name
    |  test
    |
    Custom outro: test
    EXPECTED;

    $this->assertSame($expected . "\n", $output);
  }

  #[DataProvider('dataProviderFlowOutroDrawsOneBar')]
  public function testFlowOutroDrawsOneBar(\Closure $run, string $expected): void {
    $output = $this->captureOutput($run);

    $this->assertSame($expected, $output);
  }

  public static function dataProviderFlowOutroDrawsOneBar(): \Iterator {
    yield 'after a question' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->confirm('send', 'Send order?', discovered: 'yes')
        ->run(intro: 'Kitchen order', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n+  Dish name\n|  pear tart\n|\n+  Send order?\n|  Yes\n|\n#  Order sent!\n\n",
    ];

    yield 'after a note' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->note("Next steps:\n  plate the tart")
        ->run(intro: 'Kitchen order', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n+  Dish name\n|  pear tart\n|\n*  Next steps:\n|    plate the tart\n|\n#  Order sent!\n\n",
    ];

    yield 'after a task' => [
      static fn(): mixed => Prompty::flow()
        ->task('fire', 'Firing the order', static fn(): bool => TRUE)
        ->run(intro: 'Kitchen order', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\no  Firing the order\n|  + Done\n|\n#  Order sent!\n\n",
    ];

    yield 'after a nested child' => [
      static fn(): mixed => Prompty::flow()
        ->confirm('extras', 'Extras', discovered: 'yes')
          ->children()
            ->text('note', 'Note', discovered: 'no nuts')
          ->end()
        ->run(intro: 'Kitchen order', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n+  Extras\n|  Yes\n|\n|  |\n|  +  Note\n|     no nuts\n|     \n#  Order sent!\n\n",
    ];

    yield 'after the intro, every step skipped' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
          ->when(static fn(): bool => FALSE)
        ->run(intro: 'Kitchen order', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n#  Order sent!\n\n",
    ];

    yield 'nothing drawn' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
          ->when(static fn(): bool => FALSE)
        ->run(outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "|\n#  Order sent!\n\n",
    ];

    yield 'outro callable printing an outro' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(outro: static function (): void {
          Prompty::outro('Order sent!');
        }, unicode: FALSE, interactive: FALSE),
      "+  Dish name\n|  pear tart\n|\n#  Order sent!\n\n",
    ];

    yield 'outro callable returning a message' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(intro: 'Kitchen order', outro: static fn(array $r): string => $r['dish'] . ' is ready.', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n+  Dish name\n|  pear tart\n|\n#  pear tart is ready.\n\n",
    ];

    yield 'outro callable returning a message, nothing drawn' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
          ->when(static fn(): bool => FALSE)
        ->run(outro: static fn(): string => 'Order sent!', unicode: FALSE, interactive: FALSE),
      "|\n#  Order sent!\n\n",
    ];

    yield 'intro callable returning a message' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(intro: static fn(array $r): string => 'Kitchen order (' . count($r) . ' answers)', outro: 'Order sent!', unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order (0 answers)\n|\n+  Dish name\n|  pear tart\n|\n#  Order sent!\n\n",
    ];

    yield 'intro set on the builder returning a message' => [
      static fn(): mixed => Prompty::flow()
        ->intro(static fn(): string => 'Kitchen order')
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(unicode: FALSE, interactive: FALSE),
      "\n#  Kitchen order\n|\n+  Dish name\n|  pear tart\n|\n",
    ];

    yield 'outro message naming a function' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(intro: 'ucfirst', outro: 'strtoupper', unicode: FALSE, interactive: FALSE),
      "\n#  ucfirst\n|\n+  Dish name\n|  pear tart\n|\n#  strtoupper\n\n",
    ];

    yield 'outro callable printing and returning a message' => [
      static fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name', discovered: 'pear tart')
        ->run(outro: static function (): string {
          Prompty::note('Plate warm.');

          return 'Order sent!';
        }, unicode: FALSE, interactive: FALSE),
      "+  Dish name\n|  pear tart\n|\n*  Plate warm.\n|\n#  Order sent!\n\n",
    ];

    yield 'standalone outro after a flow' => [
      static function (): void {
        Prompty::configure(unicode: FALSE, interactive: FALSE);
        Prompty::flow()->text('dish', 'Dish name', discovered: 'pear tart')->run();
        Prompty::outro('Order sent!');
      },
      "+  Dish name\n|  pear tart\n|\n|\n#  Order sent!\n\n",
    ];
  }

  public function testFlowCancelledDrawsOneBar(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(cancelled: 'Order cancelled.', unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString("(cancelled)\n|\n#  Order cancelled.\n", $r['output']);
  }

  public function testFlowCancelledCallableReturningMessage(): void {
    $r = $this->promptyRun(fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(cancelled: fn(array $r): string => 'Order cancelled after ' . count($r) . ' answers.', unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertStringContainsString("(cancelled)\n|\n#  Order cancelled after 0 answers.\n", $r['output']);
  }

  #[DataProvider('dataProviderFlowMessageCallableReturnsNonString')]
  public function testFlowMessageCallableReturnsNonString(\Closure $run, string $keystrokes, string $message): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage($message);

    $this->promptyRun($run, $keystrokes);
  }

  public static function dataProviderFlowMessageCallableReturnsNonString(): \Iterator {
    yield 'intro' => [
      static fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(intro: static fn(): int => 1, unicode: FALSE),
      'pear tart' . self::KEY_ENTER,
      'The intro callable of a flow returned int. It must return the text to draw, or NULL when it draws its own.',
    ];

    yield 'outro' => [
      static fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(outro: static fn(): bool => TRUE, unicode: FALSE),
      'pear tart' . self::KEY_ENTER,
      'The outro callable of a flow returned bool. It must return the text to draw, or NULL when it draws its own.',
    ];

    yield 'cancelled' => [
      static fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(cancelled: static fn(): array => [], unicode: FALSE),
      self::KEY_ESCAPE,
      'The cancelled callable of a flow returned array. It must return the text to draw, or NULL when it draws its own.',
    ];
  }

  public function testResultsReturnsLastFlowAnswers(): void {
    $this->setEnvVars(['name' => 'test', 'send' => 'yes']);

    $this->captureOutput(function (): void {
      Prompty::flow()
        ->text('name', 'Name')
        ->confirm('send', 'Send?')
        ->run(unicode: FALSE);
    });

    $this->assertSame(['name' => 'test', 'send' => TRUE], Prompty::results());
  }

  public function testFlowNumbering(): void {
    $this->setEnvVars(['name' => 'test', 'framework' => 'react']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow()
        ->text('name', 'Name')
        ->select('framework', 'Framework', options: ['react' => 'React'])
        ->run(numbering: TRUE, unicode: FALSE);
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
      Prompty::flow()
        ->select('type', 'Type', options: ['app' => 'App'])
          ->children()
            ->text('child', 'Child')
          ->end()
        ->run(numbering: TRUE, unicode: FALSE);
    });

    // Double-quoted because the final line carries significant trailing
    // whitespace, which .editorconfig would strip from a nowdoc.
    $expected = "+  Type (1)\n|  App\n|\n|  |\n|  +  Child (1.1)\n|     val\n|     \n";

    $this->assertSame($expected, $output);
  }

  public function testFlowConfigLabels(): void {
    $this->setEnvVars(['install' => 'yes']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow()->confirm('install', 'Install?')->run(labels: ['yes' => 'Yep', 'no' => 'Nope'], unicode: FALSE);
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
      Prompty::flow()->text('name', 'Name')->run(symbols_ascii: ['completed' => '*'], unicode: FALSE);
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

    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  public static function dataProviderFlowRejectsOutOfDomainEnv(): \Iterator {
    yield 'select' => [
      ['framework' => 'angular'],
      fn(): mixed => Prompty::flow()->select('framework', 'Framework', options: ['react' => 'React', 'vue' => 'Vue'])->run(unicode: FALSE),
      'Discovered value "angular" for "Framework" is not a valid option. Available options: react, vue.',
    ];

    yield 'multiselect' => [
      ['features' => 'ts,webpack'],
      fn(): mixed => Prompty::flow()->multiselect('features', 'Features', options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'])->run(unicode: FALSE),
      'Discovered value "webpack" for "Features" is not a valid option. Available options: ts, eslint.',
    ];

    yield 'confirm' => [
      ['install' => 'maybe'],
      fn(): mixed => Prompty::flow()->confirm('install', 'Install?')->run(unicode: FALSE),
      'Discovered value "maybe" for "Install?" is not a valid answer. Accepted values: 1, true, yes, 0, false, no.',
    ];

    yield 'nested child step' => [
      ['type' => 'app', 'app_framework' => 'svelte'],
      fn(): mixed => Prompty::flow()
        ->select('type', 'Type', options: ['app' => 'App'])
          ->children()
            ->select('app_framework', 'App framework', options: ['next' => 'Next.js', 'nuxt' => 'Nuxt'])
          ->end()
        ->run(unicode: FALSE),
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
      fn(): mixed => Prompty::flow()->text('name', 'Name')->run(cancelled: 'Cancelled!', unicode: FALSE),
      'Cancelled!',
    ];

    yield 'null default' => [fn(): mixed => Prompty::flow()->text('name', 'Name')->run(unicode: FALSE), NULL];
  }

  public function testFlowCancelledWithCallable(): void {
    $was_called = FALSE;

    $r = $this->promptyRun(function () use (&$was_called): mixed {
      return Prompty::flow()
        ->text('name', 'Name')
        ->run(cancelled: function () use (&$was_called): void {
          $was_called = TRUE;
          echo "Custom cancel\n";
        }, unicode: FALSE);
    }, self::KEY_ESCAPE);

    $this->assertNull($r['result']);
    $this->assertTrue($was_called);
    $this->assertStringContainsString('Custom cancel', $r['output']);
  }

  #[DataProvider('dataProviderFlowResetsStateWhenCallbackThrows')]
  public function testFlowResetsStateWhenCallbackThrows(\Closure $run): void {
    $this->setEnvVars(['dish' => 'pear tart']);

    $this->captureOutputThrows(\RuntimeException::class, 'boom', $run);

    $this->assertFalse($this->getStaticProperty('isInFlow'));
  }

  public static function dataProviderFlowResetsStateWhenCallbackThrows(): \Iterator {
    yield 'argument closure' => [
      fn(): mixed => Prompty::flow()->text('dish', fn(array $r): never => throw new \RuntimeException('boom'))->run(unicode: FALSE),
    ];

    yield 'intro callback' => [
      fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(intro: fn(): never => throw new \RuntimeException('boom'), unicode: FALSE),
    ];

    yield 'widget condition' => [
      fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name')
          ->when(fn(): never => throw new \RuntimeException('boom'))
        ->run(unicode: FALSE),
    ];

    yield 'outro callback' => [
      fn(): mixed => Prompty::flow()->text('dish', 'Dish name')->run(outro: fn(): never => throw new \RuntimeException('boom'), unicode: FALSE),
    ];

    // Throws from the recursive walkFlow() call, 1 level below the parent.
    yield 'nested child argument closure' => [
      fn(): mixed => Prompty::flow()
        ->text('dish', 'Dish name')
          ->children()
            ->text('method', fn(array $r): never => throw new \RuntimeException('boom'))
          ->end()
        ->run(unicode: FALSE),
    ];
  }

  public function testFlowThrowLeavesWidgetsInStandaloneMode(): void {
    $stream = $this->installKeystrokes('pear tart' . self::KEY_ENTER, FALSE);

    $value = NULL;

    $this->captureOutput(function () use (&$value): void {
      try {
        Prompty::flow()->text('dish', fn(array $r): never => throw new \RuntimeException('boom'))->run();
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
