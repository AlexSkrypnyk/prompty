# Security Policy

## Supported versions

Prompty is pre-1.0, and only the [latest release](https://github.com/AlexSkrypnyk/prompty/releases/latest) is supported. Fixes ship in a new release and aren't backported, so on an older tag, upgrade to get a fix.

## Reporting a vulnerability

Don't report a security problem in a public issue. On the [Security tab](https://github.com/AlexSkrypnyk/prompty/security/advisories/new), choose **Report a vulnerability** to open a private thread that only the maintainers can see. If you can't use private reporting, email <alex@drevops.com>.

Include as much of the following as you can:

- What the issue is, and which file or function it's in.
- The version or commit you're on.
- Steps to reproduce, ideally as a short script.
- What an attacker could actually do with it.

You'll get an acknowledgment within a few days. If the report is confirmed, we'll agree on a disclosure timeline with you. The advisory credits you unless you'd rather stay anonymous.

## Verifying a release

Every release ships 2 files for verification:

- `SHA256SUMS`, with the checksum of every asset
- `SHA256SUMS.asc`, a detached GPG signature of `SHA256SUMS`

The signing key's public half is [`PUBLIC_KEY.asc`](PUBLIC_KEY.asc) in this repository. Its fingerprint is:

```text
755E 824E 80F8 4913 5F5F  4043 E71E DB25 C4F6 D89D
```

Prompty ends up copied or embedded in your own script, so verify a download before you use it. To download `Prompty.php` and verify it:

```bash
BASE=https://github.com/AlexSkrypnyk/prompty/releases/latest/download
RAW=https://raw.githubusercontent.com/AlexSkrypnyk/prompty/main

curl -LO $BASE/Prompty.php
curl -LO $BASE/SHA256SUMS
curl -LO $BASE/SHA256SUMS.asc
curl -LO $RAW/PUBLIC_KEY.asc

# Check the fingerprint against the one above before trusting the key.
gpg --show-keys --with-fingerprint PUBLIC_KEY.asc

gpg --import PUBLIC_KEY.asc
gpg --verify SHA256SUMS.asc SHA256SUMS
sha256sum --ignore-missing -c SHA256SUMS
```

`--ignore-missing` lets the check pass when you've downloaded only some of the assets. If you've downloaded all of them, drop it.

The release workflow runs the same check before it uploads the assets, and again on the uploaded copies.

Every asset, `SHA256SUMS` included, also has a build attestation from GitHub Actions. To verify an asset with the [GitHub CLI](https://cli.github.com) instead of GPG:

```bash
gh attestation verify Prompty.php --repo AlexSkrypnyk/prompty
```

After uploading, the release workflow also verifies every attestation. It then embeds the uploaded `Prompty.php` with the uploaded `embed.php` in both modes and runs `embed.php --check` against the uploaded `SHA256SUMS`.

To check a script that already embeds Prompty, see [Verify an embedded script](README.md#verify-an-embedded-script).

### What this does and does not prove

A passing check proves 2 things:

- The files you downloaded match the signed checksums.
- The signature was made with the key in this repository.

It doesn't prove that the key belongs to the maintainer. The key, its fingerprint and this page all live in this repository. Anyone who took over the repository or the maintainer's account could replace all 3, and a first-time download would still pass.

An attestation has the same limit. It proves that a workflow run in this repository built the asset from the commit it names. Anyone who controls the repository can start that workflow.

The check does catch a key swap after you've trusted the key. Record the fingerprint on your first download and compare it on every later download. If the fingerprint changes without a note in the release notes, treat that release as suspect. [Report it](#reporting-a-vulnerability) before you run anything from it.

## Scope

Prompty reads keystrokes from a stream and writes ANSI escape sequences to stdout. It runs `stty` through the shell to switch the terminal into raw mode and back. To restore the terminal, it passes only the value it read from `stty -g`, never a value from a caller or an environment variable.

The library makes no network requests, writes no files and runs no input as code.

`embed.php` is a build-time tool that does more. It reads a class file, rewrites it and writes the result to a path you name. Then it runs these commands:

- `php -l`, to check that the output parses
- `git`, to find the repository named in the snippets it prints
- `php` on your script, to confirm that the embedded result runs

The last command runs your script, so point `embed.php` only at scripts you trust. `embed.php` runs a script only when it has a [kill switch](README.md#kill-switch), which returns before the script's real work unless `SHOULD_PROCEED` is set. `--check` only reads the script and `SHA256SUMS`, and runs nothing.

Everything under `.util/` is maintainer tooling, outside the library. It holds the drift check that `composer lint` runs against the embedded playground demo, the release embed check that CI and the release workflow run, and the scripts that record and render the README demos. `.gitattributes` marks `.util/` as `export-ignore`, so source archives and Composer's default dist installs leave it out. A `--prefer-source` install clones the whole repository and includes it.

The `.util/` scripts run `php`, and the recorder also runs `asciinema`, `expect` and `node`. Each command gets only paths derived from the script's own location or named on its command line, and job names checked against a fixed list, each passed through `escapeshellarg()`.
