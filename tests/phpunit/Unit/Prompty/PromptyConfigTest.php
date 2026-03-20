<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty constructor, configuration, and singleton behavior.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyConfigTest extends PromptyTestCase {

  public function testDefaultConfig(): void {
    $p = $this->createInstance();

    $this->assertIsArray($this->getProperty($p, 'cfgSymbolsUnicode'));
    $this->assertIsArray($this->getProperty($p, 'cfgSymbolsAscii'));
    $this->assertIsArray($this->getProperty($p, 'cfgColors'));
    $this->assertIsArray($this->getProperty($p, 'cfgSpacing'));
    $this->assertIsArray($this->getProperty($p, 'cfgLabels'));
    $this->assertSame('PROMPTY_', $this->getProperty($p, 'cfgEnvPrefix'));
    $this->assertSame(['1', 'true', 'yes'], $this->getProperty($p, 'cfgTruthy'));
    $this->assertSame(['0', 'false', 'no'], $this->getProperty($p, 'cfgFalsy'));
  }

  public function testDefaultConfigSymbolKeys(): void {
    $p = $this->createInstance();

    $expected_symbol_keys = ['bar', 'completed', 'active', 'intro', 'outro', 'radio_on', 'radio_off', 'check_on', 'check_off', 'hint_arrow'];
    /** @var array<string, string> $symbols_unicode */
    $symbols_unicode = $this->getProperty($p, 'cfgSymbolsUnicode');
    /** @var array<string, string> $symbols_ascii */
    $symbols_ascii = $this->getProperty($p, 'cfgSymbolsAscii');
    $this->assertSame($expected_symbol_keys, array_keys($symbols_unicode));
    $this->assertSame($expected_symbol_keys, array_keys($symbols_ascii));
  }

  #[DataProvider('dataProviderConfigOverrides')]
  public function testConfigOverrides(array $overrides, string $property, mixed $expected): void {
    $p = $this->createInstance($overrides);

    $this->assertSame($expected, $this->getProperty($p, $property));
  }

  public static function dataProviderConfigOverrides(): \Iterator {
    yield 'custom env_prefix' => [
      ['env_prefix' => 'MY_APP_'],
      'cfgEnvPrefix',
      'MY_APP_',
    ];

    yield 'custom truthy values' => [
      ['truthy' => ['y', 'on', 'yep']],
      'cfgTruthy',
      ['y', 'on', 'yep'],
    ];

    yield 'custom labels' => [
      ['labels' => ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(nah)', 'none' => 'Zilch', 'separator' => '|']],
      'cfgLabels',
      ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(nah)', 'none' => 'Zilch', 'separator' => '|'],
    ];
  }

  #[DataProvider('dataProviderUnicodeDetection')]
  public function testUnicodeDetection(string $env_var, string $env_value, bool $expected): void {
    // Clear all locale env vars first.
    $saved = [];
    foreach (['LANG', 'LC_ALL', 'LC_CTYPE'] as $var) {
      $saved[$var] = getenv($var);
      putenv($var);
    }

    putenv($env_var . '=' . $env_value);

    $p = $this->createInstance(['unicode' => NULL]);

    $this->assertSame($expected, $this->getProperty($p, 'cfgUnicode'));

    // Restore env vars.
    foreach ($saved as $var => $value) {
      $value !== FALSE ? putenv($var . '=' . $value) : putenv($var);
    }
  }

  public static function dataProviderUnicodeDetection(): \Iterator {
    yield 'LANG with UTF-8' => ['LANG', 'en_US.UTF-8', TRUE];
    yield 'LANG with utf8' => ['LANG', 'en_AU.utf8', TRUE];
    yield 'LANG without UTF' => ['LANG', 'C', FALSE];
    yield 'LC_ALL with UTF-8' => ['LC_ALL', 'en_US.UTF-8', TRUE];
    yield 'LC_CTYPE with UTF-8' => ['LC_CTYPE', 'en_US.UTF-8', TRUE];
  }

  #[DataProvider('dataProviderUnicodeForced')]
  public function testUnicodeForced(bool $unicode, string $expected_bar): void {
    $p = $this->createInstance(['unicode' => $unicode]);

    $this->assertSame($unicode, $this->getProperty($p, 'cfgUnicode'));
    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame($expected_bar, $symbols['bar']);
  }

  public static function dataProviderUnicodeForced(): \Iterator {
    yield 'forced unicode' => [TRUE, '│'];
    yield 'forced ascii' => [FALSE, '|'];
  }

  #[DataProvider('dataProviderSymbolResolution')]
  public function testSymbolResolution(bool $unicode, string $expected_completed, string $expected_active): void {
    $p = $this->createInstance(['unicode' => $unicode]);

    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame($expected_completed, $symbols['completed']);
    $this->assertSame($expected_active, $symbols['active']);
  }

  public static function dataProviderSymbolResolution(): \Iterator {
    yield 'unicode symbols' => [TRUE, '◆', '◇'];
    yield 'ascii symbols' => [FALSE, '+', 'o'];
  }

  #[DataProvider('dataProviderConfigure')]
  public function testConfigure(array $args, string $property, mixed $expected): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    Prompty::configure(...$args);

    $this->assertSame($expected, $this->getProperty($p, $property));
  }

  public static function dataProviderConfigure(): \Iterator {
    yield 'env_prefix' => [
      ['env_prefix' => 'MYAPP_'],
      'cfgEnvPrefix',
      'MYAPP_',
    ];

    yield 'truthy' => [
      ['truthy' => ['y', 'on']],
      'cfgTruthy',
      ['y', 'on'],
    ];

    yield 'falsy' => [
      ['falsy' => ['n', 'off']],
      'cfgFalsy',
      ['n', 'off'],
    ];

    yield 'unicode true' => [
      ['unicode' => TRUE],
      'cfgUnicode',
      TRUE,
    ];

    yield 'unicode false' => [
      ['unicode' => FALSE],
      'cfgUnicode',
      FALSE,
    ];
  }

  public function testConfigurePartialArrayMerge(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    /** @var array<string, string> $original_labels */
    $original_labels = $this->getProperty($p, 'cfgLabels');
    $original_no = $original_labels['no'];

    Prompty::configure(labels: ['yes' => 'Yep']);

    /** @var array<string, string> $labels */
    $labels = $this->getProperty($p, 'cfgLabels');
    $this->assertSame('Yep', $labels['yes']);
    $this->assertSame($original_no, $labels['no']);
  }

  public function testConfigureColors(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    Prompty::configure(colors: ['cyan' => "\033[96m"]);

    /** @var array<string, string> $colors */
    $colors = $this->getProperty($p, 'cfgColors');
    $this->assertSame("\033[96m", $colors['cyan']);
    $this->assertSame("\033[0m", $colors['reset']);
  }

  public function testConfigureSpacing(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    Prompty::configure(spacing: ['indent' => '    ']);

    /** @var array<string, string> $spacing */
    $spacing = $this->getProperty($p, 'cfgSpacing');
    $this->assertSame('    ', $spacing['indent']);
    $this->assertSame('    ', $spacing['hint_indent']);
  }

  public function testConfigureSymbolsUnicode(): void {
    $p = $this->createInstance(['unicode' => TRUE]);
    $this->setStaticProperty('instance', $p);

    Prompty::configure(symbols_unicode: ['bar' => '┃']);

    /** @var array<string, string> $symbols_unicode */
    $symbols_unicode = $this->getProperty($p, 'cfgSymbolsUnicode');
    $this->assertSame('┃', $symbols_unicode['bar']);

    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('┃', $symbols['bar']);
  }

  public function testConfigureSymbolsAscii(): void {
    $p = $this->createInstance(['unicode' => FALSE]);
    $this->setStaticProperty('instance', $p);

    Prompty::configure(symbols_ascii: ['bar' => '!']);

    /** @var array<string, string> $symbols_ascii */
    $symbols_ascii = $this->getProperty($p, 'cfgSymbolsAscii');
    $this->assertSame('!', $symbols_ascii['bar']);

    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('!', $symbols['bar']);
  }

  #[DataProvider('dataProviderAnsiDetection')]
  public function testAnsiDetection(string $env_var, string $env_value, bool $expected): void {
    $saved_no_color = getenv('NO_COLOR');
    $saved_term = getenv('TERM');
    putenv('NO_COLOR');
    putenv('TERM');

    if ($env_value !== '') {
      putenv($env_var . '=' . $env_value);
    }

    $p = $this->createInstance(['ansi' => NULL]);

    $this->assertSame($expected, $this->getProperty($p, 'cfgAnsi'));

    $saved_no_color !== FALSE ? putenv('NO_COLOR=' . $saved_no_color) : putenv('NO_COLOR');
    $saved_term !== FALSE ? putenv('TERM=' . $saved_term) : putenv('TERM');
  }

  public static function dataProviderAnsiDetection(): \Iterator {
    yield 'NO_COLOR set' => ['NO_COLOR', '1', FALSE];
    yield 'NO_COLOR empty string' => ['NO_COLOR', '', TRUE];
    yield 'TERM dumb' => ['TERM', 'dumb', FALSE];
    yield 'TERM xterm' => ['TERM', 'xterm-256color', TRUE];
  }

  #[DataProvider('dataProviderAnsiForced')]
  public function testAnsiForced(bool $ansi): void {
    $p = $this->createInstance(['ansi' => $ansi]);

    $this->assertSame($ansi, $this->getProperty($p, 'cfgAnsi'));
    /** @var array<string, string> $colors */
    $colors = $this->getProperty($p, 'cfgColors');
    if ($ansi) {
      $this->assertSame("\033[36m", $colors['cyan']);
    }
    else {
      $this->assertSame('', $colors['cyan']);
      $this->assertSame('', $colors['reset']);
    }
  }

  public static function dataProviderAnsiForced(): \Iterator {
    yield 'forced ansi on' => [TRUE];
    yield 'forced ansi off' => [FALSE];
  }

  public function testConfigureAnsiToggle(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    Prompty::configure(ansi: FALSE);

    /** @var array<string, string> $colors */
    $colors = $this->getProperty($p, 'cfgColors');
    $this->assertSame('', $colors['cyan']);
    $this->assertSame('', $colors['reset']);

    Prompty::configure(ansi: TRUE);

    /** @var array<string, string> $colors_restored */
    $colors_restored = $this->getProperty($p, 'cfgColors');
    $this->assertSame("\033[36m", $colors_restored['cyan']);
    $this->assertSame("\033[0m", $colors_restored['reset']);
  }

  public function testConfigureAnsiWithColorOverrides(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    Prompty::configure(colors: ['cyan' => "\033[96m"]);
    Prompty::configure(ansi: FALSE);

    /** @var array<string, string> $colors_off */
    $colors_off = $this->getProperty($p, 'cfgColors');
    $this->assertSame('', $colors_off['cyan']);

    Prompty::configure(ansi: TRUE);

    /** @var array<string, string> $colors_on */
    $colors_on = $this->getProperty($p, 'cfgColors');
    $this->assertSame("\033[96m", $colors_on['cyan']);
  }

  public function testAnsiInConfig(): void {
    $p = $this->createInstance(['ansi' => FALSE]);
    $this->setStaticProperty('instance', $p);

    /** @var array<string, mixed> $cfg */
    $cfg = Prompty::config();
    $this->assertArrayHasKey('ansi', $cfg);
    $this->assertFalse($cfg['ansi']);
  }

  public function testConfigureUnicodeResolvesSymbols(): void {
    $p = $this->createInstance(['unicode' => FALSE]);
    $this->setStaticProperty('instance', $p);

    /** @var array<string, string> $symbols_before */
    $symbols_before = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('|', $symbols_before['bar']);

    Prompty::configure(unicode: TRUE);

    /** @var array<string, string> $symbols_after */
    $symbols_after = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('│', $symbols_after['bar']);
  }

  public function testConfigureNullsAreIgnored(): void {
    $p = $this->createInstance();
    $this->setStaticProperty('instance', $p);

    $original_prefix = $this->getProperty($p, 'cfgEnvPrefix');

    Prompty::configure();

    $this->assertSame($original_prefix, $this->getProperty($p, 'cfgEnvPrefix'));
  }

  public function testVersionReturnsDevelopment(): void {
    $this->assertSame('development', Prompty::version());
  }

  public function testSingletonCreation(): void {
    $this->setStaticProperty('instance', NULL);

    // Access config to trigger singleton creation.
    Prompty::config();

    $instance1 = $this->getStaticProperty('instance');
    Prompty::config();
    $instance2 = $this->getStaticProperty('instance');

    $this->assertSame($instance1, $instance2);
  }

  public function testConfigPublicAccess(): void {
    $this->setStaticProperty('instance', NULL);

    /** @var array<string, mixed> $cfg */
    $cfg = Prompty::config();

    $this->assertArrayHasKey('symbols', $cfg);
    $this->assertArrayHasKey('colors', $cfg);
    $this->assertArrayHasKey('env_prefix', $cfg);
  }

}
