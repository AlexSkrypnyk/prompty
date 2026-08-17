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
 * The minified class will be inserted between these markers.
 *
 * Before embedding, if Rector is available (vendor/bin/rector), it is run
 * on the processed class content. A kill switch block is injected after
 * the embed region if one is not already present. The embedded script is
 * then run to verify it works.
 */

declare(strict_types=1);

// Configuration - adjust these when reusing for a different project.
define('EMBED_SOURCE', __DIR__ . '/Prompty.php');

define('EMBED_MARKER_START', '@embed-start');
define('EMBED_MARKER_END', '@embed-end');

// Argument parsing.
$compact = FALSE;
$stdout = FALSE;
$no_killswitch = FALSE;
$source_override = NULL;
$positional = [];

for ($arg_i = 1; $arg_i < $argc; $arg_i++) {
  if ($argv[$arg_i] === '--compact') {
    $compact = TRUE;
  }
  elseif ($argv[$arg_i] === '--stdout') {
    $stdout = TRUE;
  }
  elseif ($argv[$arg_i] === '--no-killswitch') {
    $no_killswitch = TRUE;
  }
  elseif ($argv[$arg_i] === '--source') {
    $arg_i++;
    if ($arg_i >= $argc) {
      fwrite(STDERR, sprintf('Error: --source requires a path argument.%s', PHP_EOL));
      exit(1);
    }
    $source_override = $argv[$arg_i];
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
  --no-killswitch   Skip injecting the kill switch block and post-embed
                    verification run.

Re-embedding:
  Running embed.php on a previously embedded script replaces the embedded region
  with the latest class content. All code outside the markers is preserved. Use
  --source to point at an updated Prompty.php for version updates.

USAGE;
  fwrite(STDERR, $usage);
  exit(1);
}

if ($stdout) {
  // --stdout mode: no target script needed, just an output file.
  $stdout_path = $positional[0];
}
else {
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

$embed_source = $source_override ?? EMBED_SOURCE;

if (!is_file($embed_source)) {
  fwrite(STDERR, sprintf('Error: Source class not found: %s%s', $embed_source, PHP_EOL));
  exit(1);
}

$source = file_get_contents($embed_source);

if ($source === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read source class: %s%s', $embed_source, PHP_EOL));
  exit(1);
}

$tokens = token_get_all($source);

// Find the class header docblock: the T_DOC_COMMENT immediately before
// T_CLASS token.
$class_header_index = NULL;
$token_count = count($tokens);

for ($i = 0; $i < $token_count; $i++) {
  if (!is_array($tokens[$i])) {
    continue;
  }

  if ($tokens[$i][0] !== T_CLASS) {
    continue;
  }

  for ($j = $i - 1; $j >= 0; $j--) {
    if (!is_array($tokens[$j])) {
      break;
    }

    if ($tokens[$j][0] === T_DOC_COMMENT) {
      $class_header_index = $j;
      break;
    }

    if ($tokens[$j][0] !== T_WHITESPACE) {
      break;
    }
  }

  break;
}

// Build minified output: strip preamble and comments, keep class header.
$output = '';
$skip_preamble = TRUE;

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
    $skip_preamble = FALSE;
    continue;
  }

  if ($token_type === T_COMMENT || $token_type === T_DOC_COMMENT) {
    if ($i === $class_header_index) {
      $output .= $token_value;
    }
    continue;
  }

  if ($skip_preamble && $token_type === T_WHITESPACE) {
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
if ($compact && $class_start !== NULL) {
  // Work on the class line only.
  $class_line = end($result_lines);

  // Wrap in <?php for tokenization.
  $php_code = '<?php ' . $class_line;
  $ctokens = token_get_all($php_code);
  $ctokens_count = count($ctokens);

  // --- Step 1: Collect protected/private property and method names. ---
  $protected_props = [];
  $protected_methods = [];
  $public_method_params = [];

  $visibility = NULL;
  $is_static = FALSE;

  for ($i = 0; $i < $ctokens_count; $i++) {
    if (!is_array($ctokens[$i])) {
      continue;
    }

    $token_type = $ctokens[$i][0];

    if ($token_type === T_PUBLIC) {
      $visibility = 'public';
      $is_static = FALSE;
      continue;
    }

    if ($token_type === T_PROTECTED || $token_type === T_PRIVATE) {
      $visibility = 'protected';
      $is_static = FALSE;
      continue;
    }

    if ($token_type === T_STATIC) {
      $is_static = TRUE;
      continue;
    }

    if ($token_type === T_VARIABLE && $visibility !== NULL) {
      $prop_name = substr($ctokens[$i][1], 1);

      // Treat as a property unless the preceding non-whitespace token is
      // T_FUNCTION.
      $is_property = TRUE;
      for ($k = $i - 1; $k >= 0; $k--) {
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
          continue;
        }
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_FUNCTION) {
          $is_property = FALSE;
        }
        break;
      }

      if ($is_property && $visibility !== 'public') {
        $protected_props[] = $prop_name;
      }
      $visibility = NULL;
      $is_static = FALSE;
      continue;
    }

    if ($token_type === T_FUNCTION) {
      for ($k = $i + 1; $k < $ctokens_count; $k++) {
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
          continue;
        }
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_STRING) {
          $method_name = $ctokens[$k][1];

          if (!str_starts_with($method_name, '__')) {
            if ($visibility !== 'public') {
              $protected_methods[] = $method_name;
            }
            else {
              $paren_depth = 0;
              for ($m = $k + 1; $m < $ctokens_count; $m++) {
                if (is_string($ctokens[$m]) && $ctokens[$m] === '(') {
                  $paren_depth++;
                }
                elseif (is_string($ctokens[$m]) && $ctokens[$m] === ')') {
                  $paren_depth--;
                  if ($paren_depth <= 0) {
                    break;
                  }
                }
                elseif (is_array($ctokens[$m]) && $ctokens[$m][0] === T_VARIABLE && $paren_depth > 0) {
                  $public_method_params[substr($ctokens[$m][1], 1)] = TRUE;
                }
              }
            }
          }
        }
        break;
      }
      $visibility = NULL;
      $is_static = FALSE;
      continue;
    }

    // Reset visibility on any unexpected token.
    if (!in_array($token_type, [T_WHITESPACE, T_STRING, T_ARRAY, T_NS_SEPARATOR], TRUE) && $ctokens[$i][1] !== '?') {
      $visibility = NULL;
      $is_static = FALSE;
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
  for ($i = 0; $i < $ctokens_count; $i++) {
    if (!is_array($ctokens[$i])) {
      continue;
    }
    if ($ctokens[$i][0] !== T_VARIABLE) {
      continue;
    }
    $var_name = substr($ctokens[$i][1], 1);

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

  for ($i = 1; $i < $ctokens_count; $i++) {
    if (is_string($ctokens[$i])) {
      $compact_output .= $ctokens[$i];
      continue;
    }

    $token_type = $ctokens[$i][0];
    $token_value = $ctokens[$i][1];

    if ($token_type === T_VARIABLE) {
      $var_name = substr($token_value, 1);

      // Property reference after -> : the variable itself stays, the
      // property name is handled as T_STRING after T_OBJECT_OPERATOR.
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
      $prev = NULL;
      for ($k = $i - 1; $k >= 0; $k--) {
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
          continue;
        }
        $prev = is_array($ctokens[$k]) ? $ctokens[$k][0] : $ctokens[$k];
        break;
      }

      if ($prev === T_OBJECT_OPERATOR || $prev === T_NULLSAFE_OBJECT_OPERATOR) {
        $next = NULL;
        for ($k = $i + 1; $k < $ctokens_count; $k++) {
          if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
            continue;
          }
          $next = is_array($ctokens[$k]) ? $ctokens[$k][1] : $ctokens[$k];
          break;
        }

        if ($next === '(' && isset($method_map[$token_value])) {
          $compact_output .= $method_map[$token_value];
          continue;
        }

        if ($next !== '(' && isset($prop_map[$token_value])) {
          $compact_output .= $prop_map[$token_value];
          continue;
        }
      }

      if ($prev === T_DOUBLE_COLON && isset($method_map[$token_value])) {
        $next = NULL;
        for ($k = $i + 1; $k < $ctokens_count; $k++) {
          if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
            continue;
          }
          $next = is_array($ctokens[$k]) ? $ctokens[$k][1] : $ctokens[$k];
          break;
        }
        if ($next === '(') {
          $compact_output .= $method_map[$token_value];
          continue;
        }
      }

      if ($prev === T_FUNCTION && isset($method_map[$token_value])) {
        $compact_output .= $method_map[$token_value];
        continue;
      }

      $compact_output .= $token_value;
      continue;
    }

    $compact_output .= $token_value;
  }

  // --- Step 4: Reduce whitespace. ---
  // Remove spaces around operators where PHP allows it.
  // Works on tokens so string contents are not modified.
  $ws_tokens = token_get_all('<?php ' . $compact_output);
  $ws_count = count($ws_tokens);
  $ws_output = '';

  for ($i = 1; $i < $ws_count; $i++) {
    if (!is_array($ws_tokens[$i])) {
      $ws_output .= $ws_tokens[$i];
      continue;
    }

    $token_type = $ws_tokens[$i][0];
    $token_value = $ws_tokens[$i][1];

    if ($token_type === T_WHITESPACE) {
      $before = is_array($ws_tokens[$i - 1]) ? $ws_tokens[$i - 1][1] : $ws_tokens[$i - 1];
      $after = '';
      if ($i + 1 < $ws_count) {
        $after = is_array($ws_tokens[$i + 1]) ? $ws_tokens[$i + 1][1] : $ws_tokens[$i + 1];
      }

      // Remove the space when either neighbour is a non-word character.
      $before_last = $before !== '' ? $before[strlen($before) - 1] : '';
      $after_first = $after !== '' ? $after[0] : '';

      $before_is_word = preg_match('/[a-zA-Z0-9_$]/', $before_last);
      $after_is_word = preg_match('/[a-zA-Z0-9_$\\\\]/', $after_first);

      if ($before_is_word && $after_is_word) {
        // Space is needed between two word characters.
        $ws_output .= ' ';
      }
      continue;
    }

    $ws_output .= $token_value;
  }

  $result_lines = $before_class;
  $result_lines[] = $ws_output;
  $minified = implode("\n", $result_lines);
}

$namespace = '';
if (preg_match('/^\s*namespace\s+(.+?)\s*;/m', $source, $ns_match)) {
  $namespace = $ns_match[1];
}

$class_content = trim($minified) . "\n";

$class_content = preg_replace('/^(class\s)/m', "// @phpstan-ignore-next-line\n$1", $class_content, 1);

// Run Rector on the processed class content if available.
$rector_bin = __DIR__ . '/vendor/bin/rector';
$rector_config = __DIR__ . '/rector.php';

if (is_file($rector_bin) && is_file($rector_config)) {
  $tmp_dir = __DIR__ . '/.artifacts/tmp';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }
  $rector_tmp = tempnam($tmp_dir, 'embed_rector_');
  rename($rector_tmp, $rector_tmp . '.php');
  $rector_tmp .= '.php';

  // Wrap class content in a valid PHP file for rector.
  $rector_input = "<?php\n\ndeclare(strict_types=1);\n\n" . $class_content;
  file_put_contents($rector_tmp, $rector_input);

  $rector_output = [];
  $rector_exit = 0;
  exec(
    'php ' . escapeshellarg($rector_bin) . ' process --no-ansi --config=' . escapeshellarg($rector_config) . ' ' . escapeshellarg($rector_tmp) . ' 2>&1',
    $rector_output,
    $rector_exit,
  );

  if ($rector_exit <= 1) {
    $rector_result = file_get_contents($rector_tmp);
    if ($rector_result !== FALSE) {
      $rector_result = preg_replace('/^<\?php\s+declare\(strict_types\s*=\s*1\)\s*;\s*/s', '', $rector_result);
      $class_content = trim((string) $rector_result) . "\n";
    }
  }

  unlink($rector_tmp);
  echo sprintf('Rector: processed.%s', PHP_EOL);
}

if ($stdout) {
  // --stdout mode: write a standalone PHP file.
  $standalone = "<?php\n\ndeclare(strict_types=1);\n\n";
  if ($namespace !== '') {
    $standalone .= "namespace {$namespace};\n\n";
  }
  $standalone .= $class_content;

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

// Build and inject the embedded block.
$embedded = '// ' . EMBED_MARKER_START . "\n";
$embedded .= $class_content;
$embedded .= '// ' . EMBED_MARKER_END;

$target = file_get_contents($target_path);

if ($target === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read target file: %s%s', $target_path, PHP_EOL));
  exit(1);
}

$marker_start_re = preg_quote(EMBED_MARKER_START, '/');
$marker_end_re = preg_quote(EMBED_MARKER_END, '/');
$pattern = '/^[ \t]*\/\/\s*' . $marker_start_re . '\b.*?^[ \t]*\/\/\s*' . $marker_end_re . '\b[^\n]*/ms';

if (!preg_match($pattern, $target)) {
  fwrite(STDERR, sprintf('Error: Could not find // %s and // %s markers in target file.%s', EMBED_MARKER_START, EMBED_MARKER_END, PHP_EOL));
  exit(1);
}

// Escape backslashes and dollar signs in the replacement to prevent
// preg_replace from interpreting \033 as backreference \0 + "33".
$result = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $embedded), $target, 1);

if ($result === NULL) {
  fwrite(STDERR, sprintf('Error: Failed to replace marker region.%s', PHP_EOL));
  exit(1);
}

$result = preg_replace('/\n{3,}/', "\n\n", $result);

// Inject kill switch if not present and not opted out.
$has_killswitch = str_contains((string) $result, "if (!getenv('SHOULD_PROCEED'))");

if (!$has_killswitch && !$no_killswitch) {
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

$final_content = file_get_contents($target_path);
$final_has_killswitch = $final_content !== FALSE && str_contains($final_content, "if (!getenv('SHOULD_PROCEED'))");

if (!$final_has_killswitch) {
  fwrite(STDERR, sprintf('Warning: No kill switch found in the target file. Please test the embedded script manually.%s', PHP_EOL));
}
elseif (posix_isatty(STDIN)) {
  echo sprintf('Verifying embedded script (interactive input required)...%s', PHP_EOL);

  $verify_exit = 0;
  passthru('php ' . escapeshellarg($target_path), $verify_exit);

  if ($verify_exit !== 0) {
    fwrite(STDERR, sprintf('Warning: Embedded script exited with code %d.%s', $verify_exit, PHP_EOL));
  }
}
else {
  echo sprintf('Verifying embedded script...%s', PHP_EOL);

  // Non-interactive mode: pipe keystrokes to simulate a quick run.
  $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $verify_proc = proc_open('php ' . escapeshellarg($target_path), $descriptors, $verify_pipes);

  if (is_resource($verify_proc)) {
    // Send minimal input to get through the flow, then close stdin.
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
