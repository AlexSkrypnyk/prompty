<?php

/**
 * @file
 * Playground - what a control character in a value does to the tree.
 *
 * A value does not always arrive clean. An environment variable populated by
 * a command that printed more than one line carries a newline; a value pasted
 * from somewhere else can carry a carriage return or a colour sequence. The
 * tree is drawn line by line, so text that moves the cursor itself would draw
 * over the tree that holds it.
 *
 * Every value below is drawn as printable text: escape sequences and control
 * bytes are removed, and a newline or tab becomes a space, so the value stays
 * on the line the tree drew for it. Each is shown with its raw bytes first,
 * so what arrived and what was drawn can be compared.
 *
 * A description is split on its own newlines before any of that, so the line
 * breaks a caller writes deliberately still render as line breaks.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$opts = getopt('', ['no-unicode', 'no-ansi']);
$unicode = !isset($opts['no-unicode']);
$ansi = !isset($opts['no-ansi']);

Prompty::configure(unicode: $unicode, ansi: $ansi);

/**
 * Show a value's raw bytes, then let a widget draw it.
 *
 * @param string $title
 *   What the value carries.
 * @param string $value
 *   The value a widget resolves.
 */
function demo(string $title, string $value): void {
  echo PHP_EOL . '--- ' . $title . ' ---' . PHP_EOL;
  echo 'Raw bytes:   ' . json_encode($value) . PHP_EOL;

  $r = Prompty::text('Dish name', discovered: $value);

  echo 'Returned:    ' . json_encode($r) . PHP_EOL;
}

demo('A newline, as a multi-line command would give', "pear tart\nserves four");
demo('A carriage return, which would draw over the line', "pear tart\rplum compote");
demo('A tab', "pear\ttart");
demo('An erase-line sequence', "pear tart\033[2K");
demo('A colour sequence', "\033[31mpear tart\033[0m");

echo PHP_EOL . '--- An option label carrying a newline ---' . PHP_EOL;
Prompty::select('Course', options: ['starter' => "Star\nter", 'main' => 'Main'], discovered: 'starter');

// A description is drawn while a widget is waiting, so this one asks rather
// than resolving a value: the two lines below the label are the line breaks
// the caller wrote, and they survive because a description is split on them
// before anything is stripped.
echo PHP_EOL . '--- A description keeps the line breaks it was written with ---' . PHP_EOL;
echo 'This one waits for input. Press enter to take the placeholder.' . PHP_EOL;
Prompty::text('Guest name',
  placeholder: 'Jane Doe',
  description: "Printed on the order ticket.\nLeave blank for takeaway.",
);
