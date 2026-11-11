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

**Tables**: 4, 7, 12

**Seats**: 1, 2, 3, 4

Tables and seats are the theme's numeric-keyed fields: their option keys are numbers rather than words, which is what a demo needs to show a widget handling numeric keys.

## Canonical fields

Use these exact labels, keys, values, hints and help lines. Keys are lowercase single words wherever possible. Descriptions and hints longer than one sentence may render across two lines in a demo, split at the sentence boundary. A help line is a short usage tip shown under the input, kept apart from the description.

### Dish name (text)

- Label: `Dish name`
- Placeholder and default: `pear tart`
- Key: `dish`
- Description (when one is shown): `Written on the order ticket and under "Specials" on the board.`

### Guest name (text)

- Label: `Guest name`
- Placeholder: `Jane Doe`
- Key: `guest`
- Description: `Printed on the order ticket.`
- Help: `Leave blank for takeaway.`

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
- Help: `Space to toggle, enter to confirm.`
- Hints:
  - `bread`: `Warm, cut at the table.`
  - `olives`: `Marinated, served in oil.`
  - `herbs`: `Picked the same morning.`
- Longer demos extend the options with `lemon` => `Lemon` and `honey` => `Honey`:
  - `lemon`: `Sharpens rich dishes.`
  - `honey`: `Runny, spooned over to finish.`

### Table (select, numeric keys)

- Label: `Table`
- Key: `table`
- Options: `4` => `Table 4`, `7` => `Table 7`, `12` => `Table 12`
- Description: `Where the order is going.`
- Default: `7`
- Hints:
  - `4`: `By the window, seats two.`
  - `7`: `The long one near the pass.`
  - `12`: `Corner booth, seats six.`

### Seats (multiselect, numeric keys)

- Label: `Seats`
- Key: `seats`
- Options: `1` => `Seat 1`, `2` => `Seat 2`, `3` => `Seat 3`, `4` => `Seat 4`
- Description: `Who at the table is having this dish.`
- Default: `[2, 4]`

### Menu (select, long list)

- Label: `Menu`
- Key: `menu`
- Options: every dish, in vocabulary order: `tart` => `Pear Tart`, `crumble` => `Apple Crumble`, `soup` => `Onion Soup`, `stew` => `Lentil Stew`, `omelette` => `Herb Omelette`, `salad` => `Barley Salad`, `compote` => `Plum Compote`, `loaf` => `Walnut Loaf`
- Description: `The whole menu, 3 dishes at a time.`
- Default: `compote`
- Scroll: `3`

### Pantry (multiselect, long list)

- Label: `Pantry`
- Key: `pantry`
- Options: every extra, then every fruit: `bread` => `Bread`, `olives` => `Olives`, `herbs` => `Herbs`, `lemon` => `Lemon`, `honey` => `Honey`, `almonds` => `Almonds`, `apple` => `Apple`, `pear` => `Pear`, `plum` => `Plum`, `quince` => `Quince`
- Description: `Set out for the order.`
- Default: `['honey', 'pear']`
- Scroll: `4`

### Dish (suggest)

- Label: `Dish`
- Key: `dish`
- Options: every dish label, in vocabulary order: `Pear Tart`, `Apple Crumble`, `Onion Soup`, `Lentil Stew`, `Herb Omelette`, `Barley Salad`, `Plum Compote`, `Walnut Loaf`
- Placeholder: `Pear Tart`
- Description: `Pick from the menu, or type a dish that is not on it.`
- Help: `Up and down to pick, enter to confirm.`
- Scroll: `4`

### Send order (confirm)

- Label: `Send order?`
- Key: `send`
- Description: `Passes the order to the kitchen.`

### Method (select, nested under Main)

- Label: `Method`
- Key: `method`
- Options: `baked` => `Baked`, `poached` => `Poached`, `grilled` => `Grilled`
- Description: `How the dish is cooked.`
- Help: `Up and down to move, enter to choose.`
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
- Help: `Space to toggle, enter to confirm.`
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
- `Fire the mains?` (key `fire`) - `The kitchen starts cooking at once.`, with the help line `Make sure the table is ready.`

### Section headings

Headings that group a flow's steps with `->section()`:

- `The dish` - `Name the dish and place it in the meal.` Groups Dish name and Course.
- `Finishing` - `Applied just before serving.` and `Only asked for desserts.` on 2 lines. Groups Finishes, which shows only for dessert.
- `Service` - with `Table and extras` as a second title line, and the description `Where the order is going and what is served alongside.` Groups Table and Extras.
- `Ticket` - no description. Groups Send order?.

### Notes

Messages a demo prints with `Prompty::note()`, 1 per type:

- `info`: `The kitchen opens at 5pm.`
- `success`: `Order sent to the kitchen.`
- `warning`: `The pear tart is running low.`
- `error`: `The oven did not reach temperature.`, with `Check the fan and try again.` as a second line.

### Pauses

Messages a demo waits on with `Prompty::pause()`:

- `Press any key to start the order...` - description `The kitchen opens at noon.`, help `Escape to leave.`
- `Press Enter to send the order to the kitchen` - continues only on Enter.

### Tasks

Work a demo runs with `Prompty::task()`, between its questions:

- `Preheating the oven` - hint `Fan oven, 180°C`, success `Oven ready`, failure `Oven did not reach temperature`.
- `Setting the table` - returns the detail lines `Table 7` and `Seats 2 and 4`, success `Table set`.
- `Proving the dough` - fails with `Dough did not rise`.
- `Baking the tart` - streamed. Writes `Oven at 180°C`, `Tray 1 of 2 in the top rack`, a `Rising 20%` to `Rising 100%` progress meter, `Crust golden at the edges`, `Tray 2 of 2 in the top rack`, `Fan cycling, temperature held` and `Both trays out, resting on the rack`. Success `Tart baked`.
- `Poaching the pears` - streamed. Writes `Syrup at a low simmer`, `Pears in, lid half on` and `Checked at 20 minutes: still firm`, then fails with `Pears are not soft yet`.

Nested tasks run inside a streamed task's action, 1 inside the next. `04-tasks-nested.php` adds them a level at a time:

- `Preparing the order` - streamed, the outermost task at 3 levels. Writes `Order for table 7` and `Tart plated with cream on the side`. Success `Order ready`.
- `Baking the tart` - streamed. Writes `Oven at 180°C`, runs its nested tasks, then writes `Tart in the top rack`. Success `Tart baked`. At 3 levels it runs `Preheating the oven` first.
- `Making the pastry` - streamed, nested in `Baking the tart`. Writes `Flour and butter rubbed together`, then `Dough wrapped and chilled` or its own nested task, then `Lined into the tin`. Success `Pastry made`.
- `Resting the dough` - streamed, nested in `Making the pastry`. Writes `Chilled for 20 minutes` and `Rolled thin`. Success `Dough rested`.
- `Checking the pears` - streamed, nested in `Poaching the pears`. Writes `Checked at 20 minutes: still firm`, then fails with `Pears are not soft yet`. `Poaching the pears` then fails with `Pears not ready to serve`.

### Tables and boxes

Content printed with `Prompty::table()` and `Prompty::box()`:

- Summary table: intro `Order summary`, headers `Field` and `Answer`. Section rows reuse the section headings `The dish` and `Service`. Rows: `Dish name` - `Pear tart`, `Course` - `Dessert`, `Table` - `Table 12`, `Extras` - `Bread, olives`, and `Kitchen note` - `No nuts, and the cream on the side rather than poured over the tart` to show a wrapped cell.
- Short table: `Dish` - `Pear tart`, `Course` - `Dessert`, `Extras` - `Bread, olives`, with no headers.
- Box with a title: title `Order ready`, content `Plate the tart, then:` followed by the indented lines `ring the bell` and `call the table`.
- Box without a title: `Passes the order to the kitchen.`

### Validation messages

Messages a `validate` callback returns in demos and README snippets:

- Dish name rejects a single word: `Name the whole dish, such as "pear tart".`
- Guest name rejects an empty answer: `Enter a name for the ticket.`
- Dish (suggest) rejects a dish that is not on the menu: `Pick a dish from the menu.`
- Course rejects `starter`: `The kitchen has stopped serving starters.`
- Seats rejects an empty selection: `Pick at least one seat.`
- Extras rejects `olives` when the course is `dessert`: `Olives are not served with dessert.`
- Send order? rejects yes when no seats were picked: `Pick a seat before sending the order.`

## Nested flow shape

`05-flows-nested.php` asks Course, Extras and Drinks in turn. Course branches one way per option; Extras and Drinks reveal a child per selected option:

```text
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
- A cancelled or unanswered value renders as `cancelled`. A flow step skipped by its condition answers `NULL` and renders as `skipped` in the `Collected answers:` summary.
- Every flow supplies a `cancelled:` message. The wording stays specific to its demo (`Order cancelled.`, `Kitchen order cancelled.`) rather than being flattened to one string.
- Widget demo section headers read `--- Widget: variant ---`.
- Result lines read `  Result: <value>`, indented two spaces.

## Standard flow

The shape used by `starter.php` and the linear demos:

```php
$results = Prompty::flow()
  ->text('dish', 'Dish name', placeholder: 'pear tart')
  ->select('course', 'Course', options: ['starter' => 'Starter', 'main' => 'Main', 'dessert' => 'Dessert'])
  ->multiselect('extras', 'Extras', options: ['bread' => 'Bread', 'olives' => 'Olives', 'herbs' => 'Herbs'])
  ->confirm('send', 'Send order?')
  ->run(intro: 'Compose an order', outro: 'Order sent!', cancelled: 'Order cancelled.');
```

`05-flows.php` extends this shape with `table` and `seats` between `extras` and `send`, so the linear demo covers numeric option keys.

## Flow framing

Intro, outro and cancelled lines stay order-themed and specific to their demo:

| Demo | Intro | Outro | Cancelled |
| --- | --- | --- | --- |
| `starter.php` | `Compose an order` | `Order sent!` | `Order cancelled.` |
| `05-flows.php` | `Compose an order` | `Order sent!` | `Order cancelled.` |
| `05-flows-nested.php` | `Kitchen order` | `Order sent to the kitchen!` | `Kitchen order cancelled.` |
| `08-config.php` | `Order setup` | `All done!` | `Order setup cancelled.` |
| `05-flows-scope.php` flow 1 | `A flow with its own configuration` | `Dish noted.` | `Cancelled.` |
| `05-flows-scope.php` flow 2 | `A flow that asks for nothing` | `Course noted.` | `Cancelled.` |
| `05-flows-scope.php` flow 3 | `A flow after configure()` | `Order sent!` | `Cancelled.` |

Env prefixes: `PROMPTY_` in `05-flows.php`, `KITCHEN_` in `05-flows-nested.php`, `08-config.php` and `05-flows-scope.php`, `ORDER_` in the `08-config.php` merge demo.

## Recorded inputs

Values typed into text and confirm widgets by the asset recordings and functional tests:

- `05-flows.php` dish: `plum compote`
- `01-widgets.php` dish: `apple crumble`
- `02-widgets-text.php`: `onion soup`, `Jane Doe`, `no nuts`
- Functional tests type `plum compote`, `walnut loaf` and `onion soup` as dish names.
