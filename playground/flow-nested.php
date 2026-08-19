<?php

/**
 * @file
 * Playground - nested flow with conditionals, 3 levels deep, 3 items per level.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);

$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course',
    options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'],
    description: 'Where the dish sits in the meal.',
    hints: [
      'starter' => 'Served first, in a small portion.',
      'main' => 'The centre of the meal.',
      'dessert' => 'Sweet, served last.',
    ],
    children: [
      'serving' => Prompty::select('Serving',
        options: ['plated' => 'Plated', 'sharing' => 'Sharing', 'bowl' => 'Bowl'],
        description: 'How it reaches the table.',
        condition: fn($r): bool => ($r['course'] ?? '') === 'starter',
        children: [
          'portion' => Prompty::select('Portion',
            options: ['small' => 'Small', 'regular' => 'Regular', 'generous' => 'Generous'],
            description: 'Size on the plate.',
          ),
          'bread' => Prompty::confirm('Include bread?', description: 'Cut and warmed to order.'),
          'dressing' => Prompty::select('Dressing',
            options: ['oil' => 'Oil', 'lemon' => 'Lemon', 'honey' => 'Honey'],
            description: 'Spooned over just before it leaves.',
          ),
        ],
      ),
      'method' => Prompty::select('Method',
        options: ['baked' => 'Baked', 'poached' => 'Poached', 'grilled' => 'Grilled'],
        description: 'How the dish is cooked.',
        hints: [
          'baked' => 'Dry heat, all the way through.',
          'poached' => "Gently, in barely moving liquid.\nKeeps delicate things whole.",
          'grilled' => "Over the flame for colour.\nFast, hot, and unforgiving.",
        ],
        condition: fn($r): bool => ($r['course'] ?? '') === 'main',
        children: [
          'temperature' => Prompty::select('Temperature',
            options: ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'],
            description: 'Oven setting for the bake.',
            condition: fn($r): bool => in_array($r['method'] ?? '', ['baked', 'grilled'], TRUE),
          ),
          'rest' => Prompty::confirm('Rest before serving?',
            description: 'Lets the dish settle.',
            condition: fn($r): bool => ($r['method'] ?? '') === 'baked',
          ),
          'sauce' => Prompty::confirm('Sauce on the side?', description: 'Served separately rather than poured.'),
        ],
      ),
      'finishes' => Prompty::multiselect('Finishes',
        options: ['glazed' => 'Glazed', 'dusted' => 'Dusted', 'piped' => 'Piped'],
        description: 'Applied just before serving.',
        hints: [
          'glazed' => 'Brushed with syrup for shine.',
          'dusted' => "Icing sugar through a fine sieve.\nDone at the last moment.",
          'piped' => 'Cream rosettes around the edge.',
        ],
        condition: fn($r): bool => ($r['course'] ?? '') === 'dessert',
        children: [
          'accompaniment' => Prompty::select('Accompaniment',
            options: ['cream' => 'Cream', 'custard' => 'Custard', 'sorbet' => 'Sorbet'],
            description: 'Served on the side.',
          ),
          'warm' => Prompty::confirm('Serve warm?', description: 'Straight from the oven.'),
          'garnish' => Prompty::confirm('Add garnish?', description: 'A final flourish on top.'),
        ],
      ),
    ],
  ),

  'extras' => Prompty::multiselect('Extras',
    options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs', 'lemon' => 'Lemon'],
    description: "Anything served alongside.\nSpace to toggle, enter to confirm.",
    hints: [
      'bread' => 'Warm, cut at the table.',
      'olives' => 'Marinated, served in oil.',
      'herbs' => 'Picked the same morning.',
      'lemon' => 'Sharpens rich dishes.',
    ],
    children: [
      'toast' => Prompty::confirm('Toast the bread?',
        description: 'Browned just before serving.',
        condition: fn($r): bool => in_array('bread', $r['extras'] ?? [], TRUE),
      ),
      'marinade' => Prompty::select('Marinade',
        options: ['garlic' => 'Garlic', 'chilli' => 'Chilli', 'citrus' => 'Citrus'],
        description: 'What the olives sit in.',
        condition: fn($r): bool => in_array('olives', $r['extras'] ?? [], TRUE),
      ),
      'wedges' => Prompty::confirm('Cut into wedges?',
        description: 'Quartered rather than sliced.',
        condition: fn($r): bool => in_array('lemon', $r['extras'] ?? [], TRUE),
      ),
    ],
  ),

  'drinks' => Prompty::multiselect('Drinks',
    options: ['tea' => 'Tea', 'coffee' => 'Coffee', 'juice' => 'Juice'],
    description: 'Poured at the table.',
    hints: [
      'tea' => 'Loose leaf, warmed in the pot.',
      'coffee' => "Ground for each cup.\nSlower, but worth the wait.",
      'juice' => 'Pressed from the morning delivery.',
    ],
    children: [
      'brew' => Prompty::select('Brew',
        options: ['black' => 'Black', 'green' => 'Green'],
        description: 'Leaf for the pot.',
        condition: fn($r): bool => in_array('tea', $r['drinks'] ?? [], TRUE),
      ),
      'roast' => Prompty::select('Roast',
        options: ['light' => 'Light', 'dark' => 'Dark'],
        description: 'How far the beans are taken.',
        condition: fn($r): bool => in_array('coffee', $r['drinks'] ?? [], TRUE),
      ),
      'fruit' => Prompty::select('Fruit',
        options: ['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum', 'quince' => 'Quince'],
        description: 'Pressed to order.',
        condition: fn($r): bool => in_array('juice', $r['drinks'] ?? [], TRUE),
      ),
    ],
  ),
],
  intro: 'Kitchen order',
  outro: function (array $results): void {
    Prompty::outro('Order sent to the kitchen!');
    echo "\nCollected answers:\n";
    foreach ($results as $key => $value) {
      $display = is_array($value) ? (count($value) > 0 ? implode(', ', array_filter($value, is_string(...))) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
      echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
    }
  },
  cancelled: 'Kitchen order cancelled.',
  numbering: TRUE,
  unicode: $unicode,
  ansi: $ansi,
  env_prefix: 'KITCHEN_',
);
