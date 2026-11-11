<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty;

/**
 * 🧙Prompty - Zero-dependency interactive CLI prompt library.
 *
 * Copy the contents of this file directly into your script, preserving
 * this header.
 *
 * @license MIT
 * @see LICENSE file for full license text.
 *
 * Copyright (c) 2026 Alex Skrypnyk (alex@drevops.com)
 * https://github.com/AlexSkrypnyk/prompty
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software to deal in it without restriction, including the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies, subject to the following condition:
 *
 * This notice must be included in all copies of this file, including when
 * used as a part of other files.
 *
 * @phpstan-type PromptyConfig array{
 *   symbols_unicode: array<string, string>,
 *   symbols_ascii: array<string, string>,
 *   colors: array<string, string>,
 *   spacing: array<string, string>,
 *   labels: array<string, string>,
 *   unicode: bool|null,
 *   ansi: bool|null,
 *   env_prefix: string,
 *   truthy: list<string>,
 *   falsy: list<string>,
 *   output: resource|\Closure|null,
 *   interactive: bool,
 *   scroll: int,
 *   placeholder_fallback: bool,
 *   width: int,
 *   legend: bool,
 *   back_key: string,
 * }
 */
class Prompty {

  /**
   * Microseconds to wait for each further byte of an escape sequence.
   *
   * A terminal writes all bytes of a key's sequence at once. The wait covers a
   * sequence split in transit, such as over SSH, and is the delay before a
   * lone Escape press registers.
   */
  protected const ESCAPE_TIMEOUT = 50000;

  /**
   * Where shell commands send their errors.
   *
   * Windows' cmd.exe cannot open /dev/null, and a failed redirect skips the
   * command and prints an error instead.
   */
  protected const NULL_DEVICE = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

  /**
   * Columns assumed when the terminal width cannot be detected.
   */
  protected const DEFAULT_COLUMNS = 80;

  /**
   * Seconds a detected terminal width is reused before it is measured again.
   */
  protected const COLUMNS_REFRESH = 1;

  /**
   * Microseconds between the frames of a task's spinner.
   */
  protected const int SPINNER_INTERVAL = 80000;

  /**
   * Lines of output a live streaming task keeps on screen.
   */
  protected const int STREAM_WINDOW = 5;

  /**
   * Ctrl keys a terminal sends as cancel, backspace, tab and enter.
   *
   * The names readKey() gives them are the names of those keys, so none can
   * be the back key.
   */
  protected const array RESERVED_CTRL_KEYS = ['ctrl-c', 'ctrl-h', 'ctrl-i', 'ctrl-j', 'ctrl-m'];

  /**
   * The types a note can have.
   *
   * Each type has a 'note_<type>' symbol and a 'note_<type>' colour.
   */
  protected const array NOTE_TYPES = ['info', 'success', 'warning', 'error'];

  /**
   * Characters a terminal draws 2 columns wide.
   *
   * Covers the East Asian wide and fullwidth ranges, emoji presentation
   * characters and the emoji variation selector.
   */
  protected const WIDE_PATTERN = '/[\x{1100}-\x{115F}\x{231A}\x{231B}\x{2329}\x{232A}\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{25FD}\x{25FE}\x{2614}\x{2615}'
    . '\x{2648}-\x{2653}\x{267F}\x{2693}\x{26A1}\x{26AA}\x{26AB}\x{26BD}\x{26BE}\x{26C4}\x{26C5}\x{26CE}\x{26D4}\x{26EA}\x{26F2}\x{26F3}\x{26F5}'
    . '\x{26FA}\x{26FD}\x{2705}\x{270A}\x{270B}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B1B}\x{2B1C}'
    . '\x{2B50}\x{2B55}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{A000}-\x{A4CF}\x{A960}-\x{A97F}\x{AC00}-\x{D7A3}'
    . '\x{F900}-\x{FAFF}\x{FE0F}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F004}\x{1F0CF}\x{1F18E}\x{1F191}-\x{1F19A}'
    . '\x{1F1E6}-\x{1F1FF}\x{1F200}-\x{1F251}\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F7E0}-\x{1F7EB}\x{1F90C}-\x{1F9FF}\x{1FA70}-\x{1FAFF}'
    . '\x{20000}-\x{3FFFD}]/u';

  /**
   * The release version, or the placeholder token in an unreleased copy.
   */
  public const VERSION = '__PROMPTY_VERSION__';

  /**
   * Singleton instance.
   *
   * @var static|null
   */
  protected static ?self $instance = NULL;

  /**
   * Whether a flow is currently being defined/executed.
   */
  protected static bool $isInFlow = FALSE;

  /**
   * Whether the steps callable of a flow is running.
   *
   * A note declares a step only then; once the flow walks its steps, a note
   * prints.
   */
  protected static bool $isDefining = FALSE;

  /**
   * Handler called for every answer a validator rejects.
   *
   * Receives the widget label, the rejected answer and the validator's
   * message. An exception it throws replaces the re-prompt or the rejection
   * of a discovered value.
   */
  protected static ?\Closure $onInvalid = NULL;

  /**
   * Terminal settings saved by setupTty() for teardownTty() to restore.
   */
  protected ?string $prevTty = NULL;

  /**
   * Collected answers.
   *
   * @var array<string, string|bool|int|array<string,string>>
   */
  protected array $results = [];

  /**
   * Tree connector tracking.
   *
   * @var array<int, bool>
   */
  protected array $open = [];

  /**
   * The step being rendered, and the data needed to settle its connector.
   *
   * @var array{
   *   key: int|string,
   *   depth: int,
   *   rest: array<array-key, mixed>,
   * }|null
   */
  protected ?array $pending = NULL;

  /**
   * Whether the running flow has drawn its intro, a step or a section.
   */
  protected bool $hasDrawn = FALSE;

  /**
   * Terminal rows the running flow has printed.
   */
  protected int $rows = 0;

  /**
   * The steps the running flow has started, in order.
   *
   * Each entry holds the step key, its depth, the rows printed before it and
   * whether its widget read a key.
   *
   * @var list<array{key: string, depth: int, rows: int, asked: bool}>
   */
  protected array $journal = [];

  /**
   * Answers from before the user stepped back, which replayed steps take.
   *
   * @var array<string, mixed>
   */
  protected array $previous = [];

  /**
   * The input each asked step was accepted with, before its transform.
   *
   * A reopened step and the steps after it start from these.
   *
   * @var array<string, mixed>
   */
  protected array $inputs = [];

  /**
   * The step key the flow replays up to after stepping back, or NULL.
   *
   * Steps before it take their previous answers without being drawn.
   */
  protected ?string $replay = NULL;

  /**
   * The journal index of the step to reopen, set when the user steps back.
   */
  protected ?int $stepBack = NULL;

  /**
   * Input stream for reading keystrokes (defaults to STDIN).
   *
   * @var resource|null
   */
  protected $input;

  /**
   * Unicode symbol set.
   *
   * @var array<string, string>
   */
  protected array $cfgSymbolsUnicode = [
    'bar' => '│',
    'completed' => '◆',
    'active' => '◇',
    'intro' => '┌',
    'outro' => '└',
    'section' => '├',
    'pointer' => '❯',
    'radio_on' => '●',
    'radio_off' => '○',
    'check_on' => '◼',
    'check_off' => '◻',
    'hint_arrow' => '↳',
    'error' => '▲',
    'scroll_up' => '↑',
    'scroll_down' => '↓',
    'success' => '✓',
    'spinner' => '◒◐◓◑',
    'ellipsis' => '…',
    'box_top_left' => '┌',
    'box_top_right' => '┐',
    'box_bottom_left' => '└',
    'box_bottom_right' => '┘',
    'box_horizontal' => '─',
    'box_vertical' => '│',
    'box_cross' => '┼',
    'box_top_tee' => '┬',
    'box_bottom_tee' => '┴',
    'box_left_tee' => '├',
    'box_right_tee' => '┤',
    'legend_vertical' => '↑↓',
    'legend_horizontal' => '←→',
    'legend_separator' => '·',
    'note_info' => '●',
    'note_success' => '✓',
    'note_warning' => '▲',
    'note_error' => '✕',
  ];

  /**
   * ASCII symbol set.
   *
   * @var array<string, string>
   */
  protected array $cfgSymbolsAscii = [
    'bar' => '|',
    'completed' => '+',
    'active' => 'o',
    'intro' => '#',
    'outro' => '#',
    'section' => '#',
    'pointer' => '>',
    'radio_on' => '(*)',
    'radio_off' => '( )',
    'check_on' => '[x]',
    'check_off' => '[ ]',
    'hint_arrow' => '-->',
    'error' => '!',
    'scroll_up' => '^',
    'scroll_down' => 'v',
    'success' => '+',
    'spinner' => '|/-\\',
    'ellipsis' => '...',
    'box_top_left' => '+',
    'box_top_right' => '+',
    'box_bottom_left' => '+',
    'box_bottom_right' => '+',
    'box_horizontal' => '-',
    'box_vertical' => '|',
    'box_cross' => '+',
    'box_top_tee' => '+',
    'box_bottom_tee' => '+',
    'box_left_tee' => '+',
    'box_right_tee' => '+',
    'legend_vertical' => 'up/down',
    'legend_horizontal' => 'left/right',
    'legend_separator' => '-',
    'note_info' => '*',
    'note_success' => '+',
    'note_warning' => '!',
    'note_error' => 'x',
  ];

  /**
   * Active symbol set (resolved from unicode setting).
   *
   * @var array<string, string>
   */
  protected array $cfgSymbols;

  /**
   * ANSI color escape sequences.
   *
   * @var array<string, string>
   */
  protected array $cfgColors = [
    'reset' => "\033[0m",
    'dim' => "\033[2m",
    'dim_italic' => "\033[2;3m",
    'cyan' => "\033[36m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'gray' => "\033[90m",
    'bold' => "\033[1m",
    'white' => "\033[37m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'italic' => "\033[3m",
    'underline' => "\033[4m",
    'note_info' => "\033[34m",
    'note_success' => "\033[32m",
    'note_warning' => "\033[33m",
    'note_error' => "\033[31m",
  ];

  /**
   * Default ANSI color escape sequences (used to restore after suppression).
   *
   * @var array<string, string>
   */
  protected array $cfgColorsDefault;

  /**
   * Spacing strings for indentation.
   *
   * @var array<string, string>
   */
  protected array $cfgSpacing = [
    'indent' => '  ',
    'hint_indent' => '    ',
    'hint_cont' => '      ',
  ];

  /**
   * UI labels.
   *
   * @var array<string, string>
   */
  protected array $cfgLabels = [
    'yes' => 'Yes',
    'no' => 'No',
    'cancelled' => '(cancelled)',
    'none' => 'None',
    'separator' => '/',
    'more' => 'more',
    'required' => 'Required.',
    'done' => 'Done',
    'failed' => 'Failed',
    'line' => 'line',
    'lines' => 'lines',
    'step_total' => '(%d/%d)',
    'legend_move' => 'move',
    'legend_toggle' => 'toggle',
    'legend_enter' => 'enter confirm',
    'legend_space' => 'space toggle',
    'legend_type' => 'type to filter',
    'legend_yes_no' => 'y/n choose',
    'legend_any_key' => 'any key continue',
    'legend_continue' => 'enter continue',
    'legend_back' => 'back',
  ];

  /**
   * Whether to use unicode symbols (NULL = auto-detect).
   */
  protected ?bool $cfgUseUnicode = NULL;

  /**
   * Whether to emit ANSI color codes (NULL = auto-detect).
   */
  protected ?bool $cfgUseAnsi = NULL;

  /**
   * Environment variable prefix for auto-discovery.
   */
  protected string $cfgEnvPrefix = 'PROMPTY_';

  /**
   * Values treated as TRUE when coercing a discovered value.
   *
   * @var list<string>
   */
  protected array $cfgTruthy = ['1', 'true', 'yes'];

  /**
   * Values treated as FALSE when coercing a discovered value.
   *
   * @var list<string>
   */
  protected array $cfgFalsy = ['0', 'false', 'no'];

  /**
   * Where rendered output is written (NULL = echo).
   *
   * @var resource|\Closure|null
   */
  protected $cfgOutput;

  /**
   * Whether widgets read input, or answer from their default.
   */
  protected bool $cfgIsInteractive = TRUE;

  /**
   * Options a select or multiselect draws at once (0 = all).
   */
  protected int $cfgScroll = 0;

  /**
   * Whether an empty text or suggest answer takes the placeholder.
   */
  protected bool $cfgUsePlaceholderFallback = TRUE;

  /**
   * Terminal columns that rendered lines wrap to (0 = detect).
   */
  protected int $cfgWidth = 0;

  /**
   * Whether an active widget draws the keys it accepts under it.
   */
  protected bool $cfgShowLegend = FALSE;

  /**
   * The key that steps back to the previous step in a flow ('' = none).
   */
  protected string $cfgBackKey = 'ctrl-u';

  /**
   * Terminal columns fixed for the frame being rendered.
   *
   * Cleared by printLines(), so the next frame takes the detected width again.
   */
  protected ?int $columns = NULL;

  /**
   * Terminal columns last detected, reused for COLUMNS_REFRESH seconds.
   */
  protected ?int $detectedColumns = NULL;

  /**
   * The time $detectedColumns was detected, in seconds.
   */
  protected float $columnsMeasuredAt = 0.0;

  /**
   * Whether the output target is a terminal (NULL = detect).
   */
  protected ?bool $isTerminal = NULL;

  /**
   * Lines the streaming task's action has written, or NULL when none streams.
   *
   * @var list<string>|null
   */
  protected ?array $streamed = NULL;

  /**
   * Streamed text after the last newline.
   */
  protected string $streamPartial = '';

  /**
   * Whether the streaming task draws a live window.
   */
  protected bool $isStreamLive = FALSE;

  /**
   * Draws the streaming task after new output, or NULL when none streams.
   *
   * A live window is redrawn with no argument; otherwise each complete line
   * is passed in to be printed.
   */
  protected ?\Closure $streamDraw = NULL;

  /**
   * Whether the streaming task is drawing, so write() does not stream it.
   */
  protected bool $isStreamDrawing = FALSE;

  /**
   * Output drawn while the stream's buffer handler runs, or NULL outside it.
   */
  protected ?string $streamBuffer = NULL;

  /**
   * Constructs a Prompty instance.
   */
  protected function __construct() {
    if ($this->cfgUseUnicode === NULL) {
      $lang = getenv('LANG') ?: getenv('LC_ALL') ?: getenv('LC_CTYPE') ?: setlocale(LC_CTYPE, '0') ?: '';
      $this->cfgUseUnicode = stripos($lang, 'utf') !== FALSE;
    }

    $this->cfgColorsDefault = $this->cfgColors;

    if ($this->cfgUseAnsi === NULL) {
      $no_color = getenv('NO_COLOR');
      $this->cfgUseAnsi = ($no_color !== FALSE && $no_color !== '') || getenv('TERM') === 'dumb' ? FALSE : TRUE;
    }

    $this->resolveDisplay();
  }

  /**
   * Returns the singleton instance, creating it on first call.
   */
  protected static function instance(): static {
    if (!static::$instance instanceof Prompty) {
      // @phpstan-ignore new.static
      static::$instance = new static();
    }

    return static::$instance;
  }

  /**
   * Resolves the symbol and color sets from the unicode and ANSI settings.
   */
  protected function resolveDisplay(): void {
    $this->cfgSymbols = $this->cfgUseUnicode ? $this->cfgSymbolsUnicode : $this->cfgSymbolsAscii;
    $this->cfgColors = $this->cfgUseAnsi ? $this->cfgColorsDefault : array_fill_keys(array_keys($this->cfgColorsDefault), '');
  }

  /**
   * Captures the configuration a flow can override.
   *
   * @return PromptyConfig
   *   The values in force, keyed as configure() names them.
   */
  protected function snapshotConfig(): array {
    return [
      'symbols_unicode' => $this->cfgSymbolsUnicode,
      'symbols_ascii' => $this->cfgSymbolsAscii,
      'colors' => $this->cfgColorsDefault,
      'spacing' => $this->cfgSpacing,
      'labels' => $this->cfgLabels,
      'unicode' => $this->cfgUseUnicode,
      'ansi' => $this->cfgUseAnsi,
      'env_prefix' => $this->cfgEnvPrefix,
      'truthy' => $this->cfgTruthy,
      'falsy' => $this->cfgFalsy,
      'output' => $this->cfgOutput,
      'interactive' => $this->cfgIsInteractive,
      'scroll' => $this->cfgScroll,
      'placeholder_fallback' => $this->cfgUsePlaceholderFallback,
      'width' => $this->cfgWidth,
      'legend' => $this->cfgShowLegend,
      'back_key' => $this->cfgBackKey,
    ];
  }

  /**
   * Puts a captured configuration back in force.
   *
   * @param PromptyConfig $snapshot
   *   A snapshot from snapshotConfig().
   */
  protected function restoreConfig(array $snapshot): void {
    $this->cfgSymbolsUnicode = $snapshot['symbols_unicode'];
    $this->cfgSymbolsAscii = $snapshot['symbols_ascii'];
    $this->cfgColorsDefault = $snapshot['colors'];
    $this->cfgSpacing = $snapshot['spacing'];
    $this->cfgLabels = $snapshot['labels'];
    $this->cfgUseUnicode = $snapshot['unicode'];
    $this->cfgUseAnsi = $snapshot['ansi'];
    $this->cfgEnvPrefix = $snapshot['env_prefix'];
    $this->cfgTruthy = $snapshot['truthy'];
    $this->cfgFalsy = $snapshot['falsy'];
    $this->cfgOutput = $snapshot['output'];
    $this->cfgIsInteractive = $snapshot['interactive'];
    $this->cfgScroll = $snapshot['scroll'];
    $this->cfgUsePlaceholderFallback = $snapshot['placeholder_fallback'];
    $this->cfgWidth = $snapshot['width'];
    $this->cfgShowLegend = $snapshot['legend'];
    $this->cfgBackKey = $snapshot['back_key'];
    $this->resolveDisplay();
  }

  /**
   * Configure the singleton instance.
   *
   * Creates the singleton if it does not exist yet. The settings apply to every
   * widget and flow that runs afterwards.
   *
   * @param array<string, string>|null $symbols_unicode
   *   Partial unicode symbol overrides.
   * @param array<string, string>|null $symbols_ascii
   *   Partial ASCII symbol overrides.
   * @param array<string, string>|null $colors
   *   Partial ANSI color overrides.
   * @param array<string, string>|null $spacing
   *   Partial spacing overrides.
   * @param array<string, string>|null $labels
   *   Partial label overrides.
   * @param bool|null $unicode
   *   Force unicode (TRUE) or ASCII (FALSE). NULL keeps the current setting,
   *   which is auto-detected until set.
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE) or suppress them (FALSE). NULL keeps the
   *   current setting, which is auto-detected until set.
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   * @param mixed $output
   *   Where rendered output is written: a writable stream resource, or a
   *   callable that receives each chunk as a string. NULL keeps the current
   *   target, which is echo until set.
   * @param bool|null $interactive
   *   Read input (TRUE), or answer every widget without reading it (FALSE).
   *   NULL keeps the current setting, which is TRUE until set.
   * @param int|null $scroll
   *   Options a select or multiselect draws at once. 0 draws every option.
   * @param bool|null $placeholder_fallback
   *   Answer an empty text or suggest with its placeholder (TRUE), or with ''
   *   (FALSE). NULL keeps the current setting, which is TRUE until set.
   * @param int|null $width
   *   Terminal columns that rendered lines wrap to. 0 detects the width from
   *   the terminal, falling back to 80.
   * @param bool|null $legend
   *   Draw the keys each widget accepts under it while it waits for input
   *   (TRUE), or leave them out (FALSE). NULL keeps the current setting, which
   *   is FALSE until set.
   * @param string|null $back_key
   *   The key that steps back to the previous step in a flow, from 'ctrl-a'
   *   to 'ctrl-z'. '' turns stepping back off. NULL keeps the current key,
   *   which is 'ctrl-u' until set.
   *
   * @throws \InvalidArgumentException
   *   When a map holds an unknown key, a list is empty or holds a blank value,
   *   $output is not a writable stream or a callable, $scroll or $width is
   *   negative, or $back_key is not a Ctrl key free for stepping back.
   */
  public static function configure(
    ?array $symbols_unicode = NULL,
    ?array $symbols_ascii = NULL,
    ?array $colors = NULL,
    ?array $spacing = NULL,
    ?array $labels = NULL,
    ?bool $unicode = NULL,
    ?bool $ansi = NULL,
    ?string $env_prefix = NULL,
    ?array $truthy = NULL,
    ?array $falsy = NULL,
    mixed $output = NULL,
    ?bool $interactive = NULL,
    ?int $scroll = NULL,
    ?bool $placeholder_fallback = NULL,
    ?int $width = NULL,
    ?bool $legend = NULL,
    ?string $back_key = NULL,
  ): void {
    $p = static::instance();

    // Validate every argument before applying any, so a rejected call leaves
    // the configuration unchanged.
    if ($symbols_unicode !== NULL) {
      $p->assertConfigKeys('symbols_unicode', $symbols_unicode, $p->cfgSymbolsUnicode);
    }

    if ($symbols_ascii !== NULL) {
      $p->assertConfigKeys('symbols_ascii', $symbols_ascii, $p->cfgSymbolsAscii);
    }

    if ($colors !== NULL) {
      $p->assertConfigKeys('colors', $colors, $p->cfgColorsDefault);
    }

    if ($spacing !== NULL) {
      $p->assertConfigKeys('spacing', $spacing, $p->cfgSpacing);
    }

    if ($labels !== NULL) {
      $p->assertConfigKeys('labels', $labels, $p->cfgLabels);

      if (isset($labels['step_total'])) {
        $p->assertStepTotalFormat($labels['step_total']);
      }
    }

    if ($truthy !== NULL) {
      $p->assertConfigList('truthy', $truthy);
    }

    if ($falsy !== NULL) {
      $p->assertConfigList('falsy', $falsy);
    }

    $output_target = $output !== NULL ? $p->resolveOutputTarget($output) : NULL;

    if ($scroll !== NULL) {
      $p->assertScroll($scroll, 'scroll');
    }

    if ($width !== NULL && $width < 0) {
      throw new \InvalidArgumentException(sprintf('Width %d for "width" is not valid. Use 0 to detect the terminal width, or the number of columns to wrap to.', $width));
    }

    if ($back_key !== NULL) {
      $p->assertBackKey($back_key);
    }

    if ($symbols_unicode !== NULL) {
      $p->cfgSymbolsUnicode = array_replace($p->cfgSymbolsUnicode, $symbols_unicode);
    }

    if ($symbols_ascii !== NULL) {
      $p->cfgSymbolsAscii = array_replace($p->cfgSymbolsAscii, $symbols_ascii);
    }

    if ($colors !== NULL) {
      $p->cfgColorsDefault = array_replace($p->cfgColorsDefault, $colors);
      $p->cfgColors = array_replace($p->cfgColors, $colors);
    }

    if ($spacing !== NULL) {
      $p->cfgSpacing = array_replace($p->cfgSpacing, $spacing);
    }

    if ($labels !== NULL) {
      $p->cfgLabels = array_replace($p->cfgLabels, $labels);
    }

    if ($unicode !== NULL) {
      $p->cfgUseUnicode = $unicode;
    }

    if ($ansi !== NULL) {
      $p->cfgUseAnsi = $ansi;
    }

    if ($env_prefix !== NULL) {
      $p->cfgEnvPrefix = $env_prefix;
    }

    if ($truthy !== NULL) {
      $p->cfgTruthy = $truthy;
    }

    if ($falsy !== NULL) {
      $p->cfgFalsy = $falsy;
    }

    if ($output_target !== NULL) {
      $p->cfgOutput = $output_target;
    }

    if ($interactive !== NULL) {
      $p->cfgIsInteractive = $interactive;
    }

    if ($scroll !== NULL) {
      $p->cfgScroll = $scroll;
    }

    if ($placeholder_fallback !== NULL) {
      $p->cfgUsePlaceholderFallback = $placeholder_fallback;
    }

    if ($width !== NULL) {
      $p->cfgWidth = $width;
    }

    if ($legend !== NULL) {
      $p->cfgShowLegend = $legend;
    }

    if ($back_key !== NULL) {
      $p->cfgBackKey = $back_key;
    }

    $p->resolveDisplay();
  }

  /**
   * Get the resolved configuration array.
   *
   * @return array<string, mixed>
   *   The resolved configuration.
   */
  public static function config(): array {
    $p = static::instance();

    return [
      'symbols_unicode' => $p->cfgSymbolsUnicode,
      'symbols_ascii' => $p->cfgSymbolsAscii,
      'symbols' => $p->cfgSymbols,
      'colors' => $p->cfgColors,
      'spacing' => $p->cfgSpacing,
      'labels' => $p->cfgLabels,
      'unicode' => $p->cfgUseUnicode,
      'ansi' => $p->cfgUseAnsi,
      'env_prefix' => $p->cfgEnvPrefix,
      'truthy' => $p->cfgTruthy,
      'falsy' => $p->cfgFalsy,
      'output' => $p->cfgOutput,
      'interactive' => $p->cfgIsInteractive,
      'scroll' => $p->cfgScroll,
      'placeholder_fallback' => $p->cfgUsePlaceholderFallback,
      'width' => $p->cfgWidth,
      'legend' => $p->cfgShowLegend,
      'back_key' => $p->cfgBackKey,
    ];
  }

  /**
   * Get the collected results from the last flow.
   *
   * @return array<string, mixed>
   *   The collected results.
   */
  public static function results(): array {
    return static::instance()->results;
  }

  /**
   * Get the library version.
   *
   * Returns 'development' when the version token has not been replaced
   * (i.e. running from source). During release, the __PROMPTY_VERSION__
   * token is replaced with the actual tag.
   *
   * @return string
   *   The version string.
   */
  public static function version(): string {
    return str_starts_with(static::VERSION, '__') ? 'development' : static::VERSION;
  }

  /**
   * Run a flow.
   *
   * Accepts a callable that returns the steps array. The callable is invoked
   * after $isInFlow is set, so widgets inside it return closures for deferred
   * execution instead of running immediately.
   *
   * Flow state and terminal state are restored on every exit path, including
   * when a step, condition or callback throws.
   *
   * @param callable $steps
   *   Callable that returns the associative steps array.
   * @param string|callable|null $intro
   *   Intro message or callable to render before the first step.
   * @param string|callable|null $outro
   *   Outro message or callable to render after the last step.
   * @param string|callable|null $cancelled
   *   Message or callable to render when the flow is cancelled.
   * @param bool|string $numbering
   *   TRUE numbers steps as (1), (2) and nested (2.1). 'total' also shows the
   *   number of top-level steps due, through the 'step_total' label. FALSE
   *   draws no numbers.
   * @param array<string, string>|null $symbols_unicode
   *   Partial unicode symbol overrides.
   * @param array<string, string>|null $symbols_ascii
   *   Partial ASCII symbol overrides.
   * @param array<string, string>|null $colors
   *   Partial ANSI color overrides.
   * @param array<string, string>|null $spacing
   *   Partial spacing overrides.
   * @param array<string, string>|null $labels
   *   Partial label overrides.
   * @param bool|null $unicode
   *   Force unicode (TRUE) or ASCII (FALSE). NULL keeps the current setting,
   *   which is auto-detected until set.
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE) or suppress them (FALSE). NULL keeps the
   *   current setting, which is auto-detected until set.
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   * @param mixed $output
   *   Where rendered output is written: a writable stream resource, or a
   *   callable that receives each chunk as a string. NULL keeps the current
   *   target, which is echo until set.
   * @param bool|null $interactive
   *   Read input (TRUE), or answer every step without reading it (FALSE).
   *   NULL keeps the current setting, which is TRUE until set.
   * @param int|null $scroll
   *   Options a select or multiselect draws at once. 0 draws every option.
   * @param bool|null $placeholder_fallback
   *   Answer an empty text or suggest with its placeholder (TRUE), or with ''
   *   (FALSE). NULL keeps the current setting, which is TRUE until set.
   * @param int|null $width
   *   Terminal columns that rendered lines wrap to. 0 detects the width from
   *   the terminal, falling back to 80.
   * @param bool|null $legend
   *   Draw the keys each widget accepts under it while it waits for input
   *   (TRUE), or leave them out (FALSE). NULL keeps the current setting, which
   *   is FALSE until set.
   * @param string|null $back_key
   *   The key that steps back to the previous step, from 'ctrl-a' to
   *   'ctrl-z'. '' turns stepping back off.
   *
   * @return array<string, string|bool|int|array<string,string>>|null
   *   Collected results or NULL if cancelled.
   */
  public static function flow(
    callable $steps,
    string|callable|null $intro = NULL,
    string|callable|null $outro = NULL,
    string|callable|null $cancelled = NULL,
    bool|string $numbering = FALSE,
    ?array $symbols_unicode = NULL,
    ?array $symbols_ascii = NULL,
    ?array $colors = NULL,
    ?array $spacing = NULL,
    ?array $labels = NULL,
    ?bool $unicode = NULL,
    ?bool $ansi = NULL,
    ?string $env_prefix = NULL,
    ?array $truthy = NULL,
    ?array $falsy = NULL,
    mixed $output = NULL,
    ?bool $interactive = NULL,
    ?int $scroll = NULL,
    ?bool $placeholder_fallback = NULL,
    ?int $width = NULL,
    ?bool $legend = NULL,
    ?string $back_key = NULL,
  ): ?array {
    $p = static::instance();
    $snapshot = NULL;

    if (is_string($numbering) && $numbering !== 'total') {
      throw new \InvalidArgumentException(sprintf('Numbering "%s" for "numbering" is not valid. Use TRUE to number steps, "total" to number them with the total, or FALSE for no numbers.', $numbering));
    }

    if ($symbols_unicode !== NULL
      || $symbols_ascii !== NULL
      || $colors !== NULL
      || $spacing !== NULL
      || $labels !== NULL
      || $unicode !== NULL
      || $ansi !== NULL
      || $env_prefix !== NULL
      || $truthy !== NULL
      || $falsy !== NULL
      || $output !== NULL
      || $interactive !== NULL
      || $scroll !== NULL
      || $placeholder_fallback !== NULL
      || $width !== NULL
      || $legend !== NULL
      || $back_key !== NULL) {
      $snapshot = $p->snapshotConfig();
      static::configure($symbols_unicode, $symbols_ascii, $colors, $spacing, $labels, $unicode, $ansi, $env_prefix, $truthy, $falsy, $output, $interactive, $scroll, $placeholder_fallback, $width, $legend, $back_key);
    }

    $p->results = [];
    $p->resetStepBack();
    static::$isInFlow = TRUE;

    try {
      static::$isDefining = TRUE;

      try {
        $steps = $steps();
      }
      finally {
        static::$isDefining = FALSE;
      }

      $p->assertStepKeys($steps);

      $options = [
        'numbering' => $numbering,
      ];

      if ($p->cfgIsInteractive) {
        $p->setupTty();
      }

      if ($p->prevTty !== NULL) {
        // @codeCoverageIgnoreStart
        // Restore the terminal on a fatal error, which skips the finally
        // below. teardownTty() clears prevTty, so this is a no-op once the
        // finally has run.
        register_shutdown_function(function () use ($p): void {
          $p->teardownTty();
        });
        // @codeCoverageIgnoreEnd
      }

      if ($intro !== NULL) {
        is_callable($intro) ? $intro($p->results) : $p->printLines($p->renderIntro($intro));
      }

      // Stepping back restarts the walk, which replays the steps before the
      // reopened one from their answers without drawing them.
      do {
        $p->results = [];
        $p->open = [];
        $p->hasDrawn = $intro !== NULL;
        $outcome = $p->walkFlow($steps, 0, $options, '');
      } while ($outcome === 'back');

      if ($outcome === 'cancelled') {
        if ($cancelled !== NULL) {
          is_callable($cancelled) ? $cancelled($p->results) : $p->printLines($p->renderOutro($cancelled));
        }

        return NULL;
      }

      if ($outro !== NULL) {
        is_callable($outro) ? $outro($p->results) : $p->printLines($p->renderOutro($outro));
      }

      return $p->results;
    }
    finally {
      $p->teardownTty();
      $p->pending = NULL;
      $p->hasDrawn = FALSE;
      $p->resetStepBack();
      static::$isInFlow = FALSE;

      if ($snapshot !== NULL) {
        $p->restoreConfig($snapshot);
      }
    }
  }

  /**
   * Declares a flow step whose widget is built when the step is reached.
   *
   * The builder receives the answers collected so far and returns a widget
   * call, so its label, options, default or description can depend on them.
   * The condition and children are declared here rather than on the widget,
   * so the flow knows them before the step runs.
   *
   * @param callable $builder
   *   Receives the answers collected so far and returns a widget call, such as
   *   Prompty::text(...), without a condition or children.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE. The
   *   builder is not called for a skipped step.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this step.
   *
   * @return array<string, mixed>
   *   The step array.
   *
   * @throws \LogicException
   *   When called outside the steps callable of a flow.
   * @throws \UnexpectedValueException
   *   When the builder returns a step array or anything that is not callable.
   */
  public static function step(callable $builder, ?callable $condition = NULL, array $children = []): array {
    if (!static::$isInFlow) {
      throw new \LogicException('Prompty::step() declares a flow step, so it can only be called inside the callable passed to Prompty::flow().');
    }

    $call = function (array $ctx) use ($builder): mixed {
      /** @var array<string, mixed> $results */
      $results = $ctx['results'] ?? [];
      $widget = $builder($results);
      $key = static::instance()->pending['key'] ?? '';

      if (is_array($widget) && array_key_exists('__call', $widget)) {
        throw new \UnexpectedValueException(sprintf('The builder for step "%s" returned a step array. Pass condition and children to Prompty::step(), so the flow knows them before the step runs.', $key));
      }

      if (!is_callable($widget)) {
        throw new \UnexpectedValueException(sprintf('The builder for step "%s" returned %s. A builder returns a widget call, such as Prompty::text(...).', $key, get_debug_type($widget)));
      }

      return $widget($ctx);
    };

    return ['__call' => $call, '__children' => $children, '__condition' => $condition];
  }

  /**
   * Declares a heading over the flow steps that follow it.
   *
   * A section takes no key and adds nothing to the results. Its group is the
   * steps up to the next section, and the heading is drawn only when at least
   * 1 of them renders.
   *
   * @param string $title
   *   The heading text, drawn bold. A newline starts another heading line.
   * @param string $description
   *   Optional description drawn dim below the title. A newline starts
   *   another description line.
   * @param callable|null $condition
   *   Optional condition callback; hides the heading when it returns FALSE.
   *   The steps in the group keep their own conditions.
   *
   * @return array<string, mixed>
   *   The section step array.
   *
   * @throws \LogicException
   *   When called outside the steps callable of a flow.
   * @throws \InvalidArgumentException
   *   When the title is blank.
   */
  public static function section(string $title, string $description = '', ?callable $condition = NULL): array {
    if (!static::$isInFlow) {
      throw new \LogicException('Prompty::section() declares a flow heading, so it can only be called inside the callable passed to Prompty::flow().');
    }

    if (trim($title) === '') {
      throw new \InvalidArgumentException('A section must have a title: it is the heading drawn above its steps.');
    }

    return ['__section' => $title, '__description' => $description, '__condition' => $condition];
  }

  /**
   * Renders a text input widget, returning the user's input string.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param string $default
   *   Pre-filled, editable initial value seeded into the input buffer. It is
   *   also the answer when nothing is typed.
   * @param string $placeholder
   *   Placeholder text shown when the input is empty. It is the answer when
   *   the input is submitted empty, unless the placeholder fallback is
   *   configured off. A non-interactive widget never answers with it.
   * @param string $description
   *   Optional description rendered below the label.
   * @param string $help
   *   Optional help rendered below the input while the widget is active.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. It is trimmed, and an
   *   empty result resolves as an empty answer: $default, or $placeholder.
   * @param callable|null $transform
   *   Optional transform. Receives the answer string and returns the string
   *   the widget answers with.
   * @param bool|string $required
   *   TRUE to reject a blank answer with the 'required' label, or the message
   *   to reject it with. Checked after $transform and before $validate.
   * @param callable|null $validate
   *   Optional validator. Receives the answer string and the flow results
   *   collected so far, and returns NULL when the answer is valid or an error
   *   message when it is not.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise the entered string, or NULL
   *   when the widget is cancelled.
   *
   * @throws \InvalidArgumentException
   *   When a discovered value, or the default answered without input, is
   *   blank while $required is set, or fails $validate.
   * @throws \UnexpectedValueException
   *   When $transform returns anything other than a string, or $validate
   *   returns neither NULL nor a string.
   */
  public static function text(
    string $label,
    string $default = '',
    string $placeholder = '',
    string $description = '',
    string $help = '',
    mixed $discovered = NULL,
    ?callable $transform = NULL,
    bool|string $required = FALSE,
    ?callable $validate = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|string|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|string|null => static::text(
        $label,
        default: $default,
        placeholder: $placeholder,
        description: $description,
        help: $help,
        discovered: $discovered,
        transform: $transform,
        required: $required,
        validate: $validate,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    /** @var array<string, mixed> $results */
    $results = $ctx['results'] ?? [];
    $plain_label = $label;

    /** @var string $default */
    $default = $p->previousAnswer($ctx, $default, is_string(...));
    $default = $p->printable($default);
    $placeholder = $p->printable($placeholder);
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $resolved_display = NULL;

    if ($resolved !== NULL || !$p->cfgIsInteractive) {
      /** @var int|float|string|bool|null $resolved */
      $resolved_display = trim($p->printable((string) $resolved));

      // An empty answer resolves as it does interactively: the seeded default,
      // or the placeholder fallback when nothing was seeded.
      if ($resolved_display === '') {
        $resolved_display = $default !== '' ? $default : $p->placeholderAnswer($placeholder);
      }

      // Transform and validate before raw mode is entered, so a rejected value
      // cannot leave the terminal unrestored.
      $resolved_display = $p->printable($p->transformString($plain_label, $transform, $resolved_display));
      $p->assertResolvedValid($resolved !== NULL ? 'Discovered' : 'Default', $plain_label, $required, $validate, $resolved_display, $results);
    }

    if ($is_standalone && $p->cfgIsInteractive) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    if ($resolved_display !== NULL) {
      $open = $p->settleOpen($resolved_display, $open);
      $p->printLines($p->renderCompleted($label, $resolved_display, $depth, $open));

      if ($is_standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_display;
    }

    $render_active = function (string $value, ?string $error) use ($p, $label, $placeholder, $description, $help, $depth, $open): array {
      // The buffer holds bytes as typed, and a paste can put a control
      // character among them.
      $value = $p->printable($value);
      $cursor = $p->color('█', 'cyan');
      $display = $value === '' ? $p->color($placeholder, 'gray') . $cursor : $p->color($value, 'white') . $cursor;

      if ($depth === 0) {
        $lines = $p->renderLabel($label, 'active', 'cyan');
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines = array_merge($lines, $p->wrap($p->bar() . $p->cfgSpacing['indent'], $display, $p->bar() . $p->cfgSpacing['indent']));
        $lines = array_merge($lines, $p->renderError($error));
        $lines = array_merge($lines, $p->renderHelp($help));
        $lines = array_merge($lines, $p->renderLegend('text'));
        $lines[] = $p->bar();

        return $lines;
      }

      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines = array_merge($lines, $p->wrap($p->bar() . $body_prefix, $display, $p->bar() . $body_prefix));
      $lines = array_merge($lines, $p->renderError($error, $depth, $open));
      $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
      $lines = array_merge($lines, $p->renderLegend('text', $depth, $open));
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $value = $default;
    $error = NULL;
    $line_count = $p->printLines($render_active($value, $error));

    while (TRUE) {
      $key = $p->readKey();

      if ($p->requestBack($key)) {
        return NULL;
      }

      if ($p->isCancelKey($key)) {
        $p->redraw($line_count, $p->renderCancelled($label, $value, $depth, $open));

        if ($is_standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $value = $p->printable($value);
        $display = $value !== '' ? $value : $p->placeholderAnswer($placeholder);
        $display = $p->printable($p->transformString($plain_label, $transform, $display));
        $error = $p->validateAnswer($plain_label, $required, $validate, $display, $results);

        if ($error === NULL) {
          $p->recordInput($value);
          $open = $p->settleOpen($display, $open);
          $p->redraw($line_count, $p->renderCompleted($label, $display, $depth, $open));

          if ($is_standalone) {
            // @codeCoverageIgnoreStart
            $p->teardownTty();
            // @codeCoverageIgnoreEnd
          }

          return $display;
        }

        $line_count = $p->redraw($line_count, $render_active($value, $error));

        continue;
      }

      $previous = $value;

      if ($key === 'backspace') {
        // Text that is not valid UTF-8 fails the /u match, so it loses its
        // last byte instead of its last character.
        $value = preg_replace('/.\z/su', '', $value) ?? substr($value, 0, -1);
      }
      elseif ($key === 'space') {
        $value .= ' ';
      }
      // Reject control bytes (ord < 32); accept printable ASCII and bytes
      // 0x80-0xFF, so UTF-8 characters delivered 1 byte per readKey() call
      // accumulate in the buffer.
      elseif (strlen($key) === 1 && ord($key) >= 32) {
        $value .= $key;
      }

      if ($value !== $previous) {
        $error = NULL;
      }

      $line_count = $p->redraw($line_count, $render_active($value, $error));
    }
  }

  /**
   * Renders a single-select widget, returning the chosen option key.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<int|string, string> $options
   *   Map of option key to display label. Must not be empty.
   * @param string $default
   *   Option key to focus initially. An empty string focuses the first option;
   *   anything else must be a key of $options.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   * @param int|null $scroll
   *   Options drawn at once; the window shifts to keep the focused option in
   *   view. 0 draws every option, and NULL uses the configured value.
   * @param string $help
   *   Optional help rendered below the options while the widget is active.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. Must be a key of
   *   $options. A blank value is rejected: a single choice has no answer that
   *   means "nothing chosen".
   * @param callable|null $transform
   *   Optional transform. Receives the selected option key and returns the
   *   string the widget answers with. The completed line shows the option
   *   label for a key of $options, and the string itself for any other value.
   * @param bool|string $required
   *   TRUE to reject a blank answer with the 'required' label, or the message
   *   to reject it with. Checked after $transform and before $validate. A
   *   selected key is never blank, so only a transformed answer can fail.
   * @param callable|null $validate
   *   Optional validator. Receives the selected option key and the flow
   *   results collected so far, and returns NULL when the answer is valid or
   *   an error message when it is not.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise the selected option key, or
   *   NULL when the widget is cancelled.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty, a default or discovered value is not a key of
   *   $options, $scroll is negative, or the answer resolved without input is
   *   blank while $required is set, or fails $validate.
   * @throws \UnexpectedValueException
   *   When $transform returns anything other than a string, or $validate
   *   returns neither NULL nor a string.
   */
  public static function select(
    string $label,
    array $options = [],
    string $default = '',
    string $description = '',
    array $hints = [],
    ?int $scroll = NULL,
    string $help = '',
    mixed $discovered = NULL,
    ?callable $transform = NULL,
    bool|string $required = FALSE,
    ?callable $validate = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|string|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|string|null => static::select(
        $label,
        options: $options,
        default: $default,
        description: $description,
        hints: $hints,
        scroll: $scroll,
        help: $help,
        discovered: $discovered,
        transform: $transform,
        required: $required,
        validate: $validate,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    $plain_label = $label;

    // Validate before raw mode is entered, so a rejected call cannot leave
    // the terminal unrestored.
    $p->assertDeclaredOptions($label, $options);
    /** @var string $default */
    $default = $p->previousAnswer($ctx, $default, fn(mixed $answer): bool => is_string($answer) && array_key_exists($answer, $options));
    $default_key = $default === '' ? NULL : $p->assertOptionKey('Default', $label, $default, $options);
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $resolved_key = $resolved === NULL ? NULL : $p->assertOptionKey('Discovered', $label, $resolved, $options);
    $visible = $p->visibleOptions($label, $scroll, count($options));
    /** @var array<string, mixed> $results */
    $results = $ctx['results'] ?? [];

    if ($resolved_key === NULL && !$p->cfgIsInteractive) {
      $resolved_key = $default_key ?? $p->optionKeys($options)[0];
    }

    if ($resolved_key !== NULL) {
      $resolved_key = $p->transformString($plain_label, $transform, $resolved_key);
      $p->assertResolvedValid($resolved !== NULL ? 'Discovered' : 'Default', $plain_label, $required, $validate, $resolved_key, $results);
    }

    if ($is_standalone && $p->cfgIsInteractive) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = $p->optionKeys($options);
    $option_labels = array_map($p->printable(...), array_values($options));
    $ordered_hints = array_map(fn(string $key) => $hints[$key] ?? '', $option_keys);

    if ($resolved_key !== NULL) {
      $open = $p->settleOpen($resolved_key, $open);
      $p->printLines($p->renderCompleted($label, $p->optionDisplay($resolved_key, $options), $depth, $open));

      if ($is_standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_key;
    }

    $render_active = function (int $focused, int $first, ?string $error) use ($p, $label, $option_labels, $description, $ordered_hints, $help, $visible, $depth, $open): array {
      $window = array_slice($option_labels, $first, $visible, TRUE);
      $rows = [];

      if ($depth === 0) {
        $lines = $p->renderLabel($label, 'active', 'cyan');
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($window as $index => $option) {
          $is_focused = $index === $focused;
          $radio = $p->color($p->cfgSymbols[$is_focused ? 'radio_on' : 'radio_off'], $is_focused ? 'green' : 'dim');
          $text = $is_focused ? $option : $p->color($option, 'dim');

          $rows = array_merge($rows, $p->wrap($p->bar() . $p->pointer($is_focused) . $radio . ' ', $text, $p->bar() . $p->pad($p->pointer(FALSE) . $radio . ' ')));

          if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
            $rows = array_merge($rows, $p->renderHint($ordered_hints[$index]));
          }
        }

        $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($option_labels)));
        $lines = array_merge($lines, $p->renderError($error));
        $lines = array_merge($lines, $p->renderHelp($help));
        $lines = array_merge($lines, $p->renderLegend('select'));
        $lines[] = $p->bar();

        return $lines;
      }

      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($window as $index => $option) {
        $is_focused = $index === $focused;
        $radio = $p->color($p->cfgSymbols[$is_focused ? 'radio_on' : 'radio_off'], $is_focused ? 'green' : 'dim');
        $text = $is_focused ? $option : $p->color($option, 'dim');

        $rows = array_merge($rows, $p->wrap($p->bar() . $body_prefix . $p->pointer($is_focused) . $radio . ' ', $text, $p->bar() . $body_prefix . $p->pad($p->pointer(FALSE) . $radio . ' ')));

        if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
          $rows = array_merge($rows, $p->renderHint($ordered_hints[$index], $depth, $open));
        }
      }

      $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($option_labels), $depth, $open));
      $lines = array_merge($lines, $p->renderError($error, $depth, $open));
      $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
      $lines = array_merge($lines, $p->renderLegend('select', $depth, $open));
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $focused = 0;

    if ($default_key !== NULL) {
      // The default was checked against the same keys, so it is always found.
      $focused = (int) array_search($default_key, $option_keys, TRUE);
    }

    $error = NULL;
    $first = $p->scrollWindow(0, $focused, $visible);
    $line_count = $p->printLines($render_active($focused, $first, $error));

    while (TRUE) {
      $key = $p->readKey();

      if ($p->requestBack($key)) {
        return NULL;
      }

      if ($p->isCancelKey($key)) {
        $p->redraw($line_count, $p->renderCancelled($label, $option_labels[$focused], $depth, $open));

        if ($is_standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $selected_key = $p->transformString($plain_label, $transform, $option_keys[$focused]);
        $error = $p->validateAnswer($plain_label, $required, $validate, $selected_key, $results);

        if ($error === NULL) {
          $p->recordInput($option_keys[$focused]);
          $open = $p->settleOpen($selected_key, $open);
          $p->redraw($line_count, $p->renderCompleted($label, $p->optionDisplay($selected_key, $options), $depth, $open));

          if ($is_standalone) {
            // @codeCoverageIgnoreStart
            $p->teardownTty();
            // @codeCoverageIgnoreEnd
          }

          return $selected_key;
        }

        $line_count = $p->redraw($line_count, $render_active($focused, $first, $error));

        continue;
      }

      $previous = $focused;

      if ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      if ($focused !== $previous) {
        $error = NULL;
      }

      $first = $p->scrollWindow($first, $focused, $visible);
      $line_count = $p->redraw($line_count, $render_active($focused, $first, $error));
    }
  }

  /**
   * Renders a multi-select widget, returning an array of chosen option keys.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<int|string, string> $options
   *   Map of option key to display label. Must not be empty.
   * @param list<int|string> $default
   *   Option keys to pre-check when the widget is rendered interactively. Every
   *   entry must be a key of $options; an empty list pre-checks nothing.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   * @param int|null $scroll
   *   Options drawn at once; the window shifts to keep the focused option in
   *   view, and opens on the first pre-checked option. 0 draws every option,
   *   and NULL uses the configured value.
   * @param string|\Closure $order
   *   The order of the answered keys. 'options' follows $options. 'default'
   *   puts the keys of $default first, in $default order, then the rest in
   *   option order. 'selection' follows the order the keys were checked, with
   *   $default checked first; a discovered value keeps the order it gives.
   *   A closure is a comparator, as for usort(): it receives 2 option keys
   *   and returns an int. Keys it ranks equal keep the 'selection' order.
   * @param string $help
   *   Optional help rendered below the options while the widget is active.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. Every entry must be a
   *   key of $options. A blank value selects nothing.
   * @param callable|null $transform
   *   Optional transform. Receives the list of selected option keys and
   *   returns the list of strings the widget answers with. The completed line
   *   shows the option label for each key of $options, and the string itself
   *   for any other entry.
   * @param bool|string $required
   *   TRUE to reject an empty selection with the 'required' label, or the
   *   message to reject it with. Checked after $transform and before
   *   $validate.
   * @param callable|null $validate
   *   Optional validator. Receives the list of selected option keys and the
   *   flow results collected so far, and returns NULL when the answer is valid
   *   or an error message when it is not.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|list<string>|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise the selected option keys,
   *   deduplicated and in $order, or NULL when the widget is cancelled.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty, a default or discovered entry is not a key of
   *   $options, $scroll is negative, $order is not a known order, or the
   *   answer resolved without input is empty while $required is set, or fails
   *   $validate.
   * @throws \UnexpectedValueException
   *   When $transform returns anything other than a list of strings, an
   *   $order closure returns anything other than an int, or $validate returns
   *   neither NULL nor a string.
   */
  public static function multiselect(
    string $label,
    array $options = [],
    array $default = [],
    string $description = '',
    array $hints = [],
    ?int $scroll = NULL,
    string|\Closure $order = 'options',
    string $help = '',
    mixed $discovered = NULL,
    ?callable $transform = NULL,
    bool|string $required = FALSE,
    ?callable $validate = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|null => static::multiselect(
        $label,
        options: $options,
        default: $default,
        description: $description,
        hints: $hints,
        scroll: $scroll,
        order: $order,
        help: $help,
        discovered: $discovered,
        transform: $transform,
        required: $required,
        validate: $validate,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    $plain_label = $label;

    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;

    // An environment variable holds 1 string, so several answers arrive
    // comma-separated, and a scalar from either source is split the same way.
    // A list is taken as given, so it can select an option key with a comma.
    if (is_scalar($resolved)) {
      $resolved = explode(',', (string) $resolved);
    }

    // Validate before raw mode is entered, so a rejected call cannot leave
    // the terminal unrestored.
    $p->assertDeclaredOptions($label, $options);
    /** @var list<int|string> $default */
    $default = $p->previousAnswer($ctx, $default, fn(mixed $answer): bool => is_array($answer) && array_is_list($answer) && $answer === array_values(array_filter($answer, fn(mixed $key): bool => is_string($key) && array_key_exists($key, $options))));
    $default_keys = $p->assertOptionKeys('Default', $label, $default, $options);
    $resolved_keys = $resolved === NULL ? NULL : $p->assertOptionKeys('Discovered', $label, $resolved, $options);
    $visible = $p->visibleOptions($label, $scroll, count($options));
    $p->assertOrder($order, $label);
    /** @var array<string, mixed> $results */
    $results = $ctx['results'] ?? [];

    if ($resolved_keys === NULL && !$p->cfgIsInteractive) {
      $resolved_keys = $default_keys;
    }

    if ($resolved_keys !== NULL) {
      $resolved_keys = $p->orderKeys($plain_label, $resolved_keys, $order, $options, $default_keys);
      $resolved_keys = $p->transformKeys($plain_label, $transform, $resolved_keys);
      $p->assertResolvedValid($resolved !== NULL ? 'Discovered' : 'Default', $plain_label, $required, $validate, $resolved_keys, $results);
    }

    if ($is_standalone && $p->cfgIsInteractive) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = $p->optionKeys($options);
    $option_labels = array_map($p->printable(...), array_values($options));
    $ordered_hints = array_map(fn(string $key) => $hints[$key] ?? '', $option_keys);

    if ($resolved_keys !== NULL) {
      $open = $p->settleOpen($resolved_keys, $open);
      $p->printLines($p->renderCompleted($label, $p->optionsDisplay($resolved_keys, $options), $depth, $open));

      if ($is_standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_keys;
    }

    $render_active = function (int $focused, array $checked, int $first, ?string $error) use ($p, $label, $option_labels, $description, $ordered_hints, $help, $visible, $depth, $open): array {
      $window = array_slice($option_labels, $first, $visible, TRUE);
      $rows = [];

      if ($depth === 0) {
        $lines = $p->renderLabel($label, 'active', 'cyan');
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($window as $index => $option) {
          $is_checked = $checked[$index] ?? FALSE;
          $is_focused = $index === $focused;
          $check = $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_focused || $is_checked ? 'green' : 'dim');
          $text = $is_focused || $is_checked ? $option : $p->color($option, 'dim');

          $rows = array_merge($rows, $p->wrap($p->bar() . $p->pointer($is_focused) . $check . ' ', $text, $p->bar() . $p->pad($p->pointer(FALSE) . $check . ' ')));

          if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
            $rows = array_merge($rows, $p->renderHint($ordered_hints[$index]));
          }
        }

        $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($option_labels)));
        $lines = array_merge($lines, $p->renderError($error));
        $lines = array_merge($lines, $p->renderHelp($help));
        $lines = array_merge($lines, $p->renderLegend('multiselect'));
        $lines[] = $p->bar();

        return $lines;
      }

      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($window as $index => $option) {
        $is_checked = $checked[$index] ?? FALSE;
        $is_focused = $index === $focused;
        $check = $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_focused || $is_checked ? 'green' : 'dim');
        $text = $is_focused || $is_checked ? $option : $p->color($option, 'dim');

        $rows = array_merge($rows, $p->wrap($p->bar() . $body_prefix . $p->pointer($is_focused) . $check . ' ', $text, $p->bar() . $body_prefix . $p->pad($p->pointer(FALSE) . $check . ' ')));

        if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
          $rows = array_merge($rows, $p->renderHint($ordered_hints[$index], $depth, $open));
        }
      }

      $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($option_labels), $depth, $open));
      $lines = array_merge($lines, $p->renderError($error, $depth, $open));
      $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
      $lines = array_merge($lines, $p->renderLegend('multiselect', $depth, $open));
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $focused = 0;

    // A window that opened at the top could hide every pre-checked option, so
    // it opens on the first one. A list drawn in full keeps focus at the top.
    if ($visible < count($option_keys) && $default_keys !== []) {
      $focused = (int) array_search($p->orderKeys($plain_label, $default_keys, 'options', $options, [])[0], $option_keys, TRUE);
    }

    $checked = array_map(fn(string $key): bool => in_array($key, $default_keys, TRUE), $option_keys);
    // Checked keys in the order they were checked, with the default first.
    $selection = $default_keys;
    $error = NULL;
    $first = $p->scrollWindow(0, $focused, $visible);
    $line_count = $p->printLines($render_active($focused, $checked, $first, $error));

    while (TRUE) {
      $key = $p->readKey();

      if ($p->requestBack($key)) {
        return NULL;
      }

      if ($p->isCancelKey($key)) {
        $p->redraw($line_count, $p->renderCancelled($label, '', $depth, $open));

        if ($is_standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $ordered_keys = $p->orderKeys($plain_label, $selection, $order, $options, $default_keys);
        $selected_keys = $p->transformKeys($plain_label, $transform, $ordered_keys);
        $error = $p->validateAnswer($plain_label, $required, $validate, $selected_keys, $results);

        if ($error === NULL) {
          $p->recordInput($ordered_keys);
          $open = $p->settleOpen($selected_keys, $open);
          $p->redraw($line_count, $p->renderCompleted($label, $p->optionsDisplay($selected_keys, $options), $depth, $open));

          if ($is_standalone) {
            // @codeCoverageIgnoreStart
            $p->teardownTty();
            // @codeCoverageIgnoreEnd
          }

          return $selected_keys;
        }

        $line_count = $p->redraw($line_count, $render_active($focused, $checked, $first, $error));

        continue;
      }

      $previous = $checked;

      if ($key === 'space') {
        $checked[$focused] = !$checked[$focused];
        $selection = array_values(array_diff($selection, [$option_keys[$focused]]));

        if ($checked[$focused]) {
          $selection[] = $option_keys[$focused];
        }
      }
      elseif ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      if ($checked !== $previous) {
        $error = NULL;
      }

      $first = $p->scrollWindow($first, $focused, $visible);
      $line_count = $p->redraw($line_count, $render_active($focused, $checked, $first, $error));
    }
  }

  /**
   * Renders a text input that suggests matching options as the user types.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<int|string, string>|\Closure $options
   *   The suggestions, or a closure that receives the typed text and returns
   *   the suggestions for it. A list is filtered to the entries that contain
   *   the typed text, ignoring case; its keys are ignored. A closure's return
   *   value is drawn as given: a value that is not an array suggests nothing,
   *   and entries that are not scalar are skipped. A list must not be empty.
   * @param string $default
   *   Pre-filled, editable initial value seeded into the input buffer. It is
   *   also the answer when nothing is typed.
   * @param string $placeholder
   *   Placeholder text shown when the input is empty. It is the answer when
   *   the input is submitted empty, unless the placeholder fallback is
   *   configured off. A non-interactive widget never answers with it.
   * @param string $description
   *   Optional description rendered below the label.
   * @param int|null $scroll
   *   Suggestions drawn at once; the window shifts to keep the focused one in
   *   view. 0 draws every suggestion, and NULL uses the configured value.
   * @param string $help
   *   Optional help rendered below the suggestions while the widget is active.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. It is trimmed, and an
   *   empty result resolves as an empty answer: $default, or $placeholder. It
   *   need not be one of the suggestions.
   * @param callable|null $transform
   *   Optional transform. Receives the answer string and returns the string
   *   the widget answers with.
   * @param bool|string $required
   *   TRUE to reject a blank answer with the 'required' label, or the message
   *   to reject it with.
   * @param callable|null $validate
   *   Optional validator. Receives the answer string and the flow results
   *   collected so far, and returns NULL when the answer is valid or an error
   *   message when it is not.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise the focused suggestion, or the
   *   typed text when no suggestion is focused, or NULL when the widget is
   *   cancelled.
   *
   * @throws \InvalidArgumentException
   *   When $options is an empty list, $scroll is negative, or the answer
   *   resolved without input is blank while $required is set or fails
   *   $validate.
   * @throws \UnexpectedValueException
   *   When $transform returns anything other than a string, or $validate
   *   returns neither NULL nor a string.
   */
  public static function suggest(
    string $label,
    array|\Closure $options = [],
    string $default = '',
    string $placeholder = '',
    string $description = '',
    ?int $scroll = NULL,
    string $help = '',
    mixed $discovered = NULL,
    ?callable $transform = NULL,
    bool|string $required = FALSE,
    ?callable $validate = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|string|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|string|null => static::suggest(
        $label,
        options: $options,
        default: $default,
        placeholder: $placeholder,
        description: $description,
        scroll: $scroll,
        help: $help,
        discovered: $discovered,
        transform: $transform,
        required: $required,
        validate: $validate,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    /** @var array<string, mixed> $results */
    $results = $ctx['results'] ?? [];
    $plain_label = $label;

    // Validate before raw mode is entered, so a rejected call cannot leave
    // the terminal unrestored.
    if (is_array($options)) {
      $p->assertDeclaredOptions($label, $options);
    }

    $p->assertScroll($scroll ?? $p->cfgScroll, $label);

    /** @var string $default */
    $default = $p->previousAnswer($ctx, $default, is_string(...));
    $default = $p->printable($default);
    $placeholder = $p->printable($placeholder);
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $resolved_display = NULL;

    if ($resolved !== NULL || !$p->cfgIsInteractive) {
      /** @var int|float|string|bool|null $resolved */
      $resolved_display = trim($p->printable((string) $resolved));

      // An empty answer resolves as it does interactively: the seeded default,
      // or the placeholder fallback when nothing was seeded.
      if ($resolved_display === '') {
        $resolved_display = $default !== '' ? $default : $p->placeholderAnswer($placeholder);
      }

      $resolved_display = $p->printable($p->transformString($plain_label, $transform, $resolved_display));
      $p->assertResolvedValid($resolved !== NULL ? 'Discovered' : 'Default', $plain_label, $required, $validate, $resolved_display, $results);
    }

    if ($is_standalone && $p->cfgIsInteractive) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    if ($resolved_display !== NULL) {
      $open = $p->settleOpen($resolved_display, $open);
      $p->printLines($p->renderCompleted($label, $resolved_display, $depth, $open));

      if ($is_standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_display;
    }

    $render_active = function (string $value, array $matches, ?int $focused, int $first, int $visible, ?string $error) use ($p, $label, $placeholder, $description, $help, $depth, $open): array {
      // The buffer holds bytes as typed, and a paste can put a control
      // character among them.
      $value = $p->printable($value);
      $cursor = $p->color('█', 'cyan');
      $display = $value === '' ? $p->color($placeholder, 'gray') . $cursor : $p->color($value, 'white') . $cursor;
      $body_prefix = $depth > 0 ? $p->bodyPrefix($depth, $open) : '';
      $rows = [];

      foreach (array_slice($matches, $first, $visible, TRUE) as $index => $match) {
        $is_focused = $index === $focused;
        $gutter = $p->bar() . $body_prefix . $p->pointer($is_focused);
        $rows = array_merge($rows, $p->wrap($gutter, $is_focused ? $match : $p->color($match, 'dim'), $p->bar() . $body_prefix . $p->pad($p->pointer(FALSE))));
      }

      if ($depth === 0) {
        $lines = $p->renderLabel($label, 'active', 'cyan');
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines = array_merge($lines, $p->wrap($p->bar() . $p->cfgSpacing['indent'], $display, $p->bar() . $p->cfgSpacing['indent']));
        $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($matches)));
        $lines = array_merge($lines, $p->renderError($error));
        $lines = array_merge($lines, $p->renderHelp($help));
        $lines = array_merge($lines, $p->renderLegend('suggest'));
        $lines[] = $p->bar();

        return $lines;
      }

      $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines = array_merge($lines, $p->wrap($p->bar() . $body_prefix, $display, $p->bar() . $body_prefix));
      $lines = array_merge($lines, $p->renderScrollWindow($rows, $first, $visible, count($matches), $depth, $open));
      $lines = array_merge($lines, $p->renderError($error, $depth, $open));
      $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
      $lines = array_merge($lines, $p->renderLegend('suggest', $depth, $open));
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $value = $default;
    $matches = $p->suggestMatches($options, $value);
    $visible = $p->visibleOptions($label, $scroll, count($matches));
    $focused = NULL;
    $first = 0;
    $error = NULL;
    $line_count = $p->printLines($render_active($value, $matches, $focused, $first, $visible, $error));

    while (TRUE) {
      $key = $p->readKey();

      if ($p->requestBack($key)) {
        return NULL;
      }

      if ($p->isCancelKey($key)) {
        $p->redraw($line_count, $p->renderCancelled($label, $focused !== NULL ? $matches[$focused] : $value, $depth, $open));

        if ($is_standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $value = $p->printable($value);
        $display = $focused !== NULL ? $matches[$focused] : ($value !== '' ? $value : $p->placeholderAnswer($placeholder));
        $display = $p->printable($p->transformString($plain_label, $transform, $display));
        $error = $p->validateAnswer($plain_label, $required, $validate, $display, $results);

        if ($error === NULL) {
          $p->recordInput($focused !== NULL ? $matches[$focused] : $value);
          $open = $p->settleOpen($display, $open);
          $p->redraw($line_count, $p->renderCompleted($label, $display, $depth, $open));

          if ($is_standalone) {
            // @codeCoverageIgnoreStart
            $p->teardownTty();
            // @codeCoverageIgnoreEnd
          }

          return $display;
        }

        $line_count = $p->redraw($line_count, $render_active($value, $matches, $focused, $first, $visible, $error));

        continue;
      }

      $typed = $value;
      $previous_focus = $focused;

      // Focus steps through the matches and then back to none, so the typed
      // text can still be submitted as is.
      if ($key === 'down' && $matches !== []) {
        $focused = $focused === NULL ? 0 : ($focused + 1 < count($matches) ? $focused + 1 : NULL);
      }
      elseif ($key === 'up' && $matches !== []) {
        $focused = $focused === NULL ? count($matches) - 1 : ($focused > 0 ? $focused - 1 : NULL);
      }
      elseif ($key === 'backspace') {
        // Text that is not valid UTF-8 fails the /u match, so it loses its
        // last byte instead of its last character.
        $value = preg_replace('/.\z/su', '', $value) ?? substr($value, 0, -1);
      }
      elseif ($key === 'space') {
        $value .= ' ';
      }
      elseif (strlen($key) === 1 && ord($key) >= 32) {
        $value .= $key;
      }

      if ($value !== $typed) {
        $matches = $p->suggestMatches($options, $value);
        $visible = $p->visibleOptions($label, $scroll, count($matches));
        $focused = NULL;
        $first = 0;
      }
      elseif ($focused !== NULL) {
        $first = $p->scrollWindow($first, $focused, $visible);
      }

      if ($value !== $typed || $focused !== $previous_focus) {
        $error = NULL;
      }

      $line_count = $p->redraw($line_count, $render_active($value, $matches, $focused, $first, $visible, $error));
    }
  }

  /**
   * Renders a yes/no confirm widget, returning TRUE for yes and FALSE for no.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param bool $default
   *   The default selection (TRUE for yes, FALSE for no).
   * @param string $description
   *   Optional description rendered below the label.
   * @param string $help
   *   Optional help rendered below the answers while the widget is active.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. A boolean is taken as
   *   is; anything else must appear in the truthy or falsy list. A blank value
   *   is rejected: yes and no are the only answers.
   * @param callable|null $transform
   *   Optional transform. Receives the boolean answer and returns the boolean
   *   the widget answers with.
   * @param bool|string $required
   *   Accepted so every widget shares 1 signature. A boolean answer is never
   *   empty, so it has no effect.
   * @param callable|null $validate
   *   Optional validator. Receives the boolean answer and the flow results
   *   collected so far, and returns NULL when the answer is valid or an error
   *   message when it is not.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|bool|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise TRUE for yes and FALSE for no,
   *   or NULL when the widget is cancelled.
   *
   * @throws \InvalidArgumentException
   *   When a discovered value appears in neither the truthy nor falsy list,
   *   or the answer resolved without input fails $validate.
   * @throws \UnexpectedValueException
   *   When $transform returns anything other than a boolean, or $validate
   *   returns neither NULL nor a string.
   */
  public static function confirm(
    string $label,
    bool $default = TRUE,
    string $description = '',
    string $help = '',
    mixed $discovered = NULL,
    ?callable $transform = NULL,
    bool|string $required = FALSE,
    ?callable $validate = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|bool|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|bool|null => static::confirm(
        $label,
        default: $default,
        description: $description,
        help: $help,
        discovered: $discovered,
        transform: $transform,
        required: $required,
        validate: $validate,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    $plain_label = $label;

    /** @var list<string> $truthy */
    $truthy = $ctx['truthy'] ?? $p->cfgTruthy;
    /** @var list<string> $falsy */
    $falsy = $ctx['falsy'] ?? $p->cfgFalsy;
    /** @var bool $default */
    $default = $p->previousAnswer($ctx, $default, is_bool(...));

    // Validate before raw mode is entered, so a rejected value cannot leave
    // the terminal unrestored.
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $is_resolved_yes = $resolved === NULL ? NULL : $p->assertDiscoveredBool($label, $resolved, $truthy, $falsy);
    /** @var array<string, mixed> $results */
    $results = $ctx['results'] ?? [];

    if ($is_resolved_yes === NULL && !$p->cfgIsInteractive) {
      $is_resolved_yes = $default;
    }

    if ($is_resolved_yes !== NULL) {
      $is_resolved_yes = $p->transformBool($plain_label, $transform, $is_resolved_yes);
      $p->assertResolvedValid($resolved !== NULL ? 'Discovered' : 'Default', $plain_label, $required, $validate, $is_resolved_yes, $results);
    }

    if ($is_standalone && $p->cfgIsInteractive) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    if ($is_resolved_yes !== NULL) {
      $open = $p->settleOpen($is_resolved_yes, $open);
      $p->printLines($p->renderCompleted($label, $is_resolved_yes ? $p->label('yes') : $p->label('no'), $depth, $open));

      if ($is_standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $is_resolved_yes;
    }

    $render_active = function (bool $is_yes_focused, ?string $error) use ($p, $label, $description, $help, $depth, $open): array {
      $options_display = $is_yes_focused
        ? $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->label('yes') . ' ' . $p->color($p->label('separator'), 'dim')
          . ' ' . $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->label('no'), 'dim')
        : $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->label('yes'), 'dim') . ' ' . $p->color($p->label('separator'), 'dim')
          . ' ' . $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->label('no');

      if ($depth === 0) {
        $lines = $p->renderLabel($label, 'active', 'cyan');
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines = array_merge($lines, $p->wrap($p->bar() . $p->cfgSpacing['indent'], $options_display, $p->bar() . $p->cfgSpacing['indent']));
        $lines = array_merge($lines, $p->renderError($error));
        $lines = array_merge($lines, $p->renderHelp($help));
        $lines = array_merge($lines, $p->renderLegend('confirm'));
        $lines[] = $p->bar();

        return $lines;
      }

      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines = array_merge($lines, $p->wrap($p->bar() . $body_prefix, $options_display, $p->bar() . $body_prefix));
      $lines = array_merge($lines, $p->renderError($error, $depth, $open));
      $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
      $lines = array_merge($lines, $p->renderLegend('confirm', $depth, $open));
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $is_yes = $default;
    $error = NULL;
    $line_count = $p->printLines($render_active($is_yes, $error));

    while (TRUE) {
      $key = $p->readKey();

      if ($p->requestBack($key)) {
        return NULL;
      }

      if ($p->isCancelKey($key)) {
        $p->redraw($line_count, $p->renderCancelled($label, $is_yes ? $p->label('yes') : $p->label('no'), $depth, $open));

        if ($is_standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $answer = $p->transformBool($plain_label, $transform, $is_yes);
        $error = $p->validateAnswer($plain_label, $required, $validate, $answer, $results);

        if ($error === NULL) {
          $p->recordInput($is_yes);
          $open = $p->settleOpen($answer, $open);
          $p->redraw($line_count, $p->renderCompleted($label, $answer ? $p->label('yes') : $p->label('no'), $depth, $open));

          if ($is_standalone) {
            // @codeCoverageIgnoreStart
            $p->teardownTty();
            // @codeCoverageIgnoreEnd
          }

          return $answer;
        }

        $line_count = $p->redraw($line_count, $render_active($is_yes, $error));

        continue;
      }

      $was_yes = $is_yes;

      if (in_array($key, ['left', 'right', 'up', 'down', 'tab'], TRUE)) {
        $is_yes = !$is_yes;
      }
      elseif ($key === 'y' || $key === 'Y') {
        $is_yes = TRUE;
      }
      elseif ($key === 'n' || $key === 'N') {
        $is_yes = FALSE;
      }

      if ($is_yes !== $was_yes) {
        $error = NULL;
      }

      $line_count = $p->redraw($line_count, $render_active($is_yes, $error));
    }
  }

  /**
   * Draws a message and waits for a key before continuing.
   *
   * Any key continues, unless $enter_only is set. Escape and Ctrl+C cancel.
   * In non-interactive mode the widget continues without reading input.
   *
   * @param string $label
   *   The message shown to the user, such as "Press any key to continue".
   * @param string $description
   *   Optional description rendered below the message.
   * @param string $help
   *   Optional help rendered below the description while the widget waits.
   * @param bool $enter_only
   *   Continue only on Enter and ignore every other key.
   * @param bool $erase
   *   Erase the message once a key continues, instead of replacing it with a
   *   completed line.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|bool|null
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. Otherwise TRUE once a key continues, or
   *   NULL when the widget is cancelled.
   */
  public static function pause(
    string $label,
    string $description = '',
    string $help = '',
    bool $enter_only = FALSE,
    bool $erase = FALSE,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|bool|null {
    if (static::$isInFlow && $ctx === NULL) {
      $call = fn(array $ctx): \Closure|array|bool|null => static::pause(
        $label,
        description: $description,
        help: $help,
        enter_only: $enter_only,
        erase: $erase,
        ctx: $ctx,
      );

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $finish = function (int $line_count) use ($p, $label, $erase, $depth, $open): bool {
      $open = $p->settleOpen(TRUE, $open);

      if ($erase) {
        $p->clearRows($line_count);

        return TRUE;
      }

      $completed = $p->renderLabel($label, 'completed', 'cyan', $depth, $open);
      $completed[] = $p->bar() . ($depth > 0 ? $p->bodyPrefix($depth, $open) : '');
      $p->redraw($line_count, $completed);

      return TRUE;
    };

    if (!$p->cfgIsInteractive) {
      return $finish(0);
    }

    if ($is_standalone) {
      $p->setupTty();
    }

    $body_prefix = $depth > 0 ? $p->bodyPrefix($depth, $open) : '';
    $gutter = $p->bar() . ($depth > 0 ? $body_prefix : $p->cfgSpacing['indent']);
    $lines = $p->renderLabel($label, 'active', 'cyan', $depth, $open);

    // The description ends with a separator for an input line, which a pause
    // does not draw.
    $lines = array_merge($lines, $description !== '' ? array_slice($p->renderDescription($description, $depth, $open), 0, -1) : []);
    $lines = array_merge($lines, $p->renderHelp($help, $depth, $open));
    $lines = array_merge($lines, $p->renderLegend($enter_only ? 'pause_enter' : 'pause', $depth, $open));
    $lines[] = $p->bar() . $body_prefix;
    $line_count = $p->printLines($lines);

    try {
      while (TRUE) {
        // A pause asks nothing, so stepping back passes over it.
        $key = $p->readKey(is_asking: FALSE);

        if ($p->requestBack($key)) {
          return NULL;
        }

        if ($p->isCancelKey($key)) {
          $p->redraw($line_count, [
            ...$p->renderLabel($label, 'active', 'red', $depth, $open),
            $gutter . $p->color($p->label('cancelled'), 'red'),
            $p->bar() . $body_prefix,
          ]);

          return NULL;
        }

        if ($key === 'enter' || !$enter_only) {
          $p->readCharacterRest($key);

          return $finish($line_count);
        }
      }
    }
    finally {
      if ($is_standalone) {
        $p->teardownTty();
      }
    }
  }

  /**
   * Runs an action and reports how it went.
   *
   * While the action runs, the label is drawn with a spinner. Once it returns,
   * the label, the hint, any detail lines and a result line replace the
   * spinner. The spinner animates when a child process can be forked to draw
   * it, and shows 1 static frame otherwise.
   *
   * Without a live terminal - non-interactive, ANSI off, or output that is not
   * a terminal - the label is printed before the action runs and the rest is
   * printed after it.
   *
   * An exception from the action is rethrown once the spinner is cleared, or
   * once a streamed window is expanded into the full output.
   *
   * @param string $label
   *   The task label shown to the user.
   * @param callable $action
   *   The work to run. It returns FALSE on failure, and any other value on
   *   success. A list of strings is drawn as detail lines under the label.
   * @param string|callable $hint
   *   Optional dim line under the label, or a callable that receives the
   *   action's return value and returns the line.
   * @param string|callable $success
   *   The result line on success, or a callable that receives the action's
   *   return value and returns the line. '' draws the 'done' label.
   * @param string|callable $failure
   *   The result line on failure, or a callable that receives the action's
   *   return value and returns the line. '' draws the 'failed' label.
   * @param bool $stream
   *   Draw what the action writes instead of a spinner: 'echo' and 'print'
   *   output, child process output passed through PHP's output layer, such as
   *   from passthru(), and Prompty output while a stream or callable target
   *   is configured. Each line is drawn dim and indented under the label. A
   *   live terminal shows the last lines in a window that collapses into the
   *   result on success and expands into the full output on failure.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<array-key, mixed> $children
   *   Child steps to execute after this task.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return mixed
   *   While a flow is being defined, a closure, or a step array when a
   *   condition or children are set. That closure answers the step with TRUE
   *   when the action succeeded and FALSE when it failed. Otherwise the
   *   action's return value.
   *
   * @throws \UnexpectedValueException
   *   When a $hint, $success or $failure callable returns anything other than
   *   a string.
   */
  public static function task(
    string $label,
    callable $action,
    string|callable $hint = '',
    string|callable $success = '',
    string|callable $failure = '',
    bool $stream = FALSE,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): mixed {
    if (static::$isInFlow && $ctx === NULL) {
      // A flow result keeps the outcome rather than the return value, which
      // can be NULL, the value a cancelled step answers with.
      $call = fn(array $ctx): bool => static::task(
        $label,
        action: $action,
        hint: $hint,
        success: $success,
        failure: $failure,
        stream: $stream,
        ctx: $ctx,
      ) !== FALSE;

      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $is_standalone = !static::$isInFlow;
    $plain_label = $label;

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);
    $is_live = $p->isLive();

    $outcome = function (mixed $value) use ($p, $plain_label, $hint, $success, $failure, $open): array {
      $has_succeeded = $value !== FALSE;
      $message = $p->taskText($plain_label, $has_succeeded ? 'success' : 'failure', $has_succeeded ? $success : $failure, $value);

      return [
        'ok' => $has_succeeded,
        'hint' => $p->taskText($plain_label, 'hint', $hint, $value),
        'details' => is_array($value) && array_is_list($value) && count(array_filter($value, is_string(...))) === count($value) ? $value : [],
        'message' => $message !== '' ? $message : $p->label($has_succeeded ? 'done' : 'failed'),
        'open' => $p->settleOpen($has_succeeded, $open),
      ];
    };

    if ($is_live && $is_standalone) {
      $p->hideCursor();
    }

    // A flow holds the terminal in raw mode, where Ctrl+C is a key rather than
    // SIGINT. The action runs with the settings the flow saved, so Ctrl+C
    // stops it and its child processes read a normal terminal.
    $tty = $p->prevTty;

    if ($tty !== NULL) {
      // @codeCoverageIgnoreStart
      $p->restoreTty($tty);
      // @codeCoverageIgnoreEnd
    }

    try {
      return $stream ? $p->runStreamed($label, $action, $outcome, $depth, $open, $is_live) : $p->runSpinner($label, $action, $outcome, $depth, $open, $is_live);
    }
    finally {
      if ($tty !== NULL) {
        // @codeCoverageIgnoreStart
        $p->enterRawMode();
        // @codeCoverageIgnoreEnd
      }

      if ($is_live && $is_standalone) {
        $p->showCursor();
      }
    }
  }

  /**
   * Prints an informational, success, warning or error message.
   *
   * The type's symbol takes the place of the bar on the first line, and the
   * message is drawn in the type's colour. Inside the steps callable of a
   * flow it declares a keyless step instead, drawn in the tree where it sits.
   * A note adds nothing to the results.
   *
   * @param string $message
   *   The message. A newline starts another line, and SGR styling is kept.
   * @param string $type
   *   One of 'info', 'success', 'warning' or 'error'.
   * @param callable|null $condition
   *   Optional condition callback; hides the note when it returns FALSE. Only
   *   a note declared in a flow has answers to test.
   *
   * @return array<string, mixed>|null
   *   The note step array while a flow is being defined, otherwise NULL.
   *
   * @throws \InvalidArgumentException
   *   When the type is not one of the 4 types.
   * @throws \LogicException
   *   When a condition is given outside the steps callable of a flow.
   */
  public static function note(string $message, string $type = 'info', ?callable $condition = NULL): ?array {
    if (!in_array($type, static::NOTE_TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Note type "%s" is not valid. Use 1 of: %s.', $type, implode(', ', static::NOTE_TYPES)));
    }

    if (static::$isDefining) {
      return ['__note' => $message, '__type' => $type, '__condition' => $condition];
    }

    if ($condition !== NULL) {
      throw new \LogicException('A note condition tests the answers collected so far, so it can only be given to a note declared inside the callable passed to Prompty::flow().');
    }

    $p = static::instance();
    $p->printLines($p->renderNote($message, $type));

    return NULL;
  }

  /**
   * Prints an intro banner.
   */
  public static function intro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderIntro($message));
  }

  /**
   * Prints an outro banner.
   */
  public static function outro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderOutro($message));
  }

  /**
   * Prints a table.
   *
   * Columns take the width of their widest cell. When the table is wider
   * than the terminal, the widest column shrinks and its cells wrap. A cell
   * may hold a newline to start another line.
   *
   * @param array<mixed> $rows
   *   The rows. A row is a list of cells, or a string drawn as a section row
   *   that spans every column. A cell is a string, an integer or a float.
   * @param array<mixed> $headers
   *   The header cells, or [] for no header row.
   *
   * @throws \InvalidArgumentException
   *   When a row is neither a list of cells nor a string, or a cell is not a
   *   string, an integer or a float.
   */
  public static function table(array $rows, array $headers = []): void {
    $p = static::instance();
    $lines = $p->renderTable($rows, $headers);

    if ($lines === []) {
      return;
    }

    $p->printLines($lines);
  }

  /**
   * Prints content in a box, under an optional title.
   *
   * The box is as wide as its widest line, up to the terminal width. A line
   * wider than that wraps, and its further rows keep its leading spaces.
   *
   * @param string $content
   *   The text in the box. A newline starts another line.
   * @param string $title
   *   The title drawn above the content and underlined, or '' for none.
   */
  public static function box(string $content, string $title = ''): void {
    $p = static::instance();
    $p->printLines($p->renderBox($content, $title));
  }

  /**
   * Prints an array of lines and returns the line count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   *
   * @return int
   *   The number of lines printed.
   */
  public static function output(array $lines): int {
    static::instance()->printLines($lines);

    return count($lines);
  }

  /**
   * Styles text with configured color names.
   *
   * Each line is wrapped in the codes and a reset of its own, and an empty
   * line stays empty. Inside a line, a sequence that switches an attribute
   * off is followed by the codes again, so a nested style ends without
   * ending this one. With ANSI off, the text is returned unchanged.
   *
   * @param string $text
   *   The text to style. It may span several lines.
   * @param string ...$styles
   *   Names from the 'colors' configuration, such as 'bold' or 'green'.
   *
   * @return string
   *   The styled text.
   *
   * @throws \InvalidArgumentException
   *   When a name is 'reset' or not a key of the 'colors' configuration.
   */
  public static function style(string $text, string ...$styles): string {
    $p = static::instance();
    $codes = '';

    foreach ($styles as $style) {
      if ($style === 'reset' || !array_key_exists($style, $p->cfgColors)) {
        $available = array_diff(array_keys($p->cfgColors), ['reset']);

        throw new \InvalidArgumentException(sprintf('Style "%s" is not valid. Available styles: %s.', $style, implode(', ', $available)));
      }

      $codes .= $p->cfgColors[$style];
    }

    if ($codes === '') {
      return $text;
    }

    $lines = array_map(function (string $line) use ($p, $codes): string {
      if ($line === '') {
        return '';
      }

      $line = preg_replace_callback('/\033\[[0-9;:]*m/', fn(array $match): string => $match[0] . ($p->switchesOff($match[0]) ? $codes : ''), $line) ?? $line;

      return $codes . $line . $p->cfgColors['reset'];
    }, explode("\n", $text));

    return implode("\n", $lines);
  }

  /**
   * Removes ANSI escape sequences from text.
   *
   * @param string $text
   *   Text that may hold escape sequences.
   *
   * @return string
   *   The text without its escape sequences.
   */
  public static function strip(string $text): string {
    return static::instance()->stripAnsi($text);
  }

  /**
   * Measures the columns a terminal uses to draw text.
   *
   * This is the measure Prompty lays out and wraps its own lines with.
   *
   * @param string $text
   *   Text that may hold escape sequences. It may span several lines.
   *
   * @return int
   *   The width of the widest line. Escape sequences count as 0 and a wide
   *   character as 2.
   */
  public static function width(string $text): int {
    $p = static::instance();

    return max(array_map($p->displayWidth(...), explode("\n", $text)));
  }

  /**
   * Returns the terminal width Prompty wraps its lines to.
   *
   * @return int
   *   The configured 'width', a positive COLUMNS environment variable, the
   *   width 'stty size' reports, or 80, in that order.
   */
  public static function terminalWidth(): int {
    $p = static::instance();

    // Reading the frame width would fix it until the next frame is printed.
    return $p->cfgWidth > 0 ? $p->cfgWidth : $p->detectedColumns();
  }

  /**
   * Rejects configuration keys outside the set the default declares.
   *
   * @param string $parameter
   *   The configure() parameter that supplied the values.
   * @param array<string, string> $values
   *   The supplied overrides.
   * @param array<string, string> $declared
   *   The defaults, whose keys are the valid set.
   *
   * @throws \InvalidArgumentException
   *   When a key is not part of the declared set.
   */
  protected function assertConfigKeys(string $parameter, array $values, array $declared): void {
    foreach (array_keys($values) as $key) {
      if (!array_key_exists($key, $declared)) {
        throw new \InvalidArgumentException(sprintf('Configuration key "%s" for "%s" is not valid. Available keys: %s.', $key, $parameter, implode(', ', array_keys($declared))));
      }
    }
  }

  /**
   * Rejects a configuration list that holds nothing usable.
   *
   * @param string $parameter
   *   The configure() parameter that supplied the values.
   * @param list<string> $values
   *   The supplied values.
   *
   * @throws \InvalidArgumentException
   *   When the list is empty or holds a blank value.
   */
  protected function assertConfigList(string $parameter, array $values): void {
    if ($values === []) {
      throw new \InvalidArgumentException(sprintf('No values declared for "%s". Provide at least one value.', $parameter));
    }

    foreach ($values as $value) {
      // Values are matched after trimming, so a blank entry would match an
      // empty discovered value, which the widget otherwise rejects.
      if (trim($value) === '') {
        throw new \InvalidArgumentException(sprintf('Blank value declared for "%s". Every value must hold a non-space character.', $parameter));
      }
    }
  }

  /**
   * Rejects a step total format that sprintf() cannot fill.
   *
   * @param string $format
   *   The format, which receives the step number and then the total.
   *
   * @throws \InvalidArgumentException
   *   When the format needs more than 2 arguments or holds an unknown
   *   conversion.
   */
  protected function assertStepTotalFormat(string $format): void {
    try {
      sprintf($format, 1, 1);
    }
    catch (\ValueError | \ArgumentCountError $error) {
      throw new \InvalidArgumentException(sprintf('Format "%s" for the "step_total" label is not valid (%s). Use a format that takes the step number and the total, such as "(%%d/%%d)".', $format, $error->getMessage()), 0, $error);
    }
  }

  /**
   * Rejects a back key that cannot be told apart from other keys.
   *
   * @param string $back_key
   *   The key name to check.
   *
   * @throws \InvalidArgumentException
   *   When $back_key is neither '' nor a Ctrl key that readKey() names as
   *   itself.
   */
  protected function assertBackKey(string $back_key): void {
    if ($back_key === '') {
      return;
    }

    if (preg_match('/^ctrl-[a-z]$/', $back_key) !== 1 || in_array($back_key, static::RESERVED_CTRL_KEYS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Back key "%s" for "back_key" is not valid. Use a Ctrl key from "ctrl-a" to "ctrl-z" other than "ctrl-c", "ctrl-h", "ctrl-i", "ctrl-j" and "ctrl-m", or "" to turn stepping back off.', $back_key));
    }
  }

  /**
   * Rejects a scroll size that cannot be drawn.
   *
   * @param int $scroll
   *   The supplied scroll size.
   * @param string $subject
   *   The widget label, or the configure() parameter that supplied the size.
   *
   * @throws \InvalidArgumentException
   *   When the size is negative.
   */
  protected function assertScroll(int $scroll, string $subject): void {
    if ($scroll < 0) {
      throw new \InvalidArgumentException(sprintf('Scroll size %d for "%s" is not valid. Use 0 to show every option, or the number of options to show at once.', $scroll, $subject));
    }
  }

  /**
   * Validates an output target and returns it in the form write() uses.
   *
   * @param mixed $output
   *   The supplied target.
   *
   * @return resource|\Closure
   *   The stream as given, or the callable as a closure.
   *
   * @throws \InvalidArgumentException
   *   When the target is neither a callable nor an open, writable stream.
   */
  protected function resolveOutputTarget(mixed $output) {
    if (is_callable($output)) {
      return $output(...);
    }

    if (!is_resource($output) || get_resource_type($output) !== 'stream') {
      throw new \InvalidArgumentException(sprintf('Output target of type "%s" for "output" is not valid. Provide a writable stream or a callable that receives each chunk.', get_debug_type($output)));
    }

    $mode = stream_get_meta_data($output)['mode'];

    if (strpbrk($mode, 'waxc+') === FALSE) {
      throw new \InvalidArgumentException(sprintf('Output stream for "output" is opened in read-only mode "%s". Provide a writable stream.', $mode));
    }

    return $output;
  }

  /**
   * Rejects a widget declared without options.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty.
   */
  protected function assertDeclaredOptions(string $label, array $options): void {
    if ($options === []) {
      throw new \InvalidArgumentException(sprintf('No options declared for "%s". Provide at least one option.', $label));
    }
  }

  /**
   * Validates a supplied value against a widget's declared options.
   *
   * @param string $parameter
   *   The parameter that supplied the value, capitalized, opening the error
   *   message: 'Discovered' or 'Default'.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The supplied value.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return string
   *   The supplied value as a trimmed option key.
   *
   * @throws \InvalidArgumentException
   *   When the value is not a key of $options.
   */
  protected function assertOptionKey(string $parameter, string $label, mixed $value, array $options): string {
    $key = is_scalar($value) ? trim((string) $value) : get_debug_type($value);

    if (!array_key_exists($key, $options)) {
      $available = $options === [] ? 'none' : implode(', ', $this->optionKeys($options));

      throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not a valid option. Available options: %s.', $parameter, $key, $label, $available));
    }

    return $key;
  }

  /**
   * Validates supplied multiselect values against the declared options.
   *
   * Entries that are empty after trimming are dropped, so an empty value and a
   * trailing comma both mean "nothing selected". Surviving entries are checked
   * for membership and returned in the order given, each at its first
   * position.
   *
   * @param string $parameter
   *   The parameter that supplied the value, capitalized, opening the error
   *   message: 'Discovered' or 'Default'.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The supplied value: a list of option keys, or a single option key.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return list<string>
   *   The selected option keys, deduplicated and in the order given.
   *
   * @throws \InvalidArgumentException
   *   When any entry is not a key of $options.
   */
  protected function assertOptionKeys(string $parameter, string $label, mixed $value, array $options): array {
    $entries = is_array($value) ? $value : [$value];
    $selected = [];

    foreach ($entries as $entry) {
      if (is_scalar($entry) && trim((string) $entry) === '') {
        continue;
      }

      $selected[] = $this->assertOptionKey($parameter, $label, $entry, $options);
    }

    return array_values(array_unique($selected));
  }

  /**
   * Rejects a multiselect answer order that is not known.
   *
   * @param string|\Closure $order
   *   The order to check. A closure is always accepted.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   *
   * @throws \InvalidArgumentException
   *   When $order is a string other than 'options', 'default' or 'selection'.
   */
  protected function assertOrder(string|\Closure $order, string $label): void {
    if (is_string($order) && !in_array($order, ['options', 'default', 'selection'], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Order "%s" for "%s" is not valid. Use "options", "default" or "selection".', $order, $label));
    }
  }

  /**
   * Arranges selected multiselect keys in an answer order.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param list<string> $keys
   *   The selected option keys, in the order they were selected.
   * @param string|\Closure $order
   *   One of 'options', 'default' or 'selection', or a comparator closure.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   * @param list<string> $default_keys
   *   The default option keys, in default order.
   *
   * @return list<string>
   *   The selected keys in $order.
   *
   * @throws \UnexpectedValueException
   *   When a comparator returns anything other than an int.
   */
  protected function orderKeys(string $label, array $keys, string|\Closure $order, array $options, array $default_keys): array {
    if ($order instanceof \Closure) {
      // usort() is stable, so keys the comparator ranks equal keep the order
      // they were selected in.
      usort($keys, function (string $a, string $b) use ($label, $order): int {
        $result = $order($a, $b);

        if (!is_int($result)) {
          $this->teardownTty();

          throw new \UnexpectedValueException(sprintf('Order for "%s" returned %s. An order closure compares 2 option keys and returns an int, as for usort().', $label, get_debug_type($result)));
        }

        return $result;
      });

      return $keys;
    }

    $in_options = array_values(array_filter($this->optionKeys($options), fn(string $key): bool => in_array($key, $keys, TRUE)));

    return match ($order) {
      'selection' => $keys,
      'default' => [
        ...array_values(array_filter($default_keys, fn(string $key): bool => in_array($key, $keys, TRUE))),
        ...array_values(array_filter($in_options, fn(string $key): bool => !in_array($key, $default_keys, TRUE))),
      ],
      default => $in_options,
    };
  }

  /**
   * Lists a widget's option keys as strings.
   *
   * PHP casts a canonical decimal-integer string array key to an int, so
   * array_keys() over options such as ['10' => 'Table 10'] yields ints.
   *
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return list<string>
   *   The option keys in declaration order.
   */
  protected function optionKeys(array $options): array {
    return array_map(strval(...), array_keys($options));
  }

  /**
   * Returns the text the completed line shows for a single answer key.
   *
   * @param string $key
   *   The answer key.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return string
   *   The option label for a key of $options, or the key itself.
   */
  protected function optionDisplay(string $key, array $options): string {
    return array_key_exists($key, $options) ? $options[$key] : $key;
  }

  /**
   * Returns the text the completed line shows for a list of answer keys.
   *
   * @param list<string> $keys
   *   The answer keys.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return string
   *   The comma-separated labels, or the 'none' label for an empty list.
   */
  protected function optionsDisplay(array $keys, array $options): string {
    if ($keys === []) {
      return $this->label('none');
    }

    return implode(', ', array_map(fn(string $key): string => $this->optionDisplay($key, $options), $keys));
  }

  /**
   * Resolves how many of a widget's options are drawn at once.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param int|null $scroll
   *   The widget's scroll size, or NULL to use the configured one.
   * @param int $count
   *   The number of declared options.
   *
   * @return int
   *   The number of options to draw, at most $count.
   *
   * @throws \InvalidArgumentException
   *   When the scroll size is negative.
   */
  protected function visibleOptions(string $label, ?int $scroll, int $count): int {
    $scroll ??= $this->cfgScroll;
    $this->assertScroll($scroll, $label);

    return $scroll === 0 ? $count : min($scroll, $count);
  }

  /**
   * Moves a window of options so that it contains the focused option.
   *
   * @param int $first
   *   Index of the first option in the window.
   * @param int $focused
   *   Index of the focused option.
   * @param int $visible
   *   The number of options in the window.
   *
   * @return int
   *   Index of the first option in the moved window. It changes only when the
   *   focused option is outside the window.
   */
  protected function scrollWindow(int $first, int $focused, int $visible): int {
    if ($focused < $first) {
      return $focused;
    }

    if ($focused >= $first + $visible) {
      return $focused - $visible + 1;
    }

    return $first;
  }

  /**
   * Lists the suggestions for the typed text.
   *
   * @param array<int|string, string>|\Closure $options
   *   The suggestions, or a closure returning them for the typed text.
   * @param string $typed
   *   The text in the input buffer.
   *
   * @return list<string>
   *   The matching suggestions as printable text, in the order given.
   */
  protected function suggestMatches(array|\Closure $options, string $typed): array {
    $typed = $this->printable($typed);

    if ($options instanceof \Closure) {
      $found = $options($typed);

      return is_array($found) ? array_values(array_map(fn(string|int|float|bool $entry): string => $this->printable((string) $entry), array_filter($found, is_scalar(...)))) : [];
    }

    $candidates = array_values(array_map($this->printable(...), $options));

    if ($typed === '') {
      return $candidates;
    }

    // A UTF-8 character typed 1 byte at a time leaves the buffer invalid until
    // its last byte arrives, and a /u pattern cannot be built from it.
    $pattern = preg_match('//u', $typed) === 1 ? '/' . preg_quote($typed, '/') . '/iu' : NULL;

    // Text that is not valid UTF-8 is compared byte by byte, folding ASCII
    // case only.
    return array_values(array_filter($candidates, function (string $candidate) use ($pattern, $typed): bool {
      $matched = $pattern === NULL ? FALSE : preg_match($pattern, $candidate);

      return $matched === FALSE ? stripos($candidate, $typed) !== FALSE : $matched === 1;
    }));
  }

  /**
   * Coerces a discovered value to a boolean using the truthy/falsy lists.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The discovered value.
   * @param list<string> $truthy
   *   Values treated as TRUE.
   * @param list<string> $falsy
   *   Values treated as FALSE.
   *
   * @return bool
   *   The coerced boolean.
   *
   * @throws \InvalidArgumentException
   *   When the value appears in neither list.
   */
  protected function assertDiscoveredBool(string $label, mixed $value, array $truthy, array $falsy): bool {
    if (is_bool($value)) {
      return $value;
    }

    $display = is_scalar($value) ? trim((string) $value) : get_debug_type($value);
    $normalize = fn(string $token): string => strtolower(trim($token));
    $normalized = $normalize($display);

    if (in_array($normalized, array_map($normalize, $truthy), TRUE)) {
      return TRUE;
    }

    if (in_array($normalized, array_map($normalize, $falsy), TRUE)) {
      return FALSE;
    }

    $accepted = implode(', ', array_merge($truthy, $falsy));

    throw new \InvalidArgumentException(sprintf('Discovered value "%s" for "%s" is not a valid answer. Accepted values: %s.', $display, $label, $accepted));
  }

  /**
   * Returns the answer a text or suggest gives when nothing was entered.
   *
   * A widget that reads no input never shows its placeholder, so the
   * placeholder answers only when input is read and the fallback is on.
   *
   * @param string $placeholder
   *   The widget's placeholder.
   *
   * @return string
   *   $placeholder, or '' when it does not answer.
   */
  protected function placeholderAnswer(string $placeholder): string {
    return $this->cfgIsInteractive && $this->cfgUsePlaceholderFallback ? $placeholder : '';
  }

  /**
   * Applies a widget's transform to a string answer.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param callable|null $transform
   *   The transform, or NULL when the widget has none.
   * @param string $value
   *   The answer, as the widget would return it.
   *
   * @return string
   *   The transformed answer, or $value when there is no transform.
   *
   * @throws \UnexpectedValueException
   *   When the transform returns anything other than a string.
   */
  protected function transformString(string $label, ?callable $transform, string $value): string {
    if ($transform === NULL) {
      return $value;
    }

    $transformed = $this->runTransform($transform, $value);

    if (!is_string($transformed)) {
      $this->rejectTransformed($label, get_debug_type($transformed), 'a string');
    }

    return $transformed;
  }

  /**
   * Applies a widget's transform to a list of option keys.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param callable|null $transform
   *   The transform, or NULL when the widget has none.
   * @param list<string> $value
   *   The answer, as the widget would return it.
   *
   * @return list<string>
   *   The transformed answer, or $value when there is no transform.
   *
   * @throws \UnexpectedValueException
   *   When the transform returns anything other than a list of strings.
   */
  protected function transformKeys(string $label, ?callable $transform, array $value): array {
    if ($transform === NULL) {
      return $value;
    }

    $transformed = $this->runTransform($transform, $value);

    if (!is_array($transformed) || !array_is_list($transformed)) {
      $this->rejectTransformed($label, get_debug_type($transformed), 'a list of strings');
    }

    $keys = [];

    foreach ($transformed as $entry) {
      if (!is_string($entry)) {
        $this->rejectTransformed($label, 'a list holding ' . get_debug_type($entry), 'a list of strings');
      }

      $keys[] = $entry;
    }

    return $keys;
  }

  /**
   * Applies a widget's transform to a boolean answer.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param callable|null $transform
   *   The transform, or NULL when the widget has none.
   * @param bool $value
   *   The answer, as the widget would return it.
   *
   * @return bool
   *   The transformed answer, or $value when there is no transform.
   *
   * @throws \UnexpectedValueException
   *   When the transform returns anything other than a boolean.
   */
  protected function transformBool(string $label, ?callable $transform, bool $value): bool {
    if ($transform === NULL) {
      return $value;
    }

    $transformed = $this->runTransform($transform, $value);

    if (!is_bool($transformed)) {
      $this->rejectTransformed($label, get_debug_type($transformed), 'a boolean');
    }

    return $transformed;
  }

  /**
   * Calls a widget's transform.
   *
   * The transform is caller code that can run while the terminal is in raw
   * mode, so the terminal is restored before any exception leaves the widget.
   *
   * @param callable $transform
   *   The transform.
   * @param string|bool|list<string> $value
   *   The answer, as the widget would return it.
   *
   * @return mixed
   *   Whatever the transform returns.
   */
  protected function runTransform(callable $transform, string|bool|array $value): mixed {
    try {
      return $transform($value);
    }
    catch (\Throwable $throwable) {
      $this->teardownTty();

      throw $throwable;
    }
  }

  /**
   * Rejects a transformed answer of the wrong type.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param string $actual
   *   A description of what the transform returned.
   * @param string $expected
   *   A description of the type the widget answers with.
   *
   * @throws \UnexpectedValueException
   *   Always.
   */
  protected function rejectTransformed(string $label, string $actual, string $expected): never {
    $this->teardownTty();

    throw new \UnexpectedValueException(sprintf('Transform for "%s" returned %s. A transform returns %s, the type the widget answers with.', $label, $actual, $expected));
  }

  /**
   * Runs a widget's validator against an answer.
   *
   * The validator is caller code that can run while the terminal is in raw
   * mode, so the terminal is restored before any exception leaves the widget.
   *
   * An empty answer to a required widget is rejected before the validator
   * runs, so the validator only receives answers that are not empty.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param bool|string $required
   *   FALSE, TRUE to reject an empty answer with the 'required' label, or the
   *   message to reject it with.
   * @param callable|null $validate
   *   The validator, or NULL when the widget has none.
   * @param string|bool|list<string> $value
   *   The answer, as the widget would return it.
   * @param array<string, mixed> $results
   *   The flow results collected so far.
   *
   * @return string|null
   *   The trimmed error message, or NULL when the answer is valid. A message
   *   with no printable text counts as valid.
   *
   * @throws \UnexpectedValueException
   *   When the validator returns neither NULL nor a string.
   */
  protected function validateAnswer(string $label, bool|string $required, ?callable $validate, string|bool|array $value, array $results): ?string {
    $is_empty = is_array($value) ? $value === [] : is_string($value) && trim($value) === '';

    if (($required === FALSE || !$is_empty) && $validate === NULL) {
      return NULL;
    }

    try {
      if ($required !== FALSE && $is_empty) {
        // A blank message would read as no error, so it takes the label.
        $error = is_string($required) && trim($this->printable($required)) !== '' ? trim($required) : $this->label('required');
      }
      else {
        /** @var callable $validate */
        $error = $validate($value, $results);

        if ($error !== NULL && !is_string($error)) {
          throw new \UnexpectedValueException(sprintf('Validator for "%s" returned %s. A validator returns NULL for a valid answer or an error message string.', $label, get_debug_type($error)));
        }

        $error = $error === NULL || trim($this->printable($error)) === '' ? NULL : trim($error);
      }

      if ($error !== NULL && static::$onInvalid instanceof \Closure) {
        (static::$onInvalid)($label, $value, $error);
      }
    }
    catch (\Throwable $throwable) {
      $this->teardownTty();

      throw $throwable;
    }

    return $error;
  }

  /**
   * Rejects an empty or invalid answer that was resolved without input.
   *
   * @param string $parameter
   *   The source of the answer, capitalized, opening the error message:
   *   'Discovered' or 'Default'.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param bool|string $required
   *   FALSE, TRUE to reject an empty answer with the 'required' label, or the
   *   message to reject it with.
   * @param callable|null $validate
   *   The validator, or NULL when the widget has none.
   * @param string|bool|list<string> $value
   *   The resolved answer, as the widget would return it.
   * @param array<string, mixed> $results
   *   The flow results collected so far.
   *
   * @throws \InvalidArgumentException
   *   When the answer is required and empty, or the validator returns an
   *   error message.
   */
  protected function assertResolvedValid(string $parameter, string $label, bool|string $required, ?callable $validate, string|bool|array $value, array $results): void {
    $error = $this->validateAnswer($label, $required, $validate, $value, $results);

    if ($error === NULL) {
      return;
    }

    // A list is shown in the comma-separated form a variable supplies it in.
    $display = match (TRUE) {
      is_bool($value) => $value ? 'true' : 'false',
      is_array($value) => implode(',', $value),
      default => $value,
    };

    throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not valid. %s', $parameter, $display, $label, $error));
  }

  /**
   * Reads a single keypress from the input stream and returns its name.
   *
   * A stream that is exhausted or cannot be read yields 'eof', which ends the
   * widget instead of being retried. Any other control byte from 0x01 to
   * 0x1A reads as 'ctrl-a' to 'ctrl-z'.
   *
   * Reading a key marks the flow step being rendered as one that asked,
   * unless $is_asking is FALSE.
   *
   * @param bool $is_asking
   *   Whether the key answers a question the step can be reopened for.
   *
   * @return string
   *   The key name.
   */
  protected function readKey(bool $is_asking = TRUE): string {
    $last = array_key_last($this->journal);

    if ($is_asking && $last !== NULL) {
      $this->journal[$last]['asked'] = TRUE;
    }

    $stream = $this->input ?? STDIN;
    $char = fread($stream, 1);

    if ($char === FALSE || $char === '') {
      return 'eof';
    }

    return match (TRUE) {
      $char === "\x03" => 'ctrl-c',
      $char === "\n", $char === "\r" => 'enter',
      $char === "\x7f", $char === "\x08" => 'backspace',
      $char === "\t" => 'tab',
      $char === ' ' => 'space',
      $char === "\x1b" => $this->readEscapeSequence($stream),
      ord($char) >= 0x01 && ord($char) <= 0x1A => 'ctrl-' . chr(ord($char) + 0x60),
      default => $char,
    };
  }

  /**
   * Reads the rest of an escape sequence and returns the key it encodes.
   *
   * A terminal sends a cursor key as ESC followed by a CSI ('[') or SS3 ('O')
   * sequence. Any other ESC reads as the Escape key, including ESC followed
   * by a plain byte, which a terminal sends for a key pressed with Alt.
   *
   * @param resource $stream
   *   The input stream, positioned after the ESC byte.
   *
   * @return string
   *   'up', 'down', 'right' or 'left' for a cursor key, 'unknown' for any
   *   other complete sequence, or 'escape'.
   */
  protected function readEscapeSequence($stream): string {
    $introducer = $this->readSequenceByte($stream);

    if ($introducer !== '[' && $introducer !== 'O') {
      return 'escape';
    }

    $sequence = '';

    // An SS3 sequence ends after 1 byte. A CSI sequence continues through
    // bytes in 0x20-0x3F and ends with the first byte outside that range.
    do {
      $byte = $this->readSequenceByte($stream);

      if ($byte === '') {
        return 'escape';
      }

      $sequence .= $byte;
    } while ($introducer === '[' && ord($byte) >= 0x20 && ord($byte) <= 0x3F);

    return match ($sequence) {
      'A' => 'up',
      'B' => 'down',
      'C' => 'right',
      'D' => 'left',
      default => 'unknown',
    };
  }

  /**
   * Reads the next byte of an escape sequence if it arrives in time.
   *
   * @param resource $stream
   *   The input stream.
   *
   * @return string
   *   The byte, or an empty string when none arrives within ESCAPE_TIMEOUT or
   *   the stream has ended.
   */
  protected function readSequenceByte($stream): string {
    $read = [$stream];
    $write = NULL;
    $except = NULL;

    // Seekable streams such as php://memory are read without a poll: their
    // bytes are all present, and stream_select() rejects a memory stream. A
    // failed poll returns FALSE, not 0, and falls through to the read.
    if (!stream_get_meta_data($stream)['seekable'] && stream_select($read, $write, $except, 0, static::ESCAPE_TIMEOUT) === 0) {
      return '';
    }

    return (string) fread($stream, 1);
  }

  /**
   * Reads the remaining bytes of a UTF-8 character whose first byte is a key.
   *
   * A key is read 1 byte at a time by readKey(), so a multibyte character left
   * unread would reach the next widget as stray bytes.
   *
   * @param string $key
   *   A key returned by readKey().
   */
  protected function readCharacterRest(string $key): void {
    if (strlen($key) !== 1) {
      return;
    }

    $byte = ord($key);
    $rest = match (TRUE) {
      $byte >= 0xF8 => 0,
      $byte >= 0xF0 => 3,
      $byte >= 0xE0 => 2,
      $byte >= 0xC0 => 1,
      default => 0,
    };

    if ($rest > 0) {
      fread($this->input ?? STDIN, $rest);
    }
  }

  /**
   * Checks whether a key leaves the widget without an answer.
   *
   * @param string $key
   *   A key name returned by readKey().
   *
   * @return bool
   *   TRUE for the 2 cancel keys and for the end of the input stream.
   */
  protected function isCancelKey(string $key): bool {
    return in_array($key, ['ctrl-c', 'escape', 'eof'], TRUE);
  }

  /**
   * Checks whether a key steps back, and records the request when it does.
   *
   * The key steps back only in a flow step that has an earlier step to
   * reopen; anywhere else it does nothing.
   *
   * @param string $key
   *   A key name returned by readKey().
   *
   * @return bool
   *   TRUE when the widget ends so the flow can reopen an earlier step.
   */
  protected function requestBack(string $key): bool {
    if ($this->cfgBackKey === '' || $key !== $this->cfgBackKey || $this->pending === NULL) {
      return FALSE;
    }

    $this->stepBack = $this->backTarget();

    return $this->stepBack !== NULL;
  }

  /**
   * Finds the journal entry of the step to reopen when stepping back.
   *
   * The step to reopen is the latest earlier step that read a key, at the
   * running step's depth or shallower: a previous sibling, else the parent.
   * When there is none, it is the latest earlier step that read a key.
   *
   * @return int|null
   *   The index of the entry in the journal, or NULL when there is none.
   */
  protected function backTarget(): ?int {
    $earlier = $this->journal;
    $current = array_pop($earlier);
    $depth = $current['depth'] ?? 0;
    $deeper = NULL;

    for ($index = count($earlier) - 1; $index >= 0; $index--) {
      if (!$earlier[$index]['asked']) {
        continue;
      }

      if ($earlier[$index]['depth'] <= $depth) {
        return $index;
      }

      $deeper ??= $index;
    }

    // A parent that asked nothing, such as one answered from the environment,
    // leaves only its children to go back to.
    return $deeper;
  }

  /**
   * Erases the steps drawn since the step to reopen, and plans the replay.
   *
   * The answers so far are kept as defaults, and the walk that follows
   * replays the steps before the reopened one without drawing them.
   *
   * @param int $target
   *   The journal index of the step to reopen.
   */
  protected function stepBackTo(int $target): void {
    $entry = $this->journal[$target];

    $this->previous = array_replace($this->previous, $this->results);
    $this->clearRows($this->rows - $entry['rows']);
    $this->journal = array_slice($this->journal, 0, $target);
    $this->replay = $entry['key'];
    $this->stepBack = NULL;
  }

  /**
   * Clears the state stepping back keeps for the running flow.
   */
  protected function resetStepBack(): void {
    $this->rows = 0;
    $this->journal = [];
    $this->previous = [];
    $this->inputs = [];
    $this->replay = NULL;
    $this->stepBack = NULL;
  }

  /**
   * Records the input an asked flow step was accepted with.
   *
   * @param mixed $input
   *   The input before the widget's transform: the typed text, the focused
   *   option key, the checked option keys or the focused answer.
   */
  protected function recordInput(mixed $input): void {
    if ($this->pending !== NULL) {
      $this->inputs[(string) $this->pending['key']] = $input;
    }
  }

  /**
   * Returns the input a reopened step was accepted with, when it suits.
   *
   * @param array<string, mixed> $ctx
   *   The flow context, carrying 'previous' when the step was answered
   *   before the user stepped back.
   * @param mixed $default
   *   The widget's declared default.
   * @param \Closure $accepts
   *   Receives the previous input and returns whether the widget can start
   *   from it.
   *
   * @return mixed
   *   The previous input, or $default when there is none or it does not
   *   suit the widget.
   */
  protected function previousAnswer(array $ctx, mixed $default, \Closure $accepts): mixed {
    if (!array_key_exists('previous', $ctx) || !$accepts($ctx['previous'])) {
      return $default;
    }

    return $ctx['previous'];
  }

  /**
   * Puts the terminal into raw mode and saves the settings it replaces.
   *
   * Leaves prevTty NULL when an input stream is injected or stdin is not a
   * TTY.
   */
  protected function setupTty(): void {
    $this->prevTty = $this->input === NULL ? (shell_exec('stty -g 2>' . static::NULL_DEVICE) ?: NULL) : NULL;

    if ($this->prevTty !== NULL) {
      // @codeCoverageIgnoreStart
      $this->prevTty = trim($this->prevTty);
      $this->enterRawMode();
      $this->hideCursor();
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * Switches the terminal to unbuffered input without echo or signals.
   */
  protected function enterRawMode(): void {
    // @codeCoverageIgnoreStart
    // -isig delivers Ctrl+C to readKey() as a byte rather than as SIGINT,
    // which would end the process before the terminal is restored. -ixon and
    // -iexten deliver Ctrl+S, Ctrl+Q, Ctrl+V and Ctrl+O, which the terminal
    // driver otherwise consumes, so each can be the back key.
    shell_exec('stty -echo -icanon -isig -ixon -iexten min 1 time 0 2>' . static::NULL_DEVICE);
    // @codeCoverageIgnoreEnd
  }

  /**
   * Restores the terminal settings saved by setupTty() and shows the cursor.
   */
  protected function teardownTty(): void {
    if ($this->prevTty !== NULL) {
      // @codeCoverageIgnoreStart
      $this->restoreTty($this->prevTty);
      $this->showCursor();
      $this->prevTty = NULL;
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * Restores TTY settings to the previously saved state.
   */
  protected function restoreTty(string $prev): void {
    shell_exec('stty ' . $prev . ' 2>' . static::NULL_DEVICE);
  }

  /**
   * Shows the terminal cursor.
   */
  protected function showCursor(): void {
    $this->write("\033[?25h");
  }

  /**
   * Hides the terminal cursor.
   */
  protected function hideCursor(): void {
    $this->write("\033[?25l");
  }

  /**
   * Checks whether output can be redrawn in place while work runs.
   *
   * @return bool
   *   TRUE when the widget is interactive, ANSI is on and the output target is
   *   a terminal. A callable target is never a terminal.
   */
  protected function isLive(): bool {
    if (!$this->cfgIsInteractive || !$this->cfgUseAnsi) {
      return FALSE;
    }

    if ($this->isTerminal !== NULL) {
      return $this->isTerminal;
    }

    if ($this->cfgOutput instanceof \Closure) {
      return FALSE;
    }

    return stream_isatty($this->cfgOutput ?? STDOUT);
  }

  /**
   * Resolves a task's hint or result text.
   *
   * @param string $label
   *   The task label, used to identify the task in the error message.
   * @param string $parameter
   *   The task() parameter the text came from.
   * @param string|callable $text
   *   The text, or a callable that receives the action's return value.
   * @param mixed $value
   *   The action's return value.
   *
   * @return string
   *   The text to draw.
   *
   * @throws \UnexpectedValueException
   *   When the callable returns anything other than a string.
   */
  protected function taskText(string $label, string $parameter, string|callable $text, mixed $value): string {
    if (is_string($text)) {
      return $text;
    }

    $resolved = $text($value);

    if (!is_string($resolved)) {
      throw new \UnexpectedValueException(sprintf('The %s callable for task "%s" returned %s. It must return the text to draw.', $parameter, $label, get_debug_type($resolved)));
    }

    return $resolved;
  }

  /**
   * Runs a task's action behind a spinner and draws the outcome.
   *
   * @param string $label
   *   The task label, numbered and printable.
   * @param callable $action
   *   The work to run.
   * @param \Closure $outcome
   *   Receives the return value and resolves what is drawn for it.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   * @param bool $is_live
   *   Whether the output can be redrawn in place.
   *
   * @return mixed
   *   The action's return value.
   */
  protected function runSpinner(string $label, callable $action, \Closure $outcome, int $depth, array $open, bool $is_live): mixed {
    $frames = preg_split('//u', $this->cfgSymbols['spinner'], -1, PREG_SPLIT_NO_EMPTY) ?: [$this->cfgSymbols['spinner']];
    $frame = fn(int $index): array => $this->renderMarkedLabel($label, $this->color($frames[$index % count($frames)], 'cyan'), $depth, $open);

    $rows = $this->printLines($is_live ? $frame(0) : $this->renderLabel($label, 'active', 'cyan', $depth, $open));
    $spinner = $is_live ? $this->animate($frame, $rows) : NULL;

    try {
      $value = $action();
      /** @var array{ok: bool, hint: string, details: list<string>, message: string, open: array<int, bool>} $resolved */
      $resolved = $outcome($value);
    }
    catch (\Throwable $throwable) {
      $this->stopAnimation($spinner);

      if ($is_live) {
        $this->clearRows($rows);
      }

      throw $throwable;
    }

    $this->stopAnimation($spinner);

    $result = $this->renderTaskResult($resolved['ok'], $resolved['hint'], $resolved['details'], $resolved['message'], '', $depth, $resolved['open']);

    if (!$is_live) {
      $this->printLines($result);

      return $value;
    }

    $this->redraw($rows, [...$this->renderTaskLabel($label, $resolved['ok'], $depth, $resolved['open']), ...$result]);

    return $value;
  }

  /**
   * Runs a task's action and draws every line it writes under the label.
   *
   * @param string $label
   *   The task label, numbered and printable.
   * @param callable $action
   *   The work to run.
   * @param \Closure $outcome
   *   Receives the return value and resolves what is drawn for it.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   * @param bool $is_live
   *   Whether the output can be redrawn in place. A live task shows the last
   *   lines in a window; otherwise every line is printed as it arrives.
   *
   * @return mixed
   *   The action's return value.
   */
  protected function runStreamed(string $label, callable $action, \Closure $outcome, int $depth, array $open, bool $is_live): mixed {
    $this->streamed = [];
    $this->streamPartial = '';
    $this->isStreamLive = $is_live;
    $rows = $this->printLines($this->renderLabel($label, 'active', 'cyan', $depth, $open));

    // A live stream redraws the window; otherwise each line is printed once.
    if ($is_live) {
      $this->streamDraw = function () use ($label, $depth, $open, &$rows): void {
        $rows = $this->redraw($rows, array_merge($this->renderLabel($label, 'active', 'cyan', $depth, $open), $this->renderTaskWindow($depth, $open)));
      };
    }
    else {
      $this->streamDraw = function (string $line) use ($depth, $open): void {
        $this->printLines($this->renderTaskLine($line, $depth, $open));
      };
    }

    $level = ob_get_level();
    // Chunk size 1 hands every write to the handler as it is made.
    ob_start($this->captureStream(...), 1);

    try {
      try {
        $value = $action();
      }
      finally {
        $this->endStream($level);
      }

      /** @var array{ok: bool, hint: string, details: list<string>, message: string, open: array<int, bool>} $resolved */
      $resolved = $outcome($value);
    }
    catch (\Throwable $throwable) {
      if ($is_live) {
        $this->redraw($rows, array_merge($this->renderLabel($label, 'active', 'red', $depth, $open), $this->renderTaskLines($this->streamedLines(), $depth, $open)));
      }

      throw $throwable;
    }
    finally {
      $lines = $this->streamedLines();
      $this->streamed = NULL;
    }

    $has_succeeded = $resolved['ok'];
    $settled = $resolved['open'];
    $gap = $lines !== [] ? [$this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $settled) : '')] : [];
    $count = count($lines);

    // A collapsed window says how much output it hid.
    $note = $is_live && $has_succeeded && $count > 0 ? sprintf(' (%d %s)', $count, $this->label($count === 1 ? 'line' : 'lines')) : '';
    $result = $this->renderTaskResult($has_succeeded, $resolved['hint'], $resolved['details'], $resolved['message'], $note, $depth, $settled);

    if (!$is_live) {
      $this->printLines(array_merge($gap, $result));

      return $value;
    }

    // A failure keeps every line, since the cause is among them.
    $body = $has_succeeded ? $result : array_merge($this->renderTaskLines($lines, $depth, $settled), $gap, $result);
    $this->redraw($rows, array_merge($this->renderTaskLabel($label, $has_succeeded, $depth, $settled), $body));

    return $value;
  }

  /**
   * Returns the lines the streaming task's action has written.
   *
   * @return list<string>
   *   The lines, or none when no task streams.
   */
  protected function streamedLines(): array {
    return $this->streamed ?? [];
  }

  /**
   * Turns a chunk of buffered action output into drawn lines.
   *
   * An output handler cannot echo, so write() collects what is drawn while
   * the handler runs, and the handler returns it in place of the chunk.
   *
   * @param string $buffer
   *   The bytes written since the last call.
   *
   * @return string
   *   The drawn output.
   */
  protected function captureStream(string $buffer): string {
    $this->streamBuffer = '';
    $this->feedStream($buffer);
    $drawn = $this->streamBuffer ?? '';
    $this->streamBuffer = NULL;

    return $drawn;
  }

  /**
   * Splits streamed output into lines and draws each complete one.
   *
   * @param string $chunk
   *   The bytes the action wrote.
   */
  protected function feedStream(string $chunk): void {
    if (!$this->streamDraw instanceof \Closure || $this->streamed === NULL || $chunk === '') {
      return;
    }

    $this->isStreamDrawing = TRUE;

    $lines = explode("\n", $this->streamPartial . $chunk);
    $this->streamPartial = array_pop($lines);

    foreach ($lines as $line) {
      $line = $this->lastFrame($line);
      $this->streamed[] = $line;

      if (!$this->isStreamLive) {
        ($this->streamDraw)($line);
      }
    }

    // A live window also shows the line being written, such as a progress
    // meter that has not printed its newline yet.
    if ($this->isStreamLive) {
      ($this->streamDraw)();
    }

    $this->isStreamDrawing = FALSE;
  }

  /**
   * Closes the stream's output buffer and draws the last unfinished line.
   *
   * @param int $level
   *   The output buffering level before the stream's buffer was started.
   */
  protected function endStream(int $level): void {
    // Buffers the action left open are flushed into the stream's buffer, and
    // a stream buffer the action already closed is not closed again.
    while (ob_get_level() > $level + 1) {
      ob_end_flush();
    }

    if (ob_get_level() === $level + 1) {
      ob_end_flush();
    }

    if ($this->streamPartial !== '') {
      $this->feedStream("\n");
    }

    $this->streamDraw = NULL;
  }

  /**
   * Returns what a terminal shows of a line redrawn with carriage returns.
   *
   * @param string $line
   *   A line of output, without its newline.
   *
   * @return string
   *   The text after the last carriage return, ignoring a trailing one.
   */
  protected function lastFrame(string $line): string {
    $line = rtrim($line, "\r");
    $cut = strrpos($line, "\r");

    return $cut === FALSE ? $line : substr($line, $cut + 1);
  }

  /**
   * Starts a child process that redraws the spinner frames.
   *
   * @param \Closure $frame
   *   Receives the frame index and returns the rows of that frame.
   * @param int $rows
   *   The number of terminal rows each frame occupies.
   *
   * @return int|null
   *   The child's process ID, or NULL when the spinner cannot animate.
   */
  protected function animate(\Closure $frame, int $rows): ?int {
    // A buffered echo reaches the terminal after the child's frames, and a
    // callable target cannot be written from another process.
    if (!function_exists('pcntl_fork') || !function_exists('posix_kill') || $this->cfgOutput instanceof \Closure || ($this->cfgOutput === NULL && ob_get_level() > 0)) {
      return NULL;
    }

    // @codeCoverageIgnoreStart
    $parent = getmypid();
    $pid = pcntl_fork();

    if ($pid !== 0) {
      return $pid > 0 ? $pid : NULL;
    }

    $target = $this->cfgOutput ?? STDOUT;
    $index = 0;

    // The loop ends once the parent exits without stopping the child, such as
    // on a fatal error.
    while (posix_getppid() === $parent) {
      usleep(static::SPINNER_INTERVAL);
      // 1 write per frame, so stopping the child leaves no partial frame.
      fwrite($target, "\033[{$rows}A\r" . implode(PHP_EOL, $frame(++$index)) . PHP_EOL);
    }

    // SIGKILL skips shutdown functions and buffer flushes, which belong to the
    // parent.
    posix_kill((int) getmypid(), SIGKILL);

    return NULL;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Stops a spinner started by animate() and waits for it to exit.
   *
   * @param int|null $pid
   *   The child's process ID, or NULL when no spinner runs.
   */
  protected function stopAnimation(?int $pid): void {
    if ($pid === NULL) {
      return;
    }

    // @codeCoverageIgnoreStart
    // The child inherits any SIGTERM handler the script installed, which
    // could keep it running.
    posix_kill($pid, SIGKILL);
    pcntl_waitpid($pid, $status);
    // @codeCoverageIgnoreEnd
  }

  /**
   * Writes a chunk of rendered output to the configured target.
   *
   * @param string $chunk
   *   The bytes to write, escape sequences included.
   */
  protected function write(string $chunk): void {
    if ($this->streamBuffer !== NULL && $this->cfgOutput === NULL) {
      $this->streamBuffer .= $chunk;

      return;
    }

    // Echoed output reaches the stream through its buffer, so only writes to
    // a stream or callable target are streamed here.
    if ($this->isCapturedByStream() && $this->cfgOutput !== NULL) {
      $this->feedStream($chunk);

      return;
    }

    if ($this->cfgOutput instanceof \Closure) {
      ($this->cfgOutput)($chunk);

      return;
    }

    if ($this->cfgOutput !== NULL) {
      fwrite($this->cfgOutput, $chunk);

      return;
    }

    echo $chunk;
  }

  /**
   * Prints an array of lines to the output target and returns the row count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   *
   * @return int
   *   The number of terminal rows the lines occupy. A line wider than the
   *   terminal occupies 1 row per terminal width.
   */
  protected function printLines(array $lines): int {
    // A replayed step is already on screen.
    if ($this->replay !== NULL) {
      return 0;
    }

    $this->write(implode(PHP_EOL, $lines) . PHP_EOL);

    $columns = $this->columns();
    $rows = 0;

    foreach ($lines as $line) {
      $rows += max(1, (int) ceil($this->displayWidth($line) / $columns));
    }

    $this->columns = NULL;

    if (!$this->isCapturedByStream()) {
      $this->rows += $rows;
    }

    return $rows;
  }

  /**
   * Checks whether output goes into a streaming task's capture.
   *
   * A streaming task draws its action's output, and counts the rows, itself.
   *
   * @return bool
   *   TRUE while a streaming task's action runs, outside its own drawing.
   */
  protected function isCapturedByStream(): bool {
    return $this->streamDraw instanceof \Closure && !$this->isStreamDrawing;
  }

  /**
   * Clears previously printed rows and redraws with new content.
   *
   * @param int $prev_line_count
   *   The number of terminal rows previously printed to clear.
   * @param array<int, string> $lines
   *   Lines to redraw.
   *
   * @return int
   *   The number of terminal rows printed.
   */
  protected function redraw(int $prev_line_count, array $lines): int {
    $this->clearRows($prev_line_count);

    return $this->printLines($lines);
  }

  /**
   * Erases previously printed rows and leaves the cursor where they began.
   *
   * @param int $rows
   *   The number of terminal rows to erase.
   */
  protected function clearRows(int $rows): void {
    if ($rows <= 0) {
      return;
    }

    $this->write("\033[{$rows}A\r\033[J");

    if (!$this->isCapturedByStream()) {
      $this->rows -= $rows;
    }
  }

  /**
   * Wraps text in an ANSI color sequence.
   */
  protected function color(string $text, string $color): string {
    return isset($this->cfgColors[$color]) ? $this->cfgColors[$color] . $text . $this->cfgColors['reset'] : $text;
  }

  /**
   * Draws text in a base style and keeps the SGR sequences it carries.
   *
   * The text between SGR sequences is made printable. A kept sequence that
   * switches an attribute off is followed by the base style, so the base
   * resumes where the caller closed a run. With ANSI off, the SGR sequences
   * are removed as well.
   *
   * @param string $text
   *   Text supplied by the caller.
   * @param string $base
   *   The color the text is drawn in.
   *
   * @return string
   *   The text in the base style, closed with a reset.
   */
  protected function styled(string $text, string $base): string {
    $parts = preg_split('/(\033\[[0-9;:]*m)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    $styled = '';

    foreach ($parts as $index => $part) {
      if ($index % 2 === 0) {
        $styled .= $this->printable($part);

        continue;
      }

      if (!$this->cfgUseAnsi) {
        continue;
      }

      $styled .= $part;

      if ($this->switchesOff($part)) {
        $styled .= $this->cfgColors[$base] ?? '';
      }
    }

    return $this->color($styled, $base);
  }

  /**
   * Checks whether an SGR sequence switches an attribute off.
   *
   * Parameter 0 (or none), 21-29, 39 and 49 switch an attribute off. The
   * arguments of an extended colour (38, 48 or 58 followed by ';5;n' or
   * ';2;r;g;b') are colour values, so they are skipped.
   *
   * @param string $sequence
   *   An SGR sequence, from the escape byte to the final 'm'.
   *
   * @return bool
   *   TRUE when any parameter of the sequence switches an attribute off.
   */
  protected function switchesOff(string $sequence): bool {
    $params = explode(';', substr($sequence, 2, -1));
    $count = count($params);

    for ($i = 0; $i < $count; $i++) {
      if (in_array($params[$i], ['38', '48', '58'], TRUE)) {
        $i += match ($params[$i + 1] ?? '') {
          '5' => 2,
          '2' => 4,
          default => 0,
        };

        continue;
      }

      if (preg_match('/^(?:0*|2[0-9]|39|49)$/', $params[$i]) === 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Neutralizes control characters that would break a rendered line.
   *
   * A newline or tab becomes a space, so text stays on the line the tree drew
   * for it. Escape sequences and the remaining control characters are removed,
   * since a terminal acts on them rather than showing them.
   *
   * @param string $text
   *   Text supplied by the caller or discovered from the environment.
   *
   * @return string
   *   Text safe to compose into a rendered line.
   */
  protected function printable(string $text): string {
    $text = preg_replace('/\033\[\??[0-9;]*[A-Za-z]/', '', $text) ?? $text;
    $text = preg_replace('/[\t\n\x0b\x0c\r]+/', ' ', $text) ?? $text;
    $text = preg_replace('/[\x00-\x1f\x7f]/', '', $text) ?? $text;

    // Match the remaining C1 controls by character, since their second UTF-8
    // byte also occurs inside characters such as 'ą'. Text that is not valid
    // UTF-8 fails the /u match and keeps the result of the passes above.
    return preg_replace('/\p{Cc}/u', '', $text) ?? $text;
  }

  /**
   * Removes CSI escape sequences, such as colors and cursor moves.
   *
   * @param string $text
   *   Text that may hold escape sequences.
   *
   * @return string
   *   The text without its escape sequences.
   */
  protected function stripAnsi(string $text): string {
    return preg_replace('/\033\[\??[0-9;:]*[A-Za-z]/', '', $text) ?? $text;
  }

  /**
   * Returns the terminal columns that rendered lines wrap to.
   *
   * Takes the configured width, then a positive COLUMNS environment
   * variable, then the width 'stty size' reports, then DEFAULT_COLUMNS.
   * A detected width is kept until printLines() clears it.
   */
  protected function columns(): int {
    if ($this->cfgWidth > 0) {
      return $this->cfgWidth;
    }

    return $this->columns ??= $this->detectedColumns();
  }

  /**
   * Returns the detected terminal width, measuring it again once it expires.
   *
   * @return int
   *   The width measureColumns() returned within the last COLUMNS_REFRESH
   *   seconds.
   */
  protected function detectedColumns(): int {
    if ($this->detectedColumns === NULL || microtime(TRUE) - $this->columnsMeasuredAt >= static::COLUMNS_REFRESH) {
      $this->detectedColumns = $this->measureColumns();
      $this->columnsMeasuredAt = microtime(TRUE);
    }

    return $this->detectedColumns;
  }

  /**
   * Measures the terminal width from the environment or the terminal.
   *
   * @return int
   *   A positive COLUMNS environment variable, then the width 'stty size'
   *   reports, then DEFAULT_COLUMNS.
   */
  protected function measureColumns(): int {
    $env = trim((string) getenv('COLUMNS'));

    if (preg_match('/^[1-9]\d*$/', $env) === 1) {
      return (int) $env;
    }

    // 'stty size' reads the terminal on stdin, so it fails without one.
    if (PHP_OS_FAMILY === 'Windows' || !stream_isatty(STDIN)) {
      return static::DEFAULT_COLUMNS;
    }

    // @codeCoverageIgnoreStart
    // 'stty size' prints "<rows> <columns>". A pseudo-terminal that was never
    // sized reports 0 columns.
    $size = trim((string) shell_exec('stty size 2>' . static::NULL_DEVICE));

    return preg_match('/^\d+ ([1-9]\d*)$/', $size, $matches) === 1 ? (int) $matches[1] : static::DEFAULT_COLUMNS;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Measures the columns a terminal uses to draw text.
   *
   * @param string $text
   *   Text that may hold escape sequences.
   *
   * @return int
   *   The width in columns. Escape sequences count as 0, a wide character
   *   as 2. Text that is not valid UTF-8 counts 1 column per byte.
   */
  protected function displayWidth(string $text): int {
    $text = $this->stripAnsi($text);

    if (preg_match_all('/./su', $text, $matches) === FALSE) {
      return strlen($text);
    }

    $clusters = [];

    foreach ($matches[0] as $char) {
      $last = array_key_last($clusters);

      if ($last !== NULL && $this->joinsCluster($clusters[$last], $char)) {
        $clusters[$last] .= $char;

        continue;
      }

      $clusters[] = $char;
    }

    return array_sum(array_map($this->clusterWidth(...), $clusters));
  }

  /**
   * Checks whether a character continues the cluster before it.
   *
   * Grapheme clusters are formed here rather than with \X, because PCRE2
   * releases before 10.43 join adjacent pictographic characters such as '◼◻'.
   *
   * @param string $cluster
   *   The cluster so far.
   * @param string $char
   *   The next character.
   *
   * @return bool
   *   TRUE for a combining mark, a joiner, a variation selector or a skin
   *   tone, for any character after a zero-width joiner, and for the second
   *   regional indicator of a flag.
   */
  protected function joinsCluster(string $cluster, string $char): bool {
    if (str_ends_with($cluster, "\u{200D}") || preg_match('/^[\p{Mn}\p{Me}\x{200D}\x{FE00}-\x{FE0F}\x{1F3FB}-\x{1F3FF}]$/u', $char) === 1) {
      return TRUE;
    }

    return preg_match('/^[\x{1F1E6}-\x{1F1FF}]$/u', $cluster) === 1 && preg_match('/^[\x{1F1E6}-\x{1F1FF}]$/u', $char) === 1;
  }

  /**
   * Measures the columns a terminal uses to draw 1 grapheme cluster.
   *
   * @param string $cluster
   *   A grapheme cluster, or a single byte of text that is not valid UTF-8.
   *
   * @return int
   *   2 for a cluster holding a wide character, 0 for a cluster of combining
   *   marks and format characters alone, otherwise 1.
   */
  protected function clusterWidth(string $cluster): int {
    if (preg_match(static::WIDE_PATTERN, $cluster) === 1) {
      return 2;
    }

    return preg_match('/^[\p{Mn}\p{Me}\p{Cf}]+$/u', $cluster) === 1 ? 0 : 1;
  }

  /**
   * Wraps styled content to the terminal width after a gutter.
   *
   * Content breaks at spaces, and the spaces at a break are dropped. A word
   * wider than a row breaks at the row's width. A style open at a break is
   * reset at the end of the row and reopened after the continuation gutter,
   * so the gutter keeps its own style.
   *
   * @param string $gutter
   *   The tree prefix drawn before the first row.
   * @param string $content
   *   The text to wrap, with any color sequences.
   * @param string $continuation
   *   The tree prefix drawn before every further row.
   *
   * @return list<string>
   *   The rows. Content that fits, or a gutter that leaves no room, gives 1
   *   row holding the gutter and the content unchanged.
   */
  protected function wrap(string $gutter, string $content, string $continuation): array {
    $columns = $this->columns();

    return $this->wrapWithin($gutter, $content, $continuation, $columns - $this->displayWidth($gutter), $columns - $this->displayWidth($continuation));
  }

  /**
   * Wraps styled content to given row widths after a gutter.
   *
   * Breaks and styles follow wrap().
   *
   * @param string $gutter
   *   The prefix drawn before the first row.
   * @param string $content
   *   The text to wrap, with any color sequences.
   * @param string $continuation
   *   The prefix drawn before every further row.
   * @param int $first
   *   Columns available for content on the first row.
   * @param int $rest
   *   Columns available for content on every further row.
   *
   * @return list<string>
   *   The rows. Content that fits, or a width below 1, gives 1 row holding
   *   the gutter and the content unchanged.
   */
  protected function wrapWithin(string $gutter, string $content, string $continuation, int $first, int $rest): array {
    if ($first < 1 || $rest < 1 || $this->displayWidth($content) <= $first) {
      return [$gutter . $content];
    }

    $runs = $this->wrapRuns($content);
    $row_of = $this->wrapRows($runs, $first, $rest);

    $rows = [];
    $row = $gutter;
    $current = 0;
    $active = '';
    $index = 0;

    foreach ($runs as $run) {
      foreach ($run['units'] as $unit) {
        $target = $row_of[$index++];

        if ($target === NULL) {
          continue;
        }

        while ($target > $current) {
          $rows[] = $active !== '' ? $row . $this->cfgColors['reset'] : $row;
          $row = $continuation . $active;
          $current++;
        }

        $row .= $unit['text'];

        if ($unit['kind'] === 'style') {
          $active = $unit['text'] === $this->cfgColors['reset'] || preg_match('/^\033\[0*m$/', $unit['text']) === 1 ? '' : $active . $unit['text'];
        }
      }
    }

    $rows[] = $row;

    return $rows;
  }

  /**
   * Splits styled content into alternating runs of spaces and words.
   *
   * A color sequence belongs to the run it sits in and has no width.
   *
   * @param string $content
   *   The text to split, with any color sequences.
   *
   * @return list<array{space: bool, width: int, units: list<array{text: string, kind: string, width: int}>}>
   *   The runs in order. A unit is a color sequence ('style'), a space
   *   ('space') or a grapheme cluster ('char').
   */
  protected function wrapRuns(string $content): array {
    // Text that is not valid UTF-8 fails the /u match and splits by byte.
    if (preg_match_all('/\033\[[0-9;:]*m| |./su', $content, $matches) === FALSE) {
      preg_match_all('/\033\[[0-9;:]*m| |./s', $content, $matches);
    }

    $units = [];

    foreach ($matches[0] as $text) {
      $kind = match (TRUE) {
        preg_match('/^\033\[[0-9;:]*m$/', $text) === 1 => 'style',
        $text === ' ' => 'space',
        default => 'char',
      };
      $previous = end($units);

      if ($kind === 'char' && $previous !== FALSE && $previous['kind'] === 'char' && $this->joinsCluster($previous['text'], $text)) {
        $text = $previous['text'] . $text;
        array_pop($units);
      }

      $width = $kind === 'char' ? $this->clusterWidth($text) : (int) ($kind === 'space');
      $units[] = ['text' => $text, 'kind' => $kind, 'width' => $width];
    }

    $runs = [];

    foreach ($units as $unit) {
      $last = array_key_last($runs);

      if ($last === NULL || ($unit['kind'] !== 'style' && $runs[$last]['space'] !== ($unit['kind'] === 'space'))) {
        $runs[] = ['space' => $unit['kind'] === 'space', 'width' => 0, 'units' => []];
        $last = array_key_last($runs);
      }

      $runs[$last]['units'][] = $unit;
      $runs[$last]['width'] += $unit['width'];
    }

    return $runs;
  }

  /**
   * Assigns each unit of the wrapped runs to a row.
   *
   * @param list<array{space: bool, width: int, units: list<array{text: string, kind: string, width: int}>}> $runs
   *   Runs from wrapRuns().
   * @param int $first
   *   Columns available on the first row.
   * @param int $rest
   *   Columns available on every further row.
   *
   * @return list<int|null>
   *   The row of each unit in order, or NULL for a space dropped at a break.
   */
  protected function wrapRows(array $runs, int $first, int $rest): array {
    $row_of = [];
    $row = 0;
    $used = 0;
    $available = $first;
    $is_break_due = FALSE;

    foreach ($runs as $index => $run) {
      // Spaces before the first word are content, so they wrap like a word.
      if ($run['space'] && ($row > 0 || $used > 0)) {
        $is_kept = $used + $run['width'] + ($runs[$index + 1]['width'] ?? 0) <= $available;

        foreach ($run['units'] as $unit) {
          $row_of[] = $is_kept || $unit['kind'] === 'style' ? $row : NULL;
        }

        $used += $is_kept ? $run['width'] : 0;
        $is_break_due = !$is_kept;

        continue;
      }

      if ($is_break_due || $used + $run['width'] > $available) {
        if ($used > 0) {
          $row++;
          $used = 0;
          $available = $rest;
        }

        $is_break_due = FALSE;
      }

      foreach ($run['units'] as $unit) {
        if ($used > 0 && $used + $unit['width'] > $available) {
          $row++;
          $used = 0;
          $available = $rest;
        }

        $row_of[] = $row;
        $used += $unit['width'];
      }
    }

    return $row_of;
  }

  /**
   * Cuts content to the row it starts on, ending a cut row with an ellipsis.
   *
   * @param string $gutter
   *   The prefix drawn before the content.
   * @param string $content
   *   The text to fit, with any color sequences.
   *
   * @return string
   *   The row, no wider than the terminal.
   */
  protected function fitRow(string $gutter, string $content): string {
    $rows = $this->wrap($gutter, $content, $gutter);

    if (count($rows) === 1) {
      return $rows[0];
    }

    $ellipsis = $this->color($this->cfgSymbols['ellipsis'], 'dim');
    $width = $this->cfgWidth;

    // The content is wrapped again, narrower by the ellipsis, so the cut row
    // and the ellipsis fit together.
    $this->cfgWidth = max(1, $this->columns() - $this->displayWidth($ellipsis));
    $row = $this->wrap($gutter, $content, $gutter)[0];
    $this->cfgWidth = $width;

    return $row . $ellipsis;
  }

  /**
   * Returns blank padding as wide as the given text.
   *
   * @param string $text
   *   Text that may hold escape sequences.
   *
   * @return string
   *   Spaces filling the columns $text draws.
   */
  protected function pad(string $text): string {
    return str_repeat(' ', $this->displayWidth($text));
  }

  /**
   * Returns a configured label as printable text.
   *
   * @param string $name
   *   The label to read.
   *
   * @return string
   *   The label, safe to compose into a rendered line.
   */
  protected function label(string $name): string {
    return $this->printable($this->cfgLabels[$name] ?? '');
  }

  /**
   * Returns the styled vertical bar character for the tree connector.
   */
  protected function bar(): string {
    return $this->color($this->cfgSymbols['bar'], 'gray');
  }

  /**
   * Returns the pointer gutter shown before an option's indicator.
   *
   * Marks the focused row independently of the selection state, so it stays
   * distinguishable even when it is already checked.
   *
   * @param bool $is_focused
   *   Whether this option is the focused one.
   *
   * @return string
   *   The styled pointer when focused, or equal-width padding when not,
   *   followed by a separating space.
   */
  protected function pointer(bool $is_focused): string {
    return ($is_focused ? $this->color($this->cfgSymbols['pointer'], 'cyan') : ' ') . ' ';
  }

  /**
   * Returns the indentation prefix for a widget label at the given depth.
   *
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return string
   *   The label prefix string.
   */
  protected function labelPrefix(int $depth, array $open): string {
    $result = '  ';

    for ($level = 1; $level < $depth; $level++) {
      $result .= ($open[$level] ?? FALSE) ? $this->color($this->cfgSymbols['bar'], 'gray') . '  ' : '   ';
    }

    return $result;
  }

  /**
   * Returns the indentation prefix for widget body lines at the given depth.
   *
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return string
   *   The body prefix string.
   */
  protected function bodyPrefix(int $depth, array $open): string {
    $result = '  ';

    for ($level = 1; $level <= $depth; $level++) {
      $result .= ($open[$level] ?? FALSE) ? $this->color($this->cfgSymbols['bar'], 'gray') . '  ' : '   ';
    }

    return $result;
  }

  /**
   * Appends a step number suffix to a label when numbering is enabled.
   *
   * @param string $label
   *   The original label text.
   * @param array<string, mixed> $ctx
   *   Flow context.
   *
   * @return string
   *   The label with an optional step number suffix.
   */
  protected function numberLabel(string $label, array $ctx): string {
    $label = $this->printable($label);

    if (isset($ctx['number'])) {
      /** @var string $number */
      $number = $ctx['number'];
      $suffix = is_int($ctx['total'] ?? NULL) ? sprintf($this->label('step_total'), (int) $number, $ctx['total']) : '(' . $number . ')';

      return $label . ' ' . $this->color($suffix, 'dim');
    }

    return $label;
  }

  /**
   * Renders the intro banner lines.
   *
   * @param string $message
   *   The intro message to display.
   *
   * @return array<int, string>
   *   The rendered intro lines.
   */
  protected function renderIntro(string $message): array {
    $message = $this->printable($message);
    $gutter = $this->color($this->cfgSymbols['intro'], 'gray') . $this->cfgSpacing['indent'];

    return [
      '',
      ...$this->wrap($gutter, $this->color($message, 'bold'), $this->bar() . $this->cfgSpacing['indent']),
      $this->bar(),
    ];
  }

  /**
   * Renders the outro banner lines.
   *
   * @param string $message
   *   The outro message to display.
   *
   * @return array<int, string>
   *   The rendered outro lines.
   */
  protected function renderOutro(string $message): array {
    $message = $this->printable($message);
    $gutter = $this->color($this->cfgSymbols['outro'], 'gray') . $this->cfgSpacing['indent'];

    // The outro ends the tree, so its continuation rows draw no bar.
    return [
      $this->bar(),
      ...$this->wrap($gutter, $this->color($message, 'green'), $this->pad($gutter)),
      '',
    ];
  }

  /**
   * Renders the heading lines of a flow section.
   *
   * @param string $title
   *   The section title. A newline starts another title line.
   * @param string $description
   *   The section description, or '' for none. A newline starts another
   *   description line.
   * @param bool $is_first
   *   Whether nothing has been drawn above the heading in the flow. The
   *   heading then opens the tree as an intro does.
   *
   * @return array<int, string>
   *   The rendered heading lines.
   */
  protected function renderSection(string $title, string $description, bool $is_first): array {
    $lines = $is_first ? [''] : [];
    $symbol = $this->color($this->cfgSymbols[$is_first ? 'intro' : 'section'], 'gray');

    $gutter = $this->bar() . $this->cfgSpacing['indent'];

    foreach (explode("\n", $title) as $index => $text_line) {
      $lines = array_merge($lines, $this->wrap(($index === 0 ? $symbol : $this->bar()) . $this->cfgSpacing['indent'], $this->color($this->printable($text_line), 'bold'), $gutter));
    }

    if ($description !== '') {
      foreach (explode("\n", $description) as $text_line) {
        $lines = array_merge($lines, $this->wrap($gutter, $this->color($this->printable($text_line), 'dim'), $gutter));
      }
    }

    $lines[] = $this->bar();

    return $lines;
  }

  /**
   * Renders a note: its type's symbol, then the message in its type's colour.
   *
   * @param string $message
   *   The message. A newline starts another line.
   * @param string $type
   *   The note type, 1 of NOTE_TYPES.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered lines, closed with a bar line.
   */
  protected function renderNote(string $message, string $type, int $depth = 0, array $open = []): array {
    $key = 'note_' . $type;
    $body = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $text_lines = explode("\n", $message);

    $lines = $this->renderMarkedLabel($this->styled(array_shift($text_lines), $key), $this->color($this->cfgSymbols[$key], $key), $depth, $open);

    foreach ($text_lines as $text_line) {
      $lines = array_merge($lines, $this->wrap($body, $this->styled($text_line, $key), $body));
    }

    $lines[] = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');

    return $lines;
  }

  /**
   * Renders a widget's label after its state symbol.
   *
   * @param string $label
   *   The widget label.
   * @param string $symbol
   *   The key of the symbol drawn before the label.
   * @param string $color
   *   The color of the symbol.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return list<string>
   *   The label rows. Continuation rows align with the widget's body lines.
   */
  protected function renderLabel(string $label, string $symbol, string $color, int $depth = 0, array $open = []): array {
    return $this->renderMarkedLabel($label, $this->color($this->cfgSymbols[$symbol], $color), $depth, $open);
  }

  /**
   * Renders a widget's label after a marker.
   *
   * @param string $label
   *   The widget label.
   * @param string $marker
   *   The styled text drawn before the label.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return list<string>
   *   The label rows. Continuation rows align with the widget's body lines.
   */
  protected function renderMarkedLabel(string $label, string $marker, int $depth = 0, array $open = []): array {
    $gutter = ($depth > 0 ? $this->bar() . $this->labelPrefix($depth, $open) : '') . $marker . $this->cfgSpacing['indent'];

    return $this->wrap($gutter, $label, $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']));
  }

  /**
   * Renders description text lines with appropriate depth indentation.
   *
   * @param string $description
   *   The description text to render.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered description lines.
   */
  protected function renderDescription(string $description, int $depth = 0, array $open = []): array {
    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $lines = [];

    foreach (explode("\n", $description) as $text_line) {
      $lines = array_merge($lines, $this->wrap($gutter, $this->styled($text_line, 'dim_italic'), $gutter));
    }

    $lines[] = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');

    return $lines;
  }

  /**
   * Renders hint text lines with arrow prefix and depth indentation.
   *
   * @param string $hint
   *   The hint text to render.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered hint lines.
   */
  protected function renderHint(string $hint, int $depth = 0, array $open = []): array {
    $body_prefix = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');
    $continuation = $body_prefix . $this->cfgSpacing['hint_cont'];
    $gutter = $body_prefix . $this->cfgSpacing['hint_indent'] . $this->color($this->cfgSymbols['hint_arrow'], 'dim') . ' ';
    $lines = [];

    foreach (explode("\n", $hint) as $text_line) {
      $lines = array_merge($lines, $this->wrap($gutter, $this->color($this->printable($text_line), 'dim_italic'), $continuation));
      $gutter = $continuation;
    }

    return $lines;
  }

  /**
   * Renders help text lines below a widget's input with depth indentation.
   *
   * A blank bar line comes first, so the help stays apart from the input
   * above it, which can also be dim.
   *
   * @param string $help
   *   The help text to render.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered help lines, or no lines when the help is empty.
   */
  protected function renderHelp(string $help, int $depth = 0, array $open = []): array {
    if ($help === '') {
      return [];
    }

    $body_prefix = $depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent'];

    $lines = [$this->bar() . ($depth > 0 ? $body_prefix : '')];

    foreach (explode("\n", $help) as $text_line) {
      $lines = array_merge($lines, $this->wrap($this->bar() . $body_prefix, $this->color($this->printable($text_line), 'dim'), $this->bar() . $body_prefix));
    }

    return $lines;
  }

  /**
   * Renders the keys a widget accepts, drawn like help.
   *
   * A segment whose label is blank is left out, so an empty label removes
   * that key from the legend. The back key is listed last, and only in a flow
   * step it can step back from.
   *
   * @param string $widget
   *   The widget type: text, select, multiselect, suggest, confirm, pause, or
   *   pause_enter for a pause that continues only on Enter.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered legend lines, or no lines when the legend is off.
   */
  protected function renderLegend(string $widget, int $depth = 0, array $open = []): array {
    if (!$this->cfgShowLegend) {
      return [];
    }

    $move = $this->label('legend_move') === '' ? '' : $this->cfgSymbols['legend_vertical'] . ' ' . $this->label('legend_move');
    $toggle = $this->label('legend_toggle') === '' ? '' : $this->cfgSymbols['legend_horizontal'] . ' ' . $this->label('legend_toggle');
    $enter = $this->label('legend_enter');

    $segments = match ($widget) {
      'select' => [$move, $enter],
      'multiselect' => [$move, $this->label('legend_space'), $enter],
      'suggest' => [$this->label('legend_type'), $move, $enter],
      'confirm' => [$toggle, $this->label('legend_yes_no'), $enter],
      'pause' => [$this->label('legend_any_key')],
      'pause_enter' => [$this->label('legend_continue')],
      default => [$enter],
    };

    if ($this->cfgBackKey !== '' && $this->label('legend_back') !== '' && $this->pending !== NULL && $this->backTarget() !== NULL) {
      $segments[] = str_replace('-', '+', $this->cfgBackKey) . ' ' . $this->label('legend_back');
    }

    $segments = array_filter($segments, fn(string $segment): bool => trim($segment) !== '');

    return $this->renderHelp(implode(' ' . $this->cfgSymbols['legend_separator'] . ' ', $segments), $depth, $open);
  }

  /**
   * Renders a validation error line with depth indentation.
   *
   * @param string|null $error
   *   The error message, or NULL when the answer has not been rejected.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered error line, or no lines when $error is NULL.
   */
  protected function renderError(?string $error, int $depth = 0, array $open = []): array {
    if ($error === NULL) {
      return [];
    }

    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $marker = $this->cfgSymbols['error'] . ' ';

    return $this->wrap($gutter, $this->color($marker . $this->printable($error), 'red'), $gutter . $this->pad($marker));
  }

  /**
   * Frames a window of option rows with the count hidden on each side.
   *
   * Both indicator rows are drawn while the list is windowed, blank on a side
   * with nothing hidden, so the widget keeps its height as the window moves.
   *
   * @param array<int, string> $rows
   *   The rendered rows of the options in the window, hints included.
   * @param int $first
   *   Index of the first option in the window.
   * @param int $visible
   *   The number of options in the window.
   * @param int $count
   *   The number of declared options.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rows between the indicators, or the rows alone when every option is
   *   in the window.
   */
  protected function renderScrollWindow(array $rows, int $first, int $visible, int $count, int $depth = 0, array $open = []): array {
    if ($visible >= $count) {
      return $rows;
    }

    $prefix = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');
    $indicator = fn(string $symbol, int $hidden): string => $hidden > 0
      ? $prefix . $this->pointer(FALSE) . $this->color($this->cfgSymbols[$symbol] . ' ' . $hidden . ' ' . $this->label('more'), 'dim')
      : $prefix;

    return [
      $indicator('scroll_up', $first),
      ...$rows,
      $indicator('scroll_down', $count - $first - $visible),
    ];
  }

  /**
   * Renders the completed state lines for a widget.
   *
   * @param string $label
   *   The widget label.
   * @param string $value
   *   The submitted value to display.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered completed-state lines.
   */
  protected function renderCompleted(string $label, string $value, int $depth = 0, array $open = []): array {
    $label = $this->printable($label);
    $value = $this->printable($value);

    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);

    return [
      ...$this->renderLabel($label, 'completed', 'cyan', $depth, $open),
      ...$this->wrap($gutter, $this->color($value, 'dim'), $gutter),
      $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : ''),
    ];
  }

  /**
   * Renders the cancelled state lines for a widget.
   *
   * @param string $label
   *   The widget label.
   * @param string $value
   *   The current value at the time of cancellation.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered cancelled-state lines.
   */
  protected function renderCancelled(string $label, string $value, int $depth = 0, array $open = []): array {
    $label = $this->printable($label);
    $value = $this->printable($value);

    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $cancelled = $this->color($this->label('cancelled'), 'red');

    return [
      ...$this->renderLabel($label, 'active', 'red', $depth, $open),
      ...$this->wrap($gutter, $value === '' ? $cancelled : $this->color($value, 'dim') . ' ' . $cancelled, $gutter),
      $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : ''),
    ];
  }

  /**
   * Renders a task's label once its action has returned.
   *
   * @param string $label
   *   The task label.
   * @param bool $has_succeeded
   *   Whether the action succeeded.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return list<string>
   *   The label rows.
   */
  protected function renderTaskLabel(string $label, bool $has_succeeded, int $depth = 0, array $open = []): array {
    return $has_succeeded ? $this->renderLabel($label, 'completed', 'cyan', $depth, $open) : $this->renderLabel($label, 'active', 'red', $depth, $open);
  }

  /**
   * Renders the lines a task draws under its label once the action returns.
   *
   * @param bool $has_succeeded
   *   Whether the action succeeded.
   * @param string $hint
   *   The hint, or '' for none. A newline starts another hint line.
   * @param list<string> $details
   *   Detail lines the action returned.
   * @param string $message
   *   The result message.
   * @param string $note
   *   Text drawn dim after the message, or '' for none.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered lines, closed with a bar line.
   */
  protected function renderTaskResult(bool $has_succeeded, string $hint, array $details, string $message, string $note = '', int $depth = 0, array $open = []): array {
    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $lines = [];

    if ($hint !== '') {
      foreach (explode("\n", $hint) as $text_line) {
        $lines = array_merge($lines, $this->wrap($gutter, $this->styled($text_line, 'dim'), $gutter));
      }
    }

    foreach ($details as $detail) {
      $lines = array_merge($lines, $this->renderTaskLine($detail, $depth, $open));
    }

    $marker = $this->cfgSymbols[$has_succeeded ? 'success' : 'error'] . ' ';
    $result = $this->color($marker . $this->printable($message), $has_succeeded ? 'green' : 'red') . ($note !== '' ? $this->color($note, 'dim') : '');
    $lines = array_merge($lines, $this->wrap($gutter, $result, $gutter . $this->pad($marker)));
    $lines[] = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');

    return $lines;
  }

  /**
   * Renders lines of task output.
   *
   * @param list<string> $lines
   *   The lines.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered rows.
   */
  protected function renderTaskLines(array $lines, int $depth = 0, array $open = []): array {
    $rows = [];

    foreach ($lines as $line) {
      $rows = array_merge($rows, $this->renderTaskLine($line, $depth, $open));
    }

    return $rows;
  }

  /**
   * Renders the last streamed lines under a count of the earlier ones.
   *
   * The line still being written is drawn last. Each line is cut to 1 row,
   * so the window keeps its height as lines arrive.
   *
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered rows: the count while lines are hidden, then the window.
   */
  protected function renderTaskWindow(int $depth = 0, array $open = []): array {
    $lines = $this->streamed ?? [];

    if ($this->streamPartial !== '') {
      $lines[] = $this->lastFrame($this->streamPartial);
    }

    $hidden = max(0, count($lines) - static::STREAM_WINDOW);
    $body = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']);
    $rows = $hidden > 0 ? [$body . $this->color($this->cfgSymbols['scroll_up'] . ' ' . $hidden . ' ' . $this->label('more'), 'dim')] : [];

    foreach (array_slice($lines, $hidden) as $line) {
      $rows[] = $this->fitRow($body . $this->cfgSpacing['indent'], $this->styled($line, 'dim'));
    }

    return $rows;
  }

  /**
   * Renders 1 line of task output, dim and indented below the task's body.
   *
   * @param string $line
   *   The line. SGR sequences in it are kept.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered rows. A wrapped row keeps the indent.
   */
  protected function renderTaskLine(string $line, int $depth = 0, array $open = []): array {
    $gutter = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent']) . $this->cfgSpacing['indent'];

    return $this->wrap($gutter, $this->styled($line, 'dim'), $gutter);
  }

  /**
   * Renders the lines of a table.
   *
   * @param array<mixed> $rows
   *   The rows, as passed to table().
   * @param array<mixed> $headers
   *   The header cells, as passed to table().
   *
   * @return list<string>
   *   The rendered table lines, or [] when there are no rows and no headers.
   *
   * @throws \InvalidArgumentException
   *   When a row or a cell is not valid.
   */
  protected function renderTable(array $rows, array $headers): array {
    if ($rows === [] && $headers === []) {
      return [];
    }

    $items = [];

    if ($headers !== []) {
      $items[] = ['cells' => $this->tableCells($headers, 'headers'), 'text' => '', 'base' => 'bold'];
    }

    foreach (array_values($rows) as $index => $row) {
      $items[] = is_string($row)
        ? ['cells' => NULL, 'text' => $row, 'base' => 'bold']
        : ['cells' => $this->tableCells($row, sprintf('row %d', $index)), 'text' => '', 'base' => ''];
    }

    $widths = $this->tableWidths($items);
    $span = [array_sum($widths) + 3 * (count($widths) - 1)];

    $lines = [];
    $previous = NULL;

    foreach ($items as $index => $item) {
      $is_cells = $item['cells'] !== NULL;

      // Cell rows follow each other without a rule. A header row, a section
      // row and the table edges are ruled off.
      if ($previous === NULL) {
        $lines[] = $this->boxRule($widths, FALSE, $is_cells, 'top');
      }
      elseif (!$is_cells || $previous['cells'] === NULL || ($index === 1 && $headers !== [])) {
        $lines[] = $this->boxRule($widths, $previous['cells'] !== NULL, $is_cells, 'middle');
      }

      $lines = $is_cells
        ? array_merge($lines, $this->boxRows($item['cells'], $widths, $item['base']))
        : array_merge($lines, $this->boxRows([$item['text']], $span, $item['base']));

      $previous = $item;
    }

    $lines[] = $this->boxRule($widths, $previous !== NULL && $previous['cells'] !== NULL, FALSE, 'bottom');
    $lines[] = $this->bar();

    return $lines;
  }

  /**
   * Validates a table row and returns its cells as strings.
   *
   * @param mixed $row
   *   The row or the headers passed to table().
   * @param string $name
   *   The name of the row in an error message.
   *
   * @return list<string>
   *   The cells in order.
   *
   * @throws \InvalidArgumentException
   *   When the row is not an array or a cell is not a string, an integer or
   *   a float.
   */
  protected function tableCells(mixed $row, string $name): array {
    if (!is_array($row)) {
      throw new \InvalidArgumentException(sprintf('Table %s of type "%s" is not valid. Provide a list of cells, or a string for a section row.', $name, get_debug_type($row)));
    }

    $cells = [];

    foreach ($row as $cell) {
      if (!is_string($cell) && !is_int($cell) && !is_float($cell)) {
        throw new \InvalidArgumentException(sprintf('Table %s holds a cell of type "%s". A cell must be a string, an integer or a float.', $name, get_debug_type($cell)));
      }

      $cells[] = (string) $cell;
    }

    return $cells;
  }

  /**
   * Calculates the content width of each table column.
   *
   * A section row wider than the columns widens the last column. While the
   * table is wider than the terminal, the widest column loses 1 column, the
   * rightmost of equally wide ones first. No column shrinks below 1.
   *
   * @param list<array{cells: list<string>|null, text: string, base: string}> $items
   *   The cell rows and section rows of the table.
   *
   * @return list<int>
   *   The content width of each column.
   */
  protected function tableWidths(array $items): array {
    $count = max(1, ...array_map(fn(array $item): int => count($item['cells'] ?? []), $items));
    $widths = array_fill(0, $count, 0);
    $section = 0;

    foreach ($items as $item) {
      if ($item['cells'] === NULL) {
        $section = max($section, $this->blockWidth($item['text'], $item['base']));

        continue;
      }

      foreach ($item['cells'] as $column => $cell) {
        $widths[$column] = max($widths[$column], $this->blockWidth($cell, $item['base']));
      }
    }

    $widths[$count - 1] += max(0, $section - (array_sum($widths) + 3 * ($count - 1)));

    // Each column adds a vertical border and 2 spaces of padding, and the
    // table adds 1 closing border.
    $available = $this->columns() - $this->displayWidth($this->boxGutter()) - 3 * $count - 1;

    while (array_sum($widths) > $available && max($widths) > 1) {
      $widest = array_keys($widths, max($widths), TRUE);
      $widths[(int) end($widest)]--;
    }

    return array_values($widths);
  }

  /**
   * Renders the lines of a box.
   *
   * @param string $content
   *   The text in the box.
   * @param string $title
   *   The title, or '' for none.
   *
   * @return list<string>
   *   The rendered box lines.
   */
  protected function renderBox(string $content, string $title): array {
    $available = $this->columns() - $this->displayWidth($this->boxGutter()) - 4;
    $width = max(1, min($available, max($this->blockWidth($content, ''), $this->blockWidth($title, 'bold'))));

    $lines = [$this->boxRule([$width], FALSE, FALSE, 'top')];

    if ($title !== '') {
      $title_lines = $this->blockLines($title, 'bold', $width);
      $underline = max(0, ...array_map($this->displayWidth(...), $title_lines));

      $lines = array_merge($lines, $this->boxRows([$title], [$width], 'bold'));
      $lines[] = $this->boxFrame([$this->color(str_repeat($this->cfgSymbols['box_horizontal'], $underline), 'gray')], [$width]);
    }

    return [
      ...$lines,
      ...$this->boxRows([$content], [$width], ''),
      $this->boxRule([$width], FALSE, FALSE, 'bottom'),
      $this->bar(),
    ];
  }

  /**
   * Returns the tree gutter drawn before every line of a table or a box.
   */
  protected function boxGutter(): string {
    return $this->bar() . $this->cfgSpacing['indent'];
  }

  /**
   * Renders a horizontal rule of a table or a box.
   *
   * @param list<int> $widths
   *   The content width of each column.
   * @param bool $has_columns_above
   *   Whether the row above the rule is divided into columns.
   * @param bool $has_columns_below
   *   Whether the row below the rule is divided into columns.
   * @param string $edge
   *   Where the rule sits in the frame: 'top', 'bottom' or 'middle'.
   *
   * @return string
   *   The rule, after the gutter.
   */
  protected function boxRule(array $widths, bool $has_columns_above, bool $has_columns_below, string $edge): string {
    $join = match (TRUE) {
      $has_columns_above && $has_columns_below => 'box_cross',
      $has_columns_above => 'box_bottom_tee',
      $has_columns_below => 'box_top_tee',
      default => 'box_horizontal',
    };

    [$left, $right] = match ($edge) {
      'top' => ['box_top_left', 'box_top_right'],
      'bottom' => ['box_bottom_left', 'box_bottom_right'],
      default => ['box_left_tee', 'box_right_tee'],
    };

    $segments = array_map(fn(int $width): string => str_repeat($this->cfgSymbols['box_horizontal'], $width + 2), $widths);

    return $this->boxGutter() . $this->color($this->cfgSymbols[$left] . implode($this->cfgSymbols[$join], $segments) . $this->cfgSymbols[$right], 'gray');
  }

  /**
   * Renders 1 row of cells, wrapped to the column widths.
   *
   * @param list<string> $cells
   *   The cell texts. A missing cell is drawn empty.
   * @param list<int> $widths
   *   The content width of each column.
   * @param string $base
   *   The color the cell texts are drawn in.
   *
   * @return list<string>
   *   The rendered lines, as many as the tallest cell needs.
   */
  protected function boxRows(array $cells, array $widths, string $base): array {
    $columns = [];

    foreach ($widths as $column => $width) {
      $columns[] = $this->blockLines($cells[$column] ?? '', $base, $width);
    }

    $height = max(1, ...array_map(count(...), $columns));
    $lines = [];

    for ($line = 0; $line < $height; $line++) {
      $lines[] = $this->boxFrame(array_map(fn(array $column_lines): string => $column_lines[$line] ?? '', $columns), $widths);
    }

    return $lines;
  }

  /**
   * Draws 1 line of a table or a box between vertical borders.
   *
   * @param list<string> $contents
   *   The content of each column on this line.
   * @param list<int> $widths
   *   The content width of each column.
   *
   * @return string
   *   The line, after the gutter.
   */
  protected function boxFrame(array $contents, array $widths): string {
    $border = $this->color($this->cfgSymbols['box_vertical'], 'gray');
    $line = $this->boxGutter() . $border;

    foreach ($widths as $column => $width) {
      $content = $contents[$column] ?? '';
      $line .= ' ' . $content . str_repeat(' ', max(0, $width - $this->displayWidth($content))) . ' ' . $border;
    }

    return $line;
  }

  /**
   * Splits styled text into lines and wraps each to a width.
   *
   * The rows of a wrapped line keep its leading spaces when they leave room
   * for content. A style the text leaves open is reset at the end of every
   * row, so it does not reach the padding or the border.
   *
   * @param string $text
   *   The text. A newline starts another line.
   * @param string $base
   *   The color the text is drawn in.
   * @param int $width
   *   The columns available on each row.
   *
   * @return list<string>
   *   The rows.
   */
  protected function blockLines(string $text, string $base, int $width): array {
    $rows = [];

    foreach (explode("\n", $text) as $text_line) {
      $indent = str_repeat(' ', strspn($text_line, ' '));
      $indent = strlen($indent) < $width ? $indent : '';

      foreach ($this->wrapWithin('', $this->styled($text_line, $base), $indent, $width, $width - strlen($indent)) as $row) {
        preg_match_all('/\033\[[0-9;:]*m/', $row, $matches);
        $last = end($matches[0]);
        $rows[] = $last !== FALSE && preg_match('/^\033\[0*m$/', $last) !== 1 ? $row . $this->cfgColors['reset'] : $row;
      }
    }

    return $rows;
  }

  /**
   * Measures the widest line of styled text.
   *
   * @param string $text
   *   The text. A newline starts another line.
   * @param string $base
   *   The color the text is drawn in.
   *
   * @return int
   *   The width in columns of the widest line.
   */
  protected function blockWidth(string $text, string $base): int {
    return max(array_map(fn(string $text_line): int => $this->displayWidth($this->styled($text_line, $base)), explode("\n", $text)));
  }

  /**
   * Rejects steps whose key is reused or cannot be exported.
   *
   * A step is looked up as its key uppercased behind the prefix.
   *
   * A parent process can set a name that is not a shell identifier, but a
   * shell cannot export it and most .env files cannot hold it. Such a step
   * could never be filled through either.
   *
   * The results are flat, so a key reused anywhere in the tree would let a
   * later answer overwrite an earlier one.
   *
   * A section stores no answer, so it is checked for a key it would ignore
   * rather than for a reusable one.
   *
   * @param array<array-key, mixed> $steps
   *   Flow steps, keyed by step name.
   * @param array<string, true> $seen
   *   Keys already validated elsewhere in the tree.
   * @param bool $is_nested
   *   Whether $steps are the children of a step.
   *
   * @throws \InvalidArgumentException
   *   When a key is reused, the name a step resolves through is not a valid
   *   variable name, or a section has a key or is nested.
   */
  protected function assertStepKeys(array $steps, array &$seen = [], bool $is_nested = FALSE): void {
    foreach ($steps as $key => $step) {
      if ($this->isSection($step)) {
        /** @var array{__section: string} $step */
        if (is_string($key)) {
          throw new \InvalidArgumentException(sprintf('Section "%s" has the key "%s". A section adds no answer to the results, so it takes no key.', $step['__section'], $key));
        }

        if ($is_nested) {
          throw new \InvalidArgumentException(sprintf('Section "%s" is among the children of a step. A section heads top-level steps, so it can only appear in the steps array of the flow itself.', $step['__section']));
        }

        continue;
      }

      if ($this->isNote($step)) {
        /** @var array{__note: string} $step */
        if (is_string($key)) {
          throw new \InvalidArgumentException(sprintf('Note "%s" has the key "%s". A note adds no answer to the results, so it takes no key.', $step['__note'], $key));
        }

        continue;
      }

      if ((string) $key === '') {
        throw new \InvalidArgumentException('A step must have a key: it names the answer in the results and the variable the answer can come from.');
      }

      if (isset($seen[(string) $key])) {
        throw new \InvalidArgumentException(sprintf('Step "%s" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.', $key));
      }

      $seen[(string) $key] = TRUE;

      $name = $this->cfgEnvPrefix . strtoupper((string) $key);

      // The name is checked rather than the key, so a prefix that cannot be
      // exported is caught as well as a key that cannot.
      if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
        throw new \InvalidArgumentException(sprintf('Step "%s" is looked up as the environment variable "%s", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.', $key, $name));
      }

      $children = is_array($step) ? $step['__children'] ?? NULL : NULL;

      if (is_array($children)) {
        $this->assertStepKeys($children, $seen, TRUE);
      }
    }
  }

  /**
   * Extracts the condition callback a flow step was declared with.
   *
   * @param mixed $step
   *   A flow step: a closure, or a step array carrying '__condition'.
   *
   * @return callable|null
   *   The condition callback, or NULL when the step is unconditional.
   */
  protected function stepCondition(mixed $step): ?callable {
    if (is_callable($step) || !is_array($step)) {
      return NULL;
    }

    $condition = $step['__condition'] ?? NULL;

    return is_callable($condition) ? $condition : NULL;
  }

  /**
   * Checks whether a flow step is a section heading.
   *
   * @param mixed $step
   *   A flow step.
   *
   * @return bool
   *   TRUE when the step was declared with Prompty::section().
   */
  protected function isSection(mixed $step): bool {
    return is_array($step) && array_key_exists('__section', $step);
  }

  /**
   * Checks whether a flow step is a note.
   *
   * @param mixed $step
   *   A flow step.
   *
   * @return bool
   *   TRUE when the step was declared with Prompty::note().
   */
  protected function isNote(mixed $step): bool {
    return is_array($step) && array_key_exists('__note', $step);
  }

  /**
   * Checks whether any of the given steps would render against the results.
   *
   * @param array<array-key, mixed> $steps
   *   Flow steps to test.
   * @param array<string, mixed> $results
   *   The answers each condition is tested against.
   *
   * @return bool
   *   TRUE when at least 1 step is unconditional or its condition passes.
   */
  protected function isAnyStepVisible(array $steps, array $results): bool {
    foreach ($steps as $step) {
      $condition = $this->stepCondition($step);

      if ($condition === NULL || $condition($results)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Counts the steps that are due against the current results.
   *
   * @param array<array-key, mixed> $steps
   *   Flow steps still to come at 1 depth.
   *
   * @return int
   *   The number of steps that are not sections or notes and whose condition
   *   is absent or passes.
   */
  protected function dueStepCount(array $steps): int {
    $count = 0;

    foreach ($steps as $step) {
      if ($this->isSection($step) || $this->isNote($step)) {
        continue;
      }

      $condition = $this->stepCondition($step);

      if ($condition === NULL || $this->predictCondition($condition)) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Tests a condition ahead of its step against the current results.
   *
   * The condition may read an answer that has not been collected yet, so a
   * warning or an exception counts as a failed condition. The step's own
   * evaluation in walkFlow() runs unguarded.
   *
   * @param callable $condition
   *   The step condition.
   *
   * @return bool
   *   TRUE when the condition returns a truthy value without an error.
   */
  protected function predictCondition(callable $condition): bool {
    set_error_handler(static function (int $severity, string $message): never {
      throw new \ErrorException($message, 0, $severity);
    });

    try {
      return (bool) $condition($this->results);
    }
    catch (\Throwable) {
      return FALSE;
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * Marks whether a depth level still has siblings to come.
   *
   * @param int $depth
   *   The nesting depth to mark. Depth 0 has no connector.
   * @param bool $is_last
   *   Whether the step being rendered is the last visible one at that depth.
   */
  protected function markOpen(int $depth, bool $is_last): void {
    if ($depth < 1) {
      return;
    }

    if ($is_last) {
      unset($this->open[$depth]);

      return;
    }

    $this->open[$depth] = TRUE;
  }

  /**
   * Settles the tree connector once the step's answer is known.
   *
   * Later siblings' conditions run against the results each will see when
   * reached, so none reads an answer that has not been collected.
   *
   * walkFlow() settles again from the stored value, so the next render gets
   * the exact state when that value differs from the drawn one. The widget's
   * own lines keep the state it settled, because they were drawn first.
   *
   * @param mixed $value
   *   The answer the step resolved to.
   * @param array<int, bool> $open
   *   The state to return when no flow step is being rendered.
   *
   * @return array<int, bool>
   *   The connector state for the step's own lines.
   */
  protected function settleOpen(mixed $value, array $open): array {
    $pending = $this->pending;

    if ($pending === NULL) {
      return $open;
    }

    $results = $this->results;
    $results[(string) $pending['key']] = $value;

    $this->markOpen($pending['depth'], !$this->isAnyStepVisible($pending['rest'], $results));

    return $this->open;
  }

  /**
   * Draws a section heading when its group has a step that will render.
   *
   * A group step's condition can only read an answer from the group once an
   * earlier group step renders, and that step already makes the group
   * visible. Testing the group against the current results is therefore
   * exact.
   *
   * @param array<string, mixed> $section
   *   The section step array.
   * @param array<array-key, mixed> $rest
   *   The steps after the section.
   */
  protected function drawSection(array $section, array $rest): void {
    $condition = $this->stepCondition($section);

    if ($condition !== NULL && !$condition($this->results)) {
      return;
    }

    $group = [];

    foreach ($rest as $key => $step) {
      if ($this->isSection($step)) {
        break;
      }

      $group[$key] = $step;
    }

    if (!$this->isAnyStepVisible($group, $this->results)) {
      return;
    }

    /** @var string $title */
    $title = $section['__section'];
    /** @var string $description */
    $description = $section['__description'] ?? '';
    $this->printLines($this->renderSection($title, $description, !$this->hasDrawn));
    $this->hasDrawn = TRUE;
  }

  /**
   * Draws a flow note at its depth in the tree, unless its condition fails.
   *
   * @param array<string, mixed> $note
   *   The note step array.
   * @param array<array-key, mixed> $rest
   *   The steps after the note at the same depth.
   * @param int $depth
   *   The nesting depth of the note.
   */
  protected function drawNote(array $note, array $rest, int $depth): void {
    $condition = $this->stepCondition($note);

    if ($condition !== NULL && !$condition($this->results)) {
      return;
    }

    // The answers so far are known, so the connector settles before drawing.
    $this->markOpen($depth, !$this->isAnyStepVisible($rest, $this->results));

    /** @var string $message */
    $message = $note['__note'];
    /** @var string $type */
    $type = $note['__type'];
    $this->printLines($this->renderNote($message, $type, $depth, $this->open));
    $this->hasDrawn = TRUE;
  }

  /**
   * Recursively walks flow steps, executing each widget and collecting results.
   *
   * @param array<string, mixed> $steps
   *   Flow steps (closures or step arrays).
   * @param int $depth
   *   The current nesting depth.
   * @param array<string, mixed> $options
   *   Flow options; only the 'numbering' key is read.
   * @param string $number_prefix
   *   Dot-separated prefix for hierarchical step numbering.
   *
   * @return string
   *   'done' when every step completed, 'cancelled' when the user cancelled,
   *   or 'back' when the user stepped back and the walk has to restart.
   */
  protected function walkFlow(array $steps, int $depth, array $options, string $number_prefix): string {
    $step_number = 0;
    $index = 0;

    foreach ($steps as $key => $step) {
      $index++;

      if ($this->isSection($step)) {
        /** @var array<string, mixed> $step */
        $this->drawSection($step, array_slice($steps, $index));

        continue;
      }

      if ($this->isNote($step)) {
        /** @var array<string, mixed> $step */
        $this->drawNote($step, array_slice($steps, $index), $depth);

        continue;
      }

      if (is_callable($step)) {
        $call = $step;
        $children = [];
      }
      else {
        /** @var array<string, mixed> $step */
        $call = $step['__call'];
        /** @var array<string, mixed> $children */
        $children = is_array($step['__children'] ?? NULL) ? $step['__children'] : [];
      }

      $condition = $this->stepCondition($step);

      if ($condition !== NULL && !$condition($this->results)) {
        continue;
      }

      $step_number++;

      $rest = array_slice($steps, $index);

      // A later sibling's condition cannot be decided until this step is
      // answered, so every remaining step counts as one that may render. The
      // widget settles the connector from its answer before it draws.
      $this->markOpen($depth, $rest === []);
      $this->pending = ['key' => $key, 'depth' => $depth, 'rest' => $rest];

      $number = $number_prefix !== '' ? $number_prefix . '.' . $step_number : (string) $step_number;
      $total = $depth === 0 && ($options['numbering'] ?? FALSE) === 'total' ? $step_number + $this->dueStepCount($rest) : NULL;
      $env_value = getenv($this->cfgEnvPrefix . strtoupper((string) $key));

      $ctx = [
        'depth' => $depth,
        'is_last' => $rest === [],
        'open' => $this->open,
        'results' => $this->results,
        'number' => ($options['numbering'] ?? FALSE) ? $number : NULL,
        'total' => $total,
        'discovered' => $env_value !== FALSE ? $env_value : NULL,
        'truthy' => $this->cfgTruthy,
        'falsy' => $this->cfgFalsy,
      ];

      if (array_key_exists($key, $this->inputs)) {
        $ctx['previous'] = $this->inputs[$key];
      }

      // A step before the reopened one takes its previous answer without
      // being drawn or built again. A step with no previous answer, which a
      // condition that changes on its own can produce, ends the replay.
      $is_replayed = $this->replay !== NULL && $this->replay !== (string) $key && array_key_exists($key, $this->previous);

      if (!$is_replayed) {
        $this->replay = NULL;
        $this->journal[] = ['key' => (string) $key, 'depth' => $depth, 'rows' => $this->rows, 'asked' => FALSE];
      }

      /** @var callable $call */
      $value = $is_replayed ? $this->previous[$key] : $call($ctx);

      if ($value === NULL) {
        $this->pending = NULL;

        if ($this->stepBack !== NULL) {
          $this->stepBackTo($this->stepBack);

          return 'back';
        }

        return 'cancelled';
      }

      $this->results[$key] = $value;
      $this->hasDrawn = TRUE;

      // A widget settles the connector before it draws; this settles a step
      // whose widget did not, so its children still see the exact state.
      $this->settleOpen($value, $this->open);
      $this->pending = NULL;

      if ($this->isAnyStepVisible($children, $this->results)) {
        $child_depth = $depth + 1;
        $sep = $this->bar() . $this->labelPrefix($child_depth, $this->open) . $this->bar();
        $this->printLines([$sep]);

        $outcome = $this->walkFlow($children, $child_depth, $options, $number);

        if ($outcome !== 'done') {
          return $outcome;
        }
      }
    }

    return 'done';
  }

}
