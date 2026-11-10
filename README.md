<p align="center">
  <a href="https://github.com/AlexSkrypnyk/prompty" rel="noopener">
  <img height=200px src="logo.png" alt="Prompty logo"></a>
</p>

<h1 align="center">Zero-dependency interactive CLI prompt library for PHP</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/alexskrypnyk/prompty.svg)](https://github.com/alexskrypnyk/prompty/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/alexskrypnyk/prompty.svg)](https://github.com/alexskrypnyk/prompty/pulls)
[![Test PHP](https://github.com/alexskrypnyk/prompty/actions/workflows/test-php.yml/badge.svg)](https://github.com/alexskrypnyk/prompty/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/alexskrypnyk/prompty/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/alexskrypnyk/prompty)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/alexskrypnyk/prompty)
![LICENSE](https://img.shields.io/github/license/alexskrypnyk/prompty)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

<p align="center">
  <img src=".util/assets/flow-nested.svg" height="400" alt="Nested flow">
</p>

Prompty asks questions in the terminal for PHP command-line scripts, from a single text prompt to a multi-step wizard. It's 1 file with no dependencies, so you can require it, install it with Composer or embed it in your script. This README is for PHP developers and covers installing Prompty, building prompts and flows, testing them and shipping the script that uses them.

## Features

- 📦 [**Zero dependencies**](#installation) - drop `Prompty.php` into your project or [embed](#embedding) it in your script
- 🧩 [**Widgets**](#widgets) - `text`, `select`, `multiselect`, `suggest`, `confirm` and `pause`
- 📣 [**Notes**](#notes) - info, success, warning and error messages in the tree
- ⏳ [**Tasks**](#tasks) - run work behind a spinner, or stream its output under the label
- 🔀 [**Flows**](#flows) - group prompts into a wizard with an intro, an outro, numbering and cancellation
- 🌳 [**Nested flows**](#nested-flows-with-conditions) - conditional children drawn as a tree
- 🧱 [**Steps built from earlier answers**](#steps-built-from-earlier-answers) - a step's label, options or default taken from the answers before it
- 🔖 [**Section headings**](#section-headings) - group a long flow's steps under headings in the tree
- ⚡ [**Standalone mode**](#standalone-mode) - use any widget on its own, outside a flow
- 🌍 [**Environment variable discovery**](#environment-variable-discovery) - fill answers from environment variables
- 🤖 [**Non-interactive mode**](#non-interactive-mode) - answer every prompt from its default, for CI and scripted runs
- 💬 [**Descriptions, hints and help**](#descriptions-hints-and-help) - text under each label, each option and each input
- ⌨️ [**Key legend**](#key-legend) - list the keys each widget accepts under it
- 📜 [**Long option lists**](#long-option-lists) - show a long `select` or `multiselect` a few options at a time
- 🗂️ [**Tables and boxes**](#tables-and-boxes) - print a summary table or a titled panel in the tree
- 🔧 [**Transform answers**](#transform-answers) - normalize an answer before the widget returns it
- ✅ [**Validation**](#validation) - reject an answer, show why and ask again
- ✨ [**Unicode and ASCII**](#unicode-and-ascii) - detected from the locale, or forced
- 🎨 [**ANSI colors**](#ansi-colors) - on by default, off under `NO_COLOR` or `TERM=dumb`
- ⚙️ [**Configuration**](#configuration) - symbols, colors, spacing, labels, the variable prefix and the yes/no values
- 🧪 [**Test harness**](#test-harness) - `PromptyTestTrait` feeds keystrokes to widgets in PHPUnit
- 🚀 [**Starter script**](#starter-script) - [`starter.php`](starter.php) as a template for your own scripts
- 📥 [**Embedding**](#embedding) - minify the class and embed it in your script

## Installation

Prompty is a single PHP file with no dependencies. It requires PHP 8.3 or later.

### Download from releases

Download `Prompty.php` from the [latest release](https://github.com/AlexSkrypnyk/prompty/releases/latest) assets.

The release has 3 variants of the class:

| Asset                 | Description                                                             |
|-----------------------|-------------------------------------------------------------------------|
| `Prompty.php`         | Full source with version imprinted                                      |
| `Prompty.min.php`     | Minified - comments except the license header and blank lines stripped, class body on 1 line |
| `Prompty.compact.php` | Minified with no optional whitespace, plus shortened internal names, variables and parameters |

For testing, also download `PromptyTestTrait.php` from the same release.

Each release also ships `Prompty.min.payload` and `Prompty.compact.payload`. Each holds the exact code `embed.php` writes between the markers in that mode. To check an embedded script against them, see [Verify an embedded script](#verify-an-embedded-script).

To embed the class in your script, see [Embedding](#embedding).

### Verifying a download

Every release ships a `SHA256SUMS` file signed with the maintainer's GPG key, and a build attestation for every asset. To check a download with GPG or the GitHub CLI, follow [Verifying a release](SECURITY.md#verifying-a-release) in `SECURITY.md`, which also lists the key's fingerprint.

### Composer

```bash
composer require alexskrypnyk/prompty
```

`PromptyTestTrait` is included in the package.

## Usage

Require the file and use the class directly:

```php
require_once __DIR__ . '/Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$dish = Prompty::text('Dish name');
```

To ship a single file instead, [embed](#embedding) the minified class in your script. To start from a template, copy the [starter script](#starter-script).

## Widgets

Prompty has 6 widgets. Each returns the answer, or `null` when the user presses <kbd>Escape</kbd> or <kbd>Ctrl+C</kbd>.

Widgets draw only printable text. A newline or tab in a value, label or option label becomes a space. Escape sequences and other control characters are removed, so a value can't break the tree or control the terminal. `text` and `suggest` return the same printable text they drew. A [description](#descriptions-hints-and-help) also keeps its text styling.

<p align="center">
  <img src=".util/assets/widgets.svg" width="100%" alt="Text, select, multiselect and confirm widgets in sequence">
</p>

### Text

Free-form text input, with an optional `default` and `placeholder`:

```php
$dish = Prompty::text('Dish name',
  default: 'pear tart',
  placeholder: 'e.g. pear tart',
  description: "Written on the order ticket and under\n"
    . '"Specials" on the board.',
);
```

`default` pre-fills the input with text the user can edit. `placeholder` is the gray text shown while the input is empty.

Submitting an empty input returns the `placeholder` string itself, so a placeholder also works as a fallback answer. With a `default` set, submitting without typing returns the default. To let an empty answer stay empty, leave both unset.

A [discovered](#environment-variable-discovery) value follows the same rule. Prompty trims it, and an empty result takes the `default`, or the `placeholder` when no default is set:

```php
// All 3 answer 'pear tart'.
// Submitted without typing:
Prompty::text('Dish name', placeholder: 'pear tart');
// Discovered as empty or blank:
Prompty::text('Dish name', placeholder: 'pear tart', discovered: '');
Prompty::text('Dish name', placeholder: 'pear tart', discovered: '  ');
```

In a flow with a `dish` step, an empty `PROMPTY_DISH=` takes the fallback without prompting. An unset `PROMPTY_DISH` opens the prompt, where submitting without typing gives the same fallback.

A placeholder such as `e.g. pear tart` is an example, so it makes a poor answer. To keep it out of the results, pass `placeholder_fallback: FALSE` to [`Prompty::configure()`](#configuration). To set it for 1 flow, pass it to `Prompty::flow()`. An empty submit then returns `''`, and so does a blank discovered value when no `default` is set:

```php
Prompty::configure(placeholder_fallback: FALSE);

// Returns '' when the user clears the input and presses Enter.
Prompty::text('Dish name', default: 'pear tart', placeholder: 'e.g. pear tart');
```

The option covers `text` and `suggest`, and both still draw the placeholder while the input is empty. To make `text` or `suggest` re-prompt on an empty answer, pass [`required`](#required-answers).

<table>
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/widget-text.svg" alt="Text: Unicode + ANSI"></td>
    <td><img src=".util/assets/widget-text-no-ansi.svg" alt="Text: Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/widget-text-ascii.svg" alt="Text: ASCII + ANSI"></td>
    <td><img src=".util/assets/widget-text-ascii-no-ansi.svg" alt="Text: ASCII + No ANSI"></td>
  </tr>
</table>

### Select

Pick 1 option from a list. Press the arrow keys to move and <kbd>Enter</kbd> to confirm. To focus an option other than the first, pass its key as `default`:

```php
$course = Prompty::select('Course',
  options: [
    'starter' => 'Starter',
    'main' => 'Main',
    'dessert' => 'Dessert',
  ],
  default: 'main',
  description: 'Where the dish sits in the meal.',
  hints: [
    'starter' => 'Served first, in a small portion.',
    'main' => 'The centre of the meal.',
    'dessert' => 'Sweet, served last.',
  ],
);
```

`options` must hold at least 1 option. An empty `options` throws an `InvalidArgumentException` before the widget draws anything, even when the answer comes from `discovered` or an environment variable:

```text
No options declared for "Course". Provide at least one option.
```

<table>
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/widget-select.svg" alt="Select: Unicode + ANSI"></td>
    <td><img src=".util/assets/widget-select-no-ansi.svg" alt="Select: Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/widget-select-ascii.svg" alt="Select: ASCII + ANSI"></td>
    <td><img src=".util/assets/widget-select-ascii-no-ansi.svg" alt="Select: ASCII + No ANSI"></td>
  </tr>
</table>

### Multiselect

Pick any number of options from a list. Press <kbd>Space</kbd> to toggle an option and <kbd>Enter</kbd> to confirm. To pre-check options, pass a list of their keys as `default`. Pre-checking every option makes an opt-out list:

```php
$extras = Prompty::multiselect('Extras',
  options: [
    'bread' => 'Bread',
    'olives' => 'Olives',
    'herbs' => 'Herbs',
  ],
  default: ['bread', 'olives', 'herbs'],
  description: 'Anything served alongside.',
  help: 'Space to toggle, enter to confirm.',
);
```

As with [select](#select), an empty `options` throws an `InvalidArgumentException` that names the widget.

The answer lists the checked keys in option order. To answer a saved list in its own order, pass `order`:

```php
$extras = Prompty::multiselect('Extras',
  options: [
    'bread' => 'Bread',
    'olives' => 'Olives',
    'herbs' => 'Herbs',
  ],
  default: ['herbs', 'bread'],
  order: 'default',
);
```

Accepting that default gives `['herbs', 'bread']`. Pick the `order` that matches how you use the answer:

- `'options'`, the default, lists the keys in option order.
- `'default'` lists the `default` keys that are still checked, in `default` order. Newly checked keys follow in option order.
- `'selection'` lists the keys in the order they were checked. The `default` keys count as checked first, in `default` order. A key checked again moves to the end.
- A closure sorts the keys. It's a comparator, as for `usort()`: it receives 2 option keys and returns an `int`.

A comparator starts from the `'selection'` order, so keys it ranks equal stay in the order they were checked. To answer the checked keys sorted by their labels:

```php
$options = [
  'bread' => 'Bread',
  'olives' => 'Olives',
  'herbs' => 'Herbs',
];

$extras = Prompty::multiselect('Extras',
  options: $options,
  order: fn(string $a, string $b): int => strcmp($options[$a], $options[$b]),
);
```

Pass a function as a closure, such as `strcmp(...)`. A string `order` is read as 1 of the 3 names. A comparator that returns anything other than an `int` throws an `UnexpectedValueException`.

The same `order` applies to a [discovered](#environment-variable-discovery) value and an environment variable. With `'selection'`, those keep the order they give. An unknown `order` throws an `InvalidArgumentException` before the widget draws anything.

### Suggest

Free-form text input with suggestions. As you type, the options that contain the typed text show under the input, ignoring case. Press <kbd>↑</kbd> or <kbd>↓</kbd> to focus a suggestion and <kbd>Enter</kbd> to take it. With no suggestion focused, <kbd>Enter</kbd> takes the typed text as is, so the answer can be anything:

```php
$dish = Prompty::suggest('Dish',
  options: [
    'Pear Tart',
    'Apple Crumble',
    'Onion Soup',
    'Lentil Stew',
    'Herb Omelette',
    'Barley Salad',
    'Plum Compote',
    'Walnut Loaf',
  ],
  placeholder: 'Pear Tart',
  description: 'Pick from the menu, or type a dish that is not on it.',
  scroll: 4,
);
```

After typing `a` and pressing <kbd>↓</kbd>:

```text
◇  Dish
│  Pick from the menu, or type a dish that is not on it.
│
│  a█
│❯ Pear Tart
│  Apple Crumble
│  Barley Salad
│  Walnut Loaf
│
```

Pressing <kbd>↓</kbd> on the last suggestion, or <kbd>↑</kbd> on the first, unfocuses it and leaves the typed text as the answer. Typing or deleting a character also clears the focus.

`default`, `placeholder` and [discovered](#environment-variable-discovery) values work as they do for [text](#text). A discovered value can be any text, in `options` or not. To show a long list a few suggestions at a time, pass `scroll`, as described in [Long option lists](#long-option-lists).

An empty `options` list throws an `InvalidArgumentException`, as with [select](#select). The keys of `options` are ignored; the widget returns the suggestion text.

To compute the suggestions yourself, pass a closure as `options`. It receives the typed text and returns the suggestions to draw, which Prompty draws without filtering:

```php
$menu = ['Pear Tart', 'Plum Compote', 'Walnut Loaf'];

// Suggests only the dishes that start with the typed text.
$starts_with = fn(string $typed): array => array_values(array_filter(
  $menu,
  fn(string $dish): bool => stripos($dish, $typed) === 0,
));

$dish = Prompty::suggest('Dish', options: $starts_with);
```

`options` takes a closure, so pass a named function as `find_dishes(...)`. Prompty calls the closure each time the typed text changes, and never for a discovered value. A return value that isn't an array suggests nothing, and entries that aren't strings or numbers are skipped.

<table>
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/widget-multiselect.svg" alt="Multiselect: Unicode + ANSI"></td>
    <td><img src=".util/assets/widget-multiselect-no-ansi.svg" alt="Multiselect: Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/widget-multiselect-ascii.svg" alt="Multiselect: ASCII + ANSI"></td>
    <td><img src=".util/assets/widget-multiselect-ascii-no-ansi.svg" alt="Multiselect: ASCII + No ANSI"></td>
  </tr>
</table>

### Confirm

A yes/no toggle. Press the arrow keys or <kbd>Tab</kbd> to switch, <kbd>y</kbd> or <kbd>n</kbd> to pick directly and <kbd>Enter</kbd> to confirm:

```php
$send = Prompty::confirm('Send order?',
  description: 'Passes the order to the kitchen.',
);
```

<table>
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/widget-confirm.svg" alt="Confirm: Unicode + ANSI"></td>
    <td><img src=".util/assets/widget-confirm-no-ansi.svg" alt="Confirm: Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/widget-confirm-ascii.svg" alt="Confirm: ASCII + ANSI"></td>
    <td><img src=".util/assets/widget-confirm-ascii-no-ansi.svg" alt="Confirm: ASCII + No ANSI"></td>
  </tr>
</table>

### Pause

A message that waits for a key, such as "Press any key to continue" after a welcome screen. Any key continues and returns `true`:

```php
Prompty::pause('Press any key to start the order...',
  description: 'The kitchen opens at noon.',
);
```

To continue only on <kbd>Enter</kbd> and ignore every other key, pass `enter_only: TRUE`. After the key, a completed line replaces the message. To erase the message instead, pass `erase: TRUE`.

In [non-interactive mode](#non-interactive-mode), `pause()` returns `true` without reading a key. Inside a [flow](#flows), a pause is a keyed step that stores `true`.

## Notes

`note()` prints a message between your questions: an informational note, a success, a warning or an error. The type's symbol takes the place of the bar, and the message is drawn in the type's color:

```php
Prompty::note('The kitchen opens at 5pm.');
Prompty::note('Order sent to the kitchen.', type: 'success');
Prompty::note('The pear tart is running low.', type: 'warning');
Prompty::note("The oven did not reach temperature.\nCheck the fan and try again.", type: 'error');
```

```
●  The kitchen opens at 5pm.
│
✓  Order sent to the kitchen.
│
▲  The pear tart is running low.
│
✕  The oven did not reach temperature.
│  Check the fan and try again.
│
```

The type defaults to `info`. A newline starts another line, and a long line wraps with the bar and indent kept. Each type has a `note_<type>` symbol and a `note_<type>` color that you can [configure](#configuration). A note waits for no answer, so it also prints in [non-interactive mode](#non-interactive-mode).

Inside a [flow](#flows), a note is a step without a key. It sits in the tree where you declare it, among top-level steps or children, and adds nothing to the results. Pass `condition` to show it only for some answers:

```php
$results = Prompty::flow(fn(): array => [
  Prompty::note('The kitchen opens at 5pm.'),
  'course' => Prompty::select('Course', options: ['main' => 'Main', 'dessert' => 'Dessert']),
  Prompty::note('The pear tart is running low.', type: 'warning', condition: fn(array $r): bool => $r['course'] === 'dessert'),
  'send' => Prompty::confirm('Send order?'),
]);
```

A note called while the flow runs, such as from a task's action or the `outro` callable, prints straight away.

## Tasks

`task()` runs work between your questions, such as a download or a build step, and reports the result. While the action runs, the label shows a spinner. After the action returns, the label, the `hint` and a result line replace the spinner:

```php
$ready = Prompty::task('Preheating the oven',
  action: fn(): bool => $oven->preheat(180),
  hint: 'Fan oven, 180°C',
  success: 'Oven ready',
  failure: 'Oven did not reach temperature',
);
```

The finished task looks like this:

```
◆  Preheating the oven
│  Fan oven, 180°C
│  ✓ Oven ready
│
```

The action fails when it returns `false` and succeeds with any other value. `task()` returns the action's return value. `hint`, `success` and `failure` each take a string, or a callable that receives the return value:

```php
$degrees = Prompty::task('Preheating the oven',
  action: fn(): int => $oven->preheat(180),
  success: fn(int $degrees): string => sprintf('Oven at %d°C', $degrees),
);
```

An empty `success` or `failure` draws the `done` or `failed` label. When the action returns a list of strings, each string is drawn as a dim detail line under the label. An exception from the action clears the spinner and is rethrown.

The spinner animates in a forked child process, which needs the `pcntl` and `posix` extensions. Without them, it shows 1 still frame. It also shows a still frame while your script buffers output with `ob_start()` or writes to a callable [output target](#output-target).

A task redraws only on a live terminal: interactive, with ANSI on and output going to a terminal. Otherwise, the label is printed before the action runs and the result after it.

Inside a [flow](#flows), a task is a keyed step that takes `condition` and `children`. The flow stores `true` when the action succeeded and `false` when it failed:

```php
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'oven' => Prompty::task('Preheating the oven', action: fn(): bool => $oven->preheat(180)),
  'send' => Prompty::confirm('Send order?', condition: fn(array $r): bool => $r['oven']),
]);
```

### Streamed output

Some work prints its own output while it runs, such as a build or a package install. To draw that output under the task in place of a spinner, pass `stream: TRUE`:

```php
Prompty::task('Baking the tart',
  action: function (): bool {
    echo "Oven at 180°C\n";
    passthru('bake --timer 40m 2>&1', $code);

    return $code === 0;
  },
  success: 'Tart baked',
  failure: 'The tart did not bake',
  stream: TRUE,
);
```

On a live terminal, the last 5 lines stay on screen under the label, with a count of the earlier lines. A line still being written, such as a progress meter, updates in place:

```
◇  Baking the tart
│  ↑ 2 more
│    Rising 100%
│    Crust golden at the edges
│    Tray 2 of 2 in the top rack
│    Fan cycling, temperature held
│    Both trays out, resting on the rack
```

When the action succeeds, the window collapses into the result line:

```
◆  Baking the tart
│  ✓ Tart baked (7 lines)
│
```

When the action fails or throws, the window expands into every line it wrote, because the cause is usually among them. Without a live terminal, each line is printed once as it arrives.

Each line is drawn dim and indented on the tree's bar, with its colors kept. A long line wraps with its indent kept. In the window, it's cut to 1 row with an ellipsis. A line redrawn with carriage returns shows only its last state.

The stream captures output that reaches PHP's output layer:

- `echo` and `print`.
- Child processes run with `passthru()` or `system()`. Their stderr goes straight to the terminal, so add `2>&1` to the command to stream it too.
- Prompty's own output, such as `Prompty::output()`.

Output written straight to a file descriptor reaches the terminal unstyled. That covers `fwrite(STDOUT, ...)` and a process started with `proc_open()` that inherits the terminal.

## Flows

`flow()` groups widgets into a step-by-step wizard. Each array key names a step:

```php
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: [
    'starter' => 'Starter',
    'main' => 'Main',
    'dessert' => 'Dessert',
  ]),
  'extras' => Prompty::multiselect('Extras', options: [
    'bread' => 'Bread',
    'olives' => 'Olives',
    'herbs' => 'Herbs',
  ]),
  'send' => Prompty::confirm('Send order?'),
], intro: 'Compose an order', outro: 'Order sent!');
```

`flow()` returns the answers keyed by step, or `null` when the user cancels any step.

An input stream with nothing left to read counts as a cancellation. A flow run from a pipe, from `/dev/null` or in CI without a terminal returns `null` at the first prompt it can't read. To answer every step from its default instead, use [non-interactive mode](#non-interactive-mode).

`intro`, `outro` and `cancelled` each take a string or a callable. A callable receives the answers collected so far:

```php
$results = Prompty::flow(fn(): array => [ /* ... */ ],
  intro: 'Compose an order',
  outro: function (array $results): void {
    echo 'Dish: ' . $results['dish'] . "\n";
  },
  cancelled: 'Order cancelled.',
  numbering: TRUE, // Renders (1), (2), nested as (1.1), (1.2), etc.
);
```

### Step numbering

`numbering: TRUE` adds the step number to each label: `(1)` and `(2)` at the top level, `(2.1)` for a child of step 2. To show how many top-level steps are due as well, pass `numbering: 'total'`:

```php
$results = Prompty::flow(fn(): array => [ /* ... */ ], numbering: 'total');
```

```text
◆  Dish name (1/5)
│  pear tart
│
◆  Course (2/5)
│  Dessert
│
◆  Finishes (3/6)
│  Dusted
```

The total counts the completed top-level steps, the current step and each remaining top-level step whose `condition` passes against the answers so far. An answer can make a later step due or not due, so the next step shows the new total. A step keeps the total it was answered with. In the preceding output, the `Dessert` answer made `Finishes` due. Children keep the `(2.1)` form, and sections aren't counted.

Prompty tests each remaining condition before the answers it reads exist. While counting, a condition that raises a warning or throws counts as not due. When the flow reaches the step, Prompty calls the condition again, and any error surfaces there.

To change the format, set the `step_total` label. It's a `sprintf()` format that receives the step number and then the total, `(%d/%d)` by default:

```php
Prompty::flow(fn(): array => [ /* ... */ ], numbering: 'total', labels: ['step_total' => '[%d of %d]']);
```

A `step_total` format that needs more than 2 values, or holds an unknown conversion, throws an `InvalidArgumentException`. So does a `numbering` string other than `'total'`.

### Nested flows with conditions

Prompty draws a widget's `children` as an indented tree under it, joined by vertical bars. Any step can take a `condition`, which receives the answers collected so far. When the condition returns `false`, the flow skips the step:

```php
$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course',
    options: ['main' => 'Main', 'dessert' => 'Dessert'],
    children: [
      'method' => Prompty::select('Method',
        options: [
          'baked' => 'Baked',
          'poached' => 'Poached',
          'grilled' => 'Grilled',
        ],
        condition: fn($r): bool => ($r['course'] ?? '') === 'main',
      ),
      'finishes' => Prompty::multiselect('Finishes',
        options: [
          'glazed' => 'Glazed',
          'dusted' => 'Dusted',
          'piped' => 'Piped',
        ],
        condition: fn($r): bool => ($r['course'] ?? '') === 'dessert',
      ),
    ],
  ),
]);
```

### Steps built from earlier answers

`flow()` reads every widget's arguments when it builds the steps array, before the first prompt. To take a step's label, options, default or description from an earlier answer, wrap the widget in `Prompty::step()`. Its builder receives the answers collected so far and returns the widget call:

```php
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: [
    'main' => 'Main',
    'dessert' => 'Dessert',
  ]),
  'method' => Prompty::step(
    fn(array $r) => Prompty::select('Method',
      options: $r['course'] === 'main'
        ? ['grilled' => 'Grilled', 'poached' => 'Poached']
        : ['baked' => 'Baked', 'poached' => 'Poached'],
      description: 'How the ' . $r['dish'] . ' is cooked.',
    ),
    children: [
      'rest' => Prompty::confirm('Rest before serving?'),
    ],
  ),
  'note' => Prompty::step(
    fn(array $r) => Prompty::text('Kitchen note',
      default: $r['dish'] . ', ' . $r['method'],
    ),
    condition: fn(array $r): bool => $r['course'] === 'dessert',
  ),
]);
```

The builder runs when the flow reaches the step. A step that its `condition` skips never calls its builder. A built step is numbered, drawn in the tree and filled from its [environment variable](#environment-variable-discovery) like any other step.

Pass `condition` and `children` to `step()`, because `flow()` reads them before the builder runs. `step()` throws an exception in 2 cases:

- A builder returns a widget call with its own `condition` or `children`, or returns something other than a widget call. The flow throws an `UnexpectedValueException` when it reaches the step.
- `step()` runs outside the callable passed to `flow()`. It throws a `LogicException`.

### Section headings

A long flow can group its steps under headings. Add `Prompty::section()` to the steps array without a key. The flow draws its title in bold, with an optional dim description under it:

```php
$results = Prompty::flow(fn(): array => [
  Prompty::section('The dish',
    description: 'Name the dish and place it in the meal.',
  ),
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  'course' => Prompty::select('Course', options: [
    'main' => 'Main',
    'dessert' => 'Dessert',
  ]),

  Prompty::section('Service'),
  'table' => Prompty::select('Table', options: [
    '4' => 'Table 4',
    '7' => 'Table 7',
    '12' => 'Table 12',
  ]),
  'extras' => Prompty::step(
    fn(array $r) => Prompty::multiselect('Extras',
      options: $r['course'] === 'dessert'
        ? ['honey' => 'Honey', 'almonds' => 'Almonds']
        : ['bread' => 'Bread', 'olives' => 'Olives'],
    ),
  ),
], intro: 'Compose an order');
```

The headings sit in the tree among the steps:

```text
┌  Compose an order
│
├  The dish
│  Name the dish and place it in the meal.
│
◆  Dish name
│  pear tart
│
◆  Course
│  Dessert
│
├  Service
│
◇  Table
```

A section adds nothing to the results. Every step after it still receives every earlier answer in `condition` and `step()`, and numbering continues across sections. A newline in the title or the description starts a new line.

A section's group is the steps up to the next section. The flow draws a heading only when at least 1 step in its group renders. In a flow with no `intro`, the first heading opens the tree with `┌`. To hide a heading by itself, pass `condition`. The steps under it keep their own conditions.

`section()` throws an exception in 3 cases:

- It runs outside the callable passed to `flow()`. It throws a `LogicException`.
- Its title is blank. It throws an `InvalidArgumentException`.
- It has a string key or sits among a step's `children`. The flow throws an `InvalidArgumentException` before the first prompt.

### Step back to an earlier step

In a flow, press <kbd>Ctrl+U</kbd> to go back. The widget you're on disappears, and the previous step opens again with your answer filled in, as you gave it before any `transform`. Its children don't count as previous steps, so from a later sibling the key reopens the parent. From a step's first child, it reopens that parent. When the parent asked nothing, the key reopens the last of its children that did.

Answers you gave after the reopened step become those steps' defaults, so you only press <kbd>Enter</kbd> to move forward again. A step starts from its declared `default` instead in 2 cases:

- The new answer makes the step's `condition` false. The flow skips it, and its answer leaves the results.
- A `step()` builder returns a widget the old answer doesn't fit, such as a `select` whose options no longer include it.

Steps answered from a [discovered](#environment-variable-discovery) value or an environment variable asked nothing, so the key skips them. [`task()`](#tasks) and [`pause()`](#pause) steps ask nothing either. A task after the reopened step runs its action again when you move forward, and a task before it keeps its outcome. At a pause, the back key steps back instead of continuing. On the first asked step, and outside a flow, the key does nothing.

To use another key, pass `back_key` to [`Prompty::configure()`](#configuration) or `Prompty::flow()`, from `ctrl-a` to `ctrl-z`. `ctrl-c`, `ctrl-h`, `ctrl-i`, `ctrl-j` and `ctrl-m` already mean cancel, backspace, tab and enter, so they throw an `InvalidArgumentException`. To turn the key off, pass `''`:

```php
Prompty::configure(back_key: 'ctrl-b');
```

The flow erases the rows it drew since the reopened step. Rows that scrolled off the top of the terminal stay in the scrollback, so on a very long flow the reopened step draws at the top of the screen.

## Standalone mode

Called outside a flow, a widget sets up the terminal, asks its question and returns the answer:

```php
require_once 'Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$dish = Prompty::text('Dish name');
$course = Prompty::select('Course', options: [
  'starter' => 'Starter',
  'main' => 'Main',
]);
$send = Prompty::confirm('Send order?');

echo "Sending $dish for the $course course...\n";
```

A script can mix standalone widgets and flows:

```php
// Standalone widgets can sit between flows.
$dish = Prompty::flow(fn(): array => [/* step 1 */], intro: 'Step 1: The dish');
$note = Prompty::text('Kitchen note');
$extras = Prompty::flow(fn(): array => [/* step 2 */], intro: 'Step 2: Extras');
```

## Environment variable discovery

Inside a flow, each step can take its answer from an environment variable named after the step key, uppercased, with the `PROMPTY_` prefix. A widget called outside a flow reads no variables:

```bash
PROMPTY_DISH='pear tart' PROMPTY_COURSE=main php your-script.php
```

The flow draws `dish` and `course` as completed steps and prompts only for the rest.

To change the prefix for 1 flow, pass `env_prefix`. To change it for every flow, pass it to [`Prompty::configure()`](#configuration):

```php
// Reads KITCHEN_DISH, KITCHEN_COURSE, etc.
$results = Prompty::flow(fn(): array => [/* ... */], env_prefix: 'KITCHEN_');
```

A multiselect reads its variable as a comma-separated list, so `PROMPTY_EXTRAS=bread,olives` selects both options. The result is deduplicated and follows the widget's [`order`](#multiselect). With the default `'options'`, `PROMPTY_EXTRAS=olives,bread,olives` also gives `['bread', 'olives']`. A trailing comma is ignored.

Every widget also takes a `discovered:` argument for an answer the script already holds, such as a parsed `--extras=bread,olives` flag. A widget given a `discovered:` value skips its prompt, inside a flow or outside one. In a flow, the `discovered:` value wins over the environment variable.

A multiselect reads a `discovered:` string as a comma-separated list too, so a string from `getopt()` and a list give the same answer:

```php
Prompty::multiselect('Extras',
  options: $extras, discovered: 'bread,olives');
Prompty::multiselect('Extras',
  options: $extras, discovered: ['bread', 'olives']);
```

The list form takes each entry as is. Use it to select an option key that contains a comma.

A confirm matches its value against the `truthy` and `falsy` lists, after trimming and ignoring case. The defaults are `1`, `true` and `yes` for yes, and `0`, `false` and `no` for no, so `YES` and `True` both count as yes. A boolean `discovered:` value is taken as is.

A step key of `dish_name` reads `PROMPTY_DISH_NAME`. A name a shell can export holds only letters, digits and underscores. It doesn't start with a digit. Only a parent process can set a name such as `PROMPTY_DISH-NAME`, as in `env 'PROMPTY_DISH-NAME=x' php your-script.php`. `export` rejects it in bash, zsh and sh. Most `.env` parsers reject it too. `flow()` throws an `InvalidArgumentException` for such a step before the first prompt:

```text
Step "dish-name" is looked up as the environment variable "PROMPTY_DISH-NAME", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.
```

Prompty checks the full variable name, so an `env_prefix` that can't be exported fails the same way. With an empty prefix, a key that starts with a digit fails too.

Each key must also be unique across the whole flow, children included. `flow()` stores every answer in 1 flat array keyed by step, so a reused key would overwrite an earlier answer. `flow()` rejects a reused key before the first prompt:

```text
Step "dish" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.
```

### Discovered and default values must be valid answers

A discovered value must be an answer the widget accepts interactively:

- For `select` and `multiselect`, a key of `options`
- For `confirm`, a value in the `truthy` or `falsy` list
- For any widget with a [`validate`](#validation) callback, an answer the callback accepts

Any other value throws an `InvalidArgumentException` that names the widget, the value and the accepted values:

```text
Discovered value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.
```

The check covers both the `discovered:` argument and the `PROMPTY_*` variable, with or without a terminal. `text` accepts any value, and an empty one falls back to the [default or placeholder](#text).

`select` and `multiselect` check `default` against `options` too, and the message names the parameter:

```text
Default value "pudding" for "Course" is not a valid option. Available options: starter, main, dessert.
```

An empty default is always valid. `''` makes `select` focus the first option, and `[]` makes `multiselect` pre-check nothing. Both checks run when the widget starts, before it draws, so a mistyped key in a CI variable or a script fails at the widget that reads it.

### A blank value means no answer was supplied

A blank value is empty or holds only whitespace. An exported variable that was never filled gives one, and so does `DISH="$(some-command)"` when the command prints nothing. A widget with an answer that means "nothing supplied" takes that answer, and the other widgets reject a blank value:

| Widget        | A blank value resolves to                                       |
|---------------|-----------------------------------------------------------------|
| `text`        | The `default`, or the [`placeholder`](#text) when no default was seeded |
| `suggest`     | The `default`, or the [`placeholder`](#text) when no default was seeded |
| `multiselect` | An empty selection, `[]`                                        |
| `select`      | Rejected - one choice has no answer that means "nothing chosen" |
| `confirm`     | Rejected - yes and no are the only answers                      |

A blank value differs from an unset variable. An unset `PROMPTY_COURSE` opens the prompt. `PROMPTY_COURSE=` fails the same check as any other invalid value, and the message shows the trimmed value:

```text
Discovered value "" for "Course" is not a valid option. Available options: starter, main, dessert.
```

The rule covers both the `discovered:` argument and the `PROMPTY_*` variable.

## Non-interactive mode

A script run in CI or with a `--no-interaction` flag has nobody to answer its prompts. Pass `interactive: FALSE`, and every widget answers without reading a key:

```php
// For every widget and flow.
$opts = getopt('', ['no-interaction']);
Prompty::configure(interactive: !isset($opts['no-interaction']));

// For 1 flow.
$results = Prompty::flow(fn(): array => [/* ... */], interactive: FALSE);
```

Each widget takes the first answer it finds, in this order:

1. The `discovered:` argument
2. The `PROMPTY_*` variable, inside a flow
3. The `default`

The following table lists the answer each widget gives when its `default` is left empty:

| Widget        | Answer                   |
|---------------|--------------------------|
| `text`        | An empty string, `''`    |
| `select`      | The first option         |
| `multiselect` | An empty selection, `[]` |
| `suggest`     | An empty string, `''`    |
| `confirm`     | Yes, `true`              |

A placeholder is an example of what to type, so `text` and `suggest` never answer with it in this mode. A blank discovered value takes the `default`, or `''` when no default is set.

The checks in [Discovered and default values must be valid answers](#discovered-and-default-values-must-be-valid-answers) still run, so an invalid `default` throws an `InvalidArgumentException`.

Each widget still draws its completed lines, so the output logs every answer. To write that log elsewhere, set the [output target](#output-target). The help is left out, and Prompty leaves the terminal settings as they are.

Input is on by default, so a flow without a terminal still cancels at its first prompt.

## Descriptions, hints and help

Every widget takes a `description`, shown under the label. A description can span several lines:

```php
Prompty::text('Dish name',
  description: "Written on the order ticket and under\n"
    . '"Specials" on the board.',
);
```

A description draws dim and italic. It keeps the SGR sequences it carries, so bold, italic, underline and colors written as `\e[...m` still show. Prompty removes every other escape sequence and control character. After a sequence that switches an attribute off, such as `\e[0m` or `\e[22m`, the text returns to dim italic. Each line ends with a full reset, so styling stops at the end of its line:

```php
Prompty::select('Course',
  options: [
    'starter' => 'Starter',
    'main' => 'Main',
    'dessert' => 'Dessert',
  ],
  description: "Where the dish sits in the meal.\n"
    . "\e[1mDessert\e[22m goes out \e[4mafter the mains\e[24m.",
);
```

With ANSI output off or `NO_COLOR` set, Prompty removes the styling and draws the description as plain text.

`select` and `multiselect` also take `hints`, keyed by option. The focused option's hint shows under the list and can span several lines:

```php
Prompty::select('Method',
  options: [
    'baked' => 'Baked',
    'poached' => 'Poached',
    'grilled' => 'Grilled',
  ],
  hints: [
    'baked' => 'Dry heat, all the way through.',
    'poached' => "Gently, in barely moving liquid.\n"
      . 'Keeps delicate things whole.',
    'grilled' => 'Over the flame for colour. Fast, hot, and unforgiving.',
  ],
);
```

Every widget also takes `help`, a usage tip drawn dim under the input or the option list. Put how to answer in `help` and what the question means in `description`:

```php
Prompty::multiselect('Extras',
  options: [
    'bread' => 'Bread',
    'olives' => 'Olives',
    'herbs' => 'Herbs',
  ],
  description: 'Anything served alongside.',
  hints: [
    'bread' => 'Warm, cut at the table.',
    'olives' => 'Marinated, served in oil.',
    'herbs' => 'Picked the same morning.',
  ],
  help: 'Space to toggle, enter to confirm.',
);
```

The help shows only while the widget waits for an answer. The line drawn after an answer or a cancellation leaves it out. A widget that takes a [discovered](#environment-variable-discovery) value draws no help. On `select` and `multiselect`, the focused option's hint stays under that option, and the help follows the whole list:

```text
◇  Extras
│  Anything served alongside.
│
│❯ ◻ Bread
│    ↳ Warm, cut at the table.
│  ◻ Olives
│  ◻ Herbs
│
│  Space to toggle, enter to confirm.
│
```

Like a description, the help can span several lines.

### Key legend

To show the keys each widget accepts, pass `legend: TRUE` to [`Prompty::configure()`](#configuration). To show them for 1 flow, pass it to `Prompty::flow()`. Each widget then draws a dim legend after its `help`, so `help` can hold tips about the answer itself:

```text
◇  Extras
│
│❯ ◻ Bread
│  ◻ Olives
│  ◻ Herbs
│
│  ↑↓ move · space toggle · enter confirm
│
```

Each widget type lists its own keys:

| Widget        | Legend                                     |
|---------------|--------------------------------------------|
| `text`        | `enter confirm`                            |
| `select`      | `↑↓ move · enter confirm`                  |
| `multiselect` | `↑↓ move · space toggle · enter confirm`   |
| `suggest`     | `type to filter · ↑↓ move · enter confirm` |
| `confirm`     | `←→ toggle · y/n choose · enter confirm`   |
| `pause`       | `any key continue`                         |
| `pause` with `enter_only: TRUE` | `enter continue`         |

In ASCII mode, the arrows read `up/down` and `left/right`, and a `-` separates the keys. Like the help, the legend shows only while the widget waits for an answer.

In a flow, a step that can [step back](#step-back-to-an-earlier-step) adds the back key at the end, such as `↑↓ move · enter confirm · ctrl+u back`. The first asked step leaves it out.

To reword the legend, override its `legend_*` [labels](#available-options). The arrows come from the `legend_vertical` and `legend_horizontal` symbols, and the separator from `legend_separator`. A label set to `''` removes its key from the legend:

```php
Prompty::configure(legend: TRUE, labels: [
  'legend_enter' => 'enter to send',
  'legend_type' => '',
]);
```

## Long option lists

`select`, `multiselect` and `suggest` draw every option by default. For a long list, pass `scroll` with the number of options to show at once:

```php
$dish = Prompty::select('Menu',
  options: [
    'tart' => 'Pear Tart',
    'crumble' => 'Apple Crumble',
    'soup' => 'Onion Soup',
    'stew' => 'Lentil Stew',
    'omelette' => 'Herb Omelette',
    'salad' => 'Barley Salad',
    'compote' => 'Plum Compote',
    'loaf' => 'Walnut Loaf',
  ],
  default: 'compote',
  scroll: 3,
);
```

The window opens with the focused option in view. Moving the focus past the top or bottom row shifts the window by 1 option. The rows above and below the window count the options hidden on each side:

```text
◇  Menu
│
│  ↑ 4 more
│  ○ Herb Omelette
│  ○ Barley Salad
│❯ ● Plum Compote
│  ↓ 1 more
│
```

In a scrolled `multiselect`, focus starts on the first pre-checked option, so the window opens with it in view. A list drawn in full keeps focus on the first option. The answer line shows the chosen options, the same as in a list drawn in full.

In `suggest`, the window holds the matches for the typed text. It opens at the top of the matches each time the typed text changes.

A `scroll` of `0`, or one at or above the number of options, draws every option. A negative `scroll` throws an `InvalidArgumentException` before the widget draws anything:

```text
Scroll size -1 for "Menu" is not valid. Use 0 to show every option, or the number of options to show at once.
```

To set the size for every `select`, `multiselect` and `suggest`, pass `scroll` to [`Prompty::configure()`](#configuration). To set it for 1 flow, pass it to `Prompty::flow()`. A widget's own `scroll` takes precedence over both:

```php
Prompty::configure(scroll: 10);
```

## Long lines

Prompty wraps every line that's wider than the terminal. Labels, descriptions, options, hints, help, errors and answers break between words, and each continuation row starts with the same bar and indent as its first row. A word wider than the row breaks at the row's edge:

```text
◆  Extras
│  Sourdough bread, warm, cut at the table, Marinated olives, served in oil,
│  Herbs picked the same morning
│
```

Prompty measures the terminal before it draws each frame. It reads a positive `COLUMNS` environment variable first, then the width `stty size` reports, and falls back to 80 columns. Wide characters such as emoji count as 2 columns.

To wrap at a fixed width, pass `width` to [`Prompty::configure()`](#configuration). To set it for 1 flow, pass it to `Prompty::flow()`. A `width` of `0` detects the terminal width again, and a negative `width` throws an `InvalidArgumentException`:

```php
Prompty::configure(width: 100);
```

Lines you print with `Prompty::output()` keep your layout, so Prompty doesn't wrap them. To fit them to the terminal yourself, measure them with [`Prompty::width()` and `Prompty::terminalWidth()`](#style-your-own-lines).

## Tables and boxes

`Prompty::table()` and `Prompty::box()` print a summary or a panel in the same tree, symbols and colors as the widgets. Both print straight away and return nothing.

To print a table, pass its rows and optional header cells. A string in `rows` becomes a section row that spans every column:

```php
Prompty::table([
  'The dish',
  ['Dish name', 'Pear tart'],
  ['Course', 'Dessert'],
  'Service',
  ['Table', 'Table 12'],
  ['Kitchen note', 'No nuts, and the cream on the side rather than poured over the tart'],
], headers: ['Field', 'Answer']);
```

```text
│  ┌──────────────┬────────────────────────────────────────────────────────────┐
│  │ Field        │ Answer                                                     │
│  ├──────────────┴────────────────────────────────────────────────────────────┤
│  │ The dish                                                                  │
│  ├──────────────┬────────────────────────────────────────────────────────────┤
│  │ Dish name    │ Pear tart                                                  │
│  │ Course       │ Dessert                                                    │
│  ├──────────────┴────────────────────────────────────────────────────────────┤
│  │ Service                                                                   │
│  ├──────────────┬────────────────────────────────────────────────────────────┤
│  │ Table        │ Table 12                                                   │
│  │ Kitchen note │ No nuts, and the cream on the side rather than poured over │
│  │              │ the tart                                                   │
│  └──────────────┴────────────────────────────────────────────────────────────┘
│
```

Each column is as wide as its widest cell. When the table is wider than the terminal, the widest column shrinks 1 column at a time and its cells wrap. A cell is a string, an integer or a float, and a newline in a cell starts another line. A row with fewer cells than the others gets empty cells. Any other row or cell type throws an `InvalidArgumentException`. A table with no rows and no headers prints nothing.

To print a box, pass its content and an optional title. The box is as wide as its widest line, up to the terminal width:

```php
Prompty::box("Plate the tart, then:\n  ring the bell\n  call the table", title: 'Order ready');
```

```text
│  ┌───────────────────────┐
│  │ Order ready           │
│  │ ───────────           │
│  │ Plate the tart, then: │
│  │   ring the bell       │
│  │   call the table      │
│  └───────────────────────┘
│
```

A newline in the content starts another line. A line wider than the box wraps, and its continuation rows keep the line's leading spaces.

Borders are gray, and header cells, section rows and the box title are bold. Bold, underline and color sequences in a cell or a line stay as you wrote them. In ASCII mode the borders use `+`, `-` and `|`. To draw other borders, override the `box_*` [symbols](#available-options).

## Default values

Every widget takes a `default` that sets its starting state:

| Widget        | `default` type             | Effect                                     |
|---------------|----------------------------|--------------------------------------------|
| `text`        | `string`                   | Pre-fills the editable input buffer.       |
| `select`      | `string` (option key)      | Focuses that option instead of the first.  |
| `multiselect` | `list<int\|string>` (keys) | Pre-checks those options.                  |
| `suggest`     | `string`                   | Pre-fills the editable input buffer.       |
| `confirm`     | `bool`                     | Starts on Yes (`true`) or No (`false`).    |

A `discovered:` value or a `PROMPTY_*` variable wins over `default` and skips the prompt. The exception is a blank value for `text` or `suggest`, which takes the default. In [non-interactive mode](#non-interactive-mode), `default` is the answer when neither is set.

For `select` and `multiselect`, every key in `default` must be a key of `options`. See [Discovered and default values must be valid answers](#discovered-and-default-values-must-be-valid-answers).

## Transform answers

Every widget takes a `transform` callback that rewrites the answer before the widget returns it. Use it to normalize free input, such as a dish name typed in any case:

```php
// Typing '  PEAR tart ' returns 'Pear Tart'.
$dish = Prompty::text('Dish name',
  transform: fn(string $value): string => ucwords(strtolower(trim($value))),
);
```

The callback receives the answer alone, so a built-in function such as `trim(...)` works as a transform. Its return value is the answer: the completed line shows it, and a flow stores it, so a later step's `condition` receives it. A `discovered:` value, a `PROMPTY_*` variable and the `default` answered in [non-interactive mode](#non-interactive-mode) go through `transform` too. Canceling a widget skips the callback.

The callback returns the type its widget answers with:

| Widget        | Receives and returns       | Completed line shows                                                  |
|---------------|----------------------------|-----------------------------------------------------------------------|
| `text`        | `string`                   | The returned text, with control characters removed.                   |
| `select`      | `string` (option key)      | The option's label for a key of `options`, otherwise the string.      |
| `multiselect` | `list<string>` (keys)      | Each entry as for `select`, joined by commas. `None` for no entries.  |
| `suggest`     | `string`                   | The returned text, with control characters removed.                   |
| `confirm`     | `bool`                     | Yes or No.                                                            |

A return value of any other type throws an `UnexpectedValueException` that names the widget:

```text
Transform for "Dish name" returned null. A transform returns a string, the type the widget answers with.
```

## Validation

Every widget takes a `validate` callback that can reject an answer. The callback receives the answer and returns `null` for a valid answer, or an error message:

```php
$dish = Prompty::text('Dish name',
  placeholder: 'pear tart',
  validate: fn(string $dish): ?string => str_contains($dish, ' ')
    ? NULL : 'Name the whole dish, such as "pear tart".',
);
```

When the callback returns a message, the widget stays open and draws the message in red under the input. The message clears when the answer changes. Each <kbd>Enter</kbd> runs the callback again, and the widget completes only on an answer that passes.

The answer has the type the widget returns:

| Widget        | `validate` receives                                                     |
|---------------|-------------------------------------------------------------------------|
| `text`        | The string, including a `default` or `placeholder` taken on empty input |
| `select`      | The option key                                                          |
| `multiselect` | The list of option keys                                                 |
| `suggest`     | The focused suggestion, or the string as for `text`                     |
| `confirm`     | `true` or `false`                                                       |

On a widget with a [`transform`](#transform-answers), `validate` receives the transformed answer, so the rule checks what the widget returns.

Inside a flow, the second argument holds the answers collected so far, so a rule can depend on an earlier step. Outside a flow, it's an empty array:

```php
$results = Prompty::flow(fn(): array => [
  'course' => Prompty::select('Course', options: $courses),
  'extras' => Prompty::multiselect('Extras', options: $extras,
    validate: fn(array $extras, array $r): ?string =>
      $r['course'] === 'dessert' && in_array('olives', $extras, TRUE)
        ? 'Olives are not served with dessert.' : NULL,
  ),
]);
```

An empty message counts as valid, like `null`. Any other return type throws an `UnexpectedValueException`, so a callback written to return a boolean fails at the first answer:

```text
Validator for "Dish name" returned bool. A validator returns NULL for a valid answer or an error message string.
```

A `discovered:` value or a `PROMPTY_*` variable goes through `validate` too. With nobody to ask again, a rejected value throws an `InvalidArgumentException` before the widget draws:

```text
Discovered value "pear" for "Dish name" is not valid. Name the whole dish, such as "pear tart".
```

In [non-interactive mode](#non-interactive-mode), the `default` that answers a widget goes through `validate` the same way, and the message opens with `Default value`.

The message shows a `multiselect` answer comma-separated and a `confirm` answer as `true` or `false`. The checks against `options` and the `truthy` and `falsy` lists run first, so `validate` receives only answers the widget accepts.

### Required answers

To reject an empty answer, pass `required` to any widget. `TRUE` shows the `required` label, which reads `Required.` by default. A string shows that message instead:

```php
$guest = Prompty::text('Guest name',
  required: 'Enter a name for the ticket.',
);
$extras = Prompty::multiselect('Extras', options: $extras, required: TRUE);
```

An empty answer is a blank string, including one of only spaces, or a `multiselect` with nothing selected. A `text` or `suggest` answer that takes its `default` or `placeholder` isn't empty. `select` and `confirm` always have an answer, so `required` rejects only a `select` whose `transform` returns `''`.

The check runs after `transform` and before `validate`, so `validate` receives only answers that aren't empty. `required` also checks `discovered:` values, `PROMPTY_*` variables and the `default` in non-interactive mode. It throws for an empty one, as `validate` does for a rejected one:

```text
Discovered value "" for "Guest name" is not valid. Enter a name for the ticket.
```

## Unicode and ASCII

Prompty detects Unicode support from the locale in `LANG`, `LC_ALL` or `LC_CTYPE`. A UTF-8 locale gets symbols such as `◆`, `◇`, `│` and `●`. Any other locale gets their ASCII forms: `+`, `o`, `|` and `(*)`.

To force a mode for every widget and flow:

```php
Prompty::configure(unicode: FALSE); // Always ASCII
Prompty::configure(unicode: TRUE);  // Always Unicode
```

To force it for 1 flow:

```php
$results = Prompty::flow(fn(): array => [/* ... */], unicode: FALSE);
```

## ANSI colors

Prompty uses ANSI colors unless the [`NO_COLOR`](https://no-color.org/) environment variable is set to a non-empty value or `TERM` is `dumb`.

To force colors on or off for every widget and flow:

```php
Prompty::configure(ansi: FALSE); // Suppress all color codes
Prompty::configure(ansi: TRUE);  // Force ANSI colors
```

To force them for 1 flow:

```php
$results = Prompty::flow(fn(): array => [/* ... */], ansi: FALSE);
```

### Style your own lines

To print lines of your own between widgets, style and measure them with the helpers Prompty uses for its own layout. Each one follows the `ansi` setting and `NO_COLOR`. To line up a column of styled dishes:

```php
$dishes = ['Pear tart' => 'Table 4', 'Plum compote' => 'Table 12'];
$lines = [];

foreach ($dishes as $dish => $table) {
  $name = Prompty::style($dish, 'bold');
  $pad = str_repeat(' ', 14 - Prompty::width($name));
  $lines[] = $name . $pad . Prompty::style($table, 'dim');
}

Prompty::output($lines);
```

The class has 4 helpers:

- `Prompty::style($text, ...$styles)` wraps each line of `$text` in the named styles and ends it with a reset, so the next line starts unstyled. Inside a line, a nested `style()` call ends its own style and the outer style continues. With ANSI off, it returns `$text` unchanged.
- `Prompty::strip($text)` removes ANSI escape sequences.
- `Prompty::width($text)` returns the columns the widest line of `$text` takes. Escape sequences count as 0, and wide characters such as emoji count as 2.
- `Prompty::terminalWidth()` returns the width Prompty wraps its lines to, as described in [Long lines](#long-lines).

`style()` takes every key of the `colors` [configuration](#available-options) except `reset`: `dim`, `dim_italic`, `cyan`, `green`, `red`, `gray`, `bold`, `white`, `yellow`, `blue`, `italic` and `underline`. A `colors` override changes the codes `style()` writes. An unknown name throws an `InvalidArgumentException` that lists the available names.

### Display modes

`unicode` and `ansi` combine into 4 display modes. The following table shows a flat flow in each mode:

<table align="center">
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/flow.svg" width="100%" alt="Unicode + ANSI"></td>
    <td><img src=".util/assets/flow-no-ansi.svg" width="100%" alt="Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/flow-ascii.svg" width="100%" alt="ASCII + ANSI"></td>
    <td><img src=".util/assets/flow-ascii-no-ansi.svg" width="100%" alt="ASCII + No ANSI"></td>
  </tr>
</table>

The following table shows a nested flow in each mode:

<table align="center">
  <tr>
    <td></td>
    <td align="center"><strong>ANSI</strong></td>
    <td align="center"><strong>No ANSI</strong></td>
  </tr>
  <tr>
    <td align="right"><strong>Unicode</strong></td>
    <td><img src=".util/assets/flow-nested.svg" width="100%" alt="Nested Unicode + ANSI"></td>
    <td><img src=".util/assets/flow-nested-no-ansi.svg" width="100%" alt="Nested Unicode + No ANSI"></td>
  </tr>
  <tr>
    <td align="right"><strong>ASCII</strong></td>
    <td><img src=".util/assets/flow-nested-ascii.svg" width="100%" alt="Nested ASCII + ANSI"></td>
    <td><img src=".util/assets/flow-nested-ascii-no-ansi.svg" width="100%" alt="Nested ASCII + No ANSI"></td>
  </tr>
</table>

## Configuration

`Prompty::configure()` sets options for the rest of the process. `Prompty::flow()` takes the same options as named arguments and merges them over the global ones for that flow. Every option is optional, so pass only what you change.

A per-flow option lasts until the flow returns, whether the flow completes, is canceled or throws. The next flow gets the global settings again.

The following example sets options globally, then for 1 flow:

```php
// Global.
Prompty::configure(
  unicode: FALSE,
  env_prefix: 'KITCHEN_',
  labels: ['yes' => 'Yep', 'no' => 'Nope'],
);

// Per-flow (merges on top).
$results = Prompty::flow(fn(): array => [/* ... */],
  env_prefix: 'ORDER_',
  truthy: ['1', 'true', 'yes', 'on'],
  falsy: ['0', 'false', 'no', 'off'],
);
```

### Available options

The following table lists every option and its default:

| Parameter         | Type                    | Description                                                                   |
|-------------------|-------------------------|-------------------------------------------------------------------------------|
| `unicode`         | `bool`                  | Forces Unicode or ASCII symbols. Detected from the locale by default.         |
| `ansi`            | `bool`                  | Forces ANSI colors on or off. On unless `NO_COLOR` or `TERM=dumb` is set.     |
| `env_prefix`      | `string`                | Prefix for environment variable discovery. Default `PROMPTY_`.                |
| `labels`          | `array<string, string>` | UI labels. The keys follow this table.                                        |
| `truthy`          | `list<string>`          | Values a confirm reads as yes. Default `1`, `true`, `yes`.                    |
| `falsy`           | `list<string>`          | Values a confirm reads as no. Default `0`, `false`, `no`.                     |
| `interactive`     | `bool`                  | If false, every widget answers without reading input. Default `true`.         |
| `scroll`          | `int`                   | Options select, multiselect and suggest show at once. Default `0` shows all.   |
| `placeholder_fallback` | `bool`             | If false, text and suggest answer an empty submit with `''`. Default `true`.  |
| `width`           | `int`                   | Columns that lines wrap to. Default `0` detects the terminal width.            |
| `legend`          | `bool`                  | If true, each widget draws the keys it accepts. Default `false`.              |
| `back_key`        | `string`                | Key that steps back in a flow, `ctrl-a` to `ctrl-z`. Default `ctrl-u`; `''` turns it off. |
| `symbols_unicode` | `array<string, string>` | Unicode symbol overrides.                                                     |
| `symbols_ascii`   | `array<string, string>` | ASCII symbol overrides.                                                       |
| `colors`          | `array<string, string>` | ANSI color escape overrides.                                                  |
| `spacing`         | `array<string, string>` | Indentation strings.                                                          |
| `output`          | `resource\|callable`    | Where Prompty writes: a writable stream or a callable. Default `echo`.        |

The map-valued options take these keys:

- `symbols_unicode`, `symbols_ascii`: `bar`, `completed`, `active`, `intro`, `outro`, `section`, `pointer`, `radio_on`, `radio_off`, `check_on`, `check_off`, `hint_arrow`, `error`, `scroll_up`, `scroll_down`, `success`, `spinner`, `ellipsis`, `box_top_left`, `box_top_right`, `box_bottom_left`, `box_bottom_right`, `box_horizontal`, `box_vertical`, `box_cross`, `box_top_tee`, `box_bottom_tee`, `box_left_tee`, `box_right_tee`, `legend_vertical`, `legend_horizontal`, `legend_separator`, `note_info`, `note_success`, `note_warning`, `note_error`. Each character of `spinner` is 1 frame.
- `colors`: `reset`, `dim`, `dim_italic`, `cyan`, `green`, `red`, `gray`, `bold`, `white`, `yellow`, `blue`, `italic`, `underline`, `note_info`, `note_success`, `note_warning`, `note_error`
- `spacing`: `indent`, `hint_indent`, `hint_cont`
- `labels`: `yes`, `no`, `cancelled`, `none`, `separator`, `more`, `required`, `done`, `failed`, `line`, `lines`, `step_total`, `legend_move`, `legend_toggle`, `legend_enter`, `legend_space`, `legend_type`, `legend_yes_no`, `legend_any_key`, `legend_continue`, `legend_back`

### Unknown keys are rejected

The map-valued options accept only the keys in the preceding list, which `Prompty::config()` also returns. An unknown key throws an `InvalidArgumentException` that lists the valid keys, so a misspelling fails at the call:

```text
Configuration key "bogus" for "labels" is not valid. Available keys: yes, no, cancelled, none, separator, more.
```

`truthy` and `falsy` must each hold at least 1 value, and neither accepts a blank value. Values match after trimming, so a blank entry would accept the empty value that `confirm` otherwise rejects.

`configure()` validates every argument before it applies any, so a rejected call changes nothing:

```php
// Throws, and neither the labels nor the prefix are changed.
Prompty::configure(
  labels: ['yes' => 'Yep'],
  env_prefix: 'KITCHEN_',
  truthy: [],
);
```

An empty `env_prefix` makes discovery read the uppercased step key alone. A step named `path`, `home` or `user` then reads `PATH`, `HOME` or `USER`, so keep a prefix unless you mean that.

### Output target

Prompty writes with `echo` by default. To send its output to your own writer, pass `output` a writable stream or a callable. The callable receives each chunk as a string:

```php
// A stream.
Prompty::configure(output: STDERR);

// A callable.
$buffer = '';
Prompty::configure(output: function (string $chunk) use (&$buffer): void {
  $buffer .= $chunk;
});
```

The target receives everything Prompty writes: widget frames, redraws, the cursor show and hide sequences, `intro()`, `outro()`, `table()`, `box()` and `output()`. A redraw moves the cursor up with escape sequences, so an interactive widget draws correctly only when those bytes reach the terminal.

A filename, a closed stream and a read-only stream each throw an `InvalidArgumentException`. To write to standard output again, pass `STDOUT`.

### Other methods

Besides the 5 widgets, `flow()` and `configure()`, the class has these methods:

| Method                       | Returns  | Description                                                     |
|------------------------------|----------|-----------------------------------------------------------------|
| `Prompty::results()`         | `array`  | The answers collected by the last flow.                         |
| `Prompty::config()`          | `array`  | The resolved configuration, including detected values.          |
| `Prompty::version()`         | `string` | The release version, or `development` when running from source. |
| `Prompty::intro($message)`   | `void`   | Prints an intro banner outside `flow()`.                        |
| `Prompty::outro($message)`   | `void`   | Prints an outro banner outside `flow()`.                        |
| `Prompty::output($lines)`    | `int`    | Prints an array of lines, 1 per line. Returns the count.        |
| `Prompty::style($text, ...)` | `string` | Styles each line of the text with the named colors.             |
| `Prompty::strip($text)`      | `string` | Removes ANSI escape sequences from the text.                    |
| `Prompty::width($text)`      | `int`    | Display width of the widest line of the text.                   |
| `Prompty::terminalWidth()`   | `int`    | The terminal width Prompty wraps its lines to.                  |
| `Prompty::table($rows)`      | `void`   | Prints a [table](#tables-and-boxes), with optional `headers`.   |
| `Prompty::box($content)`     | `void`   | Prints a [box](#tables-and-boxes), with an optional `title`.    |

The following example reads the answers 2 ways and prints a banner:

```php
// flow() returns the collected answers.
$results = Prompty::flow(fn(): array => [/* ... */]);

// Or read them later.
$results = Prompty::results();

// Print a banner yourself. Passing a callable as flow()'s outro suppresses
// the built-in one, so call this from inside it to keep the banner.
Prompty::outro('Order sent to the kitchen!');
```

## Test harness

`PromptyTestTrait` drives widgets from PHPUnit. It feeds keystrokes from a memory stream and captures the output, so tests run without a terminal.

To assert on what a flow returns, use `promptyRun()`:

```php
use AlexSkrypnyk\Prompty\Prompty;
use AlexSkrypnyk\Prompty\PromptyTestTrait;
use PHPUnit\Framework\TestCase;

require_once 'Prompty.php';
require_once 'PromptyTestTrait.php';

class MyFlowTest extends TestCase {
  use PromptyTestTrait;

  protected function tearDown(): void {
    $this->promptyTearDown();
    parent::tearDown();
  }

  public function testMyFlow(): void {
    $keystrokes = $this->promptyKeys(
      'plum compote', self::KEY_ENTER,  // type dish + submit
      self::KEY_DOWN, self::KEY_ENTER,  // select second option
    );

    $run = $this->promptyRun(fn(): mixed => Prompty::flow(fn(): array => [
      'dish' => Prompty::text('Dish name'),
      'course' => Prompty::select('Course', options: [
        'starter' => 'Starter',
        'main' => 'Main',
      ]),
    ]), $keystrokes);

    $this->assertSame('plum compote', $run['result']['dish']);
    $this->assertSame('main', $run['result']['course']);
    $this->assertStringContainsString('Dish name', $run['output']);
  }
}
```

To test a whole script, use `promptyRunScript()`. Leave the script's [kill switch](#starter-script) variable unset so the script stops before its real work, then read the answers from `Prompty::results()`:

```php
public function testMyScript(): void {
  $keystrokes = $this->promptyKeys(
    'plum compote', self::KEY_ENTER,
    self::KEY_DOWN, self::KEY_ENTER,
  );

  $this->promptyRunScript(function (): void {
    require 'my-script.php';
  }, $keystrokes);

  $results = Prompty::results();
  $this->assertSame('plum compote', $results['dish']);
  $this->assertSame('main', $results['course']);
}
```

Both helpers return an array whose `output` key holds the output with ANSI codes stripped. `promptyRun()` adds a `result` key with the callback's return value.

Under both helpers, widgets draw ASCII symbols and wrap lines at 80 columns, so the output doesn't depend on the terminal that runs the tests. To change either in `promptyRun()`, pass a configuration array such as `['unicode' => TRUE, 'width' => 120]` as its third argument.

A widget that reads past the last keystroke cancels, so a test with too few keystrokes gets `null` instead of hanging.

### Test a script that configures Prompty

`promptyRun()` and `promptyRunScript()` each install a new instance, so they drop any settings passed to `Prompty::configure()` before the helper runs. To keep those settings, let the script configure Prompty first, then pass the keystrokes to `promptyInput()`:

```php
public function testTakeOrder(): void {
  // Calls Prompty::configure() with the script's own settings.
  Kitchen::bootstrap();

  $this->promptyInput($this->promptyKeys(
    'pear tart', self::KEY_ENTER,
    self::KEY_DOWN, self::KEY_ENTER,
  ));

  $order = Kitchen::takeOrder();

  $this->assertSame('pear tart', $order['dish']);
}
```

`promptyInput()` sets the keystrokes as the input of the current instance and changes no other setting. With no instance yet, it creates one that draws ASCII symbols and wraps lines at 80 columns. A later `Prompty::configure()` call changes that same instance. Calling `promptyInput()` again replaces the keystrokes.

`promptyInput()` captures no output. Widgets write to the script's `output` target, or with `echo` when the script sets none. `promptyTearDown()` closes the keystroke stream and resets the instance.

### Test a rejected answer

A widget whose [`validate`](#validation) callback rejects an answer asks again. In a test, it then reads past the last keystroke and cancels, so the test gets `null`. To assert on the message instead, call `promptyThrowOnInvalid()` first:

```php
public function testRejectsSingleWord(): void {
  $this->promptyThrowOnInvalid();

  $this->expectException(PromptyValidationException::class);
  $this->expectExceptionMessage('Name the whole dish');

  $this->promptyRun(fn(): mixed => Prompty::text('Dish name',
    validate: fn(string $dish): ?string => str_contains($dish, ' ')
      ? NULL : 'Name the whole dish, such as "pear tart".',
  ), $this->promptyKeys('pear', self::KEY_ENTER));
}
```

The first rejected answer throws `AlexSkrypnyk\Prompty\PromptyValidationException`, which `PromptyTestTrait.php` defines. Its `label`, `value` and `reason` properties hold the widget label, the rejected answer and the callback's message. Its message reads `Value "pear" for "Dish name" is not valid.`, followed by the callback's message.

A rejected `discovered:` value or [non-interactive](#non-interactive-mode) default throws the same class. The class extends `InvalidArgumentException`, which those rejections throw without the switch.

The switch lasts across `promptyRun()` calls until `promptyTearDown()` resets it. To switch it off sooner, call `promptyThrowOnInvalid(FALSE)`.

### Available key constants

Pass these constants to `promptyKeys()`:

| Constant         | Key                  |
|------------------|----------------------|
| `KEY_ENTER`      | <kbd>Enter</kbd>     |
| `KEY_SPACE`      | <kbd>Space</kbd>     |
| `KEY_BACKSPACE`  | <kbd>Backspace</kbd> |
| `KEY_TAB`        | <kbd>Tab</kbd>       |
| `KEY_ESCAPE`     | <kbd>Escape</kbd>    |
| `KEY_CTRL_C`     | <kbd>Ctrl+C</kbd>    |
| `KEY_CTRL_U`     | <kbd>Ctrl+U</kbd>    |
| `KEY_UP`         | <kbd>↑</kbd>         |
| `KEY_DOWN`       | <kbd>↓</kbd>         |
| `KEY_LEFT`       | <kbd>←</kbd>         |
| `KEY_RIGHT`      | <kbd>→</kbd>         |

## Starter script

[`starter.php`](starter.php) is a template for your own scripts. It follows the *kill switch* pattern, which keeps a script testable. The script does 3 things in order:

1. Runs the flow.
2. Returns if the flow returns `null`.
3. Returns unless `SHOULD_PROCEED` is set, so tests stop before the real work.

The following excerpt shows the pattern:

```php
$results = Prompty::flow(fn(): array => [
  'dish' => Prompty::text('Dish name', placeholder: 'pear tart'),
  // ...
], intro: 'Compose an order');

if ($results === NULL) {
  return; // Cancelled, so there is nothing to act on.
}

if (!getenv('SHOULD_PROCEED')) {
  return; // Tests stop here.
}

// Real work below - only runs in production.
echo 'Dish: ' . $results['dish'] . "\n";
```

To start a script, copy `starter.php` under a new name and replace its steps with your own.

## Embedding

[`embed.php`](embed.php) minifies `Prompty.php` and writes it into your script, so the script ships as 1 file with no `require_once`. Minifying strips every comment except the license header, drops the static analysis tags from that header, drops blank lines and puts the class body on 1 line.

Download `embed.php` with `Prompty.php` from the [latest release](https://github.com/AlexSkrypnyk/prompty/releases/latest) assets.

### Setup

In your script, put `// @embed-start` and `// @embed-end` markers around the `require_once` line and the `use` import:

```php
<?php

declare(strict_types=1);

// phpcs:disable
// @embed-start
require_once __DIR__ . '/Prompty.php';
use AlexSkrypnyk\Prompty\Prompty;
// @embed-end
// phpcs:enable

$dish = Prompty::text('Dish name');
```

Keep the `use` import inside the markers. The embedded class has no namespace, so an import left outside them collides with the class name and the embedded script fails to compile.

### Usage

To embed in place, run `embed.php` on your script:

```bash
php embed.php my-script.php
```

To write the result to a separate file and leave your script unchanged, add an output path:

```bash
php embed.php my-script.php dist/my-script.php
```

Before it embeds, `embed.php` writes the code it inserts between the markers to `Prompty.min.payload` beside the output script. With `--compact`, the file is `Prompty.compact.payload`. Open the file to read the code your script will carry. Each release ships a file of the same name, so you can compare the 2 hashes. To skip writing the file, pass `--no-payload-file`.

After embedding, `embed.php` prints the size of each file involved:

```text
Sizes:
  Target script before    1,799 bytes     50 lines  my-script.php
  Prompty source        216,660 bytes  6,439 lines  /path/to/Prompty.php
  Prompty embeddable    105,488 bytes     22 lines  Prompty.min.payload (48.7% of the source)
  Target script after   107,278 bytes     68 lines  my-script.php (+105,479 bytes)
```

To silence coding-standard warnings on the minified code, wrap the markers in `// phpcs:disable` and `// phpcs:enable`.

[`starter.php`](starter.php) has the markers in place. [`playground/05-flows-embed.php`](playground/05-flows-embed.php) is the demo the embedder is tested against, and [`CONTRIBUTING.md`](CONTRIBUTING.md#the-embedded-demo) shows how to build and compare its embedded copy.

### Options

`embed.php` takes these options:

| Option            | Description                                                                             |
|-------------------|-----------------------------------------------------------------------------------------|
| `--source <path>` | Sets the class file to embed. Defaults to `Prompty.php` beside `embed.php`.             |
| `--compact`       | Strips optional whitespace, shortens internal names and renames local variables and parameters. |
| `--stdout`        | Writes the processed class as a standalone PHP file instead of embedding it.            |
| `--payload`       | Writes only the code that embedding puts between the markers.                           |
| `--check`         | Checks an embedded script. See [Verify an embedded script](#verify-an-embedded-script). |
| `--sums <path>`   | With `--check`, sets the `SHA256SUMS` file of the release that the start marker names. |
| `--no-killswitch` | Skips adding the kill switch block and the verification run.                            |
| `--no-verify`     | Skips the verification run but still adds the kill switch block.                        |
| `--no-payload-file` | Skips writing the embedded code to a `.payload` file beside the output script.        |

For example:

```bash
php embed.php --compact my-script.php
php embed.php --source /path/to/Prompty.php my-script.php
php embed.php --stdout Prompty.min.php
```

With `--stdout` or `--payload`, `embed.php` takes an output file path in place of a target script. The release workflow uses `--stdout` to build `Prompty.min.php` and `Prompty.compact.php`, and `--payload` to build the 2 `.payload` assets.

### Re-embedding

To update an embedded script to a newer Prompty, replace `Prompty.php` with the new version and run `embed.php` again. `embed.php` replaces the code between the markers and keeps everything outside them:

```bash
php embed.php my-script.php
```

If the new `Prompty.php` is in another directory, point `--source` at it:

```bash
php embed.php --source /path/to/new/Prompty.php my-script.php
```

### Kill switch

`embed.php` looks for `if (!getenv('SHOULD_PROCEED'))` in your script. If the line is missing, `embed.php` adds this block after the embed region:

```php
// Kill switch - stop here when running under tests.
// In production, set SHOULD_PROCEED=1 to continue past this point.
if (!getenv('SHOULD_PROCEED')) {
  return;
}
```

The added block sits before your prompts, so the script asks nothing and does nothing unless `SHOULD_PROCEED` is set. For tests that answer the prompts and stop before the real work, write the kill switch yourself after the flow, as [`starter.php`](starter.php) does.

To skip adding the block, pass `--no-killswitch`:

```bash
php embed.php --no-killswitch my-script.php
```

### Verification run

After writing the file, `embed.php` checks it with `php -l` and then runs it to confirm the embedded class works. In a terminal, you answer any prompts that come before the kill switch. Without a terminal, `embed.php` pipes in a few newlines and discards the output.

The run happens only when the file has a kill switch, which stops the run before the real work unless `SHOULD_PROCEED` is set. The run inherits your environment, so with `SHOULD_PROCEED` set, unset it or pass `--no-verify`. `--no-killswitch` skips the run too, even when the script has its own kill switch.

To add the kill switch but skip the run, pass `--no-verify`. Use it in build steps, CI jobs and scripts that regenerate an embedded file:

```bash
php embed.php --no-verify my-script.php
```

### Verify an embedded script

Embedded code sits on 1 line that's too long to review in a diff, so `embed.php` records what it embedded and can check it later. Embedding writes 5 attributes into the start marker line:

```php
// @embed-start class=Prompty version=1.2.0 mode=min sha256=f9a6c847...d278f embedder=46232669...8a747
```

| Attribute  | Value                                                                                                 |
|------------|-------------------------------------------------------------------------------------------------------|
| `class`    | The name of the embedded class.                                                                       |
| `version`  | The class's `VERSION` constant: a release tag, `development` for an unreleased copy, or `unknown` when the class has none. |
| `mode`     | `min`, or `compact` for an embed made with `--compact`.                                               |
| `sha256`   | The SHA-256 of the code between the start marker line and the end marker line.                       |
| `embedder` | The SHA-256 of the `embed.php` that did the embedding.                                                |

Re-embedding rewrites the whole line. The same `Prompty.php`, `embed.php` and mode always produce the same code. An embed made from a release's `Prompty.php` and `embed.php` therefore matches that release's `.payload` asset byte for byte.

To check that the embedded code still matches its marker:

```bash
php embed.php --check my-script.php
```

To also check the code and the embedder against a release, pass the `SHA256SUMS` of the release the marker names. `--check` trusts that file as given, so verify its signature first, as in [Verifying a download](#verifying-a-download):

```bash
php embed.php --check my-script.php --sums SHA256SUMS
```

`--check` prints `OK` or `FAIL` for each comparison, and exits with `1` when any comparison fails. Without `--sums`, it also prints `NOT VERIFIED AGAINST A RELEASE`. It reads Windows line endings as Unix ones.

After every embed into a script, `embed.php` prints 3 snippets for the script's repository. It fills in the script's path and the repository's GitHub remote, and lists any placeholder it couldn't fill. The `--stdout`, `--payload` and `--check` modes print no snippets. For `my-script.php` outside a git repository, the snippets look like this.

A README section that shows your users how to verify the script:

````markdown
## Verifying my-script.php

`my-script.php` runs on your machine with your permissions, so check that the file you downloaded is the one this repository built. Each release includes a build attestation for `my-script.php`. GitHub Actions signs it and ties it to the commit and workflow run that built the file.

To verify the file with the [GitHub CLI](https://cli.github.com):

```bash
curl -fsSL https://github.com/<owner>/<repo>/releases/latest/download/my-script.php -o my-script.php
gh attestation verify my-script.php --repo <owner>/<repo>
php my-script.php
```

`gh attestation verify` fails when the file changed after the build or came from another repository's workflows.

To verify the file without the GitHub CLI, compare it with the checksum attached to the release:

```bash
curl -fsSL https://github.com/<owner>/<repo>/releases/latest/download/my-script.php -o my-script.php
curl -fsSL https://github.com/<owner>/<repo>/releases/latest/download/my-script.php.sha256 -o my-script.php.sha256
sha256sum -c my-script.php.sha256    # macOS: shasum -a 256 -c my-script.php.sha256
```

When you download `my-script.php` from a host other than GitHub, tampering with that host alone fails the check, because the checksum comes from GitHub. Both checks trust this repository: anyone who controls it can rebuild the attestation and the checksum along with the file.

### The embedded Prompty library

`my-script.php` embeds [Prompty](https://github.com/AlexSkrypnyk/prompty) as minified code that's too long to review in a diff. On every pull request, CI compares that code with the signed Prompty release named in its `// @embed-start` marker. The check fails when 1 byte differs.
````

A workflow, `.github/workflows/publish-my-script.yml`, that attaches the script, its checksum and a build attestation to each release:

```yaml
name: Publish my-script.php

on:
  release:
    types:
      - published

permissions:
  contents: read

jobs:
  publish:
    name: Publish my-script.php
    runs-on: ubuntu-latest

    permissions:
      contents: write     # Upload to the release.
      id-token: write     # Sign the attestation.
      attestations: write # Record the attestation.

    steps:
      - name: Checkout code
        uses: actions/checkout@v7 # Pin to a commit SHA.
        with:
          persist-credentials: false

      # Hash the file exactly as users download it. If a build step changes
      # it after checkout, run that step first and copy its output instead.
      - name: Checksum my-script.php
        run: |
          mkdir -p .publish
          cp my-script.php .publish/my-script.php
          cd .publish
          sha256sum my-script.php > my-script.php.sha256

      - name: Attest my-script.php
        uses: actions/attest-build-provenance@v4 # Pin to a commit SHA.
        with:
          subject-path: .publish/my-script.php

      - name: Upload my-script.php to the release
        env:
          GH_TOKEN: ${{ github.token }}
          TAG: ${{ github.event.release.tag_name }}
        run: gh release upload "${TAG}" .publish/my-script.php .publish/my-script.php.sha256 --clobber
```

A job for the workflow that tests your pull requests. It downloads the Prompty release named in the marker, verifies it, checks the script against it and confirms that a changed copy fails:

```yaml
  check-embedded-prompty:
    name: Check embedded Prompty
    runs-on: ubuntu-latest

    permissions:
      contents: read

    steps:
      - name: Checkout code
        uses: actions/checkout@v7 # Pin to a commit SHA.
        with:
          persist-credentials: false

      - name: Setup PHP
        uses: shivammathur/setup-php@v2 # Pin to a commit SHA.
        with:
          php-version: '8.3'

      - name: Download the Prompty release named in the marker
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          version="$(sed -n 's/.*@embed-start.* version=\([^ ]*\).*/\1/p' my-script.php)"
          gh release download "${version}" --repo AlexSkrypnyk/prompty --dir .prompty --pattern embed.php --pattern SHA256SUMS --pattern SHA256SUMS.asc

      - name: Verify the Prompty release
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          gh attestation verify .prompty/embed.php --repo AlexSkrypnyk/prompty
          curl -fsSL https://raw.githubusercontent.com/AlexSkrypnyk/prompty/main/PUBLIC_KEY.asc | gpg --batch --import
          gpg --batch --status-fd 1 --verify .prompty/SHA256SUMS.asc .prompty/SHA256SUMS | grep -q '^\[GNUPG:\] VALIDSIG 755E824E80F849135F5F4043E71EDB25C4F6D89D '
          cd .prompty
          sha256sum --ignore-missing -c SHA256SUMS

      - name: Check the embedded code
        run: php .prompty/embed.php --check my-script.php --sums .prompty/SHA256SUMS

      # Proves the check above fails on a changed copy, so it can't pass
      # without checking anything.
      - name: Check that a changed copy fails
        run: |
          cp my-script.php .prompty/changed.php
          sed -i '/@embed-start/{n;s/^/ /;}' .prompty/changed.php
          ! php .prompty/embed.php --check .prompty/changed.php --sums .prompty/SHA256SUMS
```

The job passes only for a script embedded from a release. Re-embed from the release you want to pin, and commit the new marker line with it.

### For AI agents

<details>
<summary>Embed into starter script</summary>

```bash
BASE=https://github.com/AlexSkrypnyk/prompty/releases/latest/download

# Download Prompty.php, embed.php, and the starter template.
curl -LO $BASE/Prompty.php
curl -LO $BASE/embed.php
curl -LO $BASE/starter.php

# Rename the starter to your script name.
mv starter.php my-script.php

# Embed the minified class into the script.
php embed.php my-script.php
```

</details>

<details>
<summary>Embed into custom script</summary>

```bash
BASE=https://github.com/AlexSkrypnyk/prompty/releases/latest/download

# Download Prompty.php and embed.php.
curl -LO $BASE/Prompty.php
curl -LO $BASE/embed.php

# Add markers in your script around the require_once line and the import.
# Both must sit inside the markers - the embedded class has no namespace,
# so an import left outside them collides with it:
#
#   // phpcs:disable
#   // @embed-start
#   require_once __DIR__ . '/Prompty.php';
#   use AlexSkrypnyk\Prompty\Prompty;
#   // @embed-end
#   // phpcs:enable

# Embed the minified class into the script.
php embed.php my-script.php
```

</details>

<details>
<summary>Update Prompty</summary>

```bash
BASE=https://github.com/AlexSkrypnyk/prompty/releases/latest/download

# Download the new Prompty.php, overwriting the old one.
curl -LO $BASE/Prompty.php

# Re-run the embedder. Code outside the markers is preserved.
php embed.php my-script.php

# Or use --source if the new Prompty.php is in a different location.
php embed.php --source /path/to/new/Prompty.php my-script.php
```

</details>

## Contributing

For local setup, the lint and test commands, the playground scripts and the release process, see [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Security

To report a vulnerability or verify a downloaded release, see [`SECURITY.md`](SECURITY.md).

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
