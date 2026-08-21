<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the keys and lists configure() accepts.
 */
#[CoversClass(Prompty::class)]
#[Group('unit')]
final class PromptyConfigureKeysTest extends PromptyTestCase {

  protected function setUp(): void {
    parent::setUp();
    $this->createAndSetInstance();
  }

  /**
   * The message an unknown key produces.
   *
   * @param string $parameter
   *   The configure() parameter that carried the key.
   * @param string $config_key
   *   The config() key holding the valid set.
   *
   * @return string
   *   The expected exception message.
   */
  protected function rejection(string $parameter, string $config_key): string {
    /** @var array<string, string> $declared */
    $declared = Prompty::config()[$config_key];

    return sprintf('Configuration key "bogus" for "%s" is not valid. Available keys: %s.', $parameter, implode(', ', array_keys($declared)));
  }

  #[DataProvider('dataProviderRejectsUnknownKey')]
  public function testRejectsUnknownKey(\Closure $configure, string $parameter, string $config_key): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($this->rejection($parameter, $config_key));

    $configure();
  }

  public static function dataProviderRejectsUnknownKey(): \Iterator {
    yield 'unicode symbols' => [
      static function (): void {
        Prompty::configure(symbols_unicode: ['bogus' => 'X']);
      },
      'symbols_unicode',
      'symbols_unicode',
    ];

    yield 'ascii symbols' => [
      static function (): void {
        Prompty::configure(symbols_ascii: ['bogus' => 'X']);
      },
      'symbols_ascii',
      'symbols_ascii',
    ];

    yield 'colors' => [
      static function (): void {
        Prompty::configure(colors: ['bogus' => 'X']);
      },
      'colors',
      'colors',
    ];

    yield 'spacing' => [
      static function (): void {
        Prompty::configure(spacing: ['bogus' => 'X']);
      },
      'spacing',
      'spacing',
    ];

    yield 'labels' => [
      static function (): void {
        Prompty::configure(labels: ['bogus' => 'X']);
      },
      'labels',
      'labels',
    ];
  }

  public function testRejectsMisspeltKey(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Configuration key "bar_" for "symbols_unicode" is not valid.');

    Prompty::configure(symbols_unicode: ['bar_' => '|']);
  }

  #[DataProvider('dataProviderRejectsEmptyList')]
  public function testRejectsEmptyList(\Closure $configure, string $parameter): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('No values declared for "%s". Provide at least one value.', $parameter));

    $configure();
  }

  public static function dataProviderRejectsEmptyList(): \Iterator {
    yield 'truthy' => [
      static function (): void {
        Prompty::configure(truthy: []);
      },
      'truthy',
    ];

    yield 'falsy' => [
      static function (): void {
        Prompty::configure(falsy: []);
      },
      'falsy',
    ];
  }

  #[DataProvider('dataProviderRejectsBlankValue')]
  public function testRejectsBlankValue(\Closure $configure, string $parameter): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('Blank value declared for "%s". Every value must hold a non-space character.', $parameter));

    $configure();
  }

  public static function dataProviderRejectsBlankValue(): \Iterator {
    yield 'empty truthy value' => [
      static function (): void {
        Prompty::configure(truthy: ['yes', '']);
      },
      'truthy',
    ];

    yield 'space-only truthy value' => [
      static function (): void {
        Prompty::configure(truthy: ['  ']);
      },
      'truthy',
    ];

    yield 'empty falsy value' => [
      static function (): void {
        Prompty::configure(falsy: ['no', '']);
      },
      'falsy',
    ];

    yield 'space-only falsy value' => [
      static function (): void {
        Prompty::configure(falsy: ["\t"]);
      },
      'falsy',
    ];
  }

  public function testAcceptsDeclaredKeys(): void {
    Prompty::configure(symbols_ascii: ['bar' => '!'], spacing: ['indent' => '    '], labels: ['yes' => 'Aye']);

    $config = Prompty::config();

    $this->assertIsArray($config['labels']);
    $this->assertIsArray($config['spacing']);
    $this->assertIsArray($config['symbols_ascii']);

    $this->assertSame('Aye', $config['labels']['yes']);
    $this->assertSame('    ', $config['spacing']['indent']);
    $this->assertSame('!', $config['symbols_ascii']['bar']);
  }

  public function testRejectionLeavesTheConfigurationAlone(): void {
    $before = Prompty::config();

    try {
      Prompty::configure(labels: ['yes' => 'Aye', 'bogus' => 'X']);
    }
    catch (\InvalidArgumentException) {
      // The message is asserted elsewhere; this checks nothing was applied.
    }

    $this->assertSame($before['labels'], Prompty::config()['labels']);
  }

  public function testRejectionLeavesEarlierArgumentsAlone(): void {
    $before = Prompty::config();

    try {
      Prompty::configure(colors: ['cyan' => "\033[35m"], labels: ['yes' => 'Aye'], truthy: []);
    }
    catch (\InvalidArgumentException) {
      // The rejected argument is the last one, so the two valid arguments
      // ahead of it prove the check runs before anything is written.
    }

    $after = Prompty::config();

    $this->assertSame($before['labels'], $after['labels']);
    $this->assertSame($before['colors'], $after['colors']);
    $this->assertSame($before['truthy'], $after['truthy']);
  }

  public function testAcceptedArgumentsApplyTogether(): void {
    Prompty::configure(colors: ['cyan' => "\033[35m"], labels: ['yes' => 'Aye'], truthy: ['aye']);

    $config = Prompty::config();

    $this->assertIsArray($config['labels']);
    $this->assertIsArray($config['colors']);

    $this->assertSame('Aye', $config['labels']['yes']);
    $this->assertSame("\033[35m", $config['colors']['cyan']);
    $this->assertSame(['aye'], $config['truthy']);
  }

}
