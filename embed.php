<?php

/**
 * @file
 * Embedder - minifies and embeds a PHP class into a target script.
 *
 * Part of the Prompty project.
 *
 * @see https://github.com/AlexSkrypnyk/prompty
 *
 * Run the script without arguments to see the usage, arguments and options.
 *
 * The target script must contain // @embed-start and // @embed-end markers.
 * The minified class is inserted between these markers, and the start marker
 * records the class, its version, the minification mode, the SHA-256 of the
 * inserted code and the SHA-256 of this script.
 *
 * The same source and mode always produce the same bytes, so the inserted
 * code can be compared with the payload assets of a release. A kill switch
 * block is injected after the embed region if one is not already present.
 * The embedded script is then run to verify it works, and the snippets that
 * let the consumer's repository verify the embedded code are printed.
 *
 * --check verifies an embedded script against its marker and, with --sums,
 * against the SHA256SUMS of the release the marker names.
 */

declare(strict_types=1);

define('EMBED_SOURCE', __DIR__ . '/Prompty.php');

define('EMBED_MARKER_START', '@embed-start');
define('EMBED_MARKER_END', '@embed-end');

// Group 1 is the rest of the start marker line, group 2 the embedded code.
define('EMBED_REGION_PATTERN', '/^[ \t]*\/\/\s*' . preg_quote(EMBED_MARKER_START, '/') . '\b([^\n]*)\n(.*?)^[ \t]*\/\/\s*' . preg_quote(EMBED_MARKER_END, '/') . '\b[^\n]*/ms');

define('EMBED_MODES', ['min', 'compact']);

define('EMBED_RELEASE_REPO', 'AlexSkrypnyk/prompty');
define('EMBED_RELEASE_FINGERPRINT', '755E824E80F849135F5F4043E71EDB25C4F6D89D');
define('EMBED_SUMS_EMBEDDER', 'embed.php');

// Windows' cmd.exe cannot open /dev/null, and a failed redirect skips the
// command.
define('EMBED_NULL_DEVICE', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

/**
 * Finds the nearest token that is not whitespace.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens from token_get_all().
 * @param int $index
 *   The index the search starts at.
 * @param int $step
 *   Search direction: 1 forward, -1 backward.
 *
 * @return int|null
 *   The token's index, or NULL when only whitespace remains.
 */
function find_significant_token(array $tokens, int $index, int $step): ?int {
  $count = count($tokens);

  while ($index >= 0 && $index < $count) {
    if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_WHITESPACE) {
      return $index;
    }

    $index += $step;
  }

  return NULL;
}

/**
 * Collects the parameter names of a function signature.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens from token_get_all().
 * @param int $index
 *   The index of the first token after the function name.
 *
 * @return array<string, bool>
 *   Parameter names without the leading '$', as keys.
 */
function collect_parameter_names(array $tokens, int $index): array {
  $names = [];
  $depth = 0;
  $count = count($tokens);

  while ($index < $count) {
    $token = $tokens[$index];

    if ($token === '(') {
      $depth++;
    }
    elseif ($token === ')') {
      $depth--;

      if ($depth <= 0) {
        break;
      }
    }
    elseif (is_array($token) && $token[0] === T_VARIABLE && $depth > 0) {
      $names[substr($token[1], 1)] = TRUE;
    }

    $index++;
  }

  return $names;
}

/**
 * Reads the class name and the version constant of a class file.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens from token_get_all().
 *
 * @return array{class: string, version: string}
 *   The class name, or 'unknown' when there is no class. The value of the
 *   VERSION constant, 'development' while it holds the placeholder token,
 *   or 'unknown' when it is missing or not a plain version string.
 */
function embed_source_identity(array $tokens): array {
  $class = 'unknown';
  $version = 'unknown';
  $count = count($tokens);

  for ($i = 0; $i < $count; $i++) {
    if (!is_array($tokens[$i])) {
      continue;
    }

    if ($tokens[$i][0] === T_CLASS && $class === 'unknown') {
      $name_index = find_significant_token($tokens, $i + 1, 1);

      if ($name_index !== NULL && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
        $class = $tokens[$name_index][1];
      }

      continue;
    }

    if ($tokens[$i][0] !== T_CONST || $version !== 'unknown') {
      continue;
    }

    $name_index = find_significant_token($tokens, $i + 1, 1);

    if ($name_index === NULL || !is_array($tokens[$name_index]) || $tokens[$name_index][1] !== 'VERSION') {
      continue;
    }

    $equals_index = find_significant_token($tokens, $name_index + 1, 1);
    $value_index = $equals_index === NULL ? NULL : find_significant_token($tokens, $equals_index + 1, 1);

    if ($value_index === NULL || !is_array($tokens[$value_index]) || $tokens[$value_index][0] !== T_CONSTANT_ENCAPSED_STRING) {
      continue;
    }

    $value = substr($tokens[$value_index][1], 1, -1);

    if (str_starts_with($value, '__')) {
      $version = 'development';
    }
    elseif (preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]*$/', $value) === 1) {
      $version = $value;
    }
  }

  return ['class' => $class, 'version' => $version];
}

/**
 * Finds the embed regions of a script.
 *
 * @param string $content
 *   The script content.
 *
 * @return list<array{attributes: string, region: string}>
 *   For each region, the text after the start marker on its line, and the
 *   bytes between the start marker line and the end marker line.
 */
function embed_region_find(string $content): array {
  preg_match_all(EMBED_REGION_PATTERN, $content, $matches, PREG_SET_ORDER);

  return array_map(fn(array $match): array => ['attributes' => $match[1], 'region' => $match[2]], $matches);
}

/**
 * Parses the key=value attributes of a start marker.
 *
 * @param string $attributes
 *   The text after the start marker on its line.
 *
 * @return array<string, string>
 *   Attribute values keyed by name. Text that is not key=value is ignored.
 */
function embed_marker_parse(string $attributes): array {
  preg_match_all('/(?:^|\s)([a-z0-9]+)=(\S+)/', $attributes, $matches, PREG_SET_ORDER);

  $parsed = [];

  foreach ($matches as $match) {
    $parsed[$match[1]] = $match[2];
  }

  return $parsed;
}

/**
 * Builds a start marker line.
 *
 * @param array<string, string> $attributes
 *   Attribute values keyed by name, in the order they are written.
 *
 * @return string
 *   The start marker line, without a trailing newline.
 */
function embed_marker_build(array $attributes): string {
  $pairs = array_map(fn(string $key, string $value): string => $key . '=' . $value, array_keys($attributes), $attributes);

  return '// ' . EMBED_MARKER_START . ' ' . implode(' ', $pairs);
}

/**
 * Parses a SHA256SUMS file as written by 'sha256sum' in text mode.
 *
 * @param string $content
 *   The file content.
 *
 * @return array<string, string>
 *   Lowercase hex digests keyed by file name.
 *
 * @throws \RuntimeException
 *   When a line is malformed, a file is listed twice, or nothing is listed.
 */
function embed_sums_parse(string $content): array {
  $sums = [];

  foreach (explode("\n", $content) as $number => $line) {
    if ($line === '') {
      continue;
    }

    if (preg_match('/^([0-9a-f]{64})  ([^\s*][^\r\n]*)$/', $line, $match) !== 1) {
      throw new \RuntimeException(sprintf('Line %d of SHA256SUMS is not "<sha256>  <file>".', $number + 1));
    }

    if (isset($sums[$match[2]])) {
      throw new \RuntimeException(sprintf('SHA256SUMS lists %s more than once.', $match[2]));
    }

    $sums[$match[2]] = $match[1];
  }

  if ($sums === []) {
    throw new \RuntimeException('SHA256SUMS lists no files.');
  }

  return $sums;
}

/**
 * Checks an embedded script against its marker and a release's SHA256SUMS.
 *
 * @param string $script_path
 *   The embedded script.
 * @param string|null $sums_path
 *   The SHA256SUMS of the release, or NULL to check only that the embedded
 *   code matches the marker.
 *
 * @return int
 *   The exit code: 0 when every check passes, 1 otherwise.
 */
function embed_check(string $script_path, ?string $sums_path): int {
  $content = is_file($script_path) ? file_get_contents($script_path) : FALSE;

  if ($content === FALSE) {
    fwrite(STDERR, sprintf('Error: Script not found: %s%s', $script_path, PHP_EOL));

    return 1;
  }

  $regions = embed_region_find(str_replace("\r\n", "\n", $content));

  if (count($regions) !== 1) {
    fwrite(STDERR, sprintf('Error: Found %d embed regions in %s, expected 1.%s', count($regions), $script_path, PHP_EOL));

    return 1;
  }

  $attributes = embed_marker_parse($regions[0]['attributes']);
  $formats = [
    'class' => '/^[A-Za-z_][A-Za-z0-9_]*$/',
    'version' => '/^[0-9A-Za-z][0-9A-Za-z.+_-]*$/',
    'mode' => '/^(' . implode('|', EMBED_MODES) . ')$/',
    'sha256' => '/^[0-9a-f]{64}$/',
    'embedder' => '/^[0-9a-f]{64}$/',
  ];

  foreach ($formats as $key => $format) {
    if (!isset($attributes[$key]) || preg_match($format, $attributes[$key]) !== 1) {
      fwrite(STDERR, sprintf('Error: The start marker has no valid "%s" attribute. Re-embed the script to write one.%s', $key, PHP_EOL));

      return 1;
    }
  }

  $has_failed = FALSE;
  $report = function (bool $has_passed, string $message) use (&$has_failed): void {
    echo sprintf('  %s  %s%s', $has_passed ? 'OK  ' : 'FAIL', $message, PHP_EOL);
    $has_failed = $has_failed || !$has_passed;
  };

  echo sprintf('Embedded %s %s (%s) in %s%s', $attributes['class'], $attributes['version'], $attributes['mode'], $script_path, PHP_EOL);

  $region_hash = hash('sha256', $regions[0]['region']);
  $report($region_hash === $attributes['sha256'], $region_hash === $attributes['sha256'] ? 'The embedded code matches the marker hash.' : 'The embedded code does not match the marker hash, so it changed after embedding.');

  if ($sums_path === NULL) {
    echo sprintf('NOT VERIFIED AGAINST A RELEASE. Pass --sums with the verified SHA256SUMS of %s %s to check that the code is the released code.%s', $attributes['class'], $attributes['version'], PHP_EOL);

    return $has_failed ? 1 : 0;
  }

  $sums_content = is_file($sums_path) ? file_get_contents($sums_path) : FALSE;

  if ($sums_content === FALSE) {
    fwrite(STDERR, sprintf('Error: SHA256SUMS not found: %s%s', $sums_path, PHP_EOL));

    return 1;
  }

  try {
    $sums = embed_sums_parse($sums_content);
  }
  catch (\RuntimeException $runtime_exception) {
    fwrite(STDERR, sprintf('Error: %s%s', $runtime_exception->getMessage(), PHP_EOL));

    return 1;
  }

  $is_release = !in_array($attributes['version'], ['development', 'unknown'], TRUE);
  $report($is_release, $is_release ? sprintf('Version %s is a release version.', $attributes['version']) : sprintf('Version "%s" is not a release, so no release lists this code.', $attributes['version']));

  $payload_name = $attributes['class'] . '.' . $attributes['mode'] . '.payload';
  $payload_sum = $sums[$payload_name] ?? NULL;

  if ($payload_sum === NULL) {
    $report(FALSE, sprintf('SHA256SUMS does not list %s.', $payload_name));
  }
  else {
    $report($payload_sum === $region_hash, $payload_sum === $region_hash ? sprintf('The embedded code matches %s in SHA256SUMS.', $payload_name) : sprintf('The embedded code does not match %s in SHA256SUMS.', $payload_name));
  }

  $embedder_sum = $sums[EMBED_SUMS_EMBEDDER] ?? NULL;

  if ($embedder_sum === NULL) {
    $report(FALSE, sprintf('SHA256SUMS does not list %s.', EMBED_SUMS_EMBEDDER));
  }
  else {
    $report($embedder_sum === $attributes['embedder'], $embedder_sum === $attributes['embedder'] ? sprintf('The script was embedded by the %s in SHA256SUMS.', EMBED_SUMS_EMBEDDER) : sprintf('The script was embedded by a different %s than the one in SHA256SUMS.', EMBED_SUMS_EMBEDDER));
  }

  echo sprintf('SHA256SUMS is trusted as given. Verify SHA256SUMS.asc or its attestation before relying on this result.%s', PHP_EOL);

  return $has_failed ? 1 : 0;
}

/**
 * Runs a git command in a directory.
 *
 * @param string $dir
 *   The directory to run in.
 * @param string $arguments
 *   The git arguments, already escaped.
 *
 * @return string|null
 *   The first line of output, or NULL when git fails or prints nothing.
 */
function embed_git(string $dir, string $arguments): ?string {
  $output = [];
  $exit_code = 0;
  exec('git -C ' . escapeshellarg($dir) . ' ' . $arguments . ' 2>' . EMBED_NULL_DEVICE, $output, $exit_code);

  return $exit_code === 0 && isset($output[0]) && $output[0] !== '' ? $output[0] : NULL;
}

/**
 * Builds the snippets that make an embedded script verifiable.
 *
 * @param string $target_path
 *   The embedded script.
 * @param array<string, string> $attributes
 *   The attributes written into the start marker.
 *
 * @return string
 *   The text to print.
 */
function embed_guidance(string $target_path, array $attributes): string {
  $dir = dirname((string) realpath($target_path));
  $name = basename($target_path);
  $script = $name;
  $repo = '<owner>/<repo>';

  $remote = embed_git($dir, 'remote get-url origin');

  if ($remote !== NULL && preg_match('#^(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $remote, $match) === 1) {
    $repo = $match[1];
  }

  // Git prints the prefix with '/' on every platform, which is what the
  // snippets need: they run in GitHub Actions and in a POSIX shell.
  $prefix = embed_git($dir, 'rev-parse --show-prefix');

  if ($prefix !== NULL) {
    $script = $prefix . $name;
  }

  $values = [
    '{name}' => $name,
    '{script}' => $script,
    '{stem}' => (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($name, PATHINFO_FILENAME))),
    '{repo}' => $repo,
    '{url}' => 'https://github.com/' . $repo . '/releases/latest/download/' . $name,
    '{release_repo}' => EMBED_RELEASE_REPO,
    '{fingerprint}' => EMBED_RELEASE_FINGERPRINT,
  ];

  $readme = <<<'SNIPPET'
## Verifying {name}

`{name}` runs on your machine with your permissions, so check that the file you downloaded is the one this repository built. Each release includes a build attestation for `{name}`. GitHub Actions signs it and ties it to the commit and workflow run that built the file.

To verify the file with the [GitHub CLI](https://cli.github.com):

```bash
curl -fsSL {url} -o {name}
gh attestation verify {name} --repo {repo}
php {name}
```

`gh attestation verify` fails when the file changed after the build or came from another repository's workflows.

To verify the file without the GitHub CLI, compare it with the checksum attached to the release:

```bash
curl -fsSL {url} -o {name}
curl -fsSL https://github.com/{repo}/releases/latest/download/{name}.sha256 -o {name}.sha256
sha256sum -c {name}.sha256    # macOS: shasum -a 256 -c {name}.sha256
```

When you download `{name}` from a host other than GitHub, tampering with that host alone fails the check, because the checksum comes from GitHub. Both checks trust this repository: anyone who controls it can rebuild the attestation and the checksum along with the file.

### The embedded Prompty library

`{name}` embeds [Prompty](https://github.com/{release_repo}) as minified code that's too long to review in a diff. On every pull request, CI compares that code with the signed Prompty release named in its `// @embed-start` marker. The check fails when 1 byte differs.
SNIPPET;

  $publish = <<<'SNIPPET'
name: Publish {name}

on:
  release:
    types:
      - published

permissions:
  contents: read

jobs:
  publish:
    name: Publish {name}
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
      - name: Checksum {name}
        run: |
          mkdir -p .publish
          cp {script} .publish/{name}
          cd .publish
          sha256sum {name} > {name}.sha256

      - name: Attest {name}
        uses: actions/attest-build-provenance@v4 # Pin to a commit SHA.
        with:
          subject-path: .publish/{name}

      - name: Upload {name} to the release
        env:
          GH_TOKEN: ${{ github.token }}
          TAG: ${{ github.event.release.tag_name }}
        run: gh release upload "${TAG}" .publish/{name} .publish/{name}.sha256 --clobber
SNIPPET;

  $check = <<<'SNIPPET'
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
          version="$(sed -n 's/.*@embed-start.* version=\([^ ]*\).*/\1/p' {script})"
          gh release download "${version}" --repo {release_repo} --dir .prompty --pattern embed.php --pattern SHA256SUMS --pattern SHA256SUMS.asc

      - name: Verify the Prompty release
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          gh attestation verify .prompty/embed.php --repo {release_repo}
          curl -fsSL https://raw.githubusercontent.com/{release_repo}/main/PUBLIC_KEY.asc | gpg --batch --import
          gpg --batch --status-fd 1 --verify .prompty/SHA256SUMS.asc .prompty/SHA256SUMS | grep -q '^\[GNUPG:\] VALIDSIG {fingerprint} '
          cd .prompty
          sha256sum --ignore-missing -c SHA256SUMS

      - name: Check the embedded code
        run: php .prompty/embed.php --check {script} --sums .prompty/SHA256SUMS

      # Proves the check above fails on a changed copy, so it can't pass
      # without checking anything.
      - name: Check that a changed copy fails
        run: |
          cp {script} .prompty/changed.php
          sed -i '/@embed-start/{n;s/^/ /;}' .prompty/changed.php
          ! php .prompty/embed.php --check .prompty/changed.php --sums .prompty/SHA256SUMS
SNIPPET;

  $snippets = [
    'a README section' => $readme,
    '.github/workflows/publish-{stem}.yml' => $publish,
    'a job for the workflow that tests pull requests' => $check,
  ];

  $output = PHP_EOL . sprintf('Embedded %s %s (%s). To let your repository and its users verify %s:%s', $attributes['class'], $attributes['version'], $attributes['mode'], $name, PHP_EOL);
  $index = 0;

  foreach ($snippets as $title => $snippet) {
    $index++;
    $output .= PHP_EOL . str_pad(sprintf('-- Snippet %d of %d: %s ', $index, count($snippets), strtr($title, $values)), 78, '-') . PHP_EOL;
    $output .= strtr($snippet, $values) . PHP_EOL;
  }

  $output .= str_pad('-- End of snippets ', 78, '-') . PHP_EOL;

  if (in_array($attributes['version'], ['development', 'unknown'], TRUE)) {
    $output .= sprintf('%s is version "%s", which no release lists, so snippet 3 fails until the script is embedded from a release.%s', $attributes['class'], $attributes['version'], PHP_EOL);
  }

  if ($repo === '<owner>/<repo>') {
    $output .= sprintf('Replace <owner>/<repo> in the snippets: no GitHub remote named "origin" was found for %s.%s', $name, PHP_EOL);
  }

  return $output;
}

$is_compact = FALSE;
$to_stdout = FALSE;
$is_payload_only = FALSE;
$is_check_mode = FALSE;
$is_killswitch_skipped = FALSE;
$is_verification_skipped = FALSE;
$source_override = NULL;
$sums_path = NULL;
$positional = [];

for ($arg_i = 1; $arg_i < $argc; $arg_i++) {
  if ($argv[$arg_i] === '--compact') {
    $is_compact = TRUE;
  }
  elseif ($argv[$arg_i] === '--stdout') {
    $to_stdout = TRUE;
  }
  elseif ($argv[$arg_i] === '--payload') {
    $is_payload_only = TRUE;
  }
  elseif ($argv[$arg_i] === '--check') {
    $is_check_mode = TRUE;
  }
  elseif ($argv[$arg_i] === '--no-killswitch') {
    $is_killswitch_skipped = TRUE;
  }
  elseif ($argv[$arg_i] === '--no-verify') {
    $is_verification_skipped = TRUE;
  }
  elseif ($argv[$arg_i] === '--source' || $argv[$arg_i] === '--sums') {
    $option = $argv[$arg_i];
    $arg_i++;

    if ($arg_i >= $argc) {
      fwrite(STDERR, sprintf('Error: %s requires a path argument.%s', $option, PHP_EOL));
      exit(1);
    }

    if ($option === '--source') {
      $source_override = $argv[$arg_i];
    }
    else {
      $sums_path = $argv[$arg_i];
    }
  }
  else {
    $positional[] = $argv[$arg_i];
  }
}

if ($positional === []) {
  $usage = <<<'USAGE'
Embedder - minifies and embeds a PHP class into a target script.

Usage:
  php embed.php [options] <target-script> [<output-script>]
  php embed.php [options] --stdout <output-file>
  php embed.php [options] --payload <output-file>
  php embed.php --check <embedded-script> [--sums <SHA256SUMS>]

Arguments:
  <target-script>   Script containing // @embed-start and // @embed-end markers.
                    Modified in place unless <output-script> is provided.
  <output-script>   Optional output path. The target script is copied here
                    before embedding.

Options:
  --source <path>   Path to the PHP class file to embed. Defaults to Prompty.php
                    in the same directory as this script.
  --compact         Shorten internal property/method names, rename local
                    variables, and reduce whitespace for a smaller output.
  --stdout          Output the processed class as a standalone PHP file instead
                    of embedding into a target script.
  --payload         Output exactly the code that embedding inserts between the
                    markers. Releases publish it as <Class>.<mode>.payload.
  --check           Check an embedded script: its code against the hash in its
                    start marker and, with --sums, against a release.
  --sums <path>     With --check, the SHA256SUMS of the release named in the
                    start marker. Verify its signature before passing it.
  --no-killswitch   Skip injecting the kill switch block and post-embed
                    verification run.
  --no-verify       Skip the post-embed verification run, still injecting the
                    kill switch block.

Re-embedding:
  Running embed.php on a previously embedded script replaces the embedded region
  with the latest class content. All code outside the markers is preserved. Use
  --source to point at an updated Prompty.php for version updates.

Verifying:
  After embedding, embed.php prints a README section and 2 GitHub Actions
  snippets that let the script's repository and users verify it.

USAGE;
  fwrite(STDERR, $usage);
  exit(1);
}

if ((int) $to_stdout + (int) $is_payload_only + (int) $is_check_mode > 1) {
  fwrite(STDERR, sprintf('Error: Use only 1 of --stdout, --payload and --check.%s', PHP_EOL));
  exit(1);
}

if ($sums_path !== NULL && !$is_check_mode) {
  fwrite(STDERR, sprintf('Error: --sums is only used with --check.%s', PHP_EOL));
  exit(1);
}

if ($is_check_mode) {
  exit(embed_check($positional[0], $sums_path));
}

$stdout_path = $positional[0];
$target_path = $positional[0];

// --stdout and --payload take an output file, not a target script.
if (!$to_stdout && !$is_payload_only) {
  $input_path = $positional[0];

  if (!is_file($input_path)) {
    fwrite(STDERR, sprintf('Error: Target script not found: %s%s', $input_path, PHP_EOL));
    exit(1);
  }

  $target_path = $positional[1] ?? $input_path;

  if (isset($positional[1]) && !copy($input_path, $target_path)) {
    fwrite(STDERR, sprintf('Error: Could not copy target script to output: %s%s', $target_path, PHP_EOL));
    exit(1);
  }
}

$source_path = $source_override ?? EMBED_SOURCE;

if (!is_file($source_path)) {
  fwrite(STDERR, sprintf('Error: Source class not found: %s%s', $source_path, PHP_EOL));
  exit(1);
}

$source = file_get_contents($source_path);

if ($source === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read source class: %s%s', $source_path, PHP_EOL));
  exit(1);
}

$tokens = token_get_all($source);
$identity = embed_source_identity($tokens);

// Find the class header docblock: the T_DOC_COMMENT immediately before the
// T_CLASS token.
$class_header_index = NULL;
$token_count = count($tokens);

for ($i = 0; $i < $token_count; $i++) {
  if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
    continue;
  }

  $previous = find_significant_token($tokens, $i - 1, -1);

  if ($previous !== NULL && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOC_COMMENT) {
    $class_header_index = $previous;
  }

  break;
}

// Build minified output: strip preamble and comments, keep class header.
$output = '';
$is_skipping_preamble = TRUE;

for ($i = 0; $i < $token_count; $i++) {
  if (is_string($tokens[$i])) {
    $output .= $tokens[$i];
    continue;
  }

  $token_type = $tokens[$i][0];
  $token_value = $tokens[$i][1];

  if ($token_type === T_OPEN_TAG) {
    continue;
  }

  if ($token_type === T_DECLARE) {
    while ($i < $token_count) {
      $i++;

      if (is_string($tokens[$i]) && $tokens[$i] === ';') {
        break;
      }
    }

    continue;
  }

  // Skip the namespace declaration; --stdout mode re-adds it, embedding
  // inserts the class without one.
  if ($token_type === T_NAMESPACE) {
    while ($i < $token_count) {
      $i++;

      if (is_string($tokens[$i]) && $tokens[$i] === ';') {
        break;
      }
    }

    $is_skipping_preamble = FALSE;
    continue;
  }

  if ($token_type === T_COMMENT || $token_type === T_DOC_COMMENT) {
    if ($i === $class_header_index) {
      $output .= $token_value;
    }

    continue;
  }

  if ($is_skipping_preamble && $token_type === T_WHITESPACE) {
    continue;
  }

  $output .= $token_value;
}

$lines = explode("\n", $output);
$lines = array_map(rtrim(...), $lines);

$collapsed = array_values(array_filter($lines, fn(string $line): bool => $line !== ''));

// Collapse multi-line property array declarations to single lines.
$result_lines = [];
$i = 0;
$line_count = count($collapsed);

while ($i < $line_count) {
  $line = $collapsed[$i];

  if (preg_match('/^\s*(protected|public|private)\s+array\s+\$\w+\s*=\s*\[$/', $line)) {
    $depth = 1;
    $parts = [rtrim($line)];
    $i++;

    while ($i < $line_count && $depth > 0) {
      $inner = trim($collapsed[$i]);
      // Count only structural brackets (not those inside strings).
      $stripped = preg_replace('/"[^"]*"|\'[^\']*\'/', '', $inner) ?? $inner;
      $depth += substr_count($stripped, '[') - substr_count($stripped, ']');
      $parts[] = $inner;
      $i++;
    }

    $result_lines[] = implode(' ', $parts);

    continue;
  }

  $result_lines[] = $line;
  $i++;
}

// Join entire class body into a single line (after the header docblock).
$class_start = NULL;

foreach ($result_lines as $idx => $result_line) {
  if (preg_match('/^class\s+\w+\b/', $result_line)) {
    $class_start = $idx;

    break;
  }
}

if ($class_start !== NULL) {
  $before_class = array_slice($result_lines, 0, $class_start);
  $class_lines = array_slice($result_lines, $class_start);
  $class_single = implode(' ', array_map(trim(...), $class_lines));
  $result_lines = array_merge($before_class, [$class_single]);
}

$minified = implode("\n", $result_lines);

// Compact mode: rename internals and reduce whitespace.
if ($is_compact && $class_start !== NULL) {
  $class_line = end($result_lines);

  $php_code = '<?php ' . $class_line;
  $compact_tokens = token_get_all($php_code);
  $compact_token_count = count($compact_tokens);

  // --- Step 1: Collect protected/private property and method names. ---
  $protected_props = [];
  $protected_methods = [];
  $public_method_params = [];

  $visibility = NULL;

  for ($i = 0; $i < $compact_token_count; $i++) {
    if (!is_array($compact_tokens[$i])) {
      continue;
    }

    $token_type = $compact_tokens[$i][0];

    if ($token_type === T_PUBLIC) {
      $visibility = 'public';
      continue;
    }

    if ($token_type === T_PROTECTED || $token_type === T_PRIVATE) {
      $visibility = 'protected';
      continue;
    }

    // 'static' can follow the visibility keyword, so it must not reset the
    // visibility.
    if ($token_type === T_STATIC) {
      continue;
    }

    if ($token_type === T_VARIABLE && $visibility !== NULL) {
      // Treat as a property unless the preceding non-whitespace token is
      // T_FUNCTION.
      $previous = find_significant_token($compact_tokens, $i - 1, -1);
      $is_property = $previous === NULL || !is_array($compact_tokens[$previous]) || $compact_tokens[$previous][0] !== T_FUNCTION;

      if ($is_property && $visibility !== 'public') {
        $protected_props[] = substr($compact_tokens[$i][1], 1);
      }

      $visibility = NULL;
      continue;
    }

    if ($token_type === T_FUNCTION) {
      $name_index = find_significant_token($compact_tokens, $i + 1, 1);
      $name_token = $name_index === NULL ? NULL : $compact_tokens[$name_index];

      if ($name_index !== NULL && is_array($name_token) && $name_token[0] === T_STRING && !str_starts_with($name_token[1], '__')) {
        if ($visibility !== 'public') {
          $protected_methods[] = $name_token[1];
        }
        else {
          $public_method_params += collect_parameter_names($compact_tokens, $name_index + 1);
        }
      }

      $visibility = NULL;
      continue;
    }

    // Reset visibility on any token that cannot be part of a property type.
    $type_tokens = [T_WHITESPACE, T_STRING, T_ARRAY, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    if (!in_array($token_type, $type_tokens, TRUE)) {
      $visibility = NULL;
    }
  }

  // --- Step 2: Build rename maps. ---
  $prop_map = [];
  $idx = 0;

  foreach (array_unique($protected_props) as $prop) {
    $prop_map[$prop] = '_p' . $idx;
    $idx++;
  }

  $method_map = [];
  $idx = 0;

  foreach (array_unique($protected_methods) as $method) {
    $method_map[$method] = '_m' . $idx;
    $idx++;
  }

  $superglobals = [
    'this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES',
    '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'argc', 'argv',
  ];

  $all_vars = [];

  for ($i = 0; $i < $compact_token_count; $i++) {
    if (!is_array($compact_tokens[$i]) || $compact_tokens[$i][0] !== T_VARIABLE) {
      continue;
    }

    $var_name = substr($compact_tokens[$i][1], 1);

    if (in_array($var_name, $superglobals, TRUE)) {
      continue;
    }

    // Skip names in the property map: declarations, static::$name
    // references and same-named locals are renamed via $prop_map instead.
    if (isset($prop_map[$var_name])) {
      continue;
    }

    if (isset($public_method_params[$var_name])) {
      continue;
    }

    $all_vars[$var_name] = TRUE;
  }

  $var_map = [];
  $idx = 0;

  foreach (array_keys($all_vars) as $var) {
    $short = '';
    $n = $idx;

    do {
      $short = chr(97 + ($n % 26)) . $short;
      $n = intdiv($n, 26) - 1;
    } while ($n >= 0);

    $var_map[$var] = $short;
    $idx++;
  }

  // --- Step 3: Apply renames via token walk. ---
  $compact_output = '';

  for ($i = 1; $i < $compact_token_count; $i++) {
    if (is_string($compact_tokens[$i])) {
      $compact_output .= $compact_tokens[$i];
      continue;
    }

    $token_type = $compact_tokens[$i][0];
    $token_value = $compact_tokens[$i][1];

    if ($token_type === T_VARIABLE) {
      $var_name = substr($token_value, 1);

      // In '$this->name', only '$this' is a T_VARIABLE; the property name is a
      // T_STRING, renamed below.
      if (isset($var_map[$var_name])) {
        $compact_output .= '$' . $var_map[$var_name];
        continue;
      }

      // Property declaration or static::$propName reference.
      if (isset($prop_map[$var_name])) {
        $compact_output .= '$' . $prop_map[$var_name];
        continue;
      }

      $compact_output .= $token_value;
      continue;
    }

    if ($token_type === T_STRING) {
      $prev_index = find_significant_token($compact_tokens, $i - 1, -1);
      $next_index = find_significant_token($compact_tokens, $i + 1, 1);
      $prev = $prev_index === NULL ? NULL : $compact_tokens[$prev_index];
      $next = $next_index === NULL ? NULL : $compact_tokens[$next_index];

      $prev_type = is_array($prev) ? $prev[0] : $prev;
      $is_member = $prev_type === T_OBJECT_OPERATOR || $prev_type === T_NULLSAFE_OBJECT_OPERATOR;
      $is_call = $next === '(';
      $is_method = ($is_call && ($is_member || $prev_type === T_DOUBLE_COLON)) || $prev_type === T_FUNCTION;

      if ($is_method && isset($method_map[$token_value])) {
        $compact_output .= $method_map[$token_value];
        continue;
      }

      if ($is_member && !$is_call && isset($prop_map[$token_value])) {
        $compact_output .= $prop_map[$token_value];
        continue;
      }
    }

    $compact_output .= $token_value;
  }

  // --- Step 4: Reduce whitespace. ---
  // Remove spaces around operators where PHP allows it.
  // Works on tokens so string contents are not modified.
  $whitespace_tokens = token_get_all('<?php ' . $compact_output);
  $whitespace_token_count = count($whitespace_tokens);
  $whitespace_output = '';

  for ($i = 1; $i < $whitespace_token_count; $i++) {
    if (!is_array($whitespace_tokens[$i])) {
      $whitespace_output .= $whitespace_tokens[$i];
      continue;
    }

    $token_type = $whitespace_tokens[$i][0];
    $token_value = $whitespace_tokens[$i][1];

    if ($token_type === T_WHITESPACE) {
      $before = is_array($whitespace_tokens[$i - 1]) ? $whitespace_tokens[$i - 1][1] : $whitespace_tokens[$i - 1];
      $after = '';

      if ($i + 1 < $whitespace_token_count) {
        $after = is_array($whitespace_tokens[$i + 1]) ? $whitespace_tokens[$i + 1][1] : $whitespace_tokens[$i + 1];
      }

      $before_last = $before !== '' ? $before[strlen($before) - 1] : '';
      $after_first = $after !== '' ? $after[0] : '';

      $before_is_word = preg_match('/[a-zA-Z0-9_$]/', $before_last);
      $after_is_word = preg_match('/[a-zA-Z0-9_$\\\\]/', $after_first);

      if ($before_is_word && $after_is_word) {
        $whitespace_output .= ' ';
      }

      continue;
    }

    $whitespace_output .= $token_value;
  }

  $result_lines = $before_class;
  $result_lines[] = $whitespace_output;
  $minified = implode("\n", $result_lines);
}

$namespace = '';

if (preg_match('/^\s*namespace\s+(.+?)\s*;/m', $source, $ns_match)) {
  $namespace = $ns_match[1];
}

$payload = trim($minified) . "\n";

$payload = (string) preg_replace('/^(class\s)/m', "// @phpstan-ignore-next-line\n$1", $payload, 1);

if ($is_payload_only) {
  file_put_contents($stdout_path, $payload);

  echo sprintf('Written to %s%s', $stdout_path, PHP_EOL);
  exit(0);
}

if ($to_stdout) {
  $standalone = "<?php\n\ndeclare(strict_types=1);\n\n";

  if ($namespace !== '') {
    $standalone .= "namespace {$namespace};\n\n";
  }

  $standalone .= $payload;

  file_put_contents($stdout_path, $standalone);

  $lint_output = [];
  $lint_exit = 0;
  exec('php -l ' . escapeshellarg($stdout_path) . ' 2>&1', $lint_output, $lint_exit);

  if ($lint_exit !== 0) {
    fwrite(STDERR, sprintf('Error: PHP lint failed:%s', PHP_EOL));
    fwrite(STDERR, implode(PHP_EOL, $lint_output) . PHP_EOL);
    exit(1);
  }

  echo sprintf('Written to %s%s', $stdout_path, PHP_EOL);
  exit(0);
}

$embedder_hash = hash_file('sha256', __FILE__);

if ($embedder_hash === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not hash %s.%s', __FILE__, PHP_EOL));
  exit(1);
}

$attributes = [
  'class' => $identity['class'],
  'version' => $identity['version'],
  'mode' => $is_compact ? 'compact' : 'min',
  'sha256' => hash('sha256', $payload),
  'embedder' => $embedder_hash,
];

$embedded = embed_marker_build($attributes) . "\n";
$embedded .= $payload;
$embedded .= '// ' . EMBED_MARKER_END;

$target = file_get_contents($target_path);

if ($target === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read target file: %s%s', $target_path, PHP_EOL));
  exit(1);
}

$region_count = count(embed_region_find($target));

if ($region_count === 0) {
  fwrite(STDERR, sprintf('Error: Could not find // %s and // %s markers in target file.%s', EMBED_MARKER_START, EMBED_MARKER_END, PHP_EOL));
  exit(1);
}

if ($region_count > 1) {
  fwrite(STDERR, sprintf('Error: Found %d embed regions in target file, expected 1.%s', $region_count, PHP_EOL));
  exit(1);
}

// Escape backslashes and dollar signs in the replacement to prevent
// preg_replace from interpreting \033 as backreference \0 + "33".
$result = preg_replace(EMBED_REGION_PATTERN, str_replace(['\\', '$'], ['\\\\', '\\$'], $embedded), $target, 1);

if ($result === NULL) {
  fwrite(STDERR, sprintf('Error: Failed to replace marker region.%s', PHP_EOL));
  exit(1);
}

$result = preg_replace('/\n{3,}/', "\n\n", $result);

$has_killswitch = str_contains((string) $result, "if (!getenv('SHOULD_PROCEED'))");

if (!$has_killswitch && !$is_killswitch_skipped) {
  $killswitch = <<<'KILLSWITCH'

// Kill switch - stop here when running under tests.
// In production, set SHOULD_PROCEED=1 to continue past this point.
if (!getenv('SHOULD_PROCEED')) {
  return;
}
KILLSWITCH;

  if (str_contains((string) $result, '// phpcs:enable')) {
    $result = preg_replace('/(\/\/ phpcs:enable\n)/', '$1' . $killswitch, (string) $result, 1);
  }
  else {
    $result = preg_replace('/(\/\/ ' . preg_quote(EMBED_MARKER_END, '/') . '[^\n]*\n)/', '$1' . $killswitch, (string) $result, 1);
  }
}

// The marker hash is only valid if no later rewrite of the file touched the
// embedded code.
$written_regions = embed_region_find((string) $result);

if (count($written_regions) !== 1 || $written_regions[0]['region'] !== $payload) {
  fwrite(STDERR, sprintf('Error: The embedded code differs from the code that was hashed.%s', PHP_EOL));
  exit(1);
}

file_put_contents($target_path, $result);

$lint_output = [];
$lint_exit = 0;
exec('php -l ' . escapeshellarg($target_path) . ' 2>&1', $lint_output, $lint_exit);

if ($lint_exit !== 0) {
  fwrite(STDERR, sprintf('Error: PHP lint failed after embedding:%s', PHP_EOL));
  fwrite(STDERR, implode(PHP_EOL, $lint_output) . PHP_EOL);
  exit(1);
}

echo sprintf('Embedded into %s%s', $target_path, PHP_EOL);

$final = file_get_contents($target_path);
$final_has_killswitch = $final !== FALSE && str_contains($final, "if (!getenv('SHOULD_PROCEED'))");

if (!$final_has_killswitch) {
  fwrite(STDERR, sprintf('Warning: No kill switch found in the target file. Please test the embedded script manually.%s', PHP_EOL));
}

// Verification runs the embedded script, and --no-killswitch leaves it with
// nothing to stop before the real work.
$should_verify = $final_has_killswitch && !$is_killswitch_skipped && !$is_verification_skipped;

if ($should_verify && stream_isatty(STDIN)) {
  echo sprintf('Verifying embedded script (interactive input required)...%s', PHP_EOL);

  $verify_exit = 0;
  passthru('php ' . escapeshellarg($target_path), $verify_exit);

  if ($verify_exit !== 0) {
    fwrite(STDERR, sprintf('Warning: Embedded script exited with code %d.%s', $verify_exit, PHP_EOL));
  }
}
elseif ($should_verify) {
  echo sprintf('Verifying embedded script...%s', PHP_EOL);

  // Non-interactive mode: pipe keystrokes to simulate a quick run.
  $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $verify_proc = proc_open('php ' . escapeshellarg($target_path), $descriptors, $verify_pipes);

  if (is_resource($verify_proc)) {
    // Send minimal input to get through the flow.
    fwrite($verify_pipes[0], "\n\n\n\n");
    fclose($verify_pipes[0]);

    stream_get_contents($verify_pipes[1]);
    fclose($verify_pipes[1]);

    stream_get_contents($verify_pipes[2]);
    fclose($verify_pipes[2]);

    $verify_exit = proc_close($verify_proc);

    if ($verify_exit !== 0) {
      fwrite(STDERR, sprintf('Warning: Embedded script exited with code %d.%s', $verify_exit, PHP_EOL));
    }
  }
}

echo embed_guidance($target_path, $attributes);
