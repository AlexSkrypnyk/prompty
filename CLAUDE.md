# CLAUDE.md

## Project Overview

Prompty is a zero-dependency, single-file PHP CLI prompt library.
Namespace: `AlexSkrypnyk\Prompty`. Key files: `Prompty.php` (library),
`PromptyTestTrait.php` (test helper).

## Architecture

- **Singleton** - `static::$instance`, never `new Prompty()`.
- **Dual-mode widgets** - `text`, `select`, `multiselect`, `confirm` return
  closures in flow mode, values in standalone mode.
- **Typed config properties** - `cfgUnicode`, `cfgSymbols`, `cfgColors`,
  `cfgSpacing`, `cfgLabels`, `cfgEnvPrefix`, `cfgTruthy`, `cfgFalsy`, etc.
  Configured via `Prompty::configure(...)` named arguments.
- **Flow definition** - `flow()` takes a callable (not array) so `$inFlow`
  is set before widget evaluation.
- **Tree rendering** - nested children use bar connectors; `open` array
  tracks continuing siblings.
- **Widget signatures** - all four widgets share one parameter order, and any
  new widget follows it:

  ```
  label, [options], default, [placeholder], [description], [hints],
  discovered, condition, children, ctx
  ```

  Bracketed parameters appear only where they apply: `options` and `hints` on
  `select`/`multiselect`, `placeholder` on `text`. The trailing four are
  common to every widget. Callers pass `label` positionally and everything
  else by name, so the order matters to the signature, not to call sites.

### Playground

- `playground/widgets.php` - standalone widget demos.
- `playground/flow.php` - linear flow.
- `playground/flow-nested.php` - nested flow with conditionals.

## Demo content

All example content - playground demos, `starter.php`, README snippets, recorded
assets - uses the kitchen-order theme defined in
`.claude/demo_content_reference.md`, and contains no software, product or
technology references. Take vocabulary from that file rather than inventing it;
if a demo needs a concept it does not cover, add it there first.

## Commands

```bash
composer lint          # PHPCS + PHPStan + Rector dry-run
composer lint-fix      # Rector + PHPCBF
composer test          # PHPUnit (no coverage)
composer test-coverage # PHPUnit with coverage
composer reset         # rm vendor/, reinstall
```

## Code Quality

1. **PHPCS** - Drupal standard + strict types (`phpcs.xml`)
2. **PHPStan** - Level 9 (`phpstan.neon`)
3. **Rector** - PHP 8.2/8.3 modernization (`rector.php`)

## Conventions

- `strict_types=1` in all PHP files
- Single quotes, `snake_case` locals, `camelCase` methods/properties
- Files end with newline

## Testing

- Namespace: `AlexSkrypnyk\Prompty\Tests\Unit\Prompty`
- Base class: `PromptyTestCase` (uses `PromptyTestTrait`)
- PHPUnit 11: `#[CoversClass()]`, `#[DataProvider()]`
- Unit tests in `tests/phpunit/Unit/Prompty/`
- Functional tests in `tests/phpunit/Functional/` - these run `embed.php` and
  `starter.php` in real subprocesses
- Autoload: classmap (no PSR-4)
