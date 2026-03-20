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
 */
class Prompty {

  /**
   * Singleton instance.
   *
   * @var static|null
   */
  protected static ?self $instance = NULL;

  /**
   * Stored TTY settings for standalone widget teardown.
   */
  protected ?string $prevTty = NULL;

  /**
   * Whether a flow is currently being defined/executed.
   */
  protected static bool $inFlow = FALSE;

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
    'radio_on' => '●',
    'radio_off' => '○',
    'check_on' => '◼',
    'check_off' => '◻',
    'hint_arrow' => '↳',
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
    'radio_on' => '(*)',
    'radio_off' => '( )',
    'check_on' => '[x]',
    'check_off' => '[ ]',
    'hint_arrow' => '-->',
  ];

  /**
   * Active symbol set (resolved from unicode setting).
   *
   * @var array<string, string>
   */
  protected array $cfgSymbols = [];

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
  ];

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
  ];

  /**
   * Whether to use unicode symbols (NULL = auto-detect).
   */
  protected ?bool $cfgUnicode = NULL;

  /**
   * Whether to emit ANSI color codes (NULL = auto-detect).
   */
  protected ?bool $cfgAnsi = NULL;

  /**
   * Default ANSI color escape sequences (used to restore after suppression).
   *
   * @var array<string, string>
   */
  protected array $cfgColorsDefault = [];

  /**
   * Environment variable prefix for auto-discovery.
   */
  protected string $cfgEnvPrefix = 'PROMPTY_';

  /**
   * Values treated as TRUE for env variable coercion.
   *
   * @var list<string>
   */
  protected array $cfgTruthy = ['1', 'true', 'yes'];

  /**
   * Values treated as FALSE for env variable coercion.
   *
   * @var list<string>
   */
  protected array $cfgFalsy = ['0', 'false', 'no'];

  /**
   * Constructs a Prompty instance.
   */
  protected function __construct() {
    // Resolve Unicode: TRUE/FALSE to force, NULL to auto-detect from locale.
    if ($this->cfgUnicode === NULL) {
      $lang = getenv('LANG') ?: getenv('LC_ALL') ?: getenv('LC_CTYPE') ?: setlocale(LC_CTYPE, '0') ?: '';
      $this->cfgUnicode = stripos($lang, 'utf') !== FALSE;
    }

    $this->cfgSymbols = $this->cfgUnicode ? $this->cfgSymbolsUnicode : $this->cfgSymbolsAscii;

    // Resolve ANSI: TRUE/FALSE to force, NULL to auto-detect.
    $this->cfgColorsDefault = $this->cfgColors;
    if ($this->cfgAnsi === NULL) {
      $no_color = getenv('NO_COLOR');
      $this->cfgAnsi = ($no_color !== FALSE && $no_color !== '') || getenv('TERM') === 'dumb' ? FALSE : TRUE;
    }

    if ($this->cfgAnsi === FALSE) {
      $this->cfgColors = array_fill_keys(array_keys($this->cfgColors), '');
    }
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
      'unicode' => $p->cfgUnicode,
      'ansi' => $p->cfgAnsi,
      'env_prefix' => $p->cfgEnvPrefix,
      'truthy' => $p->cfgTruthy,
      'falsy' => $p->cfgFalsy,
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
   * token is replaced with the actual tag via sed.
   *
   * @return string
   *   The version string.
   */
  public static function version(): string {
    return str_starts_with('__PROMPTY_VERSION__', '__') ? 'development' : '__PROMPTY_VERSION__';
  }

  /**
   * Configure the singleton instance.
   *
   * Creates the singleton if it does not exist yet. Call before any widgets
   * or flows to customise symbols, colors, env prefix, truthy/falsy values.
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
   *   Force unicode (TRUE), ASCII (FALSE), or auto-detect (NULL).
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE), suppress (FALSE), or auto-detect (NULL).
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   */
  public static function configure(?array $symbols_unicode = NULL, ?array $symbols_ascii = NULL, ?array $colors = NULL, ?array $spacing = NULL, ?array $labels = NULL, ?bool $unicode = NULL, ?bool $ansi = NULL, ?string $env_prefix = NULL, ?array $truthy = NULL, ?array $falsy = NULL): void {
    $p = static::instance();
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
      $p->cfgUnicode = $unicode;
    }
    if ($ansi !== NULL) {
      $p->cfgAnsi = $ansi;
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
    $p->cfgSymbols = $p->cfgUnicode ? $p->cfgSymbolsUnicode : $p->cfgSymbolsAscii;
    $p->cfgColors = $p->cfgAnsi ? $p->cfgColorsDefault : array_fill_keys(array_keys($p->cfgColorsDefault), '');
  }

  /**
   * Run a flow.
   *
   * Accepts a callable that returns the steps array. The callable is invoked
   * after $inFlow is set, so widgets inside it return closures for deferred
   * execution instead of running immediately.
   *
   * @param callable $steps
   *   Callable that returns the associative steps array.
   * @param string|callable|null $intro
   *   Intro message or callable to render before the first step.
   * @param string|callable|null $outro
   *   Outro message or callable to render after the last step.
   * @param string|callable|null $cancelled
   *   Message or callable to render when the flow is cancelled.
   * @param bool $numbering
   *   Whether to number steps.
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
   *   Force unicode (TRUE), ASCII (FALSE), or auto-detect (NULL).
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE), suppress (FALSE), or auto-detect (NULL).
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   *
   * @return array<string, string|bool|int|array<string,string>>|null
   *   Collected results or NULL if cancelled.
   */
  public static function flow(callable $steps, string|callable|null $intro = NULL, string|callable|null $outro = NULL, string|callable|null $cancelled = NULL, bool $numbering = FALSE, ?array $symbols_unicode = NULL, ?array $symbols_ascii = NULL, ?array $colors = NULL, ?array $spacing = NULL, ?array $labels = NULL, ?bool $unicode = NULL, ?bool $ansi = NULL, ?string $env_prefix = NULL, ?array $truthy = NULL, ?array $falsy = NULL): ?array {
    // Apply config overrides if any are provided.
    if ($symbols_unicode !== NULL || $symbols_ascii !== NULL || $colors !== NULL || $spacing !== NULL || $labels !== NULL || $unicode !== NULL || $ansi !== NULL || $env_prefix !== NULL || $truthy !== NULL || $falsy !== NULL) {
      static::configure($symbols_unicode, $symbols_ascii, $colors, $spacing, $labels, $unicode, $ansi, $env_prefix, $truthy, $falsy);
    }
    $p = static::instance();
    $p->results = [];
    static::$inFlow = TRUE;

    // Evaluate the steps callable now that $inFlow is TRUE.
    $steps = $steps();

    $flow_opts = [
      'numbering' => $numbering,
    ];

    // Save current TTY settings and enable raw mode for keypress reading.
    // Returns NULL when no TTY exists (e.g., piped input), allowing the flow
    // to run with env/discovered values without terminal control.
    // Skip TTY setup when an input stream is injected (test mode).
    $prev_tty = $p->input === NULL ? (shell_exec('stty -g 2>/dev/null') ?: NULL) : NULL;

    if ($prev_tty !== NULL) {
      $prev_tty = trim($prev_tty);
      // Disable echo (-echo) and line buffering (-icanon) so each keypress
      // is available immediately without waiting for Enter.
      shell_exec('stty -echo -icanon min 1 time 0 2>/dev/null');

      // Safety net: restore terminal even on fatal errors or exceptions.
      register_shutdown_function(function () use ($p, $prev_tty): void {
        // @codeCoverageIgnoreStart
        $p->restoreTty($prev_tty);
        $p->showCursor();
        // @codeCoverageIgnoreEnd
      });

      // Hide cursor.
      echo "\033[?25l";
    }

    if ($intro !== NULL) {
      is_callable($intro) ? $intro($p->results) : $p->printLines($p->renderIntro($intro));
    }

    // Walk all steps recursively, starting at depth 0 with no number prefix.
    $outcome = $p->flowWalk($steps, 0, $flow_opts, '');

    // A widget returning NULL means the user cancelled (ctrl-c / escape).
    if ($outcome === FALSE) {
      if ($cancelled !== NULL) {
        is_callable($cancelled) ? $cancelled($p->results) : $p->printLines($p->renderOutro($cancelled));
      }

      if ($prev_tty !== NULL) {
        // @codeCoverageIgnoreStart
        $p->restoreTty($prev_tty);
        $p->showCursor();
        // @codeCoverageIgnoreEnd
      }

      static::$inFlow = FALSE;
      return NULL;
    }

    if ($outro !== NULL) {
      is_callable($outro) ? $outro($p->results) : $p->printLines($p->renderOutro($outro));
    }

    if ($prev_tty !== NULL) {
      // @codeCoverageIgnoreStart
      $p->restoreTty($prev_tty);
      $p->showCursor();
      // @codeCoverageIgnoreEnd
    }

    static::$inFlow = FALSE;
    return $p->results;
  }

  /**
   * Renders a text input widget, returning the user's input string.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param string $placeholder
   *   Placeholder text shown when the input is empty.
   * @param string $description
   *   Optional description rendered below the label.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   A closure in flow mode, or the entered string in standalone mode.
   */
  public static function text(string $label, string $placeholder = '', string $description = '', mixed $discovered = NULL, ?callable $condition = NULL, array $children = [], ?array $ctx = NULL): \Closure|array|string|null {
    // Flow mode: return closure for deferred execution.
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|string|null => static::text($label, placeholder: $placeholder, description: $description, discovered: $discovered, ctx: $ctx);
      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }
      return $call;
    }

    // Execution mode.
    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $standalone = !static::$inFlow;

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var bool $is_last */
    $is_last = $ctx['is_last'] ?? FALSE;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    // Skip interactive prompt if a value was provided directly or via env var.
    $resolved = $discovered ?? $ctx['env_value'] ?? NULL;

    if ($resolved !== NULL) {
      /** @var int|float|string|bool $resolved */
      $display = (string) $resolved;
      $p->printLines($p->renderCompleted($label, $display, $depth, $is_last, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }
      return $display;
    }

    // Render the active text input state for the current value.
    $render_active = function (string $value) use ($p, $label, $placeholder, $description, $depth, $open): array {
      $cursor = $p->color('█', 'cyan');
      $display = $value === '' ? $p->color($placeholder, 'gray') . $cursor : $p->color($value, 'white') . $cursor;

      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $display;
        $lines[] = $p->bar();
        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines[] = $p->bar() . $body_prefix . $display;
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    // Interactive input loop.
    $value = '';
    $line_count = $p->printLines($render_active($value));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $value, $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return NULL;
      }

      if ($key === 'enter') {
        $display = $value !== '' ? $value : $placeholder;
        $p->redraw($line_count, $p->renderCompleted($label, $display, $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return $display;
      }

      if ($key === 'backspace') {
        if ($value !== '') {
          $value = mb_substr($value, 0, -1);
        }
      }
      elseif ($key === 'space') {
        $value .= ' ';
      }
      // Only accept printable ASCII characters (ord >= 32).
      elseif (mb_strlen($key) === 1 && ord($key) >= 32) {
        $value .= $key;
      }

      $line_count = $p->redraw($line_count, $render_active($value));
    }
  }

  /**
   * Renders a single-select widget, returning the chosen option key.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   A closure in flow mode, or the selected option key in standalone mode.
   */
  public static function select(string $label, array $options = [], string $description = '', array $hints = [], mixed $discovered = NULL, ?callable $condition = NULL, array $children = [], ?array $ctx = NULL): \Closure|array|string|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|string|null => static::select($label, options: $options, description: $description, hints: $hints, discovered: $discovered, ctx: $ctx);
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
    $standalone = !static::$inFlow;

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var bool $is_last */
    $is_last = $ctx['is_last'] ?? FALSE;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = array_keys($options);
    $option_labels = array_values($options);
    $ordered_hints = array_map(fn(int|string $key) => $hints[$key] ?? '', $option_keys);

    // Resolve discovered or environment value.
    $resolved = $discovered ?? $ctx['env_value'] ?? NULL;

    if ($resolved !== NULL) {
      /** @var int|float|string|bool $resolved */
      $resolved_str = (string) $resolved;
      $display = $options[$resolved_str] ?? $resolved_str;
      $p->printLines($p->renderCompleted($label, $display, $depth, $is_last, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }
      return $resolved_str;
    }

    // Render the active select state for the current focused index.
    $render_active = function (int $focused) use ($p, $label, $option_labels, $description, $ordered_hints, $depth, $open): array {
      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($option_labels as $index => $option) {
          if ($index === $focused) {
            $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $option;
            if (($ordered_hints[$index] ?? '') !== '') {
              $lines = array_merge($lines, $p->renderHint($ordered_hints[$index]));
            }
          }
          else {
            $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($option, 'dim');
          }
        }

        $lines[] = $p->bar();
        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($option_labels as $index => $option) {
        if ($index === $focused) {
          $lines[] = $p->bar() . $body_prefix . $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $option;
          if (($ordered_hints[$index] ?? '') !== '') {
            $lines = array_merge($lines, $p->renderHint($ordered_hints[$index], $depth, $open));
          }
        }
        else {
          $lines[] = $p->bar() . $body_prefix . $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($option, 'dim');
        }
      }

      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    // Interactive selection loop.
    $focused = 0;
    $line_count = $p->printLines($render_active($focused));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $option_labels[$focused], $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return NULL;
      }

      if ($key === 'enter') {
        $p->redraw($line_count, $p->renderCompleted($label, $option_labels[$focused], $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return $option_keys[$focused];
      }

      if ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      $line_count = $p->redraw($line_count, $render_active($focused));
    }
  }

  /**
   * Renders a multi-select widget, returning an array of chosen option keys.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<string, string> $options
   *   Map of option key to display label.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<string, string> $hints
   *   Map of option key to hint text.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|list<string>|null
   *   A closure in flow mode, or the array of selected keys in standalone mode.
   */
  public static function multiselect(string $label, array $options = [], string $description = '', array $hints = [], mixed $discovered = NULL, ?callable $condition = NULL, array $children = [], ?array $ctx = NULL): \Closure|array|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|null => static::multiselect($label, options: $options, description: $description, hints: $hints, discovered: $discovered, ctx: $ctx);
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
    $standalone = !static::$inFlow;

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var bool $is_last */
    $is_last = $ctx['is_last'] ?? FALSE;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = array_keys($options);
    $option_labels = array_values($options);
    $ordered_hints = array_map(fn(int|string $key) => $hints[$key] ?? '', $option_keys);

    // Env values for multiselect arrive comma-separated (e.g., "a,b,c").
    $env_value = $ctx['env_value'] ?? NULL;
    /** @var int|float|string|bool|null $env_value */
    $resolved = $discovered ?? ($env_value !== NULL ? array_map(trim(...), explode(',', (string) $env_value)) : NULL);

    if ($resolved !== NULL) {
      $resolved_array = is_array($resolved) ? $resolved : [$resolved];
      $display = $resolved_array !== [] ? implode(', ', array_map(fn($key) => $options[is_string($key) ? $key : ''] ?? (is_string($key) ? $key : ''), $resolved_array)) : $p->cfgLabels['none'];
      $p->printLines($p->renderCompleted($label, $display, $depth, $is_last, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }
      return $resolved_array;
    }

    // Render the active multiselect state for focused index and checked items.
    $render_active = function (int $focused, array $checked) use ($p, $label, $option_labels, $description, $ordered_hints, $depth, $open): array {
      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($option_labels as $index => $option) {
          $is_checked = $checked[$index] ?? FALSE;

          if ($index === $focused) {
            $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], 'green') . ' ' . $option;
            if (($ordered_hints[$index] ?? '') !== '') {
              $lines = array_merge($lines, $p->renderHint($ordered_hints[$index]));
            }
          }
          else {
            $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_checked ? 'green' : 'dim') . ' ' . ($is_checked ? $option : $p->color($option, 'dim'));
          }
        }

        $lines[] = $p->bar();
        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($option_labels as $index => $option) {
        $is_checked = $checked[$index] ?? FALSE;

        if ($index === $focused) {
          $lines[] = $p->bar() . $body_prefix . $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], 'green') . ' ' . $option;
          if (($ordered_hints[$index] ?? '') !== '') {
            $lines = array_merge($lines, $p->renderHint($ordered_hints[$index], $depth, $open));
          }
        }
        else {
          $lines[] = $p->bar() . $body_prefix . $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_checked ? 'green' : 'dim') . ' ' . ($is_checked ? $option : $p->color($option, 'dim'));
        }
      }

      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    // Interactive multi-selection loop.
    $focused = 0;
    $checked = array_fill(0, count($option_labels), FALSE);
    $line_count = $p->printLines($render_active($focused, $checked));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, '', $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return NULL;
      }

      if ($key === 'enter') {
        $selected_keys = [];
        $selected_labels = [];

        foreach ($option_labels as $index => $option_label) {
          if ($checked[$index]) {
            $selected_keys[] = $option_keys[$index];
            $selected_labels[] = $option_label;
          }
        }

        $p->redraw($line_count, $p->renderCompleted($label, $selected_labels !== [] ? implode(', ', $selected_labels) : $p->cfgLabels['none'], $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return $selected_keys;
      }

      if ($key === 'space') {
        $checked[$focused] = !$checked[$focused];
      }
      elseif ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      $line_count = $p->redraw($line_count, $render_active($focused, $checked));
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
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|bool|null
   *   A closure in flow mode, or the boolean result in standalone mode.
   */
  public static function confirm(string $label, bool $default = TRUE, string $description = '', mixed $discovered = NULL, ?callable $condition = NULL, array $children = [], ?array $ctx = NULL): \Closure|array|bool|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|bool|\Closure|null => static::confirm($label, default: $default, description: $description, discovered: $discovered, ctx: $ctx);
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
    $standalone = !static::$inFlow;

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var bool $is_last */
    $is_last = $ctx['is_last'] ?? FALSE;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);
    /** @var array<int, string> $truthy */
    $truthy = $ctx['truthy'] ?? ['1', 'true', 'yes'];
    /** @var array<int, string> $falsy */
    $falsy = $ctx['falsy'] ?? ['0', 'false', 'no'];

    // Env values for confirm need truthy/falsy coercion (e.g., "yes" → TRUE).
    $env_value_confirm = $ctx['env_value'] ?? NULL;
    /** @var int|float|string|bool|null $env_value_confirm */
    if ($discovered === NULL && $env_value_confirm !== NULL) {
      $env_lower = strtolower((string) $env_value_confirm);
      if (in_array($env_lower, $truthy, TRUE)) {
        $discovered = TRUE;
      }
      elseif (in_array($env_lower, $falsy, TRUE)) {
        $discovered = FALSE;
      }
    }

    if ($discovered !== NULL) {
      $p->printLines($p->renderCompleted($label, $discovered ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $is_last, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }
      return (bool) $discovered;
    }

    // Render the active confirm state for the current yes/no focus.
    $render_active = function (bool $focused_yes) use ($p, $label, $description, $depth, $open): array {
      $options_display = $focused_yes
        ? $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->cfgLabels['yes'] . ' ' . $p->color($p->cfgLabels['separator'], 'dim') . ' ' . $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->cfgLabels['no'], 'dim')
        : $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->cfgLabels['yes'], 'dim') . ' ' . $p->color($p->cfgLabels['separator'], 'dim') . ' ' . $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->cfgLabels['no'];

      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $options_display;
        $lines[] = $p->bar();
        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines[] = $p->bar() . $body_prefix . $options_display;
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    // Interactive confirm loop.
    $yes = $default;
    $line_count = $p->printLines($render_active($yes));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $yes ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return NULL;
      }

      if ($key === 'enter') {
        $p->redraw($line_count, $p->renderCompleted($label, $yes ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $is_last, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }
        return $yes;
      }

      if (in_array($key, ['left', 'right', 'up', 'down', 'tab'], TRUE)) {
        $yes = !$yes;
      }
      elseif ($key === 'y' || $key === 'Y') {
        $yes = TRUE;
      }
      elseif ($key === 'n' || $key === 'N') {
        $yes = FALSE;
      }

      $line_count = $p->redraw($line_count, $render_active($yes));
    }
  }

  /**
   * Prints an intro banner at the start of a flow.
   */
  public static function intro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderIntro($message));
  }

  /**
   * Prints an outro banner at the end of a flow.
   */
  public static function outro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderOutro($message));
  }

  /**
   * Prints an array of lines and returns the line count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   */
  public static function output(array $lines): int {
    return static::instance()->printLines($lines);
  }

  /**
   * Wraps text in an ANSI color sequence.
   */
  protected function color(string $text, string $color): string {
    return isset($this->cfgColors[$color]) ? $this->cfgColors[$color] . $text . $this->cfgColors['reset'] : $text;
  }

  /**
   * Returns the styled vertical bar character for the tree connector.
   */
  protected function bar(): string {
    return $this->color($this->cfgSymbols['bar'], 'gray');
  }

  /**
   * Restores TTY settings to the previously saved state.
   */
  protected function restoreTty(string $prev): void {
    shell_exec('stty ' . $prev . ' 2>/dev/null');
  }

  /**
   * Reads a single keypress from the input stream and returns its name.
   */
  protected function readKey(): string {
    $stream = $this->input ?? STDIN;
    $char = fread($stream, 1);

    if ($char === FALSE || $char === '') {
      return '';
    }

    return match ($char) {
      "\x03" => 'ctrl-c',
      "\n", "\r" => 'enter',
      "\x7f", "\x08" => 'backspace',
      "\t" => 'tab',
      ' ' => 'space',
      "\x1b" => match (fread($stream, 2)) {
        '[A' => 'up',
        '[B' => 'down',
        '[C' => 'right',
        '[D' => 'left',
        default => 'escape',
      },
      default => $char,
    };
  }

  /**
   * Shows the terminal cursor.
   */
  protected function showCursor(): void {
    echo "\033[?25h";
  }

  /**
   * Set up TTY for standalone widget execution.
   */
  protected function setupTty(): void {
    $this->prevTty = $this->input === NULL ? (shell_exec('stty -g 2>/dev/null') ?: NULL) : NULL;

    if ($this->prevTty !== NULL) {
      $this->prevTty = trim($this->prevTty);
      shell_exec('stty -echo -icanon min 1 time 0 2>/dev/null');
      echo "\033[?25l";
    }
  }

  /**
   * Tear down TTY after standalone widget execution.
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
   * Prints an array of lines to stdout and returns the line count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   */
  protected function printLines(array $lines): int {
    echo implode(PHP_EOL, $lines) . PHP_EOL;
    return count($lines);
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
    return [
      '',
      $this->color($this->cfgSymbols['intro'], 'gray') . $this->cfgSpacing['indent'] . $this->color($message, 'bold'),
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
    return [
      $this->bar(),
      $this->color($this->cfgSymbols['outro'], 'gray') . $this->cfgSpacing['indent'] . $this->color($message, 'green'),
      '',
    ];
  }

  /**
   * Clears previously printed lines and redraws with new content.
   *
   * @param int $prev_line_count
   *   The number of lines previously printed to clear.
   * @param array<int, string> $lines
   *   Lines to redraw.
   *
   * @return int
   *   The number of lines printed.
   */
  protected function redraw(int $prev_line_count, array $lines): int {
    if ($prev_line_count > 0) {
      echo "\033[{$prev_line_count}A\r\033[J";
    }
    return $this->printLines($lines);
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
    $body_prefix = $depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent'];

    $lines = array_map(
      fn(string $text_line): string => $this->bar() . $body_prefix . $this->color($text_line, 'dim_italic'),
      explode("\n", $description),
    );

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
    $body_prefix = $depth > 0 ? $this->bodyPrefix($depth, $open) : '';
    $hint_lines = explode("\n", $hint);

    return array_map(
      fn($text_line, $index): string => $this->bar() . $body_prefix . ($index === 0
          ? $this->cfgSpacing['hint_indent'] . $this->color($this->cfgSymbols['hint_arrow'], 'dim') . ' ' . $this->color($text_line, 'dim_italic')
          : $this->cfgSpacing['hint_cont'] . $this->color($text_line, 'dim_italic')
        ),
      $hint_lines,
      array_keys($hint_lines),
    );
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
    if (isset($ctx['number'])) {
      /** @var string $number */
      $number = $ctx['number'];
      return $label . ' ' . $this->color('(' . $number . ')', 'dim');
    }
    return $label;
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
   * @param bool $is_last
   *   Whether this is the last widget at its depth level.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered completed-state lines.
   */
  protected function renderCompleted(string $label, string $value, int $depth = 0, bool $is_last = FALSE, array $open = []): array {

    if ($depth === 0) {
      return [
        $this->color($this->cfgSymbols['completed'], 'cyan') . $this->cfgSpacing['indent'] . $label,
        $this->bar() . $this->cfgSpacing['indent'] . $this->color($value, 'dim'),
        $this->bar(),
      ];
    }

    $label_prefix = $this->bar() . $this->labelPrefix($depth, $open);
    $body_prefix = $this->bodyPrefix($depth, $open);

    return [
      $label_prefix . $this->color($this->cfgSymbols['completed'], 'cyan') . $this->cfgSpacing['indent'] . $label,
      $this->bar() . $body_prefix . $this->color($value, 'dim'),
      $this->bar() . $body_prefix,
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
   * @param bool $is_last
   *   Whether this is the last widget at its depth level.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered cancelled-state lines.
   */
  protected function renderCancelled(string $label, string $value, int $depth = 0, bool $is_last = FALSE, array $open = []): array {

    if ($depth === 0) {
      return [
        $this->color($this->cfgSymbols['active'], 'red') . $this->cfgSpacing['indent'] . $label,
        $this->bar() . $this->cfgSpacing['indent'] . $this->color($value, 'dim') . $this->color(' ' . $this->cfgLabels['cancelled'], 'red'),
        $this->bar(),
      ];
    }

    $label_prefix = $this->bar() . $this->labelPrefix($depth, $open);
    $body_prefix = $this->bodyPrefix($depth, $open);

    return [
      $label_prefix . $this->color($this->cfgSymbols['active'], 'red') . $this->cfgSpacing['indent'] . $label,
      $this->bar() . $body_prefix . $this->color($value, 'dim') . $this->color(' ' . $this->cfgLabels['cancelled'], 'red'),
      $this->bar() . $body_prefix,
    ];
  }

  /**
   * Recursively walks flow steps, executing each widget and collecting results.
   *
   * @param array<string, mixed> $steps
   *   Flow steps (closures or step arrays).
   * @param int $depth
   *   The current nesting depth.
   * @param array<string, mixed> $options
   *   Flow options (numbering, env_prefix, truthy, falsy).
   * @param string $number_prefix
   *   Dot-separated prefix for hierarchical step numbering.
   *
   * @return bool
   *   TRUE if all steps completed, FALSE if the user cancelled.
   */
  protected function flowWalk(array $steps, int $depth, array $options, string $number_prefix): bool {
    $step_number = 0;

    foreach ($steps as $key => $step) {
      if (is_callable($step)) {
        $callback = $step;
        $condition = NULL;
        $children = [];
      }
      else {
        /** @var array<string, mixed> $step */
        $callback = $step['__call'];
        $condition = isset($step['__condition']) && is_callable($step['__condition']) ? $step['__condition'] : NULL;
        /** @var array<string, mixed> $children */
        $children = is_array($step['__children'] ?? NULL) ? $step['__children'] : [];
      }

      if ($condition !== NULL && !$condition($this->results)) {
        continue;
      }

      $step_number++;

      // Determine if this is the last visible child at this depth.
      $has_next_sibling = FALSE;
      $found_current = FALSE;

      foreach ($steps as $sibling_key => $sibling_step) {
        if (!$found_current) {
          if ($sibling_key === $key) {
            $found_current = TRUE;
          }
          continue;
        }

        if (is_callable($sibling_step)) {
          $has_next_sibling = TRUE;
          break;
        }

        /** @var array<string, mixed> $sibling_step */
        $sibling_condition = isset($sibling_step['__condition']) && is_callable($sibling_step['__condition']) ? $sibling_step['__condition'] : NULL;
        if ($sibling_condition === NULL || $sibling_condition($this->results)) {
          $has_next_sibling = TRUE;
          break;
        }
      }

      $is_last = $depth > 0 && !$has_next_sibling;

      // Update tree connector state BEFORE creating ctx so the widget sees
      // the correct open/closed state for its depth level.
      if ($depth > 0) {
        if ($is_last) {
          unset($this->open[$depth]);
        }
        else {
          $this->open[$depth] = TRUE;
        }
      }

      $number = $number_prefix !== '' ? $number_prefix . '.' . $step_number : (string) $step_number;
      $env_value = getenv($this->cfgEnvPrefix . strtoupper((string) $key));

      $ctx = [
        'depth' => $depth,
        'is_last' => $is_last,
        'open' => $this->open,
        'results' => $this->results,
        'number' => ($options['numbering'] ?? FALSE) ? $number : NULL,
        'env_value' => $env_value !== FALSE ? $env_value : NULL,
        'truthy' => $this->cfgTruthy,
        'falsy' => $this->cfgFalsy,
      ];

      /** @var callable $callback */
      $value = $callback($ctx);

      if ($value === NULL) {
        return FALSE;
      }

      $this->results[$key] = $value;

      if ($children !== []) {
        // Only render children separator if a visible child exists.
        $has_visible_child = FALSE;
        foreach ($children as $child) {
          if (is_callable($child)) {
            $child_condition = NULL;
          }
          else {
            /** @var array<string, mixed> $child */
            $child_condition = isset($child['__condition']) && is_callable($child['__condition']) ? $child['__condition'] : NULL;
          }
          if ($child_condition === NULL || $child_condition($this->results)) {
            $has_visible_child = TRUE;
            break;
          }
        }

        if ($has_visible_child) {
          $child_depth = $depth + 1;
          $sep = $this->bar() . $this->labelPrefix($child_depth, $this->open) . $this->bar();
          $this->printLines([$sep]);

          if (!$this->flowWalk($children, $child_depth, $options, $number)) {
            return FALSE;
          }
        }
      }
    }

    return TRUE;
  }

}
