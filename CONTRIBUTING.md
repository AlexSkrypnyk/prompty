# Contributing

Thanks for taking an interest. Bug reports, fixes and ideas are all welcome - open an [issue](https://github.com/AlexSkrypnyk/prompty/issues) or a pull request.

## Requirements

PHP 8.2 or newer, and Composer. CI runs the suite on PHP 8.2, 8.3, 8.4 and 8.5, against both current and lowest resolvable dependencies, so a change that only works on the newest PHP won't get through.

`composer.lock` isn't committed, so a fresh clone resolves dependencies from scratch.

## Setup

```bash
git clone https://github.com/AlexSkrypnyk/prompty.git
cd prompty
composer install
```

## Commands

| Command                  | What it does                                          |
|--------------------------|-------------------------------------------------------|
| `composer lint`          | PHPCS, then PHPStan, then Rector in dry-run mode.      |
| `composer lint-fix`      | Rector, then PHPCBF. Fixes what can be fixed.          |
| `composer test`          | PHPUnit without coverage.                              |
| `composer test-coverage` | PHPUnit with coverage, written to `.logs/`.            |
| `composer reset`         | Removes `vendor/`, `vendor-bin/` and `composer.lock`.  |

`composer reset` only deletes - run `composer install` afterwards to get back to a working tree.

## Code quality

Three tools gate every change, and CI runs all of them:

- **PHPCS** - the Drupal standard plus DrevOps rules, with `strict_types` required in every file. Config in [`phpcs.xml`](phpcs.xml).
- **PHPStan** - level 9, the strictest level. Config in [`phpstan.neon`](phpstan.neon).
- **Rector** - PHP 8.2 modernisation plus dead-code, code-quality, coding-style, type-declaration, naming and early-return sets, run in dry-run mode during linting. Config in [`rector.php`](rector.php).

Coverage has a threshold in CI (80% by default, set via the `CI_CODE_COVERAGE_THRESHOLD` repository variable). New code needs tests.

## Conventions

- `declare(strict_types=1)` at the top of every PHP file.
- Single quotes for strings, unless the string contains one.
- `snake_case` for local variables and method arguments; `camelCase` for method names and class properties.
- Every file ends with a newline.

The library itself is deliberately a single class in a single file with no dependencies. Anything that would introduce a `require` of something outside `Prompty.php` needs a good reason.

## Tests

```text
tests/phpunit/Unit/Prompty/   Unit tests, driven through PromptyTestTrait.
tests/phpunit/Functional/     Runs embed.php and starter.php as subprocesses.
```

Unit tests extend `PromptyTestCase` and use `PromptyTestTrait` to feed keystrokes into a memory stream, so no real TTY is needed. Functional tests shell out, which makes them slower but catches things the unit tests can't - like whether an embedded script still parses.

Use data providers where you can. Name the provider `dataProvider<Something>` and put it *after* the test method it feeds.

Arguments after `--` reach PHPUnit, so a single file or a filter looks like:

```bash
composer test -- tests/phpunit/Unit/Prompty/PromptyRenderTest.php
composer test -- --filter testRenderIntro
```

## Playground

The [`playground/`](playground) directory holds runnable demos - the quickest way to see a change in a real terminal:

| Script                        | Shows                                              |
|-------------------------------|----------------------------------------------------|
| `widgets.php`                 | Each widget type standalone, one after another.     |
| `widget-text.php`             | Text widget variants.                               |
| `widget-select.php`           | Select widget variants.                             |
| `widget-multiselect.php`      | Multiselect widget variants.                        |
| `widget-confirm.php`          | Confirm widget variants.                            |
| `widgets-config.php`          | Standalone widgets with custom configuration.       |
| `flow.php`                    | A linear flow with descriptions and hints.          |
| `flow-nested.php`             | A nested flow with conditionals, 3 levels deep.     |
| `flow-config.php`             | A flow with `configure()` applied beforehand.       |
| `flow-multiple.php`           | Several flows, and a standalone widget between them.|

```bash
php playground/flow-nested.php
```

Most of them accept `--no-unicode` and `--no-ansi` to force a display mode:

```bash
php playground/flow.php --no-unicode --no-ansi
```

`flow-config.php` and `flow-multiple.php` set their display mode in code instead, so the flags do nothing there.

## Demo content

Every example in the repository - playground scripts, `starter.php`, README snippets and recorded assets - uses one kitchen-order theme, with no software or technology references. The vocabulary is defined in [`.claude/demo_content_reference.md`](.claude/demo_content_reference.md).

Take labels, keys and hints from that file rather than inventing them. If a demo needs a concept the file doesn't cover, add it there first.

## Regenerating the README assets

The SVGs under `.util/assets/` are recorded from the playground scripts. Regenerate them after any change to rendering:

```bash
php .util/update-assets.php
```

That records all 7 scripts in 4 flag variants each, in parallel. To redo just one:

```bash
php .util/update-assets.php --record widgets
```

It needs `asciinema`, `expect`, `node` and `npm` on your PATH. Set `SCRIPT_QUIET=1` to suppress progress output.

## Releases

Publishing a GitHub release triggers [`release.yml`](.github/workflows/release.yml), which:

1. Substitutes the tag name for the `__PROMPTY_VERSION__` token in `Prompty.php`, so `Prompty::version()` reports the release instead of `development`.
2. Runs `starter.php` once as a smoke test.
3. Builds `Prompty.min.php` and `Prompty.compact.php` with `embed.php --stdout`.
4. Generates `SHA256SUMS` over every asset and signs it with the maintainer's GPG key.
5. Verifies the signature and checksums, uploads everything, then downloads it all again and re-verifies.

Release notes are drafted automatically by [`draft-release-notes.yml`](.github/workflows/draft-release-notes.yml) using the categories in [`.github/release-drafter.yml`](.github/release-drafter.yml).
