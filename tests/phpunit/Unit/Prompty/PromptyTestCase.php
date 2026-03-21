<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Prompty unit tests.
 *
 * Delegates to PromptyTestTrait for reflection and output helpers.
 * Shorter aliases keep existing tests unchanged.
 */
abstract class PromptyTestCase extends TestCase {

  use PromptyTestTrait;

  /**
   * Environment variables set during the test.
   *
   * @var list<string>
   */
  protected array $envVarsToClean = [];

  /**
   * Default widget context array.
   *
   * @param array<string, mixed> $overrides
   *   Overrides.
   *
   * @return array<string, mixed>
   *   Context array.
   */
  protected function defaultCtx(array $overrides = []): array {
    return array_merge([
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
      'number' => NULL,
      'env_value' => NULL,
    ], $overrides);
  }

  /**
   * Create a Prompty instance and set it as the singleton.
   */
  protected function createAndSetInstance(array $config = [], bool $in_flow = FALSE): Prompty {
    $p = $this->promptyCreateInstance($config);
    $this->promptySetStatic('instance', $p);

    if ($in_flow) {
      $this->promptySetStatic('inFlow', TRUE);
    }

    return $p;
  }

  /**
   * Set environment variables for a test.
   *
   * @param array<string, string> $vars
   *   Key-value pairs to set.
   * @param string $prefix
   *   Env prefix.
   */
  protected function setEnvVars(array $vars, string $prefix = 'PROMPTY_'): void {
    foreach ($vars as $key => $value) {
      $env_key = $prefix . strtoupper($key);
      putenv($env_key . '=' . $value);
      $this->envVarsToClean[] = $env_key;
    }
  }

  /**
   * Clear environment variables after a test.
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

  protected function createInstance(array $config = []): Prompty {
    return $this->promptyCreateInstance($config);
  }

  protected function callProtected(object $instance, string $method, mixed ...$args): mixed {
    return $this->promptyCallProtected($instance, $method, ...$args);
  }

  /**
   * Call a protected method and assert it returns an array of strings.
   *
   * @return string[]
   *   The array of strings returned by the method.
   */
  protected function callProtectedLines(object $instance, string $method, mixed ...$args): array {
    $result = $this->promptyCallProtected($instance, $method, ...$args);
    $this->assertIsArray($result);
    $lines = [];
    foreach ($result as $item) {
      $this->assertIsString($item);
      $lines[] = $item;
    }

    return $lines;
  }

  protected function setStaticProperty(string $name, mixed $value): void {
    $this->promptySetStatic($name, $value);
  }

  protected function getStaticProperty(string $name): mixed {
    return $this->promptyGetStatic($name);
  }

  protected function getProperty(object $instance, string $name): mixed {
    return $this->promptyGetProperty($instance, $name);
  }

  protected function setProperty(object $instance, string $name, mixed $value): void {
    $this->promptySetProperty($instance, $name, $value);
  }

  protected function stripAnsi(string $text): string {
    return $this->promptyStripAnsi($text);
  }

  protected function captureOutput(callable $fn): string {
    ob_start();
    $fn();

    return $this->promptyStripAnsi(ob_get_clean() ?: '');
  }

  protected function tearDown(): void {
    // Clean up any env vars set during the test.
    foreach ($this->envVarsToClean as $key) {
      putenv($key);
    }
    $this->envVarsToClean = [];

    $this->promptyTearDown();
    parent::tearDown();
  }

}
