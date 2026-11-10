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

// Docblock tags for static analysis tools, which the embedded class does not
// need.
define('EMBED_TOOL_TAG_PATTERN', '/^\s*\*\s*@(?:phpstan|psalm|phan)-/');

define('EMBED_SHORT_NAME_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

define('EMBED_RESERVED_VARIABLES', [
  'this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'argc', 'argv',
]);

// Short method names avoid keywords, so a renamed call never reads as one.
define('EMBED_RESERVED_WORDS', [
  'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
  'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends',
  'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'interface',
  'isset', 'list', 'match', 'namespace', 'new', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'return', 'self',
  'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield',
]);

// These read or write variables by name, so renaming breaks them.
define('EMBED_NAME_SENSITIVE_FUNCTIONS', ['compact', 'extract', 'get_defined_vars']);

// Tokens that can sit between a visibility keyword and the member it applies
// to.
define('EMBED_MODIFIER_TOKENS', [
  T_WHITESPACE, T_STRING, T_ARRAY, T_STATIC, T_READONLY, T_FINAL, T_ABSTRACT, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED,
]);

define('EMBED_AMPERSAND_TOKENS', [T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG]);

// Tokens before a name that make it a member or a declaration.
define('EMBED_MEMBER_TOKENS', [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION]);

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
 * Tells whether a token is '&'.
 *
 * @param array{int, string, int}|string $token
 *   A token from token_get_all().
 *
 * @return bool
 *   TRUE for '&' in any of its token types.
 */
function embed_is_ampersand(array|string $token): bool {
  return $token === '&' || (is_array($token) && in_array($token[0], EMBED_AMPERSAND_TOKENS, TRUE));
}

/**
 * Removes static analysis tags from a docblock.
 *
 * A tag runs to the next tag, blank line or the end of the docblock. A blank
 * line left doubled or before the end is dropped.
 *
 * @param string $docblock
 *   The docblock.
 *
 * @return string
 *   The docblock without the tags.
 */
function embed_docblock_strip_tool_tags(string $docblock): string {
  $lines = [];
  $in_tag = FALSE;

  foreach (explode("\n", $docblock) as $line) {
    if ($in_tag && preg_match('/^\s*\*(?:\s*$|\s*@|\/)/', $line) !== 1) {
      continue;
    }

    $in_tag = preg_match(EMBED_TOOL_TAG_PATTERN, $line) === 1;

    if ($in_tag) {
      continue;
    }

    $is_blank = preg_match('/^\s*\*\s*$/', $line) === 1;
    $is_end = str_ends_with(rtrim($line), '*/');

    if (($is_blank || $is_end) && $lines !== [] && preg_match('/^\s*\*\s*$/', $lines[count($lines) - 1]) === 1) {
      array_pop($lines);
    }

    $lines[] = $line;
  }

  return implode("\n", $lines);
}

/**
 * Returns the short name at a position: 'a' to 'Z', then 'aa' onwards.
 *
 * @param int $index
 *   The position, from 0.
 *
 * @return string
 *   The short name.
 */
function embed_short_name(int $index): string {
  $base = strlen(EMBED_SHORT_NAME_ALPHABET);
  $name = '';

  do {
    $name = EMBED_SHORT_NAME_ALPHABET[$index % $base] . $name;
    $index = intdiv($index, $base) - 1;
  } while ($index >= 0);

  return $name;
}

/**
 * Assigns short names, the shortest to the most used name.
 *
 * @param array<string, int> $counts
 *   Uses keyed by name, in order of first use.
 * @param array<string, bool> $reserved
 *   Lowercase names that no short name may take.
 * @param bool $case_insensitive
 *   Whether 2 short names that differ only in case would collide, as method
 *   names do.
 *
 * @return array<string, string>
 *   Short names keyed by name.
 */
function embed_short_names(array $counts, array $reserved, bool $case_insensitive = FALSE): array {
  // The sort is stable, so names used equally often keep their order.
  arsort($counts);

  $map = [];
  $index = 0;

  foreach (array_keys($counts) as $name) {
    do {
      $short = embed_short_name($index);
      $index++;
    } while (isset($reserved[strtolower($short)]));

    if ($case_insensitive) {
      $reserved[strtolower($short)] = TRUE;
    }

    $map[(string) $name] = $short;
  }

  return $map;
}

/**
 * Rejects a class that refers to variables by name.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 *
 * @throws \RuntimeException
 *   When the class uses a variable variable, 'global', "${...}"
 *   interpolation or a function that reads variables by name.
 */
function embed_compact_assert_supported(array $tokens): void {
  foreach ($tokens as $index => $token) {
    $construct = match (TRUE) {
      $token === '$' => 'a variable variable',
      is_array($token) && $token[0] === T_GLOBAL => '"global"',
      is_array($token) && $token[0] === T_STRING_VARNAME => '"${...}" interpolation',
      default => NULL,
    };

    if ($construct === NULL && is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], TRUE) && in_array(strtolower(ltrim($token[1], '\\')), EMBED_NAME_SENSITIVE_FUNCTIONS, TRUE)) {
      $previous = find_significant_token($tokens, $index - 1, -1);
      $next = find_significant_token($tokens, $index + 1, 1);
      $is_member = $previous !== NULL && is_array($tokens[$previous]) && in_array($tokens[$previous][0], EMBED_MEMBER_TOKENS, TRUE);

      if (!$is_member && $next !== NULL && $tokens[$next] === '(') {
        $construct = ltrim($token[1], '\\') . '()';
      }
    }

    if ($construct !== NULL) {
      throw new \RuntimeException(sprintf('The class uses %s, which refers to variables by name, so --compact cannot rename them.', $construct));
    }
  }
}

/**
 * Reads the name, parameters and extent of a method.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 * @param int $index
 *   The index of the method's 'function' token.
 * @param bool $public
 *   Whether the method is public.
 *
 * @return array{name: string, public: bool, start: int, body: int|null, end: int, parameters: list<string>, references: list<string>}
 *   The method, with the index of its 'function' token, of the '{' that opens
 *   its body or NULL when it has none, and of its last token. The
 *   parameters, and those of them passed by reference.
 *
 * @throws \RuntimeException
 *   When the method has no name, returns by reference, promotes a
 *   constructor parameter, or has no end.
 */
function embed_compact_method(array $tokens, int $index, bool $public): array {
  $count = count($tokens);
  $name_index = find_significant_token($tokens, $index + 1, 1);

  if ($name_index !== NULL && embed_is_ampersand($tokens[$name_index])) {
    throw new \RuntimeException('The class has a method that returns by reference, which --compact does not support.');
  }

  if ($name_index === NULL || !is_array($tokens[$name_index]) || $tokens[$name_index][0] !== T_STRING) {
    throw new \RuntimeException('The class has a "function" keyword outside a method body that is not a method declaration.');
  }

  $name = $tokens[$name_index][1];
  $parameters = [];
  $references = [];
  $by_reference = FALSE;
  $depth = 0;
  $i = $name_index + 1;

  for (; $i < $count; $i++) {
    $token = $tokens[$i];

    if ($token === '(') {
      $depth++;
    }
    elseif ($token === ')') {
      $depth--;

      if ($depth === 0) {
        break;
      }
    }
    elseif (is_array($token) && in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY], TRUE)) {
      throw new \RuntimeException(sprintf('Method %s() promotes a constructor parameter, which --compact does not support.', $name));
    }
    elseif (embed_is_ampersand($token)) {
      $by_reference = TRUE;
    }
    elseif (is_array($token) && $token[0] === T_VARIABLE) {
      $parameters[] = substr($token[1], 1);

      if ($by_reference) {
        $references[] = substr($token[1], 1);
      }

      $by_reference = FALSE;
    }
  }

  $method = [
    'name' => $name,
    'public' => $public,
    'start' => $index,
    'body' => NULL,
    'end' => $i,
    'parameters' => $parameters,
    'references' => $references,
  ];
  $depth = 0;

  for ($i++; $i < $count; $i++) {
    $token = $tokens[$i];

    // An abstract method ends at its signature.
    if ($depth === 0 && $token === ';') {
      return ['end' => $i] + $method;
    }

    if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE))) {
      $method['body'] ??= $i;
      $depth++;
    }
    elseif ($token === '}') {
      $depth--;

      if ($depth === 0) {
        return ['end' => $i] + $method;
      }
    }
  }

  throw new \RuntimeException(sprintf('Method %s() has no end.', $name));
}

/**
 * Lists the properties and methods of a class.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 *
 * @return array{properties: array<string, bool>, methods: list<array{name: string, public: bool, start: int, body: int|null, end: int, parameters: list<string>, references: list<string>}>}
 *   Whether each property is public, keyed by name, and the methods in
 *   source order.
 *
 * @throws \RuntimeException
 *   When a method cannot be read.
 */
function embed_compact_members(array $tokens): array {
  $properties = [];
  $methods = [];
  $visibility = NULL;
  $count = count($tokens);

  for ($i = 0; $i < $count; $i++) {
    if (!is_array($tokens[$i])) {
      continue;
    }

    $token_type = $tokens[$i][0];

    if (in_array($token_type, [T_PUBLIC, T_PROTECTED, T_PRIVATE], TRUE)) {
      $visibility = $token_type;
      continue;
    }

    if ($token_type === T_VARIABLE && $visibility !== NULL) {
      $properties[substr($tokens[$i][1], 1)] = $visibility === T_PUBLIC;
      $visibility = NULL;
      continue;
    }

    // A method without a visibility keyword is public. Skipping its body
    // leaves only class-level tokens for this loop.
    if ($token_type === T_FUNCTION) {
      $method = embed_compact_method($tokens, $i, $visibility === NULL || $visibility === T_PUBLIC);
      $methods[] = $method;
      $i = $method['end'];
      $visibility = NULL;
      continue;
    }

    if (!in_array($token_type, EMBED_MODIFIER_TOKENS, TRUE)) {
      $visibility = NULL;
    }
  }

  return ['properties' => $properties, 'methods' => $methods];
}

/**
 * Collects the names that calls pass arguments by.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 *
 * @return array<string, array<string, bool>>
 *   Argument names keyed by the lowercase name of the function or method
 *   called, '__construct' for a call with 'new', or '' when the callee has
 *   no name, such as a closure in a variable.
 */
function embed_compact_named_arguments(array $tokens): array {
  $named = [];
  $callees = [];
  $count = count($tokens);

  for ($i = 0; $i < $count; $i++) {
    $token = $tokens[$i];

    if ($token === '(') {
      $callees[] = embed_compact_callee($tokens, $i);
      continue;
    }

    if ($token === ')') {
      array_pop($callees);
      continue;
    }

    // A name that is a keyword, such as 'default', has its own token type.
    if (!is_array($token) || $callees === [] || preg_match('/^[A-Za-z_]\w*$/', $token[1]) !== 1) {
      continue;
    }

    $previous = find_significant_token($tokens, $i - 1, -1);
    $next = find_significant_token($tokens, $i + 1, 1);

    if ($previous !== NULL && $next !== NULL && in_array($tokens[$previous], ['(', ','], TRUE) && $tokens[$next] === ':') {
      $named[$callees[count($callees) - 1]][$token[1]] = TRUE;
    }
  }

  return $named;
}

/**
 * Names the function or method that an opening parenthesis calls.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 * @param int $index
 *   The index of the '(' token.
 *
 * @return string
 *   The lowercase name, '__construct' after 'new', or '' when there is no
 *   name.
 */
function embed_compact_callee(array $tokens, int $index): string {
  $name_index = find_significant_token($tokens, $index - 1, -1);

  if ($name_index === NULL || !is_array($tokens[$name_index])) {
    return '';
  }

  $previous = find_significant_token($tokens, $name_index - 1, -1);

  if ($previous !== NULL && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW) {
    return '__construct';
  }

  return $tokens[$name_index][0] === T_STRING ? strtolower($tokens[$name_index][1]) : '';
}

/**
 * Picks the parameters whose body uses are shorter through an alias.
 *
 * The alias is assigned at the top of the body, so it costs its assignment
 * and saves the difference in length on every use.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 * @param array{name: string, public: bool, start: int, body: int|null, end: int, parameters: list<string>, references: list<string>} $method
 *   The method.
 * @param array<int, string> $candidates
 *   Parameters that keep their names in the signature.
 *
 * @return list<string>
 *   The parameters to alias, in signature order.
 */
function embed_compact_aliases(array $tokens, array $method, array $candidates): array {
  if ($method['body'] === NULL || $candidates === []) {
    return [];
  }

  $uses = array_fill_keys($candidates, 0);

  for ($i = $method['body']; $i <= $method['end']; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE || !isset($uses[substr($tokens[$i][1], 1)])) {
      continue;
    }

    $previous = find_significant_token($tokens, $i - 1, -1);

    if ($previous === NULL || !is_array($tokens[$previous]) || $tokens[$previous][0] !== T_DOUBLE_COLON) {
      $uses[substr($tokens[$i][1], 1)]++;
    }
  }

  // Assumes a 1-letter alias: '$a=$name;' against 'name' becoming 'a'.
  $aliased = array_filter($uses, fn(int $count, string $name): bool => $count * (strlen($name) - 1) > strlen($name) + 5, ARRAY_FILTER_USE_BOTH);

  return array_values(array_intersect($method['parameters'], array_keys($aliased)));
}

/**
 * Tells whether a name token is a method or an instance property.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   Tokens of the class from token_get_all().
 * @param int $index
 *   The index of the T_STRING token.
 *
 * @return string|null
 *   'method' for a method call or declaration, 'property' for an instance
 *   property access, or NULL for any other name.
 */
function embed_compact_member_role(array $tokens, int $index): ?string {
  $previous_index = find_significant_token($tokens, $index - 1, -1);
  $next_index = find_significant_token($tokens, $index + 1, 1);
  $previous = $previous_index === NULL ? NULL : $tokens[$previous_index];
  $previous_type = is_array($previous) ? $previous[0] : $previous;

  $is_member = $previous_type === T_OBJECT_OPERATOR || $previous_type === T_NULLSAFE_OBJECT_OPERATOR;
  $is_call = $next_index !== NULL && $tokens[$next_index] === '(';

  if (($is_call && ($is_member || $previous_type === T_DOUBLE_COLON)) || $previous_type === T_FUNCTION) {
    return 'method';
  }

  return $is_member && !$is_call ? 'property' : NULL;
}

/**
 * Renames the internals of a class and removes optional whitespace.
 *
 * Protected properties and methods get short names, the shortest for the
 * most used. Variables and parameters get short names per method, closures
 * included, so every method starts again from 'a'. Parameters of public
 * methods and parameters that a call passes by name keep their names in the
 * signature, and the body can use a short alias for them.
 *
 * @param string $class_code
 *   The class declaration, without an opening tag.
 *
 * @return string
 *   The compacted class declaration.
 *
 * @throws \RuntimeException
 *   When the class uses a construct that renaming would break.
 */
function embed_compact(string $class_code): string {
  $tokens = token_get_all('<?php ' . $class_code);
  embed_compact_assert_supported($tokens);

  $members = embed_compact_members($tokens);
  $named = embed_compact_named_arguments($tokens);
  $methods = $members['methods'];

  $renamed_methods = [];
  $reserved = ['m' => array_fill_keys(EMBED_RESERVED_WORDS, TRUE), 'p' => []];

  foreach ($methods as $method) {
    if ($method['public'] || str_starts_with($method['name'], '__')) {
      $reserved['m'][strtolower($method['name'])] = TRUE;
    }
    else {
      $renamed_methods[$method['name']] = TRUE;
    }
  }

  $renamed_properties = [];

  foreach ($members['properties'] as $property => $public) {
    if ($public) {
      $reserved['p'][strtolower($property)] = TRUE;
    }
    else {
      $renamed_properties[$property] = TRUE;
    }
  }

  // Per method scope: variables that keep their names everywhere, and
  // parameters that keep their names in the signature because a caller can
  // pass them by name. The body refers to such a parameter through an alias
  // when that is shorter.
  $kept = [];
  $signature_kept = [];
  $aliases = [];

  foreach ($methods as $scope => $method) {
    $kept[$scope] = array_fill_keys(EMBED_RESERVED_VARIABLES, TRUE) + ($named[''] ?? []);
    $by_name = $method['public'] ? $method['parameters'] : array_intersect($method['parameters'], array_keys($named[strtolower($method['name'])] ?? []));
    $signature_kept[$scope] = array_fill_keys(array_diff($by_name, array_keys($kept[$scope])), TRUE);
    $aliases[$scope] = embed_compact_aliases($tokens, $method, array_diff(array_keys($signature_kept[$scope]), $method['references']));
    $reserved['s' . $scope] = array_change_key_case($kept[$scope] + $signature_kept[$scope]);
  }

  // Each occurrence to rename, keyed by token index, with the group whose
  // map renames it: 'm' methods, 'p' properties, 's<n>' variables of method n.
  $occurrences = [];
  $counts = [];
  $method_count = count($methods);
  $scope = 0;

  foreach ($tokens as $index => $token) {
    if (!is_array($token)) {
      continue;
    }

    while ($scope < $method_count && $index > $methods[$scope]['end']) {
      $scope++;
    }

    $in_method = $scope < $method_count && $index >= $methods[$scope]['start'];
    $group = NULL;
    $name = $token[1];

    if ($token[0] === T_VARIABLE) {
      $name = substr($name, 1);
      $previous = find_significant_token($tokens, $index - 1, -1);
      $is_static_property = $previous !== NULL && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOUBLE_COLON;

      $in_signature = $in_method && $index < ($methods[$scope]['body'] ?? PHP_INT_MAX);

      if (!$in_method || $is_static_property) {
        $group = isset($renamed_properties[$name]) ? 'p' : NULL;
      }
      elseif (isset($kept[$scope][$name])) {
        $group = NULL;
      }
      elseif (!isset($signature_kept[$scope][$name]) || (!$in_signature && in_array($name, $aliases[$scope], TRUE))) {
        $group = 's' . $scope;
      }
    }
    elseif ($token[0] === T_STRING) {
      $role = embed_compact_member_role($tokens, $index);
      $group = match (TRUE) {
        $role === 'method' && isset($renamed_methods[$name]) => 'm',
        $role === 'property' && isset($renamed_properties[$name]) => 'p',
        default => NULL,
      };
    }

    if ($group === NULL) {
      continue;
    }

    $occurrences[$index] = [$group, $name];
    $counts[$group][$name] = ($counts[$group][$name] ?? 0) + 1;
  }

  // Each alias assignment is 1 more use of the alias.
  foreach ($aliases as $scope => $names) {
    foreach ($names as $name) {
      $counts['s' . $scope][$name]++;
    }
  }

  $maps = [];

  foreach ($counts as $group => $group_counts) {
    $maps[$group] = embed_short_names($group_counts, $reserved[$group] ?? [], $group === 'm');
  }

  // Alias assignments, keyed by the index of the '{' that opens the body.
  $insertions = [];

  foreach ($aliases as $scope => $names) {
    foreach ($names as $name) {
      $body = (int) $methods[$scope]['body'];
      $insertions[$body] = ($insertions[$body] ?? '') . sprintf('$%s=$%s;', $maps['s' . $scope][$name], $name);
    }
  }

  $output = '';

  // Token 0 is the opening tag added above.
  foreach (array_slice($tokens, 1, NULL, TRUE) as $index => $token) {
    if (isset($occurrences[$index])) {
      [$group, $name] = $occurrences[$index];
      $output .= (is_array($token) && $token[0] === T_VARIABLE ? '$' : '') . $maps[$group][$name];
      continue;
    }

    $output .= (is_array($token) ? $token[1] : $token) . ($insertions[$index] ?? '');
  }

  return embed_compact_whitespace($output);
}

/**
 * Removes the whitespace that PHP does not need between tokens.
 *
 * @param string $code
 *   Code without an opening tag.
 *
 * @return string
 *   The code with a space only between 2 word characters.
 */
function embed_compact_whitespace(string $code): string {
  $tokens = token_get_all('<?php ' . $code);
  $count = count($tokens);
  $output = '';

  for ($i = 1; $i < $count; $i++) {
    if (!is_array($tokens[$i])) {
      $output .= $tokens[$i];
      continue;
    }

    if ($tokens[$i][0] !== T_WHITESPACE) {
      $output .= $tokens[$i][1];
      continue;
    }

    $before = is_array($tokens[$i - 1]) ? $tokens[$i - 1][1] : $tokens[$i - 1];
    $after = '';

    if ($i + 1 < $count) {
      $after = is_array($tokens[$i + 1]) ? $tokens[$i + 1][1] : $tokens[$i + 1];
    }

    $before_last = $before !== '' ? $before[strlen($before) - 1] : '';
    $after_first = $after !== '' ? $after[0] : '';

    $is_word_pair = preg_match('/[a-zA-Z0-9_$]/', $before_last) === 1 && preg_match('/[a-zA-Z0-9_$\\\\]/', $after_first) === 1;

    // '+ +' and '- -' would read as '++' and '--', and a dot beside a digit
    // as a float.
    $is_doubled_sign = in_array($before_last, ['+', '-'], TRUE) && $after_first === $before_last;
    $is_number_dot = ($before_last === '.' && ctype_digit($after_first)) || (ctype_digit($before_last) && $after_first === '.');

    if ($is_word_pair || $is_doubled_sign || $is_number_dot) {
      $output .= ' ';
    }
  }

  return $output;
}

/**
 * Formats the sizes of files as aligned rows.
 *
 * @param list<array{label: string, path: string, content: string, note?: string}> $rows
 *   For each file, its label, its path, its content and an optional note.
 *
 * @return string
 *   The rows under a 'Sizes:' heading.
 */
function embed_stats(array $rows): string {
  $cells = [];

  foreach ($rows as $row) {
    $bytes = strlen($row['content']);
    $lines = substr_count($row['content'], "\n") + ($row['content'] === '' || str_ends_with($row['content'], "\n") ? 0 : 1);
    $cells[] = [
      $row['label'],
      number_format($bytes) . ($bytes === 1 ? ' byte' : ' bytes'),
      number_format($lines) . ($lines === 1 ? ' line' : ' lines'),
      trim($row['path'] . ' ' . ($row['note'] ?? '')),
    ];
  }

  $widths = [];

  for ($column = 0; $column < 3; $column++) {
    $widths[$column] = max(0, ...array_map(fn(array $cell): int => strlen($cell[$column]), $cells));
  }

  $output = 'Sizes:' . PHP_EOL;

  foreach ($cells as $cell) {
    $output .= sprintf('  %s  %s  %s  %s', str_pad($cell[0], $widths[0]), str_pad($cell[1], $widths[1], ' ', STR_PAD_LEFT), str_pad($cell[2], $widths[2], ' ', STR_PAD_LEFT), $cell[3]) . PHP_EOL;
  }

  return $output;
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
$no_payload_file = FALSE;
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
  elseif ($argv[$arg_i] === '--no-payload-file') {
    $no_payload_file = TRUE;
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
                    variables and the parameters of internal methods, and
                    reduce whitespace for a smaller output.
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
  --no-payload-file Skip writing the embedded code to <Class>.<mode>.payload
                    beside the output script.

Embedding:
  Before embedding, embed.php writes the code it inserts between the markers to
  <Class>.<mode>.payload beside the output script, so it can be inspected and
  compared with the release asset of the same name. After embedding, it prints
  the sizes of the target script before and after, the source class and the
  embedded code.

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

if ($no_payload_file && ($to_stdout || $is_payload_only || $is_check_mode)) {
  fwrite(STDERR, sprintf('Error: --no-payload-file is only used when embedding into a script.%s', PHP_EOL));
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
      $output .= embed_docblock_strip_tool_tags($token_value);
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

  if ($is_compact) {
    try {
      $class_single = embed_compact($class_single);
    }
    catch (\RuntimeException $runtime_exception) {
      fwrite(STDERR, sprintf('Error: %s%s', $runtime_exception->getMessage(), PHP_EOL));
      exit(1);
    }
  }

  $result_lines = array_merge($before_class, [$class_single]);
}

$minified = implode("\n", $result_lines);

$namespace = '';

if (preg_match('/^\s*namespace\s+(.+?)\s*;/m', $source, $ns_match)) {
  $namespace = $ns_match[1];
}

$payload = trim($minified) . "\n";

$payload = (string) preg_replace('/^(class\s)/m', "// @phpstan-ignore-next-line\n$1", $payload, 1);

$mode = $is_compact ? 'compact' : 'min';
$source_row = ['label' => $identity['class'] . ' source', 'path' => $source_path, 'content' => $source];
$payload_label = $identity['class'] . ' embeddable';
$payload_note = sprintf('(%s%% of the source)', number_format(strlen($payload) / max(1, strlen($source)) * 100, 1));

if ($is_payload_only) {
  if (file_put_contents($stdout_path, $payload) === FALSE) {
    fwrite(STDERR, sprintf('Error: Could not write %s%s', $stdout_path, PHP_EOL));
    exit(1);
  }

  echo sprintf('Written to %s%s', $stdout_path, PHP_EOL);
  $payload_row = ['label' => $payload_label, 'path' => $stdout_path, 'content' => $payload, 'note' => $payload_note];
  echo PHP_EOL . embed_stats([$source_row, $payload_row]);
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
  $standalone_row = ['label' => $identity['class'] . ' standalone', 'path' => $stdout_path, 'content' => $standalone];
  echo PHP_EOL . embed_stats([$source_row, $standalone_row]);
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
  'mode' => $mode,
  'sha256' => hash('sha256', $payload),
  'embedder' => $embedder_hash,
];

// Written before the target is read, so the payload can be inspected even
// when embedding fails. The name matches the release asset, so its hash can
// be compared with the release's SHA256SUMS.
$payload_file = NULL;

if (!$no_payload_file) {
  $payload_dir = dirname($target_path);
  $payload_file = ($payload_dir === '.' ? '' : $payload_dir . '/') . $identity['class'] . '.' . $mode . '.payload';

  if (basename($payload_file) === basename($target_path)) {
    fwrite(STDERR, sprintf('Error: The payload file would overwrite the target script %s. Pass --no-payload-file.%s', $target_path, PHP_EOL));
    exit(1);
  }

  if (file_put_contents($payload_file, $payload) === FALSE) {
    fwrite(STDERR, sprintf('Error: Could not write the payload file %s%s', $payload_file, PHP_EOL));
    exit(1);
  }

  echo sprintf('Wrote the embeddable %s to %s%s', $identity['class'], $payload_file, PHP_EOL);
}

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

$growth = strlen((string) $result) - strlen($target);
$before_row = ['label' => 'Target script before', 'path' => $target_path, 'content' => $target];
$payload_row = [
  'label' => $payload_label,
  'path' => $payload_file ?? '',
  'content' => $payload,
  'note' => $payload_note . ($payload_file === NULL ? ' (not written to a file)' : ''),
];
$after_row = [
  'label' => 'Target script after',
  'path' => $target_path,
  'content' => (string) $result,
  'note' => sprintf('(%s%s bytes)', $growth < 0 ? '-' : '+', number_format(abs($growth))),
];

echo PHP_EOL . embed_stats([$before_row, $source_row, $payload_row, $after_row]);

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
