# Contributing

Thanks for helping with Prompty. This guide covers local setup, the checks and tests every change passes, the playground demos and the release process. To report a bug or suggest an idea, open an [issue](https://github.com/AlexSkrypnyk/prompty/issues) or send a pull request.

## Requirements

You need PHP 8.2 or later and Composer. CI runs the suite on PHP 8.2, 8.3, 8.4 and 8.5, with both the latest and the lowest dependency versions. A change that needs PHP newer than 8.2 fails CI.

`composer.lock` isn't committed, so a fresh clone resolves dependencies from scratch.

## Setup

Clone the repository and install its dependencies:

```bash
git clone https://github.com/AlexSkrypnyk/prompty.git
cd prompty
composer install
```

## Commands

The project's commands run through Composer:

| Command                     | What it does                                          |
|-----------------------------|-------------------------------------------------------|
| `composer lint`             | PHPCS, PHPStan, Rector in dry-run mode, then the embedded playground demo check. |
| `composer lint-fix`         | Rector, then PHPCBF. Fixes what can be fixed.          |
| `composer test`             | PHPUnit without coverage.                              |
| `composer test-coverage`    | PHPUnit with coverage, written to `.logs/`.            |
| `composer reset`            | Removes `vendor/`, `vendor-bin/` and `composer.lock`.  |

`composer reset` only deletes files. Run `composer install` after it to restore a working tree.

## Code quality

`composer lint` runs 4 checks, and CI runs it on every change:

- **PHPCS** - the Drupal standard plus DrevOps rules, with `strict_types` required in every file. Config in [`phpcs.xml`](phpcs.xml).
- **PHPStan** - level 9, the strictest level. Config in [`phpstan.neon`](phpstan.neon).
- **Rector** - PHP 8.2 modernization plus the dead-code, code-quality, coding-style, type-declaration, naming, instanceof, early-return and PHPUnit code-quality sets. Linting runs it in dry-run mode. Config in [`rector.php`](rector.php).
- **Embedded demo check** - embeds `playground/flow-embed.php` and fails if the copy prints anything different from the original. See [The embedded demo](#the-embedded-demo).

CI fails when coverage drops below a threshold: 80% by default, or the value of the `CI_CODE_COVERAGE_THRESHOLD` repository variable. New code needs tests.

## Conventions

Follow these code conventions:

- `declare(strict_types=1)` at the top of every PHP file.
- Single quotes for strings, unless the string contains a single quote.
- `snake_case` for local variables and method arguments; `camelCase` for method names and class properties.
- Every file ends with a newline.

The library is 1 class in 1 file with no dependencies, by design. A change that requires a file outside `Prompty.php` needs a strong reason.

## Tests

The tests and their fixtures live in 3 directories:

```text
tests/phpunit/Unit/Prompty/   Unit tests, driven through PromptyTestTrait.
tests/phpunit/Functional/     Runs embed.php, starter.php and fixtures as subprocesses.
tests/phpunit/Fixtures/       Scripts the functional tests run.
```

Unit tests extend `PromptyTestCase` and use `PromptyTestTrait` to feed keystrokes from a memory stream, so they need no terminal. Functional tests run subprocesses. They're slower, but they catch what unit tests can't, such as an embedded script that no longer parses. `TerminalInputTest` runs a fixture in a pseudo-terminal to test keys that arrive over time, and skips itself on PHP builds that can't open one.

`embed.php` must produce the same bytes on every PHP version, so a consumer's embed matches the release's `.payload` assets. `EmbedIntegrityTest` minifies `tests/phpunit/Fixtures/EmbedSource.php` on each PHP version in CI and compares the result with the committed `EmbedSource.min.payload` and `EmbedSource.compact.payload`. After a deliberate change to the minifier, regenerate both:

```bash
php embed.php --source tests/phpunit/Fixtures/EmbedSource.php --payload tests/phpunit/Fixtures/EmbedSource.min.payload
php embed.php --compact --source tests/phpunit/Fixtures/EmbedSource.php --payload tests/phpunit/Fixtures/EmbedSource.compact.payload
```

Use data providers where you can. Name the provider `dataProvider<Something>` and put it *after* the test method it feeds.

Arguments after `--` go to PHPUnit. To run 1 file or filter by test name:

```bash
composer test -- tests/phpunit/Unit/Prompty/PromptyRenderTest.php
composer test -- --filter testRenderIntro
```

## Playground

The [`playground/`](playground) directory holds runnable demos. Run one to see a change in a real terminal:

| Script                     | Shows                                                                  |
|----------------------------|------------------------------------------------------------------------|
| `widgets.php`              | Each widget type standalone, one after another.                        |
| `widget-text.php`          | Text widget variants.                                                  |
| `widget-select.php`        | Select widget variants.                                                |
| `widget-multiselect.php`   | Multiselect widget variants.                                           |
| `widget-confirm.php`       | Confirm widget variants.                                               |
| `widget-values.php`        | Where an answer comes from, and what each source is held to.           |
| `widget-validate.php`      | A `validate` callback rejecting answers, in widgets and in a flow.     |
| `widget-control-chars.php` | What a control character in a value draws as.                          |
| `widget-transform.php`     | A transform rewriting each widget's answer, and a wrong return type.   |
| `config.php`               | What `configure()` accepts, how a flow adds to it, what it turns down. |
| `flow.php`                 | A linear flow with descriptions, hints and help.                       |
| `flow-nested.php`          | A nested flow with conditionals, 3 levels deep.                        |
| `flow-scope.php`           | Several flows, and how long a flow's own configuration lasts.          |
| `flow-step-keys.php`       | What a step key becomes, and which are turned down.                    |
| `flow-embed.php`           | The flow the embedder is tested against.                               |

To run a demo:

```bash
php playground/flow-nested.php
```

All of them except `flow-step-keys.php` accept `--no-unicode` and `--no-ansi` to force a display mode:

```bash
php playground/flow.php --no-unicode --no-ansi
```

`flow.php` and `flow-nested.php` also accept `--no-interaction`, which switches on [non-interactive mode](README.md#non-interactive-mode).

### The embedded demo

Consumer scripts run an embedded copy of Prompty, so that copy has to keep working. `flow-embed.php` is the demo that tests it. Embedding writes `flow-embed.dist.php`, which carries the class inline and runs from any directory. To build the copy and run both versions:

```bash
php embed.php --no-verify \
  playground/flow-embed.php playground/flow-embed.dist.php
php playground/flow-embed.php
cp playground/flow-embed.dist.php ~/order.php
php ~/order.php
```

Both versions render the same flow, so any difference between them is an embedder bug. Edit `flow-embed.php` only. The built copy is git-ignored, so build it when you need it.

2 things catch a difference between the pair:

- `composer lint` runs [`.util/check-embed.php`](.util/check-embed.php). It embeds the demo into a temporary path, runs both versions with every answer set through the environment and fails if their output differs. The failure prints both outputs.
- The asset recorder captures the embedded copy answering its prompts in `.util/assets/flow-embed.svg`. The README doesn't show that SVG. A change in what the embedded script draws shows up as a diff in its frames, and a text comparison can't catch that kind of change.

## Demo content

Every example in the repository uses 1 kitchen-order theme, with no software or technology references. That covers the playground scripts, `starter.php`, README snippets and recorded assets. [`.claude/demo_content_reference.md`](.claude/demo_content_reference.md) defines the vocabulary.

Take labels, keys and hints from that file. If a demo needs a concept the file doesn't cover, add the concept there first.

## Regenerating the README assets

The SVGs under `.util/assets/` are recorded from the playground scripts. Regenerate them after any change to rendering:

```bash
php .util/update-assets.php
```

The command records 7 scripts in 4 flag variants each, plus the embedded copy of the flow. It runs a few recordings at a time. To record 1 asset again, pass its filename without `.svg`:

```bash
php .util/update-assets.php --record widgets
```

The recorder needs `asciinema`, `expect`, `node` and `npm` on your `PATH`. To hide progress output, set `SCRIPT_QUIET=1`. To keep the recordings in `.artifacts/tmp/asciinema` for inspection, set `SCRIPT_KEEP_CASTS=1`.

Recording the same session twice produces the same SVG, byte for byte. After a regeneration, a clean `git status` means the rendering didn't change, and any diff is a real change. 3 design choices keep recordings reproducible, and changing any of them can break that:

- Sessions type at a fixed rate, because `expect`'s humanized typing varies its delays.
- The recorder treats the output as 1 stream and cuts a frame wherever a widget redraws. A terminal splitting a write never creates a frame.
- The recorder sets every gap between frames to 1 of 2 fixed durations before rendering, so the SVG doesn't depend on how fast the recording ran.

Recordings run a few at a time. Running every session at once stalls them enough to blur the pauses between steps into the gaps within a step.

## Releases

Publishing a GitHub release triggers [`release.yml`](.github/workflows/release.yml), which does the following:

1. Substitutes the tag name for the `__PROMPTY_VERSION__` token in `Prompty.php`, so `Prompty::version()` reports the release instead of `development`.
2. Runs `starter.php` once as a smoke test.
3. Builds `Prompty.min.php` and `Prompty.compact.php` with `embed.php --stdout`, and `Prompty.min.payload` and `Prompty.compact.payload` with `embed.php --payload`.
4. Generates `SHA256SUMS` over every asset and signs it with the maintainer's GPG key.
5. Creates a build attestation for every asset, `SHA256SUMS` included.
6. Verifies the signature and checksums, uploads everything, then downloads it all again and re-verifies the signature, the checksums and the attestations.
7. Runs [`.util/check-embed-release.php`](.util/check-embed-release.php) on the downloaded assets. It embeds the downloaded `Prompty.php` into a copy of `starter.php` with the downloaded `embed.php`, in both modes. It then confirms that `embed.php --check` passes the embed and fails a copy with 1 changed byte.

The job runs in the `release` environment. Store `GPG_PRIVATE_KEY` as a secret of that environment and give the environment a required reviewer, so each release waits for approval before the job can read the key.

The `Check embed release` job in [`test-php.yml`](.github/workflows/test-php.yml) builds the same payloads and an unsigned `SHA256SUMS` on every pull request, and runs the same script against them.

[`draft-release-notes.yml`](.github/workflows/draft-release-notes.yml) drafts the release notes, using the categories in [`.github/release-drafter.yml`](.github/release-drafter.yml).

## Updating from the template

This repository comes from the [Scaffold](https://getscaffold.dev/) template, and can pull the template's latest CI workflows, linting configuration and docs.

To update, ask Claude Code to "update scaffold". Claude Code fetches the updater skill and follows it. [`CLAUDE.md`](CLAUDE.md) lists the steps it takes.
