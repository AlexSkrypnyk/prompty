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
 *
 * @phpstan-type Ticket array{
 *   dish: string,
 *   copies: int,
 * }
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
   * Rewrites each ticket line, or NULL to keep it as it is.
   */
  protected ?\Closure $formatter = NULL;

  /**
   * Creates the shared instance.
   */
  protected function __construct() {
    $this->extras = [];
    $this->formatter = NULL;
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
   * Lists a ticket line for each course.
   *
   * @param array<string, string> $dishes
   *   Dish names keyed by course.
   * @param int $copies
   *   How many of each dish.
   *
   * @return list<string>
   *   The ticket lines.
   */
  public function tickets(array $dishes, int $copies = 1): array {
    $lines = [];

    foreach ($dishes as $course => $dish) {
      $lines[] = $this->plate(dish: $dish, copies: $copies) . ' / ' . $this->labelFor($course);
    }

    return $lines;
  }

  /**
   * Counts a dish in a tally.
   *
   * @param array<string, int> $counts
   *   Counts keyed by dish.
   * @param string $dish
   *   The dish.
   */
  public function tally(array &$counts, string $dish): void {
    $counts[$dish] = ($counts[$dish] ?? 0) + 1;
    ksort($counts);
  }

  /**
   * Describes the plates served and returned at a table.
   *
   * @param int $served
   *   Plates served.
   * @param int $returned
   *   Plates sent back.
   *
   * @return string
   *   The summary.
   */
  public function plates(int $served, int $returned): string {
    return 'Table ' . 4 . ': ' . $served - -$returned . ' moved, ' . $served + +$returned . ' handled, ' . 2.5 . ' per guest';
  }

  /**
   * Describes several portions of a dish.
   *
   * @param string $dish
   *   The dish name.
   * @param int $copies
   *   How many portions.
   *
   * @return string
   *   The portions.
   */
  protected function plate(string $dish, int $copies): string {
    $copies = max(1, $copies);

    return $copies === 1 ? $dish : sprintf('%dx %s', $copies, $dish);
  }

  /**
   * Summarises the tally and defers a recount to a closure.
   *
   * @param array<string, int> $counts
   *   Counts keyed by dish.
   *
   * @return array{string, int}
   *   The summary line and the recount the closure returns.
   */
  public function recount(array $counts): array {
    $lines = [];

    foreach ($counts as $dish => $copies) {
      $this->tally($counts, $dish);
      $lines[] = $this->plate($dish, $copies);
    }

    $line = $this->describe(implode(' + ', $lines));
    $line = $this->describe($line, 'main', $this->extras);
    $recount = function () use ($counts): int {
      $extra = count($this->extras);

      return count($counts) + $extra;
    };

    return [$line, $recount()];
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
