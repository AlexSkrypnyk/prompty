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
 * Widgets are called with the `discovered` argument or the `discovered` ctx
 * key so they skip the interactive loop and return immediately.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyWidgetResolvedTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Ensure the singleton uses ASCII mode for all tests.
    $this->createAndSetInstance();
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
      $result = Prompty::text('Project name', ctx: $this->ctx(['discovered' => 'env-app']));
    });

    $this->assertSame('env-app', $result);
  }

  public function testTextDiscoveredTakesPrecedenceOverEnv(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Project name', discovered: 'direct', ctx: $this->ctx(['discovered' => 'from-env']));
    });

    $this->assertSame('direct', $result);
  }

  #[DataProvider('dataProviderSelectResolved')]
  public function testSelectResolved(string $discovered, array $options, string $expected): void {
    $this->captureOutput(function () use ($discovered, $options, &$result): void {
      $result = Prompty::select('Framework', options: $options, discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderSelectResolved(): \Iterator {
    $options = ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'];

    yield 'first option' => ['react', $options, 'react'];
    yield 'middle option' => ['vue', $options, 'vue'];
    yield 'last option' => ['svelte', $options, 'svelte'];
  }

  public function testSelectEnvResolved(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'], ctx: $this->ctx(['discovered' => 'vue']));
    });

    $this->assertSame('vue', $result);
  }

  public function testSelectRendersCompletedLabel(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue'], discovered: 'vue', ctx: $this->ctx());
    });

    $this->assertStringContainsString('Framework', $output);
    $this->assertStringContainsString('Vue', $output);
  }

  #[DataProvider('dataProviderMultiselectResolved')]
  public function testMultiselectResolved(string $ctx_discovered, array $expected): void {
    $this->captureOutput(function () use ($ctx_discovered, &$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
        ctx: $this->ctx(['discovered' => $ctx_discovered]),
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
      $result = Prompty::multiselect('Features', options: ['ts' => 'TypeScript'], discovered: [], ctx: $this->ctx());
    });

    $this->assertSame([], $result);
  }

  public function testMultiselectRendersNoneForEmpty(): void {
    $output = $this->captureOutput(function (): void {
      Prompty::multiselect('Features', options: ['ts' => 'TypeScript'], discovered: [], ctx: $this->ctx());
    });

    $this->assertStringContainsString('None', $output);
  }

  #[DataProvider('dataProviderConfirmResolved')]
  public function testConfirmResolved(string $ctx_discovered, bool $expected): void {
    $this->captureOutput(function () use ($ctx_discovered, &$result): void {
      $result = Prompty::confirm('Install?', ctx: $this->ctx(['discovered' => $ctx_discovered]));
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

  public function testTextResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Child name', discovered: 'child-val', ctx: $this->ctx(['depth' => 1, 'is_last' => FALSE, 'open' => [1 => TRUE]]));
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
        ctx: $this->ctx(['depth' => 1, 'is_last' => TRUE, 'open' => []]),
      );
    });

    $this->assertSame('react', $result);
    $this->assertStringContainsString('React', $output);
  }

  public function testMultiselectResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        ctx: $this->ctx(['depth' => 2, 'is_last' => FALSE, 'open' => [1 => TRUE, 2 => TRUE], 'discovered' => 'ts,eslint']),
      );
    });

    $this->assertSame(['ts', 'eslint'], $result);
    $this->assertStringContainsString('TypeScript, ESLint', $output);
  }

  public function testConfirmResolvedAtDepth(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('Enable?', discovered: TRUE, ctx: $this->ctx(['depth' => 1, 'is_last' => TRUE, 'open' => []]));
    });

    $this->assertTrue($result);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testTextStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance();

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Name', discovered: 'standalone-val');
    });

    $this->assertSame('standalone-val', $result);
    $this->assertStringContainsString('standalone-val', $output);
  }

  public function testSelectStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance();

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick', options: ['a' => 'Alpha', 'b' => 'Beta'], discovered: 'b');
    });

    $this->assertSame('b', $result);
    $this->assertStringContainsString('Beta', $output);
  }

  public function testMultiselectStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance();

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::multiselect('Pick', options: ['a' => 'Alpha', 'b' => 'Beta'], discovered: ['a', 'b']);
    });

    $this->assertSame(['a', 'b'], $result);
    $this->assertStringContainsString('Alpha, Beta', $output);
  }

  public function testConfirmStandaloneWithDiscovered(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance();

    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('OK?', discovered: FALSE);
    });

    $this->assertFalse($result);
    $this->assertStringContainsString('No', $output);
  }

  public function testConfirmStandaloneUsesConfiguredTruthyFalsy(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance(['truthy' => ['on'], 'falsy' => ['off']]);

    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::confirm('OK?', discovered: 'off');
    });

    $this->assertFalse($result);
  }

  public function testConfirmStandaloneRejectsValueOutsideConfiguredLists(): void {
    $this->setStaticProperty('inFlow', FALSE);
    $this->createAndSetInstance(['truthy' => ['on'], 'falsy' => ['off']]);

    $this->captureOutputThrows(
      \InvalidArgumentException::class,
      'Discovered value "yes" for "OK?" is not a valid answer. Accepted values: on, off.',
      function (): void {
        Prompty::confirm('OK?', discovered: 'yes');
      },
    );
  }

  public function testSelectWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Framework', options: ['react' => 'React'], description: 'Pick your framework.', discovered: 'react', ctx: $this->ctx());
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
      $result = Prompty::confirm('Install?', description: 'Runs npm install.', discovered: TRUE, ctx: $this->ctx());
    });

    $this->assertTrue($result);
    $this->assertStringContainsString('Yes', $output);
  }

  public function testTextWithDescription(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::text('Project name', description: 'Used as the directory name.', discovered: 'my-app', ctx: $this->ctx());
    });

    $this->assertSame('my-app', $result);
    $this->assertStringContainsString('my-app', $output);
  }

  public function testSelectRendersOptionLabelNotKey(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick', options: ['a' => 'Alpha'], discovered: 'a', ctx: $this->ctx());
    });

    $this->assertSame('a', $result);
    $this->assertStringContainsString('Alpha', $output);
  }

  public function testSelectRendersEmptyOptionLabel(): void {
    $output = $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick', options: ['a' => ''], discovered: 'a', ctx: $this->ctx());
    });

    $this->assertSame('a', $result);
    $this->assertStringContainsString('Pick', $output);
  }

  #[DataProvider('dataProviderSelectOutOfDomainThrows')]
  public function testSelectOutOfDomainThrows(?string $discovered, ?string $ctx_discovered, array $options, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::select('Pick',
      options: $options,
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ), $message);
  }

  public static function dataProviderSelectOutOfDomainThrows(): \Iterator {
    $options = ['a' => 'Alpha', 'b' => 'Beta'];

    yield 'discovered argument' => ['unknown', NULL, $options, 'Discovered value "unknown" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'env value' => [NULL, 'nope', $options, 'Discovered value "nope" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'empty env value' => [NULL, '', $options, 'Discovered value "" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'option label is not a key' => ['Alpha', NULL, $options, 'Discovered value "Alpha" for "Pick" is not a valid option. Available options: a, b.'];
  }

  public function testSelectTrimsDiscoveredValue(): void {
    $this->captureOutput(function () use (&$result): void {
      $result = Prompty::select('Pick', options: ['a' => 'Alpha'], ctx: $this->ctx(['discovered' => '  a  ']));
    });

    $this->assertSame('a', $result);
  }

  #[DataProvider('dataProviderMultiselectOutOfDomainThrows')]
  public function testMultiselectOutOfDomainThrows(?array $discovered, ?string $ctx_discovered, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::multiselect('Pick',
      options: ['a' => 'Alpha', 'b' => 'Beta'],
      discovered: $discovered,
      ctx: $this->ctx(['discovered' => $ctx_discovered]),
    ), $message);
  }

  public static function dataProviderMultiselectOutOfDomainThrows(): \Iterator {
    yield 'sole entry unknown' => [['nope'], NULL, 'Discovered value "nope" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'one entry of several unknown' => [['a', 'zz'], NULL, 'Discovered value "zz" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'env entry unknown' => [NULL, 'a,zz', 'Discovered value "zz" for "Pick" is not a valid option. Available options: a, b.'];
    yield 'option label is not a key' => [['Alpha'], NULL, 'Discovered value "Alpha" for "Pick" is not a valid option. Available options: a, b.'];
  }

  #[DataProvider('dataProviderMultiselectNormalizesDiscovered')]
  public function testMultiselectNormalizesDiscovered(mixed $discovered, ?string $ctx_discovered, array $expected): void {
    $this->captureOutput(function () use ($discovered, $ctx_discovered, &$result): void {
      $result = Prompty::multiselect('Pick',
        options: ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'],
        discovered: $discovered,
        ctx: $this->ctx(['discovered' => $ctx_discovered]),
      );
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderMultiselectNormalizesDiscovered(): \Iterator {
    yield 'argument reordered to option order' => [['c', 'a'], NULL, ['a', 'c']];
    yield 'argument duplicates removed' => [['b', 'a', 'b'], NULL, ['a', 'b']];
    yield 'env reordered to option order' => [NULL, 'c,a', ['a', 'c']];
    yield 'env duplicates removed' => [NULL, 'b,a,b', ['a', 'b']];
    yield 'env entries trimmed' => [NULL, ' a , b ', ['a', 'b']];
    yield 'empty env means none' => [NULL, '', []];
    yield 'trailing comma ignored' => [NULL, 'a,', ['a']];
    yield 'doubled comma ignored' => [NULL, 'a,,b', ['a', 'b']];
    yield 'whitespace-only entry ignored' => [NULL, 'a, ,b', ['a', 'b']];
    yield 'empty argument list' => [[], NULL, []];
  }

  #[DataProvider('dataProviderConfirmCoercesDiscoveredArgument')]
  public function testConfirmCoercesDiscoveredArgument(mixed $discovered, bool $expected): void {
    $this->captureOutput(function () use ($discovered, &$result): void {
      $result = Prompty::confirm('Install?', discovered: $discovered, ctx: $this->ctx());
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderConfirmCoercesDiscoveredArgument(): \Iterator {
    yield 'bool TRUE' => [TRUE, TRUE];
    yield 'bool FALSE' => [FALSE, FALSE];
    yield 'string yes' => ['yes', TRUE];
    yield 'string no' => ['no', FALSE];
    yield 'string NO (case)' => ['NO', FALSE];
    yield 'string true' => ['true', TRUE];
    yield 'string false' => ['false', FALSE];
    yield 'string padded' => [' no ', FALSE];
    yield 'int 1' => [1, TRUE];
    yield 'int 0' => [0, FALSE];
  }

  #[DataProvider('dataProviderConfirmOutOfDomainThrows')]
  public function testConfirmOutOfDomainThrows(mixed $discovered, ?string $ctx_discovered, string $message): void {
    $this->assertWidgetRejects(fn(): mixed => Prompty::confirm('Install?', discovered: $discovered, ctx: $this->ctx(['discovered' => $ctx_discovered])), $message);
  }

  public static function dataProviderConfirmOutOfDomainThrows(): \Iterator {
    $accepted = 'Accepted values: 1, true, yes, 0, false, no.';

    yield 'discovered argument' => ['maybe', NULL, 'Discovered value "maybe" for "Install?" is not a valid answer. ' . $accepted];
    yield 'env value' => [NULL, 'maybe', 'Discovered value "maybe" for "Install?" is not a valid answer. ' . $accepted];
    yield 'empty env value' => [NULL, '', 'Discovered value "" for "Install?" is not a valid answer. ' . $accepted];
    yield 'out of range int' => [2, NULL, 'Discovered value "2" for "Install?" is not a valid answer. ' . $accepted];
  }

  #[DataProvider('dataProviderConfirmHonorsCustomTruthyFalsy')]
  public function testConfirmHonorsCustomTruthyFalsy(?string $discovered, ?string $ctx_discovered, bool $expected): void {
    $ctx = $this->ctx(['truthy' => ['on'], 'falsy' => ['off'], 'discovered' => $ctx_discovered]);

    $this->captureOutput(function () use ($discovered, $ctx, &$result): void {
      $result = Prompty::confirm('Install?', discovered: $discovered, ctx: $ctx);
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderConfirmHonorsCustomTruthyFalsy(): \Iterator {
    yield 'custom truthy via argument' => ['on', NULL, TRUE];
    yield 'custom falsy via argument' => ['off', NULL, FALSE];
    yield 'custom truthy via env' => [NULL, 'on', TRUE];
    yield 'custom falsy via env' => [NULL, 'off', FALSE];
  }

  #[DataProvider('dataProviderConfirmMatchesListsCaseInsensitively')]
  public function testConfirmMatchesListsCaseInsensitively(string $discovered, bool $expected): void {
    $ctx = $this->ctx(['truthy' => ['YES', ' On '], 'falsy' => ['NO', ' Off ']]);

    $this->captureOutput(function () use ($discovered, $ctx, &$result): void {
      $result = Prompty::confirm('Install?', discovered: $discovered, ctx: $ctx);
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderConfirmMatchesListsCaseInsensitively(): \Iterator {
    yield 'lowercase value against uppercase token' => ['yes', TRUE];
    yield 'uppercase value against uppercase token' => ['YES', TRUE];
    yield 'lowercase falsy against uppercase token' => ['no', FALSE];
    yield 'value against padded truthy token' => ['on', TRUE];
    yield 'value against padded falsy token' => ['off', FALSE];
  }

  public function testConfirmRejectsDefaultListValueWhenListsAreCustom(): void {
    $ctx = $this->ctx(['truthy' => ['on'], 'falsy' => ['off']]);

    $this->assertWidgetRejects(fn(): mixed => Prompty::confirm('Install?', discovered: 'yes', ctx: $ctx), 'Discovered value "yes" for "Install?" is not a valid answer. Accepted values: on, off.');
  }

  public function testWidgetsWithNumbering(): void {
    $ctx_with_number = $this->ctx(['number' => '2.1']);

    $output = $this->captureOutput(function () use ($ctx_with_number): void {
      Prompty::text('Name', discovered: 'val', ctx: $ctx_with_number);
    });

    $this->assertStringContainsString('(2.1)', $output);
  }

  #[DataProvider('dataProviderTextDefaultPrecedence')]
  public function testTextDefaultPrecedence(?string $discovered, ?string $ctx_discovered, string $expected): void {
    $this->captureOutput(function () use ($discovered, $ctx_discovered, &$result): void {
      $result = Prompty::text('Name', default: 'seed', discovered: $discovered, ctx: $this->ctx(['discovered' => $ctx_discovered]));
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderTextDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => ['disc', NULL, 'disc'];
    yield 'env wins over default' => [NULL, 'env-val', 'env-val'];
  }

  #[DataProvider('dataProviderSelectDefaultPrecedence')]
  public function testSelectDefaultPrecedence(?string $discovered, ?string $ctx_discovered, string $expected): void {
    $this->captureOutput(function () use ($discovered, $ctx_discovered, &$result): void {
      $result = Prompty::select('Framework',
        options: ['react' => 'React', 'vue' => 'Vue'],
        default: 'vue',
        discovered: $discovered,
        ctx: $this->ctx(['discovered' => $ctx_discovered]),
      );
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderSelectDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => ['react', NULL, 'react'];
    yield 'env wins over default' => [NULL, 'react', 'react'];
  }

  #[DataProvider('dataProviderMultiselectDefaultPrecedence')]
  public function testMultiselectDefaultPrecedence(?array $discovered, ?string $ctx_discovered, array $expected): void {
    $this->captureOutput(function () use ($discovered, $ctx_discovered, &$result): void {
      $result = Prompty::multiselect('Features',
        options: ['ts' => 'TypeScript', 'eslint' => 'ESLint'],
        default: ['ts'],
        discovered: $discovered,
        ctx: $this->ctx(['discovered' => $ctx_discovered]),
      );
    });

    $this->assertSame($expected, $result);
  }

  public static function dataProviderMultiselectDefaultPrecedence(): \Iterator {
    yield 'discovered wins over default' => [['eslint'], NULL, ['eslint']];
    yield 'env wins over default' => [NULL, 'eslint', ['eslint']];
  }

}
