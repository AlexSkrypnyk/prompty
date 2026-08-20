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

| Command                     | What it does                                          |
|-----------------------------|-------------------------------------------------------|
| `composer lint`             | PHPCS, PHPStan, Rector in dry-run mode, then the embedded playground demo check. |
| `composer lint-fix`         | Rector, then PHPCBF. Fixes what can be fixed.          |
| `composer test`             | PHPUnit without coverage.                              |
| `composer test-coverage`    | PHPUnit with coverage, written to `.logs/`.            |
| `composer reset`            | Removes `vendor/`, `vendor-bin/` and `composer.lock`.  |

`composer reset` only deletes - run `composer install` afterwards to get back to a working tree.

## Code quality

Four checks gate every change, and CI runs all of them through `composer lint`:

- **PHPCS** - the Drupal standard plus DrevOps rules, with `strict_types` required in every file. Config in [`phpcs.xml`](phpcs.xml).
- **PHPStan** - level 9, the strictest level. Config in [`phpstan.neon`](phpstan.neon).
- **Rector** - PHP 8.2 modernisation plus dead-code, code-quality, coding-style, type-declaration, naming and early-return sets, run in dry-run mode during linting. Config in [`rector.php`](rector.php).
- **Embedded demo check** - embeds `playground/flow-embed.php` and fails if the copy prints anything different from the original. See [The embedded demo](#the-embedded-demo).

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
| `widget-placeholder.php`      | What an empty answer to a text widget means.        |
| `widget-discovered.php`       | Where a discovered value comes from, and its forms. |
| `widget-default.php`          | Defaults, and the option keys they are held to.     |
| `widget-control-chars.php`    | What a control character in a value draws as.       |
| `widgets-config.php`          | Standalone widgets with custom configuration.       |
| `widgets-config-keys.php`     | What `configure()` accepts, and what it turns down. |
| `flow.php`                    | A linear flow with descriptions and hints.          |
| `flow-nested.php`             | A nested flow with conditionals, 3 levels deep.     |
| `flow-config.php`             | A flow with `configure()` applied beforehand.       |
| `flow-multiple.php`           | Several flows, and a standalone widget between them.|
| `flow-config-scope.php`       | How long a flow's own configuration lasts.          |
| `flow-embed.php`              | The flow the embedder is tested against.            |

```bash
php playground/flow-nested.php
```

Most of them accept `--no-unicode` and `--no-ansi` to force a display mode:

```bash
php playground/flow.php --no-unicode --no-ansi
```

`flow-config.php` and `flow-multiple.php` set their display mode in code instead, and `flow-config-scope.php` is about configuration lifetime rather than display, so the flags do nothing in those three.

### The embedded demo

Prompty is meant to be copied into a consumer script, so the copy is the thing that has to keep working. `flow-embed.php` is the script that proves it does. Embedding rewrites it into `flow-embed.dist.php`, which carries the class inline and requires nothing, so it runs from anywhere:

```bash
php embed.php --no-verify playground/flow-embed.php playground/flow-embed.dist.php
php playground/flow-embed.php
cp playground/flow-embed.dist.php ~/order.php
php ~/order.php
```

Both render the same flow, so anything that differs between them is a fault in the embedder rather than in the flow. Make edits in `flow-embed.php`; the built copy is output, and it is not committed - build it whenever it is wanted.

Two things compare the pair without being asked:

- `composer lint` runs [`.util/check-embed.php`](.util/check-embed.php), which embeds the demo into a temporary path, runs both with every answer supplied through the environment, and fails if they print anything different. It reports both outputs when they diverge, so the difference is readable from the failure.
- `.util/assets/flow-embed.svg` records the embedded copy being driven through its prompts by the expect script the recorder generates for it. Nothing embeds that asset and it is deliberately kept out of the README: it exists so that a change in what an embedded script *draws* shows up as a diff in the frames, which comparing text cannot catch.

## Demo content

Every example in the repository - playground scripts, `starter.php`, README snippets and recorded assets - uses one kitchen-order theme, with no software or technology references. The vocabulary is defined in [`.claude/demo_content_reference.md`](.claude/demo_content_reference.md).

Take labels, keys and hints from that file rather than inventing them. If a demo needs a concept the file doesn't cover, add it there first.

## Regenerating the README assets

The SVGs under `.util/assets/` are recorded from the playground scripts. Regenerate them after any change to rendering:

```bash
php .util/update-assets.php
```

That records all 7 scripts in 4 flag variants each, plus the embedded copy of the flow, a few at a time. To redo just one:

```bash
php .util/update-assets.php --record widgets
```

It needs `asciinema`, `expect`, `node` and `npm` on your PATH. Set `SCRIPT_QUIET=1` to suppress progress output, or `SCRIPT_KEEP_CASTS=1` to keep the recordings under `.artifacts/tmp/asciinema` for inspection.

**Regeneration is reproducible.** Recording the same session twice produces the same SVG, byte for byte, so `git status` after a regeneration answers whether the rendering changed: a clean tree means it did not, and any diff is a real change worth reading. Three things make that hold, and a change to any of them can break it:

- Sessions type at a fixed rate rather than expect's humanised one, which varies its delays by design.
- The recorded output is treated as one stream and cut into frames where a widget redrew, so a frame is never created by the terminal happening to split a write.
- Every gap is rewritten to one of two durations before rendering, so the wall clock the recording ran against does not reach the SVG.

Recordings run a few at a time rather than all at once, because a machine running every session together stalls them enough to blur the gap between a pause in a session and the gaps within one.

## Releases

Publishing a GitHub release triggers [`release.yml`](.github/workflows/release.yml), which:

1. Substitutes the tag name for the `__PROMPTY_VERSION__` token in `Prompty.php`, so `Prompty::version()` reports the release instead of `development`.
2. Runs `starter.php` once as a smoke test.
3. Builds `Prompty.min.php` and `Prompty.compact.php` with `embed.php --stdout`.
4. Generates `SHA256SUMS` over every asset and signs it with the maintainer's GPG key.
5. Verifies the signature and checksums, uploads everything, then downloads it all again and re-verifies.

Release notes are drafted automatically by [`draft-release-notes.yml`](.github/workflows/draft-release-notes.yml) using the categories in [`.github/release-drafter.yml`](.github/release-drafter.yml).

## Updating from the template

This repository was generated from the [Scaffold](https://getscaffold.dev/) template and can pull the template's latest CI workflows, linting configuration and docs at any time.

The update is agent-driven: ask Claude Code to "update scaffold" and it fetches the updater skill and follows it. [`AGENTS.md`](AGENTS.md) has the steps.
