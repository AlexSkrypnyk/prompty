<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Unit\Prompty;

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../Prompty.php';
require_once __DIR__ . '/../../../../PromptyTestTrait.php';

/**
 * Base test case for Prompty unit tests.
 *
 * Delegates to PromptyTestTrait for reflection and output helpers.
 * Shorter aliases keep existing tests unchanged.
 */
abstract class PromptyTestCase extends TestCase {

  use PromptyTestTrait;

  protected function createInstance(array $config = []): Prompty {
    return $this->promptyCreateInstance($config);
  }

  protected function callProtected(object $instance, string $method, mixed ...$args): mixed {
    return $this->promptyCallProtected($instance, $method, ...$args);
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
    $this->promptyTearDown();
    parent::tearDown();
  }

}
