<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty\Tests\Fixtures;

/**
 * Fixture - a class embed.php minifies to the same bytes on every PHP version.
 *
 * It uses each construct the minifier rewrites. Its expected payloads change
 * only when the minifier does, unlike the payloads of Prompty.php.
 *
 * @license MIT
 */
class EmbedSource {

  /**
   * The fixture version.
   */
  public const VERSION = '9.8.7';

  /**
   * Microseconds between courses.
   */
  protected const PAUSE = 50000;

  /**
   * Shared instance.
   *
   * @var self|null
   */
  protected static ?self $instance = NULL;

  /**
   * Course labels.
   *
   * @var array<string, string>
   */
  protected array $courses = [
    'starter' => 'Starter',
    'main' => 'Main',
    'dessert' => "Dessert [\033[1msweet\033[0m]",
  ];

  /**
   * Extras chosen so far.
   *
   * @var list<string>
   */
  protected array $extras = [];

  /**
   * Creates the shared instance.
   */
  protected function __construct() {
    $this->extras = [];
  }

  /**
   * Returns the shared instance.
   */
  public static function instance(): self {
    if (!static::$instance instanceof EmbedSource) {
      static::$instance = new self();
    }

    return static::$instance;
  }

  /**
   * Describes a dish for the order ticket.
   *
   * @param string $dish
   *   The dish name.
   * @param string $course
   *   The course key.
   * @param list<string> $sides
   *   The extras.
   *
   * @return string
   *   The ticket line.
   */
  public function describe(string $dish, string $course = 'main', array $sides = []): string {
    $this->extras = array_values(array_filter(array_map(trim(...), $sides), fn(string $side): bool => $side !== ''));
    $label = $this->courses[$course] ?? $this->labelFor($course);
    $suffix = $this->extras === [] ? '' : ' with ' . implode(', ', $this->extras);

    return match (TRUE) {
      $dish === '' => sprintf('%s (unnamed)%s', $label, $suffix),
      default => sprintf('%s: %s%s', $label, $dish, $suffix),
    };
  }

  /**
   * Returns the pause between courses, in microseconds.
   */
  public function pause(?int $seconds = NULL): int {
    return ($seconds ?? 0) * 1000000 + static::PAUSE;
  }

  /**
   * Builds a label for a course without one.
   *
   * @param string $course
   *   The course key.
   *
   * @return string
   *   The label.
   */
  protected function labelFor(string $course): string {
    $words = explode('_', $course);
    $format = static function (string $word) use ($course): string {
      $word = trim($word);

      return $word === '' ? $course : ucfirst($word);
    };

    return implode(' ', array_map($format, $words));
  }

}
