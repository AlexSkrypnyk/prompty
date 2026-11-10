# CLAUDE.md

## Project Overview

Prompty is a zero-dependency, single-file PHP CLI prompt library.
Namespace: `AlexSkrypnyk\Prompty`. Key files: `Prompty.php` (library),
`PromptyTestTrait.php` (test helper).

## Architecture

- **Singleton** - `static::$instance`, never `new Prompty()`.
- **Dual-mode widgets** - `text`, `select`, `multiselect`, `suggest`,
  `confirm`, `pause` and `task` return closures (step arrays when given a
  condition or children) while a flow is being defined, and values otherwise.
- **Tasks** - `task()` redraws only when `isLive()`; tests set the `terminal`
  property to force it. A flow stores a task's outcome as a bool. The spinner
  runs in a `pcntl_fork()` child. `stream: TRUE` captures output with
  `ob_start()` at chunk size 1; a buffer handler can't echo, so `write()`
  collects drawn output in `$streamBuffer` for the handler to return.
- **Typed config properties** - `cfgUnicode`, `cfgSymbols`, `cfgColors`,
  `cfgSpacing`, `cfgLabels`, `cfgEnvPrefix`, `cfgTruthy`, `cfgFalsy`, etc.
  Configured via `Prompty::configure(...)` named arguments.
- **Flow definition** - `flow()` takes a callable (not array) so `$inFlow`
  is set before widget evaluation.
- **Built steps** - `step()` returns a step array whose `__call` runs a
  builder with the answers so far and then the widget closure it returns.
  `condition` and `children` live on `step()`, so they are known up front.
- **Sections** - `section()` returns a keyless step array carrying
  `__section`. `walkFlow()` draws it only when a step in its group (the steps
  up to the next section) renders, and stores no result. Top level only.
- **Notes** - `note()` returns a keyless step array carrying `__note` only
  while `$defining` is set, which `flow()` sets only around the steps
  callable; at any other time, including while the flow walks, it prints.
  `drawNote()` draws it at its depth and stores no result.
- **Tree rendering** - nested children use bar connectors; `open` array
  tracks continuing siblings.
- **Widget signatures** - all 5 widgets share one parameter order, and any
  new widget follows it:

  ```
  label, [options], default, [placeholder], description, [hints], [scroll],
  [order], help, discovered, transform, required, validate, condition,
  children, ctx
  ```

  Bracketed parameters appear only where they apply: `options` and `scroll`
  on `select`/`multiselect`/`suggest`, `hints` on `select`/`multiselect`,
  `placeholder` on `text`/`suggest`, `order` on `multiselect`. `help`,
  `discovered`, `transform`, `required`, `validate`, `condition`, `children`
  and `ctx` are common to every widget.
  `required` sits between `transform` and `validate` because it runs between
  them. `pause` has no answer to resolve, so it takes only `label`,
  `description`, `help`, its own `enter_only` and `erase`, then `condition`,
  `children` and `ctx`. Callers pass `label` positionally and
  everything else by name, so the order matters to the signature, not to call
  sites.

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
