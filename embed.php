<?php

/**
 * @file
 * Embedder — minifies and embeds a PHP class into a target script.
 *
 * Part of the Prompty project.
 *
 * @see https://github.com/AlexSkrypnyk/prompty
 *
 * Usage:
 *   php embed.php [--compact] [--no-killswitch] <source>
 *                 [<output>]
 *   php embed.php [--compact] --stdout <output-file>
 *
 * The source script must contain // @embed-start and // @embed-end markers.
 * The minified class will be inserted between these markers.
 *
 * If <output-script> is provided, the source is copied there first and the
 * embedding is performed on the copy. Otherwise the source is modified
 * in place.
 *
 * After embedding, if Rector is available (vendor/bin/rector), it will be
 * run on the processed class content. A kill-switch block is injected after
 * the embed region if one is not already present. The embedded script is
 * then run to verify it works.
 *
 * Options:
 *   --compact        Apply additional size optimizations: shorten internal
 *                    property and method names, rename local variables,
 *                    reduce whitespace.
 *   --stdout         Output the processed class as a standalone PHP file
 *                    instead of embedding into a target script. Requires an
 *                    output file path.
 *   --no-killswitch  Skip injecting the kill-switch block and post-embed
 *                    verification run.
 */

declare(strict_types=1);

// Configuration — adjust these when reusing for a different project.
// Path to the PHP class file to embed (relative to this script).
define('EMBED_SOURCE', __DIR__ . '/Prompty.php');

// Marker comments in the target script.
define('EMBED_MARKER_START', '@embed-start');
define('EMBED_MARKER_END', '@embed-end');

// Argument parsing.
$compact = FALSE;
$stdout = FALSE;
$no_killswitch = FALSE;
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
  else {
    $positional[] = $argv[$arg_i];
  }
}

if ($positional === []) {
  fwrite(STDERR, "Usage: php embed.php [--compact] [--no-killswitch] [--stdout] <source-script> [<output-script>]\n");
  exit(1);
}

if ($stdout) {
  // --stdout mode: no source script needed, just an output file.
  $stdout_path = $positional[0];
}
else {
  $input_path = $positional[0];

  if (!is_file($input_path)) {
    fwrite(STDERR, sprintf('Error: Source file not found: %s%s', $input_path, PHP_EOL));
    exit(1);
  }

  // If output path is provided, copy source there first.
  $target_path = $positional[1] ?? $input_path;

  if (isset($positional[1]) && !copy($input_path, $target_path)) {
    fwrite(STDERR, sprintf('Error: Could not copy source to output: %s%s', $target_path, PHP_EOL));
    exit(1);
  }
}

if (!is_file(EMBED_SOURCE)) {
  fwrite(STDERR, sprintf('Error: Source class not found: %s%s', EMBED_SOURCE, PHP_EOL));
  exit(1);
}

// Read and tokenize the source class.
$source = file_get_contents(EMBED_SOURCE);

if ($source === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read source class: %s%s', EMBED_SOURCE, PHP_EOL));
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

  // Walk backwards from the class token to find the preceding docblock.
  for ($j = $i - 1; $j >= 0; $j--) {
    if (!is_array($tokens[$j])) {
      break;
    }

    if ($tokens[$j][0] === T_DOC_COMMENT) {
      $class_header_index = $j;
      break;
    }

    // Skip whitespace when walking backwards.
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

  // Skip <?php tag.
  if ($token_type === T_OPEN_TAG) {
    continue;
  }

  // Skip declare(strict_types=1).
  if ($token_type === T_DECLARE) {
    // Skip everything until the semicolon after the declare statement.
    while ($i < $token_count) {
      $i++;
      if (is_string($tokens[$i]) && $tokens[$i] === ';') {
        break;
      }
    }
    continue;
  }

  // Skip the namespace declaration (we re-add it in the embedded output).
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

  // Strip all comments except the class header docblock.
  if ($token_type === T_COMMENT || $token_type === T_DOC_COMMENT) {
    if ($i === $class_header_index) {
      $output .= $token_value;
    }
    continue;
  }

  // Skip leading whitespace from the preamble.
  if ($skip_preamble && $token_type === T_WHITESPACE) {
    continue;
  }

  $output .= $token_value;
}

// Trim trailing whitespace from each line.
$lines = explode("\n", $output);
$lines = array_map(static fn(string $l): string => preg_replace('/\s+$/', '', $l) ?? $l, $lines);

// Remove all blank lines.
$collapsed = array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));

// Collapse multi-line property array declarations to single lines.
$result_lines = [];
$i = 0;
$line_count = count($collapsed);

while ($i < $line_count) {
  $line = $collapsed[$i];

  // Only collapse property declarations (protected/public/private array).
  if (preg_match('/^\s*(protected|public|private)\s+array\s+\$\w+\s*=\s*\[$/', $line)) {
    $depth = 1;
    $parts = [preg_replace('/\s+$/', '', $line) ?? $line];
    $i++;

    while ($i < $line_count && $depth > 0) {
      $inner = preg_replace('/^\s+|\s+$/', '', $collapsed[$i]) ?? $collapsed[$i];
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
  $class_single = implode(' ', array_map(static fn(string $l): string => preg_replace('/^\s+|\s+$/', '', $l) ?? $l, $class_lines));
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

    $tt = $ctokens[$i][0];

    if ($tt === T_PUBLIC) {
      $visibility = 'public';
      $is_static = FALSE;
      continue;
    }

    if ($tt === T_PROTECTED || $tt === T_PRIVATE) {
      $visibility = 'protected';
      $is_static = FALSE;
      continue;
    }

    if ($tt === T_STATIC) {
      $is_static = TRUE;
      continue;
    }

    // Property declaration.
    if ($tt === T_VARIABLE && $visibility !== NULL) {
      $prop_name = substr($ctokens[$i][1], 1);

      // Check if next non-whitespace is '(' — that would make it a
      // method parameter, not a property.
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

    // Method declaration.
    if ($tt === T_FUNCTION) {
      // Find the method name.
      for ($k = $i + 1; $k < $ctokens_count; $k++) {
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
          continue;
        }
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_STRING) {
          $method_name = $ctokens[$k][1];

          // Skip magic methods.
          if (!str_starts_with($method_name, '__')) {
            if ($visibility !== 'public') {
              $protected_methods[] = $method_name;
            }
            else {
              // Collect public method parameter names.
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

    // Reset visibility if we hit something unexpected.
    if (!in_array($tt, [T_WHITESPACE, T_STRING, T_ARRAY, T_NS_SEPARATOR], TRUE) && $ctokens[$i][1] !== '?') {
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

  // Collect all variable names used in the class (excluding $this,
  // superglobals, and public method params).
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

    // Skip property declarations and references (after -> or ::$).
    if (isset($prop_map[$var_name])) {
      continue;
    }

    // Skip public method parameter names.
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

    $tt = $ctokens[$i][0];
    $tv = $ctokens[$i][1];

    // Rename variables.
    if ($tt === T_VARIABLE) {
      $var_name = substr($tv, 1);

      // Property reference after -> : the variable itself stays, the
      // property name is handled as T_STRING after T_OBJECT_OPERATOR.
      if (isset($var_map[$var_name])) {
        $compact_output .= '$' . $var_map[$var_name];
        continue;
      }

      // Static property reference: static::$propName.
      if (isset($prop_map[$var_name])) {
        $compact_output .= '$' . $prop_map[$var_name];
        continue;
      }

      $compact_output .= $tv;
      continue;
    }

    // Rename property/method access after -> or ::.
    if ($tt === T_STRING) {
      // Check if preceded by -> (object operator).
      $prev = NULL;
      for ($k = $i - 1; $k >= 0; $k--) {
        if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
          continue;
        }
        $prev = is_array($ctokens[$k]) ? $ctokens[$k][0] : $ctokens[$k];
        break;
      }

      if ($prev === T_OBJECT_OPERATOR || $prev === T_NULLSAFE_OBJECT_OPERATOR) {
        // Check if it's a method call (followed by '(') or property access.
        $next = NULL;
        for ($k = $i + 1; $k < $ctokens_count; $k++) {
          if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
            continue;
          }
          $next = is_array($ctokens[$k]) ? $ctokens[$k][1] : $ctokens[$k];
          break;
        }

        if ($next === '(' && isset($method_map[$tv])) {
          $compact_output .= $method_map[$tv];
          continue;
        }

        if ($next !== '(' && isset($prop_map[$tv])) {
          $compact_output .= $prop_map[$tv];
          continue;
        }
      }

      // Static method call: static::methodName(.
      if ($prev === T_DOUBLE_COLON && isset($method_map[$tv])) {
        $next = NULL;
        for ($k = $i + 1; $k < $ctokens_count; $k++) {
          if (is_array($ctokens[$k]) && $ctokens[$k][0] === T_WHITESPACE) {
            continue;
          }
          $next = is_array($ctokens[$k]) ? $ctokens[$k][1] : $ctokens[$k];
          break;
        }
        if ($next === '(') {
          $compact_output .= $method_map[$tv];
          continue;
        }
      }

      // Method declaration: function methodName.
      if ($prev === T_FUNCTION && isset($method_map[$tv])) {
        $compact_output .= $method_map[$tv];
        continue;
      }

      $compact_output .= $tv;
      continue;
    }

    $compact_output .= $tv;
  }

  // --- Step 4: Reduce whitespace. ---
  // Remove spaces around operators where PHP allows it.
  // Be careful not to modify string contents — work on tokens.
  $ws_tokens = token_get_all('<?php ' . $compact_output);
  $ws_count = count($ws_tokens);
  $ws_output = '';

  for ($i = 1; $i < $ws_count; $i++) {
    if (!is_array($ws_tokens[$i])) {
      $ws_output .= $ws_tokens[$i];
      continue;
    }

    $tt = $ws_tokens[$i][0];
    $tv = $ws_tokens[$i][1];

    // Collapse whitespace to a single space, then check if it can be removed.
    if ($tt === T_WHITESPACE) {
      // Check what comes before and after.
      $before = is_array($ws_tokens[$i - 1]) ? $ws_tokens[$i - 1][1] : $ws_tokens[$i - 1];
      $after = '';
      if ($i + 1 < $ws_count) {
        $after = is_array($ws_tokens[$i + 1]) ? $ws_tokens[$i + 1][1] : $ws_tokens[$i + 1];
      }

      // Remove space if surrounded by non-alphanumeric/non-$ on both sides.
      $before_last = $before !== '' ? $before[strlen($before) - 1] : '';
      $after_first = $after !== '' ? $after[0] : '';

      $before_is_word = preg_match('/[a-zA-Z0-9_$]/', $before_last);
      $after_is_word = preg_match('/[a-zA-Z0-9_$\\\\]/', $after_first);

      if ($before_is_word && $after_is_word) {
        // Space is needed between two word characters.
        $ws_output .= ' ';
      }
      // Otherwise, remove the space entirely.
      continue;
    }

    $ws_output .= $tv;
  }

  // Replace the class line in result_lines.
  $result_lines = $before_class;
  $result_lines[] = $ws_output;
  $minified = implode("\n", $result_lines);
}

// Extract namespace from source.
$namespace = '';
if (preg_match('/^\s*namespace\s+(.+?)\s*;/m', $source, $ns_match)) {
  $namespace = $ns_match[1];
}

$class_content = preg_replace('/^\s+|\s+$/', '', $minified) . "\n";

// Add phpstan-ignore-next-line before the class declaration.
$class_content = preg_replace('/^(class\s)/m', "// @phpstan-ignore-next-line\n$1", $class_content, 1);

// Run Rector on the processed class content if available.
$rector_bin = __DIR__ . '/vendor/bin/rector';
$rector_config = __DIR__ . '/rector.php';

if (is_file($rector_bin) && is_file($rector_config)) {
  $rector_tmp = tempnam(sys_get_temp_dir(), 'embed_rector_');
  rename($rector_tmp, $rector_tmp . '.php');
  $rector_tmp .= '.php';

  // Wrap class content in a valid PHP file for rector.
  $rector_input = "<?php\n\ndeclare(strict_types=1);\n\n" . $class_content;
  file_put_contents($rector_tmp, $rector_input);

  $rector_output = [];
  $rector_exit = 0;
  exec('php ' . escapeshellarg($rector_bin) . ' process --no-ansi --config=' . escapeshellarg($rector_config) . ' ' . escapeshellarg($rector_tmp) . ' 2>&1', $rector_output, $rector_exit);

  if ($rector_exit <= 1) {
    // Read back the processed content and strip the PHP preamble.
    $rector_result = file_get_contents($rector_tmp);
    if ($rector_result !== FALSE) {
      $rector_result = preg_replace('/^<\?php\s+declare\(strict_types\s*=\s*1\)\s*;\s*/s', '', $rector_result);
      $class_content = preg_replace('/^\s+|\s+$/', '', (string) $rector_result) . "\n";
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

  // Validate with php -l.
  $lint_output = [];
  $lint_exit = 0;
  exec('php -l ' . escapeshellarg($stdout_path) . ' 2>&1', $lint_output, $lint_exit);

  if ($lint_exit !== 0) {
    fwrite(STDERR, "Error: PHP lint failed:\n");
    fwrite(STDERR, implode("\n", $lint_output) . "\n");
    exit(1);
  }

  echo sprintf('Written to %s%s', $stdout_path, PHP_EOL);
  exit(0);
}

// Build and inject the embedded block.
$embedded = '// ' . EMBED_MARKER_START . "\n";
$embedded .= $class_content;
$embedded .= '// ' . EMBED_MARKER_END;

// Read target file and replace marker region.
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
  fwrite(STDERR, "Error: Failed to replace marker region.\n");
  exit(1);
}

// Remove 'use' statements for the embedded namespace since the class is now
// embedded inline, then collapse any resulting consecutive blank lines.
if ($namespace !== '') {
  $ns_escaped = preg_quote($namespace, '/');
  $result = preg_replace('/^use\s+' . $ns_escaped . '\\\\[^;]+;\n/m', '', $result);
}
$result = preg_replace('/\n{3,}/', "\n\n", $result ?? '');

// Inject kill switch if not present and not opted out.
$has_killswitch = str_contains((string) $result, "if (!getenv('SHOULD_PROCEED'))");

if (!$has_killswitch && !$no_killswitch) {
  $killswitch = <<<'KILLSWITCH'

// Kill switch — stop here when running under tests.
// In production, set SHOULD_PROCEED=1 to continue past this point.
if (!getenv('SHOULD_PROCEED')) {
  return;
}
KILLSWITCH;

  // Insert after the // phpcs:enable line if present, otherwise
  // after @embed-end.
  if (str_contains((string) $result, '// phpcs:enable')) {
    $result = preg_replace('/(\/\/ phpcs:enable\n)/', '$1' . $killswitch, (string) $result, 1);
  }
  else {
    $result = preg_replace('/(\/\/ ' . preg_quote(EMBED_MARKER_END, '/') . '[^\n]*\n)/', '$1' . $killswitch, (string) $result, 1);
  }
}

// Write the result.
file_put_contents($target_path, $result);

// Validate with php -l.
$lint_output = [];
$lint_exit = 0;
exec('php -l ' . escapeshellarg($target_path) . ' 2>&1', $lint_output, $lint_exit);

if ($lint_exit !== 0) {
  fwrite(STDERR, "Error: PHP lint failed after embedding:\n");
  fwrite(STDERR, implode("\n", $lint_output) . "\n");
  exit(1);
}

echo sprintf('Embedded into %s%s', $target_path, PHP_EOL);

// Check final kill switch state for warnings.
$final_content = file_get_contents($target_path);
$final_has_killswitch = $final_content !== FALSE && str_contains($final_content, "if (!getenv('SHOULD_PROCEED'))");

if (!$final_has_killswitch) {
  echo "\033[33mWarning: No kill switch found in the target file. Please test the embedded script manually.\033[0m" . PHP_EOL;
}
elseif (posix_isatty(STDIN)) {
  // Run the embedded script to verify it works (only in interactive mode).
  echo sprintf('Verifying embedded script (interactive input required)...%s', PHP_EOL);

  $verify_exit = 0;
  passthru('php ' . escapeshellarg($target_path), $verify_exit);

  if ($verify_exit !== 0) {
    fwrite(STDERR, sprintf("Warning: Embedded script exited with code %d.%s", $verify_exit, PHP_EOL));
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
      fwrite(STDERR, sprintf("Warning: Embedded script exited with code %d.%s", $verify_exit, PHP_EOL));
    }
  }
}
