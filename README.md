<p align="center">
  <a href="" rel="noopener">
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

<table align="center">
  <tr>
    <td align="center"><strong>Flat flow</strong></td>
    <td align="center"><strong>Nested flow</strong></td>
  </tr>
  <tr>
    <td><img src=".util/assets/flow.svg" width="100%" alt="Flat flow"></td>
    <td><img src=".util/assets/flow-nested.svg" width="100%" alt="Nested flow"></td>
  </tr>
</table>

## Features

- 📦 [**Zero dependencies**](#zero-dependencies) — drop `Prompty.php` into your project or embed into your script
- 🧩 [**Widgets**](#widgets) — `text`, `select`, `multiselect`, `confirm`
- 🔀 [**Flows**](#flows) — group prompts into a wizard with intro/outro, numbering, and cancellation
- 🌳 [**Nested flows**](#nested-flows-with-conditions) — conditional children rendered as a tree
- ⚡ [**Standalone mode**](#standalone-mode) — use any widget on its own, outside of a flow
- 🌍 [**Environment variable discovery**](#environment-variable-discovery) — auto-fill answers from env vars
- 💬 [**Descriptions and hints**](#descriptions-and-hints) — contextual help below labels and per-option
- ✨ [**Unicode and ASCII**](#unicode-and-ascii) — auto-detects terminal support or force a mode
- ⚙️ [**Configuration**](#configuration) — symbols, colors, spacing, labels, env prefix, truthy/falsy values
- 🧪 [**Test harness**](#test-harness) — `PromptyTestTrait` injects keystrokes for PHPUnit testing
- 🚀 [**Starter script**](#starter-script) — [`starter.php`](starter.php) as a template for your own scripts

## Zero dependencies

Prompty is a single PHP file with no dependencies. There are two ways to use it:

### Simple scripts — just copy the file

Download `Prompty.php` and `require_once` it directly:

```bash
curl -O https://raw.githubusercontent.com/alexskrypnyk/prompty/main/Prompty.php
```

```php
require_once __DIR__ . '/Prompty.php';

$name = Prompty::text('Project name');
```

Or embed into your own script by copy-pasting the class definition.

> [!NOTE]
> A helper to minify and embed the class into your script will be provided soon.

### Composer projects — require as a package

For larger projects with Composer:

```bash
composer require alexskrypnyk/prompty
```

Then use it like any other class — the autoloader handles the rest:

```php
$name = Prompty::text('Project name');
```

For testing, `PromptyTestTrait` is included in the package. If you're using the
copy approach, grab it separately:

```bash
curl -O https://raw.githubusercontent.com/alexskrypnyk/prompty/main/PromptyTestTrait.php
```

## Widgets

Four widget types cover the most common prompt patterns. Each returns the user's
answer, or `null` if they cancel (Escape or Ctrl+C).

### Text

Free-form text input with an optional placeholder.

```php
$name = Prompty::text('Project name',
  placeholder: 'my-app',
  description: "Used as the directory name\nand the package name.",
);
```

<img src=".util/assets/widget-text.svg" alt="Text widget">

### Select

Single-choice from a list. Arrow keys to navigate, Enter to confirm.

```php
$framework = Prompty::select('Framework',
  options: ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'],
  description: 'The UI layer for your project.',
  hints: [
    'react' => 'Component-based library by Meta.',
    'vue' => "Gentle learning curve.\nSingle-file components.",
    'svelte' => 'Compile-time framework — no virtual DOM.',
  ],
);
```

<img src=".util/assets/widget-select.svg" alt="Select widget">

### Multiselect

Multiple-choice from a list. Space to toggle, Enter to confirm.

```php
$features = Prompty::multiselect('Features',
  options: ['ts' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier'],
  description: "Space to toggle, enter to confirm.",
);
```

<img src=".util/assets/widget-multiselect.svg" alt="Multiselect widget">

### Confirm

Yes/No toggle. Arrow keys or `y`/`n` to switch, Enter to confirm.

```php
$install = Prompty::confirm('Install dependencies?',
  description: 'Runs npm install after scaffolding.',
);
```

<img src=".util/assets/widget-confirm.svg" alt="Confirm widget">

## Flows

Group widgets into a step-by-step wizard.

```php
$results = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app'),
  'framework' => Prompty::select('Framework', options: [
    'react' => 'React',
    'vue' => 'Vue',
    'svelte' => 'Svelte',
  ]),
  'features' => Prompty::multiselect('Features', options: [
    'ts' => 'TypeScript',
    'eslint' => 'ESLint',
    'prettier' => 'Prettier',
  ]),
  'install' => Prompty::confirm('Install dependencies?'),
], intro: 'Create a new project', outro: 'Project created!');
```

Flows support intro, outro, and cancellation messages — as strings or callables:

```php
$results = Prompty::flow(fn(): array => [ /* ... */ ],
  intro: 'Welcome',
  outro: function (array $results): void {
    echo 'Created: ' . $results['name'] . "\n";
  },
  cancelled: 'Cancelled.',
  numbering: TRUE, // Renders (1), (2), nested as (1.1), (1.2), etc.
);
```

### Nested flows with conditions

Widgets accept `children` and `condition` to build tree-structured flows.
Children render as an indented tree with bar connectors. Conditions receive
the collected results so far and skip the step when they return `false`.

```php
$results = Prompty::flow(fn(): array => [
  'type' => Prompty::select('Project type',
    options: ['app' => 'Application', 'lib' => 'Library'],
    children: [
      'framework' => Prompty::select('Framework',
        options: ['next' => 'Next.js', 'nuxt' => 'Nuxt'],
        condition: fn($r): bool => ($r['type'] ?? '') === 'app',
      ),
      'format' => Prompty::multiselect('Output formats',
        options: ['esm' => 'ESM', 'cjs' => 'CommonJS'],
        condition: fn($r): bool => ($r['type'] ?? '') === 'lib',
      ),
    ],
  ),
]);
```

## Standalone mode

Widgets work outside of flows too. Call any widget directly and it handles
TTY setup/teardown internally, returning the answer immediately:

```php
require_once 'Prompty.php';

$name = Prompty::text('Project name');
$framework = Prompty::select('Framework', options: ['react' => 'React', 'vue' => 'Vue']);
$install = Prompty::confirm('Install?');

echo "Setting up $name with $framework...\n";
```

You can mix standalone widgets with flows in the same script:

```php
$basics = Prompty::flow(fn(): array => [/* step 1 */], intro: 'Step 1');
$tagline = Prompty::text('Add a tagline?'); // standalone between flows
$options = Prompty::flow(fn(): array => [/* step 2 */], intro: 'Step 2');
```

## Environment variable discovery

Flows auto-discover answers from environment variables. The key name is
uppercased and prefixed with `PROMPTY_` (configurable):

```bash
PROMPTY_NAME=my-app PROMPTY_FRAMEWORK=vue php your-script.php
```

This pre-fills `name` and `framework` without prompting the user. The flow
renders the discovered values as completed steps and moves on.

Configure the prefix per-flow or globally:

```php
$results = Prompty::flow(fn(): array => [/* ... */], env_prefix: 'MYAPP_');
// Reads MYAPP_NAME, MYAPP_FRAMEWORK, etc.
```

For confirm widgets, env values are interpreted using configurable truthy/falsy
lists (default: `1`/`true`/`yes` and `0`/`false`/`no`).

## Descriptions and hints

Every widget accepts a `description` — multi-line text rendered below the label:

```php
Prompty::text('Project name',
  description: "Used as the directory name\nand the package name.",
);
```

Select and multiselect widgets also accept `hints` — per-option text that
updates as the user navigates:

```php
Prompty::select('Framework',
  options: ['react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte'],
  hints: [
    'react' => 'Component-based library by Meta.',
    'vue' => "Gentle learning curve.\nSingle-file components.",
    'svelte' => 'Compile-time framework — no virtual DOM.',
  ],
);
```

Hints support multi-line text. They appear below the option list and change
as the user moves between options.

## Unicode and ASCII

Prompty auto-detects Unicode support from the terminal locale (`LANG`,
`LC_ALL`, `LC_CTYPE`). When Unicode is available, it uses symbols like `◆`,
`◇`, `│`, `●`. Otherwise, it falls back to ASCII: `+`, `o`, `|`, `(*)`.

Force a mode:

```php
Prompty::configure(unicode: FALSE); // Always ASCII
Prompty::configure(unicode: TRUE);  // Always Unicode
```

Or per-flow:

```php
$results = Prompty::flow(fn(): array => [/* ... */], unicode: FALSE);
```

<table align="center">
  <tr>
    <td align="center"><strong>Flat flow (ASCII)</strong></td>
    <td align="center"><strong>Nested flow (ASCII)</strong></td>
  </tr>
  <tr>
    <td><img src=".util/assets/flow-ascii.svg" width="100%" alt="Flat ASCII flow"></td>
    <td><img src=".util/assets/flow-nested-ascii.svg" width="100%" alt="Nested ASCII flow"></td>
  </tr>
</table>

## Configuration

Configure globally with `Prompty::configure()` or per-flow via named arguments
on `Prompty::flow()`. All parameters are optional — pass only what you want to
override. Per-flow config merges on top of global.

```php
// Global.
Prompty::configure(
  unicode: FALSE,
  env_prefix: 'MYAPP_',
  labels: ['yes' => 'Yep', 'no' => 'Nope'],
);

// Per-flow (merges on top).
$results = Prompty::flow(fn(): array => [/* ... */],
  env_prefix: 'SETUP_',
  truthy: ['1', 'true', 'yes', 'on'],
  falsy: ['0', 'false', 'no', 'off'],
);
```

### Available options

| Parameter         | Type                    | Description                                              |
|-------------------|-------------------------|----------------------------------------------------------|
| `unicode`         | `bool`                  | Force Unicode or ASCII symbols                           |
| `env_prefix`      | `string`                | Prefix for env var discovery                             |
| `labels`          | `array<string, string>` | UI labels: `yes`, `no`, `cancelled`, `none`, `separator` |
| `truthy`          | `list<string>`          | Strings treated as `true` for confirm env values         |
| `falsy`           | `list<string>`          | Strings treated as `false` for confirm env values        |
| `symbols_unicode` | `array<string, string>` | Unicode symbol overrides                                 |
| `symbols_ascii`   | `array<string, string>` | ASCII symbol overrides                                   |
| `colors`          | `array<string, string>` | ANSI color escape overrides                              |
| `spacing`         | `array<string, string>` | Indentation strings                                      |

### Reading results

```php
// flow() returns the collected answers.
$results = Prompty::flow(fn(): array => [/* ... */]);

// Or read them later.
$results = Prompty::results();

// Read the full config.
$config = Prompty::config();
```

## Test harness

Prompty ships with `PromptyTestTrait` for PHPUnit. It injects keystrokes into
a memory stream and captures terminal output — no real TTY needed.

```php
use PHPUnit\Framework\TestCase;

require_once 'Prompty.php';
require_once 'PromptyTestTrait.php';

class MyTest extends TestCase {
  use PromptyTestTrait;

  protected function tearDown(): void {
    $this->promptyTearDown();
    parent::tearDown();
  }

  public function testMyFlow(): void {
    $keystrokes = $this->promptyKeys(
      'my-project', self::KEY_ENTER,   // type name + submit
      self::KEY_DOWN, self::KEY_ENTER,  // select second option
      self::KEY_SPACE, self::KEY_ENTER, // toggle first + submit
      self::KEY_ENTER,                  // confirm default
    );

    $this->promptyRunScript(function (): void {
      require 'my-installer.php';
    }, $keystrokes);

    $results = \Prompty::results();
    $this->assertSame('my-project', $results['name']);
    $this->assertSame('vue', $results['framework']);
  }
}
```

### Available key constants

| Constant         | Key          |
|------------------|--------------|
| `KEY_ENTER`      | Enter        |
| `KEY_SPACE`      | Space        |
| `KEY_BACKSPACE`  | Backspace    |
| `KEY_TAB`        | Tab          |
| `KEY_ESCAPE`     | Escape       |
| `KEY_CTRL_C`     | Ctrl+C       |
| `KEY_UP`         | Arrow up     |
| `KEY_DOWN`       | Arrow down   |
| `KEY_LEFT`       | Arrow left   |
| `KEY_RIGHT`      | Arrow right  |

## Starter script

[`starter.php`](starter.php) is a ready-to-use template for your own scripts.
It demonstrates the recommended "kill switch" pattern for testable flows — the
script collects answers, then checks an env var before doing real work:

```php
$results = Prompty::flow(fn(): array => [
  'name' => Prompty::text('Project name', placeholder: 'my-app'),
  // ...
], intro: 'Setup');

if (!getenv('SHOULD_PROCEED')) {
  return; // Tests stop here.
}

// Real work below — only runs in production.
echo 'Creating ' . $results['name'] . "\n";
```

Copy `starter.php`, rename it, and replace the steps with your own.

## Maintenance

```bash
composer install
composer lint
composer test
```

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
