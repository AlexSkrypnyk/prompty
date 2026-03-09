<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the full Prompty::flow() method with env-resolved values.
 *
 * Output is captured and ANSI-stripped for heredoc assertions.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyFlowIntegrationTest extends PromptyTestCase {

  /**
   * Set environment variables for a flow.
   *
   * @param array<string, string> $vars
   *   Key-value pairs to set.
   * @param string $prefix
   *   Env prefix.
   */
  protected function setEnvVars(array $vars, string $prefix = 'PROMPTY_'): void {
    foreach ($vars as $key => $value) {
      putenv($prefix . strtoupper($key) . '=' . $value);
    }
  }

  /**
   * Clear environment variables after a flow.
   *
   * @param array<string> $keys
   *   Keys to clear.
   * @param string $prefix
   *   Env prefix.
   */
  protected function clearEnvVars(array $keys, string $prefix = 'PROMPTY_'): void {
    foreach ($keys as $key) {
      putenv($prefix . strtoupper($key));
    }
  }

  public function testFlowLinear(): void {
    $this->setEnvVars([
      'name' => 'my-app',
      'framework' => 'vue',
      'install' => 'yes',
    ]);

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

    $this->clearEnvVars(['name', 'framework', 'install']);
  }

  public function testFlowLinearRenderedOutput(): void {
    $this->setEnvVars([
      'name' => 'my-app',
      'framework' => 'react',
    ]);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Project name'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']),
      ], unicode: FALSE);
    });

    $this->assertStringContainsString('+  Project name', $output);
    $this->assertStringContainsString('my-app', $output);
    $this->assertStringContainsString('+  Framework', $output);
    $this->assertStringContainsString('React', $output);

    $this->clearEnvVars(['name', 'framework']);
  }

  public function testFlowNested(): void {
    $this->setEnvVars([
      'type' => 'app',
      'app_framework' => 'next',
      'api_routes' => 'yes',
    ]);

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

    $this->clearEnvVars(['type', 'app_framework', 'api_routes']);
  }

  public function testFlowIntroOutroStrings(): void {
    $this->setEnvVars(['name' => 'test']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ],
        intro: 'Welcome',
        outro: 'Done!',
        unicode: FALSE,
      );
    });

    $this->assertStringContainsString('#  Welcome', $output);
    $this->assertStringContainsString('#  Done!', $output);

    $this->clearEnvVars(['name']);
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
    $this->assertStringContainsString('Custom intro', $output);
    $this->assertStringContainsString('Custom outro: test', $output);

    $this->clearEnvVars(['name']);
  }

  public function testFlowConfig(): void {
    putenv('MYAPP_NAME=configured');

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ], unicode: FALSE, env_prefix: 'MYAPP_');
    });

    $this->assertNotNull($result);
    $this->assertSame('configured', $result['name']);

    putenv('MYAPP_NAME');
  }

  public function testFlowNumbering(): void {
    $this->setEnvVars(['name' => 'test', 'framework' => 'react']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
        'framework' => Prompty::select('Framework', options: ['react' => 'React']),
      ], numbering: TRUE, unicode: FALSE);
    });

    $this->assertStringContainsString('(1)', $output);
    $this->assertStringContainsString('(2)', $output);

    $this->clearEnvVars(['name', 'framework']);
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

    $this->assertStringContainsString('(1)', $output);
    $this->assertStringContainsString('(1.1)', $output);

    $this->clearEnvVars(['type', 'child']);
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

    $this->clearEnvVars(['features']);
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
      Prompty::flow(fn(): array => [
        'install' => Prompty::confirm('Install?'),
      ], labels: ['yes' => 'Yep', 'no' => 'Nope'], unicode: FALSE);
    });

    $this->assertStringContainsString('Yep', $output);

    $this->clearEnvVars(['install']);
  }

  public function testFlowConfigTruthy(): void {
    $this->setEnvVars(['install' => 'yep']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'install' => Prompty::confirm('Install?'),
      ], unicode: FALSE, truthy: ['yep']);
    });

    $this->assertNotNull($result);
    $this->assertTrue($result['install']);

    $this->clearEnvVars(['install']);
  }

  public function testFlowConfigFalsy(): void {
    $this->setEnvVars(['install' => 'nah']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'install' => Prompty::confirm('Install?'),
      ], unicode: FALSE, falsy: ['nah']);
    });

    $this->assertNotNull($result);
    $this->assertFalse($result['install']);

    $this->clearEnvVars(['install']);
  }

  public function testFlowConfigSymbolsAscii(): void {
    $this->setEnvVars(['name' => 'test']);

    $output = $this->captureOutput(function (): void {
      Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ], symbols_ascii: ['completed' => '*'], unicode: FALSE);
    });

    $this->assertStringContainsString('*', $output);

    $this->clearEnvVars(['name']);
  }

  public function testFlowConfigNoOverrides(): void {
    $this->setEnvVars(['name' => 'test']);

    $result = NULL;
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::flow(fn(): array => [
        'name' => Prompty::text('Name'),
      ], unicode: FALSE);
    });

    $this->assertNotNull($result);
    $this->assertSame('test', $result['name']);

    $this->clearEnvVars(['name']);
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
    $r = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'name' => Prompty::text('Name'),
    ], unicode: FALSE), self::KEY_ESCAPE);

    $this->assertNull($r['result']);
  }

}
