<?php

/**
 * @file
 * Playground - nested flow with conditionals, 3 levels deep, 3 items per level.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi', 'no-interaction']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);
$interactive = !isset($opts['no-interaction']);

$results = Prompty::flow()
  ->select('course', 'Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
    hints: [
      'starter' => 'Served first, in a small portion.',
      'main' => 'The centre of the meal.',
      'dessert' => 'Sweet, served last.',
    ],
  )
    ->children()
      ->select('serving', 'Serving',
        options: ['plated' => 'Plated', 'sharing' => 'Sharing', 'bowl' => 'Bowl'],
        description: 'How it reaches the table.',
      )
        ->when(fn(array $r): bool => $r['course'] === 'starter')
        ->children()
          ->select('portion', 'Portion',
            options: ['small' => 'Small', 'regular' => 'Regular', 'generous' => 'Generous'],
            description: 'Size on the plate.',
          )
          ->confirm('bread', 'Include bread?', description: 'Cut and warmed to order.')
          ->select('dressing', 'Dressing',
            options: ['oil' => 'Oil', 'lemon' => 'Lemon', 'honey' => 'Honey'],
            description: 'Spooned over just before it leaves.',
          )
        ->end()
      ->select('method', 'Method',
        options: ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'],
        description: 'How the dish is cooked.',
        hints: [
          'baked' => 'Dry heat, all the way through.',
          'poached' => "Gently, in barely moving liquid.\nKeeps delicate things whole.",
          'grilled' => "Over the flame for colour.\nFast, hot, and unforgiving.",
        ],
      )
        ->when(fn(array $r): bool => $r['course'] === 'main')
        ->children()
          ->select('temperature', 'Temperature',
            options: ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'],
            description: 'Oven setting for the bake.',
          )
            ->when(fn(array $r): bool => in_array($r['method'], ['baked', 'grilled'], TRUE))
          ->confirm('rest', 'Rest before serving?', description: 'Lets the dish settle.')
            ->when(fn(array $r): bool => $r['method'] === 'baked')
          ->confirm('sauce', 'Sauce on the side?', description: 'Served separately rather than poured.')
        ->end()
      ->multiselect('finishes', 'Finishes',
        options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
        description: 'Applied just before serving.',
        hints: [
          'glazed' => 'Brushed with syrup for shine.',
          'dusted' => "Icing sugar through a fine sieve.\nDone at the last moment.",
          'piped' => 'Cream rosettes around the edge.',
        ],
      )
        ->when(fn(array $r): bool => $r['course'] === 'dessert')
        ->children()
          ->select('accompaniment', 'Accompaniment',
            options: ['cream' => 'Cream', 'custard' => 'Custard', 'sorbet' => 'Sorbet'],
            description: 'Served on the side.',
          )
          ->confirm('warm', 'Serve warm?', description: 'Straight from the oven.')
          ->confirm('garnish', 'Add garnish?', description: 'A final flourish on top.')
        ->end()
    ->end()
  ->multiselect('extras', 'Extras',
    options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs', 'lemon' => 'Lemon'],
    description: "Anything served alongside.\nSpace to toggle, enter to confirm.",
    hints: [
      'bread' => 'Warm, cut at the table.',
      'olives' => 'Marinated, served in oil.',
      'herbs' => 'Picked the same morning.',
      'lemon' => 'Sharpens rich dishes.',
    ],
  )
    ->children()
      ->confirm('toast', 'Toast the bread?', description: 'Browned just before serving.')
        ->when(fn(array $r): bool => in_array('bread', $r['extras'], TRUE))
      ->select('marinade', 'Marinade',
        options: ['garlic' => 'Garlic', 'chilli' => 'Chilli', 'citrus' => 'Citrus'],
        description: 'What the olives sit in.',
      )
        ->when(fn(array $r): bool => in_array('olives', $r['extras'], TRUE))
      ->confirm('wedges', 'Cut into wedges?', description: 'Quartered rather than sliced.')
        ->when(fn(array $r): bool => in_array('lemon', $r['extras'], TRUE))
    ->end()
  ->multiselect('drinks', 'Drinks',
    options: ['tea' => 'Tea', 'coffee' => 'Coffee', 'juice' => 'Juice'],
    description: 'Poured at the table.',
    hints: [
      'tea' => 'Loose leaf, warmed in the pot.',
      'coffee' => "Ground for each cup.\nSlower, but worth the wait.",
      'juice' => 'Pressed from the morning delivery.',
    ],
  )
    ->children()
      ->select('brew', 'Brew', options: ['black' => 'Black', 'green' => 'Green'], description: 'Leaf for the pot.')
        ->when(fn(array $r): bool => in_array('tea', $r['drinks'], TRUE))
      ->select('roast', 'Roast', options: ['light' => 'Light', 'dark' => 'Dark'], description: 'How far the beans are taken.')
        ->when(fn(array $r): bool => in_array('coffee', $r['drinks'], TRUE))
      ->select('fruit', 'Fruit',
        options: ['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum', 'quince' => 'Quince'],
        description: 'Pressed to order.',
      )
        ->when(fn(array $r): bool => in_array('juice', $r['drinks'], TRUE))
    ->end()
  ->run(
    intro: 'Kitchen order',
    outro: function (array $results): void {
      Prompty::outro('Order sent to the kitchen!');
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
    },
    cancelled: 'Kitchen order cancelled.',
    numbering: TRUE,
    unicode: $unicode,
    ansi: $ansi,
    env_prefix: 'KITCHEN_',
    interactive: $interactive,
  );
