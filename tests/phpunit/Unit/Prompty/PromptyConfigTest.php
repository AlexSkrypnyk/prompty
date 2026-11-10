<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Prompty configuration and singleton behavior.
 *
 * Covers the defaults, what configure() accepts, what it rejects, and how a
 * rejection leaves the configuration alone.
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
    $this->assertTrue($this->getProperty($p, 'cfgIsInteractive'));
    $this->assertSame(0, $this->getProperty($p, 'cfgScroll'));
    $this->assertFalse($this->getProperty($p, 'cfgIsLegend'));
  }

  public function testDefaultConfigSymbolKeys(): void {
    $p = $this->createInstance();

    $expected_symbol_keys = ['bar', 'completed', 'active', 'intro', 'outro', 'section', 'pointer', 'radio_on', 'radio_off', 'check_on', 'check_off', 'hint_arrow', 'error', 'scroll_up', 'scroll_down', 'success', 'spinner', 'ellipsis', 'box_top_left', 'box_top_right', 'box_bottom_left', 'box_bottom_right', 'box_horizontal', 'box_vertical', 'box_cross', 'box_top_tee', 'box_bottom_tee', 'box_left_tee', 'box_right_tee', 'legend_vertical', 'legend_horizontal', 'legend_separator', 'note_info', 'note_success', 'note_warning', 'note_error'];
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
    yield 'custom env_prefix' => [['env_prefix' => 'MY_APP_'], 'cfgEnvPrefix', 'MY_APP_'];

    yield 'custom truthy values' => [['truthy' => ['y', 'on', 'yep']], 'cfgTruthy', ['y', 'on', 'yep']];

    yield 'custom labels' => [
      ['labels' => ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(nah)', 'none' => 'Zilch', 'separator' => '|']],
      'cfgLabels',
      ['yes' => 'Yep', 'no' => 'Nope', 'cancelled' => '(nah)', 'none' => 'Zilch', 'separator' => '|'],
    ];
  }

  /**
   * Tests unicode and ansi detection from the environment.
   *
   * @param array<string, string|null> $env
   *   Environment variables to apply; a NULL value unsets the variable.
   * @param string $option
   *   The config key left as NULL so the constructor detects it.
   * @param string $property
   *   The property holding the detected value.
   * @param bool $expected
   *   The detected value.
   */
  #[DataProvider('dataProviderEnvironmentDetection')]
  public function testEnvironmentDetection(array $env, string $option, string $property, bool $expected): void {
    $this->withEnv($env, function () use ($option, $property, $expected): void {
      $p = $this->createInstance([$option => NULL]);

      $this->assertSame($expected, $this->getProperty($p, $property));
    });
  }

  public static function dataProviderEnvironmentDetection(): \Iterator {
    yield 'LANG with UTF-8' => [['LANG' => 'en_US.UTF-8', 'LC_ALL' => NULL, 'LC_CTYPE' => NULL], 'unicode', 'cfgIsUnicode', TRUE];
    yield 'LANG with utf8' => [['LANG' => 'en_AU.utf8', 'LC_ALL' => NULL, 'LC_CTYPE' => NULL], 'unicode', 'cfgIsUnicode', TRUE];
    yield 'LANG without UTF' => [['LANG' => 'C', 'LC_ALL' => NULL, 'LC_CTYPE' => NULL], 'unicode', 'cfgIsUnicode', FALSE];
    yield 'LC_ALL with UTF-8' => [['LANG' => NULL, 'LC_ALL' => 'en_US.UTF-8', 'LC_CTYPE' => NULL], 'unicode', 'cfgIsUnicode', TRUE];
    yield 'LC_CTYPE with UTF-8' => [['LANG' => NULL, 'LC_ALL' => NULL, 'LC_CTYPE' => 'en_US.UTF-8'], 'unicode', 'cfgIsUnicode', TRUE];
    yield 'NO_COLOR set' => [['NO_COLOR' => '1', 'TERM' => NULL], 'ansi', 'cfgIsAnsi', FALSE];
    yield 'NO_COLOR and TERM unset' => [['NO_COLOR' => NULL, 'TERM' => NULL], 'ansi', 'cfgIsAnsi', TRUE];
    yield 'TERM dumb' => [['NO_COLOR' => NULL, 'TERM' => 'dumb'], 'ansi', 'cfgIsAnsi', FALSE];
    yield 'TERM xterm' => [['NO_COLOR' => NULL, 'TERM' => 'xterm-256color'], 'ansi', 'cfgIsAnsi', TRUE];
  }

  #[DataProvider('dataProviderUnicodeForced')]
  public function testUnicodeForced(bool $unicode, string $bar, string $completed, string $active): void {
    $p = $this->createInstance(['unicode' => $unicode]);

    $this->assertSame($unicode, $this->getProperty($p, 'cfgIsUnicode'));
    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame($bar, $symbols['bar']);
    $this->assertSame($completed, $symbols['completed']);
    $this->assertSame($active, $symbols['active']);
  }

  public static function dataProviderUnicodeForced(): \Iterator {
    yield 'forced unicode' => [TRUE, '│', '◆', '◇'];
    yield 'forced ascii' => [FALSE, '|', '+', 'o'];
  }

  #[DataProvider('dataProviderAnsiForced')]
  public function testAnsiForced(bool $ansi, string $cyan, string $reset): void {
    $p = $this->createInstance(['ansi' => $ansi]);

    $this->assertSame($ansi, $this->getProperty($p, 'cfgIsAnsi'));
    /** @var array<string, string> $colors */
    $colors = $this->getProperty($p, 'cfgColors');
    $this->assertSame($cyan, $colors['cyan']);
    $this->assertSame($reset, $colors['reset']);
  }

  public static function dataProviderAnsiForced(): \Iterator {
    yield 'forced ansi on' => [TRUE, "\033[36m", "\033[0m"];
    yield 'forced ansi off' => [FALSE, '', ''];
  }

  #[DataProvider('dataProviderConfigure')]
  public function testConfigure(array $args, string $property, mixed $expected): void {
    $p = $this->createAndSetInstance();

    Prompty::configure(...$args);

    $this->assertSame($expected, $this->getProperty($p, $property));
  }

  public static function dataProviderConfigure(): \Iterator {
    yield 'env_prefix' => [['env_prefix' => 'MYAPP_'], 'cfgEnvPrefix', 'MYAPP_'];

    yield 'truthy' => [['truthy' => ['y', 'on']], 'cfgTruthy', ['y', 'on']];

    yield 'falsy' => [['falsy' => ['n', 'off']], 'cfgFalsy', ['n', 'off']];

    yield 'unicode true' => [['unicode' => TRUE], 'cfgIsUnicode', TRUE];

    yield 'unicode false' => [['unicode' => FALSE], 'cfgIsUnicode', FALSE];

    yield 'interactive false' => [['interactive' => FALSE], 'cfgIsInteractive', FALSE];

    yield 'interactive true' => [['interactive' => TRUE], 'cfgIsInteractive', TRUE];

    yield 'scroll' => [['scroll' => 10], 'cfgScroll', 10];

    yield 'scroll 0' => [['scroll' => 0], 'cfgScroll', 0];

    yield 'placeholder_fallback false' => [['placeholder_fallback' => FALSE], 'cfgIsPlaceholderFallback', FALSE];

    yield 'placeholder_fallback true' => [['placeholder_fallback' => TRUE], 'cfgIsPlaceholderFallback', TRUE];

    yield 'width' => [['width' => 120], 'cfgWidth', 120];

    yield 'width 0' => [['width' => 0], 'cfgWidth', 0];

    yield 'legend true' => [['legend' => TRUE], 'cfgIsLegend', TRUE];

    yield 'legend false' => [['legend' => FALSE], 'cfgIsLegend', FALSE];
  }

  #[DataProvider('dataProviderConfigurePartialArrayMerge')]
  public function testConfigurePartialArrayMerge(array $args, string $property, string $key, string $value, string $kept_key, string $kept_value): void {
    $p = $this->createAndSetInstance();

    Prompty::configure(...$args);

    /** @var array<string, string> $merged */
    $merged = $this->getProperty($p, $property);
    $this->assertSame($value, $merged[$key]);
    $this->assertSame($kept_value, $merged[$kept_key]);
  }

  public static function dataProviderConfigurePartialArrayMerge(): \Iterator {
    yield 'labels' => [['labels' => ['yes' => 'Yep']], 'cfgLabels', 'yes', 'Yep', 'no', 'No'];

    yield 'colors' => [['colors' => ['cyan' => "\033[96m"]], 'cfgColors', 'cyan', "\033[96m", 'reset', "\033[0m"];

    yield 'spacing' => [['spacing' => ['indent' => '    ']], 'cfgSpacing', 'indent', '    ', 'hint_indent', '    '];
  }

  #[DataProvider('dataProviderConfigureSymbols')]
  public function testConfigureSymbols(bool $unicode, array $args, string $property, string $bar): void {
    $p = $this->createAndSetInstance(['unicode' => $unicode]);

    Prompty::configure(...$args);

    /** @var array<string, string> $stored */
    $stored = $this->getProperty($p, $property);
    $this->assertSame($bar, $stored['bar']);

    /** @var array<string, string> $symbols */
    $symbols = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame($bar, $symbols['bar']);
  }

  public static function dataProviderConfigureSymbols(): \Iterator {
    yield 'unicode set' => [TRUE, ['symbols_unicode' => ['bar' => '┃']], 'cfgSymbolsUnicode', '┃'];

    yield 'ascii set' => [FALSE, ['symbols_ascii' => ['bar' => '!']], 'cfgSymbolsAscii', '!'];
  }

  public function testConfigureAnsiToggle(): void {
    $p = $this->createAndSetInstance();

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
    $p = $this->createAndSetInstance();

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
    $this->createAndSetInstance(['ansi' => FALSE]);

    /** @var array<string, mixed> $cfg */
    $cfg = Prompty::config();
    $this->assertArrayHasKey('ansi', $cfg);
    $this->assertFalse($cfg['ansi']);
  }

  public function testInteractiveInConfig(): void {
    $this->createAndSetInstance(['interactive' => FALSE]);

    $this->assertFalse(Prompty::config()['interactive']);
  }

  public function testPlaceholderFallbackInConfig(): void {
    $this->createAndSetInstance();
    $this->assertTrue(Prompty::config()['placeholder_fallback']);

    $this->createAndSetInstance(['placeholder_fallback' => FALSE]);
    $this->assertFalse(Prompty::config()['placeholder_fallback']);
  }

  public function testLegendInConfig(): void {
    $this->createAndSetInstance();
    $this->assertFalse(Prompty::config()['legend']);

    $this->createAndSetInstance(['legend' => TRUE]);
    $this->assertTrue(Prompty::config()['legend']);
  }

  public function testConfigureUnicodeResolvesSymbols(): void {
    $p = $this->createAndSetInstance(['unicode' => FALSE]);

    /** @var array<string, string> $symbols_before */
    $symbols_before = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('|', $symbols_before['bar']);

    Prompty::configure(unicode: TRUE);

    /** @var array<string, string> $symbols_after */
    $symbols_after = $this->getProperty($p, 'cfgSymbols');
    $this->assertSame('│', $symbols_after['bar']);
  }

  public function testConfigureNullsAreIgnored(): void {
    $p = $this->createAndSetInstance();

    $original_prefix = $this->getProperty($p, 'cfgEnvPrefix');

    Prompty::configure();

    $this->assertSame($original_prefix, $this->getProperty($p, 'cfgEnvPrefix'));
    $this->assertTrue($this->getProperty($p, 'cfgIsInteractive'));
  }

  /**
   * Tests configure() applies every accepted argument.
   *
   * @param array $args
   *   The configure() arguments.
   * @param array<string, array<string, string>> $expected_partial
   *   Expected entries inside map-valued configuration keys.
   * @param array<string, list<string>> $expected_full
   *   Expected whole values for list-valued configuration keys.
   */
  #[DataProvider('dataProviderConfigureAcceptsDeclaredKeys')]
  public function testConfigureAcceptsDeclaredKeys(array $args, array $expected_partial, array $expected_full): void {
    $this->createAndSetInstance();

    Prompty::configure(...$args);

    $config = Prompty::config();

    foreach ($expected_partial as $config_key => $entries) {
      $actual = $config[$config_key];
      $this->assertIsArray($actual);

      foreach ($entries as $entry_key => $value) {
        $this->assertSame($value, $actual[$entry_key]);
      }
    }

    foreach ($expected_full as $config_key => $values) {
      $this->assertSame($values, $config[$config_key]);
    }
  }

  public static function dataProviderConfigureAcceptsDeclaredKeys(): \Iterator {
    yield 'map arguments' => [
      ['symbols_ascii' => ['bar' => '!'], 'spacing' => ['indent' => '    '], 'labels' => ['yes' => 'Aye']],
      ['labels' => ['yes' => 'Aye'], 'spacing' => ['indent' => '    '], 'symbols_ascii' => ['bar' => '!']],
      [],
    ];

    yield 'map and list arguments together' => [
      ['colors' => ['cyan' => "\033[35m"], 'labels' => ['yes' => 'Aye'], 'truthy' => ['aye']],
      ['labels' => ['yes' => 'Aye'], 'colors' => ['cyan' => "\033[35m"]],
      ['truthy' => ['aye']],
    ];
  }

  #[DataProvider('dataProviderConfigureRejectsUnknownKey')]
  public function testConfigureRejectsUnknownKey(array $args, string $key, string $parameter): void {
    $this->createAndSetInstance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($this->rejectionMessage($key, $parameter));

    Prompty::configure(...$args);
  }

  public static function dataProviderConfigureRejectsUnknownKey(): \Iterator {
    yield 'unicode symbols' => [['symbols_unicode' => ['bogus' => 'X']], 'bogus', 'symbols_unicode'];
    yield 'ascii symbols' => [['symbols_ascii' => ['bogus' => 'X']], 'bogus', 'symbols_ascii'];
    yield 'colors' => [['colors' => ['bogus' => 'X']], 'bogus', 'colors'];
    yield 'spacing' => [['spacing' => ['bogus' => 'X']], 'bogus', 'spacing'];
    yield 'labels' => [['labels' => ['bogus' => 'X']], 'bogus', 'labels'];
    yield 'misspelt key' => [['symbols_unicode' => ['bar_' => '|']], 'bar_', 'symbols_unicode'];
  }

  #[DataProvider('dataProviderConfigureRejectsInvalidList')]
  public function testConfigureRejectsInvalidList(array $args, string $message): void {
    $this->createAndSetInstance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    Prompty::configure(...$args);
  }

  public static function dataProviderConfigureRejectsInvalidList(): \Iterator {
    yield 'empty truthy list' => [['truthy' => []], 'No values declared for "truthy". Provide at least one value.'];
    yield 'empty falsy list' => [['falsy' => []], 'No values declared for "falsy". Provide at least one value.'];
    yield 'empty truthy value' => [['truthy' => ['yes', '']], 'Blank value declared for "truthy". Every value must hold a non-space character.'];
    yield 'space-only truthy value' => [['truthy' => ['  ']], 'Blank value declared for "truthy". Every value must hold a non-space character.'];
    yield 'empty falsy value' => [['falsy' => ['no', '']], 'Blank value declared for "falsy". Every value must hold a non-space character.'];
    yield 'tab-only falsy value' => [['falsy' => ["\t"]], 'Blank value declared for "falsy". Every value must hold a non-space character.'];
    yield 'negative scroll' => [['scroll' => -1], 'Scroll size -1 for "scroll" is not valid. Use 0 to show every option, or the number of options to show at once.'];
    yield 'negative width' => [['width' => -1], 'Width -1 for "width" is not valid. Use 0 to detect the terminal width, or the number of columns to wrap to.'];
  }

  /**
   * Tests a rejected configure() call applies none of its arguments.
   *
   * @param array $args
   *   The configure() arguments, at least 1 of them invalid.
   * @param list<string> $checked_keys
   *   The config() keys asserted unchanged.
   */
  #[DataProvider('dataProviderConfigureRejectionLeavesConfigAlone')]
  public function testConfigureRejectionLeavesConfigAlone(array $args, array $checked_keys): void {
    $this->createAndSetInstance();

    $before = Prompty::config();

    try {
      Prompty::configure(...$args);
    }
    catch (\InvalidArgumentException) {
      // The message is asserted elsewhere; this checks nothing was applied.
    }

    $after = Prompty::config();

    foreach ($checked_keys as $checked_key) {
      $this->assertSame($before[$checked_key], $after[$checked_key]);
    }
  }

  public static function dataProviderConfigureRejectionLeavesConfigAlone(): \Iterator {
    yield 'rejected key beside a valid key' => [['labels' => ['yes' => 'Aye', 'bogus' => 'X']], ['labels']];

    yield 'rejected argument after valid arguments' => [
      ['colors' => ['cyan' => "\033[35m"], 'labels' => ['yes' => 'Aye'], 'truthy' => []],
      ['labels', 'colors', 'truthy'],
    ];

    yield 'rejected scroll after valid arguments' => [['labels' => ['yes' => 'Aye'], 'env_prefix' => 'KITCHEN_', 'scroll' => -3], ['labels', 'env_prefix', 'scroll']];

    yield 'rejected width after valid arguments' => [['labels' => ['yes' => 'Aye'], 'scroll' => 4, 'width' => -2], ['labels', 'scroll', 'width']];
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

  /**
   * Runs a callback under a modified environment, restoring it afterwards.
   *
   * @param array<string, string|null> $env
   *   Map of variable name to value; a NULL value unsets the variable.
   * @param callable $callback
   *   The callback to run while the environment is modified.
   */
  protected function withEnv(array $env, callable $callback): void {
    $saved = [];

    foreach ($env as $var => $value) {
      $saved[$var] = getenv($var);
      $value !== NULL ? putenv($var . '=' . $value) : putenv($var);
    }

    try {
      $callback();
    }
    finally {
      foreach ($saved as $var => $original) {
        $original !== FALSE ? putenv($var . '=' . $original) : putenv($var);
      }
    }
  }

  /**
   * Builds the message configure() raises for an unknown configuration key.
   *
   * @param string $key
   *   The rejected key.
   * @param string $parameter
   *   The configure() parameter carrying the key, also the config() key that
   *   holds the valid set.
   *
   * @return string
   *   The expected exception message.
   */
  protected function rejectionMessage(string $key, string $parameter): string {
    /** @var array<string, string> $declared */
    $declared = Prompty::config()[$parameter];

    return sprintf('Configuration key "%s" for "%s" is not valid. Available keys: %s.', $key, $parameter, implode(', ', array_keys($declared)));
  }

}
