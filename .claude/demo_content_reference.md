# Demo Content Reference

Canonical vocabulary for every example in this repository: the playground demos, `starter.php`, README snippets, and any recorded asset.

## Core principle

**Examples use a kitchen-order theme and contain no software, product, or technology references.**

Prompty is a general-purpose prompt library, not a scaffolding tool. Software-themed examples make it read as though it were one, and they date: a framework list is wrong within a year. A kitchen order is timeless, needs no domain knowledge, and gives every widget something natural to ask.

Take vocabulary from this document rather than inventing it. If a demo needs a concept that is not here, add it here first.

## The scenario

Composing an order for a kitchen: name the dish, pick its course, choose extras, and send it. Nested demos drill into how the dish is prepared.

## Vocabulary

**Dishes**: Pear Tart, Apple Crumble, Onion Soup, Lentil Stew, Herb Omelette, Barley Salad, Plum Compote, Walnut Loaf

**Courses**: Starter, Main, Dessert

**Extras**: Bread, Olives, Herbs, Lemon, Honey, Almonds

**Methods**: Baked, Poached, Grilled

**Temperatures**: Low, Moderate, High

**Finishes**: Glazed, Dusted, Piped

**Accompaniments**: Cream, Custard, Sorbet

**Servings**: Plated, Sharing, Bowl

**Portions**: Small, Regular, Generous

**Marinades**: Garlic, Chilli, Citrus

**Dressings**: Oil, Lemon, Honey

**Drinks**: Tea, Coffee, Juice

**Brews**: Black, Green

**Roasts**: Light, Dark

**Fruits**: Apple, Pear, Plum, Quince

**Specials**: Pear Tart, Onion Soup, Lentil Stew, Herb Omelette

## Canonical fields

Use these exact labels, keys, values and hints. Keys are lowercase single words wherever possible. Descriptions and hints longer than one sentence may render across two lines in a demo, split at the sentence boundary.

### Dish name (text)

- Label: `Dish name`
- Placeholder and default: `pear tart`
- Key: `dish`
- Description (when one is shown): `Written on the order ticket and under "Specials" on the board.`

### Guest name (text)

- Label: `Guest name`
- Placeholder: `Jane Doe`
- Key: `guest`
- Description: `Printed on the order ticket. Leave blank for takeaway.`

### Allergy note (text)

- Label: `Allergy note`
- Key: `allergy`
- No placeholder; a typical entry is `no nuts`.

### Kitchen note (text)

- Label: `Kitchen note`
- Placeholder: `no onions`
- Key: `note`

### Dish (select)

- Label: `Dish`
- Key: `special`
- Options: `tart` => `Pear Tart`, `soup` => `Onion Soup`, `stew` => `Lentil Stew`, `omelette` => `Herb Omelette`
- Description: `Chosen from the specials board.`

### Course (select)

- Label: `Course`
- Key: `course`
- Options: `starter` => `Starter`, `main` => `Main`, `dessert` => `Dessert`
- Description: `Where the dish sits in the meal.`
- Hints:
  - `starter`: `Served first, in a small portion.`
  - `main`: `The centre of the meal.`
  - `dessert`: `Sweet, served last.`

### Extras (multiselect)

- Label: `Extras`
- Key: `extras`
- Options: `bread` => `Bread`, `olives` => `Olives`, `herbs` => `Herbs`
- Description: `Anything served alongside.`
- Hints:
  - `bread`: `Warm, cut at the table.`
  - `olives`: `Marinated, served in oil.`
  - `herbs`: `Picked the same morning.`
- Longer demos extend the options with `lemon` => `Lemon` and `honey` => `Honey`:
  - `lemon`: `Sharpens rich dishes.`
  - `honey`: `Runny, spooned over to finish.`

### Send order (confirm)

- Label: `Send order?`
- Key: `send`
- Description: `Passes the order to the kitchen.`

### Method (select, nested under Main)

- Label: `Method`
- Key: `method`
- Options: `baked` => `Baked`, `poached` => `Poached`, `grilled` => `Grilled`
- Description: `How the dish is cooked.`
- Hints:
  - `baked`: `Dry heat, all the way through.`
  - `poached`: `Gently, in barely moving liquid. Keeps delicate things whole.`
  - `grilled`: `Over the flame for colour. Fast, hot, and unforgiving.`

### Temperature (select, nested under Method)

- Label: `Temperature`
- Key: `temperature`
- Options: `low` => `Low`, `moderate` => `Moderate`, `high` => `High`
- Description: `Oven setting for the bake.`

### Finishes (multiselect, nested under Dessert)

- Label: `Finishes`
- Key: `finishes`
- Options: `glazed` => `Glazed`, `dusted` => `Dusted`, `piped` => `Piped`
- Description: `Applied just before serving.`
- Hints:
  - `glazed`: `Brushed with syrup for shine.`
  - `dusted`: `Icing sugar through a fine sieve. Done at the last moment.`
  - `piped`: `Cream rosettes around the edge.`

### Accompaniment (select, nested under Finishes)

- Label: `Accompaniment`
- Key: `accompaniment`
- Options: `cream` => `Cream`, `custard` => `Custard`, `sorbet` => `Sorbet`
- Description: `Served on the side.`

### Serving (select, nested under Starter)

- Label: `Serving`
- Key: `serving`
- Options: `plated` => `Plated`, `sharing` => `Sharing`, `bowl` => `Bowl`
- Description: `How it reaches the table.`

### Portion (select, nested under Serving)

- Label: `Portion`
- Key: `portion`
- Options: `small` => `Small`, `regular` => `Regular`, `generous` => `Generous`
- Description: `Size on the plate.`

### Dressing (select, nested under Serving)

- Label: `Dressing`
- Key: `dressing`
- Options: `oil` => `Oil`, `lemon` => `Lemon`, `honey` => `Honey`
- Description: `Spooned over just before it leaves.`

### Marinade (select, nested under Extras)

- Label: `Marinade`
- Key: `marinade`
- Options: `garlic` => `Garlic`, `chilli` => `Chilli`, `citrus` => `Citrus`
- Description: `What the olives sit in.`

### Drinks (multiselect)

- Label: `Drinks`
- Key: `drinks`
- Options: `tea` => `Tea`, `coffee` => `Coffee`, `juice` => `Juice`
- Description: `Poured at the table.`
- Hints:
  - `tea`: `Loose leaf, warmed in the pot.`
  - `coffee`: `Ground for each cup. Slower, but worth the wait.`
  - `juice`: `Pressed from the morning delivery.`

### Brew (select, nested under Drinks)

- Label: `Brew`
- Key: `brew`
- Options: `black` => `Black`, `green` => `Green`
- Description: `Leaf for the pot.`

### Roast (select, nested under Drinks)

- Label: `Roast`
- Key: `roast`
- Options: `light` => `Light`, `dark` => `Dark`
- Description: `How far the beans are taken.`

### Fruit (select, nested under Drinks)

- Label: `Fruit`
- Key: `fruit`
- Options: `apple` => `Apple`, `pear` => `Pear`, `plum` => `Plum`, `quince` => `Quince`
- Description: `Pressed to order.`

### Nested confirms

- `Rest before serving?` (key `rest`) - `Lets the dish settle.`
- `Sauce on the side?` (key `sauce`) - `Served separately rather than poured.`
- `Serve warm?` (key `warm`) - `Straight from the oven.`
- `Add garnish?` (key `garnish`) - `A final flourish on top.`
- `Include bread?` (key `bread`) - `Cut and warmed to order.`
- `Toast the bread?` (key `toast`) - `Browned just before serving.`
- `Cut into wedges?` (key `wedges`) - `Quartered rather than sliced.`
- `Fire the mains?` (key `fire`) - `The kitchen starts cooking at once. Make sure the table is ready.`

## Nested flow shape

`flow-nested.php` asks Course, Extras and Drinks in turn. Course branches one way per option; Extras and Drinks reveal a child per selected option:

```
Course
├── Starter  -> Serving  -> Portion, Include bread?, Dressing
├── Main     -> Method   -> Temperature, Rest before serving?, Sauce on the side?
└── Dessert  -> Finishes -> Accompaniment, Serve warm?, Add garnish?

Extras (Bread, Olives, Herbs, Lemon)
├── Bread    -> Toast the bread?
├── Olives   -> Marinade
└── Lemon    -> Cut into wedges?

Drinks (Tea, Coffee, Juice)
├── Tea      -> Brew
├── Coffee   -> Roast
└── Juice    -> Fruit
```

## Output wording

These are conventions, not vocabulary, and they hold in every demo:

- Booleans render as `yes` and `no`, never `true`/`false`.
- The flow summary heading is `Collected answers:`.
- A cancelled or unanswered value renders as `cancelled`, never `skipped`.
- Every flow supplies a `cancelled:` message. The wording stays specific to its demo (`Order cancelled.`, `Kitchen order cancelled.`) rather than being flattened to one string.
- Widget demo section headers read `--- Widget: variant ---`.
- Result lines read `  Result: <value>`, indented two spaces.

## Standard flow

The shape used by `starter.php` and the linear demos:

```php
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert']),
  'extras' => Prompty::multiselect('Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs']),
  'send' => Prompty::confirm('Send order?'),
], intro: 'Compose an order', outro: 'Order sent!');
```

## Flow framing

Intro, outro and cancelled lines stay order-themed and specific to their demo:

| Demo | Intro | Outro | Cancelled |
| --- | --- | --- | --- |
| `starter.php` | `Compose an order` | `Order sent!` | (library default) |
| `flow.php` | `Compose an order` | `Order sent!` | `Order cancelled.` |
| `flow-nested.php` | `Kitchen order` | `Order sent to the kitchen!` | `Kitchen order cancelled.` |
| `flow-config.php` | `Order setup` | `All done!` | `Order setup cancelled.` |
| `flow-multiple.php` step 1 | `Step 1: The dish` | `Dish noted.` | `Dish selection cancelled.` |
| `flow-multiple.php` step 2 | `Step 2: Extras` | `Order sent!` | `Extras selection cancelled.` |

Env prefixes: `PROMPTY_` in `flow.php`, `KITCHEN_` in `flow-nested.php` and `widgets-config.php`, `ORDER_` in `flow-config.php`.

## Recorded inputs

Values typed into text and confirm widgets by the asset recordings and functional tests:

- `flow.php` dish: `plum compote`
- `widgets.php` dish: `apple crumble`
- `widget-text.php`: `onion soup`, `Jane Doe`, `no nuts`
- Functional tests type `plum compote`, `walnut loaf` and `onion soup` as dish names.
