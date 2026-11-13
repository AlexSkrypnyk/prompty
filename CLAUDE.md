# CLAUDE.md

## Project Overview

Prompty is a zero-dependency, single-file PHP CLI prompt library.
Namespace: `AlexSkrypnyk\Prompty`. Key files: `Prompty.php` (library: the
`Prompty` class, the `PromptyStepInterface` interface and the `PromptyFlow`
builder), `PromptyTestTrait.php` (test helper).

## Architecture

- **Singleton** - `static::$instance`, never `new Prompty()`.
- **Widgets** - `text`, `select`, `multiselect`, `suggest`, `confirm`,
  `pause` and `task` always run and return their answer. A flow passes them
  `ctx`.
- **Tasks** - `task()` redraws only when `isLive()`; tests set the `isTerminal`
  property to force it. A flow calls a task's action with the answers and
  stores its outcome as a bool. An action's exception draws the failure line,
  then is rethrown. The spinner runs in a `pcntl_fork()` child. `stream: TRUE` captures output with
  `ob_start()` at chunk size 1; a buffer handler can't echo, so `write()`
  collects drawn output in `$streamBuffer` for the handler to return.
- **Typed config properties** - `cfgUseUnicode`, `cfgSymbols`, `cfgColors`,
  `cfgSpacing`, `cfgLabels`, `cfgEnvPrefix`, `cfgTruthy`, `cfgFalsy`, etc.
  Configured via `Prompty::configure(...)` named arguments.
- **Flow builder** - `Prompty::flow()` returns a `PromptyFlow`. Its widget
  methods take the step key first, then the static widget's arguments; a
  `\Closure` given for a value argument runs with the answers when the step
  is reached. `when()`, `children()` and `end()` shape the tree, and `run()`
  takes the per-flow options. The builder can't reach `Prompty`'s protected
  members, so `flow()` hands it a closure that calls `runFlow()`.
- **Step arrays** - the builder compiles to lists of step arrays:
  `__key`/`__call`/`__children`/`__condition`/`__numbered` for a step,
  `__section` and `__note` for the keyless kinds. `walkFlow()` walks them.
- **Unnumbered steps** - `PromptyFlow::unnumbered()` sets `__numbered` to
  `FALSE`. The step takes no number, `dueStepCount()` leaves it out of the
  total, and its children walk with numbering off.
- **Skipped steps** - a step skipped by its condition answers `NULL`, and so
  does every step under it (`skipSteps()`). Step back keeps `NULL`s out of
  `$previous`, so a replay never mistakes one for a cancellation.
- **Failed flows** - `runFlow()` catches an exception from the walk, draws the
  `failed` banner when the flow has printed rows, and rethrows.
- **Step providers** - `PromptyFlow::add()` takes a `PromptyStepInterface`;
  its `widget()` and `arguments()` are asked when the step is reached.
- **Sections** - `walkFlow()` draws a section only when a step in its group
  (the steps up to the next section) renders, and stores no result. Top
  level only.
- **Notes** - the static `note()` prints. A flow note is a `PromptyFlow::note()`
  entry that `drawNote()` draws at its depth; it stores no result. A closure
  message or type resolves against the answers in `drawNote()`, so `run()`
  checks only a string type up front.
- **Tree rendering** - nested children use bar connectors; `open` array
  tracks continuing siblings.
- **Widget signatures** - all 5 widgets share one parameter order, and any
  new widget follows it:

  ```
  label, [options], default, [placeholder], description, [hints], [scroll],
  [order], [cancel_on_no], help, discovered, transform, required, validate,
  ctx
  ```

  Bracketed parameters appear only where they apply: `options` and `scroll`
  on `select`/`multiselect`/`suggest`, `hints` on `select`/`multiselect`,
  `placeholder` on `text`/`suggest`, `order` on `multiselect`,
  `cancel_on_no` on `confirm`. `help`,
  `discovered`, `transform`, `required`, `validate` and `ctx` are common to
  every widget.
  `required` sits between `transform` and `validate` because it runs between
  them. `pause` has no answer to resolve, so it takes only `label`,
  `description`, `help`, its own `enter_only` and `erase`, then `ctx`.
  Callers pass `label` positionally and everything else by name, so the order
  matters to the signature, not to call sites. Each `PromptyFlow` widget
  method takes `key` first, then the same parameters without `ctx`.

### Playground

`playground/` holds runnable demos named `NN-topic-name.php`, numbered in
README order: `01`-`02` widgets, `03` notes, tables and boxes, `04` tasks,
`05` flows, `06` answer sources, validation and transforms, `07` long lists
and the legend, `08` configuration. Related demos share 1 script whose
sections run top to bottom; a script never branches on a flag to pick what
it shows. `CONTRIBUTING.md` lists what each one shows and which accept
`--no-unicode` / `--no-ansi`.

### Method order

`Prompty.php` groups its methods by concern: lifecycle, then the public API
(configuration, flows, widgets, output, text helpers), then protected helpers
(config validation, options, answers, key input, step back, terminal, tasks,
writing, styling, wrapping, chrome, renderers, flow walking). Add a method to
its group. A boolean method about state is named `is*`.

## Demo content

All example content - playground demos, `starter.php`, README snippets, recorded
assets - uses the kitchen-order theme defined in
`.claude/demo_content_reference.md`, and contains no software, product or
technology references. Take vocabulary from that file rather than inventing it;
if a demo needs a concept it does not cover, add it there first.

## Commands

```bash
composer lint          # PHPCS + PHPStan + Rector dry-run + embed check
composer lint-fix      # Rector + PHPCBF
composer test          # PHPUnit (no coverage)
composer test-coverage # PHPUnit with coverage
composer reset         # rm vendor/, vendor-bin/, composer.lock
```

## Code Quality

1. **PHPCS** - Drupal standard + strict types (`phpcs.xml`)
2. **PHPStan** - Level 9 (`phpstan.neon`)
3. **Rector** - PHP 8.3 modernization (`rector.php`)

## Conventions

- `strict_types=1` in all PHP files
- Single quotes, `snake_case` locals, `camelCase` methods/properties
- A boolean property or variable is named as the yes/no question it answers, with the prefix that reads naturally: `is` (`$isTerminal`, `$is_live`), `has` (`$hasDrawn`, `$has_killswitch`), `was` (`$was_called`), `should` (`$should_proceed`), `use` / `show` for settings (`$cfgUseAnsi`, `$cfgShowLegend`). Public named arguments (`unicode`, `legend`, `erase`, ...) keep their option names.
- Files end with newline

## Testing

- Namespace: `AlexSkrypnyk\Prompty\Tests\Unit\Prompty`
- Base class: `PromptyTestCase` (uses `PromptyTestTrait`)
- PHPUnit 12: `#[CoversClass()]`, `#[DataProvider()]`
- Unit tests in `tests/phpunit/Unit/Prompty/`
- Functional tests in `tests/phpunit/Functional/` - these run `embed.php`, `starter.php` and the scripts in `tests/phpunit/Fixtures/` in real subprocesses, the fixtures in a pseudo-terminal
- Autoload: classmap (no PSR-4)

## Updating from the template

This project was generated from the
[Scaffold](https://github.com/AlexSkrypnyk/scaffold) template and can pull the
template's latest infrastructure (CI workflows, linting and test configuration,
docs) at any time. When you are asked to "update scaffold", fetch the updater
skill into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-scaffold
   ```

2. Download the skill:

   ```bash
   curl -fsSL https://raw.githubusercontent.com/AlexSkrypnyk/scaffold/main/.scaffold/skills/update-consumer-scaffold/SKILL.md -o .claude/skills/update-consumer-scaffold/SKILL.md
   ```

   The `-f` flag makes curl fail on an HTTP error instead of writing the error
   body into `SKILL.md`. If the download fails, stop; do not invoke the skill.

3. Invoke the `update-consumer-scaffold` skill and follow its steps.

The skill directory is fetched on demand and is git-ignored; it is not committed
to the project.
