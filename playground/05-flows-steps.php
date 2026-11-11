<?php

/**
 * @file
 * Playground - arguments from earlier answers, sections and step objects.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyStepInterface;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);
$interactive = !isset($opts['no-interaction']);

/**
 * Prints the answers a flow collected.
 *
 * @param array<string, string|bool|array<string>|null> $results
 *   The flow results, with NULL for a skipped step.
 */
function summarize(array $results): void {
  echo PHP_EOL . 'Collected answers:' . PHP_EOL;

  foreach ($results as $key => $value) {
    $display = match (TRUE) {
      $value === NULL => 'skipped',
      is_array($value) => count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none',
      is_bool($value) => $value ? 'yes' : 'no',
      default => $value,
    };
    echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
  }
}

echo "\n--- Flow: arguments built from earlier answers ---\n";

$methods = ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'];
$methods_dessert = ['baked' => 'Baked', 'poached' => 'Poached'];

Prompty::flow()
  ->text('dish', 'Dish name', default: 'pear tart', description: 'Written on the order ticket and under "Specials" on the board.')
  ->select('course', 'Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
  )
  ->select('method', 'Method',
    options: fn(array $r): array => $r['course'] === 'dessert' ? $methods_dessert : $methods,
    default: fn(array $r): string => $r['course'] === 'starter' ? 'poached' : 'baked',
    description: fn(array $r): string => sprintf('How the %s is cooked.', $r['dish']),
  )
    ->children()
      ->select('temperature', 'Temperature',
        options: ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'],
        default: fn(array $r): string => $r['method'] === 'grilled' ? 'high' : 'moderate',
        description: 'Oven setting for the bake.',
      )
        ->when(fn(array $r): bool => in_array($r['method'], ['baked', 'grilled'], TRUE))
    ->end()
  ->multiselect('finishes', 'Finishes',
    options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
    default: fn(array $r): array => $r['method'] === 'baked' ? ['glazed', 'dusted'] : ['piped'],
    description: 'Applied just before serving.',
  )
    ->when(fn(array $r): bool => $r['course'] === 'dessert')
  ->text('note', 'Kitchen note', default: fn(array $r): string => sprintf('%s, %s', $r['dish'], $r['method']))
  ->confirm('send', 'Send order?', description: fn(array $r): string => sprintf('Passes the %s to the kitchen.', $r['dish']))
  ->run(
    intro: 'Kitchen order',
    outro: function (array $results): void {
      Prompty::outro('Order sent to the kitchen!');
      summarize($results);
    },
    cancelled: 'Kitchen order cancelled.',
    numbering: TRUE,
    unicode: $unicode,
    ansi: $ansi,
    env_prefix: 'KITCHEN_',
    interactive: $interactive,
  );

echo "\n--- Flow: steps grouped under section headings ---\n";

$extras = ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'];
$extras_dessert = ['honey' => 'Honey', 'almonds' => 'Almonds'];

Prompty::flow()
  ->section('The dish', description: 'Name the dish and place it in the meal.')
  ->text('dish', 'Dish name', default: 'pear tart', description: 'Written on the order ticket and under "Specials" on the board.')
  ->select('course', 'Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    default: 'dessert',
    description: 'Where the dish sits in the meal.',
  )
  // The heading is drawn only when a step in its group renders.
  ->section('Finishing', description: "Applied just before serving.\nOnly asked for desserts.")
  ->multiselect('finishes', 'Finishes', options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'], default: ['dusted'])
    ->when(fn(array $r): bool => $r['course'] === 'dessert')
  ->section("Service\nTable and extras", description: 'Where the order is going and what is served alongside.')
  ->select('table', 'Table',
    options: ['4' => 'Table 4', '7' => 'Table 7', '12' => 'Table 12'],
    default: '7',
    description: 'Where the order is going.',
  )
  ->multiselect('extras', 'Extras',
    options: fn(array $r): array => $r['course'] === 'dessert' ? $extras_dessert : $extras,
    description: fn(array $r): string => sprintf('Served alongside the %s.', $r['dish']),
  )
  ->section('Ticket')
  ->confirm('send', 'Send order?', description: 'Passes the order to the kitchen.')
  ->run(
    intro: 'Compose an order',
    outro: function (array $results): void {
      Prompty::outro('Order sent!');
      summarize($results);
    },
    cancelled: 'Order cancelled.',
    numbering: 'total',
    unicode: $unicode,
    ansi: $ansi,
    interactive: $interactive,
  );

echo "\n--- Flow: a step described by an object ---\n";

$accompaniment = new class implements PromptyStepInterface {

  /**
   * {@inheritdoc}
   */
  public function key(): string {
    return 'accompaniment';
  }

  /**
   * {@inheritdoc}
   */
  public function widget(): string {
    return 'select';
  }

  /**
   * {@inheritdoc}
   */
  public function arguments(array $results): array {
    return [
      'label' => 'Accompaniment',
      'options' => ['cream' => 'Cream', 'custard' => 'Custard', 'sorbet' => 'Sorbet'],
      'description' => is_string($results['dish']) ? sprintf('Served on the side of the %s.', $results['dish']) : 'Served on the side.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function condition(array $results): bool {
    return $results['course'] === 'dessert';
  }

};

Prompty::flow()
  ->text('dish', 'Dish name', default: 'pear tart')
  ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'], default: 'dessert')
  ->add($accompaniment)
  ->confirm('send', 'Send order?', description: 'Passes the order to the kitchen.')
  ->run(
    intro: 'Kitchen order',
    outro: function (array $results): void {
      Prompty::outro('Order sent to the kitchen!');
      summarize($results);
    },
    cancelled: 'Kitchen order cancelled.',
    unicode: $unicode,
    ansi: $ansi,
    interactive: $interactive,
  );
