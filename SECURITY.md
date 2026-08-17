# Security Policy

## Supported versions

Prompty is pre-1.0, and only the [latest release](https://github.com/AlexSkrypnyk/prompty/releases/latest) is supported. Fixes land in a new release rather than being backported, so if you're on an older tag, upgrading is the fix.

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Use GitHub's private reporting instead - go to the [Security tab](https://github.com/AlexSkrypnyk/prompty/security/advisories/new) and choose "Report a vulnerability". That opens a private thread visible only to the maintainers. If that isn't available to you, email <prompty@alexskrypnyk.com>.

Helpful things to include:

- What the issue is, and which file or function it's in.
- The version or commit you're on.
- Steps to reproduce, ideally as a short script.
- What an attacker could actually do with it.

You'll get an acknowledgement within a few days. If the report is confirmed, we'll agree a disclosure timeline with you and credit you in the advisory unless you'd rather stay anonymous.

## Verifying a release

Every release ships a `SHA256SUMS` file covering all assets, plus a detached GPG signature `SHA256SUMS.asc`. The signing key's public half is [`PUBLIC_KEY.asc`](PUBLIC_KEY.asc) in this repository.

Because Prompty is designed to be copied or embedded directly into your own script, verifying the download before you paste it in is worth the 20 seconds:

```bash
BASE=https://github.com/AlexSkrypnyk/prompty/releases/latest/download
RAW=https://raw.githubusercontent.com/AlexSkrypnyk/prompty/main

curl -LO $BASE/Prompty.php
curl -LO $BASE/SHA256SUMS
curl -LO $BASE/SHA256SUMS.asc
curl -LO $RAW/PUBLIC_KEY.asc

gpg --import PUBLIC_KEY.asc
gpg --verify SHA256SUMS.asc SHA256SUMS
sha256sum --ignore-missing -c SHA256SUMS
```

`--ignore-missing` lets the check pass when you've only downloaded some of the assets. Drop it if you've pulled all of them.

The release workflow performs this same verification itself after uploading, so a release that fails it never ships.

## Scope

Prompty reads keystrokes from a stream and writes ANSI escape sequences to stdout. It runs `stty` through the shell to put the terminal into raw mode and to restore it afterwards, using only values it read back from `stty -g` itself - never anything supplied by a caller or an environment variable.

The library performs no network access, writes no files, and evaluates no user input as code.

`embed.php` is a build-time tool with a wider footprint. It reads a class file, rewrites it, writes the result to a path you name, and then shells out: to Rector if it's installed, to `php -l` to check the output parses, and to `php <your-script>` to confirm the embedded result actually runs. That last step executes the target script, so only point `embed.php` at scripts you trust. The run is guarded by the [kill switch](README.md#kill-switch): `embed.php` only runs a script that has one, and the kill switch returns before the script's real work unless `SHOULD_PROCEED` is set.
