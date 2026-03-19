<?php

/**
 * @file
 * Prompty Embedder — minifies and embeds Prompty.php into a target script.
 *
 * Part of the Prompty project.
 *
 * @see https://github.com/AlexSkrypnyk/prompty
 *
 * Usage:
 *   php embed.php <source-script> [<output-script>]
 *
 * The source script must contain // @prompty-start and // @prompty-end markers.
 * The minified Prompty class will be inserted between these markers.
 *
 * If <output-script> is provided, the source is copied there first and the
 * embedding is performed on the copy. Otherwise the source is modified
 * in place.
 */

declare(strict_types=1);

// Validate arguments.
if (!isset($argv[1])) {
  fwrite(STDERR, "Usage: php embed.php <source-script> [<output-script>]\n");
  exit(1);
}

$input_path = $argv[1];

if (!is_file($input_path)) {
  fwrite(STDERR, sprintf('Error: Source file not found: %s%s', $input_path, PHP_EOL));
  exit(1);
}

// If output path is provided, copy source there first.
$target_path = $argv[2] ?? $input_path;

if (isset($argv[2]) && !copy($input_path, $target_path)) {
  fwrite(STDERR, sprintf('Error: Could not copy source to output: %s%s', $target_path, PHP_EOL));
  exit(1);
}

$source_path = __DIR__ . '/Prompty.php';

if (!is_file($source_path)) {
  fwrite(STDERR, "Error: Prompty.php not found in script directory.\n");
  exit(1);
}

// Read and tokenize Prompty.php.
$source = file_get_contents($source_path);

if ($source === FALSE) {
  fwrite(STDERR, "Error: Could not read Prompty.php.\n");
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
  if (preg_match('/^class\s+Prompty\b/', $result_line)) {
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

// Build the embedded block.
$embedded = "// @prompty-start\n";
$embedded .= "namespace AlexSkrypnyk\\Prompty;\n";
$embedded .= "\n";
$embedded .= preg_replace('/^\s+|\s+$/', '', $minified) . "\n";
$embedded .= "// @prompty-end";

// Read target file and replace marker region.
$target = file_get_contents($target_path);

if ($target === FALSE) {
  fwrite(STDERR, sprintf('Error: Could not read target file: %s%s', $target_path, PHP_EOL));
  exit(1);
}

$pattern = '/^[ \t]*\/\/\s*@prompty-start\b.*?^[ \t]*\/\/\s*@prompty-end\b[^\n]*/ms';

if (!preg_match($pattern, $target)) {
  fwrite(STDERR, "Error: Could not find // @prompty-start and // @prompty-end markers in target file.\n");
  exit(1);
}

// Escape backslashes and dollar signs in the replacement to prevent
// preg_replace from interpreting \033 as backreference \0 + "33".
$result = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $embedded), $target, 1);

if ($result === NULL) {
  fwrite(STDERR, "Error: Failed to replace marker region.\n");
  exit(1);
}

// Remove 'use' statements for the Prompty namespace since the class is now
// embedded inline, then collapse any resulting consecutive blank lines.
$result = preg_replace('/^use\s+AlexSkrypnyk\\\\Prompty\\\\[^;]+;\n/m', '', $result);
$result = preg_replace('/\n{3,}/', "\n\n", $result ?? '');

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

echo sprintf('Embedded Prompty into %s%s', $target_path, PHP_EOL);
